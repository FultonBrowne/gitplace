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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'submit_patch':
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $patchContent = trim($_POST['patch_content']);

            // Basic validation of patch format
            if (!preg_match('/^From [a-f0-9]+ /', $patchContent)) {
                $error = "Invalid patch format. Please use 'git format-patch'.";
                break;
            }

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
            break;

        case 'update_status':
            if ($_SESSION['user_id'] === $repoInfo['user_id']) {
                $patchId = filter_input(INPUT_POST, 'patch_id', FILTER_VALIDATE_INT);
                $status = $_POST['status'];

                $stmt = $db->prepare('
                    UPDATE patches
                    SET status = :status,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id AND repository_id = :repo_id
                ');

                $stmt->bindValue(':status', $status, SQLITE3_TEXT);
                $stmt->bindValue(':id', $patchId, SQLITE3_INTEGER);
                $stmt->bindValue(':repo_id', $repoInfo['id'], SQLITE3_INTEGER);

                if ($stmt->execute()) {
                    $success = "Patch status updated.";
                } else {
                    $error = "Failed to update patch status.";
                }
            }
            break;
    }
}

// Get patches
$stmt = $db->prepare('
    SELECT p.*, u.username
    FROM patches p
    JOIN users u ON p.user_id = u.id
    WHERE p.repository_id = :repo_id
    ORDER BY p.created_at DESC
');
$stmt->bindValue(':repo_id', $repoInfo['id'], SQLITE3_INTEGER);
$result = $stmt->execute();

$patches = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $patches[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($repo); ?> - Patches</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="repository-nav">
        <h1><?php echo htmlspecialchars($repo); ?></h1>
        <nav>
            <a href="files.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Files</a>
            <a href="commits.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Commits</a>
            <a href="issues.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Issues</a>
            <a href="patches.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>" class="active">Patches</a>
            <?php if ($_SESSION['user_id'] === $repoInfo['user_id']): ?>
                <a href="settings.php?<?php echo http_build_query(['repo' => $repo]); ?>">Settings</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="patches-container">
        <?php if (isset($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="submit-patch">
            <h2>Submit a Patch</h2>
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
        </section>

        <section class="patches-list">
            <h2>Submitted Patches</h2>
            <?php foreach ($patches as $patch): ?>
                <div class="patch <?php echo htmlspecialchars($patch['status']); ?>">
                    <div class="patch-header">
                        <h3><?php echo htmlspecialchars($patch['title']); ?></h3>
                        <div class="patch-meta">
                            Submitted by <?php echo htmlspecialchars($patch['username']); ?>
                            on <?php echo date('Y-m-d H:i', strtotime($patch['created_at'])); ?>
                            • Status: <?php echo ucfirst(htmlspecialchars($patch['status'])); ?>
                        </div>
                    </div>

                    <?php if ($patch['description']): ?>
                        <div class="patch-description">
                            <?php echo nl2br(htmlspecialchars($patch['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <div class="patch-actions">
                        <button class="btn-secondary" onclick="togglePatch(<?php echo $patch['id']; ?>)">
                            Show/Hide Patch
                        </button>

                        <?php if ($_SESSION['user_id'] === $repoInfo['user_id']): ?>
                            <div class="patch-commands">
                                <p>To apply this patch:</p>
                                <code>curl <?php echo getBaseUrl(); ?>/patch.php?id=<?php echo $patch['id']; ?> | git am</code>

                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="patch_id" value="<?php echo $patch['id']; ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="open" <?php echo $patch['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="merged" <?php echo $patch['status'] === 'merged' ? 'selected' : ''; ?>>Merged</option>
                                        <option value="rejected" <?php echo $patch['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <pre class="patch-content" id="patch-<?php echo $patch['id']; ?>" style="display: none;">
<?php echo htmlspecialchars($patch['patch_content']); ?>
                    </pre>
                </div>
            <?php endforeach; ?>
        </section>
    </div>

    <script>
    function togglePatch(id) {
        const element = document.getElementById('patch-' + id);
        element.style.display = element.style.display === 'none' ? 'block' : 'none';
    }
    </script>
</body>
</html>
