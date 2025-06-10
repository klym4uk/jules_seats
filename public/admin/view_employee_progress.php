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

// Validate user_id from GET parameter
if (!isset($_GET['user_id']) || !filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid user ID specified.";
    redirect('user_progress_overview.php');
}
$employee_user_id = (int)$_GET['user_id'];

// Instantiate handlers
$userHandler = new User($pdo);
$moduleHandler = new Module($pdo); // To get all module titles, even if no progress yet
$quizHandler = new Quiz($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo);
$quizResultHandler = new QuizResult($pdo);

$employee_details = $userHandler->findById($employee_user_id);

if (!$employee_details || $employee_details->role !== 'Employee') {
    $_SESSION['error_message'] = "Employee not found or selected user is not an Employee.";
    redirect('user_progress_overview.php');
}

$page_title = "Progress for " . escape_html($employee_details->first_name . ' ' . $employee_details->last_name);

// Fetch all module progress for the user
$userModulesProgress = $userModuleProgressHandler->getAllModulesProgressForUser($employee_user_id);
$allSystemModules = $moduleHandler->getAllModules(); // Get all modules in the system

// Create a map of module_id to its progress for easier lookup
$progressMap = [];
foreach($userModulesProgress as $p) {
    $progressMap[$p->module_id] = $p;
}

include_once '../../src/includes/admin_header.php';
?>

<p><a href="user_progress_overview.php" class="button-link secondary">&laquo; Back to User Progress Overview</a></p>
<h1><?php echo $page_title; ?></h1>
<p><strong>Email:</strong> <?php echo escape_html($employee_details->email); ?></p>


<?php if (empty($allSystemModules)): ?>
    <div class="message info">No modules exist in the system yet.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Module Title</th>
                <th>Status</th>
                <th>Completion Date</th>
                <th>Latest Quiz Score</th>
                <th>Quiz Attempts</th>
                <!--<th>Actions (e.g., View Detailed Attempts)</th>-->
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allSystemModules as $module): ?>
                <?php
                    $progress = $progressMap[$module->module_id] ?? null;
                    $status = $progress ? $progress->status : 'not_started';
                    $completionDate = ($progress && $progress->completion_date) ? date('Y-m-d', strtotime($progress->completion_date)) : 'N/A';

                    $latestScore = 'N/A';
                    $attemptsCount = 0;

                    $quizzesForModule = $quizHandler->getQuizzesByModuleId($module->module_id);
                    if (!empty($quizzesForModule)) {
                        $quizForModule = $quizzesForModule[0]; // Assuming one quiz
                        $latestAttempt = $quizResultHandler->getLatestAttempt($employee_user_id, $quizForModule->quiz_id);
                        if ($latestAttempt && $latestAttempt->completed_at) {
                            $latestScore = $latestAttempt->score . '%';
                        }
                        $allAttemptsForThisQuiz = $quizResultHandler->getAllAttemptsForUserQuiz($employee_user_id, $quizForModule->quiz_id);
                        $attemptsCount = count($allAttemptsForThisQuiz);
                    }
                ?>
                <tr>
                    <td><?php echo escape_html($module->title); ?> (<?php echo escape_html($module->status);?>)</td>
                    <td><span class="status-badge status-<?php echo escape_html(strtolower($status)); ?>"><?php echo escape_html(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                    <td><?php echo $completionDate; ?></td>
                    <td><?php echo $latestScore; ?></td>
                    <td><?php echo $attemptsCount; ?></td>
                    <!--<td><?php // if ($attemptsCount > 0) echo '<a href="#" class="button-link">View Attempts</a>'; ?></td>-->
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
