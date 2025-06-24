<?php
// in file: htdocs/api/mark_notifications_read.php

header('Content-Type: application/json');

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_login();

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $notification_ids = $input['notification_ids'] ?? [];
    $user_id = get_current_user_id();

    if (!empty($notification_ids) && is_array($notification_ids)) {
        // Filter out non-integer values and convert to integers
        $notification_ids = array_filter($notification_ids, 'is_int');
        $notification_ids = array_map('intval', $notification_ids);

        if (!empty($notification_ids)) {
            $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
            $params = array_merge([$user_id], $notification_ids); // user_id first, then notification IDs

            try {
                // Update the notification status to read for the specific user and notification IDs
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id IN ($placeholders) AND is_read = 0");
                $stmt->execute($params);

                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                } else {
                    $response['message'] = "No notifications found or updated.";
                }
            } catch (PDOException $e) {
                $response['message'] = "Database error: " . $e->getMessage();
            }
        } else {
            $response['message'] = "No valid notification IDs provided.";
        }
    } else {
        $response['message'] = "Invalid or empty notification IDs array.";
    }
} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
?>