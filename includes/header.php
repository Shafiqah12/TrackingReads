<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TrackingReads Repository System</title>

    <link rel="stylesheet" href="/TrackingReads/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<header>
    <nav>
        <div class="logo">
            <a href="/TrackingReads/index.php">TrackingReads
            </a>
        </div>

        <button class="hamburger-menu" aria-label="Toggle navigation">
            <span class="line"></span>
            <span class="line"></span>
            <span class="line"></span>
        </button>

        <ul class="nav-links">
            <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                <li><a href="/TrackingReads/index.php">Ebook Library</a></li>

                <?php
                // Define roles that can add/manage ebooks. If 'clerk' is a specific role, add it here.
                // Assuming 'admin' also has these permissions.
                $allowed_to_manage_ebooks = (isset($_SESSION["user_role"]) && ($_SESSION["user_role"] === "admin" || $_SESSION["user_role"] === "clerk"));
                ?>

                <?php if ($allowed_to_manage_ebooks): ?>
                    <li><a href="/TrackingReads/admin/add_ebook.php">Add New Ebook</a></li>
                <?php endif; ?>

                <?php if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin"): ?>
                    <li><a href="/TrackingReads/admin/admin-profile.php">Clerk Profile</a></li>
                <?php elseif (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "manager"): ?>
                    <li><a href="/TrackingReads/admin/admin-profile.php">Managaer Profile</a></li>
                    <li><a href="/TrackingReads/admin/admin-profile.php">Manage Clerk</a></li>
                    <li><a href="/TrackingReads/admin/admin-profile.php">Manager User</a></li>
                <?php elseif (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "user"): // Assuming 'user' is the role for regular users ?>
                    <li><a href="/TrackingReads/profile.php">User Profile</a></li>
                    <li><a href="/TrackingReads/mybooks.php">My Books</a></li>
                    <li><a href="/TrackingReads/wishlist.php">Wishlist</a></li>
                <?php endif; ?>
                <li><a href="/TrackingReads/logout.php">Logout</a></li>
            <?php else: /* Not logged in */ ?>
                <li><a href="/TrackingReads/login.php">Login</a></li>
                <li><a href="/TrackingReads/register.php">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<main>