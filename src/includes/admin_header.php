<?php
// Assumes functions.php (with escape_html and session functions) is already included by the calling script.
// Assumes session is already started by the calling script (e.g., via require_admin()).
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // This should ideally not happen if require_login() or require_admin() is used.
    // redirect('/public/login.php'); // Or an appropriate base path
    // For safety, though, ensure critical session variables exist if trying to display them.
    // For now, we'll rely on the calling page to handle session enforcement.
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? escape_html($page_title) : 'Admin Panel'; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f9f9f9; color: #333; }
        .admin-header { background-color: #2c3e50; color: white; padding: 0 20px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-header .logo { float: left; padding: 15px 0; font-size: 1.5em; color: white; text-decoration: none; }
        .admin-header .admin-nav { float: left; margin-left: 30px;}
        .admin-header .admin-nav a {
            display: inline-block; /* Changed from float: left */
            color: white;
            text-align: center;
            padding: 18px 16px;
            text-decoration: none;
            font-size: 1em;
            transition: background-color 0.3s, color 0.3s;
        }
        .admin-header .admin-nav a:hover, .admin-header .admin-nav a.active { background-color: #34495e; /* Darker shade for hover/active */ color: #ecf0f1; }
        .admin-header .user-info { float: right; padding: 18px 0; }
        .admin-header .user-info span { margin-right: 15px; }
        .admin-header .user-info a.logout { color: #e74c3c; text-decoration: none; }
        .admin-header .user-info a.logout:hover { color: #c0392b; text-decoration: underline; }

        .container { padding: 20px; background-color: #fff; margin: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        h1, h2, h3 { color: #2c3e50; }

        /* Basic Table Styling */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ecf0f1; padding: 12px 15px; text-align: left; }
        th { background-color: #34495e; color: #ecf0f1; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;}
        tr:nth-child(even) { background-color: #fdfdfd; }
        tr:hover { background-color: #f1f1f1; }

        /* Basic Form Styling */
        form { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        input[type="text"], input[type="date"], input[type="number"], textarea, select {
            width: calc(100% - 24px); padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
            font-size: 1em;
        }
        textarea { min-height: 100px; resize: vertical; }
        button[type="submit"], .button-link {
            background-color: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; text-decoration: none; display: inline-block;
        }
        button[type="submit"]:hover, .button-link:hover { background-color: #2980b9; }
        .button-link.edit { background-color: #f39c12; }
        .button-link.edit:hover { background-color: #e67e22; }
        .button-link.delete { background-color: #e74c3c; }
        .button-link.delete:hover { background-color: #c0392b; }
        .button-link.manage { background-color: #2ecc71; }
        .button-link.manage:hover { background-color: #27ae60; }
        .action-links a { margin-right: 5px; font-size:0.9em; padding: 5px 8px;}

        /* Messages */
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }

        /* For edit forms */
        .edit-form-container { background-color: #f0f7ff; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #b3d7ff;}
        .edit-form-container h3 { margin-top: 0; color: #0056b3; }
    </style>
</head>
<body>

<header class="admin-header">
    <a href="dashboard.php" class="logo">SEATS Admin</a>
    <nav class="admin-nav">
        <a href="dashboard.php" <?php if($current_page == 'dashboard.php') echo 'class="active"'; ?>>Dashboard</a>
        <a href="users.php" <?php if($current_page == 'users.php') echo 'class="active"'; ?>>Users</a>
        <a href="manage_modules.php" <?php if($current_page == 'manage_modules.php' || $current_page == 'manage_lessons.php' || $current_page == 'manage_quizzes.php'  || $current_page == 'module_progress_overview.php' || $current_page == 'view_module_progress_detail.php') echo 'class="active"'; ?>>Modules & Training</a>
        <a href="user_progress_overview.php" <?php if($current_page == 'user_progress_overview.php' || $current_page == 'view_employee_progress.php') echo 'class="active"'; ?>>User Progress</a>
        <!-- Add more links as needed, e.g., for reports -->
    </nav>
    <div class="user-info">
        <span>Welcome, <?php echo escape_html($_SESSION['first_name'] ?? 'Admin'); ?>!</span>
        <a href="../logout.php" class="logout">Logout</a>
    </div>
</header>

<main class="container">
    <!-- Main content of each page will go here -->
    <!-- Error/Success messages will be typically shown here by individual pages -->

<?php
// Universal message display section
// Pages should set $_SESSION['message'] and $_SESSION['error']
// This header will display them and then clear them to prevent reappearance on next page load.

if (isset($_SESSION['message'])) {
    echo '<div class="message success">' . escape_html($_SESSION['message']) . '</div>';
    unset($_SESSION['message']); // Clear message after displaying
}
if (isset($_SESSION['error_message'])) { // Changed from 'error' to avoid conflict with $error variable in some scripts
    echo '<div class="message error">' . escape_html($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']); // Clear error after displaying
}
?>
