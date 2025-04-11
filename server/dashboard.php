<?php
// Load and safely decode JSON
function loadJsonFile($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }

    $json = file_get_contents($filePath);
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error in $filePath: " . json_last_error_msg());
        return [];
    }

    return $data;
}

// Load device list
$devices = loadJsonFile("devices.json");
$selectedDevice = $_GET['device'] ?? ($devices[0] ?? '');

// Validate selected device
if (!in_array($selectedDevice, $devices)) {
    $selectedDevice = $devices[0] ?? '';
}

// Define file paths within device-specific folder
$folder = "device_{$selectedDevice}";
$tempFile = "$folder/temp_{$selectedDevice}.json";
$eventFile = "$folder/event_{$selectedDevice}.json";

// Load data safely
$tempData = loadJsonFile($tempFile);
$eventData = loadJsonFile($eventFile);

// Extract ongoing event details if data exists
$latestPing = end($tempData);
$startTime = $tempData[0]['timestamp'] ?? null;
$averageValue = count($tempData) ? number_format(array_sum(array_column($tempData, 'value')) / count($tempData), 2) : null;
$deviceID = $tempData[0]['deviceId'] ?? $selectedDevice;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IoT Device Dashboard</title>
  <script>
    setInterval(() => location.reload(), 5000);
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-5xl mx-auto">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-700">IoT Device Dashboard</h1>
      <?php if (!empty($devices)): ?>
      <form method="GET">
        <label class="mr-2 font-semibold">Select Device:</label>
        <select name="device" onchange="this.form.submit()" class="px-2 py-1 border rounded">
          <?php foreach ($devices as $device): ?>
            <option value="<?= $device ?>" <?= $selectedDevice === $device ? 'selected' : '' ?>><?= $device ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php else: ?>
        <p class="text-red-600">No devices available. Please check devices.json.</p>
      <?php endif; ?>
    </div>

    <!-- Ongoing Event Section -->
    <div class="bg-white p-6 rounded shadow mb-8">
      <h2 class="text-xl font-semibold mb-4 text-blue-600">Ongoing Event</h2>
      <?php if (!empty($tempData)): ?>
        <p><strong>Latest Ping:</strong> <?= $latestPing['value'] ?? 'N/A' ?> (Time: <?= $latestPing['timestamp'] ?? 'N/A' ?>)</p>
        <p><strong>Started:</strong> <?= $startTime ?></p>
        <p><strong>Average Value:</strong> <?= $averageValue ?> amps</p>
        <p><strong>Device ID:</strong> <?= $deviceID ?></p>
      <?php else: ?>
        <p class="text-red-600">No ongoing data available for this device.</p>
      <?php endif; ?>
    </div>

    <!-- Event History Section -->
    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4 text-green-600">Event History</h2>
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
        </div>
      <?php else: ?>
        <p class="text-red-600">No event history found for this device.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
