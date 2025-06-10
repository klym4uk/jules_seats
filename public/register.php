<?php
require_once '../config/database.php';
require_once '../src/User.php';
require_once '../src/includes/functions.php';

// For now, let's assume an admin is performing this action.
// Later, this should be protected by require_admin();
// start_session_if_not_started(); // Call this if using require_admin() or other session functions
// require_admin(); // Uncomment when admin sessions are implemented

$userHandler = new User($pdo);
$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role = isset($_POST['role']) ? $_POST['role'] : 'Employee'; // Default to Employee

    // Basic Validation
    if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) { // Basic password strength
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if user already exists
        $existingUser = $userHandler->findByEmail($email);
        if ($existingUser) {
            $error = "User with this email already exists.";
        } else {
            if ($userHandler->createUser($email, $password, $first_name, $last_name, $role)) {
                $message = "User registered successfully! Email: " . escape_html($email) . ", Role: " . escape_html($role);
            } else {
                $error = "Failed to register user. Please try again or check logs.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register User</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 500px; margin: auto; }
        h2 { text-align: center; color: #333; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="password"], select {
            width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        button {
            background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%;
        }
        button:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Register New User</h2>

        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="register.php" method="post">
            <div>
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div>
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            <div>
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            <div>
                <label for="role">Role:</label>
                <select id="role" name="role">
                    <option value="Employee" selected>Employee</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <button type="submit">Register User</button>
        </form>
        <p style="text-align: center; margin-top: 15px;">
            <a href="login.php">Login</a> |
            <a href="admin/users.php">Manage Users (Admin)</a>
        </p>
    </div>
</body>
</html>
