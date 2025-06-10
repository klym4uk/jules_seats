<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
// Models
require_once '../../src/User.php';
require_once '../../src/Module.php';
require_once '../../src/Quiz.php';
require_once '../../src/QuizResult.php';
require_once '../../src/UserModuleProgress.php';

start_session_if_not_started();
require_admin();

// Validate module_id from GET parameter
if (!isset($_GET['module_id']) || !filter_var($_GET['module_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid module ID specified.";
    redirect('module_progress_overview.php');
}
$module_id_for_detail = (int)$_GET['module_id'];

// Instantiate handlers
$moduleHandler = new Module($pdo);
$quizHandler = new Quiz($pdo);
$quizResultHandler = new QuizResult($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo);
// User handler not strictly needed here unless we want to fetch all users first

$module_details = $moduleHandler->getModuleById($module_id_for_detail);

if (!$module_details) {
    $_SESSION['error_message'] = "Module not found.";
    redirect('module_progress_overview.php');
}

$page_title = "Detailed Progress for: " . escape_html($module_details->title);

// Fetch the associated quiz (assuming one for simplicity)
$quiz_for_module = null;
$quizzesForModuleList = $quizHandler->getQuizzesByModuleId($module_id_for_detail);
if (!empty($quizzesForModuleList)) {
    $quiz_for_module = $quizzesForModuleList[0];
}

// Fetch all users' progress for this module
$users_progress_for_module = $userModuleProgressHandler->getUsersProgressForModule($module_id_for_detail);

include_once '../../src/includes/admin_header.php';
?>

<p><a href="module_progress_overview.php" class="button-link secondary">&laquo; Back to Module Progress Overview</a></p>
<h1><?php echo $page_title; ?></h1>
<p><strong>Module Status:</strong> <?php echo escape_html(ucfirst($module_details->status)); ?></p>
<?php if ($quiz_for_module): ?>
    <p><strong>Associated Quiz:</strong> <?php echo escape_html($quiz_for_module->title); ?></p>
<?php else: ?>
    <p><strong>Associated Quiz:</strong> None</p>
<?php endif; ?>


<?php if (empty($users_progress_for_module)): ?>
    <div class="message info">No users have interacted with this module yet.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>User Name</th>
                <th>Email</th>
                <th>Module Status</th>
                <?php if ($quiz_for_module): ?>
                <th>Latest Quiz Score</th>
                <th>Quiz Attempts</th>
                <th>Last Attempt Date</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users_progress_for_module as $user_progress): ?>
                <?php
                    $latestScore = 'N/A';
                    $attemptsCount = 0;
                    $lastAttemptDate = 'N/A';

                    if ($quiz_for_module) {
                        $latestAttempt = $quizResultHandler->getLatestAttempt($user_progress->user_id, $quiz_for_module->quiz_id);
                        if ($latestAttempt && $latestAttempt->completed_at) {
                            $latestScore = $latestAttempt->score . '%';
                            $lastAttemptDate = date('Y-m-d H:i', strtotime($latestAttempt->completed_at));
                        }
                        $allAttempts = $quizResultHandler->getAllAttemptsForUserQuiz($user_progress->user_id, $quiz_for_module->quiz_id);
                        $attemptsCount = count($allAttempts);
                    }
                ?>
                <tr>
                    <td><?php echo escape_html($user_progress->first_name . ' ' . $user_progress->last_name); ?></td>
                    <td><?php echo escape_html($user_progress->email); ?></td>
                    <td><span class="status-badge status-<?php echo escape_html(strtolower($user_progress->status)); ?>"><?php echo escape_html(ucwords(str_replace('_', ' ', $user_progress->status))); ?></span></td>
                    <?php if ($quiz_for_module): ?>
                    <td><?php echo $latestScore; ?></td>
                    <td><?php echo $attemptsCount; ?></td>
                    <td><?php echo $lastAttemptDate; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
echo "</main>"; // Close main.container from header
ob_end_flush();
?>
</body>
</html>
