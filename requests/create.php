<?php
// in file: htdocs/requests/create.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php';

require_login();

$error = '';
$success = '';

$user_id = get_current_user_id();

$stmt_manager = $pdo->prepare("SELECT direct_manager_id FROM users WHERE id = ?");
$stmt_manager->execute([$user_id]);
$user_info = $stmt_manager->fetch();
$manager_id = $user_info['direct_manager_id'] ?? null;

// Fetch available leave types
$stmt_leave_types = $pdo->query("SELECT id, name FROM leave_types WHERE is_active = 1 ORDER BY name");
$leave_types = $stmt_leave_types->fetchAll();

// Fetch current leave balances for the user
$user_balances = [];
$stmt_balances = $pdo->prepare("SELECT leave_type_id, balance_days FROM leave_balances WHERE user_id = ?");
$stmt_balances->execute([$user_id]);
foreach ($stmt_balances->fetchAll() as $balance) {
    $user_balances[$balance['leave_type_id']] = $balance['balance_days'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    $leave_type_id = $_POST['leave_type_id'] ?? null;
    $today = date('Y-m-d');

    if (empty($start_date) || empty($end_date) || empty($leave_type_id)) {
        $error = 'Start date, end date, and leave type are required.';
    } elseif (strtotime($start_date) < strtotime($today)) {
        $error = 'Start date cannot be in the past.';
    } elseif (!$manager_id) {
        $error = 'You do not have a direct manager assigned. Please contact an administrator.';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error = 'End date cannot be before the start date.';
    } else {
        // Check for overlapping vacation requests
        $stmt_overlap = $pdo->prepare("
            SELECT COUNT(*) FROM vacation_requests
            WHERE user_id = :user_id
            AND status IN ('pending_manager', 'pending_hr', 'approved') -- Only check active/pending requests
            AND (
                (:start_date <= end_date AND :end_date >= start_date)
            )
        ");
        $stmt_overlap->execute([
            ':user_id' => $user_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ]);
        $overlap_count = $stmt_overlap->fetchColumn();

        if ($overlap_count > 0) {
            $error = 'You already have an active vacation request that overlaps with the selected dates.';
        } else {
            // Calculate number of days requested
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $requested_days = $interval->days + 1; // +1 to include the end date

            // Check if user has sufficient leave balance for the selected leave type
            if (!isset($user_balances[$leave_type_id]) || $user_balances[$leave_type_id] < $requested_days) {
                $error = 'Insufficient leave balance for the selected leave type. Requested: ' . $requested_days . ' days, Available: ' . ($user_balances[$leave_type_id] ?? 0) . ' days.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // MODIFIED: Added duration_days to the INSERT statement
                    $attachment_paths = [];
                    if (isset($_FILES['attachment']) && is_array($_FILES['attachment']['error'])) {
                        $files = $_FILES['attachment'];
                        $upload_dir = __DIR__ . '/../uploads/';

                        for ($i = 0; $i < count($files['name']); $i++) {
                            if ($files['error'][$i] == 0) {
                                $file_name = $files['name'][$i];
                                $file_tmp_name = $files['tmp_name'][$i];
                                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                                $stored_name = uniqid('attachment_', true) . '.' . $file_extension;
                                $destination = $upload_dir . $stored_name;

                                if (move_uploaded_file($file_tmp_name, $destination)) {
                                    $attachment_paths[] = $stored_name;
                                } else {
                                    throw new Exception('Failed to move uploaded file: ' . $file_name);
                                }
                            } elseif ($files['error'][$i] != 4) { // Ignore "no file chosen" error
                                throw new Exception('Error uploading file: ' . $file_name . ' (Error code: ' . $files['error'][$i] . ')');
                            }
                        }
                    } elseif (isset($_FILES['attachment']['error']) && $_FILES['attachment']['error'] == 0) {
                        // Handle single file upload (for backward compatibility)
                        $file = $_FILES['attachment'];
                        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $stored_name = uniqid('attachment_', true) . '.' . $file_extension;
                        $upload_dir = __DIR__ . '/../uploads/';
                        $destination = $upload_dir . $stored_name;
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            $attachment_paths[] = $stored_name;
                        } else {
                            throw new Exception('Failed to move uploaded file.');
                        }
                    }
                    $attachment_path = implode(',', $attachment_paths);

                    $sql = "INSERT INTO vacation_requests (user_id, start_date, end_date, reason, manager_id, status, leave_type_id, duration_days, attachment_path) VALUES (?, ?, ?, ?, ?, 'pending_manager', ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$user_id, $start_date, $end_date, $reason, $manager_id, $leave_type_id, $requested_days, $attachment_path]);
                    $request_id = $pdo->lastInsertId();
                    
                    $user_name = $_SESSION['full_name'];

                    // Notify the Direct Manager
                    create_notification($pdo, $manager_id, "New vacation request from $user_name.", $request_id);

                    // Also notify all HR staff and HR Managers
                    $hr_users = $pdo->query("SELECT id FROM users WHERE role IN ('hr', 'hr_manager', 'admin')")->fetchAll();
                    foreach ($hr_users as $hr_user) {
                        create_notification($pdo, $hr_user['id'], "New request submitted by $user_name, awaiting manager review.", $request_id);
                    }
                    
                    $pdo->commit();
                    header('Location: ' . BASE_URL . '/requests/index.php?success=' . urlencode('Vacation request submitted successfully!'));
                    exit();

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'An error occurred: ' . $e->getMessage();
                    error_log("Error creating vacation request: " . $e->getMessage()); // Log the error for debugging
                }
            }
        }
    }
}

$page_title = 'Submit Vacation Request';
include __DIR__ . '/../app/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Submit Vacation Request</h1>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <?php if (!$manager_id): ?>
            <div class="alert alert-warning"><h4 class="alert-heading">Manager Not Assigned</h4><p>You cannot submit a request because you do not have a Direct Manager assigned to your profile. Please contact an Administrator to have one assigned.</p></div>
        <?php else: ?>
            <form action="<?= BASE_URL ?>/requests/create.php" method="post" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6"><label for="start_date" class="form-label">Start Date</label><input type="date" class="form-control" id="start_date" name="start_date" required min="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-6"><label for="end_date" class="form-label">End Date</label><input type="date" class="form-control" id="end_date" name="end_date" required min="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-12">
                        <label for="leave_type_id" class="form-label">Leave Type</label>
                        <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                            <option value="">Select Leave Type</option>
                            <?php foreach ($leave_types as $type): ?>
                                <option value="<?= htmlspecialchars($type['id']) ?>"><?= htmlspecialchars($type['name']) ?> (Balance: <?= $user_balances[$type['id']] ?? '0.00' ?> days)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label for="reason" class="form-label">Reason for Leave</label><textarea class="form-control" id="reason" name="reason" rows="4"></textarea></div>
                    <div class="col-12"><label for="attachment" class="form-label">Attach Document (Optional)</label><input class="form-control" type="file" id="attachment" name="attachment[]" multiple></div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill me-2"></i>Submit Request</button>
                    <a href="<?= BASE_URL ?>/requests/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../app/templates/footer.php'; ?>