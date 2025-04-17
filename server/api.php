<?php

/**
 * ================================================================
 *  IoT Device API Receiver Script
 * ================================================================
 * 
 *  Purpose:
 *  --------
 *  This PHP script receives ping data (current, timestamp, timezone, and deviceId)
 *  from IoT devices through URL parameters and stores them into a temp file
 *  for each device in JSON format. It validates registered devices, checks input
 *  sanitization, and handles folder/file creation.
 * 
 *  Author: Abhijeet Kumar (TeamSense - IIT Patna)
 *  Created On: April 11, 2025
 *  Last Modified: April 11, 2025
 *  Version: 1.0
 * 
 *  Usage:
 *  ------
 *  Endpoint URL: api.php
 *  Required GET Parameters:
 *      - current   : Current value in amps (float)
 *      - timestamp : Timestamp from RTC (format: Y-m-d H:i:s)
 *      - timezone  : Timezone string (e.g., Asia/Kolkata)
 *      - deviceId  : Registered device ID
 * 
 *  Example Request:
 *  ----------------
 *  http://yourdomain.com/api.php?current=4.5&timestamp=2025-04-11 14:22:01&timezone=Asia/Kolkata&deviceId=01xd02m25
 * 
 *  Folder Structure:
 *  -----------------
 *  /device_<deviceId>/
 *      └── temp_<deviceId>.json   ← Stores ongoing pings in JSON
 * 
 * ================================================================
 */









// ================= CONFIGURATION ================= //

// File that stores registered device IDs
$deviceIdFile = 'devices.json';

// Set default timezone (only for context/display, not used for logic)
date_default_timezone_set('Asia/Kolkata');


// ================= MAIN ================= //

// Sanitize function to clean input data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Fetch and sanitize data from URL parameters
$current   = isset($_GET['current'])   ? sanitize($_GET['current'])   : '';
$timestamp = isset($_GET['timestamp']) ? sanitize($_GET['timestamp']) : '';
$deviceId  = isset($_GET['deviceId'])  ? sanitize($_GET['deviceId'])  : '';
$timezone  = isset($_GET['timezone'])  ? sanitize($_GET['timezone'])  : '';

// Check received data — exit if any are empty
if ($current === '' || $timestamp === '' || $deviceId === '' || $timezone === '') {
    echo "Error: One or more required parameters are missing or empty.";
    exit;
}

// Load and decode JSON data from a file
$devices = file_exists($deviceIdFile) ? json_decode(file_get_contents($deviceIdFile), true) ?? [] : [];

// Validate device against registered devices list
if (!in_array($deviceId, $devices)) {
    echo "Error: 'deviceId' is not registered.";
    exit;
}

// Validate timestamp format (expects Y-m-d H:i:s)
if (DateTime::createFromFormat('Y-m-d H:i:s', $timestamp) === false) {
    echo "Error: 'timestamp' format is invalid. Expected format: Y-m-d H:i:s";
    exit;
}

// Create folder if not exists
$folder = "device_{$deviceId}";
if (!file_exists($folder)) {
    if (!mkdir($folder, 0777, true)) {
        echo "Error: Failed to create folder for device.";
        exit;
    }
}

// Prepare temp file path
$tempFile = "$folder/temp_{$deviceId}.json";

// Load current temp data
$tempData = file_exists($tempFile) ? json_decode(file_get_contents($tempFile), true) ?? [] : [];

// Append new ping data
$tempData[] = [
    "timestamp" => $timestamp,
    "date" => substr($timestamp, 0, 10),
    "value" => (float)$current,
    "timezone" => $timezone
];

// Save back to temp file
if (file_put_contents($tempFile, json_encode($tempData, JSON_PRETTY_PRINT)) === false) {
    echo "Error: Failed to save data to temp file.";
    exit;
}

// Success response
echo "Ping saved successfully.";
