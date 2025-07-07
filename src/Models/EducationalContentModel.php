<?php

namespace Models;

use PDO;
use Database;
use Helpers\EncryptionHelper;

class EducationalContentModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Adds new educational content.
     * Assumes title, content_data, and symptom_association_keys are passed unencrypted
     * and will be encrypted by this method.
     * @param array $data
     * @return int|false The ID of the newly inserted content or false on failure.
     */
    public function addContent(array $data): int|false {
        $sql = "INSERT INTO educational_content
                    (target_role, category, title, content_type, content_data, image_url, read_more_link, cycle_phase_association, symptom_association_keys, is_active)
                VALUES
                    (:target_role, :category, :title, :content_type, :content_data, :image_url, :read_more_link, :cycle_phase_association, :symptom_association_keys, :is_active)";

        $stmt = $this->db->prepare($sql);

        $encryptedTitle = isset($data['title']) ? EncryptionHelper::encrypt($data['title']) : null;
        $encryptedContentData = EncryptionHelper::encrypt($data['content_data']);
        $encryptedSymptomKeys = isset($data['symptom_association_keys']) && is_array($data['symptom_association_keys'])
                                ? EncryptionHelper::encrypt(json_encode($data['symptom_association_keys']))
                                : null;

        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $contentType = $data['content_type'] ?? 'text';
        $cyclePhase = $data['cycle_phase_association'] ?? null;
        if ($cyclePhase === 'any' || empty($cyclePhase)) $cyclePhase = null;


        $stmt->bindParam(':target_role', $data['target_role']);
        $stmt->bindParam(':category', $data['category']);
        $stmt->bindParam(':title', $encryptedTitle);
        $stmt->bindParam(':content_type', $contentType);
        $stmt->bindParam(':content_data', $encryptedContentData);
        $stmt->bindParam(':image_url', $data['image_url'] ?? null);
        $stmt->bindParam(':read_more_link', $data['read_more_link'] ?? null);
        $stmt->bindParam(':cycle_phase_association', $cyclePhase);
        $stmt->bindParam(':symptom_association_keys', $encryptedSymptomKeys);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);

        try {
            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error adding educational content: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves content based on criteria.
     * This is a flexible method. For daily tips, it might be called multiple times
     * with different criteria (e.g., first for symptoms, then for phase, then general).
     * @param array $criteria Possible keys: target_role, category, cycle_phase, active_symptoms (array of keys)
     * @param int $limit Max number of records to return
     * @return array
     */
    public function getContent(array $criteria, int $limit = 1): array {
        $sqlParts = ["SELECT * FROM educational_content WHERE is_active = TRUE"];
        $params = [];

        if (!empty($criteria['target_role'])) {
            // Target role can be specific ('menstruating', 'partner') or 'both'
            // If user is 'menstruating', they can get 'menstruating' or 'both'.
            // If user is 'partner', they can get 'partner' or 'both'.
            if (is_array($criteria['target_role'])) { // e.g. ['menstruating', 'both']
                 $sqlParts[] = "target_role IN (" . implode(',', array_fill(0, count($criteria['target_role']), '?')) . ")";
                 $params = array_merge($params, $criteria['target_role']);
            } else { // single role string
                $sqlParts[] = "target_role = ?";
                $params[] = $criteria['target_role'];
            }
        }

        if (!empty($criteria['category'])) {
            $sqlParts[] = "category = ?";
            $params[] = $criteria['category'];
        }

        if (!empty($criteria['cycle_phase'])) {
            // Content can be for a specific phase or 'any' (NULL in DB means 'any' or not phase-specific)
            $sqlParts[] = "(cycle_phase_association = ? OR cycle_phase_association IS NULL)";
            $params[] = $criteria['cycle_phase'];
        }

        // Symptom matching is more complex as it's a JSON array in the DB.
        // This simple version doesn't directly query JSON. It would fetch candidates
        // and then filter in PHP, or use JSON functions if DB supports (e.g. MySQL 5.7+).
        // For now, we'll fetch broader results and let NotificationService filter by symptoms if needed.
        // A more advanced query could use FIND_IN_SET or JSON_CONTAINS after decrypting, which is not feasible in pure SQL.
        // So, symptom matching will primarily be a post-query filter in PHP.

        $sqlParts[] = "ORDER BY RAND()"; // Get a random tip that matches
        $sqlParts[] = "LIMIT ?";
        $params[] = $limit;

        $sql = implode(" AND ", array_filter($sqlParts, function($part) { return strpos($part, "SELECT") === 0; }) ) // main SELECT
             . (count($sqlParts) > 1 ? " AND " : "")
             . implode(" AND ", array_filter($sqlParts, function($part) { return strpos($part, "SELECT") !== 0 && strpos($part, "ORDER BY") !== 0 && strpos($part, "LIMIT") !== 0; }) ) // WHERE clauses
             . " " . implode(" ", array_filter($sqlParts, function($part) { return strpos($part, "ORDER BY") === 0 || strpos($part, "LIMIT") === 0; })); // ORDER BY and LIMIT


        $stmt = $this->db->prepare($sql);

        try {
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $decryptedResults = [];
            foreach($results as $row) {
                $decryptedRow = $row;
                if (isset($row['title'])) $decryptedRow['title'] = EncryptionHelper::decrypt($row['title']);
                $decryptedRow['content_data'] = EncryptionHelper::decrypt($row['content_data']);
                if (isset($row['symptom_association_keys'])) {
                    $decryptedRow['symptom_association_keys'] = json_decode(EncryptionHelper::decrypt($row['symptom_association_keys']), true);
                }
                $decryptedResults[] = $decryptedRow;
            }
            return $decryptedResults;
        } catch (PDOException $e) {
            error_log("Error getting educational content: " . $e->getMessage() . " SQL: " . $sql . " Params: " . json_encode($params));
            return [];
        }  catch (\Exception $e) {
            error_log("Error decrypting educational content: " . $e->getMessage());
            // Return row with decryption error or skip it
            return []; // Or handle partially decrypted data if necessary
        }
    }
    // Update and Delete methods would be added for admin panel functionality
}

?>
