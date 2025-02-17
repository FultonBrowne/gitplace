<div class="repository-nav">
    <h1><?php echo htmlspecialchars($repo); ?></h1>
    <nav>
        <a href="files.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Files</a>
        <a href="commits.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Commits</a>
        <a href="issues.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Issues</a>
        <a href="patches.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>" class="active">Patches</a>
        <?php if ($_SESSION['user_id'] === $repoInfo['user_id']): ?>
            <a href="settings.php?<?php echo http_build_query(['repo' => $repo]); ?>">Settings</a>
        <?php endif; ?>
    </nav>
</div>
