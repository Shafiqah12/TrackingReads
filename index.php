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

$user_id = $_SESSION['user_id']; // <--- IMPORTANT: Get user_id for wishlist/read status

// --- Search Logic ---
$searchTerm = '';
$ebooks = [];
$searchPerformed = false;
$errorMessage = null; // Initialize error message variable

try {
    // Database connection is already established via db_connect.php, so $conn is available here.

    // Base SQL query to fetch all ebook details required for display
    // Added subqueries for 'is_in_wishlist' and 'is_read' similar to dashboard.php
    $baseSql = "SELECT e.id, e.no, e.penulis, e.tajuk, e.muka_surat, e.perkataan, e.harga_rm, e.genre, e.bulan, e.tahun, e.penerbit,
                       (SELECT COUNT(*) FROM wishlist w WHERE w.user_id = ? AND w.ebook_id = e.id) AS is_in_wishlist,
                       (SELECT COUNT(*) FROM read_status rs WHERE rs.user_id = ? AND rs.ebook_id = e.id) AS is_read
                FROM ebooks e";
    
    $whereClause = "";
    $orderByClause = " ORDER BY e.id ASC"; // Keep consistent ordering
    $limitClause = " LIMIT 100"; // Keep the limit for initial display if no search

    $params = [$user_id, $user_id]; // Parameters for the user_id bindings
    $paramTypes = "ii"; // 'i' for integer, two times

    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $searchPerformed = true;
        $searchTerm = '%' . trim($_GET['search']) . '%'; // Prepare for LIKE
        
        $whereClause = " WHERE e.tajuk LIKE ? OR e.penulis LIKE ? OR e.penerbit LIKE ?";
        $params[] = $searchTerm; // Add search term to parameters
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= "sss"; // Add 's' for string, three times
        $limitClause = ""; // Remove limit if searching
    }

    $sql_fetch_ebooks = $baseSql . $whereClause . $orderByClause . $limitClause;

    if ($stmt = $conn->prepare($sql_fetch_ebooks)) {
        // Dynamically bind parameters based on whether search is performed
        if ($searchPerformed) {
            $stmt->bind_param($paramTypes, ...$params);
        } else {
            $stmt->bind_param($paramTypes, $user_id, $user_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $ebooks[] = $row;
            }
        }
        $stmt->close();
    } else {
        throw new Exception("Error preparing SQL statement: " . $conn->error);
    }

} catch (Exception $e) {
    $errorMessage = "An error occurred: " . htmlspecialchars($e->getMessage());
    error_log("Index page error: " . $e->getMessage()); // Log the error
}

// --- Include your existing header ---
require_once 'includes/header.php';
?>

<div class="container"> <h1>Ebook Library System</h1>

    <?php if (isset($errorMessage) && $errorMessage): ?>
        <div class="message error"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="search-section">
        <form method="GET" action="index.php" class="flex flex-col md:flex-row gap-2 mb-4">
            <input
                type="text"
                name="search"
                placeholder="Search by title, penulis, or penerbit..."
                value="<?= htmlspecialchars(trim(str_replace('%', '', $searchTerm))) ?>"
                class="form-control flex-grow p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <button type="submit" class="btn btn-search">Search Ebooks</button>
            <?php if ($searchPerformed): ?>
                <a href="index.php" class="btn btn-clear-search">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>

    <h2>Ebook Results:</h2>

    <?php
    if ($searchPerformed && empty($ebooks)) {
        echo '<div class="message error">No ebooks found matching "' . htmlspecialchars(trim(str_replace('%', '', $searchTerm))) . '".</div>';
    } else if (empty($ebooks) && !$searchPerformed) {
        echo '<div class="message info">No ebooks in the database. Please import data using <a href="admin/import_excel_to_db.php" class="text-blue-500 hover:underline">import_excel_to_db.php</a>.</div>';
    } else {
        echo '<div class="ebook-list">'; // Grid container for ebook items
        foreach ($ebooks as $ebook) {
            echo '<div class="ebook-item">'; // Individual ebook card

            // --- ONLY THESE THREE LINES FOR INITIAL DISPLAY ---
            echo '<a href="ebook_detail.php?id=' . htmlspecialchars($ebook['id']) . '" class="ebook-title-link">';
            echo '<h3>' . htmlspecialchars($ebook['tajuk'] ?? '') . '</h3>'; // Ebook Title
            echo '</a>';
            echo '<p><strong>Penulis:</strong> ' . htmlspecialchars($ebook['penulis'] ?? '') . '</p>'; // Penulis (Author)
            echo '<p><strong>Penerbit:</strong> ' . htmlspecialchars($ebook['penerbit'] ?? '') . '</p>'; // Penerbit (Publisher)
            // --- END OF INITIAL DISPLAY ---

            // Wishlist and Mark as Read/Unread actions (as previously provided)
            echo '<div class="ebook-actions">';
            if ($ebook['is_in_wishlist']) {
                echo '<span class="status-badge wishlist">In Wishlist <i class="fas fa-heart"></i></span>';
                echo '<a href="remove_from_wishlist.php?ebook_id=' . htmlspecialchars($ebook['id']) . '" class="btn btn-secondary btn-sm">Remove from Wishlist</a>';
            } else {
                echo '<a href="add_to_wishlist.php?ebook_id=' . htmlspecialchars($ebook['id']) . '" class="btn btn-info btn-sm">Add to Wishlist <i class="far fa-heart"></i></a>';
            }

            if ($ebook['is_read']) {
                echo '<span class="status-badge read">Read <i class="fas fa-check-circle"></i></span>';
                echo '<a href="mark_as_unread.php?ebook_id=' . htmlspecialchars($ebook['id']) . '" class="btn btn-secondary btn-sm">Mark as Unread</a>';
            } else {
                echo '<a href="mark_as_read.php?ebook_id=' . htmlspecialchars($ebook['id']) . '" class="btn btn-success btn-sm">Mark as Read <i class="far fa-check-circle"></i></a>';
            }
            echo '</div>'; // End ebook-actions

            echo '</div>'; // End of ebook-item
        }
        echo '</div>'; // End of ebook-list
    }
    ?>
</div> <?php
// Close the database connection at the very end of the script
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
// --- Include your existing footer ---
require_once 'includes/footer.php';
?>