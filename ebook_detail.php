<?php
// ebook_detail.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$ebook = null;
$reviews = [];
$average_rating = 0;
$user_has_reviewed = false;
$user_review_rating = null;
$user_review_text = null;

// Get ebook ID from URL
$ebook_id = filter_var($_GET['id'] ?? '', FILTER_SANITIZE_NUMBER_INT);

// Handle status messages
$status_type = '';
$status_message = '';
if (isset($_GET['status']) && isset($_GET['message'])) {
    $status_type = htmlspecialchars($_GET['status']);
    $status_message = htmlspecialchars($_GET['message']);
}

// Handle wishlist/read status actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['ebook_id'])) {
    $action_ebook_id = filter_var($_GET['ebook_id'], FILTER_SANITIZE_NUMBER_INT);
    if ($user_id && $action_ebook_id) {
        try {
            $conn->begin_transaction();
            $message = '';
            $status = 'error';

            if ($_GET['action'] === 'add_to_wishlist') {
                $sql = "INSERT INTO wishlist (user_id, ebook_id) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $user_id, $action_ebook_id);
                if ($stmt->execute()) {
                    $message = "Ebook added to wishlist!";
                    $status = 'success';
                } else {
                    if ($stmt->errno == 1062) {
                        $message = "Ebook already in your wishlist.";
                    } else {
                        throw new Exception("Error adding to wishlist: " . $stmt->error);
                    }
                }
                $stmt->close();
            } 
            // ... [other actions remain the same] ...
            $conn->commit();
            header("Location: ebook_detail.php?id=" . $action_ebook_id . "&status=" . $status . "&message=" . urlencode($message));
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = "Database error: " . htmlspecialchars($e->getMessage());
            header("Location: ebook_detail.php?id=" . $action_ebook_id . "&status=error&message=" . urlencode($errorMessage));
            exit;
        }
    }
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $review_text = trim($_POST['review_text'] ?? '');
    $rating = filter_var($_POST['rating'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    
    if (!($rating >= 1 && $rating <= 5)) {
        $status_type = 'error';
        $status_message = "Rating must be between 1-5 stars.";
    } else {
        try {
            $conn->begin_transaction();
            
            // CHANGED THIS PART - Using 'id' instead of 'review_id'
            $check_sql = "SELECT id FROM reviews WHERE ebook_id = ? AND user_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $ebook_id, $user_id);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $update_sql = "UPDATE reviews SET review_text = ?, rating = ?, created_at = CURRENT_TIMESTAMP WHERE ebook_id = ? AND user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("siii", $review_text, $rating, $ebook_id, $user_id);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                $insert_sql = "INSERT INTO reviews (ebook_id, user_id, review_text, rating) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iisi", $ebook_id, $user_id, $review_text, $rating);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            
            $conn->commit();
            header("Location: ebook_detail.php?id=" . $ebook_id . "&status=success&message=Review+submitted");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $status_type = 'error';
            $status_message = "Error saving review: " . $e->getMessage();
        }
    }
}

// Fetch ebook details - THIS IS THE FIXED VERSION
if ($ebook_id > 0 && $conn) {
    $sql_fetch_ebook = "SELECT 
        e.*,
        IFNULL(u.username, 'System') AS uploaded_by_username,
        (SELECT COUNT(*) FROM wishlist w WHERE w.user_id = ? AND w.ebook_id = e.id) AS is_in_wishlist,
        (SELECT COUNT(*) FROM read_status rs WHERE rs.user_id = ? AND rs.ebook_id = e.id) AS is_read
        FROM ebooks e
        LEFT JOIN users u ON e.uploaded_by = u.id
        WHERE e.id = ?";

    $stmt_ebook = $conn->prepare($sql_fetch_ebook);
    $stmt_ebook->bind_param("iii", $user_id, $user_id, $ebook_id);
    $stmt_ebook->execute();
    $result_ebook = $stmt_ebook->get_result();
    
    if ($result_ebook->num_rows == 1) {
        $ebook = $result_ebook->fetch_assoc();
    } else {
        $errorMessage = "Ebook not found in database";
    }
    $stmt_ebook->close();

    // Fetch reviews
    $sql_fetch_reviews = "SELECT r.*, u.username 
                         FROM reviews r
                         JOIN users u ON r.user_id = u.id
                         WHERE r.ebook_id = ?
                         ORDER BY r.created_at DESC";
    $stmt_reviews = $conn->prepare($sql_fetch_reviews);
    $stmt_reviews->bind_param("i", $ebook_id);
    $stmt_reviews->execute();
    $reviews = $stmt_reviews->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_reviews->close();

    // Calculate average rating
    $sql_avg_rating = "SELECT AVG(rating) AS avg_rating FROM reviews WHERE ebook_id = ?";
    $stmt_avg = $conn->prepare($sql_avg_rating);
    $stmt_avg->bind_param("i", $ebook_id);
    $stmt_avg->execute();
    $average_rating = round($stmt_avg->get_result()->fetch_assoc()['avg_rating'] ?? 0, 1);
    $stmt_avg->close();

    // Check user's existing review
    $sql_user_review = "SELECT rating, review_text FROM reviews WHERE ebook_id = ? AND user_id = ?";
    $stmt_user_review = $conn->prepare($sql_user_review);
    $stmt_user_review->bind_param("ii", $ebook_id, $user_id);
    $stmt_user_review->execute();
    $user_review_data = $stmt_user_review->get_result()->fetch_assoc();
    if ($user_review_data) {
        $user_has_reviewed = true;
        $user_review_rating = $user_review_data['rating'];
        $user_review_text = $user_review_data['review_text'];
    }
    $stmt_user_review->close();
}

