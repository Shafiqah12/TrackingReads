<?php
// profile.php
session_start();

// Ensure db_connect.php is correctly included.
require_once __DIR__ . '/includes/db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /TrackingReads/login.php");
    exit;
}

// Initialize variables
$username = '';
$email = '';
$role = ''; // Initialize role variable (corrected from user_role)
$last_login = '';
$profilePictureSrc = '/TrackingReads/img/user.jpg'; // Default user avatar

// Prepare a select statement to get user details including role and profile_picture
// IMPORTANT: 'role' is used here to match your database column name.
$sql = "SELECT username, email, role, last_login, profile_picture FROM users WHERE id = ?";

if ($stmt = $conn->prepare($sql)) {
    // Bind parameters
    $stmt->bind_param("i", $_SESSION["user_id"]);

    // Attempt to execute the prepared statement
    if ($stmt->execute()) {
        // Store result
        $stmt->store_result();

        // Check if user exists
        if ($stmt->num_rows == 1) {
            // Bind result variables
            // IMPORTANT: Bind to $db_role to match the 'role' column.
            $stmt->bind_result($db_username, $db_email, $db_role, $db_last_login, $db_profile_picture);
            $stmt->fetch();

            // Assign fetched values to variables
            $username = $db_username;
            $email = $db_email;
            $role = $db_role; // Assign the fetched role
            $last_login = $db_last_login;

            // Set profile picture source, use default if DB value is empty
            if (!empty($db_profile_picture)) {
                $profilePictureSrc = htmlspecialchars($db_profile_picture);
            }

            // Update session with fetched data to ensure it's fresh and available across pages
            $_SESSION["username"] = $username;
            $_SESSION["email"] = $email;
            $_SESSION["user_role"] = $role; // Store as user_role in session for consistency across system
            $_SESSION["last_login"] = $last_login;
            $_SESSION["profile_picture"] = $profilePictureSrc;

        } else {
            // User not found in database, redirect to login
            header("location: /TrackingReads/login.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong executing the query. Please try again later.";
        error_log("Profile page: Error executing user data query: " . $stmt->error);
    }
    $stmt->close();
} else {
    echo "Oops! Something went wrong preparing the query. Please try again later.";
    error_log("Profile page: Error preparing user data query: " . $conn->error);
}
$conn->close(); // Close database connection

require_once __DIR__ . '/includes/header.php';
?>

<div class="container content">
    <div class="profile-header" style="text-align: center; margin-bottom: 20px;">
        <img src="<?php echo $profilePictureSrc; ?>" alt="User Profile Picture" class="profile-avatar">
        <p>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong>!</p>
        <p>Your Role: <strong><?php echo htmlspecialchars($role); ?></strong></p> </div>
    <hr>
    <h3>Profile Information</h3>
    <p>Username: <strong><?php echo htmlspecialchars($username); ?></strong></p>
    <p>Email: <strong><?php echo htmlspecialchars($email); ?></strong></p>
    <p>Last Login: <strong><?php echo htmlspecialchars($last_login); ?></strong></p>

    <div class="profile-actions">
        <a href="/TrackingReads/edit-profile.php" class="btn btn-primary">Edit Profile</a>
        <a href="/TrackingReads/change-password.php" class="btn btn-warning">Change Password</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>