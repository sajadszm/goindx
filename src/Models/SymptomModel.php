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
     * Gets all logged symptoms for a user on a specific date.
     * Returns an array of ['category_key' => ..., 'symptom_key' => ...] for easier UI mapping.
     * This requires decrypting and then matching back to keys, which is intensive.
     * A more performant way might be to store encrypted keys or a non-encrypted mapping if privacy allows.
     * For now, we decrypt and match.
     * @return array [[category_key, symptom_key], ...]
     */
    public function getLoggedSymptomsForDate(int $userId, string $symptomDate): array {
        $stmt = $this->db->prepare("SELECT encrypted_symptom_category, encrypted_symptom_name
                                    FROM logged_symptoms
                                    WHERE user_id = :user_id AND symptom_date = :symptom_date");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':symptom_date', $symptomDate);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $loggedSymptomsKeys = [];
        foreach ($results as $row) {
            try {
                $decryptedCategory = EncryptionHelper::decrypt($row['encrypted_symptom_category']);
                $decryptedSymptom = EncryptionHelper::decrypt($row['encrypted_symptom_name']);

                $foundCatKey = null;
                $foundSymKey = null;

                foreach (self::$symptomsConfig['categories'] as $catKey => $catName) {
                    if ($catName === $decryptedCategory) {
                        $foundCatKey = $catKey;
                        break;
                    }
                }
                if ($foundCatKey) {
                    foreach (self::$symptomsConfig['symptoms'][$foundCatKey] as $symKey => $symName) {
                        if ($symName === $decryptedSymptom) {
                            $foundSymKey = $symKey;
                            break;
                        }
                    }
                }
                if ($foundCatKey && $foundSymKey) {
                    $loggedSymptomsKeys[] = ['category_key' => $foundCatKey, 'symptom_key' => $foundSymKey];
                }
            } catch (\Exception $e) {
                error_log("Error decrypting/mapping symptom: " . $e->getMessage());
                // Skip this symptom if decryption fails
            }
        }
        return $loggedSymptomsKeys;
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
