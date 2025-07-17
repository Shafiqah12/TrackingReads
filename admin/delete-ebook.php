<?php
// admin/delete_ebook.php
// Halaman ini mengendalikan penghapusan rekod ebook.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Mulakan sesi
require_once '../includes/db_connect.php'; // Laluan relatif ke admin/delete_ebook.php

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
$allowedRoles = ['admin', 'manager', 'clerk']; // Admin, Manager, dan Clerk dibenarkan

// Kawalan akses: Pastikan hanya peranan yang dibenarkan boleh mengakses halaman ini
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["user_role"], $allowedRoles)) {
    header("location: ../login.php"); // Arahkan ke halaman log masuk jika tidak dibenarkan
    exit;
}

$ebookId = null;
$deleteSuccess = false;
$deleteError = '';

// Pastikan ID ebook disediakan melalui GET
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $ebookId = filter_var(trim($_GET['id']), FILTER_SANITIZE_NUMBER_INT);

    if (!is_numeric($ebookId) || $ebookId <= 0) {
        $deleteError = 'ID ebook tidak sah disediakan.';
    }
} else {
    $deleteError = 'Tiada ID ebook ditentukan untuk dihapuskan.';
}

// Jika ID sah, teruskan dengan penghapusan
if ($ebookId && empty($deleteError)) {
    // Mulakan transaksi untuk memastikan integriti data
    $conn->begin_transaction();

    try {
        // 1. Ambil laluan fail ebook dari pangkalan data sebelum menghapuskan rekod
        $sql_fetch_file = "SELECT file_path FROM ebooks WHERE id = ?";
        if ($stmt_fetch = $conn->prepare($sql_fetch_file)) {
            $stmt_fetch->bind_param("i", $ebookId);
            $stmt_fetch->execute();
            $result_fetch = $stmt_fetch->get_result();

            $file_to_delete = null;
            if ($result_fetch->num_rows === 1) {
                $row = $result_fetch->fetch_assoc();
                $file_to_delete = $row['file_path'];
            }
            $stmt_fetch->close();
        } else {
            throw new Exception("Ralat pangkalan data menyediakan pernyataan pengambilan fail: " . $conn->error);
        }

        // 2. Hapus rekod ebook dari pangkalan data
        $sql_delete = "DELETE FROM ebooks WHERE id = ?";
        if ($stmt_delete = $conn->prepare($sql_delete)) {
            $stmt_delete->bind_param("i", $ebookId);
            if ($stmt_delete->execute()) {
                if ($stmt_delete->affected_rows === 1) {
                    // 3. Jika rekod berjaya dihapus, cuba padam fail fizikal
                    if ($file_to_delete && file_exists($file_to_delete)) {
                        if (!unlink($file_to_delete)) {
                            // Log ralat jika fail tidak dapat dipadam tetapi teruskan kerana rekod DB sudah dipadam
                            error_log("Gagal memadam fail ebook: " . $file_to_delete);
                        }
                    }
                    $conn->commit(); // Komit transaksi jika semuanya berjaya
                    $deleteSuccess = true;
                } else {
                    throw new Exception("Ebook tidak ditemui dengan ID yang ditentukan atau sudah dihapuskan.");
                }
            } else {
                throw new Exception("Pelaksanaan penghapusan gagal: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        } else {
            throw new Exception("Ralat pangkalan data menyediakan pernyataan penghapusan: " . $conn->error);
        }

    } catch (Exception $e) {
        $conn->rollback(); // Gulung balik transaksi jika berlaku ralat
        $deleteError = "Ralat semasa memadam ebook: " . htmlspecialchars($e->getMessage());
    }
}

// Tutup sambungan pangkalan data
if (isset($conn) && $conn->ping()) {
    $conn->close();
}

// Arahkan semula ke halaman pengurusan ebook dengan mesej status
if ($deleteSuccess) {
    $_SESSION['status_message'] = ['type' => 'success', 'text' => 'Ebook berjaya dihapuskan.'];
} else {
    $_SESSION['status_message'] = ['type' => 'error', 'text' => 'Gagal menghapuskan ebook: ' . $deleteError];
}
header("location: manage_ebook.php");
exit;
?>
