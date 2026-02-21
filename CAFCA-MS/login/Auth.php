<?php

class Auth
{
    private $conn;

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    public function register($username, $password, $confirmPassword)
    {
        if ($password !== $confirmPassword) {
            return "Passwords do not match!";
        }

        $stmt = $this->conn->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            return "Username already taken.";
        }

        $stmt->close();

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare(
            'INSERT INTO users (username, password_hash) VALUES (?, ?)'
        );
        $stmt->bind_param('ss', $username, $passwordHash);

        if ($stmt->execute()) {
            return true;
        }

        return "Registration failed.";
    }

    public function login($username, $password)
    {
        $stmt = $this->conn->prepare(
            'SELECT id, password_hash FROM users WHERE username = ?'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            return "User not found.";
        }

        $stmt->bind_result($id, $hash);
        $stmt->fetch();

        if (password_verify($password, $hash)) {
            return $id;
        }

        return "Incorrect password.";
    }
}