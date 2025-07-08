<?php

namespace Services;

use PDO;
use Database; // For creating pending transaction records

class ZarinpalService {
    private $merchantId;
    private $apiUrlRequest = 'https://api.zarinpal.com/pg/v4/payment/request.json';
    private $apiUrlVerify = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
    private $apiUrlStartPayPrefix = 'https://www.zarinpal.com/pg/StartPay/'; // Authority will be appended
    private $callbackUrl;
    private $db;

    public function __construct() {
        if (!defined('ZARINPAL_MERCHANT_ID') || ZARINPAL_MERCHANT_ID === '') {
            throw new \Exception("Zarinpal Merchant ID is not configured.");
        }
        if (!defined('ZARINPAL_CALLBACK_URL') || ZARINPAL_CALLBACK_URL === '' || strpos(ZARINPAL_CALLBACK_URL, 'YOUR_BOT_URL') !== false) {
            throw new \Exception("Zarinpal Callback URL is not properly configured.");
        }
        $this->merchantId = ZARINPAL_MERCHANT_ID;
        $this->callbackUrl = ZARINPAL_CALLBACK_URL;
        $this->db = Database::getInstance()->getConnection();

        // Use sandbox URLs if needed for testing
        if (defined('ZARINPAL_SANDBOX') && ZARINPAL_SANDBOX === true) {
            $this->apiUrlRequest = 'https://sandbox.zarinpal.com/pg/rest/WebGate/PaymentRequest.json';
            $this->apiUrlVerify = 'https://sandbox.zarinpal.com/pg/rest/WebGate/PaymentVerification.json';
            $this->apiUrlStartPayPrefix = 'https://sandbox.zarinpal.com/pg/StartPay/';
        }
    }

