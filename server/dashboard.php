<?php
// ==========================
//        CONFIGURATION
// ==========================

// 1. Device list file (list of valid device IDs)
$deviceListFile = 'devices.json';

// 2. Timezone for display (not used in time calculations)
date_default_timezone_set('Asia/Kolkata');

// 3. Timeout in seconds to consider device as "offline"
$timeoutSeconds = 60;

// ==========================
//       HELPER FUNCTIONS
// ==========================

// Load and decode a JSON file safely
function loadJson($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }

    $json = file_get_contents($filePath);
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error parsing JSON from $filePath: " . json_last_error_msg());
        return [];
    }

    return $data;
}

// Save array as JSON into a file safely
function saveJson($filePath, $dataArray) {
    file_put_contents($filePath, json_encode($dataArray, JSON_PRETTY_PRINT));
}

// Check if the device is online based on the latest ping
function checkTimeoutAndProcessEvent($tempData, $deviceId, $timeoutSeconds) {
    if (empty($tempData)) {
        return "No Data"; // No pings at all
    }

    // Get the timestamp of the last ping
    $lastPing = end($tempData);
    $lastTimestamp = strtotime($lastPing['timestamp']);
    $now = time();

    $timeDifference = $now - $lastTimestamp;

    if ($timeDifference < $timeoutSeconds) {
        return "Device Online";
    } else {
        // Timeout detected — compile and save event, then clear temp
        $start = strtotime($tempData[0]['timestamp']);
        $end = $lastTimestamp;
        $duration = $end - $start;

        $avgValue = array_sum(array_column($tempData, 'value')) / count($tempData);

        $event = [
            "date" => $tempData[0]['date'],
            "start_time" => $tempData[0]['timestamp'],
            "end_time" => $lastPing['timestamp'],
            "duration" => $duration,
            "average_value" => round($avgValue, 2)
        ];

        $eventFile = "device_{$deviceId}/event_{$deviceId}.json";
        $events = loadJson($eventFile);
        $events[] = $event;
        saveJson($eventFile, $events);

        // Clear temp file
        $tempFile = "device_{$deviceId}/temp_{$deviceId}.json";
        file_put_contents($tempFile, "[]");

        return "Device Offline (Event Compiled)";
    }
}

// ==========================
//           MAIN
// ==========================

// Load all registered devices
$devices = loadJson($deviceListFile);

// Select default or chosen device
$selectedDevice = $_GET['device'] ?? ($devices[0] ?? '');

if (!in_array($selectedDevice, $devices)) {
    $selectedDevice = $devices[0] ?? '';
}

// If no valid devices found, show message
if (!$selectedDevice) {
    die("No valid device found. Check devices.json.");
}

// Define file paths for selected device
$folder = "device_{$selectedDevice}";
$tempFile = "$folder/temp_{$selectedDevice}.json";
$eventFile = "$folder/event_{$selectedDevice}.json";

// Load data
$tempData = loadJson($tempFile);
$eventData = loadJson($eventFile);

// Determine online/offline and possibly compile event
$deviceStatus = checkTimeoutAndProcessEvent($tempData, $selectedDevice, $timeoutSeconds);

// Refresh tempData in case it was cleared
$tempData = loadJson($tempFile);

// UI values
$latestPing = end($tempData);
$startTime = $tempData[0]['timestamp'] ?? null;
$avgCurrent = count($tempData) ? number_format(array_sum(array_column($tempData, 'value')) / count($tempData), 2) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>IoT Device Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>setInterval(() => location.reload(), 5000);</script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-5xl mx-auto">
    <!-- HEADER -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-700">IoT Dashboard</h1>
      <form method="GET">
        <label class="mr-2 font-semibold">Select Device:</label>
        <select name="device" onchange="this.form.submit()" class="px-2 py-1 border rounded">
          <?php foreach ($devices as $device): ?>
            <option value="<?= $device ?>" <?= $device === $selectedDevice ? 'selected' : '' ?>><?= $device ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <!-- ONGOING EVENT -->
    <div class="bg-white p-6 rounded shadow mb-8">
      <h2 class="text-xl font-semibold mb-4 text-blue-600">Ongoing Event</h2>
      <?php if (!empty($tempData)): ?>
        <p><strong>Status:</strong> <?= $deviceStatus ?></p>
        <p><strong>Started:</strong> <?= $startTime ?></p>
        <p><strong>Latest Ping:</strong> <?= $latestPing['value'] ?? 'N/A' ?> A (<?= $latestPing['timestamp'] ?? 'N/A' ?>)</p>
        <p><strong>Average Current:</strong> <?= $avgCurrent ?> A</p>
      <?php else: ?>
        <p class="text-red-600">No ongoing pings for this device.</p>
      <?php endif; ?>
    </div>

    <!-- EVENT HISTORY -->
    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4 text-green-600">Event History</h2>
      <?php if (!empty($eventData)): ?>
        <table class="w-full table-auto">
          <thead>
            <tr class="bg-gray-200 text-gray-700">
              <th class="px-4 py-2 text-left">Date</th>
              <th class="px-4 py-2 text-left">Start</th>
              <th class="px-4 py-2 text-left">End</th>
              <th class="px-4 py-2 text-left">Duration (s)</th>
              <th class="px-4 py-2 text-left">Average (A)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_reverse($eventData) as $event): ?>
              <tr class="border-b">
                <td class="px-4 py-2"><?= $event['date'] ?></td>
                <td class="px-4 py-2"><?= $event['start_time'] ?></td>
                <td class="px-4 py-2"><?= $event['end_time'] ?></td>
                <td class="px-4 py-2"><?= $event['duration'] ?></td>
                <td class="px-4 py-2"><?= $event['average_value'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-red-600">No event history found.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
