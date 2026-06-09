<?php
/**
 * ============================================================
 *  Tawassul - Shared Helper Functions
 *  File: config/functions.php
 * ============================================================
 */

require_once __DIR__ . '/db.php';

/* ─────────────────────────────────────────────
   USERS
───────────────────────────────────────────── */

function getUserByEmail(string $email): ?array {
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function getUserById(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function createUser(string $name, string $email, string $password, string $phone = ''): int {
    $initials = mb_substr($name, 0, 1, 'UTF-8') . mb_substr(explode(' ', $name)[1] ?? '', 0, 1, 'UTF-8');
    $stmt = db()->prepare(
        'INSERT INTO users (name, email, password, phone, role, avatar_initials)
         VALUES (?, ?, ?, ?, "student", ?)'
    );
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $initials]);
    return (int) db()->lastInsertId();
}

/* ─────────────────────────────────────────────
   ACADEMICS
───────────────────────────────────────────── */

function getAcademicByEmail(string $email): ?array {
    $stmt = db()->prepare('SELECT * FROM academics WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function getAcademicById(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM academics WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getAllAcademics(string $status = 'approved'): array {
    $stmt = db()->prepare(
        'SELECT a.*, GROUP_CONCAT(s.name SEPARATOR ", ") AS services_names
         FROM academics a
         LEFT JOIN academic_services acs ON a.id = acs.academic_id
         LEFT JOIN services s ON acs.service_id = s.id
         WHERE a.status = ?
         GROUP BY a.id
         ORDER BY a.rating DESC'
    );
    $stmt->execute([$status]);
    return $stmt->fetchAll();
}

/* ─────────────────────────────────────────────
   SERVICES
───────────────────────────────────────────── */

function getAllServices(bool $activeOnly = true): array {
    $sql  = 'SELECT * FROM services';
    $args = [];
    if ($activeOnly) {
        $sql  .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function getServiceById(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM services WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* ─────────────────────────────────────────────
   PACKAGES
───────────────────────────────────────────── */

function getAllPackages(bool $activeOnly = true): array {
    $sql = 'SELECT * FROM packages' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY price ASC';
    return db()->query($sql)->fetchAll();
}

/* ─────────────────────────────────────────────
   ORDERS
───────────────────────────────────────────── */

/**
 * Generate the next order number e.g. ORD-20260001
 */
function generateOrderNumber(): string {
    $year = date('Y');
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM orders WHERE order_number LIKE CONCAT('ORD-', ?, '%')"
    );
    $stmt->execute([$year]);
    $count = (int) $stmt->fetchColumn();
    return 'ORD-' . $year . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function createOrder(array $data): int {
    $number = generateOrderNumber();
    $stmt   = db()->prepare(
        'INSERT INTO orders
           (order_number, student_id, service_id, package_id, specialty,
            academic_level, language, description, deadline, amount, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,"new")'
    );
    $stmt->execute([
        $number,
        $data['student_id'],
        $data['service_id'],
        $data['package_id']     ?? null,
        $data['specialty']      ?? null,
        $data['academic_level'] ?? null,
        $data['language']       ?? 'العربية',
        $data['description']    ?? null,
        $data['deadline']       ?? null,
        $data['amount']         ?? 0,
    ]);
    return (int) db()->lastInsertId();
}

function getOrdersByStudent(int $studentId): array {
    $stmt = db()->prepare(
        'SELECT o.*, s.name AS service_name, s.icon AS service_icon,
                p.name AS package_name, a.name AS academic_name
         FROM orders o
         LEFT JOIN services s  ON o.service_id  = s.id
         LEFT JOIN packages p  ON o.package_id  = p.id
         LEFT JOIN academics a ON o.academic_id  = a.id
         WHERE o.student_id = ?
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

function getOrdersByAcademic(int $academicId): array {
    $stmt = db()->prepare(
        'SELECT o.*, s.name AS service_name, s.icon AS service_icon,
                p.name AS package_name, u.name AS student_name
         FROM orders o
         LEFT JOIN services s ON o.service_id  = s.id
         LEFT JOIN packages p ON o.package_id  = p.id
         LEFT JOIN users u    ON o.student_id   = u.id
         WHERE o.academic_id = ?
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([$academicId]);
    return $stmt->fetchAll();
}

function getOrderById(int $orderId): ?array {
    $stmt = db()->prepare(
        'SELECT o.*, s.name AS service_name, s.icon AS service_icon,
                p.name AS package_name,
                u.name AS student_name, u.email AS student_email,
                a.name AS academic_name, a.email AS academic_email
         FROM orders o
         LEFT JOIN services s  ON o.service_id  = s.id
         LEFT JOIN packages p  ON o.package_id  = p.id
         LEFT JOIN users u     ON o.student_id   = u.id
         LEFT JOIN academics a ON o.academic_id  = a.id
         WHERE o.id = ? LIMIT 1'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetch() ?: null;
}

function updateOrderStatus(int $orderId, string $status): bool {
    $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
    return $stmt->execute([$status, $orderId]);
}

/* ─────────────────────────────────────────────
   PAYMENTS
───────────────────────────────────────────── */

function generatePaymentNumber(): string {
    $year  = date('Y');
    $stmt  = db()->prepare(
        "SELECT COUNT(*) FROM payments WHERE payment_number LIKE CONCAT('PAY-', ?, '%')"
    );
    $stmt->execute([$year]);
    $count = (int) $stmt->fetchColumn();
    return 'PAY-' . $year . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function createPayment(array $data): int {
    $fee    = round($data['amount'] * 0.15, 2);
    $net    = round($data['amount'] - $fee, 2);
    $number = generatePaymentNumber();

    $stmt = db()->prepare(
        'INSERT INTO payments
           (payment_number, order_id, student_id, amount, platform_fee,
            academic_net, method, status)
         VALUES (?,?,?,?,?,?,?,"pending")'
    );
    $stmt->execute([
        $number,
        $data['order_id'],
        $data['student_id'],
        $data['amount'],
        $fee,
        $net,
        $data['method'] ?? 'credit_card',
    ]);
    return (int) db()->lastInsertId();
}

function getPaymentsByStudent(int $studentId): array {
    $stmt = db()->prepare(
        'SELECT p.*, o.order_number
         FROM payments p
         JOIN orders o ON p.order_id = o.id
         WHERE p.student_id = ?
         ORDER BY p.created_at DESC'
    );
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

/* ─────────────────────────────────────────────
   MESSAGES / CONVERSATIONS
───────────────────────────────────────────── */

function getOrCreateConversation(int $orderId, int $studentId, int $academicId): int {
    $stmt = db()->prepare('SELECT id FROM conversations WHERE order_id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row  = $stmt->fetch();
    if ($row) return (int) $row['id'];

    $stmt = db()->prepare(
        'INSERT INTO conversations (order_id, student_id, academic_id) VALUES (?,?,?)'
    );
    $stmt->execute([$orderId, $studentId, $academicId]);
    return (int) db()->lastInsertId();
}

function getConversationMessages(int $conversationId): array {
    $stmt = db()->prepare(
        'SELECT * FROM messages WHERE conversation_id = ? ORDER BY sent_at ASC'
    );
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll();
}

function sendMessage(int $conversationId, int $senderId, string $senderType, string $content): int {
    $stmt = db()->prepare(
        'INSERT INTO messages (conversation_id, sender_id, sender_type, content) VALUES (?,?,?,?)'
    );
    $stmt->execute([$conversationId, $senderId, $senderType, $content]);
    return (int) db()->lastInsertId();
}

/* ─────────────────────────────────────────────
   REVIEWS
───────────────────────────────────────────── */

function addReview(int $orderId, int $studentId, int $academicId, int $rating, string $comment = ''): void {
    $stmt = db()->prepare(
        'INSERT INTO reviews (order_id, student_id, academic_id, rating, comment)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment)'
    );
    $stmt->execute([$orderId, $studentId, $academicId, $rating, $comment]);

    // Update academic average rating
    $stmt = db()->prepare(
        'UPDATE academics a
         SET rating       = (SELECT AVG(r.rating) FROM reviews r WHERE r.academic_id = a.id),
             total_reviews = (SELECT COUNT(*) FROM reviews r WHERE r.academic_id = a.id)
         WHERE a.id = ?'
    );
    $stmt->execute([$academicId]);
}

/* ─────────────────────────────────────────────
   NOTIFICATIONS
───────────────────────────────────────────── */

function createNotification(int $userId, string $userType, string $title, string $message, string $icon = '🔔', string $link = ''): void {
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, user_type, title, message, icon, link)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([$userId, $userType, $title, $message, $icon, $link]);
}

function getNotifications(int $userId, string $userType, bool $unreadOnly = false): array {
    $sql  = 'SELECT * FROM notifications WHERE user_id = ? AND user_type = ?';
    $args = [$userId, $userType];
    if ($unreadOnly) {
        $sql  .= ' AND is_read = 0';
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 20';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function markNotificationsRead(int $userId, string $userType): void {
    $stmt = db()->prepare(
        'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = ?'
    );
    $stmt->execute([$userId, $userType]);
}

function countUnreadNotifications(int $userId, string $userType): int {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_type = ? AND is_read = 0'
    );
    $stmt->execute([$userId, $userType]);
    return (int) $stmt->fetchColumn();
}

/* ─────────────────────────────────────────────
   ADMIN STATS
───────────────────────────────────────────── */

function getAdminDashboardStats(): array {
    $pdo = db();
    return [
        'total_students'  => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
        'total_academics' => (int) $pdo->query("SELECT COUNT(*) FROM academics WHERE status='approved'")->fetchColumn(),
        'total_orders'    => (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'total_revenue'   => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn(),
        'pending_academics' => (int) $pdo->query("SELECT COUNT(*) FROM academics WHERE status='pending'")->fetchColumn(),
        'new_orders'      => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(),
    ];
}

/* ─────────────────────────────────────────────
   UTILITY
───────────────────────────────────────────── */

/**
 * Escape output safely for HTML
 */
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format date to Arabic readable format
 */
function formatDate(string $date): string {
    if (!$date) return '-';
    return date('Y/m/d', strtotime($date));
}

/**
 * Format currency
 */
function formatMoney(float $amount): string {
    return number_format($amount, 2) . ' ر.س';
}

/**
 * Map status code to Arabic label & badge class
 */
function orderStatusLabel(string $status): array {
    return match($status) {
        'new'         => ['label' => 'جديد',         'class' => 'badge-info'],
        'accepted'    => ['label' => 'مقبول',         'class' => 'badge-primary'],
        'in_progress' => ['label' => 'قيد التنفيذ',   'class' => 'badge-warning'],
        'revision'    => ['label' => 'تحت المراجعة',  'class' => 'badge-warning'],
        'completed'   => ['label' => 'مكتمل',         'class' => 'badge-success'],
        'cancelled'   => ['label' => 'ملغي',          'class' => 'badge-danger'],
        default       => ['label' => $status,          'class' => 'badge-secondary'],
    };
}

function paymentStatusLabel(string $status): array {
    return match($status) {
        'paid'     => ['label' => 'مدفوع',   'class' => 'badge-success'],
        'pending'  => ['label' => 'معلق',    'class' => 'badge-warning'],
        'failed'   => ['label' => 'فاشل',    'class' => 'badge-danger'],
        'refunded' => ['label' => 'مُسترجع', 'class' => 'badge-secondary'],
        default    => ['label' => $status,   'class' => 'badge-secondary'],
    };
}
