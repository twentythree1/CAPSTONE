<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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

$farmer_count = 0;
$sql = "SELECT COUNT(*) AS cnt FROM farmers";
if ($result = $conn->query($sql)) {
    $row = $result->fetch_assoc();
    $farmer_count = (int)($row['cnt'] ?? 0);
    $result->free();
}

$machine_count = 0;
$sql = "SELECT COUNT(*) AS cnt FROM machines";
if ($result = $conn->query($sql)) {
    $row = $result->fetch_assoc();
    $machine_count = (int)($row['cnt'] ?? 0);
    $result->free();
}

// Count farmers currently booked (have an On going or Approved active schedule right now)
$booked_farmers = 0;
$bookedFarmerSql = "SELECT COUNT(DISTINCT s.farmer_id) AS cnt
                    FROM schedules s
                    WHERE s.status = 'Approved'
                    AND NOW() >= CONCAT(s.schedule_date, ' ', s.start_time)
                    AND NOW() <= CONCAT(DATE_ADD(s.schedule_date, INTERVAL s.date_span DAY), ' ', s.end_time)";
if ($result = $conn->query($bookedFarmerSql)) {
    $row = $result->fetch_assoc();
    $booked_farmers = (int)($row['cnt'] ?? 0);
    $result->free();
}

// Count machines currently in use (On going schedule)
$booked_machines = 0;
$bookedMachineSql = "SELECT COUNT(DISTINCT s.machine_id) AS cnt
                     FROM schedules s
                     WHERE s.status = 'Approved'
                     AND NOW() >= CONCAT(s.schedule_date, ' ', s.start_time)
                     AND NOW() <= CONCAT(DATE_ADD(s.schedule_date, INTERVAL s.date_span DAY), ' ', s.end_time)";
if ($result = $conn->query($bookedMachineSql)) {
    $row = $result->fetch_assoc();
    $booked_machines = (int)($row['cnt'] ?? 0);
    $result->free();
}

$farmer_booked_pct = $farmer_count > 0 ? round(($booked_farmers / $farmer_count) * 100) : 0;
$machine_booked_pct = $machine_count > 0 ? round(($booked_machines / $machine_count) * 100) : 0;

// Fetch On going schedules (Approved + currently active window)
$ongoingSchedules = [];
$ongoingSql = "SELECT s.id, f.name AS farmer_name, m.name AS machine_name,
                      s.schedule_date, s.start_time, s.end_time, s.date_span
               FROM schedules s
               LEFT JOIN farmers f ON s.farmer_id = f.id
               LEFT JOIN machines m ON s.machine_id = m.id
               WHERE s.status = 'Approved'
               AND NOW() >= CONCAT(s.schedule_date, ' ', s.start_time)
               AND NOW() <= CONCAT(DATE_ADD(s.schedule_date, INTERVAL s.date_span DAY), ' ', s.end_time)
               ORDER BY s.schedule_date ASC, s.start_time ASC
               LIMIT 10";
if ($result = $conn->query($ongoingSql)) {
    while ($row = $result->fetch_assoc()) $ongoingSchedules[] = $row;
    $result->free();
}

// Handle AJAX clear-completed action
if (isset($_POST['action']) && $_POST['action'] === 'clear_completed') {
    header('Content-Type: application/json');
    $del = $conn->query("DELETE FROM schedules WHERE status = 'Completed'");
    echo json_encode(['success' => (bool)$del]);
    $conn->close();
    exit;
}

// Fetch Completed schedules
$completedSchedules = [];
$completedSql = "SELECT s.id, f.name AS farmer_name, m.name AS machine_name,
                        s.schedule_date, s.end_time, s.date_span
                 FROM schedules s
                 LEFT JOIN farmers f ON s.farmer_id = f.id
                 LEFT JOIN machines m ON s.machine_id = m.id
                 WHERE s.status = 'Completed'
                 ORDER BY s.schedule_date DESC
                 LIMIT 10";
if ($result = $conn->query($completedSql)) {
    while ($row = $result->fetch_assoc()) $completedSchedules[] = $row;
    $result->free();
}

