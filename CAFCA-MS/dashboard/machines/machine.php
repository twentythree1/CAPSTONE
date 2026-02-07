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

// Count machines "Not Returned" based on completed schedules without return date
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

                // Check if schedule is completed (past end date) and not returned
                if ($now > $endDt && empty($returnDate)) {
                    $notReturnedCount++;
                }
            } catch (Exception $e) {
                // Skip invalid dates
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
                            <span class="count-badge"><?= htmlspecialchars($machineCounts['Not Returned'] ?? 0) ?></span>
                        </a>
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
                    </div>
                </div>
                <a href="../records/records.php">
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
                <h2>List of Machines</h2>
                <?php if ($statusFilter !== 'Not Returned'): ?>
                <a href="add_machine.php?redirect=<?= urlencode($statusFilter ?: 'Available') ?>" class="btn btn-primary machine" role="button">Add Machine</a>
                <?php endif; ?>
            </div>
            <br>
            <div class='table-scroll'>
                <table style='width:100%' class='table'>
                    <thead>
                        <tr>
                            <?php if ($statusFilter === 'Not Returned'): ?>
                            <th>Farmer's Name</th>
                            <th>Machine Name</th>
                            <th>Schedule Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                            <th>Action</th>
                            <?php else: ?>
                            <th>Machine ID</th>
                            <th>Machine Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Acquisition Date</th>
                            <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($statusFilter === 'Not Returned') {
                        $sql = "SELECT s.id as schedule_id, s.schedule_date, s.date_span, s.start_time, s.end_time,
                                       m.id as machine_id, m.name as machine_name, 
                                       f.name as farmer_name
                                FROM schedules s
                                INNER JOIN machines m ON s.machine_id = m.id
                                INNER JOIN farmers f ON s.farmer_id = f.id
                                WHERE s.status IN ('Approved', 'Completed')
                                AND s.return_date IS NULL";
                        
                        $result = $conn->query($sql);
                        
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $scheduleDate = $row['schedule_date'];
                                $startTime = $row['start_time'] ?: '00:00:00';
                                $endTime = $row['end_time'] ?: '23:59:59';
                                $dateSpan = (int)$row['date_span'];
                                
                                try {
                                    $startDt = new DateTime($scheduleDate . ' ' . $startTime);
                                    $endDateStr = date('Y-m-d', strtotime($scheduleDate . " +{$dateSpan} days"));
                                    $endDt = new DateTime($endDateStr . ' ' . $endTime);
                                    
                                    if ($now > $endDt) {
                                        $formattedStartDate = date('M d, Y', strtotime($scheduleDate));
                                        $formattedEndDate = date('M d, Y', strtotime($endDateStr));
                                        echo "
                                        <tr>
                                            <td>" . htmlspecialchars($row['farmer_name']) . "</td>
                                            <td>" . htmlspecialchars($row['machine_name']) . "</td>
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
                                } catch (Exception $e) {
                                }
                            }
                        }
                    } else {
                        // Original machine listing code with usage history tooltip
                        if ($statusFilter) {
                            $sql = "SELECT * FROM machines WHERE status = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("s", $statusFilter);
                            $stmt->execute();
                            $result = $stmt->get_result();
                        } else {
                            $sql = "SELECT * FROM machines";
                            $result = $conn->query($sql);
                        }

                        if (!$result) {
                            die("Invalid query: " . $conn->error);
                        }

                        while ($row = $result->fetch_assoc()) {
                            $historySQL = "SELECT f.name, s.schedule_date, s.date_span 
                                          FROM schedules s
                                          INNER JOIN farmers f ON s.farmer_id = f.id
                                          WHERE s.machine_id = ? AND s.status IN ('Approved', 'Completed')
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

                            echo "
                            <tr>
                                <td>$row[id]</td>
                                <td>$row[name]</td>
                                <td>$row[type]</td>
                                <td>$row[status]</td>
                                <td>$row[acquisition_date]</td>
                                <td>
                                    <a class='btn btn-primary btn-sm' href='edit_machine.php?id=$row[id]&redirect=" . urlencode($statusFilter ?: 'Available') . "'>Edit</a>
                                    <a class='btn btn-success btn-sm' href='history.php?id=$row[id]&redirect=" . urlencode($statusFilter ?: 'Available') . "'>History</a>
                                    <a class='btn btn-danger btn-sm' 
                                       onclick=\"return confirm('Are you sure you want to delete $row[name]?');\" 
                                       href='delete_machine.php?id=$row[id]&redirect=" . urlencode($statusFilter ?: 'Available') . "'>Delete</a>
                                    <span class='reschedule-info-icon' data-tooltip='{$tooltipContent}'>⋯</span>
                                </td> 
                            </tr>
                            ";
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </main>
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

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('returnModal');
        if (event.target == modal) {
            closeReturnModal();
        }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeReturnModal();
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

    <script src="../main/dashscript.js"></script>
</body>

</html>