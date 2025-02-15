<?php
require_once 'auth.php';
require_once 'db_init.php';
require_once 'repository.php';

// Initialize database if needed
initDatabase();

// Initialize repository manager
$repoManager = new Repository();

// Handle login/register/logout
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'login':
            if (login($_POST['username'], $_POST['password'])) {
                header('Location: index.php');
                exit;
            }
            $error = "Invalid login credentials";
            break;

        case 'register':
            if (register($_POST['username'], $_POST['password'])) {
                header('Location: index.php?msg=registered');
                exit;
            }
            $error = "Registration failed";
            break;

        case 'logout':
            logout();
            header('Location: index.php');
            exit;

        case 'update_repo':
            if (isLoggedIn()) {
                $repoManager->updateRepository(
                    $_SESSION['user_id'],
                    $_POST['repo_name'],
                    isset($_POST['is_public']),
                    $_POST['description']
                );
                header('Location: index.php?repo=' . urlencode($_POST['repo_name']));
                exit;
            }
            break;
    }
}

// Get the base directory for the current user
$baseDir = isLoggedIn() ? getCurrentUserRepoPath() : null;

// If not logged in, show login/register form
if (!isLoggedIn()) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Git Browser - Login</title>
        <link rel="stylesheet" type="text/css" href="style.css">
    </head>
    <body>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="auth-forms">
            <div class="login-form">
                <h2>Login</h2>
                <form method="post">
                    <input type="hidden" name="action" value="login">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Login</button>
                </form>
            </div>

            <div class="register-form">
                <h2>Register</h2>
                <form method="post">
                    <input type="hidden" name="action" value="register">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Register</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Function to list Git repositories
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

// Function to get the latest commit hash
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
    <div class="user-info">
        Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="logout">
            <button type="submit">Logout</button>
        </form>
    </div>

