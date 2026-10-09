<?php
/**
 * ============================================================
 *  Eduroad - Admin Payouts AJAX Handler
 *  manages payouts from admin to academics
 * ============================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

function sendError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Admin auth
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    sendError('غير مصرح', 401);
}

require_once __DIR__ . '/../../config/functions.php';
if (!function_exists('db')) sendError('دالة db غير موجودة', 500);
$db = db();
if (!$db) sendError('فشل الاتصال بقاعدة البيانات', 500);

try {
    $db->exec("CREATE TABLE IF NOT EXISTS payout_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payout_id INT NOT NULL,
        sender_type ENUM('admin', 'academic') NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

$action = trim($_POST['action'] ?? '');

/* ─────────────────────────────────────────────
   GET ALL DATA (academics + bank accounts + payouts + stats)
   ───────────────────────────────────────────── */
if ($action === 'get_data') {
    // Academics with balance, earned, total paid and their bank accounts
    $academics = [];
    $acadStmt = $db->query("
        SELECT a.id, a.name, a.avatar_initials, a.balance, a.total_earned,
               COALESCE((SELECT SUM(pa.amount) FROM payouts pa WHERE pa.academic_id = a.id AND pa.status = 'paid'), 0) AS total_paid
        FROM academics a
        WHERE a.status = 'approved'
        ORDER BY a.name ASC
    ");
    while ($a = $acadStmt->fetch()) {
        $bankStmt = $db->prepare("SELECT * FROM academic_bank_accounts WHERE academic_id = ? ORDER BY id ASC");
        $bankStmt->execute([$a['id']]);
        $a['balance']       = (float)$a['balance'];
        $a['total_earned']  = (float)$a['total_earned'];
        $a['total_paid']    = (float)$a['total_paid'];
        $a['bank_accounts'] = $bankStmt->fetchAll();
        $academics[] = $a;
    }

    // Payout history
    $payStmt = $db->query("
        SELECT p.*, a.name AS academic_name,
               b.account_name AS bank_name, b.account_type AS bank_type, b.account_number AS bank_number
        FROM payouts p
        LEFT JOIN academics a ON p.academic_id = a.id
        LEFT JOIN academic_bank_accounts b ON p.bank_account_id = b.id
        ORDER BY CASE WHEN p.status = 'pending' THEN 0 ELSE 1 END, p.created_at DESC
    ");
    $payouts = $payStmt->fetchAll();

    // Fetch messages for these payouts
    $payoutIds = array_column($payouts, 'id');
    $messages = [];
    if (!empty($payoutIds)) {
        $inQuery = implode(',', array_fill(0, count($payoutIds), '?'));
        $msgStmt = $db->prepare("SELECT * FROM payout_messages WHERE payout_id IN ($inQuery) ORDER BY created_at ASC");
        $msgStmt->execute($payoutIds);
        foreach ($msgStmt->fetchAll() as $msg) {
            $messages[$msg['payout_id']][] = $msg;
        }
    }

    // Statistics
    $stats = [
        'total_paid'      => (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE status='paid'")->fetchColumn(),
        'this_month'      => (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE status='paid' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())")->fetchColumn(),
        'last_month'      => (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE status='paid' AND MONTH(paid_at)=MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(paid_at)=YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))")->fetchColumn(),
        'total_pending'   => (float)$db->query("SELECT COALESCE(SUM(balance),0) FROM academics WHERE status='approved'")->fetchColumn(),
        'payouts_count'   => (int)$db->query("SELECT COUNT(*) FROM payouts WHERE status='paid'")->fetchColumn(),
        'academics_count' => (int)$db->query("SELECT COUNT(*) FROM academics WHERE status='approved'")->fetchColumn(),
    ];

    echo json_encode(['success' => true, 'academics' => $academics, 'payouts' => $payouts, 'stats' => $stats, 'messages' => $messages], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ─────────────────────────────────────────────
   ADD PAYOUT (record a payment to an academic)
   ───────────────────────────────────────────── */
if ($action === 'add_payout') {
    $academicId  = intval($_POST['academic_id'] ?? 0);
    $bankAccount = intval($_POST['bank_account_id'] ?? 0);
    $amount      = floatval($_POST['amount'] ?? 0);
    $note        = trim($_POST['note'] ?? '');
    $paidAtRaw   = trim($_POST['paid_at'] ?? date('Y-m-d'));

    if ($academicId <= 0) sendError('يرجى اختيار أكاديمي.');
    if ($amount <= 0)    sendError('يرجى إدخال مبلغ صحيح أكبر من الصفر.');

    // Validate date
    $paidAt = date('Y-m-d H:i:s', strtotime($paidAtRaw)) ?: date('Y-m-d H:i:s');

    // Validate bank account belongs to the academic
    if ($bankAccount > 0) {
        $chk = $db->prepare("SELECT id FROM academic_bank_accounts WHERE id = ? AND academic_id = ?");
        $chk->execute([$bankAccount, $academicId]);
        if (!$chk->fetch()) $bankAccount = 0;
    }

    try {
        // Ensure amount does not exceed the academic balance
        $balStmt = $db->prepare("SELECT balance FROM academics WHERE id = ?");
        $balStmt->execute([$academicId]);
        $currentBalance = (float)$balStmt->fetchColumn();

        if ($amount > $currentBalance) {
            sendError("المبلغ أكبر من الرصيد المستحق ({$currentBalance} ر.س).");
        }

        // Payout number e.g. PAYOUT-20260001
        $nextNum = (int)$db->query("SELECT COALESCE(MAX(id),0)+1 FROM payouts")->fetchColumn();
        $payoutNumber = 'PAYOUT-' . date('Y') . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        $db->beginTransaction();
        $ins = $db->prepare(
            "INSERT INTO payouts (payout_number, academic_id, bank_account_id, amount, note, status, paid_at, created_by) VALUES (?,?,?,?,?, 'paid', ?, ?)"
        );
        $ins->execute([$payoutNumber, $academicId, $bankAccount ?: null, $amount, $note, $paidAt, $_SESSION['user_id']]);
        $db->prepare("UPDATE academics SET balance = GREATEST(balance - ?, 0) WHERE id = ?")->execute([$amount, $academicId]);
        $db->commit();

        echo json_encode(['success' => true, 'message' => 'تم تسجيل الدفعة بنجاح.', 'payout_number' => $payoutNumber], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        sendError('فشل تسجيل الدفعة: ' . $e->getMessage());
    }
}

/* ─────────────────────────────────────────────
   UPDATE PAYOUT STATUS
   ───────────────────────────────────────────── */
if ($action === 'update_payout_status') {
    $payoutId = intval($_POST['payout_id'] ?? 0);
    $status = trim($_POST['status'] ?? ''); 
    if ($payoutId > 0 && in_array($status, ['approved', 'rejected'])) {
        $db->prepare("UPDATE payouts SET status = ?, paid_at = IF(?='approved', NOW(), paid_at) WHERE id = ?")->execute([$status, $status, $payoutId]);
        
        // If rejected, refund the balance back to academic
        if ($status === 'rejected') {
            $stmt = $db->prepare("SELECT academic_id, amount FROM payouts WHERE id = ?");
            $stmt->execute([$payoutId]);
            $po = $stmt->fetch();
            if ($po) {
                $db->prepare("UPDATE academics SET balance = balance + ? WHERE id = ?")->execute([$po['amount'], $po['academic_id']]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'تم تحديث حالة الطلب']);
        exit;
    }
    sendError('بيانات غير صالحة');
}

/* ─────────────────────────────────────────────
   SEND MESSAGE
   ───────────────────────────────────────────── */
if ($action === 'send_message') {
    $payoutId = intval($_POST['payout_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($payoutId > 0 && !empty($message)) {
        $stmt = $db->prepare("INSERT INTO payout_messages (payout_id, sender_type, sender_id, message) VALUES (?, 'admin', ?, ?)");
        $stmt->execute([$payoutId, $_SESSION['user_id'], $message]);
        echo json_encode(['success' => true]);
        exit;
    }
    sendError('بيانات غير صالحة');
}

sendError('إجراء غير معروف');

