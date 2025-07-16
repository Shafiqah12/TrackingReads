<?php
// edit-profile.php
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// If an admin tries to access this page, redirect them to their specific edit page
if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin") {
    header("location: admin/edit-admin-profile.php");
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';

$username = $_SESSION['username'] ?? '';
$email = '';
$profile_picture_path = '/TrackingReads/img/user.jpg'; // Default path

if (isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];

    // Fetch current email and profile picture from the database
    // Use 'profile_picture' column name if that's what's in your DB
    $sql = "SELECT email, profile_picture FROM users WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($db_email, $db_profile_picture);
                $stmt->fetch();
                $email = $db_email;
                if (!empty($db_profile_picture)) {
                    $profile_picture_path = htmlspecialchars($db_profile_picture);
                }
            }
        }
        $stmt->close();
    }
}
$conn->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container content">
    <h2>Edit Profile</h2>
    <p>Here you can update your profile information and picture.</p>

    <form action="process-profile-edit.php" method="POST" enctype="multipart/form-data">
        <div class="profile-avatar-section" style="text-align: center; margin-bottom: 20px;">
            <img src="<?php echo $profile_picture_path; ?>" alt="User Profile Picture" class="profile-avatar-large">
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
        <a href="profile.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>