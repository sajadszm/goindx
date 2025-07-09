<?php

namespace Models;

use PDO;
use Database;

class UserPreferencesModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Gets all preferences for a user.
     * If no preferences exist, returns an array with default values.
     * @param int $userId
     * @return array
     */
    public function getPreferences(int $userId): array {
        $stmt = $this->db->prepare("SELECT * FROM user_preferences WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prefs) {
            // Return default preferences if none are set
            return [
                'user_id' => $userId,
                'notify_pre_pms' => 1,
                'notify_period_start' => 1,
                'notify_period_end' => 1,
                // notify_ovulation is handled by show_ovulation in users.encrypted_cycle_info
                'notify_daily_educational_self' => 1,
                'notify_daily_educational_partner' => 1,
                'notifications_snooze_until' => null,
                'preferred_content_topics' => json_encode([]), // Default to empty array
                'display_fertile_window' => 1,
                'partner_share_cycle_details' => 'full',
                'partner_share_symptoms' => 'none',
            ];
        }
        // Ensure JSON fields are decoded if necessary, though typically handled by getter/setter logic
        if (isset($prefs['preferred_content_topics']) && is_string($prefs['preferred_content_topics'])) {
            $decodedTopics = json_decode($prefs['preferred_content_topics'], true);
            $prefs['preferred_content_topics'] = is_array($decodedTopics) ? $decodedTopics : [];
        } else if (!isset($prefs['preferred_content_topics'])) {
            $prefs['preferred_content_topics'] = [];
        }

        return $prefs;
    }

    /**
     * Creates or updates a user's preference.
     * @param int $userId
     * @param string $key Preference key (column name)
     * @param mixed $value Value to set
     * @return bool
     */
    public function updatePreference(int $userId, string $key, $value): bool {
        // Validate key against known preference columns to prevent arbitrary column updates
        $allowedKeys = [
            'notify_pre_pms', 'notify_period_start', 'notify_period_end',
            'notify_daily_educational_self', 'notify_daily_educational_partner',
            'notifications_snooze_until', 'preferred_content_topics',
            'display_fertile_window', 'partner_share_cycle_details', 'partner_share_symptoms'
        ];

        if (!in_array($key, $allowedKeys)) {
            error_log("UserPreferencesModel: Attempt to update invalid preference key '{$key}' for user {$userId}");
            return false;
        }

        // Handle JSON encoding for specific fields
        if ($key === 'preferred_content_topics' && is_array($value)) {
            $value = json_encode($value);
        }

        // Check if preferences row exists, if not create one with defaults then update
        $stmtCheck = $this->db->prepare("SELECT id FROM user_preferences WHERE user_id = :user_id");
        $stmtCheck->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtCheck->execute();

        if (!$stmtCheck->fetch()) {
            $this->createDefaultPreferences($userId);
        }

        $sql = "UPDATE user_preferences SET {$key} = :value WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':value', $value);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Creates a default preference record for a user.
     * @param int $userId
     * @return bool
     */
    public function createDefaultPreferences(int $userId): bool {
        $sql = "INSERT INTO user_preferences (user_id, notify_pre_pms, notify_period_start, notify_period_end, notify_daily_educational_self, notify_daily_educational_partner, display_fertile_window, partner_share_cycle_details, partner_share_symptoms, preferred_content_topics)
                VALUES (:user_id, 1, 1, 1, 1, 1, 1, 'full', 'none', :empty_json)
                ON DUPLICATE KEY UPDATE user_id = :user_id"; // Avoids error if called concurrently
        $stmt = $this->db->prepare($sql);
        $emptyJson = json_encode([]);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':empty_json', $emptyJson);

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Error creating default preferences for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sets multiple preferences at once.
     * Expects $data to be an associative array ['key' => value, ...].
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function setPreferences(int $userId, array $data): bool {
        $this->db->beginTransaction();
        try {
            foreach ($data as $key => $value) {
                if (!$this->updatePreference($userId, $key, $value)) {
                    // updatePreference already logs error for invalid key
                    // If a valid key fails to update, this will trigger rollback
                    throw new \Exception("Failed to update preference key '{$key}' for user {$userId}");
                }
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("UserPreferencesModel::setPreferences error: " . $e->getMessage());
            return false;
        }
    }
}
?>
