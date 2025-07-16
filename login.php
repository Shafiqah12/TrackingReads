<?php
// login.php
// Skrip ini mengendalikan log masuk pengguna dan menetapkan peranan sesi.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db_connect.php';

// Jika pengguna sudah log masuk, arahkan mereka berdasarkan peranan mereka
if (!empty($_SESSION['loggedin'])) {
    switch ($_SESSION['user_role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'manager':
            header("Location: admin/dashboard.php"); // Manager juga ke dashboard admin, tetapi akan melihat butang yang berbeza
            break;
        case 'clerk':
            header("Location: index.php"); // Clerk terus ke halaman senarai ebook utama
            break;
        default:
            header("Location: index.php"); // Default untuk peranan tidak dikenali
            break;
    }
    exit;
}

/* ---------- form handling ---------- */
$email_username_err = $password_err = '';
$email_username_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_username = trim($_POST['email_username'] ?? '');
    $password       = trim($_POST['password']        ?? '');

    $email_username_val = htmlspecialchars($email_username);

    if ($email_username === '') $email_username_err = 'Please enter your email or username.';
    if ($password === '')       $password_err       = 'Please enter your password.';

    if ($email_username_err === '' && $password_err === '') {
        // Sediakan query untuk mengambil pengguna berdasarkan username atau email
        $sql  = "SELECT id, username, email, password, role, profile_picture
                 FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            // Tangani ralat penyediaan pernyataan
            $email_username_err = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param('ss', $email_username, $email_username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                // Bind hasil query ke pembolehubah
                $stmt->bind_result($id, $username, $email, $hash, $role, $pic);
                $stmt->fetch();

                // Sahkan kata laluan menggunakan password_verify()
                if (password_verify($password, $hash)) {
                    /* ---- log masuk berjaya ---- */
                    session_regenerate_id(true); // Jana semula ID sesi untuk keselamatan
                    $_SESSION['loggedin']        = true;
                    $_SESSION['user_id']         = $id;
                    $_SESSION['username']        = $username;
                    $_SESSION['user_email']      = $email;
                    $_SESSION['user_role']       = $role; // Simpan peranan pengguna dalam sesi
                    $_SESSION['profile_picture'] = $pic ?: '../img/admin.jpg'; // Tetapkan gambar profil lalai jika tiada

                    // Arahkan pengguna berdasarkan peranan mereka
                    switch ($role) {
                        case 'admin':
                            header("Location: admin/dashboard.php");
                            break;
                        case 'manager':
                            header("Location: admin/dashboard.php"); // Manager juga ke dashboard admin
                            break;
                        case 'clerk':
                            header("Location: index.php"); // Clerk terus ke halaman senarai ebook utama
                            break;
                        default:
                            header("Location: index.php"); // Default untuk peranan tidak dikenali
                            break;
                    }
                    exit; // Hentikan pelaksanaan skrip selanjutnya
                } else {
                    $password_err = 'Incorrect password.';
                }
            } else {
                $email_username_err = 'No account found with that username/email.';
            }
            $stmt->close();
        }
    }
}
$conn->close(); // Tutup sambungan pangkalan data

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
