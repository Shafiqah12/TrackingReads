<?php
// dashboard.php
// This is the dashboard for regular users.

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start a PHP session to access session variables.
session_start();

// Include the database connection file.
require_once 'includes/db_connect.php'; // Path relative to dashboard.php

// Access Control:
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] === "admin") {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch Notes for Display on the dashboard
$notes = [];
if ($conn) {
    $sql_fetch_notes = "SELECT n.id, n.title, n.description, n.price, n.file_path, u.username AS uploaded_by_username, n.created_at,
                         (SELECT COUNT(*) FROM purchases p WHERE p.user_id = ? AND p.note_id = n.id) AS is_purchased
                         FROM notes n
                         JOIN users u ON n.uploaded_by = u.id
                         ORDER BY n.created_at DESC";

    if ($stmt_notes = $conn->prepare($sql_fetch_notes)) {
        $stmt_notes->bind_param("i", $user_id);
        $stmt_notes->execute();
        $result_notes = $stmt_notes->get_result();

        if ($result_notes->num_rows > 0) {
            while ($row = $result_notes->fetch_assoc()) {
                $notes[] = $row;
            }
        }
        $stmt_notes->close();
    } else {
        error_log("Error preparing notes fetch statement: " . $conn->error);
    }
} else {
    error_log("Database connection not established in dashboard.php");
}

// Include the header file.
require_once 'includes/header.php';
?>

<div class="dashboard-container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>
    <p>This is your user dashboard. Here you can browse and purchase notes.</p>
    <p>Your role: <strong><?php echo htmlspecialchars($_SESSION["user_role"]); ?></strong></p>

    <hr>

    <div class="dashboard-content">
        <h3>Available Notes for Purchase</h3>
        
        <div class="notes-grid">
            <?php if (!empty($notes)): ?>
                <?php foreach ($notes as $note): ?>
                    <div class="note-card">
                        <h4><?php echo htmlspecialchars($note['title']); ?></h4>
                        <p class="description"><?php echo htmlspecialchars($note['description']); ?></p>
                        <p class="price">Price: RM<?php echo htmlspecialchars(number_format($note['price'], 2)); ?></p>
                        <p class="uploaded-info">
                            Uploaded by: <?php echo htmlspecialchars($note['uploaded_by_username']); ?> on: <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($note['created_at']))); ?>
                        </p>
                        
                        <?php if ($note['is_purchased']): ?>
                            <span class="purchased-note">Purchased!</span>
                            <?php
                            // Correct the file path for display/download
                            $correct_file_path = str_replace('../uploads/', '/TrackingReads/uploads/', $note['file_path']);
                            ?>
                            <a href="<?php echo htmlspecialchars($correct_file_path); ?>" class="btn btn-primary" download>Download Note</a>
                        <?php else: ?>
                            <a href="/TrackingReads/buy-note.php?note_id=<?php echo htmlspecialchars($note['id']); ?>"
                               class="btn btn-success"
                               onclick="return confirm('Are you sure you want to purchase \'<?php echo htmlspecialchars($note['title']); ?>\' for RM<?php echo htmlspecialchars(number_format($note['price'], 2)); ?>?');">
                                Buy Now
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No notes available at the moment. Please check back later!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>