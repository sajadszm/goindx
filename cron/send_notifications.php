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

        // error_log("Cron job: Processing user ID {$user['id']} (Chat ID: {$chatId}) for regular notifications.");
        $notificationService->processUserNotifications($user, $partnerUser); // Regular cycle/daily notifications

        // Optional: Add a small delay to avoid hitting Telegram API rate limits if sending many messages
        // usleep(100000); // 0.1 seconds

    } catch (\Exception $e) {
        error_log("Cron job: Error processing regular notifications for user_id {$user['id']}: " . $e->getMessage());
        // Continue to next user
    }
}

// --- Subscription Lifecycle Management ---
error_log("Cron job: Starting subscription lifecycle checks.");

// 1. Handle active subscriptions that have just expired
$sqlExpired = "SELECT id, telegram_id_hash, encrypted_chat_id, subscription_status FROM users WHERE subscription_status = 'active' AND subscription_ends_at <= NOW()";
$stmtExpired = $db->query($sqlExpired);
$expiredUsers = $stmtExpired->fetchAll(PDO::FETCH_ASSOC);

foreach ($expiredUsers as $expUser) {
    try {
        $chatId = EncryptionHelper::decrypt($expUser['encrypted_chat_id']);
        if ($chatId) {
            // Assuming plan name isn't easily available here, send generic expired message or find plan name.
            // For now, generic. Could enhance by joining with last transaction or storing current_plan_name.
            $notificationService->sendSubscriptionExpired($chatId, "فعلی");
        }
        $userModel->updateUser($expUser['telegram_id_hash'], ['subscription_status' => 'expired']);
        error_log("Cron job: User ID {$expUser['id']} subscription status updated to expired.");
    } catch (\Exception $e) {
        error_log("Cron job: Error processing expired subscription for user_id {$expUser['id']}: " . $e->getMessage());
    }
}

// 2. Handle active subscriptions expiring soon (e.g., in 3 days)
$warningDate = date('Y-m-d H:i:s', strtotime('+3 days'));
$sqlWarning = "SELECT id, telegram_id_hash, encrypted_chat_id, subscription_status, 'Unknown Plan' as plan_name, subscription_ends_at
               FROM users
               WHERE subscription_status = 'active' AND subscription_ends_at > NOW() AND subscription_ends_at <= :warning_date";
// TODO: Join with transactions or plans to get actual plan_name for the message
$stmtWarning = $db->prepare($sqlWarning);
$stmtWarning->bindParam(':warning_date', $warningDate);
$stmtWarning->execute();
$warningUsers = $stmtWarning->fetchAll(PDO::FETCH_ASSOC);

foreach ($warningUsers as $warnUser) {
    try {
        // Basic idempotency: check if a warning for THIS expiry date was already sent.
        // This needs a proper sent_notifications log. For a simple version, we could add a
        // `last_expiry_warning_sent_for_date` field to users table.
        // If (empty($warnUser['last_expiry_warning_sent_for_date']) || $warnUser['last_expiry_warning_sent_for_date'] !== date('Y-m-d', strtotime($warnUser['subscription_ends_at']))) {
            $chatId = EncryptionHelper::decrypt($warnUser['encrypted_chat_id']);
            if ($chatId) {
                $notificationService->sendSubscriptionWarning($chatId, $warnUser['plan_name'], $warnUser['subscription_ends_at']);
                // $userModel->updateUser($warnUser['telegram_id_hash'], ['last_expiry_warning_sent_for_date' => date('Y-m-d', strtotime($warnUser['subscription_ends_at']))]);
                 error_log("Cron job: Sent subscription expiry warning to user ID {$warnUser['id']}.");
            }
        // }
    } catch (\Exception $e) {
        error_log("Cron job: Error processing subscription warning for user_id {$warnUser['id']}: " . $e->getMessage());
    }
}


// 3. Handle free trials that have just expired
$sqlTrialExpired = "SELECT id, telegram_id_hash, encrypted_chat_id FROM users WHERE subscription_status = 'free_trial' AND trial_ends_at <= NOW()";
$stmtTrialExpired = $db->query($sqlTrialExpired);
$trialExpiredUsers = $stmtTrialExpired->fetchAll(PDO::FETCH_ASSOC);

foreach ($trialExpiredUsers as $trialExpUser) {
    try {
        $chatId = EncryptionHelper::decrypt($trialExpUser['encrypted_chat_id']);
        if ($chatId) {
            $notificationService->sendTrialExpired($chatId);
        }
        // Change status to 'none' or a specific 'trial_expired' status
        $userModel->updateUser($trialExpUser['telegram_id_hash'], ['subscription_status' => 'none']);
        error_log("Cron job: User ID {$trialExpUser['id']} free trial expired, status updated to none.");
    } catch (\Exception $e) {
        error_log("Cron job: Error processing expired trial for user_id {$trialExpUser['id']}: " . $e->getMessage());
    }
}

// 4. Handle free trials expiring soon (e.g., in 3 days)
$trialWarningDate = date('Y-m-d H:i:s', strtotime('+3 days'));
$sqlTrialWarning = "SELECT id, telegram_id_hash, encrypted_chat_id, trial_ends_at
                    FROM users
                    WHERE subscription_status = 'free_trial' AND trial_ends_at > NOW() AND trial_ends_at <= :trial_warning_date";
$stmtTrialWarning = $db->prepare($sqlTrialWarning);
$stmtTrialWarning->bindParam(':trial_warning_date', $trialWarningDate);
$stmtTrialWarning->execute();
$trialWarningUsers = $stmtTrialWarning->fetchAll(PDO::FETCH_ASSOC);

foreach ($trialWarningUsers as $trialWarnUser) {
     try {
        // Similar idempotency TODO as subscription warning
        // if (empty($trialWarnUser['last_trial_warning_sent_for_date']) || $trialWarnUser['last_trial_warning_sent_for_date'] !== date('Y-m-d', strtotime($trialWarnUser['trial_ends_at']))) {
            $chatId = EncryptionHelper::decrypt($trialWarnUser['encrypted_chat_id']);
            if ($chatId) {
                $notificationService->sendTrialEndingWarning($chatId, $trialWarnUser['trial_ends_at']);
                // $userModel->updateUser($trialWarnUser['telegram_id_hash'], ['last_trial_warning_sent_for_date' => date('Y-m-d', strtotime($trialWarnUser['trial_ends_at']))]);
                error_log("Cron job: Sent trial ending warning to user ID {$trialWarnUser['id']}.");
            }
        // }
    } catch (\Exception $e) {
        error_log("Cron job: Error processing trial warning for user_id {$trialWarnUser['id']}: " . $e->getMessage());
    }
}

error_log("Cron job: Subscription lifecycle checks finished.");
// --- End Subscription Lifecycle Management ---


error_log("Cron job: send_notifications.php finished at " . date('Y-m-d H:i:s'));
echo "Notification processing complete.\n";

?>
        // Continue to next user
    }
}

error_log("Cron job: send_notifications.php finished at " . date('Y-m-d H:i:s'));
echo "Notification processing complete.\n";

?>
