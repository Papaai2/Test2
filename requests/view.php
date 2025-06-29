<?php
// in file: requests/view.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/helpers.php'; // Ensure helpers are included for getStatusBadgeClass

$request_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$request_id) {
    header('Location: /requests/index.php');
    exit();
}

$current_user_id = get_current_user_id();
$current_user_role = get_current_user_role();
$current_user_name = $_SESSION['full_name'];

// Fetch request details
$sql = "
    SELECT
        r.*,
        u.full_name AS user_name,
        u.email AS user_email,
        u.employee_code AS user_employee_code,
        m.full_name AS manager_name,
        lt.name AS leave_type_name,
        r.attachment_path
    FROM
        vacation_requests r
    JOIN
        users u ON r.user_id = u.id
    LEFT JOIN
        users m ON r.manager_id = m.id
    LEFT JOIN
        leave_types lt ON r.leave_type_id = lt.id
    WHERE
        r.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    // Request not found
    http_response_code(404);
    echo "<h1>404 Not Found</h1>";
    echo "The request you are looking for does not exist.";
    exit();
}

// Authorization check: Only owner, manager, HR, HR Manager, or Admin can view
if ($request['user_id'] !== $current_user_id && $request['manager_id'] !== $current_user_id &&
    !in_array($current_user_role, ['hr', 'hr_manager', 'admin'])) {
    http_response_code(403);
    echo "<h1>403 Forbidden</h1>";
    echo "You do not have permission to view this request.";
    exit();
}

// FIX: Calculate the duration of the leave request in days (inclusive)
$startDate = new DateTime($request['start_date']);
$endDate = new DateTime($request['end_date']);
// We add 1 because the `diff` method doesn't include the last day
$request['duration_days'] = $endDate->diff($startDate)->days + 1;

