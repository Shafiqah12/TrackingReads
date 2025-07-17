<?php
// admin/view_ebook_details.php
// Halaman ini membolehkan pentadbir, pengurus, dan kerani melihat butiran ebook.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Mulakan sesi
require_once '../includes/db_connect.php'; // Laluan relatif ke admin/view_ebook_details.php

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
$allowedRoles = ['admin', 'manager', 'clerk'];

// Kawalan akses: Pastikan hanya peranan yang dibenarkan boleh mengakses halaman ini
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["user_role"], $allowedRoles)) {
    header("location: ../login.php"); // Arahkan ke halaman log masuk jika tidak dibenarkan
    exit;
}

// Mulakan pembolehubah untuk data ebook dan mesej ralat
$ebook = []; // Untuk menyimpan data ebook yang diambil
$errors = [];
$ebookId = null;

// --- 1. Dapatkan ID Ebook dari URL ---
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $ebookId = filter_var(trim($_GET['id']), FILTER_SANITIZE_NUMBER_INT);

    if (!is_numeric($ebookId) || $ebookId <= 0) {
        $errors['id'] = 'ID ebook tidak sah disediakan.';
        $ebookId = null; // Batalkan ID jika tidak sah
    }
} else {
    $errors['id'] = 'Tiada ID ebook ditentukan untuk dilihat.';
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
            // Assign fetched data to variables for easier use in HTML
            $no          = htmlspecialchars($ebook['no'] ?? '');
            $penulis     = htmlspecialchars($ebook['penulis'] ?? '');
            $tajuk       = htmlspecialchars($ebook['tajuk'] ?? '');
            $description = htmlspecialchars($ebook['description'] ?? '');
            $file_path   = htmlspecialchars($ebook['file_path'] ?? ''); // Full path
            $muka_surat  = htmlspecialchars($ebook['muka_surat'] ?? '');
            $perkataan   = htmlspecialchars($ebook['perkataan'] ?? '');
            $harga_rm    = htmlspecialchars(number_format($ebook['harga_rm'] ?? 0, 2)); // Format to 2 decimal places
            $genre       = htmlspecialchars($ebook['genre'] ?? '');
            $bulan       = htmlspecialchars($ebook['bulan'] ?? '');
            $tahun       = htmlspecialchars($ebook['tahun'] ?? '');
            $penerbit    = htmlspecialchars($ebook['penerbit'] ?? '');
            $uploaded_by_username = htmlspecialchars($ebook['uploaded_by_username'] ?? 'Unknown');
            $created_at_display = htmlspecialchars($ebook['created_at'] ?? 'N/A');

        } else {
            $errors['fetch'] = 'Ebook tidak ditemui dengan ID yang ditentukan.';
            $ebookId = null; // Batalkan ID jika tidak ditemui
        }
        $stmt_fetch->close();
    } else {
        $errors['db_fetch'] = 'Ralat pangkalan data menyediakan pernyataan pengambilan: ' . htmlspecialchars($conn->error);
    }
}

// Sertakan header sedia ada anda
require_once '../includes/header.php';
?>

