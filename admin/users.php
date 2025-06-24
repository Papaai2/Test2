<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager', 'hr']);

$page_title = 'User Management';
$error_message = '';
$success_message = '';

$users = [];
$departments = [];
$roles = ['user', 'manager', 'hr', 'hr_manager', 'admin'];
$shifts = [];

try {
    // Fetch related data for forms
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $shifts = $pdo->query("SELECT id, shift_name FROM shifts ORDER BY shift_name")->fetchAll(PDO::FETCH_ASSOC);
    $managers = $pdo->query("SELECT u.id, u.full_name, u.role, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.role IN ('manager', 'admin', 'hr_manager') ORDER BY u.full_name")->fetchAll(PDO::FETCH_ASSOC);

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        try {
            $pdo->beginTransaction();

            if ($action === 'add' || $action === 'edit') {
                $full_name = trim($_POST['full_name']);
                $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                $role = in_array($_POST['role'], $roles) ? $_POST['role'] : 'user';
                $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                $shift_id = !empty($_POST['shift_id']) ? (int)$_POST['shift_id'] : null;
                $direct_manager_id = !empty($_POST['direct_manager_id']) ? (int)$_POST['direct_manager_id'] : null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $password = $_POST['password'];
                $employee_code = !empty(trim($_POST['employee_code'])) ? trim($_POST['employee_code']) : null;
                $contact_number = '';
                $emergency_contact_name = '';
                $emergency_contact_number = '';

                if (empty($full_name) || !$email) {
                    throw new Exception("Full name and a valid email are required.");
                }

                $userIdToCheck = ($action === 'edit') ? (int)$_POST['user_id'] : 0;

                // Check for unique email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $userIdToCheck]);
                if ($stmt->fetch()) {
                    throw new Exception("This email address is already in use.");
                }

                // Check for unique employee code if it's not empty
                if (!empty($employee_code)) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_code = ? AND id != ?");
                    $stmt->execute([$employee_code, $userIdToCheck]);
                    if ($stmt->fetch()) {
                        throw new Exception("This Employee Code is already in use.");
                    }
                }

                if ($action === 'add') {
                    if (empty($password)) {
                         throw new Exception("Password is required for new users.");
                    }
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (full_name, email, password, role, department_id, shift_id, direct_manager_id, is_active, employee_code, contact_number, emergency_contact_name, emergency_contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$full_name, $email, $password_hash, $role, $department_id, $shift_id, $direct_manager_id, $is_active, $employee_code, $contact_number, $emergency_contact_name, $emergency_contact_number]);
                    $success_message = 'User added successfully.';
                } else { // Edit action
                    $user_id = (int)$_POST['user_id'];
                    
                    // Temporarily disable foreign key checks to update the user code
                    $pdo->exec('SET foreign_key_checks=0');

                    $contact_number = trim($_POST['contact_number']);
                    $emergency_contact_name = trim($_POST['emergency_contact_name']);
                    $emergency_contact_number = trim($_POST['emergency_contact_number']);
 
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE users SET full_name = ?, email = ?, password = ?, role = ?, department_id = ?, shift_id = ?, direct_manager_id = ?, is_active = ?, employee_code = ?, contact_number = ?, emergency_contact_name = ?, emergency_contact_number = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$full_name, $email, $password_hash, $role, $department_id, $shift_id, $direct_manager_id, $is_active, $employee_code, $contact_number, $emergency_contact_name, $emergency_contact_number, $user_id]);
                    } else {
                        $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, department_id = ?, shift_id = ?, direct_manager_id = ?, is_active = ?, employee_code = ?, contact_number = ?, emergency_contact_name = ?, emergency_contact_number = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$full_name, $email, $role, $department_id, $shift_id, $direct_manager_id, $is_active, $employee_code, $contact_number, $emergency_contact_name, $emergency_contact_number, $user_id]);
                    }
                    
                    // IMPORTANT: Always re-enable foreign key checks
                    $pdo->exec('SET foreign_key_checks=1');

                    $success_message = 'User updated successfully.';
                }
            } elseif ($action === 'delete') {
                $user_id = (int)$_POST['user_id'];
                if ($user_id === 1 || $user_id === $_SESSION['user_id']) {
                     throw new Exception("This user cannot be deleted.");
                }
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $success_message = 'User deleted successfully.';
            }

            $pdo->commit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Re-enable checks in case of an error during the transaction
            $pdo->exec('SET foreign_key_checks=1');
            $error_message = $e->getMessage();
        }
    }

    // Fetch all users for display
    $search_query = $_GET['search'] ?? '';
    $sql = "
        SELECT u.*, d.name as department_name, s.shift_name, m.full_name as manager_name,
               u.contact_number, u.emergency_contact_name, u.emergency_contact_number
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN shifts s ON u.shift_id = s.id
        LEFT JOIN users m ON u.direct_manager_id = m.id
    ";
    $params = [];

    if (!empty($search_query)) {
        $sql .= " WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.employee_code LIKE ?";
        $search_param = '%' . $search_query . '%';
        $params = [$search_param, $search_param, $search_param];
    }

    $sql .= " ORDER BY u.full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include __DIR__ . '/../app/templates/header.php';
