<?php
// admin/edit-ebook.php
// Halaman ini membolehkan pentadbir mengedit rekod ebook yang sedia ada.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Mulakan sesi
require_once '../includes/db_connect.php'; // Laluan relatif ke admin/edit-ebook.php

// Kawalan akses: Pastikan hanya pentadbir yang log masuk boleh mengakses halaman ini
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "admin") {
    header("location: ../login.php"); // Arahkan ke halaman log masuk jika tidak dibenarkan
    exit;
}

// Mulakan pembolehubah untuk medan borang dan mesej
$ebook = []; // Untuk menyimpan data ebook yang diambil
$errors = [];
$successMessage = '';
$ebookId = null;

// Mulakan pembolehubah medan borang dengan string kosong
$no = $penulis = $tajuk = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = '';


// --- 1. Dapatkan ID Ebook dari URL ---
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $ebookId = filter_var(trim($_GET['id']), FILTER_SANITIZE_NUMBER_INT);

    if (!is_numeric($ebookId) || $ebookId <= 0) {
        $errors['id'] = 'ID ebook tidak sah disediakan.';
        $ebookId = null; // Batalkan ID jika tidak sah
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    // Jika datang dari penyerahan POST, dapatkan ID dari data POST
    $ebookId = filter_var(trim($_POST['id']), FILTER_SANITIZE_NUMBER_INT);
    if (!is_numeric($ebookId) || $ebookId <= 0) {
        $errors['id'] = 'ID ebook tidak sah disediakan dalam penyerahan borang.';
        $ebookId = null;
    }
} else {
    $errors['id'] = 'Tiada ID ebook ditentukan untuk diedit.';
}

// --- 2. Ambil Data Ebook (jika ID sah) ---
if ($ebookId && empty($errors)) {
    $sql_fetch = "SELECT id, no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit FROM ebooks WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $ebookId);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();

        if ($result_fetch->num_rows === 1) {
            $ebook = $result_fetch->fetch_assoc();
            // Isi pembolehubah untuk medan borang dari data yang diambil
            // Gunakan operator null coalescing untuk mengendalikan nilai NULL yang berpotensi dari DB
            $no         = $ebook['no'] ?? '';
            $penulis    = $ebook['penulis'] ?? '';
            $tajuk      = $ebook['tajuk'] ?? '';
            $muka_surat = $ebook['muka_surat'] ?? '';
            $perkataan  = $ebook['perkataan'] ?? '';
            $harga_rm   = $ebook['harga_rm'] ?? '';
            $genre      = $ebook['genre'] ?? '';
            $bulan      = $ebook['bulan'] ?? '';
            $tahun      = $ebook['tahun'] ?? '';
            $penerbit   = $ebook['penerbit'] ?? '';

        } else {
            $errors['fetch'] = 'Ebook tidak ditemui dengan ID yang ditentukan.';
            $ebookId = null; // Batalkan ID jika tidak ditemui
        }
        $stmt_fetch->close();
    } else {
        $errors['db_fetch'] = 'Ralat pangkalan data menyediakan pernyataan pengambilan: ' . htmlspecialchars($conn->error);
    }
}

