<?php

namespace Controllers;

use Telegram\TelegramAPI;
use Models\SubscriptionPlanModel;
use Models\EducationalContentModel;
use Models\UserModel;
use Models\SupportTicketModel; // Added
use Helpers\EncryptionHelper;

class AdminController {
    private $telegramAPI;
    private $subscriptionPlanModel;
    private $educationalContentModel;
    private $userModel;
    private $supportTicketModel; // Added

    public function __construct(TelegramAPI $telegramAPI) {
        $this->telegramAPI = $telegramAPI;
        $this->subscriptionPlanModel = new SubscriptionPlanModel();
        $this->educationalContentModel = new EducationalContentModel();
        $this->userModel = new UserModel();
        $this->supportTicketModel = new SupportTicketModel(); // Added
    }

    private function isAdmin(string $telegramId): bool {
        return (string)$telegramId === ADMIN_TELEGRAM_ID;
    }

    private function updateUserState(string $telegramId, ?array $stateData) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $this->userModel->updateUser($hashedTelegramId, ['user_state' => $stateData ? json_encode($stateData) : null]);
    }

    private function getCurrentAdminState(string $telegramId): ?array {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if ($user && !empty($user['user_state'])) {
            $decodedState = json_decode($user['user_state'], true);
            if (is_array($decodedState) && isset($decodedState['action']) && strpos($decodedState['action'], 'admin_') === 0) {
                return $decodedState;
            }
        }
        return null;
    }

    public function showAdminMenu(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) {
            $this->telegramAPI->sendMessage($chatId, "شما اجازه دسترسی به این بخش را ندارید.");
            return;
        }
        $this->updateUserState($telegramId, null);

        $text = "👑 **پنل مدیریت ربات** 👑\n\nچه کاری می‌خواهید انجام دهید؟";
        $buttons_flat = [
            ['text' => "مدیریت طرح‌های اشتراک 💳", 'callback_data' => 'admin_plans_show_list'],
            ['text' => "📚 مدیریت محتوای آموزشی", 'callback_data' => 'admin_content_show_menu'],
            ['text' => "💬 مدیریت پیام‌های پشتیبانی", 'callback_data' => 'admin_support_show_menu'],
            ['text' => "📊 آمار ربات", 'callback_data' => 'admin_show_statistics'],
            ['text' => "📜 مشاهده تراکنش‌ها", 'callback_data' => 'admin_list_transactions:0'],
            ['text' => "👤 مدیریت کاربر", 'callback_data' => 'admin_user_manage_prompt_find'],
            ['text' => "📢 ارسال پیام همگانی", 'callback_data' => 'admin_broadcast_prompt'], // New
            ['text' => "🏠 بازگشت به منوی اصلی کاربر", 'callback_data' => 'main_menu_show']
        ];

        $grouped_buttons = [];
        for ($i = 0; $i < count($buttons_flat); $i += 2) {
            $row = [$buttons_flat[$i]];
            if (isset($buttons_flat[$i+1])) {
                $row[] = $buttons_flat[$i+1];
            }
            $grouped_buttons[] = $row;
        }
        // Ensure last item (main_menu_show) is on its own row if it's an odd one out due to adding new items
        if (count($buttons_flat) % 2 != 0 && count($grouped_buttons) > 1) {
             $last_button_row = array_pop($grouped_buttons); // get the row with the single last admin item
             $main_menu_button = array_pop($last_button_row); // get the main_menu_show button
             if ($last_button_row) { // if there was anything left in that row
                $grouped_buttons[] = $last_button_row;
             }
             $grouped_buttons[] = [$main_menu_button]; // main_menu_show on its own row
        }


        $keyboard = ['inline_keyboard' => $grouped_buttons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        }
    }

    // --- Support Ticket Management (Admin) ---

    public function showSupportTicketsMenu(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $this->updateUserState($telegramId, null);

        $text = "💬 مدیریت پیام‌های پشتیبانی\n\nانتخاب کنید:";
        $buttons = [
            [['text' => "مشاهده تیکت‌های باز", 'callback_data' => 'admin_support_list_tickets:open_0']], // page 0
            [['text' => "مشاهده همه تیکت‌ها", 'callback_data' => 'admin_support_list_tickets:all_0']], // page 0
            [['text' => "جستجوی تیکت با ID", 'callback_data' => 'admin_support_prompt_ticket_id']],
            [['text' => "🔙 بازگشت به پنل ادمین", 'callback_data' => 'admin_show_menu']],
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        }
    }

    public function listSupportTickets(string $telegramId, int $chatId, ?int $messageId, string $filterAndPage) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }

        list($filter, $page) = explode('_', $filterAndPage);
        $page = (int)$page;
        $perPage = 5; // Tickets per page
        $offset = $page * $perPage;

        $statusFilter = ($filter === 'all') ? null : $filter; // 'open', 'admin_reply', 'user_reply'
        if ($filter === 'open') $statusFilter = ['open', 'user_reply', 'admin_reply']; // Show all non-closed

        $tickets = $this->supportTicketModel->listTickets($statusFilter, $perPage, $offset);
        $totalTickets = $this->supportTicketModel->countTickets($statusFilter);
        $totalPages = ceil($totalTickets / $perPage);

        $text = "لیست تیکت‌ها (" . ($filter === 'all' ? 'همه' : 'باز') . ") - صفحه " . ($page + 1) . " از {$totalPages}\n\n";
        $ticketButtons = [];

        if (empty($tickets)) {
            $text .= "هیچ تیکتی برای نمایش وجود ندارد.";
        } else {
            foreach ($tickets as $ticket) {
                $subjectPreview = !empty($ticket['subject']) ? mb_substr($ticket['subject'], 0, 20) . "..." : "بدون موضوع";
                $userName = $ticket['user_first_name'] ?? "کاربر {$ticket['user_id']}";
                $text .= "🎟️ #{$ticket['id']} - {$subjectPreview}\n";
                $text .= "👤 {$userName} - Status: {$ticket['status']}\n";
                $text .= "📅 " . (new \DateTime($ticket['last_message_at']))->format('Y-m-d H:i') . "\n---\n";
                $ticketButtons[] = [['text' => "مشاهده/پاسخ تیکت #{$ticket['id']}", 'callback_data' => 'admin_support_view_ticket:' . $ticket['id']]];
            }
        }

        // Pagination buttons
        $paginationButtons = [];
        if ($page > 0) $paginationButtons[] = ['text' => '⬅️ قبلی', 'callback_data' => "admin_support_list_tickets:{$filter}_" . ($page - 1)];
        if (($page + 1) < $totalPages) $paginationButtons[] = ['text' => '➡️ بعدی', 'callback_data' => "admin_support_list_tickets:{$filter}_" . ($page + 1)];
        if (!empty($paginationButtons)) $ticketButtons[] = $paginationButtons;

        $ticketButtons[] = [['text' => "🔙 بازگشت به منوی پشتیبانی", 'callback_data' => 'admin_support_show_menu']];
        $keyboard = ['inline_keyboard' => $ticketButtons];

        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function promptViewTicketById(string $telegramId, int $chatId, ?int $messageId){
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $this->updateUserState($telegramId, ['action' => 'admin_awaiting_ticket_id_for_view']);
        $text = "لطفا ID تیکت مورد نظر برای مشاهده را وارد کنید:";
        if($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, null);
        else $this->telegramAPI->sendMessage($chatId, $text, null);
    }


    public function viewSupportTicket(string $telegramId, int $chatId, ?int $messageId, int $ticketId) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $this->updateUserState($telegramId, null); // Clear any input state initially

        $ticket = $this->supportTicketModel->getTicketById($ticketId);
        if (!$ticket) {
            $this->telegramAPI->sendMessage($chatId, "خطا: تیکت با ID {$ticketId} یافت نشد.");
            $this->showSupportTicketsMenu($telegramId, $chatId, $messageId);
            return;
        }

        $messages = $this->supportTicketModel->getMessagesForTicket($ticketId);
        $userName = $ticket['user_first_name'] ?? "کاربر";
        $userIdentifier = $ticket['user_username'] ? "@{$ticket['user_username']}" : "(ID: {$ticket['user_id']})";


        $text = "🎫 **مشاهده تیکت #{$ticketId}**\n";
        $text .= "👤 کاربر: {$userName} {$userIdentifier}\n";
        $text .= "ርዕሰ ጉዳይ: *{$ticket['subject']}*\n";
        $text .= "وضعیت: {$ticket['status']}\n";
        $text .= "آخرین بروزرسانی: " . (new \DateTime($ticket['last_message_at']))->format('Y-m-d H:i') . "\n";
        $text .= "-------------------------------\n";

        if (empty($messages)) {
            $text .= "هنوز پیامی در این تیکت وجود ندارد.\n";
        } else {
            foreach ($messages as $msg) {
                $sender = ($msg['sender_role'] === 'admin') ? "شما (ادمین)" : $userName;
                $sentAt = (new \DateTime($msg['sent_at']))->format('Y-m-d H:i');
                $text .= "🗣️ *{$sender}* ({$sentAt}):\n{$msg['message_text']}\n---\n";
            }
        }

        $buttons = [];
        if ($ticket['status'] !== 'closed') {
            $buttons[] = [['text' => "✍️ پاسخ به تیکت", 'callback_data' => 'admin_support_prompt_reply:' . $ticketId]];
            $buttons[] = [['text' => "🔒 بستن تیکت", 'callback_data' => 'admin_support_close_ticket:' . $ticketId]];
        }
        $buttons[] = [['text' => "🔙 بازگشت به لیست تیکت‌ها", 'callback_data' => 'admin_support_list_tickets:open_0']]; // Default to open tickets
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function promptAdminReply(string $telegramId, int $chatId, ?int $messageId, int $ticketId) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $ticket = $this->supportTicketModel->getTicketById($ticketId);
         if (!$ticket || $ticket['status'] === 'closed') {
            $this->telegramAPI->editMessageText($chatId, $messageId ?? 0, "نمی‌توان به این تیکت پاسخ داد (بسته شده یا ناموجود).", null);
            $this->showSupportTicketsMenu($telegramId, $chatId, null);
            return;
        }
        $this->updateUserState($telegramId, ['action' => 'admin_awaiting_reply_to_ticket', 'ticket_id' => $ticketId]);
        $text = "✍️ در حال پاسخ به تیکت #{$ticketId}.\nلطفا پیام خود را ارسال کنید (یا /cancel_admin_action برای لغو):";

        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, null);
        else $this->telegramAPI->sendMessage($chatId, $text, null);
    }

    // This method will be called when admin sends a text message while in 'admin_awaiting_reply_to_ticket' state
    public function handleAdminSupportReply(string $adminTelegramId, int $adminChatId, string $replyText, int $ticketId) {
        if (!$this->isAdmin($adminTelegramId)) { return; }

        $ticket = $this->supportTicketModel->getTicketById($ticketId);
        if (!$ticket || $ticket['status'] === 'closed') {
            $this->telegramAPI->sendMessage($adminChatId, "خطا: تیکت #{$ticketId} یافت نشد یا بسته شده است.");
            $this->updateUserState($adminTelegramId, null);
            return;
        }

        $messageAdded = $this->supportTicketModel->addMessage($ticketId, $adminTelegramId, 'admin', $replyText);
        if ($messageAdded) {
            $this->telegramAPI->sendMessage($adminChatId, "✅ پاسخ شما برای تیکت #{$ticketId} ارسال شد.");

            // Notify user
            $user = $this->userModel->findUserById($ticket['user_id']);
            if ($user && !empty($user['encrypted_chat_id'])) {
                try {
                    $userChatId = EncryptionHelper::decrypt($user['encrypted_chat_id']);
                    $userNotification = "💬 ادمین به تیکت پشتیبانی شما #{$ticketId} پاسخ داد:\n\n{$replyText}\n\nمی‌توانید از طریق ربات پاسخ خود را ارسال کنید.";
                    $this->telegramAPI->sendMessage((int)$userChatId, $userNotification);
                    // Set user state to allow direct reply
                    $this->userModel->updateUser($user['telegram_id_hash'], ['user_state' => json_encode(['action' => 'awaiting_user_reply_to_ticket', 'ticket_id' => $ticketId])]);

                } catch (\Exception $e) {
                    error_log("Failed to notify user for ticket #{$ticketId} reply: " . $e->getMessage());
                }
            }
        } else {
            $this->telegramAPI->sendMessage($adminChatId, "خطا در ارسال پاسخ برای تیکت #{$ticketId}.");
        }
        $this->updateUserState($adminTelegramId, null); // Clear admin state
        $this->viewSupportTicket($adminTelegramId, $adminChatId, null, $ticketId); // Show ticket again
    }

    public function handleCloseSupportTicket(string $telegramId, int $chatId, ?int $messageId, int $ticketId) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }

        $ticket = $this->supportTicketModel->getTicketById($ticketId);
        if (!$ticket) {
             if($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, "تیکت یافت نشد.", null);
             else $this->telegramAPI->sendMessage($chatId, "تیکت یافت نشد.", null);
             return;
        }

        if ($this->supportTicketModel->updateTicketStatus($ticketId, 'closed', false)) { // false: don't update last_message_at on close
            $responseText = "تیکت #{$ticketId} با موفقیت بسته شد.";
            // Notify user
            $user = $this->userModel->findUserById($ticket['user_id']);
            if ($user && !empty($user['encrypted_chat_id'])) {
                try {
                    $userChatId = EncryptionHelper::decrypt($user['encrypted_chat_id']);
                    $this->telegramAPI->sendMessage((int)$userChatId, "تیکت پشتیبانی شما #{$ticketId} توسط ادمین بسته شد.");
                     // Clear user state if they were awaiting reply for this ticket
                    $userState = $this->userModel->getUserState($user['telegram_id_hash']);
                    if ($userState && isset($userState['action']) && $userState['action'] === 'awaiting_user_reply_to_ticket' && isset($userState['ticket_id']) && (int)$userState['ticket_id'] === $ticketId) {
                        $this->userModel->updateUser($user['telegram_id_hash'], ['user_state' => null]);
                    }
                } catch (\Exception $e) {
                    error_log("Failed to notify user about ticket #{$ticketId} closure: " . $e->getMessage());
                }
            }
        } else {
            $responseText = "خطا در بستن تیکت #{$ticketId}.";
        }
        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $responseText, null);
        else $this->telegramAPI->sendMessage($chatId, $responseText, null);

        $this->showSupportTicketsMenu($telegramId, $chatId, null); // Go back to support menu
    }


    // --- Admin Statistics ---
    public function showStatistics(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }

        $totalUsers = $this->userModel->getTotalUserCount();
        $activeSubscriptions = $this->userModel->getActiveSubscriptionCount();
        $activeFreeTrials = $this->userModel->getActiveFreeTrialCount();
        $partnerConnected = $this->userModel->getPartnerConnectedCount();
        $totalReferred = $this->userModel->getTotalReferredUsersCount();
        // $totalRevenue = $this->transactionModel->getTotalRevenue(); // Assuming a TransactionModel

        $text = "📊 **آمار کلی ربات** 📊\n\n";
        $text .= "👤 کل کاربران ثبت‌نام شده: {$totalUsers}\n";
        $text .= "💳 اشتراک‌های فعال (پولی): {$activeSubscriptions}\n";
        $text .= "🎁 دوره‌های رایگان فعال: {$activeFreeTrials}\n";
        $text .= "💞 کاربران متصل به همراه: {$partnerConnected} (جفت)\n";
        $text .= "🔗 کل کاربران معرفی شده: {$totalReferred}\n";
        // $text .= "💰 مجموع درآمد (تومان): " . number_format($totalRevenue) . "\n"; // Example

        $buttons = [[['text' => "🔙 بازگشت به پنل ادمین", 'callback_data' => 'admin_show_menu']]];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        }
    }

    // --- User Management (Admin) ---
    public function promptFindUser(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $this->updateUserState($telegramId, ['action' => 'admin_awaiting_user_identifier']);
        $text = "👤 **مدیریت کاربر**\n\nلطفا شناسه تلگرام (عددی) یا نام کاربری تلگرام (همراه با @) کاربری که می‌خواهید مدیریت کنید را وارد نمایید.\n\nبرای لغو /cancel_admin_action را ارسال کنید.";

        $keyboard = [['inline_keyboard' => [[['text' => '🔙 بازگشت به پنل ادمین', 'callback_data' => 'admin_show_menu']]]]];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        }
    }

    // This method will be called from public/index.php when admin sends user identifier
    public function findAndShowUserManagementMenu(string $adminTelegramId, int $adminChatId, string $identifier) {
        if (!$this->isAdmin($adminTelegramId)) { return; }
        $this->updateUserState($adminTelegramId, null); // Clear state

        $foundUser = null;
        if (ctype_digit($identifier)) {
            $foundUser = $this->userModel->findUserByActualOrHashedTelegramId($identifier);
        } elseif (strpos($identifier, '@') === 0) {
            $usernameToSearch = substr($identifier, 1);
            $foundUser = $this->userModel->findUserByUsername($usernameToSearch);
        } else {
             // Try as username without @
            $foundUser = $this->userModel->findUserByUsername($identifier);
            if(!$foundUser){ // If still not found, try as if it was a numeric ID string
                 $foundUser = $this->userModel->findUserByActualOrHashedTelegramId($identifier);
            }
        }

        if (!$foundUser) {
            $this->telegramAPI->sendMessage($adminChatId, "کاربری با شناسه `{$identifier}` یافت نشد. لطفا دوباره تلاش کنید.", null, "Markdown");
            $this->promptFindUser($adminTelegramId, $adminChatId, null); // Show prompt again
            return;
        }

        // Decrypt user details for display
        $displayFirstName = "[رمزگشایی ناموفق]";
        $displayUsername = "[بدون نام کاربری]";
        $displayRole = "[نامشخص]";
        try {
            if (!empty($foundUser['encrypted_first_name'])) $displayFirstName = EncryptionHelper::decrypt($foundUser['encrypted_first_name']);
            if (!empty($foundUser['encrypted_username'])) $displayUsername = "@" . EncryptionHelper::decrypt($foundUser['encrypted_username']);
            if (!empty($foundUser['encrypted_role'])) $displayRole = $this->translateRole(EncryptionHelper::decrypt($foundUser['encrypted_role']));
        } catch (\Exception $e) {
            error_log("Admin: Error decrypting user details for ID {$foundUser['id']}: " . $e->getMessage());
        }

        $text = "👤 **مدیریت کاربر: {$displayFirstName}** ({$displayUsername})\n";
        $text .= "ID داخلی: `{$foundUser['id']}`\n";
        $text .= "ID تلگرام (هش شده): `{$foundUser['telegram_id_hash']}`\n";
        $text .= "نقش: {$displayRole}\n";
        $text .= "وضعیت اشتراک: `{$foundUser['subscription_status']}`\n";
        if ($foundUser['subscription_status'] === 'active' && !empty($foundUser['subscription_ends_at'])) {
            $text .= "پایان اشتراک: " . (new \DateTime($foundUser['subscription_ends_at']))->format('Y-m-d H:i:s') . "\n";
        } elseif ($foundUser['subscription_status'] === 'free_trial' && !empty($foundUser['trial_ends_at'])) {
            $text .= "پایان دوره رایگان: " . (new \DateTime($foundUser['trial_ends_at']))->format('Y-m-d H:i:s') . "\n";
        }
        $text .= "تاریخ عضویت: " . (new \DateTime($foundUser['created_at']))->format('Y-m-d H:i:s') . "\n";
        // Add more details as needed (e.g., partner info)

        $buttons = [
            [['text' => "✏️ ویرایش اشتراک کاربر", 'callback_data' => 'admin_user_edit_sub_prompt:' . $foundUser['id']]],
            // [['text' => "🗑 حذف کاربر (به زودی)", 'callback_data' => 'admin_user_delete_confirm:' . $foundUser['id']]], // Future
            [['text' => "🔙 جستجوی کاربر دیگر", 'callback_data' => 'admin_user_manage_prompt_find']],
            [['text' => "🏠 بازگشت به پنل ادمین", 'callback_data' => 'admin_show_menu']],
        ];
        $keyboard = ['inline_keyboard' => $buttons];
        $this->telegramAPI->sendMessage($adminChatId, $text, $keyboard, 'Markdown');
    }

    // This method is called by callback 'admin_user_manage_show:USER_DB_ID'
    public function handleShowUserManagementMenuCallback(string $adminTelegramId, int $adminChatId, ?int $messageId, int $userDbId) {
        if (!$this->isAdmin($adminTelegramId)) { return; }
        $this->updateUserState($adminTelegramId, null); // Clear state

        $foundUser = $this->userModel->findUserById($userDbId); // Find by internal DB ID

        if (!$foundUser) {
            $errorText = "کاربری با شناسه داخلی `{$userDbId}` یافت نشد.";
            if ($messageId) $this->telegramAPI->editMessageText($adminChatId, $messageId, $errorText, null, "Markdown");
            else $this->telegramAPI->sendMessage($adminChatId, $errorText, null, "Markdown");
            $this->promptFindUser($adminTelegramId, $adminChatId, null); // Show prompt again
            return;
        }

        // Decrypt user details for display
        $displayFirstName = "[رمزگشایی ناموفق]";
        $displayUsername = "[بدون نام کاربری]";
        $displayRole = "[نامشخص]";
        try {
            if (!empty($foundUser['encrypted_first_name'])) $displayFirstName = EncryptionHelper::decrypt($foundUser['encrypted_first_name']);
            if (!empty($foundUser['encrypted_username'])) $displayUsername = "@" . EncryptionHelper::decrypt($foundUser['encrypted_username']);
            if (!empty($foundUser['encrypted_role'])) $displayRole = $this->translateRole(EncryptionHelper::decrypt($foundUser['encrypted_role']));
        } catch (\Exception $e) {
            error_log("Admin: Error decrypting user details for ID {$foundUser['id']}: " . $e->getMessage());
        }

        $text = "👤 **مدیریت کاربر: {$displayFirstName}** ({$displayUsername})\n";
        $text .= "ID داخلی: `{$foundUser['id']}`\n";
        $text .= "ID تلگرام (هش شده): `{$foundUser['telegram_id_hash']}`\n";
        $text .= "نقش: {$displayRole}\n";
        $text .= "وضعیت اشتراک: `{$foundUser['subscription_status']}`\n";
        if ($foundUser['subscription_status'] === 'active' && !empty($foundUser['subscription_ends_at'])) {
            $text .= "پایان اشتراک: " . (new \DateTime($foundUser['subscription_ends_at']))->format('Y-m-d H:i:s') . "\n";
        } elseif ($foundUser['subscription_status'] === 'free_trial' && !empty($foundUser['trial_ends_at'])) {
            $text .= "پایان دوره رایگان: " . (new \DateTime($foundUser['trial_ends_at']))->format('Y-m-d H:i:s') . "\n";
        }
        $text .= "تاریخ عضویت: " . (new \DateTime($foundUser['created_at']))->format('Y-m-d H:i:s') . "\n";

        $buttons = [
            [['text' => "✏️ ویرایش اشتراک کاربر", 'callback_data' => 'admin_user_edit_sub_prompt:' . $foundUser['id']]],
            [['text' => "🔙 جستجوی کاربر دیگر", 'callback_data' => 'admin_user_manage_prompt_find']],
            [['text' => "🏠 بازگشت به پنل ادمین", 'callback_data' => 'admin_show_menu']],
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) $this->telegramAPI->editMessageText($adminChatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($adminChatId, $text, $keyboard, 'Markdown');
    }


    // Placeholder for translateRole, actual implementation may vary
    private function translateRole($roleKey) {
        $roles = ['menstruating' => 'فرد پریود شونده', 'partner' => 'همراه', 'prefer_not_to_say' => 'ترجیح داده نگوید'];
        return $roles[$roleKey] ?? $roleKey;
    }

    private function translateCyclePhase($phaseKey) {
        $phases = [
            'menstruation' => 'پریود (قاعدگی)',
            'follicular' => 'فولیکولار',
            'ovulation' => 'تخمک‌گذاری',
            'luteal' => 'لوتئال',
            'pms' => 'PMS',
            'any' => 'عمومی (همه فازها)'
        ];
        return $phases[$phaseKey] ?? $phaseKey;
    }

    // --- Zarinpal Transaction Listing (Admin) ---
    public function listZarinpalTransactions(string $telegramId, int $chatId, ?int $messageId, int $page = 0) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }

        $zarinpalService = new \Services\ZarinpalService();
        $perPage = 10;
        $offset = $page * $perPage;

        $transactions = $zarinpalService->getAllTransactionsAdmin($perPage, $offset);
        $totalTransactions = $zarinpalService->countAllTransactionsAdmin();
        $totalPages = ceil($totalTransactions / $perPage);

        $text = "📜 **لیست تراکنش‌های زرین‌پال** (صفحه " . ($page + 1) . " از {$totalPages})\n\n";
        $buttons = [];

        if (empty($transactions)) {
            $text .= "هیچ تراکنشی یافت نشد.";
        } else {
            foreach ($transactions as $tx) {
                $statusFa = match ($tx['status']) {
                    'pending' => 'در انتظار پرداخت',
                    'completed' => 'موفق ✅',
                    'failed' => 'ناموفق ❌',
                    default => $tx['status'],
                };
                $userName = $tx['user_first_name'] ?? "کاربر {$tx['user_id']}";
                $text .= " شناسه: `{$tx['id']}` | کاربر: {$userName}\n";
                $text .= " مبلغ: " . number_format($tx['amount']) . " تومان | وضعیت: {$statusFa}\n";
                $text .= " طرح: " . ($tx['plan_id'] ? "ID {$tx['plan_id']}" : "نامشخص") . "\n";
                if ($tx['zarinpal_authority']) $text .= " کد زرین‌پال: `{$tx['zarinpal_authority']}`\n";
                if ($tx['zarinpal_ref_id']) $text .= " کد پیگیری: `{$tx['zarinpal_ref_id']}`\n";
                $text .= " تاریخ: " . (new \DateTime($tx['created_at']))->format('Y-m-d H:i') . "\n";
                $text .= " توضیحات: " . ($tx['description'] ?? '-') . "\n";
                $text .= "--------------------\n";
            }
        }

        $paginationButtons = [];
        if ($page > 0) $paginationButtons[] = ['text' => '⬅️ قبلی', 'callback_data' => "admin_list_transactions:" . ($page - 1)];
        if (($page + 1) < $totalPages) $paginationButtons[] = ['text' => '➡️ بعدی', 'callback_data' => "admin_list_transactions:" . ($page + 1)];
        if (!empty($paginationButtons)) $buttons[] = $paginationButtons;

        $buttons[] = [['text' => "🔙 بازگشت به پنل ادمین", 'callback_data' => 'admin_show_menu']];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    // --- Broadcast Message ---
    public function promptBroadcastMessage(string $adminTelegramId, int $adminChatId, ?int $messageId = null) {
        if (!$this->isAdmin($adminTelegramId)) { return; }
        $this->updateUserState($adminTelegramId, ['action' => 'admin_awaiting_broadcast_message']);
        $text = "📢 **ارسال پیام همگانی**\n\nلطفا متن پیامی که می‌خواهید برای همه کاربران ارسال شود را وارد کنید.\n\n⚠️ احتیاط: این پیام برای تمام کاربران ارسال خواهد شد.\n(برای لغو /cancel_admin_action را ارسال کنید)";

        $keyboard = [['inline_keyboard' => [[['text' => '🔙 بازگشت به پنل ادمین', 'callback_data' => 'admin_show_menu']]]]];

        if ($messageId) {
            $this->telegramAPI->editMessageText($adminChatId, $messageId, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($adminChatId, $text, $keyboard);
        }
    }

    public function confirmBroadcastMessage(string $adminTelegramId, int $adminChatId, string $messageText) {
        if (!$this->isAdmin($adminTelegramId)) { return; }

        $messageHash = md5($messageText);
        $this->updateUserState($adminTelegramId, [
            'action' => 'admin_confirming_broadcast',
            'message_text' => $messageText,
            'message_hash' => $messageHash
        ]);

        $text = "⚠️ **تایید ارسال پیام همگانی** ⚠️\n\nشما قصد دارید پیام زیر را برای همه کاربران ارسال کنید:\n\n---\n{$messageText}\n---\n\nآیا مطمئن هستید؟";
        $buttons = [
            [['text' => "✅ بله، ارسال کن", 'callback_data' => 'admin_broadcast_send_confirm:' . $messageHash]],
            [['text' => "❌ خیر، لغو کن", 'callback_data' => 'admin_broadcast_prompt']]
        ];
        $keyboard = ['inline_keyboard' => $buttons];
        $this->telegramAPI->sendMessage($adminChatId, $text, $keyboard);
    }

    public function handleSendBroadcastMessage(string $adminTelegramId, int $adminChatId, ?int $messageId, string $confirmedHash) {
        if (!$this->isAdmin($adminTelegramId)) { return; }

        $stateInfo = $this->getCurrentAdminState($adminTelegramId);

        if (!$stateInfo || ($stateInfo['action'] ?? '') !== 'admin_confirming_broadcast' || ($stateInfo['message_hash'] ?? '') !== $confirmedHash) {
            $this->telegramAPI->sendMessage($adminChatId, "خطا: تاییدیه ارسال پیام نامعتبر است یا منقضی شده. لطفا دوباره تلاش کنید.");
            $this->updateUserState($adminTelegramId, null);
            $this->showAdminMenu($adminTelegramId, $adminChatId, $messageId);
            return;
        }

        $messageText = $stateInfo['message_text'];
        $this->updateUserState($adminTelegramId, null);

        if ($messageId) {
            $this->telegramAPI->editMessageText($adminChatId, $messageId, "⏳ در حال ارسال پیام همگانی... لطفا صبر کنید.", null);
        } else {
            $this->telegramAPI->sendMessage($adminChatId, "⏳ در حال ارسال پیام همگانی... لطفا صبر کنید.");
        }

        $users = $this->userModel->getAllUsersForBroadcast();
        $sentCount = 0;
        $failedCount = 0;
        $blockedCount = 0;

        foreach ($users as $user) {
            if (empty($user['encrypted_chat_id'])) continue;
            try {
                $chatIdToSend = EncryptionHelper::decrypt($user['encrypted_chat_id']);
                $sendResult = $this->telegramAPI->sendMessage((int)$chatIdToSend, $messageText);
                if ($sendResult && $sendResult['ok']) {
                    $sentCount++;
                } else {
                    if (isset($sendResult['error_code']) && ($sendResult['error_code'] == 403 || $sendResult['error_code'] == 400)) {
                        $blockedCount++;
                         // $this->userModel->updateUserByDBId($user['id'], ['is_bot_blocked' => 1]); // Assumes updateUserByDBId exists
                    } else {
                        $failedCount++;
                    }
                    error_log("Broadcast failed for user ID {$user['id']}: " . ($sendResult['description'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                $failedCount++;
                error_log("Broadcast exception for user ID {$user['id']}: " . $e->getMessage());
            }
            if (($sentCount + $failedCount + $blockedCount) % 20 == 0) {
                usleep(500000);
            }
        }

        $summaryText = "✅ عملیات ارسال پیام همگانی انجام شد.\n\n";
        $summaryText .= "تعداد ارسال موفق: {$sentCount}\n";
        $summaryText .= "تعداد ارسال ناموفق (خطا): {$failedCount}\n";
        $summaryText .= "تعداد کاربران مسدود کرده ربات: {$blockedCount}\n";

        $this->telegramAPI->sendMessage($adminChatId, $summaryText);
        $this->showAdminMenu($adminTelegramId, $adminChatId, null);
    }


    public function promptEditUserSubscription(string $adminTelegramId, int $adminChatId, ?int $messageId, int $userIdToEdit) {
        if (!$this->isAdmin($adminTelegramId)) { return; }

        $user = $this->userModel->findUserById($userIdToEdit);
        if (!$user) {
            $this->telegramAPI->editMessageText($adminChatId, $messageId ?? 0, "خطا: کاربر یافت نشد.", null);
            return;
        }

        $displayFirstName = "[کاربر]";
        try { if(!empty($user['encrypted_first_name'])) $displayFirstName = EncryptionHelper::decrypt($user['encrypted_first_name']); } catch (\Exception $e) {}

        $text = "✏️ **ویرایش اشتراک کاربر: {$displayFirstName} (ID: {$userIdToEdit})**\n\n";
        $text .= "وضعیت فعلی: `{$user['subscription_status']}`\n";
        if ($user['active_plan_id']) {
            $currentPlan = $this->subscriptionPlanModel->getPlanById($user['active_plan_id']);
            $text .= "طرح فعلی: " . ($currentPlan ? $currentPlan['name'] : "ID: {$user['active_plan_id']}") . "\n";
        }
        if ($user['subscription_ends_at']) {
            $text .= "تاریخ پایان فعلی: " . (new \DateTime($user['subscription_ends_at']))->format('Y-m-d H:i:s') . "\n";
        } else if ($user['trial_ends_at'] && $user['subscription_status'] === 'free_trial'){
             $text .= "تاریخ پایان دوره رایگان: " . (new \DateTime($user['trial_ends_at']))->format('Y-m-d H:i:s') . "\n";
        }
        $text .= "\n---\n";

        $plans = $this->subscriptionPlanModel->getActivePlans();
        if (!empty($plans)) {
            $text .= "طرح‌های اشتراک موجود:\n";
            foreach ($plans as $plan) {
                $text .= "- `{$plan['id']}`: {$plan['name']} ({$plan['duration_months']} ماهه - " . number_format($plan['price']) . " تومان)\n";
            }
            $text .= "\nلطفا ID طرح جدید را وارد کنید.\n";
        } else {
            $text .= "هیچ طرح اشتراک فعالی برای انتخاب وجود ندارد.\n";
        }
        $text .= "یا برای تغییر فقط تاریخ انقضا طرح فعلی (اگر دارد) `0` را وارد کنید.\n";
        $text .= "یا برای لغو اشتراک فعلی و رایگان کردن کاربر `remove` را وارد کنید.\n";
        $text .= "(برای لغو عملیات /cancel_admin_action را ارسال کنید)";

        $this->updateUserState($adminTelegramId, ['action' => 'admin_editing_user_sub', 'step' => 'awaiting_plan_choice', 'user_id_to_edit' => $userIdToEdit]);

        $keyboard = [['inline_keyboard' => [[['text' => '🔙 بازگشت به مدیریت کاربر', 'callback_data' => 'admin_user_manage_show:' . $userIdToEdit ]]]]]; // Requires findAndShow to be callable via callback
        if($messageId) $this->telegramAPI->editMessageText($adminChatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($adminChatId, $text, $keyboard, 'Markdown');
    }

    public function processUserSubscriptionPlanChoice(string $adminTelegramId, int $adminChatId, int $userIdToEdit, string $chosenPlanInput) {
        if (!$this->isAdmin($adminTelegramId)) { return; }

        $chosenPlanInput = trim(strtolower($chosenPlanInput));
        $user = $this->userModel->findUserById($userIdToEdit);
        if (!$user) {
            $this->telegramAPI->sendMessage($adminChatId, "خطا: کاربر برای ویرایش اشتراک یافت نشد.");
            $this->updateUserState($adminTelegramId, null);
            return;
        }

        $planIdToAssign = null;
        $nextStepState = 'awaiting_expiry';

        if ($chosenPlanInput === 'remove') {
            // Mark for removal, expiry will be set to past or null.
            $planIdToAssign = 'remove'; // Special keyword
        } elseif ($chosenPlanInput === '0') {
            if ($user['active_plan_id']) {
                $planIdToAssign = $user['active_plan_id']; // Keep current plan
            } else {
                $this->telegramAPI->sendMessage($adminChatId, "کاربر طرح فعال جاری ندارد که فقط تاریخ انقضای آن تغییر کند. لطفا یک طرح انتخاب کنید یا `remove` را برای رایگان کردن وارد نمایید.");
                $this->updateUserState($adminTelegramId, ['action' => 'admin_editing_user_sub', 'step' => 'awaiting_plan_choice', 'user_id_to_edit' => $userIdToEdit]); // Ask again
                return;
            }
        } elseif (ctype_digit($chosenPlanInput)) {
            $planIdToAssign = (int)$chosenPlanInput;
            $planExists = $this->subscriptionPlanModel->getPlanById($planIdToAssign);
            if (!$planExists) {
                $this->telegramAPI->sendMessage($adminChatId, "طرح با ID وارد شده یافت نشد. لطفا ID معتبر وارد کنید.");
                $this->updateUserState($adminTelegramId, ['action' => 'admin_editing_user_sub', 'step' => 'awaiting_plan_choice', 'user_id_to_edit' => $userIdToEdit]); // Ask again
                return;
            }
        } else {
            $this->telegramAPI->sendMessage($adminChatId, "ورودی نامعتبر. لطفا ID طرح، 0، یا `remove` را وارد کنید.");
            $this->updateUserState($adminTelegramId, ['action' => 'admin_editing_user_sub', 'step' => 'awaiting_plan_choice', 'user_id_to_edit' => $userIdToEdit]); // Ask again
            return;
        }

        $text = "تاریخ پایان اشتراک جدید را وارد کنید (YYYY-MM-DD HH:MM:SS).\n";
        $text .= "برای اشتراک بدون تاریخ انقضا (نامحدود) `never` را وارد کنید.\n";
        $text .= "برای رایگان کردن و حذف تاریخ انقضا (اگر `remove` را انتخاب کردید) `remove` را مجدد وارد کنید.\n";
        $text .= "(برای لغو عملیات /cancel_admin_action را ارسال کنید)";

        $this->updateUserState($adminTelegramId, ['action' => 'admin_editing_user_sub', 'step' => $nextStepState, 'user_id_to_edit' => $userIdToEdit, 'plan_id_to_assign' => $planIdToAssign]);
        $this->telegramAPI->sendMessage($adminChatId, $text);
    }

    public function handleUpdateUserSubscription(string $adminTelegramId, int $adminChatId, int $userIdToEdit, $planIdOrKeyword, string $expiryDateString) {
        if (!$this->isAdmin($adminTelegramId)) { return; }
        $this->updateUserState($adminTelegramId, null); // Clear state

        $user = $this->userModel->findUserById($userIdToEdit);
        if (!$user) {
            $this->telegramAPI->sendMessage($adminChatId, "خطا: کاربر یافت نشد.");
            return;
        }

        $updateData = [];
        $expiryDateString = trim(strtolower($expiryDateString));

        if ($planIdOrKeyword === 'remove' || $expiryDateString === 'remove') {
            $updateData['subscription_status'] = 'none'; // Or 'expired' or 'cancelled'
            $updateData['active_plan_id'] = null;
            $updateData['subscription_ends_at'] = null;
            $updateData['subscription_starts_at'] = null;
            // Also nullify trial if it was active
            if ($user['subscription_status'] === 'free_trial') {
                $updateData['trial_ends_at'] = null;
            }
        } else {
            $planIdToAssign = (int)$planIdOrKeyword;
            $plan = $this->subscriptionPlanModel->getPlanById($planIdToAssign);
            if (!$plan && $planIdToAssign !== 0) { // 0 means keep current plan, just change expiry
                 $this->telegramAPI->sendMessage($adminChatId, "خطا: طرح اشتراک انتخاب شده نامعتبر است.");
                 // Re-prompt or send back to user management menu
                 $this->findAndShowUserManagementMenu($adminTelegramId, $adminChatId, (string)$user['telegram_id_hash']); // Assuming this works with hash
                 return;
            }

            $updateData['active_plan_id'] = $planIdToAssign === 0 ? $user['active_plan_id'] : $planIdToAssign;
            if ($updateData['active_plan_id'] === null && $planIdToAssign !== 0) {
                 $this->telegramAPI->sendMessage($adminChatId, "خطا: کاربر طرح فعالی برای تمدید ندارد و طرح جدیدی هم انتخاب نشد.");
                 $this->findAndShowUserManagementMenu($adminTelegramId, $adminChatId, (string)$user['telegram_id_hash']);
                 return;
            }

            $updateData['subscription_status'] = 'active';
            $updateData['subscription_starts_at'] = date('Y-m-d H:i:s'); // Start subscription now

            if ($expiryDateString === 'never') {
                $updateData['subscription_ends_at'] = null;
            } else {
                try {
                    $newExpiry = new \DateTime($expiryDateString);
                    $updateData['subscription_ends_at'] = $newExpiry->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $this->telegramAPI->sendMessage($adminChatId, "فرمت تاریخ انقضا نامعتبر است. لطفا از YYYY-MM-DD HH:MM:SS استفاده کنید یا `never`.");
                    // Re-prompt for expiry or send back
                    $this->updateUserState($adminTelegramId, ['action' => 'admin_editing_user_sub', 'step' => 'awaiting_expiry', 'user_id_to_edit' => $userIdToEdit, 'plan_id_to_assign' => $planIdOrKeyword]);
                    return;
                }
            }
            // If they were on trial and now have an active sub, clear trial end date
            $updateData['trial_ends_at'] = null;
        }

        if ($this->userModel->updateUser($user['telegram_id_hash'], $updateData)) {
            $this->telegramAPI->sendMessage($adminChatId, "اشتراک کاربر با موفقیت به‌روزرسانی شد.");
            // Notify user
            if(!empty($user['encrypted_chat_id'])){
                try {
                    $userChatId = EncryptionHelper::decrypt($user['encrypted_chat_id']);
                    $notifyText = "مدیر سیستم اشتراک شما را به‌روزرسانی کرد.\n";
                    if($updateData['subscription_status'] === 'active'){
                        $notifyText .= "وضعیت جدید: فعال ✅";
                        if($updateData['subscription_ends_at']) $notifyText .= "\nتاریخ پایان جدید: " . $updateData['subscription_ends_at'];
                        else $notifyText .= "\nتاریخ پایان: نامحدود";
                    } else {
                         $notifyText .= "وضعیت جدید: غیرفعال/لغو شده";
                    }
                    $this->telegramAPI->sendMessage((int)$userChatId, $notifyText);
                } catch (\Exception $e) {error_log("Failed to notify user {$user['id']} of subscription change: " . $e->getMessage());}
            }
        } else {
            $this->telegramAPI->sendMessage($adminChatId, "خطا در به‌روزرسانی اشتراک کاربر.");
        }
        $this->findAndShowUserManagementMenu($adminTelegramId, $adminChatId, (string)$user['telegram_id_hash']); // Show updated info
    }


    // --- Subscription Plan Management ---
    public function showSubscriptionPlansAdmin(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $allPlans = $this->subscriptionPlanModel->getAllPlansAdmin();
        $text = "مدیریت طرح‌های اشتراک:\n\n";
        $planButtonsRows = [];
        if (empty($allPlans)) {
            $text .= "هیچ طرحی ثبت نشده است.";
        } else {
            foreach ($allPlans as $plan) {
                $status = $plan['is_active'] ? "فعال ✅" : "غیرفعال ❌";
                $text .= "▫️ *{$plan['name']}* ({$plan['duration_months']} ماهه) - " . number_format($plan['price']) . " تومان - {$status}\n";
                $text .= "   `ID: {$plan['id']}`\n";
                $actionText = $plan['is_active'] ? "غیرفعال کردن" : "فعال کردن";
                $planButtonsRows[] = [['text' => "{$actionText} ID:{$plan['id']}", 'callback_data' => 'admin_plan_toggle_active:' . $plan['id'] . '_' . ($plan['is_active'] ? 0 : 1)]];
            }
        }
        $keyboard['inline_keyboard'] = $planButtonsRows;
        $keyboard['inline_keyboard'][] = [['text' => "افزودن طرح جدید +", 'callback_data' => 'admin_plan_prompt_add']];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به پنل ادمین", 'callback_data' => 'admin_show_menu']];
        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function handleTogglePlanActive(string $telegramId, int $chatId, int $messageId, int $planId, int $newState) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $callbackQueryId = $this->telegramAPI->getLastCallbackQueryId($chatId);
        $success = $this->subscriptionPlanModel->togglePlanActive($planId, (bool)$newState);
        if ($success) $this->telegramAPI->answerCallbackQuery($callbackQueryId, "وضعیت طرح تغییر کرد.", false);
        else $this->telegramAPI->answerCallbackQuery($callbackQueryId, "خطا در تغییر وضعیت.", true);
        $this->showSubscriptionPlansAdmin($telegramId, $chatId, $messageId);
    }

    public function promptAddSubscriptionPlan(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $text = "افزودن طرح اشتراک جدید:\n\nلطفا اطلاعات طرح را با فرمت زیر ارسال کنید (هر بخش در یک خط):\nنام طرح\nتوضیحات (اختیاری، برای خط خالی . بگذارید)\nمدت زمان (به ماه، مثلا: 3)\nقیمت (به تومان، مثلا: 50000)\nوضعیت (فعال یا غیرفعال)\n\nمثال:\nاشتراک ویژه سه ماهه\nدسترسی کامل با تخفیف\n3\n45000\nفعال\n\nبرای لغو /cancel_admin_action را ارسال کنید.";
        $this->updateUserState($telegramId, ['action' => 'admin_awaiting_plan_add']);
        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, null, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, null, 'Markdown');
    }

    public function handleAddSubscriptionPlanDetails(string $telegramId, int $chatId, string $messageText) {
        if (!$this->isAdmin($telegramId)) { return; }
        $lines = explode("\n", trim($messageText));
        if (count($lines) !== 5) {
            $this->telegramAPI->sendMessage($chatId, "فرمت ورودی صحیح نیست. باید ۵ خط اطلاعات وارد کنید. لطفا دوباره تلاش کنید یا /cancel_admin_action برای لغو.");
            return;
        }
        list($name, $description, $durationMonths, $price, $statusStr) = array_map('trim', $lines);
        $description = ($description === '.' || $description === '') ? null : $description;
        $durationMonths = (int)$durationMonths;
        $price = (float)$price;
        $isActive = (mb_strtolower($statusStr) === 'فعال');

        if (empty($name) || $durationMonths <= 0 || $price <= 0) {
            $this->telegramAPI->sendMessage($chatId, "اطلاعات نامعتبر (نام، مدت یا قیمت). لطفا دوباره تلاش کنید یا /cancel_admin_action برای لغو.");
            return;
        }
        $planId = $this->subscriptionPlanModel->addPlan($name, $description, $durationMonths, $price, $isActive);
        if ($planId) $this->telegramAPI->sendMessage($chatId, "طرح جدید با موفقیت افزوده شد. ID: {$planId}");
        else $this->telegramAPI->sendMessage($chatId, "خطا در افزودن طرح جدید.");
        $this->updateUserState($telegramId, null);
        $this->showSubscriptionPlansAdmin($telegramId, $chatId, null);
    }

    // --- Educational Content Management ---

    public function showContentAdminMenu(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $this->updateUserState($telegramId, null);
        $text = "📚 مدیریت محتوای آموزشی و آموزش‌ها\n\nانتخاب کنید:";
        $buttons = [
            [['text' => "مشاهده لیست موضوعات/آموزش‌ها", 'callback_data' => 'admin_content_list_topics']],
            [['text' => "افزودن موضوع/آموزش جدید", 'callback_data' => 'admin_content_prompt_add:topic_0']],
            [['text' => "افزودن مطلب/نکته جدید (کلی)", 'callback_data' => 'admin_content_prompt_add:article_0']],
            [['text' => "🔙 بازگشت به پنل ادمین", 'callback_data' => 'admin_show_menu']],
        ];
        $keyboard = ['inline_keyboard' => $buttons];
        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function listTutorialTopicsAdmin(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $topics = $this->educationalContentModel->getTopics();
        $text = "📚 لیست موضوعات اصلی آموزش‌ها:\n\n";
        $topicButtonsRows = [];
        if (empty($topics)) {
            $text .= "هیچ موضوع آموزشی ثبت نشده است.";
        } else {
            foreach ($topics as $topic) {
                $titlePreview = mb_substr($topic['title'], 0, 15) . (mb_strlen($topic['title']) > 15 ? '...' : '');
                $text .= "🔸 *{$topic['title']}* (ID: `{$topic['id']}`)\n";
                $topicButtonsRows[] = [
                    ['text' => "👁️ " . $titlePreview, 'callback_data' => 'admin_content_list_articles:' . $topic['id']],
                    ['text' => "✏️ ویرایش", 'callback_data' => 'admin_content_prompt_edit:' . $topic['id']],
                    ['text' => "🗑 حذف", 'callback_data' => 'admin_content_confirm_delete:' . $topic['id']],
                ];
            }
        }
        $keyboard['inline_keyboard'] = $topicButtonsRows;
        $keyboard['inline_keyboard'][] = [['text' => "افزودن موضوع اصلی جدید +", 'callback_data' => 'admin_content_prompt_add:topic_0']];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به مدیریت محتوا", 'callback_data' => 'admin_content_show_menu']];
        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function listArticlesInTopicAdmin(string $telegramId, int $chatId, ?int $messageId, int $topicId) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $parentTopic = $this->educationalContentModel->getContentById($topicId);
        if (!$parentTopic) {
            $this->telegramAPI->sendMessage($chatId, "خطا: موضوع اصلی یافت نشد.");
            $this->showContentAdminMenu($telegramId, $chatId, $messageId);
            return;
        }
        $articles = $this->educationalContentModel->getContentByParentId($topicId);
        $text = "📚 مطالب داخل موضوع: *{$parentTopic['title']}*\n\n";
        $articleButtonsRows = [];
        if (empty($articles)) {
            $text .= "هیچ مطلبی برای این موضوع ثبت نشده است.";
        } else {
            foreach ($articles as $article) {
                $titlePreview = mb_substr($article['title'], 0, 15) . (mb_strlen($article['title']) > 15 ? '...' : '');
                $text .= "📄 *{$article['title']}* (ID: `{$article['id']}`)\n";
                $articleButtonsRows[] = [
                    ['text' => "✏️ ویرایش " . $titlePreview, 'callback_data' => 'admin_content_prompt_edit:' . $article['id']],
                    ['text' => "🗑 حذف", 'callback_data' => 'admin_content_confirm_delete:' . $article['id']],
                ];
            }
        }
        $keyboard['inline_keyboard'] = $articleButtonsRows;
        $keyboard['inline_keyboard'][] = [['text' => "افزودن مطلب به این موضوع +", 'callback_data' => 'admin_content_prompt_add:article_' . $topicId]];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به لیست موضوعات", 'callback_data' => 'admin_content_list_topics']];
        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function promptAddContent(string $telegramId, int $chatId, ?int $messageId, string $type, int $parentId = 0) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $contentTypeText = ($type === 'topic') ? "موضوع اصلی (آموزش)" : "مطلب/نکته";
        $text = "➕ افزودن {$contentTypeText} جدید\n";
        if ($parentId > 0) {
            $parentTopic = $this->educationalContentModel->getContentById($parentId);
            if ($parentTopic) $text .= "زیرمجموعه: *{$parentTopic['title']}*\n";
        }
        $text .= "\nلطفا عنوان {$contentTypeText} را ارسال کنید (یا /cancel_admin_action برای لغو):";
        $initialData = ['type' => $type, 'parent_id' => $parentId, 'is_tutorial_topic' => ($type === 'topic')];
        $this->updateUserState($telegramId, ['action' => 'admin_add_content', 'step' => 'awaiting_title', 'data' => $initialData]);
        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, null, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, null, 'Markdown');
    }

    public function promptEditEducationalContent(string $telegramId, int $chatId, ?int $messageId, int $contentId) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }

        $content = $this->educationalContentModel->getContentById($contentId);
        if (!$content) {
            $this->telegramAPI->editMessageText($chatId, $messageId ?? 0, "خطا: محتوا برای ویرایش یافت نشد.", null); // Pass 0 if messageId is null
            $this->showContentAdminMenu($telegramId, $chatId, null);
            return;
        }

        $existingData = [
            'type' => $content['is_tutorial_topic'] ? 'topic' : 'article',
            'parent_id' => $content['parent_id'], // This should generally not be editable this way
            'title' => $content['title'] ?? '',
            'content_topic' => $content['content_topic'] ?? '',
            'target_role' => $content['target_role'] ?? 'both',
            'content_type' => $content['content_type'] ?? 'text',
            'content_data' => $content['content_data'] ?? '',
            'image_url' => $content['image_url'] ?? null,
            'video_url' => $content['video_url'] ?? null,
            'source_url' => $content['source_url'] ?? null,
            'read_more_link' => $content['read_more_link'] ?? null,
            'cycle_phase_association' => $content['cycle_phase_association'] ?? 'any',
            'symptom_association_keys' => $content['symptom_association_keys'] ?? [],
            'tags' => $content['tags'] ?? [],
            'is_tutorial_topic' => (bool)$content['is_tutorial_topic'],
            'is_active' => (bool)$content['is_active'],
            'sequence_order' => $content['sequence_order'] ?? 0,
            'slug' => $content['slug'] ?? '' // Slug might need careful handling if title changes
        ];

        $contentTypeText = $existingData['type'] === 'topic' ? "موضوع اصلی (آموزش)" : "مطلب/نکته";
        $text = "✏️ ویرایش {$contentTypeText}: *{$existingData['title']}*\n";
        $text .= "(ID: `{$contentId}`)\n\n";
        $text .= "عنوان فعلی: `{$existingData['title']}`\n";
        $text .= "لطفا عنوان جدید را ارسال کنید، یا برای بدون تغییر ماندن `.` (نقطه تنها) ارسال کنید.\n";
        $text .= "(یا /cancel_admin_action برای لغو کل ویرایش)";

        $this->updateUserState($telegramId, ['action' => 'admin_edit_content', 'step' => 'awaiting_title', 'content_id' => $contentId, 'data' => $existingData]);

        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, null, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, null, 'Markdown');
    }


    public function handleAdminConversation(string $telegramId, int $chatId, string $messageText, array $stateInfo) {
        if (!$this->isAdmin($telegramId)) return;

        $currentAction = $stateInfo['action'] ?? null;
        $currentStep = $stateInfo['step'] ?? null;
        $collectedData = $stateInfo['data'] ?? [];
        $contentId = $stateInfo['content_id'] ?? null; // For edits

        $isEdit = ($currentAction === 'admin_edit_content');
        $textToProcess = trim($messageText);
        $keepCurrentValue = ($isEdit && $textToProcess === '.');

        // Pass $contentId to process methods only if relevant for edit prompts or next step decisions
        switch ($currentStep) {
            case 'awaiting_title': $this->processContentTitle($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_content_topic': $this->processContentTopic($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_content_data': $this->processContentData($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_image_url': $this->processImageUrl($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_video_url': $this->processVideoUrl($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_source_url': $this->processSourceUrl($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_read_more_link': $this->processReadMoreLink($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_symptom_association_keys': $this->processSymptomKeys($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_tags': $this->processTags($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_sequence_order': $this->processSequenceOrder($telegramId, $chatId, $textToProcess, $collectedData, $isEdit, $keepCurrentValue, $contentId); break;
            case 'awaiting_confirmation_save':
                 if (mb_strtolower($textToProcess) === 'بله') {
                     if ($isEdit && $contentId) $this->saveEditedEducationalContent($telegramId, $chatId, $contentId, $collectedData);
                     else $this->saveNewEducationalContent($telegramId, $chatId, $collectedData);
                 } else {
                    $this->telegramAPI->sendMessage($chatId, ($isEdit ? "ویرایش" : "افزودن") . " محتوا لغو شد.");
                    $this->updateUserState($telegramId, null);
                    $this->showContentAdminMenu($telegramId, $chatId);
                 }
                break;
            default:
                $this->telegramAPI->sendMessage($chatId, "مرحله نامشخص ({$currentStep}). عملیات لغو شد.");
                $this->updateUserState($telegramId, null);
                $this->showAdminMenu($telegramId, $chatId);
                break;
        }
    }

    private function promptForNextStep(string $telegramId, int $chatId, array &$collectedData, string $nextStep, string $promptText, ?array $keyboard = null, ?int $contentIdToEdit = null) {
        $action = $contentIdToEdit ? 'admin_edit_content' : 'admin_add_content';
        $state = ['action' => $action, 'step' => $nextStep, 'data' => $collectedData];
        if ($contentIdToEdit) {
            $state['content_id'] = $contentIdToEdit;
        }
        $this->updateUserState($telegramId, $state);
        // Always send new message for prompts in conversational flow for clarity
        $this->telegramAPI->sendMessage($chatId, $promptText, $keyboard, 'Markdown');
    }

    private function getRoleButtons() { return ['inline_keyboard' => [[['text' => "فرد پریود شونده", 'callback_data' => 'admin_content_setparam:target_role_menstruating']], [['text' => "همراه", 'callback_data' => 'admin_content_setparam:target_role_partner']], [['text' => "هر دو", 'callback_data' => 'admin_content_setparam:target_role_both']]]]; }
    private function getContentTypeButtons() { return ['inline_keyboard' => [[['text' => "متن", 'callback_data' => 'admin_content_setparam:content_type_text'], ['text' => "متن+تصویر", 'callback_data' => 'admin_content_setparam:content_type_text_with_image']], [['text' => "لینک ویدیو", 'callback_data' => 'admin_content_setparam:content_type_video_link'], ['text' => "مقاله خارجی", 'callback_data' => 'admin_content_setparam:content_type_external_article']]]]; }
    private function getCyclePhaseButtons() { return ['inline_keyboard' => [[['text' => "پریود", 'callback_data' => 'admin_content_setparam:cycle_phase_association_menstruation'], ['text' => "فولیکولار", 'callback_data' => 'admin_content_setparam:cycle_phase_association_follicular']], [['text' => "تخمک‌گذاری", 'callback_data' => 'admin_content_setparam:cycle_phase_association_ovulation'], ['text' => "لوتئال", 'callback_data' => 'admin_content_setparam:cycle_phase_association_luteal']], [['text' => "PMS", 'callback_data' => 'admin_content_setparam:cycle_phase_association_pms'], ['text' => "عمومی (Any)", 'callback_data' => 'admin_content_setparam:cycle_phase_association_any']]]]; }
    private function getYesNoButtons(string $fieldPrefix) { return ['inline_keyboard' => [[['text' => "بله", 'callback_data' => "admin_content_setparam:{$fieldPrefix}_1"], ['text' => "خیر", 'callback_data' => "admin_content_setparam:{$fieldPrefix}_0"]]]]; }

    private function formatCollectedDataForReview(array $data, bool $isEdit = false): string {
        $review = $isEdit ? "مرور اطلاعات ویرایش شده برای ذخیره:\n" : "مرور اطلاعات وارد شده برای ذخیره:\n";
        if ($isEdit && isset($data['id_to_edit'])) $review .= "- ID محتوا: `{$data['id_to_edit']}`\n"; // Use content_id from state
        else if ($isEdit && isset($stateInfo['content_id'])) $review .= "- ID محتوا: `{$stateInfo['content_id']}`\n";


        $typeDisplay = $data['is_tutorial_topic'] ? 'موضوع اصلی (آموزش)' : 'مطلب/نکته';
        if ($data['type'] && $data['type'] !== ($data['is_tutorial_topic'] ? 'topic' : 'article')) { // Check consistency
             $typeDisplay = $data['type'] . ($data['is_tutorial_topic'] ? ' (موضوع)' : ' (مطلب)');
        }

        $review .= "- نوع: " . $typeDisplay . ($data['parent_id'] ? " (زیرمجموعه ID: {$data['parent_id']})" : " (آیتم سطح بالا)") . "\n";
        $review .= "- عنوان: *" . ($data['title'] ?? 'خالی') . "*\n";
        $review .= "- موضوع کلی: " . ($data['content_topic'] ?? 'خالی') . "\n";
        $review .= "- نقش مخاطب: " . ($this->translateRole($data['target_role'] ?? 'خالی')) . "\n";
        $review .= "- نوع محتوا: " . ($data['content_type'] ?? 'خالی') . "\n";
        $review .= "- متن محتوا: " . mb_substr(($data['content_data'] ?? 'خالی'), 0, 70) . (mb_strlen(($data['content_data'] ?? '')) > 70 ? "..." : "") ."\n";
        $review .= "- URL تصویر: " . ($data['image_url'] ?? 'ندارد') . "\n";
        $review .= "- URL ویدیو: " . ($data['video_url'] ?? 'ندارد') . "\n";
        $review .= "- URL منبع: " . ($data['source_url'] ?? 'ندارد') . "\n";
        $review .= "- لینک بیشتر: " . ($data['read_more_link'] ?? 'ندارد') . "\n";
        $review .= "- فاز چرخه: " . ($this->translateCyclePhase($data['cycle_phase_association'] ?? 'عمومی')) . "\n";
        $review .= "- کلیدهای علائم: " . (!empty($data['symptom_association_keys']) ? implode(', ', $data['symptom_association_keys']) : 'ندارد') . "\n";
        $review .= "- تگ‌ها: " . (!empty($data['tags']) ? implode(', ', $data['tags']) : 'ندارد') . "\n";
        // is_tutorial_topic is now part of the main data collection
        $review .= "- موضوع اصلی آموزش؟: " . (isset($data['is_tutorial_topic']) && $data['is_tutorial_topic'] ? "بله" : "خیر") . "\n";
        $review .= "- ترتیب نمایش: " . ($data['sequence_order'] ?? '0') . "\n";
        $review .= "- فعال؟: " . (isset($data['is_active']) && $data['is_active'] ? "بله" : "خیر") . "\n";
        return $review;
    }

    // Step handlers for text inputs - now with $isEdit, $keepCurrentValue, and $contentId (for edit context)
    private function processContentTitle(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) { /* keep $data['title'] */ } else { if(empty($txt)) {$this->telegramAPI->sendMessage($cId, "عنوان نمی‌تواند خالی باشد."); return;} $data['title'] = $txt; } $this->promptForNextStep($tId, $cId, $data, 'awaiting_content_topic', "✅ عنوان: \"{$data['title']}\"\nموضوع فعلی: `".($isEdit ? $data['content_topic'] : '')."`\nموضوع محتوا (content_topic) را وارد کنید (یا `.` برای بدون تغییر):", null, $contentId); }
    private function processContentTopic(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) {} else { if(empty($txt)) {$this->telegramAPI->sendMessage($cId, "موضوع نمی‌تواند خالی باشد."); return;} $data['content_topic'] = $txt; } $this->promptForNextStep($tId, $cId, $data, 'awaiting_target_role_callback', "✅ موضوع: \"{$data['content_topic']}\"\nنقش مخاطب فعلی: `{$this->translateRole($data['target_role'])}`\nاین محتوا برای کیست؟ (برای بدون تغییر، دکمه فعلی را دوباره بزنید یا از طریق /cancel_admin_action لغو و مجدد ویرایش کنید)", $this->getRoleButtons(), $contentId); }
    private function processContentData(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) {} else { if(empty($txt)) {$this->telegramAPI->sendMessage($cId, "متن محتوا نمی‌تواند خالی باشد."); return;} $data['content_data'] = $txt; } $this->promptForNextStep($tId, $cId, $data, 'awaiting_image_url', "✅ متن محتوا ثبت شد.\nURL تصویر فعلی: `".($data['image_url'] ?? 'ندارد')."`\nURL تصویر (اختیاری، `.` برای بدون تغییر، `خالی` برای حذف/نداشتن):", null, $contentId); }
    private function processImageUrl(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) {} else { $val = trim($txt); $data['image_url'] = (mb_strtolower($val) === 'خالی' || mb_strtolower($val) === 'none' || empty($val)) ? null : $val; } $this->promptForNextStep($tId, $cId, $data, 'awaiting_video_url', "✅ URL تصویر: " . ($data['image_url'] ?: 'ندارد') . "\nURL ویدیو فعلی: `".($data['video_url'] ?? 'ندارد')."`\nURL ویدیو (اختیاری، `.` برای بدون تغییر، `خالی` برای حذف/نداشتن):", null, $contentId); }
    private function processVideoUrl(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) {} else { $val = trim($txt); $data['video_url'] = (mb_strtolower($val) === 'خالی' || mb_strtolower($val) === 'none' || empty($val)) ? null : $val; } $this->promptForNextStep($tId, $cId, $data, 'awaiting_source_url', "✅ URL ویدیو: " . ($data['video_url'] ?: 'ندارد') . "\nURL منبع فعلی: `".($data['source_url'] ?? 'ندارد')."`\nURL منبع (اختیاری، `.` برای بدون تغییر، `خالی` برای حذف/نداشتن):", null, $contentId); }
    private function processSourceUrl(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) {} else { $val = trim($txt); $data['source_url'] = (mb_strtolower($val) === 'خالی' || mb_strtolower($val) === 'none' || empty($val)) ? null : $val; } $this->promptForNextStep($tId, $cId, $data, 'awaiting_read_more_link', "✅ URL منبع: " . ($data['source_url'] ?: 'ندارد') . "\nلینک مطالعه بیشتر فعلی: `".($data['read_more_link'] ?? 'ندارد')."`\nلینک (اختیاری، `.` برای بدون تغییر، `خالی` برای حذف/نداشتن):", null, $contentId); }
    private function processReadMoreLink(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) {} else { $val = trim($txt); $data['read_more_link'] = (mb_strtolower($val) === 'خالی' || mb_strtolower($val) === 'none' || empty($val)) ? null : $val; } $this->promptForNextStep($tId, $cId, $data, 'awaiting_cycle_phase_association_callback', "✅ لینک بیشتر: " . ($data['read_more_link'] ?: 'ندارد') . "\nفاز چرخه فعلی: `{$this->translateCyclePhase($data['cycle_phase_association'])}`\nمربوط به کدام فاز چرخه است؟ (برای بدون تغییر، دکمه فعلی را دوباره بزنید)", $this->getCyclePhaseButtons(), $contentId); }
    private function processSymptomKeys(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) {} else { $val = trim($txt); $keys = (mb_strtolower($val) === 'خالی' || empty($val)) ? [] : array_map('trim', explode(',', $val)); $data['symptom_association_keys'] = $keys; } $this->promptForNextStep($tId, $cId, $data, 'awaiting_tags', "✅ کلیدهای علائم: " . (!empty($data['symptom_association_keys']) ? implode(', ', $data['symptom_association_keys']) : 'ندارد') . "\nتگ‌های فعلی: `" . (!empty($data['tags']) ? implode(', ', $data['tags']) : 'ندارد') . "`\nتگ‌ها (جدا شده با ویرگول، `.` برای بدون تغییر، `خالی` برای حذف):", null, $contentId); }
    private function processTags(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) {} else { $val = trim($txt); $tags = (mb_strtolower($val) === 'خالی' || empty($val)) ? [] : array_map('trim', explode(',', $val)); $data['tags'] = $tags; }
        $nextPromptText = "✅ تگ‌ها: " . (!empty($data['tags']) ? implode(', ', $data['tags']) : 'ندارد') . "\n\n";
        if ($data['type'] === 'topic') {
            $nextPromptText .= "آیا این یک موضوع اصلی آموزش است؟ (فعلی: " . ($data['is_tutorial_topic'] ? "بله" : "خیر") . ")";
            $this->promptForNextStep($tId, $cId, $data, 'awaiting_is_tutorial_topic_callback', $nextPromptText, $this->getYesNoButtons('is_tutorial_topic'), $contentId);
        } else {
            $data['is_tutorial_topic'] = false;
            $nextPromptText .= "شماره ترتیب فعلی: `{$data['sequence_order']}`\nشماره ترتیب (اختیاری، `.` برای بدون تغییر، `خالی` برای 0):";
            $this->promptForNextStep($tId, $cId, $data, 'awaiting_sequence_order', $nextPromptText, null, $contentId);
        }
    }
    private function processSequenceOrder(string $tId, int $cId, string $txt, array &$data, bool $isEdit, bool $keep, ?int $contentId) { if ($isEdit && $keep) {} else { $val = trim($txt); $data['sequence_order'] = (mb_strtolower($val) === 'خالی' || !is_numeric($val) || $val === '') ? 0 : (int)$val; } $this->promptForNextStep($tId, $cId, $data, 'awaiting_is_active_callback', "✅ شماره ترتیب: {$data['sequence_order']}\nوضعیت فعال بودن فعلی: " . ($data['is_active'] ? "بله" : "خیر") . "\nوضعیت فعال بودن؟", $this->getYesNoButtons('is_active'), $contentId); }

    // Callback handler for button presses during content creation/editing
    public function handleAdminContentSetParam(string $telegramId, int $chatId, int $messageId, string $fieldKeyValue) {
        if (!$this->isAdmin($telegramId)) return;

        $stateInfo = $this->getCurrentAdminState($telegramId);
        if (!$stateInfo || !in_array($stateInfo['action'] ?? '', ['admin_add_content', 'admin_edit_content']) || !isset($stateInfo['data'])) {
             $this->telegramAPI->editMessageText($chatId, $messageId, "خطا: عملیات افزودن/ویرایش محتوا فعال نیست یا وضعیت نامعتبر است. لطفا از ابتدا شروع کنید.");
             $this->updateUserState($telegramId, null);
            return;
        }

        $partsInternal = explode('_', $fieldKeyValue, 2);
        $fieldName = $partsInternal[0];
        $fieldValue = $partsInternal[1] ?? null;
        if ($fieldValue === null && !in_array($fieldName, ['is_tutorial_topic', 'is_active'])) {
            error_log("Admin Error: fieldKey missing value in callback data: " . $fieldKeyValue);
            $this->telegramAPI->editMessageText($chatId, $messageId, "خطا در پارامتر دکمه.");
            return;
        }

        $collectedData = $stateInfo['data'];
        $isEdit = ($stateInfo['action'] === 'admin_edit_content');
        $contentIdForEdit = $stateInfo['content_id'] ?? null;

        if ($fieldName === 'is_tutorial_topic' || $fieldName === 'is_active') {
            $collectedData[$fieldName] = (bool)(int)$fieldValue;
        } else {
            $collectedData[$fieldName] = $fieldValue;
        }

        $nextStep = null; $promptText = ""; $promptKeyboard = null; $shouldEditCurrentMessage = true;

        switch ($stateInfo['step']) {
            // These cases are for when the *current step* was awaiting a button press for that field.
            // The $fieldName in $fieldKeyValue tells us which button was pressed.
            case 'awaiting_target_role_callback':
                $promptText = "✅ نقش مخاطب: \"{$this->translateRole($collectedData['target_role'])}\"\n";
                $promptText .= $isEdit ? "نوع محتوای فعلی: `{$collectedData['content_type']}`\n" : "";
                $promptText .= "نوع محتوا را انتخاب کنید:";
                $promptKeyboard = $this->getContentTypeButtons();
                $nextStep = 'awaiting_content_type_callback';
                break;
            case 'awaiting_content_type_callback':
                $promptText = "✅ نوع محتوا: \"{$collectedData['content_type']}\"\n";
                $promptText .= $isEdit ? "متن محتوای فعلی: `" . mb_substr($collectedData['content_data'], 0, 30) . "...`\n" : "";
                $promptText .= "لطفا متن اصلی محتوا (content_data) را ارسال کنید (یا `.` برای بدون تغییر در ویرایش، /cancel_admin_action برای لغو):";
                $nextStep = 'awaiting_content_data';
                $shouldEditCurrentMessage = false;
                break;
            case 'awaiting_cycle_phase_association_callback':
                $promptText = "✅ فاز چرخه: \"{$this->translateCyclePhase($collectedData['cycle_phase_association'])}\"\n";
                $currentSymptoms = ($isEdit && !empty($collectedData['symptom_association_keys'])) ? implode(', ', $collectedData['symptom_association_keys']) : 'ندارد';
                $promptText .= $isEdit ? "علائم مرتبط فعلی: `{$currentSymptoms}`\n" : "";
                $promptText .= "کلیدهای علائم مرتبط را وارد کنید (جدا شده با ویرگول، `.` برای بدون تغییر، `خالی` برای حذف/نداشتن، /cancel_admin_action برای لغو):";
                $nextStep = 'awaiting_symptom_association_keys';
                $shouldEditCurrentMessage = false;
                break;
            case 'awaiting_is_tutorial_topic_callback':
                $promptText = "✅ موضوع اصلی است: " . ($collectedData['is_tutorial_topic'] ? "بله" : "خیر") . "\n";
                $promptText .= $isEdit ? "ترتیب فعلی: `{$collectedData['sequence_order']}`\n" : "";
                $promptText .= "لطفا شماره ترتیب (sequence_order) را وارد کنید (عدد، اختیاری، `.` برای بدون تغییر، `خالی` برای 0، /cancel_admin_action برای لغو):";
                $nextStep = 'awaiting_sequence_order';
                $shouldEditCurrentMessage = false;
                break;
             case 'awaiting_is_active_callback':
                $promptText = "✅ وضعیت فعال بودن: " . ($collectedData['is_active'] ? "بله" : "خیر") . "\n\n";
                $promptText .= $this->formatCollectedDataForReview($collectedData, $isEdit);
                $promptText .= "\n\nبرای ذخیره «بله» یا برای لغو «خیر» یا /cancel_admin_action را ارسال کنید.";
                $nextStep = 'awaiting_confirmation_save';
                $shouldEditCurrentMessage = false;
                break;
            default:
                // This case is hit if a button is pressed but the current step in state wasn't expecting a button for that field.
                // This indicates a logic error in step progression or state management.
                $this->telegramAPI->editMessageText($chatId, $messageId, "خطا: مرحله نامعتبر ({$stateInfo['step']}) برای تنظیم پارامتر ({$fieldName}). عملیات لغو شد.");
                $this->updateUserState($telegramId, null);
                return;
        }

        $this->updateUserState($telegramId, ['action' => $stateInfo['action'], 'step' => $nextStep, 'data' => $collectedData, 'content_id' => $contentIdForEdit]);

        if ($shouldEditCurrentMessage && $messageId) {
             $this->telegramAPI->editMessageText($chatId, $messageId, $promptText, $promptKeyboard, 'Markdown');
        } elseif ($promptText) {
             if($messageId && $promptKeyboard === null && !$shouldEditCurrentMessage) {
                 $ackText = "انتخاب شما: ";
                 if($fieldName === 'target_role') $ackText .= $this->translateRole($collectedData[$fieldName]);
                 elseif($fieldName === 'content_type') $ackText .= $collectedData[$fieldName];
                 elseif($fieldName === 'cycle_phase_association') $ackText .= $this->translateCyclePhase($collectedData[$fieldName]);
                 elseif($fieldName === 'is_tutorial_topic' || $fieldName === 'is_active') $ackText .= ($collectedData[$fieldName] ? 'بله':'خیر');
                 else $ackText .= $collectedData[$fieldName];

                 $this->telegramAPI->editMessageText($chatId, $messageId, $ackText, null, 'Markdown'); // Corrected: null for reply_markup
                 $this->telegramAPI->sendMessage($chatId, $promptText, null, 'Markdown');
             } else {
                $this->telegramAPI->sendMessage($chatId, $promptText, $promptKeyboard, 'Markdown');
             }
        }
    }

    private function saveNewEducationalContent(string $telegramId, int $chatId, array $collectedData) {
        $collectedData['title'] = $collectedData['title'] ?? 'بدون عنوان';
        $collectedData['content_topic'] = $collectedData['content_topic'] ?? 'عمومی';
        $collectedData['target_role'] = $collectedData['target_role'] ?? 'both';
        $collectedData['content_type'] = $collectedData['content_type'] ?? 'text';
        $collectedData['content_data'] = $collectedData['content_data'] ?? 'محتوا ارائه نشده است.';
        $collectedData['cycle_phase_association'] = $collectedData['cycle_phase_association'] ?? 'any';
        if ($collectedData['type'] === 'topic' && !isset($collectedData['is_tutorial_topic'])) {
             $collectedData['is_tutorial_topic'] = true;
        } elseif ($collectedData['type'] === 'article') {
            $collectedData['is_tutorial_topic'] = false;
        }
        $collectedData['is_active'] = $collectedData['is_active'] ?? true;
        $collectedData['sequence_order'] = $collectedData['sequence_order'] ?? 0;

        $contentId = $this->educationalContentModel->addContent($collectedData);

        if ($contentId) {
            $this->telegramAPI->sendMessage($chatId, "محتوای آموزشی با موفقیت ذخیره شد. ID: {$contentId}");
        } else {
            $this->telegramAPI->sendMessage($chatId, "خطا در ذخیره محتوای آموزشی. لطفا لاگ سرور را بررسی کنید.");
        }
        $this->updateUserState($telegramId, null);
        $this->showContentAdminMenu($telegramId, $chatId);
    }

    private function saveEditedEducationalContent(string $telegramId, int $chatId, int $contentId, array $collectedData) {
        unset($collectedData['type']);
        // parent_id is not directly editable in this flow, it's set by where you add/edit from.
        // slug will be regenerated if title changes and no slug is explicitly in $collectedData by model.

        $success = $this->educationalContentModel->updateContent($contentId, $collectedData);

        if ($success) {
            $this->telegramAPI->sendMessage($chatId, "محتوای آموزشی با ID: `{$contentId}` با موفقیت ویرایش شد.", null, "Markdown");
        } else {
            $this->telegramAPI->sendMessage($chatId, "خطا در ویرایش محتوای آموزشی. لطفا لاگ سرور را بررسی کنید.");
        }
        $this->updateUserState($telegramId, null);
        $this->showContentAdminMenu($telegramId, $chatId);
    }

    public function confirmDeleteEducationalContent(string $telegramId, int $chatId, int $messageId, int $contentId) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }

        $content = $this->educationalContentModel->getContentById($contentId);
        if (!$content) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "خطا: محتوا برای حذف یافت نشد.", null);
            $this->showContentAdminMenu($telegramId, $chatId, null);
            return;
        }

        $text = "⚠️ **تایید حذف محتوا** ⚠️\n\n";
        $text .= "آیا از حذف این محتوا مطمئن هستید؟\n";
        $text .= "عنوان: *{$content['title']}*\n";
        $text .= "ID: `{$content['id']}`\n\n";
        $text .= "این عملیات غیرقابل بازگشت است.";

        $cancelCallback = $content['parent_id'] ? ('admin_content_list_articles:' . $content['parent_id']) : 'admin_content_list_topics';
        $buttons = [
            [['text' => "✅ بله، حذف کن", 'callback_data' => 'admin_content_do_delete:' . $contentId]],
            [['text' => "❌ خیر، لغو", 'callback_data' => $cancelCallback]],
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
    }

    public function handleDeleteEducationalContent(string $telegramId, int $chatId, int $messageId, int $contentId) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }
        $callbackQueryId = $this->telegramAPI->getLastCallbackQueryId($chatId);

        $contentToDelete = $this->educationalContentModel->getContentById($contentId);
        if (!$contentToDelete) {
            $this->telegramAPI->answerCallbackQuery($callbackQueryId, "خطا: محتوا یافت نشد.", true);
            $this->showContentAdminMenu($telegramId, $chatId, $messageId);
            return;
        }

        if ($contentToDelete['is_tutorial_topic']) {
            $children = $this->educationalContentModel->getContentByParentId($contentId);
            if (!empty($children)) {
                $this->telegramAPI->answerCallbackQuery($callbackQueryId, "این موضوع مطالب داخلی دارد. ابتدا آنها را حذف کنید.", true);
                $cancelCallback = $contentToDelete['parent_id'] ? ('admin_content_list_articles:' . $contentToDelete['parent_id']) : 'admin_content_list_topics';
                $keyboardOnError = ['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => $cancelCallback ]]]];
                $this->telegramAPI->editMessageText($chatId, $messageId, "❌ **خطا:** این موضوع دارای مطالب داخلی است.\nابتدا آنها را حذف یا به موضوع دیگری منتقل کنید.",
                    $keyboardOnError); // Corrected: pass array directly
                return;
            }
        }

        $success = $this->educationalContentModel->deleteContent($contentId);
        if ($success) {
            $this->telegramAPI->answerCallbackQuery($callbackQueryId, "محتوا با موفقیت حذف شد.", false);
        } else {
            $this->telegramAPI->answerCallbackQuery($callbackQueryId, "خطا در حذف محتوا.", true);
        }

        if ($contentToDelete['parent_id']) {
            $this->listArticlesInTopicAdmin($telegramId, $chatId, $messageId, $contentToDelete['parent_id']);
        } else {
            $this->listTutorialTopicsAdmin($telegramId, $chatId, $messageId);
        }
    }
}
?>
