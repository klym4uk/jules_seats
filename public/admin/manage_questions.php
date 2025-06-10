<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
require_once '../../src/User.php'; // For require_admin
require_once '../../src/Module.php'; // For breadcrumbs/context
require_once '../../src/Quiz.php';
require_once '../../src/Question.php';
require_once '../../src/Answer.php';

start_session_if_not_started();
require_admin();

$quizHandler = new Quiz($pdo);
$questionHandler = new Question($pdo);
$answerHandler = new Answer($pdo);
$moduleHandler = new Module($pdo); // For breadcrumbs

$page_title = "Manage Questions & Answers";

// Validate quiz_id from GET parameter
if (!isset($_GET['quiz_id']) || !filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid or missing Quiz ID.";
    redirect('manage_modules.php'); // Or a general quiz list page
}
$current_quiz_id = (int)$_GET['quiz_id'];
$current_quiz = $quizHandler->getQuizById($current_quiz_id);

if (!$current_quiz) {
    $_SESSION['error_message'] = "Quiz not found.";
    // Try to find module context for redirect, or redirect to general module list
    $fallback_module_id = $quizHandler->getQuizById($current_quiz_id)->module_id ?? null; // Attempt to get module_id if quiz existed
    if ($fallback_module_id) {
        redirect('manage_quizzes.php?module_id=' . $fallback_module_id);
    } else {
        redirect('manage_modules.php');
    }
}
$current_module = $moduleHandler->getModuleById($current_quiz->module_id); // For breadcrumbs
$page_title = "Questions for: " . escape_html($current_quiz->title);

// --- Form & Action Handling ---
$edit_question_data = null;
$edit_answer_data = null;
$managing_answers_for_question_id = null; // ID of question whose answers are being managed

// Determine current action (add/edit question, add/edit answer, set correct answer)
$action = $_POST['action'] ?? $_GET['action'] ?? 'view';

// --- Question Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    // CSRF
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { /* ... */ }

    if ($_POST['form_type'] === 'question') {
        $question_text = trim($_POST['question_text']);
        $question_type = trim($_POST['question_type']); // Currently 'single_choice'
        $order_in_quiz = filter_input(INPUT_POST, 'order_in_quiz', FILTER_VALIDATE_INT);

        if ($order_in_quiz === false || $order_in_quiz < 0) {
            $_SESSION['error_message'] = "Order in quiz must be a non-negative integer.";
        } else {
            if (isset($_POST['question_id']) && !empty($_POST['question_id'])) { // UPDATE Question
                $question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
                if ($question_id && $questionHandler->updateQuestion($question_id, $question_text, $question_type, $order_in_quiz)) {
                    $_SESSION['message'] = "Question updated successfully!";
                } else {
                    $_SESSION['error_message'] = "Failed to update question.";
                }
            } else { // CREATE Question
                if ($questionHandler->createQuestion($current_quiz_id, $question_text, $question_type, $order_in_quiz)) {
                    $_SESSION['message'] = "Question created successfully!";
                } else {
                    $_SESSION['error_message'] = "Failed to create question.";
                }
            }
        }
        redirect("manage_questions.php?quiz_id=" . $current_quiz_id);
    }
    // --- Answer Actions ---
    elseif ($_POST['form_type'] === 'answer' && isset($_POST['question_id_for_answer'])) {
        $question_id_for_answer = filter_input(INPUT_POST, 'question_id_for_answer', FILTER_VALIDATE_INT);
        $answer_text = trim($_POST['answer_text']);
        // For 'single_choice', is_correct is handled by 'set_correct_answer' action.
        // For 'multiple_choice' (future), $is_correct = isset($_POST['is_correct']);

        if (isset($_POST['answer_id']) && !empty($_POST['answer_id'])) { // UPDATE Answer
            $answer_id = filter_input(INPUT_POST, 'answer_id', FILTER_VALIDATE_INT);
            $is_correct_update = isset($_POST['is_correct_existing_radio']) && $_POST['is_correct_existing_radio'] == $answer_id;

            // If we are directly updating an answer AND setting it correct for single_choice
            $current_question_for_answer = $questionHandler->getQuestionById($question_id_for_answer);
            if ($current_question_for_answer && $current_question_for_answer->question_type === 'single_choice' && $is_correct_update) {
                if ($answerHandler->setCorrectAnswerForQuestion($question_id_for_answer, $answer_id)) {
                     // Also update text if needed
                    if ($answerHandler->updateAnswer($answer_id, $answer_text, true)) { // true for is_correct
                         $_SESSION['message'] = "Answer updated and set as correct!";
                    } else {
                         $_SESSION['error_message'] = "Failed to update answer text after setting correct.";
                    }
                } else {
                     $_SESSION['error_message'] = "Failed to set answer as correct.";
                }
            } else { // Just update text, correctness is handled by radio for single_choice or checkbox for multiple_choice
                 if ($answerHandler->updateAnswer($answer_id, $answer_text, $is_correct_update)) { // $is_correct_update might be false here
                    $_SESSION['message'] = "Answer text updated!";
                } else {
                    $_SESSION['error_message'] = "Failed to update answer.";
                }
            }

        } else { // CREATE Answer
            // For single_choice, new answers are initially not correct. Correctness is set by a separate action.
            $newAnswerId = $answerHandler->createAnswer($question_id_for_answer, $answer_text, false);
            if ($newAnswerId) {
                $_SESSION['message'] = "Answer added successfully.";
                 // If 'set_correct_new_answer' was checked for this new answer.
                if (isset($_POST['set_correct_new_answer']) && $_POST['set_correct_new_answer'] == 'yes') {
                    $current_question_for_answer = $questionHandler->getQuestionById($question_id_for_answer);
                    if ($current_question_for_answer && $current_question_for_answer->question_type === 'single_choice') {
                        if ($answerHandler->setCorrectAnswerForQuestion($question_id_for_answer, $newAnswerId)) {
                             $_SESSION['message'] .= " And set as correct.";
                        } else {
                             $_SESSION['error_message'] .= " Failed to set new answer as correct.";
                        }
                    }
                }
            } else {
                $_SESSION['error_message'] = "Failed to add answer.";
            }
        }
        redirect("manage_questions.php?quiz_id=" . $current_quiz_id . "&action=manage_answers&question_id=" . $question_id_for_answer);

    } elseif ($_POST['form_type'] === 'set_correct_answer' && isset($_POST['question_id_for_correct_answer'])) {
        $question_id_for_correct = filter_input(INPUT_POST, 'question_id_for_correct_answer', FILTER_VALIDATE_INT);
        $correct_answer_id = filter_input(INPUT_POST, 'correct_answer_id_radio', FILTER_VALIDATE_INT);

        $current_question_for_correct = $questionHandler->getQuestionById($question_id_for_correct);

        if ($current_question_for_correct && $current_question_for_correct->question_type === 'single_choice') {
            if ($correct_answer_id && $answerHandler->setCorrectAnswerForQuestion($question_id_for_correct, $correct_answer_id)) {
                $_SESSION['message'] = "Correct answer updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to set correct answer. Ensure an answer is selected.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid operation for this question type or question not found.";
        }
        redirect("manage_questions.php?quiz_id=" . $current_quiz_id . "&action=manage_answers&question_id=" . $question_id_for_correct);
    }
}


