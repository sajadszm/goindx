<?php

require_once dirname(__DIR__) . '/config/config.php'; // Defines BASE_PATH, loads autoloader

use Services\ZarinpalService;
use Models\UserModel;
use Models\SubscriptionPlanModel; // To get plan duration
use Helpers\EncryptionHelper; // For fetching user's chat_id

// Zarinpal callback parameters
$authority = $_GET['Authority'] ?? null;
$status = $_GET['Status'] ?? null; // 'OK' or 'NOK'
$transactionIdFromCallback = $_GET['tx_id'] ?? null; // Our internal transaction ID passed to Zarinpal

$telegramAPI = new TelegramAPI(TELEGRAM_BOT_TOKEN); // For sending messages to user
$userModel = new UserModel();
$planModel = new SubscriptionPlanModel();
$zarinpalService = new ZarinpalService();

$userDisplayMessage = "وضعیت پرداخت شما در حال بررسی است...";
$finalUserChatId = null; // We need to determine this to send a message

if (!$authority || !$status || !$transactionIdFromCallback) {
    error_log("Zarinpal Callback: Missing parameters. Authority: {$authority}, Status: {$status}, TxId: {$transactionIdFromCallback}");
    // Cannot inform user directly without knowing who this callback is for.
    // This is a generic error page scenario.
    echo "اطلاعات بازگشتی از درگاه پرداخت ناقص است.";
    exit;
}

// Fetch the transaction using our internal tx_id to get amount and user_id
$transaction = $zarinpalService->getTransactionById((int)$transactionIdFromCallback);

if (!$transaction) {
    error_log("Zarinpal Callback: Transaction not found for our internal tx_id: {$transactionIdFromCallback}");
    echo "تراکنش شما در سیستم ما یافت نشد.";
    exit;
}

// Now fetch the user to get their chat_id for sending a message
$user = $userModel->findUserById($transaction['user_id']);
if ($user && !empty($user['encrypted_chat_id'])) {
    try {
        $finalUserChatId = EncryptionHelper::decrypt($user['encrypted_chat_id']);
    } catch (\Exception $e) {
        error_log("Zarinpal Callback: Failed to decrypt chat_id for user_id: {$transaction['user_id']}");
    }
}


if ($status === 'OK') {
    // Payment was potentially successful, now verify it
    $amountToVerify = (int)$transaction['amount'];
    $verificationResult = $zarinpalService->verifyPayment($authority, $amountToVerify);

    if ($verificationResult['status'] === true) {
        // Payment Successful and Verified!
        $refId = $verificationResult['ref_id'];
        $userDisplayMessage = "✅ پرداخت شما با موفقیت انجام و تایید شد.\nشماره پیگیری زرین پال: {$refId}\nاز خرید شما سپاسگزاریم!";

        // Update user's subscription
        $plan = $planModel->getPlanById((int)$transaction['plan_id']);
        if ($plan) {
            $durationMonths = (int)$plan['duration_months'];
            // Calculate new subscription end date
            // If user already has an active subscription, extend it. Otherwise, start from now.
            $currentSubEndsAt = null;
            if ($user['subscription_status'] === 'active' && !empty($user['subscription_ends_at'])) {
                $currentSubEndsAt = new DateTime($user['subscription_ends_at']);
            }

            $newSubEndsAt = ($currentSubEndsAt && $currentSubEndsAt > new DateTime()) ? $currentSubEndsAt : new DateTime();
            $newSubEndsAt->add(new DateInterval("P{$durationMonths}M"));

            $updateData = [
                'subscription_status' => 'active',
                'subscription_ends_at' => $newSubEndsAt->format('Y-m-d H:i:s')
            ];
            $userModel->updateUser($user['telegram_id_hash'], $updateData);

            $userDisplayMessage .= "\nاشتراک شما تا تاریخ " . মেয়ের($newSubEndsAt->format('Y/m/d')) . " فعال شد."; // Helper for Persian date needed

        } else {
            error_log("Zarinpal Callback: Plan ID {$transaction['plan_id']} not found after successful payment for tx_id {$transactionIdFromCallback}.");
            $userDisplayMessage .= "\nخطا در فعالسازی طرح. لطفا با پشتیبانی تماس بگیرید.";
        }

    } else {
        // Verification failed
        $userDisplayMessage = "❌ متاسفانه در تایید پرداخت شما مشکلی پیش آمد.\n{$verificationResult['message']}\nدر صورت کسر وجه، مبلغ طی ۷۲ ساعت به حساب شما باز خواهد گشت. اگر سوالی دارید با پشتیبانی تماس بگیرید.";
    }
} else {
    // Payment was not successful (Status == 'NOK') or cancelled by user
    $userDisplayMessage = "❌ پرداخت شما موفقیت آمیز نبود یا توسط شما لغو شد.";
    if (isset($transaction['id'])) { // Check if transaction was loaded
        $zarinpalService->updateTransactionStatus($transaction['id'], 'cancelled', 'User cancelled or Zarinpal NOK');
    }
}

// Send final status message to user on Telegram
if ($finalUserChatId) {
    $telegramAPI->sendMessage($finalUserChatId, $userDisplayMessage);
} else {
    error_log("Zarinpal Callback: Could not send final status to user_id: {$transaction['user_id']} due to missing chat_id.");
}

// Display a message to the user in their browser as well
// This page is shown in the user's browser after redirect from Zarinpal.
echo "<!DOCTYPE html><html lang='fa-IR'><head><meta charset='UTF-8'><title>وضعیت پرداخت</title>";
echo "<style>body { font-family: sans-serif; direction: rtl; text-align: center; padding-top: 50px; }</style></head><body>";
echo "<h1>وضعیت پرداخت</h1>";
echo "<p>" . nl2br(htmlspecialchars($userDisplayMessage)) . "</p>";
echo "<p><a href=\"https://t.me/" . (defined('BOT_USERNAME_FOR_LINK') ? BOT_USERNAME_FOR_LINK : '') . "\">بازگشت به ربات</a></p>"; // BOT_USERNAME_FOR_LINK should be bot's username
echo "</body></html>";

// Helper function for Persian date (simple example)
function মেয়ের(string $gregorianDate): string {
    // This is a very basic placeholder. A proper Jalali date library should be used for accuracy.
    try {
        $date = new DateTime($gregorianDate);
        // Simple conversion - NOT accurate for Jalali.
        // Replace with a proper Jalali conversion library.
        return $date->format('Y/m/d') . " (میلادی)";
    } catch (\Exception $e) {
        return $gregorianDate;
    }
}

?>
