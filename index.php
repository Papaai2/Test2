<?php
// in file: htdocs/index.php

// Use the central bootstrap file to initialize the application
require_once __DIR__ . '/app/bootstrap.php';

require_login();

$user_id = get_current_user_id();
if (!$user_id) {
    echo "<div class='alert alert-danger'>You are not logged in. Please <a href='" . BASE_URL . "/login.php'>login</a>.</div>";
    include __DIR__ . '/app/templates/footer.php';
    exit;
}
$user_role = get_current_user_role();
$pending_manager_requests = [];
$pending_hr_requests = [];
$hr_view_pending_manager = [];

// Query to fetch user's leave balances
$stmt_my_balances = $pdo->prepare("
    SELECT
        lt.id as leave_type_id,
        lt.name as leave_type_name,
        COALESCE(lb.balance_days, 0) as balance_days,
        lb.user_id,
        lb.last_updated_at
    FROM
        leave_types lt
    LEFT JOIN
        leave_balances lb ON lt.id = lb.leave_type_id AND lb.user_id = :user_id
    WHERE
        lt.is_active = 1
    ORDER BY
        lt.name ASC
");

$stmt_my_balances->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_my_balances->execute();
$my_leave_balances = $stmt_my_balances->fetchAll(PDO::FETCH_ASSOC);

if ($user_role === 'manager' || $user_role === 'admin') {
    $stmt = $pdo->prepare("
        SELECT r.id, r.start_date, r.end_date, r.created_at, u.full_name AS user_name
        FROM vacation_requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'pending_manager' AND r.manager_id = ?
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$user_id]);
    $pending_manager_requests = $stmt->fetchAll();
}

if ($user_role === 'hr' || $user_role === 'admin' || $user_role === 'hr_manager') {
    $stmt = $pdo->prepare("
        SELECT r.id, r.start_date, r.end_date, r.status, r.created_at, u.full_name AS user_name
        FROM vacation_requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'pending_hr'
        ORDER BY r.created_at ASC
    ");
    $stmt->execute();
    $pending_hr_requests = $stmt->fetchAll();

    $stmt_hr_view = $pdo->query("
        SELECT r.id, r.start_date, r.end_date, r.created_at, u.full_name AS user_name, m.full_name as manager_name
        FROM vacation_requests r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN users m ON r.manager_id = m.id
        WHERE r.status = 'pending_manager'
        ORDER BY r.created_at ASC
    ");
    $hr_view_pending_manager = $stmt_hr_view->fetchAll();
}

$page_title = 'Dashboard';
include __DIR__ . '/app/templates/header.php';
?>

<div class="mb-5">
    <h1 class="display-5 fw-bold text-white mb-3">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
    <p class="lead text-muted mb-4">You are logged in as a(n) <span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?></span>.</p>
</div>

<div id="dashboard-stats" class="dashboard-stats mb-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number" id="pending-requests-count">0</div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number" id="approved-this-month-count">0</div>
                <div class="stat-label">Approved This Month</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number" id="team-members-count">0</div>
                <div class="stat-label">Team Members</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-5">
    <div class="col-lg-8">
        <?php if (!empty($pending_manager_requests) || !empty($pending_hr_requests)): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> You have requests that require your action.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($pending_manager_requests)): ?>
        <div class="card mb-5">
            <div class="card-header"><h2 class="h5 mb-0"><i class="fas fa-user-check me-2"></i>Manager Approval Queue</h2></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>Employee</th><th>Dates</th><th>Submitted</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($pending_manager_requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['user_name']) ?></td>
                                <td><?= date('M d', strtotime($request['start_date'])) . ' - ' . date('M d, Y', strtotime($request['end_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                <td><a href="<?= BASE_URL ?>/requests/view.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye me-1"></i> Review</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($pending_hr_requests)): ?>
        <div class="card mb-5">
            <div class="card-header"><h2 class="h5 mb-0"><i class="fas fa-clipboard-check me-2"></i>HR Final Approval Queue</h2></div>
            <div class="card-body">
                 <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>Employee</th><th>Dates</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($pending_hr_requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['user_name']) ?></td>
                                <td><?= date('M d', strtotime($request['start_date'])) . ' - ' . date('M d, Y', strtotime($request['end_date'])) ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($request['status']) ?>"><?= getStatusText($request['status']) ?></span></td>
                                <td><a href="<?= BASE_URL ?>/requests/view.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye me-1"></i> Review</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['hr', 'admin', 'hr_manager'])): ?>
        <?php if (!empty($hr_view_pending_manager)): ?>
            <div class="card mb-5">
                <div class="card-header"><h2 class="h5 mb-0"><i class="fas fa-hourglass-half me-2"></i>Awaiting Manager Action</h2></div>
                <div class="card-body">
                    <p class="text-muted">The following requests are currently being reviewed by their respective managers.</p>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead><tr><th>Employee</th><th>Manager</th><th>Submitted</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($hr_view_pending_manager as $request): ?>
                                <tr>
                                    <td><?= htmlspecialchars($request['user_name']) ?></td>
                                    <td><?= htmlspecialchars($request['manager_name'] ?? 'N/A') ?></td>
                                    <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                    <td><a href="<?= BASE_URL ?>/requests/view.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye me-1"></i> View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div id="calendar-widget" class="card calendar-widget mb-5">
            <div class="card-header calendar-header">
                <h3 class="h5 mb-0">Team Leave Calendar</h3>
                <div class="btn-group" role="group" aria-label="Calendar Navigation">
                    <button type="button" class="btn btn-sm" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="btn btn-sm" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="card-body">
<div class="calendar-grid-container">
    <div class="calendar-grid">
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>
    </div>
</div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-5">
            <div class="card-header"><h2 class="h5 mb-0"><i class="fas fa-chart-pie me-2"></i>My Leave Balances</h2></div>
            <div class="card-body">
                <?php if (empty($my_leave_balances)): ?>
                    <p class="text-muted">No leave balances found. Contact HR.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($my_leave_balances as $balance): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <?= htmlspecialchars($balance['leave_type_name']) ?>
                                    <div class="balance-progress mt-1">
                                        <div class="balance-progress-bar" role="progressbar" style="width: <?= min(100, ($balance['balance_days'] / 21) * 100) ?>%;" aria-valuenow="<?= htmlspecialchars($balance['balance_days']) ?>" aria-valuemin="0" aria-valuemax="21"></div>
                                    </div>
                                </div>
                                <span class="badge bg-info rounded-pill fs-6">
                                    <?= htmlspecialchars(number_format($balance['balance_days'], 1)) ?> days
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
         <div class="quick-actions card">
             <div class="card-body">
                <h3 class="h5 mb-4"><i class="fas fa-bolt me-2"></i>Quick Actions</h3>
                <a href="<?= BASE_URL ?>/requests/create.php" class="btn btn-primary w-100 mb-3 d-flex align-items-center justify-content-center gap-3">
                    <i class="fas fa-calendar-plus"></i> New Leave Request
                </a>
                <a href="<?= BASE_URL ?>/requests/index.php" class="btn btn-outline-primary w-100 mb-3 d-flex align-items-center justify-content-center gap-3">
                    <i class="fas fa-list-alt"></i> My Requests
                </a>
                <a href="<?= BASE_URL ?>/user_settings.php#change-password-section" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-3">
                    <i class="fas fa-key"></i> Change Password
                </a>
             </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/app/templates/footer.php'; ?>