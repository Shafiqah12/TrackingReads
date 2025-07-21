<?php
// ebook_detail.php
// Displays all details for a single ebook and will include rating/review/wishlist/read status

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db_connect.php';

// Access Control: Only check if logged in. No role-based access needed for viewing details.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php"); // Redirect to login.php directly
    exit;
}

$user_id = $_SESSION['user_id'];
$ebook = null;
$reviews = []; // Initialize reviews array
$average_rating = 0; // Initialize average rating
$user_has_reviewed = false; // Track if current user has reviewed this ebook
$user_review_rating = null; // Store user's existing rating
$user_review_text = null; // Store user's existing review text

// Get ebook ID from URL, ensure it's an integer
$ebook_id = filter_var($_GET['id'] ?? '', FILTER_SANITIZE_NUMBER_INT);

// Handle messages from redirect (e.g., after an action)
$status_type = '';
$status_message = '';
if (isset($_GET['status']) && isset($_GET['message'])) {
    $status_type = htmlspecialchars($_GET['status']);
    $status_message = htmlspecialchars($_GET['message']);
}

// --- Handle Wishlist/Read Status Actions (GET requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['ebook_id'])) {
    $action_ebook_id = filter_var($_GET['ebook_id'], FILTER_SANITIZE_NUMBER_INT);
    if ($user_id && $action_ebook_id) {
        try {
            $conn->begin_transaction(); // Start transaction for atomicity
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
                    if ($stmt->errno == 1062) { // Duplicate entry
                        $message = "Ebook already in your wishlist.";
                    } else {
                        throw new Exception("Error adding to wishlist: " . $stmt->error);
                    }
                }
                $stmt->close();
            } elseif ($_GET['action'] === 'remove_from_wishlist') {
                $sql = "DELETE FROM wishlist WHERE user_id = ? AND ebook_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $user_id, $action_ebook_id);
                if ($stmt->execute()) {
                    $message = "Ebook removed from wishlist.";
                    $status = 'success';
                } else {
                    throw new Exception("Error removing from wishlist: " . $stmt->error);
                }
                $stmt->close();
            } elseif ($_GET['action'] === 'mark_as_read') {
                $sql = "INSERT INTO read_status (user_id, ebook_id) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $user_id, $action_ebook_id);
                if ($stmt->execute()) {
                    $message = "Ebook marked as read!";
                    $status = 'success';
                } else {
                    if ($stmt->errno == 1062) { // Duplicate entry
                        $message = "Ebook already marked as read.";
                    } else {
                        throw new Exception("Error marking as read: " . $stmt->error);
                    }
                }
                $stmt->close();
            } elseif ($_GET['action'] === 'mark_as_unread') {
                $sql = "DELETE FROM read_status WHERE user_id = ? AND ebook_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $user_id, $action_ebook_id);
                if ($stmt->execute()) {
                    $message = "Ebook marked as unread.";
                    $status = 'success';
                } else {
                    throw new Exception("Error marking as unread: " . $stmt->error);
                }
                $stmt->close();
            }
            $conn->commit(); // Commit transaction
            header("Location: ebook_detail.php?id=" . $action_ebook_id . "&status=" . $status . "&message=" . urlencode($message));
            exit;
        } catch (Exception $e) {
            $conn->rollback(); // Rollback on error
            $errorMessage = "Ralat pangkalan data: " . htmlspecialchars($e->getMessage());
            error_log("Ebook Detail Action Error: " . $e->getMessage());
            // Redirect with error message
            header("Location: ebook_detail.php?id=" . $action_ebook_id . "&status=error&message=" . urlencode($errorMessage));
            exit;
        }
    } else {
        $errorMessage = "ID pengguna atau ebook tidak sah untuk tindakan.";
    }
}

