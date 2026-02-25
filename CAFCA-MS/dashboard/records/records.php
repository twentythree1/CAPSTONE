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

// Count machines by status using simple row counts
$countSql = "SELECT status FROM machines WHERE status != 'Not Returned'";
$countResult = $conn->query($countSql);
if ($countResult) {
    while ($r = $countResult->fetch_assoc()) {
        $status = $r['status'];
        if (isset($machineCounts[$status])) {
            $machineCounts[$status]++;
        }
    }
    $countResult->free();
}

// Count "Not Returned" — mirrors the display query exactly (LEFT JOIN, same conditions)
// so the badge always matches the rows shown in the Not Returned table.
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
    $r = $notReturnedResult->fetch_assoc();
    $notReturnedCount = (int)($r['count'] ?? 0);
    $notReturnedResult->free();
}
$machineCounts['Not Returned'] = $notReturnedCount;

// Available badge: simple count of Available machines
$availableBadgeSql = "SELECT COUNT(*) AS available_count FROM machines WHERE status = 'Available'";
$availBadgeResult = $conn->query($availableBadgeSql);
if ($availBadgeResult) {
    $availBadgeRow = $availBadgeResult->fetch_assoc();
    $machineCounts['Available'] = (int)($availBadgeRow['available_count'] ?? 0);
    $availBadgeResult->free();
}

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

