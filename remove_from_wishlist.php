<?php
// remove_from_wishlist.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$ebook_id = filter_input(INPUT_GET, 'ebook_id', FILTER_VALIDATE_INT);

// Determine the correct redirect URL
$redirect_url = 'index.php';
if ($ebook_id) {
    $redirect_url = 'ebook_detail.php?id=' . $ebook_id;
}

if ($ebook_id) {
    try {
        $delete_sql = "DELETE FROM wishlist WHERE user_id = ? AND ebook_id = ?";
        if ($stmt = $conn->prepare($delete_sql)) {
            $stmt->bind_param("ii", $user_id, $ebook_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    header("location: " . $redirect_url . "&status=success&message=" . urlencode("Ebook removed from wishlist!"));
                    exit;
                } else {
                    header("location: " . $redirect_url . "&status=info&message=" . urlencode("Ebook was not in your wishlist."));
                    exit;
                }
            } else {
                throw new Exception("Error deleting from wishlist: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Error preparing delete statement: " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Wishlist remove operation failed: " . $e->getMessage());
        header("location: " . $redirect_url . "&status=error&message=" . urlencode("An unexpected error occurred: " . $e->getMessage()));
        exit;
    }
} else {
    header("location: " . $redirect_url . "&status=error&message=" . urlencode("Ebook ID missing for wishlist action."));
    exit;
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>