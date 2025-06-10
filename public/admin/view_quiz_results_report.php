<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
// Models
require_once '../../src/User.php';
require_once '../../src/Module.php';
require_once '../../src/Quiz.php';
require_once '../../src/QuizResult.php';

start_session_if_not_started();
require_admin();

$page_title = "Quiz Results Report";

// Instantiate handlers
$quizHandler = new Quiz($pdo);
$quizResultHandler = new QuizResult($pdo);

$allQuizzes = $quizHandler->getAllQuizzesWithModuleInfo(); // Fetches all quizzes with module title

$selected_quiz_id = null;
$quiz_details = null;
$attempts_details = [];
$average_score = 0;
$pass_rate = 0;

if (isset($_GET['quiz_id']) && filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT)) {
    $selected_quiz_id = (int)$_GET['quiz_id'];
    $quiz_details = $quizHandler->getQuizById($selected_quiz_id);
    if ($quiz_details) {
        $attempts_details = $quizResultHandler->getAllAttemptsDetailsForQuiz($selected_quiz_id); // Method added earlier
        $average_score = $quizResultHandler->getAverageScoreForQuiz($selected_quiz_id);
        $pass_rate = $quizResultHandler->getPassRateForQuiz($selected_quiz_id); // Assumes passing_threshold is handled by QuizResult or is passed
    } else {
        $_SESSION['error_message'] = "Selected quiz not found.";
        $selected_quiz_id = null; // Reset
    }
}

include_once '../../src/includes/admin_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><?php echo $page_title; ?></h1>
    <a href="reports.php" class="btn btn-secondary">&laquo; Back to Reports</a>
</div>

<form action="view_quiz_results_report.php" method="GET" class="mb-4 p-3 border rounded bg-light">
    <div class="row align-items-end">
        <div class="col-md-8">
            <label for="quiz_id" class="form-label fw-bold">Select a Quiz:</label>
            <select name="quiz_id" id="quiz_id" class="form-select form-select-lg">
                <option value="">-- Choose a Quiz --</option>
                <?php foreach ($allQuizzes as $quiz_opt): ?>
                    <option value="<?php echo escape_html($quiz_opt->quiz_id); ?>" <?php if ($selected_quiz_id == $quiz_opt->quiz_id) echo 'selected'; ?>>
                        <?php echo escape_html($quiz_opt->title . " (Module: " . $quiz_opt->module_title . ")"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary btn-lg w-100">View Report</button>
        </div>
    </div>
</form>

<?php if ($selected_quiz_id && $quiz_details): ?>
    <h2 class="mb-3">Report for: <span class="fw-normal fst-italic"><?php echo escape_html($quiz_details->title); ?></span></h2>
    <p class="text-muted">Module: <?php echo escape_html($quiz_details->module_title); ?></p>

    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Average Score</h5>
                    <p class="card-text display-6"><?php echo round($average_score, 2); ?>%</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Pass Rate</h5>
                    <p class="card-text display-6"><?php echo round($pass_rate, 2); ?>%</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
             <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Passing Threshold</h5>
                    <p class="card-text display-6"><?php echo escape_html($quiz_details->passing_threshold); ?>%</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <a href="export_handler.php?report=quiz_results&quiz_id=<?php echo $selected_quiz_id; ?>" class="btn btn-success">
             <i class="fas fa-file-csv me-2"></i>Export These Results to CSV
        </a>
    </div>

    <?php if (empty($attempts_details)): ?>
        <div class="alert alert-info">No attempts found for this quiz yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>User Name</th>
                        <th>Email</th>
                        <th>Attempt #</th>
                        <th>Score (%)</th>
                        <th>Status</th>
                        <th>Attempt Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts_details as $attempt): ?>
                        <tr>
                            <td><?php echo escape_html($attempt->first_name . ' ' . $attempt->last_name); ?></td>
                            <td><?php echo escape_html($attempt->email); ?></td>
                            <td><?php echo escape_html($attempt->attempt_number); ?></td>
                            <td><?php echo escape_html($attempt->score); ?>%</td>
                            <td>
                                <?php if ($attempt->passed): ?>
                                    <span class="badge bg-success">Passed</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape_html($attempt->completed_at ? date('Y-m-d H:i:s', strtotime($attempt->completed_at)) : 'In Progress'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php elseif (isset($_GET['quiz_id']) && $_GET['quiz_id'] !== '' && !$quiz_details) : ?>
    <div class="alert alert-danger">The selected quiz (ID: <?php echo escape_html($_GET['quiz_id']); ?>) could not be found. Please choose a valid quiz from the list.</div>
<?php elseif (!isset($_GET['quiz_id']) || $_GET['quiz_id'] === ''):?>
    <div class="alert alert-info">Please select a quiz from the dropdown above to view its results.</div>
<?php endif; ?>

<?php
echo "</div>"; // Close .container from admin_header.php
ob_end_flush();
?>
</body>
</html>
