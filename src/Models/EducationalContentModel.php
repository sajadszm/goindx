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

    private function generateSlug(string $title): string {
        // Remove Farsi characters for a URL-friendly slug, or use a library that handles Unicode slugs well.
        // This is a very basic slug generator.
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', mb_strtolower(trim($title), 'UTF-8')); // Allow unicode letters and numbers
        $slug = preg_replace('/[\s-]+/', '-', $slug); // Replace spaces and multiple hyphens with single hyphen
        $slug = trim($slug, '-');
        if (empty($slug)) {
            return 'content-' . time() . rand(100,999);
        }
        // Consider checking for uniqueness and appending a number if not unique,
        // or let the database unique constraint handle it if admin can provide slugs.
        return $slug;
    }

    private function decryptRow(array $row): array {
        if (isset($row['content_data'])) {
            $row['content_data'] = EncryptionHelper::decrypt($row['content_data']);
        }
        if (isset($row['symptom_association_keys'])) {
            $decryptedJson = EncryptionHelper::decrypt($row['symptom_association_keys']);
            $row['symptom_association_keys'] = $decryptedJson ? json_decode($decryptedJson, true) : [];
        } else {
            $row['symptom_association_keys'] = [];
        }
        if (isset($row['tags']) && !empty($row['tags'])) { // Tags are stored as plain JSON string
             $row['tags'] = json_decode($row['tags'], true);
        } else {
            $row['tags'] = [];
        }
        return $row;
    }

    public function addContent(array $data): int|false {
        $sql = "INSERT INTO educational_content
                    (parent_id, sequence_order, target_role, content_topic, title, slug, content_type,
                     content_data, image_url, video_url, source_url, read_more_link,
                     cycle_phase_association, symptom_association_keys, tags, is_tutorial_topic, is_active)
                VALUES
                    (:parent_id, :sequence_order, :target_role, :content_topic, :title, :slug, :content_type,
                     :content_data, :image_url, :video_url, :source_url, :read_more_link,
                     :cycle_phase_association, :symptom_association_keys, :tags, :is_tutorial_topic, :is_active)";

        $stmt = $this->db->prepare($sql);

        $plainTitle = trim($data['title']);
        $slug = $data['slug'] ?? $this->generateSlug($plainTitle);
        $encryptedContentData = EncryptionHelper::encrypt(trim($data['content_data']));

        $symptomKeysArray = $data['symptom_association_keys'] ?? [];
        if (is_string($symptomKeysArray)) $symptomKeysArray = json_decode($symptomKeysArray, true) ?: [];
        $encryptedSymptomKeys = !empty($symptomKeysArray) ? EncryptionHelper::encrypt(json_encode($symptomKeysArray)) : null;

        $tagsArray = $data['tags'] ?? [];
        if (is_string($tagsArray)) $tagsArray = json_decode($tagsArray, true) ?: [];
        $plainTagsJson = !empty($tagsArray) ? json_encode($tagsArray) : null;

        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $isTutorialTopic = isset($data['is_tutorial_topic']) ? (bool)$data['is_tutorial_topic'] : false;
        $contentType = $data['content_type'] ?? 'text';
        $cyclePhase = $data['cycle_phase_association'] ?? 'any';
        if (empty($cyclePhase) || $cyclePhase === 'all') $cyclePhase = 'any';

        $parentId = isset($data['parent_id']) && !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $sequenceOrder = isset($data['sequence_order']) ? (int)$data['sequence_order'] : 0;

        $params = [
            ':parent_id' => $parentId,
            ':sequence_order' => $sequenceOrder,
            ':target_role' => $data['target_role'],
            ':content_topic' => $data['content_topic'],
            ':title' => $plainTitle,
            ':slug' => $slug,
            ':content_type' => $contentType,
            ':content_data' => $encryptedContentData,
            ':image_url' => $data['image_url'] ?? null,
            ':video_url' => $data['video_url'] ?? null,
            ':source_url' => $data['source_url'] ?? null,
            ':read_more_link' => $data['read_more_link'] ?? null,
            ':cycle_phase_association' => $cyclePhase,
            ':symptom_association_keys' => $encryptedSymptomKeys,
            ':tags' => $plainTagsJson,
            ':is_tutorial_topic' => (int)$isTutorialTopic, // Ensure boolean is cast to int for DB
            ':is_active' => (int)$isActive
        ];

        try {
            if ($stmt->execute($params)) {
                return (int)$this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error adding educational content: " . $e->getMessage() . " Data: " . json_encode($data));
            return false;
        }
    }

    public function updateContent(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['parent_id'])) { $fields[] = "parent_id = :parent_id"; $params[':parent_id'] = empty($data['parent_id']) ? null : (int)$data['parent_id']; }
        if (isset($data['sequence_order'])) { $fields[] = "sequence_order = :sequence_order"; $params[':sequence_order'] = (int)$data['sequence_order']; }
        if (isset($data['target_role'])) { $fields[] = "target_role = :target_role"; $params[':target_role'] = $data['target_role']; }
        if (isset($data['content_topic'])) { $fields[] = "content_topic = :content_topic"; $params[':content_topic'] = $data['content_topic']; }
        if (isset($data['title'])) { $fields[] = "title = :title"; $params[':title'] = trim($data['title']); }
        if (isset($data['slug'])) { $fields[] = "slug = :slug"; $params[':slug'] = $data['slug']; }
        elseif (isset($data['title'])) { $fields[] = "slug = :slug"; $params[':slug'] = $this->generateSlug(trim($data['title']));} // Auto-update slug if title changes and no slug provided
        if (isset($data['content_type'])) { $fields[] = "content_type = :content_type"; $params[':content_type'] = $data['content_type']; }
        if (isset($data['content_data'])) { $fields[] = "content_data = :content_data"; $params[':content_data'] = EncryptionHelper::encrypt(trim($data['content_data'])); }
        if (array_key_exists('image_url', $data)) { $fields[] = "image_url = :image_url"; $params[':image_url'] = $data['image_url']; } // Allow setting to null
        if (array_key_exists('video_url', $data)) { $fields[] = "video_url = :video_url"; $params[':video_url'] = $data['video_url']; }
        if (array_key_exists('source_url', $data)) { $fields[] = "source_url = :source_url"; $params[':source_url'] = $data['source_url']; }
        if (array_key_exists('read_more_link', $data)) { $fields[] = "read_more_link = :read_more_link"; $params[':read_more_link'] = $data['read_more_link']; }
        if (isset($data['cycle_phase_association'])) { $fields[] = "cycle_phase_association = :cycle_phase_association"; $params[':cycle_phase_association'] = (empty($data['cycle_phase_association']) || $data['cycle_phase_association'] === 'all') ? 'any' : $data['cycle_phase_association']; }

        if (array_key_exists('symptom_association_keys', $data)) {
            $symptomKeysArray = $data['symptom_association_keys'] ?? [];
            if (is_string($symptomKeysArray)) $symptomKeysArray = json_decode($symptomKeysArray, true) ?: [];
            $params[':symptom_association_keys'] = !empty($symptomKeysArray) ? EncryptionHelper::encrypt(json_encode($symptomKeysArray)) : null;
            $fields[] = "symptom_association_keys = :symptom_association_keys";
        }
        if (array_key_exists('tags', $data)) {
            $tagsArray = $data['tags'] ?? [];
            if (is_string($tagsArray)) $tagsArray = json_decode($tagsArray, true) ?: [];
            $params[':tags'] = !empty($tagsArray) ? json_encode($tagsArray) : null;
            $fields[] = "tags = :tags";
        }
        if (isset($data['is_tutorial_topic'])) { $fields[] = "is_tutorial_topic = :is_tutorial_topic"; $params[':is_tutorial_topic'] = (int)(bool)$data['is_tutorial_topic']; }
        if (isset($data['is_active'])) { $fields[] = "is_active = :is_active"; $params[':is_active'] = (int)(bool)$data['is_active']; }

        if (empty($fields)) return false;

        $sql = "UPDATE educational_content SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        try {
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating educational content ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function deleteContent(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM educational_content WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting educational content ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function getContentById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM educational_content WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decryptRow($row) : null;
    }

    public function getContentBySlug(string $slug): ?array {
        $stmt = $this->db->prepare("SELECT * FROM educational_content WHERE slug = :slug AND is_active = TRUE");
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decryptRow($row) : null;
    }

    public function getTopics(?string $targetRole = null): array {
        $sql = "SELECT * FROM educational_content WHERE is_tutorial_topic = TRUE AND is_active = TRUE";
        $params = [];
        if ($targetRole) {
            $sql .= " AND (target_role = ? OR target_role = 'both')";
            $params[] = $targetRole;
        }
        $sql .= " ORDER BY sequence_order ASC, title ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $decryptedResults = [];
        foreach ($results as $row) $decryptedResults[] = $this->decryptRow($row);
        return $decryptedResults;
    }

    public function getContentByParentId(int $parentId, ?string $targetRole = null): array {
        $sql = "SELECT * FROM educational_content WHERE parent_id = ? AND is_tutorial_topic = FALSE AND is_active = TRUE";
        $params = [$parentId];
        if ($targetRole) {
            $sql .= " AND (target_role = ? OR target_role = 'both')";
            $params[] = $targetRole;
        }
        $sql .= " ORDER BY sequence_order ASC, title ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $decryptedResults = [];
        foreach ($results as $row) $decryptedResults[] = $this->decryptRow($row);
        return $decryptedResults;
    }

    public function listContent(array $filters = [], array $orderBy = ['sequence_order' => 'ASC', 'id' => 'ASC'], ?int $limit = null, ?int $offset = null): array {
        $sqlParts = ["SELECT * FROM educational_content"];
        $whereClauses = ["1=1"]; // Start with a tautology
        $params = [];

        if (isset($filters['is_active'])) {
            $whereClauses[] = "is_active = ?";
            $params[] = (int)(bool)$filters['is_active'];
        }
        if (isset($filters['is_tutorial_topic'])) {
            $whereClauses[] = "is_tutorial_topic = ?";
            $params[] = (int)(bool)$filters['is_tutorial_topic'];
        }
        if (!empty($filters['content_topic'])) {
            $whereClauses[] = "content_topic = ?";
            $params[] = $filters['content_topic'];
        }
         if (!empty($filters['target_role'])) {
            $whereClauses[] = "target_role = ?";
            $params[] = $filters['target_role'];
        }
        if (isset($filters['parent_id'])) { // Can be 0 or null for top-level, or an ID
            if ($filters['parent_id'] === null || $filters['parent_id'] === 0 || $filters['parent_id'] === 'NULL') {
                 $whereClauses[] = "parent_id IS NULL";
            } else {
                $whereClauses[] = "parent_id = ?";
                $params[] = (int)$filters['parent_id'];
            }
        }


        if (count($whereClauses) > 1) {
            $sqlParts[] = "WHERE " . implode(" AND ", array_slice($whereClauses, 1));
        }

        $orderClauses = [];
        foreach($orderBy as $field => $direction) {
            if (in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                $orderClauses[] = "$field $direction";
            }
        }
        if (!empty($orderClauses)) $sqlParts[] = "ORDER BY " . implode(', ', $orderClauses);
        else $sqlParts[] = "ORDER BY sequence_order ASC, id ASC"; // Default order

        if ($limit !== null) {
            $sqlParts[] = "LIMIT ?";
            $params[] = $limit;
            if ($offset !== null) {
                $sqlParts[] = "OFFSET ?";
                $params[] = $offset;
            }
        }

        $sql = implode(" ", $sqlParts);
        $stmt = $this->db->prepare($sql);

        try {
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $decryptedResults = [];
            foreach ($results as $row) $decryptedResults[] = $this->decryptRow($row);
            return $decryptedResults;
        } catch (PDOException $e) {
            error_log("Error listing educational content: " . $e->getMessage() . " SQL: " . $sql . " Params: " . json_encode($params));
            return [];
        }
    }

    /**
     * Retrieves content based on specific criteria, primarily for NotificationService.
     * Prioritizes symptom, then phase, then general.
     * @param array $criteria Possible keys: target_roles (array), cycle_phase, active_symptom_keys (array of "cat_sym" strings)
     * @param int $limit Max number of records to return
     * @return array
     */
    public function getContentForNotifications(array $criteria, int $limit = 1): array {
        $sqlBase = "SELECT * FROM educational_content WHERE is_active = TRUE";
        $params = [];
        $whereClauses = [];

        // Target Role
        if (!empty($criteria['target_roles']) && is_array($criteria['target_roles'])) {
             $placeholders = implode(',', array_fill(0, count($criteria['target_roles']), '?'));
             $whereClauses[] = "target_role IN ({$placeholders})";
             $params = array_merge($params, $criteria['target_roles']);
        }

        $finalResults = [];

        // 1. Try Symptom + Phase specific
        if (!empty($criteria['active_symptom_keys']) && !empty($criteria['cycle_phase'])) {
            $symptomPhaseSql = $sqlBase . (empty($whereClauses) ? " WHERE " : " AND ") . implode(" AND ", $whereClauses);
            $symptomPhaseSql .= " AND (cycle_phase_association = ? OR cycle_phase_association = 'any')";
            $symptomPhaseSql .= " ORDER BY RAND()"; // To get variety if multiple match a symptom

            $currentParams = array_merge($params, [$criteria['cycle_phase']]);
            $stmt = $this->db->prepare($symptomPhaseSql);
            $stmt->execute($currentParams);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($candidates as $row) {
                $decryptedRow = $this->decryptRow($row);
                if (!empty($decryptedRow['symptom_association_keys'])) {
                    if (count(array_intersect($criteria['active_symptom_keys'], $decryptedRow['symptom_association_keys'])) > 0) {
                        $finalResults[] = $decryptedRow;
                        if (count($finalResults) >= $limit) break;
                    }
                }
            }
            if (count($finalResults) >= $limit) return array_slice($finalResults, 0, $limit);
        }

        // 2. Try Phase specific (if not enough from symptoms)
        if (!empty($criteria['cycle_phase'])) {
            $phaseSql = $sqlBase . (empty($whereClauses) ? " WHERE " : " AND ") . implode(" AND ", $whereClauses);
            $phaseSql .= " AND (cycle_phase_association = ? OR cycle_phase_association = 'any') AND symptom_association_keys IS NULL"; // Prioritize non-symptom specific if symptom search failed
            $phaseSql .= " ORDER BY RAND() LIMIT ?";

            $currentParams = array_merge($params, [$criteria['cycle_phase'], $limit - count($finalResults)]);
            $stmt = $this->db->prepare($phaseSql);
            $stmt->execute($currentParams);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($candidates as $row) {
                 $finalResults[] = $this->decryptRow($row);
                 if (count($finalResults) >= $limit) break;
            }
            if (count($finalResults) >= $limit) return array_slice($finalResults, 0, $limit);
        }

        // 3. Try General (target_role only, phase 'any', no symptoms)
        $generalSql = $sqlBase . (empty($whereClauses) ? " WHERE " : " AND ") . implode(" AND ", $whereClauses);
        $generalSql .= " AND cycle_phase_association = 'any' AND symptom_association_keys IS NULL";
        $generalSql .= " ORDER BY RAND() LIMIT ?";

        $currentParams = array_merge($params, [$limit - count($finalResults)]);
        $stmt = $this->db->prepare($generalSql);
        $stmt->execute($currentParams);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($candidates as $row) {
             $finalResults[] = $this->decryptRow($row);
             if (count($finalResults) >= $limit) break;
        }

        return array_slice($finalResults, 0, $limit);
    }
}
?>
