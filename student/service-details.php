<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$service_id = (int)($_GET['id'] ?? 0);

if (!$service_id) {
    header('Location: services.php');
    exit;
}

$service = getServiceById($service_id);
if (!$service) {
    header('Location: services.php');
    exit;
}

// Fetch packages linked to this service (if any), or all packages
$packages = getAllPackages();

// Fetch average rating for academics who work on this service
$db = db();
$stats_stmt = $db->prepare(
    'SELECT COUNT(DISTINCT o.id) AS total_orders,
            AVG(r.rating) AS avg_rating,
            COUNT(DISTINCT r.id) AS total_reviews
     FROM orders o
     LEFT JOIN reviews r ON r.order_id = o.id
     WHERE o.service_id = ?'
);
$stats_stmt->execute([$service_id]);
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($service['name']) ?> - تواصل</title>
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
        <a href="student-dashboard.php" class="nav-item"><span class="icon">📊</span><span>لوحة المعلومات</span></a>
        <a href="services.php" class="nav-item active"><span class="icon">📦</span><span>الخدمات الأكاديمية</span></a>
        <a href="orders.php" class="nav-item"><span class="icon">📋</span><span>طلباتي</span></a>
        <a href="chat.php" class="nav-item"><span class="icon">💬</span><span>المحادثات</span></a>
        <a href="payments.php" class="nav-item"><span class="icon">💳</span><span>المدفوعات</span></a>
        <div style="font-size:12px;color:var(--text-muted);font-weight:700;margin-top:24px;margin-bottom:8px;padding:0 8px">إعدادات الحساب</div>
        <a href="profile.php" class="nav-item"><span class="icon">👤</span><span>الملف الشخصي</span></a>
      </nav>
      <div style="padding:20px;border-top:1px solid var(--border-color)">
        <a href="../logout.php" class="nav-item" style="color:var(--danger)">
          <span class="icon">🚪</span><span>تسجيل الخروج</span>
        </a>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-area">
      <header class="top-navbar">
        <div style="display:flex;align-items:center;gap:16px">
          <button class="menu-toggle" id="menuToggle">☰</button>
          <div class="h3">تفاصيل الخدمة</div>
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

      <div class="content-wrap" style="max-width:1000px;margin:0 auto">
        
        <div style="margin-bottom:24px;">
          <a href="services.php" style="color:var(--text-secondary);font-size:14px">← العودة لقائمة الخدمات</a>
        </div>

        <div class="card" style="padding:40px;position:relative;overflow:hidden;margin-bottom:24px">
          <div style="position:absolute;top:0;left:0;right:0;height:120px;background:linear-gradient(135deg, var(--primary-light), transparent);z-index:0"></div>
          
          <div style="position:relative;z-index:1;display:flex;flex-direction:column;gap:24px">
            <div style="font-size:56px"><?= e($service['icon'] ?: '📦') ?></div>
            <h1 class="h1"><?= e($service['name']) ?></h1>
            
            <p style="font-size:16px;color:var(--text-secondary);line-height:1.8;max-width:800px">
              <?= e($service['description'] ?: 'خدمة أكاديمية متكاملة مقدمة من نخبة من المتخصصين والأكاديميين ذوي الخبرة.') ?>
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;background:var(--bg-body);padding:24px;border-radius:var(--radius-md);border:1px solid var(--border-color)">
              <div>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">يبدأ من</div>
                <div style="font-size:24px;font-weight:900;color:var(--primary)">150 ر.س</div>
              </div>
              <div>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">إجمالي الطلبات</div>
                <div style="font-size:18px;font-weight:700;color:var(--text-primary)"><?= (int)$stats['total_orders'] ?> طلب</div>
              </div>
              <div>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">التقييم العام</div>
                <div style="font-size:18px;font-weight:700;color:var(--warning)">
                  <?php $avg = round((float)$stats['avg_rating'], 1); ?>
                  ⭐ <?= $avg > 0 ? $avg : 'جديد' ?>
                  <?php if ($stats['total_reviews'] > 0): ?>
                    <span style="font-size:12px;color:var(--text-muted);font-weight:400">(<?= (int)$stats['total_reviews'] ?> تقييم)</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <?php if (!empty($service['features'])): ?>
            <div style="padding-top:24px">
              <h3 class="h3" style="margin-bottom:16px">مميزات الخدمة:</h3>
              <ul style="list-style:none;padding:0;color:var(--text-secondary);line-height:2">
                <?php foreach (explode("\n", $service['features']) as $feature): ?>
                  <?php if (trim($feature)): ?>
                    <li>✅ <?= e(trim($feature)) ?></li>
                  <?php endif; ?>
                <?php endforeach; ?>
              </ul>
            </div>
            <?php endif; ?>

            <div style="margin-top:8px">
              <a href="create-order.php?sid=<?= $service['id'] ?>" class="btn btn-primary" style="padding:16px 32px;font-size:16px;width:100%;max-width:300px">اطلب الخدمة الآن 🚀</a>
            </div>

          </div>
        </div>

        <!-- Available Packages -->
        <?php if (!empty($packages)): ?>
        <div class="card">
          <h2 class="h2" style="margin-bottom:20px">الباقات المتاحة</h2>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px">
            <?php foreach ($packages as $pkg): ?>
              <div style="border:2px solid var(--border-color);border-radius:var(--radius-md);padding:24px;transition:all 0.2s;cursor:pointer" 
                   onmouseover="this.style.borderColor='var(--primary)';this.style.transform='translateY(-2px)'" 
                   onmouseout="this.style.borderColor='var(--border-color)';this.style.transform='none'">
                <div style="font-size:28px;margin-bottom:12px"><?= e($pkg['icon'] ?? '📦') ?></div>
                <h3 style="font-weight:800;margin-bottom:8px"><?= e($pkg['name']) ?></h3>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px"><?= e($pkg['description'] ?? '') ?></p>
                <div style="font-size:24px;font-weight:900;color:var(--primary);margin-bottom:16px"><?= formatMoney($pkg['price']) ?></div>
                <a href="create-order.php?sid=<?= $service['id'] ?>&pkg=<?= $pkg['id'] ?>" class="btn btn-primary" style="width:100%">اختر هذه الباقة</a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </main>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
