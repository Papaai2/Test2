<?php
// in file: htdocs/admin/import_users_csv.php

require_once __DIR__ . '/../app/bootstrap.php';

require_role(['admin', 'hr_manager', 'hr']);

$page_title = 'Import Users via CSV';
include __DIR__ . '/../app/templates/header.php';

$error = '';
$success = '';
$results = []; // To store detailed results for each row
$imported_count = 0;
$skipped_count = 0;

const BATCH_SIZE = 500; // Number of users to insert in one batch
$user_data_batch = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['user_csv'])) {
    if ($_FILES['user_csv']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error. Code: ' . $_FILES['user_csv']['error'];
    } else {
        $file = $_FILES['user_csv']['tmp_name'];
        $handle = fopen($file, "r");

        if ($handle !== FALSE) {
            // Start transaction for atomicity and performance
            $pdo->beginTransaction();
            try {
                // Skip header row
                fgetcsv($handle, 1000, ",");

                // Pre-fetch existing emails and employee codes for faster lookup
                $existing_users_stmt = $pdo->query("SELECT email, employee_code FROM users");
                $existing_emails = [];
                $existing_employee_codes = [];
                while ($row = $existing_users_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $existing_emails[$row['email']] = true;
                    if (!empty($row['employee_code'])) {
                        $existing_employee_codes[$row['employee_code']] = true;
                    }
                }

                // Cache manager IDs to avoid repeated DB queries
                $manager_email_to_id = [];
                $stmt_managers = $pdo->query("SELECT id, email FROM users WHERE role IN ('manager', 'admin', 'hr_manager')");
                while ($manager = $stmt_managers->fetch(PDO::FETCH_ASSOC)) {
                    $manager_email_to_id[$manager['email']] = $manager['id'];
                }

                $row_number = 1;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $row_number++;
                    // Match columns to the new CSV format
                    $employee_code = trim($data[0] ?? '');
                    $full_name = trim($data[1] ?? '');
                    $email = trim($data[2] ?? '');
                    $password = trim($data[3] ?? '');
                    $role = strtolower(trim($data[4] ?? 'user'));
                    $manager_email = trim($data[5] ?? '');

                    // Basic validation
                    if (empty($employee_code) || empty($full_name) || empty($email) || empty($password)) {
                        $results[] = ['status' => 'error', 'message' => "Row $row_number: Missing required data (Employee Code, Full Name, Email, Password).", 'data' => implode(', ', $data)];
                        $skipped_count++;
                        continue;
                    }

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $results[] = ['status' => 'error', 'message' => "Row $row_number: Invalid email format for user.", 'data' => implode(', ', $data)];
                        $skipped_count++;
                        continue;
                    }
                    
                    // Check for existing user (email or employee code)
                    if (isset($existing_emails[$email]) || (isset($existing_employee_codes[$employee_code]) && !empty($employee_code))) {
                        $results[] = ['status' => 'error', 'message' => "Row $row_number: User with email '$email' or Employee Code '$employee_code' already exists.", 'data' => implode(', ', $data)];
                        $skipped_count++;
                        continue;
                    }

                    $direct_manager_id = null;
                    if (!empty($manager_email)) {
                        if (!filter_var($manager_email, FILTER_VALIDATE_EMAIL)) {
                             $results[] = ['status' => 'error', 'message' => "Row $row_number: Invalid email format for manager.", 'data' => implode(', ', $data)];
                             $skipped_count++;
                             continue;
                        }
                        if (isset($manager_email_to_id[$manager_email])) {
                            $direct_manager_id = $manager_email_to_id[$manager_email];
                        } else {
                            $results[] = ['status' => 'error', 'message' => "Row $row_number: Manager with email '$manager_email' not found. User will be imported without a manager.", 'data' => implode(', ', $data)];
                            // Continue without manager, don't skip the user
                        }
                    }
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Add user data to batch
                    $user_data_batch[] = [
                        $employee_code, $full_name, $email, $hashed_password, $role, $direct_manager_id, 1 // must_change_password
                    ];

                    // If batch size is reached, execute batch insert
                    if (count($user_data_batch) >= BATCH_SIZE) {
                        $placeholders = implode(', ', array_fill(0, count($user_data_batch), '(?, ?, ?, ?, ?, ?, ?)'));
                        $sql = "INSERT INTO users (employee_code, full_name, email, password, role, direct_manager_id, must_change_password) VALUES {$placeholders}";
                        
                        $flat_params = [];
                        foreach ($user_data_batch as $user_row) {
                            $flat_params = array_merge($flat_params, $user_row);
                        }
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($flat_params);
                        $imported_count += $stmt->rowCount();
                        $user_data_batch = []; // Clear batch
                    }
                }

                // Insert any remaining users in the last batch
                if (!empty($user_data_batch)) {
                    $placeholders = implode(', ', array_fill(0, count($user_data_batch), '(?, ?, ?, ?, ?, ?, ?)'));
                    $sql = "INSERT INTO users (employee_code, full_name, email, password, role, direct_manager_id, must_change_password) VALUES {$placeholders}";
                    
                    $flat_params = [];
                    foreach ($user_data_batch as $user_row) {
                        $flat_params = array_merge($flat_params, $user_row);
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($flat_params);
                    $imported_count += $stmt->rowCount();
                }

                $pdo->commit();
                $success = "CSV processing complete. Imported {$imported_count} users, skipped {$skipped_count} users. See results below.";

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "An error occurred during import: " . $e->getMessage();
                error_log("CSV Import Error: " . $e->getMessage());
            } finally {
                fclose($handle);
            }
        } else {
            $error = 'Could not open the uploaded file.';
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Import Users via CSV</h1>
    <a href="users.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Users
    </a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0">Upload CSV File</h2>
    </div>
    <div class="card-body">
        <p>Upload a CSV file with the columns in the following order: `employee_code`, `full_name`, `email`, `password`, `role`, `direct_manager_email`.</p>
        <ul class="text-muted">
            <li>The `employee_code` must be the unique ID of the user from the attendance device.</li>
            <li>The `role` column is optional and defaults to 'user'. Allowed roles are: user, manager, hr, hr_manager, admin.</li>
            <li>The `direct_manager_email` is optional. If provided, the system will assign the user to that manager.</li>
            <li>All newly imported users will be required to change their password on their first login.</li>
        </ul>
        
        <a href="sample_users_import.csv" download class="btn btn-sm btn-info mb-3">
            <i class="bi bi-download"></i> Download Sample CSV
        </a>
        
        <form action="import_users_csv.php" method="post" enctype="multipart/form-data" class="mt-3">
            <div class="mb-3">
                <label for="user_csv" class="form-label">Select CSV file</label>
                <input class="form-control" type="file" id="user_csv" name="user_csv" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> Upload and Process</button>
        </form>
    </div>
</div>

<?php if (!empty($results)): ?>
<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">Upload Results</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Original Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr class="<?php echo $result['status'] === 'success' ? 'table-success' : 'table-danger'; ?>">
                            <td><?php echo ucfirst($result['status']); ?></td>
                            <td><?php echo htmlspecialchars($result['message']); ?></td>
                            <td><?php echo htmlspecialchars($result['data']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>


<?php
include __DIR__ . '/../app/templates/footer.php';
?>