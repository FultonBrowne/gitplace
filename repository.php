<?php
class Repository {
    private $db;

    public function __construct() {
        $this->db = new SQLite3('gitplace.db');
    }

    public function registerRepository($userId, $repoName, $isPublic = false, $description = '') {
        $stmt = $this->db->prepare('
            INSERT OR IGNORE INTO repositories
            (user_id, name, is_public, description)
            VALUES (:user_id, :name, :is_public, :description)
        ');

        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $repoName, SQLITE3_TEXT);
        $stmt->bindValue(':is_public', $isPublic ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);

        return $stmt->execute();
    }

    public function updateRepository($userId, $repoName, $isPublic, $description) {
        $stmt = $this->db->prepare('
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

        return $stmt->execute();
    }

    public function getRepositoryInfo($userId, $repoName) {
        $stmt = $this->db->prepare('
            SELECT * FROM repositories
            WHERE user_id = :user_id AND name = :name
        ');

        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $repoName, SQLITE3_TEXT);

        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function canAccessRepository($userId, $ownerId, $repoName) {
        $stmt = $this->db->prepare('
            SELECT is_public FROM repositories
            WHERE user_id = :owner_id AND name = :name
        ');

        $stmt->bindValue(':owner_id', $ownerId, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $repoName, SQLITE3_TEXT);

        $result = $stmt->execute();
        $repo = $result->fetchArray(SQLITE3_ASSOC);

        // Allow access if:
        // 1. User is the owner
        // 2. Repository is public
        return ($userId === $ownerId) || ($repo && $repo['is_public']);
    }

    public function listPublicRepositories() {
        $stmt = $this->db->prepare('
            SELECT r.*, u.username
            FROM repositories r
            JOIN users u ON r.user_id = u.id
            WHERE r.is_public = 1
            ORDER BY r.last_updated DESC
        ');

        $result = $stmt->execute();
        $repos = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $repos[] = $row;
        }
        return $repos;
    }

    public function scanAndRegisterRepositories($userId, $path) {
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $repoPath = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($repoPath) && is_dir($repoPath . DIRECTORY_SEPARATOR . '.git')) {
                $this->registerRepository($userId, $entry);
            }
        }
    }
}
