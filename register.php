<?php
/**
 * register.php — Real database student registration
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

// Already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: student/student-dashboard.php');
    exit;
}

$error   = '';
$success = '';
$step    = 1;

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_register'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $password  = $_POST['password']        ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $step      = 2;

    // Validation
    if (!$firstName || !$lastName || !$email || !$phone || !$password) {
        $error = 'يرجى تعبئة جميع الحقول المطلوبة.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صحيح.';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
    } elseif ($password !== $confirm) {
        $error = 'كلمتا المرور غير متطابقتين.';
    } elseif (getUserByEmail($email)) {
        $error = 'هذا البريد الإلكتروني مسجَّل مسبقاً.';
    } else {
        // Create user
        $fullName = $firstName . ' ' . $lastName;
        $userId   = createUser($fullName, $email, $password, $phone);
        $user     = getUserById($userId);
        loginUser($user);

        // Welcome notification
        createNotification($userId, 'student', 'مرحباً بك في تواصل 🎉', 'تم إنشاء حسابك بنجاح. ابدأ بتصفح الخدمات المتاحة.', '🎓', 'student/services.php');

        header('Location: student/student-dashboard.php');
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إنشاء حساب - تواصل</title>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    .auth-bg {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.05) 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 60px 20px;
    }
    .auth-card {
      max-width: 650px;
      width: 100%;
      padding: 48px 40px;
      border-radius: 48px;
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      transition: all 0.3s ease;
      box-sizing: border-box;
    }
    .auth-card:hover {
      box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.15);
      border-color: var(--primary-light);
    }
    .type-sel {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
      padding: 28px 20px;
      border: 2px solid var(--border-color);
      border-radius: 32px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      background: var(--bg-main);
      text-align: center;
    }
    .type-sel:hover {
      border-color: var(--primary-light);
      transform: translateY(-5px);
      box-shadow: 0 10px 20px -8px rgba(0,0,0,0.1);
    }
    .type-sel.active {
      border-color: var(--primary);
      background: rgba(79, 70, 229, 0.05);
      box-shadow: 0 8px 20px -6px rgba(79, 70, 229, 0.2);
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
    /* Grid للحقول المتجاورة */
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }
    .form-item {
      margin-bottom: 20px;
    }
    .form-item label, .form-row label {
      display: block;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--text-primary);
      font-size: 14px;
    }
    .form-input {
      width: 100%;
      padding: 14px 18px;
      border: 2px solid var(--border-color);
      border-radius: 28px;
      background: var(--bg-main);
      color: var(--text-primary);
      font-size: 15px;
      transition: all 0.3s;
      outline: none;
      font-family: inherit;
      box-sizing: border-box;
    }
    .form-input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    .terms-box {
      margin-bottom: 28px;
      padding: 16px 20px;
      background: rgba(79, 70, 229, 0.03);
      border-radius: 28px;
      border: 1px solid var(--border-color);
      font-size: 13px;
      color: var(--text-muted);
      text-align: center;
    }
    /* تحسين المسافات على اليمين واليسار داخل الحقول */
    .auth-card .form-input {
      width: 100%;
    }
    /* استجابة للشاشات الصغيرة */
    @media (max-width: 580px) {
      .auth-card { padding: 32px 24px; }
      .form-row { 
        grid-template-columns: 1fr;
        gap: 0;
      }
      .form-row .form-item:first-child {
        margin-bottom: 20px;
      }
    }
    .fade-up {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }
    /* ضمان عدم التصاق الحقول بالحدود */
    .auth-card form {
      width: 100%;
      overflow: hidden;
    }
  </style>
