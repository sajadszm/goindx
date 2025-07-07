<?php

namespace Models;

use PDO;
use Database;
// Not using EncryptionHelper here for 'about_us_text' for simplicity,
// but could be added if other settings need encryption.

class AppSettingsModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Gets a setting value by its key.
     * @param string $key The key of the setting.
     * @return string|null The value of the setting or null if not found.
     */
    public function getSetting(string $key): ?string {
        $stmt = $this->db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = :setting_key");
        $stmt->bindParam(':setting_key', $key);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : null;
    }

    /**
     * Sets (adds or updates) a setting value.
     * This will be used by the admin panel later.
     * @param string $key The key of the setting.
     * @param string $value The value of the setting.
     * @return bool True on success, false on failure.
     */
    public function setSetting(string $key, string $value): bool {
        // Using INSERT ... ON DUPLICATE KEY UPDATE for simplicity (REPLACE INTO is another option)
        $sql = "INSERT INTO app_settings (setting_key, setting_value)
                VALUES (:setting_key, :setting_value)
                ON DUPLICATE KEY UPDATE setting_value = :setting_value_update";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':setting_key', $key);
        $stmt->bindParam(':setting_value', $value);
        $stmt->bindParam(':setting_value_update', $value); // For the UPDATE part

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error setting app setting '{$key}': " . $e->getMessage());
            return false;
        }
    }
}
?>
