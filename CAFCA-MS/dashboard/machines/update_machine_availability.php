<?php
/**
 * Machine Availability Update Helper Function
 *
 * Updates a machine's unavailable_from / unavailable_until dates based on
 * active (Approved, not yet returned) schedules for that machine.
 *
 * With multi-unit machines the logic is:
 *   - Count how many units are still unreturned (return_date IS NULL).
 *   - If ANY unit is still out, set unavailable_from/until to the window
 *     of the earliest active schedule so the UI shows when the machine
 *     (or some of its units) is next in use.
 *   - If ALL units are back (no unreturned schedules), clear the dates.
 *
 * @param mysqli $conn     Database connection object
 * @param int    $machineId ID of the machine to update
 * @return bool True if update was successful, false otherwise
 */
function updateMachineAvailability($conn, $machineId) {

    // Count unreturned (still out) schedules for this machine
    $countSql = "SELECT COUNT(*) AS unreturned_count
                 FROM schedules
                 WHERE machine_id = ?
                   AND status IN ('Approved', 'Completed')
                   AND (return_date IS NULL OR return_date = '')";

    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        error_log("updateMachineAvailability: Failed to prepare count statement - " . $conn->error);
        return false;
    }
    $countStmt->bind_param("i", $machineId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $countStmt->close();

    $unreturnedCount = (int)($countRow['unreturned_count'] ?? 0);

    if ($unreturnedCount > 0) {
        // At least one unit is still out — set unavailable dates to the
        // earliest active schedule's window so the UI reflects it.
        $sql = "SELECT schedule_date, start_time, end_time, date_span
                FROM schedules
                WHERE machine_id = ?
                  AND status IN ('Approved', 'Completed')
                  AND (return_date IS NULL OR return_date = '')
                ORDER BY schedule_date ASC, start_time ASC
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("updateMachineAvailability: Failed to prepare schedule statement - " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $machineId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            // Race condition — count said >0 but no row found; clear dates to be safe
            return clearMachineAvailability($conn, $machineId);
        }

        $unavailableFrom  = $row['schedule_date'] . ' ' . $row['start_time'];
        $endDate          = date('Y-m-d', strtotime($row['schedule_date'] . " +{$row['date_span']} days"));
        $unavailableUntil = $endDate . ' ' . $row['end_time'];

        $updateSql = "UPDATE machines SET unavailable_from = ?, unavailable_until = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        if (!$updateStmt) {
            error_log("updateMachineAvailability: Failed to prepare update statement - " . $conn->error);
            return false;
        }

        $updateStmt->bind_param("ssi", $unavailableFrom, $unavailableUntil, $machineId);
        $success = $updateStmt->execute();

        if (!$success) {
            error_log("updateMachineAvailability: Failed to update machine {$machineId} - " . $updateStmt->error);
        }

        $updateStmt->close();
        return $success;

    } else {
        // All units are back — clear the unavailable window
        return clearMachineAvailability($conn, $machineId);
    }
}

/**
 * Clears unavailable_from and unavailable_until for a machine.
 */
function clearMachineAvailability($conn, $machineId) {
    $updateSql = "UPDATE machines SET unavailable_from = NULL, unavailable_until = NULL WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        error_log("clearMachineAvailability: Failed to prepare statement - " . $conn->error);
        return false;
    }

    $updateStmt->bind_param("i", $machineId);
    $success = $updateStmt->execute();

    if (!$success) {
        error_log("clearMachineAvailability: Failed to clear machine {$machineId} - " . $updateStmt->error);
    }

    $updateStmt->close();
    return $success;
}
?>