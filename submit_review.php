<?php
// submit_review.php
// Handles the submission of ebook ratings and reviews.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db_connect.php'; // Include your database connection

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php"); // Redirect to login if not logged in
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ebook_id = filter_input(INPUT_POST, 'ebook_id', FILTER_VALIDATE_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $review_text = trim($_POST['review_text'] ?? ''); // Use trim and null coalesce for safety

    // Basic validation
    if (!$ebook_id || $ebook_id <= 0) {
        $response['message'] = "Invalid Ebook ID.";
    } elseif ($rating === false || $rating < 1 || $rating > 5) {
        $response['message'] = "Please provide a rating between 1 and 5 stars.";
    } elseif (empty($review_text)) {
        // Decide if review text is optional or mandatory
        // For now, let's make it optional, so this check is commented out
        // $response['message'] = "Review text cannot be empty.";
    } else {
        try {
            // Check if the user has already reviewed this ebook
            $check_sql = "SELECT id FROM reviews WHERE ebook_id = ? AND user_id = ?";
            if ($stmt_check = $conn->prepare($check_sql)) {
                $stmt_check->bind_param("ii", $ebook_id, $user_id);
                $stmt_check->execute();
                $stmt_check->store_result();

                if ($stmt_check->num_rows > 0) {
                    // User has already reviewed, update the existing review
                    $update_sql = "UPDATE reviews SET rating = ?, review_text = ?, created_at = CURRENT_TIMESTAMP WHERE ebook_id = ? AND user_id = ?";
                    if ($stmt_update = $conn->prepare($update_sql)) {
                        $stmt_update->bind_param("isii", $rating, $review_text, $ebook_id, $user_id);
                        if ($stmt_update->execute()) {
                            $response['success'] = true;
                            $response['message'] = "Your review has been updated successfully!";
                        } else {
                            $response['message'] = "Error updating review: " . $stmt_update->error;
                        }
                        $stmt_update->close();
                    } else {
                        throw new Exception("Error preparing update statement: " . $conn->error);
                    }
                } else {
                    // No existing review, insert a new one
                    $insert_sql = "INSERT INTO reviews (ebook_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)";
                    if ($stmt_insert = $conn->prepare($insert_sql)) {
                        $stmt_insert->bind_param("iiis", $ebook_id, $user_id, $rating, $review_text);
                        if ($stmt_insert->execute()) {
                            $response['success'] = true;
                            $response['message'] = "Your review has been submitted successfully!";
                        } else {
                            $response['message'] = "Error submitting review: " . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    } else {
                        throw new Exception("Error preparing insert statement: " . $conn->error);
                    }
                }
                $stmt_check->close();
            } else {
                throw new Exception("Error preparing check statement: " . $conn->error);
            }
        } catch (Exception $e) {
            $response['message'] = "Database error: " . $e->getMessage();
            error_log("Review submission error: " . $e->getMessage());
        }
    }
} else {
    $response['message'] = "Invalid request method.";
}

// Close database connection
if (isset($conn) && $conn->ping()) {
    $conn->close();
}

// Redirect back to the ebook detail page with a message
$redirect_url = "ebook_detail.php?id=" . $ebook_id;
if ($response['success']) {
    $redirect_url .= "&status=success&message=" . urlencode($response['message']);
} else {
    $redirect_url .= "&status=error&message=" . urlencode($response['message']);
}
header("location: " . $redirect_url);
exit;
?>