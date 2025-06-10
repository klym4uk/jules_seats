<?php
<?php
// export_handler.php

// Enforce admin access and start session handling at the very beginning.
require_once '../../config/database.php'; // Defines $pdo and db constants if not already defined elsewhere
require_once '../../src/includes/functions.php';

start_session_if_not_started();
require_admin(); // Ensures only logged-in Admin users can access this script.

// Models should be included after session and auth checks.
require_once '../../src/User.php';
    require_once '../../src/Module.php';
    require_once '../../src/UserModuleProgress.php';
    require_once '../../src/Quiz.php';
    require_once '../../src/QuizResult.php';

// Only run if report type is specified
if (isset($_GET['report'])) {
    // $pdo should be available from config/database.php
    if (!isset($pdo)) {
        // This case should ideally not be reached if config/database.php is included correctly.
        error_log("export_handler.php: PDO object not available after including config.");
        die("Database connection is not available for export. Please check server logs.");
    }

    $userHandler = new User($pdo);
    $moduleHandler = new Module($pdo);
    $userModuleProgressHandler = new UserModuleProgress($pdo);
    $quizHandler = new Quiz($pdo); // Instantiate all handlers that might be needed
    $quizResultHandler = new QuizResult($pdo);

    if ($_GET['report'] == 'user_progress') {
        // User handler, module handler, UMP handler already instantiated

        $employees = $userHandler->getAllUsers('Employee');
        $activeModules = $moduleHandler->getAllModules(true);
        $totalActiveModulesCount = count($activeModules);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="user_progress_report_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['User ID', 'First Name', 'Last Name', 'Email', 'Overall Progress (%)', 'Modules Passed (Active)', 'Total Active Modules']);

        foreach ($employees as $employee) {
            $passedModulesCount = $userModuleProgressHandler->getCountPassedModulesForUser($employee->user_id);
            $overallProgressPercentage = 0;
            if ($totalActiveModulesCount > 0) {
                $overallProgressPercentage = round(($passedModulesCount / $totalActiveModulesCount) * 100, 2);
            }
            $userDataArray = [
                $employee->user_id,
                $employee->first_name,
                $employee->last_name,
                $employee->email,
                $overallProgressPercentage,
                $passedModulesCount,
                $totalActiveModulesCount
            ];
            fputcsv($output, $userDataArray);
        }
        fclose($output);
        exit;

    } elseif ($_GET['report'] == 'quiz_results' && isset($_GET['quiz_id'])) {
        $quiz_id = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
        if (!$quiz_id) {
            die("Invalid Quiz ID for export.");
        }

        $quizHandler = new Quiz($pdo);
        $quizResultHandler = new QuizResult($pdo);

        $quiz = $quizHandler->getQuizById($quiz_id);
        if (!$quiz) {
            die("Quiz not found for export.");
        }
        $quiz_name = $quiz->title;

        // getAllAttemptsDetailsForQuiz was added to QuizResult
        $attemptsDetails = $quizResultHandler->getAllAttemptsDetailsForQuiz($quiz_id);

        header('Content-Type: text/csv; charset=utf-utf-8'); // Typo: should be utf-8
        header('Content-Disposition: attachment; filename="quiz_results_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $quiz_name) . '_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Quiz Name: ' . $quiz_name]); // Header row for Quiz Name
        fputcsv($output, ['User ID', 'First Name', 'Last Name', 'Email', 'Attempt Number', 'Score (%)', 'Status (Passed/Failed)', 'Attempt Date']);

        foreach ($attemptsDetails as $attempt) {
            $status = $attempt->passed ? 'Passed' : 'Failed';
            $attemptDate = $attempt->completed_at ? date('Y-m-d H:i:s', strtotime($attempt->completed_at)) : ($attempt->created_at ? date('Y-m-d H:i:s', strtotime($attempt->created_at)).' (Started)' : 'N/A');

            $attemptDataArray = [
                $attempt->user_id,
                $attempt->first_name,
                $attempt->last_name,
                $attempt->email,
                $attempt->attempt_number,
                $attempt->score,
                $status,
                $attemptDate
            ];
            fputcsv($output, $attemptDataArray);
        }
        fclose($output);
        exit;
    } else {
        // Optionally handle unknown report types or missing parameters
        header("HTTP/1.0 400 Bad Request");
        echo "Invalid report type or missing parameters.";
        exit;
    }
} else {
    header("HTTP/1.0 400 Bad Request");
    echo "No report type specified.";
    exit;
}
?>
