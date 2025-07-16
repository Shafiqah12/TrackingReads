<?php
// admin/add_ebook.php
// Halaman ini membolehkan pentadbir menambah rekod ebook baharu.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Mulakan sesi
require_once '../includes/db_connect.php'; // Laluan relatif ke admin/add_ebook.php

// Kawalan akses: Pastikan hanya pentadbir yang log masuk boleh mengakses halaman ini
if (empty($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); // Arahkan ke halaman log masuk jika tidak dibenarkan
    exit;
}

// Mulakan pembolehubah untuk medan borang dan mesej ralat
$no = $penulis = $tajuk = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = '';
$errors = [];
$successMessage = '';

// Proses penyerahan borang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bersihkan dan sahkan input
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

    // Pengesahan asas
    if (empty($penulis)) $errors['penulis'] = 'Penulis diperlukan.';
    if (empty($tajuk)) $errors['tajuk'] = 'Tajuk diperlukan.';
    if (!empty($no) && !is_numeric($no)) $errors['no'] = 'NO mestilah nombor.';
    if (!empty($muka_surat) && !is_numeric($muka_surat)) $errors['muka_surat'] = 'Muka Surat mestilah nombor.';
    if (!empty($perkataan) && !is_numeric($perkataan)) $errors['perkataan'] = 'Perkataan mestilah nombor.';
    if (!empty($harga_rm) && !is_numeric($harga_rm)) $errors['harga_rm'] = 'Harga (RM) mestilah nombor.';
    if (!empty($tahun) && !is_numeric($tahun)) $errors['tahun'] = 'Tahun mestilah nombor.';

    // Jika tiada ralat pengesahan, teruskan dengan memasukkan ke pangkalan data
    if (empty($errors)) {
        try {
            // Sediakan pernyataan INSERT
            $sql = "INSERT INTO ebooks (no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                throw new Exception("Penyediaan gagal: " . $conn->error);
            }

            // Bind parameter (s=string, i=integer, d=double/float)
            // KOD DIBETULKAN: 's' untuk tajuk
            $stmt->bind_param("issiiidssis", $no, $penulis, $tajuk, $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit);

            // Laksanakan pernyataan
            if ($stmt->execute()) {
                $successMessage = "Ebook berjaya ditambah!";
                // Kosongkan medan borang selepas penyerahan berjaya
                $no = $penulis = $tajuk = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = '';
            } else {
                throw new Exception("Pelaksanaan gagal: " . $stmt->error);
            }

            $stmt->close();

        } catch (Exception $e) {
            $errors['db_error'] = "Ralat pangkalan data: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Sertakan header sedia ada anda
require_once '../includes/header.php'; // Laluan relatif ke admin/add_ebook.php
?>

<div class="container">
    <h2>Tambah Ebook Baharu</h2>

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

    <form method="post" action="add_ebook.php"> <!-- ACTION: Kekalkan sebagai 'add_ebook.php' kerana ia dalam folder yang sama -->
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

        <button type="submit" class="btn btn-primary">Tambah Ebook</button>
    </form>
</div>

<?php
// Tutup sambungan pangkalan data pada akhir skrip
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
// Sertakan footer sedia ada anda
require_once '../includes/footer.php'; // Laluan relatif ke admin/add_ebook.php
?>
