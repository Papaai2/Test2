<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php';

require_login();

$user_id = get_current_user_id();
$page_title = 'My Attendance Logs';
include __DIR__ . '/../app/templates/header.php';

$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to current date

// Fetch attendance logs for the current user with date filtering
$sql = "
    SELECT
        al.punch_time,
        al.punch_state,
        al.status,
        al.violation_type,
        s.shift_name
    FROM
        attendance_logs al
    LEFT JOIN
        shifts s ON al.shift_id = s.id
    WHERE
        al.user_id = ?
";

$params = [$user_id];

if (!empty($start_date)) {
    $sql .= " AND DATE(al.punch_time) >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $sql .= " AND DATE(al.punch_time) <= ?";
    $params[] = $end_date;
}

$sql .= " ORDER BY al.punch_time DESC";

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
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Violation</th>
                            <th>Shift</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d, Y H:i A', strtotime($log['punch_time']))) ?></td>
                                <td>
                                    <span class="badge <?= $log['punch_state'] == 0 ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $log['punch_state'] == 0 ? 'Punch In' : 'Punch Out' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(ucfirst($log['status'])) ?></td>
                                <td><?= htmlspecialchars($log['violation_type'] ?? 'N/A') ?></td>
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