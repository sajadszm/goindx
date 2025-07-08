<?php

require_once dirname(__DIR__) . '/config/config.php'; // Defines BASE_PATH and loads autoloader

use Controllers\UserController;
use Controllers\AdminController;
use Telegram\TelegramAPI;
use Helpers\InputHelper;

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
        $userId = (string)$message['from']['id']; // Ensure userId is string for comparisons
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
            $hashedTelegramId = \Helpers\EncryptionHelper::hashIdentifier($userId);
            $currentUser = $userController->getUserModel()->findUserByTelegramId($hashedTelegramId);

            if ($currentUser && isset($currentUser['user_state'])) {
                $userState = $currentUser['user_state'];

                if ($text === '/cancel_admin_action' && strpos($userState, 'admin_') === 0 && $userId === ADMIN_TELEGRAM_ID) {
                     $userController->getUserModel()->updateUser($hashedTelegramId, ['user_state' => null]);
                     $telegramAPI->sendMessage($chatId, "عملیات ادمین لغو شد.");
                     $adminController->showAdminMenu($userId, $chatId, null);
                } elseif ($userState === 'awaiting_support_message') {
                    if ($text === '/cancel') {
                        $userController->getUserModel()->updateUser($hashedTelegramId, ['user_state' => null]);
                        $telegramAPI->sendMessage($chatId, "ارسال پیام به پشتیبانی لغو شد.");
                        $userController->showMainMenu($chatId);
                    } else {
                        $userController->handleForwardSupportMessage($userId, $chatId, $text, $firstName, $username);
                    }
                } elseif ($userState === 'admin_awaiting_plan_add' && $userId === ADMIN_TELEGRAM_ID) {
                    $adminController->handleAddSubscriptionPlanDetails($userId, $chatId, $text);
                }
                // Add other states like admin_awaiting_plan_edit_details:PLAN_ID here later
                else {
                    // Fallback for unhandled states or if non-admin is in an admin state somehow
                    $userController->getUserModel()->updateUser($hashedTelegramId, ['user_state' => null]); // Clear potentially stuck state
                    $userController->handleStart($userId, $chatId, $firstName, $username);
                }
            } elseif ($text === '/start') {
                $userController->handleStart($userId, $chatId, $firstName, $username);
            } else {
                // Any other text, treat as start/show main menu
                $userController->handleStart($userId, $chatId, $firstName, $username);
            }
        }
        // If $text is empty (media message without caption), it's ignored by this logic.

    } elseif (isset($update['callback_query'])) {
        $callbackQuery = $update['callback_query'];
        $userId = (string)$callbackQuery['from']['id']; // Ensure userId is string
        $chatId = $callbackQuery['message']['chat']['id'];
        $callbackData = $callbackQuery['data'];
        $callbackQueryId = $callbackQuery['id'];
        $messageId = $callbackQuery['message']['message_id'];

        $telegramAPI->answerCallbackQuery($callbackQueryId);

        $parts = explode(':', $callbackData);
        $action = $parts[0];
        $value = $parts[1] ?? null;
        $param2 = $parts[2] ?? null;
        $param3 = $parts[3] ?? null;

        if (strpos($action, 'admin_') === 0) {
            if ($userId !== ADMIN_TELEGRAM_ID) {
                $telegramAPI->sendMessage($chatId, "شما اجازه دسترسی به این دستور ادمین را ندارید.");
            } else {
                switch ($action) {
                    case 'admin_show_menu':
                        $adminController->showAdminMenu($userId, $chatId, $messageId);
                        break;
                    case 'admin_plans_show_list':
                        $adminController->showSubscriptionPlansAdmin($userId, $chatId, $messageId);
                        break;
                    case 'admin_plan_prompt_add':
                        $adminController->promptAddSubscriptionPlan($userId, $chatId, $messageId);
                        break;
                    case 'admin_plan_toggle_active':
                        $adminController->handleTogglePlanActive($userId, $chatId, $messageId, (int)$value, (int)$param2);
                        break;
                    default:
                        error_log("Unknown admin callback action: " . $action . " Full data: " . $callbackData);
                        $telegramAPI->sendMessage($chatId, "دستور ادمین ناشناخته.");
                        break;
                }
            }
        } else {
            // User actions
            switch ($action) {
                case 'select_role':
                    $userController->handleRoleSelection($userId, $chatId, $value, $messageId);
                    break;
                case 'partner_invite':
                    $userController->handleGenerateInvitation((string)$userId, $chatId, $messageId);
                    break;
                case 'partner_cancel_invite':
                    $userController->handleCancelInvitation((string)$userId, $chatId, $messageId);
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
                    $userController->showMainMenu($chatId);
                    break;
                case 'cycle_log_period_start_prompt':
                    $userController->handleLogPeriodStartPrompt($userId, $chatId, $messageId);
                    break;
                case 'cycle_pick_year':
                    $userController->handleCyclePickYear($userId, $chatId, $messageId);
                    break;
                case 'cycle_select_year':
                    $userController->handleCycleSelectYear($userId, $chatId, $messageId, $value);
                    break;
                case 'cycle_select_month':
                    list($year, $month) = explode(':', $value);
                    $userController->handleCycleSelectMonth($userId, $chatId, $messageId, $year, $month);
                    break;
                case 'cycle_select_day':
                    list($year, $month, $day) = explode(':', $value);
                    $userController->handleCycleLogDate($userId, $chatId, $messageId, $year, $month, $day);
                    break;
                case 'cycle_log_date':
                    $userController->handleCycleLogDate($userId, $chatId, $messageId, $value);
                    break;
                case 'cycle_set_avg_period':
                    $userController->handleSetAveragePeriodLength($userId, $chatId, $messageId, $value);
                    break;
                case 'cycle_set_avg_cycle':
                    $userController->handleSetAverageCycleLength($userId, $chatId, $messageId, $value);
                    break;
                case 'cycle_skip_avg_period':
                     $userController->handleSkipAverageInfo($userId, $chatId, $messageId, 'period');
                     break;
                case 'cycle_skip_avg_cycle':
                    $userController->handleSkipAverageInfo($userId, $chatId, $messageId, 'cycle');
                    break;
                case 'symptom_log_start':
                    $userController->handleLogSymptomStart($userId, $chatId, $messageId, $value);
                    break;
                case 'symptom_show_cat':
                    list($dateOpt, $catKey) = explode(':', $value);
                    $userController->handleSymptomShowCategory($userId, $chatId, $messageId, $dateOpt, $catKey);
                    break;
                case 'symptom_toggle':
                    list($dateOpt, $catKey, $symKey) = explode(':', $value, 3); // Ensure 3 parts for symptomKey
                    $userController->handleSymptomToggle($userId, $chatId, $messageId, $dateOpt, $catKey, $symKey);
                    break;
                case 'symptom_save_final':
                    $userController->handleSymptomSaveFinal($userId, $chatId, $messageId, $value);
                    break;
                case 'settings_show':
                    $userController->handleSettings($userId, $chatId, $messageId);
                    break;
                case 'settings_set_notify_time_prompt':
                    $userController->handleSetNotificationTimePrompt($userId, $chatId, $messageId);
                    break;
                case 'settings_set_notify_time':
                    $userController->handleSetNotificationTime($userId, $chatId, $messageId, $value);
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
                    $userController->handleSubscribePlan($userId, $chatId, $messageId, $value);
                    break;
                default:
                    error_log("Unknown callback action: " . $action . " Full data: " . $callbackData);
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
