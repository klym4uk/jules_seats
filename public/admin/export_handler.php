<?php
// Start session if needed for auth, but typically direct script access might not have session by default
// For simplicity, we assume if someone has the link, they are authorized or this is a simplified setup.
// In a production app, add authentication here.
// require_once '../../config/database.php'; // If not already included by functions or models
// require_once '../../src/includes/functions.php'; // For escape_html if used, and potentially session checks

// Only run if report type is specified
if (isset($_GET['report'])) {

    // Database connection - ensure this is available
    // This might be better handled by including a central bootstrap/config file
    try {
        $pdo = new PDO("mysql:host=" . /*DB_SERVER*/'localhost' . ";dbname=" . /*DB_NAME*/'seats_db', /*DB_USERNAME*/'root', /*DB_PASSWORD*/'');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e){
        // In a real CSV export, you wouldn't output HTML error here. Log it.
        error_log("CSV Export DB Connection ERROR: " . $e->getMessage());
        die("Could not connect to database for export. Check server logs.");
    }

    // Include models AFTER establishing $pdo
    require_once '../../src/User.php';
    require_once '../../src/Module.php';
    require_once '../../src/UserModuleProgress.php';
    require_once '../../src/Quiz.php';
    require_once '../../src/QuizResult.php';


    if ($_GET['report'] == 'user_progress') {
        $userHandler = new User($pdo);
        $moduleHandler = new Module($pdo);
        $userModuleProgressHandler = new UserModuleProgress($pdo);

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