require_once 'includes/header.php';
?>

<div class="container ebook-detail-container">
    <a href="javascript:history.back()" class="back-arrow-button" title="Back to previous page">
        <i class="fas fa-arrow-left"></i>
    </a>

    <?php if ($ebook): ?>
        <h2 class="text-3xl font-bold text-gray-800 mb-4"><?= htmlspecialchars($ebook['tajuk'] ?? 'N/A') ?></h2>
        
        <div class="ebook-info">
            <p><strong>Author:</strong> <?= htmlspecialchars($ebook['penulis'] ?? 'N/A') ?></p>
            <p><strong>Genre:</strong> <?= htmlspecialchars($ebook['genre'] ?? 'N/A') ?></p>
            <p><strong>Pages:</strong> <?= htmlspecialchars($ebook['muka_surat'] ?? 'N/A') ?></p>
            <p><strong>Price:</strong> RM <?= number_format($ebook['harga_rm'] ?? 0, 2) ?></p>
            <p><strong>Uploaded by:</strong> <?= htmlspecialchars($ebook['uploaded_by_username']) ?> on 
               <?= date("F j, Y", strtotime($ebook['created_at'])) ?></p>
            <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($ebook['description'] ?? '')) ?></p>
        </div>

        <div class="ebook-actions mt-4">
            <?php if ($ebook['is_in_wishlist']): ?>
                <a href="ebook_detail.php?action=remove_from_wishlist&ebook_id=<?= $ebook['id'] ?>" 
                   class="btn btn-wishlist">Remove from Wishlist</a>
            <?php else: ?>
                <a href="ebook_detail.php?action=add_to_wishlist&ebook_id=<?= $ebook['id'] ?>" 
                   class="btn btn-wishlist">Add to Wishlist</a>
            <?php endif; ?>

            <?php if ($ebook['is_read']): ?>
                <a href="ebook_detail.php?action=mark_as_unread&ebook_id=<?= $ebook['id'] ?>" 
                   class="btn btn-read">Mark as Unread</a>
            <?php else: ?>
                <a href="ebook_detail.php?action=mark_as_read&ebook_id=<?= $ebook['id'] ?>" 
                   class="btn btn-read">Mark as Read</a>
            <?php endif; ?>
        </div>

        <div class="reviews-section mt-5">
            <h3>Reviews (Average: <?= $average_rating ?> / 5)</h3>
            
            <?php if ($status_message): ?>
                <div class="alert alert-<?= $status_type ?>"><?= $status_message ?></div>
            <?php endif; ?>

            <form method="POST" class="review-form">
    <input type="hidden" name="ebook_id" value="<?= $ebook_id ?>">
    
    <div class="rating-input">
        <label>Your Rating:</label>
        <div class="stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="star <?= ($i <= ($user_review_rating ?? 0)) ? 'active' : '' ?>" 
                      data-rating="<?= $i ?>">★</span>
            <?php endfor; ?>
            <input type="hidden" name="rating" id="rating-value" 
                   value="<?= $user_review_rating ?? '' ?>">
        </div>
    </div>
    
    <div class="form-group">
        <label for="review-text">Your Review:</label>
        <textarea id="review-text" name="review_text" rows="4"><?= 
            htmlspecialchars($user_review_text ?? '') ?></textarea>
    </div>
    
    <button type="submit" name="submit_review" class="btn btn-submit">
        <?= $user_has_reviewed ? 'Update Review' : 'Submit Review' ?>
    </button>
</form>

            <div class="all-reviews">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review">
                            <div class="review-header">
                                <strong><?= htmlspecialchars($review['username']) ?></strong>
                                <div class="review-rating">
                                    <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                                </div>
                                <small><?= date("M j, Y", strtotime($review['created_at'])) ?></small>
                            </div>
                            <div class="review-content"><?= nl2br(htmlspecialchars($review['review_text'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No reviews yet. Be the first to review!</p>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="error-message">
            <p><?= $errorMessage ?? "Ebook not found" ?></p>
            <a href="index.php" class="btn">Back to Library</a>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>

<style>
.ebook-detail-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.back-arrow-button {
    display: inline-block;
    margin-bottom: 20px;
    color: #6b46c1;
    font-size: 1.2rem;
}

.ebook-info p {
    margin: 8px 0;
}

.btn {
    display: inline-block;
    padding: 8px 16px;
    margin-right: 10px;
    border-radius: 4px;
    text-decoration: none;
    color: white;
}

.btn-wishlist {
    background-color: #6b46c1;
}

.btn-read {
    background-color: #4299e1;
}

.btn-submit {
    background-color: #38a169;
}

.review-form {
    margin: 20px 0;
    padding: 20px;
    background: #f7fafc;
    border-radius: 8px;
}

.stars {
    margin: 10px 0;
}

.star {
    font-size: 24px;
    color: #cbd5e0;
    cursor: pointer;
}

.star.active {
    color: #f6e05e;
}

.review {
    margin: 15px 0;
    padding: 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.alert {
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
}

.alert-error {
    background-color: #fed7d7;
    color: #c53030;
}

.alert-success {
    background-color: #c6f6d5;
    color: #22543d;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star');
    const ratingInput = document.getElementById('rating-value');
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.dataset.rating;
            ratingInput.value = rating;
            
            stars.forEach((s, index) => {
                s.classList.toggle('active', index < rating);
            });
        });
    });
});
</script>