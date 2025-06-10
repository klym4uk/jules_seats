<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
require_once '../../src/User.php'; // For require_admin
require_once '../../src/Module.php';
require_once '../../src/Quiz.php';

start_session_if_not_started();
require_admin();

$quizHandler = new Quiz($pdo);
$moduleHandler = new Module($pdo); // For module context

$page_title = "Manage Quizzes";
$current_module_id = null;
$current_module = null;
$quizzes = [];

// Check if accessed for a specific module
if (isset($_GET['module_id']) && filter_var($_GET['module_id'], FILTER_VALIDATE_INT)) {
    $current_module_id = (int)$_GET['module_id'];
    $current_module = $moduleHandler->getModuleById($current_module_id);
    if ($current_module) {
        $page_title = "Manage Quizzes for: " . escape_html($current_module->title);
        $quizzes = $quizHandler->getQuizzesByModuleId($current_module_id);
    } else {
        $_SESSION['error_message'] = "Module not found.";
        redirect('manage_modules.php'); // Or a generic quiz list page if you have one
    }
} else {
    // Display all quizzes if no specific module_id
    $page_title = "All Quizzes";
    $quizzes = $quizHandler->getAllQuizzesWithModuleInfo();
}


// Handling form submissions for Create and Update Quiz
$edit_quiz_data = null;
$form_button_text = 'Create Quiz';
$form_action_url = 'manage_quizzes.php' . ($current_module_id ? '?module_id=' . $current_module_id : '');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { /* ... */ }

    $module_id_form = filter_input(INPUT_POST, 'module_id_form', FILTER_VALIDATE_INT); // Renamed to avoid conflict
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $passing_threshold = filter_input(INPUT_POST, 'passing_threshold', FILTER_VALIDATE_FLOAT);
    $cooldown_period_hours = filter_input(INPUT_POST, 'cooldown_period_hours', FILTER_VALIDATE_INT);

    if (!$module_id_form) {
        $_SESSION['error_message'] = "Module ID is required to create/update a quiz.";
    } elseif ($passing_threshold === false || $passing_threshold < 0 || $passing_threshold > 100) {
        $_SESSION['error_message'] = "Passing threshold must be a number between 0 and 100.";
    } elseif ($cooldown_period_hours === false || $cooldown_period_hours < 0) {
        $_SESSION['error_message'] = "Cooldown period must be a non-negative integer.";
    } else {
        if (isset($_POST['quiz_id']) && !empty($_POST['quiz_id'])) { // UPDATE action
            $quiz_id = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
            if ($quiz_id && $quizHandler->updateQuiz($quiz_id, $title, $description, $passing_threshold, $cooldown_period_hours, $module_id_form)) {
                $_SESSION['message'] = "Quiz updated successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update quiz. Check input or logs.";
            }
        } else { // CREATE action
            $newQuizId = $quizHandler->createQuiz($module_id_form, $title, $description, $passing_threshold, $cooldown_period_hours);
            if ($newQuizId) {
                $_SESSION['message'] = "Quiz created successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to create quiz. Check input or logs.";
            }
        }
    }
    redirect($form_action_url); // Redirect to clear POST and show message
}

// Handling GET requests for Edit or Delete Quiz
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $redirect_url_on_error = 'manage_quizzes.php' . ($current_module_id ? '?module_id=' . $current_module_id : '');

    if (isset($_GET['edit_id'])) {
        $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
        if ($edit_id) {
            $edit_quiz_data = $quizHandler->getQuizById($edit_id); // Fetches with module_title
            if ($edit_quiz_data) {
                $form_button_text = 'Update Quiz';
                // If editing, ensure the form's module_id is set correctly for submission
            } else {
                $_SESSION['error_message'] = "Quiz not found for editing.";
                redirect($redirect_url_on_error);
            }
        }
    } elseif (isset($_GET['delete_id'])) {
        $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
        if ($delete_id) {
            if ($quizHandler->deleteQuiz($delete_id)) {
                $_SESSION['message'] = "Quiz deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to delete quiz. It might have related data or an error occurred.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid quiz ID for deletion.";
        }
        redirect($redirect_url_on_error);
    }
}

// $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$allModulesForDropdown = $moduleHandler->getAllModules(); // For the create/edit form's module selector

include_once '../../src/includes/admin_header.php';
?>

<?php if ($current_module): ?>
    <p><a href="manage_modules.php" class="button-link" style="background-color: #7f8c8d;">&laquo; Back to Modules List</a></p>
    <p><a href="manage_lessons.php?module_id=<?php echo $current_module_id; ?>" class="button-link" style="background-color: #16a085;">Manage Lessons for "<?php echo escape_html($current_module->title); ?>"</a></p>
<?php elseif (!isset($_GET['module_id'])): // Only show "All Modules" if not in specific module view ?>
     <p><a href="manage_modules.php" class="button-link" style="background-color: #7f8c8d;">&laquo; Back to Modules List</a></p>
<?php endif; ?>


