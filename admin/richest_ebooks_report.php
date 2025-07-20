<?php
// admin/reports.php
// Halaman ini menjana laporan statistik untuk ebook.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php';

// Tentukan peranan yang dibenarkan untuk mengakses halaman ini
$allowedRoles = ['admin', 'manager']; // Biasanya laporan hanya untuk admin/manager

// Semak jika pengguna TIDAK log masuk ATAU TIDAK mempunyai peranan yang dibenarkan
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], $allowedRoles)) {
    error_log("Access Denied: User not logged in or role not allowed for reports.php. Role: " . ($_SESSION['user_role'] ?? 'N/A'));
    header("Location: ../login.php");
    exit;
}

$report_data = [
    'most_wishlisted' => [],
    'most_reviewed' => [], // Kini juga akan mengandungi purata rating jika rating disimpan dalam jadual reviews
    'highest_rated' => [] // Ini akan diisi dari jadual reviews juga
];
$errorMessage = null;

try {
    // 1. Laporan: Buku dengan Wishlist Terbanyak
    $sql_wishlist = "SELECT e.tajuk, COUNT(w.ebook_id) AS wishlist_count
                     FROM ebooks e
                     JOIN wishlist w ON e.id = w.ebook_id
                     GROUP BY e.id, e.tajuk
                     ORDER BY wishlist_count DESC
                     LIMIT 10";
    if ($stmt = $conn->prepare($sql_wishlist)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report_data['most_wishlisted'][] = $row;
        }
        $stmt->close();
    } else {
        error_log("Report Prepare Error (wishlist): " . $conn->error);
        $errorMessage = "Ralat menyediakan laporan wishlist: " . htmlspecialchars($conn->error);
    }

    // 2. Laporan: Buku dengan Ulasan Terbanyak DAN Penilaian Purata Tertinggi (Menggunakan jadual 'reviews' untuk kedua-duanya)
    // Ini menggabungkan logik untuk 'most_reviewed' dan 'highest_rated' kerana rating kini dalam jadual reviews.
    $sql_reviews_and_ratings = "SELECT e.tajuk, 
                                       COUNT(r.ebook_id) AS review_count,
                                       AVG(r.rating) AS average_rating
                                FROM ebooks e
                                LEFT JOIN reviews r ON e.id = r.ebook_id
                                GROUP BY e.id, e.tajuk
                                HAVING review_count > 0 -- Hanya sertakan buku yang mempunyai sekurang-kurangnya satu ulasan/penilaian
                                ORDER BY review_count DESC, average_rating DESC
                                LIMIT 10";
    if ($stmt = $conn->prepare($sql_reviews_and_ratings)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Format average_rating kepada 2 tempat perpuluhan
            $row['average_rating'] = number_format($row['average_rating'] ?? 0, 2);
            $report_data['most_reviewed'][] = $row; // Digunakan untuk paparan ulasan terbanyak
            $report_data['highest_rated'][] = $row; // Digunakan untuk paparan penilaian tertinggi
        }
        $stmt->close();
    } else {
        error_log("Report Prepare Error (reviews/ratings combined): " . $conn->error);
        if (strpos($conn->error, "Table 'trackingreads.reviews' doesn't exist") !== false) {
            $errorMessage .= "<br>Ralat: Jadual 'reviews' tidak ditemui. Sila cipta jadual 'reviews' di phpMyAdmin (pastikan ia ada kolum 'rating').";
        } else {
            $errorMessage .= "<br>Ralat menyediakan laporan ulasan dan penilaian: " . htmlspecialchars($conn->error);
        }
    }

} catch (Exception $e) {
    $errorMessage = "Ralat umum berlaku: " . htmlspecialchars($e->getMessage());
    error_log("Reports page general error: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="container">
    <h1>Laporan Statistik Ebook</h1>

    <?php if ($errorMessage): ?>
        <div class="message error"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="report-section">
        <h2>Top 10 Ebook Paling Banyak Disenarai Hajat (Wishlisted)</h2>
        <?php if (empty($report_data['most_wishlisted'])): ?>
            <p class="message info">Tiada data wishlist ditemui.</p>
        <?php else: ?>
            <table class="notes-table"> <!-- Menggunakan kelas 'notes-table' -->
                <thead>
                    <tr>
                        <th>Tajuk Ebook</th>
                        <th>Bilangan Wishlist</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['most_wishlisted'] as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['tajuk'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['wishlist_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="report-section">
        <h2>Top 10 Ebook Paling Banyak Diulas (Reviewed)</h2>
        <?php if (empty($report_data['most_reviewed'])): ?>
            <p class="message info">Tiada data ulasan ditemui atau jadual 'reviews' belum wujud/tidak mempunyai data.</p>
        <?php else: ?>
            <table class="notes-table"> <!-- Menggunakan kelas 'notes-table' -->
                <thead>
                    <tr>
                        <th>Tajuk Ebook</th>
                        <th>Bilangan Ulasan</th>
                        <th>Penilaian Purata</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['most_reviewed'] as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['tajuk'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['review_count'] ?? 0) ?></td>
                            <td><?= htmlspecialchars($item['average_rating'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="report-section">
        <h2>Top 10 Ebook Penilaian Purata Tertinggi (Highest Rated)</h2>
        <?php 
        // Sort by average_rating descending, then by review_count descending for tie-breaking
        usort($report_data['highest_rated'], function($a, $b) {
            if ($a['average_rating'] == $b['average_rating']) {
                return $b['review_count'] <=> $a['review_count']; // More reviews for same rating
            }
            return $b['average_rating'] <=> $a['average_rating'];
        });
        // Limit to top 10 after sorting
        $top_10_highest_rated = array_slice($report_data['highest_rated'], 0, 10);
        ?>
        <?php if (empty($top_10_highest_rated)): ?>
            <p class="message info">Tiada data penilaian ditemui atau jadual 'reviews' belum wujud/tidak mempunyai data.</p>
        <?php else: ?>
            <table class="notes-table"> <!-- Menggunakan kelas 'notes-table' -->
                <thead>
                    <tr>
                        <th>Tajuk Ebook</th>
                        <th>Penilaian Purata</th>
                        <th>Bilangan Penilaian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_10_highest_rated as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['tajuk'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['average_rating'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['review_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>