<?php
if (isset($_GET['repo'])) {
    $repo = $_GET['repo'];
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $repo)) {
            die("Invalid repository name.");
        }

        // Check if we're viewing someone else's repository
        $ownerUsername = isset($_GET['user']) ? $_GET['user'] : $_SESSION['username'];

        // Get the owner's user ID and repo path
        $db = new SQLite3('gitplace.db');
        $stmt = $db->prepare('SELECT id, repo_path FROM users WHERE username = :username');
        $stmt->bindValue(':username', $ownerUsername, SQLITE3_TEXT);
        $result = $stmt->execute();
        $owner = $result->fetchArray(SQLITE3_ASSOC);

        if (!$owner) {
            die("User not found.");
        }

        $repoPath = realpath($owner['repo_path'] . DIRECTORY_SEPARATOR . $repo);
        if (!$repoPath) {
            die("Repository not found.");
        }

        // Get repository info
        $repoInfo = $repoManager->getRepositoryInfo($owner['id'], $repo);

        // Check access permissions
        if (!$repoManager->canAccessRepository(
            isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
            $owner['id'],
            $repo
        )) {
            die("Access denied.");
        }

    // File view for a specific commit and file
    if (isset($_GET['commit']) && isset($_GET['file'])) {
        $commit = $_GET['commit'];
        $file = $_GET['file'];

        // Validate commit hash
        if (!preg_match('/^[a-f0-9]+$/', $commit)) {
            die("Invalid commit hash.");
        }

        // Validate file path
        if (!preg_match('/^[a-zA-Z0-9_\-\.\s\/]+$/', $file)) {
            die("Invalid file path.");
        }

        // Get file contents
        $command = "cd " . escapeshellarg($repoPath) . " && git show " . escapeshellarg($commit . ":" . $file);
        $contents = shell_exec($command);
        ?>
        <h1>File: <?php echo htmlspecialchars($file); ?></h1>
        <h2>Commit: <?php echo htmlspecialchars($commit); ?></h2>
        <pre class="file-contents"><?php echo htmlspecialchars($contents); ?></pre>
        <p>
            <?php
            $backLink = "?repo=" . urlencode($repo) . "&commit=" . urlencode($commit);
            if (isset($_GET['path'])) {
                $backLink .= "&path=" . urlencode($_GET['path']);
            }
            ?>
            <a href="<?php echo $backLink; ?>">Back to file list</a>
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
                echo "<a href='?repo=" . urlencode($repo) . "&commit=" . urlencode($hash) . "'>" .
                     htmlspecialchars($line) . "</a>\n";
            }
        }
        ?>
        </pre>
        <p><a href="?repo=<?php echo urlencode($repo); ?>">Back to repository</a></p>
        <?php
    }
    // File tree view
    else {
        $commit = isset($_GET['commit']) ? $_GET['commit'] : getLatestCommitHash($repoPath);
        if (!preg_match('/^[a-f0-9]+$/', $commit)) {
            die("Invalid commit hash.");
        }

        $currentPath = isset($_GET['path']) ? $_GET['path'] : '';
        if (!preg_match('/^[a-zA-Z0-9_\-\.\s\/]*$/', $currentPath)) {
            die("Invalid path.");
        }
        if (strpos($currentPath, '..') !== false) {
            die("Invalid path.");
        }
        ?>
        <h1>Repository: <?php echo htmlspecialchars($repo); ?></h1>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $repoInfo['user_id']): ?>
        <div class="repo-settings">
            <h3>Repository Settings</h3>
            <form method="post">
                <input type="hidden" name="action" value="update_repo">
                <input type="hidden" name="repo_name" value="<?php echo htmlspecialchars($repo); ?>">

                <label>
                    <input type="checkbox" name="is_public" <?php echo $repoInfo['is_public'] ? 'checked' : ''; ?>>
                    Public Repository
                </label>

                <label>
                    Description:
                    <textarea name="description"><?php echo htmlspecialchars($repoInfo['description']); ?></textarea>
                </label>

                <button type="submit">Update Settings</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="current-view">
            <h2>Current Commit: <?php echo htmlspecialchars($commit); ?></h2>
            <p><a href="?repo=<?php echo urlencode($repo); ?>&commits=1">View Commits</a></p>
        </div>

        <div class="file-tree">
            <h3>Files<?php echo ($currentPath ? " in " . htmlspecialchars($currentPath) : " in repository root"); ?></h3>
            <?php if ($currentPath !== ''): ?>
                <div class="breadcrumbs">
                    <a href="?repo=<?php echo urlencode($repo); ?>&commit=<?php echo urlencode($commit); ?>">root</a>
                    <?php
                    $pathParts = explode('/', $currentPath);
                    $currentPathBuildup = '';
                    foreach ($pathParts as $part) {
                        $currentPathBuildup .= ($currentPathBuildup ? '/' : '') . $part;
                        echo ' / ';
                        echo '<a href="?repo=' . urlencode($repo) .
                             '&commit=' . urlencode($commit) .
                             '&path=' . urlencode($currentPathBuildup) .
                             '">' . htmlspecialchars($part) . '</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php
            $cmd = "cd " . escapeshellarg($repoPath) . " && git ls-tree ";
            if ($currentPath !== '') {
                $cmd .= escapeshellarg($commit . ":" . $currentPath);
            } else {
                $cmd .= escapeshellarg($commit);
            }
            $tree = shell_exec($cmd);

            if ($tree) {
                echo "<ul>";
                foreach(explode("\n", trim($tree)) as $line) {
                    if (preg_match('/^(\d+)\s+(\w+)\s+([a-f0-9]+)\s+(.+)$/', $line, $matches)) {
                        $mode = $matches[1];
                        $type = $matches[2];
                        $hash = $matches[3];
                        $name = $matches[4];

                        $fullPath = ($currentPath !== '') ? $currentPath . "/" . $name : $name;

                        if ($type === "tree") {
                            echo "<li><a href='?repo=" . urlencode($repo) .
                                 "&commit=" . urlencode($commit) .
                                 "&path=" . urlencode($fullPath) .
                                 "'>" . htmlspecialchars($name) . "/</a></li>";
                        } else if ($type === "blob") {
                            echo "<li><a href='?repo=" . urlencode($repo) .
                                 "&commit=" . urlencode($commit) .
                                 "&file=" . urlencode($fullPath) .
                                 "'>" . htmlspecialchars($name) . "</a></li>";
                        }
                    }
                }
                echo "</ul>";
            } else {
                echo "<p>No files found in this directory.</p>";
            }
            ?>
        </div>
        <p><a href="index.php">Back to repository list</a></p>
        <?php
    }
} else {
    // Register any new repositories found
    $repoManager->scanAndRegisterRepositories($_SESSION['user_id'], $baseDir);

    // List all repositories
    $repos = listGitRepos($baseDir);
    $publicRepos = $repoManager->listPublicRepositories();
    ?>
    <h1>Your Repositories</h1>
    <ul>
        <?php foreach ($repos as $repo): ?>
            <li>
                <a href="?repo=<?php echo urlencode($repo); ?>">
                    <?php echo htmlspecialchars($repo); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <h1>Public Repositories</h1>
    <ul>
        <?php foreach ($publicRepos as $repo): ?>
            <li>
                <a href="?user=<?php echo urlencode($repo['username']); ?>&repo=<?php echo urlencode($repo['name']); ?>">
                    <?php echo htmlspecialchars($repo['username'] . '/' . $repo['name']); ?>
                </a>
                <?php if ($repo['description']): ?>
                    <p class="repo-description"><?php echo htmlspecialchars($repo['description']); ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}
?>
</body>
</html>
