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

// Count machines by status
$machineCounts = [
    'Available' => 0,
    'Partially Damaged' => 0,
    'Totally Damaged' => 0,
    'Not Returned' => 0
];

$countSql = "SELECT status FROM machines";
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

$now = new DateTime();
$notReturnedSql = "SELECT DISTINCT s.machine_id, s.schedule_date, s.date_span, s.start_time, s.end_time, s.return_date, s.status
                   FROM schedules s
                   WHERE s.status IN ('Approved', 'Completed')";
$notReturnedResult = $conn->query($notReturnedSql);
$notReturnedCount = 0;

if ($notReturnedResult) {
    while ($r = $notReturnedResult->fetch_assoc()) {
        $scheduleDate = $r['schedule_date'] ?? '';
        $startTime = $r['start_time'] ?? '00:00:00';
        $endTime = $r['end_time'] ?? '23:59:59';
        $dateSpan = $r['date_span'] ?? 0;
        $returnDate = $r['return_date'];
        $scheduleStatus = $r['status'];

        if (!empty($scheduleDate)) {
            try {
                $startDt = new DateTime($scheduleDate . ' ' . $startTime);
                $endDateStr = date('Y-m-d', strtotime($scheduleDate . " +{$dateSpan} days"));
                $endDt = new DateTime($endDateStr . ' ' . $endTime);

                if ($now > $endDt && empty($returnDate)) {
                    $notReturnedCount++;
                }
            } catch (Exception $e) {
            }
        }
    }
    $notReturnedResult->free();
}

// Update the Not Returned count
$machineCounts['Not Returned'] = $notReturnedCount;

// Count schedules by status
$counts = [
    'Pending' => 0,
    'Approved' => 0,
    'On going' => 0,
    'Completed' => 0
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
        if ($dbStatus === 'Approved') {
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
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=Not Returned">
                            <span>Not Returned</span>
                            <span
                                class="count-badge"><?= htmlspecialchars($machineCounts['Not Returned'] ?? 0) ?></span>
                        </a>
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
                <a href="javascript:void(0)" onclick="openAddRecordModal()" class="btn btn-primary records" role="button">Add Record</a>
            </div>
            <br>
            <div class='table-scroll'>
                <table style='width:100%' class='table'>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Brgy</th>
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
                        echo "
                    <tr>
                        <td>" . $counter . "</td>
                        <td>" . htmlspecialchars($row['brgy']) . "</td>
                        <td>" . htmlspecialchars($row['rsbsa_reference_no']) . "</td>
                        <td>" . htmlspecialchars($row['farmer_name']) . "</td>
                        <td>" . htmlspecialchars($row['ecosystem']) . "</td>
                        <td>" . htmlspecialchars($row['variety_planted']) . "</td>
                        <td>" . htmlspecialchars($row['area_harvested']) . "</td>
                        <td>" . htmlspecialchars($row['gross_yield']) . "</td>
                        <td>" . htmlspecialchars($row['avg_weight_per_sack']) . "</td>
                        <td>" . htmlspecialchars($row['total_yield']) . "</td>
                        <td>" . htmlspecialchars($row['avg_yield']) . "</td>
                        <td>
                            <a class='btn btn-primary btn-sm' href='javascript:void(0)' onclick='openEditRecordModal(" . htmlspecialchars($row['id']) . ")'>Edit</a>
                            <a class='btn btn-danger btn-sm' 
                                            onclick=\"return confirm('Are you sure you want to delete " . htmlspecialchars($row['farmer_name']) . "\\'s record?');\" 
                                            href='delete_records.php?id=" . htmlspecialchars($row['id']) . "'>Delete</a>
                        </td> 
                    </tr>
                    ";
                        $counter++; // Increment counter
                    }

                    ?>

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

    <script src="../main/dashscript.js"></script>
</body>

</html>
<?php
$conn->close();
?>