<?php
require_once '../app/bootstrap.php';
require_once '../app/core/auth.php';
require_once '../app/core/helpers.php';

if (!is_admin() && !is_hr_manager() && !is_hr()) {
    redirect('index.php');
}

// --- Filtering Logic ---
$whereClauses = [];
$bindings = [];
$queryParams = $_GET;

$startDate = $queryParams['start_date'] ?? null;
$endDate = $queryParams['end_date'] ?? null;

if (!empty($startDate) && !empty($endDate)) {
    $whereClauses[] = "DATE(al.punch_time) BETWEEN :start_date AND :end_date";
    $bindings[':start_date'] = $startDate;
    $bindings[':end_date'] = $endDate;
}

$employeeSearch = trim($queryParams['employee_search'] ?? '');
if (!empty($employeeSearch)) {
    // Use unique named parameters for each LIKE clause to avoid HY093 error
    $whereClauses[] = "(u.full_name LIKE :employee_search_name OR u.employee_code LIKE :employee_search_code OR u.email LIKE :employee_search_email)";
    $bindings[':employee_search_name'] = '%' . $employeeSearch . '%';
    $bindings[':employee_search_code'] = '%' . $employeeSearch . '%';
    $bindings[':employee_search_email'] = '%' . $employeeSearch . '%';
}

$status = $queryParams['status'] ?? 'all';
if ($status !== 'all') {
    $whereClauses[] = "al.status = :status";
    $bindings[':status'] = $status;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// --- Pagination Logic ---
$page = (int)($queryParams['page'] ?? 1);
$recordsPerPage = 25;
$offset = ($page - 1) * $recordsPerPage;

$countSql = <<<SQL
SELECT COUNT(*) * 2
FROM (
    SELECT
        al_minmax.user_id,
        DATE(al_minmax.punch_time) AS punch_date
    FROM
        attendance_logs al_minmax
    JOIN
        users u_minmax ON al_minmax.user_id = u_minmax.id
    LEFT JOIN
        shifts s_minmax ON al_minmax.shift_id = s_minmax.id
    {$whereSql}
    GROUP BY
        al_minmax.user_id,
        DATE(al_minmax.punch_time)
    HAVING
        COUNT(al_minmax.id) >= 2
) AS DailyPunchesCount;
SQL;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($bindings);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);


// --- Data Fetching Query ---
$sql = <<<SQL
SELECT al.*, u.full_name, s.shift_name,
       CASE
           WHEN al.punch_time = DailyMinMax.min_time THEN 0 -- Force 'In' state for display
           WHEN al.punch_time = DailyMinMax.max_time THEN 1 -- Force 'Out' state for display
           ELSE al.punch_state -- Fallback, though these should be filtered out
       END AS display_punch_state
FROM attendance_logs al
JOIN users u ON al.user_id = u.id
LEFT JOIN shifts s ON al.shift_id = s.id
JOIN (
    SELECT
        al_minmax.user_id,
        DATE(al_minmax.punch_time) AS punch_date,
        MIN(al_minmax.punch_time) AS min_time,
        MAX(al_minmax.punch_time) AS max_time
    FROM
        attendance_logs al_minmax
    JOIN
        users u_minmax ON al_minmax.user_id = u_minmax.id
    LEFT JOIN
        shifts s_minmax ON al_minmax.shift_id = s_minmax.id
    {$whereSql}
    GROUP BY
        al_minmax.user_id,
        DATE(al_minmax.punch_time)
    HAVING
        COUNT(al_minmax.id) >= 2
) AS DailyMinMax ON al.user_id = DailyMinMax.user_id AND DATE(al.punch_time) = DailyMinMax.punch_date
WHERE
    al.punch_time = DailyMinMax.min_time OR al.punch_time = DailyMinMax.max_time
ORDER BY DATE(al.punch_time) DESC, al.user_id ASC, al.punch_time ASC
LIMIT :limit OFFSET :offset;
SQL;

$stmt = $pdo->prepare($sql);
foreach ($bindings as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);


include '../app/templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Attendance Logs</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end mb-4">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label for="employee_search" class="form-label">Employee (Name, Code, or Email)</label>
                    <input type="text" class="form-control" id="employee_search" name="employee_search" value="<?php echo htmlspecialchars($employeeSearch ?? ''); ?>" placeholder="Search employee...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="valid" <?php echo $status === 'valid' ? 'selected' : ''; ?>>Valid</option>
                        <option value="invalid" <?php echo $status === 'invalid' ? 'selected' : ''; ?>>Invalid</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Punch Time</th>
                            <th>State</th>
                            <th>Shift</th>
                            <th>Status</th>
                            <th>Violation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No records found for the selected criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['id']); ?></td>
                                    <td><?php echo htmlspecialchars($log['full_name'] . ' (' . $log['employee_code'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars((new DateTime($log['punch_time']))->format('Y-m-d h:i A')); ?></td>
                                    <td>
                                        <span class="badge <?php echo $log['display_punch_state'] == 0 ? 'bg-success' : 'bg-primary'; ?>">
                                            <?php echo $log['display_punch_state'] == 0 ? 'In' : 'Out'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['shift_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $log['status'] === 'valid' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($log['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $violation = $log['violation_type'];
                                        if ($violation === 'overtime') {
                                            echo '<span class="badge bg-info text-dark">' . htmlspecialchars(ucfirst($violation)) . '</span>';
                                        } elseif ($violation) {
                                            echo '<span class="badge bg-warning text-dark">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $violation))) . '</span>';
                                        } else {
                                            echo 'None';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php $page_params = $_GET; ?>
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <?php $page_params['page'] = $page - 1; ?>
                        <a class="page-link" href="?<?php echo http_build_query($page_params); ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php $page_params['page'] = $i; ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query($page_params); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <?php $page_params['page'] = $page + 1; ?>
                        <a class="page-link" href="?<?php echo http_build_query($page_params); ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include '../app/templates/footer.php'; ?>