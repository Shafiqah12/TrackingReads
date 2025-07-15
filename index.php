<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Start the session at the very beginning of the script
require_once 'includes/db_connect.php'; // Include your existing database connection file

// Redirect if not logged in (adjust this logic based on your system's needs)
// If you want the library to be public, you can remove or modify this block.
if (empty($_SESSION['loggedin'])) {
    header("Location: login.php"); // Redirect to your login page if not authenticated
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

        // SQL query to search for ebooks by tajuk (title), penulis (author), or penerbit (publisher)
        // Using LIKE for partial matches (case-insensitive in MySQL by default for many collations)
        $sql = "SELECT * FROM ebooks WHERE tajuk LIKE '%$searchTerm%' OR penulis LIKE '%$searchTerm%' OR penerbit LIKE '%$searchTerm%' ORDER BY tajuk ASC";
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
        // Display all ebooks if no search term is provided initially (or after clearing search)
        // Limiting to 100 for initial display performance; remove LIMIT if you want all by default
        $sql = "SELECT * FROM ebooks ORDER BY tajuk ASC LIMIT 100";
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
    // In a production environment, you would log $e->getTraceAsString() for debugging.
}

// --- Include your existing header.php for the site's top navigation and common HTML head ---
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
        echo '<div class="message info">No ebooks in the database. Please import data using <a href="import_excel_to_db.php" class="text-blue-500 hover:underline">import_excel_to_db.php</a>.</div>';
    } else {
        echo '<div class="ebook-list">'; // Grid container for ebook items
        foreach ($ebooks as $ebook) {
            echo '<div class="ebook-item">'; // Individual ebook card
            echo '<h3>' . htmlspecialchars($ebook['tajuk']) . '</h3>';
            echo '<p><strong>Penulis:</strong> ' . htmlspecialchars($ebook['penulis']) . '</p>';
            echo '<p><strong>Genre:</strong> ' . htmlspecialchars($ebook['genre']) . '</p>';
            echo '<p><strong>Penerbit:</strong> ' . htmlspecialchars($ebook['penerbit'] ?? 'N/A') . '</p>'; // Display penerbit, with fallback
            echo '<p><strong>Muka Surat:</strong> ' . htmlspecialchars($ebook['muka_surat']) . '</p>';
            echo '<p><strong>Harga (RM):</strong> ' . htmlspecialchars(number_format($ebook['harga_rm'], 2)) . '</p>';
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
// --- Include your existing footer.php for the site's bottom content ---
require_once 'includes/footer.php';
?>