// --- Handle Review Submission (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $review_text = trim($_POST['review_text'] ?? '');
    $rating = filter_var($_POST['rating'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $review_status = 'error';
    $review_message = '';

    if (empty($review_text) && empty($rating)) {
        $review_message = "Sila masukkan ulasan atau penilaian.";
    } elseif (!($rating >= 1 && $rating <= 5)) {
        $review_message = "Penilaian mesti antara 1 hingga 5 bintang.";
    } elseif ($user_id && $ebook_id) {
        try {
            $conn->begin_transaction(); // Start transaction

            // Check if user has already reviewed this ebook
            $check_sql = "SELECT review_id FROM reviews WHERE ebook_id = ? AND user_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $ebook_id, $user_id);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                // User has already reviewed, so UPDATE
                $update_sql = "UPDATE reviews SET review_text = ?, rating = ?, created_at = CURRENT_TIMESTAMP WHERE ebook_id = ? AND user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("siii", $review_text, $rating, $ebook_id, $user_id);
                if ($update_stmt->execute()) {
                    $review_message = "Ulasan dan penilaian anda berjaya dikemas kini!";
                    $review_status = 'success';
                } else {
                    throw new Exception("Ralat mengemas kini ulasan/penilaian: " . $update_stmt->error);
                }
                $update_stmt->close();
            } else {
                // User has not reviewed, so INSERT
                $insert_sql = "INSERT INTO reviews (ebook_id, user_id, review_text, rating) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iisi", $ebook_id, $user_id, $review_text, $rating);
                
                if ($insert_stmt->execute()) {
                    $review_message = "Ulasan dan penilaian anda berjaya dihantar!";
                    $review_status = 'success';
                } else {
                    throw new Exception("Ralat menghantar ulasan/penilaian: " . $insert_stmt->error);
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
            $conn->commit(); // Commit transaction
            // Redirect to prevent re-submission on refresh
            header("Location: ebook_detail.php?id=" . $ebook_id . "&status=" . $review_status . "&message=" . urlencode($review_message));
            exit;
        } catch (Exception $e) {
            $conn->rollback(); // Rollback on error
            $review_message = "Ralat pangkalan data: " . htmlspecialchars($e->getMessage());
            error_log("Ebook Detail Review Submit Error: " . $e->getMessage());
            // Redirect with error message
            header("Location: ebook_detail.php?id=" . $ebook_id . "&status=error&message=" . urlencode($review_message));
            exit;
        }
    } else {
        $review_message = "ID pengguna atau ebook tidak sah untuk menghantar ulasan.";
    }
    // Set status_message for display on current page if not redirected
    $status_type = $review_status;
    $status_message = $review_message;
}


// --- Fetch Ebook Details (after any action) ---
if ($ebook_id > 0 && $conn) {
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

    // --- Fetch Reviews for this Ebook ---
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

    // --- Calculate Average Rating ---
    $sql_avg_rating = "SELECT AVG(rating) AS avg_rating FROM reviews WHERE ebook_id = ?";
    if ($stmt_avg = $conn->prepare($sql_avg_rating)) {
        $stmt_avg->bind_param("i", $ebook_id);
        $stmt_avg->execute();
        $result_avg = $stmt_avg->get_result();
        $avg_row = $result_avg->fetch_assoc();
        $average_rating = round($avg_row['avg_rating'] ?? 0, 1); // Round to 1 decimal place, default 0 if NULL
        $stmt_avg->close();
    }

    // --- Check if current user has reviewed and fetch their review ---
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
    $errorMessage = "Invalid Ebook ID provided or database connection failed.";
}

require_once 'includes/header.php';
?>

<div class="container ebook-detail-container">
    <a href="javascript:history.back()" class="back-arrow-button" title="Back to previous page"><i class="fas fa-arrow-left"></i></a>

    <?php if ($ebook): ?>
        <h2 class="text-3xl font-bold text-gray-800 mb-4"><?= htmlspecialchars($ebook['title'] ?? 'N/A'); ?></h2>
        <p class="text-gray-600 mb-2"><strong>NO:</strong> <?= htmlspecialchars($ebook['no'] ?? 'N/A'); ?></p>
        <p class="text-gray-600 mb-2"><strong>Penulis:</strong> <?= htmlspecialchars($ebook['penulis'] ?? 'N/A'); ?></p>
        <p class="text-gray-600 mb-2"><strong>Genre:</strong> <?= htmlspecialchars($ebook['genre'] ?? 'N/A'); ?></p>
        <p class="text-gray-600 mb-2"><strong>Penerbit:</strong> <?= htmlspecialchars($ebook['penerbit'] ?? 'N/A'); ?></p>
        <p class="text-gray-600 mb-2"><strong>Muka Surat:</strong> <?= htmlspecialchars($ebook['muka_surat'] ?? 'N/A'); ?></p>
        <p class="text-gray-600 mb-2"><strong>Perkataan:</strong> <?= htmlspecialchars($ebook['perkataan'] ?? 'N/A'); ?></p>
        <p class="text-gray-600 mb-2"><strong>Harga (RM):</strong> <?= htmlspecialchars(number_format($ebook['price'] ?? 0, 2)); ?></p>
        <p class="text-gray-600 mb-2"><strong>Bulan:</strong> <?= htmlspecialchars($ebook['bulan'] ?? 'N/A'); ?></p>
        <p class="text-gray-600 mb-2"><strong>Tahun:</strong> <?= htmlspecialchars($ebook['tahun'] ?? 'N/A'); ?></p>
        <p class="text-gray-600 mb-2"><strong>Description:</strong> <?= nl2br(htmlspecialchars($ebook['description'] ?? '')) ?></p>
        <p class="uploaded-info text-gray-600 mb-2">
            <strong>Uploaded by:</strong> <?= htmlspecialchars($ebook['uploaded_by_username'] ?? 'Unknown'); ?> on:
            <?php
            $createdAt = $ebook['created_at'] ?? null;
            if ($createdAt && ($timestamp = strtotime($createdAt)) !== false) {
                echo htmlspecialchars(date("F j, Y, g:i a", $timestamp));
            } else {
                echo 'N/A';
            }
            ?>
        </p>


        <div class="ebook-actions-detail mt-4 flex space-x-2">
            <?php if ($ebook['is_in_wishlist']): ?>
                <a href="ebook_detail.php?action=remove_from_wishlist&ebook_id=<?= htmlspecialchars($ebook['id']) ?>&id=<?= htmlspecialchars($ebook['id']) ?>" class="btn btn-secondary">Remove from Wishlist <i class="fas fa-heart"></i></a>
            <?php else: ?>
                <a href="ebook_detail.php?action=add_to_wishlist&ebook_id=<?= htmlspecialchars($ebook['id']) ?>&id=<?= htmlspecialchars($ebook['id']) ?>" class="btn btn-info">Add to Wishlist <i class="far fa-heart"></i></a>
            <?php endif; ?>

            <?php if ($ebook['is_read']): ?>
                <a href="ebook_detail.php?action=mark_as_unread&ebook_id=<?= htmlspecialchars($ebook['id']) ?>&id=<?= htmlspecialchars($ebook['id']) ?>" class="btn btn-secondary">Mark as Unread <i class="fas fa-check-circle"></i></a>
            <?php else: ?>
                <a href="ebook_detail.php?action=mark_as_read&ebook_id=<?= htmlspecialchars($ebook['id']) ?>&id=<?= htmlspecialchars($ebook['id']) ?>" class="btn btn-info">Mark as Read <i class="far fa-check-circle"></i></a>
            <?php endif; ?>
        </div>

        <hr class="my-4 border-gray-300">

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
                    <div class="star-rating" data-rating="<?php echo htmlspecialchars($user_review_rating ?? 0); ?>">
                        <span class="star-rating-star" data-value="1">&#9733;</span>
                        <span class="star-rating-star" data-value="2">&#9733;</span>
                        <span class="star-rating-star" data-value="3">&#9733;</span>
                        <span class="star-rating-star" data-value="4">&#9733;</span>
                        <span class="star-rating-star" data-value="5">&#9733;</span>
                    </div>
                    <input type="hidden" name="rating" id="rating_input" value="<?= htmlspecialchars($user_review_rating ?? ''); ?>" required>
                </div>

                <div class="form-group mb-3">
                    <label for="review_text" class="block text-gray-700 text-sm font-bold mb-2">Your Review:</label>
                    <textarea name="review_text" id="review_text" rows="5" class="form-control shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Write your review here..."><?= htmlspecialchars($user_review_text ?? ''); ?></textarea>
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
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item bg-white p-4 rounded-lg shadow-sm mb-4 border-l-4 border-purple-500">
                        <p class="font-semibold text-gray-800">
                            <?= htmlspecialchars($review['username'] ?? 'Anonymous') ?> 
                            <span class="text-yellow-500 ml-2">
                                <?php for ($i = 0; $i < ($review['rating'] ?? 0); $i++): ?><i class="fas fa-star"></i><?php endfor; ?>
                                <?php for ($i = 0; $i < (5 - ($review['rating'] ?? 0)); $i++): ?><i class="far fa-star"></i><?php endfor; ?>
                            </span>
                        </p>
                        <p class="text-gray-500 text-sm mt-1">Reviewed on: <?= htmlspecialchars(date("F j, Y", strtotime($review['created_at'] ?? ''))); ?></small></p>
                        <p class="text-gray-700 mt-2"><?= nl2br(htmlspecialchars($review['review_text'] ?? '')) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="message error">
            <p><?= htmlspecialchars($errorMessage ?? "Ebook not found or an error occurred."); ?></p>
            <p><a href="index.php" class="text-blue-500 hover:underline">Go back to Ebook Library</a></p>
        </div>
    <?php endif; ?>
</div>

<?php
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once 'includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const starRatingContainer = document.querySelector('.star-rating');
    const ratingInput = document.getElementById('rating_input');

    if (starRatingContainer && ratingInput) {
        const stars = starRatingContainer.querySelectorAll('.star-rating-star');

        function setRating(rating) {
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.style.color = 'gold'; // Filled star
                } else {
                    star.style.color = '#ccc'; // Unfilled star
                }
            });
            ratingInput.value = rating;
            starRatingContainer.dataset.rating = rating; // Update data-rating attribute
        }

        // Initialize stars based on existing rating
        setRating(parseInt(ratingInput.value) || 0);

        stars.forEach(star => {
            star.addEventListener('click', function() {
                const value = parseInt(this.dataset.value);
                setRating(value);
            });

            star.addEventListener('mouseover', function() {
                const value = parseInt(this.dataset.value);
                stars.forEach((s, index) => {
                    if (index < value) {
                        s.style.color = 'gold';
                    } else {
                        s.style.color = '#ccc';
                    }
                });
            });

            star.addEventListener('mouseout', function() {
                // Revert to the selected rating when mouse leaves
                setRating(parseInt(ratingInput.value) || 0);
            });
        });
    }
});
</script>

