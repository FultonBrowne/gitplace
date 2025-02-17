<div class="repository-nav">
    <h1><?php echo htmlspecialchars($repo); ?></h1>
    <div class="reponav">
        <a href="files.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Files</a>
        <a href="commits.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Commits</a>
        <a href="issues.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>">Issues</a>
        <a href="patches.php?<?php echo http_build_query(['repo' => $repo, 'user' => $username]); ?>" class="active">Patches</a>
        <?php if ($_SESSION['user_id'] === $repoInfo['user_id']): ?>
            <a href="settings.php?<?php echo http_build_query(['repo' => $repo]); ?>">Settings</a>
        <?php endif; ?>
    </div>
    <!-- <div class="clone-url">
        <label for="clone-url">Clone URL:</label>
        <input type="text" id="clone-url" value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/git-serve.php?repo=' . urlencode($repo) . '&user=' . urlencode($username); ?>" readonly>
        <button onclick="copyCloneUrl()">Copy</button>
    </div> -->
</div>

<script>
function copyCloneUrl() {
    var copyText = document.getElementById("clone-url");
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices
    document.execCommand("copy");
}
</script>
