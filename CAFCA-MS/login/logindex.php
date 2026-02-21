<?php
require '../db/accounts/testdb.php';
require 'Auth.php';
require 'LoginHandler.php';
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
    <link rel="stylesheet" href="css/loginstyle.css">
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
    <script src="js/login&regScript.js"></script>
</body>

</html>