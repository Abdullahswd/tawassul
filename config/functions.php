<?php
/**
 * ============================================================
 *  Eduroad - Shared Helper Functions
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
        'SELECT a.*,
            (SELECT GROUP_CONCAT(s.name SEPARATOR ", ")
             FROM academic_services acs
             JOIN services s ON acs.service_id = s.id
             WHERE acs.academic_id = a.id) AS services_names
         FROM academics a
         WHERE a.status = ?
         ORDER BY a.rating DESC'
    );
    $stmt->execute([$status]);
    return $stmt->fetchAll();
}

/* ─────────────────────────────────────────────
   FEATURED ACADEMICS (الأكاديميون المتميزون)
───────────────────────────────────────────── */

function getAllFeaturedAcademics(bool $activeOnly = true): array {
    $sql = 'SELECT * FROM featured_academics';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

/* ─────────────────────────────────────────────
   FEATURED SERVICES (الخدمات المميزة — جدول منفصل)
───────────────────────────────────────────── */

function getAllFeaturedServices(bool $activeOnly = true): array {
    try {
        $sql = 'SELECT f.*, s.name AS service_name, s.icon AS service_icon,
                       s.icon_image AS service_icon_image, s.price AS service_price,
                       s.level AS service_level
                FROM featured_services f
                LEFT JOIN services s ON s.id = f.service_id';
        if ($activeOnly) {
            $sql .= ' WHERE f.is_active = 1';
        }
        $sql .= ' ORDER BY f.sort_order ASC, f.id ASC';
        return db()->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
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

/**
 * Get ALL the student's currently active package subscriptions.
 * A subscription is "active" when it is lifetime (expires_at IS NULL) or its
 * expiry date is still in the future. Students may hold multiple active packages.
 */
function getAllActivePackageSubscriptions(int $studentId): array {
    $stmt = db()->prepare(
        'SELECT ps.id            AS sub_id,
                ps.started_at,
                ps.expires_at,
                ps.status        AS sub_status,
                p.id             AS package_id,
                p.name           AS package_name,
                p.price          AS package_price,
                p.original_price AS package_original_price,
                p.icon           AS package_icon,
                p.color          AS package_color,
                p.service_ids,
                p.features_json,
                p.max_tasks
         FROM package_subscriptions ps
         JOIN packages p ON p.id = ps.package_id
         WHERE ps.student_id = ? AND ps.status = "active"
           AND (ps.expires_at IS NULL OR ps.expires_at > NOW())
         ORDER BY ps.id ASC'
    );
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

/**
 * Get the student's currently active package subscription (if any).
 * A subscription is "active" when it is lifetime (expires_at IS NULL) or its
 * expiry date is still in the future.
 */
function getActivePackageSubscription(int $studentId): ?array {
    $all = getAllActivePackageSubscriptions($studentId);
    return $all ? $all[0] : null;
}

/**
 * Active package subscriptions whose payment receipt is approved (or has no
 * receipt at all = activated directly). Benefits are only granted for these.
 */
function getApprovedActivePackageSubscriptions(int $studentId): array {
    $result = [];
    foreach (getAllActivePackageSubscriptions($studentId) as $sub) {
        $rcpt = getReceiptForSubscription((int) $sub['sub_id']);
        $status = $rcpt['status'] ?? 'approved'; // بدون إشعار = مفعّل مباشرة
        if ($status !== 'approved') continue; // قيد المراجعة أو مرفوض → لا مزايا بعد
        $sub['receipt_status'] = $status;
        $result[] = $sub;
    }
    return $result;
}

/**
 * Create a new lifetime active subscription for the student on the given package.
 * The subscription is NOT date-limited (expires_at = NULL).
 * Students MAY hold multiple active packages; if an active subscription already
 * exists on the same package its id is returned instead of duplicating it.
 */
function subscribeStudentToPackage(int $studentId, int $packageId): int {
    $db = db();

    // إعادة استخدام الاشتراك النشط الحالي لنفس الباقة (إن وُجد) دون تكرار
    foreach (getAllActivePackageSubscriptions($studentId) as $sub) {
        if ((int) $sub['package_id'] === $packageId) {
            return (int) $sub['sub_id'];
        }
    }

    $db->prepare(
        'INSERT INTO package_subscriptions (student_id, package_id, started_at, expires_at, status)
         VALUES (?, ?, NOW(), NULL, "active")'
    )->execute([$studentId, $packageId]);

    return (int) $db->lastInsertId();
}

// ── تتبع استهلاك الخدمات المشمولة في الباقة (مرة واحدة فقط لكل خدمة) ──

/**
 * Service IDs already consumed (used) by the student from their active package.
 */
function usedPackageServiceIds(int $studentId): array {
    // ضمان وجود جدول الاستهلاك (ترقية تلقائية آمنة)
    db()->exec("CREATE TABLE IF NOT EXISTS `package_subscription_uses` (
          `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `subscription_id` INT UNSIGNED NOT NULL,
          `service_id`      INT UNSIGNED NOT NULL,
          `used_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_use_sub_service` (`subscription_id`, `service_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $stmt = db()->prepare(
        'SELECT u.service_id
         FROM package_subscription_uses u
         JOIN package_subscriptions ps ON ps.id = u.subscription_id
         WHERE ps.student_id = ?'
    );
    $stmt->execute([$studentId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Marks a package-included service as consumed for one of the student's
 * approved active subscriptions that covers this service.
 * Returns false if no covering subscription or it was already used.
 */
function consumePackageService(int $studentId, int $serviceId): bool {
    $subId = null;
    foreach (getApprovedActivePackageSubscriptions($studentId) as $sub) {
        if (in_array($serviceId, activeSubscriptionServiceIds($sub), true)) {
            $subId = (int) $sub['sub_id'];
            break;
        }
    }
    if ($subId === null) return false;

    $db = db();
    try {
        $db->prepare(
            'INSERT INTO package_subscription_uses (subscription_id, service_id, used_at)
             VALUES (?, ?, NOW())'
        )->execute([$subId, $serviceId]);
        return true;
    } catch (Exception $e) {
        // تضارب المفتاح الفريد = الخدمة مستهلكة مسبقاً
        return false;
    }
}

/**
 * Union of service IDs covered by ALL of the student's approved active
 * package subscriptions (empty if none).
 */
function studentIncludedServiceIds(int $studentId): array {
    $all = [];
    foreach (getApprovedActivePackageSubscriptions($studentId) as $sub) {
        foreach (activeSubscriptionServiceIds($sub) as $id) {
            $all[(int) $id] = (int) $id;
        }
    }
    return array_values($all);
}

/** Remaining (not-yet-consumed) included services across all active subscriptions. */
function remainingIncludedServiceIds(int $studentId): array {
    $included   = studentIncludedServiceIds($studentId);
    $used       = usedPackageServiceIds($studentId);
    $result     = [];
    foreach ($included as $id) {
        if (!in_array($id, $used, true)) $result[] = $id;
    }
    return $result;
}

/**
 * List of service IDs covered by the active subscription (empty if none).
 */
function activeSubscriptionServiceIds(?array $subscription): array {
    if (!$subscription) return [];
    $ids = json_decode($subscription['service_ids'] ?? '[]', true);
    if (!is_array($ids)) return [];
    return array_map('intval', $ids);
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
         VALUES (?,?,?,?,?,?,?,?,?,?,"pending_assignment")'
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

function getAssignedAcademics(int $orderId): array {
    $stmt = db()->prepare(
        'SELECT a.id, a.name, a.email, a.phone, a.specialty, a.degree, a.avatar_initials, a.rating,
                oa.status AS assignment_status, oa.assigned_at, oa.response_at
         FROM order_assignments oa
         JOIN academics a ON oa.academic_id = a.id
         WHERE oa.order_id = ?
         ORDER BY oa.assigned_at ASC'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
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
        'SELECT m.*,
                CASE
                    WHEN m.sender_type = \'student\'  THEN u.name
                    WHEN m.sender_type = \'academic\' THEN a.name
                    ELSE \'الإدارة\'
                END AS sender_name
         FROM messages m
         LEFT JOIN users    u ON m.sender_id = u.id AND m.sender_type = \'student\'
         LEFT JOIN academics a ON m.sender_id = a.id AND m.sender_type = \'academic\'
         WHERE m.conversation_id = ? ORDER BY m.sent_at ASC'
    );
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll();
}

/**
 * Ensure the messages table has chat file-attachment columns (safe auto-upgrade).
 */
function ensureMessageFileColumns(): void {
    try {
        $cols = db()->query("SHOW COLUMNS FROM `messages` LIKE 'file_path'")->fetchAll();
        if (empty($cols)) {
            db()->exec("ALTER TABLE `messages`
                ADD COLUMN `file_path` VARCHAR(500) DEFAULT NULL AFTER `content`,
                ADD COLUMN `file_name` VARCHAR(255) DEFAULT NULL AFTER `file_path`,
                ADD COLUMN `file_type` VARCHAR(100) DEFAULT NULL AFTER `file_name`,
                ADD COLUMN `file_size` INT UNSIGNED DEFAULT NULL AFTER `file_type`");
        }
    } catch (Exception $e) {
        error_log('ensureMessageFileColumns failed: ' . $e->getMessage());
    }
}

/**
 * Validate & store a chat file attachment (image or document).
 * Returns array [path, name, type, size] or null on failure/absence.
 * Stored in uploads/chat/ with a unique name.
 */
function saveChatAttachmentUpload(?array $file): ?array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 10 * 1024 * 1024) return null;

    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'];
    $origName = basename((string) ($file['name'] ?? ''));
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($origName === '' || !in_array($ext, $allowed, true)) return null;

    $tmpPath = $file['tmp_name'];

    // تحقق من نوع الملف الحقيقي للصور (حماية من ملفات متنكرة)
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $ok = false;
        if (class_exists('finfo')) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $tmpPath);
            $ok = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
        } else {
            $info = @getimagesize($tmpPath);
            $ok = is_array($info) && !empty($info['mime']);
        }
        if (!$ok) return null;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/chat';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) return null;

    $safeName = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $origName);
    $uniqueName = 'chat_' . time() . '_' . mt_rand(1000, 9999) . '_' . $safeName;
    if (!move_uploaded_file($tmpPath, $uploadDir . '/' . $uniqueName)) return null;

    return [
        'path' => 'uploads/chat/' . $uniqueName,
        'name' => $origName,
        'type' => (string) ($file['type'] ?? ''),
        'size' => (int) $file['size'],
    ];
}

function sendMessage(int $conversationId, int $senderId, string $senderType, string $content, ?array $fileInfo = null): int {
    ensureMessageFileColumns();
    $stmt = db()->prepare(
        'INSERT INTO messages (conversation_id, sender_id, sender_type, content, file_path, file_name, file_type, file_size)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $conversationId, $senderId, $senderType, $content,
        $fileInfo['path'] ?? null,
        $fileInfo['name'] ?? null,
        $fileInfo['type'] ?? null,
        $fileInfo['size'] ?? null,
    ]);
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
        'new_orders'      => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('new', 'pending_assignment')")->fetchColumn(),
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
        'pending_assignment' => ['label' => 'بانتظار تعيين الإدارة', 'class' => 'badge-info'],
        'assigned'           => ['label' => 'أُرسل إلى الأكاديمي', 'class' => 'badge-primary'],
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

/* ─────────────────────────────────────────────
   PAYMENT METHODS (حسابات المنصة) + RECEIPTS (إشعارات الدفع)
───────────────────────────────────────────── */

/** Active platform payment accounts (banks & e-wallets) for students to pay into. */
function getActivePaymentMethods(): array {
    try {
        return db()->query(
            'SELECT * FROM platform_payment_methods WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/** Create the payment_receipts table if it does not exist yet. */
function ensurePaymentReceiptsTable(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS `payment_receipts` (
          `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `order_id`        INT UNSIGNED DEFAULT NULL COMMENT 'For service orders',
          `subscription_id` INT UNSIGNED DEFAULT NULL COMMENT 'For package subscriptions',
          `student_id`      INT UNSIGNED NOT NULL,
          `method_id`       INT UNSIGNED DEFAULT NULL,
          `method_label`    VARCHAR(150) DEFAULT NULL,
          `amount`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `receipt_path`    VARCHAR(500) NOT NULL,
          `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
          `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_rcpt_order` (`order_id`),
          INDEX `idx_rcpt_sub`   (`subscription_id`),
          INDEX `idx_rcpt_std`   (`student_id`),
          INDEX `idx_rcpt_status`(`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Validate & store an uploaded payment receipt (image or PDF).
 * Returns the stored relative path or null on failure/absence.
 */
function saveReceiptUpload(?array $file): ?string {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 10 * 1024 * 1024) return null;

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return null;

    $tmpPath = $file['tmp_name'];
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $ok = false;
        if (class_exists('finfo')) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$tmpPath);
            $ok = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
        } else {
            $info = @getimagesize($tmpPath);
            $ok = is_array($info) && !empty($info['mime']);
        }
        if (!$ok) return null;
    } elseif ($ext === 'pdf') {
        $head = (string) @file_get_contents($tmpPath, false, null, 0, 5);
        if (trim($head) !== '%PDF-') return null;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/receipts';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) return null;

    $fileName = 'rcpt_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($tmpPath, $uploadDir . '/' . $fileName)) return null;
    return 'uploads/receipts/' . $fileName;
}

/**
 * Ensure the users table has an avatar_path column (safe auto-upgrade).
 */
function ensureUserAvatarColumn(): void {
    try {
        $cols = db()->query("SHOW COLUMNS FROM `users` LIKE 'avatar_path'")->fetchAll();
        if (empty($cols)) {
            db()->exec("ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) DEFAULT NULL AFTER `avatar_initials`");
        }
    } catch (Exception $e) {
        error_log('ensureUserAvatarColumn failed: ' . $e->getMessage());
    }
}

/**
 * Validate & store a profile avatar image upload for a user.
 * Returns the stored relative path (e.g. uploads/avatars/xxx.jpg) or null on failure.
 * The previously stored avatar file (if any) is deleted by the caller.
 */
function saveAvatarUpload(?array $file): ?string {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 5 * 1024 * 1024) return null;

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return null;

    $tmpPath = $file['tmp_name'];
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $tmpPath);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) return null;
    } else {
        $info = @getimagesize($tmpPath);
        if (!is_array($info) || empty($info['mime'])) return null;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/avatars';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) return null;

    $fileName = 'av_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($tmpPath, $uploadDir . '/' . $fileName)) return null;
    return 'uploads/avatars/' . $fileName;
}

/**
 * Validate & store order attachments (files) uploaded by the student with a new order.
 * Expects the raw $_FILES entry of a multi-file input (name="order_attachments[]").
 * Files are stored in uploads/orders/ and registered in order_attachments table.
 * Returns the number of successfully saved attachments.
 */
function saveOrderAttachmentUploads(?array $files, int $orderId): int {
    if (!$files || $orderId <= 0) return 0;

    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'];
    $maxSize = 10 * 1024 * 1024; // 10MB لكل ملف

    $uploadDir = dirname(__DIR__) . '/uploads/orders';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) return 0;

    $count   = count($files['name'] ?? []);
    $saved   = 0;
    $stmt    = db()->prepare(
        'INSERT INTO order_attachments (order_id, file_name, file_path, file_type, file_size, uploaded_by)
         VALUES (?, ?, ?, ?, ?, "student")'
    );

    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $size  = (int) ($files['size'][$i] ?? 0);
        if ($size <= 0 || $size > $maxSize) continue;

        $origName = basename((string) ($files['name'][$i] ?? ''));
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($origName === '' || !in_array($ext, $allowed, true)) continue;

        $tmpPath   = $files['tmp_name'][$i];
        $safeName  = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $origName);
        $uniqueName = time() . '_' . bin2hex(random_bytes(8)) . '_' . $safeName;
        $destPath  = $uploadDir . '/' . $uniqueName;

        if (!move_uploaded_file($tmpPath, $destPath)) continue;

        try {
            $stmt->execute([
                $orderId,
                $origName,
                'uploads/orders/' . $uniqueName,
                (string) ($files['type'][$i] ?? ''),
                $size,
            ]);
            $saved++;
        } catch (Exception $e) {
            error_log('Order attachment save failed: ' . $e->getMessage());
            @unlink($destPath);
        }
    }

    return $saved;
}

/** Persist a payment receipt (proof) linked to an order or a subscription. */
function savePaymentReceipt(array $data): int {
    ensurePaymentReceiptsTable();
    $stmt = db()->prepare(
        'INSERT INTO payment_receipts
           (order_id, subscription_id, student_id, method_id, method_label, amount, receipt_path, status)
         VALUES (?,?,?,?,?,?,?,"pending")'
    );
    $stmt->execute([
        $data['order_id']        ?? null,
        $data['subscription_id'] ?? null,
        $data['student_id'],
        $data['method_id']       ?? null,
        $data['method_label']    ?? null,
        $data['amount']          ?? 0,
        $data['receipt_path'],
    ]);
    return (int) db()->lastInsertId();
}

function getReceiptForOrder(int $orderId): ?array {
    ensurePaymentReceiptsTable();
    $stmt = db()->prepare('SELECT * FROM payment_receipts WHERE order_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$orderId]);
    return $stmt->fetch() ?: null;
}

function getReceiptForSubscription(int $subId): ?array {
    ensurePaymentReceiptsTable();
    $stmt = db()->prepare('SELECT * FROM payment_receipts WHERE subscription_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$subId]);
    return $stmt->fetch() ?: null;
}

function updatePaymentReceiptStatus(int $receiptId, string $status): bool {
    if (!in_array($status, ['approved', 'rejected', 'pending'], true)) return false;
    $stmt = db()->prepare('UPDATE payment_receipts SET status = ? WHERE id = ?');
    return $stmt->execute([$status, $receiptId]);
}

/** All payment receipts (for the admin review screen), newest first. */
function getAllPaymentReceipts(int $limit = 100): array {
    ensurePaymentReceiptsTable();
    return db()->query(
        'SELECT pr.*, u.name AS student_name, o.order_number, p.name AS package_name
         FROM payment_receipts pr
         LEFT JOIN users u    ON pr.student_id = u.id
         LEFT JOIN orders o   ON pr.order_id = o.id
         LEFT JOIN package_subscriptions ps ON pr.subscription_id = ps.id
         LEFT JOIN packages p ON ps.package_id = p.id
         ORDER BY pr.created_at DESC
         LIMIT ' . (int)$limit
    )->fetchAll();
}

/** Human label for a platform payment account id. */
function getPaymentMethodLabel(?int $methodId): string {
    if (!$methodId) return '';
    try {
        $stmt = db()->prepare('SELECT account_type, account_name FROM platform_payment_methods WHERE id = ? LIMIT 1');
        $stmt->execute([$methodId]);
        $row = $stmt->fetch();
        if (!$row) return '';
        return (($row['account_type'] ?? 'bank') === 'wallet' ? '👛 ' : '🏦 ') . $row['account_name'];
    } catch (Exception $e) {
        return '';
    }
}
