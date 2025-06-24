<?php
// in file: htdocs/api/bulk_operations.php
header('Content-Type: application/json');

require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$bulk_action = $_POST['action'] ?? '';

try {
    $pdo->beginTransaction();
    $message = '';

    switch ($bulk_action) {
        case 'reset_all_balances':
            // FIX: Also set last_accrual_date to NULL to allow for re-accrual.
            $stmt_reset = $pdo->prepare("UPDATE leave_balances SET balance_days = 0, last_accrual_date = NULL, last_updated_at = NOW()");
            $stmt_reset->execute();
            $count = $stmt_reset->rowCount();
            $message = "{$count} leave balance record(s) have been reset to 0.";
            break;

        case 'perform_annual_accrual':
            $sql_accrual = "
                INSERT INTO leave_balances (user_id, leave_type_id, balance_days, last_accrual_date, last_updated_at)
                SELECT
                    u.id,
                    lt.id,
                    lt.accrual_days_per_year,
                    CURDATE(),
                    NOW()
                FROM
                    users u
                CROSS JOIN
                    leave_types lt
                WHERE
                    u.is_active = 1
                    AND lt.is_active = 1
                    AND lt.accrual_days_per_year > 0
                    AND NOT EXISTS (
                        SELECT 1
                        FROM leave_balances lb
                        WHERE lb.user_id = u.id
                          AND lb.leave_type_id = lt.id
                          AND YEAR(lb.last_accrual_date) = YEAR(CURDATE())
                    )
                ON DUPLICATE KEY UPDATE
                    balance_days = leave_balances.balance_days + VALUES(balance_days),
                    last_accrual_date = VALUES(last_accrual_date),
                    last_updated_at = NOW()
            ";
            
            $stmt_accrual = $pdo->prepare($sql_accrual);
            $stmt_accrual->execute();
            $updated_count = $stmt_accrual->rowCount();
            
            if ($updated_count > 0) {
                 $message = "Annual leave accrual process completed successfully.";
            } else {
                 $message = "Annual leave accrual performed. All balances were already up-to-date.";
            }
            break;

        case 'adjust_selected_balances':
            $user_ids_to_adjust = $_POST['user_ids'] ?? [];
            $leave_type_id_to_adjust = filter_input(INPUT_POST, 'leave_type_id', FILTER_VALIDATE_INT);
            $new_balance = null;

            if (isset($_POST['new_balance']) && is_numeric($_POST['new_balance'])) {
                $new_balance = (float)$_POST['new_balance'];
            }
            
            if (empty($user_ids_to_adjust) || empty($leave_type_id_to_adjust) || $new_balance === null) {
                throw new Exception('You must select users, a leave type, and provide a valid new balance value.');
            }
            
            $sql_adjust = "
                INSERT INTO leave_balances (user_id, leave_type_id, balance_days, last_updated_at)
                VALUES ";
            $params = [];
            $placeholders = [];
            foreach ($user_ids_to_adjust as $user_id) {
                 $placeholders[] = "(?, ?, ?, NOW())";
                 array_push($params, $user_id, $leave_type_id_to_adjust, $new_balance);
            }
            $sql_adjust .= implode(',', $placeholders);
            $sql_adjust .= " ON DUPLICATE KEY UPDATE balance_days = VALUES(balance_days), last_updated_at = VALUES(last_updated_at)";

            $stmt_adjust = $pdo->prepare($sql_adjust);
            $stmt_adjust->execute($params);
            
            $message = count($user_ids_to_adjust) . ' user(s) leave balances updated successfully.';
            break;

        default:
            throw new Exception('Invalid bulk action specified.');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code($e instanceof PDOException ? 500 : 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}