<?php

require_once dirname(__DIR__) . '/config/config.php'; // Defines BASE_PATH and loads autoloader

use Controllers\UserController;
use Telegram\TelegramAPI; // Will be created later
use Helpers\InputHelper; // Will be created later

// Basic security: Ensure this script is called via POST method (common for webhooks)
// Further security like checking a secret token passed by Telegram is recommended for production
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo "Method Not Allowed";
    exit;
}

$updateJson = file_get_contents('php://input');
if (!$updateJson) {
    http_response_code(400); // Bad Request
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

// Log the raw update for debugging (optional, remove in production or make configurable)
// file_put_contents(BASE_PATH . '/logs/telegram_updates.log', date('Y-m-d H:i:s') . " - " . $updateJson . "\n", FILE_APPEND);

$telegramAPI = new TelegramAPI(TELEGRAM_BOT_TOKEN);
$userController = new UserController($telegramAPI); // TelegramAPI will be injected

try {
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $userId = $message['from']['id'];
        $firstName = $message['from']['first_name'] ?? '';
        $username = $message['from']['username'] ?? null;

        // Check for the /start command or any initial message
        // For now, we'll treat any text message from a new user as a potential start
        // Later, we can refine this to specifically look for a "Start" button payload if the bot is already added.
        // The problem states "On first 'Start'", which implies an action.
        // However, users might just type text. We'll route to a general handler.

        // A simple router:
        if (strpos($text, '/start ') === 0) { // Check for /start command with parameters
            $parts = explode(' ', $text, 2);
            $payload = $parts[1] ?? '';
            if (strpos($payload, 'invite_') === 0) {
                $token = substr($payload, strlen('invite_'));
                $userController->handleAcceptInvitationCommand((string)$userId, $chatId, $firstName, $username, $token);
            } else {
                // Unknown /start payload
                $userController->handleStart($userId, $chatId, $firstName, $username);
            }
        } elseif ($text === '/start' || !empty($text)) { // Handle /start or any other text message
            // Check if user is awaiting a specific input, e.g. invitation token
            // This state management needs to be implemented (e.g. in DB user session or cache)
            // For now, any text just goes to main menu or start if not registered.
            $userController->handleStart($userId, $chatId, $firstName, $username); // This will show main menu if registered
        }
        // Add more message type handlers here (photo, audio, etc.) if needed

    } elseif (isset($update['callback_query'])) {
        $callbackQuery = $update['callback_query'];
        $userId = $callbackQuery['from']['id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $callbackData = $callbackQuery['data'];
        $callbackQueryId = $callbackQuery['id'];
        $messageId = $callbackQuery['message']['message_id'];


        // Answer callback query to remove the "loading" state on the button
        $telegramAPI->answerCallbackQuery($callbackQueryId);

        // Route callback data
        $parts = explode(':', $callbackData); // e.g. "action:value"
        $action = $parts[0];
        $value = $parts[1] ?? null;

        switch ($action) {
            case 'select_role':
                $userController->handleRoleSelection($userId, $chatId, $value, $messageId);
                break;
            case 'partner_invite':
                $userController->handleGenerateInvitation($userId, $chatId);
                break;
            case 'partner_cancel_invite':
                $userController->handleCancelInvitation($userId, $chatId, $messageId);
                break;
            case 'partner_accept_prompt':
                // This will now be primarily handled by deep linking /start invite_TOKEN
                // Kept for users who might click a button then paste a code.
                // The controller method sends a message "Please send the code".
                // Actual code handling from text needs state management or direct command.
                $userController->handleAcceptInvitationPrompt($userId, $chatId);
                break;
            case 'partner_disconnect':
                $userController->handleDisconnectPartner($userId, $chatId);
                break;
            case 'partner_disconnect_confirm':
                $userController->handleDisconnectPartnerConfirm($userId, $chatId, $messageId);
                break;
            case 'main_menu_show': // Generic callback to just show main menu
                $userController->showMainMenu($chatId);
                break;
            // Cycle logging callbacks
            case 'cycle_log_period_start_prompt':
                $userController->handleLogPeriodStartPrompt($userId, $chatId, $messageId);
                break;
            case 'cycle_pick_year':
                $userController->handleCyclePickYear($userId, $chatId, $messageId);
                break;
            case 'cycle_select_year':
                // $value will be the year
                $userController->handleCycleSelectYear($userId, $chatId, $messageId, $value);
                break;
            case 'cycle_select_month':
                // $value will be "year:month"
                list($year, $month) = explode(':', $value);
                $userController->handleCycleSelectMonth($userId, $chatId, $messageId, $year, $month);
                break;
            case 'cycle_select_day':
                // $value will be "year:month:day"
                list($year, $month, $day) = explode(':', $value);
                $userController->handleCycleLogDate($userId, $chatId, $messageId, $year, $month, $day);
                break;
            case 'cycle_log_date':
                 // $value will be "YYYY-MM-DD"
                $userController->handleCycleLogDate($userId, $chatId, $messageId, $value); // Pass full date string as $year
                break;
            case 'cycle_set_avg_period':
                // $value will be the length
                $userController->handleSetAveragePeriodLength($userId, $chatId, $messageId, $value);
                break;
            case 'cycle_set_avg_cycle':
                // $value will be the length
                $userController->handleSetAverageCycleLength($userId, $chatId, $messageId, $value);
                break;
            case 'cycle_skip_avg_period':
                 $userController->handleSkipAverageInfo($userId, $chatId, $messageId, 'period');
                 break;
            case 'cycle_skip_avg_cycle':
                $userController->handleSkipAverageInfo($userId, $chatId, $messageId, 'cycle');
                break;

            // Symptom Logging callbacks
            case 'symptom_log_start': // value: today|yesterday
                $userController->handleLogSymptomStart($userId, $chatId, $messageId, $value);
                break;
            case 'symptom_show_cat': // value: dateOption:categoryKey
                list($dateOpt, $catKey) = explode(':', $value);
                $userController->handleSymptomShowCategory($userId, $chatId, $messageId, $dateOpt, $catKey);
                break;
            case 'symptom_toggle': // value: dateOption:categoryKey:symptomKey
                list($dateOpt, $catKey, $symKey) = explode(':', $value);
                $userController->handleSymptomToggle($userId, $chatId, $messageId, $dateOpt, $catKey, $symKey);
                break;
            case 'symptom_save_final': // value: dateOption
                $userController->handleSymptomSaveFinal($userId, $chatId, $messageId, $value);
                break;

            // Settings callbacks
            case 'settings_show':
                $userController->handleSettings($userId, $chatId, $messageId);
                break;
            case 'settings_set_notify_time_prompt':
                $userController->handleSetNotificationTimePrompt($userId, $chatId, $messageId);
                break;
            case 'settings_set_notify_time': // value: HH:MM
                $userController->handleSetNotificationTime($userId, $chatId, $messageId, $value);
                break;

            // Add more callback handlers here
            default:
                error_log("Unknown callback action: " . $action . " Full data: " . $callbackData);
                // Optionally send a message to user "Action not understood"
                break;
        }
    }
    // Handle other update types (inline_query, chosen_inline_result, etc.) if needed

} catch (Exception $e) {
    error_log("Error processing update: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    // Optionally, send a generic error message to the user if appropriate and possible
    if (isset($chatId)) {
       // $telegramAPI->sendMessage($chatId, "متاسفانه مشکلی پیش آمده. لطفا دوباره تلاش کنید.");
    }
}

// Acknowledge receipt to Telegram (not strictly necessary for all responses, but good practice)
// http_response_code(200); // Already default if no error occurs.

?>
