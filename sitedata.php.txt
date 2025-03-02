<?php
// Set timezone
date_default_timezone_set("Asia/Kolkata");

// File paths
$pingsFile = 'pings.json';
$eventsFile = 'events.json';
$eventTimeout = 60;

// Load JSON data
function loadData($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

// Save JSON data
function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Load current data
$pings = loadData($pingsFile);
$events = loadData($eventsFile);

// Handle new ping
function handlePing($value) {
    global $pingsFile, $pings;
    $timestamp = date("Y-m-d H:i:s");
    $date = date("Y-m-d");
    $pings[] = ["timestamp" => $timestamp, "date" => $date, "value" => $value];
    saveData($pingsFile, $pings);
}

// Process event if timeout occurs
function processEvent() {
    global $pingsFile, $eventsFile, $pings, $events, $eventTimeout;
    
    if (empty($pings)) return;

    $lastPingTime = strtotime(end($pings)['timestamp']);
    if (time() - $lastPingTime <= $eventTimeout) return;

    $startTime = $pings[0]['timestamp'];
    $endTime = end($pings)['timestamp'];
    $eventDate = $pings[0]['date'];
    $duration = strtotime($endTime) - strtotime($startTime);
    $values = array_column($pings, 'value');
    $average = count($values) ? number_format(array_sum($values) / count($values), 2) : "N/A";

    $events[] = ["date" => $eventDate, "start_time" => $startTime, "end_time" => $endTime, "duration" => $duration, "average_value" => $average];
    saveData($eventsFile, $events);

    // Clear pings after processing
    saveData($pingsFile, []);
}

// Capture ping if provided
if (isset($_GET['value'])) {
    handlePing((float)$_GET['value']);
}

// Always check if an event needs processing
processEvent();

// Get latest data for display
$latestPing = end($pings);
$latestStartTime = $pings[0]['timestamp'] ?? null;
$latestDate = $pings[0]['date'] ?? null;
$latestAverage = count($pings) ? number_format(array_sum(array_column($pings, 'value')) / count($pings), 2) : "N/A";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Dashboard</title>
    <script>
        setInterval(() => location.reload(), 5000);
    </script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .section {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .section h2 {
            margin: 0 0 10px;
            font-size: 22px;
            color: #333;
        }
        .bold-text {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border-bottom: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #007BFF;
            color: white;
        }
        .scrollable {
            max-height: 250px;
            overflow-y: auto;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Ongoing Event Section -->
    <div class="section">
        <h2>Ongoing Event</h2>
        <p class="bold-text">Latest Ping: <?= $latestPing['value'] ?? 'N/A' ?> (Time: <?= $latestPing['timestamp'] ?? 'N/A' ?>)</p>
        <p class="bold-text">Date & Start Time: <?= $latestDate ? "$latestDate $latestStartTime" : 'N/A' ?></p>
        <p class="bold-text">Average Value: <?= $latestAverage ?></p>
    </div>

    <!-- Event History Section -->
    <div class="section">
        <h2>Event History</h2>
        <div class="scrollable">
            <table>
                <tr>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Duration (s)</th>
                    <th>Average Value</th>
                </tr>
                <?php if (!empty($events)) : ?>
                    <?php foreach ($events as $event) : ?>
                        <tr>
                            <td><?= $event['date']; ?></td>
                            <td><?= $event['start_time']; ?></td>
                            <td><?= $event['end_time']; ?></td>
                            <td><?= $event['duration']; ?></td>
                            <td><?= $event['average_value']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">No Events Recorded Yet</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

</body>
</html>
