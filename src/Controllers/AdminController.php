<?php

namespace Controllers;

use Telegram\TelegramAPI;
use Models\SubscriptionPlanModel;
use Models\EducationalContentModel;
use Models\UserModel;
use Helpers\EncryptionHelper;

class AdminController {
    private $telegramAPI;
    private $subscriptionPlanModel;
    private $educationalContentModel;
    private $userModel;

    public function __construct(TelegramAPI $telegramAPI) {
        $this->telegramAPI = $telegramAPI;
        $this->subscriptionPlanModel = new SubscriptionPlanModel();
        $this->educationalContentModel = new EducationalContentModel();
        $this->userModel = new UserModel();
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
        $buttons = [
            [['text' => "مدیریت طرح‌های اشتراک 💳", 'callback_data' => 'admin_plans_show_list']],
            [['text' => "📚 مدیریت محتوای آموزشی", 'callback_data' => 'admin_content_show_menu']],
            [['text' => "🏠 بازگشت به منوی اصلی کاربر", 'callback_data' => 'main_menu_show']],
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

                 $this->telegramAPI->editMessageText($chatId, $messageId, $ackText, json_encode(['inline_keyboard'=>[]]), 'Markdown');
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
                $this->telegramAPI->editMessageText($chatId, $messageId, "❌ **خطا:** این موضوع دارای مطالب داخلی است.\nابتدا آنها را حذف یا به موضوع دیگری منتقل کنید.",
                    json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => $cancelCallback ]]]]));
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
