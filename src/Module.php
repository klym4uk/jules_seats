<?php

class Module {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Creates a new module.
     *
     * @param string $title
     * @param string $description
     * @param string $deadline Date string (YYYY-MM-DD)
     * @param string $status Enum('active', 'inactive', 'archived')
     * @return int|false The ID of the newly created module or false on failure.
     */
    public function createModule($title, $description, $deadline, $status = 'inactive') {
        if (empty($title) || empty($status)) {
            // Basic validation
            return false;
        }
        // Validate deadline format if provided
        if (!empty($deadline) && !$this->isValidDate($deadline)) {
            error_log("Invalid deadline format: " . $deadline);
            return false;
        }

        $sql = "INSERT INTO modules (title, description, deadline, status)
                VALUES (:title, :description, :deadline, :status)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':deadline', $deadline, PDO::PARAM_STR); // Ensure YYYY-MM-DD format from input
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);

            if ($stmt->execute()) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating module: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a single module by its ID.
     *
     * @param int $module_id
     * @return object|false Module object or false if not found.
     */
    public function getModuleById($module_id) {
        $sql = "SELECT * FROM modules WHERE module_id = :module_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching module by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all modules.
     *
     * @return array Array of module objects.
     */
    public function getAllModules($activeOnly = false) {
        $sql = "SELECT * FROM modules";
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= " ORDER BY created_at DESC";
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching all modules: " . $e->getMessage());
            return []; // Return empty array on error
        }
    }

    /**
     * Updates an existing module.
     *
     * @param int $module_id
     * @param string $title
     * @param string $description
     * @param string $deadline Date string (YYYY-MM-DD)
     * @param string $status
     * @return bool True on success, false on failure.
     */
    public function updateModule($module_id, $title, $description, $deadline, $status) {
        if (empty($title) || empty($status) || empty($module_id)) {
            return false;
        }
        if (!empty($deadline) && !$this->isValidDate($deadline)) {
             error_log("Invalid deadline format for update: " . $deadline);
            return false;
        }

        $sql = "UPDATE modules SET title = :title, description = :description, deadline = :deadline, status = :status, updated_at = CURRENT_TIMESTAMP
                WHERE module_id = :module_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating module: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a module by its ID.
     * (Assumes ON DELETE CASCADE is set in DB for related lessons, quizzes, progress)
     *
     * @param int $module_id
     * @return bool True on success, false on failure.
     */
    public function deleteModule($module_id) {
        $sql = "DELETE FROM modules WHERE module_id = :module_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting module: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Counts modules by their status.
     *
     * @param string $status The status to count (e.g., 'active', 'inactive').
     * @return int The number of modules with the specified status.
     */
    public function countModulesByStatus($status) {
        $sql = "SELECT COUNT(*) FROM modules WHERE status = :status";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting modules by status: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Validates a date string format (YYYY-MM-DD).
     *
     * @param string $date
     * @return bool
     */
    private function isValidDate($date) {
        if ($date === null || $date === '') return true; // Allow empty or null deadline
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
?>
