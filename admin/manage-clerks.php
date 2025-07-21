<?php
// admin/manage_clerks.php

// 1. Include your database connection file
//    Make sure this path is correct for your setup.
require_once '../includes/db_connect.php'; // Adjust path as needed

// 2. Start session and check user authentication/authorization
//    This is crucial for security. Only managers should access this page.
session_start();

// Redirect if not logged in or not a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php"); // Adjust login page path
    exit();
}

// Initialize variables for clerks data
$clerks = [];
$error_message = '';

try {
    // 3. Prepare and execute SQL query to fetch clerks
    //    Assuming your 'users' table has 'id', 'username', 'email', 'role', 'created_at', 'last_login'
    //    and 'profile_pic_path' columns. Adjust column names if they are different in your table.
    $stmt = $conn->prepare("SELECT id, username, email, role, created_at, last_login, profile_pic_path FROM users WHERE role = 'clerk' ORDER BY username ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $clerks[] = $row;
        }
    } else {
        $error_message = "No clerks found in the system.";
    }

    $stmt->close();
} catch (mysqli_sql_exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    // In a production environment, log the full error and show a generic message to the user.
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clerks - TrackingReads</title>
    <link rel="stylesheet" href="../css/style.css"> </head>
<body>
    <header class="header">
        <div class="logo">
            <a href="dashboard.php">TRACKINGREADS</a> </div>
        <nav class="nav">
            <ul class="nav-links" id="navLinks">
                <li><a href="ebook_library.php">Ebook Library</a></li>
                <li><a href="add_ebook.php">Add New Ebook</a></li>
                <li><a href="profile.php">Manager Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
            <button class="hamburger-menu" id="hamburgerButton" aria-label="Toggle navigation">&#9776;</button>
        </nav>
    </header>

    <main class="main-content-area container">
        <h2 class="page-title">Manage Clerks</h2>
        <p>Here you can view and manage clerk accounts.</p>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (count($clerks) > 0): ?>
            <div class="table-responsive">
                <table class="notes-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Last Login</th>
                            <th>Profile Picture</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clerks as $clerk): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($clerk['id']); ?></td>
                                <td><?php echo htmlspecialchars($clerk['username']); ?></td>
                                <td><?php echo htmlspecialchars($clerk['email']); ?></td>
                                <td><?php echo htmlspecialchars($clerk['role']); ?></td>
                                <td><?php echo htmlspecialchars($clerk['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($clerk['last_login'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $profilePic = !empty($clerk['profile_pic_path']) ? $clerk['profile_pic_path'] : '../img/default-profile.png'; // Adjust default image path
                                    ?>
                                    <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile Picture" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                </td>
                                <td>
                                    <a href="edit_clerk.php?id=<?php echo $clerk['id']; ?>" class="btn btn-info btn-small">Edit</a>
                                    <a href="delete_clerk.php?id=<?php echo $clerk['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure you want to delete this clerk?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No clerk accounts found.</p>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <p>&copy; <?php echo date("Y"); ?> TrackingReads. All rights reserved.</p>
    </footer>

    <script src="../js/script.js"></script> </body>
</html>