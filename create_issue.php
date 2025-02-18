<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/repository.php';

requireLogin();

$repo = $_GET['repo'] ?? null;
$username = $_GET['user'] ?? $_SESSION['username'];

if (!$repo) {
    header('Location: dashboard.php');
    exit;
}

// Get repository info
$repoInfo = getRepositoryInfo($username, $repo);
if (!$repoInfo || !canAccessRepository($repoInfo)) {
    die("Access denied");
}

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Issue - <?php echo htmlspecialchars($repo); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/repo-header.php'; ?>

    <div class="create-issue">
        <h2>Create New Issue</h2>
        <form method="post">
            <input type="hidden" name="action" value="create_issue">
            <input type="text" name="title" required placeholder="Issue title">
            <textarea name="description" required placeholder="Issue description"></textarea>
            <input type="hidden" name="assigned_to" value="Unassigned">
            <button class="btn-primary" type="submit">Create Issue</button>
        </form>
    </div>
</body>
</html>
