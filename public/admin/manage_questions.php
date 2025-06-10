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

<p>
    <a href="manage_quizzes.php?module_id=<?php echo $current_quiz->module_id; ?>" class="button-link" style="background-color: #7f8c8d;">&laquo; Back to Quizzes in "<?php echo escape_html($current_module->title); ?>"</a>
</p>

<!-- Question Create/Edit Form -->
<div class="edit-form-container" id="question_form_container" <?php if ($managing_answers_for_question_id && !$edit_question_data) echo 'style="display:none;"';?>>
    <h3><?php echo $edit_question_data && !$managing_answers_for_question_id ? 'Edit Question' : 'Add New Question'; ?> for "<?php echo escape_html($current_quiz->title); ?>"</h3>
    <form action="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>" method="POST">
        <input type="hidden" name="form_type" value="question">
        <!-- <input type="hidden" name="csrf_token" value="<?php // echo $_SESSION['csrf_token']; ?>"> -->
        <?php if ($edit_question_data && !$managing_answers_for_question_id): ?>
            <input type="hidden" name="question_id" value="<?php echo escape_html($edit_question_data->question_id); ?>">
        <?php endif; ?>

        <div>
            <label for="question_text">Question Text:</label>
            <textarea id="question_text" name="question_text" rows="3" required><?php echo escape_html($edit_question_data->question_text ?? ''); ?></textarea>
        </div>
        <div>
            <label for="order_in_quiz">Order in Quiz:</label>
            <input type="number" id="order_in_quiz" name="order_in_quiz" value="<?php echo escape_html($edit_question_data->order_in_quiz ?? count($questionsForQuiz)); ?>" required min="0">
        </div>
        <div>
            <label for="question_type">Question Type:</label>
            <select id="question_type" name="question_type">
                <option value="single_choice" <?php echo (isset($edit_question_data->question_type) && $edit_question_data->question_type == 'single_choice') ? 'selected' : ''; ?>>Single Choice (Correct answer picked via radio button)</option>
                <!-- <option value="multiple_choice" <?php // echo (isset($edit_question_data->question_type) && $edit_question_data->question_type == 'multiple_choice') ? 'selected' : ''; ?>>Multiple Choice (Correct answers via checkboxes)</option> -->
            </select>
        </div>
        <button type="submit"><?php echo $edit_question_data && !$managing_answers_for_question_id ? 'Update Question' : 'Create Question'; ?></button>
        <?php if ($edit_question_data && !$managing_answers_for_question_id): ?>
             <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>" class="button-link" style="background-color: #7f8c8d; margin-left:10px;">Cancel Edit</a>
        <?php endif; ?>
    </form>
</div>


<!-- List Existing Questions -->
<h2>Questions in "<?php echo escape_html($current_quiz->title); ?>"</h2>
<?php if (empty($questionsForQuiz)): ?>
    <p>No questions yet for this quiz. Add one above.</p>
<?php else: ?>
    <table>
        <thead>
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
                <tr <?php if ($managing_answers_for_question_id == $question->question_id) echo 'style="background-color: #e0f2f7;"'; ?>>
                    <td><?php echo escape_html($question->order_in_quiz); ?></td>
                    <td><?php echo escape_html($question->question_text); ?></td>
                    <td><?php echo escape_html(str_replace('_', ' ', ucfirst($question->question_type))); ?></td>
                    <td><?php echo count($answers_for_this_question); ?></td>
                    <td class="action-links">
                        <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=manage_answers&question_id=<?php echo $question->question_id; ?>" class="button-link manage">Manage Answers</a>
                        <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=edit_question&question_id=<?php echo $question->question_id; ?>" class="button-link edit">Edit Q</a>
                        <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=delete_question&question_id=<?php echo $question->question_id; ?>" class="button-link delete" onclick="return confirm('Delete this question and its answers?');">Del Q</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>


