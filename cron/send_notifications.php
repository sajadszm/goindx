<?php

// cron/send_notifications.php
// This script is intended to be run by a system cron job (e.g., every minute or every 5 minutes).

require_once dirname(__DIR__) . '/config/config.php'; // Defines BASE_PATH, loads autoloader, defines constants

use Models\UserModel;
use Models\EducationalContentModel;
use Models\SymptomModel;
use Telegram\TelegramAPI;
use Services\NotificationService;
use Helpers\EncryptionHelper;

// Basic security: Ensure this script is run from CLI or a trusted environment
if (php_sapi_name() !== 'cli' && !defined('TRUSTED_CRON_RUNNER')) {
    http_response_code(403);
    die("Access Denied: This script is intended for CLI execution or trusted runners only.\n");
}

error_log("Cron job: send_notifications.php started at " . date('Y-m-d H:i:s'));

$userModel = new UserModel();
$telegramAPI = new TelegramAPI(TELEGRAM_BOT_TOKEN);
$educationalContentModel = new EducationalContentModel();
$symptomModel = new SymptomModel();
$notificationService = new NotificationService($userModel, $telegramAPI, $educationalContentModel, $symptomModel);

// Fetch users who are eligible for notifications
// Eligibility criteria:
// 1. Active subscription or still in free trial.
// 2. Opted-in to receive notifications (if such a setting exists - not yet implemented).
// 3. Have a valid encrypted_chat_id.
// 4. (Optional) Match preferred notification time (more complex, for later refinement).
//    For now, we process all eligible users, and NotificationService might check time internally if needed.

$sql = "SELECT * FROM users
        WHERE (subscription_status = 'active' OR (subscription_status = 'free_trial' AND trial_ends_at > NOW()))
        AND encrypted_chat_id IS NOT NULL";
// Add preferred_notification_time logic here if implemented:
$currentHour = date('H'); // Get current hour in server's timezone (Asia/Tehran)
$sql .= " AND (preferred_notification_time IS NULL OR HOUR(preferred_notification_time) = :current_hour)";
// If preferred_notification_time is NULL, we might send at a default time or not at all.
// For now, let's assume NULL means send at any run (e.g. a default morning run if cron runs once a day)
// or modify to only send if preferred_notification_time IS NOT NULL.
// A safer bet for daily messages is to require a preferred time.
// Let's refine: only send if preferred_notification_time is set AND matches current hour.
// So, the cron should run hourly.

$db = Database::getInstance()->getConnection();
// $stmt = $db->query($sql); // Old query without placeholder
$stmt = $db->prepare($sql);
$stmt->bindParam(':current_hour', $currentHour, PDO::PARAM_STR); // Bind current hour as string 'HH'
$stmt->execute();
$activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($activeUsers)) {
    error_log("Cron job: No active users found for notifications.");
    exit("No active users for notifications.\n");
}

error_log("Cron job: Found " . count($activeUsers) . " users to process for notifications.");

foreach ($activeUsers as $user) {
    try {
        // Decrypt chat_id for sending messages
        $chatId = null;
        if (!empty($user['encrypted_chat_id'])) {
            $chatId = EncryptionHelper::decrypt($user['encrypted_chat_id']);
        }

        if (!$chatId) {
            error_log("Cron job: Could not decrypt chat_id for user_id: {$user['id']}. Skipping.");
            continue;
        }

        // Add the decrypted chat_id to the user array for NotificationService
        $user['decrypted_chat_id'] = $chatId;

        $partnerUser = null;
        if (!empty($user['partner_telegram_id_hash'])) {
            $partnerUserData = $userModel->findUserByTelegramId($user['partner_telegram_id_hash']);
            if ($partnerUserData && !empty($partnerUserData['encrypted_chat_id'])) {
                $partnerChatId = EncryptionHelper::decrypt($partnerUserData['encrypted_chat_id']);
                if ($partnerChatId) {
                    $partnerUserData['decrypted_chat_id'] = $partnerChatId;
                    $partnerUser = $partnerUserData;
                }
            }
        }

        // error_log("Cron job: Processing user ID {$user['id']} (Chat ID: {$chatId})");
        $notificationService->processUserNotifications($user, $partnerUser);

        // Optional: Add a small delay to avoid hitting Telegram API rate limits if sending many messages
        // usleep(200000); // 0.2 seconds

    } catch (\Exception $e) {
        error_log("Cron job: Error processing notifications for user_id {$user['id']}: " . $e->getMessage());
        // Continue to next user
    }
}

error_log("Cron job: send_notifications.php finished at " . date('Y-m-d H:i:s'));
echo "Notification processing complete.\n";

?>
