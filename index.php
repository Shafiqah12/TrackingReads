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

// Initialize variables for search and ebook results
$search_query = '';
$ebook_results = [];
$sql_where_clause = '';
$params = [];
$param_types = '';

// Process search form submission
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_query'])) {
    $search_query = filter_var(trim($_GET['search_query']), FILTER_UNSAFE_RAW);

    if (!empty($search_query)) {
        $sql_where_clause = " WHERE tajuk LIKE ? OR penulis LIKE ? OR penerbit LIKE ?";
        $param_search = "%" . $search_query . "%";
        $params = [$param_search, $param_search, $param_search];
        $param_types = "sss";
    }
}

// Prepare the SQL query to fetch ebooks
$sql_fetch_ebooks = "SELECT id, no, penulis, tajuk, description, file_path, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit FROM ebooks" . $sql_where_clause;

if ($stmt_fetch_ebooks = $conn->prepare($sql_fetch_ebooks)) {
    if (!empty($params)) {
        $stmt_fetch_ebooks->bind_param($param_types, ...$params);
    }
    $stmt_fetch_ebooks->execute();
    $result_fetch_ebooks = $stmt_fetch_ebooks->get_result();
    while ($row = $result_fetch_ebooks->fetch_assoc()) {
        $ebook_results[] = $row;
    }
    $stmt_fetch_ebooks->close();
} else {
    // Handle database error
    error_log("Error preparing ebook fetch statement: " . $conn->error);
    echo "<p class='message error'>Error fetching ebooks. Please try again later.</p>";
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
                        <?php
                        // The 'file_path' in DB is like '/TrackingReads/ebooksimage/imagename.jpg'
                        $image_src_for_browser = htmlspecialchars($ebook['file_path'] ?? '');
                        $file_exists_on_server = false;
                        $absolute_file_path = ''; // Initialize

                        if (!empty($ebook['file_path'])) {
                            // Trim leading slash from the database path for consistent concatenation
                            // This ensures the path formed for file_exists() is correct.
                            // Example: DOCUMENT_ROOT = D:\xampp\htdocs\
                            // DB file_path = /TrackingReads/ebooksimage/image.jpg
                            // Trimmed path = TrackingReads/ebooksimage/image.jpg
                            // Combined: D:\xampp\htdocs\TrackingReads\ebooksimage\image.jpg (which is correct)
                            $normalized_db_path = ltrim($ebook['file_path'], '/');
                            $absolute_file_path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized_db_path);
                            
                            // Remove any potential double slashes that might arise from concatenation
                            $absolute_file_path = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $absolute_file_path);

                            // Check if the file physically exists on the server
                            if (file_exists($absolute_file_path)) {
                                $file_exists_on_server = true;
                            } else {
                                // For debugging: log paths that fail
                                error_log("index.php: Image file not found at (file_exists failed): " . $absolute_file_path);
                            }
                        }
                        ?>
                        <?php
                        if (!empty($image_src_for_browser) && $file_exists_on_server) {
                            echo '<img src="' . $image_src_for_browser . '" alt="Ebook Cover" class="ebook-image">';
                        } else {
                            echo '<div class="ebook-image-placeholder">No Image Available</div>';
                        }
                        ?>
                        <h4><?= htmlspecialchars($ebook['tajuk'] ?? 'N/A'); ?></h4>
                        <p>Penulis: <?= htmlspecialchars($ebook['penulis'] ?? 'N/A'); ?></p>
                        <p>Penerbit: <?= htmlspecialchars($ebook['penerbit'] ?? 'N/A'); ?></p>
                        <p>Harga: RM<?= htmlspecialchars(number_format($ebook['harga_rm'] ?? 0, 2)); ?></p>
                        
                        <button class="btn btn-secondary add-to-wishlist-btn" data-ebook-id="<?= htmlspecialchars($ebook['id']); ?>">
                            Add to Wishlist <i class="fas fa-heart"></i>
                        </button>
                        <button class="btn btn-secondary mark-as-read-btn" data-ebook-id="<?= htmlspecialchars($ebook['id']); ?>">
                            Mark as Read <i class="fas fa-check-circle"></i>
                        </button>
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
    /* Add success/info styles for buttons if you want visual feedback */
    .btn-success {
        background-color: #28a745; /* Green */
        color: white;
    }
    .btn-info {
        background-color: #17a2b8; /* Blue-green */
        color: white;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Event listener for Add to Wishlist buttons
        document.querySelectorAll('.add-to-wishlist-btn').forEach(button => {
            button.addEventListener('click', function() {
                const ebookId = this.dataset.ebookId; // Get ebook ID from data-attribute
                if (!ebookId) {
                    alert('Ebook ID not found!');
                    return;
                }
                
                this.disabled = true; // Disable button to prevent multiple clicks
                const originalText = this.innerHTML;
                this.innerHTML = 'Adding...'; // Change button text

                fetch('process_ajax_wishlist.php', { // AJAX call to your PHP script
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ebook_id=${ebookId}&action=add_wishlist` // Data to send
                })
                .then(response => response.json()) // Parse JSON response
                .then(data => {
                    if (data.success) {
                        alert(data.message); // Show success message
                        this.innerHTML = 'Added! <i class="fas fa-check"></i>'; // Update button text/icon
                        this.classList.remove('btn-secondary');
                        this.classList.add('btn-success'); // Change button style
                        this.disabled = true; // Keep disabled if successful
                    } else {
                        alert('Error: ' + data.message); // Show error message
                        this.innerHTML = originalText; // Revert button text
                        this.disabled = false; // Re-enable button
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during the request.');
                    this.innerHTML = originalText;
                    this.disabled = false;
                });
            });
        });

        // Event listener for Mark as Read buttons (similar logic)
        document.querySelectorAll('.mark-as-read-btn').forEach(button => {
            button.addEventListener('click', function() {
                const ebookId = this.dataset.ebookId;
                if (!ebookId) {
                    alert('Ebook ID not found!');
                    return;
                }

                this.disabled = true;
                const originalText = this.innerHTML;
                this.innerHTML = 'Marking...';

                fetch('process_ajax_wishlist.php', { // AJAX call to your PHP script
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ebook_id=${ebookId}&action=mark_read`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        this.innerHTML = 'Read! <i class="fas fa-check-circle"></i>';
                        this.classList.remove('btn-secondary');
                        this.classList.add('btn-info'); 
                        this.disabled = true;
                    } else {
                        alert('Error: ' + data.message);
                        this.innerHTML = originalText;
                        this.classList.remove('btn-info'); // Revert info style if it was applied
                        this.classList.add('btn-secondary'); // Revert to secondary
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during the request.');
                    this.innerHTML = originalText;
                    this.classList.remove('btn-info'); // Revert info style
                    this.classList.add('btn-secondary'); // Revert to secondary
                    this.disabled = false;
                });
            });
        });
    });
</script>

<?php
// Tutup sambungan pangkalan data pada akhir skrip
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
// Sertakan footer sedia ada anda
require_once 'includes/footer.php';
?>