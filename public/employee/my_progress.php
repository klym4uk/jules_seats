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
require_login('Employee');

$user_id = $_SESSION['user_id'];
$page_title = "My Progress Overview";

// Instantiate handlers
$moduleHandler = new Module($pdo);
$quizHandler = new Quiz($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo);
$quizResultHandler = new QuizResult($pdo);

// Fetch all module progress for the user
$userModulesProgress = $userModuleProgressHandler->getAllModulesProgressForUser($user_id);

// Fetch all active modules for overall progress calculation
$allActiveModules = $moduleHandler->getAllModules(true); // true for activeOnly
$totalActiveModulesCount = count($allActiveModules);
$passedModulesCount = 0;

// Create a map of module_id to its progress for easier lookup
$progressMap = [];
foreach($userModulesProgress as $p) {
    $progressMap[$p->module_id] = $p;
    if ($p->status === 'passed') {
        $passedModulesCount++;
    }
}

// Calculate overall progress percentage
$overallProgressPercentage = 0;
if ($totalActiveModulesCount > 0) {
    // Consider only active modules the user has 'passed' against total active modules
    // This definition might need refinement: e.g. should it be based on modules user started?
    // For now: (passed modules / total *active* modules in system)
    $userPassedActiveModulesCount = 0;
    foreach($allActiveModules as $activeMod) {
        if(isset($progressMap[$activeMod->module_id]) && $progressMap[$activeMod->module_id]->status === 'passed') {
            $userPassedActiveModulesCount++;
        }
    }
    $overallProgressPercentage = round(($userPassedActiveModulesCount / $totalActiveModulesCount) * 100, 2);
}


include_once '../../src/includes/employee_header.php';
?>

<h1><?php echo $page_title; ?></h1>

<div style="margin-bottom: 20px; padding: 15px; background-color: #e9ecef; border-radius: 5px;">
    <h2>Overall Training Progress: <?php echo $overallProgressPercentage; ?>%</h2>
    <p>(Based on <?php echo $userPassedActiveModulesCount; ?> passed modules out of <?php echo $totalActiveModulesCount; ?> total active modules in the system)</p>
</div>

<?php if (empty($userModulesProgress) && empty($allActiveModules)): ?>
    <div class="message info">No modules are currently assigned or available, or you haven't started any progress.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Module Title</th>
                <th>Status</th>
                <th>Completion Date</th>
                <th>Latest Quiz Score</th>
                <th>Quiz Attempts</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Display progress for modules user has interacted with, or all active modules
            $displayedModules = []; // Keep track of displayed modules to avoid duplicates if logic changes

            // First, iterate through modules the user has progress on
            foreach ($userModulesProgress as $progress):
                $displayedModules[$progress->module_id] = true;
                $moduleTitle = $progress->module_title ?? 'N/A'; // module_title comes from getAllModulesProgressForUser
                $status = $progress->status ?? 'not_started';
                $completionDate = $progress->completion_date ? date('Y-m-d', strtotime($progress->completion_date)) : 'N/A';

                $latestScore = 'N/A';
                $attemptsCount = 0;

                // Find the quiz for this module (assuming one quiz per module for simplicity)
                $quizzesForModule = $quizHandler->getQuizzesByModuleId($progress->module_id);
                if (!empty($quizzesForModule)) {
                    $quizForModule = $quizzesForModule[0]; // Take the first one
                    $latestAttempt = $quizResultHandler->getLatestAttempt($user_id, $quizForModule->quiz_id);
                    if ($latestAttempt && $latestAttempt->completed_at) { // Ensure quiz was completed
                        $latestScore = $latestAttempt->score . '%';
                    }
                    $allAttemptsForThisQuiz = $quizResultHandler->getAllAttemptsForUserQuiz($user_id, $quizForModule->quiz_id);
                    $attemptsCount = count($allAttemptsForThisQuiz);
                }
            ?>
            <tr>
                <td><?php echo escape_html($moduleTitle); ?></td>
                <td><span class="status-badge status-<?php echo escape_html(strtolower($status)); ?>"><?php echo escape_html(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                <td><?php echo $completionDate; ?></td>
                <td><?php echo $latestScore; ?></td>
                <td><?php echo $attemptsCount; ?></td>
                <td>
                    <a href="view_module.php?module_id=<?php echo $progress->module_id; ?>" class="e-button">View Module</a>
                    <?php if (!empty($quizzesForModule) && ($status === 'quiz_available' || $status === 'failed' || $status === 'passed' || $status === 'quiz_in_progress' )): ?>
                        <a href="take_quiz.php?quiz_id=<?php echo $quizzesForModule[0]->quiz_id; ?>" class="e-button secondary" style="margin-left:5px;">
                            <?php echo ($status === 'passed' || $status === 'failed') ? 'View/Retake Quiz' : 'Go to Quiz'; ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php
            // Optionally, list active modules the user hasn't started yet
            foreach ($allActiveModules as $activeModule):
                if (!isset($displayedModules[$activeModule->module_id])):
            ?>
            <tr>
                <td><?php echo escape_html($activeModule->title); ?></td>
                <td><span class="status-badge status-not_started">Not Started</span></td>
                <td>N/A</td>
                <td>N/A</td>
                <td>0</td>
                <td>
                    <a href="view_module.php?module_id=<?php echo $activeModule->module_id; ?>" class="e-button">View Module</a>
                </td>
            </tr>
            <?php
                endif;
            endforeach;
            ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
echo "</main>"; // Close main.e-container from header
ob_end_flush();
?>
</body>
</html>
