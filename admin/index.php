<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager', 'hr']);

$page_title = "Admin Panel";

// --- Fetch counts for dashboard cards ---

// Count active users
$users_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
$user_count = $users_stmt->fetchColumn();

// **FIXED**: Changed table to 'vacation_requests' and statuses to match the schema
$leave_stmt = $pdo->query("SELECT COUNT(*) FROM vacation_requests WHERE status IN ('pending_manager', 'pending_hr')");
$pending_leave_count = $leave_stmt->fetchColumn();

// Count pending attendance violations
$violations_stmt = $pdo->query("SELECT COUNT(*) FROM attendance_logs");
$violation_count = $violations_stmt->fetchColumn();


include __DIR__ . '/../app/templates/header.php';
?>

<h1 class="h3 mb-4"><?php echo htmlspecialchars($page_title); ?></h1>

<div class="row g-4">
    <!-- Users Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-0">Users</h5>
                        <small class="text-muted">Manage employee accounts and roles.</small>
                    </div>
                    <i class="bi bi-people-fill fs-1 text-primary"></i>
                </div>
                <h2 class="mt-auto mb-0 fw-bold"><?= $user_count ?></h2>
                <small class="text-muted">Active Users</small>
            </div>
            <div class="card-footer">
                <a href="users.php" class="stretched-link">Go to User Management <i class="bi bi-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>

    <!-- Attendance Logs Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-0 text-danger">Attendance Logs</h5>
                        <small class="text-muted">View and manage all attendance records.</small>
                    </div>
                    <i class="bi bi-clipboard-data-fill fs-1 text-danger"></i>
                </div>
                <h2 class="mt-auto mb-0 fw-bold"><?= $violation_count ?></h2>
                <small class="text-muted">Total Entries</small>
            </div>
            <div class="card-footer">
                <a href="attendance_logs.php" class="stretched-link">View Attendance Logs <i class="bi bi-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>
    
    <!-- Leave Management Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-0">Leave Management</h5>
                        <small class="text-muted">Manage leave types, accruals, and employee leave requests.</small>
                    </div>
                    <i class="bi bi-calendar-check fs-1 text-success"></i>
                </div>
            </div>
            <div class="card-footer">
                <a href="leave_management.php" class="stretched-link">Go to Leave Management <i class="bi bi-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>

    <!-- Departments Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-0">Departments</h5>
                        <small class="text-muted">Manage company departments.</small>
                    </div>
                    <i class="bi bi-building fs-1 text-info"></i>
                </div>
            </div>
            <div class="card-footer">
                <a href="departments.php" class="stretched-link">Manage Departments <i class="bi bi-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>

    <!-- Shift Management Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-0">Shift Management</h5>
                        <small class="text-muted">Configure and assign work shifts.</small>
                    </div>
                    <i class="bi bi-clock-history fs-1 text-warning"></i>
                </div>
            </div>
            <div class="card-footer">
                <a href="shifts.php" class="stretched-link">Manage Shifts <i class="bi bi-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>

    <?php if ($_SESSION['role'] === 'admin'): ?>
    <!-- Device Management Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-0">Device Management</h5>
                        <small class="text-muted">Manage fingerprint devices.</small>
                    </div>
                    <i class="bi bi-fingerprint fs-1 text-secondary"></i>
                </div>
            </div>
            <div class="card-footer">
                <a href="devices.php" class="stretched-link">Manage Devices <i class="bi bi-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>

    <!-- Audit Logs Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-0">Audit Logs</h5>
                        <small class="text-muted">Track all administrative actions.</small>
                    </div>
                    <i class="bi bi-journal-text fs-1 text-dark"></i>
                </div>
            </div>
            <div class="card-footer">
                <a href="audit_logs.php" class="stretched-link">View Audit Logs <i class="bi bi-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>