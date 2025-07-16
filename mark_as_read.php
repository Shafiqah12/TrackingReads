<?php
// mark_as_read.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_GET['ebook_id'])) {
    header("location: /TrackingReads/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$ebook_id = filter_input(INPUT_GET, 'ebook_id', FILTER_VALIDATE_INT);

if ($ebook_id && $conn) {
    // Correct INSERT statement: Inserts user_id and ebook_id.
    // The 'marked_as_read_at' column will automatically be set by CURRENT_TIMESTAMP.
    // ON DUPLICATE KEY UPDATE prevents an error if the user already marked it as read.
    $sql = "INSERT INTO read_status (user_id, ebook_id) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE marked_as_read_at = CURRENT_TIMESTAMP"; // Update timestamp if already exists

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $user_id, $ebook_id);
        if ($stmt->execute()) {
            // Success: Redirect back to the ebook detail page or index
            header("location: ebook_detail.php?id=" . $ebook_id . "&status=success&message=" . urlencode("Ebook marked as read."));
        } else {
            // Error during execution
            error_log("Error marking ebook as read: " . $stmt->error);
            header("location: ebook_detail.php?id=" . $ebook_id . "&status=error&message=" . urlencode("Error marking ebook as read: " . $stmt->error));
        }
        $stmt->close();
    } else {
        // Error preparing statement
        error_log("Error preparing mark as read statement: " . $conn->error);
        header("location: ebook_detail.php?id=" . $ebook_id . "&status=error&message=" . urlencode("Database error."));
    }
} else {
    header("location: index.php?status=error&message=" . urlencode("Invalid Ebook ID."));
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
exit;
?>