// --- GET Actions for Edit/Delete Question, Manage Answers ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'view'; // Default action

    if ($action === 'edit_question' && isset($_GET['question_id'])) {
        $edit_question_id = filter_input(INPUT_GET, 'question_id', FILTER_VALIDATE_INT);
        if ($edit_question_id) $edit_question_data = $questionHandler->getQuestionById($edit_question_id);
        if (!$edit_question_data || $edit_question_data->quiz_id != $current_quiz_id) {
            $_SESSION['error_message'] = "Question not found for editing.";
            redirect("manage_questions.php?quiz_id=" . $current_quiz_id);
        }
    } elseif ($action === 'delete_question' && isset($_GET['question_id'])) {
        $delete_question_id = filter_input(INPUT_GET, 'question_id', FILTER_VALIDATE_INT);
        // Ensure question belongs to quiz before deleting? (Handled by cascade mostly)
        if ($delete_question_id && $questionHandler->deleteQuestion($delete_question_id)) {
            $_SESSION['message'] = "Question deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete question.";
        }
        redirect("manage_questions.php?quiz_id=" . $current_quiz_id);
    } elseif ($action === 'manage_answers' && isset($_GET['question_id'])) {
        $managing_answers_for_question_id = filter_input(INPUT_GET, 'question_id', FILTER_VALIDATE_INT);
        $edit_question_data = $questionHandler->getQuestionById($managing_answers_for_question_id); // Load question being managed
        if (!$edit_question_data || $edit_question_data->quiz_id != $current_quiz_id) {
            $_SESSION['error_message'] = "Question not found for answer management.";
            $managing_answers_for_question_id = null; // Reset
            // redirect("manage_questions.php?quiz_id=" . $current_quiz_id); // Avoid too many redirects
        }
    } elseif ($action === 'edit_answer' && isset($_GET['answer_id']) && isset($_GET['question_id'])) {
        $edit_answer_id = filter_input(INPUT_GET, 'answer_id', FILTER_VALIDATE_INT);
        $managing_answers_for_question_id = filter_input(INPUT_GET, 'question_id', FILTER_VALIDATE_INT); // Keep context
        $edit_question_data = $questionHandler->getQuestionById($managing_answers_for_question_id); // For context
        if ($edit_answer_id) $edit_answer_data = $answerHandler->getAnswerById($edit_answer_id);

        if (!$edit_answer_data || $edit_answer_data->question_id != $managing_answers_for_question_id) {
            $_SESSION['error_message'] = "Answer not found for editing.";
            redirect("manage_questions.php?quiz_id=" . $current_quiz_id . "&action=manage_answers&question_id=" . $managing_answers_for_question_id);
        }
    } elseif ($action === 'delete_answer' && isset($_GET['answer_id']) && isset($_GET['question_id'])) {
        $delete_answer_id = filter_input(INPUT_GET, 'answer_id', FILTER_VALIDATE_INT);
        $question_id_context = filter_input(INPUT_GET, 'question_id', FILTER_VALIDATE_INT);
        // Ensure answer belongs to question? (Handled by cascade mostly)
        if ($delete_answer_id && $answerHandler->deleteAnswer($delete_answer_id)) {
            $_SESSION['message'] = "Answer deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete answer.";
        }
        redirect("manage_questions.php?quiz_id=" . $current_quiz_id . "&action=manage_answers&question_id=" . $question_id_context);
    }
}

