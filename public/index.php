<?php

require_once dirname(__DIR__) . '/config/config.php';

use Controllers\UserController;
use Controllers\AdminController;
use Controllers\SupportController;
use Models\UserModel;
use Models\SupportTicketModel;
use Telegram\TelegramAPI;
use Helpers\EncryptionHelper;

// Basic check to ensure the script is called via POST, common for webhooks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    error_log("Access attempt with invalid method: " . $_SERVER['REQUEST_METHOD']);
    echo "Method Not Allowed";
    exit;
}

$updateJson = file_get_contents('php://input');
if (!$updateJson) {
    http_response_code(400); // Bad Request
    error_log("No input received from Telegram.");
    echo "No input received";
    exit;
}

$update = json_decode($updateJson, true);

if (!$update) {
    error_log("Failed to decode JSON update: " . $updateJson);
    http_response_code(400); // Bad Request
    echo "Error decoding JSON";
    exit;
}

// error_log("Received update: " . $updateJson); // Log all updates for debugging

$telegramAPI = new TelegramAPI(TELEGRAM_BOT_TOKEN);
$userModel = new UserModel();
$userController = new UserController($telegramAPI); // UserController might need UserModel if it doesn't instantiate its own
$adminController = new AdminController($telegramAPI); // AdminController might need various models
$supportTicketModel = new SupportTicketModel();
$supportController = new SupportController($telegramAPI, $supportTicketModel, $userModel);


