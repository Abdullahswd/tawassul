<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/mailer.php';

$message = '';
$messageType = ''; // success or error
$submittedEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $submittedEmail = $email;

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'يرجى إدخال عنوان بريد إلكتروني صحيح.';
        $messageType = 'error';
    } else {
        $db = db();
        
        // 1. Check in users table (student/admin)
        $userStmt = $db->prepare("SELECT id, name, email, role, status FROM users WHERE email = ? LIMIT 1");
        $userStmt->execute([$email]);
        $user = $userStmt->fetch();

        // 2. Check in academics table
        $acadStmt = $db->prepare("SELECT id, name, email, status FROM academics WHERE email = ? LIMIT 1");
        $acadStmt->execute([$email]);
        $academic = $acadStmt->fetch();

        if ($user) {
            $userType = ($user['role'] === 'admin') ? 'admin' : 'student';
            $userName = $user['name'];
            $token = bin2hex(random_bytes(32));
            
            $sent = sendPasswordResetEmail($email, $userName, $token, $userType);
            if ($sent) {
                $message = 'تم إرسال رابط استعادة كلمة المرور إلى بريدك الإلكتروني بنجاح! يرجى مراجعة صندوق الوارد (أو مجلد البريد غير المرغوب فيه).';
                $messageType = 'success';
            } else {
                $message = 'حدث خطأ أثناء إرسال البريد الإلكتروني. يرجى التأكد من إعدادات خادم SMTP أو المحاولة لاحقاً.';
                $messageType = 'error';
            }
        } elseif ($academic) {
            if ($academic['status'] === 'suspended') {
                $message = 'هذا الحساب موقوف / مجمد حالياً من قِبل الإدارة. يرجى التواصل مع الدعم الفني للمساعدة.';
                $messageType = 'error';
            } elseif ($academic['status'] === 'rejected') {
                $message = 'هذا الحساب مرفوض من قِبل الإدارة. للاستفسار يرجى مراجعة المنصة.';
                $messageType = 'error';
            } else {
                $userType = 'academic';
                $userName = $academic['name'];
                $token = bin2hex(random_bytes(32));

                $sent = sendPasswordResetEmail($email, $userName, $token, $userType);
                if ($sent) {
                    $message = 'تم إرسال رابط استعادة كلمة المرور إلى بريدك الإلكتروني بنجاح! يرجى مراجعة صندوق الوارد.';
                    $messageType = 'success';
                } else {
                    $message = 'حدث خطأ أثناء إرسال البريد الإلكتروني. يرجى التأكد من إعدادات خادم SMTP أو المحاولة لاحقاً.';
                    $messageType = 'error';
                }
            }
        } else {
            // Friendly message
            $message = 'إذا كان هذا البريد مسجلاً في منصتنا، فستصلك رسالة تحتوي على رابط الاستعادة خلال دقائق.';
            $messageType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>استعادة كلمة المرور - Eduroad</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    * { font-family: 'Tajawal', sans-serif; }
    .auth-bg {
      background: radial-gradient(circle at 50% 10%, rgba(99, 102, 241, 0.12) 0%, rgba(15, 23, 42, 0.02) 60%), #f8fafc;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .auth-card {
      max-width: 460px;
      width: 100%;
      background: #ffffff;
      border-radius: 28px;
      padding: 44px 36px;
      box-shadow: 0 20px 45px -15px rgba(15, 23, 42, 0.08);
      border: 1px solid #e2e8f0;
      text-align: center;
    }
    .icon-badge {
      width: 72px;
      height: 72px;
      border-radius: 22px;
      background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(79, 70, 229, 0.05) 100%);
      color: #4f46e5;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      margin-bottom: 20px;
      border: 1px solid rgba(99, 102, 241, 0.2);
    }
    .form-input {
      width: 100%;
      padding: 13px 18px;
      border-radius: 12px;
      border: 1.5px solid #e2e8f0;
      background: #f8fafc;
      font-size: 15px;
      transition: all 0.2s ease;
      outline: none;
    }
    .form-input:focus {
      border-color: #4f46e5;
      background: #ffffff;
      box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
    .btn-submit {
      width: 100%;
      padding: 14px;
      border-radius: 12px;
      background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
      color: #ffffff;
      font-weight: 800;
      font-size: 16px;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.3);
    }
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 25px -5px rgba(79, 70, 229, 0.4);
    }
  </style>
</head>
<body class="auth-bg">

  <div class="auth-card">
    
    <div class="icon-badge">🔐</div>
    <h2 class="text-2xl font-black text-slate-800 mb-2">استعادة كلمة المرور</h2>
    <p class="text-sm text-slate-500 mb-6 leading-relaxed">
      أدخل بريدك الإلكتروني المسجل وسنرسل لك رابطاً آمناً لإعادة تعيين كلمة المرور فوراً عبر بريدك.
    </p>

    <?php if ($message): ?>
      <div class="mb-6 p-4 rounded-xl text-sm leading-relaxed text-right <?php echo $messageType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
        <div class="flex items-center gap-2 font-bold mb-1">
          <span><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?></span>
          <span><?php echo $messageType === 'success' ? 'تمت العملية' : 'تنبيه'; ?></span>
        </div>
        <div><?php echo htmlspecialchars($message); ?></div>
      </div>
    <?php endif; ?>

    <?php if ($messageType !== 'success'): ?>
      <form method="POST" action="forgot-password.php" class="text-right">
        <div class="mb-5">
          <label class="block text-xs font-bold text-slate-700 mb-2">البريد الإلكتروني المسجل</label>
          <input type="email" name="email" value="<?php echo htmlspecialchars($submittedEmail); ?>" class="form-input" placeholder="ahmad@example.com" dir="ltr" required autofocus>
        </div>

        <button type="submit" class="btn-submit">
          📩 إرسال رابط الاستعادة
        </button>
      </form>
    <?php else: ?>
      <div class="mt-4">
        <a href="login.php" class="btn-submit inline-block text-center no-underline">
          العودة لتسجيل الدخول
        </a>
      </div>
    <?php endif; ?>

    <div class="mt-8 pt-6 border-t border-slate-100 text-sm text-slate-500">
      <a href="login.php" class="text-indigo-600 font-bold hover:underline">← العودة لتسجيل الدخول</a>
    </div>

  </div>

</body>
</html>
