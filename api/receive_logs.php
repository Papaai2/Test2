<?php

require_once __DIR__ . '/../app/core/config.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/services/AttendanceService.php';

// Configuration
$api_key = 'YOUR_SECURE_API_KEY'; // Must match the API key in agent.php

// Function to save logs to the database
function saveLogsToDatabase(PDO $pdo, array $logs)
{
    $attendanceService = new AttendanceService($pdo);
    $savedCount = $attendanceService->saveStandardizedLogs($logs);
    return $savedCount;
}

// Authentication
$headers = getallheaders();
if (!isset($headers['X-API-Key']) || $headers['X-API-Key'] !== $api_key) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

// Receive data
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid data format']);
    exit;
}

// Database connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

// Save logs to database
try {
    $savedCount = saveLogsToDatabase($pdo, $data);
    echo json_encode(['message' => 'Logs received and saved successfully. Saved ' . $savedCount . ' logs.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Error saving logs: ' . $e->getMessage()]);
    exit;
}
?>