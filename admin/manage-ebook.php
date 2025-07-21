<?php
// admin/manage-ebook.php
// Halaman ini membolehkan pentadbir, pengurus, dan kerani melihat, mengedit, dan memadam rekod ebook.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Laluan relatif ke admin/manage-ebook.php

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
$searchTerm = ''; // Pemboleh ubah untuk menyimpan istilah carian

// -----------------------------------------------------------------------------
// Logik untuk memadam ebook
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
// Logik untuk fungsi carian dan mengambil semua ebook dari pangkalan data
// -----------------------------------------------------------------------------
$searchCondition = '';
$params = [];
$paramTypes = '';

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchTerm = trim($_GET['search']);
    $searchWildcard = '%' . $searchTerm . '%';
    // Search across multiple relevant columns
    $searchCondition = " WHERE e.id LIKE ? OR e.no LIKE ? OR e.tajuk LIKE ? OR e.penulis LIKE ? OR e.penerbit LIKE ? OR e.genre LIKE ?";
    $params = [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard];
    $paramTypes = "ssssss";
}

try {
    // Pilih kolum yang relevan dari jadual ebooks
    // LEFT JOIN users untuk mendapatkan username uploader
    $sql_fetch_ebooks = "SELECT e.id, e.no, e.penulis, e.tajuk, e.harga_rm, e.penerbit,
                                 u.username AS uploaded_by_username, e.created_at
                           FROM ebooks e
                           LEFT JOIN users u ON e.uploaded_by = u.id" . $searchCondition . " ORDER BY e.id ASC"; // Urutkan mengikut ID untuk No. yang berurutan

    if ($stmt_fetch_ebooks = $conn->prepare($sql_fetch_ebooks)) {
        if (!empty($searchCondition)) {
            $stmt_fetch_ebooks->bind_param($paramTypes, ...$params);
        }
        $stmt_fetch_ebooks->execute();
        $result_ebooks = $stmt_fetch_ebooks->get_result();

        if ($result_ebooks->num_rows > 0) {
            while ($row = $result_ebooks->fetch_assoc()) {
                $ebooks[] = $row;
            }
        } else {
            // Tiada ebook ditemui bukanlah ralat, hanya senarai kosong
            $errorMessage = null; 
        }
        $stmt_fetch_ebooks->close();
    } else {
        throw new Exception("Penyediaan SELECT ebook gagal: " . $conn->error);
    }
} catch (Exception $e) {
    $errorMessage = "Ralat mengambil data ebook: " . htmlspecialchars($e->getMessage());
    error_log("Manage Ebooks Fetch Error: " . $e->getMessage());
}

