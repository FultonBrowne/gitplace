<?php
session_start();
require_once 'database.php';

function register($username, $password) {
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        return false;
    }

    $db = Database::getInstance();

    // Check if username exists
    $stmt = $db->prepare('SELECT id FROM users WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    if ($stmt->execute()->fetchArray()) {
        return false;
    }

    // Create user's repository directory
    $repoPath = __DIR__ . '/../repos/' . $username;
    if (!file_exists($repoPath)) {
        mkdir($repoPath, 0755, true);
    }

    // Create user
    $stmt = $db->prepare('
        INSERT INTO users (username, password_hash, repo_path)
        VALUES (:username, :password_hash, :repo_path)
    ');

    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
    $stmt->bindValue(':repo_path', $repoPath, SQLITE3_TEXT);

    return $stmt->execute() !== false;
}

function login($username, $password) {
    $db = Database::getInstance();

    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $username;
            return true;
        }
    }
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;

    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    return $result->fetchArray(SQLITE3_ASSOC);
}

function logout() {
    session_destroy();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
}