<style>
/* Add this to your style.css or a <style> block if you don't have style.css */
/* Common Star Styling for reviews list */
.star {
    color: #ccc; /* Default star color (unfilled) */
    font-size: 1.2em;
}
.star.filled {
    color: gold; /* Filled star color */
}

/* Star Rating Input Specific Styles */
.star-rating {
    display: inline-block;
    font-size: 2em; /* Larger stars for input */
    cursor: pointer;
}

.star-rating-star {
    color: #ccc; /* Unfilled star color */
    display: inline-block;
    transition: color 0.2s ease-in-out;
}

/* Hover effect: Fill stars from the left up to and including the hovered star */
.star-rating-star:hover,
.star-rating-star:hover ~ .star-rating-star {
    color: gold;
}

/* When a star is selected (via JavaScript, data-rating attribute) */
.star-rating[data-rating] .star-rating-star {
    color: #ccc; /* Start by resetting all to unfilled */
}

/* Fill stars up to the 'data-rating' value from left to right */
.star-rating[data-rating="1"] .star-rating-star:nth-child(1),
.star-rating[data-rating="2"] .star-rating-star:nth-child(1),
.star-rating[data-rating="2"] .star-rating-star:nth-child(2),
.star-rating[data-rating="3"] .star-rating-star:nth-child(1),
.star-rating[data-rating="3"] .star-rating-star:nth-child(2),
.star-rating[data-rating="3"] .star-rating-star:nth-child(3),
.star-rating[data-rating="4"] .star-rating-star:nth-child(1),
.star-rating[data-rating="4"] .star-rating-star:nth-child(2),
.star-rating[data-rating="4"] .star-rating-star:nth-child(3),
.star-rating[data-rating="4"] .star-rating-star:nth-child(4),
.star-rating[data-rating="5"] .star-rating-star:nth-child(1),
.star-rating[data-rating="5"] .star-rating-star:nth-child(2),
.star-rating[data-rating="5"] .star-rating-star:nth-child(3),
.star-rating[data-rating="5"] .star-rating-star:nth-child(4),
.star-rating[data-rating="5"] .star-rating-star:nth-child(5) {
    color: gold;
}


