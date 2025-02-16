<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/repository.php';

requireLogin();

$repo = $_GET['repo'] ?? null;
$username = $_GET['user'] ?? $_SESSION['username'];
$commit = $_GET['commit'] ?? null;
$file = $_GET['file'] ?? null;

if (!$repo || !$commit || !$file) {
    header('Location: dashboard.php');
    exit;
}

// Validate commit hash
if (!preg_match('/^[a-f0-9]+$/', $commit)) {
    die("Invalid commit hash");
}

// Validate file path
if (!preg_match('/^[a-zA-Z0-9_\-\.\s\/]+$/', $file) || strpos($file, '..') !== false) {
    die("Invalid file path");
}

// Get repository info
$repoInfo = getRepositoryInfo($username, $repo);
if (!$repoInfo || !canAccessRepository($repoInfo)) {
    die("Access denied");
}

$repoPath = getRepoPath($username, $repo);

// Get file contents
$command = "cd " . escapeshellarg($repoPath) .
           " && git show " . escapeshellarg($commit . ":" . $file);
$contents = shell_exec($command);

// Get file extension for syntax highlighting
$extension = pathinfo($file, PATHINFO_EXTENSION);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($file); ?> - <?php echo htmlspecialchars($repo); ?></title>
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
            <?php if ($_SESSION['user_id'] === $repoInfo['user_id']): ?>
                <a href="settings.php?<?php echo http_build_query(['repo' => $repo]); ?>">Settings</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="file-view">
        <div class="file-info">
            <h2><?php echo htmlspecialchars($file); ?></h2>
            <div class="commit-info">
                Showing content at commit <?php echo htmlspecialchars(substr($commit, 0, 7)); ?>
            </div>
        </div>

        <div class="file-content">
            <?php if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif'])): ?>
                <img src="raw.php?<?php echo http_build_query([
                    'repo' => $repo,
                    'user' => $username,
                    'commit' => $commit,
                    'file' => $file
                ]); ?>" alt="<?php echo htmlspecialchars($file); ?>">
            <?php else: ?>
                <pre><code><?php echo htmlspecialchars($contents); ?></code></pre>
            <?php endif; ?>
        </div>

        <div class="file-actions">
            <a href="files.php?<?php echo http_build_query([
                'repo' => $repo,
                'user' => $username,
                'commit' => $commit,
                'path' => dirname($file)
            ]); ?>">Back to file list</a>
        </div>
    </div>
</body>
</html>
