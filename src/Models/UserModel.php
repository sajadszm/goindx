<?php

namespace Models;

use PDO;
use Database; // Assumes Database class is available globally or autoloaded from config
use Helpers\EncryptionHelper; // Will be created later

class UserModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findUserByTelegramId($hashedTelegramId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE telegram_id_hash = :telegram_id_hash");
        $stmt->bindParam(':telegram_id_hash', $hashedTelegramId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUser($hashedTelegramId, string $chatId, $firstName, $username = null) {
        // Storing first_name and username unencrypted for now for ease of use in welcome messages.
        // Consider if these should be encrypted based on privacy requirements.
        // For this project, Telegram ID is the primary sensitive PII that we hash.
        $encryptedChatId = EncryptionHelper::encrypt($chatId); // Encrypt the actual chat_id
        $encryptedFirstName = EncryptionHelper::encrypt($firstName);
        $encryptedUsername = $username ? EncryptionHelper::encrypt($username) : null;

        $trialEndsAt = date('Y-m-d H:i:s', strtotime('+' . FREE_TRIAL_DAYS . ' days'));

        $sql = "INSERT INTO users (telegram_id_hash, encrypted_chat_id, encrypted_first_name, encrypted_username, trial_ends_at, subscription_status)
                VALUES (:telegram_id_hash, :encrypted_chat_id, :encrypted_first_name, :encrypted_username, :trial_ends_at, :subscription_status)";
        $stmt = $this->db->prepare($sql);

        $subscriptionStatus = 'free_trial';

        $stmt->bindParam(':telegram_id_hash', $hashedTelegramId);
        $stmt->bindParam(':encrypted_chat_id', $encryptedChatId);
        $stmt->bindParam(':encrypted_first_name', $encryptedFirstName);
        $stmt->bindParam(':encrypted_username', $encryptedUsername);
        $stmt->bindParam(':trial_ends_at', $trialEndsAt);
        $stmt->bindParam(':subscription_status', $subscriptionStatus);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            // Log error, handle duplicate entry if telegram_id_hash is unique and user somehow gets past findUserByTelegramId check
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }

    public function updateUserRoleAndTrial($hashedTelegramId, $encryptedRole, $trialEndsAt) {
        $sql = "UPDATE users
                SET encrypted_role = :encrypted_role,
                    trial_ends_at = :trial_ends_at,
                    subscription_status = 'free_trial',
                    updated_at = CURRENT_TIMESTAMP
                WHERE telegram_id_hash = :telegram_id_hash";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':encrypted_role', $encryptedRole);
        $stmt->bindParam(':trial_ends_at', $trialEndsAt);
        $stmt->bindParam(':telegram_id_hash', $hashedTelegramId);
        return $stmt->execute();
    }

    public function generateInvitationToken(string $hashedTelegramId): ?string {
        // Check if user already has an active partner or an active token
        $user = $this->findUserByTelegramId($hashedTelegramId);
        if (!$user || !empty($user['partner_telegram_id_hash'])) {
            // User already has a partner, cannot generate new token
            // Or user not found
            return null;
        }

        // If an old token exists, we can either reuse it, invalidate it, or let it be overwritten.
        // For simplicity, let's generate a new one. A more robust system might invalidate old ones.
        $token = bin2hex(random_bytes(16)); // 32 chars token
        $sql = "UPDATE users SET invitation_token = :invitation_token, updated_at = CURRENT_TIMESTAMP WHERE telegram_id_hash = :telegram_id_hash";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':invitation_token', $token);
        $stmt->bindParam(':telegram_id_hash', $hashedTelegramId);

        if ($stmt->execute()) {
            return $token;
        }
        return null;
    }

    public function findUserByInvitationToken(string $token) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE invitation_token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function linkPartners(string $inviterHashedId, string $accepterHashedId): bool {
        $this->db->beginTransaction();
        try {
            // Set partner_telegram_id_hash for inviter
            $sql1 = "UPDATE users SET partner_telegram_id_hash = :accepter_id, invitation_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE telegram_id_hash = :inviter_id AND partner_telegram_id_hash IS NULL";
            $stmt1 = $this->db->prepare($sql1);
            $stmt1->bindParam(':accepter_id', $accepterHashedId);
            $stmt1->bindParam(':inviter_id', $inviterHashedId);
            $stmt1->execute();

            if ($stmt1->rowCount() === 0) {
                throw new \Exception("Inviter could not be updated or already has a partner.");
            }

            // Set partner_telegram_id_hash for accepter
            $sql2 = "UPDATE users SET partner_telegram_id_hash = :inviter_id, invitation_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE telegram_id_hash = :accepter_id AND partner_telegram_id_hash IS NULL";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->bindParam(':inviter_id', $inviterHashedId);
            $stmt2->bindParam(':accepter_id', $accepterHashedId);
            $stmt2->execute();

            if ($stmt2->rowCount() === 0) {
                throw new \Exception("Accepter could not be updated or already has a partner.");
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Partner linking failed: " . $e->getMessage());
            return false;
        }
    }

    public function unlinkPartners(string $userHashedId, string $partnerHashedId): bool {
        $this->db->beginTransaction();
        try {
            // Remove partner link for the first user
            $sql1 = "UPDATE users SET partner_telegram_id_hash = NULL, updated_at = CURRENT_TIMESTAMP WHERE telegram_id_hash = :user_id AND partner_telegram_id_hash = :partner_id";
            $stmt1 = $this->db->prepare($sql1);
            $stmt1->bindParam(':user_id', $userHashedId);
            $stmt1->bindParam(':partner_id', $partnerHashedId);
            $stmt1->execute();

            // Remove partner link for the second user
            $sql2 = "UPDATE users SET partner_telegram_id_hash = NULL, updated_at = CURRENT_TIMESTAMP WHERE telegram_id_hash = :partner_id AND partner_telegram_id_hash = :user_id";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->bindParam(':partner_id', $partnerHashedId);
            $stmt2->bindParam(':user_id', $userHashedId);
            $stmt2->execute();

            // We expect both updates to affect one row if they were correctly linked
            if ($stmt1->rowCount() > 0 || $stmt2->rowCount() > 0) {
                 $this->db->commit();
                 return true;
            } else {
                // This might mean they weren't linked to each other as expected, or one side was already unlinked.
                // Depending on desired strictness, this could be an error or a soft success.
                // For now, if anything changed, consider it a success.
                $this->db->rollBack(); // Or commit if partial unlinking is acceptable. Let's be strict.
                error_log("Unlinking partners: No link found or partial link for $userHashedId and $partnerHashedId");
                return false;
            }

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Partner unlinking failed: " . $e->getMessage());
            return false;
        }
    }


    public function updateUser($hashedTelegramId, array $data) {
        // Generic update method, ensure to only pass validated and relevant data fields
        $fields = [];
        $params = [':telegram_id_hash' => $hashedTelegramId];

        if (array_key_exists('encrypted_role', $data)) { // Use array_key_exists for NULLable fields
            $fields[] = "encrypted_role = :encrypted_role";
            $params[':encrypted_role'] = $data['encrypted_role'];
        }
        if (array_key_exists('encrypted_cycle_info', $data)) {
            $fields[] = "encrypted_cycle_info = :encrypted_cycle_info";
            $params[':encrypted_cycle_info'] = $data['encrypted_cycle_info'];
        }
        if (array_key_exists('partner_telegram_id_hash', $data)) {
            $fields[] = "partner_telegram_id_hash = :partner_telegram_id_hash";
            $params[':partner_telegram_id_hash'] = $data['partner_telegram_id_hash'];
        }
         if (array_key_exists('invitation_token', $data)) {
            $fields[] = "invitation_token = :invitation_token";
            $params[':invitation_token'] = $data['invitation_token'];
        }
        if (isset($data['subscription_status'])) {
            $fields[] = "subscription_status = :subscription_status";
            $params[':subscription_status'] = $data['subscription_status'];
        }
        if (array_key_exists('subscription_ends_at', $data)) {
            $fields[] = "subscription_ends_at = :subscription_ends_at";
            $params[':subscription_ends_at'] = $data['subscription_ends_at'];
        }
        if (array_key_exists('preferred_notification_time', $data)) {
            $fields[] = "preferred_notification_time = :preferred_notification_time";
            $params[':preferred_notification_time'] = $data['preferred_notification_time'];
        }
        if (array_key_exists('referral_code', $data)) {
            $fields[] = "referral_code = :referral_code";
            $params[':referral_code'] = $data['referral_code'];
        }
        if (array_key_exists('referred_by_user_id', $data)) {
            $fields[] = "referred_by_user_id = :referred_by_user_id";
            $params[':referred_by_user_id'] = $data['referred_by_user_id'];
        }
        // Add more fields as needed

        if (empty($fields)) {
            return false; // Nothing to update
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE telegram_id_hash = :telegram_id_hash";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    // Add other methods like deleteUser, etc., as needed
}
?>
