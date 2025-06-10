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

<h1 class="mb-4"><?php echo $page_title; ?></h1>
<p class="lead">Select a report to view or generate from the options below.</p>

<div class="row row-cols-1 row-cols-md-2 g-4">

    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">User Progress Report</h5>
                <p class="card-text">View overall progress for all employees. This report provides insights into how employees are advancing through the available training modules. You can also export this data to CSV.</p>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                 <a href="view_user_progress_report.php" class="btn btn-primary">View User Progress Report</a>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Quiz Results Report</h5>
                <p class="card-text">Analyze detailed results for specific quizzes, including average scores, pass rates, and individual attempts by users. This data can be exported to CSV for further analysis.</p>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="view_quiz_results_report.php" class="btn btn-primary">View Quiz Results Report</a>
            </div>
        </div>
    </div>

    <!-- Add more report cards here as needed -->

</div>

<?php
echo "</div>"; // Close .container from admin_header.php
ob_end_flush();
?>
</body>
</html>