<!-- Answer Management Section (shown if managing_answers_for_question_id is set) -->
<?php if ($managing_answers_for_question_id && $edit_question_data): // $edit_question_data is loaded for the question whose answers are being managed ?>
    <hr style="margin: 30px 0;">
    <div id="answer_management_section" class="edit-form-container" style="border-color: #9b59b6;">
        <h3>Manage Answers for Question <?php echo $edit_question_data->order_in_quiz+1; ?>: "<em><?php echo escape_html($edit_question_data->question_text); ?></em>"</h3>

        <h4>Existing Answers:</h4>
        <?php $answers = $answerHandler->getAnswersByQuestionId($managing_answers_for_question_id); ?>
        <?php if (empty($answers)): ?>
            <p>No answers yet for this question. Add one below.</p>
        <?php else: ?>
            <form action="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>" method="POST">
                <!-- <input type="hidden" name="csrf_token" value="<?php // echo $_SESSION['csrf_token']; ?>"> -->
                <input type="hidden" name="form_type" value="set_correct_answer">
                <input type="hidden" name="question_id_for_correct_answer" value="<?php echo $managing_answers_for_question_id; ?>">
                <table>
                    <thead><tr><th>Correct?</th><th>Answer Text</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($answers as $ans): ?>
                        <tr>
                            <td>
                                <?php if ($edit_question_data->question_type === 'single_choice'): ?>
                                <input type="radio" name="correct_answer_id_radio" value="<?php echo $ans->answer_id; ?>"
                                       <?php if ($ans->is_correct) echo 'checked'; ?>
                                       onchange="this.form.submit();"
                                       title="Select this as the correct answer and submit">
                                <?php // For multiple_choice, you'd use checkboxes and a different update mechanism
                                /* elseif ($edit_question_data->question_type === 'multiple_choice'): ?>
                                <input type="checkbox" name="correct_answer_ids[]" value="<?php echo $ans->answer_id; ?>" <?php if ($ans->is_correct) echo 'checked'; ?>>
                                <?php */
                                endif; ?>
                            </td>
                            <td><?php echo escape_html($ans->answer_text); ?></td>
                            <td class="action-links">
                                <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=edit_answer&question_id=<?php echo $managing_answers_for_question_id; ?>&answer_id=<?php echo $ans->answer_id; ?>" class="button-link edit">Edit A</a>
                                <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=delete_answer&question_id=<?php echo $managing_answers_for_question_id; ?>&answer_id=<?php echo $ans->answer_id; ?>" class="button-link delete" onclick="return confirm('Delete this answer?');">Del A</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php /* if ($edit_question_data->question_type === 'multiple_choice'): ?>
                    <button type="submit">Update Correct Answers (for Multiple Choice)</button>
                <?php endif; */ ?>
            </form>
        <?php endif; ?>

        <h4 style="margin-top: 20px;"><?php echo $edit_answer_data ? 'Edit Answer' : 'Add New Answer'; ?>:</h4>
        <form action="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>" method="POST">
            <!-- <input type="hidden" name="csrf_token" value="<?php // echo $_SESSION['csrf_token']; ?>"> -->
            <input type="hidden" name="form_type" value="answer">
            <input type="hidden" name="question_id_for_answer" value="<?php echo $managing_answers_for_question_id; ?>">
            <?php if ($edit_answer_data): ?>
                <input type="hidden" name="answer_id" value="<?php echo $edit_answer_data->answer_id; ?>">
            <?php endif; ?>

            <div>
                <label for="answer_text">Answer Text:</label>
                <input type="text" id="answer_text" name="answer_text" value="<?php echo escape_html($edit_answer_data->answer_text ?? ''); ?>" required>
            </div>

            <?php if (!$edit_answer_data && $edit_question_data->question_type === 'single_choice'): ?>
            <div>
                <input type="checkbox" id="set_correct_new_answer" name="set_correct_new_answer" value="yes">
                <label for="set_correct_new_answer">Set this new answer as the correct one?</label>
                <small>(If checked, this will replace any existing correct answer for this single-choice question.)</small>
            </div>
            <?php endif; ?>

            <?php if ($edit_answer_data && $edit_question_data->question_type === 'single_choice'): ?>
            <div>
                <!-- Hidden radio button to pass the value if the current answer being edited is selected as correct -->
                 <input type="radio" name="is_correct_existing_radio" value="<?php echo $edit_answer_data->answer_id; ?>" <?php if($edit_answer_data->is_correct) echo 'checked';?> style="display:none;">
                 <p><small>To change which answer is correct, use the radio buttons in the list above.</small></p>
            </div>
            <?php endif; ?>


            <button type="submit"><?php echo $edit_answer_data ? 'Update Answer Text' : 'Add Answer'; ?></button>
            <?php if ($edit_answer_data): ?>
                <a href="manage_questions.php?quiz_id=<?php echo $current_quiz_id; ?>&action=manage_answers&question_id=<?php echo $managing_answers_for_question_id; ?>" class="button-link" style="background-color: #7f8c8d; margin-left:10px;">Cancel Edit Answer</a>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>


<?php
echo "</main>"; // Close main.container from header
ob_end_flush();
?>
</body>
</html>
