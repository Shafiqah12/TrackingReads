<?php
// admin/delete-notes.php
// Handles the deletion of a note record from the database and its associated file.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Ensure path is correct

// Access Control: Only logged-in admins can access this page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

$delete_status_message = '';

// Check if an ID is provided via GET request
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $note_id = trim($_GET['id']);

    // Ensure the ID is an integer to prevent SQL injection attempts through type juggling
    if (!filter_var($note_id, FILTER_VALIDATE_INT)) {
        $delete_status_message = "Invalid note ID provided.";
        $_SESSION['delete_status'] = "<div class='help-block'>" . $delete_status_message . "</div>";
        header("location: manage-existing-notes.php");
        exit;
    }

    // First, retrieve the file path from the database before deleting the record
    $file_to_delete = null;
    $sql_get_file = "SELECT file_path FROM notes WHERE id = ?";
    if ($stmt_get_file = $conn->prepare($sql_get_file)) {
        $stmt_get_file->bind_param("i", $note_id);
        $stmt_get_file->execute();
        $stmt_get_file->bind_result($file_to_delete);
        $stmt_get_file->fetch();
        $stmt_get_file->close();
    } else {
        $delete_status_message = "Database error: Could not prepare file retrieval statement.";
        $_SESSION['delete_status'] = "<div class='help-block'>" . $delete_status_message . "</div>";
        header("location: manage-existing-notes.php");
        exit;
    }

    // Now, prepare a DELETE statement
    $sql_delete = "DELETE FROM notes WHERE id = ?";

    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $note_id); // 'i' for integer type

        // Attempt to execute the prepared statement
        if ($stmt_delete->execute()) {
            // If database record deleted successfully, attempt to delete the file
            if ($file_to_delete && file_exists($file_to_delete)) {
                if (unlink($file_to_delete)) {
                    $delete_status_message = "Note and associated file deleted successfully.";
                } else {
                    $delete_status_message = "Note deleted from database, but failed to delete associated file from disk. Path: " . htmlspecialchars($file_to_delete);
                }
            } else {
                $delete_status_message = "Note deleted from database. No associated file found or path was invalid.";
            }
        } else {
            $delete_status_message = "Error deleting note from database: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    } else {
        $delete_status_message = "Database error: Could not prepare delete statement. " . $conn->error;
    }
} else {
    $delete_status_message = "No note ID provided for deletion.";
}

// Store the status message in a session variable to display on the manage page
$_SESSION['delete_status'] = "<div class='";
$_SESSION['delete_status'] .= (strpos($delete_status_message, 'successfully') !== false) ? 'success-message' : 'help-block';
$_SESSION['delete_status'] .= "'>" . $delete_status_message . "</div>";


// Close DB connection
$conn->close();

// Redirect back to the manage notes page
header("location: manage-existing-notes.php");
exit;
?>