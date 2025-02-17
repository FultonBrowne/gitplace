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

        case 'signup':
            $file = fopen('signups.csv', 'a');
            if ($file) {
                fputcsv($file, [$_POST['email']]);
                fclose($file);
                header('Location: dashboard.php?msg=signedup');
                exit;
            }
            $error = "Signup failed";
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

    <h1>GitPlace</h1>
    The Simple git host

    Git hosts have become great, with a ton of tools and features and an increasing feature set and focus on enterprise.

    Sometimes you dont want all that. you just want to host your repos, have issues, and some simple and fun tools for collaboration. Git place scratches that itch. the interface is dead-simple and it built to use as many built in git tools as possible. With the ultimate goal of being able to use the platform without ever having to open your browser.
    <h2>What all is done?</h2>
    This is in a "built in a weekend state" but we have abasic support for issues with comments, patches, public and private repos. You will find bugs and if you do feel free to hop over to the GitPlace Repo on GitPlace.
    <h2>What will be done?</h2>
    I want to add some wiki support, better chat interface (that can plugin to IRC), a cli tool, and probably more. While its in the beta state and all these features are being added there WILL be breaking changes, bug, and very probably data loss. We also want to work to make this super self hostable with an easy set up and documentation. We want gitplace to be a tool a communtity and build around.
    <h2>What won't be done?</h2>
    We don't want this to become GitHub, this is a place for hackers and makers not for large enterprises, there is already great options for that, and we want to build something simple and fun. Features like Organization support, advanced access control, SCRUM-style projects, and a fancy UI will not be added. If a feature is designed for large enterprise and will impact the performance and experience of the platform, it will not be added.
    <h2>I want to sign up!</h2>
    Awesome! As of now it is a private beta but you should totally add your email address to the waiting list below
    <form method="post">
        <input type="hidden" name="action" value="signup">
        <input type="email" name="email" placeholder="Email" required>
        <button class="btn-primary" type="submit">Sign Up</button>
    </form>
</body>
</html>
