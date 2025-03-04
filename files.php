<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/repository.php';

$repo = $_GET['repo'] ?? null;
$username = $_GET['user'] ?? $_SESSION['username'] ?? null;
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

// Check if the repository has any commits
$hasCommits = trim(shell_exec("cd " . escapeshellarg($repoPath) . " && git rev-list --count HEAD") || '') > 0;

if ($hasCommits) {
    // Get current commit if not specified
    if (!$commit) {
        $commit = trim(shell_exec("cd " . escapeshellarg($repoPath) . " && git rev-parse HEAD"));
    }

    // Validate commit hash
    if (!preg_match('/^[a-f0-9]+$/', $commit)) {
        die("Invalid commit hash");
    }
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

    <?php include 'includes/repo-header.php'; ?>

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
            <?php if ($hasCommits): ?>
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
            <?php else: ?>
                <p>This repository has no commits yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
