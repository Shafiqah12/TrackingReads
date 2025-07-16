<?php
// admin/manage-ebooks.php
// Halaman ini membolehkan pentadbir dan pengurus melihat, mengedit, dan memadam rekod ebook.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php';

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
// Admin dan Manager dibenarkan mengurus ebook.
$allowedRoles = ['admin', 'manager'];

// Semak jika pengguna TIDAK log masuk ATAU TIDAK mempunyai peranan yang dibenarkan
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], $allowedRoles)) {
    header("Location: ../login.php"); // Arahkan ke halaman log masuk jika tidak dibenarkan
    exit;
}

$errorMessage = null;
$successMessage = null;
$ebooks = [];

// Logik untuk memadam ebook
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $ebook_id_to_delete = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    if ($ebook_id_to_delete) {
        try {
            $sql_delete = "DELETE FROM ebooks WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            if ($stmt_delete === false) {
                throw new Exception("Penyediaan DELETE gagal: " . $conn->error);
            }
            $stmt_delete->bind_param("i", $ebook_id_to_delete);
            if ($stmt_delete->execute()) {
                $successMessage = "Ebook dengan ID " . htmlspecialchars($ebook_id_to_delete) . " berjaya dipadam.";
            } else {
                throw new Exception("Pelaksanaan DELETE gagal: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        } catch (Exception $e) {
            $errorMessage = "Ralat memadam ebook: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $errorMessage = "ID ebook tidak sah untuk dipadam.";
    }
}


// Ambil semua ebook dari pangkalan data
try {
    $sql = "SELECT id, no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit FROM ebooks ORDER BY id ASC";
    $result = $conn->query($sql);

    if ($result === false) {
        throw new Exception("SQL Query Error: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $ebooks[] = $row;
        }
    } else {
        $errorMessage = "Tiada ebook ditemui dalam pangkalan data.";
    }
} catch (Exception $e) {
    $errorMessage = "Ralat mengambil data ebook: " . htmlspecialchars($e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="container">
    <h2>Manage Ebooks</h2>

    <?php if ($successMessage): ?>
        <div class="message success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="message error"><?= $errorMessage ?></div>
    <?php endif; ?>

    <?php if (empty($ebooks)): ?>
        <div class="message info">Tiada ebook untuk diuruskan.</div>
    <?php else: ?>
        <table class="table-auto w-full text-left whitespace-no-wrap mt-4">
            <thead>
                <tr>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100 rounded-tl rounded-bl">ID</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">NO</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">Penulis</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">Tajuk</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">Muka Surat</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">Perkataan</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">Harga (RM)</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">Genre</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">Bulan</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">Tahun</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100">Penerbit</th>
                    <th class="px-4 py-3 title-font tracking-wider font-medium text-gray-900 text-sm bg-gray-100 rounded-tr rounded-br">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ebooks as $ebook): ?>
                    <tr>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['id'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['no'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['penulis'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['tajuk'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['muka_surat'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['perkataan'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars(number_format($ebook['harga_rm'] ?? 0, 2)) ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['genre'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['bulan'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['tahun'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3"><?= htmlspecialchars($ebook['penerbit'] ?? '') ?></td>
                        <td class="border-t-2 border-gray-200 px-4 py-3">
                            <a href="edit-ebook.php?id=<?= htmlspecialchars($ebook['id']) ?>" class="btn btn-edit">Edit</a>
                            <!-- Tambah pengesahan JavaScript untuk pemadaman -->
                            <a href="manage-ebook.php?action=delete&id=<?= htmlspecialchars($ebook['id']) ?>" class="btn btn-delete" onclick="return confirm('Adakah anda pasti ingin memadam ebook ini?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>
