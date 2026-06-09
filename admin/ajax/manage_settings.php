<?php
/**
 * manage_settings.php
 * معالجة طلبات AJAX لإعدادات النظام (تحميل، حفظ، تحديث الملف الشخصي، تغيير كلمة المرور)
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

// التحقق من صلاحيات المدير
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendError('غير مصرح', 401);
}

require_once __DIR__ . '/../../config/functions.php';
if (!function_exists('db')) sendError('دالة db غير موجودة', 500);
$db = db();
if (!$db) sendError('فشل الاتصال بقاعدة البيانات', 500);

$action = $_POST['action'] ?? '';

// ========== 1. جلب جميع الإعدادات ==========
if ($action === 'get_settings') {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    // إضافة بيانات المدير من جدول users
    $adminStmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = ? AND role = 'admin'");
    $adminStmt->execute([$_SESSION['user_id']]);
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $settings['admin_name'] = $admin['name'];
        $settings['admin_email'] = $admin['email'];
        $settings['admin_phone'] = $admin['phone'] ?? '';
    }
    echo json_encode(['success' => true, 'data' => $settings]);
    exit;
}

// ========== 2. حفظ الإعدادات (عامة، عمولة، نظام، إشعارات، دفع، مظهر، بريد) ==========
if ($action === 'save_settings') {
    // قائمة الحقول المسموح بحفظها مع المجموعات
    $allowed = [
        'site_name', 'site_email', 'site_phone', 'site_url', 'site_language', 'site_timezone', 'site_description',
        'commission_percentage', 'min_withdrawal', 'payout_cycle',
        'maintenance_mode', 'allow_registration', 'email_verification', 'two_factor_auth', 'session_logging',
        'notify_new_order', 'notify_payment', 'daily_summary_email', 'notify_new_academic',
        'payment_stripe_enabled', 'payment_moyasar_enabled', 'payment_paypal_enabled',
        'primary_color', 'theme_mode',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_password'
    ];
    
    $updates = 0;
    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $value = trim($_POST[$key]);
            // تحويل القيم الرقمية أو البوليانية إلى النوع المناسب
            if (in_array($key, ['maintenance_mode', 'allow_registration', 'email_verification', 'two_factor_auth', 'session_logging', 'notify_new_order', 'notify_payment', 'daily_summary_email', 'notify_new_academic', 'payment_stripe_enabled', 'payment_moyasar_enabled', 'payment_paypal_enabled'])) {
                $value = $value ? '1' : '0';
            }
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value]);
            $updates++;
        }
    }
    echo json_encode(['success' => true, 'message' => "تم حفظ $updates إعداد بنجاح"]);
    exit;
}

// ========== 3. تحديث الملف الشخصي للمدير ==========
if ($action === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if (!$name || !$email) sendError('الاسم والبريد مطلوبان');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendError('بريد غير صالح');
    
    // التحقق من أن البريد غير مستخدم من قبل مدير آخر
    $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND role = 'admin'");
    $check->execute([$email, $_SESSION['user_id']]);
    if ($check->rowCount() > 0) sendError('البريد مستخدم من قبل مدير آخر');
    
    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phone, $_SESSION['user_id']]);
    echo json_encode(['success' => true, 'message' => 'تم تحديث الملف الشخصي']);
    exit;
}

// ========== 4. تغيير كلمة المرور ==========
if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (!$current || !$new || !$confirm) sendError('جميع الحقول مطلوبة');
    if ($new !== $confirm) sendError('كلمة المرور الجديدة غير متطابقة');
    if (strlen($new) < 6) sendError('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
    
    // جلب كلمة المرور الحالية من قاعدة البيانات
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($current, $user['password'])) {
        sendError('كلمة المرور الحالية غير صحيحة');
    }
    
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $update = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->execute([$hashed, $_SESSION['user_id']]);
    echo json_encode(['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح']);
    exit;
}

sendError('إجراء غير معروف');
?>