require_once '../includes/header.php'; // Laluan relatif ke manage-ebook.php
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

    <!-- Search Form -->
    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" class="search-form">
        <input type="text" name="search" placeholder="Search ebooks..." value="<?= htmlspecialchars($searchTerm); ?>" class="search-input">
        <button type="submit" class="btn btn-primary search-button">Search</button>
        <?php if (!empty($searchTerm)): ?>
            <a href="manage-ebook.php" class="btn btn-secondary clear-search-button">Clear Search</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <?php if (empty($ebooks)): ?>
            <p>No ebooks found. <?php if (!empty($searchTerm)) echo "Try a different search term."; ?></p>
        <?php else: ?>
            <table class='notes-table'>
                <thead>
                    <tr>
                        <th>ID</th> <!-- Lajur nombor siri bermula dari 1 -->
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
                            <td><?= htmlspecialchars($ebook['id']) ?></td> <!-- Paparkan ID sebenar dari DB di lajur No. -->
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
                                <a href='edit-ebook.php?id=<?= htmlspecialchars($ebook['id']) ?>' class='btn btn-info'>Edit✏️</a>
                                <!-- Pautan Delete -->
                                <a href='manage-ebook.php?action=delete&id=<?= htmlspecialchars($ebook['id']) ?>' class='btn btn-danger' onclick='return confirm("Are you sure you want to delete this ebook permanently? This will also delete all associated reviews, wishlist entries, and read statuses.");'>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
    /* CSS untuk fungsi carian sahaja - tidak akan mengganggu susun atur jadual */
    .search-form {
        display: flex;
        flex-direction: row; /* Pastikan elemen sebaris */
        justify-content: center;
        align-items: center; /* Penting untuk menyelaraskan secara menegak */
        margin-bottom: 20px;
        gap: 10px; /* Ruang antara elemen carian */
        /* Removed flex-wrap: wrap; to prevent stacking */
    }

    .search-input {
        padding: 10px 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 1em;
        flex-grow: 1; /* Benarkan input untuk membesar */
        max-width: 400px; /* Lebar maksimum untuk input */
        height: 40px; /* Tetapkan ketinggian eksplisit untuk menyelaraskan dengan butang */
        box-sizing: border-box; /* Pastikan padding dan border termasuk dalam ketinggian */
        /* Removed vertical-align: middle; and margin: 0; as align-items: center should handle it */
    }

    .search-button, .clear-search-button {
        padding: 0 15px; /* Padding sisi sahaja, ketinggian dikawal oleh height */
        font-size: 1em; /* Selaraskan saiz font dengan search-input */
        border-radius: 5px;
        white-space: nowrap; /* Elakkan teks butang daripada bungkus */
        cursor: pointer;
        border: none;
        transition: background-color 0.3s ease;
        width: auto; /* Biarkan lebar butang mengikut kandungan */
        flex-grow: 0; /* Pastikan butang tidak membesar */
        flex-shrink: 0; /* Pastikan butang tidak mengecil */
        height: 40px; /* Tetapkan ketinggian eksplisit sama dengan input */
        display: flex; /* Gunakan flexbox untuk pemusatan menegak teks */
        align-items: center; /* Pusatkan teks secara menegak */
        justify-content: center; /* Pusatkan teks secara mendatar */
        box-sizing: border-box; /* Pastikan padding dan border termasuk dalam ketinggian */
        /* Removed vertical-align: middle; and margin: 0; as align-items: center should handle it */
    }

    /* Gaya untuk butang carian - ini adalah gaya yang anda inginkan */
    .search-button.btn-primary { /* Menggunakan kelas btn-primary untuk butang Search */
        background-color: #A08AD3; /* Warna ungu */
        color: white;
    }
    .search-button.btn-primary:hover {
        background-color: #8c71c4; /* Warna ungu lebih gelap */
    }

    .clear-search-button.btn-secondary { /* Menggunakan kelas btn-secondary untuk butang Clear Search */
        background-color: #6c757d; /* Warna kelabu */
        color: white;
    }
    .clear-search-button.btn-secondary:hover {
        background-color: #5a6268; /* Warna kelabu lebih gelap */
    }

    /* CSS sedia ada dari fail anda, dikekalkan tanpa perubahan */
    /* Saya telah mengalih keluar definisi .btn, .btn-info, .btn-danger yang mungkin menimpa gaya asal anda */
    .container {
        margin-top: 20px;
        padding: 20px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    h2 {
        color: #A08AD3;
        text-align: center;
        margin-bottom: 15px;
    }
    p {
        text-align: center;
        color: #666;
    }
    .message {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        text-align: center;
    }
    .message.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .table-responsive {
        overflow-x: auto; /* Dikekalkan untuk responsif */
        margin-top: 20px;
    }
    .notes-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .notes-table th, .notes-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    .notes-table th {
        background-color: #f2f2f2;
        color: #555;
        font-weight: bold;
        text-transform: uppercase;
    }
    .notes-table tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .notes-table tbody tr:hover {
        background-color: #f0f0f0;
    }
    /* Saya telah mengalih keluar definisi .btn, .btn-info, .btn-danger yang mungkin menimpa gaya asal anda */
    /* Pastikan gaya butang asal anda ditakrifkan di tempat lain (contoh: header.php atau fail CSS luaran) */
</style>

<?php
// Tutup sambungan pangkalan data pada akhir skrip
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>
