<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Start the session
require_once 'includes/db_connect.php'; // Use your existing database connection

// Redirect if not logged in (adjust this logic based on your system's needs)
if (empty($_SESSION['loggedin'])) {
    header("Location: login.php"); // Redirect to your login page
    exit;
}

// --- Search Logic ---
$searchTerm = '';
$ebooks = [];
$searchPerformed = false;
$errorMessage = null; // Initialize error message variable

try {
    // Database connection is already established via db_connect.php, so $conn is available here.

    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $searchPerformed = true;
        // Sanitize the search term to prevent SQL injection
        $searchTerm = $conn->real_escape_string(trim($_GET['search']));

        // SQL query to search for ebooks by title, author, or publisher
        // MODIFIED: ORDER BY id ASC for consistency
        $sql = "SELECT id, no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit FROM ebooks WHERE tajuk LIKE '%$searchTerm%' OR penulis LIKE '%$searchTerm%' OR penerbit LIKE '%$searchTerm%' ORDER BY id ASC";
        $result = $conn->query($sql);

        if ($result === false) {
            // Handle SQL query execution error
            throw new Exception("SQL Query Error: " . $conn->error);
        }

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $ebooks[] = $row;
            }
        }
    } else {
        // Display all ebooks if no search term is provided initially
        // MODIFIED: ORDER BY id ASC for consistency
        $sql = "SELECT id, no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit FROM ebooks ORDER BY id ASC LIMIT 100";
        $result = $conn->query($sql);

        if ($result === false) {
            // Handle SQL query execution error
            throw new Exception("SQL Query Error: " . $conn->error);
        }

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $ebooks[] = $row;
            }
        }
    }

} catch (Exception $e) {
    // Catch database or other general errors
    $errorMessage = "An error occurred: " . htmlspecialchars($e->getMessage());
}

// --- Include your existing header ---
require_once 'includes/header.php';
?>

<div class="container"> <!-- Your main content container, styled by style.css -->
    <h1>Ebook Library System</h1>

    <?php if (isset($errorMessage) && $errorMessage): ?>
        <div class="message error"><?= $errorMessage ?></div>
    <?php endif; ?>

    <!-- Search Form, styled by style.css -->
    <div class="search-section">
        <form method="GET" action="index.php" class="flex flex-col md:flex-row gap-2 mb-4">
            <input
                type="text"
                name="search"
                placeholder="Search by title, penulis, or penerbit..."
                value="<?= htmlspecialchars($searchTerm) ?>"
                class="form-control flex-grow p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <button type="submit" class="btn btn-search">Search Ebooks</button>
            <?php if ($searchPerformed): ?>
                <a href="index.php" class="btn btn-clear-search">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Ebook Results Display, styled by style.css -->
    <h2>Ebook Results:</h2>

    <?php
    if ($searchPerformed && empty($ebooks)) {
        echo '<div class="message error">No ebooks found matching "' . htmlspecialchars($searchTerm) . '".</div>';
    } else if (empty($ebooks) && !$searchPerformed) {
        // This message appears if the database is empty and no search was performed
        echo '<div class="message info">No ebooks in the database. Please import data using <a href="admin/import_excel_to_db.php" class="text-blue-500 hover:underline">import_excel_to_db.php</a>.</div>'; // Corrected path to import script
    } else {
        echo '<div class="ebook-list">'; // Grid container for ebook items
        foreach ($ebooks as $ebook) {
            echo '<div class="ebook-item">'; // Individual ebook card
            // MODIFIED: Added ?? '' for string fields, ?? 0 for numeric fields before htmlspecialchars
            echo '<h3>' . htmlspecialchars($ebook['tajuk'] ?? '') . '</h3>'; // Title
            echo '<p><strong>NO:</strong> ' . htmlspecialchars($ebook['no'] ?? '') . '</p>'; // NO field
            echo '<p><strong>Penulis:</strong> ' . htmlspecialchars($ebook['penulis'] ?? '') . '</p>'; // Author
            echo '<p><strong>Genre:</strong> ' . htmlspecialchars($ebook['genre'] ?? '') . '</p>'; // Genre
            echo '<p><strong>Penerbit:</strong> ' . htmlspecialchars($ebook['penerbit'] ?? '') . '</p>'; // Publisher
            echo '<p><strong>Muka Surat:</strong> ' . htmlspecialchars($ebook['muka_surat'] ?? '') . '</p>'; // Pages
            echo '<p><strong>Perkataan:</strong> ' . htmlspecialchars($ebook['perkataan'] ?? '') . '</p>'; // Words
            echo '<p><strong>Harga (RM):</strong> ' . htmlspecialchars(number_format($ebook['harga_rm'] ?? 0, 2)) . '</p>'; // Price
            echo '<p><strong>Bulan:</strong> ' . htmlspecialchars($ebook['bulan'] ?? '') . '</p>'; // Month
            echo '<p><strong>Tahun:</strong> ' . htmlspecialchars($ebook['tahun'] ?? '') . '</p>'; // Year

            // Add more details from your database columns as needed
            echo '</div>'; // End of ebook-item
        }
        echo '</div>'; // End of ebook-list
    }
    ?>
</div> <!-- End of .container -->

<?php
// Close the database connection at the very end of the script
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
// --- Include your existing footer ---
require_once 'includes/footer.php';
?>
