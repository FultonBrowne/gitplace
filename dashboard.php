<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/repository.php';

// Initialize database if needed
initDatabase();

// Handle login/register/logout
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'login':
            if (login($_POST['username'], $_POST['password'])) {
                header('Location: dashboard.php');
                exit;
            }
            $error = "Invalid login credentials";
            break;

        case 'register':
            if (register($_POST['username'], $_POST['password'])) {
                header('Location: dashboard.php?msg=registered');
                exit;
            }
            $error = "Registration failed";
            break;

        case 'logout':
            logout();
            header('Location: dashboard.php');
            exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GitPlace</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <?php if (!isLoggedIn()): ?>
        You are not supposed to be here lmao.
    <?php else: ?>
        <div class="repository-list">
            <h1>Your Repositories</h1>
                <form action="new_repo.php" method="get" style="padding-bottom: 24px;">
                    <button type="submit" class="btn-primary">New Repository</button>
                </form>
            <?php
            $userRepos = listUserRepositories($_SESSION['user_id'], $_SESSION['username']);
            if (empty($userRepos)): ?>
                <p>You don't have any repositories yet.</p>
            <?php else: ?>
                <div class="grid-container">
                    <?php foreach ($userRepos as $repo): ?>
                        <div class="repo-card">
                            <a href="files.php?repo=<?php echo urlencode($repo['name']); ?>">
                                <?php echo htmlspecialchars($repo['name']); ?>
                            </a>
                            <?php if ($repo['description']): ?>
                                <p class="repo-description">
                                    <?php echo htmlspecialchars($repo['description']); ?>
                                </p>
                            <?php endif; ?>
                            <span class="repo-visibility <?php echo $repo['is_public'] ? '' : 'private'; ?>">
                                <?php echo $repo['is_public'] ? 'Public' : 'Private'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- <h1>Public Repositories</h1>
            <?php
            $publicRepos = listPublicRepositories();
            if (empty($publicRepos)): ?>
                <p>No public repositories available.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($publicRepos as $repo): ?>
                        <li class="repo-card">
                            <a href="files.php?user=<?php echo urlencode($repo['username']); ?>&repo=<?php echo urlencode($repo['name']); ?>">
                                <?php echo htmlspecialchars($repo['username'] . '/' . $repo['name']); ?>
                            </a>
                            <?php if ($repo['description']): ?>
                                <p class="repo-description">
                                    <?php echo htmlspecialchars($repo['description']); ?>
                                </p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?> -->
        </div>
    <?php endif; ?>
</body>
</html>
