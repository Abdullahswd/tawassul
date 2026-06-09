<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$services = getAllServices();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>الخدمات الأكاديمية - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
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
        <a href="services.php" class="nav-item active">
          <span class="icon">📦</span>
          <span>الخدمات الأكاديمية</span>
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
        <a href="profile.php" class="nav-item">
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
          <div class="h3">الخدمات الأكاديمية</div>
        </div>

        <div class="navbar-actions">
          <button class="icon-btn dark-toggle" aria-label="تبديل المظهر">🌙</button>
          <button class="icon-btn" aria-label="الإشعارات">
            🔔<span class="badge-dot"><?= countUnreadNotifications($user['id'], 'student') ?></span>
          </button>
          <div style="width:1px;height:30px;background:var(--border-color);margin:0 8px"></div>
          <div class="user-profile">
            <div class="user-info" style="text-align:left">
              <span class="user-name"><?= e($user['name']) ?></span>
              <span class="user-role">طالب</span>
            </div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <div class="content-wrap">
        
        <!-- Filter Tabs -->
        <div style="display:flex;gap:12px;margin-bottom:32px;overflow-x:auto;padding-bottom:8px">
          <button class="btn btn-primary" style="border-radius:40px;padding:8px 20px">الكل</button>
          <button class="btn btn-outline" style="border-radius:40px;padding:8px 20px;color:var(--text-secondary);border-color:var(--border-color)">البحوث الأكاديمية</button>
          <button class="btn btn-outline" style="border-radius:40px;padding:8px 20px;color:var(--text-secondary);border-color:var(--border-color)">التحليل الإحصائي</button>
          <button class="btn btn-outline" style="border-radius:40px;padding:8px 20px;color:var(--text-secondary);border-color:var(--border-color)">الترجمة والتدقيق</button>
        </div>

        <!-- Services Grid -->
        <div id="servicesGrid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:24px">
          <?php foreach ($services as $idx => $s): ?>
            <div class="card anim-fade-up" style="animation-delay:<?= $idx * 0.05 ?>s; display:flex; flex-direction:column;">
              <div style="font-size:40px;margin-bottom:16px"><?= e($s['icon']) ?></div>
              <h3 class="h3" style="margin-bottom:8px"><?= e($s['name']) ?></h3>
              <p class="text-body" style="flex-grow:1;margin-bottom:24px"><?= e($s['description'] ?: 'لا يوجد وصف حالياً لهذه الخدمة.') ?></p>
              
              <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border-color);padding-top:16px">
                <div>
                  <div style="font-size:12px;color:var(--text-secondary)">تبدأ من</div>
                  <div style="font-weight:800;color:var(--primary);font-size:18px">150 ر.س</div>
                </div>
                <a href="create-order.php?sid=<?= $s['id'] ?>" class="btn btn-primary">اطلب الآن</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      </div>
    </main>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
