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

// Count total farmers
$farmerCountSql = "SELECT COUNT(*) as count FROM farmers";
$farmerCountResult = $conn->query($farmerCountSql);
$farmer_count = 0;
if ($farmerCountResult) {
    $row = $farmerCountResult->fetch_assoc();
    $farmer_count = $row['count'];
    $farmerCountResult->free();
}

// Handle AJAX request for fetching farmer data
if (isset($_GET['action']) && $_GET['action'] == 'get_farmer' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM farmers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Farmer not found']);
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
    <link rel="stylesheet" href="farmerstyle.css">
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
                <a href="../farmers_sec/farmers.php" class="active">
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

                <a href="../settings/settings.php">
                    <span class="material-icons-sharp">settings</span>
                    <h3>Settings</h3>
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
                <?php if (isset($_GET['added'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: relative;">
                    New farmer was added successfully!
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
                <h2 class="farmers-count">List of Farmers
                    <span><?= htmlspecialchars($farmer_count, ENT_QUOTES, 'UTF-8'); ?></span>
                </h2>
                <div class="title-actions">
                    <span class="results-count" id="resultsCount"></span>
                    <div class="search-expand-wrap" id="searchWrap">
                        <div class="search-fields" id="searchFields">
                            <select id="filterUnit" title="Filter by land unit">
                                <option value="">All Units</option>
                                <option value="cm²">cm²</option>
                                <option value="m²">m²</option>
                                <option value="km²">km²</option>
                                <option value="hectare(s)">hectare(s)</option>
                                <option value="acre(s)">acre(s)</option>
                            </select>
                            <div class="search-input-wrap">
                                <input type="text" id="farmerSearch" placeholder="Search name, address, contact..."
                                    autocomplete="off">
                                <button class="clear-search" id="clearSearch" title="Clear" style="display:none;">
                                    <span class="material-icons-sharp">close</span>
                                </button>
                            </div>
                        </div>
                        <button class="search-icon-btn" id="searchToggleBtn" title="Search Farmers" type="button">
                            <span class="material-icons-sharp">search</span>
                        </button>
                    </div>
                    <a href="javascript:void(0)" onclick="exportToExcel()" class="btn btn-primary farmer-btn"
                        role="button" title="Export table to Excel" style="background-color: #217346;">
                        <span class="material-icons-sharp"
                            style="font-size:18px; vertical-align:middle; margin-right:4px;">download</span>Export Excel
                    </a>
                    <a href="javascript:void(0)" onclick="openAddFarmerModal()" class="btn btn-primary farmer-btn"
                        role="button">Add Farmer</a>
                </div>
            </div>

            <div class='table-scroll'>
                <table style='width:100%' class='table'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Birthday</th>
                            <th>Age</th>
                            <th>Address</th>
                            <th>Land Area</th>
                            <th>Unit of Measurement</th>
                            <th>Contact Number</th>
                            <th>Date Added</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                    $sql = "SELECT * FROM farmers";
                    $result = $conn->query($sql);

                    if (!$result) {
                        die("Invalid query: " . $conn->error);
                    }

                    while ($row = $result->fetch_assoc()) {
                        $birthday = new DateTime($row['birthday']);
                        $today = new DateTime();
                        $age = $birthday->diff($today)->y;
                        $safeName    = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                        $safeAddress = htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8');
                        $safePhone   = htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8');
                        $safeUnit    = htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8');
                        $safeId      = intval($row['id']);
                        
                        echo "
                    <tr class='farmer-row'
                        data-name='" . strtolower($safeName) . "'
                        data-address='" . strtolower($safeAddress) . "'
                        data-phone='" . strtolower($safePhone) . "'
                        data-unit='$safeUnit'>
                        <td>$safeId</td>
                        <td>$safeName</td>
                        <td>" . htmlspecialchars($row['birthday'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td>$age</td>
                        <td>$safeAddress</td>
                        <td>" . htmlspecialchars($row['land'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td>$safeUnit</td>
                        <td>$safePhone</td>
                        <td>" . htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td>
                            <a class='btn btn-primary btn-sm' onclick='openEditFarmerModal($safeId)'>Edit</a>
                            <a class='btn btn-danger btn-sm' 
                                            onclick=\"return confirm('Are you sure you want to delete $safeName?');\" 
                                            href='delete.php?id=$safeId'>Delete</a>
                        </td> 
                    </tr>
                    ";
                    }

                    ?>

                        <tr id="noResultsRow" style="display:none;">
                            <td colspan="10"
                                style="text-align:center; padding: 2rem; color: var(--color-dark-variant);">
                                <span class="material-icons-sharp"
                                    style="font-size:2rem; display:block; margin-bottom:0.5rem;">search_off</span>
                                No farmers found matching your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- ADD FARMER MODAL -->
    <div id="addFarmerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Farmer</h2>
                <span class="close-modal" onclick="closeAddFarmerModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div id="addFarmerErrorMessage" class="alert-error" style="display: none;"></div>

                <form id="addFarmerForm" method="POST">
                    <div class="form-group">
                        <label for="farmer_name">Name <span style="color: red;">*</span></label>
                        <input type="text" id="farmer_name" name="name" placeholder="Enter farmer's full name" required>
                    </div>

                    <div class="form-group">
                        <label for="farmer_birthday">Birthday <span style="color: red;">*</span></label>
                        <input type="date" id="farmer_birthday" name="birthday"
                            max="<?= date('Y-m-d', strtotime('-15 years')) ?>" required>
                        <small style="color: var(--color-dark-variant); display: block; margin-top: 0.25rem;">Farmer
                            must be at least 15 years old</small>
                    </div>

                    <div class="form-group">
                        <label for="farmer_address">Address <span style="color: red;">*</span></label>
                        <input type="text" id="farmer_address" name="address" placeholder="Enter complete address"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="farmer_land">Land Area <span style="color: red;">*</span></label>
                        <input type="number" id="farmer_land" name="land" step="0.25" min="0"
                            placeholder="Enter land area" required>
                    </div>

                    <div class="form-group">
                        <label for="farmer_unit">Unit of Measurement of Land Area <span
                                style="color: red;">*</span></label>
                        <select id="farmer_unit" name="unit" required>
                            <option value="">--- Select unit ---</option>
                            <option value="cm²">cm²</option>
                            <option value="m²">m²</option>
                            <option value="km²">km²</option>
                            <option value="hectare(s)">hectare(s)</option>
                            <option value="acre(s)">acre(s)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="farmer_phone">Contact Number <span style="color: red;">*</span></label>
                        <input type="text" id="farmer_phone" name="phone" placeholder="Enter contact number" required>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddFarmerModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #4CAF50">Add
                            Farmer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT FARMER MODAL -->
    <div id="editFarmerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Farmer Information</h2>
                <span class="close-modal" onclick="closeEditFarmerModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div id="editFarmerErrorMessage" class="alert-error" style="display: none;"></div>

                <form id="editFarmerForm" method="POST">
                    <input type="hidden" id="edit_farmer_id" name="id">

                    <div class="form-group">
                        <label for="edit_farmer_name">Name <span style="color: red;">*</span></label>
                        <input type="text" id="edit_farmer_name" name="name" placeholder="Enter farmer's full name"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="edit_farmer_birthday">Birthday <span style="color: red;">*</span></label>
                        <input type="date" id="edit_farmer_birthday" name="birthday"
                            max="<?= date('Y-m-d', strtotime('-15 years')) ?>" required>
                        <small style="color: var(--color-dark-variant); display: block; margin-top: 0.25rem;">Farmer
                            must be at least 15 years old</small>
                    </div>

                    <div class="form-group">
                        <label for="edit_farmer_address">Address <span style="color: red;">*</span></label>
                        <input type="text" id="edit_farmer_address" name="address" placeholder="Enter complete address"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="edit_farmer_land">Land Area <span style="color: red;">*</span></label>
                        <input type="number" id="edit_farmer_land" name="land" step="0.25" min="0"
                            placeholder="Enter land area" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_farmer_unit">Unit of Measurement of Land Area <span
                                style="color: red;">*</span></label>
                        <select id="edit_farmer_unit" name="unit" required>
                            <option value="">--- Select unit ---</option>
                            <option value="cm²">cm²</option>
                            <option value="m²">m²</option>
                            <option value="km²">km²</option>
                            <option value="hectare(s)">hectare(s)</option>
                            <option value="acre(s)">acre(s)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_farmer_phone">Contact Number <span style="color: red;">*</span></label>
                        <input type="text" id="edit_farmer_phone" name="phone" placeholder="Enter contact number"
                            maxlength="11" required>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditFarmerModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADD FARMER MODAL SCRIPT -->
    <script>
    function openAddFarmerModal() {
        const modal = document.getElementById('addFarmerModal');

        document.getElementById('addFarmerForm').reset();
        document.getElementById('addFarmerErrorMessage').style.display = 'none';

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeAddFarmerModal() {
        const modal = document.getElementById('addFarmerModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }


    // Handle form submission with AJAX
    document.getElementById('addFarmerForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('process_add_farmer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'farmers.php?added=1';
                } else {
                    const errorDiv = document.getElementById('addFarmerErrorMessage');
                    errorDiv.textContent = data.message || 'Failed to add farmer. Please try again.';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                const errorDiv = document.getElementById('addFarmerErrorMessage');
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

    // EDIT FARMER MODAL FUNCTIONS
    function openEditFarmerModal(farmerId) {
        const modal = document.getElementById('editFarmerModal');
        const errorDiv = document.getElementById('editFarmerErrorMessage');
        errorDiv.style.display = 'none';

        // Fetch farmer data
        fetch(`farmers.php?action=get_farmer&id=${farmerId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate form fields
                    document.getElementById('edit_farmer_id').value = data.data.id;
                    document.getElementById('edit_farmer_name').value = data.data.name;
                    document.getElementById('edit_farmer_birthday').value = data.data.birthday;
                    document.getElementById('edit_farmer_address').value = data.data.address;
                    document.getElementById('edit_farmer_land').value = data.data.land;
                    document.getElementById('edit_farmer_unit').value = data.data.unit;
                    document.getElementById('edit_farmer_phone').value = data.data.phone;

                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('Error loading farmer data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading farmer data');
            });
    }

    function closeEditFarmerModal() {
        const modal = document.getElementById('editFarmerModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Update ESC key handler to include edit modal
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const addModal = document.getElementById('addFarmerModal');
            const editModal = document.getElementById('editFarmerModal');
            if (addModal && addModal.style.display === 'block') {
                closeAddFarmerModal();
            }
            if (editModal && editModal.style.display === 'block') {
                closeEditFarmerModal();
            }
        }
    });

    // Handle EDIT form submission with AJAX
    document.getElementById('editFarmerForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('process_edit_farmer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'farmers.php?updated=1';
                } else {
                    const errorDiv = document.getElementById('editFarmerErrorMessage');
                    errorDiv.textContent = data.message || 'Failed to update farmer. Please try again.';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                const errorDiv = document.getElementById('editFarmerErrorMessage');
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
                console.error('Error:', error);
            });
    });
    </script>


    <!-- SEARCH & FILTER SCRIPT -->
    <script>
    (function() {
        const searchInput = document.getElementById('farmerSearch');
        const filterUnit = document.getElementById('filterUnit');
        const clearBtn = document.getElementById('clearSearch');
        const resultsCount = document.getElementById('resultsCount');
        const noResultsRow = document.getElementById('noResultsRow');
        const searchWrap = document.getElementById('searchWrap');
        const toggleBtn = document.getElementById('searchToggleBtn');

        // Columns to search: name (1), address (4), phone (7)
        const SEARCHABLE_COLS = [1, 4, 7];

        /* ---- expand / collapse ---- */
        function openSearch() {
            searchWrap.classList.add('expanded');
            setTimeout(() => searchInput.focus(), 250);
        }

        function closeSearch() {
            // Only collapse if nothing is typed and no unit filter active
            if (!searchInput.value && !filterUnit.value) {
                searchWrap.classList.remove('expanded');
            }
        }

        toggleBtn.addEventListener('click', function() {
            if (searchWrap.classList.contains('expanded')) {
                searchInput.value = '';
                filterUnit.value = '';
                applyFilters();
                searchWrap.classList.remove('expanded');
            } else {
                openSearch();
            }
        });

        // Keep open while hovering the whole wrap
        searchWrap.addEventListener('focusout', function(e) {
            if (!searchWrap.contains(e.relatedTarget)) closeSearch();
        });

        // filtering function
        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const unitFilter = filterUnit.value;
            const rows = document.querySelectorAll('.farmer-row');

            clearBtn.style.display = query ? 'flex' : 'none';

            let visible = 0;

            rows.forEach(row => {
                const name = row.dataset.name || '';
                const address = row.dataset.address || '';
                const phone = row.dataset.phone || '';
                const unit = row.dataset.unit || '';

                const matchesSearch = !query ||
                    name.includes(query) ||
                    address.includes(query) ||
                    phone.includes(query);

                const matchesUnit = !unitFilter || unit === unitFilter;

                if (matchesSearch && matchesUnit) {
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

            noResultsRow.style.display = visible === 0 ? '' : 'none';

            const total = rows.length;
            if (query || unitFilter) {
                resultsCount.textContent = `${visible} of ${total} shown`;
            } else {
                resultsCount.textContent = '';
            }
        }

        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        searchInput.addEventListener('input', applyFilters);
        filterUnit.addEventListener('change', applyFilters);

        clearBtn.addEventListener('click', function() {
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
                headers.push({
                    index: i,
                    label: th.textContent.trim()
                });
            }
        });

        const rows = table.querySelectorAll('tbody tr.farmer-row');
        const data = [];

        data.push(headers.map(h => h.label));

        rows.forEach(row => {
            if (row.style.display === 'none') return;
            const rowData = [];
            headers.forEach(h => {
                const cell = row.cells[h.index];
                const text = (cell && cell.dataset.original !== undefined) ?
                    cell.dataset.original :
                    (cell ? cell.textContent.trim() : '');
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
            return {
                wch: Math.min(maxLen + 2, 40)
            };
        });
        ws['!cols'] = colWidths;

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Farmers');

        const today = new Date();
        const dateStr = today.toISOString().slice(0, 10);
        XLSX.writeFile(wb, `Farmers_${dateStr}.xlsx`);
    }
    </script>
</body>

</html>