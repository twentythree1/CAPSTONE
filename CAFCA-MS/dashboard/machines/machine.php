<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: /CAPSTONE/CAFCA-MS/login/logindex.php");
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


// Set timezone
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// Sync schedule statuses in DB before counting
$now = new DateTime();
$currentDateTime = $now->format('Y-m-d H:i:s');

// Auto-expire past pending schedules
$conn->query("UPDATE schedules SET status = 'Expired' WHERE status = 'Pending' AND CONCAT(schedule_date, ' ', start_time) < '$currentDateTime'");

// Count machines by status
$machineCounts = [
    'Available' => 0,
    'Partially Damaged' => 0,
    'Totally Damaged' => 0,
    'Not Returned' => 0
];

// Available badge: count of machines with status='Available'
$availableBadgeSql = "SELECT COUNT(*) AS available_count FROM machines WHERE status = 'Available'";
$availBadgeResult = $conn->query($availableBadgeSql);
if ($availBadgeResult) {
    $availBadgeRow = $availBadgeResult->fetch_assoc();
    $machineCounts['Available'] = (int)($availBadgeRow['available_count'] ?? 0);
    $availBadgeResult->free();
}

// Partially Damaged / Totally Damaged badges: count machines by status
foreach (['Partially Damaged', 'Totally Damaged'] as $dmgStatus) {
    $dmgBadgeSql = "SELECT COUNT(*) as cnt FROM machines WHERE status = ?";
    $dmgStmt = $conn->prepare($dmgBadgeSql);
    $dmgStmt->bind_param("s", $dmgStatus);
    $dmgStmt->execute();
    $dmgRow = $dmgStmt->get_result()->fetch_assoc();
    $machineCounts[$dmgStatus] = (int)($dmgRow['cnt'] ?? 0);
    $dmgStmt->close();
}

// Count "Not Returned" — uses the EXACT same JOINs and conditions as the display query
// so the badge always matches the number of rows shown in the table.
// LEFT JOIN machines/farmers so orphaned records don't get silently dropped.
$notReturnedSql = "SELECT COUNT(*) as count
                   FROM schedules s
                   LEFT JOIN machines m ON s.machine_id = m.id
                   LEFT JOIN farmers f ON s.farmer_id = f.id
                   WHERE s.status IN ('Approved', 'Completed')
                   AND (s.return_date IS NULL OR s.return_date = '')
                   AND NOW() > CONCAT(DATE_ADD(s.schedule_date, INTERVAL s.date_span DAY), ' ', s.end_time)";
$notReturnedResult = $conn->query($notReturnedSql);
$notReturnedCount = 0;

