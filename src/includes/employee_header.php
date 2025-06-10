<?php
// Assumes functions.php (with escape_html and session functions) is already included by the calling script.
// Assumes session is already started by the calling script (e.g., via require_login()).
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // This should ideally not happen if require_login() is used.
    // For safety, ensure critical session variables exist if trying to display them.
}
$current_page_employee = basename($_SERVER['PHP_SELF']);
$employee_base_path = "/public/employee/"; // Adjust if your structure is different

// Determine base path for logout if header is used in different directory depths
// This is a simplified example. A more robust solution might involve a global config for paths.
$logout_path = "../logout.php"; // Default if header is one level deep (e.g. in /employee/)
if (strpos($_SERVER['REQUEST_URI'], $employee_base_path . 'module/') !== false) { // Example if nested deeper
    // $logout_path = "../../logout.php";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? escape_html($page_title) : 'Employee Portal'; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        .employee-header { background-color: #007bff; color: white; padding: 0 20px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .employee-header .logo { float: left; padding: 15px 0; font-size: 1.5em; color: white; text-decoration: none; }
        .employee-header .employee-nav { float: left; margin-left: 30px;}
        .employee-header .employee-nav a {
            display: inline-block;
            color: white;
            text-align: center;
            padding: 18px 16px;
            text-decoration: none;
            font-size: 1em;
            transition: background-color 0.3s, color 0.3s;
        }
        .employee-header .employee-nav a:hover, .employee-header .employee-nav a.active { background-color: #0056b3; color: #e6e6e6; }
        .employee-header .user-info { float: right; padding: 18px 0; }
        .employee-header .user-info span { margin-right: 15px; }
        .employee-header .user-info a.logout { color: #f8f9fa; text-decoration: none; background-color: #dc3545; padding: 8px 12px; border-radius: 4px; }
        .employee-header .user-info a.logout:hover { background-color: #c82333; text-decoration: none; }

        .e-container { padding: 20px; background-color: #fff; margin: 20px; border-radius: 8px; box-shadow: 0 1px 5px rgba(0,0,0,0.1); }
        .e-container h1, .e-container h2, .e-container h3 { color: #0056b3; }

        /* Basic Table Styling */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        th, td { border: 1px solid #dee2e6; padding: 12px 15px; text-align: left; }
        th { background-color: #e9ecef; color: #495057; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;}
        tr:nth-child(even) { background-color: #f8f9fa; }
        tr:hover { background-color: #e9ecef; }

        /* Basic Form Styling & Buttons */
        .e-button, button[type="submit"] {
            background-color: #007bff; color: white; padding: 10px 18px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; text-decoration: none; display: inline-block;
            transition: background-color 0.2s ease-in-out;
        }
        .e-button:hover, button[type="submit"]:hover { background-color: #0056b3; }
        .e-button.secondary { background-color: #6c757d; }
        .e-button.secondary:hover { background-color: #545b62; }
        .e-button.success { background-color: #28a745; }
        .e-button.success:hover { background-color: #1e7e34; }
        .e-button.disabled { background-color: #adb5bd; cursor: not-allowed; }


        /* Messages */
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }

        /* Module & Lesson Cards */
        .module-list, .lesson-list { list-style: none; padding: 0; }
        .module-item, .lesson-item { background-color: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 5px; padding: 15px; margin-bottom: 15px; transition: box-shadow 0.2s ease-in-out; }
        .module-item:hover, .lesson-item:hover { box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .module-item h3, .lesson-item h4 { margin-top: 0; }
        .module-item a, .lesson-item a { text-decoration: none; color: #007bff; }
        .module-item a:hover, .lesson-item a:hover { text-decoration: underline; }
        .status-badge { padding: 3px 8px; border-radius: 10px; font-size: 0.8em; color: white; }
        .status-not_started { background-color: #6c757d; } /* Grey */
        .status-in_progress { background-color: #ffc107; color: #333 } /* Yellow */
        .status-training_completed { background-color: #17a2b8; } /* Teal */
        .status-quiz_available { background-color: #fd7e14; } /* Orange */
        .status-passed { background-color: #28a745; } /* Green */
        .status-failed { background-color: #dc3545; } /* Red */
        .status-viewed { background-color: #17a2b8; } /* Teal for viewed lessons */
        .status-completed { background-color: #28a745; } /* Green for completed lessons */
        .status-not_viewed { background-color: #6c757d; }

        /* Lesson Content Styling */
        .lesson-content-text { background-color: #ffffff; padding: 20px; border-radius: 5px; border: 1px solid #ddd; margin-top: 15px; line-height: 1.6; }
        .lesson-content-video, .lesson-content-image { margin-top: 15px; text-align: center; }
        .lesson-content-video iframe, .lesson-content-image img { max-width: 100%; border-radius: 5px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }


    </style>
</head>
<body>

<header class="employee-header">
    <a href="<?php echo $employee_base_path; ?>dashboard.php" class="logo">SEATS Portal</a>
    <nav class="employee-nav">
        <a href="<?php echo $employee_base_path; ?>dashboard.php" <?php if($current_page_employee == 'dashboard.php') echo 'class="active"'; ?>>Dashboard</a>
        <!-- <a href="<?php echo $employee_base_path; ?>my_progress.php" <?php if($current_page_employee == 'my_progress.php') echo 'class="active"'; ?>>My Progress</a> -->
    </nav>
    <div class="user-info">
        <span>Hello, <?php echo escape_html($_SESSION['first_name'] ?? 'Employee'); ?>!</span>
        <a href="<?php echo $logout_path; ?>" class="logout">Logout</a>
    </div>
</header>

<main class="e-container">
    <!-- Main content of each employee page will go here -->

<?php
// Universal message display section for employee pages
if (isset($_SESSION['message'])) {
    echo '<div class="message success">' . escape_html($_SESSION['message']) . '</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="message error">' . escape_html($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}
?>
