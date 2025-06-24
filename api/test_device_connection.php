<?php
// in file: api/test_device_connection.php

require_once '../app/bootstrap.php';

// We don't need full authentication here, but we check for a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get the JSON payload from the request
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['ip_address']) || empty($data['port'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Missing IP address or port.']);
    exit;
}

$ip_address = $data['ip_address'];
$port = (int)$data['port'];
$response = ['status' => 'offline', 'error' => 'Unknown error.'];

try {
    // Set a short timeout for socket operations to prevent long hangs
    ini_set('default_socket_timeout', 2); // 2 seconds

    // Directly use the ZKTeco library for a simple connection test.
    $zk = new \CodingLibs\ZktecoPhp\Libs\ZKTeco($ip_address, $port);
    
    if ($zk->connect()) {
        $response['status'] = 'online';
        $response['error'] = null;
        $zk->disconnect();

        // Update device status and last_seen in the database
        $updateStmt = $pdo->prepare("UPDATE devices SET status = 'online', last_seen = NOW() WHERE ip_address = ? AND port = ?");
        $updateStmt->execute([$ip_address, $port]);

    } else {
         $response['error'] = 'Could not connect to the device. Check IP/Port and network connection.';
    }

} catch (Exception $e) {
    $response['error'] = "Connection failed: " . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);