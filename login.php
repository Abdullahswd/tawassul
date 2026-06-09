<?php
/**
 * login.php — Real database authentication
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['user_role'] === 'admin' ? 'admin/pages/dashboard.php' : 'student/student-dashboard.php'));
    exit;
}
if (isset($_SESSION['academic_id'])) {
    header('Location: academics/academic-dashboard.php');
    exit;
}

$error   = '';
$success = '';

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $type     = $_POST['user_type']     ?? 'student'; // student | academic

    if (!$email || !$password) {
        $error = 'يرجى تعبئة جميع الحقول.';
    } else {
        if ($type === 'academic') {
            // ── Academic login ──
            $academic = getAcademicByEmail($email);
            if ($academic && password_verify($password, $academic['password'])) {
                if ($academic['status'] === 'rejected') {
                    $error = 'تم رفض حسابك من قِبل الإدارة. للاستفسار تواصل معنا.';
                } else {
                    loginAcademic($academic);
                    header('Location: academics/academic-dashboard.php');
                    exit;
                }
            } else {
                $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
            }
        } else {
            // ── Student / Admin login ──
            $user = getUserByEmail($email);
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'suspended') {
                    $error = 'تم تعليق حسابك. يرجى التواصل مع الدعم.';
                } else {
                    loginUser($user);
                    $redirect = $_GET['redirect'] ?? null;
                    if ($redirect) {
                        header('Location: ' . $redirect);
                    } elseif ($user['role'] === 'admin') {
                        header('Location: admin/pages/dashboard.php');
                    } else {
                        header('Location: student/student-dashboard.php');
                    }
                    exit;
                }
            } else {
                $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تسجيل الدخول - تواصل</title>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    .auth-bg  { background: linear-gradient(135deg, var(--bg-main) 0%, rgba(79,70,229,0.05) 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
    .auth-card{ max-width:480px; width:100%; padding:40px; margin:0 auto; box-shadow:0 10px 25px -5px rgba(0,0,0,0.1); }
    .type-btn { padding:12px; border:2px solid var(--border-color); border-radius:12px; text-align:center; cursor:pointer; transition:all 0.2s; font-weight:700; color:var(--text-secondary); background:transparent; width:100%; font-family:inherit; font-size:15px; }
    .type-btn.active { border-color:var(--primary); background:rgba(79,70,229,0.05); color:var(--primary); }
    .alert-error   { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
    .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
  </style>
</head>
<body class="auth-bg">
  <div class="card auth-card fade-up">

    <div style="text-align:center;margin-bottom:32px">
      <a href="index.php" style="font-size:32px;font-weight:900;color:var(--primary);text-decoration:none">🎓 تواصل</a>
      <h1 style="font-size:24px;font-weight:800;color:var(--text-primary);margin-top:16px;margin-bottom:8px">مرحباً بك مجدداً 👋</h1>
      <p style="color:var(--text-secondary);font-size:14px">قم بتسجيل الدخول للمتابعة إلى حسابك</p>
    </div>

    <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <!-- User Type Toggle -->
    <div style="display:flex;gap:12px;margin-bottom:32px">
      <button type="button" class="type-btn active" id="btn-student"  onclick="setUser('student')">بصفتي طالب</button>
      <button type="button" class="type-btn"        id="btn-academic" onclick="setUser('academic')">بصفتي أكاديمي</button>
    </div>

    <form method="POST" action="login.php" id="loginForm">
      <input type="hidden" name="user_type" id="userTypeInput" value="<?= isset($_POST['user_type']) ? e($_POST['user_type']) : 'student' ?>">

      <div style="margin-bottom:20px">
        <label style="display:block;font-weight:700;margin-bottom:8px;color:var(--text-primary)">البريد الإلكتروني</label>
        <input type="email" name="email" class="form-input"
               value="<?= e($_POST['email'] ?? '') ?>"
               placeholder="ahmad@example.com" dir="ltr" required autocomplete="email">
      </div>

      <div style="margin-bottom:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <label style="font-weight:700;color:var(--text-primary)">كلمة المرور</label>
          <a href="forgot-password.php" style="font-size:12px;color:var(--primary);font-weight:600;text-decoration:none">نسيت كلمة المرور؟</a>
        </div>
        <input type="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary" id="submitBtn"
              style="width:100%;padding:14px;font-size:16px">تسجيل الدخول</button>
    </form>

    <div style="margin-top:24px;padding:16px;background:var(--bg-body);border-radius:10px;font-size:13px;color:var(--text-muted)">
      <strong>حسابات تجريبية:</strong><br>
      👨‍💼 مدير: admin@tawassul.com<br>
      👨‍🎓 طالب: ahmed@student.com<br>
      👨‍🏫 أكاديمي: dr.mohammed@academic.com<br>
      🔑 كلمة مرور الجميع: <code>password</code>
    </div>

    <div style="margin-top:24px;text-align:center;font-size:14px;color:var(--text-secondary)">
      ليس لديك حساب بعد؟
      <a href="register.php" style="color:var(--primary);font-weight:700;text-decoration:none">إنشاء حساب جديد</a>
    </div>

  </div>

  <script src="assets/js/global.js"></script>
  <script>
    // Restore selected tab on validation error
    const savedType = document.getElementById('userTypeInput').value;
    if (savedType === 'academic') setUser('academic');

    function setUser(type) {
      document.getElementById('userTypeInput').value = type;
      document.getElementById('btn-student').classList.toggle('active', type === 'student');
      document.getElementById('btn-academic').classList.toggle('active', type === 'academic');
    }

    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('submitBtn');
      btn.textContent = 'جاري التحقق...';
      btn.disabled = true;
    });
  </script>
</body>
</html>
