<?php
// TrackingReads/admin/profile.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Redirect jika tidak log masuk
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login.php");
    exit;
}

// Tentukan peranan yang dibenarkan untuk mengakses halaman profil ini
$allowedRoles = ['admin', 'manager', 'clerk'];

// Semak jika peranan pengguna yang log masuk ada dalam senarai peranan yang dibenarkan
if (!isset($_SESSION["user_role"]) || !in_array($_SESSION["user_role"], $allowedRoles)) {
    // Jika peranan tidak dibenarkan, redirect ke halaman dashboard atau halaman akses ditolak
    header("location: ../dashboard.php"); // Atau ke halaman ralat 'access denied'
    exit;
}

// Pastikan laluan ke db_connect.php adalah betul
require_once '../includes/db_connect.php';

// --- Pengendalian Laluan Imej Profil ---
// Laluan gambar profil lalai (relative kepada admin/profile.php)
$defaultProfilePictureWebPath = '../img/admin.jpg'; // Sesuaikan jika laluan folder 'img' berbeza

// Tentukan sumber gambar profil sebenar
// Menggunakan ?? 'default_value' untuk mengelakkan `null` pada htmlspecialchars
$profilePictureSrc = htmlspecialchars($_SESSION["profile_picture"] ?? $defaultProfilePictureWebPath);

// Pembolehubah untuk e-mel dan log masuk terakhir
$userEmail = "N/A";
$lastLogin = "N/A";

// Ambil e-mel dan log masuk terakhir dari pangkalan data untuk pengguna semasa
if (isset($_SESSION["user_id"])) {
    $sql = "SELECT email, last_login FROM users WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $_SESSION["user_id"]);

        if ($stmt->execute()) {
            $result = $stmt->get_result(); // Gunakan get_result() untuk mengambil objek mysqli_result

            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc(); // Ambil baris sebagai array bersekutu
                $userEmail = $row['email'];
                // Format last_login, jika NULL paparkan "Never"
                $lastLogin = ($row['last_login'] !== null) ? date("Y-m-d H:i:s", strtotime($row['last_login'])) : "Never";
            } else {
                error_log("Profile page: User ID " . $_SESSION["user_id"] . " not found in database.");
            }
        } else {
            error_log("Error executing profile query: " . $stmt->error);
            echo "<p class='error'>Error fetching profile data. Please try again later.</p>";
        }
        $stmt->close();
    } else {
        error_log("Error preparing profile query: " . $conn->error);
        echo "<p class='error'>Error preparing to fetch profile data. Please try again later.</p>";
    }
}

// Tutup sambungan pangkalan data
$conn->close();

// Sertakan fail header
require_once '../includes/header.php';
?>

<div class="container">
    <h2><?php echo ucfirst(htmlspecialchars($_SESSION["user_role"])); ?> Profile</h2>

    <div class="profile-header" style="text-align: center; margin-bottom: 20px;">
        <img src="<?php echo $profilePictureSrc; ?>" alt="Profile Picture" class="profile-avatar">
        <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong>!</p>
        <p>Your Role: <strong><?php echo ucfirst(htmlspecialchars($_SESSION["user_role"])); ?></strong></p>
    </div>

    <div class="profile-details">
        <h3>Profile Information</h3>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION["username"]); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($userEmail ?? 'N/A'); ?></p>
        <p><strong>Last Login:</strong> <?php echo htmlspecialchars($lastLogin ?? 'N/A'); ?></p>
    </div>

    <?php if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin"): ?>
        <div class="admin-specific-info" style="margin-top: 20px;">
            <h4>Admin Overview</h4>
            <p>Admin-specific data like total users, system logs, etc., can go here.</p>
            <a href="manage_users.php" class="btn btn-secondary">Manage All Users</a>
        </div>
    <?php elseif (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "manager"): ?>
        <div class="manager-specific-info" style="margin-top: 20px;">
            <h4>Manager Overview</h4>
            <p>Manager-specific data like team performance, pending approvals, etc., can go here.</p>
            <a href="manage-clerks.php" class="btn btn-secondary">Manage Clerks</a>
        </div>
    <?php elseif (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "clerk"): ?>
        <div class="clerk-specific-info" style="margin-top: 20px;">
            <h4>My Contributions</h4>
            <p>Clerk-specific data like number of ebooks added, recent activities, etc., can go here.</p>
            <a href="my_ebook_uploads.php" class="btn btn-secondary">View My Ebook Uploads</a>
        </div>
    <?php endif; ?>

    <div class="profile-actions" style="margin-top: 30px;">
        <h3>Manage Account</h3>
        <a href="edit-profile.php" class="btn btn-primary">Edit Profile</a>
        <a href="change_password.php" class="btn btn-primary">Change Password</a>
    </div>

    <p style="margin-top: 30px;">This section allows <?php echo htmlspecialchars($_SESSION["user_role"] ?? 'a user'); ?> to view and manage their profile details.</p>
</div>

<?php
// Sertakan fail footer
require_once '../includes/footer.php';
?>