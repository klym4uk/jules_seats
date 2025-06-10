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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css"> <!-- Assuming employee pages are in /public/employee -->
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $employee_base_path; ?>dashboard.php">SEATS Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#employeeNavbarSupportedContent" aria-controls="employeeNavbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="employeeNavbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page_employee == 'dashboard.php') echo 'active'; ?>" href="<?php echo $employee_base_path; ?>dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($current_page_employee == 'my_progress.php') echo 'active'; ?>" href="<?php echo $employee_base_path; ?>my_progress.php">My Progress</a>
                </li>
            </ul>
            <div class="d-flex">
                 <span class="navbar-text me-3 text-white">
                    Hello, <?php echo escape_html($_SESSION['first_name'] ?? 'Employee'); ?>!
                </span>
                <a href="<?php echo $logout_path; ?>" class="btn btn-danger">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <!-- Main content of each employee page will go here -->

<?php
// Universal message display section for employee pages
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . escape_html($_SESSION['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . escape_html($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['info_message'])) {
    echo '<div class="alert alert-info alert-dismissible fade show" role="alert">' . escape_html($_SESSION['info_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['info_message']);
}
?>
