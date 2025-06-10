<?php

/**
 * Redirects to another page.
 *
 * @param string $url The URL to redirect to.
 * @return void
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Escapes HTML special characters in a string.
 *
 * @param string $string The string to escape.
 * @return string The escaped string.
 */
function escape_html($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Starts a new or resumes an existing session.
 */
function start_session_if_not_started() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Checks if a user is logged in.
 *
 * @return bool True if logged in, false otherwise.
 */
function is_logged_in() {
    start_session_if_not_started();
    return isset($_SESSION['user_id']);
}

/**
 * Requires the user to be logged in. Redirects to login page if not.
 * Optionally, specify a role to check against.
 *
 * @param string|null $required_role The role required to access the page.
 * @return void
 */
function require_login($required_role = null) {
    start_session_if_not_started();
    if (!is_logged_in()) {
        redirect('/login.php'); // Adjust path if login.php is not in root
    }
    if ($required_role !== null && (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role)) {
        // User is logged in but does not have the required role
        // Redirect to a generic access denied page or back to their dashboard
        // For now, redirecting to login, but ideally, this would be a specific "access denied" page or user's dashboard
        // If you have different dashboards:
        // if ($_SESSION['role'] === 'Admin') redirect('/admin/dashboard.php');
        // else redirect('/employee/dashboard.php');
        // For now, a simple message or redirect to login
        // echo "Access Denied. You do not have the required permissions.";
        // exit;
        redirect('/login.php?error=access_denied');
    }
}

/**
 * Checks if the logged-in user is an Admin.
 *
 * @return bool True if user is Admin, false otherwise.
 */
function is_admin() {
    start_session_if_not_started();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

/**
 * Requires the user to be an Admin. Redirects if not.
 *
 * @return void
 */
function require_admin() {
    require_login('Admin');
}

?>
