<?php

namespace Controllers;

use Models\SupportTicketModel;
use Models\UserModel;
use Telegram\TelegramAPI;
use Helpers\EncryptionHelper;

class SupportController {
    private $telegramAPI;
    private $supportTicketModel;
    private $userModel;

    public function __construct(TelegramAPI $telegramAPI, SupportTicketModel $supportTicketModel, UserModel $userModel) {
        $this->telegramAPI = $telegramAPI;
        $this->supportTicketModel = $supportTicketModel;
        $this->userModel = $userModel;
    }

    private function isAdmin(string $telegramId): bool {
        return defined('ADMIN_TELEGRAM_ID') && (string)$telegramId === ADMIN_TELEGRAM_ID;
    }

    // --- User-facing support methods (to be called from UserController or main router) ---

    /**
     * User initiates a support request. Sets state to await their message.
     */
    public function userRequestSupportStart(string $telegramId, int $chatId, ?int $messageId = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $stateToSet = ['action' => 'awaiting_initial_support_message'];
        $updateResult = $this->userModel->updateUser($hashedTelegramId, ['user_state' => json_encode($stateToSet)]);

        error_log("SupportController::userRequestSupportStart - User: {$telegramId}, HashedID: {$hashedTelegramId}, State set to: " . json_encode($stateToSet) . ", Update result: " . ($updateResult ? 'Success' : 'Failed'));

        $text = "💬 شما در حال ارسال پیام به پشتیبانی هستید.\nلطفا پیام خود را بنویسید و ارسال کنید. پیام شما یک تیکت پشتیبانی جدید ایجاد خواهد کرد یا به تیکت باز فعلی شما اضافه می‌شود.\n\nبرای لغو، /cancel را ارسال کنید.";

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, null);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, null);
        }
    }

    /**
     * Handles a message from a user that is intended for the support system.
     */
    public function handleUserMessage(string $telegramId, int $chatId, string $messageText, string $firstName, ?string $username) {
        error_log("SupportController::handleUserMessage - Received message '{$messageText}' from user {$telegramId} in chat {$chatId}");
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user || !isset($user['id'])) {
            error_log("SupportController::handleUserMessage - User not found or ID missing for hashedId {$hashedTelegramId}");
            $this->telegramAPI->sendMessage($chatId, "خطا: اطلاعات کاربری شما برای ارسال پیام پشتیبانی یافت نشد.");
            return;
        }
        $dbUserId = $user['id'];
        $ticketId = null;
        $isNewTicket = false;

        $userState = $this->userModel->getUserState($hashedTelegramId);

        // Check if this is a reply to an admin's message within an existing ticket conversation
        if ($userState && isset($userState['action']) && $userState['action'] === 'awaiting_user_reply_to_ticket' && isset($userState['ticket_id'])) {
            $ticketId = (int)$userState['ticket_id'];
            $ticket = $this->supportTicketModel->getTicketById($ticketId);
            if (!$ticket || $ticket['user_id'] !== $dbUserId || $ticket['status'] === 'closed') {
                $ticketId = null; // Invalid state or ticket closed, treat as new
            }
        }

        if (!$ticketId) { // No active reply state, check for existing open ticket or create new
            $openTicket = $this->supportTicketModel->findOpenTicketByUserId($dbUserId);
            if ($openTicket && $openTicket['status'] !== 'closed') {
                $ticketId = $openTicket['id'];
                 $this->supportTicketModel->updateTicketStatus($ticketId, 'user_reply', true); // Re-open if it was admin_reply
            } else {
                $subject = mb_substr($messageText, 0, 70) . (mb_strlen($messageText) > 70 ? "..." : "");
                $newTicketId = $this->supportTicketModel->createTicket($dbUserId, $subject);
                if ($newTicketId) {
                    $ticketId = $newTicketId;
                    $isNewTicket = true;
                } else {
                    $this->telegramAPI->sendMessage($chatId, "متاسفانه در ایجاد تیکت پشتیبانی مشکلی پیش آمد. لطفا بعدا تلاش کنید.");
                    $this->userModel->updateUser($hashedTelegramId, ['user_state' => null]);
                    return;
                }
            }
        }

        $messageAdded = $this->supportTicketModel->addMessage($ticketId, $telegramId, 'user', $messageText);

        if ($messageAdded) {
            $this->userModel->updateUser($hashedTelegramId, ['user_state' => null]);

            $userDisplayName = $firstName . ($username ? " (@{$username})" : "") . " (User DB ID: {$dbUserId})";
            $adminNotificationText = ($isNewTicket ? "🎟️ تیکت پشتیبانی جدید" : "💬 پاسخ جدید به تیکت") . " #{$ticketId} از طرف {$userDisplayName}:\n\n{$messageText}";

            if (defined('ADMIN_TELEGRAM_ID') && ADMIN_TELEGRAM_ID && ADMIN_TELEGRAM_ID !== 'YOUR_ADMIN_TELEGRAM_ID') {
                $this->telegramAPI->sendMessage((int)ADMIN_TELEGRAM_ID, $adminNotificationText);
            } else {
                error_log("Admin Telegram ID not configured. Cannot send support notification for ticket #{$ticketId}.");
            }
            $this->telegramAPI->sendMessage($chatId, "پیام شما برای پشتیبانی ارسال شد. ✅\nشماره تیکت شما: #{$ticketId}\nلطفا منتظر پاسخ ادمین بمانید.");
        } else {
            $this->telegramAPI->sendMessage($chatId, "متاسفانه در ارسال پیام شما به پشتیبانی مشکلی پیش آمد.");
        }
        // Consider not showing main menu immediately, let user know message sent.
        // $userController = new UserController($this->telegramAPI); // Avoid direct instantiation if possible
        // $userController->showMainMenu($chatId);
    }
}
?>
