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

$repoPath = getRepoPath($username, $repo);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($repo); ?> - Commits</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="repository-nav">
        <h1><?php echo htmlspecialchars($repo); ?></h1>
        <nav>
            <a href="files.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Files</a>
            <a href="commits.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>" class="active">Commits</a>
            <a href="issues.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Issues</a>
            <?php if ($_SESSION['user_id'] === $repoInfo['user_id']): ?>
                <a href="settings.php?<?php echo http_build_query(['repo' => $repo]); ?>">Settings</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="commit-history">
        <?php
        $command = "cd " . escapeshellarg($repoPath) .
                   " && git log --pretty=format:'%H|%h|%an|%ar|%s'";
        $log = shell_exec($command);

        if ($log) {
            foreach(explode("\n", $log) as $line) {
                $parts = explode('|', $line);
                if (count($parts) === 5) {
                    list($fullHash, $shortHash, $author, $date, $message) = $parts;
                    ?>
                    <div class="commit">
                        <div class="commit-message">
                            <a href="files.php?<?php echo http_build_query([
                                'repo' => $repo,
                                'user' => $username,
                                'commit' => $fullHash
                            ]); ?>"><?php echo htmlspecialchars($message); ?></a>
                        </div>
                        <div class="commit-meta">
                            <?php echo htmlspecialchars($shortHash); ?> by
                            <?php echo htmlspecialchars($author); ?> •
                            <?php echo htmlspecialchars($date); ?>
                        </div>
                    </div>
                    <?php
                }
            }
        } else {
            echo "<p>No commits found in this repository.</p>";
        }
        ?>
    </div>
</body>
</html>
