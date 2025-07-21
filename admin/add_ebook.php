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

// Senarai pilihan genre
$genres = [
    'Fiction', 'Non-Fiction', 'Science Fiction', 'Fantasy', 'Mystery', 'Thriller',
    'Horror', 'Romance', 'Biography', 'Buku Resipi', 'Self-Help', 'Childrens', 'History',
    'Poetry', 'Drama', 'Humor', 'Sains & Teknologi', 'Pendidikan', 'Agama & Spriritual',
    'Bisnes & Ekonomi', 'Seni & Fotografi', 'Travel'
];
sort($genres); // Susun genre mengikut abjad

// Senarai pilihan bulan (untuk kemasukan yang lebih konsisten)
$months = [
    'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun',
    'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'
];

// Mulakan pembolehubah untuk medan borang dan mesej ralat
$no = $penulis = $tajuk = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = $description = '';
// $file_path = ''; // Dibuang kerana tiada fail ebook sebenar diuruskan
$cover_image_path = ''; // Untuk menyimpan laluan gambar cover
$errors = [];
$successMessage = '';

// Proses penyerahan borang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("DEBUG: add_ebook.php - POST request received.");

    // Bersihkan dan sahkan input
    $no           = filter_var(trim($_POST['no'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $penulis      = filter_var(trim($_POST['penulis'] ?? ''), FILTER_UNSAFE_RAW);
    $tajuk        = filter_var(trim($_POST['tajuk'] ?? ''), FILTER_UNSAFE_RAW);
    $description  = filter_var(trim($_POST['description'] ?? ''), FILTER_UNSAFE_RAW);
    // $file_path    = filter_var(trim($_POST['file_path'] ?? ''), FILTER_UNSAFE_RAW); // Dibuang
    $muka_surat   = filter_var(trim($_POST['muka_surat'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $perkataan    = filter_var(trim($_POST['perkataan'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $harga_rm     = filter_var(trim($_POST['harga_rm'] ?? ''), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $genre        = filter_var(trim($_POST['genre'] ?? ''), FILTER_UNSAFE_RAW);
    $bulan        = filter_var(trim($_POST['bulan'] ?? ''), FILTER_UNSAFE_RAW);
    $tahun        = filter_var(trim($_POST['tahun'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $penerbit     = filter_var(trim($_POST['penerbit'] ?? ''), FILTER_UNSAFE_RAW);

    // Pengendalian muat naik gambar cover
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/covers/'; // Pastikan direktori ini wujud dan boleh ditulis (chmod 775 atau 777 untuk ujian)
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true); // Cipta direktori jika tidak wujud
        }

        $fileName = basename($_FILES['cover_image']['name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        // Cipta nama fail unik untuk mengelakkan konflik
        $newFileName = uniqid('cover_', true) . '.' . $fileExtension;
        $uploadFilePath = $uploadDir . $newFileName;

        if (in_array($fileExtension, $allowedExtensions)) {
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadFilePath)) {
                $cover_image_path = '/TrackingReads/uploads/covers/' . $newFileName; // Laluan relatif untuk DB
                error_log("DEBUG: Cover image uploaded to: " . $cover_image_path);
            } else {
                $errors['cover_image'] = 'Gagal memuat naik gambar cover.';
                error_log("ERROR: Failed to move uploaded cover image. Destination: " . $uploadFilePath);
            }
        } else {
            $errors['cover_image'] = 'Jenis fail gambar cover tidak dibenarkan. Benarkan: JPG, JPEG, PNG, GIF.';
        }
    } elseif (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors besides no file being selected
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'Fail yang dimuat naik melebihi arahan upload_max_filesize dalam php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'Fail yang dimuat naik melebihi arahan MAX_FILE_SIZE yang dinyatakan dalam borang HTML.',
            UPLOAD_ERR_PARTIAL    => 'Fail yang dimuat naik hanya dimuat naik sebahagian.',
            UPLOAD_ERR_NO_TMP_DIR => 'Tiada folder sementara.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis fail ke cakera.',
            UPLOAD_ERR_EXTENSION  => 'Satu sambungan PHP menghentikan muat naik fail.'
        ];
        $error_code = $_FILES['cover_image']['error'];
        $errors['cover_image'] = $uploadErrors[$error_code] ?? 'Ralat muat naik gambar cover yang tidak diketahui: ' . $error_code;
        error_log("ERROR: Cover image upload error (code $error_code): " . ($uploadErrors[$error_code] ?? 'Unknown error'));
    } else {
        // Jika tiada fail cover dimuat naik, set laluan kepada NULL atau lalai
        // Ini penting jika cover image tidak wajib
        $cover_image_path = null;
    }


    // Pengesahan asas
    if (empty($penulis)) $errors['penulis'] = 'Penulis diperlukan.';
    if (empty($tajuk)) $errors['tajuk'] = 'Tajuk diperlukan.';
    if (empty($genre)) $errors['genre'] = 'Genre diperlukan.';
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
        $conn->begin_transaction();
        error_log("DEBUG: add_ebook.php - Transaction started.");

        try {
            // Sediakan pernyataan INSERT - PASTIKAN SEMUA KOLUM YANG BETUL DISERTAKAN
            // Kolum `file_path` telah DIBUANG dari pernyataan SQL ini
            $sql = "INSERT INTO ebooks (no, penulis, tajuk, description, cover_image_path, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                error_log("DEBUG: Add Ebook Prepare Error: " . $conn->error . " - SQL: " . $sql);
                throw new Exception("Penyediaan gagal: " . $conn->error);
            }

            // Bind parameter - Sesuaikan jenis dan bilangan parameter
            // no (i), penulis (s), tajuk (s), description (s), cover_image_path (s), muka_surat (i), perkataan (i), harga_rm (d), genre (s), bulan (s), tahun (i), penerbit (s), uploaded_by (i)
            // Perhatikan string jenis telah dipendekkan (tiada 's' untuk file_path)
            $paramTypes = "issssiidssi"; // Mengeluarkan satu 's' untuk file_path
            error_log("DEBUG: add_ebook.php - Binding parameters: Types '$paramTypes', Values: " . implode(", ", [$no, $penulis, $tajuk, $description, $cover_image_path, $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit, $current_user_id]));

            // Pastikan bilangan argumen dalam bind_param sepadan dengan $paramTypes dan $sql
            $stmt->bind_param($paramTypes, $no, $penulis, $tajuk, $description, $cover_image_path, $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit, $current_user_id);

            // Laksanakan pernyataan
            if ($stmt->execute()) {
                error_log("DEBUG: add_ebook.php - Statement executed successfully.");
                if ($stmt->affected_rows === 1) {
                    $conn->commit(); // Sahkan transaksi
                    $successMessage = "Ebook berjaya ditambah!";
                    error_log("DEBUG: Add Ebook Success: 1 row affected. Last Insert ID: " . $conn->insert_id . ". Transaction committed.");
                    // Kosongkan medan borang selepas penyerahan berjaya
                    $no = $penulis = $tajuk = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = $description = $cover_image_path = '';
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

<div class="container content">
    <h2>Tambah Ebook Baharu</h2>

    <?php if ($successMessage): ?>
        <div class="message success"><?= htmlspecialchars($successMessage) ?></div>
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

    <form method="post" action="add_ebook.php" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group col-md-5">
                <label for="penulis">Penulis (Author):</label>
                <input type="text" id="penulis" name="penulis" class="form-control" value="<?= htmlspecialchars($penulis) ?>" required>
                <?php if (isset($errors['penulis'])): ?><span class="help-block"><?= htmlspecialchars($errors['penulis']) ?></span><?php endif; ?>
            </div>
            <div class="form-group col-md-4">
                <label for="penerbit">Penerbit (Publisher):</label>
                <input type="text" id="penerbit" name="penerbit" class="form-control" value="<?= htmlspecialchars($penerbit) ?>">
                <?php if (isset($errors['penerbit'])): ?><span class="help-block"><?= htmlspecialchars($errors['penerbit']) ?></span><?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="tajuk">Tajuk (Title):</label>
            <input type="text" id="tajuk" name="tajuk" class="form-control" value="<?= htmlspecialchars($tajuk) ?>" required>
            <?php if (isset($errors['tajuk'])): ?><span class="help-block"><?= htmlspecialchars($errors['tajuk']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="description">Sinopsis (Description):</label>
            <textarea id="description" name="description" class="form-control" rows="5"><?= htmlspecialchars($description) ?></textarea>
            <?php if (isset($errors['description'])): ?><span class="help-block"><?= htmlspecialchars($errors['description']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="cover_image">Gambar Cover Ebook:</label>
            <div class="file-input-display-wrapper">
                <label for="cover_image" class="custom-file-label">Pilih Fail</label>
                <span id="file-name-display"><?= !empty($cover_image_path) ? htmlspecialchars(basename($cover_image_path)) : 'Tiada fail dipilih' ?></span>
                <input type="file" id="cover_image" name="cover_image" class="form-control-file" onchange="updateFileName(this)">
            </div>
            <?php if (isset($errors['cover_image'])): ?><span class="help-block"><?= htmlspecialchars($errors['cover_image']) ?></span><?php endif; ?>
            <?php if (!empty($cover_image_path)): ?>
                <p>Gambar semasa: <img src="<?= htmlspecialchars($cover_image_path) ?>" alt="Cover Image" style="max-width: 100px; height: auto; margin-top: 10px;"></p>
            <?php endif; ?>
        </div>

        <script>
            function updateFileName(input) {
                var fileNameDisplay = document.getElementById('file-name-display');
                if (input.files.length > 0) {
                    fileNameDisplay.textContent = input.files[0].name;
                } else {
                    fileNameDisplay.textContent = 'Tiada fail dipilih';
                }
            }
        </script>

        <div class="form-row">
    <div class="form-group col-md-3">
        <label for="muka_surat">Muka Surat (Pages):</label>
        <input type="number" id="muka_surat" name="muka_surat" class="form-control form-control-sm" value="<?= htmlspecialchars($muka_surat) ?>">
        <?php if (isset($errors['muka_surat'])): ?><span class="help-block"><?= htmlspecialchars($errors['muka_surat']) ?></span><?php endif; ?>
    </div>
    <div class="form-group col-md-3">
        <label for="perkataan">Perkataan (Words):</label>
        <input type="number" id="perkataan" name="perkataan" class="form-control form-control-sm" value="<?= htmlspecialchars($perkataan) ?>">
        <?php if (isset($errors['perkataan'])): ?><span class="help-block"><?= htmlspecialchars($errors['perkataan']) ?></span><?php endif; ?>
    </div>
    <div class="form-group col-md-3">
        <label for="harga_rm">Harga (RM):</label>
        <input type="number" step="0.01" id="harga_rm" name="harga_rm" class="form-control form-control-sm" value="<?= htmlspecialchars($harga_rm) ?>">
        <?php if (isset($errors['harga_rm'])): ?><span class="help-block"><?= htmlspecialchars($errors['harga_rm']) ?></span><?php endif; ?>
    </div>
    <div class="form-group col-md-3">
        <label for="tahun">Tahun (Year):</label>
        <input type="number" id="tahun" name="tahun" class="form-control form-control-sm" value="<?= htmlspecialchars($tahun) ?>">
        <?php if (isset($errors['tahun'])): ?><span class="help-block"><?= htmlspecialchars($errors['tahun']) ?></span><?php endif; ?>
    </div>
</div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="genre">Genre:</label>
                <select id="genre" name="genre" class="form-control" required>
                    <option value="">-- Sila Pilih Genre --</option>
                    <?php foreach ($genres as $g): ?>
                        <option value="<?= htmlspecialchars($g) ?>" <?= ($genre === $g) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['genre'])): ?><span class="help-block"><?= htmlspecialchars($errors['genre']) ?></span><?php endif; ?>
            </div>
            <div class="form-group col-md-6">
                <label for="bulan">Bulan (Month):</label>
                <select id="bulan" name="bulan" class="form-control">
                    <option value="">-- Sila Pilih Bulan --</option>
                    <?php foreach ($months as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= ($bulan === $m) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['bulan'])): ?><span class="help-block"><?= htmlspecialchars($errors['bulan']) ?></span><?php endif; ?>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Tambah Ebook</button>
    </form>
</div>

<?php
// Close database connection if it's open
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>