<?php
// admin/edit-notes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db_connect.php';

// Access control: Ensure only logged-in admins can access this page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

$note_id = $_GET['id'] ?? null; // Get the note ID from the URL

if (!$note_id) {
    // If no ID is provided, redirect back to manage notes
    header("location: manage-existing-notes.php");
    exit;
}

$title = $description = $price = $file_path_display = $uploaded_by_display = $created_at_display = '';
$title_err = $description_err = $price_err = $file_err = $general_err = '';
$current_file_path = ''; // To hold the existing file path for updating

// --- Fetch existing note data ---
if ($conn) {
    $sql_fetch = "SELECT id, title, description, price, file_path, uploaded_by, created_at FROM notes WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $note_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();

        if ($result_fetch->num_rows === 1) {
            $note = $result_fetch->fetch_assoc();
            $title = htmlspecialchars($note['title']);
            $description = htmlspecialchars($note['description']);
            $price = htmlspecialchars($note['price']);
            $current_file_path = $note['file_path']; // Store the actual path
            $file_path_display = htmlspecialchars(basename($note['file_path'])); // Display just the filename
            $uploaded_by_display = htmlspecialchars($note['uploaded_by']);
            $created_at_display = htmlspecialchars($note['created_at']);
        } else {
            $general_err = "Note not found.";
            // Consider redirecting if note not found
            // header("location: manage-existing-notes.php");
            // exit;
        }
        $stmt_fetch->close();
    } else {
        $general_err = "Database error: Could not prepare fetch statement.";
    }
} else {
    $general_err = "Database connection not established.";
}


// --- Process form submission for updating ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($general_err)) {
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
        $price = (float)$price_str;
    }

    // Handle file upload (only if a new file is selected)
    $new_file_path = $current_file_path; // Assume existing path by default

    if (isset($_FILES["note_file"]) && $_FILES["note_file"]["error"] === UPLOAD_ERR_OK) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Ensure directory exists
        }

        $file_name = basename($_FILES["note_file"]["name"]);
        $unique_file_name = uniqid() . '_' . $file_name;
        $target_file = $target_dir . $unique_file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        if (!in_array($file_type, $allowed_types)) {
            $file_err = "Sorry, only PDF, DOC, DOCX, TXT, PPT, PPTX, XLS, XLSX, JPG, JPEG, PNG files are allowed.";
        }

        if ($_FILES["note_file"]["size"] > 10 * 1024 * 1024) { // 10 MB
            $file_err = "Sorry, your file is too large. Max 10MB allowed.";
        }

        if (empty($file_err)) {
            if (move_uploaded_file($_FILES["note_file"]["tmp_name"], $target_file)) {
                // If a new file is uploaded successfully, delete the old one
                if ($current_file_path && file_exists($current_file_path) && $current_file_path !== $target_file) {
                    unlink($current_file_path); // Delete old file
                }
                $new_file_path = $target_file; // Update to new path
            } else {
                $file_err = "Sorry, there was an error uploading the new file.";
            }
        }
    } else if ($_FILES["note_file"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $file_err = "File upload error: " . $_FILES["note_file"]["error"];
    }


    // Check for errors before updating into database
    if (empty($title_err) && empty($description_err) && empty($price_err) && empty($file_err) && empty($general_err)) {
        // Prepare an update statement
        $sql_update = "UPDATE notes SET title = ?, description = ?, price = ?, file_path = ? WHERE id = ?";

        if ($stmt_update = $conn->prepare($sql_update)) {
            // Bind parameters: 'ssdsi' -> string, string, decimal, string, integer
            $stmt_update->bind_param("ssdsi", $title, $description, $price, $new_file_path, $note_id);

            if ($stmt_update->execute()) {
                $general_err = "<div class='success-message'>Note updated successfully!</div>";
                // Update $current_file_path in case a new file was uploaded
                $current_file_path = $new_file_path;
                $file_path_display = htmlspecialchars(basename($current_file_path)); // Update display
            } else {
                $general_err = "<div class='help-block'>Error updating note: " . $stmt_update->error . "</div>";
            }
            $stmt_update->close();
        } else {
            $general_err = "<div class='help-block'>Database error: Could not prepare update statement. " . $conn->error . "</div>";
        }
    } else {
        $general_err = "<div class='help-block'>Please correct the errors before updating.</div>";
    }
}

require_once '../includes/header.php';
?>

<div class="main-content-area">
    <div class="auth-container">
        <h2>Edit Note (ID: <?php echo htmlspecialchars($note_id); ?>)</h2>
        <p>Modify the details of this note below.</p>

        <?php echo $general_err; // Display general status/error messages ?>

        <form action="edit-notes.php?id=<?php echo htmlspecialchars($note_id); ?>" method="post" enctype="multipart/form-data">
            <label>Note Title:</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>">
            <span class="help-block"><?php echo $title_err; ?></span>

            <label>Description (Optional):</label>
            <textarea name="description" rows="5"><?php echo htmlspecialchars($description); ?></textarea>
            <span class="help-block"><?php echo $description_err; ?></span>

            <label>Price (MYR):</label>
            <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($price); ?>">
            <span class="help-block"><?php echo $price_err; ?></span>

            <label>Current File:</label>
            <p>
                <?php if ($file_path_display): ?>
                    <a href="<?php echo htmlspecialchars($current_file_path); ?>" target="_blank"><?php echo $file_path_display; ?></a>
                <?php else: ?>
                    No file uploaded.
                <?php endif; ?>
            </p>

            <div class="form-group">
                <label for="noteFile" style="font-weight: bold; color: #333;">Replace Note File (Optional):</label>
                <input type="file" class="form-control-file" id="noteFile" name="note_file" style="display: none;">

                <div class="file-input-display-wrapper"> <label for="noteFile" class="custom-file-label">Choose File</label>
                    <span id="file-name-display">No file chosen</span>
                </div>

                <small class="form-text text-muted" style="color: #666 !important;">
                    Leave blank to keep the current file. Max 10MB, allowed types: PDF, DOC, DOCX, TXT, PPT, PPTX, XLS, XLSX, JPG, JPEG, PNG.
                </small>
            </div>

            <label>Uploaded By (ID):</label>
            <input type="text" value="<?php echo $uploaded_by_display; ?>" disabled>
            <small>This field cannot be changed here.</small>

            <label>Created At:</label>
            <input type="text" value="<?php echo $created_at_display; ?>" disabled>
            <small>This field cannot be changed here.</small>

            <button type="submit" class="btn btn-primary">Update Note</button>
            <a href="manage-existing-notes.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('noteFile');
        const fileNameDisplay = document.getElementById('file-name-display');

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
$conn->close();
require_once '../includes/footer.php';
?>