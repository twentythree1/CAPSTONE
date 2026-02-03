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
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: relative;">
                    The recent schedule was approved successfully!
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
                            
            // Filter schedules based on status
            while ($row = $result->fetch_assoc()) {
                $current_status = $row['status']; 
                            
                if (isset($statusFilter) && $current_status !== $statusFilter) {
                    continue;
                }
                            
                $schedules[$current_status][] = [
                    'row' => $row,
                    'status' => $current_status
                ];
            }
                            
            // Render function for displaying schedules in a table
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
                            
                    $edit_redirect = 'edit_schedule.php?id=' . $row['id'];
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
                            <a class='btn btn-primary btn-sm' href='$edit_redirect'>Edit</a>
                            <a class='btn btn-success btn-sm' href='print_certificate.php?id={$row['id']}'>Details</a>";
                    if ($status === 'Pending') {
                        echo "<a class='btn btn-warning btn-sm' style='margin-left: 4px;' href='approve_schedule.php?id={$row['id']}'>Approve</a>";
                        echo "<a class='btn btn-danger btn-sm' onclick=\"return confirm('Are you sure you want to cancel this schedule?');\"  style='margin-left: 4px;' href='cancel_schedule.php?id={$row['id']}'>Cancel</a>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
                            
                echo "</tbody>
                </table>
                </div><br>";
            }
                            
            if ($statusFilter) {
                renderScheduleTable("$statusFilter Schedules", $schedules[$statusFilter]);
            } else {
                renderScheduleTable("Pending Schedules", $schedules['Pending']);
                renderScheduleTable("Approved Schedules", $schedules['Approved']);
                renderScheduleTable("On-Going Schedules", $schedules['On going']);
                renderScheduleTable("Completed Schedules", $schedules['Completed']);
                renderScheduleTable("Cancelled Schedules", $schedules['Cancelled']);
            }
            ?>
        </main>
    </div>

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