<?php
class IssueManager {
    private $db;

    public function __construct() {
        $this->db = new SQLite3('gitplace.db');
    }

    public function createIssue($repoId, $title, $description, $createdBy, $assignedTo = null) {
        $stmt = $this->db->prepare('
            INSERT INTO issues
            (repository_id, title, description, created_by, assigned_to)
            VALUES
            (:repo_id, :title, :description, :created_by, :assigned_to)
        ');

        $stmt->bindValue(':repo_id', $repoId, SQLITE3_INTEGER);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':created_by', $createdBy, SQLITE3_INTEGER);
        $stmt->bindValue(':assigned_to', $assignedTo, SQLITE3_INTEGER);

        return $stmt->execute();
    }

    public function getIssues($repoId) {
        $stmt = $this->db->prepare('
            SELECT i.*,
                   c.username as creator_name,
                   a.username as assignee_name
            FROM issues i
            JOIN users c ON i.created_by = c.id
            LEFT JOIN users a ON i.assigned_to = a.id
            WHERE i.repository_id = :repo_id
            ORDER BY i.created_at DESC
        ');

        $stmt->bindValue(':repo_id', $repoId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $issues = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $issues[] = $row;
        }
        return $issues;
    }

    public function getIssue($issueId) {
        $stmt = $this->db->prepare('
            SELECT i.*,
                   c.username as creator_name,
                   a.username as assignee_name
            FROM issues i
            JOIN users c ON i.created_by = c.id
            LEFT JOIN users a ON i.assigned_to = a.id
            WHERE i.id = :issue_id
        ');

        $stmt->bindValue(':issue_id', $issueId, SQLITE3_INTEGER);
        return $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    }

    public function updateIssueStatus($issueId, $status) {
        $stmt = $this->db->prepare('
            UPDATE issues
            SET status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :issue_id
        ');

        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':issue_id', $issueId, SQLITE3_INTEGER);
        return $stmt->execute();
    }

    public function addComment($issueId, $userId, $comment) {
        $stmt = $this->db->prepare('
            INSERT INTO issue_comments
            (issue_id, user_id, comment)
            VALUES
            (:issue_id, :user_id, :comment)
        ');

        $stmt->bindValue(':issue_id', $issueId, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':comment', $comment, SQLITE3_TEXT);
        return $stmt->execute();
    }

    public function getComments($issueId) {
        $stmt = $this->db->prepare('
            SELECT c.*, u.username
            FROM issue_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.issue_id = :issue_id
            ORDER BY c.created_at ASC
        ');

        $stmt->bindValue(':issue_id', $issueId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $comments = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $comments[] = $row;
        }
        return $comments;
    }
}
