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

// Set timezone to ensure consistency between PHP and MySQL
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// Auto-expire pending schedules
$now = new DateTime();
$currentDateTime = $now->format('Y-m-d H:i:s');


$expireSql = "UPDATE schedules 
              SET status = 'Expired' 
              WHERE status = 'Pending' 
              AND CONCAT(schedule_date, ' ', start_time) < ?";
$expireStmt = $conn->prepare($expireSql);
if ($expireStmt) {
    $expireStmt->bind_param("s", $currentDateTime);
    $expireStmt->execute();
    $affectedRows = $expireStmt->affected_rows;
    
    $expireStmt->close();
}

$checkSql = "SELECT id, schedule_date, start_time, status FROM schedules WHERE status = 'Pending'";
$checkResult = $conn->query($checkSql);
if ($checkResult) {
    while ($row = $checkResult->fetch_assoc()) {
        $scheduleStartTime = $row['schedule_date'] . ' ' . $row['start_time'];
        if ($scheduleStartTime < $currentDateTime) {
            $updateId = $row['id'];
            $conn->query("UPDATE schedules SET status = 'Expired' WHERE id = $updateId");
        }
    }
    $checkResult->free();
}

// Auto-complete approved schedules whose end datetime has passed
$completeSql = "UPDATE schedules 
                SET status = 'Completed' 
                WHERE status = 'Approved' 
                AND CONCAT(DATE_ADD(schedule_date, INTERVAL date_span DAY), ' ', end_time) < ?";
$completeStmt = $conn->prepare($completeSql);
if ($completeStmt) {
    $completeStmt->bind_param("s", $currentDateTime);
    $completeStmt->execute();
    $completeStmt->close();
}

// Count machines by status
$machineCounts = [
    'Available' => 0,
    'Partially Damaged' => 0,
    'Totally Damaged' => 0,
    'Not Returned' => 0
];

$countSql = "SELECT status, COUNT(*) as cnt FROM machines GROUP BY status";
$countResult = $conn->query($countSql);
if ($countResult) {
    while ($r = $countResult->fetch_assoc()) {
        $status = $r['status'];
        if (isset($machineCounts[$status])) {
            $machineCounts[$status] += intval($r['cnt']);
        }
    }
    $countResult->free();
}

// Calculate on-going and not-returned counts by looping in PHP
// so we use the SAME end-datetime logic as the sidebar status computation.
$ongoingCount     = 0;
$notReturnedCount = 0;

$activeSql = "SELECT schedule_date, date_span, start_time, end_time, status, return_date
              FROM schedules WHERE status IN ('Approved', 'Completed')";
$activeResult = $conn->query($activeSql);
if ($activeResult) {
    $tz    = new DateTimeZone('Asia/Manila');
    $nowDt = new DateTime('now', $tz);
    while ($row = $activeResult->fetch_assoc()) {
        $dbStatus   = $row['status'];
        $span       = (int)$row['date_span'];
        $startTime  = $row['start_time'] ?: '00:00:00';
        $endTime    = $row['end_time']   ?: '23:59:59';
        $returnDate = $row['return_date'];
        $hasReturned = ($returnDate !== null && $returnDate !== '');

        // DB-stored 'Completed': if return_date is missing, machine was not returned
        if ($dbStatus === 'Completed') {
            if (!$hasReturned) {
                $notReturnedCount++;
            }
            continue;
        }

        // 'Approved': compute actual start/end with midnight-crossing support
        $startDt = new DateTime($row['schedule_date'] . ' ' . $startTime, $tz);
        $endBase  = new DateTime($row['schedule_date'], $tz);
        $endBase->modify("+{$span} days");
        $endDt = new DateTime($endBase->format('Y-m-d') . ' ' . $endTime, $tz);
        if ($endDt <= $startDt) {
            $endDt->modify('+1 day'); // crosses midnight
        }

        if ($nowDt >= $startDt && $nowDt <= $endDt) {
            $ongoingCount++;
        } elseif ($nowDt > $endDt && !$hasReturned) {
            $notReturnedCount++;
        }
    }
    $activeResult->free();
}

$machineCounts['Not Returned'] = $notReturnedCount;
$machineCounts['Available']    = max(0, $machineCounts['Available'] - $notReturnedCount - $ongoingCount);

// Count schedules by status
$counts = [
    'Pending' => 0,
    'Approved' => 0,
    'On going' => 0,
    'Completed' => 0,
    'Expired' => 0,
    'Cancelled' => 0
];

$now = new DateTime();

$countSql = "SELECT schedule_date, start_time, end_time, date_span, status FROM schedules";
$countResult = $conn->query($countSql);

if ($countResult) {
    while ($r = $countResult->fetch_assoc()) {
        $dbStatus = $r['status'];
        $scheduleDate = $r['schedule_date'];
        $startTime = $r['start_time'] ?: '00:00:00';
        $endTime = $r['end_time'] ?: '23:59:59';
        $dateSpan = isset($r['date_span']) ? (int)$r['date_span'] : 0;

        try {
            $startDt = new DateTime($scheduleDate . ' ' . $startTime);
        } catch (Exception $e) {
            $startDt = new DateTime($scheduleDate . ' 00:00:00');
        }

        $endDateStr = date('Y-m-d', strtotime($scheduleDate . " +{$dateSpan} days"));
        try {
            $endDt = new DateTime($endDateStr . ' ' . $endTime);
        } catch (Exception $e) {
            $endDt = new DateTime($endDateStr . ' 23:59:59');
        }

        $computedStatus = $dbStatus;
        if ($dbStatus === 'Pending') {
            if ($now >= $startDt) {
                $computedStatus = 'Expired';
            }
        } elseif ($dbStatus === 'Approved') {
            if ($now >= $startDt && $now <= $endDt) {
                $computedStatus = 'On going';
            } elseif ($now > $endDt) {
                $computedStatus = 'Completed';
            }
        }

        if (!isset($counts[$computedStatus])) $counts[$computedStatus] = 0;
        $counts[$computedStatus]++;
    }
    $countResult->free();
}

// Get status filter from URL
$statusFilter = $_GET['status'] ?? null;

// Total machine count
$machine_count = array_sum($machineCounts);

// Handle AJAX request for fetching schedule data
if (isset($_GET['action']) && $_GET['action'] == 'get_schedule' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);
    $sql = "SELECT s.*, f.name as farmer_name, m.name as machine_name 
            FROM schedules s
            LEFT JOIN farmers f ON s.farmer_id = f.id
            LEFT JOIN machines m ON s.machine_id = m.id
            WHERE s.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Schedule not found']);
    }
    $stmt->close();
    $conn->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Admin</title>
    <link rel="stylesheet" href="../farmers_sec/farmerstyle.css">
    <!-- MATERIAL ICONS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp">
    <!-- HTML2CANVAS for image export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>

