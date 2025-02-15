<?php
require_once 'database.php';
require_once 'auth.php';

function getRepositoryInfo($username, $repoName) {
    $db = Database::getInstance();

    $stmt = $db->prepare('
        SELECT r.*, u.username, u.repo_path
        FROM repositories r
        JOIN users u ON r.user_id = u.id
        WHERE u.username = :username AND r.name = :name
    ');

    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':name', $repoName, SQLITE3_TEXT);

    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function canAccessRepository($repoInfo) {
    if (!$repoInfo) return false;

    // Allow access if:
    // 1. User is the owner
    // 2. Repository is public
    return isLoggedIn() && (
        $_SESSION['user_id'] === $repoInfo['user_id'] ||
        $repoInfo['is_public']
    );
}

function getRepoPath($username, $repoName) {
    return "/Users/fultonbrowne/code/users/$username/$repoName";
}

function listUserRepositories($userId) {
    $db = Database::getInstance();

    $stmt = $db->prepare('
        SELECT * FROM repositories
        WHERE user_id = :user_id
        ORDER BY name
    ');

    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);

    $repos = [];
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $repos[] = $row;
    }
    return $repos;
}

function listPublicRepositories() {
    $db = Database::getInstance();

    $result = $db->query('
        SELECT r.*, u.username
        FROM repositories r
        JOIN users u ON r.user_id = u.id
        WHERE r.is_public = 1
        ORDER BY r.last_updated DESC
    ');

    $repos = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $repos[] = $row;
    }
    return $repos;
}

function updateRepository($userId, $repoName, $isPublic, $description) {
    $db = Database::getInstance();

    $stmt = $db->prepare('
        UPDATE repositories
        SET is_public = :is_public,
            description = :description,
            last_updated = CURRENT_TIMESTAMP
        WHERE user_id = :user_id AND name = :name
    ');

    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':name', $repoName, SQLITE3_TEXT);
    $stmt->bindValue(':is_public', $isPublic ? 1 : 0, SQLITE3_INTEGER);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);

    return $stmt->execute() !== false;
}
