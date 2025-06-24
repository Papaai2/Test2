<?php
// in file: app/core/services/AttendanceService.php

require_once __DIR__ . '/../../../vendor/autoload.php';

use CodingLibs\ZktecoPhp\Libs\ZKTeco;

class AttendanceService
{
    private PDO $pdo;
    private const PUNCH_IN = 0;
    private const PUNCH_OUT = 1;

    public function __construct(?PDO $pdo)
    {
        if ($pdo) {
            $this->pdo = $pdo;
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    public function processPunch(string $employeeCode, string $punchTime)
    {
        $userShiftDetails = $this->getUserAndShiftDetails($employeeCode);
        if (!$userShiftDetails) {
            return "Unknown employee_code: '{$employeeCode}'. This employee might not be registered in the portal's user database.";
        }
        $userId = $userShiftDetails['user_id'];

        // Fetch all punches for the day, including the current one
        $punchDateTime = new DateTime($punchTime);
        $punchDate = $punchDateTime->format('Y-m-d');
        $allPunchesForDay = $this->getAllPunchesForDay($userId, $punchDate, $punchTime);

        // Correct erroneous punches
        $correctedPunches = $this->correctErroneousPunches($userId, $allPunchesForDay, $userShiftDetails['shift']);

        // Determine punch state (IN or OUT) based on corrected punches
        $punchState = $this->determinePunchState($userId, $punchTime, $correctedPunches);

        $violation = $this->calculateViolation($punchTime, $punchState, $userShiftDetails['shift']);

        if ($this->savePunch($userId, $employeeCode, $punchTime, $punchState, $userShiftDetails['shift_id'] ?? null, $violation)) {
            return true;
        }

        return "Could not save to database due to an unexpected error.";
    }
    
    public function saveStandardizedLogs(array $logs): int
    {
        $savedCount = 0;
        $processedLogs = [];

        // Group logs by employee_code and date
        $dailyPunches = [];
        foreach ($logs as $log) {
            if (empty($log['employee_code']) || empty($log['punch_time'])) {
                continue;
            }
            $punchDateTime = new DateTime($log['punch_time']);
            $punchDate = $punchDateTime->format('Y-m-d');
            $dailyPunches[$log['employee_code']][$punchDate][] = $log;
        }

        // For each user and each day, identify the earliest and latest punch
        foreach ($dailyPunches as $employeeCode => $dates) {
            foreach ($dates as $punchDate => $punches) {
                // Sort punches for the day by time to easily find first and last
                usort($punches, fn($a, $b) => strcmp($a['punch_time'], $b['punch_time']));

                $earliestPunch = $punches[0]; // First punch of the day
                $latestPunch = end($punches); // Last punch of the day

                // Add the earliest punch
                $filteredPunchesToProcess[] = $earliestPunch;

                // If there's more than one unique punch for the day, add the latest punch
                if ($earliestPunch['punch_time'] !== $latestPunch['punch_time']) {
                    $filteredPunchesToProcess[] = $latestPunch;
                }
            }
        }

        // Sort all filtered punches by punch_time before processing to maintain chronological order
        usort($filteredPunchesToProcess, fn($a, $b) => strcmp($a['punch_time'], $b['punch_time']));

        foreach ($filteredPunchesToProcess as $log) {
            // Before processing, check if this specific punch_time for this user already exists
            // This prevents redundant processing of logs already in the database
            $userShiftDetails = $this->getUserAndShiftDetails($log['employee_code']);
            if (!$userShiftDetails) {
                error_log("Unknown employee_code: '{$log['employee_code']}'. Skipping log processing.");
                continue;
            }
            $userId = $userShiftDetails['user_id'];

            $checkSql = "SELECT COUNT(*) FROM attendance_logs WHERE user_id = ? AND punch_time = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$userId, $log['punch_time']]);
            if ($checkStmt->fetchColumn() > 0) {
                // Log for this user and punch_time already exists, skip processing
                continue;
            }

            try {
                // processPunch returns true on success, or a string message on failure
                if ($this->processPunch($log['employee_code'], $log['punch_time']) === true) {
                    $savedCount++;
                }
            } catch (Exception $e) {
                error_log("Failed to process punch for {$log['employee_code']}: " . $e->getMessage());
            }
        }
        return $savedCount;
    }

    private function isDuplicatePunch(int $userId, string $punchTime): bool
    {
        $timeWindow = 180; // seconds
        $stmt = $this->pdo->prepare("SELECT 1 FROM attendance_logs WHERE user_id = ? AND punch_time BETWEEN DATE_SUB(?, INTERVAL ? SECOND) AND DATE_ADD(?, INTERVAL ? SECOND)");
        $stmt->execute([$userId, $punchTime, $timeWindow, $punchTime, $timeWindow]);
        return $stmt->fetchColumn() !== false;
    }

    private function getLastPunch(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM attendance_logs WHERE user_id = ? ORDER BY punch_time DESC LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getAllPunchesForDay(int $userId, string $punchDate, string $currentPunchTime = null): array
    {
        $sql = "SELECT punch_time, punch_state FROM attendance_logs WHERE user_id = ? AND DATE(punch_time) = ?";
        if ($currentPunchTime !== null) {
            $sql .= " AND punch_time <= ?"; // Include the current punch
        }
        $sql .= " ORDER BY punch_time ASC";
        $stmt = $this->pdo->prepare($sql);
        $params = [$userId, $punchDate];
        if ($currentPunchTime !== null) {
            $params[] = $currentPunchTime;
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function correctErroneousPunches(int $userId, array $punches, ?array $shift): array
    {
        $correctedPunches = $punches;
        $outOutThreshold = 1800; // 30 minutes in seconds

        for ($i = 0; $i < count($correctedPunches) - 1; $i++) {
            if ($correctedPunches[$i]['punch_state'] == self::PUNCH_OUT && $correctedPunches[$i + 1]['punch_state'] == self::PUNCH_OUT) {
                $timeDiff = (new DateTime($correctedPunches[$i + 1]['punch_time']))->getTimestamp() - (new DateTime($correctedPunches[$i]['punch_time']))->getTimestamp();
                if ($timeDiff <= $outOutThreshold) {
                    // Remove the erroneous "IN" punch (which is actually the first "OUT")
                    array_splice($correctedPunches, $i, 1);
                    $i--; // Adjust index because we removed an element
                }
            }
        }

        return $correctedPunches;
    }

    private function determinePunchState(int $userId, string $punchTime, array $correctedPunches): int
    {
        if (empty($correctedPunches)) {
            return self::PUNCH_IN; // First punch of the day is always IN
        }

        $lastPunch = end($correctedPunches);
        return ($lastPunch['punch_state'] == self::PUNCH_IN) ? self::PUNCH_OUT : self::PUNCH_IN;
    }

    private function getUserAndShiftDetails(string $employeeCode): ?array
    {
        $sql = "SELECT u.id AS user_id, u.shift_id, s.shift_name, s.start_time, s.end_time, s.grace_in_minutes, s.grace_out_minutes, s.is_night_shift FROM users u LEFT JOIN shifts s ON u.shift_id = s.id WHERE u.employee_code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$employeeCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) return null;

        return [
            'user_id' => $result['user_id'], 'shift_id' => $result['shift_id'],
            'shift' => $result['shift_id'] ? ['shift_name' => $result['shift_name'], 'start_time' => $result['start_time'], 'end_time' => $result['end_time'], 'grace_in_minutes' => (int)$result['grace_in_minutes'], 'grace_out_minutes' => (int)$result['grace_out_minutes'], 'is_night_shift' => (bool)$result['is_night_shift']] : null
        ];
    }

    /**
     * Calculates if a punch constitutes a violation.
     * **MODIFIED** to include overtime detection.
     */
    private function calculateViolation(string $punchTime, int $punchState, ?array $shift): ?string
    {
        if (!$shift) return null;

        $punchDateTime = new DateTime($punchTime);
        $punchDate = $punchDateTime->format('Y-m-d');
        $shiftStartTime = new DateTime($punchDate . ' ' . $shift['start_time']);
        $shiftEndTime = new DateTime($punchDate . ' ' . $shift['end_time']);

        if ($shift['is_night_shift'] && $shiftEndTime <= $shiftStartTime) {
            if ($punchDateTime->format('H:i:s') < $shift['start_time']) {
                $shiftStartTime->modify('-1 day');
            } else {
                $shiftEndTime->modify('+1 day');
            }
        }

        $violation = null;
        if ($punchState == self::PUNCH_IN) {
            $graceInLimit = (clone $shiftStartTime)->modify('+' . $shift['grace_in_minutes'] . ' minutes');
            if ($punchDateTime > $graceInLimit) $violation = 'late_in';
        } elseif ($punchState == self::PUNCH_OUT) {
            $earlyOutLimit = (clone $shiftEndTime)->modify('-' . $shift['grace_out_minutes'] . ' minutes');
            // Overtime is considered any punch 15 minutes after the shift is supposed to end.
            $overtimeStartLimit = (clone $shiftEndTime)->modify('+15 minutes');

            if ($punchDateTime < $earlyOutLimit) {
                $violation = 'early_out';
            } elseif ($punchDateTime > $overtimeStartLimit) {
                $violation = 'overtime';
            }
        }
        return $violation;
    }

    /**
     * Saves the final punch data into the database.
     * **MODIFIED** to ensure overtime punches are marked as 'valid'.
     */
    private function savePunch(int $userId, string $employeeCode, string $punchTime, int $punchState, ?int $shiftId, ?string $violationType): bool
    {
        // A punch is only 'invalid' if it has a violation that IS NOT 'overtime'.
        $status = 'valid';
        if ($violationType !== null && $violationType !== 'overtime') {
            $status = 'invalid';
        }
        
        // Check for duplicate before inserting
        // Use INSERT IGNORE to prevent duplicate key errors and only insert new records.
        // The unique index (user_id, punch_time, punch_state) will handle the uniqueness.
        $insertSql = "INSERT IGNORE INTO attendance_logs (user_id, employee_code, punch_time, punch_state, shift_id, status, violation_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
        try {
            $stmt = $this->pdo->prepare($insertSql);
            $stmt->execute([$userId, $employeeCode, $punchTime, $punchState, $shiftId, $status, $violationType]);
            // Check if a new row was actually inserted (not ignored)
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("DB error saving punch for user_id {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function getLogsFromDevice(string $ip_address, int $port): array
    {
        try {
            $zk = new ZKTeco($ip_address, $port, 30);
            if ($zk->connect()) {
                $logs = $zk->getAttendances();
                $zk->disconnect();
                return $logs;
            }
        } catch (Exception $e) {
            error_log("Error fetching logs from device {$ip_address}:{$port}: " . $e->getMessage());
        }
        return [];
    }
}