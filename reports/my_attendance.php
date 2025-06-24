<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php';

require_login();

$user_id = get_current_user_id();

if (!$user_id) {
    // Log the error for debugging
    error_log("User ID not found in session for my_attendance.php. Session might be stale or user deleted.");
    
    // Invalidate the session and redirect to login
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/'); // Destroy session cookie
    
    // Ensure BASE_URL is defined before using it
    if (!defined('BASE_URL')) {
        require_once __DIR__ . '/../app/core/config.php';
    }
    header("Location: " . BASE_URL . "/login.php?error=session_expired");
    exit('Session expired. Please log in again.');
}

$page_title = 'My Attendance Logs';
include __DIR__ . '/../app/templates/header.php';

$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to current date

// Fetch attendance logs for the current user with date filtering
$sql = "
    SELECT
        DATE(al.punch_time) AS attendance_date,
        MIN(CASE WHEN al.punch_state = 0 THEN al.punch_time END) AS first_in,
        MAX(CASE WHEN al.punch_state = 1 THEN al.punch_time END) AS last_out,
        GROUP_CONCAT(DISTINCT al.violation_type ORDER BY al.violation_type ASC SEPARATOR ', ') AS daily_violations,
        s.shift_name,
        s.start_time AS shift_start_time,
        s.end_time AS shift_end_time,
        s.is_night_shift
    FROM
        attendance_logs al
    LEFT JOIN
        shifts s ON al.shift_id = s.id
    WHERE
        al.user_id = ?
        AND DATE(al.punch_time) >= ?
        AND DATE(al.punch_time) <= ?
    GROUP BY
        attendance_date, al.user_id, s.shift_name, s.start_time, s.end_time, s.is_night_shift
    ORDER BY
        attendance_date DESC
";

$params = [$user_id, $start_date, $end_date];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">My Attendance Logs</h1>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Filter Records</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/reports/my_attendance.php" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-2"></i>Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Your Attendance Records</h5>
    </div>
    <div class="card-body">
        <?php if (empty($attendance_logs)): ?>
            <div class="alert alert-info" role="alert">
                No attendance records found for the selected date range.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>First In</th>
                            <th>Last Out</th>
                            <th>Status</th>
                            <th>Violations</th>
                            <th>Shift</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_logs as $log): ?>
                            <?php
                                $daily_status = 'Absent';
                                if ($log['first_in'] && $log['last_out']) {
                                    $daily_status = 'Present';
                                } elseif ($log['first_in'] || $log['last_out']) {
                                    $daily_status = 'Incomplete';
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d, Y', strtotime($log['attendance_date']))) ?></td>
                                <td><?= $log['first_in'] ? htmlspecialchars(date('h:i A', strtotime($log['first_in']))) : '<span class="text-muted">--</span>' ?></td>
                                <td><?= $log['last_out'] ? htmlspecialchars(date('h:i A', strtotime($log['last_out']))) : '<span class="text-muted">--</span>' ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($daily_status) ?>">
                                        <?= htmlspecialchars(getStatusText($daily_status)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log['daily_violations'])): ?>
                                        <?php
                                        $violations = explode(', ', $log['daily_violations']);
                                        foreach ($violations as $violation):
                                            echo '<span class="badge ' . getViolationBadgeClass($violation) . ' me-1">' . htmlspecialchars(getStatusText($violation)) . '</span>';
                                        endforeach;
                                        ?>
                                    <?php else: ?>
                                        <span class="badge bg-success">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['shift_name'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>