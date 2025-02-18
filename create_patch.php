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
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $patchContent = trim($_POST['patch_content']);

    // Basic validation of patch format
    if (!preg_match('/^From [a-f0-9]+ /', $patchContent)) {
        $error = "Invalid patch format. Please use 'git format-patch'.";
    } else {
        $stmt = $db->prepare('
            INSERT INTO patches (repository_id, user_id, title, description, patch_content)
            VALUES (:repo_id, :user_id, :title, :description, :patch_content)
        ');

        $stmt->bindValue(':repo_id', $repoInfo['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':patch_content', $patchContent, SQLITE3_TEXT);

        if ($stmt->execute()) {
            $success = "Patch submitted successfully.";
        } else {
            $error = "Failed to submit patch.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Submit Patch - <?php echo htmlspecialchars($repo); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/repo-header.php'; ?>

    <div class="submit-patch">
        <h2>Submit a Patch</h2>
        <?php if (isset($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="help-text">
            <p>To create a patch:</p>
            <ol>
                <li>Create and commit your changes locally</li>
                <li>Run: <code>git format-patch -1 HEAD</code></li>
                <li>Copy the contents of the generated .patch file</li>
            </ol>
        </div>
        <form method="post" class="patch-form">
            <input type="hidden" name="action" value="submit_patch">

            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description"></textarea>
            </div>

            <div class="form-group">
                <label for="patch_content">Patch Content</label>
                <textarea name="patch_content" id="patch_content" required
                          class="patch-content" rows="10"></textarea>
            </div>

            <button type="submit" class="btn-primary">Submit Patch</button>
        </form>
    </div>
</body>
</html>
