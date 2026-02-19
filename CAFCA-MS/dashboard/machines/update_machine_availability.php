<?php
/**
 * Machine Availability Update Helper Function
 * 
 * This function automatically updates a machine's unavailable_from and unavailable_until
 * dates based on active schedules (Approved or On going status, not yet returned).
 * 
 * @param mysqli $conn Database connection object
 * @param int $machineId ID of the machine to update
 * @return bool True if update was successful, false otherwise
 */
function updateMachineAvailability($conn, $machineId) {
    // Get the earliest active schedule for this machine
    // Active = Approved or On going status, and not yet returned (return_date is NULL)
    $sql = "SELECT schedule_date, start_time, end_time, date_span, status, return_date
            FROM schedules 
            WHERE machine_id = ? 
            AND status IN ('Approved', 'On going')
            AND return_date IS NULL
            ORDER BY schedule_date ASC, start_time ASC
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("updateMachineAvailability: Failed to prepare statement - " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $machineId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Machine has an active schedule - set unavailable dates
        $scheduleDate = $row['schedule_date'];
        $startTime = $row['start_time'];
        $dateSpan = $row['date_span'];
        $endTime = $row['end_time'];
        
        // Calculate start datetime: schedule_date + start_time
        $unavailableFrom = $scheduleDate . ' ' . $startTime;
        
        // Calculate end datetime: (schedule_date + date_span days) + end_time
        $endDate = date('Y-m-d', strtotime($scheduleDate . " +{$dateSpan} days"));
        $unavailableUntil = $endDate . ' ' . $endTime;
        
        // Update machine with calculated unavailable dates
        $updateSql = "UPDATE machines 
                      SET unavailable_from = ?, unavailable_until = ? 
                      WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        
        if (!$updateStmt) {
            error_log("updateMachineAvailability: Failed to prepare update statement - " . $conn->error);
            $stmt->close();
            return false;
        }
        
        $updateStmt->bind_param("ssi", $unavailableFrom, $unavailableUntil, $machineId);
        $success = $updateStmt->execute();
        
        if (!$success) {
            error_log("updateMachineAvailability: Failed to update machine {$machineId} - " . $updateStmt->error);
        }
        
        $updateStmt->close();
        $stmt->close();
        return $success;
        
    } else {
        // No active schedules - clear unavailable dates
        $updateSql = "UPDATE machines 
                      SET unavailable_from = NULL, unavailable_until = NULL 
                      WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        
        if (!$updateStmt) {
            error_log("updateMachineAvailability: Failed to prepare clear statement - " . $conn->error);
            $stmt->close();
            return false;
        }
        
        $updateStmt->bind_param("i", $machineId);
        $success = $updateStmt->execute();
        
        if (!$success) {
            error_log("updateMachineAvailability: Failed to clear machine {$machineId} - " . $updateStmt->error);
        }
        
        $updateStmt->close();
        $stmt->close();
        return $success;
    }
}
?>