// Fetch the leave balance for the user who made the request
$current_balance_before_request = null;
$stmt_balance = $pdo->prepare("
    SELECT balance_days
    FROM leave_balances
    WHERE user_id = ? AND leave_type_id = ?
");
$stmt_balance->execute([$request['user_id'], $request['leave_type_id']]);
$current_balance_before_request = $stmt_balance->fetchColumn();

$projected_balance_after_request = ($current_balance_before_request !== null) ?
                                   ($current_balance_before_request - $request['duration_days']) :
                                   null;

// Handle request actions (approve, reject, cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $comment_text = sanitize_input($_POST['comment'] ?? '');

    $new_status = $request['status']; // Default to current status

    try {
        $pdo->beginTransaction();

        switch ($action) {
            case 'approve_manager':
                if ($request['status'] === 'pending_manager' && ($current_user_role === 'manager' || $current_user_role === 'hr_manager' || $current_user_role === 'admin')) {
                    $new_status = 'pending_hr';
                    create_notification($pdo, get_hr_user_id($pdo), "Leave request for " . $request['user_name'] . " (ID: {$request['user_id']}) has been approved by their manager.", $request_id);
                    create_notification($pdo, $request['user_id'], "Your leave request (#{$request_id}) has been approved by your manager.", $request_id);
                    log_audit_action($pdo, 'approve_request_manager', "Manager approved request #{$request_id} for user '{$request['user_name']}'. Status changed to 'pending_hr'.");
                }
                break;

            case 'approve_hr':
                if ($request['status'] === 'pending_hr' && in_array($current_user_role, ['hr', 'hr_manager', 'admin'])) {
                    $new_status = 'approved';
                    // Deduct leave balance
                    $stmt_balance = $pdo->prepare("UPDATE leave_balances SET balance_days = balance_days - ?, last_updated_at = NOW() WHERE user_id = ? AND leave_type_id = ?");
                    $stmt_balance->execute([$request['duration_days'], $request['user_id'], $request['leave_type_id']]);
                    
                    create_notification($pdo, $request['user_id'], "Your leave request (#{$request_id}) has been fully approved by HR.", $request_id);
                    create_notification($pdo, $request['manager_id'], "Leave request for " . $request['user_name'] . " (#{$request_id}) has been fully approved by HR.", $request_id);
                    log_audit_action($pdo, 'approve_request_hr', "HR approved request #{$request_id} for user '{$request['user_name']}'. Final status: 'approved'.");
                }
                break;

            case 'reject':
                if (in_array($request['status'], ['pending_manager', 'pending_hr']) && in_array($current_user_role, ['manager', 'hr', 'hr_manager', 'admin'])) {
                    $new_status = 'rejected';
                    create_notification($pdo, $request['user_id'], "Your leave request (#{$request_id}) has been rejected.", $request_id);
                     if ($request['status'] === 'pending_hr' || in_array($current_user_role, ['hr', 'hr_manager', 'admin'])) {
                        create_notification($pdo, get_hr_user_id($pdo), "Leave request for " . $request['user_name'] . " (#{$request_id}) has been rejected.", $request_id);
                    }
                    if ($request['status'] === 'pending_manager' && $request['manager_id'] !== $current_user_id) { // If HR/Admin rejects at manager stage
                        create_notification($pdo, $request['manager_id'], "Leave request for " . $request['user_name'] . " (#{$request_id}) was rejected by an admin/HR.", $request_id);
                    }
                    log_audit_action($pdo, 'reject_request', "Rejected request #{$request_id} for user '{$request['user_name']}'.");
                }
                break;

            case 'cancel':
                if (($request['user_id'] === $current_user_id || in_array($current_user_role, ['hr', 'hr_manager', 'admin'])) && $request['status'] !== 'approved' && $request['status'] !== 'rejected') {
                    $new_status = 'cancelled';
                     if ($request['manager_id']) {
                         create_notification($pdo, $request['manager_id'], "Leave request for " . $request['user_name'] . " (#{$request_id}) has been cancelled.", $request_id);
                    }
                    if (in_array($request['status'], ['pending_hr', 'approved'])) { 
                        create_notification($pdo, get_hr_user_id($pdo), "Leave request for " . $request['user_name'] . " (#{$request_id}) has been cancelled.", $request_id);
                    }
                    log_audit_action($pdo, 'cancel_request', "Request #{$request_id} for user '{$request['user_name']}' was cancelled.");
                } else if ($request['user_id'] === $current_user_id && $request['status'] === 'approved') {
                    $new_status = 'pending_cancellation_hr';
                    create_notification($pdo, get_hr_user_id($pdo), "Leave request for " . $request['user_name'] . " (#{$request_id}) (approved) has been requested for cancellation.", $request_id);
                    create_notification($pdo, $request['user_id'], "Your approved leave request (#{$request_id}) cancellation is pending HR review.", $request_id);
                    log_audit_action($pdo, 'request_cancel_approved', "User '{$request['user_name']}' requested to cancel their approved request #{$request_id}.");
                }
                break;
            
            case 'revert_approved_cancellation':
                if ($request['status'] === 'pending_cancellation_hr' && in_array($current_user_role, ['hr', 'hr_manager', 'admin'])) {
                    $new_status = 'cancelled'; 
                    $stmt_balance = $pdo->prepare("UPDATE leave_balances SET balance_days = balance_days + ?, last_updated_at = NOW() WHERE user_id = ? AND leave_type_id = ?");
                    $stmt_balance->execute([$request['duration_days'], $request['user_id'], $request['leave_type_id']]);
                    
                    create_notification($pdo, $request['user_id'], "Your approved leave request (#{$request_id}) has been successfully cancelled by HR and balance reverted.", $request_id);
                    create_notification($pdo, $request['manager_id'], "Leave request for " . $request['user_name'] . " (#{$request_id}) has been cancelled by HR and balance reverted.", $request_id);
                    log_audit_action($pdo, 'revert_approved_cancel', "HR finalized cancellation for request #{$request_id} and reverted leave balance.");
                }
                break;
            
            case 'add_comment':
                if (!empty($comment_text)) {
                    $stmt_comment = $pdo->prepare("INSERT INTO request_comments (request_id, user_id, comment) VALUES (?, ?, ?)");
                    $stmt_comment->execute([$request_id, $current_user_id, $comment_text]);
                    
                    if ($current_user_id !== $request['user_id']) create_notification($pdo, $request['user_id'], "A comment was added to your request (#{$request_id}).", $request_id);
                    if ($current_user_id !== $request['manager_id'] && $request['manager_id']) create_notification($pdo, $request['manager_id'], "A comment was added to a team request (#{$request_id}) for " . $request['user_name'] . ".", $request_id);
                    if (!in_array($current_user_role, ['hr', 'hr_manager', 'admin'])) {
                         create_notification($pdo, get_hr_user_id($pdo), "A comment was added to request (#{$request_id}) for " . $request['user_name'] . ".", $request_id);
                    }
                    log_audit_action($pdo, 'add_request_comment', "User '{$current_user_name}' added a comment to request #{$request_id}.");
                }
                break;
        }

        // Update request status if changed
        if ($new_status !== $request['status']) {
            $sql_update_status = "UPDATE vacation_requests SET status = ?";
            $params_update_status = [$new_status];
            
            if (in_array($new_status, ['approved', 'rejected'])) {
                $sql_update_status .= ", action_at = NOW()";
            }
            
            $sql_update_status .= " WHERE id = ?";
            $params_update_status[] = $request_id;

            $stmt_update_status = $pdo->prepare($sql_update_status);
            $stmt_update_status->execute($params_update_status);
            $request['status'] = $new_status; // Update local request object
        }

        $pdo->commit();
        header("Location: view.php?id={$request_id}&success=Action completed successfully.");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
        error_log("Error processing request action: " . $e->getMessage());
    }
}

