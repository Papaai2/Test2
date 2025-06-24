<?php
// in file: api/fetch_device_logs.php

require_once '../app/bootstrap.php';
header('Content-Type: application/json');

// Ensure user is an admin to perform this action
if (!is_admin()) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$deviceId = $data['device_id'] ?? null;

if (!$deviceId) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Device ID is missing.']);
    exit;
}

try {
    // $pdo is available globally from bootstrap.php
    $deviceStmt = $pdo->prepare("SELECT ip_address, port FROM devices WHERE id = ?");
    $deviceStmt->execute([$deviceId]);
    $device = $deviceStmt->fetch();

    if (!$device) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Device not found in database.']);
        exit;
    }

    $attendanceService = new AttendanceService($pdo);

    $rawLogs = $attendanceService->getLogsFromDevice($device['ip_address'], $device['port']);
    
    // Standardize logs using the correct keys
    $standardizedLogs = [];
    foreach ($rawLogs as $log) {
        if (isset($log['user_id']) && isset($log['record_time'])) {
            $standardizedLogs[] = [
                'employee_code' => $log['user_id'],
                'punch_time'    => $log['record_time']
            ];
        }
    }
    
    $savedCount = $attendanceService->saveStandardizedLogs($standardizedLogs);

    echo json_encode([
        'success' => true,
        'fetched_total' => count($rawLogs),
        'saved_new' => $savedCount,
        'message' => "Successfully fetched " . count($rawLogs) . " logs. Saved " . $savedCount . " new records."
    ]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Fetch Logs Error (Device ID: $deviceId): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred. Please check the system logs.']);
}