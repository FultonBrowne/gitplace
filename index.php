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
        <div class="repository-list">
            <h1>Your Repositories</h1>
            <?php
            $userRepos = listUserRepositories($_SESSION['user_id']);
            if (empty($userRepos)): ?>
                <p>You don't have any repositories yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($userRepos as $repo): ?>
                        <li>
                            <a href="files.php?repo=<?php echo urlencode($repo['name']); ?>">
                                <?php echo htmlspecialchars($repo['name']); ?>
                            </a>
                            <?php if ($repo['description']): ?>
                                <p class="repo-description">
                                    <?php echo htmlspecialchars($repo['description']); ?>
                                </p>
                            <?php endif; ?>
                            <span class="repo-visibility">
                                <?php echo $repo['is_public'] ? 'Public' : 'Private'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h1>Public Repositories</h1>
            <?php
            $publicRepos = listPublicRepositories();
            if (empty($publicRepos)): ?>
                <p>No public repositories available.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($publicRepos as $repo): ?>
                        <li>
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
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
