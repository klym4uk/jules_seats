<?php

class Lesson {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Creates a new lesson.
     *
     * @param int $module_id
     * @param string $title
     * @param string $content_type Enum('text', 'video', 'image')
     * @param string|null $content_text
     * @param string|null $content_url
     * @param int $order_in_module
     * @return int|false The ID of the newly created lesson or false on failure.
     */
    public function createLesson($module_id, $title, $content_type, $content_text, $content_url, $order_in_module) {
        if (empty($module_id) || empty($title) || empty($content_type) || !isset($order_in_module)) {
            return false; // Basic validation
        }

        // Validate content based on type
        if ($content_type === 'text' && empty($content_text)) return false;
        if (($content_type === 'video' || $content_type === 'image') && empty($content_url)) return false;
        // Clear irrelevant content
        if ($content_type === 'text') $content_url = null;
        else $content_text = null;


        $sql = "INSERT INTO lessons (module_id, title, content_type, content_text, content_url, order_in_module)
                VALUES (:module_id, :title, :content_type, :content_text, :content_url, :order_in_module)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':content_type', $content_type, PDO::PARAM_STR);
            $stmt->bindParam(':content_text', $content_text, PDO::PARAM_STR);
            $stmt->bindParam(':content_url', $content_url, PDO::PARAM_STR);
            $stmt->bindParam(':order_in_module', $order_in_module, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating lesson: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all lessons for a given module, ordered by order_in_module.
     *
     * @param int $module_id
     * @return array Array of lesson objects.
     */
    public function getLessonsByModuleId($module_id) {
        $sql = "SELECT * FROM lessons WHERE module_id = :module_id ORDER BY order_in_module ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching lessons by module ID: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetches a single lesson by its ID.
     *
     * @param int $lesson_id
     * @return object|false Lesson object or false if not found.
     */
    public function getLessonById($lesson_id) {
        $sql = "SELECT * FROM lessons WHERE lesson_id = :lesson_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching lesson by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates an existing lesson.
     *
     * @param int $lesson_id
     * @param string $title
     * @param string $content_type
     * @param string|null $content_text
     * @param string|null $content_url
     * @param int $order_in_module
     * @return bool True on success, false on failure.
     */
    public function updateLesson($lesson_id, $title, $content_type, $content_text, $content_url, $order_in_module) {
        if (empty($lesson_id) || empty($title) || empty($content_type) || !isset($order_in_module)) {
            return false;
        }

        // Validate content based on type
        if ($content_type === 'text' && empty($content_text)) return false;
        if (($content_type === 'video' || $content_type === 'image') && empty($content_url)) return false;
        // Clear irrelevant content
        if ($content_type === 'text') $content_url = null;
        else $content_text = null;

        $sql = "UPDATE lessons SET title = :title, content_type = :content_type,
                content_text = :content_text, content_url = :content_url, order_in_module = :order_in_module,
                updated_at = CURRENT_TIMESTAMP
                WHERE lesson_id = :lesson_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':content_type', $content_type, PDO::PARAM_STR);
            $stmt->bindParam(':content_text', $content_text, PDO::PARAM_STR);
            $stmt->bindParam(':content_url', $content_url, PDO::PARAM_STR);
            $stmt->bindParam(':order_in_module', $order_in_module, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating lesson: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a lesson by its ID.
     *
     * @param int $lesson_id
     * @return bool True on success, false on failure.
     */
    public function deleteLesson($lesson_id) {
        $sql = "DELETE FROM lessons WHERE lesson_id = :lesson_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting lesson: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates the order of a specific lesson.
     * (Note: This is a simple update for one lesson's order.
     * Managing order across multiple lessons typically requires more complex logic,
     * like re-ordering siblings, which is not implemented here.)
     *
     * @param int $lesson_id
     * @param int $new_order
     * @return bool True on success, false on failure.
     */
    public function updateLessonOrder($lesson_id, $new_order) {
        if (empty($lesson_id) || !isset($new_order) || !is_numeric($new_order) || $new_order < 0) {
            return false;
        }

        $sql = "UPDATE lessons SET order_in_module = :order_in_module, updated_at = CURRENT_TIMESTAMP
                WHERE lesson_id = :lesson_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
            $stmt->bindParam(':order_in_module', $new_order, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating lesson order: " . $e->getMessage());
            return false;
        }
    }
}
?>