if ($notReturnedResult) {
    $row = $notReturnedResult->fetch_assoc();
    $notReturnedCount = (int)($row['count'] ?? 0);
    $notReturnedResult->free();
}
$machineCounts['Not Returned'] = $notReturnedCount;

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

        $tz = new DateTimeZone('Asia/Manila');
        try {
            $startDt = new DateTime($scheduleDate . ' ' . $startTime, $tz);
        } catch (Exception $e) {
            $startDt = new DateTime($scheduleDate . ' 00:00:00', $tz);
        }

        $endDateStr = date('Y-m-d', strtotime($scheduleDate . " +{$dateSpan} days"));
        try {
            $endDt = new DateTime($endDateStr . ' ' . $endTime, $tz);
        } catch (Exception $e) {
            $endDt = new DateTime($endDateStr . ' 23:59:59', $tz);
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

// Handle AJAX request for fetching machine data
if (isset($_GET['action']) && $_GET['action'] == 'get_machine' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM machines WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Machine not found']);
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
    <link rel="icon" href="../../assets/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Admin</title>
    <link rel="stylesheet" href="../farmers_sec/farmerstyle.css">
    <!-- MATERIAL ICONS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp">
</head>

<body>
    <div class="container">
        <aside>
            <div class="top">
                <a class="logo" href="../main/dashdex.php">
                    <img src="../../assets/logo.png">
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
                            <span class="count-badge"><?= htmlspecialchars($machineCounts['Partially Damaged'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=Totally Damaged">
                            <span>Totally Damaged</span>
                            <span class="count-badge"><?= htmlspecialchars($machineCounts['Totally Damaged'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=Not Returned">
                            <span>Not Returned</span>
                            <span class="count-badge"><?= htmlspecialchars($machineCounts['Not Returned'] ?? 0) ?></span>
                        </a>
                    </div>
                </div>
                <div class="sidebar-dropdown <?= ($isSchedulePage && $statusParam) ? 'open' : '' ?>">
                    <a href="javascript:void(0)" class="dropdown-toggle"
                        aria-expanded="<?= ($isSchedulePage && $statusParam) ? 'true' : 'false' ?>">
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
                <a href="../settings/settings.php">
                    <span class="material-icons-sharp">settings</span>
                    <h3>Settings</h3>
                </a>
                <div class="logout"><a href="../../login/logout.php" onclick="return confirm('Are you sure you want to log out?');" class="danger">
                        <span class="material-icons-sharp">logout</span>
                        <h3>Log out</h3>
                    </a>
                </div>
            </div>
        </aside>

        <main>
            <div class="top">
                <?php if (isset($_GET['added'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: relative;">
                    New machine was added successfully!
                    <div class="progress-bar">
                        <div class="progress-bar-inner"></div>
                    </div>
                </div>
                <?php endif; ?>
                <button id="menu-btn">
                    <span class="material-icons-sharp">menu</span>
                </button>
                <div class="theme-toggler">
                    <span class="material-icons-sharp active">light_mode</span>
                    <span class="material-icons-sharp">dark_mode</span>
                </div>
                <div class="profile" style="display: flex; flex-direction: column; text-align: right;">
                    <span
                        style="font-size: 18px; text-transform: capitalize; font-weight: 700; "><?= $_SESSION['username']; ?></span>
                    <small class="text-muted">Admin</small>
                </div>
            </div>
            <div class="title">
                <h2 class="machine-count">List of Machines</h2>
                <div class="title-actions">
                    <span class="results-count" id="resultsCount"></span>
                    <div class="search-expand-wrap" id="searchWrap">
                        <div class="search-fields" id="searchFields">
                            <div class="search-input-wrap">
                                <input type="text" id="machineSearch" placeholder="Search machines..." autocomplete="off">
                                <button class="clear-search" id="clearSearch" title="Clear" style="display:none;">
                                    <span class="material-icons-sharp">close</span>
                                </button>
                            </div>
                        </div>
                        <button class="search-icon-btn" id="searchToggleBtn" title="Search machines" type="button">
                            <span class="material-icons-sharp">search</span>
                        </button>
                    </div>
                    <?php 
                    $currentStatus = $statusFilter ?: 'Available';
                    if ($statusFilter !== 'Not Returned'): ?>
                    <a href="javascript:void(0)" onclick="openAddMachineModal()" class="btn btn-primary farmer-btn" role="button">Add Machine</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            // Prepare data first to check if table should be shown
            if ($statusFilter === 'Not Returned') {
                // LEFT JOIN so rows with orphaned machine/farmer references still appear.
                // COALESCE gives a fallback display name if a farmer/machine record is missing.
                $sql = "SELECT s.id as schedule_id, s.schedule_date, s.date_span, s.start_time, s.end_time,
                               m.id as machine_id, COALESCE(m.name, '(Deleted Machine)') as machine_name,
                               COALESCE(f.name, '(Deleted Farmer)') as farmer_name
                        FROM schedules s
                        LEFT JOIN machines m ON s.machine_id = m.id
                        LEFT JOIN farmers f ON s.farmer_id = f.id
                        WHERE s.status IN ('Approved', 'Completed')
                        AND (s.return_date IS NULL OR s.return_date = '')
                        AND NOW() > CONCAT(DATE_ADD(s.schedule_date, INTERVAL s.date_span DAY), ' ', s.end_time)
                        ORDER BY s.schedule_date ASC";
                
                $result = $conn->query($sql);
                $notReturnedRows = [];
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $notReturnedRows[] = $row;
                    }
                }
                
                if (empty($notReturnedRows)) {
                    echo "<p style='display: flex; justify-content: center; text-transform: capitalize;'>No Not Returned Machines Found.</p>";
                } else {
                    echo "<div class='table-scroll'>
                          <table style='width:100%' class='table'>
                            <thead>
                                <tr>
                                    <th>Farmer's Name</th>
                                    <th>Machine Name</th>
                                    <th>Schedule Date</th>
                                    <th>End Date</th>
                                    <th>Duration</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>";
                    
                    foreach ($notReturnedRows as $row) {
                        $scheduleDate = $row['schedule_date'];
                        $dateSpan = (int)$row['date_span'];
                        $endDateStr = date('Y-m-d', strtotime($scheduleDate . " +{$dateSpan} days"));
                        $formattedStartDate = date('M d, Y', strtotime($scheduleDate));
                        $formattedEndDate = date('M d, Y', strtotime($endDateStr));
                        $safeFarmer  = htmlspecialchars($row['farmer_name'], ENT_QUOTES, 'UTF-8');
                        $safeMachine = htmlspecialchars($row['machine_name'], ENT_QUOTES, 'UTF-8');
                        
                        echo "
                        <tr class='machine-row'
                            data-name='" . strtolower($safeMachine) . "'
                            data-farmer='" . strtolower($safeFarmer) . "'>
                            <td>{$safeFarmer}</td>
                            <td>{$safeMachine}</td>
                            <td>{$formattedStartDate}</td>
                            <td>{$formattedEndDate}</td>
                            <td>{$dateSpan} day(s)</td>
                            <td>
                                <a class='btn btn-success btn-sm' 
                                   onclick='openReturnModal(" . $row['schedule_id'] . ", \"" . 
                                   htmlspecialchars($row['farmer_name'], ENT_QUOTES) . "\", \"" . 
                                   htmlspecialchars($row['machine_name'], ENT_QUOTES) . "\", " . 
                                   $row['machine_id'] . ")' 
                                   href='javascript:void(0)'>Return</a>
                            </td>
                        </tr>
                        ";
                    }
                    
                    echo "</tbody>
                          <tr id='noResultsRow' style='display:none;'>
                              <td colspan='6' style='text-align:center; padding:2rem; color:var(--color-dark-variant);'>
                                  <span class='material-icons-sharp' style='font-size:2rem;display:block;margin-bottom:0.5rem;'>search_off</span>
                                  No machines found matching your search.
                              </td>
                          </tr>
                          </table></div>";
                }
            }
            else {
                $baseSelect = "SELECT m.*";

                if ($statusFilter === 'Available') {
                    $availableSql = "$baseSelect FROM machines m WHERE m.status = 'Available'";
                    $result = $conn->query($availableSql);

                } else {
                    $sql = "$baseSelect FROM machines m";
                    if ($statusFilter) {
                        $sql .= " WHERE m.status = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $statusFilter);
                        $stmt->execute();
                        $result = $stmt->get_result();
                    } else {
                        $result = $conn->query($sql);
                    }
                }

                if (!$result) {
                    die("Invalid query: " . $conn->error);
                }

                $machineRows = [];
                while ($row = $result->fetch_assoc()) {
                    $machineRows[] = $row;
                }

                if (empty($machineRows)) {
                    $statusName = $statusFilter ?: 'Machines';
                    echo "<p style='display: flex; justify-content: center; margin-top: 1.5rem;'>No {$statusName} machine(s).</p>";
                } else {
                    echo "<div class='table-scroll' style='margin-top: 1.5rem;'>
                          <table style='width:100%;' class='table'>
                            <thead>
                                <tr>
                                    <th>Machine Name</th>
                                    <th>Status</th>
                                    <th>Unavailable From</th>
                                    <th>Unavailable Until</th>
                                    <th>Acquisition Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>";
                    
                    foreach ($machineRows as $row) {
                        $historySQL = "SELECT f.name, s.schedule_date, s.date_span 
                                      FROM schedules s
                                      INNER JOIN farmers f ON s.farmer_id = f.id
                                      WHERE s.machine_id = ? AND s.status = 'Completed'
                                      ORDER BY s.schedule_date DESC
                                      LIMIT 1";
                        $historyStmt = $conn->prepare($historySQL);
                        $historyStmt->bind_param("i", $row['id']);
                        $historyStmt->execute();
                        $historyResult = $historyStmt->get_result();
                        $historyData = $historyResult->fetch_assoc();
                        $historyStmt->close();

                        $tooltipContent = '';
                        if ($historyData) {
                            $usageDate = date('M d, Y', strtotime($historyData['schedule_date']));
                            $endDate = date('M d, Y', strtotime($historyData['schedule_date'] . ' +' . $historyData['date_span'] . ' days'));
                            $tooltipContent = "Last Used By: " . htmlspecialchars($historyData['name']) . "&#10;Date: {$usageDate} - {$endDate}";
                        } else {
                            $tooltipContent = "No usage history";
                        }

                        $safeName     = htmlspecialchars($row['name'],   ENT_QUOTES, 'UTF-8');
                        $safeStatus   = htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8');
                        $safeId       = intval($row['id']);
                        
                        // Format unavailable dates
                        $unavailableFrom = !empty($row['unavailable_from']) ? date('M d, Y g:i A', strtotime($row['unavailable_from'])) : '-';
                        $unavailableUntil = !empty($row['unavailable_until']) ? date('M d, Y g:i A', strtotime($row['unavailable_until'])) : '-';

                        echo "
                        <tr class='machine-row'
                            data-name='" . strtolower($safeName) . "'
                            data-status='{$safeStatus}'>
                            <td>{$safeName}</td>
                            <td>{$safeStatus}</td>
                            <td>{$unavailableFrom}</td>
                            <td>{$unavailableUntil}</td>
                            <td>" . htmlspecialchars($row['acquisition_date'], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>
                                <a class='btn btn-primary btn-sm' onclick='openEditMachineModal({$safeId})' href='javascript:void(0)'>Edit</a>
                                <a class='btn btn-success btn-sm' onclick='openHistoryModal({$safeId})'>History</a>
                                <a class='btn btn-danger btn-sm' 
                                   onclick=\"return confirm('Are you sure you want to delete {$safeName}?');\" 
                                   href='delete_machine.php?id={$safeId}&redirect=" . urlencode($statusFilter ?: 'Available') . "'>Delete</a>
                                <span class='reschedule-info-icon' data-tooltip='{$tooltipContent}'>⋯</span>
                            </td> 
                        </tr>
                        ";
                    }
                    
                    echo "</tbody>
                          <tr id='noResultsRow' style='display:none;'>
                              <td colspan='7' style='text-align:center; padding:2rem; color:var(--color-dark-variant);'>
                                  <span class='material-icons-sharp' style='font-size:2rem;display:block;margin-bottom:0.5rem;'>search_off</span>
                                  No machines found matching your search.
                              </td>
                          </tr>
                          </table></div>";
                }
            }
            ?>
        </main>
    </div>


    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Usage History: <span id="history-machine-name"></span></h2>
                <button class="close-modal" onclick="closeHistoryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="historyLoadingMessage" style="text-align: center; padding: 20px; display: none;">
                    <p>Loading history...</p>
                </div>
                <div id="historyErrorMessage" class="error-message" style="display: none;"></div>
                <div id="historyContent">
                </div>
            </div>
        </div>
    </div>
    <!-- Return Machine Modal -->
    <div id="returnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Return Machine</h2>
                <button class="close-modal" onclick="closeReturnModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="errorMessage" class="error-message"></div>

                <div class="machine-info">
                    <h3>Current Usage Information</h3>
                    <p><strong>Farmer:</strong> <span id="current-farmer"></span></p>
                    <p><strong>Machine:</strong> <span id="current-machine"></span></p>
                </div>

                <form id="returnForm" method="POST" action="process_return.php">
                    <input type="hidden" id="schedule_id" name="schedule_id">
                    <input type="hidden" id="machine_id" name="machine_id">

                    <div class="form-group">
                        <label for="machine_status">Machine Status <span style="color: red;">*</span></label>
                        <select id="machine_status" name="machine_status" required>
                            <option value="">--- Select machine condition ---</option>
                            <option value="Available">Good Condition (Available)</option>
                            <option value="Partially Damaged">Partially Damaged</option>
                            <option value="Totally Damaged">Totally Damaged</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="return_notes">Notes (Optional)</label>
                        <textarea id="return_notes" name="return_notes" 
                                  placeholder="Add any notes about the machine condition or issues found..."></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeReturnModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm Return</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Return Modal Functions
    function openReturnModal(scheduleId, farmerName, machineName, machineId) {
        const modal = document.getElementById('returnModal');
        
        document.getElementById('current-farmer').textContent = farmerName;
        document.getElementById('current-machine').textContent = machineName;
        document.getElementById('schedule_id').value = scheduleId;
        document.getElementById('machine_id').value = machineId;
        document.getElementById('machine_status').value = '';
        document.getElementById('return_notes').value = '';
        document.getElementById('errorMessage').style.display = 'none';
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeReturnModal() {
        const modal = document.getElementById('returnModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    // Close modals when clicking outside
    window.onclick = function(event) {
        const returnModal = document.getElementById('returnModal');
        const historyModal = document.getElementById('historyModal');
        
        if (event.target == returnModal) {
            closeReturnModal();
        }
        if (event.target == historyModal) {
            closeHistoryModal();
        }
    }

    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const returnModal = document.getElementById('returnModal');
            const historyModal = document.getElementById('historyModal');
            
            if (returnModal && returnModal.style.display === 'block') {
                closeReturnModal();
            }
            if (historyModal && historyModal.style.display === 'block') {
                closeHistoryModal();
            }
        }
    });

    // Handle form submission
    document.getElementById('returnForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to process this machine return?')) {
            return;
        }
        
        const formData = new FormData(this);
        
        fetch('process_return.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'machine.php?status=Not Returned&returned=1';
            } else {
                const errorDiv = document.getElementById('errorMessage');
                errorDiv.textContent = data.message || 'Failed to process return. Please try again.';
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = 'block';
            console.error('Error:', error);
        });
    });
    </script>

    <!-- TOOLTIP FOR MACHINE USAGE HISTORY -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.reschedule-info-icon').forEach(function(icon) {
            icon.addEventListener('mouseenter', function() {
                const rect = this.getBoundingClientRect();
                this.style.setProperty('--tip-x', (rect.left + rect.width / 2) + 'px');
                this.style.setProperty('--tip-y', (rect.top - 12) + 'px');
                this.classList.add('tip-visible');
            });
            icon.addEventListener('mouseleave', function() {
                this.classList.remove('tip-visible');
            });
        });
    });
    </script>

    <script>
    // MACHINE HISTORY MODAL FUNCTIONS
    function openHistoryModal(machineId) {
        const modal = document.getElementById('historyModal');
        const loadingMsg = document.getElementById('historyLoadingMessage');
        const errorMsg = document.getElementById('historyErrorMessage');
        const historyContent = document.getElementById('historyContent');
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        loadingMsg.style.display = 'block';
        errorMsg.style.display = 'none';
        historyContent.innerHTML = '';
        
        fetch('get_machine_history.php?id=' + machineId)
            .then(response => response.json())
            .then(data => {
                loadingMsg.style.display = 'none';
                
                if (data.success) {
                    document.getElementById('history-machine-name').textContent = data.machine_name;
                    
                    if (data.history && data.history.length > 0) {
                        let tableHTML = '<table class="table table-striped"><thead><tr><th>Farmer</th><th>Start Date</th><th>End Date</th><th>Total Days</th></tr></thead><tbody>';
                        
                        data.history.forEach(record => {
                            tableHTML += '<tr>' +
                                '<td>' + escapeHtml(record.farmer_name) + '</td>' +
                                '<td>' + record.start_date + '</td>' +
                                '<td>' + record.end_date + '</td>' +
                                '<td>' + record.total_days + '</td>' +
                                '</tr>';
                        });
                        
                        tableHTML += '</tbody></table>';
                        historyContent.innerHTML = tableHTML;
                    } else {
                        historyContent.innerHTML = '<p style="text-align: center; padding: 20px;">No usage history found for this machine.</p>';
                    }
                } else {
                    errorMsg.textContent = data.message || 'Failed to load history. Please try again.';
                    errorMsg.style.display = 'block';
                }
            })
            .catch(error => {
                loadingMsg.style.display = 'none';
                errorMsg.textContent = 'An error occurred while loading history. Please try again.';
                errorMsg.style.display = 'block';
                console.error('Error:', error);
            });
    }

    function closeHistoryModal() {
        const modal = document.getElementById('historyModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    </script>

    <!-- ADD MACHINE MODAL -->
    <div id="addMachineModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Machine</h2>
                <span class="close-modal" onclick="closeAddMachineModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div id="addMachineErrorMessage" class="alert-error" style="display: none;"></div>

                <form id="addMachineForm" method="POST">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentStatus) ?>">

                    <div class="form-group">
                        <label for="machine_name">Machine Name <span style="color: red;">*</span></label>
                        <input type="text" id="machine_name" name="name" placeholder="Enter machine name" required>
                    </div>

                    <div class="form-group">
                        <label for="acquisition_date">Acquisition Date <span style="color: red;">*</span></label>
                        <input type="date" id="acquisition_date" name="acquisition_date" max="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="unavailable_from">Unavailable From (Optional)</label>
                        <input type="datetime-local" id="unavailable_from" name="unavailable_from">
                    </div>

                    <div class="form-group">
                        <label for="unavailable_until">Unavailable Until (Optional)</label>
                        <input type="datetime-local" id="unavailable_until" name="unavailable_until">
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddMachineModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #4CAF50;">Add Machine</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT MACHINE MODAL -->
    <div id="editMachineModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Machine Information</h2>
                <span class="close-modal" onclick="closeEditMachineModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div id="editMachineErrorMessage" class="alert-error" style="display: none;"></div>

                <form id="editMachineForm" method="POST">
                    <input type="hidden" id="edit_machine_id" name="id">

                    <div class="form-group">
                        <label for="edit_machine_name">Machine Name <span style="color: red;">*</span></label>
                        <input type="text" id="edit_machine_name" name="name" placeholder="Enter machine name" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_machine_status">Status <span style="color: red;">*</span></label>
                        <select id="edit_machine_status" name="status" required>
                            <option value="">--- Select status ---</option>
                            <option value="Available">Available</option>
                            <option value="Partially Damaged">Partially Damaged</option>
                            <option value="Totally Damaged">Totally Damaged</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_acquisition_date">Acquisition Date <span style="color: red;">*</span></label>
                        <input type="date" id="edit_acquisition_date" name="acquisition_date" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_unavailable_from">Unavailable From (Optional)</label>
                        <input type="datetime-local" id="edit_unavailable_from" name="unavailable_from">
                    </div>

                    <div class="form-group">
                        <label for="edit_unavailable_until">Unavailable Until (Optional)</label>
                        <input type="datetime-local" id="edit_unavailable_until" name="unavailable_until">
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditMachineModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #4CAF50;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADD MACHINE MODAL SCRIPT -->
    <script>
    function openAddMachineModal() {
        const modal = document.getElementById('addMachineModal');
        
        document.getElementById('addMachineForm').reset();
        document.getElementById('addMachineErrorMessage').style.display = 'none';
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeAddMachineModal() {
        const modal = document.getElementById('addMachineModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const addModal = document.getElementById('addMachineModal');
            const editModal = document.getElementById('editMachineModal');
            if (addModal && addModal.style.display === 'block') {
                closeAddMachineModal();
            }
            if (editModal && editModal.style.display === 'block') {
                closeEditMachineModal();
            }
        }
    });

    document.getElementById('addMachineForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('process_add_machine.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'machine.php?status=' + encodeURIComponent(data.redirect) + '&added=1';
                } else {
                    const errorDiv = document.getElementById('addMachineErrorMessage');
                    errorDiv.textContent = data.message || 'Failed to add machine. Please try again.';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                const errorDiv = document.getElementById('addMachineErrorMessage');
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
                console.error('Error:', error);
            });
    });

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

    <!-- EDIT MACHINE MODAL SCRIPT -->
    <script>
    function openEditMachineModal(machineId) {
        const modal = document.getElementById('editMachineModal');
        const errorDiv = document.getElementById('editMachineErrorMessage');
        errorDiv.style.display = 'none';
        
        fetch(`machine.php?action=get_machine&id=${machineId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_machine_id').value = data.data.id;
                    document.getElementById('edit_machine_name').value = data.data.name;
                    document.getElementById('edit_machine_status').value = data.data.status;
                    document.getElementById('edit_acquisition_date').value = data.data.acquisition_date;
                    
                    // Set unavailable dates if they exist
                    document.getElementById('edit_unavailable_from').value = data.data.unavailable_from || '';
                    document.getElementById('edit_unavailable_until').value = data.data.unavailable_until || '';
                    
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('Error loading machine data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading machine data');
            });
    }

    function closeEditMachineModal() {
        const modal = document.getElementById('editMachineModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    document.getElementById('editMachineForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('process_edit_machine.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'machine.php?status=<?= urlencode($statusFilter ?: 'Available') ?>&updated=1';
                } else {
                    const errorDiv = document.getElementById('editMachineErrorMessage');
                    errorDiv.textContent = data.message || 'Failed to update machine. Please try again.';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                const errorDiv = document.getElementById('editMachineErrorMessage');
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
                console.error('Error:', error);
            });
    });
    </script>

    <!-- SEARCH & FILTER SCRIPT -->
    <script>
    (function () {
        const STORAGE_KEY_QUERY = 'machineSearch_query';
        const STORAGE_KEY_OPEN  = 'machineSearch_open';

        const searchInput  = document.getElementById('machineSearch');
        const clearBtn     = document.getElementById('clearSearch');
        const resultsCount = document.getElementById('resultsCount');
        const noResultsRow = document.getElementById('noResultsRow');
        const searchWrap   = document.getElementById('searchWrap');
        const toggleBtn    = document.getElementById('searchToggleBtn');

        if (!searchInput) return;

        const isNotReturned = <?= json_encode($statusFilter === 'Not Returned') ?>;

        const SEARCHABLE_COLS = isNotReturned ? [0, 1] : [1, 2];

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

        toggleBtn.addEventListener('click', function () {
            if (searchWrap.classList.contains('expanded')) {
                searchInput.value = '';
                localStorage.removeItem(STORAGE_KEY_QUERY);
                applyFilters();
                closeSearch();
            } else {
                openSearch();
            }
        });

        searchWrap.addEventListener('focusout', function (e) {
            if (!searchWrap.contains(e.relatedTarget)) {
                if (!searchInput.value) {
                    closeSearch();
                }
            }
        });

        /* ---- filtering ---- */
        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const rows  = document.querySelectorAll('.machine-row');

            // Persist query to localStorage
            if (query) {
                localStorage.setItem(STORAGE_KEY_QUERY, query);
            } else {
                localStorage.removeItem(STORAGE_KEY_QUERY);
            }

            clearBtn.style.display = query ? 'flex' : 'none';

            let visible = 0;

            rows.forEach(row => {
                const name   = row.dataset.name   || '';
                const farmer = row.dataset.farmer || '';
                const status = row.dataset.status ? row.dataset.status.toLowerCase() : '';

                const matchesSearch = !query ||
                    name.includes(query) ||
                    farmer.includes(query) ||
                    status.includes(query);

                if (matchesSearch) {
                    row.style.display = '';
                    visible++;

                    SEARCHABLE_COLS.forEach(colIdx => {
                        const cell = row.cells[colIdx];
                        if (!cell) return;
                        if (cell.dataset.original === undefined) {
                            cell.dataset.original = cell.textContent;
                        }
                        const original = cell.dataset.original;
                        if (query) {
                            const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
                            cell.innerHTML = original.replace(regex, '<mark class="search-highlight">$1</mark>');
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

            if (noResultsRow) noResultsRow.style.display = visible === 0 ? '' : 'none';

            const total = rows.length;
            resultsCount.textContent = query ? `${visible} of ${total} shown` : '';
        }

        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        searchInput.addEventListener('input', applyFilters);

        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            localStorage.removeItem(STORAGE_KEY_QUERY);
            applyFilters();
            searchInput.focus();
        });

        /* ---- Restore state on page load ---- */
        const savedQuery = localStorage.getItem(STORAGE_KEY_QUERY);
        const savedOpen  = localStorage.getItem(STORAGE_KEY_OPEN);

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