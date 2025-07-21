<?php
// TrackingReads/includes/header.php
// Pastikan sesi bermula di sini jika belum bermula
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
        <a href="/TrackingReads/admin/dashboard.php" class="logo">TrackingReads</a> <button class="hamburger-menu" aria-label="Toggle navigation" id="hamburgerButton">
            <span class="line"></span>
            <span class="line"></span>
            <span class="line"></span>
        </button>

        <ul class="nav-links" id="navLinks">
            <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true):?>
                <li><a href="/TrackingReads/admin/manage-ebook.php">Ebook Library</a></li> <?php
                $allowed_to_manage_ebooks = (isset($_SESSION["user_role"]) && ($_SESSION["user_role"] === "admin" || $_SESSION["user_role"] === "clerk"));
                ?>

                <?php if ($allowed_to_manage_ebooks):?>
                    <li><a href="/TrackingReads/admin/add_ebook.php">Add New Ebook</a></li>
                <?php endif;?>

                <?php // Pautan Profil untuk setiap peranan ?>
                <?php if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "clerk"):?>
                    <li><a href="/TrackingReads/admin/profile.php">Clerk Profile</a></li>
                <?php elseif (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "manager"):?>
                    <li><a href="/TrackingReads/admin/profile.php">Manager Profile</a></li>
                    <li><a href="/TrackingReads/admin/manage_users.php">Manage Users</a></li>
                <?php elseif (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin"):?>
                    <li><a href="/TrackingReads/admin/profile.php">Admin Profile</a></li>
                    <li><a href="/TrackingReads/admin/manage_users.php">Manage Users</a></li> <?php elseif (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "user"):?>
                    <li><a href="/TrackingReads/profile.php">User Profile</a></li>
                    <li><a href="/TrackingReads/mybooks.php">My Books</a></li>
                    <li><a href="/TrackingReads/wishlist.php">Wishlist</a></li>
                <?php endif;?>
                <li><a href="/TrackingReads/logout.php">Logout</a></li>
            <?php else: /* Pengguna tidak log masuk */?>
                <li><a href="/TrackingReads/login.php">Login</a></li>
                <li><a href="/TrackingReads/register.php">Register</a></li>
            <?php endif;?>
        </ul>
    </nav>
</header>
<main>