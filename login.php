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
    .auth-bg {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.05) 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
    }
    .auth-card {
      max-width: 500px;
      width: 100%;
      padding: 48px 40px;
      border-radius: 48px;
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      transition: all 0.3s ease;
    }
    .auth-card:hover {
      box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.15);
      border-color: var(--primary-light);
    }
    .type-btn {
      padding: 14px;
      border: 2px solid var(--border-color);
      border-radius: 40px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      font-weight: 700;
      color: var(--text-secondary);
      background: transparent;
      width: 100%;
      font-family: inherit;
      font-size: 15px;
    }
    .type-btn.active {
      border-color: var(--primary);
      background: rgba(79, 70, 229, 0.08);
      color: var(--primary);
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
    }
    .type-btn:hover:not(.active) {
      border-color: var(--primary-light);
      background: rgba(79, 70, 229, 0.03);
    }
    .alert-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
      padding: 14px 18px;
      border-radius: 24px;
      margin-bottom: 24px;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .alert-success {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #16a34a;
      padding: 14px 18px;
      border-radius: 24px;
      margin-bottom: 24px;
      font-size: 14px;
      font-weight: 600;
    }
    .form-input {
      width: 100%;
      padding: 14px 18px;
      border: 2px solid var(--border-color);
      border-radius: 24px;
      background: var(--bg-main);
      color: var(--text-primary);
      font-size: 15px;
      transition: all 0.3s;
      outline: none;
      font-family: inherit;
    }
    .form-input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    .demo-box {
      margin-top: 28px;
      padding: 20px;
      background: rgba(79, 70, 229, 0.03);
      border-radius: 28px;
      border: 1px solid var(--border-color);
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.7;
    }
    .demo-box code {
      background: var(--bg-card);
      padding: 2px 8px;
      border-radius: 20px;
      font-family: monospace;
      color: var(--primary);
    }
    .fade-up {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }
  </style>
</head>
<body class="auth-bg">
  <div class="auth-card fade-up">

    <!-- Header أنيق مثل باقي الصفحات -->
    <div style="text-align:center;margin-bottom:32px">
      <a href="index.php" style="display:inline-block;font-size:36px;font-weight:900;color:var(--primary);text-decoration:none;margin-bottom:16px">🎓 تواصل</a>
      <span style="display:inline-block;background:var(--primary-light);color:var(--primary);padding:4px 16px;border-radius:40px;font-size:12px;font-weight:700;margin-bottom:16px">تسجيل الدخول</span>
      <h1 style="font-size:28px;font-weight:800;color:var(--text-primary);margin-bottom:8px">مرحباً بك مجدداً 👋</h1>
      <p style="color:var(--text-secondary);font-size:14px">قم بتسجيل الدخول للمتابعة إلى حسابك</p>
    </div>

    <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <!-- تبويب نوع المستخدم -->
    <div style="display:flex;gap:16px;margin-bottom:32px">
      <button type="button" class="type-btn active" id="btn-student"  onclick="setUser('student')">📚 بصفتي طالب</button>
      <button type="button" class="type-btn"        id="btn-academic" onclick="setUser('academic')">🎓 بصفتي أكاديمي</button>
    </div>

    <form method="POST" action="login.php" id="loginForm">
      <input type="hidden" name="user_type" id="userTypeInput" value="<?= isset($_POST['user_type']) ? e($_POST['user_type']) : 'student' ?>">

      <div style="margin-bottom:20px">
        <label style="display:block;font-weight:700;margin-bottom:10px;color:var(--text-primary)">📧 البريد الإلكتروني</label>
        <input type="email" name="email" class="form-input"
               value="<?= e($_POST['email'] ?? '') ?>"
               placeholder="ahmed@example.com" dir="ltr" required autocomplete="email">
      </div>

      <div style="margin-bottom:28px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
          <label style="font-weight:700;color:var(--text-primary)">🔒 كلمة المرور</label>
          <a href="forgot-password.php" style="font-size:13px;color:var(--primary);font-weight:600;text-decoration:none;transition:0.2s">نسيت كلمة المرور؟</a>
        </div>
        <input type="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary" id="submitBtn"
              style="width:100%;padding:16px;font-size:16px;font-weight:800;border-radius:40px;margin-bottom:8px">
        تسجيل الدخول 🚀
      </button>
    </form>

    <!-- حسابات تجريبية بشكل أنيق -->
    <div class="demo-box">
      <div style="font-weight:800;margin-bottom:12px;color:var(--text-primary)">🔐 حسابات تجريبية للاختبار:</div>
      <div style="display:grid;grid-template-columns:auto 1fr;gap:8px 12px;font-size:13px">
        <span>👨‍💼 مدير:</span><span><code>admin@tawassul.com</code></span>
        <span>👨‍🎓 طالب:</span><span><code>ahmed@student.com</code></span>
        <span>👨‍🏫 أكاديمي:</span><span><code>dr.mohammed@academic.com</code></span>
        <span>🔑 كلمة المرور:</span><span><code>password</code> (للجميع)</span>
      </div>
    </div>

    <div style="margin-top:28px;text-align:center;font-size:14px;color:var(--text-secondary)">
      ليس لديك حساب بعد؟
      <a href="register.php" style="color:var(--primary);font-weight:800;text-decoration:none">إنشاء حساب جديد ✨</a>
    </div>

  </div>

  <script src="assets/js/global.js"></script>
  <script>
    // استعادة التبويب النشط عند إعادة تحميل الصفحة (في حال وجود خطأ)
    const savedType = document.getElementById('userTypeInput').value;
    if (savedType === 'academic') setUser('academic');

    function setUser(type) {
      document.getElementById('userTypeInput').value = type;
      document.getElementById('btn-student').classList.toggle('active', type === 'student');
      document.getElementById('btn-academic').classList.toggle('active', type === 'academic');
    }

    // تأثير تعطيل الزر أثناء الإرسال لمنع التكرار
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('submitBtn');
      btn.textContent = 'جاري التحقق... ⏳';
      btn.disabled = true;
    });

    // تأثير fade-up عند التمرير (للعناصر التي تحتاج إلى ظهور تدريجي)
    const fadeElement = document.querySelector('.fade-up');
    if (fadeElement) {
      fadeElement.style.opacity = '0';
      fadeElement.style.transform = 'translateY(30px)';
      setTimeout(() => {
        fadeElement.style.opacity = '1';
        fadeElement.style.transform = 'translateY(0)';
      }, 100);
    }
  </script>
</body>
</html>
