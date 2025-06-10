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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo WEB_ROOT_PATH; ?>/public/css/style.css">
    <?php
        // Define WEB_ROOT_PATH if not already defined - this is a placeholder
        // In a real app, this would be part of a global config or bootstrap file.
        if (!defined('WEB_ROOT_PATH')) {
            // Attempt to determine root path. This is a simplified example.
            // Assumes admin header is in src/includes/ and public is one level up from src/
            // Adjust if your structure is different.
            // A more robust way is to have a config file that sets this path.
            $doc_root = $_SERVER['DOCUMENT_ROOT'];
            $project_folder_from_doc_root = ''; // if seats_app is directly in doc_root
            // If script is /app/src/includes/admin_header.php, and public is /app/public
            // We need to find the common base like /app
            // This is tricky to auto-detect robustly in all server configs.
            // For now, using a relative path, assuming CSS is accessible from where PHP is served.
            // This relative path assumes that PHP files including this header are at a certain depth.
            // e.g. if files in /public/admin/ use this header, path to css is ../../public/css/style.css
            // This is highly dependent on file structure and include method.
            // A defined constant from a bootstrap file is the best way.
            // For this exercise, I'll use a simplified relative path, assuming common structure.
            // This might need adjustment based on how the app is served.
            // Let's assume the PHP files including this are two levels deep from a common root where /public sits.
            // e.g. /app/public/admin/somefile.php includes /app/src/includes/admin_header.php
            // CSS is at /app/public/css/style.css
            // So from somefile.php, path to style.css is ../../public/css/style.css
            // From admin_header.php if it were directly accessed (it's not), it's different.
            // Let's assume files including this header are in /public/admin/
            // So, path from there to /public/css/style.css is ../css/style.css
            // This is still problematic. Using an absolute path from a defined root is better.
            // For now, I'll construct a path that might work if WEB_ROOT_PATH is defined.
            // If WEB_ROOT_PATH is not defined, this will likely fail to load CSS correctly.
            // **This pathing logic is a placeholder for a proper configuration setup.**
             $css_path = "../../public/css/style.css"; // Assuming files are in public/admin/
             if (strpos($_SERVER['PHP_SELF'], "/public/admin/") === false) {
                 // If file is at public/ root, like login.php including an admin header (unlikely)
                 // $css_path = "css/style.css";
             }
             // A better approach if WEB_ROOT_PATH is available:
             // $css_path = (defined('WEB_ROOT_PATH') ? WEB_ROOT_PATH : '') . "/public/css/style.css";
             // For this tool, I can't define WEB_ROOT_PATH. Will use a relative path that
             // assumes files including this header are in /public/admin/ folder.
        }
    ?>
    <!-- <link rel="stylesheet" href="<?php // echo $css_path; ?>"> -->
    <!-- Using a simpler relative path for now based on typical admin page location -->
    <link rel="stylesheet" href="../css/style.css"> <!-- If admin pages are in /public/admin -->


</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">SEATS Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbarSupportedContent" aria-controls="adminNavbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page == 'dashboard.php') echo 'active'; ?>" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page == 'users.php') echo 'active'; ?>" href="users.php">Users</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php if(in_array($current_page, ['manage_modules.php', 'manage_lessons.php', 'manage_quizzes.php', 'module_progress_overview.php', 'view_module_progress_detail.php'])) echo 'active'; ?>" href="#" id="navbarDropdownModules" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Modules & Training
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownModules">
                        <li><a class="dropdown-item <?php if($current_page == 'manage_modules.php') echo 'active'; ?>" href="manage_modules.php">Manage Modules</a></li>
                        <li><a class="dropdown-item <?php if($current_page == 'module_progress_overview.php') echo 'active'; ?>" href="module_progress_overview.php">Module Progress</a></li>
                    </ul>
                </li>
                 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php if(in_array($current_page, ['user_progress_overview.php', 'view_employee_progress.php'])) echo 'active'; ?>" href="#" id="navbarDropdownUserProgress" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        User Progress
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownUserProgress">
                        <li><a class="dropdown-item <?php if($current_page == 'user_progress_overview.php') echo 'active'; ?>" href="user_progress_overview.php">Overview</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page == 'reports.php' || $current_page == 'view_user_progress_report.php' || $current_page == 'view_quiz_results_report.php') echo 'active'; ?>" href="reports.php">Reports</a>
                </li>
            </ul>
            <div class="d-flex">
                 <span class="navbar-text me-3">
                    Welcome, <?php echo escape_html($_SESSION['first_name'] ?? 'Admin'); ?>!
                </span>
                <a href="../logout.php" class="btn btn-outline-danger">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <!-- Main content of each page will go here -->
    <!-- Error/Success messages will be typically shown here by individual pages -->

<?php
// Universal message display section
// Pages should set $_SESSION['message'] and $_SESSION['error']
// This header will display them and then clear them to prevent reappearance on next page load.

if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . escape_html($_SESSION['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['message']); // Clear message after displaying
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . escape_html($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['error_message']); // Clear error after displaying
}
if (isset($_SESSION['info_message'])) {
    echo '<div class="alert alert-info alert-dismissible fade show" role="alert">' . escape_html($_SESSION['info_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['info_message']); // Clear error after displaying
}
?>
