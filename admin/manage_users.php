<?php
// admin/manage_users.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Adjust path as needed

// Only Admin and Manager should access this page
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: ../login.php"); // Adjust login page path
    exit();
}

// Initialize variables for users data
$users = [];
$error_message = '';

try {
    // Corrected column names: 'role' and 'profile_picture' to match your 'users' table schema
    $stmt = $conn->prepare("SELECT id, username, email, role, created_at, last_login, profile_picture FROM users ORDER BY username ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        $error_message = "No users found in the system.";
    }

    $stmt->close();
} catch (mysqli_sql_exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

$conn->close();
require_once '../includes/header.php'; // Include your header
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage All Users - TrackingReads</title>
    <link rel="stylesheet" href="../css/style.css"> </head>
<body>
    <main class="main-content-area container">
        <h2 class="page-title">Manage All Users</h2>
        <p>Here you can view and manage all user accounts, including clerks.</p>

        <div class="button-group" style="margin-bottom: 20px;">
            <a href="add_user.php" class="btn btn-success">Add New User</a>
        </div>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && isset($_GET['type'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['type']); ?>">
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <?php if (count($users) > 0): ?>
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
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($user['last_login'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    // Corrected: 'profile_picture' column name
                                    $profilePic = !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : '../img/default-profile.png';
                                    ?>
                                    <img src="<?php echo $profilePic; ?>" alt="Profile Picture" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                </td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-small">Edit</a>
                                    <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No user accounts found.</p>
        <?php endif; ?>
    </main>

    <?php
    require_once '../includes/footer.php'; // Include your footer
    ?>
    <script src="../js/script.js"></script> </body>
</html>