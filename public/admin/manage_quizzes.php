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
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><?php echo $current_module ? 'Quizzes for: <em>' . escape_html($current_module->title) . '</em>' : 'Manage All Quizzes'; ?></h1>
    <div>
    <?php if ($current_module): ?>
        <a href="manage_lessons.php?module_id=<?php echo $current_module_id; ?>" class="btn btn-outline-info">Manage Lessons</a>
        <a href="manage_modules.php" class="btn btn-secondary ms-2">&laquo; Back to Modules</a>
    <?php else: ?>
        <a href="manage_modules.php" class="btn btn-secondary">&laquo; Back to Modules</a>
    <?php endif; ?>
    </div>
</div>


<div class="card <?php echo $edit_quiz_data ? 'border-primary' : 'border-secondary'; ?> mb-4">
    <div class="card-header">
        <h3 class="mb-0"><?php echo $edit_quiz_data ? 'Edit Quiz: ' . escape_html($edit_quiz_data->title) : 'Create New Quiz'; ?></h3>
    </div>
    <div class="card-body">
        <form action="<?php echo $form_action_url; ?>" method="POST">
            <?php if ($edit_quiz_data): ?>
                <input type="hidden" name="quiz_id" value="<?php echo escape_html($edit_quiz_data->quiz_id); ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="module_id_form" class="form-label">Module:</label>
                <select class="form-select" id="module_id_form" name="module_id_form" required <?php if($current_module_id && !$edit_quiz_data) echo 'disabled'; ?>>
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
                 <?php if($current_module_id && !$edit_quiz_data): // If creating for specific module, pass it as hidden field if disabled select not submitted ?>
                    <input type="hidden" name="module_id_form" value="<?php echo $current_module_id; ?>" />
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Quiz Title:</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo escape_html($edit_quiz_data->title ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description (Optional):</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php echo escape_html($edit_quiz_data->description ?? ''); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="passing_threshold" class="form-label">Passing Threshold (%):</label>
                    <input type="number" class="form-control" id="passing_threshold" name="passing_threshold" value="<?php echo escape_html($edit_quiz_data->passing_threshold ?? '70.00'); ?>" required min="0" max="100" step="0.01">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cooldown_period_hours" class="form-label">Cooldown Period (Hours):</label>
                    <input type="number" class="form-control" id="cooldown_period_hours" name="cooldown_period_hours" value="<?php echo escape_html($edit_quiz_data->cooldown_period_hours ?? '1'); ?>" required min="0">
                </div>
            </div>

            <button type="submit" class="btn <?php echo $edit_quiz_data ? 'btn-primary' : 'btn-success'; ?>"><?php echo $form_button_text; ?></button>
            <?php if ($edit_quiz_data): ?>
                <a href="<?php echo $form_action_url; ?>" class="btn btn-secondary ms-2">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>


<h2 class="mt-4 mb-3"><?php echo $current_module ? 'Quizzes in "' . escape_html($current_module->title) . '"' : 'All Quizzes'; ?></h2>
<?php if (empty($quizzes)): ?>
    <div class="alert alert-info">No quizzes found<?php echo $current_module ? ' for this module' : ''; ?>. <?php if (!$edit_quiz_data && ($current_module_id || empty($allModulesForDropdown))) echo 'Create one above!';?></div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <?php if (!$current_module_id) echo '<th>Module</th>'; ?>
                    <th>Threshold</th>
                    <th>Cooldown</th>
                    <th># Questions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $questionHandler = new Question($pdo);
                foreach ($quizzes as $quiz):
                $num_questions = $questionHandler->countQuestionsInQuiz($quiz->quiz_id);
                ?>
                    <tr>
                        <td><?php echo escape_html($quiz->quiz_id); ?></td>
                        <td><?php echo escape_html($quiz->title); ?></td>
                        <?php if (!$current_module_id) echo '<td><small>' . escape_html($quiz->module_title ?? $moduleHandler->getModuleById($quiz->module_id)->title ?? 'N/A') . '</small></td>'; ?>
                        <td><?php echo escape_html($quiz->passing_threshold); ?>%</td>
                        <td><?php echo escape_html($quiz->cooldown_period_hours); ?> hrs</td>
                        <td><?php echo $num_questions; ?></td>
                        <td>
                            <a href="manage_questions.php?quiz_id=<?php echo escape_html($quiz->quiz_id); ?>" class="btn btn-sm btn-info mb-1">Questions</a>
                            <a href="manage_quizzes.php?<?php echo ($current_module_id ? 'module_id='.$current_module_id.'&' : ''); ?>edit_id=<?php echo escape_html($quiz->quiz_id); ?>" class="btn btn-sm btn-warning mb-1">Edit</a>
                            <a href="manage_quizzes.php?<?php echo ($current_module_id ? 'module_id='.$current_module_id.'&' : ''); ?>delete_id=<?php echo escape_html($quiz->quiz_id); ?>"
                               class="btn btn-sm btn-danger mb-1"
                               onclick="return confirm('Are you sure you want to delete this quiz? This may also delete related questions and answers.');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
echo "</div>"; // Close .container from admin_header.php
ob_end_flush();
?>
</body>
</html>
