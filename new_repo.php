<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/repository.php';

if (!isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $repoName = trim($_POST['repo_name']);
    $isPublic = isset($_POST['is_public']) ? true : false;
    $description = trim($_POST['description']);

    if (empty($repoName)) {
        $msg = "Repository name cannot be empty.";
    } else {
        // Check if the repository already exists for this user
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM repositories WHERE user_id = :user_id AND name = :name");
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':name', $repoName, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            $msg = "Repository already exists.";
        } else {
            // Insert the new repository into the database
            $stmt = $db->prepare('
                INSERT INTO repositories (name, user_id, is_public, description, last_updated)
                VALUES (:name, :user_id, :is_public, :description, CURRENT_TIMESTAMP)
            ');
            $stmt->bindValue(':name', $repoName, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':is_public', $isPublic ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $result = $stmt->execute();

            if ($result) {
                // Optionally, create the user's repository directory on the filesystem.
                $repoPath = "/Users/fultonbrowne/code/users/" . $_SESSION['username'] . '/' . $repoName;
                if (!is_dir($repoPath)) {
                    mkdir($repoPath, 0775, true);
                }
                // initialize repository
                $initCmd = "cd $repoPath && git init";
                exec($initCmd);
                header('Location: dashboard.php?msg=repo_created');
                exit;
            } else {
                $msg = "Failed to create repository.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Create New Repository</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="new-repo-form">
        <h1>Create New Repository</h1>
        <?php if ($msg): ?>
            <p class="error-msg"><?php echo htmlspecialchars($msg); ?></p>
        <?php endif; ?>
        <form method="post" action="new_repo.php">
            <label for="repo_name">Repository Name:</label>
            <input type="text" id="repo_name" name="repo_name" required>
            <br>
            <label for="description">Description:</label>
            <textarea id="description" name="description"></textarea>
            <br>
            <label for="is_public">Public Repository:</label>
            <input type="checkbox" id="is_public" name="is_public" value="1">
            <br>
            <br>
            <button class="btn-primary" type="submit">Create Repository</button>
        </form>
    </div>
</body>
</html>
