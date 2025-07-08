<?php

namespace Services;

use Models\UserModel;
use Models\EducationalContentModel;
use Models\SymptomModel; // Needed for fetching logged symptoms
use Telegram\TelegramAPI;
use Helpers\EncryptionHelper;
// CycleService will be used extensively here

class NotificationService {
    private $userModel;
    private $telegramAPI;
    private $educationalContentModel;
    private $symptomModel;
    private $symptomsConfig;


    public function __construct(UserModel $userModel, TelegramAPI $telegramAPI, EducationalContentModel $educationalContentModel, SymptomModel $symptomModel) {
        $this->userModel = $userModel;
        $this->telegramAPI = $telegramAPI;
        $this->educationalContentModel = $educationalContentModel;
        $this->symptomModel = $symptomModel;
        $this->symptomsConfig = require BASE_PATH . '/config/symptoms_config.php';
    }

    /**
     * Processes and sends relevant notifications for a single user.
     * This method will be called by the cron job for each active user.
     *
     * @param array $user User data array from the database.
     * @param array|null $partnerUser Partner's user data array, if linked.
     * @return void
     */
    public function processUserNotifications(array $user, ?array $partnerUser = null): void {
        // Ensure chat_id is available - this is a critical piece.
        // We need to store the actual Telegram chat_id for sending messages.
        // Assuming $user['telegram_id'] is the chat_id for now.
        // This should be clarified: telegram_id_hash is for DB lookup,
        // but we need the original telegram_id (chat_id) to send messages.
        // Let's assume we add an 'encrypted_telegram_chat_id' to the users table
        // or that the 'telegram_id' used to create 'telegram_id_hash' IS the chat_id.
        // For now, this is a placeholder for where chat_id would come from.

        // For this core step, we won't implement specific notifications yet,
        // but set up the structure. Specific notifications are in the next step.

        // Example: Get user's decrypted role and cycle info
        $decryptedRole = null;
        if (!empty($user['encrypted_role'])) {
            try {
                $decryptedRole = EncryptionHelper::decrypt($user['encrypted_role']);
            } catch (\Exception $e) {
                error_log("Notify: Failed to decrypt role for user_id {$user['id']}: " . $e->getMessage());
                return; // Cannot proceed without role
            }
        }

        $userCycleInfo = null;
        if (!empty($user['encrypted_cycle_info'])) {
            try {
                $userCycleInfo = json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true);
            } catch (\Exception $e) {
                error_log("Notify: Failed to decrypt cycle_info for user_id {$user['id']}: " . $e->getMessage());
                // Continue, maybe some notifications don't need cycle_info
            }
        }

        // Placeholder for user's actual chat ID. This is a critical field to add to the users table.
        // For now, we assume $user['id'] could be used if we had a mapping, or we need a new field.
        // Let's assume there's a field $user['telegram_chat_id_plain'] for demonstration.
        // In reality, this would be an encrypted field that's decrypted here, or the original tg id.
        // THIS NEEDS TO BE ADDRESSED PROPERLY IN USER MODEL/TABLE by storing chat_id.
        // For the purpose of this step, we'll simulate it.
        // $userChatId = $this->getChatIdForUser($user['id']); // This method would need to exist

        // --- LOGIC FOR SPECIFIC NOTIFICATIONS WILL GO HERE IN THE NEXT STEP ---
        // e.g. checkPrePMS, checkPeriodStart, checkOvulation, sendDailyTip etc.

        // For now, a debug message if run:
        // error_log("Processing notifications for user ID: {$user['id']}, Role: {$decryptedRole}");

