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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store">
    <link rel="icon" href="../../LandingPage/others/logo.png" type="image/x-icon">
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
                    <img src="../../LandingPage/others/logo.png">
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
            <h1>Dashboard</h1>

            <div class="insights">
                <a href="../farmers_sec/farmers.php" class="insight-card-link" title="View registered farmers">
                    <div class="farmers">
                        <div class="farmers-left" style="display:flex;align-items:center;gap:1rem;">
                            <div class="icon-bg">
                                <span class="material-icons-sharp">groups</span>
                            </div>
                            <div class="left">
                                <h3>Registered</h3>
                                <h4 style="margin-top:6px; font-weight:600; color:var(--color-dark-variant);">Farmers</h4>
                            </div>
                        </div>

                        <div class="count-right">
                            <h1><?= htmlspecialchars($farmer_count, ENT_QUOTES, 'UTF-8'); ?></h1>
                        </div>
                    </div>
                </a>
                <div class="attendance">
                    <span class="material-icons-sharp">inventory</span>
                    <div class="middle">
                        <div class="left">
                            <h3>Attendance</h3>
                            <h1>Today</h1>
                        </div>
                        <div class="progress">
                            <svg>
                                <circle cx='38' cy='36' r='36'></circle>
                            </svg>
                            <div class="number">
                                <p>62%</p>
                            </div>
                        </div>
                    </div>
                </div>
                <a href="../machines/machine.php" class="insight-card-link" title="View registered machines">
                    <div class="machines">
                        <div class="machines-left" style="display:flex;align-items:center;gap:1rem;">
                            <div class="icon-bg">
                                <span class="material-icons-sharp">agriculture</span>
                            </div>
                            <div class="left">
                                <h3>Registered</h3>
                                <h4 style="margin-top:6px; font-weight:600; color:var(--color-dark-variant);">Machines</h4>
                            </div>
                        </div>

                        <div class="count-right">
                            <h1><?= htmlspecialchars($machine_count, ENT_QUOTES, 'UTF-8'); ?></h1>
                        </div>
                    </div>
                </a>
            </div>

            <div id="calendar-container">
                <h2>Schedule Calendar</h2>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <button id="prev-month"><span
                            class="material-icons-sharp">keyboard_arrow_left</span>Previous</button>
                    <div id="calendar-header"></div>
                    <button id="next-month">Next<span class="material-icons-sharp">keyboard_arrow_right</span></button>
                </div>
                <div id="calendar">
                </div>
            </div>
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
                cell.className = "calendar-day";

                // day number pinned to the top (CSS handles absolute positioning)
                const dayLabel = document.createElement("strong");
                dayLabel.textContent = day;
                cell.appendChild(dayLabel);

                const thisDate =
                `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                // add events for this date (could be multiple)
                events.forEach(ev => {
                    if (ev.date === thisDate) {
                        const eventDiv = document.createElement("div");
                        eventDiv.className = "event";
                        eventDiv.textContent = ev.title || "";
                        // store farmer name in data attribute for custom tooltip and also set title
                        if (ev.farmer_name) {
                            eventDiv.setAttribute('data-farmer', ev.farmer_name);
                            eventDiv.title = ev.farmer_name;
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
</body>

</html>