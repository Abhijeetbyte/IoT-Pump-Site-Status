<?php

// ================= CONFIGURATION ================= //

// File that stores registered device IDs
$deviceIdFile = 'devices.json';

// Time in seconds to wait before considering an event as "finished"
$timeout = 60; // If hardware device is OFF for more than 60s

// Set default timezone (only for context/display, not used for logic)
date_default_timezone_set('Asia/Kolkata');






// ================= HELPER FUNCTIONS ================= //

/**
 * Load and decode JSON data from a file.
 * If file does not exist, return an empty array.
 */
function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

/**
 * Save (encode) data to a file in JSON format.
 */
function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Ensure the folder for a device exists.
 * If not, create it with full permissions.
 */
function ensureDeviceFolder($deviceId) {
    $folder = "device_{$deviceId}";
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }
    return $folder;
}

/**
 * Check whether the deviceId is registered in devices.json
 */
function validateDevice($deviceId) {
    $devices = loadJson('devices.json');
    return in_array($deviceId, $devices);
}

/**
 * Validate whether the timestamp is in the format "Y-m-d H:i:s"
 */
function isValidTimestamp($timestamp) {
    return DateTime::createFromFormat('Y-m-d H:i:s', $timestamp) !== false;
}






// ================= TEMP FILE FUNCTIONS ================= //

/**
 * Get the path to the temp file for a device.
 */
function getTempFilePath($deviceId) {
    $folder = ensureDeviceFolder($deviceId);
    return "$folder/temp_{$deviceId}.json";
}

/**
 * Append new data to the device's temp file.
 */
function appendToTemp($deviceId, $data) {
    $tempFile = getTempFilePath($deviceId);
    $tempData = loadJson($tempFile);  // Load current temp data
    $tempData[] = $data;              // Add new ping
    saveJson($tempFile, $tempData);   // Save back to file
}




/**
 * Load the current temp data for the device.
 */
function loadTempData($deviceId) {
    return loadJson(getTempFilePath($deviceId));
}




/**
 * Clear the temp file (i.e., after event is saved).
 */
function clearTemp($deviceId) {
    saveJson(getTempFilePath($deviceId), []);
}






// ================= EVENT FILE FUNCTIONS ================= //

/**
 * Get the path to the event file for a device.
 */
function getEventFilePath($deviceId) {
    $folder = ensureDeviceFolder($deviceId);
    return "$folder/event_{$deviceId}.json";
}

/**
 * Append a new compiled event to the event file.
 */
function appendToEventFile($deviceId, $event) {
    $eventFile = getEventFilePath($deviceId);
    $eventData = loadJson($eventFile);
    $eventData[] = $event;
    saveJson($eventFile, $eventData);
}





// ================= EVENT COMPILATION ================= //

/**
 * Create a single event from the collected temp data.
 * Then save it to the event file and clear the temp file.
 */
function compileAndSaveEvent($deviceId) {
    $tempData = loadTempData($deviceId);
    //if (count($tempData) < 2) return;  // Not enough data for an event

    $start = $tempData[0]['timestamp'];
    $end = end($tempData)['timestamp'];
    $duration = strtotime($end) - strtotime($start);
    $avg = number_format(array_sum(array_column($tempData, 'value')) / count($tempData), 2);

    $event = [
        "date" => $tempData[0]['date'],
        "start_time" => $start,
        "end_time" => $end,
        "duration" => $duration,
        "average_value" => $avg
    ];
    

    appendToEventFile($deviceId, $event);  // Save compiled event
    clearTemp($deviceId);                  // Clear temp data after saving
}












// ================= SESSION EXPIRY CHECK ================= //

/**
 * Check if enough time (timeout) has passed since the last ping.
 * If yes, compile and save the event.
 */
function checkIfSessionExpired($deviceId, $timeout) {
    $tempData = loadTempData($deviceId);
    if (empty($tempData)) return false;

    $lastPing = end($tempData)['timestamp'];
    $secondsPassed = time() - strtotime($lastPing);

    return $secondsPassed >= $timeout;
}





















// ================= MAIN LOGIC (API ENTRY POINT) ================= //

// Step 1: Get data from the API request
$current   = $_GET['current'] ?? null;    // Current value in amps
$timestamp = $_GET['timestamp'] ?? null;  // Timestamp from RTC
$deviceId  = $_GET['deviceId'] ?? null;   // Device sending the data



// ================= INPUT VALIDATION ================= //

// Check if deviceId is missing or empty
if (!isset($_GET['deviceId']) || trim($_GET['deviceId']) === '') {
    exit("Error: 'deviceId' is missing or empty.");
} else {
    $deviceId = $_GET['deviceId'];
}

// Check if current value is missing or empty
if (!isset($_GET['current']) || trim($_GET['current']) === '') {
    exit("Error: 'current' value is missing or empty.");
} else {
    $current = $_GET['current'];
}

// Check if timestamp is missing or empty
if (!isset($_GET['timestamp']) || trim($_GET['timestamp']) === '') {
    exit("Error: 'timestamp' is missing or empty.");
} else {
    $timestamp = $_GET['timestamp'];
}

// Validate device against registered devices list
if (!validateDevice($deviceId)) {
    exit("Error: 'deviceId' is not registered.");
}

// Validate timestamp format (expects Y-m-d H:i:s)
if (!isValidTimestamp($timestamp)) {
    exit("Error: 'timestamp' format is invalid. Expected format: Y-m-d H:i:s");
}


// ================= SESSION HANDLING ================= //

// Step 3: Load previous temp data
$prevTempData = loadTempData($deviceId);

// Step 4: Append the new ping FIRST (so temp always has the latest point)
appendToTemp($deviceId, [
    "timestamp" => $timestamp,
    "date" => substr($timestamp, 0, 10),
    "value" => (float)$current
]);

// Step 5: Always compile the event and update the event file from current temp data
//         (do NOT clear the temp yet — that only happens after timeout)
$tempData = loadTempData($deviceId);
$start = $tempData[0]['timestamp'];
$end = end($tempData)['timestamp'];
$duration = strtotime($end) - strtotime($start);
$avg = number_format(array_sum(array_column($tempData, 'value')) / count($tempData), 2);

$event = [
    "date" => $tempData[0]['date'],
    "start_time" => $start,
    "end_time" => $end,
    "duration" => $duration,
    "average_value" => $avg
];

// Save compiled event (this will be overwritten repeatedly)
$eventFile = getEventFilePath($deviceId);
saveJson($eventFile, [$event]);

/*
    What we're doing here:
    - We save the event on EVERY ping (based on current temp).
    - This ensures that even if the device goes offline unexpectedly, at least one event will exist.
    - When the device comes back and a 60s timeout is detected, the temp will be cleared, ready for the next cycle.
*/

// Step 6: Now check if the session expired (i.e., 60s gap from previous ping)
if (!empty($prevTempData)) {
    $lastPing = end($prevTempData)['timestamp'];
    $secondsPassed = strtotime($timestamp) - strtotime($lastPing);

    if ($secondsPassed >= $timeout) {
        // If session expired → compile and save the final event (and clear temp)
        compileAndSaveEvent($deviceId);
    }
}

// Step 7: Success response
echo "Ping saved successfully.";
