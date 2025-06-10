<?php

class User {
    private $pdo;

    /**
     * Constructor to set the PDO database connection object.
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Finds a user by their email address.
     *
     * @param string $email The email address to search for.
     * @return mixed User object if found, false otherwise.
     */
    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ); // Using FETCH_OBJ for easier property access
    }

    /**
     * Creates a new user in the database.
     *
     * @param string $email User's email.
     * @param string $password User's raw password.
     * @param string $first_name User's first name.
     * @param string $last_name User's last name.
     * @param string $role User's role ('Employee' or 'Admin').
     * @return bool True on success, false on failure.
     */
    public function createUser($email, $password, $first_name, $last_name, $role = 'Employee') {
        if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            return false; // Basic validation
        }

        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        if (!$password_hash) {
            // Password hashing failed
            error_log("Password hashing failed for email: " . $email);
            return false;
        }

        $sql = "INSERT INTO users (email, password_hash, first_name, last_name, role)
                VALUES (:email, :password_hash, :first_name, :last_name, :role)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
            $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            // Log error, e.g., duplicate email
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifies a user's password against the stored hash.
     *
     * @param string $password The raw password to verify.
     * @param string $hash The stored password hash from the database.
     * @return bool True if the password matches the hash, false otherwise.
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Retrieves all users from the database.
     *
     * @return array An array of user objects.
     */
    public function getAllUsers() {
        $stmt = $this->pdo->query("SELECT user_id, email, first_name, last_name, role, created_at, last_login_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Updates the last_login_at timestamp for a user.
     *
     * @param int $user_id The ID of the user.
     * @return bool True on success, false on failure.
     */
    public function updateLastLogin($user_id) {
        $sql = "UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE user_id = :user_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating last login: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds a user by their ID.
     *
     * @param int $user_id The user's ID.
     * @return mixed User object if found, false otherwise.
     */
    public function findById($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Counts users by their role.
     *
     * @param string $role The role to count (e.g., 'Employee', 'Admin').
     * @return int The number of users with the specified role.
     */
    public function countUsersByRole($role) {
        $sql = "SELECT COUNT(*) FROM users WHERE role = :role";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting users by role: " . $e->getMessage());
            return 0;
        }
    }
}
?>
