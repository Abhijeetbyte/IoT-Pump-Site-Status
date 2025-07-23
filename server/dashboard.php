<?php
/**
 * IoT Device Dashboard (Display Only)
 * Author: Abhijeet Kumar
 * Version: 2.0
 * Purpose: View device online status + history, without modifying any files.
 */

// Configuration
$deviceIdFile = 'devices.json';
$deviceFolder = 'device_';
$tempFilePrefix = 'temp_';
$eventFilePrefix = 'event_';

// Load JSON from file
function loadJsonFile($filePath) {
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

// Load device list
$devices = loadJsonFile($deviceIdFile);
$selectedDevice = $_GET['device'] ?? ($devices[0] ?? '');

// Construct file paths
$folder = $deviceFolder . $selectedDevice;
$tempFile = "$folder/" . $tempFilePrefix . $selectedDevice . '.json';
$eventFile = "$folder/" . $eventFilePrefix . $selectedDevice . '.json';

// Load temp + event data
$tempData = loadJsonFile($tempFile);
$eventData = loadJsonFile($eventFile);

// Determine status and latest ping
$deviceStatus = "Unknown";
$startDate = $startTime = $latestDate = $latestTime = $latestValue = 'N/A';

if (!empty($tempData)) {
    $firstPing = $tempData[0];
    $lastPing = end($tempData);

    $startTime = $firstPing['timestamp'] ?? 'N/A';
    $startDate = $firstPing['date'] ?? 'N/A';
    $latestTime = $lastPing['timestamp'] ?? 'N/A';
    $latestDate = $lastPing['date'] ?? 'N/A';
    $latestValue = $lastPing['value'] ?? 'N/A';

    $tz = new DateTimeZone($lastPing['timezone'] ?? 'Asia/Kolkata');
    $lastPingDT = DateTime::createFromFormat('Y-m-d H:i:s', $latestTime, $tz);
    $now = new DateTime('now', $tz);
    $deviceStatus = ($now->getTimestamp() - $lastPingDT->getTimestamp() <= 60) ? "Device Online" : "Device Offline";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>IoT Device Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>setInterval(() => location.reload(), 5000);</script>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-5xl mx-auto">

    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-700">MARU Site Pump Status: Dashboard</h1>
      <?php if (!empty($devices)): ?>
        <form method="GET">
          <label class="mr-2 font-semibold">Select Device:</label>
          <select name="device" onchange="this.form.submit()" class="px-2 py-1 border rounded">
            <?php foreach ($devices as $device): ?>
              <option value="<?= $device ?>" <?= $device === $selectedDevice ? 'selected' : '' ?>><?= $device ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php else: ?>
        <p class="text-red-600">No devices found.</p>
      <?php endif; ?>
    </div>

    <!-- Live Status -->
    <div class="bg-white p-6 rounded shadow mb-8">
      <h2 class="text-xl font-semibold mb-4 text-blue-600">Live Status</h2>

      <?php if ($deviceStatus === "Device Online"): ?>
        <span class="bg-green-200 text-green-800 px-2 py-1 rounded italic">Pump is currently running...</span>
        <p><strong>Start Date:</strong> <?= htmlspecialchars($startDate) ?></p>
        <p><strong>Start Time:</strong> <?= htmlspecialchars($startTime) ?></p>
        <p><strong>Latest Ping Date:</strong> <?= htmlspecialchars($latestDate) ?></p>
        <p><strong>Latest Ping Time:</strong> <?= htmlspecialchars($latestTime) ?></p>
        <p><strong>Latest Value:</strong> <?= htmlspecialchars($latestValue) ?> amps</p>
        <p><strong>Status:</strong>
            <span class="bg-green-200 text-green-800 px-2 py-1 rounded">🟢 Online</span>
        </p>
      <?php else: ?>
        <p><strong>Status:</strong>
            <span class="bg-red-200 text-red-800 px-2 py-1 rounded">🔴 Offline</span>
        </p>
        <p class="text-red-600">No ongoing data available or device is offline.</p>
      <?php endif; ?>
    </div>

    <!-- Activity Log -->
    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4 text-green-600">Activity Log</h2>
      <?php if (!empty($eventData)): ?>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto">
            <thead>
              <tr class="bg-gray-200 text-gray-700">
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Start Time</th>
                <th class="px-4 py-2 text-left">End Time</th>
                <th class="px-4 py-2 text-left">Duration (s)</th>
                <th class="px-4 py-2 text-left">Average Value (amps)</th>
                <th class="px-4 py-2 text-left">Water Discharged (L)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_reverse($eventData) as $event): ?>
                <?php
                  $K = 0.5; // Discharge constant (L per amp-second)
                  $Q = round(($event['average_value'] ?? 0) * ($event['duration'] ?? 0) * $K, 2);
                ?>
                <tr class="border-b">
                  <td class="px-4 py-2"><?= $event['date'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $event['start_time'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $event['end_time'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $event['duration'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $event['average_value'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $Q ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-red-600">No events found for this device.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
