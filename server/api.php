<?php

/**
 * IoT Device Data Receiver & Event Generator
 * 
 * Features:
 * 1. Receives current + timestamp + timezone + deviceId
 * 2. Appends to temp_<deviceId>.json
 * 3. If delay > 60s since last ping, logs an event
 * 
 * Author: Abhijeet Kumar
 * Version: 2.0
 * Date: July 2025
 */

// ------------------- CONFIG ------------------- //

$deviceIdFile = 'devices.json';
date_default_timezone_set('Asia/Kolkata');

// ------------------- FUNCTIONS ------------------- //

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveJson($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// ------------------- INPUT ------------------- //

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

// ------------------- SETUP FILE PATHS ------------------- //

$folder = "device_{$deviceId}";
if (!is_dir($folder)) {
    if (!mkdir($folder, 0777, true)) {
        echo "Error: Could not create device folder.";
        exit;
    }
}

$tempFile  = "$folder/temp_{$deviceId}.json";
$eventFile = "$folder/event_{$deviceId}.json";

// ------------------- LOAD DATA ------------------- //

$tempData  = loadJson($tempFile);
$eventData = loadJson($eventFile);

// ------------------- CHECK FOR DELAY ------------------- //

$pingDelayExceeded = false;

if (!empty($tempData)) {
    $lastPing = end($tempData)['timestamp'];
    $lastZone = end($tempData)['timezone'];
    $lastDT   = DateTime::createFromFormat('Y-m-d H:i:s', $lastPing, new DateTimeZone($lastZone));
    $now      = new DateTime('now', new DateTimeZone($lastZone));
    $timeDiff = $now->getTimestamp() - $lastDT->getTimestamp();

    if ($timeDiff > 60) {
        $pingDelayExceeded = true;
    }
}

// ------------------- EVENT CREATION ------------------- //

if ($pingDelayExceeded && count($tempData) > 1) {
    $startTimestamp = $tempData[0]['timestamp'];
    $endTimestamp   = end($tempData)['timestamp'];
    $duration       = strtotime($endTimestamp) - strtotime($startTimestamp);
    $totalValue     = array_sum(array_column($tempData, 'value'));
    $averageValue   = number_format($totalValue / count($tempData), 2);

    $eventEntry = [
        "deviceId"      => $deviceId,
        "date"          => $tempData[0]['date'],
        "start_time"    => $startTimestamp,
        "end_time"      => $endTimestamp,
        "duration"      => $duration,
        "average_value" => $averageValue
    ];

    $eventData[] = $eventEntry;
    saveJson($eventFile, $eventData);
    $tempData = []; // Reset temp data
}

// ------------------- APPEND NEW PING ------------------- //

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

// ------------------- SUCCESS ------------------- //

echo "Ping received. Status: " . ($pingDelayExceeded ? "Delay detected. Event logged." : "Normal ping.");

?>
