<?php
// admin/add_user.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Adjust path as needed
require_once '../includes/functions.php'; // For password hashing and image upload function

// Only Admin and Manager should be able to add users
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: ../login.php");
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    // Profile picture upload handling
    $profile_picture_path = null;
    $upload_dir = '../uploads/profile_pictures/'; // Adjust this path if needed
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
    }

    // Input validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error_message = "All fields (Username, Email, Password, Confirm Password, Role) are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Password and Confirm Password do not match.";
    } elseif (strlen($password) < 6) { // Minimum password length
        $error_message = "Password must be at least 6 characters long.";
    } elseif (!in_array($role, ['admin', 'manager', 'clerk', 'user'])) { // Validate against allowed roles
        $error_message = "Invalid user role selected.";
    } else {
        // Check if username or email already exists
        try {
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $error_message = "Username or Email already exists.";
            }
            $stmt_check->close();
        } catch (mysqli_sql_exception $e) {
            $error_message = "Database error during check: " . $e->getMessage();
        }

        if (empty($error_message)) {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Handle profile picture upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profile_picture']['tmp_name'];
                $file_name = uniqid() . "_" . basename($_FILES['profile_picture']['name']);
                $target_file = $upload_dir . $file_name;

                // Basic file type and size validation
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $max_file_size = 2 * 1024 * 1024; // 2MB

                if (!in_array($imageFileType, $allowed_extensions)) {
                    $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed for profile pictures.";
                } elseif ($_FILES['profile_picture']['size'] > $max_file_size) {
                    $error_message = "Sorry, your profile picture file is too large. Max 2MB.";
                } else {
                    if (move_uploaded_file($file_tmp, $target_file)) {
                        $profile_picture_path = $target_file; // Store path for database
                    } else {
                        $error_message = "Sorry, there was an error uploading your profile picture.";
                    }
                }
            }

            // If no errors so far, proceed with database insertion
            if (empty($error_message)) {
                try {
                    // Corrected column name: 'profile_picture'
                    $stmt_insert = $conn->prepare("INSERT INTO users (username, email, password, role, profile_picture) VALUES (?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("sssss", $username, $email, $hashed_password, $role, $profile_picture_path);

                    if ($stmt_insert->execute()) {
                        $success_message = "New user added successfully!";
                        // Clear form fields after successful submission (optional)
                        $_POST = array();
                    } else {
                        $error_message = "Error adding user: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                } catch (mysqli_sql_exception $e) {
                    $error_message = "Database error during insert: " . $e->getMessage();
                }
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
    <title>Add New User - TrackingReads</title>
    <link rel="stylesheet" href="../css/style.css"> </head>
<body>
    <main class="main-content-area container">
        <h2 class="page-title">Add New User</h2>
        <p>Use this form to add a new user to the system.</p>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form action="add_user.php" method="POST" enctype="multipart/form-data" class="form-card">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="role">User Role:</label>
                <select id="role" name="role" required>
                    <option value="">-- Select Role --</option>
                    <option value="user" <?php echo (($_POST['role'] ?? '') === 'user') ? 'selected' : ''; ?>>User</option>
                    <option value="clerk" <?php echo (($_POST['role'] ?? '') === 'clerk') ? 'selected' : ''; ?>>Clerk</option>
                    <option value="manager" <?php echo (($_POST['role'] ?? '') === 'manager') ? 'selected' : ''; ?>>Manager</option>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <option value="admin" <?php echo (($_POST['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="profile_picture">Profile Picture (Optional):</label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                <small>Max file size: 2MB. Allowed formats: JPG, JPEG, PNG, GIF.</small>
            </div>

            <button type="submit" class="btn btn-primary">Add User</button>
            <a href="manage_users.php" class="btn btn-secondary">Back to Manage Users</a>
        </form>
    </main>

    <?php
    require_once '../includes/footer.php'; // Include your footer
    ?>
    <script src="../js/script.js"></script> </body>
</html>