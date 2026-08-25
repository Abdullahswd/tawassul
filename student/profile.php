<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db = db();

// Fetch fresh details from database
$stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!$name) {
        $error = 'الاسم بالكامل مطلوب.';
    } else {
        // Update user
        $update = $db->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
        if ($update->execute([$name, $phone, $user['id']])) {
            $success = 'تم حفظ التغييرات بنجاح.';
            
            // Refresh session details
            $userData['name'] = $name;
            $userData['phone'] = $phone;
            $_SESSION['user_name'] = $name;
            $initials = mb_substr($name, 0, 1, 'UTF-8') . mb_substr(explode(' ', $name)[1] ?? '', 0, 1, 'UTF-8');
            $_SESSION['user_avatar'] = $initials;
            $user = currentUser();
        } else {
            $error = 'حدث خطأ أثناء حفظ التحديثات.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>الملف الشخصي - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .profile-tabs {
      display: flex;
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 24px;
    }
    .p-tab {
      padding: 12px 24px;
      font-weight: 700;
      color: var(--text-secondary);
      border-bottom: 3px solid transparent;
      cursor: pointer;
    }
    .p-tab.active {
      color: var(--primary);
      border-bottom-color: var(--primary);
    }
    .alert-error   { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
    .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
  </style>
</head>
<body>
  
  <div class="mobile-overlay" id="mobileOverlay"></div>

  <div class="app-container">
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="logo-icon">🎓</div>
        <div class="logo-text">تواصل</div>
      </div>
      
      <nav class="sidebar-nav">
        <div style="font-size:12px;color:var(--text-muted);font-weight:700;margin-bottom:8px;padding:0 8px">القائمة الرئيسية</div>
        <a href="student-dashboard.php" class="nav-item">
          <span class="icon">📊</span>
          <span>لوحة المعلومات</span>
        </a>
        <a href="services.php" class="nav-item">
          <span class="icon">📦</span>
          <span>الخدمات الأكاديمية</span>
        </a>
        <a href="packages.php" class="nav-item">
          <span class="icon">🎁</span>
          <span>الباقات المخصصة</span>
        </a>
        <a href="orders.php" class="nav-item">
          <span class="icon">📋</span>
          <span>طلباتي</span>
        </a>
        <a href="chat.php" class="nav-item">
          <span class="icon">💬</span>
          <span>المحادثات</span>
        </a>
        <a href="payments.php" class="nav-item">
          <span class="icon">💳</span>
          <span>المدفوعات</span>
        </a>
        
        <div style="font-size:12px;color:var(--text-muted);font-weight:700;margin-top:24px;margin-bottom:8px;padding:0 8px">إعدادات الحساب</div>
        <a href="profile.php" class="nav-item active">
          <span class="icon">👤</span>
          <span>الملف الشخصي</span>
        </a>
      </nav>
      
      <div style="padding:20px;border-top:1px solid var(--border-color)">
        <a href="../logout.php" class="nav-item" style="color:var(--danger)">
          <span class="icon">🚪</span>
          <span>تسجيل الخروج</span>
        </a>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-area">
      
      <!-- Top Navbar -->
      <header class="top-navbar">
        <div style="display:flex;align-items:center;gap:16px">
          <button class="menu-toggle" id="menuToggle">☰</button>
          <div class="h3">الملف الشخصي</div>
        </div>
        <div class="navbar-actions">
          <button class="icon-btn dark-toggle" aria-label="تبديل المظهر">🌙</button>
          <div class="user-profile">
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <div class="content-wrap" style="max-width:900px;margin:0 auto">
        
        <h1 class="h1" style="margin-bottom:32px">إعدادات الحساب</h1>

        <?php if ($error): ?>
          <div class="alert-error">⚠️ <?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert-success">✓ <?= e($success) ?></div>
        <?php endif; ?>

        <div class="card" style="padding:0;overflow:hidden">
          
          <!-- Avatar Header -->
          <div style="padding:40px;background:var(--bg-body);border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:24px">
            <div style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:800;position:relative">
              <span><?= e($user['avatar']) ?></span>
            </div>
            <div>
              <h2 class="h2" style="margin-bottom:4px"><?= e($userData['name']) ?></h2>
              <div style="color:var(--text-secondary);margin-bottom:12px"><?= e($userData['email']) ?></div>
              <span class="status-badge status-completed">طالب موثق</span>
            </div>
          </div>

          <!-- Tabs -->
          <div class="profile-tabs" style="padding:0 24px;margin-bottom:0">
            <div class="p-tab active">البيانات الشخصية</div>
          </div>

          <!-- Tab Content 1 -->
          <div style="padding:32px">
            <form method="POST" action="profile.php">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
                
                <div class="form-group">
                  <label class="form-label">الاسم بالكامل</label>
                  <input type="text" name="name" class="form-input" value="<?= e($userData['name']) ?>" required>
                </div>
                
                <div class="form-group">
                  <label class="form-label">البريد الإلكتروني (غير قابل للتعديل)</label>
                  <input type="email" class="form-input" value="<?= e($userData['email']) ?>" dir="ltr" readonly style="background:var(--bg-body);cursor:not-allowed">
                </div>
                
                <div class="form-group" style="grid-column:1/-1">
                  <label class="form-label">رقم الجوال</label>
                  <input type="tel" name="phone" class="form-input" value="<?= e($userData['phone']) ?>" dir="ltr">
                </div>

              </div>

              <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
              </div>
            </form>
          </div>

        </div>

      </div>
    </main>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
