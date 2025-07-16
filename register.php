<?php
// register.php
// This file handles user registration.

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start a PHP session to manage user login state.
session_start();

// Include the database connection file.
// This file contains the $conn variable for database interaction.
require_once 'includes/db_connect.php';

// If user is already logged in, redirect them based on their role
if (!empty($_SESSION['loggedin'])) {
    switch ($_SESSION['user_role']) {
        case 'admin':
        case 'manager':
            header("Location: admin/dashboard.php");
            break;
        case 'clerk':
        default:
            header("Location: index.php");
            break;
    }
    exit; // IMPORTANT: Always exit after a header redirect
}

// Initialize variables to store error messages.
$username_err = $email_err = $password_err = $confirm_password_err = "";
$registration_success = "";
$username_val = ''; // To retain username value in form
$email_val = '';   // To retain email value in form

// Check if the form has been submitted using the POST method.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get and trim input values
    $username = trim($_POST["username"] ?? '');
    $email    = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');
    $confirm_password = trim($_POST["confirm_password"] ?? '');

    // Retain values for form
    $username_val = htmlspecialchars($username);
    $email_val = htmlspecialchars($email);

    // 1. Validate Username
    if (empty($username)) {
        $username_err = "Please enter a username.";
    } else {
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $username_err = "This username is already taken.";
                }
            } else {
                error_log("Register DB Error (username check): " . $stmt->error);
                echo "Oops! Something went wrong with username check. Please try again later.";
            }
            $stmt->close();
        } else {
            error_log("Register Prepare Error (username check): " . $conn->error);
            echo "Oops! Something went wrong with username check preparation. Please try again later.";
        }
    }

    // 2. Validate Email
    if (empty($email)) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = $email;
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $email_err = "This email is already registered.";
                }
            } else {
                error_log("Register DB Error (email check): " . $stmt->error);
                echo "Oops! Something went wrong with email check. Please try again later.";
            }
            $stmt->close();
        } else {
            error_log("Register Prepare Error (email check): " . $conn->error);
            echo "Oops! Something went wrong with email check preparation. Please try again later.";
        }
    }

    // 3. Validate Password
    if (empty($password)) {
        $password_err = "Please enter a password.";
    } elseif (strlen($password) < 6) {
        $password_err = "Password must have at least 6 characters.";
    }

    // 4. Validate Confirm Password
    if (empty($confirm_password)) {
        $confirm_password_err = "Please confirm password.";
    } else {
        if ($password !== $confirm_password) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // If there are no input errors, proceed with inserting the user into the database.
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {

        // Prepare an INSERT statement.
        // MODIFIED: Added `profile_picture` to the INSERT statement.
        // Assuming `profile_picture` can be NULL in your database.
        $sql = "INSERT INTO users (username, email, password, role, profile_picture) VALUES (?, ?, ?, 'user', ?)";

        if ($stmt = $conn->prepare($sql)) {
            // Bind parameters (s = string).
            // MODIFIED: Added 's' for profile_picture.
            $stmt->bind_param("ssss", $param_username, $param_email, $param_password, $param_profile_picture);

            // Set parameters.
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_profile_picture = NULL; // Default to NULL for now, no upload functionality yet

            // Attempt to execute the prepared statement.
            if ($stmt->execute()) {
                $registration_success = "Registration successful! You can now log in.";
                // Optionally, redirect to login page after successful registration
                // header("location: login.php");
                // exit();
            } else {
                // Log specific database error for insert failure
                error_log("Register DB Error (insert): " . $stmt->error);
                echo "Something went wrong with registration. Please try again later.";
            }

            // Close statement.
            $stmt->close();
        } else {
            // Log specific database error for prepare failure
            error_log("Register Prepare Error (insert): " . $conn->error);
            echo "Oops! Something went wrong with registration preparation. Please try again later.";
        }
    }
}

// Include the header file.
require_once 'includes/header.php';
?>

<div class="auth-container">
    <h2>Register</h2>
    <p>Please fill this form to create an account.</p>
    <?php
    // Display registration success message if available.
    if (!empty($registration_success)) {
        echo '<div class="success-message">' . $registration_success . '</div>';
    }
    ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
            <label>Username</label>
            <input type="text" name="username" class="form-control" value="<?php echo $username_val; ?>">
            <span class="help-block"><?php echo $username_err; ?></span>
        </div>
        <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo $email_val; ?>">
            <span class="help-block"><?php echo $email_err; ?></span>
        </div>
        <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
            <label>Password</label>
            <input type="password" name="password" class="form-control">
            <span class="help-block"><?php echo $password_err; ?></span>
        </div>
        <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?>">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control">
            <span class="help-block"><?php echo $confirm_password_err; ?></span>
        </div>
        <div class="form-group">
            <input type="submit" class="btn btn-primary" value="Register">
        </div>
        <p>Already have an account? <a href="login.php">Login here</a>.</p>
        <!-- Optional: Google Sign-in button placeholder -->
        <div class="google-signin-btn">
            <p>Or sign in with Google:</p>
            <!-- You'll need to integrate Google Sign-In API here -->
            <button type="button" class="btn btn-google">Sign in with Google</button>
        </div>
    </form>
</div>

<?php
// Include the footer file.
require_once 'includes/footer.php';

// Close connection at the very end of the script
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
