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
            $this->userModel->createUser($hashedTelegramId, (string)$chatId, $firstName, $username);
            $welcomeMessage = "سلام {$firstName}! 👋\nبه ربات «همراه من» خوش آمدید.\n\nاینجا فضایی امن برای شما و همراهتان است تا چرخه قاعدگی را بهتر درک کنید، توصیه‌های روزانه دریافت کنید و با هم در ارتباط باشید.\n\nبرای شروع، لطفا نقش خود را مشخص کنید:";
            $keyboard = ['inline_keyboard' => [[['text' => "🩸 من پریود می‌شوم", 'callback_data' => 'select_role:menstruating']],[['text' => "🤝 همراه هستم", 'callback_data' => 'select_role:partner']],[['text' => "🚫 ترجیح می‌دهم نگویم", 'callback_data' => 'select_role:prefer_not_to_say']]]];
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
                case 'menstruating': $roleMessage = "نقش شما به عنوان «فردی که پریود می‌شود» ثبت شد. 🩸"; break;
                case 'partner': $roleMessage = "نقش شما به عنوان «همراه» ثبت شد. 🤝"; break;
                case 'prefer_not_to_say': $roleMessage = "انتخاب شما ثبت شد. 🚫"; break;
                default: $roleMessage = "نقش شما ثبت شد."; break;
            }
            $confirmationMessage = $roleMessage . "\n\nاز الان به مدت " . FREE_TRIAL_DAYS . " روز می‌توانید رایگان از امکانات ربات استفاده کنید. امیدواریم این تجربه برایتان مفید باشد!";
            $this->telegramAPI->editMessageText($chatId, $messageId, $confirmationMessage);
            $this->promptToEnterReferralCode($telegramId, $chatId);
        } else {
            $this->telegramAPI->editMessageText($chatId, $messageId, "شما قبلا نقش خود را انتخاب کرده‌اید.");
            $this->showMainMenu($chatId, "به منوی اصلی بازگشتید:");
        }
    }

    private function promptToEnterReferralCode(string $telegramId, int $chatId) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if ($user && empty($user['referred_by_user_id'])) {
            $text = "آیا کد معرف دارید؟ وارد کردن کد معرف به شما و دوستتان هدیه می‌دهد.";
            $keyboard = ['inline_keyboard' => [
                [['text' => "بله، کد معرف دارم", 'callback_data' => 'user_enter_referral_code_prompt']],
                [['text' => "خیر، ادامه می‌دهم", 'callback_data' => 'main_menu_show_direct']]
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
            catch (\Exception $e) { error_log("Failed to decrypt role for main menu user {$hashedTelegramId}: " . $e->getMessage()); }
        }
        $cycleInfo = null;
        if (!empty($user['encrypted_cycle_info'])) {
            try { $cycleInfo = json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true); }
            catch (\Exception $e) { error_log("Failed to decrypt cycle_info for main menu user {$hashedTelegramId}: " . $e->getMessage()); }
        }

        $menuText = $text;
        $buttons = [];
        $hasAccess = $this->checkSubscriptionAccess($hashedTelegramId);

        if ($decryptedRole === 'menstruating') {
            if ($hasAccess && $cycleInfo && !empty($cycleInfo['period_start_dates'])) {
                $cycleService = new \Services\CycleService($cycleInfo);
                $currentDay = $cycleService->getCurrentCycleDay();
                $currentPhaseKey = $cycleService->getCurrentCyclePhase();
                $phaseTranslations = ['menstruation' => 'پریود (قاعدگی) 🩸','follicular' => 'فولیکولار (پیش از تخمک‌گذاری) 🌱','ovulation' => 'تخمک‌گذاری (احتمالی) 🥚','luteal' => 'لوتئال (پیش از پریود) 🍂','unknown' => 'نامشخص'];
                $currentPhase = $phaseTranslations[$currentPhaseKey] ?? 'نامشخص';
                if ($currentDay) {
                    $menuText .= "\n\n🗓️ *وضعیت دوره شما:*\n"; // Using * for bold in classic Markdown
                    $menuText .= "- روز جاری دوره: " . $currentDay . "\n";
                    $menuText .= "- فاز تخمینی: " . $currentPhase . "\n";
                    $daysUntilNext = $cycleService->getDaysUntilNextPeriod();
                    if ($daysUntilNext !== null) {
                        if ($daysUntilNext > 0) $menuText .= "- حدود " . $daysUntilNext . " روز تا پریود بعدی";
                        elseif ($daysUntilNext == 0) $menuText .= "- پریود بعدی احتمالا امروز شروع می‌شود.";
                        else $menuText .= "- پریود شما " . abs($daysUntilNext) . " روز به تاخیر افتاده است.";
                    }
                }
            } elseif ($decryptedRole === 'menstruating') {
                 $menuText .= "\n\nبرای مشاهده اطلاعات دوره، ابتدا تاریخ آخرین پریود خود را از طریق دکمه زیر ثبت کنید.";
            }
            $buttons[] = [['text' => "🩸 ثبت/ویرایش اطلاعات دوره", 'callback_data' => 'cycle_log_period_start_prompt']];
            $buttons[] = [['text' => "📝 ثبت علائم روزانه", 'callback_data' => 'symptom_log_start:today']];
        }

        if (empty($user['partner_telegram_id_hash'])) {
            if (empty($user['invitation_token'])) {
                $buttons[] = [['text' => "💌 دعوت از همراه", 'callback_data' => 'partner_invite']];
            } else {
                 // Message about active invite link is now sent only from handleGenerateInvitation.
                 // showMainMenu will just show the cancel button.
                 $buttons[] = [['text' => "🔗 لغو دعوتنامه ارسال شده", 'callback_data' => 'partner_cancel_invite']];
            }
            $buttons[] = [['text' => "🤝 پذیرش دعوتنامه (با کد)", 'callback_data' => 'partner_accept_prompt']];
        } else {
            $partnerHashedId = $user['partner_telegram_id_hash'];
            $partnerUser = $this->userModel->findUserByTelegramId($partnerHashedId);
            $partnerFirstName = "همراه شما";
            if ($partnerUser && !empty($partnerUser['encrypted_first_name'])) {
                try { $partnerFirstName = EncryptionHelper::decrypt($partnerUser['encrypted_first_name']); }
                catch (\Exception $e) { error_log("Failed to decrypt partner name for main menu: " . $e->getMessage()); }
            }
            $menuText .= "\n\n💞 شما به {$partnerFirstName} متصل هستید.";
            $buttons[] = [['text' => "💔 قطع اتصال از {$partnerFirstName}", 'callback_data' => 'partner_disconnect']];

            if ($decryptedRole === 'partner' && $partnerUser) {
                if ($hasAccess) {
                    $partnerCycleInfoData = null;
                    if (!empty($partnerUser['encrypted_cycle_info'])) {
                        try { $partnerCycleInfoData = json_decode(EncryptionHelper::decrypt($partnerUser['encrypted_cycle_info']), true); }
                        catch (\Exception $e) { error_log("Failed to decrypt partner's cycle_info: " . $e->getMessage()); }
                    }
                    if ($partnerCycleInfoData && !empty($partnerCycleInfoData['period_start_dates'])) {
                        $partnerCycleService = new \Services\CycleService($partnerCycleInfoData);
                        $partnerCurrentDay = $partnerCycleService->getCurrentCycleDay();
                        $partnerCurrentPhaseKey = $partnerCycleService->getCurrentCyclePhase();
                        $phaseTranslations = ['menstruation' => 'پریود (قاعدگی) 🩸','follicular' => 'فولیکولار (پیش از تخمک‌گذاری) 🌱','ovulation' => 'تخمک‌گذاری (احتمالی) 🥚','luteal' => 'لوتئال (پیش از پریود) 🍂','unknown' => 'نامشخص'];
                        $partnerCurrentPhase = $phaseTranslations[$partnerCurrentPhaseKey] ?? 'نامشخص';
                        if ($partnerCurrentDay) {
                            $menuText .= "\n\n🗓️ *وضعیت دوره {$partnerFirstName}:*\n"; // Using * for bold
                            $menuText .= "- روز جاری دوره: " . $partnerCurrentDay . "\n";
                            $menuText .= "- فاز تخمینی: " . $partnerCurrentPhase . "\n";
                            $partnerDaysUntilNext = $partnerCycleService->getDaysUntilNextPeriod();
                            if ($partnerDaysUntilNext !== null) {
                                 if ($partnerDaysUntilNext > 0) $menuText .= "- حدود " . $partnerDaysUntilNext . " روز تا پریود بعدی";
                                 elseif ($partnerDaysUntilNext == 0) $menuText .= "- پریود بعدی احتمالا امروز شروع می‌شود.";
                                 else $menuText .= "- پریود " . abs($partnerDaysUntilNext) . " روز به تاخیر افتاده است.";
                            }
                        }
                    } else { $menuText .= "\n\n{$partnerFirstName} هنوز اطلاعات دوره‌ای ثبت نکرده است."; }
                } else { $menuText .= "\n\n(برای مشاهده اطلاعات دوره همراه خود، نیاز به اشتراک فعال دارید)";}
            }
        }

        $buttons[] = [['text' => "⚙️ تنظیمات", 'callback_data' => 'settings_show']];
        $buttons[] = [['text' => "راهنما ❓", 'callback_data' => 'show_guidance']];
        $buttons[] = [['text' => "💬 پشتیبانی", 'callback_data' => 'support_request_start']];
        $buttons[] = [['text' => "ℹ️ درباره ما", 'callback_data' => 'show_about_us']];
        $buttons[] = [['text' => "📚 آموزش ها", 'callback_data' => 'user_show_tutorial_topics']];
        $buttons[] = [['text' => "🎁 معرفی دوستان", 'callback_data' => 'user_show_referral_info']];

        $showSubscriptionButton = true;
        if (isset($user['subscription_status']) && $user['subscription_status'] === 'active' && !empty($user['subscription_ends_at'])) {
            $expiryDate = new \DateTime($user['subscription_ends_at']);
            if ($expiryDate > new \DateTime()) $showSubscriptionButton = false;
        }
        if ($showSubscriptionButton) {
             $buttons[] = [['text' => "خرید اشتراک 💳", 'callback_data' => 'sub_show_plans']];
        }

        if ((string)$chatId === ADMIN_TELEGRAM_ID) {
            $buttons[] = [['text' => "👑 پنل ادمین", 'callback_data' => 'admin_show_menu']];
        }
        $keyboard = ['inline_keyboard' => $buttons];
        $this->telegramAPI->sendMessage($chatId, $menuText, $keyboard, 'Markdown'); // Keep as Markdown (classic)
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
        $remainingBonuses = max(0, $maxReferralsPerMonth - $referralsThisMonth);

        $text = "🎁 **برنامه معرفی به دوستان** 🎁\n\n";
        $text .= "دوستان خود را به ربات «همراه من» دعوت کنید!\n";
        $text .= "با هر ثبت نام موفق از طریق کد معرف شما، هم شما و هم دوستتان (و همراهان متصلتان) **{$bonusDays} روز اشتراک رایگان** هدیه می‌گیرید.\n\n";
        $text .= "کد معرف شما: `{$referralCode}`\n";
        $text .= "(این کد را کپی کرده و برای دوستانتان ارسال کنید. آنها می‌توانند پس از شروع ربات یا از بخش معرفی دوستان، این کد را وارد کنند.)\n\n";
        $text .= "شما این ماه **{$referralsThisMonth}** نفر را با موفقیت دعوت کرده‌اید.\n";
        $text .= "می‌توانید این ماه برای **{$remainingBonuses}** دعوت موفق دیگر هدیه دریافت کنید.\n";

        $buttons = [];
        if (empty($user['referred_by_user_id'])) {
            $buttons[] = [['text' => "کد معرف دارم", 'callback_data' => 'user_enter_referral_code_prompt']];
        }
        $buttons[] = [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function handleEnterReferralCodePrompt(string $telegramId, int $chatId, ?int $messageId = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier($telegramId);
        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);
        if ($user && !empty($user['referred_by_user_id'])) {
            $responseText = "شما قبلا از طریق یک کد معرف ثبت نام کرده‌اید و نمی‌توانید مجددا کد وارد کنید.";
            $backButtonKeyboard = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'user_show_referral_info']]]]);
            if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $responseText, $backButtonKeyboard);
            else $this->telegramAPI->sendMessage($chatId, $responseText, $backButtonKeyboard);
            return;
        }

        $text = "لطفا کد معرف دوست خود را وارد کنید (یا /cancel برای لغو):";
        $this->userModel->updateUser($hashedTelegramId, ['user_state' => 'awaiting_referral_code']);

        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, json_encode(['inline_keyboard' => []]));
        else $this->telegramAPI->sendMessage($chatId, $text, json_encode(['inline_keyboard' => []]));
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
            $this->showMainMenu($chatId);
            return;
        }

        if ((int)$referrerUser['id'] === (int)$refereeUser['id']) {
            $this->telegramAPI->sendMessage($chatId, "شما نمی‌توانید از کد معرف خودتان استفاده کنید.");
            $this->showMainMenu($chatId);
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
        if ($referralsThisMonth <= $maxReferralsPerMonth) {
            $this->userModel->applyReferralBonus($referrerUser['id'], $bonusDays);
            if ($referrerUser['partner_telegram_id_hash']) {
                $referrerPartner = $this->userModel->findUserByTelegramId($referrerUser['partner_telegram_id_hash']);
                if ($referrerPartner) $this->userModel->applyReferralBonus($referrerPartner['id'], $bonusDays);
            }
            if (!empty($referrerUser['encrypted_chat_id'])) {
                try {
                    $referrerChatId = EncryptionHelper::decrypt($referrerUser['encrypted_chat_id']);
                    $refereeDisplayName = $firstName . ($username ? " (@{$username})" : "");
                    $this->telegramAPI->sendMessage($referrerChatId, "🎉 مژده! {$refereeDisplayName} با کد معرف شما عضو شد و {$bonusDays} روز اشتراک رایگان به شما و همراهتان (در صورت اتصال) تعلق گرفت!");
                } catch (\Exception $e) { error_log("Failed to decrypt referrer chat_id or send notification: " . $e->getMessage()); }
            }
        } else {
             if (!empty($referrerUser['encrypted_chat_id'])) {
                try {
                    $referrerChatId = EncryptionHelper::decrypt($referrerUser['encrypted_chat_id']);
                    $this->telegramAPI->sendMessage($referrerChatId, "یک نفر با کد شما عضو شد، اما شما این ماه به سقف دریافت هدیه معرفی رسیده‌اید. دوست شما هدیه‌اش را دریافت کرد.");
                } catch (\Exception $e) { error_log("Failed to notify referrer about limit: " . $e->getMessage());}
             }
        }
        $this->showMainMenu($chatId);
    }
    // --- END REFERRAL PROGRAM ---

    // --- TUTORIALS / EDUCATIONAL CONTENT FOR USER ---
    public function handleShowTutorialTopics(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "بخش آموزش‌ها");
            return;
        }
        $educationalContentModel = new \Models\EducationalContentModel();
        $user = $this->userModel->findUserByTelegramId(EncryptionHelper::hashIdentifier($telegramId));
        $userRole = $user ? EncryptionHelper::decrypt($user['encrypted_role']) : null;

        $targetRolesForQuery = ['both'];
        if ($userRole === 'menstruating') $targetRolesForQuery[] = 'menstruating';
        if ($userRole === 'partner') $targetRolesForQuery[] = 'partner';

        $topics = $educationalContentModel->listContent(['is_tutorial_topic' => true, 'is_active' => true], ['sequence_order' => 'ASC', 'title' => 'ASC']);
        $accessibleTopics = [];
        foreach($topics as $topic) {
            if (in_array($topic['target_role'], $targetRolesForQuery)) $accessibleTopics[] = $topic;
        }

        $text = "📚 **موضوعات آموزشی**\n\nلطفا یک موضوع را برای مطالعه انتخاب کنید:";
        $topicButtons = [];
        if (empty($accessibleTopics)) $text = "در حال حاضر هیچ موضوع آموزشی برای شما در دسترس نیست.";
        else foreach ($accessibleTopics as $topic) $topicButtons[] = [['text' => $topic['title'], 'callback_data' => 'user_show_tutorial_topic_content:' . $topic['id']]];

        $keyboard = ['inline_keyboard' => $topicButtons];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']];
        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function handleShowTutorialTopicContent(string $telegramId, int $chatId, ?int $messageId, int $topicId) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "محتوای آموزشی");
            return;
        }
        $educationalContentModel = new \Models\EducationalContentModel();
        $topic = $educationalContentModel->getContentById($topicId);

        if (!$topic || !$topic['is_tutorial_topic']) {
            $this->telegramAPI->editMessageText($chatId, $messageId, "موضوع آموزشی انتخاب شده یافت نشد یا معتبر نیست.", json_encode(['inline_keyboard' => [[['text' => "بازگشت به لیست آموزش‌ها", 'callback_data' => 'user_show_tutorial_topics']]]]));
            return;
        }

        $user = $this->userModel->findUserByTelegramId(EncryptionHelper::hashIdentifier($telegramId));
        $userRole = $user ? EncryptionHelper::decrypt($user['encrypted_role']) : null;
        $targetRolesForQuery = ['both'];
        if ($userRole === 'menstruating') $targetRolesForQuery[] = 'menstruating';
        if ($userRole === 'partner') $targetRolesForQuery[] = 'partner';

        $articles = $educationalContentModel->getContentByParentId($topicId);
        $accessibleArticles = [];
        foreach($articles as $article) {
            if (in_array($article['target_role'], $targetRolesForQuery)) $accessibleArticles[] = $article;
        }

        $text = "📚 **{$topic['title']}**\n\nمطالب این بخش:\n\n";
        $articleButtons = [];
        if (empty($accessibleArticles)) $text .= "متاسفانه هنوز مطلبی برای این موضوع اضافه نشده است.";
        else foreach ($accessibleArticles as $article) $articleButtons[] = [['text' => $article['title'], 'callback_data' => 'user_show_tutorial_article:' . $article['id']]];

        $keyboard = ['inline_keyboard' => $articleButtons];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به لیست موضوعات", 'callback_data' => 'user_show_tutorial_topics']];
        $keyboard['inline_keyboard'][] = [['text' => "🏠 منوی اصلی", 'callback_data' => 'main_menu_show']];
        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    public function handleShowTutorialArticle(string $telegramId, int $chatId, ?int $messageId, int $articleId) {
        if (!$this->checkSubscriptionAccess(EncryptionHelper::hashIdentifier($telegramId))) {
            $this->promptToSubscribe($chatId, $messageId, "این مطلب آموزشی");
            return;
        }
        $educationalContentModel = new \Models\EducationalContentModel();
        $article = $educationalContentModel->getContentById($articleId);

        if (!$article) {
            if($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, "مطلب آموزشی انتخاب شده یافت نشد.", null);
            else $this->telegramAPI->sendMessage($chatId, "مطلب آموزشی انتخاب شده یافت نشد.", null);
            $this->handleShowTutorialTopics($telegramId, $chatId, null);
            return;
        }

        $text = "📄 **{$article['title']}**\n\n";
        $text .= $article['content_data'] . "\n";
        if (!empty($article['source_url'])) $text .= "\nمنبع: {$article['source_url']}\n";
        if (!empty($article['image_url']) && $article['content_type'] === 'text_with_image') $text .= "\nتصویر مرتبط: {$article['image_url']}\n";
        if (!empty($article['video_url']) && $article['content_type'] === 'video_link') $text .= "\nلینک ویدیو: {$article['video_url']}\n";

        $buttons = [];
        if (!empty($article['read_more_link'])) $buttons[] = [['text' => "مطالعه بیشتر 🔗", 'url' => $article['read_more_link']]];

        $returnCallback = $article['parent_id'] ? 'user_show_tutorial_topic_content:' . $article['parent_id'] : 'user_show_tutorial_topics';
        $buttons[] = [['text' => "🔙 بازگشت", 'callback_data' => $returnCallback]];
        $buttons[] = [['text' => "🏠 منوی اصلی", 'callback_data' => 'main_menu_show']];
        $keyboard = ['inline_keyboard' => $buttons];

        $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        if ($messageId) $this->telegramAPI->deleteMessage($chatId, $messageId);
    }
    // --- END TUTORIALS ---

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
            $priceFormatted = number_format($plan['price']);
            $buttonText = "{$plan['name']} ({$plan['duration_months']} ماهه) - {$priceFormatted} تومان";
            $planButtons[] = [['text' => $buttonText, 'callback_data' => 'sub_select_plan:' . $plan['id']]];
        }

        $keyboard = ['inline_keyboard' => $planButtons];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'main_menu_show']];

        if ($messageId) {
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
        // ... (rest of guidance text remains the same)
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
            $this->telegramAPI->sendMessage($chatId, $text, $emptyKeyboard);
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
            [['text' => "🗑 حذف حساب کاربری", 'callback_data' => 'user_delete_account_prompt']],
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

    public function handleGenerateInvitation($telegramId, $chatId, $messageIdToEdit = null) {
        $hashedTelegramId = EncryptionHelper::hashIdentifier((string)$telegramId);

        if (!$this->checkSubscriptionAccess($hashedTelegramId)) {
            $this->promptToSubscribe($chatId, $messageIdToEdit, "قابلیت دعوت از همراه");
            return;
        }

        $user = $this->userModel->findUserByTelegramId($hashedTelegramId);

        if (!$user) {
            $this->telegramAPI->sendMessage($chatId, "خطا: کاربر یافت نشد.");
            return;
        }
        if (!empty($user['partner_telegram_id_hash'])) {
            $message = "شما در حال حاضر یک همراه متصل دارید. برای دعوت از فرد جدید، ابتدا باید اتصال فعلی را قطع کنید.";
            if ($messageIdToEdit) $this->telegramAPI->editMessageText($chatId, $messageIdToEdit, $message, json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'main_menu_show']]]]));
            else $this->telegramAPI->sendMessage($chatId, $message, json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'main_menu_show']]]]));
            return;
        }

        $token = $this->userModel->generateInvitationToken($hashedTelegramId);

        if ($token) {
            $botUsername = $this->getBotUsername();
            $invitationLink = "https://t.me/{$botUsername}?start=invite_{$token}";
            $message = "لینک دعوت برای همراه شما ایجاد شد:\n\n{$invitationLink}\n\nاین لینک را کپی کرده و برای فرد مورد نظر ارسال کنید. این لینک یکبار مصرف است و پس از استفاده یا ساخت لینک جدید، باطل می‌شود.\n\nهمچنین همراه شما می‌تواند کد زیر را مستقیما در ربات وارد کند (از طریق دکمه پذیرش دعوتنامه):\n{$token}";
            $this->telegramAPI->sendMessage($chatId, $message, null, 'Markdown');
            // Send a new main menu message instead of trying to edit, because the previous message might have been the main menu itself.
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
                if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, json_encode(['inline_keyboard'=>[]])); // Remove buttons
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
        $accepterUser = $this->userModel->findUserByTelegramId($accepterHashedId);

        if ($accepterUser) {
            if (!$this->checkSubscriptionAccess($accepterHashedId)) {
                $this->promptToSubscribe($chatId, null, "قابلیت اتصال به همراه");
                return;
            }
        } else {
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
    // ... (Cycle logging methods are assumed to be complete and correct from previous versions) ...
    public function handleLogPeriodStartPrompt($telegramId, $chatId, $messageId = null) { /* ... */ }
    public function handleCyclePickYear($telegramId, $chatId, $messageId) { /* ... */ }
    public function handleCycleSelectYear($telegramId, $chatId, $messageId, $year) { /* ... */ }
    public function handleCycleSelectMonth($telegramId, $chatId, $messageId, $year, $month) { /* ... */ }
    public function handleCycleLogDate($telegramId, $chatId, $messageId, $year, $month = null, $day = null) { /* ... */ }
    public function handleAskAverageLengths($telegramId, $chatId, $messageId = null) { /* ... */ }
    public function handleSetAveragePeriodLength($telegramId, $chatId, $messageId, $length) { /* ... */ }
    public function handleSetAverageCycleLength($telegramId, $chatId, $messageId, $length) { /* ... */ }
    public function handleSkipAverageInfo($telegramId, $chatId, $messageId, $type) { /* ... */ }
    // --------- CYCLE LOGGING METHODS END -----------

    // --------- SYMPTOM LOGGING METHODS START -----------
    private $symptomsConfig;
    private $symptomModel;
    private function loadSymptomsConfig() { if ($this->symptomsConfig === null) $this->symptomsConfig = require BASE_PATH . '/config/symptoms_config.php'; }
    private function getSymptomModel(): \Models\SymptomModel { if ($this->symptomModel === null) $this->symptomModel = new \Models\SymptomModel(); return $this->symptomModel; }
    public function getUserModel(): \Models\UserModel { return $this->userModel; }
    public function handleLogSymptomStart($telegramId, $chatId, $messageId = null, $dateOption = 'today') { /* ... */ }
    public function handleSymptomShowCategory($telegramId, $chatId, $messageId, $dateOption, $categoryKey) { /* ... */ }
    public function handleSymptomToggle($telegramId, $chatId, $messageId, $dateOption, $categoryKey, $symptomKey) { /* ... */ }
    public function handleSymptomSaveFinal($telegramId, $chatId, $messageId, $dateOption) { /* ... */ }
    // --------- SYMPTOM LOGGING METHODS END -----------

    // --------- SUBSCRIPTION METHODS START -----------
    public function handleSubscribePlan($telegramId, $chatId, $messageId, $planId) { /* ... */ }
    // --------- SUBSCRIPTION METHODS END -----------

    // --------- ACCESS CONTROL START -----------
    private function checkSubscriptionAccess(string $hashedTelegramId): bool { /* ... */ }
    private function promptToSubscribe(int $chatId, ?int $messageIdToEdit = null, string $featureName = "این قابلیت") { /* ... */ }
    // --------- ACCESS CONTROL END -----------

    // --- DELETE ACCOUNT ---
    public function handleDeleteAccountPrompt(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin((string)$telegramId) && !$messageId) { // Admin check is not relevant here; it's a user action
             // If messageId is null, it means it was called directly, not from a button.
             // Send a new message for the prompt.
        }

        $text = "⚠️ **تایید حذف حساب کاربری** ⚠️\n\n";
        $text .= "آیا مطمئن هستید که می‌خواهید حساب کاربری خود را برای همیشه حذف کنید؟\n";
        $text .= "تمام اطلاعات شما از جمله تاریخچه دوره‌ها، علائم ثبت شده، اطلاعات اشتراک و اتصال به همراه (در صورت وجود) به طور کامل پاک خواهد شد و این عملیات **غیرقابل بازگشت** است.";

        $keyboard = ['inline_keyboard' => [
            [['text' => "✅ بله، مطمئن هستم و حذف کن", 'callback_data' => 'user_delete_account_confirm']],
            [['text' => "❌ خیر، منصرف شدم", 'callback_data' => 'settings_show']]
        ]];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
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
                 $this->telegramAPI->editMessageText($chatId, $messageId, $finalMessage, json_encode(['inline_keyboard' => []])); // Remove buttons
            } else {
                 $this->telegramAPI->sendMessage($chatId, $finalMessage, json_encode(['inline_keyboard' => []]));
            }


            if ($partnerChatId) {
                $this->telegramAPI->sendMessage($partnerChatId, "همراه شما حساب کاربری خود را در ربات «همراه من» حذف کرد و اتصال شما به طور خودکار قطع شد.");
            }
        } else {
            $errorMessage = "متاسفانه در حذف حساب شما مشکلی پیش آمد. لطفا با پشتیبانی تماس بگیرید.";
            if ($messageId) {
                $this->telegramAPI->editMessageText($chatId, $messageId, $errorMessage, json_encode(['inline_keyboard' => [[['text' => "بازگشت به تنظیمات", 'callback_data' => 'settings_show']]]]));
            } else {
                $this->telegramAPI->sendMessage($chatId, $errorMessage, json_encode(['inline_keyboard' => [[['text' => "بازگشت به تنظیمات", 'callback_data' => 'settings_show']]]]));
            }
        }
    }
    // --- END DELETE ACCOUNT ---
}
?>