<?php if ($edit_quiz_data): ?>
<div class="edit-form-container">
    <h3>Edit Quiz: "<?php echo escape_html($edit_quiz_data->title); ?>" (Module: <?php echo escape_html($edit_quiz_data->module_title); ?>)</h3>
<?php else: ?>
    <h3>Create New Quiz <?php if ($current_module) echo 'for "' . escape_html($current_module->title) . '"'; ?></h3>
<?php endif; ?>

<form action="<?php echo $form_action_url; ?>" method="POST">
    <!-- <input type="hidden" name="csrf_token" value="<?php // echo $_SESSION['csrf_token']; ?>"> -->
    <?php if ($edit_quiz_data): ?>
        <input type="hidden" name="quiz_id" value="<?php echo escape_html($edit_quiz_data->quiz_id); ?>">
    <?php endif; ?>

    <div>
        <label for="module_id_form">Module:</label>
        <select id="module_id_form" name="module_id_form" required>
            <option value="">-- Select Module --</option>
            <?php foreach ($allModulesForDropdown as $module_opt): ?>
                <option value="<?php echo escape_html($module_opt->module_id); ?>"
                    <?php
                    if ($edit_quiz_data && $edit_quiz_data->module_id == $module_opt->module_id) echo 'selected';
                    elseif (!$edit_quiz_data && $current_module_id == $module_opt->module_id) echo 'selected';
                    ?>
                >
                    <?php echo escape_html($module_opt->title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="title">Quiz Title:</label>
        <input type="text" id="title" name="title" value="<?php echo escape_html($edit_quiz_data->title ?? ''); ?>" required>
    </div>
    <div>
        <label for="description">Description (Optional):</label>
        <textarea id="description" name="description" rows="3"><?php echo escape_html($edit_quiz_data->description ?? ''); ?></textarea>
    </div>
    <div>
        <label for="passing_threshold">Passing Threshold (%):</label>
        <input type="number" id="passing_threshold" name="passing_threshold" value="<?php echo escape_html($edit_quiz_data->passing_threshold ?? '70.00'); ?>" required min="0" max="100" step="0.01">
    </div>
    <div>
        <label for="cooldown_period_hours">Cooldown Period (Hours before re-attempt):</label>
        <input type="number" id="cooldown_period_hours" name="cooldown_period_hours" value="<?php echo escape_html($edit_quiz_data->cooldown_period_hours ?? '1'); ?>" required min="0">
    </div>

    <button type="submit"><?php echo $form_button_text; ?></button>
    <?php if ($edit_quiz_data): ?>
        <a href="<?php echo $form_action_url; ?>" class="button-link" style="background-color: #7f8c8d; margin-left:10px;">Cancel Edit</a>
    <?php endif; ?>
</form>
<?php if ($edit_quiz_data || !$current_module_id ) echo '</div>'; // End edit-form-container or general create form container ?>


<h2><?php echo $current_module ? 'Quizzes in "' . escape_html($current_module->title) . '"' : 'All Quizzes'; ?></h2>
<?php if (empty($quizzes)): ?>
    <p>No quizzes found<?php echo $current_module ? ' for this module' : ''; ?>. <?php if (!$edit_quiz_data) echo 'Create one above!';?></p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <?php if (!$current_module_id) echo '<th>Module</th>'; ?>
                <th>Threshold</th>
                <th>Cooldown (hrs)</th>
                <th># Questions</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $questionHandler = new Question($pdo); // For counting questions
            foreach ($quizzes as $quiz):
            $num_questions = $questionHandler->countQuestionsInQuiz($quiz->quiz_id);
            ?>
                <tr>
                    <td><?php echo escape_html($quiz->quiz_id); ?></td>
                    <td><?php echo escape_html($quiz->title); ?></td>
                    <?php if (!$current_module_id) echo '<td>' . escape_html($quiz->module_title ?? $moduleHandler->getModuleById($quiz->module_id)->title ?? 'N/A') . '</td>'; ?>
                    <td><?php echo escape_html($quiz->passing_threshold); ?>%</td>
                    <td><?php echo escape_html($quiz->cooldown_period_hours); ?></td>
                    <td><?php echo $num_questions; ?></td>
                    <td class="action-links">
                        <a href="manage_questions.php?quiz_id=<?php echo escape_html($quiz->quiz_id); ?>" class="button-link manage">Manage Questions</a>
                        <a href="manage_quizzes.php?<?php echo ($current_module_id ? 'module_id='.$current_module_id.'&' : ''); ?>edit_id=<?php echo escape_html($quiz->quiz_id); ?>" class="button-link edit">Edit</a>
                        <a href="manage_quizzes.php?<?php echo ($current_module_id ? 'module_id='.$current_module_id.'&' : ''); ?>delete_id=<?php echo escape_html($quiz->quiz_id); ?>"
                           class="button-link delete"
                           onclick="return confirm('Are you sure you want to delete this quiz? This may also delete related questions and answers.');">Delete</a>
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