// Fetch comments for this request
$stmt_comments = $pdo->prepare("
    SELECT rc.*, u.full_name AS commenter_name, u.role AS commenter_role
    FROM request_comments rc
    JOIN users u ON rc.user_id = u.id
    WHERE rc.request_id = ?
    ORDER BY rc.created_at ASC
");
$stmt_comments->execute([$request_id]);
$comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);

// Helper to get HR user ID for notifications
function get_hr_user_id(PDO $pdo) {
    $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('hr', 'hr_manager') LIMIT 1");
    return $stmt->fetchColumn();
}


$page_title = 'View Request #' . $request['id'];
include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Leave Request for <?= htmlspecialchars($request['user_name']) ?> (Code: <?= htmlspecialchars($request['user_employee_code'] ?? 'N/A') ?>)</h1>
    <span class="badge rounded-pill <?= getStatusBadgeClass($request['status']) ?> fs-5">
        <?= getStatusText($request['status']) ?>
    </span>
</div>

<?php if (isset($_GET['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
<?php if (isset($error_message) && $error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Request Details</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <strong>Employee:</strong> <?= htmlspecialchars($request['user_name']) ?> (Code: <?= htmlspecialchars($request['user_employee_code'] ?? 'N/A') ?>)
            </div>
            <div class="col-md-6 mb-3">
                <strong>Employee Email:</strong> <?= htmlspecialchars($request['user_email']) ?>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Leave Type:</strong> <?= htmlspecialchars($request['leave_type_name'] ?? 'N/A') ?>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Manager:</strong> <?= htmlspecialchars($request['manager_name'] ?? 'N/A') ?>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Start Date:</strong> <?= date('M d, Y', strtotime($request['start_date'])) ?>
            </div>
            <div class="col-md-6 mb-3">
                <strong>End Date:</strong> <?= date('M d, Y', strtotime($request['end_date'])) ?>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Duration:</strong> <?= htmlspecialchars($request['duration_days'] ?? '') ?> days
            </div>
            <div class="col-md-6 mb-3">
                <strong>Requested On:</strong> <?= date('M d, Y H:i A', strtotime($request['created_at'])) ?>
            </div>
            <div class="col-12 mb-3">
                <strong>Reason:</strong> <?= htmlspecialchars($request['reason']) ?>
            </div>
            <?php if (!empty($request['attachment_path'])): ?>
            <div class="col-12 mb-3">
                <strong>Attachment:</strong> <a href="<?= BASE_URL ?>/download.php?file=<?= urlencode($request['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i> Download Attachment</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (in_array($current_user_role, ['manager', 'hr', 'hr_manager', 'admin'])): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Leave Balance Information</h5>
    </div>
    <div class="card-body">
        <p class="mb-1"><strong>Leave Type:</strong> <?= htmlspecialchars($request['leave_type_name'] ?? 'N/A') ?></p>
        <p class="mb-1"><strong>Requested Days:</strong> <?= htmlspecialchars($request['duration_days'] ?? '') ?> days</p>
        <p class="mb-1"><strong>Balance Before Request:</strong>
            <?= htmlspecialchars(number_format($current_balance_before_request ?? 0, 1)) ?> days
        </p>
        <p class="mb-1"><strong>Projected Balance After Request:</strong>
            <?= htmlspecialchars(number_format($projected_balance_after_request ?? 0, 1)) ?> days
        </p>
        <?php if (($projected_balance_after_request ?? 0) < 0): ?>
            <div class="alert alert-warning mt-3 mb-0">
                <strong>Warning:</strong> Insufficient leave balance for this request!
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Actions</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/requests/view.php?id=<?= htmlspecialchars($request['id']) ?>" method="POST">
            <div class="d-flex flex-wrap gap-2">
                <?php if ($request['status'] === 'pending_manager' && ($request['manager_id'] === $current_user_id || in_array($current_user_role, ['hr_manager', 'admin']))): ?>
                    <button type="submit" name="action" value="approve_manager" class="btn btn-success"><i class="bi bi-check-circle me-2"></i>Approve (Manager)</button>
                <?php endif; ?>

                <?php if ($request['status'] === 'pending_hr' && in_array($current_user_role, ['hr', 'hr_manager', 'admin'])): ?>
                    <button type="submit" name="action" value="approve_hr" class="btn btn-success"><i class="bi bi-check-circle me-2"></i>Approve (HR)</button>
                <?php endif; ?>

                <?php if (in_array($request['status'], ['pending_manager', 'pending_hr']) && in_array($current_user_role, ['manager', 'hr', 'hr_manager', 'admin'])): ?>
                    <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this request?');"><i class="bi bi-x-circle me-2"></i>Reject</button>
                <?php endif; ?>
                
                <?php if (($request['user_id'] === $current_user_id || in_array($current_user_role, ['hr', 'hr_manager', 'admin'])) && $request['status'] !== 'approved' && $request['status'] !== 'rejected' && $request['status'] !== 'cancelled' && $request['status'] !== 'pending_cancellation_hr'): ?>
                    <button type="submit" name="action" value="cancel" class="btn btn-secondary" onclick="return confirm('Are you sure you want to cancel this request?');"><i class="bi bi-slash-circle me-2"></i>Cancel Request</button>
                <?php endif; ?>

                <?php if ($request['user_id'] === $current_user_id && $request['status'] === 'approved'): ?>
                    <button type="submit" name="action" value="cancel" class="btn btn-secondary" onclick="return confirm('This request is already approved. Are you sure you want to request cancellation? HR will need to approve this cancellation to revert leave balance.');"><i class="bi bi-slash-circle me-2"></i>Request Cancellation</button>
                <?php endif; ?>

                <?php if ($request['status'] === 'pending_cancellation_hr' && in_array($current_user_role, ['hr', 'hr_manager', 'admin'])): ?>
                    <button type="submit" name="action" value="revert_approved_cancellation" class="btn btn-success" onclick="return confirm('Are you sure you want to finalize this cancellation and revert the leave balance?');"><i class="bi bi-arrow-counterclockwise me-2"></i>Confirm Cancellation & Revert Balance</button>
                <?php endif; ?>

            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Comments</h5>
    </div>
    <div class="card-body">
        <?php if (empty($comments)): ?>
            <p class="text-muted">No comments yet.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($comments as $comment): ?>
                    <li class="list-group-item">
                        <small class="text-muted d-block">
                            <strong><?= htmlspecialchars($comment['commenter_name']) ?> (<?= htmlspecialchars(ucfirst($comment['commenter_role'])) ?>)</strong> on <?= date('M d, Y H:i A', strtotime($comment['created_at'])) ?>
                        </small>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($comment['comment'] ?? '')) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/requests/view.php?id=<?= htmlspecialchars($request['id']) ?>" method="POST">
            <input type="hidden" name="action" value="add_comment">
            <div class="mb-3">
                <label for="comment" class="form-label">Add a Comment</label>
                <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Comment</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>