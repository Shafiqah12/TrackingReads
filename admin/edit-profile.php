<?php
// TrackingReads/admin/edit-profile.php (Selepas dinamakan semula)
session_start();

// Pastikan laporan ralat dihidupkan untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Tentukan peranan yang dibenarkan untuk mengakses halaman edit profil ini
// Ini membenarkan Admin, Manager, Clerk, dan User biasa untuk mengedit profil mereka.
$allowedRoles = ['admin', 'manager', 'clerk', 'user'];

// Semak jika pengguna TIDAK log masuk, atau jika peranan mereka TIDAK dibenarkan.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || (isset($_SESSION["user_role"]) && !in_array($_SESSION["user_role"], $allowedRoles))) {
    header("location: ../login.php"); // Redirect ke halaman login jika akses tidak dibenarkan
    exit;
}

require_once '../includes/db_connect.php';

$username = $_SESSION['username'] ?? ''; // Ambil username dari session
$email = ''; // Initialize email variable
$profile_picture_path = ''; // Initialize profile picture path

// Ambil e-mel semasa pengguna dan gambar profil dari pangkalan data
if (isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];

    $sql = "SELECT email, profile_picture FROM users WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($db_email, $db_profile_picture);
                $stmt->fetch();
                $email = $db_email;
                // Tentukan laluan gambar profil
                // Guna gambar dari DB jika ada, jika tidak, gunakan gambar lalai.
                // Laluan ini MESTI disesuaikan dengan struktur folder anda.
                // Contoh: Jika gambar profil disimpan dalam 'TrackingReads/uploads/', gunakan '/TrackingReads/uploads/'
                // Jika gambar lalai disimpan dalam 'TrackingReads/img/', gunakan '../img/'
                $default_image_web_path = '../img/default-profile.jpg'; // Laluan gambar profil lalai generik
                $profile_picture_path = !empty($db_profile_picture) ? htmlspecialchars($db_profile_picture) : htmlspecialchars($default_image_web_path);
            } else {
                // Log ralat jika user ID tidak ditemui dalam DB (jarang berlaku jika sesi betul)
                error_log("Edit Profile: User ID " . $user_id . " not found in database.");
            }
        } else {
            error_log("Edit Profile: Error executing query: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Edit Profile: Error preparing query: " . $conn->error);
    }
}
$conn->close();

// Sertakan header (Pastikan laluan betul jika header.php berada di includes/)
require_once '../includes/header.php';
?>

<div class="container content">
    <h2>Edit Profile (<?php echo ucfirst(htmlspecialchars($_SESSION["user_role"] ?? 'User')); ?>)</h2>
    <p>Here you can update your profile information and picture.</p>

    <form action="process-profile-edit.php" method="POST" enctype="multipart/form-data">
        <div class="profile-avatar-section" style="text-align: center; margin-bottom: 20px;">
            <img src="<?php echo $profile_picture_path; ?>" alt="Profile Picture" class="profile-avatar-large">
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

<?php
// Sertakan footer (Pastikan laluan betul jika footer.php berada di includes/)
require_once '../includes/footer.php';
?>