<?php
session_start();
header('Content-Type: application/json');

// Suppress PHP warnings/notices from polluting JSON output
error_reporting(0);

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit();
}

$servername = "localhost";
$db_user    = "root";
$db_pass    = "";
$database   = "testdb";

$conn = new mysqli($servername, $db_user, $db_pass, $database);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

$newName         = trim($_POST['new_name'] ?? '');
$password        = trim($_POST['password'] ?? '');
$currentUsername = $_SESSION['username'];

if (!$newName || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit();
}

// Verify current password against the users table
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
$stmt->bind_param("s", $currentUsername);
$stmt->execute();
$result = $stmt->get_result();

if (!$row = $result->fetch_assoc()) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    $stmt->close();
    $conn->close();
    exit();
}

if (!password_verify($password, $row['password_hash'])) {
    echo json_encode(['status' => 'error', 'message' => 'Incorrect password.']);
    $stmt->close();
    $conn->close();
    exit();
}

$stmt->close();

// Check if the new name already exists in the users table (case-insensitive)
$stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND username != ?");
$stmt->bind_param("ss", $newName, $currentUsername);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => "The name \"$newName\" is already taken. Please choose a different name."]);
    $stmt->close();
    $conn->close();
    exit();
}

$stmt->close();

// Update the username
$stmt = $conn->prepare("UPDATE users SET username = ? WHERE username = ?");
$stmt->bind_param("ss", $newName, $currentUsername);

if ($stmt->execute()) {
    $_SESSION['username'] = $newName;
    echo json_encode(['status' => 'success', 'message' => 'Name updated successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update name. Please try again.']);
}

$stmt->close();
$conn->close();
?>