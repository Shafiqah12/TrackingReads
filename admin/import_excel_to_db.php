<?php
// admin/import_excel_to_db.php (Nama fail dikekalkan untuk keserasian pautan, tetapi kini mengimport CSV)
// Skrip ini mengimport data dari fail CSV ke dalam jadual 'ebooks'.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Mulakan sesi

// Kita tidak lagi memerlukan PhpSpreadsheet untuk CSV
// require '../vendor/autoload.php'; // Komenkan baris ini

// Sertakan fail sambungan pangkalan data sedia ada anda.
require_once '../includes/db_connect.php';

// Pilihan: Tambah semakan admin jika hanya admin dibenarkan menjalankan import ini
if (empty($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// --- Laluan Fail CSV ---
// Pastikan nama fail ini sepadan dengan nama fail .csv yang anda simpan tadi.
// Pastikan anda telah menyimpan fail Excel anda sebagai "CSV (Comma delimited) (*.csv)"
// dan namakan ia "ebook_data.csv" di dalam folder "TrackingReads".
$inputFileName = '../ebook_data.csv'; // <--- Sila ubah jika nama fail CSV anda berbeza

// --- Sertakan header sedia ada anda untuk gaya dan navigasi yang konsisten ---
require_once '../includes/header.php';
?>

<div class="container">
    <h1>Pengimport CSV ke Pangkalan Data</h1>
    <p class="message info">Pastikan fail CSV anda bernama <code>ebook_data.csv</code> dan berada di folder <code>TrackingReads</code>.</p>

    <?php
    try {
        echo '<div class="message success">Pangkalan data berjaya disambungkan.</div>';

        if (!file_exists($inputFileName)) {
            throw new Exception("Ralat: Fail CSV input tidak ditemui di " . htmlspecialchars($inputFileName));
        }

        $insertedCount = 0;
        $skippedCount = 0;

        // --- PENTING UNTUK DEBUGGING: KOSONGKAN JADUAL UNTUK SETIAP JALANAN ---
        $conn->query("TRUNCATE TABLE ebooks");
        echo '<div class="message info">Data sedia ada dalam jadual "ebooks" telah dikosongkan untuk import baharu.</div></div>';

        // Buka div log import
        echo '<h3>Log Import:</h3>';
        echo '<div style="max-height: 400px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; background-color: #f9f9f9;">';

        // Buka fail CSV untuk dibaca
        // Tambah 'b' untuk mod binary jika ada isu encoding
        if (($handle = fopen($inputFileName, "rb")) !== FALSE) { // Added 'b' for binary mode
            $row = 1; // Untuk mengesan nombor baris
            while (($data = fgetcsv($handle, 0, ",")) !== FALSE) { // Set length to 0 for unlimited line length
                if ($row == 1) { // Langkau baris header
                    $row++;
                    continue;
                }

                // --- DEBUGGING: Paparkan data mentah dari CSV ---
                echo '<p style="color: #000; background-color: #ffffcc; padding: 3px;"><strong>CSV Raw Data (Row ' . $row . '):</strong> ' . htmlspecialchars(implode(' | ', $data)) . '</p>';

                // Pastikan ada 10 kolum data yang dijangka
                if (count($data) < 10) {
                    echo '<p style="color: #FF5733;">Ralat: Baris ' . $row . ' tidak mempunyai bilangan kolum yang mencukupi (' . count($data) . ' daripada 10 dijangka). Dilangkau.</p>';
                    $skippedCount++;
                    $row++;
                    continue;
                }

                // Peta data CSV ke pembolehubah
                // Gunakan array key 0, 1, 2, ...
                // --- PERUBAHAN KRITIKAL: Bersihkan karakter tidak kelihatan ---
                $no_raw         = str_replace("\xEF\xBB\xBF", "", (string)($data[0] ?? '')); // Remove BOM if present
                $penulis_raw    = str_replace("\xEF\xBB\xBF", "", (string)($data[1] ?? ''));
                $tajuk_raw      = str_replace("\xEF\xBB\xBF", "", (string)($data[2] ?? '')); // FOKUS UTAMA DI SINI
                $muka_surat_raw = str_replace("\xEF\xBB\xBF", "", (string)($data[3] ?? ''));
                $perkataan_raw  = str_replace("\xEF\xBB\xBF", "", (string)($data[4] ?? ''));
                $harga_rm_raw   = str_replace("\xEF\xBB\xBF", "", (string)($data[5] ?? ''));
                $genre_raw      = str_replace("\xEF\xBB\xBF", "", (string)($data[6] ?? ''));
                $bulan_raw      = str_replace("\xEF\xBB\xBF", "", (string)($data[7] ?? ''));
                $tahun_raw      = str_replace("\xEF\xBB\xBF", "", (string)($data[8] ?? ''));
                $penerbit_raw   = str_replace("\xEF\xBB\xBF", "", (string)($data[9] ?? '')); // Pastikan ini kolum yang betul

                // Semak jika baris kritikal kosong
                if (empty(trim($penulis_raw)) && empty(trim($tajuk_raw))) {
                    echo '<p style="color: #666;">Melangkau baris kosong ' . $row . '. (Penulis dan Tajuk kosong)</p>';
                    $skippedCount++;
                    $row++;
                    continue;
                }

                // Trim nilai sebelum type casting atau binding
                $no         = trim($no_raw);
                $penulis    = trim($penulis_raw);
                $tajuk      = trim($tajuk_raw);
                $muka_surat = trim($muka_surat_raw);
                $perkataan  = trim($perkataan_raw);
                $harga_rm   = trim($harga_rm_raw);
                $genre      = trim($genre_raw);
                $bulan      = trim($bulan_raw);
                $tahun      = trim($tahun_raw);
                $penerbit   = trim($penerbit_raw);

                // Type Casting (Penting untuk MySQL)
                $no = is_numeric($no) ? (int)$no : 0;
                $muka_surat = is_numeric($muka_surat) ? (int)$muka_surat : 0;
                $perkataan = is_numeric($perkataan) ? (int)$perkataan : 0;
                $harga_rm = is_numeric($harga_rm) ? (float)$harga_rm : 0.00;
                $tahun = is_numeric($tahun) ? (int)$tahun : 0;

                // --- OUTPUT DEBUGGING BAHARU: VAR_DUMP SEBELUM EXECUTE ---
                echo '<div style="border-bottom: 1px dashed #ddd; padding: 5px 0; margin-bottom: 5px; background-color: #e0ffe0;">';
                echo '<strong>Baris ' . $row . ' - Nilai yang akan dimasukkan:</strong><br>';
                echo 'NO: ' . var_export($no, true) . '<br>';
                echo 'Penulis: ' . var_export($penulis, true) . '<br>';
                echo 'Tajuk: ' . var_export($tajuk, true) . '<br>';
                echo 'Muka Surat: ' . var_export($muka_surat, true) . '<br>';
                echo 'Perkataan: ' . var_export($perkataan, true) . '<br>';
                echo 'Harga RM: ' . var_export($harga_rm, true) . '<br>';
                echo 'Genre: ' . var_export($genre, true) . '<br>';
                echo 'Bulan: ' . var_export($bulan, true) . '<br>';
                echo 'Tahun: ' . var_export($tahun, true) . '<br>';
                echo 'Penerbit: ' . var_export($penerbit, true) . '<br>';
                echo '</div>';

                // Sediakan pernyataan INSERT (sama seperti sebelumnya)
                $stmt = $conn->prepare("INSERT INTO ebooks (no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt === false) {
                    throw new Exception("Gagal menyediakan pernyataan SQL: " . $conn->error);
                }
                // Bind parameter (sama seperti sebelumnya, isiiidssis)
                $stmt->bind_param("isiiidssis", $no, $penulis, $tajuk, $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit);

                if ($stmt->execute()) {
                    $insertedCount++;
                } else {
                    $skippedCount++;
                    echo '<div class="message error">Ralat memasukkan baris ' . $row . ': ' . htmlspecialchars($stmt->error) . '</div>';
                }
                $stmt->close(); // Tutup pernyataan selepas setiap pelaksanaan

                $row++;
            }
            fclose($handle);
        } else {
            throw new Exception("Gagal membuka fail CSV.");
        }

        echo '</div>'; // Tutup div log debug
        echo '<div class="message success">Import selesai! ' . $insertedCount . ' baris dimasukkan, ' . $skippedCount . ' baris dilangkau.</div>';

    } catch (Exception $e) {
        echo '<div class="message error">';
        echo "<h3>Ralat:</h3>";
        echo "<p>Mesej: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Fail: " . htmlspecialchars($e->getFile()) . " pada baris " . htmlspecialchars($e->getLine()) . "</p>";
        echo "<h4>Jejak:</h4> <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo '</div>';
    }
    ?>
</div>

<?php
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
require_once '../includes/footer.php';
?>
