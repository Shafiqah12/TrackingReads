<?php
// admin/dashboard.php
// Ini adalah dashboard untuk pentadbir dan pengurus.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php';

// Tentukan peranan yang dibenarkan untuk mengakses halaman dashboard ini
// Admin dan Manager dibenarkan. Clerk tidak dibenarkan mengakses dashboard ini.
$allowedRoles = ['manager'];

// Semak jika pengguna TIDAK log masuk ATAU TIDAK mempunyai peranan yang dibenarkan
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], $allowedRoles)) {
    // Jika tidak log masuk atau tidak dibenarkan, arahkan ke halaman log masuk.
    header("Location: ../login.php");
    exit;
}

// Dapatkan peranan pengguna semasa untuk kawalan akses peringkat ciri
$currentUserRole = $_SESSION['user_role'];

// -----------------------------------------------------------------------------
// Fetch Dynamic Data for Dashboard Overview:
// -----------------------------------------------------------------------------
$total_users = 0;
$total_ebooks = 0;

if ($conn) {
    // Kira jumlah pengguna
    $stmt_users = $conn->prepare("SELECT COUNT(id) FROM users");
    if ($stmt_users) {
        $stmt_users->execute();
        $stmt_users->bind_result($total_users);
        $stmt_users->fetch();
        $stmt_users->close();
    } else {
        error_log("Failed to prepare users count statement: " . $conn->error);
    }

    // Kira jumlah ebook
    $stmt_ebooks = $conn->prepare("SELECT COUNT(id) FROM ebooks");
    if ($stmt_ebooks) {
        $stmt_ebooks->execute();
        $stmt_ebooks->bind_result($total_ebooks);
        $stmt_ebooks->fetch();
        $stmt_ebooks->close();
    } else {
        error_log("Failed to prepare ebooks count statement: " . $conn->error);
    }
} else {
    error_log("Database connection not established in admin/dashboard.php");
}

require_once '../includes/header.php';
?>

<div class="main-content-area">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>
    <p>This is your dashboard. Here you can generate report and manage clerk or users.</p>
    <p>Your role: <strong><?php echo htmlspecialchars($currentUserRole); ?></strong></p>

    <div class="dashboard-content">
        <h3>Overview</h3>
        <p>Total Registered Users: <strong><?php echo $total_users; ?></strong></p>
        <p>Total Ebooks in Library: <strong><?php echo $total_ebooks; ?></strong></p>
        <hr>
        <h3>Actions</h3>
        <div class="admin-actions-buttons">
            <!-- Add New Ebook: Dibenarkan untuk Admin, Manager, Clerk -->
            <?php if (in_array($currentUserRole, ['manager'])): ?>
                <a href="richest_ebooks_report.php" class="btn btn-primary">Generate Report</a>
            <?php endif; ?>

            <!-- Contoh: Manage Users: Dibenarkan untuk Admin sahaja -->
            <?php if (in_array($currentUserRole, ['manager'])): ?>
                <a href="manage_users.php" class="btn btn-primary">Manage Clerk/Users</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once '../includes/footer.php';
?>
