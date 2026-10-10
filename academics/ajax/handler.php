<?php
/**
 * ============================================================
 *  Eduroad - Academics AJAX Handler
 *  File: academics/ajax/handler.php
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep it clean for JSON response

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';

function sendJSONResponse($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function uploadQualificationDoc($fileArray)
{
    if (!$fileArray || !isset($fileArray['error']) || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $uploadDir = __DIR__ . '/../../uploads/qualifications/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileName = basename($fileArray['name']);
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueName = time() . '_' . bin2hex(random_bytes(8)) . ($ext ? '.' . $ext : '');
    $filePath = $uploadDir . $uniqueName;
    if (move_uploaded_file($fileArray['tmp_name'], $filePath)) {
        return 'uploads/qualifications/' . $uniqueName;
    }
    return null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!$action) {
    sendJSONResponse(false, 'إجراء غير محدد.');
}

// ── إضافة تلقائية للأعمدة (logo + الصورة الشخصية) إن لم تكن موجودة ──
// تعمل عند أي استدعاء، وتعود بنتيجة لمعرفة إن نجحت أم لا
function columnExists($conn, $table, $column)
{
    try {
        // استخدام information_schema بدلاً من SHOW COLUMNS ... LIKE ?
        // لأن SHOW لا يدعم المعاملات في وضع الـ native prepared statements
        // فيعود استثناء دائماً حتى لو كان العمود موجوداً.
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("columnExists failed for $table.$column: " . $e->getMessage());
        return false;
    }
}

function ensureColumn($conn, $table, $column, $definition)
{
    try {
        if (!columnExists($conn, $table, $column)) {
            $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
        return true;
    } catch (Exception $e) {
        error_log("ensureColumn failed for $table.$column: " . $e->getMessage());
        return false;
    }
}

try {
    $dbEnsure = db();
    if ($dbEnsure) {
        ensureColumn($dbEnsure, 'academic_bank_accounts', 'logo', 'VARCHAR(255) DEFAULT NULL');
        ensureColumn($dbEnsure, 'academics', 'avatar', 'VARCHAR(255) DEFAULT NULL');
        ensureColumn($dbEnsure, 'academic_qualifications', 'document_file', 'VARCHAR(255) DEFAULT NULL');
    }
} catch (Exception $e) {
    // تجاهل
}

// رفع الصورة الشخصية للأكاديمي
if ($action === 'upload_avatar') {
    if (empty($_FILES['image'])) {
        sendJSONResponse(false, 'لم يتم استلام الصورة.');
    }
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendJSONResponse(false, 'حدث خطأ أثناء رفع الصورة (رمز ' . $file['error'] . ').');
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        sendJSONResponse(false, 'صيغة الصورة غير مدعومة (JPG/PNG/GIF/WEBP).');
    }

    $isRealImage = false;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        $isRealImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    } else {
        $info = @getimagesize($file['tmp_name']);
        $isRealImage = is_array($info) && !empty($info['mime'])
            && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }
    if (!$isRealImage) {
        sendJSONResponse(false, 'الملف ليس صورة صالحة.');
    }

    $uploadDir = dirname(__DIR__) . '/../uploads/avatars';
    if (!is_dir($uploadDir))
        @mkdir($uploadDir, 0755, true);
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        sendJSONResponse(false, 'مجلد رفع الصور (uploads/avatars) غير قابل للكتابة.');
    }

    $fileName = 'av_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $dest = rtrim($uploadDir, '/\\') . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        sendJSONResponse(false, 'فشل حفظ الصورة على الخادم.');
    }

    sendJSONResponse(true, 'تم رفع الصورة بنجاح.', ['image' => 'uploads/avatars/' . $fileName]);
}

// ─────────────────────────────────────────────────────────────
// 0. UPDATE ACADEMIC PROFILE (البيانات الشخصية + الصورة الشخصية)
// ─────────────────────────────────────────────────────────────
if ($action === 'update_profile') {
    if (empty($_SESSION['academic_id'])) {
        sendJSONResponse(false, 'الجلسة منتهية، يرجى تسجيل الدخول مرة أخرى.');
    }
    $academicId = (int) $_SESSION['academic_id'];
    $dbGet = db();
    $stmtGet = $dbGet->prepare("SELECT * FROM academics WHERE id = ?");
    $stmtGet->execute([$academicId]);
    $academic = $stmtGet->fetch() ?: [];


    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $available = (isset($_POST['availability']) && $_POST['availability'] === 'available') ? 'available' : 'busy';

    if (!$name || !$email || !$phone) {
        sendJSONResponse(false, 'يرجى ملء الاسم، البريد الوإيميل، ورقم الهاتف.');
    }

    // Validate email uniqueness (excluding current academic)
    try {
        $db = db();
        $chk = $db->prepare("SELECT id FROM academics WHERE email = ? AND id != ?");
        $chk->execute([$email, $academicId]);
        if ($chk->fetch()) {
            sendJSONResponse(false, 'هذا البريد الإلكتروني مستخدم مسبقاً من قبل أكاديمي آخر.');
        }

        $sql = "UPDATE academics SET name = ?, email = ?, phone = ?, bio = ?, availability = ?";
        $params = [$name, $email, $phone, $bio, $available];

        // التأكد من وجود عمود الصورة الشخصية قبل محاولة التحديث
        // (في السيرفرات المبنية من نسخة قديمة قد لا يكون موجوداً)
        $dbAvatarChk = db();
        if (!columnExists($dbAvatarChk, 'academics', 'avatar')) {
            if (ensureColumn($dbAvatarChk, 'academics', 'avatar', 'VARCHAR(255) DEFAULT NULL')) {
                // أُضيف الآن — نكمل
            } else {
                sendJSONResponse(false, 'تعذر حفظ الصورة الشخصية: عمود الصورة غير موجود في قاعدة البيانات، ولم تنجح الإضافة التلقائية (ينقص المستخدم صلاحية ALTER TABLE). يُرجى تشغيل ملف الترحيل migrations/2026_09_14_01_add_avatar_to_academics.sql على قاعدة البيانات.');
            }
        }

        // تخزين/تحديث أو حذف الصورة الشخصية.
        // الافتراضي: الإبقاء على الصورة الحالية (كي لا تُمسح عند حفظ
        // تغييرات أخرى لا تشمل حقل avatar، مثل زر «حفظ التغييرات»).
        $avatarPath = $academic['avatar'] ?? null;
        if (isset($_POST['avatar'])) {
            $avatarPath = trim($_POST['avatar']);
            if ($avatarPath === '' || $avatarPath === 'null') {
                $avatarPath = null;
            }
        }
        // إزالة الصورة إذا أُرسل العلم avatar_cleared
        if (!empty($_POST['avatar_cleared'])) {
            $avatarPath = null;
        }
        // حذف الصورة القديمة من القرص إذا تم استبدالها بأخرى
        $oldAvatar = $academic['avatar'] ?? '';
        if (!empty($_POST['avatar']) && $oldAvatar && $avatarPath && $avatarPath !== $oldAvatar) {
            $oldFile = dirname(__DIR__) . '/../' . $oldAvatar;
            if (is_file($oldFile) && strpos($oldAvatar, 'uploads/avatars/') === 0) {
                @unlink($oldFile);
            }
        }
        $sql .= ", avatar = ?";
        $params[] = $avatarPath;

        $sql .= " WHERE id = ?";
        $params[] = $academicId;

        $db->prepare($sql)->execute($params);

        // حفظ الخدمات المحددة (شجرة الخدمات): استبدال القائمة القديمة بالمحددات الجديدة
        // ملاحظة: PHP مع FormData: عند إرسال services[] واحد فقط قد يصل كنص، لذا نوحّد الصيغة
        $servicesRaw = $_POST['services'] ?? [];
        if (is_string($servicesRaw)) {
            $servicesRaw = $servicesRaw !== '' ? [$servicesRaw] : [];
        }
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', (array) $servicesRaw))));
        $db->prepare("DELETE FROM academic_services WHERE academic_id = ?")->execute([$academicId]);
        if (!empty($serviceIds)) {
            $srvStmt = $db->prepare("INSERT IGNORE INTO academic_services (academic_id, service_id, custom_price) VALUES (?, ?, NULL)");
            foreach ($serviceIds as $srvId) {
                $srvStmt->execute([$academicId, $srvId]);
            }
        }

        sendJSONResponse(true, 'تم حفظ البيانات الشخصية بنجاح.');
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل حفظ البيانات: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// 1. ACADEMIC REGISTRATION
// ─────────────────────────────────────────────────────────────
if ($action === 'register') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSONResponse(false, 'طريقة طلب غير صالحة.');
    }

    $name = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $idNumber = trim($_POST['idNumber'] ?? '');
    $birthPlace = trim($_POST['birthPlace'] ?? '');
    $birthDate = trim($_POST['birthDate'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Credentials
    $password = $_POST['password'] ?? 'Academic@123'; // Default fallback if not provided
    $googleId = $_POST['google_id'] ?? null;

    // Qualifications checkbox indicators
    $hasBachelor = isset($_POST['hasBachelor']) || !empty($_POST['bsUniversity']);
    $hasMasters = isset($_POST['hasMasters']) || !empty($_POST['msUniversity']);
    $hasPhd = isset($_POST['hasPhd']) || !empty($_POST['phdUniversity']);

    // Selected services
    $services = $_POST['services'] ?? []; // Array of service IDs
    $basePrice = floatval($_POST['basePrice'] ?? 0);

    if (!$name || !$email || !$phone || !$bio) {
        sendJSONResponse(false, 'يرجى ملء جميع الحقول الشخصية الأساسية المطلوبة.');
    }

    if (!$hasBachelor && !$hasMasters && !$hasPhd) {
        sendJSONResponse(false, 'يرجى تقديم مؤهل أكاديمي واحد على الأقل.');
    }

    try {
        $db = db();

        // Check email uniqueness
        $check = $db->prepare("SELECT id FROM academics WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            sendJSONResponse(false, 'البريد الإلكتروني مسجل مسبقاً كأكاديمي.');
        }

        // Determine main specialty and degree
        $mainSpecialty = '';
        $mainDegree = 'بكالوريوس';
        $mainUniversity = '';

        if ($hasPhd) {
            $mainDegree = 'دكتوراه';
            $mainSpecialty = trim($_POST['phdMajor'] ?? '');
            $mainUniversity = trim($_POST['phdUniversity'] ?? '');
        } elseif ($hasMasters) {
            $mainDegree = 'ماجستير';
            $mainSpecialty = trim($_POST['msMajor'] ?? '');
            $mainUniversity = trim($_POST['msUniversity'] ?? '');
        } elseif ($hasBachelor) {
            $mainDegree = 'بكالوريوس';
            $mainSpecialty = trim($_POST['bsMajor'] ?? '');
            $mainUniversity = trim($_POST['bsUniversity'] ?? '');
        }

        // Avatar Initials
        $initials = mb_substr($name, 0, 1, 'UTF-8') . mb_substr(explode(' ', $name)[1] ?? '', 0, 1, 'UTF-8');

        // Insert into academics
        $stmt = $db->prepare(
            "INSERT INTO academics (name, email, password, google_id, phone, specialty, degree, university, bio, avatar_initials, starting_price, status, availability, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'available', NOW())"
        );
        $stmt->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $googleId,
            $phone,
            $mainSpecialty,
            $mainDegree,
            $mainUniversity,
            $bio,
            $initials,
            $basePrice
        ]);

        $academicId = (int) $db->lastInsertId();

        // Save Qualifications
        if ($hasBachelor) {
            $docFile = uploadQualificationDoc($_FILES['bsDocument'] ?? null);
            $db->prepare("INSERT INTO academic_qualifications (academic_id, level, field, university, country, graduation_year, document_file, verified) VALUES (?, 'بكالوريوس', ?, ?, ?, ?, ?, 1)")
                ->execute([$academicId, trim($_POST['bsMajor'] ?? 'عام'), trim($_POST['bsUniversity'] ?? 'غير محدد'), trim($_POST['bsCountry'] ?? 'السعودية'), intval($_POST['bsYear'] ?? 2015), $docFile]);
        }
        if ($hasMasters) {
            $docFile = uploadQualificationDoc($_FILES['msDocument'] ?? null);
            $db->prepare("INSERT INTO academic_qualifications (academic_id, level, field, university, country, graduation_year, document_file, verified) VALUES (?, 'ماجستير', ?, ?, ?, ?, ?, 1)")
                ->execute([$academicId, trim($_POST['msMajor'] ?? 'عام'), trim($_POST['msUniversity'] ?? 'غير محدد'), trim($_POST['msCountry'] ?? 'السعودية'), intval($_POST['msYear'] ?? 2018), $docFile]);
        }
        if ($hasPhd) {
            $docFile = uploadQualificationDoc($_FILES['phdDocument'] ?? null);
            $db->prepare("INSERT INTO academic_qualifications (academic_id, level, field, university, country, graduation_year, document_file, verified) VALUES (?, 'دكتوراه', ?, ?, ?, ?, ?, 1)")
                ->execute([$academicId, trim($_POST['phdMajor'] ?? 'عام'), trim($_POST['phdUniversity'] ?? 'غير محدد'), trim($_POST['phdCountry'] ?? 'السعودية'), intval($_POST['phdYear'] ?? 2022), $docFile]);
        }

        // Save Services
        if (!empty($services)) {
            $srvStmt = $db->prepare("INSERT INTO academic_services (academic_id, service_id, custom_price) VALUES (?, ?, NULL)");
            foreach ($services as $srvId) {
                $srvStmt->execute([$academicId, intval($srvId)]);
            }
        }

        // Auto log in as academic
        $newAcademic = getAcademicById($academicId);
        loginAcademic($newAcademic);

        if (isset($_SESSION['google_temp_user'])) {
            unset($_SESSION['google_temp_user']);
        }

        sendJSONResponse(true, 'تم تسجيل حسابك بنجاح وقيد المراجعة الآن من الإدارة!');

    } catch (Exception $e) {
        sendJSONResponse(false, 'حدث خطأ أثناء التسجيل: ' . $e->getMessage());
    }
}

// ── AUTH CHECK FOR REMAINING ACTIONS ────────────────────────
if ($action === 'create_order') {
    // Requires logged-in student user
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, 'يرجى تسجيل الدخول كطالب لطلب الخدمة.');
    }
} else {
    // Requires logged-in academic
    if (!isset($_SESSION['academic_id'])) {
        sendJSONResponse(false, 'غير مصرح: يرجى تسجيل الدخول كأكاديمي.');
    }
    $academicId = $_SESSION['academic_id'];
}

// ─────────────────────────────────────────────────────────────
// 2. UPDATE ORDER STATUS
// ─────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────
// 2. ACCEPT / REJECT / UPDATE ORDER ASSIGNMENT
// ─────────────────────────────────────────────────────────────
if ($action === 'accept_assignment' || $action === 'reject_assignment' || $action === 'update_order_status') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($action === 'accept_assignment') {
        $status = 'accepted';
    } elseif ($action === 'reject_assignment') {
        $status = 'rejected';
    }

    if ($orderId <= 0 || !$status) {
        sendJSONResponse(false, 'بيانات غير مكتملة.');
    }

    // Check ownership or assignment
    $db = db();
    $stmt = $db->prepare("SELECT id, order_number, student_id, academic_id, status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        sendJSONResponse(false, 'الطلب غير موجود.');
    }

    $is_authorized = false;
    if ($order['academic_id'] == $academicId) {
        $is_authorized = true;
    } else {
        // Check if academic is assigned in order_assignments table
        $assignStmt = $db->prepare("SELECT COUNT(*) FROM order_assignments WHERE order_id = ? AND academic_id = ?");
        $assignStmt->execute([$orderId, $academicId]);
        if ($assignStmt->fetchColumn() > 0) {
            $is_authorized = true;
        }
    }

    if (!$is_authorized) {
        sendJSONResponse(false, 'غير مصرح: الطلب غير موجود أو غير مرتبط بحسابك.');
    }

    try {
        if ($status === 'accepted') {
            // Update this academic's assignment status
            $db->prepare("UPDATE order_assignments SET status = 'accepted', response_at = NOW() WHERE order_id = ? AND academic_id = ?")
                ->execute([$orderId, $academicId]);

            // If main academic_id is not set, set it to this academic
            if (empty($order['academic_id'])) {
                $db->prepare("UPDATE orders SET academic_id = ? WHERE id = ?")->execute([$academicId, $orderId]);
            }

            // Set order status to accepted
            $db->prepare("UPDATE orders SET status = 'accepted' WHERE id = ?")->execute([$orderId]);

            // Ensure conversation exists
            $convStmt = $db->prepare("SELECT id FROM conversations WHERE order_id = ? LIMIT 1");
            $convStmt->execute([$orderId]);
            if (!$convStmt->fetchColumn()) {
                $db->prepare("INSERT INTO conversations (order_id, student_id, academic_id) VALUES (?, ?, ?)")
                    ->execute([$orderId, $order['student_id'], $academicId]);
            }

            // Fetch academic name
            $acName = $db->query("SELECT name FROM academics WHERE id = $academicId")->fetchColumn() ?: 'الأكاديمي';

            // Send notification to student
            createNotification(
                $order['student_id'],
                'student',
                'تم قبول طلبك بنجاح ✅',
                'قام ' . $acName . ' بقبول طلبك #' . $order['order_number'] . ' وتم فتح قناة المحادثة لبدء العمل.',
                '✅',
                'student/order-details.php?id=' . $orderId
            );

            sendJSONResponse(true, 'تم قبول المهمة بنجاح، يمكنك الآن التواصل مع الطالب والبدء بالتنفيذ.');
        } elseif ($status === 'rejected') {
            // Mark as rejected in order_assignments
            $db->prepare("UPDATE order_assignments SET status = 'rejected', response_at = NOW() WHERE order_id = ? AND academic_id = ?")
                ->execute([$orderId, $academicId]);

            // Check if there are other accepted or pending academics
            $remStmt = $db->prepare("SELECT COUNT(*) FROM order_assignments WHERE order_id = ? AND status != 'rejected'");
            $remStmt->execute([$orderId]);
            $remaining = (int) $remStmt->fetchColumn();

            if ($remaining === 0) {
                // No one left, revert order status to pending_assignment
                $db->prepare("UPDATE orders SET status = 'pending_assignment', academic_id = NULL WHERE id = ?")->execute([$orderId]);

                // Notify admin
                $adminId = (int) $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
                if ($adminId) {
                    createNotification(
                        $adminId,
                        'admin',
                        'اعتذار عن طلب ⚠️',
                        'اعتذر الأكاديمي عن تنفيذ الطلب #' . $order['order_number'] . '، يرجى إعادة إسناد الطلب لأكاديمي آخر.',
                        '⚠️',
                        'admin/pages/order-details.php?id=' . $order['order_number']
                    );
                }
            }

            sendJSONResponse(true, 'تم تسجيل اعتذارك عن هذه المهمة بنجاح.');
        } else {
            // Other status updates (in_progress, revision, completed, etc.)
            updateOrderStatus($orderId, $status);

            $statusLabels = [
                'in_progress' => 'طلبك الآن قيد التنفيذ.',
                'revision' => 'طلبك حالياً تحت المراجعة والتدقيق.',
                'completed' => 'اكتمل تنفيذ طلبك بنجاح.',
                'cancelled' => 'تم إلغاء الطلب.'
            ];
            $msg = $statusLabels[$status] ?? 'تم تحديث حالة طلبك.';

            createNotification(
                $order['student_id'],
                'student',
                'تحديث للطلب #' . $order['order_number'],
                $msg,
                '📋',
                'student/order-details.php?id=' . $orderId
            );

            sendJSONResponse(true, 'تم تحديث حالة الطلب بنجاح.');
        }
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل تحديث الحالة: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// 3. UPDATE PROFILE/SETTINGS
// ─────────────────────────────────────────────────────────────
if ($action === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $availability = trim($_POST['availability'] ?? 'available');
    $startingPrice = floatval($_POST['starting_price'] ?? 0);
    $iban = trim($_POST['iban'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountName = trim($_POST['account_name'] ?? '');
    $services = $_POST['services'] ?? []; // Array of services
    $avatar = trim($_POST['avatar'] ?? '');
    $avatarCleared = isset($_POST['avatar_cleared']) ? (intval($_POST['avatar_cleared']) ? true : false) : false;

    if (!$name || !$email || !$phone) {
        sendJSONResponse(false, 'الاسم والبريد ورقم الجوال حقول مطلوبة.');
    }

    try {
        $db = db();

        // Check email uniqueness
        $check = $db->prepare("SELECT id FROM academics WHERE email = ? AND id != ?");
        $check->execute([$email, $academicId]);
        if ($check->rowCount() > 0) {
            sendJSONResponse(false, 'البريد الإلكتروني مستخدم بالفعل.');
        }

        // Update academic details
        $stmt = $db->prepare(
            "UPDATE academics SET name = ?, email = ?, phone = ?, bio = ?, availability = ?, starting_price = ?, iban = ?, bank_name = ?, account_name = ? WHERE id = ?"
        );
        $stmt->execute([$name, $email, $phone, $bio, $availability, $startingPrice, $iban, $bankName, $accountName, $academicId]);

        // Sync Services
        $db->prepare("DELETE FROM academic_services WHERE academic_id = ?")->execute([$academicId]);
        if (!empty($services)) {
            $ins = $db->prepare("INSERT INTO academic_services (academic_id, service_id) VALUES (?, ?)");
            foreach ($services as $srvId) {
                $ins->execute([$academicId, intval($srvId)]);
            }
        }

        // Update session name/email
        $_SESSION['academic_name'] = $name;
        $_SESSION['academic_email'] = $email;
        $_SESSION['academic_avatar'] = mb_substr($name, 0, 2);

        sendJSONResponse(true, 'تم حفظ التعديلات بنجاح.');
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل الحفظ: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// 4. CHANGE PASSWORD
// ─────────────────────────────────────────────────────────────
if ($action === 'change_password') {
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$current || !$new || !$confirm) {
        sendJSONResponse(false, 'يرجى تعبئة جميع الحقول.');
    }

    if ($new !== $confirm) {
        sendJSONResponse(false, 'كلمتا المرور الجديدتان غير متطابقتين.');
    }

    if (strlen($new) < 6) {
        sendJSONResponse(false, 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل.');
    }

    try {
        $db = db();
        $stmt = $db->prepare("SELECT password FROM academics WHERE id = ?");
        $stmt->execute([$academicId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            sendJSONResponse(false, 'كلمة المرور الحالية غير صحيحة.');
        }

        $update = $db->prepare("UPDATE academics SET password = ? WHERE id = ?");
        $update->execute([password_hash($new, PASSWORD_DEFAULT), $academicId]);

        sendJSONResponse(true, 'تم تحديث كلمة المرور بنجاح.');
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل تحديث كلمة المرور: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// 5. SUBMIT WITHDRAW
// ─────────────────────────────────────────────────────────────
if ($action === 'submit_withdraw') {
    $amount = floatval($_POST['amount'] ?? 0);
    $bankAccountId = intval($_POST['bank_account_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    try {
        $db = db();

        // Fetch payout policy settings
        $settings = [];
        $stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $min_withdrawal = (float) ($settings['min_withdrawal'] ?? 200);
        $withdrawal_eligibility_days = (int) ($settings['withdrawal_eligibility_days'] ?? 14);

        if ($amount < $min_withdrawal) {
            sendJSONResponse(false, 'الحد الأدنى للسحب هو ' . $min_withdrawal . ' ر.س.');
        }
        if ($bankAccountId <= 0) {
            sendJSONResponse(false, 'الرجاء اختيار حساب استلام صحيح.');
        }

        $stmt = $db->prepare("SELECT balance, total_earned FROM academics WHERE id = ?");
        $stmt->execute([$academicId]);
        $row = $stmt->fetch();
        $balance = floatval($row['balance']);
        $totalEarned = floatval($row['total_earned']);
        $totalWithdrawn = max(0, $totalEarned - $balance);

        $totalEligibleEarned = (float) $db->query("
                SELECT SUM(p.academic_net)
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                WHERE o.academic_id = $academicId AND p.status = 'paid' AND DATEDIFF(NOW(), p.paid_at) >= $withdrawal_eligibility_days
            ")->fetchColumn();

        $eligibleBalance = max(0, $totalEligibleEarned - $totalWithdrawn);
        if ($eligibleBalance > $balance)
            $eligibleBalance = $balance;

        if ($amount > $eligibleBalance) {
            sendJSONResponse(false, 'المبلغ يتجاوز الرصيد المؤهل للسحب.');
        }

        // Deduct balance
        $newBalance = $balance - $amount;
        $db->prepare("UPDATE academics SET balance = ? WHERE id = ?")->execute([$newBalance, $academicId]);

        // Insert pending payout
        $nextNum = (int) $db->query("SELECT COALESCE(MAX(id),0)+1 FROM payouts")->fetchColumn();
        $payoutNum = 'WD-' . date('Ym') . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        $insStmt = $db->prepare("INSERT INTO payouts (payout_number, academic_id, bank_account_id, amount, note, status, created_by) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $insStmt->execute([$payoutNum, $academicId, $bankAccountId, $amount, $note, $academicId]);

        sendJSONResponse(true, 'تم إرسال طلب السحب بنجاح. سيتم تحويل المبلغ إلى حسابك البنكي قريباً.');
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل إرسال طلب السحب: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// 6. CREATE SERVICE ORDER FROM STUDENT
// ─────────────────────────────────────────────────────────────
if ($action === 'create_order') {
    $studentId = $_SESSION['user_id'];
    $academicTargetId = intval($_POST['academic_id'] ?? 0);
    $serviceId = intval($_POST['service_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $packageType = trim($_POST['package_type'] ?? 'البداية'); // Default mock package

    if ($academicTargetId <= 0 || $serviceId <= 0 || !$description || !$deadline) {
        sendJSONResponse(false, 'يرجى تعبئة كافة حقول النموذج.');
    }

    try {
        $db = db();

        // Get package info or use academic starting price
        $packageId = null;
        $amount = 349.00; // default average

        // Fetch package price from packages table
        $pkgStmt = $db->prepare("SELECT id, price FROM packages WHERE name = ? LIMIT 1");
        $pkgStmt->execute([$packageType]);
        $pkg = $pkgStmt->fetch();
        if ($pkg) {
            $packageId = $pkg['id'];
            $amount = floatval($pkg['price']);
        } else {
            // Get starting price of academic
            $acPriceStmt = $db->prepare("SELECT starting_price FROM academics WHERE id = ?");
            $acPriceStmt->execute([$academicTargetId]);
            $acPrice = $acPriceStmt->fetchColumn();
            if ($acPrice) {
                $amount = floatval($acPrice);
            }
        }

        // Generate Order Number
        $number = generateOrderNumber();

        // Insert order
        $stmt = $db->prepare(
            'INSERT INTO orders
                (order_number, student_id, academic_id, service_id, package_id, specialty,
                    academic_level, language, description, deadline, amount, status, created_at)
                VALUES (?, ?, NULL, ?, ?, ?, "ماجستير", "العربية", ?, ?, ?, "pending_assignment", NOW())'
        );

        $stmt->execute([
            $number,
            $studentId,
            $serviceId,
            $packageId,
            'عام',
            $description,
            $deadline,
            $amount
        ]);

        $orderId = (int) $db->lastInsertId();

        // Create Payment
        $fee = round($amount * 0.15, 2);
        $net = round($amount - $fee, 2);
        $payNum = generatePaymentNumber();

        $payStmt = $db->prepare(
            'INSERT INTO payments
                (payment_number, order_id, student_id, amount, platform_fee, academic_net, method, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, "credit_card", "paid", NOW())'
        );
        $payStmt->execute([
            $payNum,
            $orderId,
            $studentId,
            $amount,
            $fee,
            $net
        ]);

        // Send notification to admin (user ID 1)
        createNotification(
            1,
            'admin',
            'طلب جديد بانتظار التعيين 📋',
            'تم إنشاء طلب جديد برقم ' . $number . ' وهو بانتظار التعيين للأكاديميين.',
            '📋',
            'admin/pages/order-details.php?id=' . $number
        );

        sendJSONResponse(true, 'تم تقديم الطلب ودفع الرسوم بنجاح! تم تحويل الطلب للإدارة لتعيين الأكاديمي المناسب.');
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل إرسال الطلب: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// 7. MARK NOTIFICATIONS AS READ
// ─────────────────────────────────────────────────────────────
if ($action === 'mark_notifications_read') {
    try {
        markNotificationsRead($academicId, 'academic');
        sendJSONResponse(true, 'تم تعليم الإشعارات كمقروءة.');
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل تحديث الإشعارات.');
    }
}

// ─────────────────────────────────────────────────────────────
// 8. SEND MESSAGE (CHAT)
// ─────────────────────────────────────────────────────────────
if ($action === 'send_message') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $hasFile = !empty($_FILES['file']['name']);

    if ($orderId <= 0 || (!$content && !$hasFile)) {
        sendJSONResponse(false, 'معلومات غير مكتملة.');
    }

    try {
        $db = db();

        // Ensure academic owns the order or is assigned to it
        $orderStmt = $db->prepare("
                SELECT id, student_id FROM orders 
                WHERE id = ? AND (academic_id = ? OR id IN (SELECT order_id FROM order_assignments WHERE academic_id = ? AND status != 'rejected'))
            ");
        $orderStmt->execute([$orderId, $academicId, $academicId]);
        $order = $orderStmt->fetch();

        if (!$order) {
            sendJSONResponse(false, 'الطلب غير موجود أو لا تملك صلاحية.');
        }

        // Get or Create Conversation
        $convStmt = $db->prepare("SELECT id FROM conversations WHERE order_id = ?");
        $convStmt->execute([$orderId]);
        $conversationId = $convStmt->fetchColumn();

        if (!$conversationId) {
            $db->prepare("INSERT INTO conversations (order_id, student_id, academic_id) VALUES (?, ?, ?)")
                ->execute([$orderId, $order['student_id'], $academicId]);
            $conversationId = (int) $db->lastInsertId();
        }

        // رفع الملف (إن وجد) + إدراج الرسالة
        $fileInfo = saveChatAttachmentUpload($_FILES['file'] ?? null);
        sendMessage((int) $conversationId, $academicId, 'academic', $content, $fileInfo);

        // Get academic name
        $acName = $db->query("SELECT name FROM academics WHERE id = $academicId")->fetchColumn() ?: 'الأكاديمي';

        // Send notification to student
        createNotification(
            $order['student_id'],
            'student',
            'رسالة جديدة من ' . $acName,
            ($fileInfo ? 'أرفق ' : 'أرسل ') . 'الأكاديمي رسالة بخصوص طلبك',
            '💬',
            'student/order-details.php?id=' . $orderId
        );

        sendJSONResponse(true, 'تم الإرسال بنجاح.');
    } catch (Exception $e) {
        sendJSONResponse(false, 'حدث خطأ: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// 9. UPLOAD ATTACHMENT
// ─────────────────────────────────────────────────────────────
if ($action === 'upload_attachment') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $file = $_FILES['file'] ?? null;

    if ($orderId <= 0 || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        sendJSONResponse(false, 'يرجى اختيار ملف صحيح.');
    }

    try {
        $db = db();

        // Ensure academic owns the order or is assigned to it
        $orderStmt = $db->prepare("
                SELECT id FROM orders 
                WHERE id = ? AND (academic_id = ? OR id IN (SELECT order_id FROM order_assignments WHERE academic_id = ? AND status != 'rejected'))
            ");
        $orderStmt->execute([$orderId, $academicId, $academicId]);
        if (!$orderStmt->fetch()) {
            sendJSONResponse(false, 'الطلب غير موجود أو لا تملك صلاحية.');
        }

        $uploadDir = __DIR__ . '/../../uploads/orders/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = basename($file['name']);
        $uniqueName = time() . '_' . bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', $fileName);
        $filePath = $uploadDir . $uniqueName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $db->prepare("INSERT INTO order_attachments (order_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, 'academic')")
                ->execute([$orderId, $fileName, 'uploads/orders/' . $uniqueName, $file['type'], $file['size']]);

            sendJSONResponse(true, 'تم الرفع بنجاح.');
        } else {
            sendJSONResponse(false, 'فشل في حفظ الملف.');
        }
    } catch (Exception $e) {
        sendJSONResponse(false, 'حدث خطأ: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// 10. BANK ACCOUNTS (البيانات البنكية)
// ─────────────────────────────────────────────────────────────
if ($action === 'get_bank_accounts') {
    try {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM academic_bank_accounts WHERE academic_id = ? ORDER BY id ASC");
        $stmt->execute([$academicId]);
        sendJSONResponse(true, 'ok', ['accounts' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل جلب البيانات: ' . $e->getMessage());
    }
}

// رفع شعار البنك/المحفظة (اختياري)
if ($action === 'upload_bank_logo') {
    if (empty($_FILES['image'])) {
        sendJSONResponse(false, 'لم يتم استلام الصورة.');
    }
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendJSONResponse(false, 'حدث خطأ أثناء رفع الصورة (رمز ' . $file['error'] . ').');
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        sendJSONResponse(false, 'صيغة الصورة غير مدعومة (JPG/PNG/GIF/WEBP/SVG).');
    }

    $isRealImage = false;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        $isRealImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);
    } else {
        $info = @getimagesize($file['tmp_name']);
        $isRealImage = is_array($info) && !empty($info['mime'])
            && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }
    if (!$isRealImage) {
        sendJSONResponse(false, 'الملف ليس صورة صالحة.');
    }

    $uploadDir = dirname(__DIR__) . '/../uploads/bank_logos';
    if (!is_dir($uploadDir))
        @mkdir($uploadDir, 0755, true);
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        sendJSONResponse(false, 'مجلد رفع الشعارات (uploads/bank_logos) غير قابل للكتابة.');
    }

    $fileName = 'bank_logo_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $dest = rtrim($uploadDir, '/\\') . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        sendJSONResponse(false, 'فشل حفظ الصورة على الخادم.');
    }

    sendJSONResponse(true, 'تم رفع الشعار بنجاح.', ['image' => 'uploads/bank_logos/' . $fileName]);
}

if ($action === 'add_bank_account' || $action === 'update_bank_account') {
    $isEdit = ($action === 'update_bank_account');
    $id = intval($_POST['id'] ?? 0);
    $accountType = trim($_POST['account_type'] ?? 'bank');
    $accountName = trim($_POST['account_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $holderName = trim($_POST['holder_name'] ?? '');
    $logo = trim($_POST['logo'] ?? '');
    $logoCleared = isset($_POST['logo_cleared']) ? (intval($_POST['logo_cleared']) ? true : false) : false;

    if (!in_array($accountType, ['bank', 'wallet'], true)) {
        $accountType = 'bank';
    }
    if (!$accountName || !$accountNumber) {
        sendJSONResponse(false, 'يرجى إدخال اسم البنك أو المحفظة والرقم.');
    }

    try {
        $db = db();

        if ($isEdit) {
            // Verify the academic owns this account before updating
            $check = $db->prepare("SELECT id, logo FROM academic_bank_accounts WHERE id = ? AND academic_id = ?");
            $check->execute([$id, $academicId]);
            $existing = $check->fetch();
            if (!$existing) {
                sendJSONResponse(false, 'الحساب غير موجود أو لا تملك صلاحية تعديله.');
            }

            // معالجة الشعار: إزالة صريحة / صورة جديدة / الاحتفاظ بالقديمة
            if ($logoCleared) {
                $logo = null;
            } elseif ($logo === '') {
                $logo = $existing['logo'] ?? null;
            }

            $db->prepare(
                "UPDATE academic_bank_accounts SET account_type = ?, account_name = ?, account_number = ?, holder_name = ?, logo = ? WHERE id = ? AND academic_id = ?"
            )->execute([$accountType, $accountName, $accountNumber, $holderName, $logo, $id, $academicId]);
            sendJSONResponse(true, 'تم تحديث الحساب بنجاح.');
        } else {
            if ($logoCleared)
                $logo = null;
            $db->prepare(
                "INSERT INTO academic_bank_accounts (academic_id, account_type, account_name, account_number, holder_name, logo) VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$academicId, $accountType, $accountName, $accountNumber, $holderName, $logo]);
            sendJSONResponse(true, 'تمت إضافة الحساب بنجاح.');
        }
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل الحفظ: ' . $e->getMessage());
    }
}

if ($action === 'delete_bank_account') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        sendJSONResponse(false, 'معرّف حساب غير صالح.');
    }
    try {
        $db = db();
        $del = $db->prepare("DELETE FROM academic_bank_accounts WHERE id = ? AND academic_id = ?");
        $del->execute([$id, $academicId]);
        sendJSONResponse(true, 'تم حذف الحساب بنجاح.');
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل الحذف: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// 11. QUALIFICATIONS (المؤهلات الأكاديمية)
// ─────────────────────────────────────────────────────────────
if ($action === 'get_qualifications') {
    try {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM academic_qualifications WHERE academic_id = ? ORDER BY id ASC");
        $stmt->execute([$academicId]);
        sendJSONResponse(true, 'ok', ['qualifications' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل جلب البيانات: ' . $e->getMessage());
    }
}

if ($action === 'add_qualification' || $action === 'update_qualification') {
    $isEdit = ($action === 'update_qualification');
    $id = intval($_POST['id'] ?? 0);
    $level = trim($_POST['level'] ?? 'بكالوريوس');
    $field = trim($_POST['field'] ?? '');
    $university = trim($_POST['university'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $year = !empty($_POST['graduation_year']) ? intval($_POST['graduation_year']) : null;

    if (!in_array($level, ['بكالوريوس', 'ماجستير', 'دكتوراه'], true)) {
        $level = 'بكالوريوس';
    }
    if (!$field || !$university) {
        sendJSONResponse(false, 'يرجى إدخال التخصص والجامعة.');
    }

    try {
        $db = db();

        // التأكد من وجود عمود وثيقة المؤهل قبل الحفظ (السيرفرات القديمة تفتقده)
        if (!columnExists($db, 'academic_qualifications', 'document_file')) {
            if (!ensureColumn($db, 'academic_qualifications', 'document_file', 'VARCHAR(255) DEFAULT NULL')) {
                sendJSONResponse(false, 'تعذر حفظ المؤهل: عمود الوثيقة (document_file) غير موجود في قاعدة البيانات ولم تنجح الإضافة التلقائية (ينقص المستخدم صلاحية ALTER TABLE). يُرجى تشغيل ملف الترحيل migrations/2026_09_14_02_add_document_file_to_academic_qualifications.sql على قاعدة البيانات.');
            }
        }

        $docFile = uploadQualificationDoc($_FILES['document_file'] ?? null);

        if ($isEdit) {
            $check = $db->prepare("SELECT document_file FROM academic_qualifications WHERE id = ? AND academic_id = ?");
            $check->execute([$id, $academicId]);
            $existing = $check->fetch();
            if (!$existing) {
                sendJSONResponse(false, 'المؤهل غير موجود أو لا تملك صلاحية تعديله.');
            }
            if (!$docFile) {
                $docFile = $existing['document_file'];
            }
            $db->prepare(
                "UPDATE academic_qualifications SET level = ?, field = ?, university = ?, country = ?, graduation_year = ?, document_file = ? WHERE id = ? AND academic_id = ?"
            )->execute([$level, $field, $university, $country, $year, $docFile, $id, $academicId]);
            sendJSONResponse(true, 'تم تحديث المؤهل بنجاح.');
        } else {
            $db->prepare(
                "INSERT INTO academic_qualifications (academic_id, level, field, university, country, graduation_year, document_file, verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
            )->execute([$academicId, $level, $field, $university, $country, $year, $docFile]);
            sendJSONResponse(true, 'تمت إضافة المؤهل بنجاح.');
        }
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل حفظ المؤهل: ' . $e->getMessage());
    }
}

if ($action === 'delete_qualification') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        sendJSONResponse(false, 'معرّف مؤهل غير صالح.');
    }
    try {
        $db = db();
        $del = $db->prepare("DELETE FROM academic_qualifications WHERE id = ? AND academic_id = ?");
        $del->execute([$id, $academicId]);
        sendJSONResponse(true, 'تم حذف المؤهل بنجاح.');
    } catch (Exception $e) {
        sendJSONResponse(false, 'فشل الحذف: ' . $e->getMessage());
    }
}

sendJSONResponse(false, 'إجراء غير معروف.');
