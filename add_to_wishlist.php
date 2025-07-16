<?php
// add_to_wishlist.php
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

// Determine the correct redirect URL based on where the action came from
// Default to index.php, but if ebook_id is present, redirect to ebook_detail.php
$redirect_url = 'index.php';
if ($ebook_id) {
    $redirect_url = 'ebook_detail.php?id=' . $ebook_id;
}

if ($ebook_id) {
    try {
        // Check if already in wishlist to prevent duplicate entries
        $check_sql = "SELECT id FROM wishlist WHERE user_id = ? AND ebook_id = ?";
        if ($stmt = $conn->prepare($check_sql)) {
            $stmt->bind_param("ii", $user_id, $ebook_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 0) { // Only add if not already present
                $insert_sql = "INSERT INTO wishlist (user_id, ebook_id) VALUES (?, ?)";
                if ($stmt_insert = $conn->prepare($insert_sql)) {
                    $stmt_insert->bind_param("ii", $user_id, $ebook_id);
                    if ($stmt_insert->execute()) {
                        header("location: " . $redirect_url . "&status=success&message=" . urlencode("Ebook added to wishlist!"));
                        exit;
                    } else {
                        throw new Exception("Error inserting into wishlist: " . $stmt_insert->error);
                    }
                    $stmt_insert->close();
                } else {
                    throw new Exception("Error preparing insert statement: " . $conn->error);
                }
            } else {
                header("location: " . $redirect_url . "&status=info&message=" . urlencode("Ebook is already in your wishlist."));
                exit;
            }
            $stmt->close();
        } else {
            throw new Exception("Error preparing check statement: " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Wishlist add operation failed: " . $e->getMessage());
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