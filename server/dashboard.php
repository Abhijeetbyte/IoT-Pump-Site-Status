<?php


// ================= CONFIGURATION ================= //

// File that stores registered device IDs
$deviceIdFile = 'devices.json';

// Set default timezone (only for context/display, not used for logic)
//date_default_timezone_set('Asia/Kolkata');

// Define file paths prefixes
$deviceFolder = 'device_';
$eventFilePrefix = 'event_';
$tempFilePrefix = 'temp_';

// Device status flag holder
$deviceStatus = "None";




// Function to load JSON data from a file and handle errors
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




// =================Select device  ================= //

// Load known devices from 'devices.json' file
$devices = loadJsonFile($deviceIdFile);


// Get the selected device from the URL, or [0] from devices json file
if (isset($_GET['device'])) {
    $selectedDevice = $_GET['device'];
} else if (!empty($devices)) {
    $selectedDevice = $devices[0];
} else {
    $selectedDevice = '';
}

// Define file paths for the temp and event files according to selected device Id
$folder = $deviceFolder . $selectedDevice;
$tempFile = "$folder/" . $tempFilePrefix . $selectedDevice . '.json';
$eventFile = "$folder/" . $eventFilePrefix . $selectedDevice . '.json';






// ================= Check Timeout and Status Flag ================= //
function checkDeviceStatus($tempData) {
    global $deviceStatus;

    // Initialize the default device status to "None"
    $deviceStatus = "None";

    // Check if the temp json file is not empty
    if (!empty($tempData)) {
        
        // Extract the last entry (latest ping) from the tempData
        $lastEntry = $tempData[count($tempData) - 1];
        $lastPingTime = $lastEntry['timestamp'];  // Timestamp of last ping
        $timezone = $lastEntry['timezone'];       // Timezone of the last ping

        // Step 1: Create a DateTime object for the last ping time in the specified timezone
        $tz = new DateTimeZone($timezone);
        $pingDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $lastPingTime, $tz);

        // If DateTime object creation fails, output an error message and return
        if (!$pingDateTime) {
            echo "<p>⚠️ Failed to parse timestamp correctly.</p>";
            return;
        }

        // Uncomment for debugging purposes:
        // echo "<p><strong>Parsed Ping Time:</strong> " . $pingDateTime->format('Y-m-d H:i:s') . "</p>";

        // Step 2: Convert the ping DateTime object to a Unix timestamp (in seconds)
        $lastPingTimestamp = $pingDateTime->getTimestamp();

        // Step 3: Get the current time in the same timezone as the last ping
        $now = new DateTime('now', $tz);  // Get current time in the specified timezone
        $currentTimestamp = $now->getTimestamp();  // Convert the current time to a Unix timestamp

        // Uncomment for debugging purposes: 
        // echo "<p><strong>Current Time:</strong> " . $now->format('Y-m-d H:i:s') . "</p>";

        //Calculate the time difference between the current time and the last ping
        $timeDiff = $currentTimestamp - $lastPingTimestamp;

        // Uncomment for debugging purposes: 
        // echo "<p><strong>Last Ping Timestamp (in seconds):</strong> " . $lastPingTimestamp . "</p>";
        // echo "<p><strong>Current Timestamp (in seconds):</strong> " . $currentTimestamp . "</p>";
        // echo "<p><strong>Expected Time Difference (in seconds):</strong> " . ($currentTimestamp - $lastPingTimestamp) . "</p>";
        // echo "<p><strong>Time Diff (in seconds):</strong> " . $timeDiff . " seconds</p>";

        // If the time difference is less than or equal to 60 seconds, the device is online
        if ($timeDiff <= 60) {
            $deviceStatus = "Device Online";
        } else {
            // If the time difference is greater than 60 seconds, the device is offline
            $deviceStatus = "Device Offline";
        }

        // Step 6: Display the final device status
        //echo "<p><strong>Device Status:</strong> " . $deviceStatus . "</p>";
    }
}





// ================= Control Structure ================= //


