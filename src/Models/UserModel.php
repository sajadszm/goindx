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
        if (array_key_exists('user_state', $data)) { // Ensure user_state is handled
            $fields[] = "user_state = :user_state";
            $params[':user_state'] = $data['user_state'];
        }
        // Add more fields as needed

        // Log built fields before checking if empty
        error_log("UserModel::updateUser - Data received: " . json_encode($data));
        error_log("UserModel::updateUser - Fields constructed for SQL: " . json_encode($fields));

        if (empty($fields)) {
            error_log("UserModel::updateUser - No valid fields to update for user {$hashedTelegramId}. Params: " . json_encode($params) . " Data: " . json_encode($data));
            return false; // Nothing to update
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE telegram_id_hash = :telegram_id_hash";

        error_log("UserModel::updateUser - SQL: " . $sql);
        error_log("UserModel::updateUser - Params: " . json_encode($params));

        $stmt = $this->db->prepare($sql);

        try {
            $success = $stmt->execute($params);
            if (!$success) {
                error_log("UserModel::updateUser - execute() returned false. ErrorInfo: " . json_encode($stmt->errorInfo()));
            } else {
                error_log("UserModel::updateUser - execute() successful. Rows affected: " . $stmt->rowCount());
            }
            return $success;
        } catch (\PDOException $e) {
            error_log("UserModel::updateUser - PDOException: " . $e->getMessage());
            return false;
        }
    }

    // Add other methods like deleteUser, etc., as needed

    // --- Referral Program Methods ---

    public function generateReferralCode(int $userId): ?string {
        $user = $this->findUserById($userId);
        if (!$user) return null;

        if (!empty($user['referral_code'])) {
            return $user['referral_code'];
        }

        // Generate a unique 6-8 character alphanumeric code
        $isUnique = false;
        $newCode = '';
        $maxTries = 10; // Prevent infinite loop
        $tryCount = 0;
        while (!$isUnique && $tryCount < $maxTries) {
            $newCode = substr(str_shuffle(str_repeat('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', 8)), 0, 8);
            $stmt = $this->db->prepare("SELECT id FROM users WHERE referral_code = :code");
            $stmt->bindParam(':code', $newCode);
            $stmt->execute();
            if (!$stmt->fetch()) {
                $isUnique = true;
            }
            $tryCount++;
        }

        if (!$isUnique) {
            error_log("Failed to generate a unique referral code for user {$userId} after {$maxTries} tries.");
            return null; // Could not generate a unique code
        }

        $sql = "UPDATE users SET referral_code = :code WHERE id = :id AND referral_code IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':code', $newCode);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            return $newCode;
        }
        // If rowCount is 0, it might mean referral_code was set by a concurrent process, re-fetch.
        $updatedUser = $this->findUserById($userId);
        return $updatedUser['referral_code'] ?? null;
    }

    public function findUserByReferralCode(string $code) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE referral_code = :code");
        $stmt->bindParam(':code', $code);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Applies bonus days to a user. Extends trial or active subscription.
     * @param int $userId
     * @param int $bonusDays
     * @return bool
     */
    public function applyReferralBonus(int $userId, int $bonusDays): bool {
        $user = $this->findUserById($userId);
        if (!$user) return false;

        $newEndDate = null;
        $updateField = null;

        if ($user['subscription_status'] === 'active' && !empty($user['subscription_ends_at'])) {
            $currentEnd = new \DateTime($user['subscription_ends_at']);
            // If subscription is already past, bonus starts from now. Otherwise, extend.
            $baseDate = ($currentEnd < new \DateTime()) ? new \DateTime() : $currentEnd;
            $baseDate->add(new \DateInterval("P{$bonusDays}D"));
            $newEndDate = $baseDate->format('Y-m-d H:i:s');
            $updateField = 'subscription_ends_at';
        } elseif ($user['subscription_status'] === 'free_trial' && !empty($user['trial_ends_at'])) {
            $currentEnd = new \DateTime($user['trial_ends_at']);
            $baseDate = ($currentEnd < new \DateTime()) ? new \DateTime() : $currentEnd;
            $baseDate->add(new \DateInterval("P{$bonusDays}D"));
            $newEndDate = $baseDate->format('Y-m-d H:i:s');
            $updateField = 'trial_ends_at';
        } else { // No active sub or trial (e.g. status 'none' or 'expired')
            // Start a new trial period with bonus days
            $baseDate = new \DateTime();
            $baseDate->add(new \DateInterval("P{$bonusDays}D"));
            $newEndDate = $baseDate->format('Y-m-d H:i:s');

            $sql = "UPDATE users SET trial_ends_at = :new_end_date, subscription_status = 'free_trial', updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':new_end_date', $newEndDate);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        }

        if ($updateField && $newEndDate) {
            $sql = "UPDATE users SET {$updateField} = :new_end_date, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':new_end_date', $newEndDate);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        }
        return false;
    }

    public function countReferralsThisMonth(int $referrerUserId): int {
        $firstDayOfMonth = date('Y-m-01 00:00:00');
        $lastDayOfMonth = date('Y-m-t 23:59:59'); // 't' gives number of days in month

        $sql = "SELECT COUNT(id) as referral_count FROM users
                WHERE referred_by_user_id = :referrer_id
                AND created_at >= :start_date AND created_at <= :end_date";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':referrer_id', $referrerUserId, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $firstDayOfMonth);
        $stmt->bindParam(':end_date', $lastDayOfMonth);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['referral_count'] : 0;
    }

    /**
     * Deletes a user account and handles related data cleanup.
     * @param int $userId The internal ID of the user to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteUserAccount(int $userId): bool {
        $this->db->beginTransaction();
        try {
            // 1. Find the user to get their hashed ID and partner's hashed ID (if any)
            $userToDelete = $this->findUserById($userId);
            if (!$userToDelete) {
                $this->db->rollBack();
                error_log("DeleteUserAccount: User with ID {$userId} not found.");
                return false; // User not found
            }
            $userHashedId = $userToDelete['telegram_id_hash'];
            $partnerHashedId = $userToDelete['partner_telegram_id_hash'];

            // 2. If partnered, unlink the partner first
            if ($partnerHashedId) {
                $stmtUnlinkPartner = $this->db->prepare(
                    "UPDATE users SET partner_telegram_id_hash = NULL, updated_at = CURRENT_TIMESTAMP
                     WHERE telegram_id_hash = :partner_hashed_id AND partner_telegram_id_hash = :user_hashed_id"
                );
                $stmtUnlinkPartner->bindParam(':partner_hashed_id', $partnerHashedId);
                $stmtUnlinkPartner->bindParam(':user_hashed_id', $userHashedId);
                $stmtUnlinkPartner->execute();
            }

            // 3. Delete the user record from the 'users' table.
            // Foreign key constraints should handle related data:
            // - `logged_symptoms` (ON DELETE CASCADE)
            // - `transactions` (ON DELETE CASCADE)
            // - `users.referred_by_user_id` (ON DELETE SET NULL for other users who were referred by this user)
            $stmtDeleteUser = $this->db->prepare("DELETE FROM users WHERE id = :user_id");
            $stmtDeleteUser->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $deleteSuccess = $stmtDeleteUser->execute();

            if (!$deleteSuccess) {
                throw new \Exception("Failed to delete user record for ID {$userId}");
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error deleting user account for ID {$userId}: " . $e->getMessage());
            return false;
        }
    }

    // --- Admin Statistics Methods ---
    public function getTotalUserCount(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        return (int)$stmt->fetchColumn();
    }

    public function getActiveSubscriptionCount(): int {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE subscription_status = 'active' AND subscription_ends_at > :now");
        $stmt->bindParam(':now', $now);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function getActiveFreeTrialCount(): int {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE subscription_status = 'free_trial' AND trial_ends_at > :now");
        $stmt->bindParam(':now', $now);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function getPartnerConnectedCount(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE partner_telegram_id_hash IS NOT NULL");
        return (int)$stmt->fetchColumn() / 2; // Each connection involves two users
    }

    public function getTotalReferredUsersCount(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE referred_by_user_id IS NOT NULL");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Retrieves all users for broadcast purposes.
     * Fetches only necessary fields like encrypted_chat_id.
     * @return array List of users with their encrypted_chat_id.
     */
    public function getAllUsersForBroadcast(): array {
        // Removed "WHERE is_bot_blocked = 0" for now to ensure functionality
        // TODO: Consider re-adding is_bot_blocked logic later if the column exists or is added.
        $stmt = $this->db->query("SELECT id, encrypted_chat_id FROM users");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves user by their Telegram ID (hashed or unhashed depending on input)
     * This is more flexible for admin finding users.
     * @param string $telegramId Can be the actual ID or the hashed ID.
     * @return array|false User data or false if not found.
     */
    public function findUserByActualOrHashedTelegramId(string $telegramIdToFind) {
        // Try finding by direct hash first
        $user = $this->findUserByTelegramId($telegramIdToFind);
        if ($user) {
            return $user;
        }
        // If not found, hash the input and try again (assuming input might be an unhashed ID)
        if (ctype_digit($telegramIdToFind)) { // Check if it looks like a raw ID
            $hashedInput = EncryptionHelper::hashIdentifier($telegramIdToFind);
             if ($hashedInput === $telegramIdToFind) return false; // Avoid infinite loop if hash is same as input
            return $this->findUserByTelegramId($hashedInput);
        }
        return false; // Not found
    }
     /**
     * Retrieves user by their username (exact match, case-insensitive for some DBs by default).
     * Username is stored encrypted. This method requires iterating if not directly queryable.
     * For large user bases, direct query on an unencrypted username column (if acceptable) or a search index would be better.
     * This is a simplified version that might be slow.
     *
     * @param string $username
     * @return array|false User data or false if not found.
     */
    public function findUserByUsername(string $usernameToFind): ?array {
        // This is inefficient for large tables as it fetches all and decrypts.
        // In a real large-scale app, consider storing a hashed version of username for lookup
        // or using a proper search solution if usernames need to be frequently searched.
        // For now, this is a placeholder for small-scale.
        $stmt = $this->db->query("SELECT * FROM users WHERE encrypted_username IS NOT NULL");
        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                $decryptedUsername = EncryptionHelper::decrypt($user['encrypted_username']);
                if (strcasecmp($decryptedUsername, $usernameToFind) === 0) {
                    return $user;
                }
            } catch (\Exception $e) {
                // Decryption failed, skip this user or log error
            }
        }
        return null;
    }


    /**
     * Fetches user's state (JSON string or specific array structure).
     * @param string $hashedTelegramId
     * @return mixed User state (array if JSON, string otherwise) or null.
     */
    public function getUserState(string $hashedTelegramId) {
        $stmt = $this->db->prepare("SELECT user_state FROM users WHERE telegram_id_hash = :hashed_id");
        $stmt->bindParam(':hashed_id', $hashedTelegramId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['user_state']) {
            $decoded = json_decode($result['user_state'], true);
            return $decoded ?: $result['user_state']; // Return array if valid JSON, else raw string
        }
        return null;
    }


}
?>
