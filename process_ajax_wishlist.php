<?php
// process_ajax_wishlist.php - NEW FILE NAME
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db_connect.php';

// Set header to return JSON response
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "user") {
    $response['message'] = 'User not authenticated or not authorized.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    // Use INPUT_POST because your JavaScript sends a POST request
    $ebook_id = filter_input(INPUT_POST, 'ebook_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

    if (!$ebook_id) {
        $response['message'] = 'Invalid Ebook ID provided.';
        echo json_encode($response);
        exit;
    }

    switch ($action) {
        case 'add_wishlist':
            try {
                // Check if already in wishlist to prevent duplicate entries
                $check_sql = "SELECT id FROM wishlist WHERE user_id = ? AND ebook_id = ?";
                if ($stmt = $conn->prepare($check_sql)) {
                    $stmt->bind_param("ii", $user_id, $ebook_id);
                    $stmt->execute();
                    $stmt->store_result();

                    if ($stmt->num_rows == 0) { // Only add if not already present
                        $insert_sql = "INSERT INTO wishlist (user_id, ebook_id) VALUES (?, ?)";
                        if ($stmt_insert = $conn->prepare($insert_sql)) {
                            $stmt_insert->bind_param("ii", $user_id, $ebook_id);
                            if ($stmt_insert->execute()) {
                                $response['success'] = true;
                                $response['message'] = 'Ebook added to wishlist successfully!';
                            } else {
                                throw new Exception("Error inserting into wishlist: " . $stmt_insert->error);
                            }
                            $stmt_insert->close();
                        } else {
                            throw new Exception("Error preparing insert statement: " . $conn->error);
                        }
                    } else {
                        $response['message'] = 'Ebook is already in your wishlist.';
                    }
                    $stmt->close();
                } else {
                    throw new Exception("Error preparing check statement: " . $conn->error);
                }
            } catch (Exception $e) {
                $response['message'] = "An unexpected error occurred: " . $e->getMessage();
                error_log("Wishlist add operation failed: " . $e->getMessage());
            }
            break;

        case 'mark_read':
            // Add your 'mark_read' logic here, similar to 'add_wishlist'
            // Check if already read to prevent duplicates
            $check_read_sql = "SELECT id FROM read_status WHERE user_id = ? AND ebook_id = ?";
            if ($stmt_read = $conn->prepare($check_read_sql)) {
                $stmt_read->bind_param("ii", $user_id, $ebook_id);
                $stmt_read->execute();
                $stmt_read->store_result();

                if ($stmt_read->num_rows == 0) {
                    $insert_read_sql = "INSERT INTO read_status (user_id, ebook_id) VALUES (?, ?)";
                    if ($stmt_insert_read = $conn->prepare($insert_read_sql)) {
                        $stmt_insert_read->bind_param("ii", $user_id, $ebook_id);
                        if ($stmt_insert_read->execute()) {
                            $response['success'] = true;
                            $response['message'] = 'Ebook marked as read successfully!';
                        } else {
                            $response['message'] = 'Error marking as read: ' . $stmt_insert_read->error;
                        }
                        $stmt_insert_read->close();
                    } else {
                        $response['message'] = 'Database prepare error (mark_read insert): ' . $conn->error;
                    }
                } else {
                    $response['message'] = 'Ebook is already marked as read.';
                }
                $stmt_read->close();
            } else {
                $response['message'] = 'Database prepare error (mark_read check): ' . $conn->error;
            }
            break;

        default:
            $response['message'] = 'Invalid action specified.';
            break;
    }
} else {
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

echo json_encode($response); // Output the JSON response
exit; // Crucial to stop further output