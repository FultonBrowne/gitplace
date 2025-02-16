<header class="main-header">
        <div class="logo">
            <a href="index.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-git-branch"><line x1="6" x2="6" y1="3" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg>            </a>
        </div>
        <nav class="main-nav">
            <a href="index.php">Home</a></li>
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php">My Repositories</a>
                    <a href="user_settings.php">Settings</a>
                <?php endif; ?>
        </nav>
        <div class="header-spacer"></div>
            <?php if (isLoggedIn()): ?>
                <!-- <span>Hello <?php echo htmlspecialchars($_SESSION['username']); ?></span> -->
                <form method="post" class="logout-form">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn-primary" type="submit">Logout</button>
                </form>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
</header>
