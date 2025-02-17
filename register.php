<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

// Initialize database if needed
initDatabase();

// Handle registration
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    if (register($_POST['username'], $_POST['password'])) {
        header('Location: dashboard.php?msg=registered');
        exit;
    }
    $error = "Registration failed";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Register - GitPlace</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="auth-form">
        <h2>Register</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="register">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button class="btn-primary" type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>
