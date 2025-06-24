<?php

require_once __DIR__ . '/vendor/autoload.php';

use CodingLibs\ZktecoPhp\Libs\ZKTeco;

// Configuration
$machines = [
    [
        'ip_address' => 'MACHINE_1_IP',
        'port' => 4370,
    ],
    [
        'ip_address' => 'MACHINE_2_IP',
        'port' => 4370,
    ],
    // Add more machines here
];
$api_url = 'YOUR_WEB_HOST_API_ENDPOINT'; // Replace with your API endpoint URL
$api_key = 'YOUR_SECURE_API_KEY'; // Replace with a strong, random API key

// Function to fetch attendance logs from a single machine
function getAttendanceLogs(string $ip_address, int $port): array
{
    try {
        $zk = new ZKTeco($ip_address, $port, 30);
        $isConnected = $zk->connect();

        if ($isConnected) {
            $attendances = $zk->getAttendances();
            $zk->disconnect();

            $logs = [];
            foreach ($attendances as $attendance) {
                $logs[] = [
                    'employee_code' => $attendance['uid'],
                    'punch_time' => date('Y-m-d H:i:s', strtotime($attendance['timestamp'])),
                ];
            }
            return $logs;
        } else {
            echo "Failed to connect to fingerprint reader at {$ip_address}:{$port}." . PHP_EOL;
            return [];
        }
    } catch (Exception $e) {
        echo "Error fetching logs from {$ip_address}:{$port}: " . $e->getMessage() . PHP_EOL;
        return [];
    }
}

// Main script
$all_logs = [];
foreach ($machines as $machine) {
    $ip_address = $machine['ip_address'];
    $port = $machine['port'];
    $logs = getAttendanceLogs($ip_address, $port);
    $all_logs = array_merge($all_logs, $logs);
}

// Send logs to API endpoint
$data = json_encode($all_logs);

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $api_key,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    echo "Logs sent successfully. Response: " . $response . PHP_EOL;
} else {
    echo "Error sending logs. HTTP code: " . $http_code . ", Response: " . $response . PHP_EOL;
}

?>