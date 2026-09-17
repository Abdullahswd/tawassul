<?php
/**
 * ============================================================
 *  Eduroad - Support / FAQ / Contact Channels Helpers
 *  File: config/support_functions.php
 * ============================================================
 * Requires functions.php (db(), e(), saveChatAttachmentUpload(), createNotification()).
 */

if (!function_exists('db')) {
    require_once __DIR__ . '/functions.php';
}

/**
 * Create/ensure all support tables (safe auto-upgrade).
 */
function ensureSupportTables(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS `support_faqs` (
          `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `audience`   ENUM('student','academic') NOT NULL DEFAULT 'student',
          `question`   VARCHAR(500) NOT NULL,
          `answer`     TEXT NOT NULL,
          `sort_order` INT NOT NULL DEFAULT 0,
          `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_faq_aud` (`audience`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS `support_channels` (
          `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `type`       ENUM('whatsapp','telegram','email','phone','instagram','twitter','website','other') NOT NULL DEFAULT 'other',
          `label`      VARCHAR(150) NOT NULL,
          `value`      VARCHAR(255) NOT NULL,
          `icon`       VARCHAR(20) NOT NULL DEFAULT '📞',
          `sort_order` INT NOT NULL DEFAULT 0,
          `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS `support_conversations` (
          `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id`    INT UNSIGNED NOT NULL,
          `user_type`  ENUM('student','academic') NOT NULL,
          `subject`    VARCHAR(255) NOT NULL,
          `status`     ENUM('open','pending','closed') NOT NULL DEFAULT 'open',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_conv_user` (`user_id`, `user_type`),
          INDEX `idx_conv_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS `support_messages` (
          `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `conversation_id` INT UNSIGNED NOT NULL,
          `sender_type`     ENUM('user','admin') NOT NULL,
          `sender_id`       INT UNSIGNED NOT NULL,
          `content`         TEXT NULL,
          `file_path`       VARCHAR(500) NULL,
          `file_name`       VARCHAR(255) NULL,
          `file_size`       INT UNSIGNED NULL,
          `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
          `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_sm_conv` (`conversation_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* ─────────────────────────────────────────────
   FAQ
───────────────────────────────────────────── */
function getSupportFaqs(string $audience = ''): array {
    ensureSupportTables();
    if ($audience !== '' && in_array($audience, ['student', 'academic'], true)) {
        $stmt = db()->prepare('SELECT * FROM support_faqs WHERE audience = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$audience]);
        return $stmt->fetchAll();
    }
    return db()->query('SELECT * FROM support_faqs ORDER BY audience ASC, sort_order ASC, id ASC')->fetchAll();
}

function saveSupportFaq(array $d): int {
    ensureSupportTables();
    $audience = $d['audience'] === 'academic' ? 'academic' : 'student';
    $id = (int) ($d['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('UPDATE support_faqs SET audience=?, question=?, answer=?, sort_order=?, is_active=? WHERE id = ?')
            ->execute([$audience, $d['question'], $d['answer'], (int)$d['sort_order'], (int)$d['is_active'] ? 1 : 0, $id]);
        return $id;
    }
    db()->prepare('INSERT INTO support_faqs (audience, question, answer, sort_order, is_active) VALUES (?,?,?,?,?)')
        ->execute([$audience, $d['question'], $d['answer'], (int)$d['sort_order'], (int)$d['is_active'] ? 1 : 0]);
    return (int) db()->lastInsertId();
}

function deleteSupportFaq(int $id): void {
    ensureSupportTables();
    db()->prepare('DELETE FROM support_faqs WHERE id = ?')->execute([$id]);
}
/* ─────────────────────────────────────────────
   CONTACT CHANNELS
───────────────────────────────────────────── */
function getSupportChannels(bool $activeOnly = true): array {
    ensureSupportTables();
    if ($activeOnly) {
        return db()->query('SELECT * FROM support_channels WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
    }
    return db()->query('SELECT * FROM support_channels ORDER BY sort_order ASC, id ASC')->fetchAll();
}

function saveSupportChannel(array $d): int {
    ensureSupportTables();
    $id = (int) ($d['id'] ?? 0);
    $type = in_array($d['type'] ?? '', ['whatsapp','telegram','email','phone','instagram','twitter','website','other'], true) ? $d['type'] : 'other';
    if ($id > 0) {
        db()->prepare('UPDATE support_channels SET type=?, label=?, value=?, icon=?, sort_order=?, is_active=? WHERE id = ?')
            ->execute([$type, $d['label'], $d['value'], $d['icon'] ?: '📞', (int)$d['sort_order'], (int)$d['is_active'] ? 1 : 0, $id]);
        return $id;
    }
    db()->prepare('INSERT INTO support_channels (type, label, value, icon, sort_order, is_active) VALUES (?,?,?,?,?,?)')
        ->execute([$type, $d['label'], $d['value'], $d['icon'] ?: '📞', (int)$d['sort_order'], (int)$d['is_active'] ? 1 : 0]);
    return (int) db()->lastInsertId();
}

function deleteSupportChannel(int $id): void {
    ensureSupportTables();
    db()->prepare('DELETE FROM support_channels WHERE id = ?')->execute([$id]);
}

/* ─────────────────────────────────────────────
   CONVERSATIONS & MESSAGES
───────────────────────────────────────────── */
function createSupportConversation(int $userId, string $userType, string $subject): int {
    ensureSupportTables();
    // إعادة فتح آخر محادثة مفتوحة لنفس المستخدم إن وُجدت
    $stmt = db()->prepare("SELECT id FROM support_conversations WHERE user_id = ? AND user_type = ? AND status != 'closed' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $userType]);
    $existing = $stmt->fetchColumn();
    if ($existing) return (int) $existing;

    db()->prepare('INSERT INTO support_conversations (user_id, user_type, subject) VALUES (?,?,?)')
        ->execute([$userId, $userType, $subject]);
    return (int) db()->lastInsertId();
}

function getSupportConversationsForUser(int $userId, string $userType): array {
    ensureSupportTables();
    $stmt = db()->prepare(
        "SELECT c.*,
                (SELECT COUNT(*) FROM support_messages m WHERE m.conversation_id = c.id AND m.sender_type = 'admin' AND m.is_read = 0) AS unread_admin
         FROM support_conversations c
         WHERE c.user_id = ? AND c.user_type = ?
         ORDER BY c.updated_at DESC"
    );
    $stmt->execute([$userId, $userType]);
    return $stmt->fetchAll();
}

function getSupportConversationsForAdmin(string $userType, string $statusFilter = ''): array {
    ensureSupportTables();
    $sql = "SELECT c.*, 
                   COALESCE(u.name, a.name) AS user_name,
                   COALESCE(u.email, a.email) AS user_email,
                   (SELECT COUNT(*) FROM support_messages m WHERE m.conversation_id = c.id AND m.sender_type = 'user' AND m.is_read = 0) AS unread_user,
                   (SELECT content FROM support_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                   (SELECT created_at FROM support_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_time
            FROM support_conversations c
            LEFT JOIN users u ON c.user_type = 'student' AND c.user_id = u.id
            LEFT JOIN academics a ON c.user_type = 'academic' AND c.user_id = a.id
            WHERE c.user_type = ?";
    $args = [$userType];
    if ($statusFilter !== '' && in_array($statusFilter, ['open', 'pending', 'closed'], true)) {
        $sql .= ' AND c.status = ?';
        $args[] = $statusFilter;
    }
    $sql .= ' ORDER BY c.updated_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function getSupportConversationMessages(int $conversationId): array {
    ensureSupportTables();
    $stmt = db()->prepare('SELECT * FROM support_messages WHERE conversation_id = ? ORDER BY id ASC');
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll();
}

function sendSupportMessage(int $conversationId, string $senderType, int $senderId, string $content, ?array $fileInfo = null): int {
    ensureSupportTables();
    $stmt = db()->prepare(
        'INSERT INTO support_messages (conversation_id, sender_type, sender_id, content, file_path, file_name, file_size)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $conversationId, $senderType, $senderId, $content,
        $fileInfo['path'] ?? null,
        $fileInfo['name'] ?? null,
        $fileInfo['size'] ?? null,
    ]);
    return (int) db()->lastInsertId();
}

function setSupportConversationStatus(int $conversationId, string $status): void {
    ensureSupportTables();
    if (!in_array($status, ['open', 'pending', 'closed'], true)) return;
    db()->prepare('UPDATE support_conversations SET status = ? WHERE id = ?')->execute([$status, $conversationId]);
}

function countUnreadSupportForUser(int $userId, string $userType): int {
    ensureSupportTables();
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM support_messages m JOIN support_conversations c ON c.id = m.conversation_id
         WHERE c.user_id = ? AND c.user_type = ? AND m.sender_type = 'admin' AND m.is_read = 0"
    );
    $stmt->execute([$userId, $userType]);
    return (int) $stmt->fetchColumn();
}

function countUnreadSupportForAdmin(string $userType): int {
    ensureSupportTables();
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM support_messages m JOIN support_conversations c ON c.id = m.conversation_id
         WHERE c.user_type = ? AND m.sender_type = 'user' AND m.is_read = 0"
    );
    $stmt->execute([$userType]);
    return (int) $stmt->fetchColumn();
}