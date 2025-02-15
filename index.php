<?php
require_once 'auth.php';
require_once 'db_init.php';
require_once 'repository.php';
require_once 'issues.php';

// Initialize database if needed
initDatabase();

// Initialize managers
$repoManager = new Repository();
$issueManager = new IssueManager();

// Handle login/register/logout and other actions
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

        case 'create_issue':
            if (isLoggedIn()) {
                $issueManager->createIssue(
                    $_POST['repo_id'],
                    $_POST['title'],
                    $_POST['description'],
                    $_SESSION['user_id'],
                    !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null
                );
                header('Location: ?repo=' . urlencode($_GET['repo']) . '&issues=1');
                exit;
            }
            break;

        case 'update_status':
            if (isLoggedIn()) {
                $issueManager->updateIssueStatus($_POST['issue_id'], $_POST['status']);
                header('Location: ?repo=' . urlencode($_GET['repo']) . '&issues=1');
                exit;
            }
            break;

        case 'add_comment':
            if (isLoggedIn()) {
                $issueManager->addComment(
                    $_POST['issue_id'],
                    $_SESSION['user_id'],
                    $_POST['comment']
                );
                header('Location: ?repo=' . urlencode($_GET['repo']) . '&issues=1&issue=' . $_POST['issue_id']);
                exit;
            }
            break;
    }
}

// Get the base directory for the current user
$baseDir = isLoggedIn() ? getCurrentUserRepoPath() : null;

