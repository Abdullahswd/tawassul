<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

$db = db();

$packages = [];
try {
    // جلب الباقات المفعلة من قاعدة البيانات
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
        
        // ربط الخدمات المشمولة بالباقة
        $row['included_services'] = [];
        foreach ($row['service_ids'] as $sid) {
            if (isset($services_by_id[$sid])) {
                $row['included_services'][] = $services_by_id[$sid];
            }
        }

        $packages[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching packages: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>باقات الخدمات الأكاديمية - Eduroad</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    * { font-family: 'Tajawal', sans-serif; }
    body { background-color: #f8fafc; color: #0f172a; }

    .packages-header {
      background: linear-gradient(145deg, #ffffff 0%, rgba(79, 70, 229, 0.05) 100%);
      border-radius: 40px;
      padding: 48px 24px;
      margin-bottom: 40px;
      border: 1px solid #e2e8f0;
      text-align: center;
    }

    .pkg-card {
      background: #ffffff;
      border-radius: 24px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }
    .pkg-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px -10px rgba(79, 70, 229, 0.18);
    }

    .pkg-icon-wrap {
      width: 60px;
      height: 60px;
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      margin-bottom: 16px;
    }

    .discount-badge {
      background: #ef4444;
      color: white;
      font-size: 11px;
      font-weight: 800;
      padding: 3px 10px;
      border-radius: 20px;
      box-shadow: 0 2px 6px rgba(239,68,68,0.3);
    }

    .price-box {
      margin: 16px 0;
      display: flex;
      align-items: baseline;
      gap: 10px;
    }
    .price-new { font-size: 36px; font-weight: 900; line-height: 1; }
    .price-old { font-size: 18px; color: #94a3b8; text-decoration: line-through; font-weight: 600; }

    .pkg-service-list {
      list-style: none;
      padding: 0;
      margin: 16px 0;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .pkg-service-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13.5px;
      color: #334155;
      font-weight: 600;
    }
    .pkg-service-item .check {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: #dcfce7;
      color: #166534;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      flex-shrink: 0;
      font-weight: 800;
    }

    .fade-up {
      opacity: 0;
      transform: translateY(20px);
      animation: fadeUp 0.6s forwards;
    }
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <!-- شريط التنقل الرئيسي -->
  <nav class="relative flex items-center justify-between flex-wrap p-4 lg:px-8 bg-white border-b border-slate-200">
    <div class="flex items-center gap-2 text-2xl font-black">
      <img src="image/eduroad_logo.png" alt="Eduroad" style="height:28px;width:auto" />
      <span class="text-slate-900">Eduroad</span>
    </div>
    <div class="hidden md:flex items-center gap-6 text-sm font-semibold">
      <a href="index.php#features" class="text-slate-600 hover:text-indigo-600 transition">الخدمات والأقسام</a>
      <a href="index.php#how-it-works" class="text-slate-600 hover:text-indigo-600 transition">كيف نعمل</a>
      <a href="index.php#academics" class="text-slate-600 hover:text-indigo-600 transition">أكاديميونا</a>
      <a href="packages.php" class="text-indigo-600 font-extrabold">الباقات</a>
      <a href="reviews.php" class="text-slate-600 hover:text-indigo-600 transition">التقييمات</a>
    </div>
    <div class="flex items-center gap-3">
      <button class="dark-toggle text-xl bg-transparent border-none cursor-pointer">🌙</button>
      <a href="login.php" class="px-4 py-2 rounded-full border border-indigo-600 text-indigo-600 text-sm font-bold hover:bg-indigo-600 hover:text-white transition">تسجيل الدخول</a>
      <a href="register.php" class="px-4 py-2 rounded-full bg-indigo-600 text-white text-sm font-bold shadow-md hover:bg-indigo-700 transition">إنشاء حساب</a>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-4 py-10">

    <!-- Header الباقات -->
    <div class="packages-header fade-up">
      <span class="inline-block bg-indigo-50 text-indigo-600 px-4 py-1.5 rounded-full text-xs font-extrabold mb-3">🎁 باقات مجمعة بأسعار مخفضة</span>
      <h1 class="text-3xl md:text-5xl font-black text-slate-900 mb-4">باقات الخدمات الأكاديمية الشاملة</h1>
      <p class="text-slate-600 text-base md:text-lg max-w-2xl mx-auto leading-relaxed">
        وفر حتى 30% من تكلفة طلب الخدمات الأكاديمية بشكل منفرد، واستمتع بتنفيذ متكامل وأولوية مع فريقنا الأكاديمي.
      </p>
    </div>

    <!-- شبكة عرض الباقات المجمعة بالخدمات -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
      <?php if (empty($packages)): ?>
        <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-dashed border-slate-200">
          <span class="text-6xl block mb-4">🎁</span>
          <h3 class="text-xl font-bold text-slate-800 mb-2">لا توجد باقات متاحة حالياً</h3>
          <p class="text-slate-500 text-sm mb-6">يرجى زيارة صفحة الخدمات الفردية للاطلاع على كافة خدماتنا المتاحة.</p>
          <a href="index.php#features" class="inline-block px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl text-sm shadow">تصفح الخدمات الفردية 🚀</a>
        </div>
      <?php else: ?>
        <?php foreach ($packages as $index => $pkg): 
          $color = !empty($pkg['color']) ? $pkg['color'] : '#6366f1';
          $origPrice = $pkg['original_price'] > 0 ? $pkg['original_price'] : $pkg['price'];
          $discountAmount = $origPrice > $pkg['price'] ? ($origPrice - $pkg['price']) : 0;
          $discountPercent = $origPrice > 0 ? round(($discountAmount / $origPrice) * 100) : 0;
          $included = $pkg['included_services'];
        ?>
        <div class="pkg-card p-6 fade-up" style="border-top: 5px solid <?= $color ?>; animation-delay: <?= $index * 0.08 ?>s;">
          <div>
            <div class="flex items-center justify-between mb-3">
              <div class="pkg-icon-wrap" style="background: <?= $color ?>15; color: <?= $color ?>;">
                <?= $pkg['icon'] ?>
              </div>
              <?php if ($discountPercent > 0): ?>
                <span class="discount-badge">خصم <?= $discountPercent ?>%</span>
              <?php endif; ?>
            </div>

            <h3 class="text-2xl font-black text-slate-900 mb-2"><?= htmlspecialchars($pkg['name']) ?></h3>

            <div class="price-box">
              <span class="price-new" style="color: <?= $color ?>;"><?= number_format($pkg['price'], 2) ?> <span style="font-size:16px;font-weight:700;">ر.س</span></span>
              <?php if ($discountAmount > 0): ?>
                <span class="price-old"><?= number_format($origPrice, 2) ?> ر.س</span>
              <?php endif; ?>
            </div>

            <!-- قائمة الخدمات المشمولة -->
            <div class="border-t border-slate-100 pt-4 mt-4">
              <div class="text-xs font-bold text-slate-500 mb-3">الخدمات المشمولة في هذه الباقة (<?= count($included) ?>):</div>
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

          <div class="pt-6 border-t border-slate-100 mt-4">
            <a href="register.php?package_id=<?= $pkg['id'] ?>" class="w-full inline-block text-center py-3 px-4 rounded-xl font-extrabold text-white text-sm shadow-md transition hover:scale-[1.02]" style="background: <?= $color ?>;">
              اطلب الباقة الآن 🚀
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Banner إضافي -->
    <div class="bg-gradient-to-l from-indigo-900 to-slate-900 text-white rounded-3xl p-8 md:p-12 text-center shadow-xl fade-up">
      <h3 class="text-2xl md:text-3xl font-black mb-3">✨ ترغب في تصميم باقة مخصصة لاحتياجاتك الخاصة؟</h3>
      <p class="text-slate-300 text-sm md:text-base max-w-2xl mx-auto mb-6">
        تواصل مباشرة مع المستشار الأكاديمي لتجميع الخدمات المطلوبة وحساب الخصم الخاص بمشروعك الجامعي.
      </p>
      <div class="flex justify-center gap-4 flex-wrap">
        <a href="register.php" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm shadow">تواصل مع المستشار الأكاديمي 💬</a>
        <a href="index.php#features" class="px-6 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl font-bold text-sm">تصفح جميع الخدمات ←</a>
      </div>
    </div>

  </div>

  <footer class="bg-slate-900 text-white py-10 px-4 mt-16">
    <div class="max-w-7xl mx-auto text-center text-xs text-slate-500">
      جميع الحقوق محفوظة © <?= date('Y') ?> منصة Eduroad
    </div>
  </footer>

</body>
</html>