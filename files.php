<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/repository.php';

requireLogin();

$repo = $_GET['repo'] ?? null;
$username = $_GET['user'] ?? $_SESSION['username'];
$commit = $_GET['commit'] ?? null;
$path = $_GET['path'] ?? '';

if (!$repo) {
    header('Location: dashboard.php');
    exit;
}

// Validate path to prevent directory traversal
if (strpos($path, '..') !== false) {
    die("Invalid path");
}

// Get repository info
$repoInfo = getRepositoryInfo($username, $repo);
if (!$repoInfo || !canAccessRepository($repoInfo)) {
    die("Access denied");
}

$repoPath = getRepoPath($username, $repo);

// Get current commit if not specified
if (!$commit) {
    $commit = trim(shell_exec("cd " . escapeshellarg($repoPath) . " && git rev-parse HEAD"));
}

// Validate commit hash
if (!preg_match('/^[a-f0-9]+$/', $commit)) {
    die("Invalid commit hash");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($repo); ?> - Files</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="repository-nav">
        <h1><?php echo htmlspecialchars($repo); ?></h1>
        <nav>
            <a href="files.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>" class="active">Files</a>
            <a href="commits.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Commits</a>
            <a href="issues.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Issues</a>
            <a href="patches.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Pull Requests</a>
            <?php if ($_SESSION['user_id'] === $repoInfo['user_id']): ?>
                <a href="settings.php?<?php echo http_build_query(['repo' => $repo]); ?>">Settings</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="file-browser">
        <?php if ($path): ?>
            <div class="breadcrumbs">
                <a href="files.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username, 'commit' => $commit]); ?>">root</a>
                <?php
                $parts = explode('/', $path);
                $currentPath = '';
                foreach ($parts as $part) {
                    $currentPath .= ($currentPath ? '/' : '') . $part;
                    echo ' / ';
                    echo '<a href="files.php?' . http_build_query([
                        'repo' => $repo,
                        'user' => $username,
                        'commit' => $commit,
                        'path' => $currentPath
                    ]) . '">' . htmlspecialchars($part) . '</a>';
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="file-list">
            <?php
            $cmd = "cd " . escapeshellarg($repoPath) . " && git ls-tree ";
            if ($path) {
                $cmd .= escapeshellarg($commit . ":" . $path);
            } else {
                $cmd .= escapeshellarg($commit);
            }

            $tree = shell_exec($cmd);
            if ($tree) {
                foreach(explode("\n", trim($tree)) as $line) {
                    if (preg_match('/^(\d+)\s+(\w+)\s+([a-f0-9]+)\s+(.+)$/', $line, $matches)) {
                        $type = $matches[2];
                        $name = $matches[4];
                        $fullPath = ($path ? $path . '/' : '') . $name;

                        echo "<div class='" . ($type === 'tree' ? 'directory' : 'file') . "'>";
                        if ($type === 'tree') {
                            echo "<a href='files.php?" . http_build_query([
                                'repo' => $repo,
                                'user' => $username,
                                'commit' => $commit,
                                'path' => $fullPath
                            ]) . "'>" . htmlspecialchars($name) . "/</a>";
                        } else {
                            echo "<a href='view.php?" . http_build_query([
                                'repo' => $repo,
                                'user' => $username,
                                'commit' => $commit,
                                'file' => $fullPath
                            ]) . "'>" . htmlspecialchars($name) . "</a>";
                        }
                        echo "</div>";
                    }
                }
            } else {
                echo "<p>No files found in this directory.</p>";
            }
            ?>
        </div>
    </div>
</body>
</html>
