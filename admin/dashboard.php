<?php
// admin/dashboard.php
// This is the dashboard for administrators.

// Enable error reporting for development purposes.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start a PHP session to access session variables.
session_start();

// Include the database connection file.
// The path '../includes/db_connect.php' assumes db_connect.php is in an 'includes'
// folder one level up from the 'admin' folder. Adjust if your structure differs.
require_once '../includes/db_connect.php';

// -----------------------------------------------------------------------------
// Access Control:
// Check if the user is NOT logged in, or if they are logged in but are NOT an admin.
// If either condition is true, redirect them to the the login page.
// This prevents unauthorized access to the admin dashboard.
// -----------------------------------------------------------------------------
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "admin") {
    // If not logged in or not an admin, redirect to login page.
    // The path '../login.php' assumes login.php is one directory level up.
    header("location: ../login.php");
    exit; // Stop further script execution to prevent unintended output.
}

// -----------------------------------------------------------------------------
// Fetch Dynamic Data for Dashboard Overview:
// This section queries the database to get summary information for the admin.
// It checks if the database connection ($conn) is established before executing queries.
// -----------------------------------------------------------------------------
$total_users = 0; // Initialize total users count
$total_ebooks = 0; // Initialize total ebooks count (renamed from total_notes)

// Check if the database connection object ($conn) exists and is valid.
if ($conn) {
    // Prepare and execute a SQL statement to count all users in the 'users' table.
    $stmt_users = $conn->prepare("SELECT COUNT(id) FROM users");
    if ($stmt_users) { // Check if statement preparation was successful
        $stmt_users->execute();
        $stmt_users->bind_result($total_users); // Bind the result to $total_users variable
        $stmt_users->fetch(); // Fetch the result
        $stmt_users->close(); // Close the statement
    } else {
        // Handle error if statement preparation failed
        error_log("Failed to prepare users count statement: " . $conn->error);
    }

    // Prepare and execute a SQL statement to count all ebooks in the 'ebooks' table.
    // IMPORTANT: Changed from 'notes' to 'ebooks'
    $stmt_ebooks = $conn->prepare("SELECT COUNT(id) FROM ebooks");
    if ($stmt_ebooks) { // Check if statement preparation was successful
        $stmt_ebooks->execute();
        $stmt_ebooks->bind_result($total_ebooks); // Bind the result to $total_ebooks variable
        $stmt_ebooks->fetch(); // Fetch the result
        $stmt_ebooks->close(); // Close the statement
    } else {
        // Handle error if statement preparation failed
        error_log("Failed to prepare ebooks count statement: " . $conn->error);
    }
} else {
    error_log("Database connection not established in admin/dashboard.php");
}

// -----------------------------------------------------------------------------
// Include Header and HTML Content:
// The header file typically contains the HTML <head> section, navigation,
// and opening body/main tags.
// -----------------------------------------------------------------------------
// The path '../includes/header.php' assumes header.php is in an 'includes'
// folder one level up from the 'admin' folder.
require_once '../includes/header.php';
?>

<div class="main-content-area">
    <h2>Welcome, Admin <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>
    <p>This is your administrator dashboard. Here you can manage ebooks and users.</p>
    <p>Your role: <strong><?php echo htmlspecialchars($_SESSION["user_role"]); ?></strong></p>

    <div class="dashboard-content">
        <h3>Admin Overview</h3>
        <p>Total Registered Users: <strong><?php echo $total_users; ?></strong></p>
        <p>Total Ebooks in Library: <strong><?php echo $total_ebooks; ?></strong></p> <!-- Renamed display text -->
        <hr>
        <h3>Admin Actions</h3>
        <div class="admin-actions-buttons">
            <a href="add_ebook.php" class="btn btn-primary">Add New Ebook</a> <!-- Link to your new add_ebook.php -->
            <a href="../index.php" class="btn btn-primary">View Ebook Library</a> <!-- Link to the main library page -->
            <a href="manage-ebook.php" class="btn btn-primary">Manage Ebook</a>
            <!-- You might want to create a manage_ebooks.php for editing/deleting individual ebooks -->
            <!-- <a href="manage_ebooks.php" class="btn btn-primary">Manage Ebooks</a> -->
            <!-- You might want to create a report page for ebooks -->
            <!-- <a href="ebook_reports.php" class="btn btn-primary">Ebook Reports</a> -->
            <a href="../import_excel_to_db.php" class="btn btn-primary">Import Ebooks from Excel</a> <!-- Link to your import script -->
        </div>
        <p>You can start adding logic for admin functionalities here.</p>
    </div>
</div>

<?php
// -----------------------------------------------------------------------------
// Include Footer and Close Database Connection:
// The footer file typically contains closing body/html tags and scripts.
// The database connection is closed here as it's no longer needed for this page.
// -----------------------------------------------------------------------------
$conn->close(); // Close the database connection.
// The path '../includes/footer.php' assumes footer.php is in an 'includes'
// folder one level up from the 'admin' folder.
require_once '../includes/footer.php';
?>