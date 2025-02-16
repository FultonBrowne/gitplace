<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/repository.php';

requireLogin();

$db = Database::getInstance();

// Get current user and their settings
$stmt = $db->prepare('
    SELECT username, git_email, git_name
    FROM users
    WHERE id = :user_id
');
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'update_git_config':
            $newEmail = filter_var($_POST['git_email'], FILTER_VALIDATE_EMAIL);
            $newName = trim($_POST['git_name']);

            if (!$newEmail) {
                $error = "Please enter a valid email address.";
            } elseif (empty($newName)) {
                $error = "Please enter a name.";
            } else {
                $stmt = $db->prepare('
                    UPDATE users
                    SET git_email = :git_email,
                        git_name = :git_name
                    WHERE id = :user_id
                ');

                $stmt->bindValue(':git_email', $newEmail, SQLITE3_TEXT);
                $stmt->bindValue(':git_name', $newName, SQLITE3_TEXT);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

                if ($stmt->execute()) {
                    $success = "Git configuration updated successfully.";
                    $user['git_email'] = $newEmail;
                    $user['git_name'] = $newName;
                } else {
                    $error = "Failed to update git configuration.";
                }
            }
            break;

        case 'add_ssh_key':
            $keyName = trim($_POST['key_name']);
            $publicKey = trim($_POST['public_key']);

            if (empty($keyName) || empty($publicKey)) {
                $error = "Both key name and public key are required.";
                break;
            }

            // Validate SSH key format
            if (!preg_match('/^(ssh-rsa|ssh-ed25519)\s+[A-Za-z0-9+\/]+[=]{0,3}\s*(.*)/', $publicKey)) {
                $error = "Invalid SSH key format.";
                break;
            }

            // Generate fingerprint
            $tempFile = tempnam(sys_get_temp_dir(), 'ssh_');
            file_put_contents($tempFile, $publicKey);
            $fingerprint = trim(shell_exec("ssh-keygen -lf " . escapeshellarg($tempFile) . " | awk '{print $2}'"));
            unlink($tempFile);

            if (empty($fingerprint)) {
                $error = "Failed to generate key fingerprint.";
                break;
            }

            $stmt = $db->prepare('
                INSERT INTO ssh_keys (user_id, name, public_key, fingerprint)
                VALUES (:user_id, :name, :public_key, :fingerprint)
            ');

            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':name', $keyName, SQLITE3_TEXT);
            $stmt->bindValue(':public_key', $publicKey, SQLITE3_TEXT);
            $stmt->bindValue(':fingerprint', $fingerprint, SQLITE3_TEXT);

            if ($stmt->execute()) {
                $success = "SSH key added successfully.";
            } else {
                $error = "Failed to add SSH key.";
            }
            break;

        case 'delete_ssh_key':
            $keyId = filter_input(INPUT_POST, 'key_id', FILTER_VALIDATE_INT);

            if ($keyId) {
                $stmt = $db->prepare('
                    DELETE FROM ssh_keys
                    WHERE id = :id AND user_id = :user_id
                ');

                $stmt->bindValue(':id', $keyId, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

                if ($stmt->execute()) {
                    $success = "SSH key deleted successfully.";
                } else {
                    $error = "Failed to delete SSH key.";
                }
            }
            break;

        case 'change_password':
            // ... (password change code remains the same)
            break;
    }
}

// Get SSH keys
$stmt = $db->prepare('
    SELECT id, name, fingerprint, created_at, last_used_at
    FROM ssh_keys
    WHERE user_id = :user_id
    ORDER BY created_at DESC
');
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();

$sshKeys = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $sshKeys[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Settings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="settings-container">
        <h1>User Settings</h1>

        <?php if (isset($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="settings-section">
            <h2>Git Configuration</h2>
            <form method="post" class="settings-form">
                <input type="hidden" name="action" value="update_git_config">

                <div class="form-group">
                    <label for="git_email">Git Email</label>
                    <input type="email" name="git_email" id="git_email"
                           value="<?php echo htmlspecialchars($user['git_email'] ?? ''); ?>" required>
                    <p class="help-text">
                        This email will be used for your git commits.
                    </p>
                </div>

                <div class="form-group">
                    <label for="git_name">Git Name</label>
                    <input type="text" name="git_name" id="git_name"
                           value="<?php echo htmlspecialchars($user['git_name'] ?? ''); ?>" required>
                    <p class="help-text">
                        This name will appear in your git commits.
                    </p>
                </div>

                <button type="submit" class="btn-primary">Update Git Config</button>
            </form>
        </section>

        <!-- SSH Keys section -->
        <section class="settings-section">
            <h2>SSH Keys</h2>

            <div class="ssh-keys-list">
                <?php if (!empty($sshKeys)): ?>
                    <table class="ssh-keys-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Fingerprint</th>
                                <th>Added</th>
                                <th>Last used</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sshKeys as $key): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($key['name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($key['fingerprint']); ?></code></td>
                                    <td><?php echo date('Y-m-d', strtotime($key['created_at'])); ?></td>
                                    <td>
                                        <?php
                                        echo $key['last_used_at']
                                            ? date('Y-m-d', strtotime($key['last_used_at']))
                                            : 'Never';
                                        ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_ssh_key">
                                            <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                            <button type="submit" class="btn-danger btn-small"
                                                    onclick="return confirm('Are you sure you want to delete this SSH key?')">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No SSH keys added yet.</p>
                <?php endif; ?>
            </div>

            <form method="post" class="settings-form add-ssh-key">
                <input type="hidden" name="action" value="add_ssh_key">

                <div class="form-group">
                    <label for="key_name">Key Name</label>
                    <input type="text" name="key_name" id="key_name" required
                           placeholder="e.g., Work Laptop">
                </div>

                <div class="form-group">
                    <label for="public_key">Public Key</label>
                    <textarea name="public_key" id="public_key" required
                              placeholder="Begins with 'ssh-rsa' or 'ssh-ed25519'"></textarea>
                    <p class="help-text">
                        Paste your public key content here. It should start with 'ssh-rsa' or 'ssh-ed25519'.
                    </p>
                </div>

                <button type="submit" class="btn-primary">Add SSH Key</button>
            </form>
        </section>

        <section class="settings-section">
                    <h2>Change Password</h2>
                    <form method="post" class="settings-form">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" name="current_password" id="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" name="new_password" id="new_password" required
                                   minlength="6">
                            <p class="help-text">
                                Must be at least 6 characters long.
                            </p>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" required>
                        </div>

                        <button type="submit" class="btn-primary">Change Password</button>
                    </form>
                </section>
    </div>
</body>
</html>
