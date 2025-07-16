<?php
// my-notes.php
error_reporting(E_ALL); // Show all errors
ini_set('display_errors', 1); // Display errors directly on the page

session_start(); // Start the session to access $_SESSION variables
require_once 'includes/db_connect.php'; // Include your database connection file

// --- Access Control ---
// Redirect if user is not logged in or user_id is not set
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}

// Redirect if logged-in user is an admin (admins shouldn't be on this page)
if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin") {
    header("location: admin/dashboard.php"); // Redirect to admin dashboard
    exit;
}

$user_id = $_SESSION['user_id']; // Get the current user's ID
$status_message = ''; // Initialize a variable for displaying status messages

// Check if there's a purchase status message from buy-note.php
if (isset($_SESSION['purchase_status'])) {
    $status_message = $_SESSION['purchase_status']; // Get the message
    unset($_SESSION['purchase_status']); // Clear the message from session after displaying
}

$purchased_notes = []; // Initialize an empty array to store purchased notes

// --- Fetch Purchased Notes from Database ---
if ($conn) { // Check if database connection is successful
    // SQL query to retrieve notes purchased by the current user
    // It joins 'notes' (n) with 'purchases' (p) on note_id,
    // and 'users' (u) to get the uploader's username.
    $sql_fetch_purchased_notes = "SELECT n.id, n.title, n.description, n.price, n.file_path, u.username AS uploaded_by_username, n.created_at
                                  FROM notes n
                                  JOIN purchases p ON n.id = p.note_id
                                  JOIN users u ON n.uploaded_by = u.id
                                  WHERE p.user_id = ?
                                  ORDER BY n.created_at DESC";

    if ($stmt_purchased = $conn->prepare($sql_fetch_purchased_notes)) {
        $stmt_purchased->bind_param("i", $user_id); // Bind user ID to the query
        $stmt_purchased->execute(); // Execute the query
        $result_purchased = $stmt_purchased->get_result(); // Get the result set

        if ($result_purchased->num_rows > 0) {
            // Fetch all rows into the $purchased_notes array
            while ($row = $result_purchased->fetch_assoc()) {
                $purchased_notes[] = $row;
            }
        }
        $stmt_purchased->close(); // Close the statement
    } else {
        // Log database error for debugging
        error_log("Error preparing purchased notes fetch statement in my-notes.php: " . $conn->error);
        $status_message .= "<div class='help-block'>Error fetching your purchased notes from the database.</div>";
    }
} else {
    error_log("Database connection not established in my-notes.php");
    $status_message .= "<div class='help-block'>Database connection issue. Please try again later.</div>";
}

// Include the header (e.g., navigation, common CSS)
require_once 'includes/header.php'; // Ensure this path is correct
?>

<div class="dashboard-container">
    <h2>My Purchased Notes</h2>
    <?php
    // Display any status messages (like "Note purchased successfully!")
    if (!empty($status_message)) {
        echo $status_message;
    }
    ?>
    <p>Here are the notes you have purchased.</p>

    <div class="notes-grid">
        <?php if (!empty($purchased_notes)): ?>
            <?php foreach ($purchased_notes as $note): ?>
                <div class="note-card">
                    <h4><?php echo htmlspecialchars($note['title']); ?></h4>
                    <p class="description"><?php echo htmlspecialchars($note['description']); ?></p>
                    <p class="price">Price: RM<?php echo htmlspecialchars(number_format($note['price'], 2)); ?></p>
                    <p class="uploaded-info">
                        Uploaded by: <?php echo htmlspecialchars($note['uploaded_by_username']); ?> on: <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($note['created_at']))); ?>
                    </p>
                    <?php
                    // Correct the file path for direct web access/download
                    // The database stores '../uploads/', but for the web, it needs to be '/NOTESYNC/uploads/'
                    $correct_file_path = str_replace('../uploads/', '/TrackingReads/uploads/', $note['file_path']);
                    ?>
                    <div class="button-group"> <a href="<?php echo htmlspecialchars($correct_file_path); ?>" class="btn btn-primary" download>Download Note</a>
                        <a href="<?php echo htmlspecialchars($correct_file_path); ?>" class="btn btn-secondary" target="_blank">Read File</a> </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>You have not purchased any notes yet. Browse the <a href="dashboard.php">dashboard</a> to find notes.</p>
        <?php endif; ?>
    </div>
</div>

<?php
$conn->close(); // Close the database connection
require_once 'includes/footer.php'; // Include the footer
?>