// $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$questionsForQuiz = $questionHandler->getQuestionsByQuizId($current_quiz_id);

include_once '../../src/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">Questions for: <em><?php echo escape_html($current_quiz->title); ?></em></h1>
    <a href="manage_quizzes.php?module_id=<?php echo $current_quiz->module_id; ?>" class="btn btn-secondary">&laquo; Back to Quizzes in "<?php echo escape_html($current_module->title); ?>"</a>
</div>


<!-- Question Create/Edit Form -->
<div class="card mb-4 <?php if ($managing_answers_for_question_id && !$edit_question_data) echo 'd-none'; else echo ($edit_question_data && !$managing_answers_for_question_id ? 'border-primary' : 'border-secondary'); ?>" id="question_form_container">
    <div class="card-header">
        <h3 class="mb-0"><?php echo $edit_question_data && !$managing_answers_for_question_id ? 'Edit Question' : 'Add New Question'; ?></h3>
    </div>
    <div class="card-body">
        <form action="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>" method="POST">
            <input type="hidden" name="form_type" value="question">
            <?php if ($edit_question_data && !$managing_answers_for_question_id): ?>
                <input type="hidden" name="question_id" value="<?php echo escape_html($edit_question_data->question_id); ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="question_text" class="form-label">Question Text:</label>
                <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?php echo escape_html($edit_question_data->question_text ?? ''); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="order_in_quiz" class="form-label">Order in Quiz:</label>
                    <input type="number" class="form-control" id="order_in_quiz" name="order_in_quiz" value="<?php echo escape_html($edit_question_data->order_in_quiz ?? count($questionsForQuiz)); ?>" required min="0">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="question_type" class="form-label">Question Type:</label>
                    <select class="form-select" id="question_type" name="question_type">
                        <option value="single_choice" <?php echo (isset($edit_question_data->question_type) && $edit_question_data->question_type == 'single_choice') ? 'selected' : ''; ?>>Single Choice</option>
                        <!-- Multiple choice UI for answers would need more work -->
                    </select>
                </div>
            </div>
            <button type="submit" class="btn <?php echo ($edit_question_data && !$managing_answers_for_question_id) ? 'btn-primary' : 'btn-success'; ?>"><?php echo $edit_question_data && !$managing_answers_for_question_id ? 'Update Question' : 'Create Question'; ?></button>
            <?php if ($edit_question_data && !$managing_answers_for_question_id): ?>
                 <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>" class="btn btn-secondary ms-2">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>


<!-- List Existing Questions -->
<h2 class="mt-4 mb-3">Questions in "<?php echo escape_html($current_quiz->title); ?>"</h2>
<?php if (empty($questionsForQuiz)): ?>
    <div class="alert alert-info">No questions yet for this quiz. Add one above.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Order</th>
                    <th>Question Text</th>
                    <th>Type</th>
                    <th>Answers</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questionsForQuiz as $question):
                    $answers_for_this_question = $answerHandler->getAnswersByQuestionId($question->question_id);
                ?>
                    <tr class="<?php if ($managing_answers_for_question_id == $question->question_id) echo 'table-info'; ?>">
                        <td><?php echo escape_html($question->order_in_quiz); ?></td>
                        <td><?php echo escape_html($question->question_text); ?></td>
                        <td><?php echo escape_html(str_replace('_', ' ', ucfirst($question->question_type))); ?></td>
                        <td><?php echo count($answers_for_this_question); ?></td>
                        <td>
                            <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=manage_answers&question_id=<?php echo $question->question_id; ?>" class="btn btn-sm btn-success mb-1">Manage Answers</a>
                            <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=edit_question&question_id=<?php echo $question->question_id; ?>" class="btn btn-sm btn-warning mb-1">Edit Q</a>
                            <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=delete_question&question_id=<?php echo $question->question_id; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Delete this question and its answers?');">Del Q</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>


