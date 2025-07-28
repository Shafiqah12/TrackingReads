<?php
// admin/process-change-password.php
// This file handles the submission of the change password form for all authorized roles.

session_start();

// Include database connection
require_once '../includes/db_connect.php'; // Adjust path as needed

// Define allowed roles for this page.
// Added 'user', 'clerk', 'manager' to the allowed roles.
$allowedRoles = ['admin', 'manager', 'clerk', 'user']; // MODIFIED: Added more roles

// Check if the user is NOT logged in, or if their role is NOT in the allowed roles.
// Redirect to login if not authorized.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["user_role"], $allowedRoles)) { // MODIFIED: Changed condition
    error_log("Access Denied: process-change-password.php - User not logged in or role not allowed. Role: " . ($_SESSION['user_role'] ?? 'N/A'));
    header("location: ../login.php");
    exit;
}

// Define variables and initialize with empty values
$current_password = $new_password = $confirm_password = "";
$current_password_err = $new_password_err = $confirm_password_err = "";

// Process form submission if it's a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get user ID from session
    $user_id = $_SESSION["user_id"] ?? null; // Use null coalescing to prevent undefined index notice
    if (is_null($user_id)) {
        $_SESSION['error_message'] = "User ID not found in session. Please log in again.";
        header("location: change-password.php");
        exit();
    }
    error_log("DEBUG: process-change-password.php - Processing POST for User ID: " . $user_id);

    // Validate current password
    if (empty(trim($_POST["current_password"]))) {
        $current_password_err = "Please enter your current password.";
    } else {
        $current_password = trim($_POST["current_password"]);
    }

    // Validate new password
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Please enter a new password.";
    } elseif (strlen(trim($_POST["new_password"])) < 6) { // Minimum 6 characters
        $new_password_err = "Password must have at least 6 characters.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }

    // Validate confirm new password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the new password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($new_password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "New password and confirmation password do not match.";
        }
    }

    // Check input errors before updating the database
    if (empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        // Prepare a select statement to get the stored password hash
        $sql = "SELECT password FROM users WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $param_id);
            $param_id = $user_id;

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($hashed_password);
                    if ($stmt->fetch()) {
                        // Verify current password
                        if (password_verify($current_password, $hashed_password)) {
                            // Current password is correct, now update new password
                            $update_sql = "UPDATE users SET password = ? WHERE id = ?";

                            if ($stmt_update = $conn->prepare($update_sql)) {
                                $stmt_update->bind_param("si", $param_new_password, $param_id_update);

                                // Hash the new password
                                $param_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $param_id_update = $user_id;

                                if ($stmt_update->execute()) {
                                    // Password updated successfully
                                    $_SESSION['success_message'] = "Password updated successfully.";

                                    // Determine redirect location based on user role
                                    $redirect_page = '';
                                    switch ($_SESSION['user_role']) {
                                        case 'admin':
                                            $redirect_page = 'admin-profile.php';
                                            break;
                                        case 'manager':
                                            $redirect_page = 'manager-profile.php'; // Assuming you have this
                                            break;
                                        case 'clerk':
                                            $redirect_page = 'clerk-profile.php'; // Assuming you have this
                                            break;
                                        case 'user':
                                            $redirect_page = 'user-profile.php'; // Assuming you have this
                                            break;
                                        default:
                                            $redirect_page = '../dashboard.php'; // Fallback to a general dashboard
                                            break;
                                    }
                                    header("location: " . $redirect_page); // MODIFIED: Dynamic redirect
                                    exit();
                                } else {
                                    $_SESSION['error_message'] = "Error updating password: " . $stmt_update->error;
                                    error_log("ERROR: process-change-password.php - Error updating password for User ID $user_id: " . $stmt_update->error);
                                }
                                $stmt_update->close();
                            } else {
                                $_SESSION['error_message'] = "Database error: Could not prepare update statement.";
                                error_log("ERROR: process-change-password.php - Failed to prepare update statement: " . $conn->error);
                            }
                        } else {
                            $_SESSION['error_message'] = "The current password you entered is not valid.";
                            error_log("WARNING: process-change-password.php - Invalid current password for User ID $user_id.");
                        }
                    }
                } else {
                    $_SESSION['error_message'] = "User not found.";
                    error_log("WARNING: process-change-password.php - User ID $user_id not found in DB.");
                }
            } else {
                $_SESSION['error_message'] = "Error fetching user data: " . $stmt->error;
                error_log("ERROR: process-change-password.php - Error executing select statement for User ID $user_id: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Database error: Could not prepare select statement.";
            error_log("ERROR: process-change-password.php - Failed to prepare select statement: " . $conn->error);
        }
    } else {
        // Collect all error messages for display
        $all_errors = array_filter([$current_password_err, $new_password_err, $confirm_password_err]);
        $_SESSION['error_message'] = implode("<br>", $all_errors);
        error_log("WARNING: process-change-password.php - Validation errors: " . implode(" | ", $all_errors));
    }

    // Redirect back to the change password page if there were errors
    header("location: change-password.php");
    exit();

} else {
    // If accessed directly without POST request, redirect
    // Redirect based on role if possible, otherwise a default
    if (isset($_SESSION['user_role'])) {
        switch ($_SESSION['user_role']) {
            case 'admin':
                header("location: admin-profile.php");
                break;
            case 'manager':
                header("location: manager-profile.php");
                break;
            case 'clerk':
                header("location: clerk-profile.php");
                break;
            case 'user':
                header("location: user-profile.php");
                break;
            default:
                header("location: ../dashboard.php"); // Fallback
                break;
        }
    } else {
        header("location: ../login.php"); // Not logged in, go to login
    }
    exit;
}

// Close connection (if not already closed by statement close or if an error occurred before reaching close)
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>