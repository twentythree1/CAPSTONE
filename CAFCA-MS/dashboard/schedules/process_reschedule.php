<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: /CAFCA-MS/login/logindex.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include the machine availability helper function
require_once('../machines/update_machine_availability.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = $_POST['schedule_id'] ?? null;
    $schedule_date = $_POST['schedule_date'] ?? null;
    $date_span = $_POST['date_span'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $reschedule_reason = $_POST['reschedule_reason'] ?? null;
    $original_status = $_POST['original_status'] ?? 'Approved';
    $redirect = $_POST['redirect'] ?? 'Approved';

    // Validate required fields
    if (!$schedule_id || !$schedule_date || !$start_time || !$end_time || !$reschedule_reason) {
        header("Location: schedule.php?status=" . urlencode($redirect) . "&error=missing_fields");
        exit();
    }

    // Validate the schedule exists
    $checkSql = "SELECT id, machine_id FROM schedules WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $schedule_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        header("Location: schedule.php?status=" . urlencode($redirect) . "&error=schedule_not_found");
        exit();
    }
    
    $row = $result->fetch_assoc();
    $machine_id = $row['machine_id'];
    $checkStmt->close();

    // Get machine unavailable dates
    $machineQuery = "SELECT unavailable_from, unavailable_until FROM machines WHERE id = ?";
    $machineStmt = $conn->prepare($machineQuery);
    $machineStmt->bind_param("i", $machine_id);
    $machineStmt->execute();
    $machineResult = $machineStmt->get_result();
    
    if ($machineResult->num_rows === 0) {
        $machineStmt->close();
        $conn->close();
        header("Location: schedule.php?status=" . urlencode($redirect) . "&error=machine_not_found");
        exit();
    }
    
    $machineData = $machineResult->fetch_assoc();
    $unavailableFrom = $machineData['unavailable_from'];
    $unavailableUntil = $machineData['unavailable_until'];
    $machineStmt->close();

    // Check if the requested time period overlaps with machine's unavailable period
    if (!empty($unavailableFrom) && !empty($unavailableUntil)) {
        try {
            $end_date = date('Y-m-d', strtotime($schedule_date . " +{$date_span} days"));
            
            $requestStart = new DateTime($schedule_date . ' ' . $start_time);
            $requestEnd = new DateTime($end_date . ' ' . $end_time);
            $machineUnavailableStart = new DateTime($unavailableFrom);
            $machineUnavailableEnd = new DateTime($unavailableUntil);
            
            // Check if there's any overlap
            if ($requestStart < $machineUnavailableEnd && $requestEnd > $machineUnavailableStart) {
                $conn->close();
                header("Location: schedule.php?status=" . urlencode($redirect) . "&error=machine_unavailable");
                exit();
            }
        } catch (Exception $e) {
            // If date parsing fails, continue with availability check
        }
    }

    // Check if this machine is already booked during the requested period (excluding current schedule)
    $end_date = date('Y-m-d', strtotime($schedule_date . " +{$date_span} days"));
    
    $conflictSql = "SELECT COUNT(*) as conflict_count FROM schedules 
                    WHERE machine_id = ? 
                    AND id != ? 
                    AND status IN ('Pending', 'Approved', 'On going')
                    AND NOT (
                        DATE_ADD(?, INTERVAL ? DAY) < schedule_date 
                        OR ? > DATE_ADD(schedule_date, INTERVAL date_span DAY)
                    )";
    
    $conflictStmt = $conn->prepare($conflictSql);
    $conflictStmt->bind_param("iisis", $machine_id, $schedule_id, $schedule_date, $date_span, $schedule_date);
    $conflictStmt->execute();
    $conflictResult = $conflictStmt->get_result();
    $conflictData = $conflictResult->fetch_assoc();
    $conflictStmt->close();
    
    if ((int)$conflictData['conflict_count'] > 0) {
        $conn->close();
        header("Location: schedule.php?status=" . urlencode($redirect) . "&error=fully_booked");
        exit();
    }

    // If rescheduling from Cancelled, change to Pending so admin can approve
    // If rescheduling from Approved, keep as Approved
    if ($original_status === 'Cancelled') {
        $new_status = 'Pending';
        $redirect_to = 'Pending';
    } else {
        $new_status = 'Approved';
        $redirect_to = $redirect;
    }

    // Update the schedule with new date, time, reason, and status
    $updateSql = "UPDATE schedules 
                  SET schedule_date = ?, 
                      date_span = ?, 
                      start_time = ?, 
                      end_time = ?, 
                      reschedule_reason = ?, 
                      rescheduled_at = NOW(),
                      status = ?
                  WHERE id = ?";
    
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sissssi", $schedule_date, $date_span, $start_time, $end_time, $reschedule_reason, $new_status, $schedule_id);
    
    if ($updateStmt->execute()) {
        // Update machine availability dates if status is Approved
        if ($new_status === 'Approved') {
            updateMachineAvailability($conn, $machine_id);
        }
        
        $updateStmt->close();
        $conn->close();
        header("Location: schedule.php?status=" . urlencode($redirect_to) . "&rescheduled=1");
        exit();
    } else {
        // Log the error for debugging
        error_log("Reschedule update failed: " . $updateStmt->error);
        $updateStmt->close();
        $conn->close();
        header("Location: schedule.php?status=" . urlencode($redirect) . "&error=update_failed");
        exit();
    }
} else {
    header("Location: schedule.php");
    exit();
}
?>