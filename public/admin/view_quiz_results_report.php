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

<p><a href="reports.php" class="button-link secondary">&laquo; Back to Reports</a></p>
<h1><?php echo $page_title; ?></h1>

<form action="view_quiz_results_report.php" method="GET" style="margin-bottom: 20px;">
    <label for="quiz_id" style="font-weight: bold;">Select a Quiz:</label>
    <select name="quiz_id" id="quiz_id" onchange="this.form.submit()" style="padding: 8px; margin-right: 10px; border-radius: 4px;">
        <option value="">-- Choose a Quiz --</option>
        <?php foreach ($allQuizzes as $quiz_opt): ?>
            <option value="<?php echo escape_html($quiz_opt->quiz_id); ?>" <?php if ($selected_quiz_id == $quiz_opt->quiz_id) echo 'selected'; ?>>
                <?php echo escape_html($quiz_opt->title . " (Module: " . $quiz_opt->module_title . ")"); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit">View Report</button></noscript>
</form>

<?php if ($selected_quiz_id && $quiz_details): ?>
    <h2>Report for: <?php echo escape_html($quiz_details->title); ?></h2>
    <p>Module: <?php echo escape_html($quiz_details->module_title); ?></p>

    <div style="margin-bottom: 20px; display:flex; gap: 20px;">
        <div class="stat-card" style="background-color: #f0f0f0; padding: 15px; border-radius: 5px; text-align:center;">
            <strong>Average Score:</strong> <?php echo round($average_score, 2); ?>%
        </div>
        <div class="stat-card" style="background-color: #f0f0f0; padding: 15px; border-radius: 5px; text-align:center;">
            <strong>Pass Rate:</strong> <?php echo round($pass_rate, 2); ?>%
            (Threshold: <?php echo escape_html($quiz_details->passing_threshold); ?>%)
        </div>
    </div>

    <p style="margin-bottom: 20px;">
        <a href="export_handler.php?report=quiz_results&quiz_id=<?php echo $selected_quiz_id; ?>" class="button-link success">Export These Results to CSV</a>
    </p>

    <?php if (empty($attempts_details)): ?>
        <div class="message info">No attempts found for this quiz yet.</div>
    <?php else: ?>
        <table>
            <thead>
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
                                <span style="color: green;">Passed</span>
                            <?php else: ?>
                                <span style="color: red;">Failed</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo escape_html($attempt->completed_at ? date('Y-m-d H:i:s', strtotime($attempt->completed_at)) : 'In Progress'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php elseif (isset($_GET['quiz_id']) && !$quiz_details) : // if quiz_id was in URL but not found?>
    <div class="message error">The selected quiz (ID: <?php echo escape_html($_GET['quiz_id']); ?>) could not be found. Please choose a valid quiz from the list.</div>
<?php else: ?>
    <p class="message info">Please select a quiz from the dropdown above to view its results.</p>
<?php endif; ?>

<?php
echo "</main>"; // Close main.container from header
ob_end_flush();
?>
</body>
</html>
