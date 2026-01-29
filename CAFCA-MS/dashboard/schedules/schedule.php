<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: /CAFCA-MS/login/logindex.php");
    exit();
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
                <a href="../machines/machine.php">
                    <span class="material-icons-sharp">agriculture</span>
                    <h3>Machines</h3>
                </a>
                <?php $schedulesStatus = $_GET['status'] ?? ''; ?>
                <div class="sidebar-dropdown <?= $schedulesStatus ? 'open' : '' ?>">
                    <a href="javascript:void(0)" class="dropdown-toggle"
                        aria-expanded="<?= $schedulesStatus ? 'true' : 'false' ?>">
                        <span class="material-icons-sharp">event</span>
                        <h3>Schedules</h3>
                        <span class="material-icons-sharp dropdown-icon">expand_more</span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="schedule.php?status=Pending">Pending</a>
                        <a href="schedule.php?status=Approved">Approved</a>
                        <a href="schedule.php?status=On going">On going</a>
                        <a href="schedule.php?status=Completed">Completed</a>
                    </div>
                </div>
                <a href="../records/records.php">
                    <span class="material-icons-sharp">topic</span>
                    <h3>Records</h3>
                </a>
                <div class="logout"><a href="../../login/logout.php" class="danger">
                        <span class="material-icons-sharp">logout</span>
                        <h3>Log out</h3>
                    </a>
                </div>
            </div>
        </aside>

        <main>
            <div class="top">
                <?php if (isset($_GET['approved'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    The recent schedule was approved successfully!
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
            <h2>List of Schedules</h2>

            <a href="add_schedule.php" class="btn btn-primary" role="button">Create</a>
            <br>


            <?php
            $servername = "localhost";
            $username = "root";
            $password = "";
            $database = "testdb";

            $conn = new mysqli($servername, $username, $password, $database);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            $sql = "
                SELECT 
                    schedules.id,
                    schedules.status,
                    farmers.name AS farmer_name,
                    machines.name AS machine_name,
                    schedules.schedule_date,
                    schedules.date_span,
                    schedules.start_time,
                    schedules.end_time
                FROM schedules
                JOIN farmers ON schedules.farmer_id = farmers.id
                JOIN machines ON schedules.machine_id = machines.id
            ";
            $result = $conn->query($sql);

            if (!$result) {
                die("Error executing query: " . $conn->error);
            }

            $statusFilter = $_GET['status'] ?? null;

            $schedules = [
                'Pending' => [],
                'Approved' => [],
                'On going' => [],
                'Completed' => []
            ];

            while ($row = $result->fetch_assoc()) {
                if (empty($row['id'])) {
                    continue;
                }
                
                $schedule_start = strtotime($row['schedule_date'] . ' ' . $row['start_time']);
                $schedule_end = strtotime(
                    date('Y-m-d', strtotime($row['schedule_date'] . " +{$row['date_span']} days")) 
                    . ' ' . $row['end_time']
                );
                $now = time();

                if ($row['status'] === 'Approved') {
                    $dynamic_status = 'Approved';
                } elseif ($now < $schedule_start) {
                    $dynamic_status = 'Pending';
                } elseif ($now >= $schedule_start && $now <= $schedule_end) {
                    $dynamic_status = 'On going';
                } else {
                    $dynamic_status = 'Completed';
                }
                if ($statusFilter && $dynamic_status !== $statusFilter) {
                    continue;
                }

                $schedules[$dynamic_status][] = [
                    'row' => $row,
                    'status' => $dynamic_status
                ];
            }

            function renderScheduleTable($title, $data) {
                echo "<h3>$title</h3>";

                if (empty($data)) {
                    echo "<p>No schedules available.</p>";
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
                            <th>Date</th>
                            <th>Span</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                ";

                foreach ($data as $item) {
                    if (!isset($item['row'], $item['status'])) {
                        continue;
                    }

                    $row = $item['row'];
                    $status = $item['status'];

                    $edit_redirect = 'edit_schedule.php?id=' . $row['id'];
                    if (isset($_GET['status'])) {
                        $edit_redirect .= '&redirect=' . urlencode('schedule.php?status=' . $_GET['status']);
                    }
                            
                    echo "<tr>";
                    echo "<td>{$row['id']}</td>";
                    echo "<td>{$row['farmer_name']}</td>";
                    echo "<td>{$row['machine_name']}</td>";
                    echo "<td>{$row['schedule_date']}</td>";
                    echo "<td>{$row['date_span']}</td>";
                    echo "<td>{$row['start_time']}</td>";
                    echo "<td>{$row['end_time']}</td>";
                    echo "<td>$status</td>";
                    echo "<td>";
                    echo "  <a class='btn btn-primary btn-sm' href='$edit_redirect'>Edit</a>";
                    echo "  <a class='btn btn-success btn-sm' href='print_certificate.php?id={$row['id']}'>Details</a>";
                    if ($status === 'Pending') {
                        echo "  <a class='btn btn-warning btn-sm' href='approve_schedule.php?id={$row['id']}'>Approve</a>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }

                echo "</tbody>
                </table>
                </div><br>";
            }
            $currentStatus = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : null;

            if ($statusFilter) {
                renderScheduleTable($statusFilter . " Schedules", $schedules[$statusFilter], $currentStatus);
            } else {
                renderScheduleTable("Pending Schedules", $schedules['Pending'], "schedule.php?status=Pending");
                renderScheduleTable("Approved Schedules", $schedules['Approved'], "schedule.php?status=Approved");
                renderScheduleTable("On-Going Schedules", $schedules['On going'], "schedule.php?status=On going");
                renderScheduleTable("Completed Schedules", $schedules['Completed'], "schedule.php?status=Completed");
            }
            ?>
        </main>
    </div>

    <script>
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) alert.remove();
    }, 5000);
    </script>
    <script src="../main/dashscript.js"></script>

    <script>
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) alert.remove();
    }, 5000);
    </script>

    <script>
    const scheduleDropdown = document.querySelector(".sidebar-dropdown");

    scheduleDropdown.querySelector(".dropdown-toggle")
        .addEventListener("click", () => {
            scheduleDropdown.classList.toggle("open");

            const menu = scheduleDropdown.querySelector(".dropdown-menu");
            menu.style.display = menu.style.display === "flex" ? "none" : "flex";
        });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const scheduleDropdown = document.querySelector(".sidebar-dropdown");
        if (!scheduleDropdown) return;

        const toggle = scheduleDropdown.querySelector(".dropdown-toggle");
        const menu = scheduleDropdown.querySelector(".dropdown-menu");
        if (!toggle || !menu) return;

        toggle.addEventListener("click", () => {
            scheduleDropdown.classList.toggle("open");
            menu.style.display = scheduleDropdown.classList.contains("open") ? "flex" : "none";
            toggle.setAttribute('aria-expanded', scheduleDropdown.classList.contains('open'));
        });

        const normalize = s => (s || '').toString().replace(/\s+/g, ' ').trim().toLowerCase();

        const params = new URLSearchParams(window.location.search);
        const urlStatus = normalize(params.get('status'));

        Array.from(menu.querySelectorAll('a')).forEach(a => {
            let hrefStatus = '';
            try {
                hrefStatus = normalize((new URL(a.href, window.location.origin)).searchParams.get(
                    'status'));
            } catch (e) {
                hrefStatus = normalize(a.getAttribute('href').split('?')[1] || '');
            }

            if (urlStatus && hrefStatus === urlStatus) {
                scheduleDropdown.classList.add('open');
                menu.style.display = 'flex';
                toggle.setAttribute('aria-expanded', 'true');

                a.classList.add('active');
            } else {
                a.classList.remove('active');
            }

            a.addEventListener('click', () => {
                const st = hrefStatus || urlStatus;
                if (st) localStorage.setItem('schedulesStatus', st);
            });
        });

        if (!urlStatus) {
            const saved = localStorage.getItem('schedulesStatus');
            if (saved) {
                const savedNorm = normalize(saved);
                Array.from(menu.querySelectorAll('a')).forEach(a => {
                    let hrefStatus = normalize((new URL(a.href, window.location.origin)).searchParams
                        .get('status') || '');
                    if (hrefStatus === savedNorm) {
                        a.classList.add('active');
                        scheduleDropdown.classList.add('open');
                        menu.style.display = 'flex';
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                });
            }
        }
    });
    </script>
    <script src="../main/dashscript.js"></script>
</body>

</html>