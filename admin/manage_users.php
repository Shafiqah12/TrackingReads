<?php
// admin/manage-users.php
// Halaman ini membolehkan pentadbir melihat, mengedit, dan memadam rekod pengguna.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Path relative to manage-users.php

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
// Hanya Admin dibenarkan mengurus pengguna.
$allowedRoles = ['admin'];

// Semak jika pengguna TIDAK log masuk ATAU TIDAK mempunyai peranan yang dibenarkan
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], $allowedRoles)) {
    header("Location: ../login.php"); // Arahkan ke halaman log masuk jika tidak dibenarkan
    exit;
}

$errorMessage = null;
$successMessage = null;
$users = []; // Array untuk menyimpan data pengguna

// -----------------------------------------------------------------------------
// Logik untuk memadam pengguna
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id_to_delete = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    // Pastikan ID pengguna yang sah dan bukan akaun admin yang sedang log masuk
    if ($user_id_to_delete && $user_id_to_delete != $_SESSION['user_id']) {
        try {
            $sql_delete = "DELETE FROM users WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            if ($stmt_delete === false) {
                throw new Exception("Penyediaan DELETE gagal: " . $conn->error);
            }
            $stmt_delete->bind_param("i", $user_id_to_delete);
            if ($stmt_delete->execute()) {
                $successMessage = "Pengguna dengan ID " . htmlspecialchars($user_id_to_delete) . " berjaya dipadam.";
            } else {
                throw new Exception("Pelaksanaan DELETE gagal: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        } catch (Exception $e) {
            $errorMessage = "Ralat memadam pengguna: " . htmlspecialchars($e->getMessage());
        }
    } else if ($user_id_to_delete == $_SESSION['user_id']) {
        $errorMessage = "Anda tidak boleh memadam akaun anda sendiri.";
    } else {
        $errorMessage = "ID pengguna tidak sah untuk dipadam.";
    }
}

// -----------------------------------------------------------------------------
// Ambil semua pengguna dari pangkalan data untuk paparan jadual
// -----------------------------------------------------------------------------
try {
    // Tambah created_at ke dalam SELECT statement
    $sql = "SELECT id, username, email, role, created_at FROM users ORDER BY username ASC";
    $result = $conn->query($sql); // Guna query() kerana tiada parameter yang diikat

    if ($result === false) {
        throw new Exception("SQL Query Error: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        $errorMessage = "Tiada pengguna ditemui dalam pangkalan data.";
    }
} catch (Exception $e) {
    $errorMessage = "Ralat mengambil data pengguna: " . htmlspecialchars($e->getMessage());
}

require_once '../includes/header.php'; // Path relative to manage-users.php
?>

<div class="container mt-4">
    <h2>Manage Users</h2>
    <p>Here you can view, edit, or delete user accounts.</p>

    <?php if ($successMessage): ?>
        <div class="message success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="message error"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <?php if (empty($users)): ?>
            <p>No users found.</p>
        <?php else: ?>
            <!-- Menggunakan struktur HTML dan kelas CSS dari screenshot anda -->
            <table class='notes-table'>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id'] ?? '') ?></td>
                            <td><?= htmlspecialchars($user['username'] ?? '') ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($user['role'] ?? '') ?></td>
                            <td>
                                <!-- Pautan Edit -->
                                <a href='edit_user.php?id=<?= htmlspecialchars($user['id']) ?>' class='btn btn-info'>Edit</a>
                                <!-- Pautan Delete dengan logik saya untuk tidak memadam akaun sendiri -->
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href='manage_users.php?action=delete&id=<?= htmlspecialchars($user['id']) ?>' class='btn btn-danger' onclick='return confirm("Are you sure you want to delete this user?");'>Delete</a>
                                <?php else: ?>
                                    <span class="text-gray-500 text-sm">Cannot Delete Self</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>
