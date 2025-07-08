<?php

namespace Models;

use PDO;
use Database;

class PeriodHistoryModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Adds or updates a period start date in the history.
     * If a record for this user and start date already exists, it might update it (e.g., if other details change).
     * For simplicity, this version assumes we add a new record if not exactly matched or handle updates if needed.
     * A more robust version might prevent duplicate start dates for the same user.
     *
     * @param int $userId
     * @param string $startDate YYYY-MM-DD
     * @param int|null $cycleLength
     * @return int|false The ID of the created/updated history entry or false on failure.
     */
    public function logPeriodStart(int $userId, string $startDate, ?int $cycleLength = null): int|false {
        // Check if a period with this exact start date already exists for this user
        $stmtCheck = $this->db->prepare("SELECT id FROM period_history WHERE user_id = :user_id AND period_start_date = :start_date");
        $stmtCheck->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtCheck->bindParam(':start_date', $startDate);
        $stmtCheck->execute();
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Optionally update if cycle_length is now available and wasn't before
            if ($cycleLength !== null) {
                $stmtUpdate = $this->db->prepare("UPDATE period_history SET cycle_length = :cycle_length WHERE id = :id AND cycle_length IS NULL");
                $stmtUpdate->bindParam(':cycle_length', $cycleLength, PDO::PARAM_INT);
                $stmtUpdate->bindParam(':id', $existing['id'], PDO::PARAM_INT);
                $stmtUpdate->execute();
            }
            return (int)$existing['id'];
        } else {
            // Insert new period start
            $sql = "INSERT INTO period_history (user_id, period_start_date, cycle_length, logged_at)
                    VALUES (:user_id, :start_date, :cycle_length, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':cycle_length', $cycleLength, $cycleLength === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            }
            error_log("Error logging period start for user_id {$userId}, date {$startDate}: " . implode(", ", $stmt->errorInfo()));
            return false;
        }
    }

    /**
     * Updates the end date and period length for a given period start.
     * Finds the most recent period start for the user that doesn't have an end date yet.
     *
     * @param int $userId
     * @param string $periodStartDate YYYY-MM-DD (The start date of the period to update)
     * @param string $endDate YYYY-MM-DD
     * @return bool Success or failure.
     */
    public function logPeriodEnd(int $userId, string $periodStartDate, string $endDate): bool {
        $startDateObj = new \DateTime($periodStartDate);
        $endDateObj = new \DateTime($endDate);
        if ($endDateObj < $startDateObj) {
            error_log("Period end date {$endDate} cannot be before start date {$periodStartDate} for user {$userId}");
            return false; // End date cannot be before start date
        }
        $periodLength = $endDateObj->diff($startDateObj)->days + 1;

        $sql = "UPDATE period_history
                SET period_end_date = :end_date, period_length = :period_length
                WHERE user_id = :user_id AND period_start_date = :start_date AND period_end_date IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $periodStartDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->bindParam(':period_length', $periodLength, PDO::PARAM_INT);

        $success = $stmt->execute();
        if(!$success || $stmt->rowCount() === 0){
            error_log("Failed to log period end or no matching open period found for user {$userId}, start {$periodStartDate}. Error: " . implode(", ", $stmt->errorInfo()));
            return false;
        }
        return true;
    }

    /**
     * Retrieves period history for a user, paginated.
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getPeriodHistory(int $userId, int $limit = 10, int $offset = 0): array {
        $sql = "SELECT * FROM period_history
                WHERE user_id = :user_id
                ORDER BY period_start_date DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Counts total period history entries for a user (for pagination).
     * @param int $userId
     * @return int
     */
    public function countPeriodHistory(int $userId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM period_history WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Gets the last N period start dates for cycle length calculation.
     * @param int $userId
     * @param int $count Number of recent periods to fetch (e.g., 2 for the last cycle).
     * @return array Array of 'Y-m-d' date strings, sorted descending.
     */
    public function getRecentPeriodStartDates(int $userId, int $count = 2): array {
        $sql = "SELECT period_start_date FROM period_history
                WHERE user_id = :user_id
                ORDER BY period_start_date DESC
                LIMIT :count";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':count', $count, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