        // If user is partnered, also consider partner notifications
        if ($partnerUser) {
            // $partnerChatId = $this->getChatIdForUser($partnerUser['id']);
            // error_log("User {$user['id']} is partnered with {$partnerUser['id']}. Partner role: (get partner role)");
            // Send partner-specific notifications
        }
    }

    // This is a placeholder. A real implementation needs to securely store and retrieve chat_id.
    // For example, by adding an `encrypted_chat_id` field to the `users` table.
    private function getChatIdForUserFromHashedId(string $hashedTelegramId): ?string {
        // This is problematic. Hashing is one-way.
        // We MUST store the chat_id (the actual Telegram ID for messaging) separately,
        // possibly encrypted, when the user first interacts or registers.
        // For now, we'll assume the cron job fetches users with their actual chat_ids.
        // This function is a conceptual placeholder for what the cron job needs to provide.
        return null;
    }

    // More methods for specific notification types will be added:
    // e.g., sendPrePMSWarning(), sendPeriodStartedAlert(), sendDailyInsight()

    // --- Specific Notification Logic ---

    private function checkAndSendPrePMSNotification(array $user, ?array $partnerUser, \Services\CycleService $cycleService): void {
        $daysUntilNext = $cycleService->getDaysUntilNextPeriod();
        $userChatId = $user['decrypted_chat_id'];

        // Pre-PMS: 3-5 days before period
        if ($daysUntilNext !== null && $daysUntilNext >= 3 && $daysUntilNext <= 5) {
            // Check if this notification was already sent for this cycle (to prevent daily Pre-PMS messages for 3 days straight)
            // This requires a mechanism to track sent notifications, e.g., a `sent_notifications` table or a flag in user's JSON data.
            // For now, we'll keep it simple and send if conditions match. A robust solution needs tracking.
            // if ($this->hasNotificationBeenSentRecently($user['id'], 'pre_pms', $cycleService->getEstimatedNextPeriodStartDate()->format('Y-m-d'))) return;


            $messageToUser = "💡 روزهای حساس نزدیک است (حدود {$daysUntilNext} روز دیگر تا شروع پریود).\nممکن است تغییرات خلقی یا جسمی را تجربه کنی. صبور باش و به خودت اهمیت بده. 🧘‍♀️\nنکات: استراحت کافی، تغذیه مناسب، و فعالیت بدنی سبک.";
            $this->telegramAPI->sendMessage($userChatId, $messageToUser);

            if ($partnerUser && !empty($partnerUser['decrypted_chat_id'])) {
                $partnerChatId = $partnerUser['decrypted_chat_id'];
                $partnerMessage = "💡 همراه شما حدود {$daysUntilNext} روز دیگر پریود خواهد شد.\nاین روزها ممکن است حساس‌تر باشد. حمایت و درک شما بسیار ارزشمند است. ❤️\nمی‌توانید با پیشنهاد کمک در کارها یا فراهم کردن فضایی آرام، به او کمک کنید.";
                $this->telegramAPI->sendMessage($partnerChatId, $partnerMessage);
            }
            // $this->markNotificationAsSent($user['id'], 'pre_pms', $cycleService->getEstimatedNextPeriodStartDate()->format('Y-m-d'));
        }
    }

    private function checkAndSendPeriodStartNotification(array $user, ?array $partnerUser, \Services\CycleService $cycleService): void {
        $userChatId = $user['decrypted_chat_id'];
        $currentCycleDay = $cycleService->getCurrentCycleDay();

        // Assuming period_start_dates in cycleInfo is sorted, most recent first.
        $lastLoggedPeriodDate = $user['cycleInfo']['period_start_dates'][0] ?? null;
        $today = date('Y-m-d');

        // Notification if period was logged today OR if today is estimated start day (day 1 of cycle)
        $sendNotification = false;
        $reason = "";

        if ($lastLoggedPeriodDate === $today) {
            $sendNotification = true;
            $reason = "logged_today";
        } elseif ($currentCycleDay === 1 && $cycleService->getCurrentCyclePhase() === 'menstruation') {
            // Estimated start, and not yet logged for today (or previous days of this cycle)
            // This logic needs to be careful not to spam if user is just late logging.
            // We might only send this if no period has been logged for say, the last (avgCycleLength - 3) days.
            // For now, simpler: if it's estimated day 1 and no log for today exists.
             if ($lastLoggedPeriodDate !== $today) { // Avoid if already logged today
                $sendNotification = true;
                $reason = "estimated_start";
             }
        }

        // if ($this->hasNotificationBeenSentRecently($user['id'], 'period_start', $today)) return;

        if ($sendNotification) {
            $messageToUser = "";
            if ($reason === "logged_today") {
                $messageToUser = "🩸 پریود شما امروز شروع شد (ثبت شد).\nبه بدنت گوش بده و مراقبت‌های لازم را انجام بده. کیسه آب گرم، دمنوش‌های آرامبخش و استراحت فراموش نشود. ✨";
            } else { // estimated_start
                $messageToUser = "🩸 به نظر می‌رسد امروز روز اول پریود شما باشد.\nاگر شروع شده، لطفا آن را در ربات ثبت کن. مراقب خودت باش! ☕";
            }
            $this->telegramAPI->sendMessage($userChatId, $messageToUser);

            if ($partnerUser && !empty($partnerUser['decrypted_chat_id'])) {
                $partnerChatId = $partnerUser['decrypted_chat_id'];
                $partnerMessage = "🩸 پریود همراه شما امروز شروع شده است.\nحمایت شما در این دوران بسیار مهم است. صبور باشید و نیازهای او را در اولویت قرار دهید. 🫂";
                $this->telegramAPI->sendMessage($partnerChatId, $partnerMessage);
            }
            // $this->markNotificationAsSent($user['id'], 'period_start', $today);
        }
    }

    private function checkAndSendPeriodEndNotification(array $user, ?array $partnerUser, \Services\CycleService $cycleService): void {
        $userChatId = $user['decrypted_chat_id'];
        $currentCycleDay = $cycleService->getCurrentCycleDay();
        $avgPeriodLength = $cycleService->getAveragePeriodLength(); // Uses CycleService's internal logic for default/user value

        // Send if current cycle day is one day after average period length
        if ($currentCycleDay === ($avgPeriodLength + 1)) {
            // if ($this->hasNotificationBeenSentRecently($user['id'], 'period_end', date('Y-m-d'))) return;

            $messageToUser = "🎉 به نظر می‌رسد دوره پریود شما به پایان رسیده باشد.\nیک خلاصه کوچک از این دوره (به زودی!). انرژی‌تان را بازیابی کنید و برای یک چرخه جدید آماده شوید! ☀️";
            // TODO: Add actual cycle summary if possible (e.g. length of this period, common symptoms logged)
            $this->telegramAPI->sendMessage($userChatId, $messageToUser);

            if ($partnerUser && !empty($partnerUser['decrypted_chat_id'])) {
                $partnerChatId = $partnerUser['decrypted_chat_id'];
                $partnerMessage = "🎉 دوره پریود همراه شما احتمالا به پایان رسیده است.\nزمان خوبی برای برنامه‌ریزی فعالیت‌های مشترک و لذت بردن از زمان با هم است. 😊";
                $this->telegramAPI->sendMessage($partnerChatId, $partnerMessage);
            }
            // $this->markNotificationAsSent($user['id'], 'period_end', date('Y-m-d'));
        }
    }

    private function checkAndSendOvulationNotification(array $user, ?array $partnerUser, \Services\CycleService $cycleService): void {
        // TODO: Add a user setting to toggle this notification on/off.
        // For now, assume it's on if we can estimate.
        $userChatId = $user['decrypted_chat_id'];
        $ovulationWindow = $cycleService->getEstimatedOvulationWindow();
        $today = date('Y-m-d');

        if ($ovulationWindow && $ovulationWindow['ovulation_date_estimated']->format('Y-m-d') === $today) {
            // if ($this->hasNotificationBeenSentRecently($user['id'], 'ovulation_day', $today)) return;

            $messageToUser = "🥚 امروز احتمالا روز تخمک‌گذاری شماست.\nاین زمان اوج باروری در چرخه شماست. اگر قصد بارداری دارید، الان بهترین زمان است.  Fertility awareness is key! ✨";
            $this->telegramAPI->sendMessage($userChatId, $messageToUser);

            if ($partnerUser && !empty($partnerUser['decrypted_chat_id'])) {
                $partnerChatId = $partnerUser['decrypted_chat_id'];
                $partnerMessage = "🥚 امروز احتمالا روز تخمک‌گذاری همراه شماست.\nاگر قصد بارداری دارید، این اطلاعات می‌تواند برایتان مفید باشد. 💑";
                $this->telegramAPI->sendMessage($partnerChatId, $partnerMessage);
            }
            // $this->markNotificationAsSent($user['id'], 'ovulation_day', $today);
        }
    }

    private function sendDailyMessage(array $user, ?array $partnerUser, \Services\CycleService $cycleService, string $userRole): void {
        // $user is the recipient of the message.
        // $partnerUser is the menstruating partner if $userRole is 'partner'.
        // $cycleService is for the menstruating user's cycle.

        $recipientChatId = $user['decrypted_chat_id'];
        $currentPhase = $cycleService->getCurrentCyclePhase();
        $today = date('Y-m-d');

        $criteria = [
            'cycle_phase' => $currentPhase,
            'target_roles' => []
        ];

        if ($userRole === 'menstruating') {
            $criteria['target_roles'] = ['menstruating', 'both'];
            // Fetch logged symptoms for the menstruating user for today
            $rawLoggedSymptoms = $this->symptomModel->getLoggedSymptomsForDate($user['id'], $today);
            $loggedSymptomKeys = [];
            foreach ($rawLoggedSymptoms as $s) {
                $loggedSymptomKeys[] = $s['category_key'] . '_' . $s['symptom_key'];
            }
            if (!empty($loggedSymptomKeys)) {
                $criteria['active_symptom_keys'] = $loggedSymptomKeys;
            }
        } elseif ($userRole === 'partner') {
            $criteria['target_roles'] = ['partner', 'both'];
            // If partner is receiving tip about menstruating user, symptoms of menstruating user are relevant
            if ($partnerUser) { // $partnerUser here is the menstruating user
                 $rawLoggedSymptoms = $this->symptomModel->getLoggedSymptomsForDate($partnerUser['id'], $today);
                 $loggedSymptomKeys = [];
                 foreach ($rawLoggedSymptoms as $s) {
                    $loggedSymptomKeys[] = $s['category_key'] . '_' . $s['symptom_key'];
                 }
                 if (!empty($loggedSymptomKeys)) {
                    $criteria['active_symptom_keys'] = $loggedSymptomKeys;
                }
            }
        }

        if (empty($criteria['target_roles'])) return;

        // TODO: Implement idempotency for daily tips (e.g., don't send same tip ID within X days)
        // if ($this->hasDailyTipBeenSentRecently($user['id'], $currentPhase, $today)) return;

        $contentResults = $this->educationalContentModel->getContentForNotifications($criteria, 1);
        $messageText = "";
        $keyboard = null;

        if (!empty($contentResults)) {
            $contentToSend = $contentResults[0];
            $messagePrefix = ($userRole === 'partner') ? "☀️ نکته روز برای همراهی بهتر با یار شما:\n\n" : "☀️ پیام و نکته روز برای شما:\n\n";
            $messageText = $messagePrefix;

            if (!empty($contentToSend['title'])) {
                 $messageText .= "*" . $contentToSend['title'] . "*\n";
            }
            $messageText .= $contentToSend['content_data']; // Already decrypted by model

            $inlineKeyboard = [];
            if (!empty($contentToSend['read_more_link'])) {
                $inlineKeyboard[] = [['text' => " مطالعه بیشتر 🔗", 'url' => $contentToSend['read_more_link']]];
            }
            if (count($inlineKeyboard) > 0) {
                $keyboard = ['inline_keyboard' => $inlineKeyboard];
            }

            if (!empty($contentToSend['image_url']) && $contentToSend['content_type'] === 'text_with_image') {
                 // For simplicity, append as text. sendPhoto is a separate consideration.
                 $messageText .= "\n\nتصویر: " . $contentToSend['image_url'];
            }
        } else {
            // Fallback generic message
            if ($userRole === 'menstruating') {
                $messageText = "☀️ امیدواریم روز خوبی داشته باشید! به زودی نکات و اطلاعات بیشتری برای شما خواهیم داشت.";
            } elseif ($userRole === 'partner') {
                 $messageText = "☀️ به یاد داشته باشید که حمایت شما از همراهتان ارزشمند است. به زودی نکات بیشتری برایتان ارسال می‌شود.";
            }
        }

        if (!empty($messageText)) {
            $this->telegramAPI->sendMessage($recipientChatId, $messageText, $keyboard, 'Markdown');
            // TODO: Mark this tip type/ID as sent for today/this cycle for this user
        }
        }
    }

    /**
     * Processes and sends relevant notifications for a single user.
     * This method will be called by the cron job for each active user.
     */
    public function processUserNotifications(array $user, ?array $partnerUser = null): void {
        if (empty($user['decrypted_chat_id'])) {
            error_log("Notify: User ID {$user['id']} has no decrypted_chat_id. Skipping.");
            return;
        }

        $decryptedRole = null;
        if (!empty($user['encrypted_role'])) {
            try {
                $decryptedRole = EncryptionHelper::decrypt($user['encrypted_role']);
            } catch (\Exception $e) {
                error_log("Notify: Failed to decrypt role for user_id {$user['id']}: " . $e->getMessage());
                return;
            }
        } else { return; /* No role, no notifications */ }

        $userCycleInfoData = null;
        if (!empty($user['encrypted_cycle_info'])) {
            try {
                $userCycleInfoData = json_decode(EncryptionHelper::decrypt($user['encrypted_cycle_info']), true);
            } catch (\Exception $e) {
                error_log("Notify: Failed to decrypt cycle_info for user_id {$user['id']}: " . $e->getMessage());
            }
        }

        // Determine whose cycle info to use for calculations
        $cycleTrackingUser = null; // The user whose cycle is being tracked
        $cycleTrackingUserData = null;
        $observingPartnerUser = null; // The user who is the partner (observing)

        if ($decryptedRole === 'menstruating') {
            $cycleTrackingUser = $user;
            $cycleTrackingUserData = $userCycleInfoData;
            $observingPartnerUser = $partnerUser; // This is the actual partner of $user
        } elseif ($decryptedRole === 'partner' && $partnerUser) {
            // Here, $user is the partner, and $partnerUser is the menstruating user whose cycle is tracked.
            $cycleTrackingUser = $partnerUser;
            if (!empty($partnerUser['encrypted_cycle_info'])) {
                try {
                    $cycleTrackingUserData = json_decode(EncryptionHelper::decrypt($partnerUser['encrypted_cycle_info']), true);
                } catch (\Exception $e) { /* log */ }
            }
            $observingPartnerUser = $user; // $user (the one with 'partner' role) is observing
        }

        if (!$cycleTrackingUser || !$cycleTrackingUserData || empty($cycleTrackingUserData['period_start_dates'])) {
            // Not enough data for cycle-dependent notifications for this user or their partner
            // Maybe send a generic daily tip if they are a partner but their partner has no data?
            // error_log("Notify: User ID {$user['id']} or their partner has insufficient cycle data. Skipping most notifications.");
            // We can still send a generic daily message if they are a partner.
            if ($decryptedRole === 'partner' && $observingPartnerUser && !$cycleTrackingUserData) {
                 // $this->sendGenericPartnerTip($observingPartnerUser); // TODO
            }
            return;
        }

        $cycleService = new \Services\CycleService($cycleTrackingUserData);

        // --- Call specific notification checks ---
        // Pass $cycleTrackingUser for whom the notification is primarily about,
        // and $observingPartnerUser for the linked partner (can be null).

        // For menstruating user
        if ($cycleTrackingUser['id'] == $user['id']) { // Current user IS the one menstruating
            $this->checkAndSendPrePMSNotification($cycleTrackingUser, $observingPartnerUser, $cycleService);
            $this->checkAndSendPeriodStartNotification($cycleTrackingUser, $observingPartnerUser, $cycleService);
            $this->checkAndSendPeriodEndNotification($cycleTrackingUser, $observingPartnerUser, $cycleService);
            $this->checkAndSendOvulationNotification($cycleTrackingUser, $observingPartnerUser, $cycleService);
            $this->sendDailyMessage($cycleTrackingUser, $observingPartnerUser, $cycleService, 'menstruating');
        }
        // For the partner of a menstruating user (observingPartnerUser)
        // The daily message for the partner is handled within sendDailyMessage if observingPartnerUser is not null.
        // Specific event notifications (PrePMS, PeriodStart, Ovulation) already message both.
        // So, if $user['id'] is the partner's ID, their specific notifications were already triggered when
        // their menstruating partner was processed, OR they get them via the $observingPartnerUser parameter.
        // The key is that `processUserNotifications` is called for *every* user.
        // If $user is a 'partner', $cycleTrackingUser is their menstruating partner.
        // The daily message for $user (the partner) should be triggered here.
        elseif ($observingPartnerUser && $observingPartnerUser['id'] == $user['id'] && $decryptedRole === 'partner') {
             $this->sendDailyMessage($cycleTrackingUser, $observingPartnerUser, $cycleService, 'partner'); // $cycleTrackingUser is the one with cycle, $observingPartnerUser is the partner receiving msg
        }

        // --- Reminders (for the $user object passed to processUserNotifications, if they are 'menstruating') ---
        if ($user['id'] == $cycleTrackingUser['id'] && $decryptedRole === 'menstruating') { // Ensure $user is the menstruating user
            $this->checkAndSendPeriodLoggingReminder($user, $cycleService);
            $this->checkAndSendSymptomLoggingReminder($user, $cycleService);
        }

    }

    private function checkAndSendPeriodLoggingReminder(array $menstruatingUser, \Services\CycleService $cycleService): void {
        $daysUntilNext = $cycleService->getDaysUntilNextPeriod(); // This will be negative if overdue
        $userChatId = $menstruatingUser['decrypted_chat_id'];

        // If period is overdue by 2-3 days and not logged recently for this overdue period
        if ($daysUntilNext !== null && $daysUntilNext <= -2 && $daysUntilNext >= -3) {
            // More precise check: Has a period been logged *after* the estimated start of this overdue cycle?
            $estimatedCurrentOverdueStartDate = $cycleService->getEstimatedNextPeriodStartDate(); // This will be the one in the past
            $lastLoggedPeriodDateStr = $menstruatingUser['cycleInfo']['period_start_dates'][0] ?? null;

            if ($lastLoggedPeriodDateStr) {
                $lastLoggedPeriodDate = new \DateTime($lastLoggedPeriodDateStr);
                if ($lastLoggedPeriodDate >= $estimatedCurrentOverdueStartDate) {
                    return; // Period already logged for this overdue cycle or later
                }
            }

            // TODO: Idempotency check for this reminder for this specific overdue cycle.
            // if ($this->hasNotificationBeenSentRecently($menstruatingUser['id'], 'period_log_reminder', $estimatedCurrentOverdueStartDate->format('Y-m-d'))) return;

            $message = "🕰️ به نظر می‌رسد پریود شما چند روزی به تاخیر افتاده است. اگر پریود شده‌اید، لطفا تاریخ شروع آن را در ربات ثبت کنید تا پیش‌بینی‌ها دقیق‌تر شوند.";
            $this->telegramAPI->sendMessage($userChatId, $message);
            // $this->markNotificationAsSent($menstruatingUser['id'], 'period_log_reminder', $estimatedCurrentOverdueStartDate->format('Y-m-d'));
        }
    }

    private function checkAndSendSymptomLoggingReminder(array $menstruatingUser, \Services\CycleService $cycleService): void {
        $userChatId = $menstruatingUser['decrypted_chat_id'];
        $lastSymptomLogDateStr = $menstruatingUser['last_symptom_log_date'] ?? null;
        $currentPhase = $cycleService->getCurrentCyclePhase();
        $today = new \DateTime('today');

        // Remind if no symptoms logged for 1-2 days, especially during luteal or menstruation
        if (in_array($currentPhase, ['luteal', 'menstruation'])) {
            $daysSinceLastLog = 100; // Default to a large number if never logged
            if ($lastSymptomLogDateStr) {
                try {
                    $lastLogDateObj = new \DateTime($lastSymptomLogDateStr);
                    $daysSinceLastLog = $lastLogDateObj->diff($today)->days;
                } catch (\Exception $e) { /* ignore malformed date */ }
            }

            if ($daysSinceLastLog >= 1 && $daysSinceLastLog <= 2) { // Remind if not logged for 1 or 2 days
                 // TODO: Idempotency check for this reminder for today.
                // if ($this->hasNotificationBeenSentRecently($menstruatingUser['id'], 'symptom_log_reminder', $today->format('Y-m-d'))) return;

                $message = "📝 یادت نره علائم امروزت رو ثبت کنی! ثبت منظم علائم به شناخت بهتر بدنت کمک می‌کنه.";
                $keyboard = ['inline_keyboard' => [[['text' => "ثبت علائم امروز", 'callback_data' => 'symptom_log_start:today']]]];
                $this->telegramAPI->sendMessage($userChatId, $message, $keyboard);
                // $this->markNotificationAsSent($menstruatingUser['id'], 'symptom_log_reminder', $today->format('Y-m-d'));
            }
        }
    }

    // TODO: Implement hasNotificationBeenSentRecently and markNotificationAsSent
    // This would typically involve a new DB table `sent_notifications` (user_id, notification_type, cycle_ref_date, sent_at)
    // to avoid sending the same event-based notification multiple times for the same event occurrence.
    // For daily tips, the "cycle_ref_date" could just be the date sent.

    // --- Subscription Lifecycle Notifications ---

    public function sendSubscriptionWarning(string $chatId, string $planName, string $expiryDate): void {
        $expiryDateFormatted = মেয়ের($expiryDate); // Assuming meyr is a Persian date formatter
        $message = "⚠️ اشتراک «{$planName}» شما در تاریخ {$expiryDateFormatted} منقضی می‌شود.\nبرای ادامه دسترسی بدون وقفه، لطفا اشتراک خود را تمدید کنید.";
        $keyboard = ['inline_keyboard' => [[['text' => "تمدید اشتراک 💳", 'callback_data' => 'sub_show_plans']]]];
        $this->telegramAPI->sendMessage($chatId, $message, $keyboard);
        // TODO: Mark this warning as sent for this expiry cycle to avoid daily spam
    }

    public function sendSubscriptionExpired(string $chatId, string $planName): void {
        $message = "❌ اشتراک «{$planName}» شما منقضی شده است.\nبرخی از قابلیت‌های ربات برای شما محدود شده است. برای دسترسی کامل، اشتراک خود را تمدید کنید.";
        $keyboard = ['inline_keyboard' => [[['text' => "خرید اشتراک جدید 💳", 'callback_data' => 'sub_show_plans']]]];
        $this->telegramAPI->sendMessage($chatId, $message, $keyboard);
    }

    public function sendTrialEndingWarning(string $chatId, string $trialEndDate): void {
        $trialEndDateFormatted = মেয়ের($trialEndDate);
        $message = "⏳ دوره استفاده رایگان (آزمایشی) شما در تاریخ {$trialEndDateFormatted} به پایان می‌رسد.\nبرای ادامه دسترسی به تمام امکانات پس از این تاریخ، لطفا یکی از طرح‌های اشتراک ما را فعال کنید.";
        $keyboard = ['inline_keyboard' => [[['text' => "مشاهده طرح‌های اشتراک 💳", 'callback_data' => 'sub_show_plans']]]];
        $this->telegramAPI->sendMessage($chatId, $message, $keyboard);
        // TODO: Mark this warning as sent
    }

    public function sendTrialExpired(string $chatId): void {
        $message = "🚫 دوره استفاده رایگان (آزمایشی) شما به پایان رسیده است.\nبرای ادامه استفاده از امکانات کامل ربات، لطفا اشتراک تهیه کنید.";
        $keyboard = ['inline_keyboard' => [[['text' => "خرید اشتراک 💳", 'callback_data' => 'sub_show_plans']]]];
        $this->telegramAPI->sendMessage($chatId, $message, $keyboard);
    }
}
?>
