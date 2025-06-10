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
        redirect('admin/dashboard.php'); // Placeholder for admin dashboard
    } else {
        redirect('employee/dashboard.php'); // Placeholder for employee dashboard
    }
}

$userHandler = new User($pdo);
$error = '';

if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $error = "Access Denied. You do not have the required permissions to view that page.";
}
if (isset($_GET['message']) && $_GET['message'] === 'logout_success') {
    $message = "You have been logged out successfully.";
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $user = $userHandler->findByEmail($email);

        if ($user && $userHandler->verifyPassword($password, $user->password_hash)) {
            // Password is correct, start session
            $_SESSION['user_id'] = $user->user_id;
            $_SESSION['email'] = $user->email;
            $_SESSION['first_name'] = $user->first_name;
            $_SESSION['role'] = $user->role;

            // Update last login timestamp
            $userHandler->updateLastLogin($user->user_id);

            // Redirect to appropriate dashboard
            if ($user->role === 'Admin') {
                redirect('admin/dashboard.php'); // Placeholder
            } else {
                redirect('employee/dashboard.php'); // Placeholder
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
ob_end_flush(); // Flush output buffer
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; display: flex; justify-content: center; align-items: center; min-height: 90vh; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="email"], input[type="password"] {
            width: calc(100% - 24px); padding: 12px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        button {
            background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%;
        }
        button:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-footer { text-align: center; margin-top: 20px; }
        .form-footer a { color: #007bff; text-decoration: none; }
        .form-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>

        <?php if (!empty($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? escape_html($_POST['email']) : ''; ?>" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="form-footer">
            <p>Don't have an account? <a href="register.php">Register here</a> (Admins can create users).</p>
        </div>
    </div>
</body>
</html>
