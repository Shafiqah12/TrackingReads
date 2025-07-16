<?php
    // admin/export_ebooks.php
    // Halaman ini akan mengendalikan eksport data ebook ke fail Excel/CSV.

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    session_start();
    require_once '../includes/db_connect.php';

    // Kawalan akses: Pastikan hanya pentadbir yang log masuk boleh mengakses halaman ini
    if (empty($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
        header("Location: ../login.php");
        exit;
    }

    require_once '../includes/header.php';
    ?>

    <div class="container">
        <h1>Export Ebooks</h1>
        <p>This page will contain the logic to export your ebook data from the database to an Excel or CSV file.</p>
        <p>Fungsi eksport akan dibangunkan di sini.</p>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
    require_once '../includes/footer.php';
    ?>
    