$completedTotal = 0;
if ($r = $conn->query("SELECT COUNT(*) AS cnt FROM schedules WHERE status = 'Completed'")) {
    $completedTotal = (int)($r->fetch_assoc()['cnt'] ?? 0);
    $r->free();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store">
    <link rel="icon" href="../../assets/logo.png" type="image/x-icon">
    <title>CAFCA | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- MATERIAL ICONS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp">
    <!-- CUSTOM CSS -->
    <link rel="stylesheet" href="dashstyle.css">
</head>

<body>
    <div class="container">
        <aside>
            <div class="top">
                <a class="logo" href="dashdex.php">
                    <img src="../../assets/logo.png">
                    <h2>CAFCA <span>MS</span></h2>
                </a>
                <div class="close" id="close-btn">
                    <span class="material-icons-sharp">close</span>
                </div>
            </div>

            <div class="sidebar">
                <a href="#" class="active">
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
            <div class="dash-welcome">
                <div>
                    <h1>Dashboard</h1>
                    <p class="text-muted">Here's what's happening today.</p>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dash-grid">

                <!-- FARMERS CARD -->
                <a href="../farmers_sec/farmers.php" class="stat-card stat-card--farmers dash-grid__farmers" title="View registered farmers">
                    <div class="stat-card__body">
                        <div class="stat-card__top-row">
                            <div class="stat-card__left">
                                <div class="stat-card__icon-wrap">
                                    <span class="material-icons-sharp">groups</span>
                                </div>
                                <div class="stat-card__label">Registered Farmers</div>
                            </div>
                            <div class="stat-card__count"><?= htmlspecialchars($farmer_count, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="stat-card__progress-wrap">
                            <div class="stat-card__progress-bar">
                                <div class="stat-card__progress-fill" style="width: <?= $farmer_booked_pct ?>%"></div>
                            </div>
                            <div class="stat-card__progress-label">
                                <span><?= $booked_farmers ?> currently active</span>
                                <span><?= $farmer_booked_pct ?>%</span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card__deco"></div>
                </a>

                <!-- MACHINES CARD -->
                <a href="../machines/machine.php" class="stat-card stat-card--machines dash-grid__machines" title="View registered machines">
                    <div class="stat-card__body">
                        <div class="stat-card__top-row">
                            <div class="stat-card__left">
                                <div class="stat-card__icon-wrap">
                                    <span class="material-icons-sharp">agriculture</span>
                                </div>
                                <div class="stat-card__label">Registered Machines</div>
                            </div>
                            <div class="stat-card__count"><?= htmlspecialchars($machine_count, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="stat-card__progress-wrap">
                            <div class="stat-card__progress-bar">
                                <div class="stat-card__progress-fill" style="width: <?= $machine_booked_pct ?>%"></div>
                            </div>
                            <div class="stat-card__progress-label">
                                <span><?= $booked_machines ?> currently in use</span>
                                <span><?= $machine_booked_pct ?>%</span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card__deco"></div>
                </a>

                <!-- ON GOING PANEL -->
                <div class="panel-card dash-grid__ongoing">
                    <div class="panel-card__header">
                        <div>
                            <h2 class="panel-card__title">On Going Schedules</h2>
                            <p class="text-muted panel-card__sub"><?= count($ongoingSchedules) ?> active right now</p>
                        </div>
                        <a href="../schedules/schedule.php?status=Pending" class="panel-btn panel-btn--primary" title="Add new schedule">
                            <span class="material-icons-sharp">add</span>
                        </a>
                    </div>
                    <div class="panel-card__list panel-card__list--fixed">
                        <?php if (empty($ongoingSchedules)): ?>
                            <div class="panel-empty">
                                <span class="material-icons-sharp">event_available</span>
                                <p>No active schedules right now</p>
                            </div>
                        <?php else: foreach ($ongoingSchedules as $s): ?>
                            <div class="panel-item">
                                <div class="panel-item__dot panel-item__dot--ongoing"></div>
                                <div class="panel-item__body">
                                    <span class="panel-item__name"><?= htmlspecialchars($s['farmer_name'] ?? '—') ?></span>
                                    <span class="panel-item__meta"><?= htmlspecialchars($s['machine_name'] ?? '—') ?></span>
                                </div>
                                <div class="panel-item__time">
                                    <?= date('M j', strtotime($s['schedule_date'])) ?>
                                    <small><?= date('H:i', strtotime($s['start_time'])) ?> – <?= date('H:i', strtotime($s['end_time'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <a href="../schedules/schedule.php?status=On+going" class="panel-see-all">
                        See all ongoing schedules <span class="material-icons-sharp">chevron_right</span>
                    </a>
                </div>

                <!-- CALENDAR -->
                <div id="calendar-container" class="dash-grid__calendar">
                    <div class="calendar-section-header">
                        <div>
                            <h2>Schedule Calendar</h2>
                            <p class="text-muted">Overview of all scheduled activities</p>
                        </div>
                        <div class="calendar-nav">
                            <button id="prev-month"><span class="material-icons-sharp">keyboard_arrow_left</span></button>
                            <div id="calendar-header"></div>
                            <button id="next-month"><span class="material-icons-sharp">keyboard_arrow_right</span></button>
                        </div>
                    </div>
                    <div id="calendar"></div>
                </div>

                <!-- COMPLETED PANEL -->
                <div class="panel-card dash-grid__completed">
                    <div class="panel-card__header">
                        <div>
                            <h2 class="panel-card__title">Completed Schedules</h2>
                            <p class="text-muted panel-card__sub"><?= $completedTotal ?> total completed</p>
                        </div>
                        <button class="panel-btn panel-btn--danger" id="clearCompletedBtn" <?= $completedTotal === 0 ? 'disabled' : '' ?>>
                            <span class="material-icons-sharp">delete_sweep</span> Clear
                        </button>
                    </div>
                    <div class="panel-card__list panel-card__list--fixed">
                        <?php if (empty($completedSchedules)): ?>
                            <div class="panel-empty">
                                <span class="material-icons-sharp">check_circle</span>
                                <p>No completed schedules yet</p>
                            </div>
                        <?php else: foreach ($completedSchedules as $s):
                            $endDate = date('Y-m-d', strtotime($s['schedule_date'] . " +{$s['date_span']} days"));
                        ?>
                            <div class="panel-item">
                                <div class="panel-item__dot panel-item__dot--done"></div>
                                <div class="panel-item__body">
                                    <span class="panel-item__name"><?= htmlspecialchars($s['farmer_name'] ?? '—') ?></span>
                                    <span class="panel-item__meta"><?= htmlspecialchars($s['machine_name'] ?? '—') ?></span>
                                </div>
                                <div class="panel-item__time">
                                    <?= date('M j', strtotime($endDate)) ?>
                                    <small>Completed</small>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <a href="../schedules/schedule.php?status=Completed" class="panel-see-all">
                        See all completed schedules <span class="material-icons-sharp">chevron_right</span>
                    </a>
                </div>

            </div><!-- end dash-grid -->
        </main>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const calendar = document.getElementById("calendar");
        const prevBtn = document.getElementById("prev-month");
        const nextBtn = document.getElementById("next-month");
        const header = document.getElementById("calendar-header");

        let currentDate = new Date();

        function renderCalendar(events) {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const firstDay = new Date(year, month, 1).getDay();

            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            header.innerText = `${monthNames[month]} ${year}`;
            calendar.innerHTML = "";

            // empty slots for the first row
            for (let i = 0; i < firstDay; i++) {
                calendar.innerHTML += '<div></div>';
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const cell = document.createElement("div");
                const thisDate =
                `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                const today = new Date();
                const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
                cell.className = "calendar-day" + (thisDate === todayStr ? " today" : "");

                // day number pinned to the top (CSS handles absolute positioning)
                const dayLabel = document.createElement("strong");
                dayLabel.textContent = day;
                cell.appendChild(dayLabel);

                // add events for this date - expand multi-day spans via date_span
                events.forEach(ev => {
                    const spanDays = (ev.date_span || 0);
                    const startDate = new Date(ev.date + 'T00:00:00');
                    const endDate = new Date(startDate);
                    endDate.setDate(endDate.getDate() + spanDays);
                    const cellDate = new Date(thisDate + 'T00:00:00');

                    if (cellDate >= startDate && cellDate <= endDate) {
                        const eventDiv = document.createElement("div");
                        eventDiv.className = "event";

                        // Show time on first day only; other days show continuation
                        if (cellDate.getTime() === startDate.getTime()) {
                            eventDiv.textContent = ev.title || "";
                        } else {
                            eventDiv.textContent = "📍 (cont.)";
                        }

                        // Tooltip: farmer - machine
                        const tooltipParts = [];
                        if (ev.farmer_name) tooltipParts.push(ev.farmer_name);
                        if (ev.machine_name) tooltipParts.push(ev.machine_name);
                        const tooltipText = tooltipParts.join(' - ');
                        if (tooltipText) {
                            eventDiv.setAttribute('data-farmer', tooltipText);
                            eventDiv.title = tooltipText;
                        }
                        cell.appendChild(eventDiv);
                    }
                });

                calendar.appendChild(cell);
            }
        }

        function loadCalendar() {
            fetch("get_schedules.php")
                .then(res => res.json())
                .then(data => renderCalendar(Array.isArray(data) ? data : []))
                .catch(err => console.error("Failed to fetch schedules:", err));
        }

        prevBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            loadCalendar();
        });

        nextBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            loadCalendar();
        });

        loadCalendar();
    });
    </script>
    <script src="dashscript.js"></script>
    <script>
    // Animate progress bars on load
    document.addEventListener("DOMContentLoaded", function () {
        const fills = document.querySelectorAll(".stat-card__progress-fill");
        fills.forEach(function (el) {
            const target = el.style.width;
            el.style.width = "0%";
            setTimeout(function () { el.style.width = target; }, 300);
        });

        // Clear completed schedules
        const clearBtn = document.getElementById("clearCompletedBtn");
        if (clearBtn) {
            clearBtn.addEventListener("click", function () {
                if (!confirm("Are you sure you want to permanently delete all completed schedules? This cannot be undone.")) return;
                clearBtn.disabled = true;
                clearBtn.innerHTML = '<span class="material-icons-sharp" style="animation:spin 0.8s linear infinite">sync</span> Clearing...';
                fetch("dashdex.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "action=clear_completed"
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert("Failed to clear. Please try again.");
                        clearBtn.disabled = false;
                        clearBtn.innerHTML = '<span class="material-icons-sharp">delete_sweep</span> Clear';
                    }
                })
                .catch(() => {
                    alert("An error occurred.");
                    clearBtn.disabled = false;
                    clearBtn.innerHTML = '<span class="material-icons-sharp">delete_sweep</span> Clear';
                });
            });
        }
    });
    </script>
</body>

</html>