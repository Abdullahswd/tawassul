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
require_once __DIR__ . '/../../config/auth.php';
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
        'site_name', 'site_email', 'site_phone', 'site_url', 'site_language', 'site_timezone', 'site_description', 'site_logo',
        'commission_percentage', 'min_withdrawal', 'payout_cycle',
        'withdrawal_eligibility_days', 'auto_approve_hours', 'payout_processing_time',
        'maintenance_mode', 'allow_registration', 'email_verification', 'two_factor_auth', 'session_logging',
        'notify_new_order', 'notify_payment', 'daily_summary_email', 'notify_new_academic',
        'payment_stripe_enabled', 'payment_moyasar_enabled', 'payment_paypal_enabled',
        'primary_color', 'theme_mode',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_password', 'smtp_encryption'
    ];
    
    $servicesUpdated = 0;
    $customCount = 0;
    $commissionMsg = '';

    // مزامنة نسبة عمولة الخدمات وفق القواعد المحددة:
    // 1. أي خدمة عمولتها صفر أو غير محددة: تُسند لها القيمة الجديدة.
    // 2. أي خدمة عمولتها تساوي القيمة السابقة: تُحدث إلى القيمة الجديدة.
    // 3. أي خدمة تحمل قيمة عمولة مخصصة أخرى: تُستثنى بالكامل ولا تتأثر.
    if (isset($_POST['commission_percentage'])) {
        $newCommission = round((float)$_POST['commission_percentage'], 2);
        
        $prevStmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'commission_percentage'");
        $prevStmt->execute();
        $oldRow = $prevStmt->fetchColumn();
        $oldCommission = ($oldRow !== false && $oldRow !== null && $oldRow !== '') ? round((float)$oldRow, 2) : null;

        if ($oldCommission !== null && $oldCommission > 0) {
            $syncStmt = $db->prepare("
                UPDATE services 
                SET platform_percent = ? 
                WHERE level = 3 AND (platform_percent = 0 OR platform_percent IS NULL OR ABS(platform_percent - ?) < 0.01)
            ");
            $syncStmt->execute([$newCommission, $oldCommission]);
        } else {
            $syncStmt = $db->prepare("
                UPDATE services 
                SET platform_percent = ? 
                WHERE level = 3 AND (platform_percent = 0 OR platform_percent IS NULL)
            ");
            $syncStmt->execute([$newCommission]);
        }
        $servicesUpdated = $syncStmt->rowCount();

        // حساب عدد الخدمات المستثناة (التي لها عمولة مخصصة مختلفة)
        $customStmt = $db->prepare("SELECT COUNT(*) FROM services WHERE level = 3 AND platform_percent > 0 AND ABS(platform_percent - ?) >= 0.01");
        $customStmt->execute([$newCommission]);
        $customCount = (int)$customStmt->fetchColumn();

        $commissionMsg = " وتم تحديث نسبة {$servicesUpdated} خدمة (استثناء {$customCount} خدمة مخصصة)";
    }

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
    echo json_encode([
        'success' => true, 
        'message' => "تم حفظ الإعدادات بنجاح" . $commissionMsg,
        'services_updated' => $servicesUpdated,
        'custom_excepted' => $customCount
    ]);
    exit;
}

// ========== 2b. اختبار اتصال SMTP ==========
if ($action === 'test_smtp') {
    $to = trim($_POST['test_email'] ?? '');
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        // Fallback to logged-in admin email
        $adminStmt = $db->prepare("SELECT email FROM users WHERE id = ?");
        $adminStmt->execute([$_SESSION['user_id']]);
        $to = $adminStmt->fetchColumn() ?: '';
    }
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        sendError('يرجى تحديد عنوان بريد إلكتروني صحيح لتجربة الإرسال');
    }
    require_once __DIR__ . '/../../config/mailer.php';
    $client = new EduroadSmtpClient();
    $subject = '📧 اختبار اتصال SMTP لمنصة Eduroad';
    $html = renderEmailTemplate('🧪 اختبار البريد', 'تم الاتصال بنجاح بخادم SMTP!', '<p>تهانينا! خادم البريد الإلكتروني SMTP الخاص بمنصة Eduroad متصل ويعمل بكفاءة وبشكل سليم.</p>');
    $ok = $client->send($to, 'مدير المنصة', $subject, $html);
    if ($ok) {
        echo json_encode(['success' => true, 'message' => "تم إرسال البريد التجريبي بنجاح إلى {$to}!"]);
    } else {
        sendError('فشل الإرسال: ' . $client->getLastError());
    }
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

// ========== 5. رفع صورة الملف الشخصي ==========
if ($action === 'upload_avatar') {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        sendError('الرجاء اختيار صورة صالحة');
    }
    
    $file = $_FILES['avatar'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    
    if (!in_array($ext, $allowedExt)) {
        sendError('صيغة الملف غير مدعومة');
    }
    
    // Check size limit (e.g., 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        sendError('حجم الصورة كبير جداً، يجب ألا يتجاوز 2 ميجابايت');
    }
    
    // Ensure uploads directory exists
    $uploadDir = __DIR__ . '/../../uploads/avatars';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = 'admin_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . '/' . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $avatarDbPath = 'uploads/avatars/' . $fileName;
        
        // Update database
        $stmt = $db->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        $stmt->execute([$avatarDbPath, $_SESSION['user_id']]);
        
        $_SESSION['user_avatar_path'] = $avatarDbPath;
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم تحديث الصورة الشخصية بنجاح',
            'avatar_url' => rootUrl() . '/' . $avatarDbPath
        ]);
        exit;
    } else {
        sendError('فشل في رفع الصورة');
    }
}

// ========== 6. رفع شعار المنصة (Site Logo) ==========
if ($action === 'upload_site_logo') {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        sendError('يرجى اختيار ملف صورة صالح للشعار');
    }
    
    $file = $_FILES['logo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
    
    if (!in_array($ext, $allowedExt)) {
        sendError('صيغة الشعار غير مدعومة (PNG, SVG, JPG, WEBP فقط)');
    }
    
    if ($file['size'] > 3 * 1024 * 1024) {
        sendError('حجم ملف الشعار كبير، يجب ألا يتجاوز 3 ميجابايت');
    }
    
    $uploadDir = __DIR__ . '/../../uploads/site';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = 'site_logo_' . time() . '.' . $ext;
    $targetPath = $uploadDir . '/' . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $logoDbPath = 'uploads/site/' . $fileName;
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$logoDbPath]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم رفع وتحديث شعار المنصة بنجاح',
            'logo_url' => rootUrl() . '/' . $logoDbPath
        ]);
        exit;
    } else {
        sendError('فشل في رفع ملف الشعار إلى الخادم');
    }
}

sendError('إجراء غير معروف');
?>