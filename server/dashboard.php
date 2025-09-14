<?php
/**
 * IoT Device Dashboard (Display Only)
 * Author: Abhijeet Kumar
 * Version: 2.1
 * Purpose: View device online status + event history with water discharge.
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

        <!-- Top toolbar: device selector (left-aligned) -->
        <div class="mb-4">
          <?php if (!empty($devices)): ?>
            <form method="GET" class="inline-flex items-center gap-2">
              <label for="device" class="font-semibold">Select Device:</label>
              <select id="device" name="device" onchange="this.form.submit()" class="px-2 py-1 border rounded">
                <?php foreach ($devices as $device): ?>
                  <option value="<?= $device ?>" <?= $device === $selectedDevice ? 'selected' : '' ?>><?= $device ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          <?php else: ?>
            <p class="text-red-600">No devices found.</p>
          <?php endif; ?>
        </div>
        
        <!-- Heading (centered) -->
        <div class="mb-6 text-center">
          <h1 class="text-2xl font-bold text-gray-700">
            Middle School Udwantnagar, Bhojpur, Bihar MARU Site Pump Status : Dashboard
          </h1>
        </div>
        
        <!-- Banner + Geo (stack) -->
        <?php
          $latitude  = $latitude  ?? 25.570399;
          $longitude = $longitude ?? 84.525861;
        
          $haveCoords = isset($latitude, $longitude) && $latitude !== '' && $longitude !== '';
          if ($haveCoords) {
            $lat = number_format((float)$latitude, 6, '.', '');
            $lng = number_format((float)$longitude, 6, '.', '');
            $mapsUrl = "https://maps.google.com/?q={$lat},{$lng}";
          }
        ?>
        
        <div class="flex flex-col items-center space-y-3 mb-6">
          <!-- Banner -->
          <div class="w-full max-w-[720px] px-2">
            <img
              src="banner-site-image.jpg"
              alt="MARU Site — Udwantnagar Banner"
              class="w-full h-48 sm:h-64 md:h-72 object-cover rounded-xl shadow"
              loading="lazy"
              decoding="async"
            />
          </div>
        
          <!-- Coordinates + Maps link -->
          <div class="w-full max-w-[720px] px-2">
            <?php if ($haveCoords): ?>
              <div class="bg-white rounded-lg shadow p-3 text-sm text-gray-700 flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="font-semibold">Coordinates:</span>
                <span>Lat: <?= $lat ?>, Lng: <?= $lng ?></span>
                <a
                  href="<?= htmlspecialchars($mapsUrl, ENT_QUOTES) ?>"
                  target="_blank" rel="noopener"
                  class="ml-auto inline-flex items-center underline decoration-dotted hover:no-underline text-green-800 font-bold"
                >
                  Open in Google Maps
                  <svg xmlns="http://www.w3.org/2000/svg" class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 10.5c0 7.5-9 11-9 11s-9-3.5-9-11a9 9 0 1118 0z" />
                    <circle cx="12" cy="10.5" r="3" stroke-width="2"/>
                  </svg>
                </a>
              </div>
            <?php else: ?>
              <div class="bg-white rounded-lg shadow p-3 text-sm text-gray-600 italic">
                Coordinates unavailable.
              </div>
            <?php endif; ?>
          </div>
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
                <th class="px-4 py-2 text-left">Average Value (Amps) ± 0.5A</th>
                <th class="px-4 py-2 text-left">Approx Water Discharged (L) by L/s cal. ±50L</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_reverse($eventData) as $event): ?>
                <tr class="border-b">
                  <td class="px-4 py-2"><?= $event['date'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $event['start_time'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $event['end_time'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $event['duration'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $event['average_value'] ?? '-' ?></td>
                  <td class="px-4 py-2"><?= $event['discharge_litres'] ?? '-' ?></td>
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