</head>
<body class="auth-bg">
  <div class="auth-card fade-up">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:32px">
      <a href="index.php" style="display:inline-block;font-size:36px;font-weight:900;color:var(--primary);text-decoration:none;margin-bottom:16px">🎓 تواصل</a>
      <span style="display:inline-block;background:var(--primary-light);color:var(--primary);padding:4px 16px;border-radius:40px;font-size:12px;font-weight:700;margin-bottom:16px">انضم إلينا</span>
      <h1 style="font-size:28px;font-weight:800;color:var(--text-primary);margin-bottom:8px">كن متصلاً بشبكة المعرفة ✨</h1>
      <p style="color:var(--text-secondary);font-size:14px">اختر نوع الحساب الذي يناسبك وابدأ رحلتك الأكاديمية</p>
    </div>

    <!-- Step 1: اختيار نوع الحساب -->
    <div id="step1" <?= ($step === 2 && $error) ? 'style="display:none"' : '' ?>>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:40px">
        <div class="type-sel active" id="sel-student" onclick="selectType('student')">
          <div style="font-size:48px">👨‍🎓</div>
          <div style="font-weight:800;color:var(--text-primary);font-size:20px">حساب طالب</div>
          <div style="font-size:13px;color:var(--text-secondary);text-align:center">أبحث عن خدمات أكاديمية وبحوث احترافية.</div>
        </div>
        <div class="type-sel" id="sel-academic" onclick="selectType('academic')">
          <div style="font-size:48px">👨‍🏫</div>
          <div style="font-weight:800;color:var(--text-primary);font-size:20px">حساب أكاديمي</div>
          <div style="font-size:13px;color:var(--text-secondary);text-align:center">متخصص وأرغب في تقديم خدماتي للطلاب.</div>
        </div>
      </div>
      <button class="btn btn-primary" style="width:100%;padding:16px;font-size:16px;font-weight:800;border-radius:40px" onclick="nextStep()">المتابعة ←</button>
    </div>

    <!-- Step 2: نموذج تسجيل الطالب -->
    <div id="step2" <?= ($step !== 2 || !$error) ? 'style="display:none"' : '' ?>>

      <?php if ($error): ?>
        <div class="alert-error">⚠️ <?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="register.php" onsubmit="handleSubmit(this)">
        <input type="hidden" name="do_register" value="1">

        <div class="form-row">
          <div>
            <label>📝 الاسم الأول <span style="color:var(--danger)">*</span></label>
            <input type="text" name="first_name" class="form-input"
                   value="<?= e($_POST['first_name'] ?? '') ?>"
                   placeholder="مثال: محمد" required>
          </div>
          <div>
            <label>📝 اسم العائلة <span style="color:var(--danger)">*</span></label>
            <input type="text" name="last_name" class="form-input"
                   value="<?= e($_POST['last_name'] ?? '') ?>"
                   placeholder="مثال: الأحمد" required>
          </div>
        </div>

        <div class="form-item">
          <label>📧 البريد الإلكتروني <span style="color:var(--danger)">*</span></label>
          <input type="email" name="email" class="form-input" dir="ltr"
                 value="<?= e($_POST['email'] ?? '') ?>"
                 placeholder="ahmed@example.com" required autocomplete="email">
        </div>

        <div class="form-item">
          <label>📞 رقم الجوال <span style="color:var(--danger)">*</span></label>
          <input type="tel" name="phone" class="form-input" dir="ltr"
                 value="<?= e($_POST['phone'] ?? '') ?>"
                 placeholder="05XXXXXXXX" required>
        </div>

        <div class="form-row">
          <div>
            <label>🔒 كلمة المرور <span style="color:var(--danger)">*</span></label>
            <input type="password" name="password" id="pw" class="form-input"
                   placeholder="6 أحرف على الأقل" required minlength="6" autocomplete="new-password">
          </div>
          <div>
            <label>🔒 تأكيد كلمة المرور <span style="color:var(--danger)">*</span></label>
            <input type="password" name="confirm_password" id="pw2" class="form-input"
                   placeholder="أعد كلمة المرور" required autocomplete="new-password">
          </div>
        </div>

        <div class="terms-box">
          ✅ بالنقر على "إنشاء الحساب" فأنت توافق على 
          <a href="#" style="color:var(--primary);font-weight:700">سياسة الخصوصية</a> و 
          <a href="#" style="color:var(--primary);font-weight:700">شروط الاستخدام</a>.
        </div>

        <button type="submit" class="btn btn-primary" id="regBtn"
                style="width:100%;padding:16px;font-size:16px;font-weight:800;border-radius:40px">
          إنشاء الحساب الآن 🚀
        </button>

        <button type="button" class="btn btn-outline"
                style="width:100%;padding:14px;margin-top:16px;border-radius:40px;font-weight:700"
                onclick="goBack()">
          ← العودة لاختيار نوع الحساب
        </button>
      </form>
    </div>

    <div style="margin-top:32px;text-align:center;font-size:14px;color:var(--text-secondary)">
      لديك حساب بالفعل؟
      <a href="login.php" style="color:var(--primary);font-weight:800;text-decoration:none">تسجيل الدخول ✨</a>
    </div>

  </div>

  <script src="assets/js/global.js"></script>
  <script>
    let uType = 'student';

    function selectType(t) {
      uType = t;
      document.getElementById('sel-student').classList.toggle('active', t === 'student');
      document.getElementById('sel-academic').classList.toggle('active', t === 'academic');
    }

    function nextStep() {
      if (uType === 'academic') {
        window.location.href = 'academics/academic-register.php';
      } else {
        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
      }
    }

    function goBack() {
      document.getElementById('step2').style.display = 'none';
      document.getElementById('step1').style.display = 'block';
    }

    function handleSubmit(form) {
      const pw  = document.getElementById('pw').value;
      const pw2 = document.getElementById('pw2').value;
      if (pw !== pw2) {
        alert('⚠️ كلمتا المرور غير متطابقتين.');
        return false;
      }
      const btn = document.getElementById('regBtn');
      btn.textContent = 'جاري الإنشاء... ⏳';
      btn.disabled = true;
      return true;
    }

    <?php if ($step === 2 && $error): ?>
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    <?php endif; ?>

    // تأثير fade-up
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
