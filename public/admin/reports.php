<?php
ob_start();
require_once '../../config/database.php'; // For any potential DB interaction, though not used now
require_once '../../src/includes/functions.php'; // For session and security functions
require_once '../../src/User.php'; // For require_admin

start_session_if_not_started();
require_admin(); // Ensure only Admin users can access

$page_title = "Admin Reports";
include_once '../../src/includes/admin_header.php';
?>

<h1><?php echo $page_title; ?></h1>
<p>Select a report to view or generate.</p>

<div class="dashboard-stats-container" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top:20px;">

    <div class="stat-card" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-basis: 300px;">
        <h3>User Progress Report</h3>
        <p>View overall progress for all employees and export data to CSV.</p>
        <p><a href="view_user_progress_report.php" class="button-link">View User Progress Report</a></p>
    </div>

    <div class="stat-card" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-basis: 300px;">
        <h3>Quiz Results Report</h3>
        <p>View detailed results for a specific quiz, including pass rates and individual attempts. Export data to CSV.</p>
        <p><a href="view_quiz_results_report.php" class="button-link">View Quiz Results Report</a></p>
    </div>

    <!-- Add more report sections here as needed -->

</div>

<?php
echo "</main>"; // Close main.container from header
ob_end_flush();
?>
</body>
</html>
