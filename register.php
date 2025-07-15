<?php
// register.php
// This file handles user registration.

// Start a PHP session to manage user login state.
session_start();

// Include the database connection file.
// This file contains the $conn variable for database interaction.
require_once 'includes/db_connect.php';

// Initialize variables to store error messages.
$username_err = $email_err = $password_err = $confirm_password_err = "";
$registration_success = "";

// Check if the form has been submitted using the POST method.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Validate Username
    // Check if username is empty.
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Prepare a SELECT statement to check if the username already exists.
        $sql = "SELECT id FROM users WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Bind parameters (s = string).
            $stmt->bind_param("s", $param_username);

            // Set parameters.
            $param_username = trim($_POST["username"]);

            // Attempt to execute the prepared statement.
            if ($stmt->execute()) {
                // Store result.
                $stmt->store_result();

                // If a row exists, the username is already taken.
                if ($stmt->num_rows == 1) {
                    $username_err = "This username is already taken.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement.
            $stmt->close();
        }
    }

    // 2. Validate Email
    // Check if email is empty.
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Prepare a SELECT statement to check if the email already exists.
        $sql = "SELECT id FROM users WHERE email = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $email_err = "This email is already registered.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }

    // 3. Validate Password
    // Check if password is empty.
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    }

    // 4. Validate Confirm Password
    // Check if confirm password is empty.
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        // Check if password and confirm password match.
        if (trim($_POST["password"]) != trim($_POST["confirm_password"])) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // If there are no input errors, proceed with inserting the user into the database.
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {

        // Prepare an INSERT statement.
        // The 'role' is defaulted to 'user' for new registrations.
        $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')";

        if ($stmt = $conn->prepare($sql)) {
            // Bind parameters (s = string).
            $stmt->bind_param("sss", $param_username, $param_email, $param_password);

            // Set parameters.
            $param_username = trim($_POST["username"]);
            $param_email = trim($_POST["email"]);
            // Hash the password before storing it in the database for security.
            $param_password = password_hash(trim($_POST["password"]), PASSWORD_DEFAULT); // Creates a password hash

            // Attempt to execute the prepared statement.
            if ($stmt->execute()) {
                $registration_success = "Registration successful! You can now log in.";
                // Optionally, redirect to login page after successful registration
                // header("location: login.php");
                // exit();
            } else {
                echo "Something went wrong. Please try again later.";
            }

            // Close statement.
            $stmt->close();
        }
    }

    // Close connection.
    $conn->close();
}
?>

<?php
// Include the header file.
// This will provide the common HTML head, opening body, and navigation.
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
            <input type="text" name="username" class="form-control" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            <span class="help-block"><?php echo $username_err; ?></span>
        </div>
        <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
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
// This will provide the closing body and HTML tags.
require_once 'includes/footer.php';
?>
