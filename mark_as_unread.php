<?php
// mark_as_unread.php
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
    // Correct DELETE statement: Removes the record for the specific user and ebook.
    $sql = "DELETE FROM read_status WHERE user_id = ? AND ebook_id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $user_id, $ebook_id);
        if ($stmt->execute()) {
            // Success: Redirect back to the ebook detail page or index
            header("location: ebook_detail.php?id=" . $ebook_id . "&status=success&message=" . urlencode("Ebook marked as unread."));
        } else {
            // Error during execution
            error_log("Error marking ebook as unread: " . $stmt->error);
            header("location: ebook_detail.php?id=" . $ebook_id . "&status=error&message=" . urlencode("Error marking ebook as unread: " . $stmt->error));
        }
        $stmt->close();
    } else {
        // Error preparing statement
        error_log("Error preparing mark as unread statement: " . $conn->error);
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