// --- 3. Proses Penyerahan Borang (jika permintaan POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ebookId && empty($errors)) {
    // Bersihkan dan sahkan input dari POST
    $no         = filter_var(trim($_POST['no'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $penulis    = filter_var(trim($_POST['penulis'] ?? ''), FILTER_UNSAFE_RAW);
    $tajuk      = filter_var(trim($_POST['tajuk'] ?? ''), FILTER_UNSAFE_RAW);
    $muka_surat = filter_var(trim($_POST['muka_surat'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $perkataan  = filter_var(trim($_POST['perkataan'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $harga_rm   = filter_var(trim($_POST['harga_rm'] ?? ''), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $genre      = filter_var(trim($_POST['genre'] ?? ''), FILTER_UNSAFE_RAW);
    $bulan      = filter_var(trim($_POST['bulan'] ?? ''), FILTER_UNSAFE_RAW);
    $tahun      = filter_var(trim($_POST['tahun'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $penerbit   = filter_var(trim($_POST['penerbit'] ?? ''), FILTER_UNSAFE_RAW);

    // Pengesahan asas (serupa dengan add_ebook.php)
    if (empty($penulis)) $errors['penulis'] = 'Penulis diperlukan.';
    if (empty($tajuk)) $errors['tajuk'] = 'Tajuk diperlukan.';
    if (!empty($no) && !is_numeric($no)) $errors['no'] = 'NO mestilah nombor.';
    if (!empty($muka_surat) && !is_numeric($muka_surat)) $errors['muka_surat'] = 'Muka Surat mestilah nombor.';
    if (!empty($perkataan) && !is_numeric($perkataan)) $errors['perkataan'] = 'Perkataan mestilah nombor.';
    if (!empty($harga_rm) && !is_numeric($harga_rm)) $errors['harga_rm'] = 'Harga (RM) mestilah nombor.';
    if (!empty($tahun) && !is_numeric($tahun)) $errors['tahun'] = 'Tahun mestilah nombor.';

    // Jika tiada ralat pengesahan, teruskan dengan kemas kini pangkalan data
    if (empty($errors)) {
        try {
            $sql_update = "UPDATE ebooks SET no = ?, penulis = ?, tajuk = ?, muka_surat = ?, perkataan = ?, harga_rm = ?, genre = ?, bulan = ?, tahun = ?, penerbit = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);

            if ($stmt_update === false) {
                throw new Exception("Penyediaan kemas kini gagal: " . $conn->error);
            }

            // KOD DIBETULKAN: 's' untuk tajuk
            $stmt_update->bind_param("issiiidssisi", $no, $penulis, $tajuk, $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit, $ebookId);

            if ($stmt_update->execute()) {
                $successMessage = "Ebook berjaya dikemas kini!";
                // Selepas kemas kini berjaya, ambil semula data untuk menunjukkan perubahan terkini
                $sql_re_fetch = "SELECT no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit FROM ebooks WHERE id = ?";
                $stmt_re_fetch = $conn->prepare($sql_re_fetch);
                $stmt_re_fetch->bind_param("i", $ebookId);
                $stmt_re_fetch->execute();
                $result_re_fetch = $stmt_re_fetch->get_result();
                if ($result_re_fetch->num_rows === 1) {
                    $ebook = $result_re_fetch->fetch_assoc();
                    $no         = $ebook['no'] ?? '';
                    $penulis    = $ebook['penulis'] ?? '';
                    $tajuk      = $ebook['tajuk'] ?? '';
                    $muka_surat = $ebook['muka_surat'] ?? '';
                    $perkataan  = $ebook['perkataan'] ?? '';
                    $harga_rm   = $ebook['harga_rm'] ?? '';
                    $genre      = $ebook['genre'] ?? '';
                    $bulan      = $ebook['bulan'] ?? '';
                    $tahun      = $ebook['tahun'] ?? '';
                    $penerbit   = $ebook['penerbit'] ?? '';
                }
                $stmt_re_fetch->close();

            } else {
                throw new Exception("Pelaksanaan kemas kini gagal: " . $stmt_update->error);
            }
            $stmt_update->close();

        } catch (Exception $e) {
            $errors['db_update'] = "Ralat pangkalan data semasa kemas kini: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Sertakan header sedia ada anda
require_once '../includes/header.php';
?>

<div class="container">
    <h2>Edit Ebook</h2>

    <?php if ($successMessage): ?>
        <div class="message success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <h3>Sila betulkan ralat berikut:</h3>
            <ul>
                <?php foreach ($errors as $field => $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$ebookId || !empty($errors['id']) || !empty($errors['fetch'])): ?>
        <p class="message info">Sila kembali ke <a href="manage-ebooks.php">Urus Ebook</a> untuk memilih ebook untuk diedit.</p>
    <?php else: ?>
        <form method="post" action="edit-ebook.php?id=<?= htmlspecialchars($ebookId) ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($ebookId) ?>">

            <div class="form-group">
                <label for="no">NO:</label>
                <input type="number" id="no" name="no" class="form-control" value="<?= htmlspecialchars($no) ?>">
                <?php if (isset($errors['no'])): ?><span class="help-block"><?= $errors['no'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="penulis">Penulis (Author):</label>
                <input type="text" id="penulis" name="penulis" class="form-control" value="<?= htmlspecialchars($penulis) ?>" required>
                <?php if (isset($errors['penulis'])): ?><span class="help-block"><?= $errors['penulis'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="tajuk">Tajuk (Title):</label>
                <input type="text" id="tajuk" name="tajuk" class="form-control" value="<?= htmlspecialchars($tajuk) ?>" required>
                <?php if (isset($errors['tajuk'])): ?><span class="help-block"><?= $errors['tajuk'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="muka_surat">Muka Surat (Pages):</label>
                <input type="number" id="muka_surat" name="muka_surat" class="form-control" value="<?= htmlspecialchars($muka_surat) ?>">
                <?php if (isset($errors['muka_surat'])): ?><span class="help-block"><?= $errors['muka_surat'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="perkataan">Perkataan (Words):</label>
                <input type="number" id="perkataan" name="perkataan" class="form-control" value="<?= htmlspecialchars($perkataan) ?>">
                <?php if (isset($errors['perkataan'])): ?><span class="help-block"><?= $errors['perkataan'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="harga_rm">Harga (RM):</label>
                <input type="number" step="0.01" id="harga_rm" name="harga_rm" class="form-control" value="<?= htmlspecialchars($harga_rm) ?>">
                <?php if (isset($errors['harga_rm'])): ?><span class="help-block"><?= $errors['harga_rm'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="genre">Genre:</label>
                <input type="text" id="genre" name="genre" class="form-control" value="<?= htmlspecialchars($genre) ?>">
                <?php if (isset($errors['genre'])): ?><span class="help-block"><?= $errors['genre'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="bulan">Bulan (Month):</label>
                <input type="text" id="bulan" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan) ?>">
                <?php if (isset($errors['bulan'])): ?><span class="help-block"><?= $errors['bulan'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="tahun">Tahun (Year):</label>
                <input type="number" id="tahun" name="tahun" class="form-control" value="<?= htmlspecialchars($tahun) ?>">
                <?php if (isset($errors['tahun'])): ?><span class="help-block"><?= $errors['tahun'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="penerbit">Penerbit (Publisher):</label>
                <input type="text" id="penerbit" name="penerbit" class="form-control" value="<?= htmlspecialchars($penerbit) ?>">
                <?php if (isset($errors['penerbit'])): ?><span class="help-block"><?= $errors['penerbit'] ?></span><?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Kemas Kini Ebook</button>
            <a href="manage-ebooks.php" class="btn btn-secondary">Batal</a> <!-- Pautan kembali ke halaman urus -->
        </form>
    <?php endif; ?>
</div>

<?php
// Tutup sambungan pangkalan data pada akhir skrip
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
// Sertakan footer sedia ada anda
require_once '../includes/footer.php';
?>
