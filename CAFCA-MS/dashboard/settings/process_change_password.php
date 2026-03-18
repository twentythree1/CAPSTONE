<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
session_start();

header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "testdb");
if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_SESSION['username'];
    $current_pw = $_POST['current_password'];
    $new_pw = $_POST['new_password'];
    $confirm_pw = $_POST['confirm_password'];

    if ($new_pw !== $confirm_pw) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
        exit;
    }

    if ($new_pw === $current_pw) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'New password cannot be the same as the current password']);
        exit;
    }

    // Fetch user from database
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($current_pw, $user['password_hash'])) {
        // Current password is correct, hash and update with new password
        $hashed_pw = password_hash($new_pw, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $update_stmt->bind_param("ss", $hashed_pw, $username);
        
        if ($update_stmt->execute()) {
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
        } else {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Database update failed']);
        }
    } else {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Incorrect current password']);
    }
}