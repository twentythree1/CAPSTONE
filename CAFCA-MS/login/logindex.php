<?php
require '../db/accounts/testdb.php';
require 'Auth.php';
require 'LoginHandler.php';

if (!isset($formType))     $formType     = 'login';
if (!isset($errorMessage)) $errorMessage = '';

if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
} else {
    $successMessage = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../assets/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/loginstyle.css">
</head>
<body>

    <div class="particles" id="particles"></div>

    <div class="container <?php echo ($formType === 'register') ? 'active no-transition' : 'no-transition'; ?>" id="mainContainer">

        <!-- LOGIN PANEL -->
        <div class="form-box login">
            <form action="" method="post" id="loginForm">
                <?php if (!empty($errorMessage) && $formType === 'login'): ?>
                    <div class="flash-message error"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                <?php if (!empty($successMessage)): ?>
                    <div class="flash-message success"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <div class="brand">
                    <img src="../assets/logo.png" alt="CAFCA Logo">
                    <span class="brand-name">CAFCA-MS</span>
                </div>
                <p class="form-subtitle">Sign in to continue</p>

                <div class="input-box">
                    <i class='bx bxs-user'></i>
                    <input type="text" name="username" placeholder="Username" required autocomplete="username">
                </div>
                <div class="input-box">
                    <i class='bx bxs-lock-alt'></i>
                    <input type="password" name="password" id="loginPassword" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="toggle-pass" data-target="loginPassword" tabindex="-1">
                        <i class='bx bx-show'></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span>Log In</span>
                    <i class='bx bx-right-arrow-alt'></i>
                </button>
            </form>
        </div>

        <!-- REGISTER PANEL -->
        <div class="form-box register">
            <form action="" method="post" id="registerForm">
                <?php if (!empty($errorMessage) && $formType === 'register'): ?>
                    <div class="flash-message error"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <div class="brand">
                    <span class="brand-name">Create Account</span>
                </div>
                <p class="form-subtitle">Join CAFCA today</p>

                <div class="input-box">
                    <i class='bx bxs-user'></i>
                    <input type="text" name="username" placeholder="Username" required autocomplete="username">
                </div>
                <div class="input-box">
                    <i class='bx bxs-lock-alt'></i>
                    <input type="password" name="password" id="regPassword" placeholder="Password" required>
                    <button type="button" class="toggle-pass" data-target="regPassword" tabindex="-1">
                        <i class='bx bx-show'></i>
                    </button>
                </div>
                <div class="input-box">
                    <i class='bx bxs-lock-alt'></i>
                    <input type="password" name="password_confirm" id="regConfirm" placeholder="Confirm Password" required>
                    <button type="button" class="toggle-pass" data-target="regConfirm" tabindex="-1">
                        <i class='bx bx-show'></i>
                    </button>
                </div>

                <div class="password-strength" id="passStrength">
                    <div class="strength-bar">
                        <div class="bar-fill" id="strengthFill"></div>
                    </div>
                    <span class="strength-label" id="strengthLabel">Password strength</span>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span>Register</span>
                    <i class='bx bx-right-arrow-alt'></i>
                </button>
            </form>
        </div>

        <!-- TOGGLE PANEL -->
        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <div class="toggle-art">
                    <div class="circle c1"></div>
                    <div class="circle c2"></div>
                    <div class="circle c3"></div>
                </div>
                <h2>Welcome Back!</h2>
                <p>Sign in to manage your cooperative records seamlessly.</p>
                <button class="btn btn-outline register-btn">Register</button>
            </div>
            <div class="toggle-panel toggle-right">
                <div class="toggle-art">
                    <div class="circle c1"></div>
                    <div class="circle c2"></div>
                    <div class="circle c3"></div>
                </div>
                <h2>Hello, Friend!</h2>
                <p>Register to get access to the CAFCA Management System.</p>
                <button class="btn btn-outline login-btn">Log In</button>
            </div>
        </div>

    </div>

    <script src="js/login&amp;regScript.js"></script>
</body>
</html>