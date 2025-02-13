<?php
// Set the directory where your Git repositories are stored
$baseDir = '/Users/fultonbrowne/code/';

/**
 * Returns an array of repository names found in $baseDir.
 */
function listGitRepos($baseDir) {
    $repos = [];
    foreach (scandir($baseDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $repoPath = $baseDir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($repoPath) && is_dir($repoPath . DIRECTORY_SEPARATOR . '.git')) {
            $repos[] = $entry;
        }
    }
    return $repos;
}

/**
 * Gets the latest commit hash for a repository
 */
function getLatestCommitHash($repoPath) {
    $command = "cd " . escapeshellarg($repoPath) . " && git rev-parse HEAD";
    return trim(shell_exec($command));
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Git Browser</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
<?php

if (isset($_GET['repo'])) {
    $repo = $_GET['repo'];
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $repo)) {
        die("Invalid repository name.");
    }

    $repoPath = realpath($baseDir . DIRECTORY_SEPARATOR . $repo);

    // View specific commit contents
    if (isset($_GET['commit']) && isset($_GET['file'])) {
        $commit = $_GET['commit'];
        $file = $_GET['file'];

        // Validate commit hash
        if (!preg_match('/^[a-f0-9]+$/', $commit)) {
            die("Invalid commit hash.");
        }

        // Validate file path
        if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $file)) {
            die("Invalid file path.");
        }

        // Get file contents for this commit
        $command = "cd " . escapeshellarg($repoPath) . " && git show " . escapeshellarg($commit . ":" . $file);
        $contents = shell_exec($command);
        ?>
        <h1>File: <?php echo htmlspecialchars($file); ?></h1>
        <h2>Commit: <?php echo htmlspecialchars($commit); ?></h2>
        <pre class="file-contents"><?php echo htmlspecialchars($contents); ?></pre>
        <p>
            <a href="?repo=<?php echo urlencode($repo); ?>&commit=<?php echo urlencode($commit); ?>">Back to file list</a>
        </p>
        <?php
    }
    // View commit history
    else if (isset($_GET['commits'])) {
        ?>
        <h1>Repository: <?php echo htmlspecialchars($repo); ?></h1>
        <h2>Commit History</h2>
        <pre>
        <?php
        $command = "cd " . escapeshellarg($repoPath) . " && git log --pretty=format:'%h - %an, %ar : %s'";
        $log = shell_exec($command);
        foreach(explode("\n", $log) as $line) {
            if (preg_match('/^([a-f0-9]+) - /', $line, $matches)) {
                $hash = $matches[1];
                echo "<a href='?repo=" . urlencode($repo) . "&commit=" . $hash . "'>" .
                     htmlspecialchars($line) . "</a>\n";
            }
        }
        ?>
        </pre>
        <p><a href="?repo=<?php echo urlencode($repo); ?>">Back to repository</a></p>
        <?php
    }
    // View files in commit
    else {
        // Get the commit hash - either from URL or latest
        $commit = isset($_GET['commit']) ? $_GET['commit'] : getLatestCommitHash($repoPath);

        if (!preg_match('/^[a-f0-9]+$/', $commit)) {
            die("Invalid commit hash.");
        }

        ?>
        <h1>Repository: <?php echo htmlspecialchars($repo); ?></h1>
        <div class="current-view">
            <h2>Current Commit: <?php echo htmlspecialchars($commit); ?></h2>
            <p><a href="?repo=<?php echo urlencode($repo); ?>&commits=1">View Commits</a></p>
        </div>

        <!-- File Tree for Current Commit -->
        <div class="file-tree">
            <h3>Files in commit</h3>
            <pre><?php
            $command = "cd " . escapeshellarg($repoPath) . " && git ls-tree -r " . escapeshellarg($commit);
            $files = shell_exec($command);
            foreach(explode("\n", $files) as $line) {
                if (preg_match('/^\d+\s+\w+\s+[a-f0-9]+\s+(.+)$/', $line, $matches)) {
                    $filename = $matches[1];
                    echo "<a href='?repo=" . urlencode($repo) .
                         "&commit=" . urlencode($commit) .
                         "&file=" . urlencode($filename) . "'>" .
                         htmlspecialchars($line) . "</a>\n";
                }
            }
            ?></pre>
        </div>
        <p><a href="index.php">Back to repository list</a></p>
        <?php
    }
} else {
    // List all repositories
    $repos = listGitRepos($baseDir);
    ?>
    <h1>Available Git Repositories</h1>
    <ul>
        <?php foreach ($repos as $repo): ?>
            <li>
                <a href="?repo=<?php echo urlencode($repo); ?>">
                    <?php echo htmlspecialchars($repo); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}
?>
</body>
</html>
