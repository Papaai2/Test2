<?php
// test_insert.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Device Log Fetch & Insert Test</title><style>body { font-family: sans-serif; } .results { border-left: 5px solid; padding-left: 15px; margin-bottom: 10px; } .success { border-color: green; } .failure { border-color: red; }</style></head><body>';
echo '<h1>Testing Real Punch Log Fetch and Insert...</h1>';

try {
    require_once __DIR__ . '/app/core/config.php';
    require_once __DIR__ . '/app/core/database.php';
    require_once __DIR__ . '/app/core/services/AttendanceService.php';

    echo "<p>Core files included successfully.</p>";

    if (!isset($pdo)) {
        die("<p style='color:red;'>FATAL ERROR: \$pdo object was not created.</p></body></html>");
    }
    echo "<p>PDO object found.</p>";

    $attendanceService = new AttendanceService($pdo);
    echo "<p>AttendanceService instantiated.</p>";
    
    $deviceStmt = $pdo->query("SELECT ip_address, port, name FROM devices LIMIT 1");
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        die("<p style='color:red;'>ERROR: No devices found in the 'devices' table.</p></body></html>");
    }

    $ip_address = $device['ip_address'];
    $port = $device['port'];
    echo "<p>Found device '<strong>" . htmlspecialchars($device['name']) . "</strong>' at IP: <strong>{$ip_address}:{$port}</strong>.</p>";

    echo "<p>Attempting to connect to the device and get attendance logs...</p>";
    $logs = $attendanceService->getLogsFromDevice($ip_address, $port);

    if (empty($logs)) {
        echo "<p style='color:orange;'>Could not fetch any new logs from the device.</p>";
    } else {
        echo "<p style='color:green;'>SUCCESS! Fetched " . count($logs) . " log records from the device.</p>";
        
        echo '<h3>Raw Device Logs:</h3><pre>';
        print_r($logs);
        echo '</pre>';

        // **FIXED HERE:** Standardize logs using the correct keys from your device ('user_id' and 'record_time').
        $standardizedLogs = [];
        foreach ($logs as $log) {
            if (isset($log['user_id']) && isset($log['record_time'])) {
                $standardizedLogs[] = [
                    'employee_code' => $log['user_id'],
                    'punch_time'    => $log['record_time']
                ];
            }
        }

        echo "<h3>Processing Results:</h3>";
        $savedCount = 0;
        
        // Sort logs by time to process them in chronological order
        usort($standardizedLogs, fn($a, $b) => strcmp($a['punch_time'], $b['punch_time']));

        foreach ($standardizedLogs as $log) {
            $result = $attendanceService->processPunch($log['employee_code'], $log['punch_time']);
            
            if ($result === true) {
                $savedCount++;
                echo "<div class='results success'><strong>SUCCESS:</strong> Saved log for employee <strong>" . htmlspecialchars($log['employee_code']) . "</strong> at " . htmlspecialchars($log['punch_time']) . "</div>";
            } else {
                echo "<div class='results failure'><strong>FAILURE:</strong> Could not save log for employee <strong>" . htmlspecialchars($log['employee_code']) . "</strong> at " . htmlspecialchars($log['punch_time']) . "<br><strong>Reason:</strong> " . htmlspecialchars($result) . "</div>";
            }
        }
        echo "<h3>Total New Records Inserted: " . $savedCount . "</h3>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>FATAL EXCEPTION CAUGHT!</p>";
    echo "<p><strong>Error Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Stack Trace:</strong></p><pre>" . $e->getTraceAsString() . "</pre>";
}

echo '</body></html>';