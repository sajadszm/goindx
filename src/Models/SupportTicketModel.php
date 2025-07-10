<?php

namespace Models;

use PDO;
use Database;
use Helpers\EncryptionHelper; // For potential future use if messages need encryption

class SupportTicketModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Creates a new support ticket.
     * @param int $userId The user's internal database ID.
     * @param string $subject Optional subject for the ticket.
     * @return int|false The ID of the created ticket, or false on failure.
     */
    public function createTicket(int $userId, ?string $subject = null) {
        $sql = "INSERT INTO support_tickets (user_id, subject, status, last_message_at)
                VALUES (:user_id, :subject, 'open', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':subject', $subject);

        if ($stmt->execute()) {
            return (int)$this->db->lastInsertId();
        }
        error_log("Error creating support ticket for user_id {$userId}: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    /**
     * Adds a message to a support ticket.
     * @param int $ticketId
     * @param string $senderTelegramId Telegram ID of the message sender.
     * @param string $senderRole 'user' or 'admin'.
     * @param string $messageText The content of the message.
     * @param string|null $telegramMessageId Optional original Telegram message ID.
     * @return bool True on success, false on failure.
     */
    public function addMessage(int $ticketId, string $senderTelegramId, string $senderRole, string $messageText, ?string $telegramMessageId = null): bool {
        // Encrypt message_text if needed in future, for now plain
        // $encryptedMessageText = EncryptionHelper::encrypt($messageText);

        $sql = "INSERT INTO support_messages (ticket_id, sender_telegram_id, sender_role, message_text, telegram_message_id)
                VALUES (:ticket_id, :sender_telegram_id, :sender_role, :message_text, :telegram_message_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->bindParam(':sender_telegram_id', $senderTelegramId);
        $stmt->bindParam(':sender_role', $senderRole);
        $stmt->bindParam(':message_text', $messageText); // Use $encryptedMessageText if encrypting
        $stmt->bindParam(':telegram_message_id', $telegramMessageId);

        if ($stmt->execute()) {
            // Update ticket's last_message_at and status
            $newStatus = ($senderRole === 'admin') ? 'admin_reply' : 'user_reply';
            $this->updateTicketStatus($ticketId, $newStatus, true);
            return true;
        }
        error_log("Error adding message to ticket {$ticketId}: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    /**
     * Updates the status and last_message_at timestamp of a ticket.
     * @param int $ticketId
     * @param string $status New status ('open', 'admin_reply', 'user_reply', 'closed').
     * @param bool $updateTimestamp Whether to update last_message_at to NOW().
     * @return bool
     */
    public function updateTicketStatus(int $ticketId, string $status, bool $updateTimestamp = true): bool {
        $sql = "UPDATE support_tickets SET status = :status";
        if ($updateTimestamp) {
            $sql .= ", last_message_at = NOW()";
        }
        $sql .= " WHERE id = :ticket_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Retrieves a ticket by its ID, along with user information.
     * @param int $ticketId
     * @return array|false Ticket data or false if not found.
     */
    public function getTicketById(int $ticketId) {
        $sql = "SELECT st.*, u.telegram_id_hash, u.encrypted_first_name, u.encrypted_username
                FROM support_tickets st
                JOIN users u ON st.user_id = u.id
                WHERE st.id = :ticket_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ticket && !empty($ticket['encrypted_first_name'])) {
            try { $ticket['user_first_name'] = EncryptionHelper::decrypt($ticket['encrypted_first_name']); } catch (\Exception $e) {$ticket['user_first_name'] = '[رمزگشایی ناموفق]';}
        }
        if ($ticket && !empty($ticket['encrypted_username'])) {
             try { $ticket['user_username'] = EncryptionHelper::decrypt($ticket['encrypted_username']); } catch (\Exception $e) {$ticket['user_username'] = null;}
        }
        return $ticket;
    }

    /**
     * Retrieves all messages for a given ticket ID, ordered by sent_at.
     * @param int $ticketId
     * @return array List of messages.
     */
    public function getMessagesForTicket(int $ticketId): array {
        $sql = "SELECT * FROM support_messages WHERE ticket_id = :ticket_id ORDER BY sent_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Decrypt messages here if they were encrypted
        // foreach ($messages as &$message) {
        //    $message['message_text'] = EncryptionHelper::decrypt($message['message_text']);
        // }
        return $messages;
    }

    /**
     * Lists tickets, optionally filtered by status(es), ordered by last_message_at descending.
     * @param string|array|null $statusFilter Filter by status or array of statuses. Null for all.
     * @param int $limit
     * @param int $offset
     * @return array List of tickets.
     */
    public function listTickets(string|array|null $statusFilter = null, int $limit = 20, int $offset = 0): array {
        $sql = "SELECT st.*, u.encrypted_first_name, u.encrypted_username
                FROM support_tickets st
                JOIN users u ON st.user_id = u.id";
        $params = [];
        $types = []; // To store PDO::PARAM_types for execute array

        if ($statusFilter !== null) {
            if (is_array($statusFilter)) {
                if (!empty($statusFilter)) {
                    $placeholders = implode(',', array_fill(0, count($statusFilter), '?'));
                    $sql .= " WHERE st.status IN ({$placeholders})";
                    foreach ($statusFilter as $sf) {
                        $params[] = $sf;
                        $types[] = PDO::PARAM_STR;
                    }
                }
                // If $statusFilter is an empty array, no WHERE clause is added, fetching all.
            } else { // Single string status
                $sql .= " WHERE st.status = ?";
                $params[] = $statusFilter;
                $types[] = PDO::PARAM_STR;
            }
        }
        $sql .= " ORDER BY st.last_message_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $types[] = PDO::PARAM_INT;
        $params[] = $offset;
        $types[] = PDO::PARAM_INT;

        error_log("SupportTicketModel::listTickets - SQL: {$sql}");
        error_log("SupportTicketModel::listTickets - Params: " . json_encode($params));

        $stmt = $this->db->prepare($sql);
        // Execute with parameters passed directly to execute, if using ? placeholders
        // For bindValue, types would be more explicit. Let's stick to execute array for simplicity with '?'
        $stmt->execute($params);

        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("SupportTicketModel::listTickets - Fetched " . count($tickets) . " raw ticket rows.");

        foreach($tickets as &$ticket){
            if (!empty($ticket['encrypted_first_name'])) {
                try { $ticket['user_first_name'] = EncryptionHelper::decrypt($ticket['encrypted_first_name']); } catch (\Exception $e) {$ticket['user_first_name'] = '[رمزگشایی ناموفق]';}
            }
             if (!empty($ticket['encrypted_username'])) {
                try { $ticket['user_username'] = EncryptionHelper::decrypt($ticket['encrypted_username']); } catch (\Exception $e) {$ticket['user_username'] = null;}
            }
        }
        return $tickets;
    }

    /**
     * Counts tickets, optionally filtered by status(es).
     * @param string|array|null $statusFilter Filter by status or array of statuses. Null for all.
     * @return int Count of tickets.
     */
    public function countTickets(string|array|null $statusFilter = null): int {
        $sql = "SELECT COUNT(*) FROM support_tickets st";
        $params = [];
        $types = []; // To store PDO::PARAM_types for execute array

        if ($statusFilter !== null) {
            if (is_array($statusFilter)) {
                if (empty($statusFilter)) {
                    error_log("SupportTicketModel::countTickets - StatusFilter is empty array, returning 0.");
                    return 0;
                }
                $placeholders = implode(',', array_fill(0, count($statusFilter), '?'));
                $sql .= " WHERE st.status IN ({$placeholders})";
                foreach ($statusFilter as $sf) {
                    $params[] = $sf;
                    $types[] = PDO::PARAM_STR;
                }
            } else { // Single string status
                $sql .= " WHERE st.status = ?";
                $params[] = $statusFilter;
                $types[] = PDO::PARAM_STR;
            }
        }

        error_log("SupportTicketModel::countTickets - SQL: {$sql}");
        error_log("SupportTicketModel::countTickets - Params: " . json_encode($params));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $count = (int)$stmt->fetchColumn();
        error_log("SupportTicketModel::countTickets - Raw count from DB: {$count}");
        return $count;
    }

    /**
     * Finds an open or user_reply ticket for a user to prevent multiple open tickets.
     * @param int $userId
     * @return array|false Ticket data or false if not found.
     */
    public function findOpenTicketByUserId(int $userId) {
        $sql = "SELECT * FROM support_tickets
                WHERE user_id = :user_id AND status IN ('open', 'user_reply', 'admin_reply')
                ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>