?>

<div class="container-fluid mt-4">
    <h1 class="h3 mb-4">User Management</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h2 class="h5 mb-0 me-auto p-2">All Users</h2>
            <form method="get" class="d-flex p-2 flex-grow-1 flex-md-grow-0 mb-2 mb-md-0">
                <input type="text" name="search" class="form-control me-2" placeholder="Search by name, email, or employee code..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit" class="btn btn-outline-secondary">Search</button>
            </form>
            <a href="import_users_csv.php" class="btn btn-info p-2 me-2">
                <i class="fas fa-file-csv"></i> Import Users (CSV)
            </a>
            <button type="button" class="btn btn-primary p-2" data-bs-toggle="modal" data-bs-target="#userModal">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>
        <div class="card-body">
            <div class="user-cards-container">
                <?php foreach ($users as $user): ?>
                    <div class="user-card card shadow-sm">
                        <div class="card-body">
                            <div class="user-card-header d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0 text-primary">
                                    <?= htmlspecialchars($user['full_name']) ?>
                                    <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-secondary' ?> ms-2">
                                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </h5>
                            </div>
                            <div class="user-card-details">
                                <p class="mb-1"><strong>Employee Code:</strong> <?= htmlspecialchars($user['employee_code'] ?? 'N/A') ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                                <p class="mb-1"><strong>Contact Number:</strong> <?= htmlspecialchars($user['contact_number'] ?? 'N/A') ?></p>
                                <p class="mb-1"><strong>Emergency Contact:</strong> <?= htmlspecialchars($user['emergency_contact_name'] ?? 'N/A') ?> (<?= htmlspecialchars($user['emergency_contact_number'] ?? 'N/A') ?>)</p>
                                <p class="mb-1"><strong>Role:</strong> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?></p>
                                <p class="mb-1"><strong>Department:</strong> <?= htmlspecialchars($user['department_name'] ?? 'N/A') ?></p>
                                <p class="mb-1"><strong>Shift:</strong> <?= htmlspecialchars($user['shift_name'] ?? 'N/A') ?></p>
                                <p class="mb-3"><strong>Manager:</strong> <?= htmlspecialchars($user['manager_name'] ?? 'N/A') ?></p>
                            </div>
                            <div class="user-card-actions d-flex justify-content-end gap-2">
                                <button class="btn btn-sm btn-outline-primary edit-user-btn" data-user='<?= htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8') ?>'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this user?');" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" <?= ($user['id'] === 1 || $user['id'] === $_SESSION['user_id']) ? 'disabled' : '' ?>>
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="userForm" method="post" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="user_id" id="userId" value="">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fullName" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fullName" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="employeeCode" class="form-label">Employee Code</label>
                        <input type="text" class="form-control" id="employeeCode" name="employee_code">
                        <div class="form-text">The unique ID from the fingerprint/attendance device. Must be unique.</div>
                    </div>

                    <div class="mb-3">
                        <label for="contactNumber" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="contactNumber" name="contact_number">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="emergencyContactName" class="form-label">Emergency Contact Name</label>
                            <input type="text" class="form-control" id="emergencyContactName" name="emergency_contact_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="emergencyContactNumber" class="form-label">Emergency Contact Number</label>
                            <input type="text" class="form-control" id="emergencyContactNumber" name="emergency_contact_number">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="form-text" id="passwordHelp">Leave blank to keep current password when editing.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role">
                                <?php foreach($roles as $role): ?>
                                    <option value="<?= $role ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departmentId" class="form-label">Department</label>
                            <select class="form-select" id="departmentId" name="department_id">
                                <option value="">None</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="shiftId" class="form-label">Shift</label>
                            <select class="form-select" id="shiftId" name="shift_id">
                                <option value="">None</option>
                                <?php foreach($shifts as $shift): ?>
                                    <option value="<?= $shift['id'] ?>"><?= htmlspecialchars($shift['shift_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="directManagerId" class="form-label">Direct Manager</label>
                        <select class="form-select" id="directManagerId" name="direct_manager_id">
                            <option value="">None</option>
                            <?php foreach($managers as $manager): ?>
                                <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['full_name']) ?> (<?php
    $displayRole = ucfirst(str_replace('_', ' ', $manager['role']));
    $displayDepartment = htmlspecialchars($manager['department_name'] ?? '');

    $parts = [];
    if (!empty($displayDepartment) && $displayDepartment !== 'N/A') {
        // Check if the department name is already part of the role name (e.g., "HR" in "HR Manager")
        if (stripos($displayRole, $displayDepartment) === false) {
            $parts[] = $displayDepartment;
        }
    }
    $parts[] = $displayRole;
    echo implode(' ', $parts);
?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="isActive" name="is_active" value="1" checked>
                        <label class="form-check-label" for="isActive">
                            User is Active
                        </label>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>