<?php
/**
 * Task management: CRUD + comments, linked optionally to leads/customers/
 * proposals/quotations via related_type/related_id.
 */
final class TaskService
{
    public function list(int $businessId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["t.business_id = ?", "t.deleted_at IS NULL"];
        $params = [$businessId];
        if (!empty($filters['status'])) {
            $where[] = "t.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['assigned_user_id'])) {
            $where[] = "t.assigned_user_id = ?";
            $params[] = $filters['assigned_user_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = "t.title LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM tasks t WHERE {$whereSql}", $params)['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT t.*, u.name AS assigned_user_name FROM tasks t LEFT JOIN users u ON u.id = t.assigned_user_id
             WHERE {$whereSql} ORDER BY t.due_at IS NULL, t.due_at ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function create(int $businessId, int $userId, array $data): array
    {
        Database::query(
            "INSERT INTO tasks (business_id, assigned_user_id, created_by, related_type, related_id, title, description, status, priority, due_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())",
            [
                $businessId, $data['assigned_user_id'] ?? null, $userId, $data['related_type'] ?? null, $data['related_id'] ?? null,
                Security::cleanString($data['title']), $data['description'] ?? null, $data['priority'] ?? 'medium', $data['due_at'] ?? null,
            ]
        );
        return Database::fetchOne("SELECT * FROM tasks WHERE id = ?", [(int) Database::lastInsertId()]);
    }

    public function update(int $businessId, int $id, array $data): array
    {
        $sets = [];
        $params = [];
        foreach (['title', 'description', 'status', 'priority', 'due_at', 'assigned_user_id'] as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = ?";
                $params[] = $data[$col];
            }
        }
        if (isset($data['status']) && $data['status'] === 'completed') {
            $sets[] = "completed_at = NOW()";
        }
        if (!empty($sets)) {
            $params[] = $id;
            $params[] = $businessId;
            Database::query("UPDATE tasks SET " . implode(', ', $sets) . " WHERE id = ? AND business_id = ?", $params);
        }
        return Database::fetchOne("SELECT * FROM tasks WHERE id = ?", [$id]);
    }

    public function delete(int $businessId, int $id): void
    {
        Database::query("UPDATE tasks SET deleted_at = NOW() WHERE id = ? AND business_id = ?", [$id, $businessId]);
    }

    public function addComment(int $taskId, int $userId, string $comment): array
    {
        Database::query("INSERT INTO task_comments (task_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())", [$taskId, $userId, Security::cleanString($comment)]);
        return Database::fetchOne("SELECT * FROM task_comments WHERE id = ?", [(int) Database::lastInsertId()]);
    }
}
