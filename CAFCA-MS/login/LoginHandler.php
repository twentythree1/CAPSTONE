<?php
session_start();

require_once '../db/accounts/testdb.php';
require_once 'Auth.php';

$auth = new Auth($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['password_confirm'])) {
        $result = $auth->register(
            $_POST['username'],
            $_POST['password'],
            $_POST['password_confirm']
        );

        if ($result === true) {
            $_SESSION['success_message'] = "Registration successful!";
            header("Location: logindex.php");
            exit;
        } else {
            $errorMessage = $result;
        }

    } else {
        $result = $auth->login(
            $_POST['username'],
            $_POST['password']
        );

        if (is_int($result)) {
            $_SESSION['user_id'] = $result;
            $_SESSION['username'] = $_POST['username'];
            header("Location: ../dashboard/main/dashdex.php");
            exit;
        } else {
            $errorMessage = $result;
        }
    }
}