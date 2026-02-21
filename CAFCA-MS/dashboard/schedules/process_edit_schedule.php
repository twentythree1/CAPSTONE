<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Include the machine availability helper function
require_once('../machines/update_machine_availability.php');

$id = "";
$farmer_id = "";
$machine_id = "";
$schedule_date = "";
$date_span = "";
$start_time = "";
$end_time = "";

$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
    $farmer_id = isset($_POST["farmer_id"]) ? (int)$_POST["farmer_id"] : 0;
    $machine_id = isset($_POST["machine_id"]) ? (int)$_POST["machine_id"] : 0;
    $schedule_date = isset($_POST["schedule_date"]) ? $_POST["schedule_date"] : '';
    $date_span = isset($_POST["date_span"]) ? (int)$_POST["date_span"] : 0;
    $start_time = isset($_POST["start_time"]) ? $_POST["start_time"] : '';
    $end_time = isset($_POST["end_time"]) ? $_POST["end_time"] : '';

    do {
        if (
            empty($id) || empty($farmer_id) || empty($machine_id) ||
            empty($schedule_date) || (!is_numeric($date_span) && $date_span !== 0) ||
            empty($start_time) || empty($end_time)
        ) {
            $errorMessage = "All fields are required!";
            break;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule_date)) {
            $errorMessage = "Invalid schedule date format.";
            break;
        }

        if ($date_span < 0) {
            $errorMessage = "Date span must be 0 or greater.";
            break;
        }

        if (!is_numeric($id)) {
            $errorMessage = "Invalid schedule ID.";
            break;
        }

        // Get the old machine_id before updating (in case it changed)
        $oldMachineSql = "SELECT machine_id, status FROM schedules WHERE id = ?";
        $oldMachineStmt = $conn->prepare($oldMachineSql);
        $oldMachineStmt->bind_param("i", $id);
        $oldMachineStmt->execute();
        $oldMachineResult = $oldMachineStmt->get_result();
        $oldMachineData = $oldMachineResult->fetch_assoc();
        $oldMachineId = $oldMachineData ? $oldMachineData['machine_id'] : null;
        $scheduleStatus = $oldMachineData ? $oldMachineData['status'] : null;
        $oldMachineStmt->close();

        // Get machine unavailable dates
        $machineQuery = "SELECT unavailable_from, unavailable_until FROM machines WHERE id = ?";
        $machineStmt = $conn->prepare($machineQuery);
        $machineStmt->bind_param("i", $machine_id);
        $machineStmt->execute();
        $machineResult = $machineStmt->get_result();
        
        if ($machineResult->num_rows === 0) {
            $machineStmt->close();
            $errorMessage = "Machine not found.";
            break;
        }
        
        $machineData = $machineResult->fetch_assoc();
        $unavailableFrom = $machineData['unavailable_from'];
        $unavailableUntil = $machineData['unavailable_until'];
        $machineStmt->close();
        
        // Check if the requested time period overlaps with machine's unavailable period
        if (!empty($unavailableFrom) && !empty($unavailableUntil)) {
            try {
                // Calculate end date for this schedule
                $end_date = date('Y-m-d', strtotime($schedule_date . " +{$date_span} days"));
                
                $requestStart = new DateTime($schedule_date . ' ' . $start_time);
                $requestEnd = new DateTime($end_date . ' ' . $end_time);
                $machineUnavailableStart = new DateTime($unavailableFrom);
                $machineUnavailableEnd = new DateTime($unavailableUntil);
                
                // Check if there's any overlap
                if ($requestStart < $machineUnavailableEnd && $requestEnd > $machineUnavailableStart) {
                    $fromFormatted = $machineUnavailableStart->format('M d, Y g:i A');
                    $untilFormatted = $machineUnavailableEnd->format('M d, Y g:i A');
                    $errorMessage = "This machine is unavailable from $fromFormatted to $untilFormatted";
                    break;
                }
            } catch (Exception $e) {
                // If date parsing fails, continue with availability check
            }
        }
        
        // Calculate end date for overlap checking
        $end_date = date('Y-m-d', strtotime($schedule_date . " +{$date_span} days"));

        // Check if this machine is already booked during the requested period (excluding current schedule)
        $conflictQuery = "
            SELECT COUNT(*) as conflict_count FROM schedules 
            WHERE machine_id = ? 
              AND id != ?
              AND status IN ('Pending', 'Approved', 'On going')
              AND (
                    DATE_ADD(schedule_date, INTERVAL date_span DAY) >= ?
                    AND schedule_date <= ?
                )
              AND (
                    start_time < ? AND end_time > ?
                )
        ";
        
        $conflictStmt = $conn->prepare($conflictQuery);
        $conflictStmt->bind_param("iissss", $machine_id, $id, $schedule_date, $end_date, $end_time, $start_time);
        $conflictStmt->execute();
        $conflictResult = $conflictStmt->get_result();
        $conflictData = $conflictResult->fetch_assoc();
        $conflictStmt->close();
        
        if ((int)$conflictData['conflict_count'] > 0) {
            $errorMessage = "This machine is already booked during the selected date/time.";
            break;
        }

        // Check if this farmer is already booked during the requested period (excluding current schedule)
        $farmerConflictQuery = "
            SELECT COUNT(*) as conflict_count FROM schedules 
            WHERE farmer_id = ? 
              AND id != ?
              AND status IN ('Pending', 'Approved', 'On going')
              AND (
                    DATE_ADD(schedule_date, INTERVAL date_span DAY) >= ?
                    AND schedule_date <= ?
                )
              AND (
                    start_time < ? AND end_time > ?
                )
        ";

        $farmerConflictStmt = $conn->prepare($farmerConflictQuery);
        $farmerConflictStmt->bind_param("iissss", $farmer_id, $id, $schedule_date, $end_date, $end_time, $start_time);
        $farmerConflictStmt->execute();
        $farmerConflictResult = $farmerConflictStmt->get_result();
        $farmerConflictData = $farmerConflictResult->fetch_assoc();
        $farmerConflictStmt->close();

        if ((int)$farmerConflictData['conflict_count'] > 0) {
            $errorMessage = "This farmer is already booked during the selected date/time.";
            break;
        }

        $sql = "UPDATE schedules 
                SET farmer_id = ?, machine_id = ?, schedule_date = ?, date_span = ?, start_time = ?, end_time = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMessage = "Database error: " . $conn->error;
            break;
        }

        $stmt->bind_param("iisissi", $farmer_id, $machine_id, $schedule_date, $date_span, $start_time, $end_time, $id);

        if (!$stmt->execute()) {
            $errorMessage = "Error updating schedule: " . $stmt->error;
            $stmt->close();
            break;
        }

        $stmt->close();

        // Update machine availability if the schedule is Approved or On going
        if ($scheduleStatus === 'Approved' || $scheduleStatus === 'On going') {
            // Update the new machine's availability
            updateMachineAvailability($conn, $machine_id);
            
            // If machine was changed, update the old machine's availability too
            if ($oldMachineId && $oldMachineId != $machine_id) {
                updateMachineAvailability($conn, $oldMachineId);
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Schedule successfully updated!']);
        $conn->close();
        exit;

    } while (false);

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    $conn->close();
    exit;
}

$conn->close();

// If accessed directly (not via POST), redirect to schedules page
header("location: /CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Pending");
exit;