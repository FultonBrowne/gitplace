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
    // 1. Repository is public
    // 2. User is the owner
    return $repoInfo['is_public'] || (isLoggedIn() && $_SESSION['user_id'] === $repoInfo['user_id']);
}

function getRepoPath($username, $repoName) {
    return "/Users/fultonbrowne/code/users/$username/$repoName";
}

function listUserRepositories($userId, $username) {
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
    // check if any repos have been added to the user's directory but not to the database
    $dir = "/Users/fultonbrowne/code/users/$username";
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (!in_array($file, array_column($repos, 'name'))) {
            // create an empty repository
            $stmt = $db->prepare('
                INSERT INTO repositories (name, user_id, is_public)
                VALUES (:name, :user_id, :is_public)
            ');
            $stmt->bindValue(':name', $file, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':is_public', false, SQLITE3_INTEGER);
            $stmt->execute();
            $repos[] = ['name' => $file, 'user_id' => $userId, 'is_public' => false];
        }
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
