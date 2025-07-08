<?php

namespace Controllers;

use Models\UserModel;
use Models\SymptomModel; // Added for symptom logging
use Models\EducationalContentModel; // For tutorials
use Models\SubscriptionPlanModel; // For subscriptions
use Models\AppSettingsModel; // For About Us
use Telegram\TelegramAPI;
use Helpers\EncryptionHelper;
use Services\CycleService; // For cycle calculations

class UserController {
    private $userModel;
    private $telegramAPI;
    private $symptomsConfig;
    private $symptomModel;
    private $periodHistoryModel; // Added

    public function __construct(TelegramAPI $telegramAPI) {
        $this->userModel = new UserModel();
        $this->telegramAPI = $telegramAPI;
        $this->periodHistoryModel = new \Models\PeriodHistoryModel(); // Added
        // Symptom model and config are loaded on demand now
    }

    private function loadSymptomsConfig() {
        if ($this->symptomsConfig === null) {
            $this->symptomsConfig = require BASE_PATH . '/config/symptoms_config.php';
        }
    }
    private function getSymptomModel(): SymptomModel {
        if ($this->symptomModel === null) {
            $this->symptomModel = new SymptomModel();
        }
        return $this->symptomModel;
    }

    public function getUserModel(): UserModel {
        return $this->userModel;
    }

    private function getBotUsername() {
        $botInfo = $this->telegramAPI->getMe();
        return ($botInfo && $botInfo['ok']) ? $botInfo['result']['username'] : 'YOUR_BOT_USERNAME';
    }

    private function isAdmin(string $telegramId): bool {
        return defined('ADMIN_TELEGRAM_ID') && (string)$telegramId === ADMIN_TELEGRAM_ID;
    }

