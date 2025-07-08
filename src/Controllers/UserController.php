<?php

namespace Controllers;

use Models\UserModel;
use Telegram\TelegramAPI;
use Helpers\EncryptionHelper;

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
            $this->telegramAPI->sendMessage($chatId, "سلام مجدد {$firstName}! خوشحالیم که دوباره شما را می‌بینیم. 😊");
            $this->showMainMenu($chatId);
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
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$chatId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "ثبت نام شما کامل نشده یا مشکلی پیش آمده. لطفا با ارسال /start مجددا تلاش کنید.");
            return;
        }

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

        $menuText = $text;
        $buttons = [];
        $hasAccess = $this->checkSubscriptionAccess($hashedTelegramId);

        if ($decryptedRole === 'menstruating') {
            if ($hasAccess && $cycleInfo && !empty($cycleInfo['period_start_dates'])) {
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
                }
            } else {
                 $menuText .= "\n\nبرای مشاهده اطلاعات دوره، ابتدا تاریخ آخرین پریود خود را از طریق دکمه زیر ثبت کنید.";
            }
            $buttons[] = [['text' => "🩸 ثبت/ویرایش اطلاعات دوره", 'callback_data' => 'cycle_log_period_start_prompt']];
            $buttons[] = [['text' => "📝 ثبت علائم روزانه", 'callback_data' => 'symptom_log_start:today']];
        }

        if (empty($user['partner_telegram_id_hash'])) {
            if (empty($user['invitation_token'])) {
                $buttons[] = [['text' => "💌 دعوت از همراه", 'callback_data' => 'partner_invite']];
            } else {
                $buttons[] = [['text' => "🔗 لغو دعوتنامه ارسال شده", 'callback_data' => 'partner_cancel_invite']];
            }
            $buttons[] = [['text' => "🤝 پذیرش دعوتنامه (با کد)", 'callback_data' => 'partner_accept_prompt']];
        } else {
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

            if ($decryptedRole === 'partner' && $partnerUser) {
                // Partner viewing menstruating user's info. Access depends on the partner's own subscription.
                if ($hasAccess) { // $hasAccess here refers to the partner's subscription status
                    $partnerCycleInfoData = null;
                    if (!empty($partnerUser['encrypted_cycle_info'])) {
                        try {
                            $partnerCycleInfoData = json_decode(EncryptionHelper::decrypt($partnerUser['encrypted_cycle_info']), true);
                        } catch (\Exception $e) { error_log("Failed to decrypt partner's cycle_info: " . $e->getMessage()); }
                    }

                    if ($partnerCycleInfoData && !empty($partnerCycleInfoData['period_start_dates'])) {
                        $partnerCycleService = new \Services\CycleService($partnerCycleInfoData);
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
                    }
                } else {
                     $menuText .= "\n\n{$partnerFirstName} هنوز اطلاعات دوره‌ای ثبت نکرده است.";
                }
            }
        }

        $buttons[] = [['text' => "⚙️ تنظیمات", 'callback_data' => 'settings_show']];
        $buttons[] = [['text' => "راهنما ❓", 'callback_data' => 'show_guidance']];
        $buttons[] = [['text' => "💬 پشتیبانی", 'callback_data' => 'support_request_start']];
        $buttons[] = [['text' => "ℹ️ درباره ما", 'callback_data' => 'show_about_us']];

        // Conditional subscription button
        $showSubscriptionButton = true;
        if (isset($user['subscription_status']) && $user['subscription_status'] === 'active' && !empty($user['subscription_ends_at'])) {
            // Could add logic here to show if expiry is near, or a "Manage Subscription" button
            // For now, if active, don't show "Buy Subscription"
            $showSubscriptionButton = false;
        }
        if ($showSubscriptionButton) {
             $buttons[] = [['text' => "خرید اشتراک 💳", 'callback_data' => 'sub_show_plans']];
        }

        // Admin Panel Button
        if ((string)$chatId === ADMIN_TELEGRAM_ID) { // Ensure ADMIN_TELEGRAM_ID is defined and matches
            $buttons[] = [['text' => "👑 پنل ادمین", 'callback_data' => 'admin_show_menu']];
        }


        $keyboard = ['inline_keyboard' => $buttons];
        $this->telegramAPI->sendMessage($chatId, $menuText, $keyboard, 'Markdown');
    }

    public function handleShowSubscriptionPlans($telegramId, $chatId, $messageId = null) {
        $subscriptionPlanModel = new \Models\SubscriptionPlanModel();
        $plans = $subscriptionPlanModel->getActivePlans();

        if (empty($plans)) {
            $text = "متاسفانه در حال حاضر هیچ طرح اشتراکی فعالی وجود ندارد. لطفا بعدا دوباره سر بزنید.";
            $keyboard = [['inline_keyboard' => [[['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]]]];
            if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
            else $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
            return;
        }

        $text = "💎 طرح‌های اشتراک «همراه من»:\n\n";
        $planButtons = [];
        foreach ($plans as $plan) {
            $priceFormatted = number_format($plan['price']); // Format price for readability
            $buttonText = "{$plan['name']} ({$plan['duration_months']} ماهه) - {$priceFormatted} تومان";
            if (!empty($plan['description'])) {
                 // $text .= "*{$plan['name']}* ({$plan['duration_months']} ماهه) - {$priceFormatted} تومان\n_{$plan['description']}_\n\n"; // Add to main text
            }
            $planButtons[] = [['text' => $buttonText, 'callback_data' => 'sub_select_plan:' . $plan['id']]];
        }

        $keyboard = ['inline_keyboard' => $planButtons];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']];

        // Send as a new message or edit existing one
        if ($messageId) {
            // Check if the current message text is already the plans list to avoid "message is not modified"
            // For simplicity, just try to edit. If it fails, it means it's likely the same message or an issue.
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        }
    }


    public function handleShowAboutUs($telegramId, $chatId, $messageId = null) {
        $appSettingsModel = new \Models\AppSettingsModel();
        $aboutUsText = $appSettingsModel->getSetting('about_us_text');

        if (empty($aboutUsText)) {
            $aboutUsText = "به ربات «همراه من» خوش آمدید!\n\nما تیمی هستیم که به سلامت و آگاهی شما و همراهتان اهمیت می‌دهیم. هدف ما ارائه ابزاری کاربردی برای درک بهتر چرخه قاعدگی و تقویت روابط زوجین است.\n\nنسخه فعلی: 1.0.0 (توسعه اولیه)";
        }

        $text = "ℹ️ **درباره ما**\n\n" . $aboutUsText;
        $keyboard = [['inline_keyboard' => [[['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]]]];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        }
    }

    public function handleShowGuidance($telegramId, $chatId, $messageId = null) {
        $guidanceText = "راهنمای استفاده از ربات «همراه من»:\n\n";
        $guidanceText .= "1.  **ثبت نام و نقش:**\n";
        $guidanceText .= "    - با اولین پیام به ربات، ثبت نام می‌شوید.\n";
        $guidanceText .= "    - نقش خود را انتخاب کنید: «من پریود می‌شوم» یا «همراه هستم».\n\n";
        $guidanceText .= "2.  **اتصال به همراه:**\n";
        $guidanceText .= "    - از منوی اصلی، «💌 دعوت از همراه» را انتخاب کنید تا لینک دعوت بسازید.\n";
        $guidanceText .= "    - لینک را برای همراه خود بفرستید. وقتی همراهتان روی لینک کلیک کند، به هم متصل می‌شوید.\n";
        $guidanceText .= "    - برای پذیرش دعوت با کد: «🤝 پذیرش دعوتنامه» را زده و کد دریافتی را وارد کنید.\n";
        $guidanceText .= "    - برای قطع اتصال: «💔 قطع اتصال» را از منوی اصلی انتخاب کنید.\n\n";
        $guidanceText .= "3.  **ثبت اطلاعات دوره (برای افرادی که پریود می‌شوند):**\n";
        $guidanceText .= "    - از منوی اصلی، «🩸 ثبت/ویرایش اطلاعات دوره» را انتخاب کنید.\n";
        $guidanceText .= "    - تاریخ شروع آخرین پریود خود را وارد کنید (امروز، دیروز، یا از تقویم).\n";
        $guidanceText .= "    - میانگین طول دوره پریود و طول کل چرخه قاعدگی خود را وارد کنید تا پیش‌بینی‌ها دقیق‌تر شوند.\n\n";
        $guidanceText .= "4.  **ثبت علائم روزانه (برای افرادی که پریود می‌شوند):**\n";
        $guidanceText .= "    - از منوی اصلی، «📝 ثبت علائم روزانه» را انتخاب کنید.\n";
        $guidanceText .= "    - دسته‌بندی علائم (مثل حالت روحی، درد جسمی) را انتخاب کنید.\n";
        $guidanceText .= "    - علائم مورد نظر را انتخاب (یا لغو انتخاب) کنید.\n";
        $guidanceText .= "    - در نهایت «✅ ثبت نهایی علائم» را بزنید.\n\n";
        $guidanceText .= "5.  **اطلاعات برای همراه:**\n";
        $guidanceText .= "    - اگر به عنوان «همراه» متصل شده‌اید، در منوی اصلی خلاصه‌ای از وضعیت دوره همراهتان (روز چندم، فاز تخمینی) را می‌بینید.\n";
        $guidanceText .= "    - اعلان‌ها و پیام‌های روزانه متناسب با وضعیت همراهتان دریافت خواهید کرد.\n\n";
        $guidanceText .= "6.  **اعلان‌ها و پیام‌های روزانه:**\n";
        $guidanceText .= "    - ربات اعلان‌هایی مانند نزدیک شدن به PMS، شروع پریود، پایان پریود و روز تخمک‌گذاری ارسال می‌کند.\n";
        $guidanceText .= "    - همچنین پیام‌های آموزشی و احساسی روزانه متناسب با نقش و وضعیت دوره دریافت خواهید کرد.\n";
        $guidanceText .= "    - می‌توانید زمان دریافت اعلان‌های روزانه را از «⚙️ تنظیمات» > «⏰ تنظیم زمان اعلان‌ها» تغییر دهید.\n\n";
        $guidanceText .= "7.  **پشتیبانی:**\n";
        $guidanceText .= "    - از منوی اصلی، «💬 پشتیبانی» را انتخاب کنید. پیام بعدی شما مستقیما برای ادمین ارسال خواهد شد.\n\n";
        $guidanceText .= "امیدواریم این ربات برای شما مفید باشد! 😊";

        $keyboard = [['inline_keyboard' => [[['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]]]];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $guidanceText, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $guidanceText, $keyboard, 'Markdown');
        }
    }

    public function handleSupportRequestStart($telegramId, $chatId, $messageId = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $this->userModel->updateUser($hashedTelegramId, ['user_state' => 'awaiting_support_message']);

        $text = "💬 شما در حال ارسال پیام به پشتیبانی هستید.\nلطفا پیام خود را بنویسید و ارسال کنید. پیام شما مستقیما برای ادمین ارسال خواهد شد.\n\nبرای لغو، /cancel را ارسال کنید یا از منوی اصلی گزینه دیگری انتخاب نمایید.";

        $emptyKeyboard = json_encode(['inline_keyboard' => []]);
        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $emptyKeyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $emptyKeyboard); // Send with empty keyboard to remove any previous one if it was a new message context
        }
    }

    public function handleForwardSupportMessage($telegramUserId, $chatId, $text, $firstName, $username) {
        $adminTelegramId = ADMIN_TELEGRAM_ID;
        if (empty($adminTelegramId) || $adminTelegramId === 'YOUR_ADMIN_TELEGRAM_ID') {
            error_log("Admin Telegram ID not configured. Cannot forward support message.");
            $this->telegramAPI->sendMessage($chatId, "متاسفانه امکان ارسال پیام به پشتیبانی در حال حاضر وجود ندارد.");
            return;
        }

        $forwardMessage = "پیام پشتیبانی جدید از کاربر:\n";
        $forwardMessage .= "نام: {$firstName}\n";
        if ($username) {
            $forwardMessage .= "نام کاربری تلگرام: @{$username}\n";
        }
        $forwardMessage .= "ID تلگرام کاربر: {$telegramUserId}\n";
        $forwardMessage .= "متن پیام:\n--------------------\n{$text}\n--------------------";

        $this->telegramAPI->sendMessage($adminTelegramId, $forwardMessage);

        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramUserId);
        $this->userModel->updateUser($hashedTelegramId, ['user_state' => null]);

        $this->telegramAPI->sendMessage($chatId, "پیام شما با موفقیت برای پشتیبانی ارسال شد. ✅");
        $this->showMainMenu($chatId, "به منوی اصلی بازگشتید:");
    }

    public function handleSettings($telegramId, $chatId, $messageId = null) {
        $text = "⚙️ تنظیمات\n\nچه کاری می‌خواهید انجام دهید؟";
        $buttons = [
            [['text' => "⏰ تنظیم زمان اعلان‌ها", 'callback_data' => 'settings_set_notify_time_prompt']],
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

        $keyboard = ['inline_keyboard' => array_chunk($timeButtons, 3)];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به تنظیمات", 'callback_data' => 'settings_show']];

        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleSetNotificationTime($telegramId, $chatId, $messageId, $time) {
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            $this->telegramAPI->sendMessage($chatId, "فرمت زمان نامعتبر است، لطفا دوباره تلاش کنید.");
            return;
        }

        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $updated = $this->userModel->updateUser($hashedTelegramId, ['preferred_notification_time' => $time . ':00']);

        if ($updated) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "⏰ زمان دریافت اعلان‌های شما به {$time} تغییر یافت.");
        } else {
            $this->telegramAPI->editMessageText($chatId, $messageId, "مشکلی در ذخیره زمان اعلان پیش آمد. لطفا دوباره تلاش کنید.");
        }
        $this->showMainMenu($chatId, "به منوی اصلی بازگشتید.");
    }

    private function getBotUsername() {
        $botInfo = $this->telegramAPI->getMe();
        if ($botInfo && $botInfo['ok']) {
            return $botInfo['result']['username'];
        }
        return 'YOUR_BOT_USERNAME';
    }

    public function handleGenerateInvitation($telegramId, $chatId, $messageIdToEdit = null) { // Added messageIdToEdit
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);

        if (!$this->checkSubscriptionAccess($hashedTelegramId)) {
            $this->promptToSubscribe($chatId, $messageIdToEdit, "قابلیت دعوت از همراه");
            return;
        }

        $user = $this->userModel->findUserByTelegramId($hashedTelegramId); // Already fetched in checkSubscriptionAccess, but cleaner to fetch again or pass user object

        if (!$user) {
            // This case should be rare if checkSubscriptionAccess passed, but as a safeguard
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }
        if (!empty($user['partner_telegram_id_hash'])) {
            $message = "شما در حال حاضر یک همراه متصل دارید. برای دعوت از فرد جدید، ابتدا باید اتصال فعلی را قطع کنید.";
            if ($messageIdToEdit) $this->telegramAPI->editMessageText($chatId, $messageIdToEdit, $message, json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'main_menu_show']]]]));
            else $this->telegramAPI->sendMessage($chatId, $message, json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'main_menu_show']]]]));
            // $this->showMainMenu($chatId); // Avoid recursive call if showMainMenu itself calls this
            return;
        }

        $token = $this->userModel->generateInvitationToken($hashedTelegramId);

        if ($token) {
            $botUsername = $this->getBotUsername();
            $invitationLink = "https://t.me/{$botUsername}?start=invite_{$token}";
            // Using classic Markdown, `backticks` are for code blocks. For simple highlighting of the link/token,
            // we might not need them or can use *bold* or just plain text.
            // For now, let's remove backticks to be safe with classic Markdown, or ensure they are correctly paired if intended as code.
            // Telegram classic markdown treats single backticks as inline code.
            $message = "لینک دعوت برای همراه شما ایجاد شد:\n\n{$invitationLink}\n\nاین لینک را کپی کرده و برای فرد مورد نظر ارسال کنید. این لینک یکبار مصرف است و پس از استفاده یا ساخت لینک جدید، باطل می‌شود.\n\nهمچنین همراه شما می‌تواند کد زیر را مستقیما در ربات وارد کند (از طریق دکمه پذیرش دعوتنامه):\n{$token}";
            $this->telegramAPI->sendMessage($chatId, $message, null, 'Markdown'); // Switched to Markdown
            $this->showMainMenu($chatId, "لینک دعوت ارسال شد. منوی اصلی:");
            return;
        } else {
            $this->telegramAPI->sendMessage($chatId, "متاسفانه مشکلی در ایجاد لینک دعوت پیش آمد. لطفا دوباره تلاش کنید.");
        }
        $this->showMainMenu($chatId);
    }

    public function handleCancelInvitation($telegramId, $chatId, $messageId = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

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
        $this->telegramAPI->sendMessage($chatId, "لطفا کد دعوتی که از همراه خود دریافت کرده‌اید را ارسال کنید, یا از طریق لینکی که همراهتان فرستاده اقدام کنید.");
    }

    public function handleAcceptInvitationCommand(string $telegramId, int $chatId, string $firstName, ?string $username, string $token) {
        $accepterHashedId = EncryptionHelper::hashIdentifier($telegramId);

        // Check subscription status of the user trying to accept.
        // If they are a new user being created by this flow, checkSubscriptionAccess might fail initially.
        // So, we might need to allow acceptance, then prompt for subscription if they are new and trial needs to start,
        // or if the *feature of being partnered* is premium.
        // For now, let's assume accepting an invite might be free, but using partner features requires the *inviter* or *accepter* to be subbed.
        // Let's check the accepter's status. If they are an existing user, they need access.
        // If they are a new user, they will get a free trial upon creation.

        $accepterUser = $this->userModel->findUserByTelegramId($accepterHashedId);

        if ($accepterUser) { // If user already exists, check their subscription
            if (!$this->checkSubscriptionAccess($accepterHashedId)) {
                $this->promptToSubscribe($chatId, null, "قابلیت اتصال به همراه"); // Send as new message
                return;
            }
        } else {
            // New user: will be created and get a free trial. Access check will pass after creation.
            $this->userModel->createUser($accepterHashedId, (string)$chatId, $firstName, $username);
            $accepterUser = $this->userModel->findUserByTelegramId($accepterHashedId);
            if (!$accepterUser) {
                 $this->telegramAPI->sendMessage($chatId, "خطا در پردازش اطلاعات شما. لطفا ربات را مجددا با /start آغاز کنید و سپس تلاش کنید.");
                 return;
            }
        }

        if (!empty($accepterUser['partner_telegram_id_hash'])) {
            $this->telegramAPI->sendMessage($chatId, "شما در حال حاضر یک همراه متصل دارید. برای پذیرش این دعوت، ابتدا باید اتصال فعلی خود را قطع کنید.");
            $this->showMainMenu($chatId);
            return;
        }

        $inviterUser = $this->userModel->findUserByInvitationToken($token);

        if (!$inviterUser) {
            $this->telegramAPI->sendMessage($chatId, "کد دعوت نامعتبر است یا منقضی شده.");
            $this->showMainMenu($chatId);
            return;
        }

        $inviterHashedId = $inviterUser['telegram_id_hash'];
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
                $this->showMainMenu($chatId);
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
        $partnerFirstName = "همراهتان";
        $partnerUserDB = $this->userModel->findUserByTelegramId($partnerHashedId);
         if ($partnerUserDB && !empty($partnerUserDB['encrypted_first_name'])) {
            try {
                $partnerFirstName = EncryptionHelper::decrypt($partnerUserDB['encrypted_first_name']);
            } catch (\Exception $e) { /* log */ }
        }

        if ($this->userModel->unlinkPartners($userHashedId, $partnerHashedId)) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "اتصال شما از {$partnerFirstName} با موفقیت قطع شد.");
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
            $response = $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
            if (!$response || !$response['ok']) {
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
        if ($month === null && $day === null) {
            $dateString = $year;
        } else {
            if (!\Helpers\DateHelper::isValidDate((int)$year, (int)$month, (int)$day)) {
                $this->telegramAPI->sendMessage($chatId, "تاریخ انتخاب شده نامعتبر است. لطفا دوباره تلاش کنید.");
                $this->handleLogPeriodStartPrompt($telegramId, $chatId, $messageId);
                return;
            }
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        if (strtotime($dateString) > time()) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "تاریخ شروع پریود نمی‌تواند در آینده باشد. لطفا تاریخ معتبری انتخاب کنید.",
                ['inline_keyboard' => [[['text' => " تلاش مجدد", 'callback_data' => 'cycle_log_period_start_prompt']]]]);
            return;
        }

        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) {
             $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد. لطفا /start را مجددا اجرا کنید.");
            return;
        }

        $cycleInfo = !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];

        if (!isset($cycleInfo['period_start_dates'])) {
            $cycleInfo['period_start_dates'] = [];
        }

        if (!in_array($dateString, $cycleInfo['period_start_dates'])) {
            $cycleInfo['period_start_dates'][] = $dateString;
            usort($cycleInfo['period_start_dates'], function($a, $b) {
                return strtotime($b) - strtotime($a);
            });
            $cycleInfo['period_start_dates'] = array_slice($cycleInfo['period_start_dates'], 0, 12);
        }

        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $editedMessageText = "تاریخ شروع آخرین پریود شما (" . $dateString . ") ثبت شد. ✅";
        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $editedMessageText, [], '');
        } else {
             $this->telegramAPI->sendMessage($chatId, $editedMessageText);
        }

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

        $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
    }

    public function handleSetAveragePeriodLength($telegramId, $chatId, $messageId, $length) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }

        $cycleInfo = !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];
        $cycleInfo['average_period_length'] = (int)$length;
        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $text = "میانگین طول پریود شما {$length} روز ثبت شد.\n\nمیانگین طول کل چرخه قاعدگی شما چند روز است؟ (از اولین روز یک پریود تا اولین روز پریود بعدی، معمولا بین ۲۱ تا ۳۵ روز)";
        $buttons = [];
        for ($i = 20; $i <= 45; $i++) { $buttons[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_cycle:$i"]; }

        $keyboard = ['inline_keyboard' => array_chunk($buttons, 5)];
        $keyboard['inline_keyboard'][] = [['text' => " نمی‌دانم/رد کردن", 'callback_data' => 'cycle_skip_avg_cycle']];

        $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function handleSetAverageCycleLength($telegramId, $chatId, $messageId, $length) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
         if (!$user) {
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

    public function getUserModel(): \Models\UserModel {
        return $this->userModel;
    }

    public function handleLogSymptomStart($telegramId, $chatId, $messageId = null, $dateOption = 'today') {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId); // Use $telegramId consistently
        if (!$this->checkSubscriptionAccess($hashedTelegramId)) {
            $this->promptToSubscribe($chatId, $messageId, "قابلیت ثبت علائم");
            return;
        }

        $this->loadSymptomsConfig();
        $symptomDate = ($dateOption === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

        // $userHashedId is already defined
        $userIdRecord = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$userIdRecord) { /* handle error, though checkSubscriptionAccess might have caught it */
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }
        $dbUserId = $userIdRecord['id'];

        $loggedSymptomsRaw = $this->getSymptomModel()->getLoggedSymptomsForDate($dbUserId, $symptomDate);
        $currentlyLoggedSet = [];
        foreach ($loggedSymptomsRaw as $s) {
            $currentlyLoggedSet[$s['category_key'] . '_' . $s['symptom_key']] = true;
        }

        $text = "📝 علائم روز: " . $symptomDate . "\n\n";
        $text .= "لطفا یک دسته بندی انتخاب کنید یا علائم ثبت شده را نهایی کنید.\n";

        $categoryButtons = [];
        foreach ($this->symptomsConfig['categories'] as $key => $label) {
            $categoryButtons[] = ['text' => $label, 'callback_data' => "symptom_show_cat:{$dateOption}:{$key}"];
        }

        $keyboard = [
            'inline_keyboard' => array_chunk($categoryButtons, 2)
        ];
        $keyboard['inline_keyboard'][] = [
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
            'inline_keyboard' => array_chunk($symptomButtons, 2)
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => "다른 دسته‌بندی‌ها" , 'callback_data' => "symptom_log_start:{$dateOption}"],
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
            $remainingSymptoms = $this->getSymptomModel()->getLoggedSymptomsForDate($dbUserId, $symptomDate);
            if (!empty($remainingSymptoms)) {
                 $this->userModel->updateUser($userHashedId, ['last_symptom_log_date' => $symptomDate]);
            } else {
                if (!$isCurrentlyLogged) {
                    $this->userModel->updateUser($userHashedId, ['last_symptom_log_date' => $symptomDate]);
                }
            }
        }
        $this->handleSymptomShowCategory($telegramId, $chatId, $messageId, $dateOption, $categoryKey);
    }

    public function handleSymptomSaveFinal($telegramId, $chatId, $messageId, $dateOption) {
        $symptomDate = ($dateOption === 'yesterday') ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

        $userHashedId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $userIdRecord = $this->userModel->findUserByTelegramId($userHashedId);
        if (!$userIdRecord) { /* handle error */ return; }
        $dbUserId = $userIdRecord['id'];

        $loggedSymptoms = $this->getSymptomModel()->getLoggedSymptomsForDate($dbUserId, $symptomDate);

        if (empty($loggedSymptoms)) {
            $text = "هیچ علامتی برای تاریخ {$symptomDate} ثبت نشد.";
        } else {
            $text = "علائم شما برای تاریخ {$symptomDate} با موفقیت ثبت شد! ✅\n";
        }

        $this->telegramAPI->editMessageText($chatId, $messageId, $text, json_encode(['inline_keyboard' => []]));
        $this->showMainMenu($chatId, "به منوی اصلی بازگشتید.");
    }

    // --------- SYMPTOM LOGGING METHODS END -----------

    // --------- SUBSCRIPTION METHODS START -----------
    public function handleSubscribePlan($telegramId, $chatId, $messageId, $planId) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }

        $subscriptionPlanModel = new \Models\SubscriptionPlanModel();
        $plan = $subscriptionPlanModel->getPlanById((int)$planId);

        if (!$plan || !$plan['is_active']) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "متاسفانه طرح انتخاب شده معتبر یا فعال نیست. لطفا دوباره تلاش کنید.", json_encode(['inline_keyboard' => [[['text' => "🔙 نمایش طرح‌ها", 'callback_data' => 'sub_show_plans']]]]));
            return;
        }

        $zarinpalService = new \Services\ZarinpalService();
        // Amount should be in Toman as per Zarinpal docs for v4, ensure price is stored correctly.
        $amount = (int)$plan['price'];
        $description = "خرید اشتراک: " . $plan['name'];
        // User email/mobile can be fetched from user profile if stored, or passed as null
        $userEmail = null; // Example: $user['email'] if you store it
        $userMobile = null; // Example: $user['mobile'] if you store it

        $paymentUrl = $zarinpalService->requestPayment($amount, $user['id'], (int)$planId, $description, $userEmail, $userMobile);

        if ($paymentUrl) {
            $text = "شما طرح «{$plan['name']}» ({$plan['duration_months']} ماهه) به قیمت " . number_format($plan['price']) . " تومان را انتخاب کردید.\n\n";
            $text .= "برای تکمیل خرید، لطفا از طریق لینک زیر پرداخت خود را انجام دهید:";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "💳 پرداخت آنلاین", 'url' => $paymentUrl]],
                    [['text' => "🔙 انتخاب طرح دیگر", 'callback_data' => 'sub_show_plans']],
                    [['text' => "🏠 منوی اصلی", 'callback_data' => 'main_menu_show']]
                ]
            ];
            if ($messageId) {
                 $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
            } else {
                 $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
            }
        } else {
            $text = "متاسفانه در اتصال به درگاه پرداخت مشکلی پیش آمد. لطفا لحظاتی دیگر دوباره تلاش کنید.";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "🔙 انتخاب طرح دیگر", 'callback_data' => 'sub_show_plans']],
                    [['text' => "🏠 منوی اصلی", 'callback_data' => 'main_menu_show']]
                ]
            ];
             if ($messageId) {
                $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard);
            } else {
                $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
            }
        }
    }
    // --------- SUBSCRIPTION METHODS END -----------

    // --------- ACCESS CONTROL START -----------
    private function checkSubscriptionAccess(string $hashedTelegramId): bool {
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) {
            return false; // Should not happen for an active user flow
        }

        if ($user['subscription_status'] === 'active') {
            if (empty($user['subscription_ends_at'])) return true; // Active, no end date (lifetime? or error?) -> allow
            try {
                $expiryDate = new \DateTime($user['subscription_ends_at']);
                return $expiryDate > new \DateTime(); // Active and not expired
            } catch (\Exception $e) {
                error_log("Error parsing subscription_ends_at for user {$hashedTelegramId}: " . $e->getMessage());
                return false; // Error in date, deny access
            }
        } elseif ($user['subscription_status'] === 'free_trial') {
            if (empty($user['trial_ends_at'])) return false; // Trial but no end date -> deny
            try {
                $trialExpiryDate = new \DateTime($user['trial_ends_at']);
                return $trialExpiryDate > new \DateTime(); // Trial and not expired
            } catch (\Exception $e) {
                error_log("Error parsing trial_ends_at for user {$hashedTelegramId}: " . $e->getMessage());
                return false; // Error in date, deny access
            }
        }
        return false; // 'expired', 'none', or any other status
    }

    private function promptToSubscribe(int $chatId, ?int $messageIdToEdit = null, string $featureName = "این قابلیت") {
        $text = "⚠️ برای دسترسی به {$featureName}، نیاز به اشتراک فعال دارید.\n\nلطفا یکی از طرح‌های اشتراک ما را انتخاب کنید:";
        // $this->handleShowSubscriptionPlans will be called by the callback 'sub_show_plans'
        $keyboard = [
            'inline_keyboard' => [
                [['text' => "مشاهده طرح‌های اشتراک 💳", 'callback_data' => 'sub_show_plans']],
                [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]
            ]
        ];
        if ($messageIdToEdit) {
            $this->telegramAPI->editMessageText($chatId, $messageIdToEdit, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
    }
    // --------- ACCESS CONTROL END -----------

}
?>
