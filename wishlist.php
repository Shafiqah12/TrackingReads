<?php
// wishlist.php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "user") {
    header("location: /TrackingReads/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$wishlist_ebooks = [];

if ($conn) {
    $sql_wishlist = "SELECT e.id, e.tajuk AS title, e.description, e.harga_rm AS price, e.file_path, u.username AS uploaded_by_username, e.created_at, w.added_at
                     FROM ebooks e
                     JOIN users u ON e.uploaded_by = u.id
                     JOIN wishlist w ON e.id = w.ebook_id
                     WHERE w.user_id = ?
                     ORDER BY w.added_at DESC";

    if ($stmt_wishlist = $conn->prepare($sql_wishlist)) {
        $stmt_wishlist->bind_param("i", $user_id);
        $stmt_wishlist->execute();
        $result_wishlist = $stmt_wishlist->get_result();

        if ($result_wishlist->num_rows > 0) {
            while ($row = $result_wishlist->fetch_assoc()) {
                $wishlist_ebooks[] = $row;
            }
        }
        $stmt_wishlist->close();
    } else {
        error_log("Error preparing wishlist statement: " . $conn->error);
    }
} else {
    error_log("Database connection not established in wishlist.php");
}

require_once 'includes/header.php';
?>

<div class="container wishlist-container">
    <h2>My Wishlist</h2>
    <p>These are the ebooks you've added to your wishlist.</p>

    <div class="ebooks-grid">
        <?php if (!empty($wishlist_ebooks)): ?>
            <?php foreach ($wishlist_ebooks as $ebook): ?>
                <div class="ebook-card">
                    <h4><?php echo htmlspecialchars($ebook['title'] ?? ''); ?></h4>
                    <p class="description"><?php echo htmlspecialchars($ebook['description'] ?? ''); ?></p>
                    <p class="price">Price: RM<?php echo htmlspecialchars(number_format($ebook['price'] ?? 0, 2)); ?></p>
                    <p class="uploaded-info">
                        Uploaded by: <?php echo htmlspecialchars($ebook['uploaded_by_username'] ?? 'Unknown'); ?> on: <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($ebook['created_at'] ?? ''))); ?>
                    </p>
                    <p class="wishlist-info">
                        Added to wishlist on: <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($ebook['added_at'] ?? ''))); ?>
                    </p>
                    
                    <?php
                    // The file path correction variable is no longer strictly needed for download,
                    // but keeping it if it's used elsewhere (e.g., for showing a preview link later)
                    $correct_file_path = str_replace('../uploads/', '/TrackingReads/uploads/', $ebook['file_path'] ?? '');
                    ?>
                    <div class="button-group">
                        <a href="/TrackingReads/remove_from_wishlist.php?ebook_id=<?php echo htmlspecialchars($ebook['id']); ?>" class="btn btn-secondary btn-sm">Remove from Wishlist</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Your wishlist is empty. Go to your <a href="/TrackingReads/dashboard.php">Dashboard</a> to add some ebooks!</p>
        <?php endif; ?>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>