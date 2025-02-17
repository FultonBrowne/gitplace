<?php
require_once 'includes/database.php';
require_once 'includes/repository.php';

// Initialize database if needed
initDatabase();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Public Repositories - GitPlace</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="repository-list">
        <h1>Public Repositories</h1>
        <?php
        $publicRepos = listPublicRepositories();
        if (empty($publicRepos)): ?>
            <p>No public repositories available.</p>
            <?php else: ?>
                <div class="grid-container">
                    <?php foreach ($publicRepos as $repo): ?>
                        <div class="repo-card">
                            <a href="files.php?user=<?php echo urlencode($repo['username']); ?>&repo=<?php echo urlencode($repo['name']); ?>">
                                <?php echo htmlspecialchars($repo['username'] . '/' . $repo['name']); ?>
                            </a>
                            <?php if ($repo['description']): ?>
                                <p class="repo-description">
                                    <?php echo htmlspecialchars($repo['description']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
    </div>
</body>
</html>
