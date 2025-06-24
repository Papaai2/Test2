<?php
// in file: app/templates/header.php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/helpers.php'; // Ensure helpers are included for functions like getStatusBadgeClass
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME) : htmlspecialchars(SITE_NAME); ?></title>
    
    <link href="<?= BASE_URL ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
    <!-- Bootstrap Bundle with Popper -->
    <script src="<?= BASE_URL ?>/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <script>
        const BASE_URL = "<?= BASE_URL ?>";
    </script>
    <div class="d-flex" id="wrapper">

        <nav class="sidebar" id="sidebar-wrapper">
            <div class="sidebar-heading py-3 px-4 d-flex align-items-center justify-content-between">
                <a class="navbar-brand fw-bold m-0" href="/"><?php echo htmlspecialchars(SITE_NAME); ?></a>
                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="list-group list-group-flush pt-2">
                <?php
                // Function to check if a path is active
                function isActive($path) {
                    $current_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                    return $current_uri === $path;
                }
                ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/index.php" class="list-group-item list-group-item-action <?= isActive('/index.php') ? 'active' : '' ?>">
                        <i class="fas fa-home me-3"></i><span>Dashboard</span>
                    </a>
                    <a href="/requests/index.php" class="list-group-item list-group-item-action <?= isActive('/requests/index.php') || isActive('/requests/create.php') || isActive('/requests/view.php') ? 'active' : '' ?>">
                        <i class="fas fa-paper-plane me-3"></i><span>My Requests</span>
                    </a>
                    <a href="/reports/my_attendance.php" class="list-group-item list-group-item-action <?= isActive('/reports/my_attendance.php') ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check me-3"></i><span>My Attendance</span>
                    </a>
                    
                    <?php if (in_array($_SESSION['role'], ['manager', 'admin', 'hr_manager'])): ?>
                        <a class="list-group-item list-group-item-action dropdown-toggle <?= isActive('/reports/team.php') || isActive('/reports/manager_history.php') ? 'active' : '' ?>" href="#teamReportsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="<?= isActive('/reports/team.php') || isActive('/reports/manager_history.php') ? 'true' : 'false' ?>" aria-controls="teamReportsSubmenu">
                            <i class="fas fa-chart-line me-3"></i><span>Team Reports</span>
                        </a>
                        <div class="collapse <?= isActive('/reports/team.php') || isActive('/reports/manager_history.php') ? 'show' : '' ?>" id="teamReportsSubmenu">
                            <a class="list-group-item list-group-item-action ps-5 <?= isActive('/reports/team.php') ? 'active' : '' ?>" href="/reports/team.php">My Team</a>
                            <a class="list-group-item list-group-item-action ps-5 <?= isActive('/reports/manager_history.php') ? 'active' : '' ?>" href="/reports/manager_history.php">Team History</a>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($_SESSION['role'], ['hr', 'hr_manager', 'admin'])): ?>
                         <a class="list-group-item list-group-item-action dropdown-toggle <?= isActive('/reports/hr_history.php') || isActive('/reports/user_balances.php') || isActive('/reports/timesheet.php') ? 'active' : '' ?>" href="#hrReportsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="<?= isActive('/reports/hr_history.php') || isActive('/reports/user_balances.php') || isActive('/reports/timesheet.php') ? 'true' : 'false' ?>" aria-controls="hrReportsSubmenu">
                            <i class="fas fa-chart-bar me-3"></i><span>HR Reports</span>
                        </a>
                        <div class="collapse <?= isActive('/reports/hr_history.php') || isActive('/reports/user_balances.php') || isActive('/reports/timesheet.php') ? 'show' : '' ?>" id="hrReportsSubmenu">
                            <a class="list-group-item list-group-item-action ps-5 <?= isActive('/reports/hr_history.php') ? 'active' : '' ?>" href="/reports/hr_history.php">Full History</a>
                            <a class="list-group-item list-group-item-action ps-5 <?= isActive('/reports/user_balances.php') ? 'active' : '' ?>" href="/reports/user_balances.php">User Balances</a>
                            <div class="dropdown-divider my-1"></div> <!-- Replaced <hr> with <div> for consistent styling -->
                            <a class="list-group-item list-group-item-action ps-5 <?= isActive('/reports/timesheet.php') ? 'active' : '' ?>" href="/reports/timesheet.php">Daily Timesheet</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['role'], ['hr', 'hr_manager', 'admin'])): ?>
                        <a href="/admin/index.php" class="list-group-item list-group-item-action <?= strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? 'active' : '' ?>">
                            <i class="fas fa-cog me-3"></i><span>Admin Panel</span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/login.php" class="list-group-item list-group-item-action <?= isActive('/login.php') ? 'active' : '' ?>">
                        <i class="fas fa-sign-in-alt me-3"></i><span>Login</span>
                    </a>
                <?php endif; ?>
            </div>
             <div class="mt-auto p-3 text-center small text-muted">
                &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>
            </div>
        </nav>

        <div id="page-content-wrapper" class="d-flex flex-column flex-grow-1">
            <nav class="navbar navbar-expand-lg navbar-dark fixed-top py-0">
                <div class="container-fluid">
                    <button class="btn d-lg-none" id="sidebarToggle">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    
                    <a class="navbar-brand" href="/"><?php echo htmlspecialchars(SITE_NAME); ?></a> <!-- Show brand on desktop -->

                    <div class="d-flex align-items-center ms-auto">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <ul class="navbar-nav">
                                <li class="nav-item dropdown">
                                    <a class="nav-link" href="#" id="notification-bell" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-bell position-relative">
                                            <span id="notification-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill" style="display: none;"></span>
                                        </i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notification-bell" id="notification-dropdown">
                                        <li><h6 class="dropdown-header">Notifications</h6></li>
                                        <li><div id="notification-list"></div></li>
                                        <li><a class="dropdown-item text-center small text-muted" href="/notifications.php">View all</a></li>
                                    </ul>
                                </li>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-user-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (in_array($_SESSION['role'], ['admin'])): ?>
                                            <li><a class="dropdown-item" href="/admin/audit_logs.php"><i class="fas fa-file-alt me-2"></i>Audit Logs</a></li>
                                            <li><div class="dropdown-divider"></div></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item" href="/user_settings.php"><i class="fas fa-user-cog me-2"></i>Settings</a></li>
                                        <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                                </li>
                            </ul>
                        <?php else: ?>
                            <ul class="navbar-nav">
                                <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <main class="container-xl p-4 flex-grow-1">