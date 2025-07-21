<?php
// admin/view-ebook-details.php
// Halaman ini memaparkan butiran lengkap ebook.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Laluan relatif ke admin/view-ebook-details.php

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
$allowedRoles = ['admin', 'manager', 'clerk'];

// Kawalan akses: Pastikan hanya peranan yang dibenarkan boleh mengakses halaman ini
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["user_role"], $allowedRoles)) {
    header("location: ../login.php"); // Arahkan ke halaman log masuk jika tidak dibenarkan
    exit;
}

$ebook = null;
$errorMessage = null;

// Semak jika ID ebook disediakan dalam URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $ebookId = filter_var(trim($_GET['id']), FILTER_SANITIZE_NUMBER_INT);

    if (!is_numeric($ebookId) || $ebookId <= 0) {
        $errorMessage = 'ID ebook tidak sah disediakan.';
    }
} else {
    $errorMessage = 'Tiada ID ebook ditentukan untuk dilihat.';
}

// Jika ID sah, ambil butiran ebook dari pangkalan data
if (empty($errorMessage)) {
    try {
        $sql_fetch_ebook = "SELECT e.id, e.no, e.penulis, e.tajuk, e.description, e.file_path,
                                 e.muka_surat, e.perkataan, e.harga_rm, e.genre, e.bulan, e.tahun, e.penerbit,
                                 u.username AS uploaded_by_username, e.created_at
                           FROM ebooks e
                           LEFT JOIN users u ON e.uploaded_by = u.id
                           WHERE e.id = ?";

        if ($stmt_fetch_ebook = $conn->prepare($sql_fetch_ebook)) {
            $stmt_fetch_ebook->bind_param("i", $ebookId);
            $stmt_fetch_ebook->execute();
            $result_ebook = $stmt_fetch_ebook->get_result();

            if ($result_ebook->num_rows === 1) {
                $ebook = $result_ebook->fetch_assoc();
            } else {
                $errorMessage = "Ebook tidak ditemui dengan ID " . htmlspecialchars($ebookId) . ".";
            }
            $stmt_fetch_ebook->close();
        } else {
            $errorMessage = "Ralat pangkalan data menyediakan pernyataan: " . htmlspecialchars($conn->error);
        }
    } catch (Exception $e) {
        $errorMessage = "Ralat mengambil data ebook: " . htmlspecialchars($e->getMessage());
    }
}

require_once '../includes/header.php';
?>

