<?php
// in file: app/core/helpers.php

/**
 * Sanitizes user input to prevent XSS.
 *
 * @param string|null $data The raw input data. Can be null.
 * @param string $type The type of data (e.g., 'string', 'email').
 * @return string The sanitized data.
 */
function sanitize_input(?string $data, $type = 'string') {
    // Handle null input gracefully for trim()
    if ($data === null) {
        return '';
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    if ($type === 'email') {
        $data = filter_var($data, FILTER_SANITIZE_EMAIL);
    }
    // Add more types as needed, e.g., 'int', 'float', 'url'

    return $data;
}

/**
 * Creates a notification for a specific user.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $user_id The ID of the user to notify.
 * @param string $message The notification message.
 * @param int|null $request_id The ID of the related request, if any.
 */
function create_notification(PDO $pdo, int $user_id, string $message, ?int $request_id = null) {
    // Avoid notifying a user about their own action if a session exists
    if (isset($_SESSION['user_id']) && $user_id == $_SESSION['user_id']) {
        return;
    }
    
    $sql = "INSERT INTO notifications (user_id, message, request_id) VALUES (?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $message, $request_id]);
    } catch (PDOException $e) {
        // In a real application, you should log this error instead of ignoring it.
        error_log("Failed to create notification: " . $e->getMessage());
    }
}

/**
 * Gets the Bootstrap badge class based on the request status.
 *
 * @param string $status The status of the request.
 * @return string The corresponding CSS class.
 */
function getStatusBadgeClass($status) {
    $map = [
        'pending_manager' => 'bg-warning text-dark',
        'pending_hr' => 'bg-info text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'cancelled' => 'bg-secondary',
        'pending_cancellation_hr' => 'bg-warning text-dark',
        // Add attendance log specific statuses
        'valid' => 'bg-success',
        'invalid' => 'bg-danger',
        '' => 'bg-light text-dark' // Handle empty string status as a default/neutral
    ];
    return $map[$status] ?? 'bg-light text-dark';
}

/**
 * Gets a human-readable text for a request status.
 *
 * @param string $status The status from the database.
 * @return string The display-friendly text.
 */
function getStatusText($status) {
    return ucwords(str_replace('_', ' ', $status));
}

/**
 * Logs an audit action to the audit_logs table.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $action The action performed (e.g., 'login', 'update_user').
 * @param string|null $details Optional details about the action (JSON or text).
 */
function log_audit_action(PDO $pdo, string $action, ?string $details = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    // User Agent and IP Address logging removed
    $sql = "INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $action, $details]);
    } catch (PDOException $e) {
        error_log("Failed to log audit action: " . $e->getMessage());
    }
}

function exportUserBalancesToCsv($users_data, $filename = "user_balances_report.csv") {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Name', 'Email', 'Role', 'Department', 'Employee Code', 'Leave Type', 'Balance (Days)']);

    foreach ($users_data as $user) {
        if ($user['balances_str']) {
            foreach (explode(';', $user['balances_str']) as $balance_pair) {
                @list($type_name, $balance) = explode(':', $balance_pair);
                if (isset($type_name) && isset($balance)) {
                    fputcsv($output, [
                        $user['full_name'], $user['email'], ucfirst($user['role']),
                        $user['department_name'] ?? 'N/A', $user['employee_code'] ?? 'N/A', $type_name, number_format((float)$balance, 2)
                    ]);
                }
            }
        } else {
            fputcsv($output, [
                $user['full_name'], $user['email'], ucfirst($user['role']),
                $user['department_name'] ?? 'N/A', $user['employee_code'] ?? 'N/A', 'N/A', '0.00'
            ]);
        }
    }
    fclose($output);
    exit();
}

function getViolationBadgeClass(?string $violationType): string {
    switch ($violationType) {
        case 'late_in':
        case 'early_out':
        case 'missing_punch': // If you introduce this later
            return 'bg-danger';
        case 'double_punch':
            return 'bg-warning text-dark'; // Use warning for less severe but notable issues
        case 'unmatched_punch': // If you introduce this later
            return 'bg-secondary';
        default:
            return 'bg-info text-dark'; // Default for unknown or generic issues
    }
}

/**
 * Redirects the user to a specified URL and terminates script execution.
 *
 * @param string $url The URL to redirect to.
 */
function redirect(string $url) {
    header("Location: " . $url);
    exit();
}