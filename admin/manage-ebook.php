<?php
// admin/manage_ebooks.php
// Halaman ini membolehkan pentadbir, pengurus, dan kerani melihat, mengedit, dan memadam rekod ebook.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Laluan relatif ke admin/manage_ebooks.php

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
// Admin, Manager, dan Clerk dibenarkan untuk mengurus ebook.
$allowedRoles = ['admin', 'manager', 'clerk'];

// Kawalan akses: Pastikan hanya peranan yang dibenarkan boleh mengakses halaman ini
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["user_role"], $allowedRoles)) {
    header("location: ../login.php"); // Arahkan ke halaman log masuk jika tidak dibenarkan
    exit;
}

$errorMessage = null;
$successMessage = null;
$ebooks = []; // Array untuk menyimpan data ebook

// -----------------------------------------------------------------------------
// Logik untuk memadam ebook (Jika anda memutuskan untuk mengendalikan DELETE di sini)
// Jika anda mempunyai fail delete_ebook.php yang berasingan, anda boleh abaikan ini
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $ebook_id_to_delete = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    if ($ebook_id_to_delete) {
        try {
            // Mulakan transaksi
            $conn->begin_transaction();

            // Padam ulasan dan rating berkaitan terlebih dahulu (jika ada)
            $sql_delete_reviews = "DELETE FROM reviews WHERE ebook_id = ?";
            $stmt_delete_reviews = $conn->prepare($sql_delete_reviews);
            if ($stmt_delete_reviews) {
                $stmt_delete_reviews->bind_param("i", $ebook_id_to_delete);
                $stmt_delete_reviews->execute();
                $stmt_delete_reviews->close();
            }

            // Padam wishlist berkaitan
            $sql_delete_wishlist = "DELETE FROM wishlist WHERE ebook_id = ?";
            $stmt_delete_wishlist = $conn->prepare($sql_delete_wishlist);
            if ($stmt_delete_wishlist) {
                $stmt_delete_wishlist->bind_param("i", $ebook_id_to_delete);
                $stmt_delete_wishlist->execute();
                $stmt_delete_wishlist->close();
            }

            // Padam read_status berkaitan
            $sql_delete_read_status = "DELETE FROM read_status WHERE ebook_id = ?";
            $stmt_delete_read_status = $conn->prepare($sql_delete_read_status);
            if ($stmt_delete_read_status) {
                $stmt_delete_read_status->bind_param("i", $ebook_id_to_delete);
                $stmt_delete_read_status->execute();
                $stmt_delete_read_status->close();
            }

            // Padam ebook itu sendiri
            $sql_delete_ebook = "DELETE FROM ebooks WHERE id = ?";
            $stmt_delete_ebook = $conn->prepare($sql_delete_ebook);
            if ($stmt_delete_ebook === false) {
                throw new Exception("Penyediaan DELETE ebook gagal: " . $conn->error);
            }
            $stmt_delete_ebook->bind_param("i", $ebook_id_to_delete);
            if ($stmt_delete_ebook->execute()) {
                $successMessage = "Ebook dengan ID " . htmlspecialchars($ebook_id_to_delete) . " berjaya dipadam.";
                $conn->commit(); // Sahkan transaksi
            } else {
                throw new Exception("Pelaksanaan DELETE ebook gagal: " . $stmt_delete_ebook->error);
            }
            $stmt_delete_ebook->close();
        } catch (Exception $e) {
            $conn->rollback(); // Gulung balik transaksi jika ada ralat
            $errorMessage = "Ralat memadam ebook: " . htmlspecialchars($e->getMessage());
            error_log("Manage Ebooks Delete Error: " . $e->getMessage());
        }
    } else {
        $errorMessage = "ID ebook tidak sah untuk dipadam.";
    }
}


// -----------------------------------------------------------------------------
// Ambil semua ebook dari pangkalan data untuk paparan jadual
// -----------------------------------------------------------------------------
try {
    // Pilih kolum yang relevan dari jadual ebooks
    // LEFT JOIN users untuk mendapatkan username uploader
    $sql_fetch_ebooks = "SELECT e.id, e.no, e.penulis, e.tajuk, e.harga_rm, e.penerbit,
                                 u.username AS uploaded_by_username, e.created_at
                           FROM ebooks e
                           LEFT JOIN users u ON e.uploaded_by = u.id
                           ORDER BY e.created_at DESC"; // Urutkan mengikut yang terbaru dahulu

    $result_ebooks = $conn->query($sql_fetch_ebooks);

    if ($result_ebooks === false) {
        throw new Exception("SQL Query Error: " . $conn->error);
    }

    if ($result_ebooks->num_rows > 0) {
        while ($row = $result_ebooks->fetch_assoc()) {
            $ebooks[] = $row;
        }
    } else {
        // Tiada ebook ditemui bukanlah ralat, hanya senarai kosong
        $errorMessage = null; 
    }
} catch (Exception $e) {
    $errorMessage = "Ralat mengambil data ebook: " . htmlspecialchars($e->getMessage());
    error_log("Manage Ebooks Fetch Error: " . $e->getMessage());
}

require_once '../includes/header.php'; // Laluan relatif ke manage_ebooks.php
?>

<div class="container mt-4">
    <h2>Manage Ebooks</h2>
    <p>Here you can view, edit, or delete ebook records.</p>

    <?php if ($successMessage): ?>
        <div class="message success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="message error"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <?php if (empty($ebooks)): ?>
            <p>No ebooks found.</p>
        <?php else: ?>
            <table class='notes-table'> <!-- Menggunakan kelas 'notes-table' untuk gaya -->
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>NO</th>
                        <th>Tajuk</th>
                        <th>Penulis</th>
                        <th>Penerbit</th>
                        <th>Harga (RM)</th>
                        <th>Uploaded By</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ebooks as $ebook): ?>
                        <tr>
                            <td><?= htmlspecialchars($ebook['id'] ?? '') ?></td>
                            <td><?= htmlspecialchars($ebook['no'] ?? '') ?></td>
                            <td><?= htmlspecialchars($ebook['tajuk'] ?? '') ?></td>
                            <td><?= htmlspecialchars($ebook['penulis'] ?? '') ?></td>
                            <td><?= htmlspecialchars($ebook['penerbit'] ?? '') ?></td>
                            <td><?= htmlspecialchars(number_format($ebook['harga_rm'] ?? 0, 2)) ?></td>
                            <td><?= htmlspecialchars($ebook['uploaded_by_username'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($ebook['created_at'] ?? 'N/A') ?></td>
                            <td>
                                <!-- Pautan View Details -->
                                <a href='view_ebook_details.php?id=<?= htmlspecialchars($ebook['id']) ?>' class='btn btn-info btn-sm'>View</a>
                                <!-- Pautan Edit -->
                                <a href='edit_ebook.php?id=<?= htmlspecialchars($ebook['id']) ?>' class='btn btn-info'>Edit✏️</a>
                                <!-- Pautan Delete -->
                                <a href='manage_ebooks.php?action=delete&id=<?= htmlspecialchars($ebook['id']) ?>' class='btn btn-danger' onclick='return confirm("Are you sure you want to delete this ebook permanently? This will also delete all associated reviews, wishlist entries, and read statuses.");'>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
// Tutup sambungan pangkalan data pada akhir skrip
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>
