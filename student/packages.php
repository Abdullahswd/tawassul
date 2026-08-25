<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db = db();

// جلب الباقات المفعلة بالنظام
$packages = [];
try {
    $stmt = $db->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب جميع الخدمات للربط
    $services_stmt = $db->query("SELECT id, name, icon, price FROM services WHERE is_active = 1");
    $all_services_raw = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
    $services_by_id = [];
    foreach ($all_services_raw as $srv) {
        $services_by_id[$srv['id']] = $srv;
    }

    foreach ($rows as $row) {
        $row['features'] = json_decode($row['features_json'] ?? '[]', true) ?: [];
        $row['service_ids'] = json_decode($row['service_ids'] ?? '[]', true) ?: [];
        $row['icon'] = !empty($row['icon']) ? $row['icon'] : '📦';
        $row['original_price'] = (float)($row['original_price'] ?? 0);
        $row['price'] = (float)($row['price'] ?? 0);
        
        $row['included_services'] = [];
        foreach ($row['service_ids'] as $sid) {
            if (isset($services_by_id[$sid])) {
                $row['included_services'][] = $services_by_id[$sid];
            }
        }
        $packages[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching packages for student: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>باقات الخدمات الأكاديمية - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    * { font-family: 'Tajawal', sans-serif; }
    
    .pkg-card {
      background: var(--bg-card);
      border-radius: 24px;
      border: 1px solid var(--border-color);
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
      padding: 24px;
    }
    .pkg-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 15px 35px -10px rgba(79,70,229,0.18);
      border-color: var(--primary);
    }

    .pkg-icon-wrap {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
    }

    .discount-badge {
      background: #ef4444;
      color: white;
      font-size: 11px;
      font-weight: 800;
      padding: 3px 10px;
      border-radius: 20px;
    }

    .price-box {
      margin: 16px 0;
      display: flex;
      align-items: baseline;
      gap: 8px;
    }
    .price-new { font-size: 32px; font-weight: 900; line-height: 1; }
    .price-old { font-size: 16px; color: #94a3b8; text-decoration: line-through; font-weight: 600; }

    .pkg-service-list {
      list-style: none;
      padding: 0;
      margin: 14px 0;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .pkg-service-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--text-secondary);
      font-weight: 600;
    }
    .pkg-service-item .check {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #dcfce7;
      color: #166534;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      flex-shrink: 0;
      font-weight: 800;
    }
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
        <a href="packages.php" class="nav-item active">
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
          <div class="h3">🎁 باقات الخدمات المجمعة</div>
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
      <div class="content-wrap" style="padding:24px;max-width:1200px;margin:0 auto">

        <div class="mb-8 bg-gradient-to-l from-indigo-900 to-slate-900 text-white rounded-3xl p-8 shadow-md">
          <div class="max-w-2xl">
            <span class="inline-block bg-indigo-500/20 text-indigo-300 px-3 py-1 rounded-full text-xs font-bold mb-3">وفر حتى 30% من التكلفة</span>
            <h1 class="text-2xl md:text-3xl font-black mb-3">اختر الباقة الأكاديمية المناسبة لمشروعك</h1>
            <p class="text-slate-300 text-sm leading-relaxed">
              تجمع لك الباقات أهم الخدمات الأكاديمية في حزمة واحدة بسعر مخفض مع أولوية في التنفيذ ومتابعة مستمرة.
            </p>
          </div>
        </div>

        <!-- شبكة عرض الباقات -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php if (empty($packages)): ?>
            <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-dashed border-slate-200">
              <span class="text-6xl block mb-4">🎁</span>
              <h3 class="text-lg font-bold text-slate-800 mb-2">لا توجد باقات مفعلة حالياً</h3>
              <p class="text-slate-500 text-xs mb-6">يمكنك الاستفادة من الخدمات الفردية بطلب مباشر.</p>
              <a href="services.php" class="btn btn-primary">تصفح الخدمات 🚀</a>
            </div>
          <?php else: ?>
            <?php foreach ($packages as $idx => $pkg): 
              $color = !empty($pkg['color']) ? $pkg['color'] : '#6366f1';
              $origPrice = $pkg['original_price'] > 0 ? $pkg['original_price'] : $pkg['price'];
              $discountAmount = $origPrice > $pkg['price'] ? ($origPrice - $pkg['price']) : 0;
              $discountPercent = $origPrice > 0 ? round(($discountAmount / $origPrice) * 100) : 0;
              $included = $pkg['included_services'];
            ?>
            <div class="pkg-card" style="border-top: 5px solid <?= $color ?>;">
              <div>
                <div class="flex items-center justify-between mb-3">
                  <div class="pkg-icon-wrap" style="background: <?= $color ?>15; color: <?= $color ?>;">
                    <?= $pkg['icon'] ?>
                  </div>
                  <?php if ($discountPercent > 0): ?>
                    <span class="discount-badge">خصم <?= $discountPercent ?>%</span>
                  <?php endif; ?>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 mb-2"><?= htmlspecialchars($pkg['name']) ?></h3>

                <div class="price-box">
                  <span class="price-new" style="color: <?= $color ?>;"><?= number_format($pkg['price'], 2) ?> <span style="font-size:14px;font-weight:700;">ر.س</span></span>
                  <?php if ($discountAmount > 0): ?>
                    <span class="price-old"><?= number_format($origPrice, 2) ?> ر.س</span>
                  <?php endif; ?>
                </div>

                <!-- قائمة الخدمات المشمولة -->
                <div class="border-t border-slate-100 pt-3 mt-3">
                  <div class="text-xs font-bold text-slate-500 mb-2">الخدمات المتضمنة (<?= count($included) ?>):</div>
                  <ul class="pkg-service-list">
                    <?php if (!empty($included)): ?>
                      <?php foreach ($included as $srv): ?>
                        <li class="pkg-service-item">
                          <span class="check">✓</span>
                          <span><?= htmlspecialchars($srv['icon'] ?: '•') ?> <?= htmlspecialchars($srv['name']) ?></span>
                        </li>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($pkg['features'])): ?>
                      <?php foreach ($pkg['features'] as $feat): ?>
                        <li class="pkg-service-item">
                          <span class="check" style="background:#e0e7ff;color:#4338ca;">★</span>
                          <span><?= htmlspecialchars($feat) ?></span>
                        </li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>

              <div class="pt-4 border-t border-slate-100 mt-4">
                <a href="create-order.php?package_id=<?= $pkg['id'] ?>" class="w-full inline-block text-center py-2.5 px-4 rounded-xl font-bold text-white text-sm shadow-sm transition hover:scale-[1.02]" style="background: <?= $color ?>;">
                  الاشتراك بالباقة الآن 🚀
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
