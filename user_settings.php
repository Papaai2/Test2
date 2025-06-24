<?php
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/helpers.php';

require_login();

$error_message = '';
$success_message = '';
$user_id = get_current_user_id();

// Fetch user details for display and potential updates
$stmt_user = $pdo->prepare("
    SELECT
        u.full_name, u.email, u.employee_code, u.role,
        u.contact_number, u.emergency_contact_name, u.emergency_contact_number,
        d.name AS department_name,
        s.shift_name,
        m.full_name AS manager_name
    FROM
        users u
    LEFT JOIN
        departments d ON u.department_id = d.id
    LEFT JOIN
        shifts s ON u.shift_id = s.id
    LEFT JOIN
        users m ON u.direct_manager_id = m.id
    WHERE
        u.id = ?
");
$stmt_user->execute([$user_id]);
$user_details = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user_details) {
    // Handle case where user details are not found (shouldn't happen if require_login works)
    http_response_code(404);
    exit('User details not found.');
}

$personal_info_error = '';
$personal_info_success = '';
$password_error = '';
$password_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_personal_info') {
        $new_full_name = trim($_POST['full_name'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $new_contact_number = trim($_POST['contact_number']);
        $new_emergency_contact_name = trim($_POST['emergency_contact_name']);
        $new_emergency_contact_number = trim($_POST['emergency_contact_number']);

        $current_role = get_current_user_role();
        $can_edit_restricted_fields = in_array($current_role, ['admin', 'hr', 'hr_manager']);

        // If not authorized to edit restricted fields, use current values from DB
        if (!$can_edit_restricted_fields) {
            $new_full_name = $user_details['full_name'];
            $new_email = $user_details['email'];
        }

        // Validate only if the fields are editable or if they are being changed by an authorized user
        if ($can_edit_restricted_fields) {
            if (empty($new_full_name)) {
                $personal_info_error = 'Full name is required.';
            }
            if (empty($new_email)) {
                $personal_info_error = 'Email is required.';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $personal_info_error = 'Invalid email format.';
            }
        }

        if (empty($personal_info_error)) { // Proceed only if no validation error
            try {
                // Check if email already exists for another user, only if email is being changed
                if ($new_email !== $user_details['email']) {
                    $stmt_check_email = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt_check_email->execute([$new_email, $user_id]);
                    if ($stmt_check_email->fetch()) {
                        $personal_info_error = 'This email is already registered to another account.';
                    }
                }

                if (empty($personal_info_error)) { // Proceed only if no email conflict error
                    $sql = "UPDATE users SET full_name = ?, email = ?, contact_number = ?, emergency_contact_name = ?, emergency_contact_number = ? WHERE id = ?";
                    $pdo->prepare($sql)->execute([$new_full_name, $new_email, $new_contact_number, $new_emergency_contact_name, $new_emergency_contact_number, $user_id]);
                    $personal_info_success = 'Your personal information has been successfully updated.';
                    // Update session and local user_details array
                    $_SESSION['full_name'] = $new_full_name;
                    $user_details['full_name'] = $new_full_name;
                    $user_details['email'] = $new_email;
                    $user_details['contact_number'] = $new_contact_number;
                    $user_details['emergency_contact_name'] = $new_emergency_contact_name;
                    $user_details['emergency_contact_number'] = $new_emergency_contact_number;
                }
            } catch (PDOException $e) {
                $personal_info_error = 'A database error occurred. Could not update personal information.';
                error_log("Personal info update error for user ID {$user_id}: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Fetch current hashed password from DB
        $stmt_password = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_password->execute([$user_id]);
        $hashed_current_password = $stmt_password->fetchColumn();

        if (!password_verify($current_password, $hashed_current_password)) {
            $password_error = 'Current password is incorrect.';
        } elseif (empty($new_password) || empty($confirm_password)) {
            $password_error = 'Please enter and confirm your new password.';
        } elseif ($new_password !== $confirm_password) {
            $password_error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $password_error = 'New password must be at least 8 characters long.';
        } else {
            try {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?";
                $pdo->prepare($sql)->execute([$hashed_new_password, $user_id]);
                $password_success = 'Your password has been successfully updated.';
            } catch (PDOException $e) {
                $password_error = 'A database error occurred. Could not update password.';
                error_log("Password change error for user ID {$user_id}: " . $e->getMessage());
            }
        }
    }
}

$page_title = 'User Settings';
include __DIR__ . '/app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">User Settings</h1>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Personal Information</h5>
    </div>
    <div class="card-body">
        <?php if ($personal_info_success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($personal_info_success) ?></div>
        <?php endif; ?>
        <?php if ($personal_info_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($personal_info_error) ?></div>
        <?php endif; ?>
        
        <form action="user_settings.php" method="post">
            <input type="hidden" name="action" value="update_personal_info">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <?php $is_editable = in_array(get_current_user_role(), ['admin', 'hr', 'hr_manager']); ?>
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($user_details['full_name']) ?>" <?= $is_editable ? '' : 'readonly disabled' ?> required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user_details['email']) ?>" <?= $is_editable ? '' : 'readonly disabled' ?> required>
            </div>
            <div class="mb-3">
                <label for="employee_code" class="form-label">Employee Code</label>
                <input type="text" class="form-control" id="employee_code" value="<?= htmlspecialchars($user_details['employee_code'] ?? 'N/A') ?>" readonly disabled>
            </div>
            <div class="mb-3">
                <label for="contact_number" class="form-label">Contact Number</label>
                <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?= htmlspecialchars($user_details['contact_number'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= htmlspecialchars($user_details['emergency_contact_name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="emergency_contact_number" class="form-label">Emergency Contact Number</label>
                <input type="text" class="form-control" id="emergency_contact_number" name="emergency_contact_number" value="<?= htmlspecialchars($user_details['emergency_contact_number'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="department" class="form-label">Department</label>
                <input type="text" class="form-control" id="department" value="<?= htmlspecialchars($user_details['department_name'] ?? 'N/A') ?>" readonly disabled>
            </div>
            <div class="mb-3">
                <label for="shift" class="form-label">Shift</label>
                <input type="text" class="form-control" id="shift" value="<?= htmlspecialchars($user_details['shift_name'] ?? 'N/A') ?>" readonly disabled>
            </div>
            <div class="mb-3">
                <label for="manager" class="form-label">Direct Manager</label>
                <input type="text" class="form-control" id="manager" value="<?= htmlspecialchars($user_details['manager_name'] ?? 'N/A') ?>" readonly disabled>
            </div>
            <button type="submit" class="btn btn-primary">Update Personal Info</button>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4" id="change-password-section">
    <div class="card-header">
        <h5 class="card-title mb-0">Change Password</h5>
    </div>
    <div class="card-body">
        <?php if ($password_success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($password_success) ?></div>
        <?php endif; ?>
        <?php if ($password_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($password_error) ?></div>
        <?php endif; ?>
        
        <form action="user_settings.php" method="post">
            <input type="hidden" name="action" value="change_password">
            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary">Change Password</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/app/templates/footer.php'; ?>