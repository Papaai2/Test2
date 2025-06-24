<?php
// in file: htdocs/download.php

require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/database.php';

require_login();

$requested_file_name = $_GET['file'] ?? null;
if (!$requested_file_name) {
    http_response_code(400);
    exit('Invalid request. File parameter is missing.');
}

// Sanitize the requested file name to prevent directory traversal
$requested_file_name = basename($requested_file_name);

$user_id = get_current_user_id();
$user_role = get_current_user_role();

// Fetch request details using the attachment_path
$stmt = $pdo->prepare("
    SELECT id, user_id, manager_id, attachment_path
    FROM vacation_requests
    WHERE attachment_path = ?
");
$stmt->execute([$requested_file_name]);
$request_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request_data) {
    http_response_code(404);
    exit('File not found or no associated request.');
}

// Use the attachment_path from the database for the actual file path
$file_to_serve = [
    'file_name' => $request_data['attachment_path'], // Use stored name as file name for download
    'stored_name' => $request_data['attachment_path'],
    'user_id' => $request_data['user_id'],
    'manager_id' => $request_data['manager_id']
];


// --- ACCESS CONTROL ---
// Check if the current user has permission to download this file.
// Permission is granted if:
// 1. They are the user who submitted the request.
// 2. They are the manager assigned to the request.
// 3. They have an 'hr' or 'admin' role.
$is_owner = ($user_id == $file_to_serve['user_id']);
$is_manager = ($user_id == $file_to_serve['manager_id']);
$is_hr_or_admin = in_array($user_role, ['hr', 'admin']);

if (!$is_owner && !$is_manager && !$is_hr_or_admin) {
    http_response_code(403);
    exit('Access Denied.');
}

$file_path = __DIR__ . '/uploads/' . $file_to_serve['stored_name'];

if (!file_exists($file_path)) {
    http_response_code(404);
    exit('File not found on server.');
}

// Serve the file for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream'); // Generic type for all files
header('Content-Disposition: attachment; filename="' . basename($file_to_serve['file_name']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));
flush(); // Flush system output buffer
readfile($file_path);
exit();