<?php

/**
 * IoT Device Data Receiver & Event Logger
 * Version: 2.2
 * Author: Abhijeet Kumar
 * Updated: July 24, 2025
 * 
 * - Receives: current, timestamp, timezone, deviceId
 * - Checks delay from last ping (strictly sequential)
 * - Logs event if delay > 60s
 * - Calculates discharge in litres
 */

// ---------------- CONFIG ---------------- //

$deviceIdFile = 'devices.json';
$dischargeCoefficient = 0.5; // L/sec
date_default_timezone_set('Asia/Kolkata');

// ---------------- FUNCTIONS ---------------- //

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveJson($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// ---------------- INPUT ---------------- //

$current   = isset($_GET['current'])   ? sanitize($_GET['current'])   : '';
$timestamp = isset($_GET['timestamp']) ? sanitize($_GET['timestamp']) : '';
$deviceId  = isset($_GET['deviceId'])  ? sanitize($_GET['deviceId'])  : '';
$timezone  = isset($_GET['timezone'])  ? sanitize($_GET['timezone'])  : '';

if ($current === '' || $timestamp === '' || $deviceId === '' || $timezone === '') {
    echo "Error: Missing parameters.";
    exit;
}

if (!DateTime::createFromFormat('Y-m-d H:i:s', $timestamp)) {
    echo "Error: Invalid timestamp format. Use Y-m-d H:i:s";
    exit;
}

$registeredDevices = loadJson($deviceIdFile);
if (!in_array($deviceId, $registeredDevices)) {
    echo "Error: deviceId not registered.";
    exit;
}

// ---------------- PATHS ---------------- //

$folder = "device_{$deviceId}";
if (!is_dir($folder)) {
    if (!mkdir($folder, 0777, true)) {
        echo "Error: Could not create device folder.";
        exit;
    }
}

$tempFile  = "$folder/temp_{$deviceId}.json";
$eventFile = "$folder/event_{$deviceId}.json";

$tempData  = loadJson($tempFile);
$eventData = loadJson($eventFile);

// ---------------- CHECK GAP (STRICT) ---------------- //

$pingDelayExceeded = false;

$tz = new DateTimeZone($timezone);
$currentPingDT = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp, $tz);

if (!empty($tempData)) {
    $lastPing = end($tempData)['timestamp'];
    $lastPingDT = DateTime::createFromFormat('Y-m-d H:i:s', $lastPing, $tz);

    if ($currentPingDT && $lastPingDT) {
        $gap = $currentPingDT->getTimestamp() - $lastPingDT->getTimestamp();

        if ($gap > 60) {
            $pingDelayExceeded = true;
        }
    }
}

// ---------------- COMPILE EVENT ---------------- //

// Only compile event if at least two pings exist to calculate valid duration and average
if ($pingDelayExceeded && count($tempData) >= 2) {
    $startTimestamp = $tempData[0]['timestamp'];
    $endTimestamp   = end($tempData)['timestamp'];
    $duration       = strtotime($endTimestamp) - strtotime($startTimestamp);

    if ($duration > 0) {
        $totalValue     = array_sum(array_column($tempData, 'value'));
        $averageValue   = number_format($totalValue / count($tempData), 2);
        $dischargeLitres = round($duration * $dischargeCoefficient, 2);

        $eventEntry = [
            "deviceId"         => $deviceId,
            "date"             => $tempData[0]['date'],
            "start_time"       => $startTimestamp,
            "end_time"         => $endTimestamp,
            "duration"         => $duration,
            "average_value"    => $averageValue,
            "discharge_litres" => $dischargeLitres
        ];

        $eventData[] = $eventEntry;
        saveJson($eventFile, $eventData);
    }

    // Reset tempData and keep only the current (delayed) ping
    $tempData = [];
}


// ---------------- APPEND NEW PING ---------------- //

$tempData[] = [
    "timestamp" => $timestamp,
    "date"      => substr($timestamp, 0, 10),
    "value"     => (float)$current,
    "timezone"  => $timezone
];

if (!saveJson($tempFile, $tempData)) {
    echo "Error: Could not save ping data.";
    exit;
}

// ---------------- DONE ---------------- //

echo "Ping received. Status: " . ($pingDelayExceeded ? "Delay detected. Event compiled." : "Normal ping.");
?>
