<?php
// in file: htdocs/api/get_leave_calendar.php

header('Content-Type: application/json');

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_login();

// If year and month are not provided, default to current month/year
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('n'); // 'n' for month without leading zeros

try {
    // Basic validation for year and month
    if (!is_numeric($year) || !is_numeric($month) || $month < 1 || $month > 12) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid year or month.']);
        exit();
    }

    // Fetch leave data for the specified month for all users visible to the current user's role
    // This example fetches all approved leaves for the month, adjust visibility based on roles.
    $stmt = $pdo->prepare("
        SELECT vr.start_date, vr.end_date, u.full_name AS user_name, lt.name AS leave_type
        FROM vacation_requests vr
        JOIN users u ON vr.user_id = u.id
        JOIN leave_types lt ON vr.leave_type_id = lt.id
        WHERE vr.status = 'approved'
        AND (
            (YEAR(vr.start_date) = ? AND MONTH(vr.start_date) = ?)
            OR (YEAR(vr.end_date) = ? AND MONTH(vr.end_date) = ?)
            OR (vr.start_date < ? AND vr.end_date > ?)
        )
        AND (
            -- Employee: only show their own requests
            (? = 'user' AND vr.user_id = ?)
            OR
            -- Manager: show their own requests and their team members' requests
            (? = 'manager' AND (vr.user_id = ? OR u.direct_manager_id = ?))
            OR
            -- HR/Admin: show all requests
            (? IN ('hr', 'admin', 'hr_manager'))
        )
        ORDER BY vr.start_date ASC
    ");
    $first_day_of_month = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $last_day_of_month = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime($first_day_of_month));
    $stmt->execute([$year, $month, $year, $month, $first_day_of_month, $last_day_of_month, $_SESSION['role'], $_SESSION['user_id'], $_SESSION['role'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['role']]);
    $raw_leave_requests = $stmt->fetchAll();

    $leave_events_by_date = [];
    foreach ($raw_leave_requests as $request) {
        $current_date = new DateTime($request['start_date']);
        $end_date = new DateTime($request['end_date']);
        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');
            // Ensure date is within the requested month/year for display purposes
            if (intval($current_date->format('Y')) == $year && intval($current_date->format('n')) == $month) {
                if (!isset($leave_events_by_date[$date_str])) {
                    $leave_events_by_date[$date_str] = [];
                }
                // Add user to the list for this date if not already present
                $user_already_added = false;
                foreach ($leave_events_by_date[$date_str] as $existing_entry) {
                    if ($existing_entry['user_name'] === $request['user_name']) {
                        $user_already_added = true;
                        break;
                    }
                }
                if (!$user_already_added) {
                    $leave_events_by_date[$date_str][] = [
                        'user_name' => $request['user_name'],
                        'leave_type' => $request['leave_type']
                    ];
                }
            }
            $current_date->modify('+1 day');
        }
    }

    // Convert associative array to indexed array for JSON encoding
    $leave_events = [];
    foreach ($leave_events_by_date as $date => $events) {
        $leave_events[] = [
            'date' => $date,
            'users_on_leave' => $events // Each entry now contains a list of users for that day
        ];
    }

    echo json_encode([
        'success' => true,
        'current_year' => intval($year),   // Added to match JS expectation
        'current_month' => intval($month), // Added to match JS expectation
        'leave_events' => $leave_events    // Renamed from 'leaveDays'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>