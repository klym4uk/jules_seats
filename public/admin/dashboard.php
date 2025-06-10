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


include_once '../../src/includes/admin_header.php';
?>

<div class="container-fluid px-4"> <!-- Using container-fluid for more width if desired, or stick to .container -->
    <h1 class="mt-4">Admin Dashboard</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Overview & Statistics</li>
    </ol>

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    Total Employees
                    <div class="display-4"><?php echo $totalEmployees; ?></div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="users.php">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-secondary text-white mb-4">
                <div class="card-body">
                    Total Admins
                    <div class="display-4"><?php echo $totalAdmins; ?></div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="users.php">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    Active Modules
                    <div class="display-4"><?php echo $totalActiveModules; ?></div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="manage_modules.php">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4"> <!-- Changed from Inactive Modules to User Passed Rate -->
                <div class="card-body">
                    Users Passed 1+ Module
                    <div class="display-4"><?php echo $percentageUsersPassedOneModule; ?>%</div>
                    <small>(<?php echo $employeesWhoPassedAtLeastOneModule . ' of ' . $totalEmployees . ' employees'; ?>)</small>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="user_progress_overview.php">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-link me-1"></i> <!-- Placeholder for FontAwesome icon -->
                    Quick Links
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><a href="users.php" class="text-decoration-none">Manage Users</a></li>
                        <li class="list-group-item"><a href="manage_modules.php" class="text-decoration-none">Manage Modules & Training</a></li>
                        <li class="list-group-item"><a href="user_progress_overview.php" class="text-decoration-none">User Progress Overview</a></li>
                        <li class="list-group-item"><a href="module_progress_overview.php" class="text-decoration-none">Module Progress Overview</a></li>
                        <li class="list-group-item"><a href="reports.php" class="text-decoration-none">Generate Reports</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bell me-1"></i> <!-- Placeholder for FontAwesome icon -->
                    System Activity (Placeholder)
                </div>
                <div class="card-body">
                    No new critical system activity to show.
                    <!-- Placeholder for future charts or activity logs -->
                </div>
            </div>
        </div>
    </div>
</div> <!-- Closing .container or .container-fluid from admin_header.php -->

<?php
// The main closing tag is in admin_header.php's structure
// For scripts that use admin_header.php, they should end with:
// echo "</div>"; // This closes the .container or .container-fluid from header
// ob_end_flush();
// echo "</body></html>";
// However, since admin_header.php ends with opening <div class="container mt-4">
// and the </body></html> is in this file, we just need to close the container.
// The current admin_header.php doesn't have </body></html>, so this file provides it.
// Let's ensure admin_header.php includes the start of the main container, and here we close it.
// The admin_header was updated to start <div class="container mt-4">
// So, here we need to close it.
echo "</div>"; // This closes .container from admin_header.php
ob_end_flush();
?>
</body>
</html>
