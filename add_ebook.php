<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Start the session
require_once 'includes/db_connect.php'; // Include your existing database connection

// Redirect if not logged in (or if not an admin, if you want to restrict this)
if (empty($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}
// Optional: Restrict to admin users only
// if ($_SESSION['user_role'] !== 'admin') {
//     header("Location: index.php"); // Or dashboard.php, or an unauthorized page
//     exit;
// }

// Initialize variables for form fields and error messages
$no = $penulis = $tajuk = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = '';
$errors = [];
$successMessage = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $no         = filter_var(trim($_POST['no'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $penulis    = filter_var(trim($_POST['penulis'] ?? ''), FILTER_SANITIZE_STRING);
    $tajuk      = filter_var(trim($_POST['tajuk'] ?? ''), FILTER_SANITIZE_STRING);
    $muka_surat = filter_var(trim($_POST['muka_surat'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $perkataan  = filter_var(trim($_POST['perkataan'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $harga_rm   = filter_var(trim($_POST['harga_rm'] ?? ''), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $genre      = filter_var(trim($_POST['genre'] ?? ''), FILTER_SANITIZE_STRING);
    $bulan      = filter_var(trim($_POST['bulan'] ?? ''), FILTER_SANITIZE_STRING);
    $tahun      = filter_var(trim($_POST['tahun'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
    $penerbit   = filter_var(trim($_POST['penerbit'] ?? ''), FILTER_SANITIZE_STRING);

    // Basic validation
    if (empty($penulis)) $errors['penulis'] = 'Penulis is required.';
    if (empty($tajuk)) $errors['tajuk'] = 'Tajuk is required.';
    if (!empty($no) && !is_numeric($no)) $errors['no'] = 'NO must be a number.';
    if (!empty($muka_surat) && !is_numeric($muka_surat)) $errors['muka_surat'] = 'Muka Surat must be a number.';
    if (!empty($perkataan) && !is_numeric($perkataan)) $errors['perkataan'] = 'Perkataan must be a number.';
    if (!empty($harga_rm) && !is_numeric($harga_rm)) $errors['harga_rm'] = 'Harga (RM) must be a number.';
    if (!empty($tahun) && !is_numeric($tahun)) $errors['tahun'] = 'Tahun must be a number.';

    // If no validation errors, proceed with database insertion
    if (empty($errors)) {
        try {
            // Prepare an INSERT statement
            $sql = "INSERT INTO ebooks (no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // Bind parameters (s=string, i=integer, d=double/float)
            $stmt->bind_param("isiiidssis", $no, $penulis, $tajuk, $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit);

            // Execute the statement
            if ($stmt->execute()) {
                $successMessage = "Ebook added successfully!";
                // Clear form fields after successful submission
                $no = $penulis = $tajuk = $muka_surat = $perkataan = $harga_rm = $genre = $bulan = $tahun = $penerbit = '';
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();

        } catch (Exception $e) {
            $errors['db_error'] = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Include your existing header.php
require_once 'includes/header.php';
?>

<div class="container">
    <h1>Add New Ebook</h1>

    <?php if ($successMessage): ?>
        <div class="message success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <h3>Please correct the following errors:</h3>
            <ul>
                <?php foreach ($errors as $field => $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="add_ebook.php">
        <div class="form-group">
            <label for="no">NO:</label>
            <input type="number" id="no" name="no" class="form-control" value="<?= htmlspecialchars($no) ?>">
            <?php if (isset($errors['no'])): ?><span class="help-block"><?= $errors['no'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="penulis">Penulis (Author):</label>
            <input type="text" id="penulis" name="penulis" class="form-control" value="<?= htmlspecialchars($penulis) ?>" required>
            <?php if (isset($errors['penulis'])): ?><span class="help-block"><?= $errors['penulis'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="tajuk">Tajuk (Title):</label>
            <input type="text" id="tajuk" name="tajuk" class="form-control" value="<?= htmlspecialchars($tajuk) ?>" required>
            <?php if (isset($errors['tajuk'])): ?><span class="help-block"><?= $errors['tajuk'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="muka_surat">Muka Surat (Pages):</label>
            <input type="number" id="muka_surat" name="muka_surat" class="form-control" value="<?= htmlspecialchars($muka_surat) ?>">
            <?php if (isset($errors['muka_surat'])): ?><span class="help-block"><?= $errors['muka_surat'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="perkataan">Perkataan (Words):</label>
            <input type="number" id="perkataan" name="perkataan" class="form-control" value="<?= htmlspecialchars($perkataan) ?>">
            <?php if (isset($errors['perkataan'])): ?><span class="help-block"><?= $errors['perkataan'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="harga_rm">Harga (RM):</label>
            <input type="number" step="0.01" id="harga_rm" name="harga_rm" class="form-control" value="<?= htmlspecialchars($harga_rm) ?>">
            <?php if (isset($errors['harga_rm'])): ?><span class="help-block"><?= $errors['harga_rm'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="genre">Genre:</label>
            <input type="text" id="genre" name="genre" class="form-control" value="<?= htmlspecialchars($genre) ?>">
            <?php if (isset($errors['genre'])): ?><span class="help-block"><?= $errors['genre'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="bulan">Bulan (Month):</label>
            <input type="text" id="bulan" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan) ?>">
            <?php if (isset($errors['bulan'])): ?><span class="help-block"><?= $errors['bulan'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="tahun">Tahun (Year):</label>
            <input type="number" id="tahun" name="tahun" class="form-control" value="<?= htmlspecialchars($tahun) ?>">
            <?php if (isset($errors['tahun'])): ?><span class="help-block"><?= $errors['tahun'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="penerbit">Penerbit (Publisher):</label>
            <input type="text" id="penerbit" name="penerbit" class="form-control" value="<?= htmlspecialchars($penerbit) ?>">
            <?php if (isset($errors['penerbit'])): ?><span class="help-block"><?= $errors['penerbit'] ?></span><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">Add Ebook</button>
    </form>
</div>

<?php
// Close the database connection at the very end of the script
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
// Include your existing footer.php
require_once 'includes/footer.php';
?>