<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

// Initialize database if needed
initDatabase();

// Handle login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if (login($_POST['username'], $_POST['password'])) {
        header('Location: dashboard.php');
        exit;
    }
    $error = "Invalid login credentials";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login - GitPlace</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="auth-form">
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button class="btn-primary" type="submit">Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>
