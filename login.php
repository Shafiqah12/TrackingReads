<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db_connect.php';

if (!empty($_SESSION['loggedin'])) {
    header("Location: " . ($_SESSION['user_role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

/* ---------- form handling ---------- */
$email_username_err = $password_err = '';
$email_username_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_username = trim($_POST['email_username'] ?? '');
    $password       = trim($_POST['password']       ?? '');

    $email_username_val = htmlspecialchars($email_username);

    if ($email_username === '') $email_username_err = 'Please enter your email or username.';
    if ($password === '')       $password_err       = 'Please enter your password.';

    if ($email_username_err === '' && $password_err === '') {
        $sql  = "SELECT id, username, email, password, role, profile_picture
                 FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $email_username, $email_username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $username, $email, $hash, $role, $pic);
            $stmt->fetch();

            // --- THIS IS THE CRITICAL LINE FOR SECURE PASSWORD VERIFICATION ---
            if (password_verify($password, $hash)) { // ✨ This line must be exactly like this
                /* ---- successful login ---- */
                session_regenerate_id(true);
                $_SESSION['loggedin']        = true;
                $_SESSION['user_id']         = $id;
                $_SESSION['username']        = $username;
                $_SESSION['user_email']      = $email;
                $_SESSION['user_role']       = $role;
                $_SESSION['profile_picture'] = $pic ?: '../img/admin.jpg';

                header("Location: " . ($role === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
                exit;
            } else {
                $password_err = 'Incorrect password.';
            }
        } else {
            $email_username_err = 'No account found with that username/email.';
        }
        $stmt->close();
    }
}
$conn->close();
require_once 'includes/header.php';
?>
<div class="auth-container">
    <h2>Login</h2>
    <p>Please fill in your credentials to login.</p>

    <form method="post" action="login.php">
        <label>Email or Username</label>
        <input type="text" name="email_username" value="<?= $email_username_val ?>">
        <span class="help-block"><?= $email_username_err ?></span>

        <label>Password</label>
        <input type="password" name="password">
        <span class="help-block"><?= $password_err ?></span>

        <button type="submit" class="btn btn-primary">Login</button>
    </form>

    <div style="text-align:center;margin-top:20px;">
        <a href="/TrackingReads/google-auth.php" class="btn btn-google">
            <i class="fab fa-google"></i> Sign in with Google
        </a>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>