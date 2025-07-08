<?php

namespace Models;

use PDO;
use Database;

class SubscriptionPlanModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Fetches all active subscription plans.
     * @return array An array of active subscription plans.
     */
    public function getActivePlans(): array {
        $stmt = $this->db->prepare("SELECT * FROM subscription_plans WHERE is_active = TRUE ORDER BY duration_months ASC");
        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching active subscription plans: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetches a specific subscription plan by its ID.
     * @param int $planId
     * @return array|false The plan details or false if not found.
     */
    public function getPlanById(int $planId): array|false {
        $stmt = $this->db->prepare("SELECT * FROM subscription_plans WHERE id = :plan_id");
        $stmt->bindParam(':plan_id', $planId, PDO::PARAM_INT);
        try {
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching plan by ID {$planId}: " . $e->getMessage());
            return false;
        }
    }

    // Admin methods for managing plans (to be used in Phase 5 Admin Panel)
    public function addPlan(string $name, ?string $description, int $durationMonths, float $price, bool $isActive = true): int|false {
        $sql = "INSERT INTO subscription_plans (name, description, duration_months, price, is_active)
                VALUES (:name, :description, :duration_months, :price, :is_active)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':duration_months', $durationMonths, PDO::PARAM_INT);
        $stmt->bindParam(':price', $price); // PDO will handle float to decimal if column type is DECIMAL
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);
        try {
            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error adding subscription plan: " . $e->getMessage());
            return false;
        }
    }

    public function updatePlan(int $planId, string $name, ?string $description, int $durationMonths, float $price, bool $isActive): bool {
        $sql = "UPDATE subscription_plans
                SET name = :name, description = :description, duration_months = :duration_months, price = :price, is_active = :is_active, updated_at = CURRENT_TIMESTAMP
                WHERE id = :plan_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':plan_id', $planId, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':duration_months', $durationMonths, PDO::PARAM_INT);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating subscription plan {$planId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all subscription plans for admin view (regardless of active status).
     * @return array An array of all subscription plans.
     */
    public function getAllPlansAdmin(): array {
        $stmt = $this->db->prepare("SELECT * FROM subscription_plans ORDER BY duration_months ASC, id ASC");
        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all subscription plans for admin: " . $e->getMessage());
            return [];
        }
    }

    public function togglePlanActive(int $planId, bool $isActive): bool {
        $sql = "UPDATE subscription_plans SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :plan_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':plan_id', $planId, PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error toggling active status for plan {$planId}: " . $e->getMessage());
            return false;
        }
    }
}
?>
