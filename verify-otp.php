<?php
/**
 * verify-otp.php — Two-Factor Authentication (2FA) OTP Verification
 * Eduroad Platform
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/mailer.php';

// Verify there is an active pending 2FA challenge
if (empty($_SESSION['pending_2fa'])) {
    if (isset($_GET['admin'])) {
        header('Location: admin/');
    } else {
        header('Location: login.php');
    }
    exit;
}

$pending   = $_SESSION['pending_2fa'];
$email     = $pending['email'];
$userName  = $pending['name'];
$userType  = $pending['user_type'];
$isAdmin   = ($userType === 'admin');
$redirect  = $pending['redirect'] ?? null;

$error   = '';
$success = '';

// Helper to mask email (e.g. ahmed@gmail.com -> a***d@gmail.com)
function maskEmail(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $len = mb_strlen($name);
    if ($len <= 2) {
        $masked = mb_substr($name, 0, 1) . '***';
    } else {
        $masked = mb_substr($name, 0, 1) . str_repeat('*', max(3, $len - 2)) . mb_substr($name, -1);
    }
    return $masked . '@' . $parts[1];
}

// ── Handle Resend Request ────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'resend_otp') {
    $lastSent = $pending['sent_at'] ?? 0;
    if (time() - $lastSent < 30) {
        $error = 'يرجى الانتظار قليلاً قبل طلب رمز جديد.';
    } else {
        $res = generateAndSendLoginOtp($pending['user_data'], $userType, $redirect);
        $pending = $_SESSION['pending_2fa']; // Refresh pending session
        $success = 'تم إرسال رمز تحقق جديد إلى بريدك الإلكتروني بنجاح.';
    }
}

// ── Handle Cancel / Switch Account ───────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'cancel_2fa') {
    unset($_SESSION['pending_2fa']);
    if ($isAdmin) {
        header('Location: admin/');
    } else {
        header('Location: login.php');
    }
    exit;
}

// ── Handle OTP Submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'verify')) {
    $digits = $_POST['otp_digits'] ?? null;
    if (is_array($digits)) {
        $otp = implode('', $digits);
    } else {
        $otp = trim($_POST['otp'] ?? '');
    }

    if (!$otp) {
        $error = 'يرجى إدخال رمز التحقق المكون من 6 أرقام.';
    } else {
        $verify = verifyLoginOtp($email, $userType, $otp);
        if ($verify['ok']) {
            $userData = $pending['user_data'];
            unset($_SESSION['pending_2fa']);

            if ($userType === 'academic') {
                loginAcademic($userData);
                sendLoginNotificationEmail($userData, 'academic');
                $targetUrl = $redirect ?: 'academics/academic-dashboard.php';
            } elseif ($userType === 'admin') {
                loginUser($userData);
                sendLoginNotificationEmail($userData, 'admin');
                $targetUrl = $redirect ?: 'admin/pages/dashboard.php';
            } else {
                loginUser($userData);
                sendLoginNotificationEmail($userData, 'student');
                $targetUrl = $redirect ?: 'student/student-dashboard.php';
            }

            header('Location: ' . $targetUrl);
            exit;
        } else {
            $error = $verify['error'];
        }
    }
}

$maskedEmail = maskEmail($email);
$secondsSinceSent = time() - ($pending['sent_at'] ?? time());
$countdownSeconds = max(0, 60 - $secondsSinceSent);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>رمز التحقق (2FA) - Eduroad</title>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    :root {
      --primary: #4f46e5;
      --primary-hover: #4338ca;
      --primary-light: rgba(79, 70, 229, 0.08);
      --bg-main: #f8fafc;
      --bg-card: #ffffff;
      --border-color: #e2e8f0;
      --text-primary: #0f172a;
      --text-secondary: #475569;
      --text-muted: #94a3b8;
    }

    body.otp-bg {
      background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 40%),
                  radial-gradient(circle at bottom left, rgba(14, 165, 233, 0.06), transparent 40%),
                  var(--bg-main);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 30px 20px;
      font-family: 'Tajawal', sans-serif;
      margin: 0;
    }

    .otp-card {
      max-width: 480px;
      width: 100%;
      background: var(--bg-card);
      border-radius: 36px;
      padding: 44px 36px;
      border: 1px solid var(--border-color);
      box-shadow: 0 20px 45px -15px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.02);
      text-align: center;
      position: relative;
      animation: fadeInUp 0.4s ease-out;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .shield-icon {
      width: 76px;
      height: 76px;
      background: linear-gradient(135deg, #eef2ff, #e0e7ff);
      color: var(--primary);
      border-radius: 24px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 34px;
      margin-bottom: 20px;
      box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.25);
    }

    .badge-role {
      display: inline-block;
      padding: 4px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 800;
      margin-bottom: 12px;
      background: var(--primary-light);
      color: var(--primary);
    }

    .otp-inputs {
      display: flex;
      gap: 10px;
      justify-content: center;
      direction: ltr;
      margin: 28px 0;
    }

    .otp-box {
      width: 52px;
      height: 60px;
      border: 2px solid var(--border-color);
      border-radius: 16px;
      text-align: center;
      font-size: 26px;
      font-weight: 800;
      color: var(--text-primary);
      background: var(--bg-main);
      outline: none;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      font-family: monospace;
    }

    .otp-box:focus {
      border-color: var(--primary);
      background: #ffffff;
      box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
      transform: translateY(-2px);
    }

    .btn-submit {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #4f46e5, #6366f1);
      color: white;
      border: none;
      border-radius: 30px;
      font-size: 16px;
      font-weight: 800;
      font-family: inherit;
      cursor: pointer;
      transition: all 0.25s;
      box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
    }

    .btn-submit:hover {
      background: linear-gradient(135deg, #4338ca, #4f46e5);
      box-shadow: 0 12px 25px rgba(79, 70, 229, 0.4);
      transform: translateY(-1px);
    }

    .btn-submit:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .alert {
      padding: 12px 16px;
      border-radius: 16px;
      font-size: 13px;
      font-weight: 700;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
      text-align: right;
    }

    .alert-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
    }

    .alert-success {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #16a34a;
    }

    .resend-box {
      margin-top: 24px;
      font-size: 14px;
      color: var(--text-secondary);
    }

    .btn-link {
      background: none;
      border: none;
      color: var(--primary);
      font-weight: 800;
      font-size: 14px;
      cursor: pointer;
      font-family: inherit;
      padding: 0;
      text-decoration: underline;
    }

    .btn-link:disabled {
      color: var(--text-muted);
      cursor: not-allowed;
      text-decoration: none;
    }
  </style>
</head>
<body class="otp-bg">

  <div class="otp-card">
    <div class="shield-icon">🔐</div>

    <div class="badge-role">
      <?= $userType === 'academic' ? '🎓 حساب أكاديمي' : ($isAdmin ? '🛡️ لوحة الإدارة' : '👨‍🎓 حساب طالب') ?>
    </div>

    <h1 style="font-size: 24px; font-weight: 900; color: var(--text-primary); margin: 0 0 8px;">رمز التحقق (2FA)</h1>
    <p style="font-size: 14px; color: var(--text-secondary); margin: 0 0 20px; line-height: 1.6;">
      أدخل الرمز المكون من 6 أرقام المرسل إلى بريدك الإلكتروني:<br>
      <strong style="color: var(--primary); direction: ltr; display: inline-block;"><?= htmlspecialchars($maskedEmail) ?></strong>
    </p>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <span>⚠️</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <span>✅</span>
        <span><?= htmlspecialchars($success) ?></span>
      </div>
    <?php endif; ?>

    <!-- OTP Form -->
    <form method="POST" action="verify-otp.php<?= $isAdmin ? '?admin=1' : '' ?>" id="otpForm">
      <input type="hidden" name="action" value="verify">

      <div class="otp-inputs">
        <input type="text" inputmode="numeric" maxlength="1" class="otp-box" name="otp_digits[]" autofocus required autocomplete="one-time-code">
        <input type="text" inputmode="numeric" maxlength="1" class="otp-box" name="otp_digits[]" required>
        <input type="text" inputmode="numeric" maxlength="1" class="otp-box" name="otp_digits[]" required>
        <input type="text" inputmode="numeric" maxlength="1" class="otp-box" name="otp_digits[]" required>
        <input type="text" inputmode="numeric" maxlength="1" class="otp-box" name="otp_digits[]" required>
        <input type="text" inputmode="numeric" maxlength="1" class="otp-box" name="otp_digits[]" required>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        تأكيد الدخول 🚀
      </button>
    </form>

    <!-- Resend & Timer -->
    <div class="resend-box">
      <span id="countdownText" style="<?= $countdownSeconds > 0 ? '' : 'display:none;' ?>">
        يمكنك إعادة إرسال الرمز خلال <strong id="timer"><?= $countdownSeconds ?></strong> ثانية
      </span>

      <form method="POST" action="verify-otp.php<?= $isAdmin ? '?admin=1' : '' ?>" id="resendForm" style="<?= $countdownSeconds > 0 ? 'display:none;' : '' ?>">
        <input type="hidden" name="action" value="resend_otp">
        <span>لم يصلك الرمز؟ </span>
        <button type="submit" class="btn-link" id="resendBtn">إعادة إرسال الرمز الآن 🔄</button>
      </form>
    </div>

    <!-- Cancel / Back -->
    <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color);">
      <form method="POST" action="verify-otp.php<?= $isAdmin ? '?admin=1' : '' ?>">
        <input type="hidden" name="action" value="cancel_2fa">
        <button type="submit" style="background: none; border: none; color: var(--text-muted); font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit;">
          ← تسجيل الدخول بحساب آخر
        </button>
      </form>
    </div>

  </div>

  <script>
    // Auto-advance and paste logic for 6 OTP boxes
    const inputs = document.querySelectorAll('.otp-box');
    const form   = document.getElementById('otpForm');
    const submitBtn = document.getElementById('submitBtn');

    inputs.forEach((input, index) => {
      input.addEventListener('input', (e) => {
        const val = e.target.value.replace(/\D/g, '');
        e.target.value = val;

        if (val && index < inputs.length - 1) {
          inputs[index + 1].focus();
        }

        // Auto submit when all filled
        checkFullAndAutoSubmit();
      });

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && index > 0) {
          inputs[index - 1].focus();
        }
      });

      input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        if (pasted.length >= 6) {
          for (let i = 0; i < 6; i++) {
            inputs[i].value = pasted[i] || '';
          }
          inputs[5].focus();
          checkFullAndAutoSubmit();
        }
      });
    });

    function checkFullAndAutoSubmit() {
      const allFilled = Array.from(inputs).every(inp => inp.value.length === 1);
      if (allFilled) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'جاري التحقق... ⏳';
        form.submit();
      }
    }

    form.addEventListener('submit', () => {
      submitBtn.disabled = true;
      submitBtn.textContent = 'جاري التحقق... ⏳';
    });

    // Countdown Timer logic
    let remaining = <?= (int)$countdownSeconds ?>;
    const timerElem = document.getElementById('timer');
    const countdownText = document.getElementById('countdownText');
    const resendForm = document.getElementById('resendForm');

    if (remaining > 0) {
      const interval = setInterval(() => {
        remaining--;
        if (timerElem) timerElem.textContent = remaining;
        if (remaining <= 0) {
          clearInterval(interval);
          if (countdownText) countdownText.style.display = 'none';
          if (resendForm) resendForm.style.display = 'block';
        }
      }, 1000);
    }
  </script>
</body>
</html>
