<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/mailer.php';

ensurePasswordResetsTable();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');

$error = '';
$success = '';
$validToken = false;
$resetRecord = null;

if (!$token || !$email) {
    $error = 'رابط الاستعادة غير صالح أو تنقصه بيانات التحقق.';
} else {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND email = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token, $email]);
    $resetRecord = $stmt->fetch();

    if ($resetRecord) {
        $validToken = true;
    } else {
        $error = 'انتهت صلاحية رابط الاستعادة أو تم استخدامه مسبقاً. يرجى طلب رابط استعادة جديد.';
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 6) {
        $error = 'يجب أن تتكون كلمة المرور من 6 أحرف أو أرقام على الأقل.';
    } elseif ($password !== $password_confirm) {
        $error = 'كلمتا المرور غير متطابقتين، يرجى إعادة المحاولة.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $userType = $resetRecord['user_type'];
        $db = db();
        $updated = false;
        $userName = 'المستخدم';

        if ($userType === 'academic') {
            $uStmt = $db->prepare("UPDATE academics SET password = ? WHERE email = ?");
            $updated = $uStmt->execute([$hashed, $email]);

            $nameStmt = $db->prepare("SELECT name FROM academics WHERE email = ?");
            $nameStmt->execute([$email]);
            $userName = $nameStmt->fetchColumn() ?: $userName;
        } else {
            $uStmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $updated = $uStmt->execute([$hashed, $email]);

            $nameStmt = $db->prepare("SELECT name FROM users WHERE email = ?");
            $nameStmt->execute([$email]);
            $userName = $nameStmt->fetchColumn() ?: $userName;
        }

        if ($updated) {
            // Delete used reset tokens for this email
            $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            // Send Confirmation Email
            sendPasswordChangedConfirmationEmail($email, $userName);

            $success = 'تم تحديث كلمة المرور بنجاح! يمكنك الآن تسجيل الدخول باستخدام كلمة المرور الجديدة.';
            $validToken = false; // Hide form
        } else {
            $error = 'حدث خطأ أثناء حفظ كلمة المرور الجديدة في قاعدة البيانات.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تعيين كلمة المرور الجديدة - Eduroad</title>
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
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.05) 100%);
      color: #059669;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      margin-bottom: 20px;
      border: 1px solid rgba(16, 185, 129, 0.2);
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
    
    <div class="icon-badge">🔑</div>
    <h2 class="text-2xl font-black text-slate-800 mb-2">تعيين كلمة المرور الجديدة</h2>
    <p class="text-sm text-slate-500 mb-6 leading-relaxed">
      أدخل كلمة مرور قوية جديدة لتأمين حسابك والبدء في استخدامه.
    </p>

    <?php if ($error): ?>
      <div class="mb-6 p-4 rounded-xl text-sm leading-relaxed text-right bg-rose-50 text-rose-800 border border-rose-200">
        <div class="flex items-center gap-2 font-bold mb-1">
          <span>⚠️</span>
          <span>تنبيه</span>
        </div>
        <div><?php echo htmlspecialchars($error); ?></div>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="mb-6 p-4 rounded-xl text-sm leading-relaxed text-right bg-emerald-50 text-emerald-800 border border-emerald-200">
        <div class="flex items-center gap-2 font-bold mb-1">
          <span>✅</span>
          <span>نجاح العملية</span>
        </div>
        <div><?php echo htmlspecialchars($success); ?></div>
      </div>
      <div class="mt-4">
        <a href="login.php" class="btn-submit inline-block text-center no-underline">
          الانتقال لتسجيل الدخول
        </a>
      </div>
    <?php elseif ($validToken): ?>
      <form method="POST" action="reset-password.php" class="text-right">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

        <div class="mb-4">
          <label class="block text-xs font-bold text-slate-700 mb-2">كلمة المرور الجديدة (6 أحرف أو أكثر)</label>
          <input type="password" name="password" class="form-input" placeholder="••••••••" required autofocus minlength="6">
        </div>

        <div class="mb-6">
          <label class="block text-xs font-bold text-slate-700 mb-2">تأكيد كلمة المرور الجديدة</label>
          <input type="password" name="password_confirm" class="form-input" placeholder="••••••••" required minlength="6">
        </div>

        <button type="submit" class="btn-submit">
          💾 حفظ كلمة المرور وتسجيل الدخول
        </button>
      </form>
    <?php else: ?>
      <div class="mt-4">
        <a href="forgot-password.php" class="btn-submit inline-block text-center no-underline">
          طلب رابط استعادة جديد
        </a>
      </div>
    <?php endif; ?>

    <div class="mt-8 pt-6 border-t border-slate-100 text-sm text-slate-500">
      <a href="login.php" class="text-indigo-600 font-bold hover:underline">← العودة لتسجيل الدخول</a>
    </div>

  </div>

</body>
</html>
