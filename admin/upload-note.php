<?php
// admin/upload-note.php
// Allows administrators to upload new notes.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php'; // Ensure path is correct

// Access Control: Only logged-in admins can access this page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

$title = $description = $price = $upload_status = '';
$title_err = $description_err = $price_err = $file_err = '';
$file_path = NULL; // Initialize file path as NULL

// Process form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate title
    if (empty(trim($_POST["title"] ?? ''))) {
        $title_err = "Please enter a title for the note.";
    } else {
        $title = trim($_POST["title"]);
    }

    // Validate description (optional)
    $description = trim($_POST["description"] ?? '');

    // Validate price
    $price_str = trim($_POST["price"] ?? '');
    if (empty($price_str)) {
        $price_err = "Please enter a price for the note.";
    } else if (!is_numeric($price_str) || $price_str < 0) {
        $price_err = "Price must be a non-negative number.";
    } else {
        $price = (float)$price_str; // Convert to float for decimal type
    }

    // Handle file upload
    if (isset($_FILES["note_file"]) && $_FILES["note_file"]["error"] === UPLOAD_ERR_OK) {
        $target_dir = "../uploads/"; // Directory to save uploaded files (one level up from admin/)
        // Create the uploads directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Consider more restrictive permissions for production
        }

        $file_name = basename($_FILES["note_file"]["name"]);
        // Append a unique ID to the filename to prevent overwrites and make names unique
        $unique_file_name = uniqid() . '_' . $file_name;
        $target_file = $target_dir . $unique_file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Allow certain file formats (adjust as needed)
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']; // Added image types
        if (!in_array($file_type, $allowed_types)) {
            $file_err = "Sorry, only PDF, DOC, DOCX, TXT, PPT, PPTX, XLS, XLSX, JPG, JPEG, PNG files are allowed.";
        }

        // Check file size (e.g., max 10MB)
        if ($_FILES["note_file"]["size"] > 10 * 1024 * 1024) { // 10 MB
            $file_err = "Sorry, your file is too large. Max 10MB allowed.";
        }

        // Check if $file_err is empty before attempting to upload
        if (empty($file_err)) {
            if (move_uploaded_file($_FILES["note_file"]["tmp_name"], $target_file)) {
                $file_path = $target_file; // Store the relative path
            } else {
                $file_err = "Sorry, there was an error uploading your file.";
            }
        }
    } else if ($_FILES["note_file"]["error"] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors besides no file being selected
        $file_err = "File upload error: " . $_FILES["note_file"]["error"];
    } else {
        // If no file was uploaded at all, and it's required (adjust as needed)
        $file_err = "Please select a file to upload.";
    }


    // Check for errors before inserting into database
    if (empty($title_err) && empty($description_err) && empty($price_err) && empty($file_err)) {
        // Prepare an insert statement that matches your 'notes' table columns
        $sql = "INSERT INTO notes (title, description, price, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // Bind parameters: 'sdssi' -> string, decimal, string, string, integer
            $uploaded_by_id = $_SESSION['user_id']; // Assuming admin's user_id is in session and matches uploaded_by column type
            $stmt->bind_param("ssdsi", $title, $description, $price, $file_path, $uploaded_by_id);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $upload_status = "<div class='success-message'>Note uploaded successfully!</div>";
                // Clear form fields
                $title = $description = $price = '';
                $file_path = NULL; // Clear file path variable
            } else {
                $upload_status = "<div class='help-block'>Error uploading note: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $upload_status = "<div class='help-block'>Database error: Could not prepare statement. " . $conn->error . "</div>";
        }
    } else {
        $upload_status = "<div class='help-block'>Please correct the errors above before uploading.</div>";
    }
}

require_once '../includes/header.php'; // Ensure path is correct
?>

<div class="main-content-area">
    <div class="auth-container"> <h2>Upload New Note</h2>
        <p>Fill out the form below to upload a new educational note.</p>

        <?php echo $upload_status; // Display upload status/messages ?>

        <form action="upload-note.php" method="post" enctype="multipart/form-data">
            <label>Note Title:</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>">
            <span class="help-block"><?php echo $title_err; ?></span>

            <label>Description (Optional):</label>
            <textarea name="description" rows="5"><?php echo htmlspecialchars($description); ?></textarea>
            <span class="help-block"><?php echo $description_err; ?></span>

            <label>Price (MYR):</label>
            <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($price); ?>">
            <span class="help-block"><?php echo $price_err; ?></span>

            <div class="form-group">
                <label for="noteFile" style="font-weight: bold; color: #333;">Select Note File:</label>
                <input type="file" class="form-control-file" id="noteFile" name="note_file" style="display: none;">

                <div class="file-input-display-wrapper">
                    <label for="noteFile" class="custom-file-label">Choose File</label>
                    <span id="file-name-display">No file chosen</span>
                </div>

                <small class="form-text text-muted" style="color: #666 !important;">
                    Max 10MB, allowed types: PDF, DOC, DOCX, TXT, PPT, PPTX, XLS, XLSX, JPG, JPEG, PNG.
                </small>
                <span class="help-block"><?php echo $file_err ?? ''; ?></span>
            </div>

            <button type="submit" class="btn btn-primary">Upload Note</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('noteFile'); // Corrected ID
        const fileNameDisplay = document.getElementById('file-name-display'); // Corrected ID

        if (fileInput && fileNameDisplay) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    fileNameDisplay.textContent = this.files[0].name;
                } else {
                    fileNameDisplay.textContent = 'No file chosen';
                }
            });
        }
    });
</script>

<?php
$conn->close(); // Close DB connection
require_once '../includes/footer.php'; // Ensure path is correct
?>