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
    .auth-bg   { background:linear-gradient(135deg,var(--bg-main) 0%,rgba(79,70,229,0.05) 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:40px 20px; }
    .auth-card { max-width:580px; width:100%; padding:40px; margin:0 auto; box-shadow:0 10px 25px -5px rgba(0,0,0,0.1); }
    .type-sel  { display:flex; flex-direction:column; gap:8px; padding:20px; border:2px solid var(--border-color); border-radius:12px; cursor:pointer; transition:all 0.2s; }
    .type-sel:hover { border-color:rgba(79,70,229,0.4); }
    .type-sel.active { border-color:var(--primary); background:rgba(79,70,229,0.05); }
    .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
    .form-row  { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
    .form-item { margin-bottom:16px; }
    .form-item label { display:block; font-weight:700; margin-bottom:8px; color:var(--text-primary); font-size:14px; }
    @media(max-width:480px) { .form-row { grid-template-columns:1fr; } }
  </style>
</head>
<body class="auth-bg">
  <div class="card auth-card fade-up">

    <div style="text-align:center;margin-bottom:32px">
      <a href="index.php" style="font-size:32px;font-weight:900;color:var(--primary);text-decoration:none">🎓 تواصل</a>
      <h1 style="font-size:24px;font-weight:800;color:var(--text-primary);margin-top:16px;margin-bottom:8px">كن متصلاً بشبكة المعرفة</h1>
      <p style="color:var(--text-secondary);font-size:14px">اختر نوع الحساب الذي ترغب بإنشائه</p>
    </div>

    <!-- Step 1: Select account type -->
    <div id="step1" <?= ($step === 2 && $error) ? 'style="display:none"' : '' ?>>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:32px">
        <div class="type-sel active" id="sel-student" onclick="selectType('student')">
          <div style="font-size:32px">👨‍🎓</div>
          <div style="font-weight:800;color:var(--text-primary);font-size:18px">حساب طالب</div>
          <div style="font-size:12px;color:var(--text-secondary)">أبحث عن خدمات أكاديمية وبحوث.</div>
        </div>
        <div class="type-sel" id="sel-academic" onclick="selectType('academic')">
          <div style="font-size:32px">👨‍🏫</div>
          <div style="font-weight:800;color:var(--text-primary);font-size:18px">حساب أكاديمي</div>
          <div style="font-size:12px;color:var(--text-secondary)">أنا متخصص وأرغب بتقديم خدماتي.</div>
        </div>
      </div>
      <button class="btn btn-primary" style="width:100%;padding:14px;font-size:16px" onclick="nextStep()">المتابعة ←</button>
    </div>

    <!-- Step 2: Student Registration Form -->
    <div id="step2" <?= ($step !== 2 || !$error) ? 'style="display:none"' : '' ?>>

      <?php if ($error): ?>
        <div class="alert-error">⚠️ <?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="register.php" onsubmit="handleSubmit(this)">
        <input type="hidden" name="do_register" value="1">

        <div class="form-row">
          <div>
            <label>الاسم الأول <span style="color:var(--danger)">*</span></label>
            <input type="text" name="first_name" class="form-input"
                   value="<?= e($_POST['first_name'] ?? '') ?>"
                   placeholder="محمد" required>
          </div>
          <div>
            <label>اسم العائلة <span style="color:var(--danger)">*</span></label>
            <input type="text" name="last_name" class="form-input"
                   value="<?= e($_POST['last_name'] ?? '') ?>"
                   placeholder="الأحمد" required>
          </div>
        </div>

        <div class="form-item">
          <label>البريد الإلكتروني <span style="color:var(--danger)">*</span></label>
          <input type="email" name="email" class="form-input" dir="ltr"
                 value="<?= e($_POST['email'] ?? '') ?>"
                 placeholder="ahmad@example.com" required autocomplete="email">
        </div>

        <div class="form-item">
          <label>رقم الجوال <span style="color:var(--danger)">*</span></label>
          <input type="tel" name="phone" class="form-input" dir="ltr"
                 value="<?= e($_POST['phone'] ?? '') ?>"
                 placeholder="05XXXXXXXX" required>
        </div>

        <div class="form-row">
          <div>
            <label>كلمة المرور <span style="color:var(--danger)">*</span></label>
            <input type="password" name="password" id="pw" class="form-input"
                   placeholder="6 أحرف على الأقل" required minlength="6" autocomplete="new-password">
          </div>
          <div>
            <label>تأكيد كلمة المرور <span style="color:var(--danger)">*</span></label>
            <input type="password" name="confirm_password" id="pw2" class="form-input"
                   placeholder="أعد كلمة المرور" required autocomplete="new-password">
          </div>
        </div>

        <div style="margin-bottom:24px;padding:12px;background:var(--bg-body);border-radius:8px;font-size:13px;color:var(--text-muted)">
          بالنقر على "إنشاء الحساب" فأنت توافق على <a href="#" style="color:var(--primary)">سياسة الخصوصية</a> و<a href="#" style="color:var(--primary)">شروط الاستخدام</a>.
        </div>

        <button type="submit" class="btn btn-primary" id="regBtn"
                style="width:100%;padding:14px;font-size:16px">إنشاء الحساب الآن</button>

        <button type="button" class="btn btn-outline"
                style="width:100%;padding:12px;margin-top:12px"
                onclick="goBack()">← العودة لاختيار نوع الحساب</button>
      </form>
    </div>

    <div style="margin-top:28px;text-align:center;font-size:14px;color:var(--text-secondary)">
      لديك حساب بالفعل؟
      <a href="login.php" style="color:var(--primary);font-weight:700;text-decoration:none">تسجيل الدخول</a>
    </div>

  </div>

  <script src="assets/js/global.js"></script>
  <script>
    let uType = 'student';

    function selectType(t) {
      uType = t;
      document.getElementById('sel-student').classList.toggle('active',  t === 'student');
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
        alert('كلمتا المرور غير متطابقتين.');
        return false;
      }
      const btn = document.getElementById('regBtn');
      btn.textContent = 'جاري الإنشاء...';
      btn.disabled = true;
    }

    // If we came back from a validation error, show step 2
    <?php if ($step === 2 && $error): ?>
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    <?php endif; ?>
  </script>
</body>
</html>
