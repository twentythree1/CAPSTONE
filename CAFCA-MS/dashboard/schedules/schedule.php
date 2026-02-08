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
                <?php
                    $currentStatus = isset($_GET['status']) ? $_GET['status'] : 'Pending'; ?>
                <a href="javascript:void(0)" onclick="openAddScheduleModal()" class="btn btn-primary schedule"
                    role="button">Add Schedule</a>
            </div>
            <br>


            <?php
            $statusFilter = $_GET['status'] ?? null;

            $schedules = [
                'Pending' => [],
                'Approved' => [],
                'On going' => [],
                'Completed' => []
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
                if ($dbStatus === 'Approved') {
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
                echo "<h3 style='margin-top: -1rem; margin-bottom: 1.2rem; padding-left: 1rem;'>$title</h3>";
                            
                if (empty($data)) {
                    echo "<p style='display: flex; justify-content: center; text-transform: capitalize;'>No {$title} available.</p>";
                    return;
                }
                            
                echo "
                <div class='table-scroll'>
                <table style='width:100%' class='table'>
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
                    $details_redirect = 'print_certificate.php?id=' . $row['id'] . '&redirect=' . urlencode($status);
                    echo "<tr>";
                    echo "<td>{$row['id']}</td>";
                    echo "<td>{$row['farmer_name']}</td>";
                    echo "<td>{$row['machine_name']}</td>";
                    echo "<td>$start_date</td>";
                    echo "<td>$end_date</td>";
                    echo "<td>{$row['start_time']}</td>";
                    echo "<td>{$row['end_time']}</td>";
                    echo "<td>$status</td>";
                    echo "<td>
                            <a class='btn btn-primary btn-sm' onclick='openEditScheduleModal({$row['id']})' href='javascript:void(0)'>Edit</a>
                            <a class='btn btn-success btn-sm' href='$details_redirect'>Details</a>";
                    if ($status === 'Pending') {
                        echo "<a class='btn btn-warning btn-sm' onclick=\"return confirm('Are you sure you want to approve " . htmlspecialchars($row['farmer_name']) . "\\'s schedule to use " . htmlspecialchars($row['machine_name']) . "?');\" style='margin-left: 4px;' href='approve_schedule.php?id={$row['id']}'>Approve</a>";
                        echo "<a class='btn btn-danger btn-sm' onclick=\"return confirm('Are you sure you want to cancel " . htmlspecialchars($row['farmer_name']) . "\\'s schedule?');\"  style='margin-left: 4px;' href='cancel_schedule.php?id={$row['id']}'>Cancel</a>";
                    } elseif ($status === 'Approved') {
                        echo "<a class='btn btn-resched btn-sm' style='margin-left: 4px;' onclick='openRescheduleModal({$row['id']}, \"" . htmlspecialchars($row['farmer_name'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['machine_name'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['schedule_date']) . "\", {$row['date_span']}, \"" . htmlspecialchars($row['start_time']) . "\", \"" . htmlspecialchars($row['end_time']) . "\", \"$status\")'>Reschedule</a>";
                        
                        // Show ellipses with tooltip if there's a reschedule reason
                        if (!empty($row['reschedule_reason'])) {
                            $reason = htmlspecialchars($row['reschedule_reason'], ENT_QUOTES);
                            $rescheduled_date = !empty($row['rescheduled_at']) ? date('M d, Y g:i A', strtotime($row['rescheduled_at'])) : 'N/A';
                            echo "<span class='reschedule-info-icon' data-tooltip='Rescheduled on: $rescheduled_date&#10;Reason: $reason'>⋯</span>";
                        }
                    }
                    echo "</td>";
                    echo "</tr>";
                }
                            
                echo "</tbody>
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
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($statusFilter ?? 'Approved') ?>">

                    <div class="form-group">
                        <label for="schedule_date">New Schedule Date <span style="color: red;">*</span></label>
                        <input type="date" id="schedule_date" name="schedule_date" min="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="date_span">Duration (Days) <span style="color: red;">*</span></label>
                        <input type="number" id="date_span" name="date_span" min="1" max="30" required>
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
        e.preventDefault();

        if (!confirm('Are you sure you want to reschedule this appointment?')) {
            return;
        }

        const formData = new FormData(this);

        fetch('process_reschedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'schedule.php?status=' + data.redirect + '&rescheduled=1';
                } else {
                    const errorDiv = document.getElementById('errorMessage');
                    errorDiv.textContent = data.message || 'Failed to reschedule. Please try again.';
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
                        <select name="machine_id" id="machine_id" required class="form-select">
                            <option value="">Select a Machine</option>
                            <?php
                            $machineList = $conn->query("SELECT id, name FROM machines ORDER BY name ASC");
                            while ($row = $machineList->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>">
                                <?= htmlspecialchars($row['name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="add_schedule_date">Schedule Date <span style="color: red;">*</span></label>
                        <input type="date" id="add_schedule_date" name="schedule_date" min="<?= date('Y-m-d') ?>" required>
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
                        <button type="button" class="btn btn-secondary" onclick="closeAddScheduleModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #4CAF50;">Create Schedule</button>
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
                        <select id="edit_machine_id" name="machine_id" required>
                            <option value="">Select a Machine</option>
                            <?php
                            $machineList = $conn->query("SELECT id, name FROM machines ORDER BY name");
                            while ($row = $machineList->fetch_assoc()):
                            ?>
                                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_schedule_date">Schedule Date <span style="color: red;">*</span></label>
                        <input type="date" id="edit_schedule_date" name="schedule_date" min="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_date_span">Date Span (days) <span style="color: red;">*</span></label>
                        <input type="number" id="edit_date_span" name="date_span" min="0" required>
                        <small style="color: var(--color-dark-variant); display: block; margin-top: 0.25rem;">Number of days to add to the schedule date to get the end date.</small>
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
                        <button type="button" class="btn btn-secondary" onclick="closeEditScheduleModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #4CAF50;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADD SCHEDULE MODAL SCRIPT -->
    <script>
    function openAddScheduleModal() {
        const modal = document.getElementById('addScheduleModal');
        
        document.getElementById('addScheduleForm').reset();
        document.getElementById('addScheduleErrorMessage').style.display = 'none';
        
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

        const formData = new FormData(this);

        fetch('process_add_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'schedule.php?status=' + encodeURIComponent(data.redirect) + '&added=1';
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
    function openEditScheduleModal(scheduleId) {
        const modal = document.getElementById('editScheduleModal');
        const errorDiv = document.getElementById('editScheduleErrorMessage');
        errorDiv.style.display = 'none';
        
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
        
        if (editScheduleDate && editDateSpan) {
            editScheduleDate.addEventListener('change', updateEditEndDate);
            editDateSpan.addEventListener('input', updateEditEndDate);
        }
    });

    document.getElementById('editScheduleForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('process_edit_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'schedule.php?status=<?= urlencode($statusFilter ?: 'Pending') ?>&updated=1';
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

    <script src="../main/dashscript.js"></script>
</body>

</html>