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

$read_ebooks = []; // Initialize an empty array to store read ebooks

// --- Fetch Read Ebooks from Database ---
if ($conn) { // Check if database connection is successful
    // SQL query to retrieve ebooks marked as read by the current user
    // Added penulis, penerbit, and is_in_wishlist check (still needed for the badge)
    $sql_fetch_read_ebooks = "SELECT e.id, e.tajuk, e.penulis, e.penerbit, e.harga_rm,
                                      (SELECT COUNT(*) FROM wishlist w WHERE w.user_id = ? AND w.ebook_id = e.id) AS is_in_wishlist,
                                      rs.marked_as_read_at
                               FROM ebooks e
                               JOIN read_status rs ON e.id = rs.ebook_id
                               WHERE rs.user_id = ?
                               ORDER BY rs.marked_as_read_at DESC"; // Order by when it was marked as read

    if ($stmt_read_ebooks = $conn->prepare($sql_fetch_read_ebooks)) {
        $stmt_read_ebooks->bind_param("ii", $user_id, $user_id); // Bind user ID twice for wishlist subquery and main query
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

<div class="container mx-auto px-4 py-8">
    <h1 class="text-pink-500 text-3xl font-bold mb-6">My Books</h1>
    <p class="mb-6 text-gray-700">Here are the ebooks you have marked as read.</p>

    <div class="ebook-list grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (!empty($read_ebooks)): ?>
            <?php foreach ($read_ebooks as $ebook): ?>
                <div class="ebook-item bg-white p-4 rounded-lg shadow-md flex flex-col justify-between">
                    <div>
                        <a href="ebook_detail.php?id=<?php echo htmlspecialchars($ebook['id']); ?>" class="ebook-title-link text-lg font-bold text-blue-600 hover:underline">
                            <h3 class="text-pink-500"><?php echo htmlspecialchars($ebook['tajuk'] ?? ''); ?></h3>
                        </a>
                        <p class="text-gray-700"><strong>Penulis:</strong> <?php echo htmlspecialchars($ebook['penulis'] ?? ''); ?></p>
                        <p class="text-gray-700"><strong>Penerbit:</strong> <?php echo htmlspecialchars($ebook['penerbit'] ?? ''); ?></p>
                        <p class="text-gray-700">Price: RM<?php echo htmlspecialchars(number_format($ebook['harga_rm'] ?? 0, 2)); ?></p>
                        <p class="text-gray-700 mt-2">
                            Read <i class="fas fa-check-circle text-green-500"></i>
                            <?php if ($ebook['is_in_wishlist']): ?>
                                &nbsp; In Wishlist <i class="fas fa-heart text-pink-500"></i>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="ebook-actions mt-4 flex flex-col gap-2"> <a href="mark_as_unread.php?ebook_id=<?php echo htmlspecialchars($ebook['id']); ?>"
                           class="btn btn-secondary bg-gray-200 text-gray-800 px-3 py-2 rounded-md text-sm hover:bg-gray-300 text-center">
                           Mark as Unread
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full message info bg-blue-100 text-blue-700 p-4 rounded-md">
                <p>You haven't marked any ebooks as read yet. Browse the <a href="index.php" class="text-blue-500 hover:underline">Ebook Library</a> to find ebooks.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
if (isset($conn) && $conn->ping()) {
    $conn->close(); // Close the database connection
}
require_once 'includes/footer.php'; // Include the footer
?>