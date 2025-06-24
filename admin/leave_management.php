<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager', 'hr']);

$page_title = "Leave Management";
$feedback = ['success' => '', 'error' => ''];
$search_term = trim($_GET['search'] ?? '');

try {
    // --- FORM PROCESSING for LEAVE TYPE CRUD ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add_leave_type', 'edit_leave_type', 'delete_leave_type'])) {
        $action = $_POST['action'];
        $pdo->beginTransaction();
        try {
            if ($action === 'add_leave_type' || $action === 'edit_leave_type') {
                $type_name = trim($_POST['name'] ?? '');
                $accrual_days = filter_input(INPUT_POST, 'accrual_days_per_year', FILTER_VALIDATE_FLOAT);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $type_id = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);
                if (empty($type_name) || $accrual_days === false) {
                    throw new Exception("Leave type name and a valid accrual days value are required.");
                }
                if ($action === 'add_leave_type') {
                    $stmt = $pdo->prepare("INSERT INTO leave_types (name, accrual_days_per_year, is_active) VALUES (?, ?, ?)");
                    $stmt->execute([$type_name, $accrual_days, $is_active]);
                    $feedback['success'] = "Leave type added successfully.";
                } else {
                    if (empty($type_id)) throw new Exception("Invalid Leave Type ID for editing.");
                    $stmt = $pdo->prepare("UPDATE leave_types SET name = ?, accrual_days_per_year = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$type_name, $accrual_days, $is_active, $type_id]);
                    $feedback['success'] = "Leave type updated successfully.";
                }
            } elseif ($action === 'delete_leave_type') {
                $type_id = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);
                if (empty($type_id)) throw new Exception("Invalid Leave Type ID for deletion.");
                $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = ?");
                $stmt->execute([$type_id]);
                $feedback['success'] = "Leave type deleted successfully.";
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    // --- DATA FETCHING for display ---
    $leave_types = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $users = $pdo->query("SELECT id, full_name, employee_code FROM users WHERE is_active = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // --- DATA FETCHING for balances with search functionality ---
    $params = [];
    $sql_balances = "
        SELECT
            u.id as user_id,
            u.full_name,
            u.employee_code,
            u.email,
            lt.id as leave_type_id,
            lt.name as leave_type_name,
            COALESCE(lb.balance_days, 0) as balance_days,
            lb.last_accrual_date
        FROM
            users u
        CROSS JOIN
            leave_types lt
        LEFT JOIN
            leave_balances lb ON u.id = lb.user_id AND lt.id = lb.leave_type_id
        WHERE
            u.is_active = 1
    ";

    if (!empty($search_term)) {
        $sql_balances .= " AND (u.full_name LIKE ? OR u.employee_code LIKE ? OR u.email LIKE ?)";
        $like_term = "%{$search_term}%";
        array_push($params, $like_term, $like_term, $like_term);
    }
    
    $sql_balances .= " ORDER BY u.full_name, lt.name";
    
    $balances_stmt = $pdo->prepare($sql_balances);
    $balances_stmt->execute($params);
    $leave_balances = $balances_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $feedback['error'] = "An error occurred: " . $e->getMessage();
}

include __DIR__ . '/../app/templates/header.php';
?>

<div class="container mt-4">
    <h1 class="h3 mb-4">Leave Management</h1>

    <?php if (!empty($feedback['success'])): ?><div class="alert alert-success" role="alert"><?= htmlspecialchars($feedback['success']) ?></div><?php endif; ?>
    <?php if (!empty($feedback['error'])): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($feedback['error']) ?></div><?php endif; ?>
    <div id="ajax-feedback"></div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Leave Types</h2><button id="addLeaveTypeBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add New</button></div>
                <div class="card-body"><div class="table-responsive"><table class="table table-sm table-bordered table-hover table-leave-types"><thead class="table-light"><tr><th>Name</th><th>Days/Year</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                <?php foreach($leave_types as $type): ?>
                <tr>
                    <td><?= htmlspecialchars($type['name']) ?></td>
                    <td><?= htmlspecialchars($type['accrual_days_per_year']) ?></td>
                    <td><span class="badge <?= $type['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $type['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm py-0 px-1 edit-leave-type-btn" data-type='<?= htmlspecialchars(json_encode($type), ENT_QUOTES, 'UTF-8') ?>'><i class="fas fa-edit"></i></button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure? This may affect existing data.');"><input type="hidden" name="action" value="delete_leave_type"><input type="hidden" name="type_id" value="<?= $type['id'] ?>"><button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table></div></div>
            </div>
        </div>
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"><h2 class="h5 mb-0">Bulk & Group Actions</h2></div>
                <div class="card-body d-flex flex-column">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-3">
                        <button id="btn-annual-accrual" class="btn btn-info">Perform Annual Accrual</button>
                        <button id="btn-reset-balances" class="btn btn-danger">Reset All Balances to Zero</button>
                    </div><hr>
                    <h3 class="h6 mt-3">Adjust Balances for Selected Users</h3>
                    <p class="text-muted small">Select one or more users to set their balance for a specific leave type.</p>
                    <div class="row g-2 align-items-end flex-grow-1">
                        <div class="col-sm-12"><label for="bulk-users" class="form-label">Select Users (Ctrl+Click for multiple)</label><select id="bulk-users" class="form-select" multiple size="5"><?php foreach($users as $user): ?><option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-5"><label for="bulk-leave-type" class="form-label">Leave Type</label><select id="bulk-leave-type" class="form-select"><option value="">-- Select --</option><?php foreach($leave_types as $type): ?><option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label for="bulk-new-balance" class="form-label">Set New Balance</label><input type="number" step="0.5" id="bulk-new-balance" class="form-control" placeholder="e.g., 21"></div>
                        <div class="col-md-3"><button id="btn-adjust-balances" class="btn btn-primary w-100">Adjust</button></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h2 class="h5 mb-0">Current Leave Balances</h2></div>
        <div class="card-body">
            <form method="GET" action="" class="mb-3">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, employee code, or email..." value="<?= htmlspecialchars($search_term) ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($search_term)): ?>
                        <a href="leave_management.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="table-responsive"><table class="table table-bordered table-hover">
            <thead class="table-light"><tr><th>Employee</th><th>Leave Type</th><th>Balance (Days)</th><th>Last Accrual Date</th></tr></thead>
            <tbody>
            <?php if (empty($leave_balances)): ?>
                <tr><td colspan="4" class="text-center text-muted">No users or leave types found<?php if(!empty($search_term)) echo ' matching your search'; ?>.</td></tr>
            <?php else: ?>
                <?php foreach ($leave_balances as $balance): ?>
                <tr>
                    <td><?= htmlspecialchars($balance['full_name']) ?> (<?= htmlspecialchars($balance['employee_code']) ?>)</td>
                    <td><?= htmlspecialchars($balance['leave_type_name']) ?></td>
                    <td><?= htmlspecialchars($balance['balance_days']) ?></td>
                    <td><?= htmlspecialchars($balance['last_accrual_date'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table></div></div>
    </div>
</div>

<div class="modal fade" id="leaveTypeModal" tabindex="-1" aria-labelledby="leaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="leaveTypeForm" method="post">
                <div class="modal-header"><h5 class="modal-title" id="leaveTypeModalLabel">Add Leave Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value=""><input type="hidden" name="type_id" id="typeId" value="">
                    <div class="mb-3"><label for="typeName" class="form-label">Leave Type Name</label><input type="text" class="form-control" id="typeName" name="name" required></div>
                    <div class="mb-3"><label for="accrualDays" class="form-label">Accrual Days per Year</label><input type="number" step="0.5" class="form-control" id="accrualDays" name="accrual_days_per_year" required></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="isActive" name="is_active" value="1"><label class="form-check-label" for="isActive">Active</label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>