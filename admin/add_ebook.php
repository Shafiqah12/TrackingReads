<?php
// admin/add_ebook.php
// Halaman ini membolehkan pentadbir, pengurus, dan kerani menambah rekod ebook baharu.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php';

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
$allowedRoles = ['admin', 'manager', 'clerk'];

// Semak jika pengguna TIDAK log masuk ATAU TIDAK mempunyai peranan yang dibenarkan
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], $allowedRoles)) {
    error_log("Access Denied: User not logged in or role not allowed for add_ebook.php. Role: " . ($_SESSION['user_role'] ?? 'N/A'));
    header("Location: ../login.php");
    exit;
}

// Dapatkan user_id dari sesi untuk kolum uploaded_by
$current_user_id = $_SESSION['user_id'] ?? null;
error_log("DEBUG: add_ebook.php - User ID from session: " . ($current_user_id ?? 'NULL'));


// Mulakan pembolehubah untuk medan borang dan mesej ralat
$no = $penulis = $tajuk = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = '';
$errors = [];
$successMessage = '';

// Proses penyerahan borang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("DEBUG: add_ebook.php - POST request received.");

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

    // Tambah pembolehubah untuk description dan file_path (jika ada dalam borang atau set kepada NULL)
    // Berdasarkan struktur DB, description boleh NULL, file_path juga boleh NULL (selepas perubahan DB)
    $description = filter_var(trim($_POST['description'] ?? ''), FILTER_UNSAFE_RAW) ?: NULL; // Jika kosong, set kepada NULL
    $file_path = filter_var(trim($_POST['file_path'] ?? ''), FILTER_UNSAFE_RAW) ?: NULL; // Jika kosong, set kepada NULL

    // Log nilai input yang diterima
    error_log("DEBUG: add_ebook.php - Input values: NO=$no, Penulis=$penulis, Tajuk=$tajuk, Muka_Surat=$muka_surat, Perkataan=$perkataan, Harga_RM=$harga_rm, Genre=$genre, Bulan=$bulan, Tahun=$tahun, Penerbit=$penerbit, Description=" . ($description ?? 'NULL') . ", File_Path=" . ($file_path ?? 'NULL'));


    // Pengesahan asas
    if (empty($penulis)) $errors['penulis'] = 'Penulis diperlukan.';
    if (empty($tajuk)) $errors['tajuk'] = 'Tajuk diperlukan.';
    if (!empty($no) && !is_numeric($no)) $errors['no'] = 'NO mestilah nombor.';
    if (!empty($muka_surat) && !is_numeric($muka_surat)) $errors['muka_surat'] = 'Muka Surat mestilah nombor.';
    if (!empty($perkataan) && !is_numeric($perkataan)) $errors['perkataan'] = 'Perkataan mestilah nombor.';
    if (!empty($harga_rm) && !is_numeric($harga_rm)) $errors['harga_rm'] = 'Harga (RM) mestilah nombor.';
    if (!empty($tahun) && !is_numeric($tahun)) $errors['tahun'] = 'Tahun mestilah nombor.';

    // PENTING: Sahkan uploaded_by_id wujud
    if (is_null($current_user_id)) {
        $errors['user_id'] = 'ID pengguna yang log masuk tidak ditemui. Sila log masuk semula.';
    }

    // Jika tiada ralat pengesahan, teruskan dengan memasukkan ke pangkalan data
    if (empty($errors)) {
        error_log("DEBUG: add_ebook.php - No validation errors, attempting database insert.");
        // Mulakan transaksi secara manual
        $conn->begin_transaction();
        error_log("DEBUG: add_ebook.php - Transaction started.");

        try {
            // Sediakan pernyataan INSERT - PASTIKAN SEMUA KOLUM YANG TIDAK NULL DISERTAKAN
            // Jika description dan file_path dibenarkan NULL di DB, kita boleh masukkan NULL jika tiada input
            $sql = "INSERT INTO ebooks (no, penulis, tajuk, description, file_path, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                // Log ralat penyediaan pernyataan
                error_log("DEBUG: Add Ebook Prepare Error: " . $conn->error . " - SQL: " . $sql);
                throw new Exception("Penyediaan gagal: " . $conn->error);
            }

            // --- DEBUGGING TAJUK: Log nilai dan jenis $tajuk sebelum bind_param ---
            error_log("DEBUG: add_ebook.php - Value of \$tajuk before bind_param: '" . $tajuk . "'");
            error_log("DEBUG: add_ebook.php - Type of \$tajuk before bind_param: " . gettype($tajuk));
            // --- TAMAT DEBUGGING TAJUK ---

            // Bind parameter - Sesuaikan jenis dan bilangan parameter
            // "isssisiiidssi" -> 13 parameter (no, penulis, tajuk, description, file_path, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit, uploaded_by)
            // i (no), s (penulis), s (tajuk), s (description), s (file_path), i (muka_surat), i (perkataan), d (harga_rm), s (genre), s (bulan), i (tahun), s (penerbit), i (uploaded_by)
            $paramTypes = "isssisiiidssi";
            error_log("DEBUG: add_ebook.php - Binding parameters: Types '$paramTypes', Values: $no, $penulis, $tajuk, " . ($description ?? 'NULL') . ", " . ($file_path ?? 'NULL') . ", $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit, $current_user_id");
            $stmt->bind_param($paramTypes, $no, $penulis, $tajuk, $description, $file_path, $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit, $current_user_id);

            // Laksanakan pernyataan
            if ($stmt->execute()) {
                error_log("DEBUG: add_ebook.php - Statement executed successfully.");
                // Semak berapa banyak baris yang terjejas (sepatutnya 1)
                if ($stmt->affected_rows === 1) {
                    $conn->commit(); // Sahkan transaksi
                    $successMessage = "Ebook berjaya ditambah!";
                    error_log("DEBUG: Add Ebook Success: 1 row affected. Last Insert ID: " . $conn->insert_id . ". Transaction committed.");
                    // Kosongkan medan borang selepas penyerahan berjaya
                    $no = $penulis = $tajuk = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = '';
                    $description = $file_path = ''; // Kosongkan juga ini
                } else {
                    $conn->rollback(); // Gulung balik jika tiada baris terjejas
                    $errors['db_error'] = "Ebook tidak dapat ditambah. Tiada baris terjejas.";
                    error_log("DEBUG: Add Ebook Execute Warning: No rows affected. Error: " . $stmt->error . ". Transaction rolled back.");
                }
            } else {
                $conn->rollback(); // Gulung balik jika pelaksanaan gagal
                error_log("DEBUG: Add Ebook Execute Error: " . $stmt->error . " - SQL: " . $sql . ". Transaction rolled back.");
                $errors['db_error'] = "Pelaksanaan gagal: " . $stmt->error;
            }

            $stmt->close();

        } catch (Exception $e) {
            $conn->rollback(); // Gulung balik jika ada pengecualian
            $errors['db_error'] = "Ralat pangkalan data: " . htmlspecialchars($e->getMessage());
            error_log("DEBUG: Add Ebook General Error: " . $e->getMessage() . ". Transaction rolled back.");
        }
    }
}

require_once '../includes/header.php';
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

    <form method="post" action="add_ebook.php">
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

        <!-- Tambah medan untuk description dan file_path jika perlu, atau biarkan ia tidak kelihatan -->
        <!-- Contoh:
        <div class="form-group">
            <label for="description">Description:</label>
            <textarea id="description" name="description" class="form-control"><?= htmlspecialchars($description ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="file_path">File Path:</label>
            <input type="text" id="file_path" name="file_path" class="form-control" value="<?= htmlspecialchars($file_path ?? '') ?>">
        </div>
        -->

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
            <?php if (isset(
                $errors['harga_rm']
            )): ?><span class="help-block"><?= $errors['harga_rm'] ?></span><?php endif; ?>
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
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>
