<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

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
        <p>You are already logged in. Go to your <a href="dashboard.php">dashboard</a>.</p>
    <?php endif; ?>
</body>
</html>
