<?php
// admin/delete_user.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/db_connect.php'; // Adjust path as needed
session_start();

// Redirect if not logged in or not authorized
// Only Admin and Manager should be able to delete users
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: ../login.php");
    exit();
}

$message = '';
$message_type = 'danger'; // Default to danger for errors

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];

    // Prevent deletion of self for 'admin' role, or if user is the only admin
    // This is a basic safety check. You might need more robust logic for production.
    if ($user_id === $_SESSION['user_id'] && $_SESSION['user_role'] === 'admin') {
        $message = "You cannot delete your own admin account.";
        header("Location: manage_users.php?msg=" . urlencode($message) . "&type=" . $message_type);
        exit();
    }

    try {
        // Optional: Before deleting the user, consider deleting or reassigning their uploaded ebooks.
        // Example if you want to delete ebooks uploaded by this user:
        // $stmt_ebooks = $conn->prepare("DELETE FROM ebooks WHERE uploaded_by = ?");
        // $stmt_ebooks->bind_param("i", $user_id);
        // $stmt_ebooks->execute();
        // $stmt_ebooks->close();

        // Prepare and execute the DELETE statement for the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $message = "User account deleted successfully.";
                $message_type = 'success';
            } else {
                $message = "User not found or could not be deleted.";
            }
        } else {
            $message = "Error deleting user: " . $stmt->error;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $message = "Database error: " . $e->getMessage();
    }

    $conn->close();

    // Redirect back to manage_users.php with a message
    header("Location: manage_users.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();

} else {
    // If no ID is provided, redirect with an error
    header("Location: manage_users.php?msg=" . urlencode("No user ID provided for deletion.") . "&type=danger");
    exit();
}
?>