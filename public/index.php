<?php

require_once dirname(__DIR__) . '/config/config.php';

use Controllers\UserController;
use Controllers\AdminController;
use Controllers\SupportController; // Added
use Models\UserModel; // Added for SupportController
use Models\SupportTicketModel; // Added for SupportController
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
$userModel = new UserModel(); // Instantiate UserModel
$userController = new UserController($telegramAPI);
// $adminController instantiation needs SupportTicketModel if it uses it. For now, it doesn't directly.
$adminController = new AdminController($telegramAPI);
$supportTicketModel = new SupportTicketModel(); // Instantiate SupportTicketModel
$supportController = new SupportController($telegramAPI, $supportTicketModel, $userModel); // Instantiate SupportController


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
                } elseif ($stateAction === 'awaiting_initial_support_message' || (isset($stateInfo['action']) && $stateInfo['action'] === 'awaiting_user_reply_to_ticket')) {
                    $supportController->handleUserMessage($userId, $chatId, $text, $firstName, $username);
                } elseif ($stateAction === 'admin_awaiting_plan_add' && $userId === ADMIN_TELEGRAM_ID) {
                    $adminController->handleAddSubscriptionPlanDetails($userId, $chatId, $text);
                } elseif ((strpos($stateAction, 'admin_add_content') === 0 || strpos($stateAction, 'admin_edit_content') === 0) && $userId === ADMIN_TELEGRAM_ID && is_array($stateInfo)) { // Adjusted for more flexible admin content states
                    $adminController->handleAdminConversation($userId, $chatId, $text, $stateInfo);
                } elseif ($stateAction === 'admin_awaiting_ticket_id_for_view' && $userId === ADMIN_TELEGRAM_ID) {
                    if (ctype_digit($text)) {
                        $adminController->viewSupportTicket($userId, $chatId, null, (int)$text);
                    } else {
                        $telegramAPI->sendMessage($chatId, "ID تیکت باید یک عدد باشد.");
                        $adminController->showSupportTicketsMenu($userId, $chatId, null); // Go back
                    }
                } elseif (isset($stateInfo['action']) && $stateInfo['action'] === 'admin_awaiting_reply_to_ticket' && $userId === ADMIN_TELEGRAM_ID && isset($stateInfo['ticket_id'])) {
                    $adminController->handleAdminSupportReply($userId, $chatId, $text, (int)$stateInfo['ticket_id']);
                } elseif ($stateAction === 'admin_awaiting_user_identifier' && $userId === ADMIN_TELEGRAM_ID) {
                    $adminController->findAndShowUserManagementMenu($userId, $chatId, $text);
                } elseif (isset($stateInfo['action']) && $stateInfo['action'] === 'admin_editing_user_sub' && $userId === ADMIN_TELEGRAM_ID) { // New
                    $step = $stateInfo['step'] ?? '';
                    $userIdToEdit = $stateInfo['user_id_to_edit'] ?? null;
                    if ($userIdToEdit) {
                        if ($step === 'awaiting_plan_choice') {
                            $adminController->processUserSubscriptionPlanChoice($userId, $chatId, (int)$userIdToEdit, $text);
                        } elseif ($step === 'awaiting_expiry' && isset($stateInfo['plan_id_to_assign'])) {
                            $adminController->handleUpdateUserSubscription($userId, $chatId, (int)$userIdToEdit, $stateInfo['plan_id_to_assign'], $text);
                        } else {
                             error_log("Admin editing user sub: Invalid step '{$step}' or missing data.");
                             $userController->getUserModel()->updateUser($hashedTelegramId, ['user_state' => null]);
                             $adminController->showAdminMenu($userId, $chatId, null);
                        }
                    } else {
                        error_log("Admin editing user sub: Missing userIdToEdit in state.");
                        $userController->getUserModel()->updateUser($hashedTelegramId, ['user_state' => null]);
                        $adminController->showAdminMenu($userId, $chatId, null);
                    }
                } elseif ($stateAction === 'admin_awaiting_broadcast_message' && $userId === ADMIN_TELEGRAM_ID) { // New
                    $adminController->confirmBroadcastMessage($userId, $chatId, $text);
                } elseif ($stateAction === 'awaiting_referral_code') {
                    $userController->handleProcessReferralCode($userId, $chatId, $text, $firstName, $username);
                } else {
                    error_log("User {$userId} in unhandled text state: {$userStateJson}. Clearing state.");
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
                    // Support System Admin Callbacks
                    case 'admin_support_show_menu':
                        $adminController->showSupportTicketsMenu($userId, $chatId, $messageId);
                        break;
                    case 'admin_support_list_tickets':
                        $adminController->listSupportTickets($userId, $chatId, $messageId, $value); // value is filter_page
                        break;
                    case 'admin_support_prompt_ticket_id':
                        $adminController->promptViewTicketById($userId, $chatId, $messageId);
                        break;
                    case 'admin_support_view_ticket':
                        $adminController->viewSupportTicket($userId, $chatId, $messageId, (int)$value); // value is ticket_id
                        break;
                    case 'admin_support_prompt_reply':
                        $adminController->promptAdminReply($userId, $chatId, $messageId, (int)$value); // value is ticket_id
                        break;
                    case 'admin_support_close_ticket':
                        $adminController->handleCloseSupportTicket($userId, $chatId, $messageId, (int)$value); // value is ticket_id
                        break;
                    case 'admin_show_statistics':
                        $adminController->showStatistics($userId, $chatId, $messageId);
                        break;
                    case 'admin_list_transactions':
                        $adminController->listZarinpalTransactions($userId, $chatId, $messageId, (int)($value ?? 0)); // value is page
                        break;
                    case 'admin_user_manage_prompt_find':
                        $adminController->promptFindUser($userId, $chatId, $messageId);
                        break;
                    case 'admin_user_edit_sub_prompt':
                        $adminController->promptEditUserSubscription($userId, $chatId, $messageId, (int)$value);
                        break;
                    case 'admin_broadcast_prompt':
                        $adminController->promptBroadcastMessage($userId, $chatId, $messageId);
                        break;
                    case 'admin_broadcast_send_confirm':
                        $adminController->handleSendBroadcastMessage($userId, $chatId, $messageId, $value); // value is message_hash
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
                case 'support_request_start': // Route to SupportController
                    $supportController->userRequestSupportStart($userId, $chatId, $messageId);
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
                case 'user_show_referral_info': // Added this case
                    $userController->handleShowReferralProgram($userId, $chatId, $messageId);
                    break;
                case 'user_enter_referral_code_prompt':
                    $userController->handleEnterReferralCodePrompt($userId, $chatId, $messageId);
                    break;
                case 'user_delete_account_prompt':
                    $userController->handleDeleteAccountPrompt($userId, $chatId, $messageId);
                    break;
                case 'user_delete_account_confirm':
                    $userController->handleDeleteAccountConfirm($userId, $chatId, $messageId);
                    break;
                case 'user_show_history_menu': // New
                    $userController->handleShowHistoryMenu($userId, $chatId, $messageId);
                    break;
                case 'user_history_periods': // New - value is page number
                    $userController->handleShowPeriodHistory($userId, $chatId, $messageId, (int)($val1 ?? 0));
                    break;
                case 'user_history_symptoms': // New - value is page number
                    $userController->handleShowSymptomHistory($userId, $chatId, $messageId, (int)($val1 ?? 0));
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
