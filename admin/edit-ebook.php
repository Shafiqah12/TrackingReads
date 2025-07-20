<?php
// admin/edit_ebook.php
// Halaman ini membolehkan pentadbir, pengurus, dan kerani mengedit rekod ebook yang sedia ada.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Mulakan sesi
require_once '../includes/db_connect.php'; // Laluan relatif ke admin/edit_ebook.php

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
$allowedRoles = ['admin', 'manager', 'clerk']; // Admin, Manager, dan Clerk dibenarkan

// Kawalan akses: Pastikan hanya peranan yang dibenarkan boleh mengakses halaman ini
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["user_role"], $allowedRoles)) {
    header("location: ../login.php"); // Arahkan ke halaman log masuk jika tidak dibenarkan
    exit;
}

// Mulakan pembolehubah untuk medan borang dan mesej
$ebook = []; // Untuk menyimpan data ebook yang diambil
$errors = [];
$successMessage = '';
$ebookId = null;

// Mulakan pembolehubah medan borang dengan string kosong atau nilai lalai
$no = $penulis = $tajuk = $description = $file_path = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = '';
$uploaded_by_username = ''; // Untuk memaparkan username uploader
$created_at_display = ''; // Untuk memaparkan masa dicipta

// --- 1. Dapatkan ID Ebook dari URL atau POST ---
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
    // Pilih semua kolum yang diperlukan dari jadual ebooks, serta username uploader
    $sql_fetch = "SELECT e.id, e.no, e.penulis, e.tajuk, e.description, e.file_path,
                             e.muka_surat, e.perkataan, e.harga_rm, e.genre, e.bulan, e.tahun, e.penerbit,
                             u.username AS uploaded_by_username, e.created_at
                     FROM ebooks e
                     LEFT JOIN users u ON e.uploaded_by = u.id
                     WHERE e.id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $ebookId);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();

        if ($result_fetch->num_rows === 1) {
            $ebook = $result_fetch->fetch_assoc();
            // Isi pembolehubah untuk medan borang dari data yang diambil
            // Gunakan operator null coalescing untuk mengendalikan nilai NULL yang berpotensi dari DB
            $no          = $ebook['no'] ?? '';
            $penulis     = $ebook['penulis'] ?? '';
            $tajuk       = $ebook['tajuk'] ?? '';
            $description = $ebook['description'] ?? '';
            $file_path   = $ebook['file_path'] ?? ''; // Path penuh
            $muka_surat  = $ebook['muka_surat'] ?? '';
            $perkataan   = $ebook['perkataan'] ?? '';
            $harga_rm    = $ebook['harga_rm'] ?? '';
            $genre       = $ebook['genre'] ?? '';
            $bulan       = $ebook['bulan'] ?? '';
            $tahun       = $ebook['tahun'] ?? '';
            $penerbit    = $ebook['penerbit'] ?? '';
            $uploaded_by_username = $ebook['uploaded_by_username'] ?? 'Unknown';
            $created_at_display = $ebook['created_at'] ?? 'N/A';

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
    $no          = filter_var(trim($_POST['no'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $penulis     = filter_var(trim($_POST['penulis'] ?? ''), FILTER_UNSAFE_RAW);
    $tajuk       = filter_var(trim($_POST['tajuk'] ?? ''), FILTER_UNSAFE_RAW);
    $description = trim($_POST['description'] ?? '') ?: NULL; // Jika kosong, set kepada NULL
    $muka_surat  = filter_var(trim($_POST['muka_surat'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $perkataan   = filter_var(trim($_POST['perkataan'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $harga_rm    = filter_var(trim($_POST['harga_rm'] ?? ''), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $genre       = filter_var(trim($_POST['genre'] ?? ''), FILTER_UNSAFE_RAW);
    $bulan       = filter_var(trim($_POST['bulan'] ?? ''), FILTER_UNSAFE_RAW);
    $tahun       = filter_var(trim($_POST['tahun'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $penerbit    = filter_var(trim($_POST['penerbit'] ?? ''), FILTER_UNSAFE_RAW);

    // Handle file upload (only if a new file is selected)
    $new_file_path = $file_path; // Assume existing path by default (from fetched data)

    // Mengendalikan muat naik imej (bukan fail ebook umum)
    if (isset($_FILES["ebook_image"]) && $_FILES["ebook_image"]["error"] === UPLOAD_ERR_OK) {
        $target_dir = "../images/"; // Folder untuk menyimpan fail gambar
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Pastikan direktori wujud
        }

        $file_name = basename($_FILES["ebook_image"]["name"]);
        $unique_file_name = uniqid() . '_' . $file_name; // Pastikan nama fail unik
        $target_file = $target_dir . $unique_file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Jenis fail yang dibenarkan untuk gambar
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp']; 
        if (!in_array($file_type, $allowed_types)) {
            $errors['file_upload'] = "Maaf, hanya fail JPG, JPEG, PNG, GIF, WEBP dibenarkan.";
        }

        if ($_FILES["ebook_image"]["size"] > 5 * 1024 * 1024) { // Contoh: 5 MB untuk gambar
            $errors['file_upload'] = "Maaf, fail gambar anda terlalu besar. Maksimum 5MB dibenarkan.";
        }

        if (empty($errors['file_upload'])) {
            if (move_uploaded_file($_FILES["ebook_image"]["tmp_name"], $target_file)) {
                // Jika fail baharu berjaya dimuat naik, padam fail lama jika wujud
                if ($file_path && file_exists($file_path) && $file_path !== $target_file) {
                    unlink($file_path); // Padam fail lama
                }
                $new_file_path = $target_file; // Kemas kini ke laluan fail baharu
            } else {
                $errors['file_upload'] = "Maaf, terdapat ralat semasa memuat naik fail gambar baharu.";
            }
        }
    } else if (isset($_FILES["ebook_image"]) && $_FILES["ebook_image"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $errors['file_upload'] = "Ralat muat naik fail gambar: " . $_FILES["ebook_image"]["error"];
    }


    // Pengesahan asas
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
            // UPDATE statement untuk jadual ebooks
            $sql_update = "UPDATE ebooks SET no = ?, penulis = ?, tajuk = ?, description = ?, file_path = ?, muka_surat = ?, perkataan = ?, harga_rm = ?, genre = ?, bulan = ?, tahun = ?, penerbit = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);

            if ($stmt_update === false) {
                throw new Exception("Penyediaan kemas kini gagal: " . $conn->error);
            }

            // String jenis bind_param: isssisiiidssi
            // (no, penulis, tajuk, description, file_path, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit, ebookId)
            $stmt_update->bind_param("isssisiiidssi", $no, $penulis, $tajuk, $description, $new_file_path,
                                     $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit, $ebookId);

            if ($stmt_update->execute()) {
                $successMessage = "Ebook berjaya dikemas kini!";
                // Selepas kemas kini berjaya, ambil semula data untuk menunjukkan perubahan terkini
                // Ini memastikan borang memaparkan data terkini selepas POST
                $sql_re_fetch = "SELECT e.id, e.no, e.penulis, e.tajuk, e.description, e.file_path,
                                     e.muka_surat, e.perkataan, e.harga_rm, e.genre, e.bulan, e.tahun, e.penerbit,
                                     u.username AS uploaded_by_username, e.created_at
                                     FROM ebooks e
                                     LEFT JOIN users u ON e.uploaded_by = u.id
                                     WHERE e.id = ?";
                $stmt_re_fetch = $conn->prepare($sql_re_fetch);
                $stmt_re_fetch->bind_param("i", $ebookId);
                $stmt_re_fetch->execute();
                $result_re_fetch = $stmt_re_fetch->get_result();
                if ($result_re_fetch->num_rows === 1) {
                    $ebook = $result_re_fetch->fetch_assoc();
                    $no          = $ebook['no'] ?? '';
                    $penulis     = $ebook['penulis'] ?? '';
                    $tajuk       = $ebook['tajuk'] ?? '';
                    $description = $ebook['description'] ?? '';
                    $file_path   = $ebook['file_path'] ?? '';
                    $muka_surat  = $ebook['muka_surat'] ?? '';
                    $perkataan   = $ebook['perkataan'] ?? '';
                    $harga_rm    = $ebook['harga_rm'] ?? '';
                    $genre       = $ebook['genre'] ?? '';
                    $bulan       = $ebook['bulan'] ?? '';
                    $tahun       = $ebook['tahun'] ?? '';
                    $penerbit    = $ebook['penerbit'] ?? '';
                    $uploaded_by_username = $ebook['uploaded_by_username'] ?? 'Unknown';
                    $created_at_display = $ebook['created_at'] ?? 'N/A';
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

<div class="main-content-area">
    <div class="auth-container">
        <h2>Edit Ebook (ID: <?= htmlspecialchars($ebookId ?? 'N/A'); ?>)</h2>
        <p>Modify the details of this ebook below.</p>

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
            <p class="message info">Sila kembali ke <a href="manage-ebook.php">Urus Ebook</a> untuk memilih ebook untuk diedit.</p>
        <?php else: ?>
            <form method="post" action="edit-ebook.php?id=<?= htmlspecialchars($ebookId) ?>" enctype="multipart/form-data">
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
                    <label for="description">Description (Optional):</label>
                    <textarea id="description" name="description" rows="5" class="form-control"><?= htmlspecialchars($description) ?></textarea>
                    <?php if (isset($errors['description'])): ?><span class="help-block"><?= $errors['description'] ?></span><?php endif; ?>
                </div>

                <label>Current Ebook Image:</label>
                <p>
                    <?php if ($file_path): ?>
                        <img src="<?= htmlspecialchars($file_path); ?>" alt="Ebook Image" style="max-width: 200px; height: auto; border-radius: 5px;">
                    <?php else: ?>
                        No image uploaded.
                    <?php endif; ?>
                </p>

                <div class="form-group">
                    <label for="ebookImage" style="font-weight: bold; color: #333;">Replace Ebook Image (Optional):</label>
                    <input type="file" class="form-control-file" id="ebookImage" name="ebook_image" style="display: none;">

                    <div class="file-input-display-wrapper">
                        <label for="ebookImage" class="custom-file-label">Choose File</label>
                        <span id="file-name-display">No file chosen</span>
                    </div>

                    <small class="form-text text-muted" style="color: #666 !important;">
                        Leave blank to keep the current image. Max 5MB, allowed types: JPG, JPEG, PNG, GIF, WEBP.
                    </small>
                    <?php if (isset($errors['file_upload'])): ?><span class="help-block"><?= $errors['file_upload'] ?></span><?php endif; ?>
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

                <label>Uploaded By:</label>
                <input type="text" value="<?= htmlspecialchars($uploaded_by_username) ?>" disabled class="form-control">
                <small class="text-muted">This field cannot be changed here.</small>

                <label>Created At:</label>
                <input type="text" value="<?= htmlspecialchars($created_at_display) ?>" disabled class="form-control">
                <small class="text-muted">This field cannot be changed here.</small>

                <button type="submit" class="btn btn-primary mt-4">Kemas Kini Ebook</button>
                <a href="manage-ebook.php" class="btn btn-secondary mt-4">Batal</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('ebookImage'); 
        const fileNameDisplay = document.getElementById('file-name-display');

        if (fileInput && fileNameDisplay) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    fileNameDisplay.textContent = this.files[0].name;
                } else {
                    fileNameDisplay.textContent = 'No file chosen';
                }
            });
        }
    });
</script>

<style>
    /* Basic styling for form elements for consistency */
    .form-group {
        margin-bottom: 1rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: bold;
    }
    .form-control {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #ccc;
        border-radius: 0.25rem;
        box-sizing: border-box;
    }
    textarea.form-control {
        resize: vertical;
    }
    .help-block {
        color: #dc3545; /* Red for errors */
        font-size: 0.875em;
        margin-top: 0.25rem;
        display: block;
    }
    .message {
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
    }
    .message.success {
        background-color: #d4edda;
        color: #A08AD3;
        border: 1px solid #c3e6cb;
    }
    .message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .message.info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
    .btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 0.25rem;
        cursor: pointer;
        font-size: 1em;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        margin-right: 0.5rem;
    }
    .btn-primary {
        background-color: #bfaaebff;
        color: white;
    }
    .btn-primary:hover {
        background-color: #b396f3ff;
    }
    .btn-secondary {
        background-color: #B8AEE2;
        color: white;
    }
    .btn-secondary:hover {
        background-color: #ad99fbff;
    }
    .custom-file-label {
        display: inline-block;
        padding: 0.5rem 1rem;
        background-color: #A08AD3;
        color: white;
        border-radius: 0.25rem;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .custom-file-label:hover {
        background-color: #8a73c3;
    }
    .file-input-display-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
    }
    .text-muted {
        color: #6c757d !important;
    }
    .mt-4 {
        margin-top: 1rem;
    }
</style>

<?php
// Tutup sambungan pangkalan data pada akhir skrip
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
// Sertakan footer sedia ada anda
require_once '../includes/footer.php';
?>
