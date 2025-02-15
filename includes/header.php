<header class="main-header">
    <div class="user-info">
        <?php if (isLoggedIn()): ?>
            Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Logout</button>
            </form>
        <?php endif; ?>
    </div>
    <nav class="main-nav">
        <a href="index.php">Home</a>
        <?php if (isLoggedIn()): ?>
            <a href="index.php?my_repos=1">My Repositories</a>
        <?php endif; ?>
    </nav>
</header>
