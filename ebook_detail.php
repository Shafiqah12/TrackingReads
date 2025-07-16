<?php
// ebook_detail.php
// Displays all details for a single ebook and will include rating/review/wishlist/read status

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db_connect.php';

// Access Control:
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /TrackingReads/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$ebook = null;
$reviews = []; // Initialize reviews array
$average_rating = 0; // Initialize average rating
$user_has_reviewed = false; // Track if current user has reviewed this ebook
$user_review_rating = null; // Store user's existing rating
$user_review_text = null; // Store user's existing review text

$ebook_id = isset($_GET['id']) ? (int)$_GET['id'] : 0; // Get ebook ID from URL

// Handle messages from review submission
$status_message = '';
if (isset($_GET['status']) && isset($_GET['message'])) {
    $status_type = htmlspecialchars($_GET['status']);
    $status_message = htmlspecialchars($_GET['message']);
}

if ($ebook_id > 0 && $conn) {
    // 1. Fetch Ebook Details
    $sql_fetch_ebook = "SELECT e.id, e.no, e.tajuk AS title, e.description, e.harga_rm AS price, e.file_path,
                               e.penulis, e.muka_surat, e.perkataan, e.genre, e.bulan, e.tahun, e.penerbit,
                               u.username AS uploaded_by_username, e.created_at,
                               (SELECT COUNT(*) FROM wishlist w WHERE w.user_id = ? AND w.ebook_id = e.id) AS is_in_wishlist,
                               (SELECT COUNT(*) FROM read_status rs WHERE rs.user_id = ? AND rs.ebook_id = e.id) AS is_read
                        FROM ebooks e
                        JOIN users u ON e.uploaded_by = u.id
                        WHERE e.id = ?";

    if ($stmt_ebook = $conn->prepare($sql_fetch_ebook)) {
        $stmt_ebook->bind_param("iii", $user_id, $user_id, $ebook_id);
        $stmt_ebook->execute();
        $result_ebook = $stmt_ebook->get_result();

        if ($result_ebook->num_rows == 1) {
            $ebook = $result_ebook->fetch_assoc();
        } else {
            $errorMessage = "Ebook not found.";
        }
        $stmt_ebook->close();
    } else {
        error_log("Error preparing ebook detail statement: " . $conn->error);
        $errorMessage = "Error fetching ebook details.";
    }

    // 2. Fetch Reviews for this Ebook
    $sql_fetch_reviews = "SELECT r.rating, r.review_text, r.created_at, u.username
                          FROM reviews r
                          JOIN users u ON r.user_id = u.id
                          WHERE r.ebook_id = ?
                          ORDER BY r.created_at DESC";

    if ($stmt_reviews = $conn->prepare($sql_fetch_reviews)) {
        $stmt_reviews->bind_param("i", $ebook_id);
        $stmt_reviews->execute();
        $result_reviews = $stmt_reviews->get_result();

        if ($result_reviews->num_rows > 0) {
            while ($row = $result_reviews->fetch_assoc()) {
                $reviews[] = $row;
            }
        }
        $stmt_reviews->close();
    } else {
        error_log("Error preparing reviews fetch statement: " . $conn->error);
    }

    // 3. Calculate Average Rating
    $sql_avg_rating = "SELECT AVG(rating) AS avg_rating FROM reviews WHERE ebook_id = ?";
    if ($stmt_avg = $conn->prepare($sql_avg_rating)) {
        $stmt_avg->bind_param("i", $ebook_id);
        $stmt_avg->execute();
        $result_avg = $stmt_avg->get_result();
        $avg_row = $result_avg->fetch_assoc();
        $average_rating = round($avg_row['avg_rating'] ?? 0, 1); // Round to 1 decimal place
        $stmt_avg->close();
    }

    // 4. Check if current user has reviewed and fetch their review
    $sql_user_review = "SELECT rating, review_text FROM reviews WHERE ebook_id = ? AND user_id = ?";
    if ($stmt_user_review = $conn->prepare($sql_user_review)) {
        $stmt_user_review->bind_param("ii", $ebook_id, $user_id);
        $stmt_user_review->execute();
        $result_user_review = $stmt_user_review->get_result();
        if ($result_user_review->num_rows > 0) {
            $user_has_reviewed = true;
            $user_review_data = $result_user_review->fetch_assoc();
            $user_review_rating = $user_review_data['rating'];
            $user_review_text = $user_review_data['review_text'];
        }
        $stmt_user_review->close();
    }

} else {
    $errorMessage = "Invalid Ebook ID provided.";
}

