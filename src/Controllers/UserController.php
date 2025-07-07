<?php

namespace Controllers;

use Models\UserModel;
use Telegram\TelegramAPI;
use Helpers\EncryptionHelper; // Will be created later

class UserController {
    private $userModel;
    private $telegramAPI;

    public function __construct(TelegramAPI $telegramAPI) {
        $this->userModel = new UserModel();
        $this->telegramAPI = $telegramAPI;
    }

    public function handleStart($telegramId, $chatId, $firstName, $username = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            // New user, start registration
            // $chatId is the user's Telegram ID, which is also their chat_id for private messages
            $this->userModel->createUser($hashedTelegramId, (string)$chatId, $firstName, $username);

            $welcomeMessage = "سلام {$firstName}! 👋\nبه ربات «همراه من» خوش آمدید.\n\nاینجا فضایی امن برای شما و همراهتان است تا چرخه قاعدگی را بهتر درک کنید، توصیه‌های روزانه دریافت کنید و با هم در ارتباط باشید.\n\nبرای شروع، لطفا نقش خود را مشخص کنید:";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => "🩸 من پریود می‌شوم", 'callback_data' => 'select_role:menstruating'],
                    ],
                    [
                        ['text' => "🤝 همراه هستم", 'callback_data' => 'select_role:partner'],
                    ],
                    [
                        ['text' => "🚫 ترجیح می‌دهم نگویم", 'callback_data' => 'select_role:prefer_not_to_say'],
                    ]
                ]
            ];
            $this->telegramAPI->sendMessage($chatId, $welcomeMessage, $keyboard);
        } else {
            // Existing user
            // For now, just send a welcome back message. Later, this could be a main menu.
            $this->telegramAPI->sendMessage($chatId, "سلام مجدد {$firstName}! خوشحالیم که دوباره شما را می‌بینیم. 😊");
            // TODO: Show main menu or relevant info for existing user
        }
    }

    public function handleRoleSelection($telegramId, $chatId, $role, $messageId) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "متاسفانه مشکلی در شناسایی شما پیش آمده. لطفا دوباره با ارسال متن یا /start شروع کنید.");
            error_log("Role selection attempted for non-existent hashed_telegram_id: " . $hashedTelegramId);
            return;
        }

        if (empty($user['encrypted_role'])) {
            $encryptedRole = EncryptionHelper::encrypt($role);
            // Trial end date is already set at user creation, but we can confirm/update if needed.
            // For now, let's assume it's correctly set.
            // $trialEndsAt = date('Y-m-d H:i:s', strtotime('+' . FREE_TRIAL_DAYS . ' days'));
            // $this->userModel->updateUserRoleAndTrial($hashedTelegramId, $encryptedRole, $trialEndsAt);
             $this->userModel->updateUser($hashedTelegramId, ['encrypted_role' => $encryptedRole]);


            $roleMessage = "";
            switch ($role) {
                case 'menstruating':
                    $roleMessage = "نقش شما به عنوان «فردی که پریود می‌شود» ثبت شد. 🩸";
                    break;
                case 'partner':
                    $roleMessage = "نقش شما به عنوان «همراه» ثبت شد. 🤝";
                    break;
                case 'prefer_not_to_say':
                    $roleMessage = "انتخاب شما ثبت شد. 🚫";
                    break;
                default:
                    $roleMessage = "نقش شما ثبت شد.";
                    break;
            }

            $confirmationMessage = $roleMessage . "\n\nاز الان به مدت " . FREE_TRIAL_DAYS . " روز می‌توانید رایگان از امکانات ربات استفاده کنید. امیدواریم این تجربه برایتان مفید باشد!";

            $this->telegramAPI->editMessageText($chatId, $messageId, $confirmationMessage);
            $this->showMainMenu($chatId, "برای ادامه، یکی از گزینه‌های زیر را انتخاب کنید:");

        } else {
            $this->telegramAPI->editMessageText($chatId, $messageId, "شما قبلا نقش خود را انتخاب کرده‌اید.");
            $this->showMainMenu($chatId, "به منوی اصلی بازگشتید:");
        }
    }

    public function showMainMenu($chatId, $text = "منوی اصلی ⚙️") {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$chatId); // Assuming chatId is telegramId for direct user interaction
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "ابتدا باید ثبت نام کنید. لطفا /start را ارسال کنید.");
            return;
        }

        // Decrypt essential user info
        $decryptedRole = null;
        if (!empty($user['encrypted_role'])) {
            try {
                $decryptedRole = EncryptionHelper::decrypt($user['encrypted_role']);
            } catch (\Exception $e) {
                error_log("Failed to decrypt role for main menu user {$hashedTelegramId}: " . $e->getMessage());
            }
        }
        $cycleInfo = null;
        if (!empty($user['encrypted_cycle_info'])) {
            try {
                $cycleInfo = json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true);
            } catch (\Exception $e) {
                error_log("Failed to decrypt cycle_info for main menu user {$hashedTelegramId}: " . $e->getMessage());
            }
        }

        $menuText = $text; // Start with the passed-in text or default "منوی اصلی ⚙️"
        $buttons = [];

        // Cycle Info display for menstruating user
        if ($decryptedRole === 'menstruating' && $cycleInfo) {
            $cycleService = new \Services\CycleService($cycleInfo);
            $currentDay = $cycleService->getCurrentCycleDay();
            $currentPhaseKey = $cycleService->getCurrentCyclePhase();
            $phaseTranslations = [
                'menstruation' => 'پریود (قاعدگی) 🩸',
                'follicular' => 'فولیکولار (پیش از تخمک‌گذاری) 🌱',
                'ovulation' => 'تخمک‌گذاری (احتمالی) 🥚',
                'luteal' => 'لوتئال (پیش از پریود) 🍂',
                'unknown' => 'نامشخص',
            ];
            $currentPhase = $phaseTranslations[$currentPhaseKey] ?? 'نامشخص';

            if ($currentDay) {
                $menuText .= "\n\n🗓️ **وضعیت دوره شما:**";
                $menuText .= "\n- روز جاری دوره: " . $currentDay;
                $menuText .= "\n- فاز تخمینی: " . $currentPhase;
                $daysUntilNext = $cycleService->getDaysUntilNextPeriod();
                if ($daysUntilNext !== null) {
                    if ($daysUntilNext > 0) {
                        $menuText .= "\n- حدود " . $daysUntilNext . " روز تا پریود بعدی";
                    } elseif ($daysUntilNext == 0) {
                        $menuText .= "\n- پریود بعدی احتمالا امروز شروع می‌شود.";
                    } else {
                        $menuText .= "\n- پریود شما " . abs($daysUntilNext) . " روز به تاخیر افتاده است.";
                    }
                }
            } else {
                $menuText .= "\n\nبرای مشاهده اطلاعات دوره، ابتدا تاریخ آخرین پریود خود را ثبت کنید.";
            }
            $buttons[] = [['text' => "🩸 ثبت/ویرایش اطلاعات دوره", 'callback_data' => 'cycle_log_period_start_prompt']];
            $buttons[] = [['text' => "📝 ثبت علائم روزانه", 'callback_data' => 'symptom_log_start:today']]; // Default to today
        }

        // Partner related information and buttons
        if (empty($user['partner_telegram_id_hash'])) {
            if (empty($user['invitation_token'])) {
                $buttons[] = [['text' => "💌 دعوت از همراه", 'callback_data' => 'partner_invite']];
            } else {
                $buttons[] = [['text' => "🔗 لغو دعوتنامه ارسال شده", 'callback_data' => 'partner_cancel_invite']];
                 $botUsername = $this->getBotUsername();
                 $invitationLink = "https://t.me/{$botUsername}?start=invite_{$user['invitation_token']}";
                 // Avoid sending this message every time main menu is shown, maybe only after generating link.
                 // For now, let's keep it simple and show it if token exists.
                 $this->telegramAPI->sendMessage($chatId, "شما یک دعوتنامه فعال دارید. این لینک را برای همراه خود ارسال کنید:\n{$invitationLink}\n\nیا می‌توانید دعوتنامه را لغو کنید.");
            }
            $buttons[] = [['text' => "🤝 پذیرش دعوتنامه (با کد)", 'callback_data' => 'partner_accept_prompt']];
        } else { // User is partnered
            $partnerHashedId = $user['partner_telegram_id_hash'];
            $partnerUser = $this->userModel->findUserByTelegramId($partnerHashedId);
            $partnerFirstName = "همراه شما";
            if ($partnerUser && !empty($partnerUser['encrypted_first_name'])) {
                try {
                    $partnerFirstName = EncryptionHelper::decrypt($partnerUser['encrypted_first_name']);
                } catch (\Exception $e) { error_log("Failed to decrypt partner name for main menu: " . $e->getMessage()); }
            }
            $menuText .= "\n\n💞 شما به {$partnerFirstName} متصل هستید.";
            $buttons[] = [['text' => "💔 قطع اتصال از {$partnerFirstName}", 'callback_data' => 'partner_disconnect']];

            // If current user is 'partner', show their partner's (menstruating user's) cycle info
            if ($decryptedRole === 'partner' && $partnerUser) {
                $partnerCycleInfo = null;
                if (!empty($partnerUser['encrypted_cycle_info'])) {
                    try {
                        $partnerCycleInfo = json_decode(EncryptionHelper::decrypt($partnerUser['encrypted_cycle_info']), true);
                    } catch (\Exception $e) { error_log("Failed to decrypt partner's cycle_info: " . $e->getMessage()); }
                }

                if ($partnerCycleInfo) {
                    $partnerCycleService = new \Services\CycleService($partnerCycleInfo);
                    $partnerCurrentDay = $partnerCycleService->getCurrentCycleDay();
                    $partnerCurrentPhaseKey = $partnerCycleService->getCurrentCyclePhase();
                    $phaseTranslations = [
                        'menstruation' => 'پریود (قاعدگی) 🩸',
                        'follicular' => 'فولیکولار (پیش از تخمک‌گذاری) 🌱',
                        'ovulation' => 'تخمک‌گذاری (احتمالی) 🥚',
                        'luteal' => 'لوتئال (پیش از پریود) 🍂',
                        'unknown' => 'نامشخص',
                    ];
                    $partnerCurrentPhase = $phaseTranslations[$partnerCurrentPhaseKey] ?? 'نامشخص';

                    if ($partnerCurrentDay) {
                        $menuText .= "\n\n🗓️ **وضعیت دوره {$partnerFirstName}:**";
                        $menuText .= "\n- روز جاری دوره: " . $partnerCurrentDay;
                        $menuText .= "\n- فاز تخمینی: " . $partnerCurrentPhase;
                        $partnerDaysUntilNext = $partnerCycleService->getDaysUntilNextPeriod();
                        if ($partnerDaysUntilNext !== null) {
                             if ($partnerDaysUntilNext > 0) {
                                $menuText .= "\n- حدود " . $partnerDaysUntilNext . " روز تا پریود بعدی";
                            } elseif ($partnerDaysUntilNext == 0) {
                                $menuText .= "\n- پریود بعدی احتمالا امروز شروع می‌شود.";
                            } else {
                                $menuText .= "\n- پریود " . abs($partnerDaysUntilNext) . " روز به تاخیر افتاده است.";
                            }
                        }
                    } else {
                         $menuText .= "\n\n{$partnerFirstName} هنوز اطلاعات دوره‌ای ثبت نکرده است.";
                    }
                } else {
                    $menuText .= "\n\n{$partnerFirstName} هنوز اطلاعات دوره‌ای ثبت نکرده است.";
                }
            }
        }

        // Add settings button later
        $buttons[] = [['text' => "⚙️ تنظیمات", 'callback_data' => 'settings_show']];

        $keyboard = ['inline_keyboard' => $buttons];
        $this->telegramAPI->sendMessage($chatId, $menuText, $keyboard, 'MarkdownV2'); // Using Markdown for bold
    }

    public function handleSettings($telegramId, $chatId, $messageId = null) {
        $text = "⚙️ تنظیمات\n\nچه کاری می‌خواهید انجام دهید؟";
        $buttons = [
            [['text' => "⏰ تنظیم زمان اعلان‌ها", 'callback_data' => 'settings_set_notify_time_prompt']],
            // [['text' => "👤 مدیریت پروفایل (به زودی)", 'callback_data' => 'settings_profile_manage']],
            [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']],
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function handleSetNotificationTimePrompt($telegramId, $chatId, $messageId) {
        $text = "⏰ در چه ساعتی از روز مایل به دریافت اعلان‌های روزانه هستید؟\n(زمان‌ها بر اساس وقت تهران هستند)";

        $timeOptions = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '17:00', '18:00', '19:00', '20:00', '21:00'];
        $timeButtons = [];
        foreach ($timeOptions as $time) {
            $timeButtons[] = ['text' => $time, 'callback_data' => 'settings_set_notify_time:' . $time];
        }

        $keyboard = ['inline_keyboard' => array_chunk($timeButtons, 3)]; // 3 time options per row
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به تنظیمات", 'callback_data' => 'settings_show']];

        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleSetNotificationTime($telegramId, $chatId, $messageId, $time) {
        // Validate time format briefly (HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            $this->telegramAPI->answerCallbackQuery($this->telegramAPI->getLastCallbackQueryId(), "فرمت زمان نامعتبر است.", true);
            // $this->telegramAPI->editMessageText($chatId, $messageId, "فرمت زمان ارسالی نامعتبر است. لطفا مجددا تلاش کنید.");
            // $this->handleSetNotificationTimePrompt($telegramId, $chatId, $messageId); // Show prompt again
            return;
        }

        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $updated = $this->userModel->updateUser($hashedTelegramId, ['preferred_notification_time' => $time . ':00']); // Add seconds for TIME type

        if ($updated) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "⏰ زمان دریافت اعلان‌های شما به {$time} تغییر یافت.");
        } else {
            $this->telegramAPI->editMessageText($chatId, $messageId, "مشکلی در ذخیره زمان اعلان پیش آمد. لطفا دوباره تلاش کنید.");
        }
        $this->showMainMenu($chatId, "به منوی اصلی بازگشتید."); // Or back to settings: $this->handleSettings($telegramId, $chatId);
    }


    private function getBotUsername() {
        // This should ideally be fetched once and cached, or from config
        // For now, a placeholder. You might need to call getMe method of TelegramAPI
        $botInfo = $this->telegramAPI->getMe();
        if ($botInfo && $botInfo['ok']) {
            return $botInfo['result']['username'];
        }
        return 'YOUR_BOT_USERNAME'; // Fallback or get from config
    }

    public function handleGenerateInvitation($telegramId, $chatId) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }
        if (!empty($user['partner_telegram_id_hash'])) {
            $this->telegramAPI->sendMessage($chatId, "شما در حال حاضر یک همراه متصل دارید. برای دعوت از فرد جدید، ابتدا باید اتصال فعلی را قطع کنید.");
            $this->showMainMenu($chatId);
            return;
        }

        $token = $this->userModel->generateInvitationToken($hashedTelegramId);

        if ($token) {
            $botUsername = $this->getBotUsername();
            $invitationLink = "https://t.me/{$botUsername}?start=invite_{$token}";
            $message = "لینک دعوت برای همراه شما ایجاد شد:\n\n`{$invitationLink}`\n\nاین لینک را کپی کرده و برای فرد مورد نظر ارسال کنید. این لینک یکبار مصرف است و پس از استفاده یا ساخت لینک جدید، باطل می‌شود.\n\nهمچنین همراه شما می‌تواند کد زیر را مستقیما در ربات وارد کند (از طریق دکمه پذیرش دعوتنامه):\n`{$token}`";
            $this->telegramAPI->sendMessage($chatId, $message, null, 'MarkdownV2');
             // Update the main menu to show the cancel option
            $this->showMainMenu($chatId, "لینک دعوت ارسال شد. منوی اصلی:");
            return; // Return to avoid showing menu twice if called from another function that then calls showMainMenu
        } else {
            $this->telegramAPI->sendMessage($chatId, "متاسفانه مشکلی در ایجاد لینک دعوت پیش آمد. لطفا دوباره تلاش کنید.");
        }
        $this->showMainMenu($chatId);
    }

    public function handleCancelInvitation($telegramId, $chatId, $messageId = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId); // Get user to check current token

        if ($user && !empty($user['invitation_token'])) {
            $updated = $this->userModel->updateUser($hashedTelegramId, ['invitation_token' => null]);
            if ($updated) {
                $text = "دعوتنامه شما با موفقیت لغو شد.";
                if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text);
                else $this->telegramAPI->sendMessage($chatId, $text);
            } else {
                $text = "مشکلی در لغو دعوتنامه رخ داد.";
                 if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text);
                else $this->telegramAPI->sendMessage($chatId, $text);
            }
        } else {
            $text = "دعوتنامه‌ای برای لغو وجود نداشت.";
            if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text);
            else $this->telegramAPI->sendMessage($chatId, $text);
        }
        $this->showMainMenu($chatId);
    }

    public function handleAcceptInvitationPrompt($telegramId, $chatId) {
        // This function just prompts the user to send the token
        // In a real scenario, you'd use force_reply or wait for the next message
        $this->telegramAPI->sendMessage($chatId, "لطفا کد دعوتی که از همراه خود دریافت کرده‌اید را ارسال کنید, یا از طریق لینکی که همراهتان فرستاده اقدام کنید.");
        // We'll need to store a state for this user, e.g., 'awaiting_invitation_token'
        // For now, we assume the next message from this user will be the token. This is simplistic.
        // A better way is to use callback buttons that include the token or use deep linking with /start command.
        // The current setup uses deep linking: /start invite_TOKEN
    }

    public function handleAcceptInvitationCommand(string $telegramId, int $chatId, string $firstName, ?string $username, string $token) {
        $accepterHashedId = EncryptionHelper::hashIdentifier($telegramId);
        $accepterUser = $this->userModel->findUserByTelegramId($accepterHashedId);

        // Ensure accepter is registered
        if (!$accepterUser) {
            // Register the user first (simplified: assuming they just started via link)
            $this->userModel->createUser($accepterHashedId, $firstName, $username);
            $accepterUser = $this->userModel->findUserByTelegramId($accepterHashedId); // Re-fetch
            if (!$accepterUser) {
                 $this->telegramAPI->sendMessage($chatId, "خطا در پردازش اطلاعات شما. لطفا ربات را مجددا با /start آغاز کنید و سپس تلاش کنید.");
                 return;
            }
            // New user accepting invite might not have a role yet.
            // Prompt them to select a role AFTER successful linking or as part of it.
        }


        if (!empty($accepterUser['partner_telegram_id_hash'])) {
            $this->telegramAPI->sendMessage($chatId, "شما در حال حاضر یک همراه متصل دارید. برای پذیرش این دعوت، ابتدا باید اتصال فعلی خود را قطع کنید.");
            $this->showMainMenu($chatId);
            return;
        }

        $inviterUser = $this->userModel->findUserByInvitationToken($token);

        if (!$inviterUser) {
            $this->telegramAPI->sendMessage($chatId, "کد دعوت نامعتبر است یا منقضی شده.");
            $this->showMainMenu($chatId); // Show menu even on failure
            return;
        }

        $inviterHashedId = $inviterUser['telegram_id_hash'];
        // It's good to get inviter's first name here to mention in messages
        $inviterFirstName = "همراه شما";
         if (!empty($inviterUser['encrypted_first_name'])) {
            try {
                $inviterFirstName = EncryptionHelper::decrypt($inviterUser['encrypted_first_name']);
            } catch (\Exception $e) { /* log error */ }
        }


        if ($inviterHashedId === $accepterHashedId) {
            $this->telegramAPI->sendMessage($chatId, "شما نمی‌توانید خودتان را به عنوان همراه دعوت کنید.");
            $this->showMainMenu($chatId);
            return;
        }

        if ($this->userModel->linkPartners($inviterHashedId, $accepterHashedId)) {
            $this->telegramAPI->sendMessage($chatId, "شما با موفقیت به {$inviterFirstName} متصل شدید! 🎉");

            // Notify the inviter. This requires getting the inviter's actual chat_id.
            // This is a known challenge if we only store hashed IDs and don't have a direct chat_id mapping.
            // For now, we will attempt to get the inviter's chat_id if their username is part of the user record
            // or if their telegram_id (which is the user_id for the bot) can be used as chat_id.
            // Let's assume inviterUser['telegram_id_hash'] was derived from an ID that can receive messages.
            // This is a simplification. A robust system might need to store `chat_id` explicitly upon first interaction.
            $inviterOriginalTelegramId = null; // This needs to be retrieved.
            // If we had stored an encrypted version of the inviter's Telegram ID (the one used for chat), we could use it.
            // For now, let's assume we cannot directly message the inviter without their explicit chat_id which we don't have readily.
            // A possible solution: When inviter generates token, store their chat_id with the token.
            // Or, when user record is created, store chat_id (encrypted).
            // For this iteration, we'll skip direct inviter notification.
            // $this->telegramAPI->sendMessage($inviterChatId, "{$firstName} دعوت شما را پذیرفت!");


            // If the accepter is a new user (no role), prompt for role.
            if (empty($accepterUser['encrypted_role'])) {
                 $welcomeMessage = "عالی! اتصال شما برقرار شد. حالا لطفا نقش خود را در این همراهی مشخص کنید:";
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => "🩸 من پریود می‌شوم", 'callback_data' => 'select_role:menstruating']],
                        [['text' => "🤝 همراه هستم", 'callback_data' => 'select_role:partner']],
                        [['text' => "🚫 ترجیح می‌دهم نگویم", 'callback_data' => 'select_role:prefer_not_to_say']]
                    ]
                ];
                $this->telegramAPI->sendMessage($chatId, $welcomeMessage, $keyboard);
            } else {
                $this->showMainMenu($chatId); // Show main menu to accepter
            }


        } else {
            $this->telegramAPI->sendMessage($chatId, "متاسفانه مشکلی در اتصال به همراه پیش آمد. ممکن است {$inviterFirstName} دیگر این دعوت را لغو کرده باشد یا همزمان با فرد دیگری متصل شده باشد. لطفا دوباره تلاش کنید یا از همراهتان بخواهید لینک جدیدی ارسال کند.");
            $this->showMainMenu($chatId);
        }
    }


    public function handleDisconnectPartner($telegramId, $chatId) {
        $userHashedId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($userHashedId);

        if (!$user || empty($user['partner_telegram_id_hash'])) {
            $this->telegramAPI->sendMessage($chatId, "شما در حال حاضر به هیچ همراهی متصل نیستید.");
            $this->showMainMenu($chatId);
            return;
        }

        $partnerFirstName = "همراهتان";
        $partnerUser = $this->userModel->findUserByTelegramId($user['partner_telegram_id_hash']);
        if ($partnerUser && !empty($partnerUser['encrypted_first_name'])) {
            try {
                $partnerFirstName = EncryptionHelper::decrypt($partnerUser['encrypted_first_name']);
            } catch (\Exception $e) { /* log */ }
        }


        // Confirmation step
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "✅ بله، مطمئنم", 'callback_data' => 'partner_disconnect_confirm'],
                    ['text' => "❌ خیر، منصرف شدم", 'callback_data' => 'main_menu_show'],
                ]
            ]
        ];
        $this->telegramAPI->sendMessage($chatId, "آیا مطمئن هستید که می‌خواهید از {$partnerFirstName} جدا شوید؟", $keyboard);
    }

    public function handleDisconnectPartnerConfirm($telegramId, $chatId, $messageId) {
        $userHashedId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($userHashedId);

        if (!$user || empty($user['partner_telegram_id_hash'])) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "شما به همراهی متصل نبودید.");
            $this->showMainMenu($chatId);
            return;
        }

        $partnerHashedId = $user['partner_telegram_id_hash'];
        // Get partner's first name for the message before unlinking
        $partnerFirstName = "همراهتان";
        $partnerUser = $this->userModel->findUserByTelegramId($partnerHashedId);
         if ($partnerUser && !empty($partnerUser['encrypted_first_name'])) {
            try {
                $partnerFirstName = EncryptionHelper::decrypt($partnerUser['encrypted_first_name']);
            } catch (\Exception $e) { /* log */ }
        }


        if ($this->userModel->unlinkPartners($userHashedId, $partnerHashedId)) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "اتصال شما از {$partnerFirstName} با موفقیت قطع شد.");

            // Notify the (now ex-)partner - Same chat_id challenge as above
            // $partnerChatId = ... // retrieve partner's actual chat_id
            // if ($partnerChatId) {
            //    $currentUserFirstName = EncryptionHelper::decrypt($user['encrypted_first_name']);
            //    $this->telegramAPI->sendMessage($partnerChatId, "{$currentUserFirstName} از شما جدا شد.");
            // }
            $this->showMainMenu($chatId);

        } else {
            $this->telegramAPI->editMessageText($chatId, $messageId, "مشکلی در قطع اتصال پیش آمد. لطفا دوباره تلاش کنید.");
            $this->showMainMenu($chatId);
        }
    }

    // --------- CYCLE LOGGING METHODS START ---------

    public function handleLogPeriodStartPrompt($telegramId, $chatId, $messageId = null) {
        $text = "لطفا تاریخ شروع آخرین پریود خود را مشخص کنید:";
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $buttons = [
            [['text' => "☀️ امروز (" . $today . ")", 'callback_data' => 'cycle_log_date:' . $today]],
            [['text' => "🌙 دیروز (" . $yesterday . ")", 'callback_data' => 'cycle_log_date:' . $yesterday]],
            [['text' => "📅 انتخاب تاریخ دیگر", 'callback_data' => 'cycle_pick_year']],
            [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']],
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) {
            // Try to edit the message, if it fails (e.g. too old, or text not different), send a new one.
            $response = $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
            if (!$response || !$response['ok']) {
                 // If edit failed (e.g. message not modified error, or other issues)
                error_log("Failed to edit message for cycle prompt, sending new. ChatID: {$chatId}, MsgID: {$messageId}. Error: ".($response['description'] ?? 'Unknown'));
                $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
            }
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function handleCyclePickYear($telegramId, $chatId, $messageId) {
        $text = "📅 انتخاب سال:";
        $keyboard = ['inline_keyboard' => \Helpers\DateHelper::getYearSelector()];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت", 'callback_data' => 'cycle_log_period_start_prompt']];
        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleCycleSelectYear($telegramId, $chatId, $messageId, $year) {
        $text = "📅 انتخاب ماه برای سال " . $year . ":";
        $keyboard = ['inline_keyboard' => \Helpers\DateHelper::getMonthSelector((int)$year)];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت (انتخاب سال)", 'callback_data' => 'cycle_pick_year']];
        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleCycleSelectMonth($telegramId, $chatId, $messageId, $year, $month) {
        $text = "📅 انتخاب روز برای ماه {$month} سال {$year}:";
        $keyboard = ['inline_keyboard' => \Helpers\DateHelper::getDaySelector((int)$year, (int)$month)];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت (انتخاب ماه)", 'callback_data' => 'cycle_select_year:' . $year]];
        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleCycleLogDate($telegramId, $chatId, $messageId, $year, $month = null, $day = null) {
        // If month and day are null, $year is actually the full date string 'YYYY-MM-DD'
        if ($month === null && $day === null) {
            $dateString = $year; // $year here is the full date string
        } else {
            if (!\Helpers\DateHelper::isValidDate((int)$year, (int)$month, (int)$day)) {
                $this->telegramAPI->sendMessage($chatId, "تاریخ انتخاب شده نامعتبر است. لطفا دوباره تلاش کنید.");
                $this->handleLogPeriodStartPrompt($telegramId, $chatId, $messageId); // Or take them to year selection
                return;
            }
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // Future date check
        if (strtotime($dateString) > time()) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "تاریخ شروع پریود نمی‌تواند در آینده باشد. لطفا تاریخ معتبری انتخاب کنید.",
                ['inline_keyboard' => [[['text' => " تلاش مجدد", 'callback_data' => 'cycle_log_period_start_prompt']]]]);
            return;
        }

        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) { /* Error handling */
             $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد. لطفا /start را مجددا اجرا کنید.");
            return;
        }

        $cycleInfo = !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];

        if (!isset($cycleInfo['period_start_dates'])) {
            $cycleInfo['period_start_dates'] = [];
        }

        // Add date if not already present, maintain sorted order (most recent first)
        if (!in_array($dateString, $cycleInfo['period_start_dates'])) {
            $cycleInfo['period_start_dates'][] = $dateString;
            usort($cycleInfo['period_start_dates'], function($a, $b) {
                return strtotime($b) - strtotime($a); // Sort descending
            });
            // Optional: Limit the number of stored start dates, e.g., last 12
            $cycleInfo['period_start_dates'] = array_slice($cycleInfo['period_start_dates'], 0, 12);
        }

        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $editedMessageText = "تاریخ شروع آخرین پریود شما (" . $dateString . ") ثبت شد. ✅";
        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $editedMessageText, [], ''); // No keyboard, clear previous
        } else {
             $this->telegramAPI->sendMessage($chatId, $editedMessageText);
        }


        // Check if average lengths are set, if not, ask for them
        if (!isset($cycleInfo['average_period_length']) || !isset($cycleInfo['average_cycle_length'])) {
            $this->handleAskAverageLengths($telegramId, $chatId); // Send as new message
        } else {
            $this->showMainMenu($chatId, "اطلاعات شما به‌روز شد.");
        }
    }

    public function handleAskAverageLengths($telegramId, $chatId, $messageId = null) { // messageId might be null if called after new message
        $text = "برای پیش‌بینی دقیق‌تر، لطفا اطلاعات زیر را وارد کنید:\n\nمیانگین طول دوره پریود شما چند روز است؟ (معمولا بین ۳ تا ۷ روز)";
        $buttons = [];
        for ($i = 2; $i <= 10; $i++) { $buttons[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_period:$i"]; }

        $keyboard = ['inline_keyboard' => array_chunk($buttons, 3)];
        $keyboard['inline_keyboard'][] = [['text' => " نمی‌دانم/رد کردن", 'callback_data' => 'cycle_skip_avg_period']];

        // This should be a new message, not an edit of the date confirmation.
        $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
    }

    public function handleSetAveragePeriodLength($telegramId, $chatId, $messageId, $length) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) { /* Error handling */
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }

        $cycleInfo = !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];
        $cycleInfo['average_period_length'] = (int)$length;
        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $text = "میانگین طول پریود شما {$length} روز ثبت شد.\n\nمیانگین طول کل چرخه قاعدگی شما چند روز است؟ (از اولین روز یک پریود تا اولین روز پریود بعدی، معمولا بین ۲۱ تا ۳۵ روز)";
        $buttons = [];
        for ($i = 20; $i <= 45; $i++) { $buttons[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_cycle:$i"]; }

        $keyboard = ['inline_keyboard' => array_chunk($buttons, 5)]; // 5 buttons per row
        $keyboard['inline_keyboard'][] = [['text' => " نمی‌دانم/رد کردن", 'callback_data' => 'cycle_skip_avg_cycle']];

        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleSetAverageCycleLength($telegramId, $chatId, $messageId, $length) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
         if (!$user) { /* Error handling */
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }

        $cycleInfo = !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];
        $cycleInfo['average_cycle_length'] = (int)$length;
        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $this->telegramAPI->editMessageText($chatId, $messageId, "میانگین طول چرخه شما {$length} روز ثبت شد. ممنون از اطلاعات شما! 👍");
        $this->showMainMenu($chatId, "اطلاعات دوره شما با موفقیت به‌روز شد.");
    }

    public function handleSkipAverageInfo($telegramId, $chatId, $messageId, $type) {
        // Type can be 'period' or 'cycle'
        // We just acknowledge and move to main menu or next step
        $text = "";
        if ($type === 'period') {
            $text = "بسیار خب. می‌توانید این اطلاعات را بعدا از بخش تنظیمات تکمیل کنید.\n\nمیانگین طول کل چرخه قاعدگی شما چند روز است؟ (از اولین روز یک پریود تا اولین روز پریود بعدی، معمولا بین ۲۱ تا ۳۵ روز)";
            $buttons = [];
            for ($i = 20; $i <= 45; $i++) { $buttons[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_cycle:$i"]; }
            $keyboard = ['inline_keyboard' => array_chunk($buttons, 5)];
            $keyboard['inline_keyboard'][] = [['text' => " نمی‌دانم/رد کردن", 'callback_data' => 'cycle_skip_avg_cycle']];
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
            return;
        } elseif ($type === 'cycle') {
             $text = "بسیار خب. می‌توانید این اطلاعات را بعدا از بخش تنظیمات تکمیل کنید. اطلاعات شما ثبت شد.";
             $this->telegramAPI->editMessageText($chatId, $messageId, $text);
        }

        $this->showMainMenu($chatId, "اطلاعات اولیه دوره شما ثبت شد.");
    }


    // --------- CYCLE LOGGING METHODS END -----------

    // --------- SYMPTOM LOGGING METHODS START -----------
    private $symptomsConfig;
    private $symptomModel;

    private function loadSymptomsConfig() {
        if ($this->symptomsConfig === null) {
            $this->symptomsConfig = require BASE_PATH . '/config/symptoms_config.php';
        }
    }
    private function getSymptomModel(): \Models\SymptomModel {
        if ($this->symptomModel === null) {
            $this->symptomModel = new \Models\SymptomModel();
        }
        return $this->symptomModel;
    }

    public function handleLogSymptomStart($telegramId, $chatId, $messageId = null, $dateOption = 'today') {
        $this->loadSymptomsConfig();
        $symptomDate = ($dateOption === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

        $userHashedId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $userIdRecord = $this->userModel->findUserByTelegramId($userHashedId);
        if (!$userIdRecord) { /* handle error */ return; }
        $dbUserId = $userIdRecord['id'];

        $loggedSymptomsRaw = $this->getSymptomModel()->getLoggedSymptomsForDate($dbUserId, $symptomDate);
        // Convert loggedSymptomsRaw to a simple list of "catKey_symKey" for quick checks
        $currentlyLoggedSet = [];
        foreach ($loggedSymptomsRaw as $s) {
            $currentlyLoggedSet[$s['category_key'] . '_' . $s['symptom_key']] = true;
        }

        $text = "📝 علائم روز: " . $symptomDate . "\n\n";
        $text .= "لطفا یک دسته بندی انتخاب کنید یا علائم ثبت شده را نهایی کنید.\n";
        // Could list currently selected symptoms here if space and UX allows.

        $categoryButtons = [];
        foreach ($this->symptomsConfig['categories'] as $key => $label) {
            $categoryButtons[] = ['text' => $label, 'callback_data' => "symptom_show_cat:{$dateOption}:{$key}"];
        }

        $keyboard = [
            'inline_keyboard' => array_chunk($categoryButtons, 2) // 2 categories per row
        ];
        $keyboard['inline_keyboard'][] = [
            // Potentially add "Log for Yesterday" button here too if starting with 'today'
            // ['text' => ($dateOption === 'today' ? "ثبت برای دیروز" : "ثبت برای امروز"), 'callback_data' => 'symptom_log_start:' . ($dateOption === 'today' ? 'yesterday' : 'today')],
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => "✅ ثبت نهایی علائم", 'callback_data' => "symptom_save_final:{$dateOption}"],
            ['text' => "🔙 منوی اصلی", 'callback_data' => 'main_menu_show'],
        ];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
        // Temporary storage of selected symptoms for this session (until "Finalize")
        // This is tricky with stateless Telegram. For a robust solution, each toggle would be an AJAX-like DB update,
        // or we pass the whole state in callback_data (gets very long).
        // Simpler for now: rely on re-fetching from DB for the "currently logged" state.
    }

    public function handleSymptomShowCategory($telegramId, $chatId, $messageId, $dateOption, $categoryKey) {
        $this->loadSymptomsConfig();
        $symptomDate = ($dateOption === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
        $categoryName = $this->symptomsConfig['categories'][$categoryKey] ?? 'ناشناخته';

        $userHashedId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $userIdRecord = $this->userModel->findUserByTelegramId($userHashedId);
        if (!$userIdRecord) { /* handle error */ return; }
        $dbUserId = $userIdRecord['id'];

        $loggedSymptomsRaw = $this->getSymptomModel()->getLoggedSymptomsForDate($dbUserId, $symptomDate);
        $currentlyLoggedSet = [];
        foreach ($loggedSymptomsRaw as $s) {
            $currentlyLoggedSet[$s['category_key'] . '_' . $s['symptom_key']] = true;
        }

        $text = "📝 علائم روز: " . $symptomDate . "\n";
        $text .= "دسته: **" . $categoryName . "**\n";
        $text .= "روی علامت مورد نظر کلیک کنید تا انتخاب/لغو انتخاب شود:";

        $symptomButtons = [];
        if (isset($this->symptomsConfig['symptoms'][$categoryKey])) {
            foreach ($this->symptomsConfig['symptoms'][$categoryKey] as $symKey => $symLabel) {
                $isLogged = isset($currentlyLoggedSet[$categoryKey . '_' . $symKey]);
                $buttonText = ($isLogged ? "✅ " : "") . $symLabel;
                $symptomButtons[] = ['text' => $buttonText, 'callback_data' => "symptom_toggle:{$dateOption}:{$categoryKey}:{$symKey}"];
            }
        }

        $keyboard = [
            'inline_keyboard' => array_chunk($symptomButtons, 2) // 2 symptoms per row
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => "다른 دسته‌بندی‌ها" , 'callback_data' => "symptom_log_start:{$dateOption}"], // Back to categories
        ];
         $keyboard['inline_keyboard'][] = [
            ['text' => "✅ ثبت نهایی علائم", 'callback_data' => "symptom_save_final:{$dateOption}"],
            ['text' => "🔙 منوی اصلی", 'callback_data' => 'main_menu_show'],
        ];

        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'MarkdownV2');
    }

    public function handleSymptomToggle($telegramId, $chatId, $messageId, $dateOption, $categoryKey, $symptomKey) {
        $this->loadSymptomsConfig();
        $symptomDate = ($dateOption === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

        $userHashedId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $userIdRecord = $this->userModel->findUserByTelegramId($userHashedId);
        if (!$userIdRecord) { /* handle error */ return; }
        $dbUserId = $userIdRecord['id'];

        $loggedSymptomsRaw = $this->getSymptomModel()->getLoggedSymptomsForDate($dbUserId, $symptomDate);
        $isCurrentlyLogged = false;
        foreach ($loggedSymptomsRaw as $s) {
            if ($s['category_key'] === $categoryKey && $s['symptom_key'] === $symptomKey) {
                $isCurrentlyLogged = true;
                break;
            }
        }

        $actionSuccess = false;
        if ($isCurrentlyLogged) {
            $actionSuccess = $this->getSymptomModel()->removeSymptom($dbUserId, $symptomDate, $categoryKey, $symptomKey);
        } else {
            $actionSuccess = $this->getSymptomModel()->addSymptom($dbUserId, $symptomDate, $categoryKey, $symptomKey);
        }

        if ($actionSuccess) {
            // Update last_symptom_log_date if any symptom was added or removed for the specific symptomDate
            // We check if any symptoms remain for the day; if so, update last_symptom_log_date to symptomDate.
            // If all symptoms for symptomDate were removed, this logic might need to be smarter,
            // perhaps by finding the latest date with any symptoms.
            // For simplicity now: if an add/remove happened, update last_symptom_log_date to the date of this action.
            // A more accurate way: if addSymptom, update. If removeSymptom, only update if other symptoms still exist for this date,
            // OR if no symptoms exist for this date and this was the last date with symptoms, then find previous.
            // Let's simplify: any successful toggle updates it to symptomDate if symptoms were added or if some remain.

            $remainingSymptoms = $this->getSymptomModel()->getLoggedSymptomsForDate($dbUserId, $symptomDate);
            if (!empty($remainingSymptoms)) {
                 $this->userModel->updateUser($userHashedId, ['last_symptom_log_date' => $symptomDate]);
            } else {
                // If all symptoms for this specific 'symptomDate' were removed,
                // we might want to set last_symptom_log_date to the latest date that still has symptoms.
                // This is more complex. For now, if removing the last symptom for 'symptomDate',
                // last_symptom_log_date won't be updated by this specific action to symptomDate.
                // It will retain its previous value. A dedicated "find latest symptom log date" function might be needed if this becomes an issue.
                // OR: Simpler: just update it to $symptomDate if an add occurred.
                if (!$isCurrentlyLogged) { // if symptom was added
                    $this->userModel->updateUser($userHashedId, ['last_symptom_log_date' => $symptomDate]);
                }
            }
        }

        // After toggling, refresh the category view
        $this->handleSymptomShowCategory($telegramId, $chatId, $messageId, $dateOption, $categoryKey);
    }

    public function handleSymptomSaveFinal($telegramId, $chatId, $messageId, $dateOption) {
        $symptomDate = ($dateOption === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
        // In the current model, symptoms are added/removed immediately upon toggle.
        // So, "finalize" here is more of a confirmation and navigation step.
        // If we were batching changes in session/callback_data, this is where we'd save them.

        $userHashedId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $userIdRecord = $this->userModel->findUserByTelegramId($userHashedId);
        if (!$userIdRecord) { /* handle error */ return; }
        $dbUserId = $userIdRecord['id'];

        $loggedSymptoms = $this->getSymptomModel()->getLoggedSymptomsForDate($dbUserId, $symptomDate);

        if (empty($loggedSymptoms)) {
            $text = "هیچ علامتی برای تاریخ {$symptomDate} ثبت نشد.";
        } else {
            $text = "علائم شما برای تاریخ {$symptomDate} با موفقیت ثبت شد! ✅\n";
            // Optionally list the logged symptoms as a summary
            // $symptomNames = [];
            // foreach($loggedSymptoms as $s) {
            //    $symptomNames[] = $this->symptomsConfig['symptoms'][$s['category_key']][$s['symptom_key']];
            // }
            // $text .= "علائم ثبت شده: " . implode(', ', $symptomNames);
        }

        $this->telegramAPI->editMessageText($chatId, $messageId, $text, null); // Remove keyboard
        $this->showMainMenu($chatId, "به منوی اصلی بازگشتید.");
    }

    // --------- SYMPTOM LOGGING METHODS END -----------
}
            }
        }

        if ($decryptedRole === 'menstruating') {
            $buttons[] = [['text' => "🩸 ثبت/ویرایش اطلاعات دوره", 'callback_data' => 'cycle_log_period_start_prompt']];
        }

        // $buttons[] = [['text' => "⚙️ تنظیمات", 'callback_data' => 'settings']]; // For later

        $keyboard = ['inline_keyboard' => $buttons];
        $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
    }

    private function getBotUsername() {
        // This should ideally be fetched once and cached, or from config
        // For now, a placeholder. You might need to call getMe method of TelegramAPI
        $botInfo = $this->telegramAPI->getMe();
        if ($botInfo && $botInfo['ok']) {
            return $botInfo['result']['username'];
        }
        return 'YOUR_BOT_USERNAME'; // Fallback or get from config
    }

    public function handleGenerateInvitation($telegramId, $chatId) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }
        if (!empty($user['partner_telegram_id_hash'])) {
            $this->telegramAPI->sendMessage($chatId, "شما در حال حاضر یک همراه متصل دارید. برای دعوت از فرد جدید، ابتدا باید اتصال فعلی را قطع کنید.");
            $this->showMainMenu($chatId);
            return;
        }

        $token = $this->userModel->generateInvitationToken($hashedTelegramId);

        if ($token) {
            $botUsername = $this->getBotUsername();
            $invitationLink = "https://t.me/{$botUsername}?start=invite_{$token}";
            $message = "لینک دعوت برای همراه شما ایجاد شد:\n\n`{$invitationLink}`\n\nاین لینک را کپی کرده و برای فرد مورد نظر ارسال کنید. این لینک یکبار مصرف است و پس از استفاده یا ساخت لینک جدید، باطل می‌شود.\n\nهمچنین همراه شما می‌تواند کد زیر را مستقیما در ربات وارد کند (از طریق دکمه پذیرش دعوتنامه):\n`{$token}`";
            $this->telegramAPI->sendMessage($chatId, $message, null, 'MarkdownV2');
        } else {
            $this->telegramAPI->sendMessage($chatId, "متاسفانه مشکلی در ایجاد لینک دعوت پیش آمد. لطفا دوباره تلاش کنید.");
        }
        $this->showMainMenu($chatId);
    }

    public function handleCancelInvitation($telegramId, $chatId, $messageId = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $updated = $this->userModel->updateUser($hashedTelegramId, ['invitation_token' => null]);
        if ($updated) {
            $text = "دعوتنامه شما با موفقیت لغو شد.";
            if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text);
            else $this->telegramAPI->sendMessage($chatId, $text);
        } else {
            $text = "مشکلی در لغو دعوتنامه رخ داد یا دعوتنامه‌ای برای لغو وجود نداشت.";
             if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text);
            else $this->telegramAPI->sendMessage($chatId, $text);
        }
        $this->showMainMenu($chatId);
    }

    public function handleAcceptInvitationPrompt($telegramId, $chatId) {
        // This function just prompts the user to send the token
        // In a real scenario, you'd use force_reply or wait for the next message
        $this->telegramAPI->sendMessage($chatId, "لطفا کد دعوتی که از همراه خود دریافت کرده‌اید را ارسال کنید:");
        // We'll need to store a state for this user, e.g., 'awaiting_invitation_token'
        // For now, we assume the next message from this user will be the token. This is simplistic.
        // A better way is to use callback buttons that include the token or use deep linking with /start command.
        // The current setup uses deep linking: /start invite_TOKEN
    }

    public function handleAcceptInvitationCommand(string $telegramId, int $chatId, string $firstName, ?string $username, string $token) {
        $accepterHashedId = EncryptionHelper::hashIdentifier($telegramId);
        $accepterUser = $this->userModel->findUserByTelegramId($accepterHashedId);

        // Ensure accepter is registered
        if (!$accepterUser) {
            // Register the user first (simplified: assuming they just started via link)
            $this->userModel->createUser($accepterHashedId, $firstName, $username);
            $accepterUser = $this->userModel->findUserByTelegramId($accepterHashedId); // Re-fetch
            if (!$accepterUser) {
                 $this->telegramAPI->sendMessage($chatId, "خطا در پردازش اطلاعات شما. لطفا ربات را مجددا با /start آغاز کنید و سپس تلاش کنید.");
                 return;
            }
            // New user accepting invite might not have a role yet.
            // Prompt them to select a role AFTER successful linking or as part of it.
        }


        if (!empty($accepterUser['partner_telegram_id_hash'])) {
            $this->telegramAPI->sendMessage($chatId, "شما در حال حاضر یک همراه متصل دارید. برای پذیرش این دعوت، ابتدا باید اتصال فعلی خود را قطع کنید.");
            $this->showMainMenu($chatId);
            return;
        }

        $inviterUser = $this->userModel->findUserByInvitationToken($token);

        if (!$inviterUser) {
            $this->telegramAPI->sendMessage($chatId, "کد دعوت نامعتبر است یا منقضی شده.");
            return;
        }

        $inviterHashedId = $inviterUser['telegram_id_hash'];

        if ($inviterHashedId === $accepterHashedId) {
            $this->telegramAPI->sendMessage($chatId, "شما نمی‌توانید خودتان را به عنوان همراه دعوت کنید.");
            return;
        }

        if ($this->userModel->linkPartners($inviterHashedId, $accepterHashedId)) {
            $this->telegramAPI->sendMessage($chatId, "شما با موفقیت به همراه خود متصل شدید! 🎉");
            $this->showMainMenu($chatId);


            // Notify the inviter
            $inviterChatId = null;
            // We need a way to get inviter's original telegram_id (chat_id) to notify them.
            // This requires storing raw telegram_id (encrypted) or having a mapping.
            // For now, this is a limitation. Let's assume we can't directly message inviter without their chatId.
            // If inviter's telegram_id was part of inviterUser, we could use it.
            // For now, we'll skip direct notification to inviter in this simplified step.
            // A better approach would be to store inviter's chat_id (or telegram_id if it's the same)
            // in the invitation record or fetch it if we store unhashed telegram_ids securely.

            // If the accepter is a new user (no role), prompt for role.
            if (empty($accepterUser['encrypted_role'])) {
                 $welcomeMessage = "عالی! اتصال شما برقرار شد. حالا لطفا نقش خود را در این همراهی مشخص کنید:";
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => "🩸 من پریود می‌شوم", 'callback_data' => 'select_role:menstruating']],
                        [['text' => "🤝 همراه هستم", 'callback_data' => 'select_role:partner']],
                        [['text' => "🚫 ترجیح می‌دهم نگویم", 'callback_data' => 'select_role:prefer_not_to_say']]
                    ]
                ];
                $this->telegramAPI->sendMessage($chatId, $welcomeMessage, $keyboard);
            }


        } else {
            $this->telegramAPI->sendMessage($chatId, "متاسفانه مشکلی در اتصال به همراه پیش آمد. ممکن است همراه شما دیگر این دعوت را لغو کرده باشد یا همزمان با فرد دیگری متصل شده باشد. لطفا دوباره تلاش کنید یا از همراهتان بخواهید لینک جدیدی ارسال کند.");
        }
    }


    public function handleDisconnectPartner($telegramId, $chatId) {
        $userHashedId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($userHashedId);

        if (!$user || empty($user['partner_telegram_id_hash'])) {
            $this->telegramAPI->sendMessage($chatId, "شما در حال حاضر به هیچ همراهی متصل نیستید.");
            $this->showMainMenu($chatId);
            return;
        }

        $partnerHashedId = $user['partner_telegram_id_hash'];

        // Confirmation step
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "✅ بله، مطمئنم", 'callback_data' => 'partner_disconnect_confirm'],
                    ['text' => "❌ خیر، منصرف شدم", 'callback_data' => 'main_menu_show'],
                ]
            ]
        ];
        $this->telegramAPI->sendMessage($chatId, "آیا مطمئن هستید که می‌خواهید از همراه خود جدا شوید؟", $keyboard);
    }

    public function handleDisconnectPartnerConfirm($telegramId, $chatId, $messageId) {
        $userHashedId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($userHashedId);

        if (!$user || empty($user['partner_telegram_id_hash'])) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "شما به همراهی متصل نبودید.");
            $this->showMainMenu($chatId);
            return;
        }

        $partnerHashedId = $user['partner_telegram_id_hash'];

        if ($this->userModel->unlinkPartners($userHashedId, $partnerHashedId)) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "اتصال شما از همراهتان با موفقیت قطع شد.");
            $this->showMainMenu($chatId);

            // Notify the (now ex-)partner
            // This has the same limitation as notifying inviter: needs partner's original telegram_id (chat_id)
            // We need to retrieve the original partner's Telegram ID to send them a message.
            // This is a complex part if we only store hashed IDs.
            // For now, we'll assume we cannot directly message the ex-partner without their chat_id.
            // A robust solution would involve storing chat_id securely or having a lookup mechanism.
            // One way: if we have a `users.telegram_id_encrypted` field.
        } else {
            $this->telegramAPI->editMessageText($chatId, $messageId, "مشکلی در قطع اتصال پیش آمد. لطفا دوباره تلاش کنید.");
            $this->showMainMenu($chatId);
        }

    // --------- CYCLE LOGGING METHODS START ---------

    public function handleLogPeriodStartPrompt($telegramId, $chatId, $messageId = null) {
        $text = "لطفا تاریخ شروع آخرین پریود خود را مشخص کنید:";
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $buttons = [
            [['text' => "☀️ امروز (" . $today . ")", 'callback_data' => 'cycle_log_date:' . $today]],
            [['text' => "🌙 دیروز (" . $yesterday . ")", 'callback_data' => 'cycle_log_date:' . $yesterday]],
            [['text' => "📅 انتخاب تاریخ دیگر", 'callback_data' => 'cycle_pick_year']],
            [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']],
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function handleCyclePickYear($telegramId, $chatId, $messageId) {
        $text = "📅 انتخاب سال:";
        $keyboard = ['inline_keyboard' => \Helpers\DateHelper::getYearSelector()];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت", 'callback_data' => 'cycle_log_period_start_prompt']];
        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleCycleSelectYear($telegramId, $chatId, $messageId, $year) {
        $text = "📅 انتخاب ماه برای سال " . $year . ":";
        $keyboard = ['inline_keyboard' => \Helpers\DateHelper::getMonthSelector((int)$year)];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت (انتخاب سال)", 'callback_data' => 'cycle_pick_year']];
        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleCycleSelectMonth($telegramId, $chatId, $messageId, $year, $month) {
        $text = "📅 انتخاب روز برای ماه {$month} سال {$year}:";
        $keyboard = ['inline_keyboard' => \Helpers\DateHelper::getDaySelector((int)$year, (int)$month)];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت (انتخاب ماه)", 'callback_data' => 'cycle_select_year:' . $year]];
        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleCycleLogDate($telegramId, $chatId, $messageId, $year, $month = null, $day = null) {
        // If month and day are null, $year is actually the full date string 'YYYY-MM-DD'
        if ($month === null && $day === null) {
            $dateString = $year; // $year here is the full date string
        } else {
            if (!\Helpers\DateHelper::isValidDate((int)$year, (int)$month, (int)$day)) {
                $this->telegramAPI->sendMessage($chatId, "تاریخ انتخاب شده نامعتبر است. لطفا دوباره تلاش کنید.");
                $this->handleLogPeriodStartPrompt($telegramId, $chatId, $messageId); // Or take them to year selection
                return;
            }
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // Future date check
        if (strtotime($dateString) > time()) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "تاریخ شروع پریود نمی‌تواند در آینده باشد. لطفا تاریخ معتبری انتخاب کنید.",
                ['inline_keyboard' => [[['text' => " تلاش مجدد", 'callback_data' => 'cycle_log_period_start_prompt']]]]);
            return;
        }

        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) { /* Error handling */ return; }

        $cycleInfo = !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];

        if (!isset($cycleInfo['period_start_dates'])) {
            $cycleInfo['period_start_dates'] = [];
        }

        // Add date if not already present, maintain sorted order (most recent first)
        if (!in_array($dateString, $cycleInfo['period_start_dates'])) {
            $cycleInfo['period_start_dates'][] = $dateString;
            usort($cycleInfo['period_start_dates'], function($a, $b) {
                return strtotime($b) - strtotime($a); // Sort descending
            });
            // Optional: Limit the number of stored start dates, e.g., last 12
            $cycleInfo['period_start_dates'] = array_slice($cycleInfo['period_start_dates'], 0, 12);
        }

        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $editedMessageText = "تاریخ شروع آخرین پریود شما (" . $dateString . ") ثبت شد. ✅";
        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $editedMessageText);
        } else {
             $this->telegramAPI->sendMessage($chatId, $editedMessageText);
        }


        // Check if average lengths are set, if not, ask for them
        if (!isset($cycleInfo['average_period_length']) || !isset($cycleInfo['average_cycle_length'])) {
            $this->handleAskAverageLengths($telegramId, $chatId);
        } else {
            $this->showMainMenu($chatId, "اطلاعات شما به‌روز شد.");
        }
    }

    public function handleAskAverageLengths($telegramId, $chatId, $messageId = null) {
        $text = "برای پیش‌بینی دقیق‌تر، لطفا اطلاعات زیر را وارد کنید:\n\nمیانگین طول دوره پریود شما چند روز است؟ (معمولا بین ۳ تا ۷ روز)";
        $buttons = [];
        for ($i = 2; $i <= 10; $i++) { $buttons[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_period:$i"]; }

        $keyboard = ['inline_keyboard' => array_chunk($buttons, 3)];
        $keyboard['inline_keyboard'][] = [['text' => " نمی‌دانم/رد کردن", 'callback_data' => 'cycle_skip_avg_period']];

        if ($messageId) {
             $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function handleSetAveragePeriodLength($telegramId, $chatId, $messageId, $length) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) { /* Error handling */ return; }

        $cycleInfo = !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];
        $cycleInfo['average_period_length'] = (int)$length;
        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $text = "میانگین طول پریود شما {$length} روز ثبت شد.\n\nمیانگین طول کل چرخه قاعدگی شما چند روز است؟ (از اولین روز یک پریود تا اولین روز پریود بعدی، معمولا بین ۲۱ تا ۳۵ روز)";
        $buttons = [];
        for ($i = 20; $i <= 45; $i++) { $buttons[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_cycle:$i"]; }

        $keyboard = ['inline_keyboard' => array_chunk($buttons, 5)]; // 5 buttons per row
        $keyboard['inline_keyboard'][] = [['text' => " نمی‌دانم/رد کردن", 'callback_data' => 'cycle_skip_avg_cycle']];

        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleSetAverageCycleLength($telegramId, $chatId, $messageId, $length) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) { /* Error handling */ return; }

        $cycleInfo = !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];
        $cycleInfo['average_cycle_length'] = (int)$length;
        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $this->telegramAPI->editMessageText($chatId, $messageId, "میانگین طول چرخه شما {$length} روز ثبت شد. ممنون از اطلاعات شما! 👍");
        $this->showMainMenu($chatId, "اطلاعات دوره شما با موفقیت به‌روز شد.");
    }

    public function handleSkipAverageInfo($telegramId, $chatId, $messageId, $type) {
        // Type can be 'period' or 'cycle'
        // We just acknowledge and move to main menu or next step
        $text = "";
        if ($type === 'period') {
            $text = "بسیار خب. می‌توانید این اطلاعات را بعدا از بخش تنظیمات تکمیل کنید.\n\nمیانگین طول کل چرخه قاعدگی شما چند روز است؟ (از اولین روز یک پریود تا اولین روز پریود بعدی، معمولا بین ۲۱ تا ۳۵ روز)";
            $buttons = [];
            for ($i = 20; $i <= 45; $i++) { $buttons[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_cycle:$i"]; }
            $keyboard = ['inline_keyboard' => array_chunk($buttons, 5)];
            $keyboard['inline_keyboard'][] = [['text' => " نمی‌دانم/رد کردن", 'callback_data' => 'cycle_skip_avg_cycle']];
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
            return;
        } elseif ($type === 'cycle') {
             $text = "بسیار خب. می‌توانید این اطلاعات را بعدا از بخش تنظیمات تکمیل کنید. اطلاعات شما ثبت شد.";
             $this->telegramAPI->editMessageText($chatId, $messageId, $text);
        }

        $this->showMainMenu($chatId, "اطلاعات اولیه دوره شما ثبت شد.");
    }


    // --------- CYCLE LOGGING METHODS END -----------
    }
}
?>
