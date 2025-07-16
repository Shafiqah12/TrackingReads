<?php
// mybooks.php
// This page lists ebooks the user has marked as 'read'.

error_reporting(E_ALL); // Show all errors
ini_set('display_errors', 1); // Display errors directly on the page

session_start(); // Start the session to access $_SESSION variables
require_once 'includes/db_connect.php'; // Include your database connection file

// --- Access Control ---
// Redirect if user is not logged in or user_id is not set
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["user_id"])) {
    header("location: /TrackingReads/login.php"); // Corrected path
    exit;
}

// Redirect if logged-in user is an admin (admins shouldn't be on this page)
if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin") {
    header("location: /TrackingReads/admin/dashboard.php"); // Corrected path
    exit;
}

$user_id = $_SESSION['user_id']; // Get the current user's ID
// $status_message = ''; // Not needed if purchase status messages are removed

$read_ebooks = []; // Initialize an empty array to store read ebooks

// --- Fetch Read Ebooks from Database ---
if ($conn) { // Check if database connection is successful
    // SQL query to retrieve ebooks marked as read by the current user
    $sql_fetch_read_ebooks = "SELECT e.id, e.tajuk AS title, e.description, e.harga_rm AS price, e.file_path, u.username AS uploaded_by_username, e.created_at, rs.marked_as_read_at
                               FROM ebooks e
                               JOIN users u ON e.uploaded_by = u.id
                               JOIN read_status rs ON e.id = rs.ebook_id
                               WHERE rs.user_id = ?
                               ORDER BY rs.marked_as_read_at DESC"; // Order by when it was marked as read

    if ($stmt_read_ebooks = $conn->prepare($sql_fetch_read_ebooks)) {
        $stmt_read_ebooks->bind_param("i", $user_id); // Bind user ID to the query
        $stmt_read_ebooks->execute(); // Execute the query
        $result_read_ebooks = $stmt_read_ebooks->get_result(); // Get the result set

        if ($result_read_ebooks->num_rows > 0) {
            // Fetch all rows into the $read_ebooks array
            while ($row = $result_read_ebooks->fetch_assoc()) {
                $read_ebooks[] = $row;
            }
        }
        $stmt_read_ebooks->close(); // Close the statement
    } else {
        // Log database error for debugging
        error_log("Error preparing read ebooks fetch statement in mybooks.php: " . $conn->error);
    }
} else {
    error_log("Database connection not established in mybooks.php");
}

// Include the header (e.g., navigation, common CSS)
require_once 'includes/header.php'; // Ensure this path is correct
?>

<div class="dashboard-container">
    <h2>My Books</h2>
    <p>Here are the ebooks you have marked as read.</p>
    <div class="ebooks-grid">
        <?php if (!empty($read_ebooks)): ?>
            <?php foreach ($read_ebooks as $ebook): ?>
                <div class="ebook-card">
                    <h4><?php echo htmlspecialchars($ebook['title'] ?? ''); ?></h4>
                    <p class="description"><?php echo htmlspecialchars($ebook['description'] ?? ''); ?></p>
                    <p class="price">Price: RM<?php echo htmlspecialchars(number_format($ebook['price'] ?? 0, 2)); ?></p>
                    <p class="uploaded-info">
                        Uploaded by: <?php echo htmlspecialchars($ebook['uploaded_by_username'] ?? 'Unknown'); ?> on: <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($ebook['created_at'] ?? ''))); ?>
                    </p>
                    <p class="read-info">
                        Marked as read on: <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($ebook['marked_as_read_at'] ?? ''))); ?>
                    </p>
                    <?php
                    // The file path correction variable ($correct_file_path) is included here,
                    // even though the download button is removed, in case it's used for other
                    // purposes (e.g., a preview link) later.
                    $correct_file_path = str_replace('../uploads/', '/TrackingReads/uploads/', $ebook['file_path'] ?? '');
                    ?>
                    <div class="button-group">
                        <a href="/TrackingReads/mark_as_unread.php?ebook_id=<?php echo htmlspecialchars($ebook['id']); ?>" class="btn btn-secondary" target="_blank">Mark as Unread</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>You haven't marked any ebooks as read yet. Browse the <a href="/TrackingReads/dashboard.php">dashboard</a> to find ebooks.</p>
        <?php endif; ?>
    </div>
</div>

<?php
$conn->close(); // Close the database connection
require_once 'includes/footer.php'; // Include the footer
?>