<?php
ob_start();
require_once '../../config/database.php'; // For any potential DB interaction, though not used now
require_once '../../src/includes/functions.php'; // For session and security functions
require_once '../../src/User.php'; // If you want to display user-specific info beyond session

start_session_if_not_started();
require_admin(); // Ensure only Admin users can access

$page_title = "Admin Dashboard";
include_once '../../src/includes/admin_header.php'; // Include the new header

// Potentially, you could fetch some stats here to display on the dashboard
// For example: number of users, number of modules, etc.
// For any potential DB interaction, though not used now
require_once '../../src/includes/functions.php'; // For session and security functions
require_once '../../src/User.php'; // If you want to display user-specific info beyond session
require_once '../../src/Module.php'; // For stats
require_once '../../src/UserModuleProgress.php'; // For more advanced stats if needed

start_session_if_not_started();
require_admin(); // Ensure only Admin users can access

$page_title = "Admin Dashboard";

// Instantiate handlers for stats
$userHandler = new User($pdo);
$moduleHandler = new Module($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo); // For more complex stats

// Fetch stats
$totalEmployees = $userHandler->countUsersByRole('Employee');
$totalAdmins = $userHandler->countUsersByRole('Admin');
$totalActiveModules = $moduleHandler->countModulesByStatus('active');
$totalInactiveModules = $moduleHandler->countModulesByStatus('inactive');


// Simplified Overall Training Completion Rate: Percentage of users who passed at least one module.
$allEmployees = $userHandler->getAllUsers(); // Assuming this gets all users, filter for role='Employee'
$employeesWhoPassedAtLeastOneModule = 0;
if (count($allEmployees) > 0) {
    foreach ($allEmployees as $employee) {
        if ($employee->role === 'Employee') {
            $progressRecords = $userModuleProgressHandler->getAllModulesProgressForUser($employee->user_id);
            foreach ($progressRecords as $record) {
                if ($record->status === 'passed') {
                    $employeesWhoPassedAtLeastOneModule++;
                    break; // Move to next employee once one passed module is found
                }
            }
        } else {
            // Filter out non-employees if getAllUsers doesn't do it.
            // For countUsersByRole, we already have $totalEmployees.
        }
    }
}
$percentageUsersPassedOneModule = ($totalEmployees > 0) ? round(($employeesWhoPassedAtLeastOneModule / $totalEmployees) * 100, 2) : 0;


include_once '../../src/includes/admin_header.php'; // Include the new header
?>

<h1>Welcome to the Admin Dashboard, <?php echo escape_html($_SESSION['first_name']); ?>!</h1>

<p>This is your central hub for managing the SEATS application. Use the navigation above to manage users, modules, and track progress.</p>

<div class="dashboard-stats-container" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top:20px;">
    <div class="stat-card" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-basis: 200px; text-align: center;">
        <h4>Total Employees</h4>
        <p style="font-size: 2em; margin: 5px 0;"><?php echo $totalEmployees; ?></p>
    </div>
    <div class="stat-card" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-basis: 200px; text-align: center;">
        <h4>Total Admins</h4>
        <p style="font-size: 2em; margin: 5px 0;"><?php echo $totalAdmins; ?></p>
    </div>
    <div class="stat-card" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-basis: 200px; text-align: center;">
        <h4>Active Modules</h4>
        <p style="font-size: 2em; margin: 5px 0;"><?php echo $totalActiveModules; ?></p>
    </div>
    <div class="stat-card" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-basis: 200px; text-align: center;">
        <h4>Inactive Modules</h4>
        <p style="font-size: 2em; margin: 5px 0;"><?php echo $totalInactiveModules; ?></p>
    </div>
    <div class="stat-card" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-basis: 300px; text-align: center;">
        <h4>Users Passed at Least One Module</h4>
        <p style="font-size: 2em; margin: 5px 0;"><?php echo $percentageUsersPassedOneModule; ?>%</p>
        <small>(<?php echo $employeesWhoPassedAtLeastOneModule . ' of ' . $totalEmployees . ' employees'; ?>)</small>
    </div>
</div>

<h2 style="margin-top:30px;">Quick Links:</h2>
<ul>
    <li><a href="users.php" class="button-link">Manage Users</a></li>
    <li><a href="manage_modules.php" class="button-link">Manage Modules & Training</a></li>
    <li><a href="user_progress_overview.php" class="button-link">User Progress Overview</a> (New)</li>
    <li><a href="module_progress_overview.php" class="button-link">Module Progress Overview</a> (New)</li>
</ul>

<section style="margin-top: 30px; padding: 20px; background-color: #f0f0f0; border-radius: 5px;">
    <h2>System Activity (Placeholder)</h2>
    <p>No new critical system activity to show.</p>
</section>

<!-- You could add more dashboard widgets or summaries here -->

<?php
// Placeholder for a simple footer if you create one
// include_once '../../src/includes/admin_footer.php';
echo "</main>"; // Close main.container from header
ob_end_flush();
?>
</body>
</html>
