<?php
// TrackingReads/admin/my_ebook_uploads.php
session_start();

// Pastikan laporan ralat dihidupkan untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Semak jika pengguna TIDAK log masuk
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login.php");
    exit;
}

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
$allowedRoles = ['admin', 'clerk'];

// Semak jika peranan pengguna yang log masuk TIDAK dibenarkan
if (!isset($_SESSION["user_role"]) || !in_array($_SESSION["user_role"], $allowedRoles)) {
    header("location: ../dashboard.php"); // Atau ke halaman akses ditolak
    exit;
}

require_once '../includes/db_connect.php';

$user_id = $_SESSION["user_id"];
$uploaded_ebooks = []; // Array untuk menyimpan ebook yang dimuat naik

// SQL untuk mengambil ebook yang dimuat naik oleh pengguna ini
// Menggunakan NAMA LAJUR yang TEPAT dari struktur jadual anda (image_9ae2de.png)
$sql = "SELECT id, tajuk, penulis, genre, file_path, created_at FROM ebooks WHERE uploaded_by = ?"; //

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $uploaded_ebooks[] = $row;
        }
    } else {
        // Ralat pelaksanaan query
        $error_message = "Error fetching ebooks: " . $stmt->error;
        error_log($error_message);
    }
    $stmt->close();
} else {
    // Ralat penyediaan query
    $error_message = "Error preparing ebook query: " . $conn->error;
    error_log($error_message);
}
$conn->close();

require_once '../includes/header.php';
?>

<div class="container content">
    <h2>My Ebook Uploads</h2>
    <p>This page displays all ebooks you have uploaded to the system.</p>

    <?php if (!empty($uploaded_ebooks)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Genre</th>
                    <th>File</th>
                    <th>Upload Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uploaded_ebooks as $ebook): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ebook['tajuk']); ?></td>
                        <td><?php echo htmlspecialchars($ebook['penulis']); ?></td>
                        <td><?php echo htmlspecialchars($ebook['genre']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($ebook['file_path']); ?>" target="_blank">Download</a></td>
                        <td><?php echo htmlspecialchars(date("Y-m-d", strtotime($ebook['created_at']))); ?></td>
                        <td>
                            <a href="edit-ebook.php?id=<?php echo $ebook['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                            <a href="delete-ebook.php?id=<?php echo $ebook['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this ebook?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>You have not uploaded any ebooks yet.</p>
        <a href="add_ebook.php" class="btn btn-primary">Upload New Ebook</a>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <p class="error"><?php echo $error_message; ?></p>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>