// Handle AJAX request for fetching record data
if (isset($_GET['action']) && $_GET['action'] == 'get_record' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM records WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
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
                <a href="../records/records.php" class="active">
                    <span class="material-icons-sharp">topic</span>
                    <h3>Records</h3>
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
                <h2 class="machine-count">List of Records</h2>
                <div class="title-actions">
                    <span class="results-count-record" id="resultsCount"></span>
                    <div class="search-expand-wrap" id="searchWrap">
                        <div class="record-placeholder search-fields" id="searchFields">
                            <select id="filterEcosystem" title="Filter by ecosystem">
                                <option value="">All Ecosystems</option>
                                <option value="Irrigated">Irrigated</option>
                                <option value="Rainfed">Rainfed</option>
                            </select>
                            <div class="search-input-wrap">
                                <input type="text" id="recordSearch" placeholder="Search name, barangay, etc..." autocomplete="off">
                                <button class="clear-search" id="clearSearch" title="Clear" style="display:none;">
                                    <span class="material-icons-sharp">close</span>
                                </button>
                            </div>
                        </div>
                        <button class="search-icon-btn record-search" id="searchToggleBtn" title="Search Records" type="button">
                            <span class="material-icons-sharp">search</span>
                        </button>
                    </div>
                    <a href="javascript:void(0)" onclick="exportToExcel()" class="btn btn-primary records" role="button" title="Export table to Excel" style="background-color: #217346;">
                        <span class="material-icons-sharp" style="font-size:18px; vertical-align:middle; margin-right:4px;">download</span>Export Excel
                    </a>
                    <a href="javascript:void(0)" onclick="openAddRecordModal()" class="btn btn-primary records" role="button">Add Record</a>
                </div>
            </div>
            <div class='table-scroll' style='margin-top: 1.5rem;'>
                <table style='width:100%' class='table'>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Brgy.</th>
                            <th>RSBSA Reference No.</th>
                            <th>Name</th>
                            <th>Ecosystem</th>
                            <th>Variety Planted</th>
                            <th>Area Harvested</th>
                            <th>Gross Yield</th>
                            <th>Avg. Weight/Sack</th>
                            <th>Total Yield</th>
                            <th>Avg. Yield</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "
                        SELECT 
                            records.id AS id,
                            farmers.address AS brgy,
                            records.rsbsa_reference_no,
                            farmers.name AS farmer_name,
                            records.ecosystem,
                            records.variety_planted,
                            records.area_harvested,
                            records.gross_yield,
                            records.avg_weight_per_sack,
                            records.total_yield,
                            records.avg_yield
                        FROM records
                        JOIN farmers ON records.farmer_id = farmers.id
                        ORDER BY records.id DESC
                    ";
                    $result = $conn->query($sql);

                    if (!$result) {
                        die("Invalid query: " . $conn->error);
                    }

                    $counter = 1; // Initialize counter for No. column
                    while ($row = $result->fetch_assoc()) {
                        $safeName  = htmlspecialchars($row['farmer_name'], ENT_QUOTES, 'UTF-8');
                        $safeBrgy  = htmlspecialchars($row['brgy'], ENT_QUOTES, 'UTF-8');
                        $safeRsbsa = htmlspecialchars($row['rsbsa_reference_no'], ENT_QUOTES, 'UTF-8');
                        $safeEco   = htmlspecialchars($row['ecosystem'], ENT_QUOTES, 'UTF-8');
                        $safeId    = intval($row['id']);
                        echo "
                    <tr class='record-row'
                        data-name='" . strtolower($safeName) . "'
                        data-brgy='" . strtolower($safeBrgy) . "'
                        data-rsbsa='" . strtolower($safeRsbsa) . "'
                        data-ecosystem='$safeEco'>
                        <td>" . $counter . "</td>
                        <td>$safeBrgy</td>
                        <td>$safeRsbsa</td>
                        <td>$safeName</td>
                        <td>$safeEco</td>
                        <td>" . htmlspecialchars($row['variety_planted'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td>" . htmlspecialchars($row['area_harvested'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td>" . htmlspecialchars($row['gross_yield'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td>" . htmlspecialchars($row['avg_weight_per_sack'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td>" . htmlspecialchars($row['total_yield'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td>" . htmlspecialchars($row['avg_yield'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td>
                            <a class='btn btn-primary btn-sm' href='javascript:void(0)' onclick='openEditRecordModal($safeId)'>Edit</a>
                            <a class='btn btn-danger btn-sm' 
                                            onclick=\"return confirm('Are you sure you want to delete $safeName\\'s record?');\" 
                                            href='delete_records.php?id=$safeId'>Delete</a>
                        </td> 
                    </tr>
                    ";
                        $counter++; // auto increment counter
                    }

                    ?>

                        <tr id="noResultsRow" style="display:none;">
                            <td colspan="12" style="text-align:center; padding: 2rem; color: var(--color-dark-variant);">
                                <span class="material-icons-sharp" style="font-size:2rem; display:block; margin-bottom:0.5rem;">search_off</span>
                                No records found matching your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- ADD RECORD MODAL -->
    <div id="addRecordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Record</h2>
                <span class="close-modal" onclick="closeAddRecordModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div id="addRecordErrorMessage" class="alert-error" style="display: none;"></div>

                <form id="addRecordForm" method="POST">
                    <div class="form-group">
                        <label for="record_farmer_id">Farmer <span style="color: red;">*</span></label>
                        <select id="record_farmer_id" name="farmer_id" required>
                            <option value="">Select a Farmer</option>
                            <?php
                            $farmerList = $conn->query("SELECT id, name FROM farmers ORDER BY name");
                            while ($row = $farmerList->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>">
                                    <?= htmlspecialchars($row['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="record_rsbsa_reference_no">RSBSA Reference No. <span style="color: red;">*</span></label>
                        <input type="text" id="record_rsbsa_reference_no" name="rsbsa_reference_no" placeholder="e.g., 06-45-13-006-001234" required>
                    </div>

                    <div class="form-group">
                        <label for="record_ecosystem">Ecosystem <span style="color: red;">*</span></label>
                        <select id="record_ecosystem" name="ecosystem" required>
                            <option value="">--- Select Ecosystem ---</option>
                            <option value="Irrigated">Irrigated</option>
                            <option value="Rainfed">Rainfed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="record_variety_planted">Variety Planted <span style="color: red;">*</span></label>
                        <input type="number" id="record_variety_planted" name="variety_planted" step="1" min="1" placeholder="e.g., 216" required>
                    </div>

                    <div class="form-group">
                        <label for="record_area_harvested">Area Harvested (HA) <span style="color: red;">*</span></label>
                        <input type="number" id="record_area_harvested" name="area_harvested" step="0.01" min="0.01" placeholder="e.g., 2.5" required>
                    </div>

                    <div class="form-group">
                        <label for="record_gross_yield">Gross Yield (Total Number of Sacks) <span style="color: red;">*</span></label>
                        <input type="number" id="record_gross_yield" name="gross_yield" step="1" min="1" placeholder="e.g., 150" required>
                    </div>

                    <div class="form-group">
                        <label for="record_avg_weight_per_sack">Avg. Weight /Sack (KG) <span style="color: red;">*</span></label>
                        <input type="number" id="record_avg_weight_per_sack" name="avg_weight_per_sack" step="1" min="1" placeholder="e.g., 50" required>
                    </div>

                    <div class="form-group">
                        <label for="record_total_yield">Total Yield (KG) <span style="color: red;">*</span></label>
                        <input type="number" id="record_total_yield" name="total_yield" step="1" min="1" placeholder="e.g., 7500" required>
                    </div>

                    <div class="form-group">
                        <label for="record_avg_yield">Avg. Yield (MT/HA) <span style="color: red;">*</span></label>
                        <input type="number" id="record_avg_yield" name="avg_yield" step="0.01" min="0.01" placeholder="e.g., 2.84" required>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddRecordModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #4CAF50">Add Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT RECORD MODAL -->
    <div id="editRecordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Record</h2>
                <span class="close-modal" onclick="closeEditRecordModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div id="editRecordErrorMessage" class="alert-error" style="display: none;"></div>

                <form id="editRecordForm" method="POST">
                    <input type="hidden" id="edit_record_id" name="id">
                    
                    <div class="form-group">
                        <label for="edit_record_farmer_id">Farmer <span style="color: red;">*</span></label>
                        <select id="edit_record_farmer_id" name="farmer_id" required>
                            <option value="">Select a Farmer</option>
                            <?php
                            $farmerList2 = $conn->query("SELECT id, name FROM farmers ORDER BY name");
                            while ($row = $farmerList2->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>">
                                    <?= htmlspecialchars($row['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_record_rsbsa_reference_no">RSBSA Reference No. <span style="color: red;">*</span></label>
                        <input type="text" id="edit_record_rsbsa_reference_no" name="rsbsa_reference_no" placeholder="e.g., 06-45-13-006-001234" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_record_ecosystem">Ecosystem <span style="color: red;">*</span></label>
                        <select id="edit_record_ecosystem" name="ecosystem" required>
                            <option value="">--- Select Ecosystem ---</option>
                            <option value="Irrigated">Irrigated</option>
                            <option value="Rainfed">Rainfed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_record_variety_planted">Variety Planted <span style="color: red;">*</span></label>
                        <input type="number" id="edit_record_variety_planted" name="variety_planted" step="1" min="1" placeholder="e.g., 216" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_record_area_harvested">Area Harvested (HA) <span style="color: red;">*</span></label>
                        <input type="number" id="edit_record_area_harvested" name="area_harvested" step="0.01" min="0.01" placeholder="e.g., 2.5" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_record_gross_yield">Gross Yield (Total Number of Sacks) <span style="color: red;">*</span></label>
                        <input type="number" id="edit_record_gross_yield" name="gross_yield" step="1" min="1" placeholder="e.g., 150" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_record_avg_weight_per_sack">Avg. Weight /Sack (KG) <span style="color: red;">*</span></label>
                        <input type="number" id="edit_record_avg_weight_per_sack" name="avg_weight_per_sack" step="1" min="1" placeholder="e.g., 50" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_record_total_yield">Total Yield (KG) <span style="color: red;">*</span></label>
                        <input type="number" id="edit_record_total_yield" name="total_yield" step="1" min="1" placeholder="e.g., 7500" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_record_avg_yield">Avg. Yield (MT/HA) <span style="color: red;">*</span></label>
                        <input type="number" id="edit_record_avg_yield" name="avg_yield" step="0.01" min="0.01" placeholder="e.g., 2.84" required>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditRecordModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RECORD MODAL SCRIPTS -->
    <script>
    // ADD RECORD MODAL
    function openAddRecordModal() {
        const modal = document.getElementById('addRecordModal');
        document.getElementById('addRecordForm').reset();
        document.getElementById('addRecordErrorMessage').style.display = 'none';
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeAddRecordModal() {
        const modal = document.getElementById('addRecordModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    document.getElementById('addRecordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('process_add_records.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeAddRecordModal();
                location.reload();
            } else {
                const errorDiv = document.getElementById('addRecordErrorMessage');
                errorDiv.textContent = data.message;
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            const errorDiv = document.getElementById('addRecordErrorMessage');
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = 'block';
        });
    });

    // EDIT RECORD MODAL
    function openEditRecordModal(recordId) {
        const modal = document.getElementById('editRecordModal');
        document.getElementById('editRecordErrorMessage').style.display = 'none';

        fetch('records.php?action=get_record&id=' + recordId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_record_id').value = data.data.id;
                    document.getElementById('edit_record_farmer_id').value = data.data.farmer_id;
                    document.getElementById('edit_record_rsbsa_reference_no').value = data.data.rsbsa_reference_no || '';
                    document.getElementById('edit_record_ecosystem').value = data.data.ecosystem;
                    document.getElementById('edit_record_variety_planted').value = data.data.variety_planted;
                    document.getElementById('edit_record_area_harvested').value = data.data.area_harvested;
                    document.getElementById('edit_record_gross_yield').value = data.data.gross_yield;
                    document.getElementById('edit_record_avg_weight_per_sack').value = data.data.avg_weight_per_sack;
                    document.getElementById('edit_record_total_yield').value = data.data.total_yield;
                    document.getElementById('edit_record_avg_yield').value = data.data.avg_yield;

                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('Error loading record data');
                }
            })
            .catch(error => {
                alert('An error occurred while loading the record');
            });
    }

    function closeEditRecordModal() {
        const modal = document.getElementById('editRecordModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    document.getElementById('editRecordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('process_edit_records.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeEditRecordModal();
                location.reload();
            } else {
                const errorDiv = document.getElementById('editRecordErrorMessage');
                errorDiv.textContent = data.message;
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            const errorDiv = document.getElementById('editRecordErrorMessage');
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = 'block';
        });
    });
    </script>

    <!-- SEARCH & FILTER SCRIPT -->
    <script>
    (function () {
        const searchInput  = document.getElementById('recordSearch');
        const filterEco    = document.getElementById('filterEcosystem');
        const clearBtn     = document.getElementById('clearSearch');
        const resultsCount = document.getElementById('resultsCount');
        const noResultsRow = document.getElementById('noResultsRow');
        const searchWrap   = document.getElementById('searchWrap');
        const toggleBtn    = document.getElementById('searchToggleBtn');

        // Columns to highlight: Brgy (1), RSBSA (2), Name (3)
        const SEARCHABLE_COLS = [1, 2, 3];

        /* ---- expand / collapse ---- */
        function openSearch() {
            searchWrap.classList.add('expanded');
            setTimeout(() => searchInput.focus(), 250);
        }

        function closeSearch() {
            if (!searchInput.value && !filterEco.value) {
                searchWrap.classList.remove('expanded');
            }
        }

        toggleBtn.addEventListener('click', function () {
            if (searchWrap.classList.contains('expanded')) {
                searchInput.value = '';
                filterEco.value   = '';
                applyFilters();
                searchWrap.classList.remove('expanded');
            } else {
                openSearch();
            }
        });

        searchWrap.addEventListener('focusout', function (e) {
            if (!searchWrap.contains(e.relatedTarget)) closeSearch();
        });

        function applyFilters() {
            const query     = searchInput.value.trim().toLowerCase();
            const ecoFilter = filterEco.value;
            const rows      = document.querySelectorAll('.record-row');

            clearBtn.style.display = query ? 'flex' : 'none';

            let visible = 0;

            rows.forEach(row => {
                const name      = row.dataset.name  || '';
                const brgy      = row.dataset.brgy  || '';
                const rsbsa     = row.dataset.rsbsa || '';
                const ecosystem = row.dataset.ecosystem || '';

                const matchesSearch = !query ||
                    name.includes(query)  ||
                    brgy.includes(query)  ||
                    rsbsa.includes(query);

                const matchesEco = !ecoFilter || ecosystem === ecoFilter;

                if (matchesSearch && matchesEco) {
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

            noResultsRow.style.display = visible === 0 ? '' : 'none';

            const total = rows.length;
            if (query || ecoFilter) {
                resultsCount.textContent = `${visible} of ${total} shown`;
            } else {
                resultsCount.textContent = '';
            }
        }

        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        searchInput.addEventListener('input', applyFilters);
        filterEco.addEventListener('change', applyFilters);

        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            applyFilters();
            searchInput.focus();
        });
    })();
    </script>

    <script src="../main/dashscript.js"></script>

    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
    function exportToExcel() {
        const table = document.querySelector('.table');
        const headers = [];
        const headerCells = table.querySelectorAll('thead tr th');
        headerCells.forEach((th, i) => {
            if (th.textContent.trim() !== 'Action') {
                headers.push({ index: i, label: th.textContent.trim() });
            }
        });

        // Collect visible rows data
        const rows = table.querySelectorAll('tbody tr.record-row');
        const data = [];

        // Add header row
        data.push(headers.map(h => h.label));

        rows.forEach(row => {
            if (row.style.display === 'none') return;
            const rowData = [];
            headers.forEach(h => {
                const cell = row.cells[h.index];
                const text = (cell && cell.dataset.original !== undefined)
                    ? cell.dataset.original
                    : (cell ? cell.textContent.trim() : '');
                rowData.push(text);
            });
            data.push(rowData);
        });

        const ws = XLSX.utils.aoa_to_sheet(data);

        const colWidths = data[0].map((_, colIdx) => {
            const maxLen = data.reduce((max, row) => {
                const val = row[colIdx] ? String(row[colIdx]).length : 0;
                return Math.max(max, val);
            }, 10);
            return { wch: Math.min(maxLen + 2, 40) };
        });
        ws['!cols'] = colWidths;

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Records');

        const today = new Date();
        const dateStr = today.toISOString().slice(0, 10);
        XLSX.writeFile(wb, `Records_${dateStr}.xlsx`);
    }
    </script>
</body>

</html>
<?php
$conn->close();
?>