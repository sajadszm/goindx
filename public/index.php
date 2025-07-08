<?php

require_once dirname(__DIR__) . '/config/config.php';

use Controllers\UserController;
use Controllers\AdminController;
use Telegram\TelegramAPI;
use Helpers\EncryptionHelper;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$updateJson = file_get_contents('php://input');
if (!$updateJson) {
    http_response_code(400);
    echo "No input received";
    exit;
}

$update = json_decode($updateJson, true);

if (!$update) {
    error_log("Failed to decode JSON update: " . $updateJson);
    http_response_code(400);
    echo "Error decoding JSON";
    exit;
}

$telegramAPI = new TelegramAPI(TELEGRAM_BOT_TOKEN);
$userController = new UserController($telegramAPI);
$adminController = new AdminController($telegramAPI);

try {
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $userId = (string)$message['from']['id'];
        $firstName = $message['from']['first_name'] ?? '';
        $username = $message['from']['username'] ?? null;

        if (strpos($text, '/start ') === 0) {
            $parts = explode(' ', $text, 2);
            $payload = $parts[1] ?? '';
            if (strpos($payload, 'invite_') === 0) {
                $token = substr($payload, strlen('invite_'));
                $userController->handleAcceptInvitationCommand($userId, $chatId, $firstName, $username, $token);
            } else {
                $userController->handleStart($userId, $chatId, $firstName, $username);
            }
        } elseif (!empty($text)) {
            $hashedTelegramId = EncryptionHelper::hashIdentifier($userId);
            $currentUser = $userController->getUserModel()->findUserByTelegramId($hashedTelegramId);

            if ($currentUser && !empty($currentUser['user_state'])) {
                $userStateJson = $currentUser['user_state'];
                $stateInfo = json_decode($userStateJson, true);
                $stateAction = (is_array($stateInfo) && isset($stateInfo['action'])) ? $stateInfo['action'] : $userStateJson;

                if ($text === '/cancel') {
                    $userController->getUserModel()->updateUser($hashedTelegramId, ['user_state' => null]);
                    $telegramAPI->sendMessage($chatId, "عملیات لغو شد.");
                    if ($stateAction === 'awaiting_support_message') { // If cancelling support, show main menu
                         $userController->showMainMenu($chatId);
                    } elseif (strpos($stateAction, 'admin_') === 0 && $userId === ADMIN_TELEGRAM_ID) { // If admin cancelling admin action
                         $adminController->showAdminMenu($userId, $chatId, null);
                    } else { // Default for other user states that might use /cancel
                        $userController->showMainMenu($chatId);
                    }
                } elseif ($text === '/cancel_admin_action' && strpos($stateAction, 'admin_') === 0 && $userId === ADMIN_TELEGRAM_ID) {
                     $userController->getUserModel()->updateUser($hashedTelegramId, ['user_state' => null]);
                     $telegramAPI->sendMessage($chatId, "عملیات ادمین لغو شد.");
                     $adminController->showAdminMenu($userId, $chatId, null);
                } elseif ($stateAction === 'awaiting_support_message') {
                    $userController->handleForwardSupportMessage($userId, $chatId, $text, $firstName, $username);
                } elseif ($stateAction === 'admin_awaiting_plan_add' && $userId === ADMIN_TELEGRAM_ID) {
                    $adminController->handleAddSubscriptionPlanDetails($userId, $chatId, $text);
                } elseif (($stateAction === 'admin_add_content' || $stateAction === 'admin_edit_content') && $userId === ADMIN_TELEGRAM_ID && is_array($stateInfo)) {
                    $adminController->handleAdminConversation($userId, $chatId, $text, $stateInfo);
                } elseif ($stateAction === 'awaiting_referral_code') {
                    $userController->handleProcessReferralCode($userId, $chatId, $text, $firstName, $username);
                } else {
                    error_log("User {$userId} in unhandled state: {$userStateJson}. Clearing state.");
                    $userController->getUserModel()->updateUser($hashedTelegramId, ['user_state' => null]);
                    $userController->handleStart($userId, $chatId, $firstName, $username);
                }
            } elseif ($text === '/start') {
                $userController->handleStart($userId, $chatId, $firstName, $username);
            } else {
                $userController->handleStart($userId, $chatId, $firstName, $username);
            }
        }

    } elseif (isset($update['callback_query'])) {
        $callbackQuery = $update['callback_query'];
        $userId = (string)$callbackQuery['from']['id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $callbackData = $callbackQuery['data'];
        $callbackQueryId = $callbackQuery['id'];
        $messageId = $callbackQuery['message']['message_id'];

        $telegramAPI->answerCallbackQuery($callbackQueryId);

        $parts = explode(':', $callbackData, 2);
        $action = $parts[0];
        $value = $parts[1] ?? null;

        if (strpos($action, 'admin_') === 0) {
            if ($userId !== ADMIN_TELEGRAM_ID) {
                $telegramAPI->sendMessage($chatId, "شما اجازه دسترسی به این دستور ادمین را ندارید.");
            } else {
                // Admin actions
                switch ($action) {
                    case 'admin_show_menu':
                        $adminController->showAdminMenu($userId, $chatId, $messageId);
                        break;
                    // ... other admin cases ...
                    case 'admin_plans_show_list':
                        $adminController->showSubscriptionPlansAdmin($userId, $chatId, $messageId);
                        break;
                    case 'admin_plan_prompt_add':
                        $adminController->promptAddSubscriptionPlan($userId, $chatId, $messageId);
                        break;
                    case 'admin_plan_toggle_active':
                        list($planIdVal, $newStateVal) = explode('_', $value);
                        $adminController->handleTogglePlanActive($userId, $chatId, $messageId, (int)$planIdVal, (int)$newStateVal);
                        break;
                    case 'admin_content_show_menu':
                        $adminController->showContentAdminMenu($userId, $chatId, $messageId);
                        break;
                    case 'admin_content_list_topics':
                        $adminController->listTutorialTopicsAdmin($userId, $chatId, $messageId);
                        break;
                    case 'admin_content_list_articles':
                        $adminController->listArticlesInTopicAdmin($userId, $chatId, $messageId, (int)$value);
                        break;
                    case 'admin_content_prompt_add':
                        list($contentTypeVal, $parentIdVal) = explode('_', $value);
                        $adminController->promptAddContent($userId, $chatId, $messageId, $contentTypeVal, (int)$parentIdVal);
                        break;
                    case 'admin_content_prompt_edit':
                        $adminController->promptEditEducationalContent($userId, $chatId, $messageId, (int)$value);
                        break;
                    case 'admin_content_setparam':
                        list($fieldName, $fieldVal) = explode('_', $value, 2);
                        $adminController->handleAdminContentSetParam($userId, $chatId, $messageId, $fieldName, $fieldVal);
                        break;
                    case 'admin_content_confirm_delete':
                        $adminController->confirmDeleteEducationalContent($userId, $chatId, $messageId, (int)$value);
                        break;
                    case 'admin_content_do_delete':
                        $adminController->handleDeleteEducationalContent($userId, $chatId, $messageId, (int)$value);
                        break;
                    default:
                        error_log("Unknown admin callback action: " . $action . " Full data: " . $callbackData);
                        $telegramAPI->sendMessage($chatId, "دستور ادمین ناشناخته.");
                        break;
                }
            }
        } else {
            // User actions
            $userParts = explode(':', $callbackData);
            $action = $userParts[0];
            $val1 = $userParts[1] ?? null;
            $val2 = $userParts[2] ?? null;
            $val3 = $userParts[3] ?? null;

            switch ($action) {
                case 'select_role':
                    $userController->handleRoleSelection($userId, $chatId, $val1, $messageId);
                    break;
                // ... other user cases ...
                case 'partner_invite':
                    $userController->handleGenerateInvitation($userId, $chatId, $messageId);
                    break;
                case 'partner_cancel_invite':
                    $userController->handleCancelInvitation($userId, $chatId, $messageId);
                    break;
                case 'partner_accept_prompt':
                    $userController->handleAcceptInvitationPrompt($userId, $chatId);
                    break;
                case 'partner_disconnect':
                    $userController->handleDisconnectPartner($userId, $chatId);
                    break;
                case 'partner_disconnect_confirm':
                    $userController->handleDisconnectPartnerConfirm($userId, $chatId, $messageId);
                    break;
                case 'main_menu_show':
                case 'main_menu_show_direct': // Handles direct to main menu after skipping referral
                    $userController->showMainMenu($chatId);
                    break;
                case 'cycle_log_period_start_prompt':
                    $userController->handleLogPeriodStartPrompt($userId, $chatId, $messageId);
                    break;
                case 'cycle_pick_year':
                    $userController->handleCyclePickYear($userId, $chatId, $messageId);
                    break;
                case 'cycle_select_year':
                    $userController->handleCycleSelectYear($userId, $chatId, $messageId, $val1);
                    break;
                case 'cycle_select_month':
                    $userController->handleCycleSelectMonth($userId, $chatId, $messageId, $val1, $val2);
                    break;
                case 'cycle_select_day':
                    $userController->handleCycleLogDate($userId, $chatId, $messageId, $val1, $val2, $val3);
                    break;
                case 'cycle_log_date':
                    $userController->handleCycleLogDate($userId, $chatId, $messageId, $val1);
                    break;
                case 'cycle_set_avg_period':
                    $userController->handleSetAveragePeriodLength($userId, $chatId, $messageId, $val1);
                    break;
                case 'cycle_set_avg_cycle':
                    $userController->handleSetAverageCycleLength($userId, $chatId, $messageId, $val1);
                    break;
                case 'cycle_skip_avg_period':
                     $userController->handleSkipAverageInfo($userId, $chatId, $messageId, 'period');
                     break;
                case 'cycle_skip_avg_cycle':
                    $userController->handleSkipAverageInfo($userId, $chatId, $messageId, 'cycle');
                    break;
                case 'symptom_log_start':
                    $userController->handleLogSymptomStart($userId, $chatId, $messageId, $val1);
                    break;
                case 'symptom_show_cat':
                    $userController->handleSymptomShowCategory($userId, $chatId, $messageId, $val1, $val2);
                    break;
                case 'symptom_toggle':
                    $userController->handleSymptomToggle($userId, $chatId, $messageId, $val1, $val2, $val3);
                    break;
                case 'symptom_save_final':
                    $userController->handleSymptomSaveFinal($userId, $chatId, $messageId, $val1);
                    break;
                case 'settings_show':
                    $userController->handleSettings($userId, $chatId, $messageId);
                    break;
                case 'settings_set_notify_time_prompt':
                    $userController->handleSetNotificationTimePrompt($userId, $chatId, $messageId);
                    break;
                case 'settings_set_notify_time':
                    $userController->handleSetNotificationTime($userId, $chatId, $messageId, $val1);
                    break;
                case 'show_guidance':
                    $userController->handleShowGuidance($userId, $chatId, $messageId);
                    break;
                case 'support_request_start':
                    $userController->handleSupportRequestStart($userId, $chatId, $messageId);
                    break;
                case 'show_about_us':
                    $userController->handleShowAboutUs($userId, $chatId, $messageId);
                    break;
                case 'sub_show_plans':
                    $userController->handleShowSubscriptionPlans($userId, $chatId, $messageId);
                    break;
                case 'sub_select_plan':
                    $userController->handleSubscribePlan($userId, $chatId, $messageId, $val1);
                    break;
                case 'user_show_tutorial_topics':
                    $userController->handleShowTutorialTopics($userId, $chatId, $messageId);
                    break;
                case 'user_show_tutorial_topic_content':
                    $userController->handleShowTutorialTopicContent($userId, $chatId, $messageId, (int)$val1);
                    break;
                case 'user_show_tutorial_article':
                    $userController->handleShowTutorialArticle($userId, $chatId, $messageId, (int)$val1);
                    break;
                case 'user_enter_referral_code_prompt':
                    $userController->handleEnterReferralCodePrompt($userId, $chatId, $messageId);
                    break;
                case 'user_delete_account_prompt': // New
                    $userController->handleDeleteAccountPrompt($userId, $chatId, $messageId);
                    break;
                case 'user_delete_account_confirm': // New
                    $userController->handleDeleteAccountConfirm($userId, $chatId, $messageId);
                    break;
                default:
                    error_log("Unknown user callback action: " . $action . " Full data: " . $callbackData);
                    break;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error processing update: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (isset($chatId)) {
       // $telegramAPI->sendMessage($chatId, "متاسفانه مشکلی پیش آمده. لطفا دوباره تلاش کنید.");
    }
}
?>
