<?php
// admin/edit_clerk.php

require_once '../includes/db_connect.php'; // Adjust path as needed
session_start();

// Redirect if not logged in or not a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit();
}

$clerkData = null;
$error_message = '';
$success_message = '';

// Check if clerk ID is provided in the URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $clerk_id = (int)$_GET['id'];

    // Fetch existing clerk data
    try {
        $stmt = $conn->prepare("SELECT id, username, email, profile_pic_path FROM users WHERE id = ? AND role = 'clerk'");
        $stmt->bind_param("i", $clerk_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $clerkData = $result->fetch_assoc();
        } else {
            $error_message = "Clerk not found or is not a clerk account.";
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
} else {
    $error_message = "No clerk ID provided.";
}

// Handle form submission for updating clerk data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $clerkData) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $clerk_id_from_post = (int)$_POST['clerk_id']; // Hidden field for ID

    // Basic validation
    if (empty($new_username) || empty($new_email)) {
        $error_message = "Username and Email cannot be empty.";
    } elseif ($clerk_id_from_post !== $clerkData['id']) {
        $error_message = "Security error: Mismatched clerk ID.";
    } else {
        try {
            // Update the clerk's details
            $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ? AND role = 'clerk'");
            $update_stmt->bind_param("ssi", $new_username, $new_email, $clerk_id_from_post);

            if ($update_stmt->execute()) {
                $success_message = "Clerk details updated successfully.";
                // Re-fetch data to show updated info on the form
                $stmt = $conn->prepare("SELECT id, username, email, profile_pic_path FROM users WHERE id = ? AND role = 'clerk'");
                $stmt->bind_param("i", $clerk_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $clerkData = $result->fetch_assoc(); // Update $clerkData with new values
                $stmt->close();
            } else {
                $error_message = "Failed to update clerk details: " . $update_stmt->error;
            }
            $update_stmt->close();
        } catch (mysqli_sql_exception $e) {
            $error_message = "Database error during update: " . $e->getMessage();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Clerk - TrackingReads</title>
    <link rel="stylesheet" href="../css/style.css"> </head>
<body>
    <header class="header">
        <div class="logo">
            <a href="dashboard.php">TRACKINGREADS</a>
        </div>
        <nav class="nav">
            <ul class="nav-links" id="navLinks">
                <li><a href="ebook_library.php">Ebook Library</a></li>
                <li><a href="add_ebook.php">Add New Ebook</a></li>
                <li><a href="profile.php">Manager Profile</a></li>
                <li><a href="manage_clerks.php">Manage Clerks</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
            <button class="hamburger-menu" id="hamburgerButton" aria-label="Toggle navigation">&#9776;</button>
        </nav>
    </header>

    <main class="main-content-area container">
        <h2 class="page-title">Edit Clerk</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($clerkData): ?>
            <form action="edit_clerk.php?id=<?php echo htmlspecialchars($clerkData['id']); ?>" method="POST" class="form-card">
                <input type="hidden" name="clerk_id" value="<?php echo htmlspecialchars($clerkData['id']); ?>">

                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($clerkData['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($clerkData['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Current Profile Picture:</label>
                    <?php
                    $profilePic = !empty($clerkData['profile_pic_path']) ? $clerkData['profile_pic_path'] : '../img/default-profile.png'; // Adjust default image path
                    ?>
                    <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Current Profile Picture" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                </div>

                <button type="submit" class="btn btn-primary">Update Clerk</button>
                <a href="manage-clerks.php" class="btn btn-secondary">Back to Manage Clerks</a>
            </form>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <p>&copy; <?php echo date("Y"); ?> TrackingReads. All rights reserved.</p>
    </footer>
    <script src="../js/script.js"></script>
</body>
</html>