    /**
     * Creates a pending transaction record.
     * @return int|false Transaction ID or false on failure.
     */
    private function createPendingTransaction(int $userId, int $planId, int $amount, string $description, ?string $userEmail, ?string $userMobile): int|false {
        $sql = "INSERT INTO transactions (user_id, plan_id, amount, status, description, user_email, user_mobile)
                VALUES (:user_id, :plan_id, :amount, 'pending', :description, :user_email, :user_mobile)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':plan_id', $planId, PDO::PARAM_INT);
        $stmt->bindParam(':amount', $amount); // Zarinpal amount is in Toman
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':user_email', $userEmail);
        $stmt->bindParam(':user_mobile', $userMobile);

        try {
            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("ZarinpalService: Error creating pending transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates a pending transaction with the authority code from Zarinpal.
     */
    private function updatePendingTransactionWithAuthority(int $transactionId, string $authority): bool {
        $sql = "UPDATE transactions SET zarinpal_authority = :authority, updated_at = CURRENT_TIMESTAMP
                WHERE id = :transaction_id AND status = 'pending'";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':authority', $authority);
        $stmt->bindParam(':transaction_id', $transactionId, PDO::PARAM_INT);
        try {
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("ZarinpalService: Error updating transaction with authority: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Requests a payment from Zarinpal.
     * @param int $amount Amount in Toman.
     * @param int $userId User's internal ID.
     * @param int $planId Plan ID being purchased.
     * @param string $description Description of the transaction.
     * @param string|null $userEmail Optional user email.
     * @param string|null $userMobile Optional user mobile.
     * @return string|null Payment URL or null on failure.
     */
    public function requestPayment(int $amount, int $userId, int $planId, string $description, ?string $userEmail = null, ?string $userMobile = null): ?string {
        // 1. Create a pending transaction record in our DB
        $transactionId = $this->createPendingTransaction($userId, $planId, $amount, $description, $userEmail, $userMobile);
        if (!$transactionId) {
            error_log("ZarinpalService: Failed to create pending transaction record.");
            return null;
        }
        // Use the internal transaction ID as part of the callback to identify it later
        $callbackUrlWithTxId = $this->callbackUrl . '?tx_id=' . $transactionId;


        $data = [
            'merchant_id' => $this->merchantId,
            'amount' => $amount, // Amount in Toman
            'callback_url' => $callbackUrlWithTxId,
            'description' => $description,
        ];
        if ($userEmail) $data['metadata']['email'] = $userEmail;
        if ($userMobile) $data['metadata']['mobile'] = $userMobile;
        // Zarinpal recommends sending mobile/email in metadata for better tracking

        $jsonData = json_encode($data);
        $ch = curl_init($this->apiUrlRequest);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($jsonData)]);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true);

        if ($http_code === 200 && isset($response['data']['authority']) && $response['data']['code'] == 100) {
            $authority = $response['data']['authority'];
            // 2. Update our pending transaction with the authority
            if ($this->updatePendingTransactionWithAuthority($transactionId, $authority)) {
                return $this->apiUrlStartPayPrefix . $authority;
            } else {
                 error_log("ZarinpalService: Failed to update pending transaction with authority {$authority}.");
                // TODO: Potentially cancel or mark the Zarinpal transaction if possible, or handle cleanup.
                return null;
            }
        } else {
            $errorCode = $response['errors']['code'] ?? 'Unknown';
            $errorMessage = $response['errors']['message'] ?? 'Request failed';
            error_log("ZarinpalService: Payment request failed. HTTP Code: {$http_code}, ZP Code: {$errorCode}, Message: {$errorMessage}, Response: {$result}");
            // Update transaction status to 'failed'
            $this->updateTransactionStatus($transactionId, 'failed', "ZP Error Code: $errorCode - $errorMessage");
            return null;
        }
    }

    /**
     * Fetches a transaction by its Zarinpal authority code.
     */
    public function getTransactionByAuthority(string $authority): array|false {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE zarinpal_authority = :authority");
        $stmt->bindParam(':authority', $authority);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches a transaction by its internal ID.
     */
    public function getTransactionById(int $transactionId): array|false {
         $stmt = $this->db->prepare("SELECT * FROM transactions WHERE id = :id");
        $stmt->bindParam(':id', $transactionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Verifies a payment with Zarinpal.
     * @param string $authority The authority code received from Zarinpal.
     * @param int $amount The amount that was supposed to be paid (in Toman).
     * @return array ['status' => bool, 'ref_id' => string|null, 'message' => string, 'transaction_id' => int|null, 'user_id' => int|null, 'plan_id' => int|null]
     */
    public function verifyPayment(string $authority, int $amount): array {
        $transaction = $this->getTransactionByAuthority($authority);
        if (!$transaction) {
            return ['status' => false, 'ref_id' => null, 'message' => 'تراکنش یافت نشد.', 'transaction_id' => null, 'user_id' => null, 'plan_id' => null];
        }
        // Ensure the amount matches what we stored for this authority/transaction
        if ((int)$transaction['amount'] !== $amount) {
             error_log("ZarinpalService: Amount mismatch for authority {$authority}. Expected: {$transaction['amount']}, Got: {$amount}");
             $this->updateTransactionStatus($transaction['id'], 'failed', 'Amount mismatch');
            return ['status' => false, 'ref_id' => null, 'message' => 'مبلغ تراکنش همخوانی ندارد.', 'transaction_id' => $transaction['id'], 'user_id' => $transaction['user_id'], 'plan_id' => $transaction['plan_id']];
        }

        $data = [
            'merchant_id' => $this->merchantId,
            'authority' => $authority,
            'amount' => $amount, // Amount in Toman
        ];
        $jsonData = json_encode($data);
        $ch = curl_init($this->apiUrlVerify);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($jsonData)]);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true);

        if ($http_code === 200 && isset($response['data']['code']) && $response['data']['code'] == 100) {
            // Payment successful
            $refId = $response['data']['ref_id'];
            $this->updateTransactionStatus($transaction['id'], 'completed', "Ref ID: $refId", $refId);
            return ['status' => true, 'ref_id' => $refId, 'message' => 'پرداخت با موفقیت تایید شد.', 'transaction_id' => $transaction['id'], 'user_id' => $transaction['user_id'], 'plan_id' => $transaction['plan_id']];
        } elseif (isset($response['data']['code']) && $response['data']['code'] == 101) {
            // Payment previously verified - treat as success for idempotency
             error_log("ZarinpalService: Payment previously verified for authority {$authority}. Ref ID: {$transaction['zarinpal_ref_id']}");
             return ['status' => true, 'ref_id' => $transaction['zarinpal_ref_id'], 'message' => 'پرداخت قبلا تایید شده بود.', 'transaction_id' => $transaction['id'], 'user_id' => $transaction['user_id'], 'plan_id' => $transaction['plan_id']];
        }else {
            $errorCode = $response['errors']['code'] ?? ($response['data']['code'] ?? 'Unknown');
            $errorMessage = $response['errors']['message'] ?? 'Verification failed';
            error_log("ZarinpalService: Payment verification failed. HTTP Code: {$http_code}, ZP Code: {$errorCode}, Message: {$errorMessage}, Response: {$result}");
            $this->updateTransactionStatus($transaction['id'], 'failed', "ZP Error Code: $errorCode - $errorMessage");
            return ['status' => false, 'ref_id' => null, 'message' => "تایید پرداخت با مشکل مواجه شد (کد: {$errorCode}).", 'transaction_id' => $transaction['id'], 'user_id' => $transaction['user_id'], 'plan_id' => $transaction['plan_id']];
        }
    }

    /**
     * Updates the status and optionally ref_id of a transaction.
     */
    public function updateTransactionStatus(int $transactionId, string $status, ?string $descriptionSuffix = null, ?string $refId = null): bool {
        $parts = ["status = :status", "updated_at = CURRENT_TIMESTAMP"];
        $params = [':transaction_id' => $transactionId, ':status' => $status];

        if ($refId !== null) {
            $parts[] = "zarinpal_ref_id = :ref_id";
            $params[':ref_id'] = $refId;
        }
        if ($descriptionSuffix !== null) {
            // Append to description if it exists, otherwise set it.
            // This might need to fetch current description first if we want to truly append.
            // For simplicity, let's assume we are setting or overriding part of description.
            // A better way is a dedicated log or separate field for gateway messages.
            // For now, we just update the description field.
            $parts[] = "description = CONCAT(COALESCE(description, ''), :desc_suffix)";
            $params[':desc_suffix'] = ($descriptionSuffix ? " | " . $descriptionSuffix : "");
        }

        $sql = "UPDATE transactions SET " . implode(', ', $parts) . " WHERE id = :transaction_id";
        $stmt = $this->db->prepare($sql);

        try {
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("ZarinpalService: Error updating transaction status for ID {$transactionId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all transactions for admin view, paginated.
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllTransactionsAdmin(int $limit = 20, int $offset = 0): array {
        // Joins with users table to get some user info if needed, e.g., telegram_id_hash or decrypted name (if stored)
        // For simplicity, just fetching from transactions table first.
        // Add JOIN users u ON t.user_id = u.id if user details are desired directly here.
        $sql = "SELECT t.*, u.telegram_id_hash, u.encrypted_first_name
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                ORDER BY t.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($transactions as &$tx){
            if(!empty($tx['encrypted_first_name'])){
                try {
                    $tx['user_first_name'] = EncryptionHelper::decrypt($tx['encrypted_first_name']);
                } catch (\Exception $e) {
                    $tx['user_first_name'] = '[رمزگشایی ناموفق]';
                }
            } else {
                 $tx['user_first_name'] = 'کاربر حذف شده/نامشخص';
            }
        }
        return $transactions;
    }

    /**
     * Counts total transactions for admin view.
     * @return int
     */
    public function countAllTransactionsAdmin(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM transactions");
        return (int)$stmt->fetchColumn();
    }
}
?>
