<?php
// admin/change-password.php
// This page allows administrators, managers, clerks, and regular users to change their password.

session_start();

// Define allowed roles for this page.
// Added 'user', 'clerk', 'manager' to the allowed roles.
$allowedRoles = ['admin', 'manager', 'clerk', 'user'];

// Check if the user is NOT logged in, or if their role is NOT in the allowed roles.
// Redirect to login if not authorized.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["user_role"], $allowedRoles)) {
    // Log for debugging (optional, but good practice)
    error_log("Access Denied: User not logged in or role not allowed for change-password.php. Role: " . ($_SESSION['user_role'] ?? 'N/A'));
    header("location: ../login.php"); // Go up one level to find login.php
    exit;
}

// Include the header file for consistent site layout.
require_once '../includes/header.php'; // Go up one level to find the includes folder
?>

    <div class="container content"> <h2>Change Password</h2>
        <p>Please enter your current password and your new password.</p>

        <?php
        // Display error message if set
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']); // Clear the message after displaying
        }
        // Display success message if set
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']); // Clear the message after displaying
        }
        ?>

        <div class="password-change-form">
            <form action="process-change-password.php" method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Change Password</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div> <?php
// Include the footer file.
require_once '../includes/footer.php'; // Go up one level to find the includes folder
?>