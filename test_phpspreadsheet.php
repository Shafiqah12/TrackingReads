<?php

// This line is crucial! It loads all your Composer-installed libraries.
require 'vendor/autoload.php';

// Use statements for PhpSpreadsheet classes
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate; // Added for stringFromColumnIndex

// Define the path to your existing Excel file
$inputFileName = 'D:/xampp/htdocs/TrackingReads/01 Dataset eBook untuk Project CSC575.xlsx';

// Start HTML output
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Data Processing</title>
    <link rel="stylesheet" href="style.css"> </head>
<body>
    <div class="container">'; // Start of the main content container

try {
    // Check if the input file exists
    if (!file_exists($inputFileName)) {
        throw new Exception("Error: Input Excel file not found at " . htmlspecialchars($inputFileName));
    }

    // 1. Read the Excel file
    $reader = IOFactory::createReaderForFile($inputFileName);
    // Set to true to only read data (ignoring formulas and only getting cached values if available).
    // This often helps prevent "Formula Error" during reading of complex formulas.
    $reader->setReadDataOnly(true); 

    $spreadsheet = $reader->load($inputFileName);

    $worksheet = $spreadsheet->getActiveSheet();

    echo '<div class="message success">Successfully loaded Excel file: ' . htmlspecialchars(basename($inputFileName)) . '</div>';

    // Example: Read some data (e.g., from cell A1)
    // When setReadDataOnly(true), getValue() and getCalculatedValue() will return the same (cached) value.
    $cellValueA1 = $worksheet->getCell('A1')->getValue(); 
    echo '<p><strong>Value from A1 (Raw):</strong> ' . htmlspecialchars($cellValueA1) . '</p>';

    $calculatedValueA1 = $worksheet->getCell('A1')->getCalculatedValue();
    echo '<p><strong>Value from A1 (Calculated):</strong> ' . htmlspecialchars($calculatedValueA1) . '</p>';

    echo '<h3>All Rows of Data (Calculated Values):</h3>';
    echo '<div class="data-section"><pre>';

    $highestRow = $worksheet->getHighestDataRow(); // Get the highest data row (can be over-estimated sometimes)
    $highestColumn = $worksheet->getHighestDataColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

    // Loop through each row, with a check to stop at the first entirely empty row
    for ($row = 1; $row <= $highestRow; ++$row) {
        $isRowEmpty = true; // Assume the current row is empty initially

        // Build the content for the current row
        $rowDataOutput = ''; 
        for ($col = 1; $col <= $highestColumnIndex; ++$col) {
            $columnLetter = Coordinate::stringFromColumnIndex($col);
            $cellCoordinate = $columnLetter . $row;
            
            // Get the cell value. With setReadDataOnly(true), this retrieves the cached value.
            $cellValue = $worksheet->getCell($cellCoordinate)->getCalculatedValue(); 
            
            // Check if the cell has any non-empty content after trimming whitespace
            if (trim($cellValue) !== '') {
                $isRowEmpty = false; // Found content, so row is not empty
            }
            $rowDataOutput .= htmlspecialchars($cellValue) . "\t"; // Append cell value to row string
        }

        // After checking all columns in the current row:
        if ($isRowEmpty) {
            // If the row is entirely empty, break out of the loop
            echo "\n--- End of Data (Empty Row Detected at Row " . $row . ") ---\n";
            break; // Stop processing further rows
        } else {
            // If the row is not empty, print its content
            echo "Row " . $row . ": " . $rowDataOutput . "\n";
        }
    }
    echo '</pre></div>'; // End of data-section and pre tag

    // 2. Edit the Excel file (Example: Add a new value to a specific cell)
    // Using current time in Kuala Terengganu (GMT+8)
    $currentTimeFormatted = date('Y-m-d H:i:s', time() + 8 * 3600); 
    $worksheet->setCellValue('G1', 'Data Processed on: ' . $currentTimeFormatted);
    echo '<div class="message success">Added \'Data Processed on: ' . $currentTimeFormatted . '\' to cell G1.</div>';

    // 3. Save the modified file
    $outputFileName = 'D:/xampp/htdocs/TrackingReads/Modified Dataset.xlsx';
    $writer = new Xlsx($spreadsheet);
    // Set to false to avoid recalculating formulas before saving. Excel will do it on open.
    // This often helps prevent "Formula Error" during saving.
    $writer->setPreCalculateFormulas(false); 

    $writer->save($outputFileName);

    echo '<div class="message success">Modified Excel file saved as: ' . htmlspecialchars(basename($outputFileName)) . '</div>';

} catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
    echo '<div class="message error">';
    echo "<h3>Error with PhpSpreadsheet:</h3>";
    echo "<p>Message: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . htmlspecialchars($e->getFile()) . " on line " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<h4>Trace:</h4> <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo '</div>'; // End of error message div
} catch (Exception $e) {
    echo '<div class="message error">';
    echo "<h3>An unexpected error occurred:</h3>";
    echo "<p>Message: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . htmlspecialchars($e->getFile()) . " on line " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<h4>Trace:</h4> <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo '</div>'; // End of error message div
}

echo '    </div> </body>
</html>'; // End of HTML output

?>