<div class="main-content-area">
    <div class="auth-container">
        <h2>Ebook Details (ID: <?= htmlspecialchars($ebookId ?? 'N/A'); ?>)</h2>
        <p>View the comprehensive details of this ebook.</p>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <h3>Sila betulkan ralat berikut:</h3>
                <ul>
                    <?php foreach ($errors as $field => $msg): ?>
                        <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Sila kembali ke <a href="manage-ebook.php">Urus Ebook</a>.</p>
            </div>
        <?php elseif (empty($ebook)): ?>
             <div class="message info">
                <p>Ebook tidak ditemui atau tiada ID ditentukan. Sila kembali ke <a href="manage-ebook.php">Urus Ebook</a> untuk memilih ebook.</p>
            </div>
        <?php else: ?>
            <div class="ebook-details-card">
                <div class="detail-row">
                    <span class="detail-label">Image:</span>
                    <span class="detail-value">
                        <?php if ($file_path): ?>
                            <?php
                            // Determine if the file_path is an image or a document
                            $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                            $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            ?>
                            <?php if (in_array($file_extension, $image_extensions)): ?>
                                <img src="<?= $file_path; ?>" alt="Ebook Image" class="ebook-detail-image">
                            <?php else: ?>
                                <p>No image uploaded for this ebook, but a document file exists.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>No image uploaded for this ebook.</p>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">NO:</span>
                    <span class="detail-value"><?= $no ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Penulis (Author):</span>
                    <span class="detail-value"><?= $penulis ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tajuk (Title):</span>
                    <span class="detail-value"><?= $tajuk ?></span>
                </div>
                <div class="detail-row detail-description">
                    <span class="detail-label">Description:</span>
                    <span class="detail-value"><?= !empty($description) ? nl2br($description) : 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">File:</span>
                    <span class="detail-value">
                        <?php if ($file_path): ?>
                            <a href="<?= $file_path; ?>" target="_blank" class="file-link">
                                <i class="fas fa-file-alt"></i> <?= basename($file_path); ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Muka Surat (Pages):</span>
                    <span class="detail-value"><?= !empty($muka_surat) ? $muka_surat : 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Perkataan (Words):</span>
                    <span class="detail-value"><?= !empty($perkataan) ? $perkataan : 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Harga (RM):</span>
                    <span class="detail-value">RM <?= $harga_rm ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Genre:</span>
                    <span class="detail-value"><?= !empty($genre) ? $genre : 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Bulan (Month):</span>
                    <span class="detail-value"><?= !empty($bulan) ? $bulan : 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tahun (Year):</span>
                    <span class="detail-value"><?= !empty($tahun) ? $tahun : 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Penerbit (Publisher):</span>
                    <span class="detail-value"><?= !empty($penerbit) ? $penerbit : 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Uploaded By:</span>
                    <span class="detail-value"><?= $uploaded_by_username ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created At:</span>
                    <span class="detail-value"><?= $created_at_display ?></span>
                </div>
            </div>
            <div class="mt-4">
                <a href="manage-ebook.php" class="btn btn-secondary">Back to Manage Ebooks</a>
                <!-- Optional: Link to edit the ebook, only for Admin/Manager -->
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
                    <a href="edit-ebook.php?id=<?= $ebookId ?>" class="btn btn-primary">Edit Ebook</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Reusing existing styles for form-group, message, btn, etc. */
    .main-content-area {
        padding: 20px;
        max-width: 900px;
        margin: 20px auto;
        background-color: #f9f9f9;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .auth-container {
        padding: 20px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    h2 {
        color: #333;
        margin-bottom: 10px;
    }

    p {
        color: #666;
        margin-bottom: 20px;
    }

    .ebook-details-card {
        background-color: #f0f0f0;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 25px;
        margin-top: 20px;
    }

    .detail-row {
        display: flex;
        flex-wrap: wrap; /* Allows wrapping on smaller screens */
        margin-bottom: 10px;
        align-items: baseline;
    }

    .detail-label {
        font-weight: bold;
        color: #555;
        flex: 0 0 150px; /* Fixed width for labels */
        margin-right: 15px;
    }

    .detail-value {
        flex: 1; /* Takes remaining space */
        color: #333;
    }

    .detail-description .detail-value {
        white-space: pre-wrap; /* Preserves line breaks in description */
    }

    .ebook-detail-image {
        max-width: 250px;
        height: auto;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-top: 5px;
        margin-bottom: 10px;
    }

    .file-link {
        color: #A08AD3;
        text-decoration: none;
        font-weight: bold;
    }

    .file-link:hover {
        text-decoration: underline;
    }

    /* Font Awesome icon for file */
    .fas.fa-file-alt {
        margin-right: 5px;
    }

    .mt-4 {
        margin-top: 1rem;
    }

    /* Ensure buttons are styled correctly from previous CSS */
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
    .message {
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
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

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .detail-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .detail-label {
            flex: none;
            width: auto;
            margin-right: 0;
            margin-bottom: 5px;
        }
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
