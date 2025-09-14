<?php

/**
 * ================================================================
 * IoT Device Data Receiver & Event Logger
 * Version: 2.3
 * Author: Abhijeet Kumar
 * Last Updated: July 24, 2025
 * 
 * Description:
 * ------------
 * - Receives data from IoT device (current, timestamp, timezone, deviceId)
 * - Stores live pings in a temporary file
 * - If delay between pings > 60s:
 *     - If 2 or more previous pings exist, creates an event
 *     - If only 1 old ping exists, clears it to prevent bad event duration
 * - Calculates average current and discharge (L) during the interval
 * - Appends new ping at the end
 * ================================================================
 */

// ---------------- CONFIGURATION ---------------- //

$deviceIdFile = 'devices.json';             // List of valid device IDs
$dischargeCoefficient = 0.87;               // Water discharge rate (L/sec)
date_default_timezone_set('Asia/Kolkata'); // Fallback timezone


// ---------------- UTILITY FUNCTIONS ---------------- //

/**
 * Clean incoming data to prevent injection/invalid values
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Load JSON content from a file or return empty array
 */
function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

/**
 * Save data array to a JSON file in pretty format
 */
function saveJson($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}


// ---------------- READ INPUT PARAMETERS ---------------- //

$current   = isset($_GET['current'])   ? sanitize($_GET['current'])   : '';
$timestamp = isset($_GET['timestamp']) ? sanitize($_GET['timestamp']) : '';
$deviceId  = isset($_GET['deviceId'])  ? sanitize($_GET['deviceId'])  : '';
$timezone  = isset($_GET['timezone'])  ? sanitize($_GET['timezone'])  : '';

// Validate required parameters
if ($current === '' || $timestamp === '' || $deviceId === '' || $timezone === '') {
    echo "Error: Missing parameters.";
    exit;
}

// Check timestamp format (must match 'Y-m-d H:i:s')
if (!DateTime::createFromFormat('Y-m-d H:i:s', $timestamp)) {
    echo "Error: Invalid timestamp format. Use Y-m-d H:i:s";
    exit;
}

// Validate deviceId against registered devices
$registeredDevices = loadJson($deviceIdFile);
if (!in_array($deviceId, $registeredDevices)) {
    echo "Error: deviceId not registered.";
    exit;
}


// ---------------- DEFINE FILE PATHS ---------------- //

$folder = "device_{$deviceId}";                                // e.g., device_01xd02m25
$tempFile  = "$folder/temp_{$deviceId}.json";                  // Live ping buffer
$eventFile = "$folder/event_{$deviceId}.json";                 // Event history

// Create folder if it doesn't exist
if (!is_dir($folder)) {
    if (!mkdir($folder, 0777, true)) {
        echo "Error: Could not create device folder.";
        exit;
    }
}


// ---------------- LOAD CURRENT FILE DATA ---------------- //

$tempData  = loadJson($tempFile);   // Array of ongoing ping data
$eventData = loadJson($eventFile);  // Array of saved events


// ---------------- CHECK DELAY BETWEEN LAST AND CURRENT PING ---------------- //

$pingDelayExceeded = false;
$tz = new DateTimeZone($timezone);  // Use device's timezone
$currentPingDT = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp, $tz);

// Compare current ping time with the last ping
if (!empty($tempData)) {
    $lastPing = end($tempData)['timestamp'];
    $lastPingDT = DateTime::createFromFormat('Y-m-d H:i:s', $lastPing, $tz);

    if ($currentPingDT && $lastPingDT) {
        $gap = $currentPingDT->getTimestamp() - $lastPingDT->getTimestamp();

        if ($gap > 60) {
            $pingDelayExceeded = true; // Transmission break detected
        }
    }
}


// ---------------- COMPILE EVENT IF SUFFICIENT DATA BLOCKS EXIST ---------------- //

/**
 * Rule:
 * - If delay > 60s and at least 2 pings exist, create event
 * - If delay > 60s and only 1 ping exists, discard it to avoid invalid duration
 */

if ($pingDelayExceeded && count($tempData) >= 2) {
    $startTimestamp = $tempData[0]['timestamp'];
    $endTimestamp   = end($tempData)['timestamp'];
    $duration       = strtotime($endTimestamp) - strtotime($startTimestamp);

    if ($duration > 0) {
        $totalValue     = array_sum(array_column($tempData, 'value'));
        $averageValue   = number_format($totalValue / count($tempData), 2);
        $dischargeLitres = round($duration * $dischargeCoefficient, 2);

        // Create compiled event object
        $eventEntry = [
            "deviceId"         => $deviceId,
            "date"             => $tempData[0]['date'],
            "start_time"       => $startTimestamp,
            "end_time"         => $endTimestamp,
            "duration"         => $duration,
            "average_value"    => $averageValue,
            "discharge_litres" => $dischargeLitres
        ];

        // Append event and save
        $eventData[] = $eventEntry;
        saveJson($eventFile, $eventData);
    }

    // Clear temp data to start a new session
    $tempData = [];
}

// If delay was detected but not enough data for event, discard old stale ping
if ($pingDelayExceeded && count($tempData) < 2) {
    $tempData = []; // Prevent future corrupt event duration
}


// ---------------- APPEND CURRENT PING ---------------- //

$tempData[] = [
    "timestamp" => $timestamp,
    "date"      => substr($timestamp, 0, 10),
    "value"     => (float)$current,
    "timezone"  => $timezone
];

// Save updated tempData buffer
if (!saveJson($tempFile, $tempData)) {
    echo "Error: Could not save ping data.";
    exit;
}


// ---------------- FINAL RESPONSE ---------------- //

echo "Ping received. Status: " . ($pingDelayExceeded ? "Delay detected. Event compiled or stale entry cleared." : "Normal ping.");

?>
