<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/repository.php';

requireLogin();

$repo = $_GET['repo'] ?? null;
$username = $_GET['user'] ?? $_SESSION['username'];
$issueId = $_GET['issue'] ?? null;

if (!$repo) {
    header('Location: index.php');
    exit;
}

// Get repository info
$repoInfo = getRepositoryInfo($username, $repo);
if (!$repoInfo || !canAccessRepository($repoInfo)) {
    die("Access denied");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();

    switch ($_POST['action']) {
        case 'create_issue':
            $stmt = $db->prepare('
                INSERT INTO issues (repository_id, title, description, created_by, assigned_to)
                VALUES (:repo_id, :title, :description, :created_by, :assigned_to)
            ');

            $stmt->bindValue(':repo_id', $repoInfo['id'], SQLITE3_INTEGER);
            $stmt->bindValue(':title', $_POST['title'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $_POST['description'], SQLITE3_TEXT);
            $stmt->bindValue(':created_by', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':assigned_to', $_POST['assigned_to'] ?: null, SQLITE3_INTEGER);

            $stmt->execute();
            header('Location: issues.php?' . http_build_query(['repo' => $repo, 'user' => $username]));
            exit;

        case 'add_comment':
            $stmt = $db->prepare('
                INSERT INTO issue_comments (issue_id, user_id, comment)
                VALUES (:issue_id, :user_id, :comment)
            ');

            $stmt->bindValue(':issue_id', $_POST['issue_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':comment', $_POST['comment'], SQLITE3_TEXT);

            $stmt->execute();
            header('Location: issues.php?' . http_build_query([
                'repo' => $repo,
                'user' => $username,
                'issue' => $_POST['issue_id']
            ]));
            exit;

        case 'update_status':
            if ($_SESSION['user_id'] === $repoInfo['user_id']) {
                $stmt = $db->prepare('
                    UPDATE issues
                    SET status = :status, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :issue_id AND repository_id = :repo_id
                ');

                $stmt->bindValue(':status', $_POST['status'], SQLITE3_TEXT);
                $stmt->bindValue(':issue_id', $_POST['issue_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':repo_id', $repoInfo['id'], SQLITE3_INTEGER);

                $stmt->execute();
                header('Location: issues.php?' . http_build_query([
                    'repo' => $repo,
                    'user' => $username,
                    'issue' => $_POST['issue_id']
                ]));
                exit;
            }
            break;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($repo); ?> - Issues</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="repository-nav">
        <h1><?php echo htmlspecialchars($repo); ?></h1>
        <nav>
            <a href="files.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Files</a>
            <a href="commits.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Commits</a>
            <a href="issues.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>" class="active">Issues</a>
            <?php if ($_SESSION['user_id'] === $repoInfo['user_id']): ?>
                <a href="settings.php?<?php echo http_build_query(['repo' => $repo]); ?>">Settings</a>
            <?php endif; ?>
        </nav>
    </div>

    <?php if ($issueId): // Single issue view
        $db = Database::getInstance();

        // Get issue details
        $stmt = $db->prepare('
            SELECT i.*,
                   c.username as creator_name,
                   a.username as assignee_name
            FROM issues i
            JOIN users c ON i.created_by = c.id
            LEFT JOIN users a ON i.assigned_to = a.id
            WHERE i.id = :id AND i.repository_id = :repo_id
        ');

        $stmt->bindValue(':id', $issueId, SQLITE3_INTEGER);
        $stmt->bindValue(':repo_id', $repoInfo['id'], SQLITE3_INTEGER);

        $issue = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$issue) {
            die("Issue not found");
        }

        // Get comments
        $stmt = $db->prepare('
            SELECT c.*, u.username
            FROM issue_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.issue_id = :issue_id
            ORDER BY c.created_at ASC
        ');

        $stmt->bindValue(':issue_id', $issueId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        ?>
        <div class="issue-detail">
            <h2><?php echo htmlspecialchars($issue['title']); ?></h2>
            <div class="issue-meta">
                <span class="status <?php echo $issue['status']; ?>">
                    <?php echo ucfirst($issue['status']); ?>
                </span>
                Created by <?php echo htmlspecialchars($issue['creator_name']); ?>
                on <?php echo date('Y-m-d H:i', strtotime($issue['created_at'])); ?>
                <?php if ($issue['assignee_name']): ?>
                    • Assigned to <?php echo htmlspecialchars($issue['assignee_name']); ?>
                <?php endif; ?>
            </div>

            <div class="issue-description">
                <?php echo nl2br(htmlspecialchars($issue['description'])); ?>
            </div>

            <?php if ($_SESSION['user_id'] === $repoInfo['user_id']): ?>
                <form method="post" class="status-update">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>">
                    <select name="status">
                        <option value="open" <?php echo $issue['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="closed" <?php echo $issue['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                    <button type="submit">Update Status</button>
                </form>
            <?php endif; ?>

            <div class="comments">
                <h3>Comments</h3>
                <?php while ($comment = $result->fetchArray(SQLITE3_ASSOC)): ?>
                    <div class="comment">
                        <div class="comment-meta">
                            <?php echo htmlspecialchars($comment['username']); ?>
                            commented on
                            <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                        </div>
                        <div class="comment-content">
                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>

                <form method="post" class="add-comment">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>">
                    <textarea name="comment" required placeholder="Add a comment..."></textarea>
                    <button type="submit">Add Comment</button>
                </form>
            </div>
        </div>
    <?php else: // Issues list view ?>
        <div class="issues-list">
            <form method="post" class="create-issue">
                <input type="hidden" name="action" value="create_issue">
                <h3>Create New Issue</h3>
                <input type="text" name="title" required placeholder="Issue title">
                <textarea name="description" required placeholder="Issue description"></textarea>
                <select name="assigned_to">
                    <option value="">Unassigned</option>
                    <?php
                    $users = $db->query('SELECT id, username FROM users ORDER BY username');
                    while ($user = $users->fetchArray(SQLITE3_ASSOC)) {
                        echo '<option value="' . $user['id'] . '">' .
                             htmlspecialchars($user['username']) . '</option>';
                    }
                    ?>
                </select>
                <button type="submit">Create Issue</button>
            </form>

            <h3>Issues</h3>
            <?php
            $stmt = $db->prepare('
                SELECT i.*,
                       c.username as creator_name,
                       a.username as assignee_name
                FROM issues i
                JOIN users c ON i.created_by = c.id
                LEFT JOIN users a ON i.assigned_to = a.id
                WHERE i.repository_id = :repo_id
                ORDER BY i.created_at DESC
            ');

            $stmt->bindValue(':repo_id', $repoInfo['id'], SQLITE3_INTEGER);
            $result = $stmt->execute();

            while ($issue = $result->fetchArray(SQLITE3_ASSOC)):
            ?>
                <div class="issue <?php echo $issue['status']; ?>">
                    <h4>
                        <a href="issues.php?<?php echo http_build_query([
                            'repo' => $repo,
                            'user' => $username,
                            'issue' => $issue['id']
                        ]); ?>">
                            <?php echo htmlspecialchars($issue['title']); ?>
                        </a>
                    </h4>
                    <div class="issue-meta">
                        #<?php echo $issue['id']; ?>
                        opened by <?php echo htmlspecialchars($issue['creator_name']); ?>
                        on <?php echo date('Y-m-d', strtotime($issue['created_at'])); ?>
                        <?php if ($issue['assignee_name']): ?>
                            • Assigned to <?php echo htmlspecialchars($issue['assignee_name']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</body>
</html>
