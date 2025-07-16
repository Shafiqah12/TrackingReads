<?php
// process-profile-edit.php
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';

$user_id = $_SESSION["user_id"];
$new_email = '';
$email_err = '';
$profile_picture_err = '';
$upload_success = false;

// Path to store uploaded profile pictures relative to the NOTESYNC root
$target_dir_relative = '/NOTESYNC/uploads/profile_pictures/';
// Absolute path for file system operations (using $_SERVER['DOCUMENT_ROOT'])
$target_dir_absolute = $_SERVER['DOCUMENT_ROOT'] . $target_dir_relative;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Handle Email Update ---
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        $new_email = trim($_POST["email"]);

        // Check if email is already taken by another user
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $new_email, $user_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $email_err = "This email is already taken by another user.";
            }
            $stmt->close();
        } else {
            error_log("USER PROCESS PROFILE EDIT: Error preparing email check query: " . $conn->error);
        }
    }

    // --- Handle Profile Picture Upload ---
    $current_profile_picture = null; // To store existing path if no new file is uploaded

    // Fetch current profile picture path from DB to handle deletion of old file
    $sql_fetch_pic = "SELECT profile_picture FROM users WHERE id = ?";
    if ($stmt_fetch_pic = $conn->prepare($sql_fetch_pic)) {
        $stmt_fetch_pic->bind_param("i", $user_id);
        if ($stmt_fetch_pic->execute()) {
            $stmt_fetch_pic->bind_result($current_profile_picture);
            $stmt_fetch_pic->fetch();
        }
        $stmt_fetch_pic->close();
    }

    $new_profile_picture_path = null;
    // Check if a new file was uploaded without errors
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == UPLOAD_ERR_OK) {
        $file_name = basename($_FILES["profile_picture"]["name"]);
        $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $unique_filename = uniqid('profile_', true) . '.' . $file_type; // Create a unique filename
        $target_file = $target_dir_absolute . $unique_filename;

        // Check if upload directory exists, create if not
        if (!is_dir($target_dir_absolute)) {
            mkdir($target_dir_absolute, 0777, true); // Create recursively with full permissions
        }

        // Check file size (max 2MB)
        if ($_FILES["profile_picture"]["size"] > 2 * 1024 * 1024) {
            $profile_picture_err = "Sorry, your file is too large. Max 2MB.";
        }

        // Allow certain file formats
        $allowed_types = array("jpg", "jpeg", "png", "gif");
        if (!in_array($file_type, $allowed_types)) {
            $profile_picture_err = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }

        // If no errors during checks, attempt to upload file
        if (empty($profile_picture_err)) {
            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                $new_profile_picture_path = $target_dir_relative . $unique_filename;
                $upload_success = true;

                // Delete old profile picture if it exists and is not a default image
                if ($current_profile_picture && file_exists($_SERVER['DOCUMENT_ROOT'] . $current_profile_picture) &&
                    strpos($current_profile_picture, '/TrackingReads/img/default-avatar.png') === false) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $current_profile_picture);
                }
            } else {
                $profile_picture_err = "Sorry, there was an error uploading your file.";
                error_log("USER PROFILE UPLOAD: Error moving uploaded file for user " . $user_id . ": " . $_FILES["profile_picture"]["error"]);
            }
        }
    } else if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE) {
        // Handle other PHP upload errors
        $profile_picture_err = "File upload error: " . $_FILES["profile_picture"]["error"];
        error_log("USER PROFILE UPLOAD: PHP File Upload Error for user " . $user_id . ": " . $_FILES["profile_picture"]["error"]);
    }

    // --- Update Database (Combine email and profile picture updates) ---
    if (empty($email_err) && empty($profile_picture_err)) {
        $update_fields = [];
        $bind_types = "";
        $bind_params = [];

        // Add email to update if it's new/changed
        if (!empty($new_email)) {
            $update_fields[] = "email = ?";
            $bind_types .= "s";
            $bind_params[] = $new_email;
        }

        // Add profile picture to update if a new file was successfully uploaded
        if ($upload_success) {
            $update_fields[] = "profile_picture = ?";
            $bind_types .= "s";
            $bind_params[] = $new_profile_picture_path;
        }

        if (!empty($update_fields)) {
            $sql_update = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $bind_types .= "i"; // Add 'i' for user_id
            $bind_params[] = $user_id;

            if ($stmt = $conn->prepare($sql_update)) {
                $stmt->bind_param($bind_types, ...$bind_params);

                if ($stmt->execute()) {
                    // Update session variables if changes were successful
                    if (!empty($new_email)) $_SESSION['email'] = $new_email;
                    if ($upload_success) $_SESSION['profile_picture'] = $new_profile_picture_path;

                    $_SESSION['profile_updated'] = true; // Set a flag for success message
                    header("location: profile.php");
                    exit;
                } else {
                    echo "Something went wrong. Please try again later.";
                    error_log("USER PROCESS PROFILE EDIT: Error executing update query for user " . $user_id . ": " . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log("USER PROCESS PROFILE EDIT: Error preparing update query for user " . $user_id . ": " . $conn->error);
            }
        } else {
            // If no email or picture was changed, just redirect back without an update
            header("location: profile.php");
            exit;
        }
    }
}
$conn->close();

// If there were errors, redirect back to the edit-profile.php page with error messages
$redirect_url = "edit-profile.php";
$params = [];
if (!empty($email_err)) $params[] = "email_err=" . urlencode($email_err);
if (!empty($profile_picture_err)) $params[] = "profile_picture_err=" . urlencode($profile_picture_err);

if (!empty($params)) {
    $redirect_url .= "?" . implode("&", $params);
}
header("location: " . $redirect_url);
exit;
?>