<?php

namespace Controllers;

use Telegram\TelegramAPI;
use Models\SubscriptionPlanModel;
use Helpers\EncryptionHelper; // If needed for user interactions, not directly for plan names/prices

class AdminController {
    private $telegramAPI;
    private $subscriptionPlanModel;
    private $userModel; // For user-related admin actions later

    public function __construct(TelegramAPI $telegramAPI) {
        $this->telegramAPI = $telegramAPI;
        $this->subscriptionPlanModel = new SubscriptionPlanModel();
        // $this->userModel = new \Models\UserModel(); // Instantiate if needed
    }

    /**
     * Security check: Ensure the user is the admin.
     * This should be called at the beginning of each admin handler.
     */
    private function isAdmin(string $telegramId): bool {
        return (string)$telegramId === ADMIN_TELEGRAM_ID;
    }

    public function showAdminMenu(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) {
            $this->telegramAPI->sendMessage($chatId, "شما اجازه دسترسی به این بخش را ندارید.");
            return;
        }

        $text = "👑 **پنل مدیریت ربات** 👑\n\nچه کاری می‌خواهید انجام دهید؟";
        $buttons = [
            [['text' => "مدیریت طرح‌های اشتراک", 'callback_data' => 'admin_plans_show_list']],
            // [['text' => "ارسال پیام همگانی (به زودی)", 'callback_data' => 'admin_broadcast_prompt']],
            // [['text' => "آمار ربات (به زودی)", 'callback_data' => 'admin_show_stats']],
            [['text' => "بازگشت به منوی اصلی کاربر", 'callback_data' => 'main_menu_show']],
        ];
        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) {
            $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        } else {
            $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
        }
    }

    // --- Subscription Plan Management ---

    public function showSubscriptionPlansAdmin(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }

        $plans = $this->subscriptionPlanModel->getActivePlans(); // Or getAllPlans() if we want to show inactive too
        $allPlans = $this->subscriptionPlanModel->getAllPlansAdmin(); // A new method to get all plans for admin


        $text = "مدیریت طرح‌های اشتراک:\n\n";
        $planButtons = [];

        if (empty($allPlans)) {
            $text .= "هیچ طرحی ثبت نشده است.";
        } else {
            foreach ($allPlans as $plan) {
                $status = $plan['is_active'] ? "فعال ✅" : "غیرفعال ❌";
                $text .= "▫️ *{$plan['name']}* ({$plan['duration_months']} ماهه) - " . number_format($plan['price']) . " تومان - {$status}\n";
                $text .= "   `ID: {$plan['id']}`\n"; // Show ID for editing
                $actionText = $plan['is_active'] ? "غیرفعال کردن" : "فعال کردن";
                $planButtons[] = [
                    // ['text' => "ویرایش ID:{$plan['id']}", 'callback_data' => 'admin_plan_prompt_edit:' . $plan['id']], // Not fully implemented yet
                    ['text' => "{$actionText} ID:{$plan['id']}", 'callback_data' => 'admin_plan_toggle_active:' . $plan['id'] . ':' . ($plan['is_active'] ? 0 : 1)],
                ];
            }
        }
        $keyboard = ['inline_keyboard' => $planButtons];
        $keyboard['inline_keyboard'][] = [['text' => "افزودن طرح جدید +", 'callback_data' => 'admin_plan_prompt_add']];
        $keyboard['inline_keyboard'][] = [['text' => "🔙 بازگشت به پنل ادمین", 'callback_data' => 'admin_show_menu']];

        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, $keyboard, 'Markdown');
        else $this->telegramAPI->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    // Placeholder for a method in SubscriptionPlanModel to get ALL plans regardless of active status for admin
    // This would be: public function getAllPlansAdmin(): array { ... query without is_active filter ... }

    public function handleTogglePlanActive(string $telegramId, int $chatId, int $messageId, int $planId, int $newState) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }

        $success = $this->subscriptionPlanModel->togglePlanActive($planId, (bool)$newState);
        if ($success) {
            $this->telegramAPI->answerCallbackQuery($this->telegramAPI->getLastCallbackQueryId($chatId), "وضعیت طرح تغییر کرد.", false); // Need to get last callback_query_id
        } else {
            $this->telegramAPI->answerCallbackQuery($this->telegramAPI->getLastCallbackQueryId($chatId), "خطا در تغییر وضعیت.", true);
        }
        // Refresh the list
        $this->showSubscriptionPlansAdmin($telegramId, $chatId, $messageId);
    }

    public function promptAddSubscriptionPlan(string $telegramId, int $chatId, ?int $messageId = null) {
        if (!$this->isAdmin($telegramId)) { $this->telegramAPI->sendMessage($chatId, "عدم دسترسی."); return; }

        $text = "افزودن طرح اشتراک جدید:\n\n";
        $text .= "لطفا اطلاعات طرح را با فرمت زیر ارسال کنید (هر بخش در یک خط):\n";
        $text .= "نام طرح\n";
        $text .= "توضیحات (اختیاری، برای خط خالی . بگذارید)\n";
        $text .= "مدت زمان (به ماه، مثلا: 3)\n";
        $text .= "قیمت (به تومان، مثلا: 50000)\n";
        $text .= "وضعیت (فعال یا غیرفعال)\n\n";
        $text .= "مثال:\nاشتراک ویژه سه ماهه\nدسترسی کامل با تخفیف\n3\n45000\nفعال\n\n";
        $text .= "برای لغو /cancel_admin_action را ارسال کنید.";

        // Set user state to await plan details
        $userController = new UserController($this->telegramAPI); // Temporary, ideally inject or use a service
        $userModel = $userController->getUserModel();
        $userModel->updateUser(EncryptionHelper::hashIdentifier($telegramId), ['user_state' => 'admin_awaiting_plan_add']);

        if ($messageId) $this->telegramAPI->editMessageText($chatId, $messageId, $text, null, 'Markdown'); // Remove keyboard
        else $this->telegramAPI->sendMessage($chatId, $text, null, 'Markdown');
    }

    public function handleAddSubscriptionPlanDetails(string $telegramId, int $chatId, string $messageText) {
        if (!$this->isAdmin($telegramId)) { return; } // Silently ignore if not admin or state not set

        $lines = explode("\n", trim($messageText));
        if (count($lines) !== 5) {
            $this->telegramAPI->sendMessage($chatId, "فرمت ورودی صحیح نیست. باید ۵ خط اطلاعات وارد کنید. لطفا دوباره تلاش کنید یا /cancel_admin_action برای لغو.");
            return;
        }

        list($name, $description, $durationMonths, $price, $statusStr) = $lines;
        $description = (trim($description) === '.' || trim($description) === '') ? null : trim($description);
        $durationMonths = (int)trim($durationMonths);
        $price = (float)trim($price);
        $isActive = (mb_strtolower(trim($statusStr)) === 'فعال');

        if (empty($name) || $durationMonths <= 0 || $price <= 0) {
            $this->telegramAPI->sendMessage($chatId, "اطلاعات نامعتبر (نام، مدت یا قیمت). لطفا دوباره تلاش کنید یا /cancel_admin_action برای لغو.");
            return;
        }

        $planId = $this->subscriptionPlanModel->addPlan($name, $description, $durationMonths, $price, $isActive);

        if ($planId) {
            $this->telegramAPI->sendMessage($chatId, "طرح جدید با موفقیت افزوده شد. ID: {$planId}");
        } else {
            $this->telegramAPI->sendMessage($chatId, "خطا در افزودن طرح جدید.");
        }

        // Clear user state
        $userController = new UserController($this->telegramAPI);
        $userModel = $userController->getUserModel();
        $userModel->updateUser(EncryptionHelper::hashIdentifier($telegramId), ['user_state' => null]);

        // Show updated list
        $this->showSubscriptionPlansAdmin($telegramId, $chatId, null);
    }

    // TODO: Implement promptEditSubscriptionPlan, handleEditSubscriptionPlanDetails
    // Similar to add, but fetches existing plan first.

}
?>
