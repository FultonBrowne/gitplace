<?php
session_start();

function register($username, $password) {
    $db = new SQLite3('gitplace.db');

    // Check if username already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();

    if ($result->fetchArray()) {
        return false; // Username already exists
    }

    // Create user's repository directory
    $repoPath = '/Users/fultonbrowne/code/users/' . $username;
    if (!file_exists($repoPath)) {
        mkdir($repoPath, 0755, true);
    }

    // Insert new user
    $stmt = $db->prepare('INSERT INTO users (username, password_hash, repo_path) VALUES (:username, :password_hash, :repo_path)');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
    $stmt->bindValue(':repo_path', $repoPath, SQLITE3_TEXT);

    return $stmt->execute() !== false;
}

function login($username, $password) {
    $db = new SQLite3('gitplace.db');

    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();

    if ($row = $result->fetchArray()) {
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

function getCurrentUserRepoPath() {
    if (!isLoggedIn()) return null;

    $db = new SQLite3('gitplace.db');
    $stmt = $db->prepare('SELECT repo_path FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($row = $result->fetchArray()) {
        return $row['repo_path'];
    }
    return null;
}

function logout() {
    session_destroy();
}
