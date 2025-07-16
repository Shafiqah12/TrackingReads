<?php
// admin/change-password.php
// This page allows administrators to change their password.

session_start();

// Check if the user is NOT logged in, or if they are logged in but are NOT an admin.
// Redirect to login if not authorized.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || (isset($_SESSION["user_role"]) && $_SESSION["user_role"] !== "admin")) {
    header("location: ../login.php"); // Go up one level to find login.php
    exit;
}

// Include the header file for consistent site layout.
require_once '../includes/header.php'; // Go up one level to find the includes folder
?>

    <h2>Change Password</h2>
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
            <a href="admin-profile.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

<?php
// Include the footer file.
require_once '../includes/footer.php'; // Go up one level to find the includes folder
?>