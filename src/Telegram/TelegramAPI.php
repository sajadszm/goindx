<?php

namespace Telegram;

class TelegramAPI {
    private $botToken;
    private $apiBaseUrl;

    public function __construct(string $botToken) {
        $this->botToken = $botToken;
        $this->apiBaseUrl = 'https://api.telegram.org/bot' . $this->botToken . '/';
    }

    private function executeCurl(string $method, array $params = []) {
        $url = $this->apiBaseUrl . $method;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params)); // Send as form data
        // It's good practice to set a timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // For debugging CURL:
        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        // $verbose = fopen('php://temp', 'w+');
        // curl_setopt($ch, CURLOPT_STDERR, $verbose);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            error_log("Telegram API cURL Error for method $method: " . $error_msg);
            // In a production environment, you might throw an exception or handle this more gracefully
            return ['ok' => false, 'error_code' => 'curl_error', 'description' => $error_msg];
        }

        curl_close($ch);

        // if ($verbose) {
        //     rewind($verbose);
        //     $verboseLog = stream_get_contents($verbose);
        //     error_log("Telegram API Curl Verbose for method $method: " . $verboseLog);
        //     fclose($verbose);
        // }

        $decodedResponse = json_decode($response, true);

        if ($httpCode !== 200 || !$decodedResponse || !$decodedResponse['ok']) {
            error_log("Telegram API Error for method $method: HTTP Code $httpCode. Response: " . $response);
            // Return the decoded error response from Telegram if available
            return $decodedResponse ?: ['ok' => false, 'error_code' => $httpCode, 'description' => 'API request failed or invalid response.'];
        }

        return $decodedResponse;
    }

    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null, string $parseMode = '') {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        if (!empty($parseMode)) {
            $params['parse_mode'] = $parseMode; // e.g., 'MarkdownV2' or 'HTML'
        }
        return $this->executeCurl('sendMessage', $params);
    }

    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null, string $parseMode = '') {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ];
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
         if (!empty($parseMode)) {
            $params['parse_mode'] = $parseMode;
        }
        return $this->executeCurl('editMessageText', $params);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, ?bool $showAlert = false) {
        $params = [
            'callback_query_id' => $callbackQueryId,
        ];
        if ($text !== null) {
            $params['text'] = $text;
        }
        if ($showAlert !== null) {
            $params['show_alert'] = $showAlert;
        }
        return $this->executeCurl('answerCallbackQuery', $params);
    }

    public function setWebhook(string $url, ?string $secretToken = null) {
        $params = [
            'url' => $url
        ];
        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }
        return $this->executeCurl('setWebhook', $params);
    }

    public function deleteWebhook() {
        return $this->executeCurl('deleteWebhook');
    }

    public function getWebhookInfo() {
        return $this->executeCurl('getWebhookInfo');
    }

    public function getMe() {
        return $this->executeCurl('getMe');
    }

    // Add more methods as needed, e.g., sendPhoto, sendDocument, editMessageReplyMarkup, etc.
}
?>
