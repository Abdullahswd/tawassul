<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if (!$email || !$password) {
    $error = 'يرجى تعبئة جميع الحقول.';
  } else {
    $user = getUserByEmail($email);
    if ($user && password_verify($password, $user['password'])) {
      if ($user['role'] !== 'admin') {
        $error = 'هذا الحساب ليس لديه صلاحيات الإدارة.';
      } else {
        loginUser($user);
        header('Location: pages/dashboard.php');
        exit;
      }
    } else {
      $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Eduroad - لوحة الإدارة</title>
  <meta name="description" content="لوحة إدارة منصة Eduroad" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 50%, #1e293b 100%);
      position: relative;
      overflow: hidden;
      padding: 20px;
    }

    .login-page::before {
      content: '';
      position: absolute;
      width: 600px;
      height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
      top: -200px;
      right: -200px;
    }

    .login-page::after {
      content: '';
      position: absolute;
      width: 400px;
      height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(14, 165, 233, 0.1) 0%, transparent 70%);
      bottom: -100px;
      left: -100px;
    }

    .login-card {
      background: rgba(30, 27, 75, 0.8);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(99, 102, 241, 0.3);
      border-radius: 24px;
      padding: 48px;
      width: 100%;
      max-width: 440px;
      position: relative;
      z-index: 1;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
      animation: fadeInUp 0.5s ease forwards;
    }

    @media (max-width: 480px) {
      .login-card {
        padding: 28px 20px !important;
        border-radius: 20px !important;
      }

      .login-logo {
        margin-bottom: 24px;
      }

      .login-logo .logo-icon-lg {
        width: 60px;
        height: 60px;
        font-size: 30px;
      }
    }

    .login-logo {
      text-align: center;
      margin-bottom: 36px;
    }

    .login-logo .logo-icon-lg {
      width: 72px;
      height: 72px;
      background: linear-gradient(135deg, #6366f1, #818cf8);
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 36px;
      margin-bottom: 16px;
      box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    }

    .login-logo h1 {
      font-size: 26px;
      font-weight: 800;
      color: white;
      margin-bottom: 6px;
    }

    .login-logo p {
      font-size: 14px;
      color: rgba(255, 255, 255, 0.5);
    }

    .login-input {
      width: 100%;
      padding: 13px 16px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      color: white;
      font-family: 'Tajawal', sans-serif;
      font-size: 15px;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      margin-bottom: 16px;
    }

    .login-input:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }

    .login-input::placeholder {
      color: rgba(255, 255, 255, 0.3);
    }

    .login-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.6);
      margin-bottom: 8px;
    }

    .login-btn {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #6366f1, #4f46e5);
      color: white;
      border: none;
      border-radius: 12px;
      font-family: 'Tajawal', sans-serif;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
      margin-top: 8px;
    }

    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(99, 102, 241, 0.5);
    }

    .login-btn:active {
      transform: translateY(0);
    }

    .floating-shape {
      position: absolute;
      border-radius: 50%;
      opacity: 0.06;
      background: #6366f1;
      animation: float 8s ease-in-out infinite;
    }

    @keyframes float {

      0%,
      100% {
        transform: translateY(0px) rotate(0deg);
      }

      50% {
        transform: translateY(-30px) rotate(180deg);
      }
    }
  </style>
</head>

<body style="background:#0f172a;font-family:'Tajawal',sans-serif">

  <div class="login-page">

    <!-- Floating Shapes -->
    <div class="floating-shape" style="width:80px;height:80px;top:10%;left:10%;animation-delay:0s"></div>
    <div class="floating-shape"
      style="width:120px;height:120px;top:70%;right:10%;animation-delay:2s;background:#0ea5e9"></div>
    <div class="floating-shape"
      style="width:60px;height:60px;bottom:20%;left:20%;animation-delay:4s;background:#10b981"></div>

    <!-- Login Card -->
    <div class="login-card">

      <!-- Logo -->
      <div class="login-logo">
        <div class="logo-icon-lg" style="background:none;box-shadow:none;overflow:hidden"><img
            src="../image/eduroad_logo.png" alt="Eduroad"
            style="width:100%;height:100%;object-fit:contain;border-radius:20px" /></div>
        <h1>Eduroad Admin</h1>
        <p>لوحة إدارة المنصة الأكاديمية</p>
      </div>

      <!-- Form -->
      <form method="POST" action="">

        <?php if ($error): ?>
          <div
            style="background:#ef4444;color:white;padding:12px;border-radius:12px;margin-bottom:16px;font-size:14px;text-align:center;">
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <div>
          <label class="login-label">البريد الإلكتروني</label>
          <input class="login-input" type="email" name="email" id="loginEmail" placeholder="admin@eduroad.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
        </div>

        <div>
          <label class="login-label" style="display:flex;justify-content:space-between">
            <span>كلمة المرور</span>
            <a href="#" style="color:#818cf8;font-size:12px;text-decoration:none">نسيت كلمة المرور؟</a>
          </label>
          <div style="position:relative">
            <input class="login-input" type="password" name="password" id="loginPassword" placeholder="••••••••"
              required style="margin-bottom:0;padding-left:44px" />
            <button type="button"
              style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer;font-size:18px"
              onclick="togglePassword()">👁</button>
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;margin-top:16px;margin-bottom:8px">
          <input type="checkbox" id="rememberMe" style="width:16px;height:16px;accent-color:#6366f1;cursor:pointer" />
          <label for="rememberMe" style="font-size:14px;color:rgba(255,255,255,0.5);cursor:pointer">تذكرني</label>
        </div>

        <button type="submit" class="login-btn" id="loginBtn"
          onclick="this.innerHTML='⏳ جاري التحقق...'; setTimeout(()=>this.disabled=true,0); this.form.submit();">
          تسجيل الدخول
        </button>

      </form>

      <!-- Quick Access Note -->
      <div
        style="margin-top:24px;padding:14px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.2);border-radius:12px">
        <p style="font-size:12px;color:rgba(255,255,255,0.5);text-align:center;margin-bottom:6px">بيانات الدخول
          التجريبية</p>
        <p style="font-size:13px;color:#a5b4fc;text-align:center;font-weight:500">admin@eduroad.com / password</p>
      </div>

      <!-- Footer -->
      <p style="text-align:center;font-size:12px;color:rgba(255,255,255,0.3);margin-top:24px">
        © 2024 Eduroad · جميع الحقوق محفوظة
      </p>

    </div>
  </div>

  <script>
    function togglePassword() {
      const input = document.getElementById('loginPassword');
      input.type = input.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>

</html>