require_once 'includes/header.php';
?>

<div class="container ebook-detail-container">
    <a href="index.php" class="back-arrow-button" title="Back to Ebook Library"><i class="fas fa-arrow-left"></i></a>

    <?php if ($ebook): ?>
        <h2><?php echo htmlspecialchars($ebook['title']); ?></h2>
        <p><strong>NO:</strong> <?php echo htmlspecialchars($ebook['no']); ?></p>
        <p><strong>Penulis:</strong> <?php echo htmlspecialchars($ebook['penulis']); ?></p>
        <p><strong>Genre:</strong> <?php echo htmlspecialchars($ebook['genre']); ?></p>
        <p><strong>Penerbit:</strong> <?php echo htmlspecialchars($ebook['penerbit']); ?></p>
        <p><strong>Muka Surat:</strong> <?php echo htmlspecialchars($ebook['muka_surat']); ?></p>
        <p><strong>Perkataan:</strong> <?php echo htmlspecialchars($ebook['perkataan']); ?></p>
        <p><strong>Harga (RM):</strong> <?php echo htmlspecialchars(number_format($ebook['price'], 2)); ?></p>
        <p><strong>Bulan:</strong> <?php echo htmlspecialchars($ebook['bulan']); ?></p>
        <p><strong>Tahun:</strong> <?php echo htmlspecialchars($ebook['tahun']); ?></p>
        <p><strong>Description:</strong> <?php echo htmlspecialchars($ebook['description']); ?></p>
        <p class="uploaded-info">
            Uploaded by: <?php echo htmlspecialchars($ebook['uploaded_by_username'] ?? 'Unknown'); ?> on:
            <?php
            // FIX for Deprecated: htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated
            $createdAt = $ebook['created_at'] ?? null;
            if ($createdAt && ($timestamp = strtotime($createdAt)) !== false) { // Check if not empty AND strtotime succeeds
                echo htmlspecialchars(date("F j, Y, g:i a", $timestamp));
            } else {
                echo 'N/A'; // Or a more appropriate placeholder if date is missing/invalid
            }
            ?>
        </p>


        <div class="ebook-actions-detail mt-4">
            <?php if ($ebook['is_in_wishlist']): ?>
                <span class="status-badge wishlist">In Wishlist <i class="fas fa-heart"></i></span>
                <a href="remove_from_wishlist.php?ebook_id=<?php echo htmlspecialchars($ebook['id']); ?>" class="btn btn-secondary btn-sm">Remove from Wishlist</a>
            <?php else: ?>
                <a href="add_to_wishlist.php?ebook_id=<?php echo htmlspecialchars($ebook['id']); ?>" class="btn btn-info btn-sm">Add to Wishlist <i class="far fa-heart"></i></a>
            <?php endif; ?>

            <?php if ($ebook['is_read']): ?>
                <span class="status-badge read">Read <i class="fas fa-check-circle"></i></span>
                <a href="mark_as_unread.php?ebook_id=<?php echo htmlspecialchars($ebook['id']); ?>" class="btn btn-secondary btn-sm">Mark as Unread</a>
            <?php else: ?>
                <a href="mark_as_read.php?ebook_id=<?php echo htmlspecialchars($ebook['id']); ?>" class="btn btn-success btn-sm">Mark as Read <i class="far fa-check-circle"></i></a>
            <?php endif; ?>
        </div>

        <hr class="my-4">

        <h3>Ratings & Reviews (Average: <?php echo $average_rating; ?> / 5)</h3>

        <?php if ($status_message): ?>
            <div class="message <?php echo $status_type === 'success' ? 'success' : 'error'; ?>">
                <?php echo $status_message; ?>
            </div>
        <?php endif; ?>

        <div class="rating-review-section">
            <h4><?php echo $user_has_reviewed ? 'Update Your Review' : 'Submit Your Review'; ?></h4>
            <form action="submit_review.php" method="POST">
                <input type="hidden" name="ebook_id" value="<?php echo htmlspecialchars($ebook_id); ?>">
                
                <div class="form-group mb-3">
                    <label for="rating">Your Rating:</label>
                    <select name="rating" id="rating" class="form-control" required>
                        <option value="">Select a rating</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($user_review_rating == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?> Star<?php echo ($i > 1) ? 's' : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label for="review_text">Your Review:</label>
                    <textarea name="review_text" id="review_text" rows="5" class="form-control" placeholder="Write your review here..."><?php echo htmlspecialchars($user_review_text ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary"><?php echo $user_has_reviewed ? 'Update Review' : 'Submit Review'; ?></button>
            </form>

            <h4 class="mt-5">All Reviews:</h4>
            <?php if (!empty($reviews)): ?>
                <div class="all-reviews-list">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item mb-3 p-3 border rounded">
                            <p><strong><?php echo htmlspecialchars($review['username']); ?></strong> rated: 
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?php echo ($i <= $review['rating']) ? 'filled' : ''; ?>">&#9733;</span>
                                <?php endfor; ?>
                                (<?php echo htmlspecialchars($review['rating']); ?>/5)
                            </p>
                            <?php if (!empty($review['review_text'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                            <?php endif; ?>
                            <small class="text-muted">Reviewed on: <?php echo htmlspecialchars(date("F j, Y", strtotime($review['created_at']))); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No reviews yet for this ebook. Be the first to review!</p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="message error">
            <p><?php echo htmlspecialchars($errorMessage ?? "Ebook not found or an error occurred."); ?></p>
            <p><a href="index.php">Go back to Ebook Library</a></p>
        </div>
    <?php endif; ?>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>

<style>
    /* Add this to your style.css or a <style> block if you don't have style.css */
    .star {
        color: #ccc; /* Default star color (unfilled) */
        font-size: 1.2em;
    }
    .star.filled {
        color: gold; /* Filled star color */
    }
    .message {
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
    }
    .message.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    /* Basic styling for form elements if not already in your CSS */
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box; /* Ensures padding doesn't increase total width */
    }
    textarea.form-control {
        resize: vertical; /* Allow vertical resizing of textarea */
    }
    .btn {
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1em;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        margin-right: 5px; /* For buttons next to each other */
    }
    .btn-primary {
        background-color: #007bff;
        color: white;
    }
    .btn-primary:hover {
        background-color: #0056b3;
    }
    /* Original .btn-secondary, keep if used elsewhere */
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    .btn-secondary:hover {
        background-color: #5a6268;
    }
    /* Original .mb-3 if used elsewhere */
    .mb-3 {
        margin-bottom: 1rem;
    }

    /* CSS for the top-left back arrow button */
    .ebook-detail-container {
        position: relative; /* This is crucial for positioning children absolutely within it */
        padding-top: 50px; /* Add padding to make space for the button at the top */
        /* You might need to adjust other padding/margin on this container */
    }

    .back-arrow-button {
        position: absolute; /* Position it relative to the .ebook-detail-container */
        top: 15px; /* Adjust top padding as needed */
        left: 15px; /* Adjust left padding as needed */
        width: 40px; /* Set a fixed width for the circle */
        height: 40px; /* Set a fixed height for the circle */
        border-radius: 50%; /* Make it perfectly circular */
        background-color: #FF69B4; /* Pink color */
        color: white; /* White color for the icon */
        display: flex; /* Use flexbox to center the icon */
        justify-content: center; /* Center horizontally */
        align-items: center; /* Center vertically */
        font-size: 1.2em; /* Size of the arrow icon */
        text-decoration: none; /* Remove underline */
        box-shadow: 0 2px 5px rgba(0,0,0,0.2); /* Subtle shadow for depth */
        transition: background-color 0.3s ease; /* Smooth hover effect */
        z-index: 10; /* Ensure it's above other content */
    }

    .back-arrow-button:hover {
        background-color: #FF1493; /* Darker pink on hover */
    }
</style>