// Load current temp and event data
$tempData = loadJsonFile($tempFile);
$eventData = loadJsonFile($eventFile);

// Call status/timeout function to get device status (online or offline)
$deviceStatus = "None"; // initialize before checking
checkDeviceStatus($tempData);


// Echo the status directly on the webpage
//echo "<p><strong>Device Status:</strong> " . $deviceStatus . "</p>";


// If the device is offline, compile the temp data into a single event and then clear the temp file

if ($deviceStatus === "Device Offline" && !empty($tempData)) { //If the device is offline, and ( temnp file is not empty to break the loop)
    
    
    // Extract start and end timestamps from the temp data
    $startTimestamp = $tempData[0]['timestamp'];
    $endTimestamp = $tempData[count($tempData) - 1]['timestamp'];

    // Convert timestamps to UNIX format for calculation
    $startTime = strtotime($startTimestamp);
    $endTime = strtotime($endTimestamp);

    // Calculate duration in seconds
    $duration = $endTime - $startTime;

    // Calculate average value from all pings
    $totalValue = 0;
    $count = count($tempData);

    foreach ($tempData as $ping) {
        $totalValue += $ping['value'];
    }

    $averageValue = $count > 0 ? number_format($totalValue / $count, 2) : 0;

    // Create compiled event array with "date" at the top
    $compiledEvent = [
        "date" => $tempData[0]['date'],
        "start_time" => $startTimestamp,
        "end_time" => $endTimestamp,
        "duration" => $duration,
        "average_value" => $averageValue
    ];

    // Append the compiled event to the existing event data
    $eventData[] = $compiledEvent;

    // Save updated event list back to the event file
    file_put_contents($eventFile, json_encode($eventData, JSON_PRETTY_PRINT));

    // Clear the temp file after saving event
    file_put_contents($tempFile, json_encode([], JSON_PRETTY_PRINT));

} 





// If the device is online.

if ($deviceStatus === "Device Online" ) { 

    // Just open the temp file.
    
    //1. Show start time & date. (fetch first entry in temp file)
    // 2.  Show latest stats.
    
    $firstPing = $tempData[0];
    $lastPing = $tempData[count($tempData) - 1];
    
    // Make sure you're getting the values from the correct keys in tempData
    $startTime = isset($firstPing['timestamp']) ? $firstPing['timestamp'] : 'N/A';
    $startDate = isset($firstPing['date']) ? $firstPing['date'] : 'N/A';
    $latestTime = isset($lastPing['timestamp']) ? $lastPing['timestamp'] : 'N/A';
    $latestDate = isset($lastPing['date']) ? $lastPing['date'] : 'N/A';
    $latestValue = isset($lastPing['value']) ? $lastPing['value'] : 'N/A';

}










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
            <option value="<?= $device ?>" <?= $device === $selectedDevice ? 'selected' : '' ?>><?= $device ?></option>
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
            <p><strong>Start Time:</strong> <?= htmlspecialchars($startTime) ?></p>
            <p><strong>Start Date:</strong> <?= htmlspecialchars($startDate) ?></p>
            <p><strong>Latest Ping Time:</strong> <?= htmlspecialchars($latestTime) ?></p>
            <p><strong>Latest Ping Date:</strong> <?= htmlspecialchars($latestDate) ?></p>
            <p><strong>Latest Value:</strong> <?= htmlspecialchars($latestValue) ?> amps</p>
            
            <p><strong>Status:</strong> 
              <?php if ($deviceStatus === "Device Online"): ?>
                <span class="bg-green-200 text-green-800 px-2 py-1 rounded">🟢 Online</span>
              <?php elseif ($deviceStatus === "Device Offline"): ?>
                <span class="bg-red-200 text-red-800 px-2 py-1 rounded">🔴 Offline</span>
              <?php else: ?>
                <span class="bg-gray-200 text-gray-800 px-2 py-1 rounded">⚪ Unknown</span>
              <?php endif; ?>
            </p>
        
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
        <p class="text-red-600">No events found.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
