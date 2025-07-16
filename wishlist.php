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
    // Modified SQL query to fetch details needed for the new display
    // is_read is still fetched for potential future use or if the user wants a badge,
    // but the buttons for it will be removed.
    $sql_wishlist = "SELECT e.id, e.tajuk, e.penulis, e.penerbit, e.harga_rm,
                            (SELECT COUNT(*) FROM read_status rs WHERE rs.user_id = ? AND rs.ebook_id = e.id) AS is_read
                     FROM ebooks e
                     JOIN wishlist w ON e.id = w.ebook_id
                     WHERE w.user_id = ?
                     ORDER BY w.added_at DESC";

    if ($stmt_wishlist = $conn->prepare($sql_wishlist)) {
        $stmt_wishlist->bind_param("ii", $user_id, $user_id); // Bind user_id twice
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

<div class="container mx-auto px-4 py-8">
    <h1 class="text-pink-500 text-3xl font-bold mb-6">My Wishlist</h1>
    <p class="mb-6 text-gray-700">These are the ebooks you've added to your wishlist.</p>

    <div class="ebook-list grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (!empty($wishlist_ebooks)): ?>
            <?php foreach ($wishlist_ebooks as $ebook): ?>
                <div class="ebook-item bg-white p-4 rounded-lg shadow-md flex flex-col justify-between">
                    <div>
                        <a href="ebook_detail.php?id=<?php echo htmlspecialchars($ebook['id']); ?>" class="ebook-title-link text-lg font-bold text-blue-600 hover:underline">
                            <h3><?php echo htmlspecialchars($ebook['tajuk'] ?? ''); ?></h3>
                        </a>
                        <p class="text-gray-700"><strong>Penulis:</strong> <?php echo htmlspecialchars($ebook['penulis'] ?? ''); ?></p>
                        <p class="text-gray-700"><strong>Penerbit:</strong> <?php echo htmlspecialchars($ebook['penerbit'] ?? ''); ?></p>
                        <p class="text-gray-700">Price: RM<?php echo htmlspecialchars(number_format($ebook['harga_rm'] ?? 0, 2)); ?></p>
                    </div>

                    <div class="ebook-actions mt-3 flex flex-wrap gap-2">
                        <span class="status-badge wishlist bg-pink-100 text-pink-700 px-2 py-1 rounded-full text-sm flex items-center gap-1">
                            In Wishlist <i class="fas fa-heart"></i>
                        </span>

                        <?php
                        // Optional: If you still want to show 'Read' status as a badge
                        if ($ebook['is_read']): ?>
                            <span class="status-badge read bg-green-100 text-green-700 px-2 py-1 rounded-full text-sm flex items-center gap-1">
                                Read <i class="fas fa-check-circle"></i>
                            </span>
                        <?php endif; ?>

                        <a href="/TrackingReads/remove_from_wishlist.php?ebook_id=<?php echo htmlspecialchars($ebook['id']); ?>"
                           class="btn btn-remove bg-pink-600 text-white px-3 py-1 rounded-md text-sm hover:bg-pink-700">
                           Remove from Wishlist
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full message info bg-blue-100 text-blue-700 p-4 rounded-md">
                <p>Your wishlist is empty. Go to your <a href="index.php" class="text-blue-500 hover:underline">Ebook Library</a> to add some ebooks!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once 'includes/footer.php';
?>