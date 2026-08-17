<?php
/**
 * Support ticket management for both business users (create/view own
 * tickets) and admins (view all, reply, change status).
 */
final class SupportService
{
    public function createTicket(?int $businessId, int $userId, string $subject, string $description, string $priority = 'medium'): array
    {
        $uuid = $this->uuid4();
        Database::query(
            "INSERT INTO support_tickets (uuid, business_id, user_id, subject, description, priority, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())",
            [$uuid, $businessId, $userId, Security::cleanString($subject), Security::cleanString($description), $priority]
        );
        return Database::fetchOne("SELECT * FROM support_tickets WHERE id = ?", [(int) Database::lastInsertId()]);
    }

    public function listForUser(int $userId): array
    {
        return Database::fetchAll("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
    }

    public function listAll(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = "st.status = ?";
            $params[] = $filters['status'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetchOne("SELECT COUNT(*) c FROM support_tickets st WHERE {$whereSql}", $params)['c'] ?? 0);
        $rows = Database::fetchAll(
            "SELECT st.*, u.name AS user_name, u.email AS user_email, b.name AS business_name
             FROM support_tickets st LEFT JOIN users u ON u.id = st.user_id LEFT JOIN businesses b ON b.id = st.business_id
             WHERE {$whereSql} ORDER BY st.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function findWithReplies(int $ticketId): ?array
    {
        $ticket = Database::fetchOne(
            "SELECT st.*, u.name AS user_name FROM support_tickets st LEFT JOIN users u ON u.id = st.user_id WHERE st.id = ?",
            [$ticketId]
        );
        if ($ticket === null) {
            return null;
        }
        $ticket['replies'] = Database::fetchAll(
            "SELECT sr.*, u.name AS user_name FROM support_replies sr LEFT JOIN users u ON u.id = sr.user_id
             WHERE sr.ticket_id = ? ORDER BY sr.created_at ASC",
            [$ticketId]
        );
        return $ticket;
    }

    public function reply(int $ticketId, int $userId, string $message, bool $isAdmin): array
    {
        Database::query(
            "INSERT INTO support_replies (ticket_id, user_id, is_admin_reply, message, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$ticketId, $userId, $isAdmin ? 1 : 0, Security::cleanString($message)]
        );
        // Capture the id immediately after the INSERT, before any other query.
        $replyId = (int) Database::lastInsertId();

        Database::query(
            "UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?",
            [$isAdmin ? 'in_progress' : 'open', $ticketId]
        );

        return Database::fetchOne("SELECT * FROM support_replies WHERE id = ?", [$replyId]);
    }

    public function setStatus(int $ticketId, string $status, int $adminUserId): void
    {
        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            Response::validationError(['status' => ['Invalid status.']]);
        }
        Database::query("UPDATE support_tickets SET status = ?, assigned_admin_id = ? WHERE id = ?", [$status, $adminUserId, $ticketId]);
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