try {
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $userId = (string)$message['from']['id'];
        $firstName = $message['from']['first_name'] ?? 'کاربر';
        $username = $message['from']['username'] ?? null;

        // Handle /start command with potential payload (for invitations)
        if (strpos($text, '/start ') === 0) {
            $parts = explode(' ', $text, 2);
            $payload = $parts[1] ?? '';
            if (strpos($payload, 'invite_') === 0) {
                $token = substr($payload, strlen('invite_'));
                $userController->handleAcceptInvitationCommand($userId, $chatId, $firstName, $username, $token);
            } else {
                // Potentially other payload types or just /start
                $userController->handleStart($userId, $chatId, $firstName, $username);
            }
        } elseif (!empty($text)) {
            // Handle text messages based on user state
            $hashedTelegramId = EncryptionHelper::hashIdentifier($userId);
            $currentUserStateInfo = $userModel->getUserState($hashedTelegramId); // Use the instantiated $userModel

            $stateAction = null;
            $stateData = [];

            if ($currentUserStateInfo) { // $currentUserStateInfo can be string or array from getUserState
                if(is_array($currentUserStateInfo) && isset($currentUserStateInfo['action'])) {
                    $stateAction = $currentUserStateInfo['action'];
                    $stateData = $currentUserStateInfo; // Pass full state info
                } elseif (is_string($currentUserStateInfo)) {
                    $stateAction = $currentUserStateInfo; // Old simple string state
                }
            }

            if ($text === '/cancel') {
                $userModel->updateUser($hashedTelegramId, ['user_state' => null]);
                $telegramAPI->sendMessage($chatId, "عملیات لغو شد.");
                // Determine where to go back based on state if needed
                if (strpos((string)$stateAction, 'admin_') === 0 && $userId === ADMIN_TELEGRAM_ID) {
                     $adminController->showAdminMenu($userId, $chatId, null);
                } else {
                    $userController->showMainMenu($chatId);
                }
            } elseif ($text === '/cancel_admin_action' && strpos((string)$stateAction, 'admin_') === 0 && $userId === ADMIN_TELEGRAM_ID) {
                 $userModel->updateUser($hashedTelegramId, ['user_state' => null]);
                 $telegramAPI->sendMessage($chatId, "عملیات ادمین لغو شد.");
                 $adminController->showAdminMenu($userId, $chatId, null);
            } elseif ($stateAction === 'awaiting_initial_support_message' || ($stateAction === 'awaiting_user_reply_to_ticket' && isset($stateData['ticket_id']))) {
                $supportController->handleUserMessage($userId, $chatId, $text, $firstName, $username);
            } elseif ($stateAction === 'admin_awaiting_plan_add' && $userId === ADMIN_TELEGRAM_ID) {
                $adminController->handleAddSubscriptionPlanDetails($userId, $chatId, $text);
            } elseif ((strpos($stateAction, 'admin_add_content') === 0 || strpos($stateAction, 'admin_edit_content') === 0) && $userId === ADMIN_TELEGRAM_ID && is_array($stateData)) {
                $adminController->handleAdminConversation($userId, $chatId, $text, $stateData);
            } elseif ($stateAction === 'admin_awaiting_ticket_id_for_view' && $userId === ADMIN_TELEGRAM_ID) {
                if (ctype_digit($text)) {
                    $adminController->viewSupportTicket($userId, $chatId, null, (int)$text);
                } else {
                    $telegramAPI->sendMessage($chatId, "ID تیکت باید یک عدد باشد.");
                    $adminController->showSupportTicketsMenu($userId, $chatId, null);
                }
            } elseif ($stateAction === 'admin_awaiting_reply_to_ticket' && $userId === ADMIN_TELEGRAM_ID && isset($stateData['ticket_id'])) {
                $adminController->handleAdminSupportReply($userId, $chatId, $text, (int)$stateData['ticket_id']);
            } elseif ($stateAction === 'admin_awaiting_user_identifier' && $userId === ADMIN_TELEGRAM_ID) {
                $adminController->findAndShowUserManagementMenu($userId, $chatId, $text);
            } elseif ($stateAction === 'admin_editing_user_sub' && $userId === ADMIN_TELEGRAM_ID && is_array($stateData)) {
                $step = $stateData['step'] ?? '';
                $userIdToEdit = $stateData['user_id_to_edit'] ?? null;
                if ($userIdToEdit) {
                    if ($step === 'awaiting_plan_choice') {
                        $adminController->processUserSubscriptionPlanChoice($userId, $chatId, (int)$userIdToEdit, $text);
                    } elseif ($step === 'awaiting_expiry' && isset($stateData['plan_id_to_assign'])) {
                        $adminController->handleUpdateUserSubscription($userId, $chatId, (int)$userIdToEdit, $stateData['plan_id_to_assign'], $text);
                    } else {
                         error_log("Admin editing user sub: Invalid step '{$step}' or missing data. State: " . json_encode($stateData));
                         $userModel->updateUser($hashedTelegramId, ['user_state' => null]);
                         $adminController->showAdminMenu($userId, $chatId, null);
                    }
                } else { /* Error handling */ }
            } elseif ($stateAction === 'admin_awaiting_broadcast_message' && $userId === ADMIN_TELEGRAM_ID) {
                $adminController->confirmBroadcastMessage($userId, $chatId, $text);
            } elseif ($stateAction === 'awaiting_referral_code') {
                $userController->handleProcessReferralCode($userId, $chatId, $text, $firstName, $username);
            } else {
                // If no specific state is matched, or state is null, treat as normal message (e.g., /start)
                if ($text === '/start') {
                    $userController->handleStart($userId, $chatId, $firstName, $username);
                } else {
                    // Default action for unmatched text when no specific state or an unhandled one
                    $telegramAPI->sendMessage($chatId, "متوجه نشدم. لطفا از دکمه‌ها استفاده کنید یا /start را بزنید.");
                    $userController->showMainMenu($chatId);
                }
            }
        } // end !empty($text)
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

        // Route admin actions
        if (strpos($action, 'admin_') === 0) {
            if ($userId !== ADMIN_TELEGRAM_ID) {
                $telegramAPI->sendMessage($chatId, "شما اجازه دسترسی به این دستور ادمین را ندارید.");
            } else {
                switch ($action) {
                    case 'admin_show_menu': $adminController->showAdminMenu($userId, $chatId, $messageId); break;
                    case 'admin_plans_show_list': $adminController->showSubscriptionPlansAdmin($userId, $chatId, $messageId); break;
                    case 'admin_plan_prompt_add': $adminController->promptAddSubscriptionPlan($userId, $chatId, $messageId); break;
                    case 'admin_plan_toggle_active': list($pId, $nState) = explode('_', $value); $adminController->handleTogglePlanActive($userId, $chatId, $messageId, (int)$pId, (int)$nState); break;
                    case 'admin_content_show_menu': $adminController->showContentAdminMenu($userId, $chatId, $messageId); break;
                    case 'admin_content_list_topics': $adminController->listTutorialTopicsAdmin($userId, $chatId, $messageId); break;
                    case 'admin_content_list_articles': $adminController->listArticlesInTopicAdmin($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_content_prompt_add': list($cType, $pId) = explode('_', $value); $adminController->promptAddContent($userId, $chatId, $messageId, $cType, (int)$pId); break;
                    case 'admin_content_prompt_edit': $adminController->promptEditEducationalContent($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_content_setparam': list($fName, $fVal) = explode('_', $value, 2); $adminController->handleAdminContentSetParam($userId, $chatId, $messageId, $fName, $fVal); break;
                    case 'admin_content_confirm_delete': $adminController->confirmDeleteEducationalContent($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_content_do_delete': $adminController->handleDeleteEducationalContent($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_support_show_menu': $adminController->showSupportTicketsMenu($userId, $chatId, $messageId); break;
                    case 'admin_support_list_tickets': $adminController->listSupportTickets($userId, $chatId, $messageId, $value); break;
                    case 'admin_support_prompt_ticket_id': $adminController->promptViewTicketById($userId, $chatId, $messageId); break;
                    case 'admin_support_view_ticket': $adminController->viewSupportTicket($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_support_prompt_reply': $adminController->promptAdminReply($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_support_close_ticket': $adminController->handleCloseSupportTicket($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_show_statistics': $adminController->showStatistics($userId, $chatId, $messageId); break;
                    case 'admin_list_transactions': $adminController->listZarinpalTransactions($userId, $chatId, $messageId, (int)($value ?? 0)); break;
                    case 'admin_user_manage_prompt_find': $adminController->promptFindUser($userId, $chatId, $messageId); break;
                    case 'admin_user_manage_show': $adminController->handleShowUserManagementMenuCallback($userId, $chatId, $messageId, (int)$value); break; // For back button
                    case 'admin_user_edit_sub_prompt': $adminController->promptEditUserSubscription($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_user_delete_confirm': $adminController->confirmDeleteUserByAdmin($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_user_do_delete': $adminController->handleDeleteUserByAdmin($userId, $chatId, $messageId, (int)$value); break;
                    case 'admin_broadcast_prompt': $adminController->promptBroadcastMessage($userId, $chatId, $messageId); break;
                    case 'admin_broadcast_send_confirm': $adminController->handleSendBroadcastMessage($userId, $chatId, $messageId, $value); break;
                    default: error_log("Unknown admin callback action: {$action}"); $telegramAPI->sendMessage($chatId, "دستور ادمین ناشناخته."); break;
                }
            }
        } else {
            // User actions
            $userParts = explode(':', $callbackData); // Re-explode for potentially more parts
            $action = $userParts[0];
            $val1 = $userParts[1] ?? null;
            $val2 = $userParts[2] ?? null;
            $val3 = $userParts[3] ?? null;

            switch ($action) {
                case 'select_role': $userController->handleRoleSelection($userId, $chatId, $val1, $messageId); break;
                case 'partner_invite': $userController->handleGenerateInvitation($userId, $chatId, $messageId); break;
                case 'partner_cancel_invite': $userController->handleCancelInvitation($userId, $chatId, $messageId); break;
                case 'partner_accept_prompt': $userController->handleAcceptInvitationPrompt($userId, $chatId); break;
                case 'partner_disconnect': $userController->handleDisconnectPartner($userId, $chatId); break;
                case 'partner_disconnect_confirm': $userController->handleDisconnectPartnerConfirm($userId, $chatId, $messageId); break;
                case 'main_menu_show':
                case 'main_menu_show_direct': $userController->showMainMenu($chatId); break;
                case 'cycle_log_period_start_prompt': $userController->handleLogPeriodStartPrompt($userId, $chatId, $messageId); break;
                case 'cycle_pick_year': $userController->handleCyclePickYear($userId, $chatId, $messageId); break;
                case 'cycle_select_year': $userController->handleCycleSelectYear($userId, $chatId, $messageId, $val1); break;
                case 'cycle_select_month': $userController->handleCycleSelectMonth($userId, $chatId, $messageId, $val1, $val2); break;
                case 'cycle_select_day': $userController->handleCycleLogDate($userId, $chatId, $messageId, $val1, $val2, $val3); break;
                case 'cycle_log_date': $userController->handleCycleLogDate($userId, $chatId, $messageId, $val1); break;
                case 'cycle_set_avg_period': $userController->handleSetAveragePeriodLength($userId, $chatId, $messageId, $val1); break;
                case 'cycle_set_avg_cycle': $userController->handleSetAverageCycleLength($userId, $chatId, $messageId, $val1); break;
                case 'cycle_skip_avg_period': $userController->handleSkipAverageInfo($userId, $chatId, $messageId, 'period'); break;
                case 'cycle_skip_avg_cycle': $userController->handleSkipAverageInfo($userId, $chatId, $messageId, 'cycle'); break;
                case 'symptom_log_start': $userController->handleLogSymptomStart($userId, $chatId, $messageId, $val1); break;
                case 'symptom_show_cat': $userController->handleSymptomShowCategory($userId, $chatId, $messageId, $val1, $val2); break;
                case 'symptom_toggle': $userController->handleSymptomToggle($userId, $chatId, $messageId, $val1, $val2, $val3); break;
                case 'symptom_save_final': $userController->handleSymptomSaveFinal($userId, $chatId, $messageId, $val1); break;
                case 'settings_show': $userController->handleSettings($userId, $chatId, $messageId); break;
                case 'settings_set_notify_time_prompt': $userController->handleSetNotificationTimePrompt($userId, $chatId, $messageId); break;
                case 'settings_set_notify_time': $userController->handleSetNotificationTime($userId, $chatId, $messageId, $val1); break;
                case 'show_guidance': $userController->handleShowGuidance($userId, $chatId, $messageId); break;
                case 'support_request_start': $supportController->userRequestSupportStart($userId, $chatId, $messageId); break;
                case 'show_about_us': $userController->handleShowAboutUs($userId, $chatId, $messageId); break;
                case 'sub_show_plans': $userController->handleShowSubscriptionPlans($userId, $chatId, $messageId); break;
                case 'sub_select_plan': $userController->handleSubscribePlan($userId, $chatId, $messageId, $val1); break;
                case 'user_show_tutorial_topics': $userController->handleShowTutorialTopics($userId, $chatId, $messageId); break;
                case 'user_show_tutorial_topic_content': $userController->handleShowTutorialTopicContent($userId, $chatId, $messageId, (int)$val1); break;
                case 'user_show_tutorial_article': $userController->handleShowTutorialArticle($userId, $chatId, $messageId, (int)$val1); break;
                case 'user_show_referral_info': $userController->handleShowReferralProgram($userId, $chatId, $messageId); break;
                case 'user_enter_referral_code_prompt': $userController->handleEnterReferralCodePrompt($userId, $chatId, $messageId); break;
                case 'user_delete_account_prompt': $userController->handleDeleteAccountPrompt($userId, $chatId, $messageId); break;
                case 'user_delete_account_confirm': $userController->handleDeleteAccountConfirm($userId, $chatId, $messageId); break;
                case 'user_show_history_menu': $userController->handleShowHistoryMenu($userId, $chatId, $messageId); break;
                case 'user_history_periods': $userController->handleShowPeriodHistory($userId, $chatId, $messageId, (int)($val1 ?? 0)); break;
                case 'user_history_symptoms': $userController->handleShowSymptomHistory($userId, $chatId, $messageId, (int)($val1 ?? 0)); break;
                default: error_log("Unknown user callback action: {$action}"); break;
            }
        }
    }
} catch (\Throwable $e) { // Catch Throwable for PHP 7+ to catch Errors as well
    error_log("FATAL ERROR processing update: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\nUpdate JSON: " . $updateJson);
    // Avoid sending a message to user here if API itself might be broken or in a loop.
    // If $telegramAPI is available and chat_id is known, a generic error message can be attempted.
    if (isset($telegramAPI) && isset($chatId)) {
         // $telegramAPI->sendMessage($chatId, "متاسفانه یک خطای خیلی جدی در ربات رخ داده است. تیم فنی به زودی بررسی خواهد کرد.");
    }
}
?>
