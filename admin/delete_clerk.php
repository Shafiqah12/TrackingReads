<?php
// admin/delete_clerk.php

require_once '../includes/db_connect.php'; // Adjust path as needed
session_start();

// Redirect if not logged in or not a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $clerk_id = (int)$_GET['id'];
    $message = '';
    $message_type = 'danger'; // Default to danger for errors

    try {
        // Prepare and execute the DELETE statement
        // Ensure you only delete 'clerk' role accounts
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'clerk'");
        $stmt->bind_param("i", $clerk_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $message = "Clerk account deleted successfully.";
                $message_type = 'success';
            } else {
                $message = "Clerk not found or could not be deleted (might not be a clerk account).";
            }
        } else {
            $message = "Error deleting clerk: " . $stmt->error;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $message = "Database error: " . $e->getMessage();
    }

    $conn->close();

    // Redirect back to manage_clerks.php with a message
    header("Location: manage-clerks.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();

} else {
    // If no ID is provided, redirect with an error
    header("Location: manage-clerks.php?msg=" . urlencode("No clerk ID provided for deletion.") . "&type=danger");
    exit();
}
?>