// Helper Functions
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

    <?php if (!isLoggedIn()): ?>
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
    <?php else: ?>
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
                $_SESSION['user_id'],
                $owner['id'],
                $repo
            )) {
                die("Access denied.");
            }

            // Repository Navigation Menu
            ?>
            <div class="repository-nav">
                <h1>Repository: <?php echo htmlspecialchars($repo); ?></h1>
                <nav>
                    <a href="?repo=<?php echo urlencode($repo); ?>">Files</a>
                    <a href="?repo=<?php echo urlencode($repo); ?>&commits=1">Commits</a>
                    <a href="?repo=<?php echo urlencode($repo); ?>&issues=1">Issues</a>
                </nav>
            </div>

            <?php
            // Repository Settings (for owner only)
            if ($_SESSION['user_id'] === $owner['id']): ?>
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
            <?php
                    // Issues Section
                    if (isset($_GET['issues'])) {
                        if (isset($_GET['issue'])) {
                            // Single Issue View
                            $issue = $issueManager->getIssue($_GET['issue']);
                            $comments = $issueManager->getComments($_GET['issue']);
                            ?>
                            <div class="issue-detail">
                                <h2><?php echo htmlspecialchars($issue['title']); ?></h2>
                                <div class="issue-meta">
                                    Created by: <?php echo htmlspecialchars($issue['creator_name']); ?><br>
                                    Status: <?php echo htmlspecialchars($issue['status']); ?><br>
                                    <?php if ($issue['assignee_name']): ?>
                                        Assigned to: <?php echo htmlspecialchars($issue['assignee_name']); ?>
                                    <?php endif; ?>
                                </div>

                                <div class="issue-description">
                                    <?php echo nl2br(htmlspecialchars($issue['description'])); ?>
                                </div>

                                <?php if ($_SESSION['user_id'] === $owner['id']): ?>
                                <form method="post" class="status-update">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>">
                                    <select name="status">
                                        <option value="open" <?php echo $issue['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="closed" <?php echo $issue['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                    <button type="submit">Update Status</button>
                                </form>
                                <?php endif; ?>

                                <h3>Comments</h3>
                                <div class="comments">
                                    <?php foreach ($comments as $comment): ?>
                                        <div class="comment">
                                            <div class="comment-meta">
                                                <?php echo htmlspecialchars($comment['username']); ?> commented on
                                                <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                                            </div>
                                            <div class="comment-content">
                                                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <form method="post" class="add-comment">
                                    <input type="hidden" name="action" value="add_comment">
                                    <input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>">
                                    <textarea name="comment" required placeholder="Add a comment..."></textarea>
                                    <button type="submit">Add Comment</button>
                                </form>
                            </div>
                            <?php
                        } else {
                            // Issues List View
                            $issues = $issueManager->getIssues($repoInfo['id']);
                            ?>
                            <div class="issues-list">
                                <h2>Issues</h2>

                                <form method="post" class="create-issue">
                                    <input type="hidden" name="action" value="create_issue">
                                    <input type="hidden" name="repo_id" value="<?php echo $repoInfo['id']; ?>">
                                    <input type="text" name="title" required placeholder="Issue title">
                                    <textarea name="description" required placeholder="Issue description"></textarea>
                                    <select name="assigned_to">
                                        <option value="">Unassigned</option>
                                        <?php
                                        $users = $db->query('SELECT id, username FROM users ORDER BY username');
                                        while ($user = $users->fetchArray(SQLITE3_ASSOC)) {
                                            echo '<option value="' . $user['id'] . '">' .
                                                 htmlspecialchars($user['username']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button type="submit">Create Issue</button>
                                </form>

                                <div class="issues">
                                    <?php foreach ($issues as $issue): ?>
                                        <div class="issue <?php echo $issue['status']; ?>">
                                            <h3>
                                                <a href="?repo=<?php echo urlencode($repo); ?>&issues=1&issue=<?php echo $issue['id']; ?>">
                                                    <?php echo htmlspecialchars($issue['title']); ?>
                                                </a>
                                            </h3>
                                            <div class="issue-meta">
                                                #<?php echo $issue['id']; ?>
                                                opened by <?php echo htmlspecialchars($issue['creator_name']); ?>
                                                on <?php echo date('Y-m-d', strtotime($issue['created_at'])); ?>
                                                <?php if ($issue['assignee_name']): ?>
                                                    • Assigned to <?php echo htmlspecialchars($issue['assignee_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    // Commit History View
                            else if (isset($_GET['commits'])) {
                                ?>
                                <div class="commit-history">
                                    <h2>Commit History</h2>
                                    <div class="commits">
                                    <?php
                                    $command = "cd " . escapeshellarg($repoPath) . " && git log --pretty=format:'%h - %an, %ar : %s'";
                                    $log = shell_exec($command);
                                    foreach(explode("\n", $log) as $line) {
                                        if (preg_match('/^([a-f0-9]+) - /', $line, $matches)) {
                                            $hash = $matches[1];
                                            echo "<div class='commit'>";
                                            echo "<a href='?repo=" . urlencode($repo) . "&commit=" . urlencode($hash) . "'>" .
                                                 htmlspecialchars($line) . "</a>";
                                            echo "</div>";
                                        }
                                    }
                                    ?>
                                    </div>
                                    <p><a href="?repo=<?php echo urlencode($repo); ?>">Back to repository</a></p>
                                </div>
                                <?php
                            }
                            // File View
                            else if (isset($_GET['commit']) && isset($_GET['file'])) {
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
                                <div class="file-view">
                                    <h2>File: <?php echo htmlspecialchars($file); ?></h2>
                                    <h3>Commit: <?php echo htmlspecialchars($commit); ?></h3>
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
                                </div>
                                <?php
                            }
                            // File Tree View
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
                                <div class="file-browser">
                                    <h2>Files<?php echo ($currentPath ? " in " . htmlspecialchars($currentPath) : ""); ?></h2>

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

                                    <div class="file-tree">
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
                                                        echo "<li class='directory'><a href='?repo=" . urlencode($repo) .
                                                             "&commit=" . urlencode($commit) .
                                                             "&path=" . urlencode($fullPath) .
                                                             "'>" . htmlspecialchars($name) . "/</a></li>";
                                                    } else if ($type === "blob") {
                                                        echo "<li class='file'><a href='?repo=" . urlencode($repo) .
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
                                </div>
                                <?php
                            }
                        } else {
                            // Repository List View
                            $repoManager->scanAndRegisterRepositories($_SESSION['user_id'], $baseDir);
                            $repos = listGitRepos($baseDir);
                            $publicRepos = $repoManager->listPublicRepositories();
                            ?>
                            <div class="repository-list">
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
                            </div>
                            <?php
                        }
                    endif;
                    ?>
                    </body>
                    </html>