/* Message and form control styles (kept from original, adjust as needed) */
.message {
    padding: 10px;
    margin-bottom: 20px;
    border-radius: 5px;
}
.message.success {
    background-color: #d4edda;
    color: #A08AD3;
    border: 1px solid #c3e6cb;
}
.message.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
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
    box-sizing: border-box;
}
textarea.form-control {
    resize: vertical;
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
    margin-right: 5px;
}
.btn-primary {
    background-color: #bfaaebff;
    color: white;
}
.btn-primary:hover {
    background-color: #b396f3ff;
}
.btn-secondary {
    background-color: #B8AEE2;
    color: white;
}
.btn-secondary:hover {
    background-color: #ad99fbff;
}
.btn-info { /* Added for wishlist/read buttons */
    background-color: #A08AD3;
    color: white;
}
.btn-info:hover {
    background-color: #8a73c3;
}
.btn-success { /* Added for mark as read button */
    background-color: #4CAF50;
    color: white;
}
.btn-success:hover {
    background-color: #45a049;
}


.mb-3 {
    margin-bottom: 1rem;
}

.ebook-detail-container {
    position: relative;
    padding-top: 50px;
}

    .back-arrow-button {
        position: absolute;
        top: 15px;
        left: 15px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #A08AD3;
        color: white;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 1.2em;
        text-decoration: none;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        transition: background-color 0.3s ease;
        z-index: 10;
    }

    .back-arrow-button:hover {
        background-color: #A08AD3;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ratingContainer = document.querySelector('.star-rating');
        const hiddenInput = document.getElementById('rating_input');
        const stars = Array.from(ratingContainer.querySelectorAll('.star-rating-star')); // Convert NodeList to Array
        let currentRating = parseInt(ratingContainer.dataset.rating) || 0;

        // Function to update star display based on a given rating
        function updateStars(rating) {
            stars.forEach((star, index) => {
                if (index < rating) { // Fill stars from left up to the rating
                    star.style.color = 'gold';
                } else {
                    star.style.color = '#ccc';
                }
            });
        }

        // Initialize stars based on existing user rating
        if (currentRating > 0) {
            updateStars(currentRating);
        } else {
            // Ensure all stars are initially unfilled if no rating
            updateStars(0);
        }

        // Mouseover (hover) effect
        ratingContainer.addEventListener('mouseover', function(e) {
            if (e.target.classList.contains('star-rating-star')) {
                const hoverValue = parseInt(e.target.dataset.value);
                updateStars(hoverValue);
            }
        });

        // Mouseout (reset) effect
        ratingContainer.addEventListener('mouseout', function() {
            // Reset to the currently selected rating, or 0 if none
            updateStars(currentRating);
        });

        // Click (select) effect
        ratingContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('star-rating-star')) {
                const clickedValue = parseInt(e.target.dataset.value);
                currentRating = clickedValue; // Update currentRating
                hiddenInput.value = currentRating; // Set hidden input value
                updateStars(currentRating); // Update display
            }
        });
    });
</script>