<?php
// admin/edit_user.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Adjust path as needed

// Redirect if not logged in or not authorized
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: ../login.php");
    exit();
}

$userData = null;
$error_message = '';
$success_message = '';

// Check if user ID is provided in the URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];

    // Fetch existing user data
    try {
        // Corrected column names: 'role' and 'profile_picture'
        $stmt = $conn->prepare("SELECT id, username, email, role, profile_picture FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $userData = $result->fetch_assoc();
        } else {
            $error_message = "User not found.";
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
} else {
    $error_message = "No user ID provided.";
}

// Handle form submission for updating user data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userData) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_role = trim($_POST['user_role']);

    $user_id_from_post = (int)$_POST['user_id'];

    // Basic validation
    if (empty($new_username) || empty($new_email) || empty($new_role)) {
        $error_message = "All fields (Username, Email, Role) are required.";
    } elseif ($user_id_from_post !== $userData['id']) {
        $error_message = "Security error: Mismatched user ID.";
    } else {
        // Additional validation: Ensure only allowed roles are set
        if (!in_array($new_role, ['admin', 'manager', 'clerk'])) { // Valid roles from your schema
            $error_message = "Invalid user role specified.";
        } else {
            try {
                // Update the user's details
                // Corrected: 'role = ?'
                $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                $update_stmt->bind_param("sssi", $new_username, $new_email, $new_role, $user_id_from_post);

                if ($update_stmt->execute()) {
                    $success_message = "User details updated successfully.";
                    // Re-fetch data to show updated info on the form after successful update
                    // Corrected: 'profile_picture' here too
                    $stmt = $conn->prepare("SELECT id, username, email, role, profile_picture FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $userData = $result->fetch_assoc(); // Update $userData with new values
                    $stmt->close();
                } else {
                    $error_message = "Failed to update user details: " . $update_stmt->error;
                }
                $update_stmt->close();
            } catch (mysqli_sql_exception $e) {
                $error_message = "Database error during update: " . $e->getMessage();
            }
        }
    }
}

$conn->close();
require_once '../includes/header.php'; // Include your header
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - TrackingReads</title>
    <link rel="stylesheet" href="../css/style.css"> </head>
<body>
    <main class="main-content-area container">
        <h2 class="page-title">Edit User</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($userData): ?>
            <form action="edit_user.php?id=<?php echo htmlspecialchars($userData['id']); ?>" method="POST" class="form-card">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userData['id']); ?>">

                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="user_role">Role:</label>
                    <select id="user_role" name="user_role" required>
                        <option value="clerk" <?php echo ($userData['role'] === 'clerk') ? 'selected' : ''; ?>>Clerk</option>
                        <option value="manager" <?php echo ($userData['role'] === 'manager') ? 'selected' : ''; ?>>Manager</option>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <option value="admin" <?php echo ($userData['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Current Profile Picture:</label>
                    <?php
                    // Corrected: 'profile_picture'
                    $profilePic = !empty($userData['profile_picture']) ? $userData['profile_picture'] : '../img/default-profile.png';
                    ?>
                    <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Current Profile Picture" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                    </div>

                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="manage_users.php" class="btn btn-secondary">Back to Manage Users</a>
            </form>
        <?php else: ?>
            <p>Error: Could not load user data. Please go back to the <a href="manage_users.php">Manage Users</a> page.</p>
        <?php endif; ?>
    </main>

    <?php
    require_once '../includes/footer.php'; // Include your footer
    ?>
    <script src="../js/script.js"></script> </body>
</html>