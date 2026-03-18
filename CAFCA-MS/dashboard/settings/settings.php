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
    <link rel="icon" href="../../assets/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Admin</title>
    <link rel="stylesheet" href="../farmers_sec/farmerstyle.css">
    <!-- MATERIAL ICONS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
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
                <a href="../settings/settings.php" class="active">
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
                <button id="menu-btn">
                    <span class="material-icons-sharp">menu</span>
                </button>
                <div class="theme-toggler">
                    <span class="material-icons-sharp active">light_mode</span>
                    <span class="material-icons-sharp">dark_mode</span>
                </div>
                <div class="profile" style="display: flex; flex-direction: column; text-align: right;">
                    <span style="font-size: 18px; text-transform: capitalize; font-weight: 700;"><?= $_SESSION['username']; ?></span>
                    <small class="text-muted">Admin</small>
                </div>
            </div>

            <div class="settings-content">

                <!-- TABBED CARD WRAPPER -->
                <div class="settings-tabcard">

                    <!-- TAB BAR — glued to top of card -->
                    <div class="settings-tabs">
                        <button class="settings-tab active" onclick="showTab('name')" id="tab-btn-name" title="Change Name">
                            <span class="material-icons-sharp">badge</span>
                            <span class="tab-label">Change Name</span>
                        </button>
                        <button class="settings-tab" onclick="showTab('security')" id="tab-btn-security" title="Change Password">
                            <span class="material-icons-sharp">lock</span>
                            <span class="tab-label">Change Password</span>
                        </button>
                    </div>

                    <!-- TAB 1: CHANGE NAME -->
                    <div class="settings-card" id="tab-name">
                        <div class="settings-card-header">
                            <h1>Change <span>Name</span></h1>
                            <p>Update your display name</p>
                        </div>

                        <div id="nameErrorMsg" class="settings-error"></div>

                        <div class="settings-field">
                            <label for="new_name">New Name</label>
                            <div class="settings-input-wrap">
                                <input type="text" id="new_name" placeholder="Enter new name"
                                    onkeydown="if(event.key==='Enter') submitChangeName()">
                            </div>
                        </div>

                        <div class="settings-field">
                            <label for="confirm_name_password">Current Password</label>
                            <div class="settings-input-wrap">
                                <input type="password" id="confirm_name_password" placeholder="Enter current password to confirm"
                                    onkeydown="if(event.key==='Enter') submitChangeName()">
                                <span class="toggle-pw" onclick="togglePw('confirm_name_password', this)">SHOW</span>
                            </div>
                        </div>

                        <div class="settings-card-footer">
                            <button class="btn-update-pw" onclick="submitChangeName()">Update Name</button>
                        </div>
                    </div>

                    <!-- TAB 2: CHANGE PASSWORD -->
                    <div class="settings-card" id="tab-security" style="display:none;">
                        <div class="settings-card-header">
                            <h1>Change <span>Password</span></h1>
                            <p>Use a strong, unique password</p>
                        </div>

                        <div id="settingsErrorMsg" class="settings-error"></div>

                        <div class="settings-field">
                            <label for="current_password">Current Password</label>
                            <div class="settings-input-wrap">
                                <input type="password" id="current_password" placeholder="Enter current password"
                                    onkeydown="if(event.key==='Enter') submitChangePassword()">
                                <span class="toggle-pw" onclick="togglePw('current_password', this)">SHOW</span>
                            </div>
                        </div>

                        <div class="settings-field">
                            <label for="new_password">New Password</label>
                            <div class="settings-input-wrap">
                                <input type="password" id="new_password" placeholder="Enter new password" oninput="checkStrength(this.value)"
                                    onkeydown="if(event.key==='Enter') submitChangePassword()">
                                <span class="toggle-pw" onclick="togglePw('new_password', this)">SHOW</span>
                            </div>
                            <div class="pw-strength">
                                <span id="strengthLabel">Password strength will appear here</span>
                                <div class="pw-strength-bar"><div class="pw-strength-fill" id="strengthBar"></div></div>
                            </div>
                        </div>

                        <div class="settings-field">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="settings-input-wrap">
                                <input type="password" id="confirm_password" placeholder="Re-enter new password"
                                    onkeydown="if(event.key==='Enter') submitChangePassword()">
                                <span class="toggle-pw" onclick="togglePw('confirm_password', this)">SHOW</span>
                            </div>
                        </div>

                        <div class="settings-card-footer">
                            <button class="btn-update-pw" onclick="submitChangePassword()">Update Password</button>
                        </div>
                    </div>

                </div><!-- end .settings-tabcard -->
            </div>
        </div>

        <style>
        /* Glue wrapper: tabs sit directly on top of the card */
        .settings-tabcard {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .settings-tabs {
            display: flex;
            gap: 0;
            padding: 0;
            margin: 0;
        }

        .settings-tab {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 22px;
            background: var(--color-primary-variant, #dcebde);
            color: var(--color-dark-variant, #5b685d);
            border: 1.5px solid rgba(160, 200, 120, 0.35);
            border-bottom: none;
            border-radius: 10px 10px 0 0;
            font-family: poppins, sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            margin-right: 4px;
        }

        .settings-tab .material-icons-sharp {
            font-size: 1.15rem;
            line-height: 1;
        }

        /* Active tab: white background bleeds into card, hides the seam */
        .settings-tab.active {
            background: var(--color-white, #fff);
            color: var(--color-primary, #A0C878);
            border-color: rgba(160, 200, 120, 0.35);
            position: relative;
            z-index: 2;
            margin-bottom: -2px;
        }

        .settings-tab:not(.active):hover {
            background: var(--color-info-light, #dcebde);
            color: var(--color-dark, #364938);
        }

        /* Card: square top-left corner so it connects with the tab edge */
        .settings-tabcard .settings-card {
            border-radius: 0 16px 16px 16px;
            position: relative;
            z-index: 1;
        }
        </style>

        <script>
        function togglePw(fieldId, el) {
            const input = document.getElementById(fieldId);
            if (input.type === 'password') {
                input.type = 'text';
                el.textContent = 'HIDE';
            } else {
                input.type = 'password';
                el.textContent = 'SHOW';
            }
        }

        function checkStrength(val) {
            const bar = document.getElementById('strengthBar');
            const label = document.getElementById('strengthLabel');
            if (!val) {
                bar.style.width = '0'; label.textContent = 'Password strength will appear here'; return;
            }
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const levels = [
                { w: '25%', color: '#e74c3c', text: 'Weak' },
                { w: '50%', color: '#e67e22', text: 'Fair' },
                { w: '75%', color: '#f1c40f', text: 'Good' },
                { w: '100%', color: '#27ae60', text: 'Strong' },
            ];
            const lvl = levels[score - 1] || levels[0];
            bar.style.width = lvl.w;
            bar.style.background = lvl.color;
            label.textContent = lvl.text;
        }

        function showTab(tab) {
            document.getElementById('tab-name').style.display     = (tab === 'name')     ? '' : 'none';
            document.getElementById('tab-security').style.display = (tab === 'security') ? '' : 'none';
            document.getElementById('tab-btn-name').classList.toggle('active',     tab === 'name');
            document.getElementById('tab-btn-security').classList.toggle('active', tab === 'security');
        }

        function submitChangeName() {
            const newName  = document.getElementById('new_name').value.trim();
            const password = document.getElementById('confirm_name_password').value.trim();
            const errDiv   = document.getElementById('nameErrorMsg');

            errDiv.style.display = 'none';
            errDiv.textContent   = '';

            if (!newName || !password) {
                errDiv.textContent   = 'All fields are required.';
                errDiv.style.display = 'block';
                return;
            }

            const formData = new FormData();
            formData.append('new_name', newName);
            formData.append('password', password);

            fetch('process_change_name.php', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(text => {
                    let data;
                    try {
                        const jsonStart = text.indexOf('{');
                        data = JSON.parse(jsonStart !== -1 ? text.slice(jsonStart) : text);
                    } catch (e) {
                        errDiv.textContent   = 'Unexpected server response. Please try again.';
                        errDiv.style.display = 'block';
                        return;
                    }
                    if (data.status === 'success') {
                        document.getElementById('new_name').value              = '';
                        document.getElementById('confirm_name_password').value = '';
                        errDiv.style.display = 'none';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Success', data.message, 'success').then(() => location.reload());
                        } else {
                            alert(data.message);
                            location.reload();
                        }
                    } else {
                        errDiv.textContent   = data.message || 'Failed to update name. Please try again.';
                        errDiv.style.display = 'block';
                    }
                })
                .catch(() => {
                    errDiv.textContent   = 'An error occurred. Please try again.';
                    errDiv.style.display = 'block';
                });
        }

        function submitChangePassword() {
            const current = document.getElementById('current_password').value.trim();
            const newPw   = document.getElementById('new_password').value.trim();
            const confirm = document.getElementById('confirm_password').value.trim();
            const errDiv  = document.getElementById('settingsErrorMsg');

            errDiv.style.display = 'none';
            errDiv.textContent   = '';

            if (!current || !newPw || !confirm) {
                errDiv.textContent = 'All fields are required.';
                errDiv.style.display = 'block';
                return;
            }
            if (newPw !== confirm) {
                errDiv.textContent = 'New password and confirmation do not match.';
                errDiv.style.display = 'block';
                return;
            }
            if (newPw === current) {
                errDiv.textContent = 'New password cannot be the same as the current password.';
                errDiv.style.display = 'block';
                return;
            }

            const formData = new FormData();
            formData.append('current_password', current);
            formData.append('new_password', newPw);
            formData.append('confirm_password', confirm);

            fetch('process_change_password.php', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(text => {
                    let data;
                    try {
                        const jsonStart = text.indexOf('{');
                        data = JSON.parse(jsonStart !== -1 ? text.slice(jsonStart) : text);
                    } catch (e) {
                        errDiv.textContent   = 'Unexpected server response. Please try again.';
                        errDiv.style.display = 'block';
                        return;
                    }
                    if (data.status === 'success') {
                        document.getElementById('current_password').value = '';
                        document.getElementById('new_password').value     = '';
                        document.getElementById('confirm_password').value = '';
                        document.getElementById('strengthBar').style.width = '0';
                        document.getElementById('strengthLabel').textContent = 'Password strength will appear here';
                        errDiv.style.display = 'none';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Success', data.message, 'success');
                        } else {
                            alert(data.message);
                        }
                    } else {
                        errDiv.textContent   = data.message || 'Failed to update password. Please try again.';
                        errDiv.style.display = 'block';
                    }
                })
                .catch(() => {
                    errDiv.textContent   = 'An error occurred. Please try again.';
                    errDiv.style.display = 'block';
                });
        }
        </script>

        <script src="../main/dashscript.js"></script>
        </main>
    </div>
</body>

</html>