<body>
    <div class="container">
        <aside>
            <div class="top">
                <a class="logo" href="../main/dashdex.php">
                    <img src="../../LandingPage/others/logo.png">
                    <h2>CAFCA <span>MS</span></h2>
                </a>
                <div class="close" id="close-btn">
                    <span class="material-icons-sharp">close</span>
                </div>
            </div>

            <div class="sidebar">
                <a href="../main/dashdex.php">
                    <span class="material-icons-sharp">dashboard</span>
                    <h3>Dashboard</h3>
                </a>
                <a href="../farmers_sec/farmers.php">
                    <span class="material-icons-sharp">people</span>
                    <h3>Farmers</h3>
                </a>
                <?php
                $statusParam = $_GET['status'] ?? ''; 
                $currentPage = basename($_SERVER['PHP_SELF']);
                $isMachinePage = ($currentPage === 'machine.php');
                $isSchedulePage = ($currentPage === 'schedule.php');
                ?>
                <div class="sidebar-dropdown <?= ($isMachinePage && $statusParam) ? 'open' : '' ?>">
                    <a href="javascript:void(0)" class="dropdown-toggle"
                        aria-expanded="<?= ($isMachinePage && $statusParam) ? 'true' : 'false' ?>">
                        <span class="material-icons-sharp">agriculture</span>
                        <h3>Machines</h3>
                        <span class="material-icons-sharp dropdown-icon">expand_more</span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=Available">
                            <span>Available</span>
                            <span class="count-badge"><?= htmlspecialchars($machineCounts['Available'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=Partially Damaged">
                            <span>Partially Damaged</span>
                            <span
                                class="count-badge"><?= htmlspecialchars($machineCounts['Partially Damaged'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=Totally Damaged">
                            <span>Totally Damaged</span>
                            <span
                                class="count-badge"><?= htmlspecialchars($machineCounts['Totally Damaged'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=Not Returned">
                            <span>Not Returned</span>
                            <span
                                class="count-badge"><?= htmlspecialchars($machineCounts['Not Returned'] ?? 0) ?></span>
                        </a>
                    </div>
                </div>
                <div class="sidebar-dropdown <?= $schedulesStatus ? 'open' : '' ?>">
                    <a href="javascript:void(0)" class="dropdown-toggle"
                        aria-expanded="<?= $schedulesStatus ? 'true' : 'false' ?>">
                        <span class="material-icons-sharp">event</span>
                        <h3>Schedules</h3>
                        <span class="material-icons-sharp dropdown-icon">expand_more</span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Pending">
                            <span>Pending</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['Pending'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Approved">
                            <span>Approved</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['Approved'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=On going">
                            <span>On going</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['On going'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Completed">
                            <span>Completed</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['Completed'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Expired">
                            <span>Expired</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['Expired'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Cancelled">
                            <span>Cancelled</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['Cancelled'] ?? 0) ?></span>
                        </a>
                    </div>
                </div>
                <a href="../records/records.php">
                    <span class="material-icons-sharp">topic</span>
                    <h3>Records</h3>
                </a>
                <div class="logout"><a href="../../login/logout.php"
                        onclick="return confirm('Are you sure you want to log out?');" class="danger">
                        <span class="material-icons-sharp">logout</span>
                        <h3>Log out</h3>
                    </a>
                </div>
            </div>
        </aside>

        <main>
            <div class="top">
                <?php if (isset($_GET['approved'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: relative;">
                    The recent schedule was approved successfully!
                    <div class="progress-bar">
                        <div class="progress-bar-inner"></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['rescheduled'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: relative;">
                    The schedule was rescheduled successfully!
                    <div class="progress-bar">
                        <div class="progress-bar-inner"></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['added'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: relative;">
                    New schedule was added successfully!
                    <div class="progress-bar">
                        <div class="progress-bar-inner"></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: relative;">
                    Schedule was deleted successfully!
                    <div class="progress-bar">
                        <div class="progress-bar-inner"></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                <?php
                    $errorMessages = [
                        'missing_fields' => 'Please fill in all required fields.',
                        'schedule_not_found' => 'Schedule not found.',
                        'machine_not_found' => 'Machine not found.',

                        'machine_unavailable' => 'This machine is unavailable during the selected time period (maintenance/blocked).',
                        'fully_booked' => 'All units of this machine are already booked for the selected time.',
                        'update_failed' => 'Failed to update the schedule. Please try again.'
                    ];
                    $errorKey = $_GET['error'];
                    $errorMessage = isset($errorMessages[$errorKey]) ? $errorMessages[$errorKey] : 'An error occurred. Please try again.';
                    ?>
                <div class="alert alert-error alert-dismissible fade show" role="alert"
                    style="position: relative; background: #ffebee; color: #c62828; border-left: 4px solid #f44336;">
                    <?= htmlspecialchars($errorMessage) ?>
                    <div class="progress-bar">
                        <div class="progress-bar-inner" style="background: #f44336;"></div>
                    </div>
                </div>
                <?php endif; ?>
                <button id="menu-btn">
                    <span class="material-icons-sharp">menu</span>
                </button>
                <div class="theme-toggler" title="Theme">
                    <span class="material-icons-sharp active">light_mode</span>
                    <span class="material-icons-sharp">dark_mode</span>
                </div>
                <div class="profile" style="display: flex; flex-direction: column; text-align: right;">
                    <span style="font-size: 18px; text-transform: capitalize; font-weight: 700; ">
                        <?= $_SESSION['username']; ?>
                    </span>
                    <small class="text-muted">Admin</small>
                </div>
            </div>
            <div class="title">
                <h2>List of Schedules</h2>
                <?php $currentStatus = isset($_GET['status']) ? $_GET['status'] : 'Pending'; ?>
                <div class="title-actions">
                    <span class="results-count-schedule" id="resultsCount"></span>
                    <div class="search-expand-wrap" id="searchWrap">
                        <div class="schedule-placeholder search-fields" id="searchFields">
                            <div class="search-input-wrap">
                                <input type="text" id="scheduleSearch" placeholder="Search schedules..."
                                    autocomplete="off">
                                <button class="clear-search" id="clearSearch" title="Clear" style="display:none;">
                                    <span class="material-icons-sharp">close</span>
                                </button>
                            </div>
                        </div>
                        <button class="search-icon-btn schedule-search" id="searchToggleBtn" title="Search schedules"
                            type="button">
                            <span class="material-icons-sharp">search</span>
                        </button>
                    </div>
                    <a href="javascript:void(0)" onclick="openAddScheduleModal()" class="btn btn-primary schedule"
                        role="button">Add Schedule</a>
                </div>
            </div>
            <br>


            <?php
            $statusFilter = $_GET['status'] ?? null;

            $schedules = [
                'Pending' => [],
                'Approved' => [],
                'On going' => [],
                'Completed' => [],
                'Expired' => [],
                'Cancelled' => []
            ];

            $sql = "
                SELECT 
                    schedules.id,
                    schedules.status,
                    farmers.name AS farmer_name,
                    machines.name AS machine_name,
                    schedules.schedule_date,
                    schedules.date_span,
                    schedules.start_time,
                    schedules.end_time,
                    schedules.reschedule_reason,
                    schedules.rescheduled_at
                FROM schedules
                JOIN farmers ON schedules.farmer_id = farmers.id
                JOIN machines ON schedules.machine_id = machines.id
            ";
            $result = $conn->query($sql);

            if (!$result) {
                die("Error executing query: " . $conn->error);
            }

            $now = new DateTime();

            while ($row = $result->fetch_assoc()) {
                $dbStatus = $row['status'];

                $scheduleDate = $row['schedule_date'];
                $startTime = $row['start_time'] ?: '00:00:00';
                $endTime = $row['end_time'] ?: '23:59:59';
                $dateSpan = isset($row['date_span']) ? (int)$row['date_span'] : 0;

                try {
                    $startDt = new DateTime($scheduleDate . ' ' . $startTime);
                } catch (Exception $e) {
                    $startDt = new DateTime($scheduleDate . ' 00:00:00');
                }

                $endDateStr = date('Y-m-d', strtotime($scheduleDate . " +{$dateSpan} days"));
                try {
                    $endDt = new DateTime($endDateStr . ' ' . $endTime);
                } catch (Exception $e) {
                    $endDt = new DateTime($endDateStr . ' 23:59:59');
                }

                $computedStatus = $dbStatus;
                
                // Check if Pending schedule should be Expired
                if ($dbStatus === 'Pending') {
                    if ($now >= $startDt) {
                        $computedStatus = 'Expired';
                    }
                }
                // Check if Approved schedule is On going or Completed
                elseif ($dbStatus === 'Approved') {
                    if ($now >= $startDt && $now <= $endDt) {
                        $computedStatus = 'On going';
                    } elseif ($now > $endDt) {
                        $computedStatus = 'Completed';
                    }
                }

                if (isset($statusFilter) && $computedStatus !== $statusFilter) {
                    continue;
                }

                if (!isset($schedules[$computedStatus])) {
                    $schedules[$computedStatus] = [];
                }

                $schedules[$computedStatus][] = [
                    'row' => $row,
                    'status' => $computedStatus
                ];
            }
                            
            function renderScheduleTable($title, $data) {
                static $tableIndex = 0;
                $tableIndex++;
                $tableId = 'scheduleTable_' . $tableIndex;
                echo "<h3 style='margin-top: -1rem; margin-bottom: 1.2rem; padding-left: 1rem;'>$title</h3>";
                            
                if (empty($data)) {
                    echo "<p style='display: flex; justify-content: center; text-transform: capitalize;'>No {$title} available.</p>";
                    return;
                }
                            
                echo "
                <div class='table-scroll'>
                <table id='{$tableId}' style='width:100%' class='table'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Farmer</th>
                            <th>Machine</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                ";
                            
                foreach ($data as $item) {
                    $row = $item['row'];
                    $status = $item['status'];
                    $start_date = date('m-d', strtotime($row['schedule_date']));
                    $end_date = date('m-d', strtotime($row['schedule_date'] . " +{$row['date_span']} days"));
                            
                    $edit_redirect = 'edit_schedule.php?id=' . $row['id'] . '&redirect=' . urlencode($status);
                    $safefarmer  = htmlspecialchars(strtolower($row['farmer_name']),  ENT_QUOTES, 'UTF-8');
                    $safemachine = htmlspecialchars(strtolower($row['machine_name']), ENT_QUOTES, 'UTF-8');
                    $safestatus  = htmlspecialchars(strtolower($status),              ENT_QUOTES, 'UTF-8');
                    echo "<tr class='schedule-row' data-farmer='{$safefarmer}' data-machine='{$safemachine}' data-status='{$safestatus}'>";
                    echo "<td>{$row['id']}</td>";
                    echo "<td>{$row['farmer_name']}</td>";
                    echo "<td>{$row['machine_name']}</td>";
                    echo "<td>$start_date</td>";
                    echo "<td>$end_date</td>";
                    echo "<td>{$row['start_time']}</td>";
                    echo "<td>{$row['end_time']}</td>";
                    echo "<td>$status</td>";
                    echo "<td>
                            <a class='btn btn-success btn-sm' onclick='openDetailsModal({$row['id']})' href='javascript:void(0)'>Details</a>";
                    if ($status === 'Pending') {
                        echo "<a class='btn btn-primary btn-sm' onclick='openEditScheduleModal({$row['id']})' href='javascript:void(0)' style='margin-left: 4px;'>Edit</a>";
                        echo "<a class='btn btn-primary btn-sm' onclick=\"return confirm('Are you sure you want to approve " . htmlspecialchars($row['farmer_name']) . "\\'s schedule to use " . htmlspecialchars($row['machine_name']) . "?');\" style='margin-left: 4px;' href='approve_schedule.php?id={$row['id']}'>Approve</a>";
                        echo "<a class='btn btn-danger btn-sm' onclick=\"return confirm('Are you sure you want to cancel " . htmlspecialchars($row['farmer_name']) . "\\'s schedule?');\"  style='margin-left: 4px;' href='cancel_schedule.php?id={$row['id']}'>Cancel</a>";
                    } elseif ($status === 'Approved') {
                        echo "<a class='btn btn-resched btn-sm' style='margin-left: 4px;' onclick='openRescheduleModal({$row['id']}, \"" . htmlspecialchars($row['farmer_name'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['machine_name'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['schedule_date']) . "\", {$row['date_span']}, \"" . htmlspecialchars($row['start_time']) . "\", \"" . htmlspecialchars($row['end_time']) . "\", \"$status\")'>Reschedule</a>";
                        // Show ellipses with tooltip if there's a reschedule reason
                        if (!empty($row['reschedule_reason'])) {
                            $reason = htmlspecialchars($row['reschedule_reason'], ENT_QUOTES);
                            $rescheduled_date = !empty($row['rescheduled_at']) ? date('M d, Y g:i A', strtotime($row['rescheduled_at'])) : 'N/A';
                            echo "<span class='reschedule-info-icon' data-tooltip='Rescheduled on: $rescheduled_date&#10;Reason: $reason'>⋯</span>";
                        }
                    } elseif ($status === 'Expired') {
                        echo "<a class='btn btn-danger btn-sm' onclick=\"return confirm('Are you sure you want to delete this expired schedule?');\" style='margin-left: 4px;' href='delete_schedule.php?id={$row['id']}'>Delete</a>";
                    } elseif ($status === 'Cancelled') {
                        echo "<a class='btn btn-resched btn-sm' style='margin-left: 4px;' onclick='openRescheduleModal({$row['id']}, \"" . htmlspecialchars($row['farmer_name'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['machine_name'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['schedule_date']) . "\", {$row['date_span']}, \"" . htmlspecialchars($row['start_time']) . "\", \"" . htmlspecialchars($row['end_time']) . "\", \"$status\")'>Reschedule</a>";
                        echo "<a class='btn btn-danger btn-sm' onclick=\"return confirm('Are you sure you want to delete this cancelled schedule?');\" style='margin-left: 4px;' href='delete_schedule.php?id={$row['id']}'>Delete</a>";
                    }
                    
                    echo "</td>";
                    echo "</tr>";
                }
                            
                echo "</tbody>
                <tr class='no-results-row' style='display:none;'>
                    <td colspan='9' style='text-align:center; padding:2rem; color:var(--color-dark-variant);'>
                        <span class='material-icons-sharp' style='font-size:2rem;display:block;margin-bottom:0.5rem;'>search_off</span>
                        No schedules found matching your search.
                    </td>
                </tr>
                </table>
                </div><br>";
            }
                            
            if ($statusFilter) {
                renderScheduleTable("$statusFilter Schedules", $schedules[$statusFilter] ?? []);
            } else {
                renderScheduleTable("Pending Schedules", $schedules['Pending'] ?? []);
                renderScheduleTable("Approved Schedules", $schedules['Approved'] ?? []);
                renderScheduleTable("On-Going Schedules", $schedules['On going'] ?? []);
                renderScheduleTable("Completed Schedules", $schedules['Completed'] ?? []);
                renderScheduleTable("Expired Schedules", $schedules['Expired'] ?? []);
                renderScheduleTable("Cancelled Schedules", $schedules['Cancelled'] ?? []);
            }
            ?>
        </main>
    </div>

    <!-- Reschedule Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reschedule Appointment</h2>
                <span class="close-modal">&times;</span>
            </div>

            <div class="modal-body">
                <div id="errorMessage" class="alert-error" style="display: none;"></div>

                <div class="schedule-info">
                    <h3>Current Schedule Information</h3>
                    <p><strong>Farmer:</strong> <span id="current-farmer"></span></p>
                    <p><strong>Machine:</strong> <span id="current-machine"></span></p>
                    <p><strong>Current Date:</strong> <span id="current-date"></span></p>
                    <p><strong>Duration:</strong> <span id="current-duration"></span> day(s)</p>
                    <p><strong>Time:</strong> <span id="current-time"></span></p>
                    <p><strong>Status:</strong> <span id="current-status"></span></p>
                </div>

                <form id="rescheduleForm" method="POST" action="process_reschedule.php">
                    <input type="hidden" id="schedule_id" name="schedule_id">
                    <input type="hidden" id="original_status" name="original_status">
                    <input type="hidden" id="original_date" name="original_date">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($statusFilter ?? 'Approved') ?>">

                    <div class="form-group">
                        <label for="schedule_date">New Schedule Date <span style="color: red;">*</span></label>
                        <input type="date" id="schedule_date" name="schedule_date" min="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="date_span">Duration (Days) <span style="color: red;">*</span></label>
                        <input type="number" id="date_span" name="date_span" min="0" max="30" required>
                    </div>

                    <div class="form-group">
                        <label for="start_time">Start Time <span style="color: red;">*</span></label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time <span style="color: red;">*</span></label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>

                    <div class="form-group">
                        <label for="reschedule_reason">Reason for Rescheduling <span
                                style="color: red;">*</span></label>
                        <textarea id="reschedule_reason" name="reschedule_reason"
                            placeholder="Please provide a reason for rescheduling this appointment..."
                            required></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeRescheduleModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reschedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Schedule Details</h2>
                <span class="close-modal" onclick="closeDetailsModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div class="schedule-info" id="scheduleDetailsContent" style="position: relative;">
                    <img src="../../LandingPage/others/logo.png" alt="CAFCA Logo"
                        style="position: absolute; top: 10px; right: 10px; width: 80px; height: 80px; object-fit: contain;">
                    <h3>Schedule Information</h3>
                    <p><strong>Schedule ID:</strong> <span id="details-id"></span></p>
                    <p><strong>Farmer:</strong> <span id="details-farmer"></span></p>
                    <p><strong>Machine:</strong> <span id="details-machine"></span></p>
                    <p><strong>Start Date:</strong> <span id="details-start-date"></span></p>
                    <p><strong>End Date:</strong> <span id="details-end-date"></span></p>
                    <p><strong>Start Time:</strong> <span id="details-start-time"></span></p>
                    <p><strong>End Time:</strong> <span id="details-end-time"></span></p>
                    <p><strong>Duration:</strong> <span id="details-duration"></span> day(s)</p>
                    <p><strong>Status:</strong> <span id="details-status"></span></p>
                    <div id="details-reschedule-info"
                        style="display: none; margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 5px;">
                        <p style="margin: 5px 0;"><strong>Rescheduled At:</strong> <span
                                id="details-rescheduled-at"></span></p>
                        <p style="margin: 5px 0;"><strong>Reschedule Reason:</strong> <span
                                id="details-reschedule-reason"></span></p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printScheduleAsImage()">Download as
                        Image</button>
                </div>
            </div>
        </div>
    </div>

    <!-- APPROVE SUCCESS MSG -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const alert = document.querySelector('.alert');
        const progressBarInner = document.querySelector('.progress-bar-inner');
        const timerDuration = 3;
        const interval = 30;
        const totalSteps = timerDuration * 1000 / interval;
        let currentStep = 0;

        if (progressBarInner) {
            const timer = setInterval(() => {
                currentStep++;
                const progressWidth = (currentStep / totalSteps) * 100;
                progressBarInner.style.width = progressWidth + '%';

                if (progressWidth >= 100) {
                    clearInterval(timer);
                    if (alert) alert.remove();
                }
            }, interval);
        }
    });
    </script>

    <!-- MODAL -->
    <script>
    function openRescheduleModal(id, farmerName, machineName, scheduleDate, dateSpan, startTime, endTime, status) {
        const modal = document.getElementById('rescheduleModal');

        document.getElementById('current-farmer').textContent = farmerName;
        document.getElementById('current-machine').textContent = machineName;
        document.getElementById('current-date').textContent = formatDate(scheduleDate);
        document.getElementById('current-duration').textContent = dateSpan;
        document.getElementById('current-time').textContent = formatTime(startTime) + ' - ' + formatTime(endTime);
        document.getElementById('current-status').textContent = status;

        document.getElementById('schedule_id').value = id;
        document.getElementById('original_status').value = status;
        document.getElementById('original_date').value = scheduleDate;
        document.getElementById('schedule_date').value = scheduleDate;
        document.getElementById('date_span').value = dateSpan;
        document.getElementById('start_time').value = startTime;
        document.getElementById('end_time').value = endTime;
        document.getElementById('reschedule_reason').value = '';

        document.getElementById('errorMessage').style.display = 'none';

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeRescheduleModal() {
        const modal = document.getElementById('rescheduleModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        return date.toLocaleDateString('en-US', options);
    }

    function formatTime(timeString) {
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }

    window.onclick = function(event) {
        const modal = document.getElementById('rescheduleModal');
        if (event.target == modal) {
            closeRescheduleModal();
        }
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeRescheduleModal();
        }
    });

    document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
        const originalDate = document.getElementById('original_date').value;
        const newDate = document.getElementById('schedule_date').value;
        const errorMsg = document.getElementById('errorMessage');

        if (newDate === originalDate) {
            e.preventDefault();
            errorMsg.textContent = 'You must change the date when rescheduling.';
            errorMsg.style.display = 'block';
            errorMsg.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
            return false;
        }

        errorMsg.style.display = 'none';

        if (!confirm('Are you sure you want to reschedule this appointment?')) {
            e.preventDefault();
            return false;
        }
        // Allow the form to submit normally - process_reschedule.php will handle the redirect
        return true;
    });

    document.querySelector('.close-modal').addEventListener('click', closeRescheduleModal);

    // TOOLTIP FOR RESCHEDULE REASON
    document.addEventListener('DOMContentLoaded', function() {
        const infoIcons = document.querySelectorAll('.reschedule-info-icon');

        infoIcons.forEach(icon => {
            icon.addEventListener('mouseenter', function(e) {
                const rect = this.getBoundingClientRect();
                const tooltip = window.getComputedStyle(this, '::before');

                this.style.setProperty('--tooltip-left', rect.left + (rect.width / 2) + 'px');
                this.style.setProperty('--tooltip-top', (rect.top - 10) + 'px');
            });
        });
    });
    </script>

    <!-- ADD SCHEDULE MODAL -->
    <div id="addScheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Schedule</h2>
                <span class="close-modal" onclick="closeAddScheduleModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div id="addScheduleErrorMessage" class="alert-error" style="display: none;"></div>

                <form id="addScheduleForm" method="POST">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentStatus) ?>">

                    <div class="form-group">
                        <label for="farmer_id">Farmer <span style="color: red;">*</span></label>
                        <select name="farmer_id" id="farmer_id" required class="form-select">
                            <option value="">Select a Farmer</option>
                            <?php
                            $farmerList = $conn->query("SELECT id, name FROM farmers ORDER BY name ASC");
                            while ($row = $farmerList->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>">
                                <?= htmlspecialchars($row['name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="machine_id">Machine <span style="color: red;">*</span></label>
                        <select name="machine_id" id="machine_id" required class="form-select"
                            onchange="checkMachineAvailability()">
                            <option value="">Select a Machine</option>
                            <?php
                            $machineList = $conn->query("SELECT id, name, status, unavailable_from, unavailable_until FROM machines ORDER BY name ASC");
                            while ($row = $machineList->fetch_assoc()):
                                $isTotallyDamaged = ($row['status'] === 'Totally Damaged');

                                // Check if machine has unavailable dates set
                                $unavailableInfo = '';
                                if (!empty($row['unavailable_from']) && !empty($row['unavailable_until'])) {
                                    $fromDate = new DateTime($row['unavailable_from']);
                                    $untilDate = new DateTime($row['unavailable_until']);
                                    $unavailableInfo = ' [Unavailable: ' . $fromDate->format('M d') . ' - ' . $untilDate->format('M d, Y') . ']';
                                }
                            ?>
                            <option value="<?= $row['id'] ?>" data-status="<?= htmlspecialchars($row['status']) ?>"
                                data-unavailable-from="<?= htmlspecialchars($row['unavailable_from'] ?? '') ?>"
                                data-unavailable-until="<?= htmlspecialchars($row['unavailable_until'] ?? '') ?>"
                                <?= $isTotallyDamaged ? 'disabled style="color: #aaa;"' : '' ?>>
                                <?= htmlspecialchars($row['name']) ?><?= $isTotallyDamaged ? ' — Unavailable. This machine is Totally Damaged.' : '' ?><?= htmlspecialchars($unavailableInfo) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <div id="addMachineWarning" class="alert-error"
                            style="display: none; margin-top: 0.5rem; transition: all 0.2s;"></div>

                    </div>

                    <div class="form-group">
                        <label for="add_schedule_date">Schedule Date <span style="color: red;">*</span></label>
                        <input type="date" id="add_schedule_date" name="schedule_date" min="<?= date('Y-m-d') ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="add_end_date">Schedule End Date <span style="color: red;">*</span></label>
                        <input type="date" id="add_end_date" name="end_date" min="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="add_start_time">Start Time <span style="color: red;">*</span></label>
                        <input type="time" id="add_start_time" name="start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="add_end_time">End Time <span style="color: red;">*</span></label>
                        <input type="time" id="add_end_time" name="end_time" required>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary"
                            onclick="closeAddScheduleModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #4CAF50;">Create
                            Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT SCHEDULE MODAL -->
    <div id="editScheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Schedule</h2>
                <span class="close-modal" onclick="closeEditScheduleModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div id="editScheduleErrorMessage" class="alert-error" style="display: none;"></div>

                <form id="editScheduleForm" method="POST">
                    <input type="hidden" id="edit_schedule_id" name="id">

                    <div class="form-group">
                        <label for="edit_farmer_id">Farmer <span style="color: red;">*</span></label>
                        <select id="edit_farmer_id" name="farmer_id" required>
                            <option value="">Select a Farmer</option>
                            <?php
                            $farmerList = $conn->query("SELECT id, name FROM farmers ORDER BY name");
                            while ($row = $farmerList->fetch_assoc()):
                            ?>
                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_machine_id">Machine <span style="color: red;">*</span></label>
                        <select id="edit_machine_id" name="machine_id" required
                            onchange="checkEditMachineAvailability()">
                            <option value="">Select a Machine</option>
                            <?php
                            $machineList = $conn->query("SELECT id, name, status, unavailable_from, unavailable_until FROM machines ORDER BY name");
                            while ($row = $machineList->fetch_assoc()):
                                $isTotallyDamaged = ($row['status'] === 'Totally Damaged');

                                // Check if machine has unavailable dates set
                                $unavailableInfo = '';
                                if (!empty($row['unavailable_from']) && !empty($row['unavailable_until'])) {
                                    $fromDate = new DateTime($row['unavailable_from']);
                                    $untilDate = new DateTime($row['unavailable_until']);
                                    $unavailableInfo = ' [Unavailable: ' . $fromDate->format('M d') . ' - ' . $untilDate->format('M d, Y') . ']';
                                }
                            ?>
                            <option value="<?= $row['id'] ?>" data-status="<?= htmlspecialchars($row['status']) ?>"
                                data-unavailable-from="<?= htmlspecialchars($row['unavailable_from'] ?? '') ?>"
                                data-unavailable-until="<?= htmlspecialchars($row['unavailable_until'] ?? '') ?>"
                                <?= $isTotallyDamaged ? 'disabled style="color: #aaa;"' : '' ?>>
                                <?= htmlspecialchars($row['name']) ?><?= $isTotallyDamaged ? ' — Unavailable. This machine is Totally Damaged' : '' ?><?= htmlspecialchars($unavailableInfo) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <div id="editMachineWarning" class="alert-error"
                            style="display: none; margin-top: 0.5rem; transition: all 0.2s;"></div>
                        <div id="editMachineAvailability"
                            style="margin-top: 0.5rem; padding: 0.75rem; background: #e3f2fd; border-radius: 4px; display: none; border-left: 4px solid #2196F3;">
                            <small style="display: block; margin-bottom: 0.25rem;"><strong>📊 Availability
                                    Check:</strong></small>
                            <small id="editAvailabilityMessage" style="color: #1565C0;"></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_schedule_date">Schedule Date <span style="color: red;">*</span></label>
                        <input type="date" id="edit_schedule_date" name="schedule_date" min="<?= date('Y-m-d') ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="edit_date_span">Date Span (days) <span style="color: red;">*</span></label>
                        <input type="number" id="edit_date_span" name="date_span" min="0" required>
                        <small style="color: var(--color-dark-variant); display: block; margin-top: 0.25rem;">Number of
                            days to add to the schedule date to get the end date.</small>
                    </div>

                    <div class="form-group">
                        <label for="edit_end_date_preview">End Date (preview)</label>
                        <input type="date" id="edit_end_date_preview" readonly>
                    </div>

                    <div class="form-group">
                        <label for="edit_start_time">Start Time <span style="color: red;">*</span></label>
                        <input type="time" id="edit_start_time" name="start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_end_time">End Time <span style="color: red;">*</span></label>
                        <input type="time" id="edit_end_time" name="end_time" required>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary"
                            onclick="closeEditScheduleModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #4CAF50;">Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADD SCHEDULE MODAL SCRIPT -->
    <script>
    // Function to check machine availability in real-time
    function checkMachineAvailability() {
        const machineSelect = document.getElementById('machine_id');
        const scheduleDate = document.getElementById('add_schedule_date').value;
        const endDate = document.getElementById('add_end_date').value;
        const startTime = document.getElementById('add_start_time').value;
        const endTime = document.getElementById('add_end_time').value;

        const selectedOption = machineSelect.options[machineSelect.selectedIndex];
        const warningDiv = document.getElementById('addMachineWarning');

        // Hide warning initially
        warningDiv.style.display = 'none';

        if (!selectedOption || !selectedOption.value) {
            return;
        }

        const status = selectedOption.getAttribute('data-status');
        const machineId = selectedOption.value;
        const unavailableFrom = selectedOption.getAttribute('data-unavailable-from');
        const unavailableUntil = selectedOption.getAttribute('data-unavailable-until');

        // Check status first
        if (status === 'Totally Damaged') {
            warningDiv.innerHTML =
                '⚠️ This machine is <strong>Totally Damaged</strong> and cannot be scheduled for use.';
            warningDiv.style.background = '#fde8e8';
            warningDiv.style.color = '#b71c1c';
            warningDiv.style.display = 'block';
            return;
        }

        // Show unavailable date info if set
        if (unavailableFrom && unavailableUntil) {
            const fromDate = new Date(unavailableFrom);
            const untilDate = new Date(unavailableUntil);
            const fromFormatted = fromDate.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
            const untilFormatted = untilDate.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
            warningDiv.innerHTML =
                `ℹ️ <strong>Note:</strong> This machine is unavailable from ${fromFormatted} to ${untilFormatted}.`;
            warningDiv.style.background = '#e3f2fd';
            warningDiv.style.color = '#1565C0';
            warningDiv.style.display = 'block';
        }

        if (status === 'Partially Damaged') {
            warningDiv.innerHTML =
                '⚠️ This machine is <strong>Partially Damaged</strong>. You will be asked to confirm before booking.';
            warningDiv.style.background = '#fff8e1';
            warningDiv.style.color = '#7a5000';
            warningDiv.style.display = 'block';
        }

        // If dates and times are filled, check real-time availability
        if (scheduleDate && endDate && startTime && endTime) {
            fetch('check_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `machine_id=${machineId}&schedule_date=${scheduleDate}&end_date=${endDate}&start_time=${startTime}&end_time=${endTime}`
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        warningDiv.innerHTML = `❌ ${data.message || 'Unable to check availability'}`;
                        warningDiv.style.background = '#ffebee';
                        warningDiv.style.color = '#c62828';
                        warningDiv.style.display = 'block';
                    }
                })
                .catch(error => console.error('Error checking availability:', error));
        }
    }

    // Add event listeners to trigger availability check when dates/times change
    document.addEventListener('DOMContentLoaded', function() {
        const dateTimeInputs = ['add_schedule_date', 'add_end_date', 'add_start_time', 'add_end_time'];
        dateTimeInputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', checkMachineAvailability);
            }
        });
    });

    function openAddScheduleModal() {
        const modal = document.getElementById('addScheduleModal');

        document.getElementById('addScheduleForm').reset();
        document.getElementById('addScheduleErrorMessage').style.display = 'none';
        document.getElementById('addMachineWarning').style.display = 'none';

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeAddScheduleModal() {
        const modal = document.getElementById('addScheduleModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const addModal = document.getElementById('addScheduleModal');
            const editModal = document.getElementById('editScheduleModal');
            if (addModal && addModal.style.display === 'block') {
                closeAddScheduleModal();
            }
            if (editModal && editModal.style.display === 'block') {
                closeEditScheduleModal();
            }
        }
    });

    document.getElementById('addScheduleForm').addEventListener('submit', function(e) {
        e.preventDefault();

        // Guard: block submission if a Totally Damaged machine is selected
        const machineSelect = document.getElementById('machine_id');
        const selectedOption = machineSelect.options[machineSelect.selectedIndex];
        if (selectedOption && selectedOption.getAttribute('data-status') === 'Totally Damaged') {
            const errorDiv = document.getElementById('addScheduleErrorMessage');
            errorDiv.textContent =
                'Cannot book a schedule: the selected machine is Totally Damaged and unavailable for use.';
            errorDiv.style.display = 'block';
            return;
        }

        // Confirmation: warn if Partially Damaged machine is selected
        if (selectedOption && selectedOption.getAttribute('data-status') === 'Partially Damaged') {
            const machineName = selectedOption.textContent.trim();
            const confirmed = confirm(
                `⚠️ "${machineName}" is Partially Damaged. It may not perform at full capacity and could affect operations.\n\nAre you sure you want to book this machine?`
                );
            if (!confirmed) return;
        }

        const formData = new FormData(this);

        fetch('process_add_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'schedule.php?status=' + encodeURIComponent(data.redirect) +
                        '&added=1';
                } else {
                    const errorDiv = document.getElementById('addScheduleErrorMessage');
                    errorDiv.textContent = data.message || 'Failed to create schedule. Please try again.';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                const errorDiv = document.getElementById('addScheduleErrorMessage');
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
                console.error('Error:', error);
            });
    });
    </script>

    <!-- EDIT SCHEDULE MODAL SCRIPT -->
    <script>
    // Function to check machine availability for edit modal
    function checkEditMachineAvailability() {
        const machineSelect = document.getElementById('edit_machine_id');
        const scheduleDate = document.getElementById('edit_schedule_date').value;
        const dateSpan = document.getElementById('edit_date_span').value;
        const startTime = document.getElementById('edit_start_time').value;
        const endTime = document.getElementById('edit_end_time').value;
        const scheduleId = document.getElementById('edit_schedule_id').value;

        const selectedOption = machineSelect.options[machineSelect.selectedIndex];
        const warningDiv = document.getElementById('editMachineWarning');
        const availabilityDiv = document.getElementById('editMachineAvailability');
        const availabilityMsg = document.getElementById('editAvailabilityMessage');

        // Hide both divs initially
        warningDiv.style.display = 'none';
        availabilityDiv.style.display = 'none';

        if (!selectedOption || !selectedOption.value) {
            return;
        }

        const status = selectedOption.getAttribute('data-status');
        const machineId = selectedOption.value;
        const unavailableFrom = selectedOption.getAttribute('data-unavailable-from');
        const unavailableUntil = selectedOption.getAttribute('data-unavailable-until');

        // Check status first
        if (status === 'Totally Damaged') {
            warningDiv.innerHTML =
                '⚠️ This machine is <strong>Totally Damaged</strong> and cannot be scheduled for use.';
            warningDiv.style.background = '#fde8e8';
            warningDiv.style.color = '#b71c1c';
            warningDiv.style.display = 'block';
            return;
        }

        // Show unavailable date info if set
        if (unavailableFrom && unavailableUntil) {
            const fromDate = new Date(unavailableFrom);
            const untilDate = new Date(unavailableUntil);
            const fromFormatted = fromDate.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
            const untilFormatted = untilDate.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
            warningDiv.innerHTML =
                `ℹ️ <strong>Note:</strong> This machine is unavailable from ${fromFormatted} to ${untilFormatted}.`;
            warningDiv.style.background = '#e3f2fd';
            warningDiv.style.color = '#1565C0';
            warningDiv.style.display = 'block';
        }

        if (status === 'Partially Damaged') {
            warningDiv.innerHTML =
                '⚠️ This machine is <strong>Partially Damaged</strong>. You will be asked to confirm before updating.';
            warningDiv.style.background = '#fff8e1';
            warningDiv.style.color = '#7a5000';
            warningDiv.style.display = 'block';
        }

        // If dates and times are filled, check real-time availability
        if (scheduleDate && dateSpan && startTime && endTime) {
            // Calculate end date
            const startDateObj = new Date(scheduleDate);
            startDateObj.setDate(startDateObj.getDate() + parseInt(dateSpan));
            const endDate = startDateObj.toISOString().split('T')[0];

            fetch('check_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `machine_id=${machineId}&schedule_date=${scheduleDate}&end_date=${endDate}&start_time=${startTime}&end_time=${endTime}&exclude_schedule_id=${scheduleId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        availabilityDiv.style.display = 'block';
                        availabilityDiv.style.background = '#ffebee';
                        availabilityDiv.style.borderColor = '#f44336';
                        availabilityMsg.style.color = '#c62828';
                        availabilityMsg.innerHTML = `❌ ${data.message || 'This machine is already booked for the selected period.'}`;
                    }
                })
                .catch(error => console.error('Error checking availability:', error));
        }
    }

    function openEditScheduleModal(scheduleId) {
        const modal = document.getElementById('editScheduleModal');
        const errorDiv = document.getElementById('editScheduleErrorMessage');
        errorDiv.style.display = 'none';
        document.getElementById('editMachineWarning').style.display = 'none';
        document.getElementById('editMachineAvailability').style.display = 'none';

        fetch(`schedule.php?action=get_schedule&id=${scheduleId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_schedule_id').value = data.data.id;
                    document.getElementById('edit_farmer_id').value = data.data.farmer_id;
                    document.getElementById('edit_machine_id').value = data.data.machine_id;
                    document.getElementById('edit_schedule_date').value = data.data.schedule_date;
                    document.getElementById('edit_date_span').value = data.data.date_span;
                    document.getElementById('edit_start_time').value = data.data.start_time;
                    document.getElementById('edit_end_time').value = data.data.end_time;

                    updateEditEndDate();
                    checkEditMachineAvailability();

                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('Error loading schedule data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading schedule data');
            });
    }

    function closeEditScheduleModal() {
        const modal = document.getElementById('editScheduleModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function updateEditEndDate() {
        const scheduleDateInput = document.getElementById('edit_schedule_date');
        const dateSpanInput = document.getElementById('edit_date_span');
        const endDateInput = document.getElementById('edit_end_date_preview');

        const sd = scheduleDateInput.value;
        let span = parseInt(dateSpanInput.value, 10);
        if (!sd || isNaN(span)) {
            endDateInput.value = '';
            return;
        }

        const d = new Date(sd);
        d.setDate(d.getDate() + span);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        endDateInput.value = `${yyyy}-${mm}-${dd}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const editScheduleDate = document.getElementById('edit_schedule_date');
        const editDateSpan = document.getElementById('edit_date_span');
        const editStartTime = document.getElementById('edit_start_time');
        const editEndTime = document.getElementById('edit_end_time');

        if (editScheduleDate && editDateSpan) {
            editScheduleDate.addEventListener('change', function() {
                updateEditEndDate();
                checkEditMachineAvailability();
            });
            editDateSpan.addEventListener('input', function() {
                updateEditEndDate();
                checkEditMachineAvailability();
            });
        }

        if (editStartTime) {
            editStartTime.addEventListener('change', checkEditMachineAvailability);
        }

        if (editEndTime) {
            editEndTime.addEventListener('change', checkEditMachineAvailability);
        }
    });

    document.getElementById('editScheduleForm').addEventListener('submit', function(e) {
        e.preventDefault();

        // Guard: block submission if a Totally Damaged machine is selected
        const machineSelect = document.getElementById('edit_machine_id');
        const selectedOption = machineSelect.options[machineSelect.selectedIndex];
        if (selectedOption && selectedOption.getAttribute('data-status') === 'Totally Damaged') {
            const errorDiv = document.getElementById('editScheduleErrorMessage');
            errorDiv.textContent =
                'Cannot save changes: the selected machine is Totally Damaged and unavailable for use.';
            errorDiv.style.display = 'block';
            return;
        }

        // Confirmation: warn if Partially Damaged machine is selected
        if (selectedOption && selectedOption.getAttribute('data-status') === 'Partially Damaged') {
            const machineName = selectedOption.textContent.trim();
            const confirmed = confirm(
                `⚠️ The selected machine "${machineName}" is Partially Damaged.\n\nIt may not perform at full capacity and could affect operations.\n\nAre you sure you want to book this machine?`
                );
            if (!confirmed) return;
        }

        const formData = new FormData(this);

        fetch('process_edit_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href =
                        'schedule.php?status=<?= urlencode($statusFilter ?: 'Pending') ?>&updated=1';
                } else {
                    const errorDiv = document.getElementById('editScheduleErrorMessage');
                    errorDiv.textContent = data.message || 'Failed to update schedule. Please try again.';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                const errorDiv = document.getElementById('editScheduleErrorMessage');
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
                console.error('Error:', error);
            });
    });
    </script>

    <!-- DETAILS MODAL SCRIPT -->
    <script>
    function openDetailsModal(scheduleId) {
        const modal = document.getElementById('detailsModal');

        fetch(`schedule.php?action=get_schedule&id=${scheduleId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const schedule = data.data;

                    const startDate = new Date(schedule.schedule_date);
                    const endDate = new Date(startDate);
                    endDate.setDate(startDate.getDate() + parseInt(schedule.date_span || 0));

                    const now = new Date();
                    const startDateTime = new Date(schedule.schedule_date + ' ' + schedule.start_time);
                    const endDateTime = new Date(endDate.toISOString().split('T')[0] + ' ' + schedule.end_time);

                    let currentStatus = schedule.status;

                    // Check if Pending schedule should be Expired
                    if (schedule.status === 'Pending') {
                        if (now >= startDateTime) {
                            currentStatus = 'Expired';
                        }
                    }
                    // Check if Approved schedule is On going or Completed
                    else if (schedule.status === 'Approved') {
                        if (now >= startDateTime && now <= endDateTime) {
                            currentStatus = 'On going';
                        } else if (now > endDateTime) {
                            currentStatus = 'Completed';
                        }
                    }

                    document.getElementById('details-id').textContent = schedule.id;
                    document.getElementById('details-farmer').textContent = schedule.farmer_name;
                    document.getElementById('details-machine').textContent = schedule.machine_name;
                    document.getElementById('details-start-date').textContent = formatDate(schedule.schedule_date);
                    document.getElementById('details-end-date').textContent = formatDate(endDate.toISOString()
                        .split('T')[0]);
                    document.getElementById('details-start-time').textContent = formatTime(schedule.start_time);
                    document.getElementById('details-end-time').textContent = formatTime(schedule.end_time);
                    document.getElementById('details-duration').textContent = schedule.date_span || 0;
                    document.getElementById('details-status').textContent = currentStatus;

                    // Show reschedule info if available
                    if (schedule.reschedule_reason) {
                        document.getElementById('details-reschedule-info').style.display = 'block';
                        document.getElementById('details-rescheduled-at').textContent =
                            schedule.rescheduled_at ? new Date(schedule.rescheduled_at).toLocaleString() : 'N/A';
                        document.getElementById('details-reschedule-reason').textContent = schedule
                            .reschedule_reason;
                    } else {
                        document.getElementById('details-reschedule-info').style.display = 'none';
                    }

                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('Error loading schedule details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading schedule details');
            });
    }

    function closeDetailsModal() {
        const modal = document.getElementById('detailsModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // PRINT SCHEDULE AS IMAGE
    function printScheduleAsImage() {
        const content = document.getElementById('scheduleDetailsContent');
        const originalBg = content.style.backgroundColor;
        const originalPadding = content.style.padding;

        content.style.backgroundColor = '#ffffff';
        content.style.padding = '20px';

        html2canvas(content, {
            backgroundColor: '#ffffff',
            scale: 2,
            logging: false,
            useCORS: true,
            allowTaint: true
        }).then(canvas => {
            content.style.backgroundColor = originalBg;
            content.style.padding = originalPadding;

            // Convert canvas to blob
            canvas.toBlob(function(blob) {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                const scheduleId = document.getElementById('details-id').textContent;
                const farmerName = document.getElementById('details-farmer').textContent;

                link.href = url;
                link.download = `Schedule_${scheduleId}_${farmerName.replace(/\s+/g, '_')}.jpg`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }, 'image/jpeg', 0.95);
        }).catch(error => {
            content.style.backgroundColor = originalBg;
            content.style.padding = originalPadding;
            console.error('Error generating image:', error);
            alert('Error generating image. Please try again.');
        });
    }

    window.addEventListener('click', function(event) {
        const detailsModal = document.getElementById('detailsModal');
        if (event.target === detailsModal) {
            closeDetailsModal();
        }
    });
    </script>

    <!-- SEARCH & FILTER SCRIPT -->
    <script>
    (function() {
        const STORAGE_KEY_QUERY = 'scheduleSearch_query';
        const STORAGE_KEY_OPEN = 'scheduleSearch_open';

        const searchInput = document.getElementById('scheduleSearch');
        const clearBtn = document.getElementById('clearSearch');
        const resultsCount = document.getElementById('resultsCount');
        const searchWrap = document.getElementById('searchWrap');
        const toggleBtn = document.getElementById('searchToggleBtn');

        if (!searchInput) return;

        // Columns to highlight: farmer(1), machine(2), status(7)
        const SEARCHABLE_COLS = [1, 2, 7];

        /* ---- expand / collapse ---- */
        function openSearch() {
            searchWrap.classList.add('expanded');
            localStorage.setItem(STORAGE_KEY_OPEN, '1');
            setTimeout(() => searchInput.focus(), 250);
        }

        function closeSearch() {
            searchWrap.classList.remove('expanded');
            localStorage.removeItem(STORAGE_KEY_OPEN);
        }

        toggleBtn.addEventListener('click', function() {
            if (searchWrap.classList.contains('expanded')) {
                searchInput.value = '';
                localStorage.removeItem(STORAGE_KEY_QUERY);
                applyFilters();
                closeSearch();
            } else {
                openSearch();
            }
        });

        searchWrap.addEventListener('focusout', function(e) {
            if (!searchWrap.contains(e.relatedTarget)) {
                if (!searchInput.value) {
                    closeSearch();
                }
            }
        });

        /* ---- filtering ---- */
        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const rows = document.querySelectorAll('.schedule-row');

            // Persist query to localStorage
            if (query) {
                localStorage.setItem(STORAGE_KEY_QUERY, query);
            } else {
                localStorage.removeItem(STORAGE_KEY_QUERY);
            }

            clearBtn.style.display = query ? 'flex' : 'none';

            let totalVisible = 0;
            let totalRows = rows.length;

            rows.forEach(row => {
                const farmer = row.dataset.farmer || '';
                const machine = row.dataset.machine || '';
                const status = row.dataset.status || '';

                const matchesSearch = !query ||
                    farmer.includes(query) ||
                    machine.includes(query) ||
                    status.includes(query);

                if (matchesSearch) {
                    row.style.display = '';
                    totalVisible++;

                    SEARCHABLE_COLS.forEach(colIdx => {
                        const cell = row.cells[colIdx];
                        if (!cell) return;
                        if (cell.dataset.original === undefined) {
                            cell.dataset.original = cell.textContent;
                        }
                        const original = cell.dataset.original;
                        if (query) {
                            const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
                            cell.innerHTML = original.replace(regex,
                                '<mark class="search-highlight">$1</mark>');
                        } else {
                            cell.textContent = original;
                        }
                    });
                } else {
                    row.style.display = 'none';
                    SEARCHABLE_COLS.forEach(colIdx => {
                        const cell = row.cells[colIdx];
                        if (cell && cell.dataset.original !== undefined) {
                            cell.textContent = cell.dataset.original;
                        }
                    });
                }
            });

            // Show/hide "no results" row per table
            document.querySelectorAll('.table').forEach(table => {
                const tableRows = table.querySelectorAll('.schedule-row');
                const noResultsRow = table.querySelector('.no-results-row');
                if (!noResultsRow) return;
                const anyVisible = Array.from(tableRows).some(r => r.style.display !== 'none');
                noResultsRow.style.display = (tableRows.length > 0 && !anyVisible) ? '' : 'none';
            });

            resultsCount.textContent = query ? `${totalVisible} of ${totalRows} shown` : '';
        }

        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        searchInput.addEventListener('input', applyFilters);

        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            localStorage.removeItem(STORAGE_KEY_QUERY);
            applyFilters();
            searchInput.focus();
        });

        /* ---- Restore state on page load ---- */
        const savedQuery = localStorage.getItem(STORAGE_KEY_QUERY);
        const savedOpen = localStorage.getItem(STORAGE_KEY_OPEN);

        if (savedQuery || savedOpen) {
            searchWrap.classList.add('expanded');
        }
        if (savedQuery) {
            searchInput.value = savedQuery;
            applyFilters();
        }
    })();
    </script>

    <script src="../main/dashscript.js"></script>
</body>

</html>