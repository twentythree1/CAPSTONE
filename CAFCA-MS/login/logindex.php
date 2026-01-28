<?php
session_start();
require '../db/accounts/testdb.php';

$errorMessage = '';
$successMessage = '';

if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$formType = 'login';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['password_confirm'])) {
        $formType = 'register';
        // Registration form
        $username = htmlspecialchars($_POST['username']);
        $password = $_POST['password'];
        $passwordConfirm = $_POST['password_confirm'];

        if ($password !== $passwordConfirm) {
            $errorMessage = 'Passwords do not match!';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            if ($stmt) {
                $stmt->bind_param('ss', $username, $passwordHash);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Registration successful! You can now log in.";
                    header("Location: logindex.php");
                    exit;
                } else {
                    $errorMessage = "Registration failed: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMessage = "Database error. Please try again.";
            }
        }
    } else {
        $formType = 'login';
        // Login form
        $username = htmlspecialchars($_POST['username']);
        $password = $_POST['password'];

        $stmt = $conn->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $passwordHash);
                $stmt->fetch();
                if (password_verify($password, $passwordHash)) {
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username;
                    header("Location: ../dashboard/main/dashdex.php");
                    exit;
                } else {
                    $errorMessage = 'Incorrect Password!   Please try again.';
                }
            } else {
                $errorMessage = 'User not found! Please register before logging in.';
            }
            $stmt->close();
        } else {
            $errorMessage = 'Database error. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="loginstyle.css">
</head>

<body>
    <div class="container">
        <div class="form-box login">
            <form action="" method="post">
                <?php if (!empty($errorMessage)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                <?php if (!empty($successMessage)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>
                <img src="../LandingPage/others/logo.png">
                <h1>CAFCA-MS</h1>
                <div class="input-box">
                    <input type="name" name="username" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <button type="submit" class="btn">Log in</button>
            </form>
        </div>

        <div class="form-box register">
            <form action="" method="post">
                <?php if (!empty($errorMessage)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                <h1>Register</h1>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password_confirm" placeholder="Password Confirm" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <button type="submit" class="btn">Register</button>
            </form>
        </div>

        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h1>Hello, Welcome!</h1>
                <p>Don't have an account?</p>
                <button class="btn register-btn">Register</button>
            </div>
            <div class="toggle-panel toggle-right">
                <h1>Welcome Back!</h1>
                <p>Already have an account?</p>
                <button class="btn login-btn">Log in</button>
            </div>
        </div>
    </div>

    <script>
        const initialForm = "<?php echo $formType; ?>";
    </script>
    <script src="login&regScript.js"></script>
</body>

</html>