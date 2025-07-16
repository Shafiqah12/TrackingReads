<?php
// admin/manage-ebooks.php
// This page allows administrators to view, edit, and delete ebook records.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Path relative to admin/manage-ebooks.php

// Access control: Ensure only logged-in admins can access this page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "admin") {
    header("location: ../login.php"); // Redirect to login page if not authorized
    exit;
}

require_once '../includes/header.php'; // Include your existing header file
?>

<div class="container mt-4">
    <h2>Manage Ebooks</h2>
    <p>Here you can view, edit, or delete ebook records in your library.</p>

    <div class="table-responsive">
        <?php
        // Fetch ebooks from the database
        // Select all relevant columns from the 'ebooks' table
        $sql_ebooks = "SELECT id, no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit FROM ebooks ORDER BY tajuk ASC";

        if ($stmt = $conn->prepare($sql_ebooks)) {
            $stmt->execute();
            $result_ebooks = $stmt->get_result();

            if ($result_ebooks && $result_ebooks->num_rows > 0) {
                echo "<table class='notes-table'>"; // Using the 'notes-table' class from your CSS
                echo "<thead>";
                echo "<tr>";
                echo "<th>ID</th>";
                echo "<th>NO</th>";
                echo "<th>Tajuk</th>";
                echo "<th>Penulis</th>";
                echo "<th>Genre</th>";
                echo "<th>Penerbit</th>";
                echo "<th>Muka Surat</th>";
                echo "<th>Perkataan</th>";
                echo "<th>Harga (RM)</th>";
                echo "<th>Bulan</th>";
                echo "<th>Tahun</th>";
                echo "<th>Actions</th>"; // For Edit/Delete buttons
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";

                while ($ebook = $result_ebooks->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($ebook['id'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>" . htmlspecialchars($ebook['no'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>" . htmlspecialchars($ebook['tajuk'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>" . htmlspecialchars($ebook['penulis'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>" . htmlspecialchars($ebook['genre'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>" . htmlspecialchars($ebook['penerbit'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>" . htmlspecialchars($ebook['muka_surat'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>" . htmlspecialchars($ebook['perkataan'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>" . htmlspecialchars(number_format($ebook['harga_rm'] ?? 0, 2)) . "</td>"; // Added ?? 0 for numeric
                    echo "<td>" . htmlspecialchars($ebook['bulan'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>" . htmlspecialchars($ebook['tahun'] ?? '') . "</td>"; // Added ?? ''
                    echo "<td>";
                    // Links to edit and delete specific ebooks
                    echo "<a href='edit-ebook.php?id=" . htmlspecialchars($ebook['id'] ?? '') . "' class='btn btn-info'>Edit✏️</a> ";
                    echo "<a href='delete-ebook.php?id=" . htmlspecialchars($ebook['id'] ?? '') . "' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete this ebook?\");'>Delete</a>";
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</tbody>";
                echo "</table>";
            } else {
                echo "<p class='message info'>No ebooks found in the library.</p>"; // Changed to message info class
            }
            $stmt->close();
        } else {
            echo "<p class='message error'>Error preparing statement: " . htmlspecialchars($conn->error) . "</p>";
        }
        $conn->close(); // Close the database connection
        ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; // Include your existing footer file ?>