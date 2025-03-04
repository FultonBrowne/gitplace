<?php
class Database {
    private static $db = null;

    public static function getInstance() {
        if (self::$db === null) {
            self::$db = new SQLite3('gitplace.db');
            self::$db->enableExceptions(true);
        }
        return self::$db;
    }
}

function initDatabase() {
    $db = Database::getInstance();

    // Create users table
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        repo_path TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    // Create repositories table
    $db->exec('CREATE TABLE IF NOT EXISTS repositories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        is_public BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        UNIQUE(user_id, name)
    )');

    // Create issues table
    $db->exec('CREATE TABLE IF NOT EXISTS issues (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        repository_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT NOT NULL DEFAULT "open",
        created_by INTEGER NOT NULL,
        assigned_to INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (repository_id) REFERENCES repositories(id),
        FOREIGN KEY (created_by) REFERENCES users(id),
        FOREIGN KEY (assigned_to) REFERENCES users(id)
    )');

    // Create issue comments table
    $db->exec('CREATE TABLE IF NOT EXISTS issue_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        issue_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (issue_id) REFERENCES issues(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS ssh_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            public_key TEXT NOT NULL,
            fingerprint TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(user_id, fingerprint)
        )');

        $hasGitEmail = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('users') WHERE name='git_email'") > 0;
        if (!$hasGitEmail) {
            $db->exec('ALTER TABLE users ADD COLUMN git_email TEXT;');
            echo "Added git_email column\n";
        }

        // Check if git_name column exists
        $hasGitName = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('users') WHERE name='git_name'") > 0;
        if (!$hasGitName) {
            $db->exec('ALTER TABLE users ADD COLUMN git_name TEXT;');
            echo "Added git_name column\n";
        }

        $db->exec('CREATE TABLE IF NOT EXISTS patches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                repository_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                patch_content TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "open",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (repository_id) REFERENCES repositories(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )');
}
