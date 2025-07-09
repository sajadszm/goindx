<?php

namespace Models;

use PDO;
use Database;
use Helpers\EncryptionHelper;

class SymptomModel {
    private $db;
    private static $symptomsConfig;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        if (self::$symptomsConfig === null) {
            self::$symptomsConfig = require BASE_PATH . '/config/symptoms_config.php';
        }
    }

    /**
     * Logs a symptom for a user on a specific date.
     * If the symptom is already logged for that day, it can optionally be removed (toggle behavior).
     * For simplicity, this version adds a symptom. A separate removeSymptom might be cleaner.
     * Or, more practically, we fetch all symptoms for the day, let user toggle in UI, then batch save.
     * This method will add a symptom if it doesn't exist for the user, category, name, and date.
     */
    public function addSymptom(int $userId, string $symptomDate, string $categoryKey, string $symptomKey): bool {
        $categoryName = self::$symptomsConfig['categories'][$categoryKey] ?? 'Unknown Category';
        $symptomName = self::$symptomsConfig['symptoms'][$categoryKey][$symptomKey] ?? 'Unknown Symptom';

        $encryptedCategory = EncryptionHelper::encrypt($categoryName);
        $encryptedSymptom = EncryptionHelper::encrypt($symptomName);
        // Store keys as well for easier, non-encrypted querying if ever needed for aggregations (though less private)
        // For now, just encrypting the full names.

        // Check if already exists to prevent duplicates for the same day/symptom
        $stmt_check = $this->db->prepare("SELECT id FROM logged_symptoms
                                          WHERE user_id = :user_id AND symptom_date = :symptom_date
                                          AND encrypted_symptom_category = :cat AND encrypted_symptom_name = :sym");
        $stmt_check->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt_check->bindParam(':symptom_date', $symptomDate);
        $stmt_check->bindParam(':cat', $encryptedCategory);
        $stmt_check->bindParam(':sym', $encryptedSymptom);
        $stmt_check->execute();
        if ($stmt_check->fetch()) {
            return true; // Already logged, treat as success or implement toggle
        }

        $sql = "INSERT INTO logged_symptoms (user_id, symptom_date, encrypted_symptom_category, encrypted_symptom_name)
                VALUES (:user_id, :symptom_date, :encrypted_category, :encrypted_symptom)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':symptom_date', $symptomDate);
        $stmt->bindParam(':encrypted_category', $encryptedCategory);
        $stmt->bindParam(':encrypted_symptom', $encryptedSymptom);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error logging symptom: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Removes a specific symptom for a user on a specific date.
     */
    public function removeSymptom(int $userId, string $symptomDate, string $categoryKey, string $symptomKey): bool {
        $categoryName = self::$symptomsConfig['categories'][$categoryKey] ?? 'Unknown Category';
        $symptomName = self::$symptomsConfig['symptoms'][$categoryKey][$symptomKey] ?? 'Unknown Symptom';

        $encryptedCategory = EncryptionHelper::encrypt($categoryName);
        $encryptedSymptom = EncryptionHelper::encrypt($symptomName);

        $sql = "DELETE FROM logged_symptoms
                WHERE user_id = :user_id AND symptom_date = :symptom_date
                AND encrypted_symptom_category = :encrypted_category
                AND encrypted_symptom_name = :encrypted_symptom";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':symptom_date', $symptomDate);
        $stmt->bindParam(':encrypted_category', $encryptedCategory);
        $stmt->bindParam(':encrypted_symptom', $encryptedSymptom);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error removing symptom: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Logs a single symptom. Used by UserController's save final.
     * This version is more aligned with how UserController saves symptoms.
     */
    public function logSymptom(int $userId, string $symptomDate, string $encryptedCategoryName, string $encryptedSymptomName): bool {
        // Check if already exists to prevent duplicates for the same day/symptom
        // This check might be redundant if deleteSymptomsForDate is called first in a batch operation.
        $stmt_check = $this->db->prepare("SELECT id FROM logged_symptoms
                                          WHERE user_id = :user_id AND symptom_date = :symptom_date
                                          AND encrypted_symptom_category = :cat AND encrypted_symptom_name = :sym");
        $stmt_check->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt_check->bindParam(':symptom_date', $symptomDate);
        $stmt_check->bindParam(':cat', $encryptedCategoryName);
        $stmt_check->bindParam(':sym', $encryptedSymptomName);
        $stmt_check->execute();
        if ($stmt_check->fetch()) {
            return true; // Already logged
        }

        $sql = "INSERT INTO logged_symptoms (user_id, symptom_date, encrypted_symptom_category, encrypted_symptom_name)
                VALUES (:user_id, :symptom_date, :encrypted_category, :encrypted_symptom)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':symptom_date', $symptomDate);
        $stmt->bindParam(':encrypted_category', $encryptedCategoryName);
        $stmt->bindParam(':encrypted_symptom', $encryptedSymptomName);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error logging symptom (v2): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes all symptoms for a user on a specific date.
     * Typically used before re-logging a new set of symptoms for that day.
     */
    public function deleteSymptomsForDate(int $userId, string $symptomDate): bool {
        $sql = "DELETE FROM logged_symptoms WHERE user_id = :user_id AND symptom_date = :symptom_date";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':symptom_date', $symptomDate);
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting symptoms for date: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Gets all logged symptoms for a user on a specific date.
     * This version is for display in history, returns already encrypted data.
     * @return array Raw rows from DB with encrypted data.
     */
    public function getSymptomsForDate(int $userId, string $symptomDate): array {
        $stmt = $this->db->prepare("SELECT id, encrypted_symptom_category, encrypted_symptom_name
                                    FROM logged_symptoms
                                    WHERE user_id = :user_id AND symptom_date = :symptom_date");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':symptom_date', $symptomDate);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gets distinct dates for which a user has logged symptoms, for pagination.
     * @return array List of dates ['logged_date' => YYYY-MM-DD].
     */
    public function getDistinctLoggedDates(int $userId, int $limit, int $offset): array {
        $sql = "SELECT DISTINCT symptom_date AS logged_date
                FROM logged_symptoms
                WHERE user_id = :user_id
                ORDER BY symptom_date DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Counts the number of distinct dates for which a user has logged symptoms.
     */
    public function countDistinctLoggedDates(int $userId): int {
        $sql = "SELECT COUNT(DISTINCT symptom_date) FROM logged_symptoms WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Clears all symptoms for a user for a specific date and then adds the provided list.
     * This is a batch update method.
     * @param int $userId
     * @param string $symptomDate
     * @param array $symptomsToLog Array of ['category_key' => ..., 'symptom_key' => ...]
     * @return bool
     */
    public function saveSymptomsForDate(int $userId, string $symptomDate, array $symptomsToLog): bool {
        $this->db->beginTransaction();
        try {
            // Clear existing symptoms for the day
            $stmt_delete = $this->db->prepare("DELETE FROM logged_symptoms WHERE user_id = :user_id AND symptom_date = :symptom_date");
            $stmt_delete->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt_delete->bindParam(':symptom_date', $symptomDate);
            $stmt_delete->execute();

            // Add new symptoms
            $sql_insert = "INSERT INTO logged_symptoms (user_id, symptom_date, encrypted_symptom_category, encrypted_symptom_name)
                           VALUES (:user_id, :symptom_date, :encrypted_category, :encrypted_symptom)";
            $stmt_insert = $this->db->prepare($sql_insert);

            foreach ($symptomsToLog as $symptomData) {
                $categoryKey = $symptomData['category_key'];
                $symptomKey = $symptomData['symptom_key'];

                $categoryName = self::$symptomsConfig['categories'][$categoryKey] ?? 'Unknown Category';
                $symptomName = self::$symptomsConfig['symptoms'][$categoryKey][$symptomKey] ?? 'Unknown Symptom';

                $encryptedCategory = EncryptionHelper::encrypt($categoryName);
                $encryptedSymptom = EncryptionHelper::encrypt($symptomName);

                $stmt_insert->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt_insert->bindParam(':symptom_date', $symptomDate);
                $stmt_insert->bindParam(':encrypted_category', $encryptedCategory);
                $stmt_insert->bindParam(':encrypted_symptom', $encryptedSymptom);
                $stmt_insert->execute();
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error saving symptoms for date: " . $e->getMessage());
            return false;
        }
    }
}
?>
