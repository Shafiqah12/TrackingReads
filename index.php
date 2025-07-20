<?php
// index.php
// Halaman utama sistem perpustakaan ebook.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Mulakan sesi
require_once 'includes/db_connect.php'; // Laluan relatif ke index.php

// Kawalan akses: Pastikan hanya pengguna dengan peranan 'user' yang boleh mengakses halaman ini.
// Manager dan Clerk tidak dibenarkan di sini, mereka akan melihat data ebook melalui antaramuka lain.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "user") {
    // Jika bukan 'user' atau tidak log masuk, arahkan ke halaman log masuk
    header("location: login.php"); 
    exit;
}

// ... (Rest of your existing index.php code below) ...
// This includes the search functionality, ebook display, etc.

// Example of how your existing index.php might continue:

// Initialize variables for search and ebook results
$search_query = '';
$ebook_results = [];

// Process search form submission
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_query'])) {
    $search_query = filter_var(trim($_GET['search_query']), FILTER_UNSAFE_RAW);

    if (!empty($search_query)) {
        // Prepare a search query (example, adjust according to your database schema)
        $sql_search = "SELECT id, no, penulis, tajuk, description, file_path, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit FROM ebooks WHERE tajuk LIKE ? OR penulis LIKE ? OR penerbit LIKE ?";
        if ($stmt_search = $conn->prepare($sql_search)) {
            $param_search = "%" . $search_query . "%";
            $stmt_search->bind_param("sss", $param_search, $param_search, $param_search);
            $stmt_search->execute();
            $result_search = $stmt_search->get_result();
            while ($row = $result_search->fetch_assoc()) {
                $ebook_results[] = $row;
            }
            $stmt_search->close();
        } else {
            // Handle database error
            echo "Error preparing search statement: " . htmlspecialchars($conn->error);
        }
    }
}

// Include header (assuming you have a header.php)
require_once 'includes/header.php';
?>

<div class="main-content-area">
    <div class="auth-container">
        <h2>EBOOK Library System</h2>
        <p>Search for ebooks by title, author, or publisher.</p>

        <form method="GET" action="index.php" class="search-form">
            <div class="form-group">
                <input type="text" name="search_query" class="form-control" placeholder="Search by title, penulis, or penerbit..." value="<?= htmlspecialchars($search_query) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Search Ebooks</button>
        </form>

        <h3>Ebook Results:</h3>
        <div class="ebook-grid">
            <?php if (!empty($ebook_results)): ?>
                <?php foreach ($ebook_results as $ebook): ?>
                    <div class="ebook-card">
                        <?php if ($ebook['file_path']): ?>
                            <img src="<?= htmlspecialchars($ebook['file_path']); ?>" alt="Ebook Image" class="ebook-image">
                        <?php else: ?>
                            <div class="ebook-image-placeholder">No Image</div>
                        <?php endif; ?>
                        <h4><?= htmlspecialchars($ebook['tajuk']); ?></h4>
                        <p>Penulis: <?= htmlspecialchars($ebook['penulis']); ?></p>
                        <p>Penerbit: <?= htmlspecialchars($ebook['penerbit']); ?></p>
                        <button class="btn btn-secondary">Add to Wishlist <i class="fas fa-heart"></i></button>
                        <button class="btn btn-secondary">Mark as Read <i class="fas fa-check-circle"></i></button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No ebooks found or no search performed.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

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

    /* Ebook Grid Specific Styles */
    .ebook-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .ebook-card {
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .ebook-image {
        max-width: 100%;
        height: 200px; /* Fixed height for consistency */
        object-fit: contain; /* Ensures image fits without cropping, maintaining aspect ratio */
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .ebook-image-placeholder {
        width: 100%;
        height: 200px;
        background-color: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #888;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .ebook-card h4 {
        margin-top: 0;
        margin-bottom: 10px;
        color: #333;
    }

    .ebook-card p {
        font-size: 0.9em;
        color: #555;
        margin-bottom: 5px;
    }

    .ebook-card .btn {
        width: calc(100% - 10px); /* Adjust for margin */
        margin-top: 10px;
    }
</style>

<?php
// Tutup sambungan pangkalan data pada akhir skrip
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
// Sertakan footer sedia ada anda
require_once 'includes/footer.php';
?>
