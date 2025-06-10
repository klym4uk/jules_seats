<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
// Models
require_once '../../src/User.php'; // Not directly used but good for context
require_once '../../src/Module.php';
require_once '../../src/Quiz.php';
require_once '../../src/QuizResult.php';

start_session_if_not_started();
require_admin();

$page_title = "Module Progress Overview";

// Instantiate handlers
$moduleHandler = new Module($pdo);
$quizHandler = new Quiz($pdo);
$quizResultHandler = new QuizResult($pdo);

$allModules = $moduleHandler->getAllModules(); // Get all modules (active or not for this overview)

include_once '../../src/includes/admin_header.php';
?>

<h1><?php echo $page_title; ?></h1>

<?php if (empty($allModules)): ?>
    <div class="message info">No modules found in the system.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Module Title</th>
                <th>Status</th>
                <th>Associated Quiz</th>
                <th>Avg. Quiz Score</th>
                <th>Quiz Pass Rate</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allModules as $module): ?>
                <?php
                    $avgScore = 'N/A';
                    $passRate = 'N/A';
                    $quizTitle = 'N/A';
                    $quiz_id_for_module = null;

                    // Find the quiz for this module (assuming one quiz per module for simplicity)
                    $quizzesForModule = $quizHandler->getQuizzesByModuleId($module->module_id);
                    if (!empty($quizzesForModule)) {
                        $quizForModule = $quizzesForModule[0]; // Take the first one
                        $quiz_id_for_module = $quizForModule->quiz_id;
                        $quizTitle = $quizForModule->title;

                        $_avgScore = $quizResultHandler->getAverageScoreForQuiz($quiz_id_for_module);
                        if ($_avgScore !== false) {
                             $avgScore = round($_avgScore, 2) . '%';
                        }

                        $_passRate = $quizResultHandler->getPassRateForQuiz($quiz_id_for_module);
                         if ($_passRate !== false) {
                            $passRate = round($_passRate, 2) . '%';
                        }
                    }
                ?>
                <tr>
                    <td><?php echo escape_html($module->title); ?></td>
                    <td><span class="status-badge status-<?php echo escape_html(strtolower($module->status)); ?>"><?php echo escape_html(ucfirst($module->status)); ?></span></td>
                    <td><?php echo escape_html($quizTitle); ?></td>
                    <td><?php echo $avgScore; ?></td>
                    <td><?php echo $passRate; ?></td>
                    <td>
                        <?php if ($quiz_id_for_module): // Only show link if there's a quiz ?>
                        <a href="view_module_progress_detail.php?module_id=<?php echo $module->module_id; ?>" class="button-link">View Details</a>
                        <?php else: ?>
                        No Quiz Associated
                        <?php endif; ?>
                    </td>
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
