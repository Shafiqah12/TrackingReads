<?php
// admin/manage-users.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Path relative to manage-users.php

// Access control: Ensure only logged-in admins can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

require_once '../includes/header.php'; // Path relative to manage-users.php
?>

<h2>Manage Notes</h2>
<p>Here you can view, edit, or delete notes.</p> <div class="table-responsive"> <?php
    // Fetch notes from the database
    $sql_notes = "SELECT id, title, description, price, file_path, uploaded_by, created_at FROM notes ORDER BY created_at ASC"; // Corrected variable name for clarity
    $result_notes = $conn->query($sql_notes);

    if ($result_notes && $result_notes->num_rows > 0) {
        echo "<table class='notes-table'>"; // Added class 'notes-table'
        echo "<thead><tr><th>ID</th><th>Title</th><th>Description</th><th>Price</th><th>File path</th><th>Uploaded by</th><th>Created At</th><th>Actions</th></tr></thead>"; // Added 'Actions' header
        echo "<tbody>";
        while ($note = $result_notes->fetch_assoc()) { // Changed $users to $note for clarity
            echo "<tr>";
            echo "<td>" . htmlspecialchars($note['id']) . "</td>";
            echo "<td>" . htmlspecialchars($note['title']) . "</td>";
            echo "<td>" . htmlspecialchars($note['description']) . "</td>";
            echo "<td>" . htmlspecialchars($note['price']) . "</td>";
            echo "<td>" . htmlspecialchars($note['file_path']) . "</td>";
            echo "<td>" . htmlspecialchars($note['uploaded_by']) . "</td>";
            echo "<td>" . htmlspecialchars($note['created_at']) . "</td>";
            echo "<td>";
            echo "<a href='edit-notes.php?id=" . $note['id'] . "' class='btn btn-info'>Edit✏️</a> ";
            echo "<a href='delete-notes.php?id=" . htmlspecialchars($note['id']) . "' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete this note and its associated file permanently?\");'>Delete</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No notes found.</p>"; // Changed this line
    }
    $conn->close();
    ?>
</div> <?php require_once '../includes/footer.php'; ?>