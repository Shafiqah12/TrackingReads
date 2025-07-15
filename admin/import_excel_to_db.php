<?php

// Require Composer's autoloader for PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// --- Database Configuration ---
$dbHost = 'localhost';
$dbUser = 'root'; // Default XAMPP MySQL user
$dbPass = '';     // Default XAMPP MySQL password (empty)
$dbName = 'TrackingReads'; // Your existing database name

// --- Excel File Path ---
$inputFileName = 'D:/xampp/htdocs/TrackingReads/01 Dataset eBook untuk Project CSC575.xlsx';

// --- HTML Output Start ---
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel to DB Importer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">';
    echo '<h1>Excel to Database Importer</h1>';

try {
    // --- 1. Database Connection ---
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    if ($conn->connect_error) {
        throw new Exception("Database Connection failed: " . $conn->connect_error);
    }
    echo '<div class="message success">Database connected successfully.</div>';

    // --- 2. Load Excel File ---
    if (!file_exists($inputFileName)) {
        throw new Exception("Error: Input Excel file not found at " . htmlspecialchars($inputFileName));
    }

    $reader = IOFactory::createReaderForFile($inputFileName);
    $reader->setReadDataOnly(true); // Read only data, ignore formulas for faster import
    $spreadsheet = $reader->load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();

    echo '<div class="message success">Excel file loaded: ' . htmlspecialchars(basename($inputFileName)) . '</div>';

    // --- 3. Prepare for Database Insertion ---
    $highestRow = $worksheet->getHighestDataRow();
    $highestColumn = $worksheet->getHighestDataColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

    $insertedCount = 0;
    $skippedCount = 0;

    // Optional: TRUNCATE TABLE if you want to clear existing data before importing
    // Uncomment the line below if you want to clear the table every time you run this script
    // $conn->query("TRUNCATE TABLE ebooks");
    // echo '<div class="message info">Existing data in "ebooks" table cleared.</div>';


    // Prepare SQL INSERT statement with all columns, including 'penerbit'
    $stmt = $conn->prepare("INSERT INTO ebooks (no, penulis, tajuk, muka_surat, perkataan, harga_rm, genre, bulan, tahun, penerbit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    // 's' for string, 'i' for integer, 'd' for double/decimal
    // The bind_param string must match the types and order of your columns
    $stmt->bind_param("isiiidssis", $no, $penulis, $tajuk, $muka_surat, $perkataan, $harga_rm, $genre, $bulan, $tahun, $penerbit);

    // --- 4. Iterate through Excel Rows and Insert into DB ---
    // Start from row 2 to skip headers (assuming row 1 is headers)
    for ($row = 2; $row <= $highestRow; ++$row) {
        $isRowEmpty = true;
        $rowData = []; // To store data for current row

        for ($col = 1; $col <= $highestColumnIndex; ++$col) {
            $columnLetter = Coordinate::stringFromColumnIndex($col);
            $cellValue = $worksheet->getCell($columnLetter . $row)->getCalculatedValue();
            $rowData[] = $cellValue; // Collect all cell values for the row

            if (trim($cellValue) !== '') {
                $isRowEmpty = false;
            }
        }

        // If the row is truly empty, break the loop (end of data)
        if ($isRowEmpty) {
            echo '<p>Skipped empty row ' . $row . '. End of data reached.</p>';
            break;
        }

        // --- Map Excel Columns (by 0-indexed position) to PHP Variables for Binding ---
        // Adjust these indices based on your Excel column order (Column A is index 0, B is 1, etc.)
        $no         = $rowData[0] ?? null;    // Column A
        $penulis    = $rowData[1] ?? null;    // Column B
        $tajuk      = $rowData[2] ?? null;    // Column C
        $muka_surat = $rowData[3] ?? null;    // Column D
        $perkataan  = $rowData[4] ?? null;    // Column E
        $harga_rm   = $rowData[5] ?? null;    // Column F
        $genre      = $rowData[6] ?? null;    // Column G
        $bulan      = $rowData[7] ?? null;    // Column H
        $tahun      = $rowData[8] ?? null;    // Column I
        $penerbit   = $rowData[9] ?? null;    // Column J (as seen in your screenshot)

        // --- Type Casting (Important for MySQL) ---
        // Ensure data types match your database table definition
        $no = (int)$no;
        $muka_surat = (int)$muka_surat;
        $perkataan = (int)$perkataan;
        $harga_rm = (float)$harga_rm;
        $tahun = (int)$tahun;
        // penerbit and other VARCHARs are fine as strings

        // Execute the prepared statement
        if ($stmt->execute()) {
            $insertedCount++;
        } else {
            $skippedCount++;
            echo '<div class="message error">Error inserting row ' . $row . ': ' . htmlspecialchars($stmt->error) . '</div>';
        }
    }

    $stmt->close();
    $conn->close();

    echo '<div class="message success">Import complete! ' . $insertedCount . ' rows inserted, ' . $skippedCount . ' rows skipped.</div>';

} catch (Exception $e) {
    echo '<div class="message error">';
    echo "<h3>Error:</h3>";
    echo "<p>Message: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . htmlspecialchars($e->getFile()) . " on line " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<h4>Trace:</h4> <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo '</div>';
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}

echo '    </div>
</body>
</html>';

?>