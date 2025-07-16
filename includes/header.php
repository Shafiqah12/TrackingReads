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

    <link rel="stylesheet" href="/TrackingReads/style.css" /> <!-- Corrected path to style.css based on our discussion -->
    <!-- You might also need Font Awesome for Google icon if not already included elsewhere -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<header>
    <nav>
      <div class="logo">
        <a href="/TrackingReads/index.php"> <!-- Changed to absolute path for consistency -->
            <img src="/TrackingReads/img/your-logo.png" alt="Logo" class="header-logo"> <!-- Adjust path to your logo -->
            TrackingReads
        </a>
      </div>

    <button class="hamburger-menu" aria-label="Toggle navigation">
        <span class="line"></span>
        <span class="line"></span>
        <span class="line"></span>
    </button>

    <ul class="nav-links">
        <li><a href="/TrackingReads/dashboard.php">Dashboard</a></li>
        <!-- NEW EBOOK LIBRARY LINKS START HERE -->
        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
            <li><a href="/TrackingReads/index.php">Ebook Library</a></li> <!-- Link to the main library search page -->
            <li><a href="/TrackingReads/admin/add_ebook.php">Add New Ebook</a></li>
        <?php endif; ?>
        <!-- NEW EBOOK LIBRARY LINKS END HERE -->

        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
            <?php if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin"): ?>
                <li><a href="/TrackingReads/admin/admin-profile.php">Admin Profile</a></li>
                <!-- You might also want to add a link to import_excel_to_db.php here for admins -->
                <li><a href="/TrackingReads/admin/import_excel_to_db.php">Import Ebooks</a></li>
            <?php else: /* This is for regular users */ ?>
                <li><a href="/TrackingReads/profile.php">User Profile</a></li>
                <li><a href="/TrackingReads/mynotes.php">My Notes</a></li>
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