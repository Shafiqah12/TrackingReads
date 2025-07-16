<?php
// admin/edit-admin-profile.php
session_start();

// Check if the user is NOT logged in, or if they are logged in but are NOT an admin.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || (isset($_SESSION["user_role"]) && $_SESSION["user_role"] !== "admin")) {
    header("location: ../login.php");
    exit;
}

require_once '../includes/db_connect.php';

$username = $_SESSION['username'] ?? ''; // Get username from session
$email = ''; // Initialize email variable
$profile_picture_path = ''; // Initialize profile picture path

// Fetch admin's current email and profile picture from the database
if (isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];

    // The column name for role is 'role' in your DB, but session uses 'user_role'
    $sql = "SELECT email, profile_picture FROM users WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($db_email, $db_profile_picture);
                $stmt->fetch();
                $email = $db_email;
                // If a profile picture exists in DB, use it, otherwise use a default admin image.
                $profile_picture_path = !empty($db_profile_picture) ? htmlspecialchars($db_profile_picture) : '/NOTESYNC/img/admin.jpg'; // Using 'admin.jpg' as default
            }
        }
        $stmt->close();
    }
}
$conn->close();

require_once '../includes/header.php';
?>

<div class="container content">
    <h2>Edit Admin Profile</h2>
    <p>Here you can update your profile information and picture.</p>

    <form action="process-admin-profile-edit.php" method="POST" enctype="multipart/form-data">
        <div class="profile-avatar-section" style="text-align: center; margin-bottom: 20px;">
            <img src="<?php echo $profile_picture_path; ?>" alt="Admin Profile Picture" class="profile-avatar-large">
            <p>Current Profile Picture</p>
        </div>

        <div class="form-group">
            <label for="profile_picture">Upload New Profile Picture:</label>
            <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="form-control-file">
            <small class="form-text text-muted">Max file size: 2MB. Allowed formats: JPG, JPEG, PNG, GIF.</small>
        </div>

        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" class="form-control" readonly>
            <small>Username cannot be changed.</small>
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="admin-profile.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php
require_once '../includes/footer.php';
?>