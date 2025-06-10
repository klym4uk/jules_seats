<?php
// Ensure no output before session_start()
ob_start();

require_once '../config/database.php';
require_once '../src/User.php';
require_once '../src/includes/functions.php';

start_session_if_not_started();

// If already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('employee/dashboard.php');
    }
}

$userHandler = new User($pdo);
$login_error = ''; // Renamed from $error to avoid conflict with any global $error
$login_message = ''; // Renamed from $message

if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $_SESSION['error_message'] = "Access Denied. You do not have the required permissions to view that page.";
    // $login_error = "Access Denied. You do not have the required permissions to view that page.";
}
if (isset($_GET['message']) && $_GET['message'] === 'logout_success') {
    $_SESSION['message'] = "You have been logged out successfully.";
    // $login_message = "You have been logged out successfully.";
}
if (isset($_GET['message']) && $_GET['message'] === 'reg_success') {
    $_SESSION['message'] = "Registration successful! Please login.";
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $login_error = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = "Invalid email format.";
    } else {
        $user = $userHandler->findByEmail($email);

        if ($user && $userHandler->verifyPassword($password, $user->password_hash)) {
            $_SESSION['user_id'] = $user->user_id;
            $_SESSION['email'] = $user->email;
            $_SESSION['first_name'] = $user->first_name;
            $_SESSION['role'] = $user->role;

            $userHandler->updateLastLogin($user->user_id);

            if ($user->role === 'Admin') {
                redirect('admin/dashboard.php');
            } else {
                redirect('employee/dashboard.php');
            }
        } else {
            $login_error = "Invalid email or password.";
        }
    }
    // If there's a login error, set it to session to be displayed by header include
    if ($login_error) {
        $_SESSION['error_message'] = $login_error;
        redirect('login.php'); // Redirect to show the message via session
        exit;
    }
}
// ob_end_flush(); // Flushing here might be problematic if redirecting. Better to let it flush at end of script.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEATS - Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f8f9fa; /* Light background */
        }
        .login-container {
            max-width: 450px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h2 class="text-center mb-4">SEATS Portal Login</h2>

                <?php
                // Display session messages
                if (isset($_SESSION['message'])) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . escape_html($_SESSION['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    unset($_SESSION['message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . escape_html($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    unset($_SESSION['error_message']);
                }
                // $login_error and $login_message are now handled by session messages.
                ?>

                <form action="login.php" method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? escape_html($_POST['email']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>
                <div class="mt-3 text-center">
                    <small>Admin only: <a href="register.php">Register new user</a></small>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>
