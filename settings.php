<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/repository.php';

requireLogin();

$repo = $_GET['repo'] ?? null;

if (!$repo) {
    header('Location: dashboard.php');
    exit;
}

// Get repository info
$repoInfo = getRepositoryInfo($_SESSION['username'], $repo);

// Check if user owns this repository
if (!$repoInfo || $repoInfo['user_id'] !== $_SESSION['user_id']) {
    die("Access denied");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();

    switch ($_POST['action']) {
        case 'update_settings':
            $stmt = $db->prepare('
                UPDATE repositories
                SET is_public = :is_public,
                    description = :description,
                    last_updated = CURRENT_TIMESTAMP
                WHERE id = :repo_id AND user_id = :user_id
            ');

            $stmt->bindValue(':is_public', isset($_POST['is_public']) ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':description', $_POST['description'], SQLITE3_TEXT);
            $stmt->bindValue(':repo_id', $repoInfo['id'], SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

            if ($stmt->execute()) {
                $success = "Repository settings updated successfully.";
            } else {
                $error = "Failed to update repository settings.";
            }
            break;

        case 'delete_repo':
            // Verify confirmation code
            if ($_POST['confirm_name'] === $repo) {
                $stmt = $db->prepare('
                    DELETE FROM repositories
                    WHERE id = :repo_id AND user_id = :user_id
                ');

                $stmt->bindValue(':repo_id', $repoInfo['id'], SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

                if ($stmt->execute()) {
                    // Delete the physical repository
                    $repoPath = getRepoPath($_SESSION['username'], $repo);
                    if (is_dir($repoPath)) {
                        exec('rm -rf ' . escapeshellarg($repoPath));
                    }

                    header('Location: dashboard.php?msg=repo_deleted');
                    exit;
                } else {
                    $error = "Failed to delete repository.";
                }
            } else {
                $error = "Repository name confirmation did not match.";
            }
            break;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($repo); ?> - Settings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="repository-nav">
        <h1><?php echo htmlspecialchars($repo); ?></h1>
        <nav>
            <a href="files.php?repo=<?php echo urlencode($repo); ?>">Files</a>
            <a href="commits.php?repo=<?php echo urlencode($repo); ?>">Commits</a>
            <a href="issues.php?repo=<?php echo urlencode($repo); ?>">Issues</a>
            <a href="settings.php?repo=<?php echo urlencode($repo); ?>" class="active">Settings</a>
        </nav>
    </div>

    <div class="settings-container">
        <?php if (isset($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="settings-section">
            <h2>Repository Settings</h2>
            <form method="post" class="settings-form">
                <input type="hidden" name="action" value="update_settings">

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_public"
                               <?php echo $repoInfo['is_public'] ? 'checked' : ''; ?>>
                        Public Repository
                    </label>
                    <p class="help-text">
                        Public repositories are visible to all users.
                    </p>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="3"><?php
                        echo htmlspecialchars($repoInfo['description']);
                    ?></textarea>
                    <p class="help-text">
                        A short description of your repository.
                    </p>
                </div>

                <button type="submit" class="btn-primary">Update Settings</button>
            </form>
        </section>

        <section class="settings-section danger-zone">
            <h2>Danger Zone</h2>
            <div class="danger-zone-content">
                <h3>Delete Repository</h3>
                <p>
                    Once you delete a repository, there is no going back.
                    Please be certain.
                </p>

                <form method="post" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this repository? This action cannot be undone.');">
                    <input type="hidden" name="action" value="delete_repo">

                    <div class="form-group">
                        <label for="confirm_name">
                            Please type <strong><?php echo htmlspecialchars($repo); ?></strong>
                            to confirm.
                        </label>
                        <input type="text" name="confirm_name" id="confirm_name" required
                               pattern="<?php echo preg_quote($repo, '/'); ?>"
                               title="Must match repository name exactly">
                    </div>

                    <button type="submit" class="btn-danger">
                        I understand the consequences, delete this repository
                    </button>
                </form>
            </div>
        </section>
    </div>

    <script>
    // Optional JavaScript for better UX
    document.querySelector('.delete-form').addEventListener('submit', function(e) {
        const confirmInput = this.querySelector('input[name="confirm_name"]');
        const repoName = '<?php echo addslashes($repo); ?>';

        if (confirmInput.value !== repoName) {
            e.preventDefault();
            alert('Repository name confirmation did not match.');
            return false;
        }
    });
    </script>
</body>
</html>