<!-- Answer Management Section -->
<?php if ($managing_answers_for_question_id && $edit_question_data): ?>
    <hr class="my-4">
    <div id="answer_management_section" class="card border-success mb-4">
        <div class="card-header bg-success text-white">
             <h3 class="mb-0">Manage Answers for Question #<?php echo $edit_question_data->order_in_quiz; ?>: "<em><?php echo escape_html(substr($edit_question_data->question_text, 0, 50) . '...'); ?></em>"</h3>
        </div>
        <div class="card-body">
            <h4 class="card-title">Existing Answers:</h4>
            <?php $answers = $answerHandler->getAnswersByQuestionId($managing_answers_for_question_id); ?>
            <?php if (empty($answers)): ?>
                <div class="alert alert-info">No answers yet for this question. Add one below.</div>
            <?php else: ?>
                <form action="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>" method="POST" id="set_correct_answer_form">
                    <input type="hidden" name="form_type" value="set_correct_answer">
                    <input type="hidden" name="question_id_for_correct_answer" value="<?php echo $managing_answers_for_question_id; ?>">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th style="width:10%">Correct?</th><th>Answer Text</th><th style="width:20%">Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($answers as $ans): ?>
                                <tr>
                                    <td>
                                        <?php if ($edit_question_data->question_type === 'single_choice'): ?>
                                        <input class="form-check-input" type="radio" name="correct_answer_id_radio" value="<?php echo $ans->answer_id; ?>"
                                               id="correct_ans_<?php echo $ans->answer_id; ?>"
                                               <?php if ($ans->is_correct) echo 'checked'; ?>
                                               onchange="document.getElementById('set_correct_answer_form').submit();"
                                               title="Select this as the correct answer and submit">
                                        <?php endif; ?>
                                    </td>
                                    <td><label for="correct_ans_<?php echo $ans->answer_id; ?>"><?php echo escape_html($ans->answer_text); ?></label></td>
                                    <td>
                                        <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=edit_answer&question_id=<?php echo $managing_answers_for_question_id; ?>&answer_id=<?php echo $ans->answer_id; ?>#add_answer_form" class="btn btn-sm btn-outline-warning py-0 px-1">Edit</a>
                                        <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=delete_answer&question_id=<?php echo $managing_answers_for_question_id; ?>&answer_id=<?php echo $ans->answer_id; ?>" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="return confirm('Delete this answer?');">Del</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>

            <hr>
            <h4 id="add_answer_form" class="card-title mt-3"><?php echo $edit_answer_data ? 'Edit Answer Option' : 'Add New Answer Option'; ?>:</h4>
            <form action="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>" method="POST">
                <input type="hidden" name="form_type" value="answer">
                <input type="hidden" name="question_id_for_answer" value="<?php echo $managing_answers_for_question_id; ?>">
                <?php if ($edit_answer_data): ?>
                    <input type="hidden" name="answer_id" value="<?php echo $edit_answer_data->answer_id; ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="answer_text" class="form-label">Answer Text:</label>
                    <input type="text" class="form-control" id="answer_text" name="answer_text" value="<?php echo escape_html($edit_answer_data->answer_text ?? ''); ?>" required>
                </div>

                <?php if (!$edit_answer_data && $edit_question_data->question_type === 'single_choice'): ?>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="set_correct_new_answer" name="set_correct_new_answer" value="yes">
                    <label class="form-check-label" for="set_correct_new_answer">Set this new answer as the correct one?</label>
                    <div class="form-text">If checked, this will replace any existing correct answer for this single-choice question.</div>
                </div>
                <?php endif; ?>

                <?php if ($edit_answer_data && $edit_question_data->question_type === 'single_choice'): ?>
                <div class="mb-3 form-text">
                    To change which answer is correct, use the radio buttons in the list above. This form only updates the answer text.
                    <input type="radio" name="is_correct_existing_radio" value="<?php echo $edit_answer_data->answer_id; ?>" <?php if($edit_answer_data->is_correct) echo 'checked';?> style="display:none;">
                </div>
                <?php endif; ?>

                <button type="submit" class="btn <?php echo $edit_answer_data ? 'btn-primary' : 'btn-success'; ?>"><?php echo $edit_answer_data ? 'Update Answer Text' : 'Add Answer'; ?></button>
                <?php if ($edit_answer_data): ?>
                    <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=manage_answers&question_id=<?php echo $managing_answers_for_question_id; ?>" class="btn btn-secondary ms-2">Cancel Edit Answer</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php endif; ?>


<?php
echo "</div>"; // Close .container from admin_header.php
ob_end_flush();
?>
</body>
</html>
