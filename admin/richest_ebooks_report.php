<?php
// admin/richest_notes_report.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include database connection
// Corrected path: Go up one level from 'admin' to 'NOTESYNC', then into 'includes'
require_once '../includes/db_connect.php';

// Check if the user is logged in and is an admin (or has appropriate privileges)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "manager") {
    // Redirect non-admin users or not logged in users to the login page (one level up)
    header("location: ../login.php");
    exit;
}

// -----------------------------------------------------------------------------
// Include Header:
// This will bring in your consistent navigation bar, CSS, and other head elements.
// The path '../includes/header.php' assumes header.php is in an 'includes'
// folder one level up from the 'admin' folder.
require_once '../includes/header.php';
// -----------------------------------------------------------------------------
?>

<div class="main-content-area"> <h2>💰 Richest Notes Report</h2>
    <p>This report lists notes by their total sales, from highest to lowest revenue.</p>

    <?php
    // SQL query to get notes sorted by total sales value
    $sql = "SELECT
                n.id,
                n.title,
                n.price,
                COUNT(p.id) AS total_purchases,
                SUM(n.price) AS total_revenue
            FROM
                notes n
            JOIN
                purchases p ON n.id = p.note_id
            GROUP BY
                n.id, n.title, n.price
            ORDER BY
                total_revenue DESC, total_purchases DESC";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo "<div class='table-responsive'>"; // Assuming you have this class for tables
            echo "<table class='notes-table'>"; // Assuming 'notes-table' or similar for styling
            echo "<thead>";
            echo "<tr>";
            echo "<th>Note ID</th>";
            echo "<th>Title</th>";
            echo "<th>Price (MYR)</th>";
            echo "<th>Total Purchases</th>";
            echo "<th>Total Revenue (MYR)</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";

            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row["id"]) . "</td>";
                echo "<td>" . htmlspecialchars($row["title"]) . "</td>";
                echo "<td>RM " . number_format(htmlspecialchars($row["price"]), 2) . "</td>";
                echo "<td>" . htmlspecialchars($row["total_purchases"]) . "</td>";
                echo "<td>RM " . number_format(htmlspecialchars($row["total_revenue"]), 2) . "</td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
            echo "</div>";
        } else {
            echo "<p>No sales data found yet.</p>";
        }

        $stmt->close();
    } else {
        echo "<div class='help-block'>Error preparing SQL statement: " . $conn->error . "</div>";
    }

    // Close the database connection here, after all database operations for this page are done
    $conn->close();
    ?>
</div>

<?php
// -----------------------------------------------------------------------------
// Include Footer:
// This will close your HTML body and document tags, and include any scripts.
// The path '../includes/footer.php' assumes footer.php is in an 'includes'
// folder one level up from the 'admin' folder.
require_once '../includes/footer.php';
?>