<div class="main-content-area">
    <div class="auth-container">
        <h2>Ebook Details</h2>
        <p>Detailed information about the ebook.</p>

        <?php if ($errorMessage): ?>
            <div class="message error"><?= $errorMessage ?></div>
            <p class="text-center"><a href="manage-ebook.php" class="btn btn-secondary">Back to Manage Ebooks</a></p>
        <?php elseif ($ebook): ?>
            <div class="ebook-details-card">
                <div class="ebook-image-container">
                    <?php 
                    $image_src_path = ''; // Path for the <img> tag (browser URL)
                    $file_exists_on_server = false; // Flag to check if file exists on server

                    if (!empty($ebook['file_path'])) {
                        $image_src_path = htmlspecialchars($ebook['file_path']); 
                        
                        // Construct the absolute path for PHP's file_exists() check
                        // Convert all forward slashes to backslashes for Windows file system check
                        // Use DIRECTORY_SEPARATOR for cross-platform compatibility
                        $normalized_file_path = str_replace('/', DIRECTORY_SEPARATOR, str_replace('../', '', $ebook['file_path']));
                        $absolute_file_path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'TrackingReads' . DIRECTORY_SEPARATOR . $normalized_file_path;
                        
                        // Check if the file actually exists on the server's file system
                        if (file_exists($absolute_file_path)) {
                            $file_exists_on_server = true;
                        } else {
                            // Log the path PHP is checking if it fails, for further debugging
                            error_log("File NOT found at (absolute): " . $absolute_file_path);
                        }
                    }
                    ?>
                    <?php if (!empty($image_src_path) && $file_exists_on_server): ?>
                        <img src="<?= $image_src_path; ?>" alt="Ebook Image" class="ebook-detail-image">
                    <?php else: ?>
                        <div class="no-image-placeholder">No Image Available</div>
                    <?php endif; ?>
                </div>
                
                <div class="ebook-info-grid">
                    <div class="grid-label">ID</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['id'] ?? 'N/A') ?></div>

                    <div class="grid-label">NO</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['no'] ?? 'N/A') ?></div>

                    <div class="grid-label">Tajuk (Title)</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['tajuk'] ?? 'N/A') ?></div>

                    <div class="grid-label">Penulis (Author)</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['penulis'] ?? 'N/A') ?></div>

                    <div class="grid-label">Penerbit (Publisher)</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['penerbit'] ?? 'N/A') ?></div>

                    <div class="grid-label">Harga (RM)</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars(number_format($ebook['harga_rm'] ?? 0, 2)) ?></div>

                    <div class="grid-label">Muka Surat (Pages)</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['muka_surat'] ?? 'N/A') ?></div>

                    <div class="grid-label">Perkataan (Words)</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['perkataan'] ?? 'N/A') ?></div>

                    <div class="grid-label">Genre</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['genre'] ?? 'N/A') ?></div>

                    <div class="grid-label">Bulan (Month)</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['bulan'] ?? 'N/A') ?></div>

                    <div class="grid-label">Tahun (Year)</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['tahun'] ?? 'N/A') ?></div>

                    <div class="grid-label">Uploaded By</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['uploaded_by_username'] ?? 'Unknown') ?></div>

                    <div class="grid-label">Created At</div>
                    <div class="grid-colon">:</div>
                    <div class="grid-value"><?= htmlspecialchars($ebook['created_at'] ?? 'N/A') ?></div>
                </div> <!-- /ebook-info-grid -->
                <h3 class="description-heading">Description:</h3>
                <div class="ebook-description-box">
                    <?= nl2br(htmlspecialchars($ebook['description'] ?? 'No description provided.')) ?>
                </div>
            </div>
            <div class="mt-4 text-center">
                <a href="manage-ebook.php" class="btn btn-secondary">Back to Manage Ebooks</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Styles for this specific page, placed here to ensure they apply */
    body, html {
        /* Remove any global text-align that might be centering things */
        text-align: initial; 
    }

    .main-content-area {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .auth-container {
        background-color: #ffffff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        text-align: left !important; /* THIS IS THE KEY FIX for description text alignment */
    }

    h2 {
        color: #A08AD3;
        text-align: center;
        margin-bottom: 15px;
        font-size: 2em;
    }

    p {
        /* This paragraph is the "Detailed information about the ebook." below the title.
           Keep it centered as per previous screenshots. */
        text-align: center; 
        margin-bottom: 20px;
        color: #666;
    }

    .ebook-details-card {
        display: flex;
        flex-direction: column; /* Image always on top of info */
        align-items: center; /* Center image and info horizontally */
        gap: 20px; /* Space between image and info section */
        background-color: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        margin-top: 20px;
    }
    
    .ebook-image-container {
        flex-shrink: 0;
        width: 100%;
        max-width: 250px; /* Control image container width */
        display: flex;
        justify-content: center;
        align-items: center;
        border: 1px solid #eee;
        border-radius: 8px;
        overflow: hidden;
        background-color: #f9f9f9;
        min-height: 200px;
    }
    .ebook-detail-image {
        max-width: 100%;
        height: auto;
        display: block;
        border-radius: 5px;
    }
    .no-image-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        color: #888;
        font-style: italic;
        background-color: #f0f0f0;
        text-align: center;
        padding: 20px;
    }

    /* Grid Layout for Ebook Info to ensure perfect alignment */
    .ebook-info-grid {
        display: grid;
        /* Three columns: label (auto-width), colon (fixed width), value (takes remaining space) */
        grid-template-columns: max-content max-content 1fr; 
        gap: 8px 5px; /* Row gap, Column gap (reduced gap between colon and value) */
        width: 100%; /* Take full width of its parent */
        max-width: 600px; /* Max width for the info grid itself */
        margin-top: 15px; /* Space between image and info grid */
    }

    .grid-label {
        font-weight: bold; /* Labels are bold */
        color: #555;
        text-align: left; /* Align labels to the left within their cell */
    }

    .grid-colon {
        font-weight: bold; /* Make colon bold too for consistency with label */
        color: #555;
        text-align: center; /* Center the colon within its small column */
    }

    .grid-value {
        color: #333;
        word-wrap: break-word; /* Ensure long values wrap */
        text-align: left; /* Align values to the left within their cell */
    }

    .description-heading {
        text-align: left;
        margin-top: 20px;
        margin-bottom: 10px;
        font-weight: bold; /* Keep description heading bold */
        color: #555;
        font-size: 1.2em;
        width: 100%;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .ebook-description-box {
        background-color: #f8f8f8;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        padding: 15px;
        margin-top: 10px; 
        white-space: pre-wrap;
        word-wrap: break-word;
        color: #444;
        width: 100%;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        text-align: left !important; /* THIS IS THE KEY FIX for description text alignment */
    }

    .text-center {
        text-align: center;
    }
    .mt-4 {
        margin-top: 1.5rem;
    }
    /* Re-using existing button styles */
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
</style>

<?php
// Tutup sambungan pangkalan data pada akhir skrip
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>