    public function handleStart($telegramId, $chatId, $firstName, $username = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            $this->userModel->createUser($hashedTelegramId, (string)$chatId, $firstName, $username);
            $welcomeMessage = "سلام {$firstName}! 👋\nبه ربات «همراه من» خوش آمدید.\n\nاینجا فضایی امن برای شما و همراهتان است تا چرخه قاعدگی را بهتر درک کنید، توصیه‌های روزانه دریافت کنید و با هم در ارتباط باشید.\n\nبرای شروع، لطفا نقش خود را مشخص کنید:";
            $keyboard = ['inline_keyboard' => [
                [['text' => "🩸 من پریود می‌شوم", 'callback_data' => 'select_role:menstruating']],
                [['text' => "🤝 همراه هستم", 'callback_data' => 'select_role:partner']],
                [['text' => "🚫 ترجیح می‌دهم نگویم", 'callback_data' => 'select_role:prefer_not_to_say']]
            ]];
            $this->telegramAPI->sendMessage($chatId, $welcomeMessage, $keyboard);
        } else {
            $this->userModel->updateUser($hashedTelegramId, ['user_state' => null]); // Clear state on /start
            $this->telegramAPI->sendMessage($chatId, "سلام مجدد {$firstName}! خوشحالیم که دوباره شما را می‌بینیم. 😊");
            $this->showMainMenu($chatId);
        }
    }

    public function handleRoleSelection($telegramId, $chatId, $role, $messageId) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "متاسفانه مشکلی در شناسایی شما پیش آمده. لطفا دوباره با ارسال /start شروع کنید.");
            return;
        }

        if (empty($user['encrypted_role'])) {
            $encryptedRole = EncryptionHelper::encrypt($role);
            $this->userModel->updateUser($hashedTelegramId, ['encrypted_role' => $encryptedRole]);
            $roleMessage = "";
            switch ($role) {
                case 'menstruating': $roleMessage = "نقش شما به عنوان «فردی که پریود می‌شود» ثبت شد. 🩸"; break;
                case 'partner': $roleMessage = "نقش شما به عنوان «همراه» ثبت شد. 🤝"; break;
                case 'prefer_not_to_say': $roleMessage = "انتخاب شما ثبت شد. 🚫"; break;
                default: $roleMessage = "نقش شما ثبت شد."; break;
            }
            $confirmationMessage = $roleMessage . "\n\nاز الان به مدت " . FREE_TRIAL_DAYS . " روز می‌توانید رایگان از امکانات ربات استفاده کنید. امیدواریم این تجربه برایتان مفید باشد!";
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $confirmationMessage);
            $this->promptToEnterReferralCode($telegramId, $chatId);
        } else {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "شما قبلا نقش خود را انتخاب کرده‌اید.");
            $this->showMainMenu($chatId, "به منوی اصلی بازگشتید:");
        }
    }

    private function promptToEnterReferralCode(string $telegramId, int $chatId) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if ($user && empty($user['referred_by_user_id'])) {
            $text = "آیا کد معرف دارید؟ وارد کردن کد معرف به شما و دوستتان هدیه می‌دهد.";
            $keyboard = ['inline_keyboard' => [
                [['text' => "بله، کد معرف دارم", 'callback_data' => 'user_enter_referral_code_prompt'], ['text' => "خیر، ادامه می‌دهم", 'callback_data' => 'main_menu_show_direct']]
            ]];
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        } else {
            $this->showMainMenu($chatId, "به منوی اصلی خوش آمدید!");
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
            try { $decryptedRole = EncryptionHelper::decrypt($user['encrypted_role']); }
            catch (\Exception $e) { error_log("Failed to decrypt role for main menu user {$user['id']}: " . $e->getMessage()); }
        }
        $cycleInfo = null;
        if (!empty($user['encrypted_cycle_info'])) {
            try { $cycleInfo = json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true); }
            catch (\Exception $e) { error_log("Failed to decrypt cycle_info for main menu user {$user['id']}: " . $e->getMessage()); }
        }

        $menuText = $text;
        $flat_buttons_for_grouping = [];
        $hasAccess = $this->checkSubscriptionAccess($hashedTelegramId);

        if ($decryptedRole === 'menstruating') {
            if ($hasAccess && $cycleInfo && !empty($cycleInfo['period_start_dates'])) {
                $cycleService = new CycleService($cycleInfo);
                $currentDay = $cycleService->getCurrentCycleDay();
                $currentPhaseKey = $cycleService->getCurrentCyclePhase();
                $phaseTranslations = ['menstruation' => 'پریود (قاعدگی) 🩸','follicular' => 'فولیکولار (پیش از تخمک‌گذاری) 🌱','ovulation' => 'تخمک‌گذاری (احتمالی) 🥚','luteal' => 'لوتئال (پیش از پریود) 🍂','unknown' => 'نامشخص'];
                $currentPhase = $phaseTranslations[$currentPhaseKey] ?? 'نامشخص';
                if ($currentDay) {
                    $menuText .= "\n\n🗓️ *وضعیت دوره شما:*\n";
                    $menuText .= "- روز جاری دوره: " . $currentDay . "\n";
                    $menuText .= "- فاز تخمینی: " . $currentPhase . "\n";
                    $daysUntilNext = $cycleService->getDaysUntilNextPeriod();
                    if ($daysUntilNext !== null) {
                        if ($daysUntilNext > 0) $menuText .= "- حدود " . $daysUntilNext . " روز تا پریود بعدی";
                        elseif ($daysUntilNext == 0) $menuText .= "- پریود بعدی احتمالا امروز شروع می‌شود.";
                        else $menuText .= "- پریود شما " . abs($daysUntilNext) . " روز به تاخیر افتاده است.";
                    }
                }
            } elseif ($decryptedRole === 'menstruating' && $hasAccess) {
                 $menuText .= "\n\nبرای مشاهده اطلاعات دوره، ابتدا تاریخ آخرین پریود خود را از طریق دکمه زیر ثبت کنید.";
            }
            $flat_buttons_for_grouping[] = ['text' => "🩸 ثبت/ویرایش اطلاعات دوره", 'callback_data' => 'cycle_log_period_start_prompt'];
            $flat_buttons_for_grouping[] = ['text' => "📝 ثبت علائم روزانه", 'callback_data' => 'symptom_log_start:today'];
        }

        if (empty($user['partner_telegram_id_hash'])) {
            if (empty($user['invitation_token'])) {
                $flat_buttons_for_grouping[] = ['text' => "💌 دعوت از همراه", 'callback_data' => 'partner_invite'];
            } else {
                $invitationLink = "https://t.me/" . $this->getBotUsername() . "?start=invite_" . $user['invitation_token'];
                $menuText .= "\n\n⚠️ شما یک لینک دعوت فعال دارید:\n`{$invitationLink}`\nمی‌توانید آن را لغو کنید یا برای همراه خود بفرستید.";
                $flat_buttons_for_grouping[] = ['text' => "🔗 لغو دعوتنامه فعلی", 'callback_data' => 'partner_cancel_invite'];
            }
            $flat_buttons_for_grouping[] = ['text' => "🤝 پذیرش دعوتنامه (با کد)", 'callback_data' => 'partner_accept_prompt'];
        } else {
            $partnerHashedId = $user['partner_telegram_id_hash'];
            $partnerUser = $this->userModel->findUserByTelegramId($partnerHashedId);
            $partnerFirstName = "همراه شما";
            if ($partnerUser && !empty($partnerUser['encrypted_first_name'])) {
                try { $partnerFirstName = EncryptionHelper::decrypt($partnerUser['encrypted_first_name']); } catch (\Exception $e) {}
            }
            $menuText .= "\n\n💞 شما به {$partnerFirstName} متصل هستید.";
            $flat_buttons_for_grouping[] = ['text' => "💔 قطع اتصال از {$partnerFirstName}", 'callback_data' => 'partner_disconnect'];

            if ($decryptedRole === 'partner' && $partnerUser) {
                if ($hasAccess) {
                    $partnerCycleInfoData = null;
                    if (!empty($partnerUser['encrypted_cycle_info'])) {
                        try { $partnerCycleInfoData = json_decode(EncryptionHelper::decrypt($partnerUser['encrypted_cycle_info']), true); }
                        catch (\Exception $e) { error_log("Failed to decrypt partner's cycle_info for user {$user['id']}: " . $e->getMessage()); }
                    }
                    if ($partnerCycleInfoData && !empty($partnerCycleInfoData['period_start_dates'])) {
                        $partnerCycleService = new CycleService($partnerCycleInfoData);
                        $partnerCurrentDay = $partnerCycleService->getCurrentCycleDay();
                        $partnerCurrentPhaseKey = $partnerCycleService->getCurrentCyclePhase();
                        $phaseTranslations = ['menstruation' => 'پریود (قاعدگی) 🩸','follicular' => 'فولیکولار (پیش از تخمک‌گذاری) 🌱','ovulation' => 'تخمک‌گذاری (احتمالی) 🥚','luteal' => 'لوتئال (پیش از پریود) 🍂','unknown' => 'نامشخص'];
                        $partnerCurrentPhase = $phaseTranslations[$partnerCurrentPhaseKey] ?? 'نامشخص';
                        if ($partnerCurrentDay) {
                            $menuText .= "\n\n🗓️ *وضعیت دوره {$partnerFirstName}:*\n";
                            $menuText .= "- روز جاری دوره: " . $partnerCurrentDay . "\n";
                            $menuText .= "- فاز تخمینی: " . $partnerCurrentPhase . "\n";
                            $partnerDaysUntilNext = $partnerCycleService->getDaysUntilNextPeriod();
                            if ($partnerDaysUntilNext !== null) {
                                 if ($partnerDaysUntilNext > 0) $menuText .= "- حدود " . $partnerDaysUntilNext . " روز تا پریود بعدی";
                                 elseif ($partnerDaysUntilNext == 0) $menuText .= "- پریود بعدی ایشان احتمالا امروز شروع می‌شود.";
                                 else $menuText .= "- پریود ایشان " . abs($partnerDaysUntilNext) . " روز به تاخیر افتاده است.";
                            }
                        }
                    } else { $menuText .= "\n\n{$partnerFirstName} هنوز اطلاعات دوره‌ای ثبت نکرده است."; }
                } else { $menuText .= "\n\n(برای مشاهده اطلاعات دقیق دوره همراه خود، نیاز به اشتراک فعال دارید)";}
            }
        }

        $flat_buttons_for_grouping[] = ['text' => "⚙️ تنظیمات", 'callback_data' => 'settings_show'];
        $flat_buttons_for_grouping[] = ['text' => "راهنما ❓", 'callback_data' => 'show_guidance'];
        $flat_buttons_for_grouping[] = ['text' => "💬 پشتیبانی", 'callback_data' => 'support_request_start'];
        $flat_buttons_for_grouping[] = ['text' => "ℹ️ درباره ما", 'callback_data' => 'show_about_us'];
        $flat_buttons_for_grouping[] = ['text' => "📚 آموزش ها", 'callback_data' => 'user_show_tutorial_topics'];
        $flat_buttons_for_grouping[] = ['text' => "🎁 معرفی دوستان", 'callback_data' => 'user_show_referral_info'];

        if ($decryptedRole === 'menstruating' && $hasAccess) {
            $flat_buttons_for_grouping[] = ['text' => "📜 تاریخچه من", 'callback_data' => 'user_show_history_menu'];
        }

        $showSubscriptionButton = true;
        if (isset($user['subscription_status']) && $user['subscription_status'] === 'active' && !empty($user['subscription_ends_at'])) {
            try {
                $expiryDate = new \DateTime($user['subscription_ends_at']);
                if ($expiryDate > new \DateTime()) $showSubscriptionButton = false;
            } catch (\Exception $e) { /* keep true if date is invalid */ }
        }
        if ($showSubscriptionButton && isset($user['subscription_status']) && $user['subscription_status'] === 'free_trial' && !empty($user['trial_ends_at'])){
             try {
                $trialEndDate = new \DateTime($user['trial_ends_at']);
                if($trialEndDate > new \DateTime()){
                     // User is on active free trial, don't show "Buy Subscription" unless they want to override.
                     // For now, we hide it. If they want to buy during trial, they can be guided by support or future "manage subscription"
                }
            } catch (\Exception $e) {}
        }


        if ($showSubscriptionButton) {
             $flat_buttons_for_grouping[] = ['text' => "خرید اشتراک 💳", 'callback_data' => 'sub_show_plans'];
        }

        if ($this->isAdmin((string)$chatId)) {
            $flat_buttons_for_grouping[] = ['text' => "👑 پنل ادمین", 'callback_data' => 'admin_show_menu'];
        }

        $grouped_buttons = [];
        for ($i = 0; $i < count($flat_buttons_for_grouping); $i += 2) {
            $row = [$flat_buttons_for_grouping[$i]];
            if (isset($flat_buttons_for_grouping[$i+1])) {
                $row[] = $flat_buttons_for_grouping[$i+1];
            }
            $grouped_buttons[] = $row;
        }

        $keyboard = ['inline_keyboard' => $grouped_buttons];
        $this->telegramAPI->sendMessage($chatId, $menuText, $keyboard, 'Markdown');
    }

    // --- REFERRAL PROGRAM USER FLOW ---
    public function handleShowReferralProgram(string $telegramId, int $chatId, ?int $messageId = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) { $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد."); return; }

        $referralCode = $this->userModel->generateReferralCode($user['id']);
        if (!$referralCode) {
            $this->telegramAPI->sendMessage($chatId, "خطا در ایجاد کد معرف شما. لطفا با پشتیبانی تماس بگیرید.");
            return;
        }

        $referralsThisMonth = $this->userModel->countReferralsThisMonth($user['id']);
        $maxReferralsPerMonth = defined('MAX_REFERRALS_PER_MONTH') ? MAX_REFERRALS_PER_MONTH : 5;
        $bonusDays = defined('REFERRAL_BONUS_DAYS') ? REFERRAL_BONUS_DAYS : 3;

        $text = "🎁 **برنامه معرفی به دوستان** 🎁\n\n";
        $text .= "دوستان خود را به ربات «همراه من» دعوت کنید!\n";
        $text .= "با هر ثبت نام موفق از طریق کد معرف شما، هم شما و هم دوستتان (و همراهان متصلتان) **{$bonusDays} روز اشتراک رایگان** هدیه می‌گیرید.\n\n";
        $text .= "کد معرف شما: `{$referralCode}`\n";
        $text .= "(این کد را کپی کرده و برای دوستانتان ارسال کنید. آنها می‌توانند پس از شروع ربات یا از بخش معرفی دوستان، این کد را وارد کنند.)\n\n";
        $text .= "شما این ماه **{$referralsThisMonth}** نفر را با موفقیت دعوت کرده‌اید.\n";
        $text .= "می‌توانید این ماه برای **" . max(0, $maxReferralsPerMonth - $referralsThisMonth) . "** دعوت موفق دیگر هدیه دریافت کنید.\n";

        $buttons_rows = [];
        $action_buttons = [];
        if (empty($user['referred_by_user_id'])) {
            $action_buttons[] = ['text' => "کد معرف دارم", 'callback_data' => 'user_enter_referral_code_prompt'];
        }
        $action_buttons[] = ['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show'];
        $buttons_rows[] = $action_buttons; // Put these on one row if possible, or they will be single

        $keyboard = ['inline_keyboard' => $buttons_rows];

        if ($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function handleEnterReferralCodePrompt(string $telegramId, int $chatId, ?int $messageId = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if ($user && !empty($user['referred_by_user_id'])) {
            $responseText = "شما قبلا از طریق یک کد معرف ثبت نام کرده‌اید و نمی‌توانید مجددا کد وارد کنید.";
            $backButtonKeyboard = ['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'user_show_referral_info']]]];
            if ($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, $responseText, $backButtonKeyboard);
            else $this->telegramAPI->sendMessage($chatId, $responseText, $backButtonKeyboard);
            return;
        }

        $text = "لطفا کد معرف دوست خود را وارد کنید (یا /cancel برای لغو):";
        $this->userModel->updateUser($hashedTelegramId, ['user_state' => 'awaiting_referral_code']);

        if ($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, null);
        else $this->telegramAPI->sendMessage($chatId, $text, null);
    }

    public function handleProcessReferralCode(string $telegramId, int $chatId, string $code, string $firstName, ?string $username) {
        $refereeHashedId = EncryptionHelper::hashIdentifier($telegramId);
        $refereeUser = $this->userModel->findUserByTelegramId($refereeHashedId);

        if (!$refereeUser) {
             $this->telegramAPI->sendMessage($chatId, "خطا: اطلاعات شما یافت نشد. لطفا /start را بزنید.");
             $this->userModel->updateUser($refereeHashedId, ['user_state' => null]);
             return;
        }

        $this->userModel->updateUser($refereeHashedId, ['user_state' => null]);

        if (!empty($refereeUser['referred_by_user_id'])) {
            $this->telegramAPI->sendMessage($chatId, "شما قبلا از طریق یک کد معرف ثبت نام کرده‌اید.");
            $this->showMainMenu($chatId);
            return;
        }

        $code = trim($code);
        $referrerUser = $this->userModel->findUserByReferralCode($code);

        if (!$referrerUser) {
            $this->telegramAPI->sendMessage($chatId, "کد معرف وارد شده نامعتبر است. لطفا دوباره بررسی کنید یا از این مرحله صرف نظر کنید (/cancel).");
            $this->userModel->updateUser($refereeHashedId, ['user_state' => 'awaiting_referral_code']);
            return;
        }

        if ((int)$referrerUser['id'] === (int)$refereeUser['id']) {
            $this->telegramAPI->sendMessage($chatId, "شما نمی‌توانید از کد معرف خودتان استفاده کنید.");
            $this->userModel->updateUser($refereeHashedId, ['user_state' => 'awaiting_referral_code']);
            return;
        }

        $maxReferralsPerMonth = defined('MAX_REFERRALS_PER_MONTH') ? MAX_REFERRALS_PER_MONTH : 5;
        $bonusDays = defined('REFERRAL_BONUS_DAYS') ? REFERRAL_BONUS_DAYS : 3;

        $this->userModel->updateUser($refereeHashedId, ['referred_by_user_id' => $referrerUser['id']]);
        $this->userModel->applyReferralBonus($refereeUser['id'], $bonusDays);
        if ($refereeUser['partner_telegram_id_hash']) {
            $refereePartner = $this->userModel->findUserByTelegramId($refereeUser['partner_telegram_id_hash']);
            if ($refereePartner) $this->userModel->applyReferralBonus($refereePartner['id'], $bonusDays);
        }
        $this->telegramAPI->sendMessage($chatId, "✅ تبریک! {$bonusDays} روز اشتراک رایگان به شما و همراهتان (در صورت اتصال) اضافه شد.");

        $referralsThisMonth = $this->userModel->countReferralsThisMonth($referrerUser['id']);
        if ($referralsThisMonth < $maxReferralsPerMonth) {
            $this->userModel->applyReferralBonus($referrerUser['id'], $bonusDays);
            if ($referrerUser['partner_telegram_id_hash']) {
                $referrerPartner = $this->userModel->findUserByTelegramId($referrerUser['partner_telegram_id_hash']);
                if ($referrerPartner) $this->userModel->applyReferralBonus($referrerPartner['id'], $bonusDays);
            }
            if (!empty($referrerUser['encrypted_chat_id'])) {
                try {
                    $referrerChatId = EncryptionHelper::decrypt($referrerUser['encrypted_chat_id']);
                    $refereeDisplayName = $firstName . ($username ? " (@{$username})" : "");
                    $this->telegramAPI->sendMessage((int)$referrerChatId, "🎉 مژده! {$refereeDisplayName} با کد معرف شما عضو شد و {$bonusDays} روز اشتراک رایگان به شما و همراهتان (در صورت اتصال) تعلق گرفت!");
                } catch (\Exception $e) { error_log("Failed to decrypt referrer chat_id or send notification: " . $e->getMessage()); }
            }
        } else {
             if (!empty($referrerUser['encrypted_chat_id'])) {
                try {
                    $referrerChatId = EncryptionHelper::decrypt($referrerUser['encrypted_chat_id']);
                    $this->telegramAPI->sendMessage((int)$referrerChatId, "یک نفر با کد شما عضو شد، اما شما این ماه به سقف دریافت هدیه ({$maxReferralsPerMonth} دعوت) رسیده‌اید. دوست شما هدیه‌اش را دریافت کرد.");
                } catch (\Exception $e) { error_log("Failed to notify referrer about limit: " . $e->getMessage());}
             }
        }
        $this->showMainMenu($chatId);
    }
    // --- END REFERRAL PROGRAM ---

    // --- TUTORIALS / EDUCATIONAL CONTENT FOR USER ---
    public function handleShowTutorialTopics(string $telegramId, int $chatId, ?int $messageId = null) {
        error_log("handleShowTutorialTopics called by user: {$telegramId} in chat: {$chatId}");
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "بخش آموزش‌ها");
            return;
        }
        $educationalContentModel = new EducationalContentModel();
        $user = $this->userModel->findUserByTelegramId(EncryptionHelper::hashIdentifier($telegramId));
        $userRole = $user && !empty($user['encrypted_role']) ? EncryptionHelper::decrypt($user['encrypted_role']) : null;

        $targetRolesForQuery = ['both'];
        if ($userRole === 'menstruating') $targetRolesForQuery[] = 'menstruating';
        if ($userRole === 'partner') $targetRolesForQuery[] = 'partner';

        $topics = $educationalContentModel->listContent(
            ['type' => 'topic', 'is_active' => true, 'target_roles' => $targetRolesForQuery],
            ['sequence_order' => 'ASC', 'title' => 'ASC']
        );

        $text = "📚 **موضوعات آموزشی**\n\nلطفا یک موضوع را برای مطالعه انتخاب کنید:";
        $topic_buttons_flat = [];
        if (empty($topics)) {
            $text = "در حال حاضر هیچ موضوع آموزشی برای شما در دسترس نیست.";
        } else {
            foreach ($topics as $topic) {
                $topic_buttons_flat[] = ['text' => $topic['title'], 'callback_data' => 'user_show_tutorial_topic_content:' . $topic['id']];
            }
        }

        $grouped_topic_buttons = [];
        for ($i = 0; $i < count($topic_buttons_flat); $i += 2) {
            $row = [$topic_buttons_flat[$i]];
            if (isset($topic_buttons_flat[$i+1])) {
                $row[] = $topic_buttons_flat[$i+1];
            }
            $grouped_topic_buttons[] = $row;
        }
        $grouped_topic_buttons[] = [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']];

        $keyboard = ['inline_keyboard' => $grouped_topic_buttons];
        if ($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function handleShowTutorialTopicContent(string $telegramId, int $chatId, ?int $messageId, int $topicId) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "محتوای آموزشی");
            return;
        }
        $educationalContentModel = new EducationalContentModel();
        $topic = $educationalContentModel->getContentById($topicId);

        if (!$topic || $topic['type'] !== 'topic') {
            $keyboard = ['inline_keyboard' => [[['text' => "بازگشت به لیست آموزش‌ها", 'callback_data' => 'user_show_tutorial_topics']]]];
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "موضوع آموزشی انتخاب شده یافت نشد یا معتبر نیست.", $keyboard);
            return;
        }

        $user = $this->userModel->findUserByTelegramId(EncryptionHelper::hashIdentifier($telegramId));
        $userRole = $user && !empty($user['encrypted_role']) ? EncryptionHelper::decrypt($user['encrypted_role']) : null;
        $targetRolesForQuery = ['both'];
        if ($userRole === 'menstruating') $targetRolesForQuery[] = 'menstruating';
        if ($userRole === 'partner') $targetRolesForQuery[] = 'partner';

        $articles = $educationalContentModel->listContent(
            ['parent_id' => $topicId, 'type' => 'article', 'is_active' => true, 'target_roles' => $targetRolesForQuery],
            ['sequence_order' => 'ASC', 'title' => 'ASC']
        );

        $text = "📚 **{$topic['title']}**\n\nمطالب این بخش:\n\n";
        $article_buttons_flat = [];
        if (empty($articles)) {
            $text .= "متاسفانه هنوز مطلبی برای این موضوع اضافه نشده است.";
        } else {
            foreach ($articles as $article) {
                $article_buttons_flat[] = ['text' => $article['title'], 'callback_data' => 'user_show_tutorial_article:' . $article['id']];
            }
        }

        $grouped_article_buttons = [];
        for ($i = 0; $i < count($article_buttons_flat); $i += 2) {
            $row = [$article_buttons_flat[$i]];
            if (isset($article_buttons_flat[$i+1])) {
                $row[] = $article_buttons_flat[$i+1];
            }
            $grouped_article_buttons[] = $row;
        }
        $grouped_article_buttons[] = [['text' => "🔙 بازگشت به لیست موضوعات", 'callback_data' => 'user_show_tutorial_topics']];
        $grouped_article_buttons[] = [['text' => "🏠 منوی اصلی", 'callback_data' => 'main_menu_show']];

        $keyboard = ['inline_keyboard' => $grouped_article_buttons];
        if ($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function handleShowTutorialArticle(string $telegramId, int $chatId, ?int $messageId, int $articleId) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "این مطلب آموزشی");
            return;
        }
        $educationalContentModel = new EducationalContentModel();
        $article = $educationalContentModel->getContentById($articleId);

        if (!$article || $article['type'] !== 'article') {
            if($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, "مطلب آموزشی انتخاب شده یافت نشد.", null);
            else $this->telegramAPI->sendMessage($chatId, "مطلب آموزشی انتخاب شده یافت نشد.", null);
            $this->handleShowTutorialTopics($telegramId, $chatId, null);
            return;
        }

        $text = "📄 **{$article['title']}**\n\n";
        $text .= ($article['content_data'] ? EncryptionHelper::decrypt($article['content_data']) : "محتوایی برای نمایش وجود ندارد.") . "\n";
        if (!empty($article['source_url'])) $text .= "\nمنبع: {$article['source_url']}\n";

        $buttons = [];
        if (!empty($article['image_url']) && $article['content_type'] === 'text_with_image') {
            $buttons[] = [['text' => "🖼️ مشاهده تصویر", 'url' => $article['image_url']]];
        }
        if (!empty($article['video_url']) && $article['content_type'] === 'video_link') {
             $buttons[] = [['text' => "🎬 مشاهده ویدیو", 'url' => $article['video_url']]];
        }
        if (!empty($article['read_more_link'])) $buttons[] = [['text' => "مطالعه بیشتر 🔗", 'url' => $article['read_more_link']]];

        $returnCallback = $article['parent_id'] ? 'user_show_tutorial_topic_content:' . $article['parent_id'] : 'user_show_tutorial_topics';
        $buttons[] = [['text' => "🔙 بازگشت", 'callback_data' => $returnCallback]];
        $buttons[] = [['text' => "🏠 منوی اصلی", 'callback_data' => 'main_menu_show']];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) $this->telegramAPI->deleteMessage($chatId, (int)$messageId);
        $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }
    // --- END TUTORIALS ---

    public function handleShowSubscriptionPlans($telegramId, $chatId, $messageId = null) {
        error_log("handleShowSubscriptionPlans called by user: {$telegramId} in chat: {$chatId}");
        $subscriptionPlanModel = new SubscriptionPlanModel();
        $plans = $subscriptionPlanModel->getActivePlans();

        if (empty($plans)) {
            $text = "متاسفانه در حال حاضر هیچ طرح اشتراکی فعالی وجود ندارد. لطفا بعدا دوباره سر بزنید.";
            $keyboardArray = ['inline_keyboard' => [[['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]]];
            if ($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboardArray);
            else $this->telegramAPI->sendMessage($chatId, $text, $keyboardArray);
            return;
        }

        $text = "💎 طرح‌های اشتراک «همراه من»:\n\n";
        $plan_buttons_flat = [];
        foreach ($plans as $plan) {
            $priceFormatted = number_format($plan['price']);
            $buttonText = "{$plan['name']} ({$plan['duration_months']} ماهه) - {$priceFormatted} تومان";
            $plan_buttons_flat[] = ['text' => $buttonText, 'callback_data' => 'sub_select_plan:' . $plan['id']];
        }

        $grouped_plan_buttons = [];
        for ($i = 0; $i < count($plan_buttons_flat); $i += 1) {
            $grouped_plan_buttons[] = [$plan_buttons_flat[$i]];
        }
        $grouped_plan_buttons[] = [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']];

        $keyboardArray = ['inline_keyboard' => $grouped_plan_buttons];

        if ($messageId) {
            error_log("handleShowSubscriptionPlans: Editing messageId {$messageId}. Keyboard: " . json_encode($keyboardArray));
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboardArray, 'Markdown');
        } else {
            error_log("handleShowSubscriptionPlans: Sending new message. Keyboard: " . json_encode($keyboardArray));
            $this->telegramAPI->sendMessage($chatId, $text, $keyboardArray, 'Markdown');
        }
    }

    public function handleShowAboutUs($telegramId, $chatId, $messageId = null) {
        error_log("handleShowAboutUs called by user: {$telegramId} in chat: {$chatId}");
        $appSettingsModel = new AppSettingsModel();
        $aboutUsText = $appSettingsModel->getSetting('about_us_text');

        if (empty($aboutUsText)) {
            $aboutUsText = "به ربات «همراه من» خوش آمدید!\n\nما تیمی هستیم که به سلامت و آگاهی شما و همراهتان اهمیت می‌دهیم. هدف ما ارائه ابزاری کاربردی برای درک بهتر چرخه قاعدگی و تقویت روابط زوجین است.\n\nنسخه فعلی: 1.0.0 (توسعه اولیه)";
        }

        $text = "ℹ️ **درباره ما**\n\n" . $aboutUsText;
        $keyboardArray = ['inline_keyboard' => [[['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]]];

        if ($messageId) {
            error_log("handleShowAboutUs: Editing messageId {$messageId}. Keyboard: " . json_encode($keyboardArray));
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboardArray, 'Markdown');
        } else {
            error_log("handleShowAboutUs: Sending new message. Keyboard: " . json_encode($keyboardArray));
            $this->telegramAPI->sendMessage($chatId, $text, $keyboardArray, 'Markdown');
        }
    }

    public function handleShowGuidance($telegramId, int $chatId, ?int $messageId = null) {
        error_log("handleShowGuidance called by user: {$telegramId} in chat: {$chatId}. MessageId: " . ($messageId ?? 'null'));
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
        $guidanceText .= "8. **معرفی دوستان:**\n";
        $guidanceText .= "    - از منوی «🎁 معرفی دوستان» می‌توانید کد معرف خود را دریافت و برای دوستانتان ارسال کنید یا کد معرف دوستانتان را وارد کنید و هدیه بگیرید.\n\n";
        $guidanceText .= "9. **حذف حساب کاربری:**\n";
        $guidanceText .= "    - از «⚙️ تنظیمات» > «🗑 حذف حساب کاربری» می‌توانید حساب خود را به طور کامل حذف کنید. این عمل غیرقابل بازگشت است.\n\n";
        $guidanceText .= "امیدواریم این ربات برای شما مفید باشد! 😊";

        $keyboardArray = ['inline_keyboard' => [[['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]]];

        if ($messageId) {
            error_log("handleShowGuidance: Editing messageId {$messageId}. Keyboard: " . json_encode($keyboardArray));
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $guidanceText, $keyboardArray, 'Markdown');
        } else {
            error_log("handleShowGuidance: Sending new message. Keyboard: " . json_encode($keyboardArray));
            $this->telegramAPI->sendMessage($chatId, $guidanceText, $keyboardArray, 'Markdown');
        }
    }

    public function handleSupportRequestStart(string $telegramId, int $chatId, ?int $messageId = null) {
        // This method will now be handled by SupportController
        // For now, ensure this method is not directly called or update public/index.php to route to SupportController
        error_log("UserController::handleSupportRequestStart called - SHOULD BE ROUTED TO SupportController");
        $supportController = new SupportController($this->telegramAPI, new \Models\SupportTicketModel(), $this->userModel);
        $supportController->userRequestSupportStart($telegramId, $chatId, $messageId);
    }

    /**
     * Handles messages from users that are intended for the support system.
     * This method will now be handled by SupportController
     */
    public function handleUserSupportMessage($telegramUserId, int $chatId, string $messageText, string $firstName, ?string $username) {
        // This method will now be handled by SupportController
        error_log("UserController::handleUserSupportMessage called - SHOULD BE ROUTED TO SupportController");
        $supportController = new SupportController($this->telegramAPI, new \Models\SupportTicketModel(), $this->userModel);
        $supportController->handleUserMessage($telegramUserId, $chatId, $messageText, $firstName, $username);
    }

    public function handleSettings($telegramId, $chatId, $messageId = null) {
        $text = "⚙️ تنظیمات\n\nچه کاری می‌خواهید انجام دهید؟";

        $settings_buttons_flat = [
            ['text' => "⏰ تنظیم زمان اعلان‌ها", 'callback_data' => 'settings_set_notify_time_prompt'],
            ['text' => "🗑 حذف حساب کاربری", 'callback_data' => 'user_delete_account_prompt']
        ];

        $grouped_settings_buttons = [];
        for ($i = 0; $i < count($settings_buttons_flat); $i += 2) {
            $row = [$settings_buttons_flat[$i]];
            if (isset($settings_buttons_flat[$i+1])) {
                $row[] = $settings_buttons_flat[$i+1];
            }
            $grouped_settings_buttons[] = $row;
        }
        $grouped_settings_buttons[] = [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']];

        $keyboard = ['inline_keyboard' => $grouped_settings_buttons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function handleSetNotificationTimePrompt($telegramId, $chatId, $messageId) {
        $text = "⏰ در چه ساعتی از روز مایل به دریافت اعلان‌های روزانه هستید؟\n(زمان‌ها بر اساس وقت تهران هستند)";

        $timeOptions = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '17:00', '18:00', '19:00', '20:00', '21:00'];
        $time_buttons_flat = [];
        foreach ($timeOptions as $time) {
            $time_buttons_flat[] = ['text' => $time, 'callback_data' => 'settings_set_notify_time:' . $time];
        }

        $grouped_time_buttons = [];
        $buttons_per_row = 3;
        for ($i = 0; $i < count($time_buttons_flat); $i += $buttons_per_row) {
            $row = [];
            for ($j = 0; $j < $buttons_per_row && ($i + $j) < count($time_buttons_flat); $j++) {
                $row[] = $time_buttons_flat[$i + $j];
            }
            $grouped_time_buttons[] = $row;
        }
        $grouped_time_buttons[] = [['text' => "🔙 بازگشت به تنظیمات", 'callback_data' => 'settings_show']];

        $keyboard = ['inline_keyboard' => $grouped_time_buttons];
        $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard);
    }

    public function handleSetNotificationTime($telegramId, $chatId, $messageId, $time) {
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            $this->telegramAPI->sendMessage($chatId, "فرمت زمان نامعتبر است، لطفا دوباره تلاش کنید.");
            return;
        }

        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);
        $updated = $this->userModel->updateUser($hashedTelegramId, ['preferred_notification_time' => $time . ':00']);

        if ($updated) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "⏰ زمان دریافت اعلان‌های شما به {$time} تغییر یافت.");
        } else {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "مشکلی در ذخیره زمان اعلان پیش آمد. لطفا دوباره تلاش کنید.");
        }
        $this->showMainMenu($chatId, "به منوی اصلی بازگشتید.");
    }

    // ... (All other methods from handleGenerateInvitation to handleDeleteAccountConfirm remain the same as the last fully correct version) ...
    // ... including the fully restored cycle logging and symptom logging methods ...

// --------- CYCLE LOGGING METHODS START ---------
    public function handleLogPeriodStartPrompt($telegramId, $chatId, $messageId = null) {
        error_log("handleLogPeriodStartPrompt called by user: {$telegramId} in chat: {$chatId}");
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "ثبت اطلاعات دوره");
            return;
        }

        $text = "لطفا تاریخ شروع آخرین پریود خود را انتخاب کنید:";
        $today = new \DateTime();
        $yesterday = (new \DateTime())->modify('-1 day');

        $buttons = [
            [['text' => "امروز (" . $today->format('Y-m-d') . ")", 'callback_data' => 'cycle_log_date:' . $today->format('Y-m-d')]],
            [['text' => "دیروز (" . $yesterday->format('Y-m-d') . ")", 'callback_data' => 'cycle_log_date:' . $yesterday->format('Y-m-d')]],
            [['text' => "انتخاب از تقویم 🗓️", 'callback_data' => 'cycle_pick_year']],
            [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function handleCyclePickYear($telegramId, $chatId, $messageId) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "ثبت اطلاعات دوره");
            return;
        }
        $currentYear = (int)date('Y');
        $buttons = [];
        for ($i = 0; $i < 2; $i++) {
            $year = $currentYear - $i;
            $buttons[] = [['text' => (string)$year, 'callback_data' => 'cycle_select_year:' . $year]];
        }
        $buttons[] = [['text' => "🔙 بازگشت", 'callback_data' => 'cycle_log_period_start_prompt']];
        $keyboard = ['inline_keyboard' => $buttons];
        $this->telegramAPI->editMessageText($chatId, (int)$messageId, "لطفا سال را انتخاب کنید:", $keyboard);
    }

    public function handleCycleSelectYear($telegramId, $chatId, $messageId, $year) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "ثبت اطلاعات دوره");
            return;
        }
        $months = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];
        $buttons = [];
        $row = [];
        for ($i = 0; $i < 12; $i++) {
            $monthNum = $i + 1;
            $row[] = ['text' => $months[$i], 'callback_data' => "cycle_select_month:{$year}:{$monthNum}"];
            if (count($row) == 3) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) $buttons[] = $row;
        $buttons[] = [['text' => "🔙 بازگشت به انتخاب سال", 'callback_data' => 'cycle_pick_year']];
        $keyboard = ['inline_keyboard' => $buttons];
        $this->telegramAPI->editMessageText($chatId, (int)$messageId, "سال {$year} - لطفا ماه را انتخاب کنید:", $keyboard);
    }

    public function handleCycleSelectMonth($telegramId, $chatId, $messageId, $year, $month) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "ثبت اطلاعات دوره");
            return;
        }
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);

        $buttons = [];
        $row = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $row[] = ['text' => (string)$day, 'callback_data' => "cycle_select_day:{$year}:{$month}:{$day}"];
            if (count($row) == 7) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) $buttons[] = $row;
        $buttons[] = [['text' => "🔙 بازگشت به انتخاب ماه", 'callback_data' => 'cycle_select_year:' . $year]];
        $keyboard = ['inline_keyboard' => $buttons];
        $this->telegramAPI->editMessageText($chatId, (int)$messageId, "سال {$year}, ماه {$month} - لطفا روز را انتخاب کنید:", $keyboard);
    }

    public function handleCycleLogDate($telegramId, $chatId, $messageId, $year, $month = null, $day = null) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "ثبت اطلاعات دوره");
            return;
        }

        $dateString = $year;
        if ($month && $day) {
             $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        try {
            $selectedDate = new \DateTime($dateString);
            if ($selectedDate > new \DateTime()) {
                $this->telegramAPI->editMessageText($chatId, (int)$messageId, "تاریخ انتخاب شده نمی‌تواند در آینده باشد. لطفا تاریخ معتبری انتخاب کنید.", null);
                $this->handleLogPeriodStartPrompt($telegramId, $chatId, null);
                return;
            }
        } catch (\Exception $e) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "فرمت تاریخ نامعتبر است. لطفا دوباره تلاش کنید.", null);
            $this->handleLogPeriodStartPrompt($telegramId, $chatId, null);
            return;
        }

        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        $cycleInfo = $user && !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];

        if (!isset($cycleInfo['period_start_dates'])) $cycleInfo['period_start_dates'] = [];

        if (!in_array($selectedDate->format('Y-m-d'), $cycleInfo['period_start_dates'])) {
            $cycleInfo['period_start_dates'][] = $selectedDate->format('Y-m-d');
        }
        rsort($cycleInfo['period_start_dates']);
        $cycleInfo['period_start_dates'] = array_values(array_unique($cycleInfo['period_start_dates']));

        $max_history = 12;
        if (count($cycleInfo['period_start_dates']) > $max_history) {
            $cycleInfo['period_start_dates'] = array_slice($cycleInfo['period_start_dates'], 0, $max_history);
        }

        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        // Log to period_history table
        $dbUserId = $user['id'];
        $cycleLength = null;
        // Calculate cycle length based on the new period_start_dates array from $cycleInfo
        // $cycleInfo['period_start_dates'] is already sorted descending
        if (count($cycleInfo['period_start_dates']) >= 2) {
            $currentPeriodStartDate = new \DateTime($cycleInfo['period_start_dates'][0]);
            $previousPeriodStartDate = new \DateTime($cycleInfo['period_start_dates'][1]);
            $interval = $currentPeriodStartDate->diff($previousPeriodStartDate);
            $cycleLength = $interval->days;
        }
        $this->periodHistoryModel->logPeriodStart($dbUserId, $selectedDate->format('Y-m-d'), $cycleLength);
        // End logging to period_history

        $this->telegramAPI->editMessageText($chatId, (int)$messageId, "تاریخ شروع آخرین پریود شما: {$selectedDate->format('Y-m-d')} ثبت شد. ✅", null);

        if (!isset($cycleInfo['avg_period_length']) || !isset($cycleInfo['avg_cycle_length'])) {
            $this->handleAskAverageLengths($telegramId, $chatId, null);
        } else {
            $this->showMainMenu($chatId, "اطلاعات دوره شما به‌روز شد.");
        }
    }

    public function handleAskAverageLengths($telegramId, $chatId, $messageId = null) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "تکمیل اطلاعات دوره");
            return;
        }

        $period_buttons_flat = [];
        for ($i=2; $i <= 7; $i++) $period_buttons_flat[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_period:$i"];
        $period_buttons_flat[] = ['text' => "رد کردن", 'callback_data' => "cycle_skip_avg_period"];

        $grouped_period_buttons = [];
         for ($i = 0; $i < count($period_buttons_flat); $i += 3) {
            $row = [];
            for($j=0; $j<3 && ($i+$j) < count($period_buttons_flat); $j++) $row[] = $period_buttons_flat[$i+$j];
            $grouped_period_buttons[] = $row;
        }
        $grouped_period_buttons[] = [['text' => "بعدا وارد می‌کنم / فعلا کافیست", 'callback_data' => 'main_menu_show']];

        $keyboard = ['inline_keyboard' => $grouped_period_buttons];
        $messageToSend = "🩸 میانگین دوره پریود (خونریزی) شما معمولا چند روز است؟";

        if ($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, $messageToSend, $keyboard);
        else $this->telegramAPI->sendMessage($chatId, $messageToSend, $keyboard);
    }

    public function handleSetAveragePeriodLength($telegramId, $chatId, $messageId, $length) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "تکمیل اطلاعات دوره");
            return;
        }
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        $cycleInfo = $user && !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];

        $cycleInfo['avg_period_length'] = (int)$length;
        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $this->telegramAPI->editMessageText($chatId, (int)$messageId, "میانگین طول پریود شما: {$length} روز ثبت شد. ✅", null);

        $cycle_len_buttons_flat = [];
        for ($i=21; $i <= 35; $i++) $cycle_len_buttons_flat[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_cycle:$i"];
        $cycle_len_buttons_flat[] = ['text' => "رد کردن", 'callback_data' => "cycle_skip_avg_cycle"];

        $grouped_cycle_len_buttons = [];
        for ($i = 0; $i < count($cycle_len_buttons_flat); $i += 4) {
             $row = [];
            for($j=0; $j<4 && ($i+$j) < count($cycle_len_buttons_flat); $j++) $row[] = $cycle_len_buttons_flat[$i+$j];
            $grouped_cycle_len_buttons[] = $row;
        }
        $grouped_cycle_len_buttons[] = [['text' => "بعدا وارد می‌کنم / فعلا کافیست", 'callback_data' => 'main_menu_show']];

        $keyboard = ['inline_keyboard' => $grouped_cycle_len_buttons];
        $this->telegramAPI->sendMessage($chatId, "🗓️ میانگین طول کل چرخه قاعدگی شما (از اولین روز یک پریود تا اولین روز پریود بعدی) معمولا چند روز است؟", $keyboard);
    }

    public function handleSetAverageCycleLength($telegramId, $chatId, $messageId, $length) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "تکمیل اطلاعات دوره");
            return;
        }
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        $cycleInfo = $user && !empty($user['encrypted_cycle_info']) ? json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true) : [];

        $cycleInfo['avg_cycle_length'] = (int)$length;
        $this->userModel->updateUser($hashedTelegramId, ['encrypted_cycle_info' => EncryptionHelper::encrypt(json_encode($cycleInfo))]);

        $this->telegramAPI->editMessageText($chatId, (int)$messageId, "میانگین طول چرخه شما: {$length} روز ثبت شد. ✅", null);
        $this->showMainMenu($chatId, "اطلاعات دوره شما تکمیل شد!");
    }

    public function handleSkipAverageInfo($telegramId, $chatId, $messageId, $type) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "تکمیل اطلاعات دوره");
            return;
        }
        if ($type === 'period') {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "ثبت میانگین طول پریود رد شد.", null);

            $cycle_len_buttons_flat = [];
            for ($i=21; $i <= 35; $i++) $cycle_len_buttons_flat[] = ['text' => "$i روز", 'callback_data' => "cycle_set_avg_cycle:$i"];
            $cycle_len_buttons_flat[] = ['text' => "رد کردن", 'callback_data' => "cycle_skip_avg_cycle"];
            $grouped_cycle_len_buttons = [];
            for ($i = 0; $i < count($cycle_len_buttons_flat); $i += 4) {
                $row = [];
                for($j=0; $j<4 && ($i+$j) < count($cycle_len_buttons_flat); $j++) $row[] = $cycle_len_buttons_flat[$i+$j];
                $grouped_cycle_len_buttons[] = $row;
            }
            $grouped_cycle_len_buttons[] = [['text' => "بعدا وارد می‌کنم / فعلا کافیست", 'callback_data' => 'main_menu_show']];
            $keyboard = ['inline_keyboard' => $grouped_cycle_len_buttons];
            $this->telegramAPI->sendMessage($chatId, "🗓️ میانگین طول کل چرخه قاعدگی شما (از اولین روز یک پریود تا اولین روز پریود بعدی) معمولا چند روز است؟", $keyboard);

        } elseif ($type === 'cycle') {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "ثبت میانگین طول چرخه رد شد.", null);
            $this->showMainMenu($chatId, "اطلاعات دوره شما به‌روز شد.");
        }
    }
    // --------- CYCLE LOGGING METHODS END -----------

    // --------- SYMPTOM LOGGING METHODS START -----------
    public function handleLogSymptomStart($telegramId, $chatId, $messageId = null, $dateOption = 'today') {
        error_log("handleLogSymptomStart called by user: {$telegramId} in chat: {$chatId} for date: {$dateOption}");
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "ثبت علائم روزانه");
            return;
        }
        $this->loadSymptomsConfig();
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);

        $userState = $this->userModel->getUserState($hashedTelegramId);
        if ($userState && isset($userState['action']) && $userState['action'] === 'logging_symptoms' && isset($userState['data']['date']) && $userState['data']['date'] === $dateOption) {
            // Continue existing session
        } else {
            // Start new session
            $this->userModel->updateUser($hashedTelegramId, ['user_state' => json_encode([
                'action' => 'logging_symptoms',
                'data' => ['date' => $dateOption, 'symptoms' => []]
            ])]);
        }

        $text = "📝 ثبت علائم روزانه برای " . ($dateOption === 'today' ? "امروز" : $dateOption) . "\n\n";
        $text .= "لطفا یک دسته‌بندی را انتخاب کنید:";

        $category_buttons_flat = [];
        foreach ($this->symptomsConfig['categories'] as $key => $category) {
            $category_buttons_flat[] = ['text' => $category['name_fa'], 'callback_data' => "symptom_show_cat:{$dateOption}:{$key}"];
        }

        $groupedCategoryButtons = [];
        for ($i = 0; $i < count($category_buttons_flat); $i += 2) {
            $row = [$category_buttons_flat[$i]];
            if (isset($category_buttons_flat[$i+1])) {
                $row[] = $category_buttons_flat[$i+1];
            }
            $groupedCategoryButtons[] = $row;
        }

        $groupedCategoryButtons[] = [['text' => "✅ ثبت نهایی علائم", 'callback_data' => "symptom_save_final:{$dateOption}"]];
        $groupedCategoryButtons[] = [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']];
        $keyboard = ['inline_keyboard' => $groupedCategoryButtons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function handleSymptomShowCategory($telegramId, $chatId, $messageId, $dateOption, $categoryKey) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "ثبت علائم روزانه");
            return;
        }
        $this->loadSymptomsConfig();
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $userState = $this->userModel->getUserState($hashedTelegramId);
        $currentLoggedForCat = [];

        if ($userState && isset($userState['action']) && $userState['action'] === 'logging_symptoms' && isset($userState['data']['symptoms'][$categoryKey])) {
            $currentLoggedForCat = $userState['data']['symptoms'][$categoryKey];
        }

        if (!isset($this->symptomsConfig['categories'][$categoryKey])) {
            $this->telegramAPI->sendMessage($chatId, "خطا: دسته‌بندی علائم نامعتبر است.");
            $this->handleLogSymptomStart($telegramId, $chatId, null, $dateOption);
            return;
        }
        $category = $this->symptomsConfig['categories'][$categoryKey];
        $text = "علائم دسته‌بندی: *{$category['name_fa']}*\n";
        $text .= "برای " . ($dateOption === 'today' ? "امروز" : $dateOption) . ". انتخاب کنید:\n";

        $symptomButtons = [];
        $row = [];
        foreach ($category['items'] as $itemKey => $item) {
            $isChecked = in_array($itemKey, $currentLoggedForCat);
            $buttonText = ($isChecked ? "✅ " : "◻️ ") . $item['name_fa'];
            $row[] = ['text' => $buttonText, 'callback_data' => "symptom_toggle:{$dateOption}:{$categoryKey}:{$itemKey}"];
            if (count($row) == 2) {
                $symptomButtons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) $symptomButtons[] = $row;

        $symptomButtons[] = [['text' => "🔙 بازگشت به دسته‌بندی‌ها", 'callback_data' => "symptom_log_start:{$dateOption}"]];
        $keyboard = ['inline_keyboard' => $symptomButtons];
        $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard, 'Markdown');
    }

    public function handleSymptomToggle($telegramId, $chatId, $messageId, $dateOption, $categoryKey, $symptomKey) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "ثبت علائم روزانه");
            return;
        }
        $this->loadSymptomsConfig();
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $userState = $this->userModel->getUserState($hashedTelegramId);

        if (!$userState || !isset($userState['action']) || $userState['action'] !== 'logging_symptoms' || !isset($userState['data']['date']) || $userState['data']['date'] !== $dateOption) {
            $this->telegramAPI->sendMessage($chatId, "خطا در وضعیت ثبت علائم. لطفا از ابتدا شروع کنید.");
            $this->handleLogSymptomStart($telegramId, $chatId, null, $dateOption);
            return;
        }

        $tempSymptoms = $userState['data']['symptoms'] ?? [];
        if (!isset($tempSymptoms[$categoryKey])) $tempSymptoms[$categoryKey] = [];

        if (in_array($symptomKey, $tempSymptoms[$categoryKey])) {
            $tempSymptoms[$categoryKey] = array_values(array_diff($tempSymptoms[$categoryKey], [$symptomKey]));
        } else {
            $tempSymptoms[$categoryKey][] = $symptomKey;
        }
        if (empty($tempSymptoms[$categoryKey])) unset($tempSymptoms[$categoryKey]);

        $newStateData = ['action' => 'logging_symptoms', 'data' => ['date' => $dateOption, 'symptoms' => $tempSymptoms]];
        $this->userModel->updateUser($hashedTelegramId, ['user_state' => json_encode($newStateData)]);

        $this->handleSymptomShowCategory($telegramId, $chatId, $messageId, $dateOption, $categoryKey);
    }

    public function handleSymptomSaveFinal($telegramId, $chatId, $messageId, $dateOption) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "ثبت علائم روزانه");
            return;
        }
        $this->loadSymptomsConfig();
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $userState = $this->userModel->getUserState($hashedTelegramId);

        if (!$userState || !isset($userState['action']) || $userState['action'] !== 'logging_symptoms' || !isset($userState['data']['date']) || $userState['data']['date'] !== $dateOption || !isset($userState['data']['symptoms'])) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "خطا: اطلاعاتی برای ذخیره یافت نشد یا وضعیت نامعتبر است.", null);
            $this->handleLogSymptomStart($telegramId, $chatId, null, $dateOption);
            return;
        }

        $symptomsToSave = $userState['data']['symptoms'];
        $dateToLog = ($dateOption === 'today') ? date('Y-m-d') : $dateOption;
        $userDB = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if(!$userDB || !isset($userDB['id'])){
             $this->telegramAPI->editMessageText($chatId, (int)$messageId, "خطا: کاربر یافت نشد.", null);
             return;
        }
        $userIdDb = $userDB['id'];

        $symptomModel = $this->getSymptomModel();
        $symptomModel->deleteSymptomsForDate($userIdDb, $dateToLog);

        $savedCount = 0;
        if (is_array($symptomsToSave)) {
            foreach ($symptomsToSave as $categoryKey => $symptomKeysArray) {
                if (is_array($symptomKeysArray) && isset($this->symptomsConfig['categories'][$categoryKey])) {
                    foreach ($symptomKeysArray as $symptomKey) {
                         if(isset($this->symptomsConfig['categories'][$categoryKey]['items'][$symptomKey])) {
                            $categoryName = $this->symptomsConfig['categories'][$categoryKey]['name_fa'];
                            $symptomName = $this->symptomsConfig['categories'][$categoryKey]['items'][$symptomKey]['name_fa'];

                            if ($symptomModel->logSymptom(
                                $userIdDb,
                                $dateToLog,
                                EncryptionHelper::encrypt($categoryName),
                                EncryptionHelper::encrypt($symptomName)
                            )) {
                                $savedCount++;
                            }
                        }
                    }
                }
            }
        }

        $this->userModel->updateUser($hashedTelegramId, ['user_state' => null, 'last_symptom_log_date' => date('Y-m-d H:i:s')]);

        if ($savedCount > 0) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "✅ علائم شما برای {$dateToLog} با موفقیت ثبت شد.", null);
        } else {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "هیچ علامتی برای ثبت انتخاب نشده بود یا مشکلی در ثبت رخ داد.", null);
        }
        $this->showMainMenu($chatId, "به منوی اصلی بازگشتید.");
    }
    // --------- SYMPTOM LOGGING METHODS END -----------

    // --------- SUBSCRIPTION METHODS START -----------
    public function handleSubscribePlan($telegramId, $chatId, $messageId, $planId) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        $planModel = new SubscriptionPlanModel();
        $plan = $planModel->getPlanById((int)$planId);

        if (!$user || !$plan) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "خطا: کاربر یا طرح اشتراک یافت نشد.", null);
            return;
        }

        $zarinpalService = new \Services\ZarinpalService();
        $amount = (int)$plan['price'];
        $description = "خرید اشتراک {$plan['name']} برای ربات همراه من";
        $userIdForCallback = $user['id'];

        $paymentResult = $zarinpalService->requestPayment($amount, $description, $telegramId, $userIdForCallback, $plan['id']);

        if ($paymentResult && $paymentResult['success']) {
            $text = "برای تکمیل خرید اشتراک «{$plan['name']}» به مبلغ " . number_format($amount) . " تومان، لطفا از طریق لینک زیر پرداخت را انجام دهید:\n\n{$paymentResult['payment_url']}\n\nپس از پرداخت، وضعیت اشتراک شما به طور خودکار به‌روزرسانی خواهد شد.";
            $keyboard = ['inline_keyboard' => [[['text' => "💳 رفتن به صفحه پرداخت", 'url' => $paymentResult['payment_url']]], [['text' => "🔙 بازگشت", 'callback_data' => 'sub_show_plans']]]]];
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard);
        } else {
            $errorMsg = $paymentResult['error'] ?? 'خطای نامشخص در اتصال به درگاه پرداخت.';
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, "متاسفانه مشکلی در ایجاد لینک پرداخت پیش آمد: {$errorMsg} \nلطفا بعدا تلاش کنید.", null);
        }
    }
    // --------- SUBSCRIPTION METHODS END -----------

    // --------- ACCESS CONTROL START -----------
    private function checkSubscriptionAccess(string $hashedTelegramId): bool {
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) {
            error_log("checkSubscriptionAccess: User not found with hash {$hashedTelegramId}");
            return false;
        }

        if (isset($user['subscription_status']) && $user['subscription_status'] === 'active') {
            if (!empty($user['subscription_ends_at'])) {
                try {
                    $expiryDate = new \DateTime($user['subscription_ends_at']);
                    if ($expiryDate > new \DateTime()) {
                        return true;
                    }
                    error_log("checkSubscriptionAccess: User {$user['id']} has 'active' status but subscription_ends_at ({$user['subscription_ends_at']}) is past.");
                    return false;
                } catch (\Exception $e) {
                    error_log("Error checking active subscription date for user {$user['id']}: " . $e->getMessage());
                    return false;
                }
            } else {
                 return true;
            }
        }

        if (isset($user['subscription_status']) && $user['subscription_status'] === 'free_trial') {
            if (isset($user['trial_ends_at'])) {
                try {
                    $trialEndDate = new \DateTime($user['trial_ends_at']);
                    if ($trialEndDate > new \DateTime()) {
                        return true;
                    }
                    error_log("checkSubscriptionAccess: User {$user['id']} has 'free_trial' status but trial_ends_at ({$user['trial_ends_at']}) is past.");
                } catch (\Exception $e) {
                    error_log("Error checking free trial date for user {$user['id']}: " . $e->getMessage());
                    return false;
                }
            } else {
                error_log("checkSubscriptionAccess: User {$user['id']} has 'free_trial' status but no trial_ends_at date.");
            }
        }

        error_log("checkSubscriptionAccess: User {$user['id']} has no active subscription or valid trial. Status: " . ($user['subscription_status'] ?? 'N/A'));
        return false;
    }

    private function promptToSubscribe(int $chatId, ?int $messageIdToEdit = null, string $featureName = "این قابلیت") {
        $text = "⚠️ برای دسترسی به «{$featureName}» نیاز به اشتراک فعال دارید.\n\n";
        $text .= "می‌خواهید طرح‌های اشتراک را مشاهده کنید؟";
        $keyboard = ['inline_keyboard' => [
            [['text' => "💳 مشاهده طرح‌های اشتراک", 'callback_data' => 'sub_show_plans']],
            [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]
        ]];

        if ($messageIdToEdit) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageIdToEdit, $text, $keyboard);
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard);
        }
    }
    // --------- ACCESS CONTROL END -----------

    // --- DELETE ACCOUNT ---
    public function handleDeleteAccountPrompt(string $telegramId, int $chatId, ?int $messageId = null) {
        $text = "⚠️ **تایید حذف حساب کاربری** ⚠️\n\n";
        $text .= "آیا مطمئن هستید که می‌خواهید حساب کاربری خود را برای همیشه حذف کنید؟\n";
        $text .= "تمام اطلاعات شما از جمله تاریخچه دوره‌ها، علائم ثبت شده، اطلاعات اشتراک و اتصال به همراه (در صورت وجود) به طور کامل پاک خواهد شد و این عملیات **غیرقابل بازگشت** است.";

        $keyboard = ['inline_keyboard' => [
            [['text' => "✅ بله، مطمئن هستم و حذف کن", 'callback_data' => 'user_delete_account_confirm']],
            [['text' => "❌ خیر، منصرف شدم", 'callback_data' => 'settings_show']]
        ]];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        }
    }

    public function handleDeleteAccountConfirm(string $telegramId, int $chatId, ?int $messageId = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر برای حذف یافت نشد.");
            return;
        }

        $partnerHashedId = $user['partner_telegram_id_hash'] ?? null;
        $partnerChatId = null;
        $userFirstName = "همراه شما";
        try {
            if(!empty($user['encrypted_first_name'])) $userFirstName = EncryptionHelper::decrypt($user['encrypted_first_name']);
        } catch (\Exception $e) { /* ignore */ }


        if ($partnerHashedId) {
            $partnerUser = $this->userModel->findUserByTelegramId($partnerHashedId);
            if ($partnerUser && !empty($partnerUser['encrypted_chat_id'])) {
                try {
                    $partnerChatId = EncryptionHelper::decrypt($partnerUser['encrypted_chat_id']);
                } catch (\Exception $e) {
                    error_log("DeleteAccount: Failed to decrypt partner chat_id for user {$user['id']}'s partner {$partnerUser['id']}: " . $e->getMessage());
                }
            }
        }

        $deleted = $this->userModel->deleteUserAccount($user['id']);

        if ($deleted) {
            $finalMessage = "حساب کاربری شما با موفقیت حذف شد.\nامیدواریم در آینده دوباره شما را ببینیم. برای استفاده مجدد از ربات، دستور /start را ارسال کنید.";
            if ($messageId) {
                 $this->telegramAPI->editMessageText($chatId, (int)$messageId, $finalMessage, null);
            } else {
                 $this->telegramAPI->sendMessage($chatId, $finalMessage, null);
            }

            if ($partnerChatId) {
                $this->telegramAPI->sendMessage((int)$partnerChatId, "{$userFirstName} حساب کاربری خود را در ربات «همراه من» حذف کرد و اتصال شما به طور خودکار قطع شد.");
            }
        } else {
            $errorMessage = "متاسفانه در حذف حساب شما مشکلی پیش آمد. لطفا با پشتیبانی تماس بگیرید.";
            $keyboard = ['inline_keyboard' => [[['text' => "بازگشت به تنظیمات", 'callback_data' => 'settings_show']]]];
            if ($messageId) {
                $this->telegramAPI->editMessageText($chatId, (int)$messageId, $errorMessage, $keyboard);
            } else {
                $this->telegramAPI->sendMessage($chatId, $errorMessage, $keyboard);
            }
        }
    }
    // --- END DELETE ACCOUNT ---

    // --- USER HISTORY SECTION ---
    public function handleShowHistoryMenu(string $telegramId, int $chatId, ?int $messageId = null) {
        error_log("handleShowHistoryMenu called by user: {$telegramId} in chat: {$chatId}");
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        if (!$this->checkSubscriptionAccess($hashedTelegramId)) {
            $this->promptToSubscribe($chatId, $messageId, "مشاهده تاریخچه");
            return;
        }

        $text = "📜 **تاریخچه من**\n\nکدام بخش از تاریخچه خود را مایل به مشاهده هستید؟";
        $buttons = [
            [['text' => "📅 تاریخچه دوره‌های پریود", 'callback_data' => 'user_history_periods:0']], // Page 0
            [['text' => "📝 تاریخچه علائم ثبت شده", 'callback_data' => 'user_history_symptoms:0']], // Page 0
            [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']]
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        }
    }

    public function handleShowPeriodHistory(string $telegramId, int $chatId, ?int $messageId, int $page = 0) {
        error_log("handleShowPeriodHistory called by user: {$telegramId}, page: {$page}");
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        if (!$this->checkSubscriptionAccess($hashedTelegramId)) {
            $this->promptToSubscribe($chatId, $messageId, "تاریخچه دوره‌ها");
            return;
        }
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }

        $perPage = 5; // Number of entries per page
        $offset = $page * $perPage;
        $historyEntries = $this->periodHistoryModel->getPeriodHistory($user['id'], $perPage, $offset);
        $totalEntries = $this->periodHistoryModel->countPeriodHistory($user['id']);
        $totalPages = ceil($totalEntries / $perPage);

        $text = "📅 **تاریخچه دوره‌های پریود شما** (صفحه " . ($page + 1) . " از {$totalPages})\n\n";
        if (empty($historyEntries)) {
            $text .= "هنوز هیچ تاریخچه پریودی برای شما ثبت نشده است.";
        } else {
            foreach ($historyEntries as $entry) {
                $text .= "เริ่ม: " . $entry['period_start_date'];
                if ($entry['period_end_date']) {
                    $text .= " | پایان: " . $entry['period_end_date'];
                    if ($entry['period_length']) $text .= " (مدت: {$entry['period_length']} روز)";
                } else {
                    $text .= " (در جریان)";
                }
                if ($entry['cycle_length']) {
                    $text .= "\nطول دوره قبلی: {$entry['cycle_length']} روز";
                }
                $text .= "\n--------------------\n";
            }
        }

        $paginationButtons = [];
        if ($page > 0) {
            $paginationButtons[] = ['text' => '⬅️ قبلی', 'callback_data' => 'user_history_periods:' . ($page - 1)];
        }
        if (($page + 1) < $totalPages) {
            $paginationButtons[] = ['text' => '➡️ بعدی', 'callback_data' => 'user_history_periods:' . ($page + 1)];
        }

        $buttons = [];
        if (!empty($paginationButtons)) {
            $buttons[] = $paginationButtons;
        }
        $buttons[] = [['text' => "🔙 بازگشت به منوی تاریخچه", 'callback_data' => 'user_show_history_menu']];
        $buttons[] = [['text' => "🏠 منوی اصلی", 'callback_data' => 'main_menu_show']];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function handleShowSymptomHistory(string $telegramId, int $chatId, ?int $messageId, int $page = 0) {
        error_log("handleShowSymptomHistory called by user: {$telegramId}, page: {$page}");
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        if (!$this->checkSubscriptionAccess($hashedTelegramId)) {
            $this->promptToSubscribe($chatId, $messageId, "تاریخچه علائم");
            return;
        }
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }

        $symptomModel = $this->getSymptomModel();
        $perPage = 10; // Entries per page (e.g., 10 days of logs)
        $offset = $page * $perPage;

        // For simplicity, let's fetch distinct dates with logs first for pagination
        // A more advanced version might allow filtering by date range or symptom
        $loggedDates = $symptomModel->getDistinctLoggedDates($user['id'], $perPage, $offset);
        $totalLoggedDatesCount = $symptomModel->countDistinctLoggedDates($user['id']);
        $totalPages = ceil($totalLoggedDatesCount / $perPage);

        $text = "📝 **تاریخچه علائم ثبت شده شما** (صفحه " . ($page + 1) . " از {$totalPages})\n\n";

        if (empty($loggedDates)) {
            $text .= "هنوز هیچ علائمی توسط شما ثبت نشده است.";
        } else {
            foreach ($loggedDates as $logDateArray) {
                $logDate = $logDateArray['logged_date'];
                $text .= "🗓️ **تاریخ: {$logDate}**\n";
                $symptomsOnDate = $symptomModel->getSymptomsForDate($user['id'], $logDate);
                if (empty($symptomsOnDate)) {
                    $text .= "  - علامتی برای این روز ثبت نشده (این نباید اتفاق بیفتد اگر تاریخ از distinct گرفته شده).\n";
                } else {
                    $symptomsByCategory = [];
                    foreach ($symptomsOnDate as $symptom) {
                        try {
                            $cat = EncryptionHelper::decrypt($symptom['encrypted_symptom_category']);
                            $sym = EncryptionHelper::decrypt($symptom['encrypted_symptom_name']);
                            if (!isset($symptomsByCategory[$cat])) $symptomsByCategory[$cat] = [];
                            $symptomsByCategory[$cat][] = $sym;
                        } catch (\Exception $e) {
                            error_log("Error decrypting symptom history: " . $e->getMessage());
                        }
                    }
                    foreach($symptomsByCategory as $catName => $symArray) {
                        $text .= "  - *{$catName}*: " . implode(', ', $symArray) . "\n";
                    }
                }
                 $text .= "--------------------\n";
            }
        }

        $paginationButtons = [];
        if ($page > 0) {
            $paginationButtons[] = ['text' => '⬅️ قبلی', 'callback_data' => 'user_history_symptoms:' . ($page - 1)];
        }
        if (($page + 1) < $totalPages) {
            $paginationButtons[] = ['text' => '➡️ بعدی', 'callback_data' => 'user_history_symptoms:' . ($page + 1)];
        }

        $buttons = [];
        if (!empty($paginationButtons)) {
            $buttons[] = $paginationButtons;
        }
        $buttons[] = [['text' => "🔙 بازگشت به منوی تاریخچه", 'callback_data' => 'user_show_history_menu']];
        $buttons[] = [['text' => "🏠 منوی اصلی", 'callback_data' => 'main_menu_show']];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) $this->telegramAPI->editMessageText($chatId, (int)$messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }
    // --- END USER HISTORY SECTION ---
}
?>
