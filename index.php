<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$db = db();

// جلب جميع الخدمات المفعلة مع الأقسام والأب
$services_stmt = $db->query("
    SELECT s.id, s.parent_id, s.name, s.icon, s.description, s.price, s.level, s.is_active,
           (SELECT name FROM services p WHERE p.id = s.parent_id) AS parent_name,
           (SELECT icon FROM services p WHERE p.id = s.parent_id) AS parent_icon
    FROM services s
    WHERE s.is_active = 1
    ORDER BY s.level ASC, s.sort_order ASC, s.id ASC
");
$all_services = $services_stmt->fetchAll();

if (!is_array($all_services)) {
  $all_services = [];
}

// ── استبعاد أي خدمة/قسم يقع تحت قسم غير مفعّل (المستوى الأول أو الثاني) ──
// حتى لا تظهر في الفلترة أو العرض إذا تم إيقاف القسم الأب
$byId = [];
foreach ($all_services as $row) {
  $byId[(int)$row['id']] = $row;
}

function hasInactiveAncestor(array $row, array $byId): bool
{
  $pid = $row['parent_id'] ? (int)$row['parent_id'] : null;
  while ($pid !== null && isset($byId[$pid])) {
    $parent = $byId[$pid];
    if (!(int)$parent['is_active']) return true;
    $pid = $parent['parent_id'] ? (int)$parent['parent_id'] : null;
  }
  return false;
}

$all_services = array_values(array_filter($all_services, function ($row) use ($byId) {
  return !hasInactiveAncestor($row, $byId);
}));

// ── إعادة الترتيب الهرمي (نفس تسلسل عرض admin/pages/services.php) ──
// كل قسم رئيسي بترتيبه، ثم أقسامه الفرعية بترتيبها، ثم خدمات كل قسم فرعي
$childrenMap = [];
foreach ($all_services as $row) {
  $pid = $row['parent_id'] ? (int)$row['parent_id'] : 0;
  $childrenMap[$pid][] = $row;
}
$orderedServices = [];
$buildOrderedServices = function ($pid) use (&$buildOrderedServices, &$orderedServices, $childrenMap) {
  foreach ($childrenMap[$pid] ?? [] as $row) {
    $orderedServices[] = $row;
    $buildOrderedServices((int)$row['id']);
  }
};
$buildOrderedServices(0);
$all_services = $orderedServices;

// تحويل البيانات إلى JSON للاستخدام في JavaScript
$all_services_json = json_encode($all_services, JSON_UNESCAPED_UNICODE);

// ── جلب الخدمات المميزة (جدول منفصل featured_services) ──
// تُعرض أولاً في قسم خدماتنا، ثم زر لعرض باقي الخدمات
$featured_services = [];
$featured_ids = [];
try {
    $fstmt = $db->query("
        SELECT f.id AS fid, f.service_id, f.badge, f.sort_order, f.is_active AS f_active,
               s.id AS sid, s.parent_id, s.name, s.icon, s.icon_image, s.description, s.price,
               s.level, s.is_active,
               (SELECT name FROM services p WHERE p.id = s.parent_id) AS parent_name
        FROM featured_services f
        JOIN services s ON s.id = f.service_id
        WHERE f.is_active = 1 AND s.is_active = 1
        ORDER BY f.sort_order ASC, f.id ASC
    ");
    $featured_services = $fstmt->fetchAll() ?: [];
    // استبعاد أي خدمة مميزة تقع تحت قسم غير مفعّل + استبعاد غير المستوى الثالث
    $featured_services = array_values(array_filter($featured_services, function ($row) use ($byId) {
        if ((int)($row['level'] ?? 0) !== 3) return false;
        $tmp = ['id' => $row['sid'], 'parent_id' => $row['parent_id'], 'is_active' => $row['is_active']];
        return !hasInactiveAncestor($tmp, $byId);
    }));
    foreach ($featured_services as $fr) { $featured_ids[] = (int)$fr['service_id']; }
} catch (Throwable $e) {
    $featured_services = [];
    $featured_ids = [];
}
$featured_services_json = json_encode($featured_services, JSON_UNESCAPED_UNICODE);
$featured_ids_json = json_encode($featured_ids, JSON_UNESCAPED_UNICODE);

// جلب الأكاديميين المتميزين
$all_featured = [];
if (function_exists('getAllFeaturedAcademics')) {
  try {
    $all_featured = getAllFeaturedAcademics(true);
    if (!is_array($all_featured)) $all_featured = [];
  } catch (Exception $e) {
    $all_featured = [];
  }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
  <title>Eduroad - منصة الخدمات الأكاديمية الشاملة</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    * {
      font-family: 'Tajawal', sans-serif;
    }

    body {
      overflow-x: hidden;
      width: 100%;
      background-color: #f8fafc;
    }

    .hero {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.05) 100%);
      border-radius: 48px;
      margin: 20px 16px 0 16px;
    }

    @media (min-width: 768px) {
      .hero {
        margin: 20px 20px 0 20px;
      }
    }

    /* ===== قسم الخدمات المميزة ⭐ (تُعرض أولاً في الواجهة) ===== */
    .featured-card {
      background: linear-gradient(135deg, #fffbeb 0%, #ffffff 60%);
      border-radius: 24px;
      padding: 24px 20px;
      text-align: right;
      transition: all 0.3s ease;
      border: 2px solid #fcd34d;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 6px 20px rgba(245,158,11,0.12);
      position: relative;
      overflow: hidden;
    }
    .featured-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px -10px rgba(245,158,11,0.30);
      border-color: #f59e0b;
    }
    .featured-card .feature-icon {
      width: 60px; height: 60px;
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #fff; border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      font-size: 30px; margin-bottom: 16px; transition: all 0.3s;
    }
    .featured-card:hover .feature-icon { transform: scale(1.05) rotate(-3deg); }
    .featured-ribbon {
      position: absolute; top: 14px; left: -34px;
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #fff; font-size: 11px; font-weight: 800;
      padding: 4px 36px; transform: rotate(-35deg);
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .featured-badge-tag {
      display: inline-flex; align-items: center; gap: 4px;
      background: #fef3c7; border: 1px solid #fcd34d; color: #92400e;
      font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 20px;
    }
    .featured-divider {
      display: flex; align-items: center; gap: 12px; margin: 26px 0 4px;
    }
    .featured-divider::before, .featured-divider::after {
      content: ''; flex: 1; height: 1px; background: #e2e8f0;
    }
    .toggle-all-btn {
      display: inline-flex; align-items: center; gap: 8px;
      background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
      color: #fff; font-weight: 800; font-size: 15px;
      padding: 12px 30px; border-radius: 9999px; border: none; cursor: pointer;
      box-shadow: 0 6px 20px rgba(79,70,229,0.30); transition: all 0.25s ease;
    }
    .toggle-all-btn:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 10px 28px rgba(79,70,229,0.40); }
    .toggle-all-btn.collapse { background: linear-gradient(135deg, #64748b 0%, #94a3b8 100%); box-shadow: 0 6px 20px rgba(100,116,139,0.25); }
    #allServicesWrapper { overflow: hidden; transition: opacity 0.3s ease; }
    #allServicesWrapper.hidden-box { opacity: 0; margin: 0 !important; }
    #allServicesWrapper.shown-box { opacity: 1; }
    /* زر عائم ثابت لإغلاق القائمة (يبقى ظاهراً أثناء التمرير مهما طالت القائمة) */
    .floating-close-btn {
      position: fixed; z-index: 999; bottom: 22px; left: 50%;
      transform: translateX(-50%);
      align-items: center; gap: 8px;
      background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
      color: #fff; font-weight: 800; font-size: 14px;
      padding: 12px 26px; border-radius: 9999px; border: none; cursor: pointer;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.35);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .floating-close-btn:hover { transform: translateX(-50%) translateY(-2px); box-shadow: 0 14px 34px rgba(15, 23, 42, 0.45); }
    /* زر إغلاق أسفل القائمة */
    .close-bottom-btn {
      display: inline-flex; align-items: center; gap: 8px;
      background: #ffffff; color: #334155; font-weight: 800; font-size: 14px;
      padding: 11px 26px; border-radius: 9999px; border: 2px solid #cbd5e1; cursor: pointer;
      transition: all 0.2s ease;
    }
    .close-bottom-btn:hover { background: #f1f5f9; border-color: #94a3b8; }

    .feature-card {
      background: #ffffff;
      border-radius: 24px;
      padding: 24px 20px;
      text-align: right;
      transition: all 0.3s ease;
      border: 1px solid #e2e8f0;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    }

    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 35px -10px rgba(79, 70, 229, 0.15);
      border-color: #6366f1;
    }

    .feature-icon {
      width: 60px;
      height: 60px;
      background: #eef2ff;
      color: #4f46e5;
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
      margin-bottom: 16px;
      transition: all 0.3s;
    }

    .feature-card:hover .feature-icon {
      background: #4f46e5;
      color: white;
      transform: scale(1.05);
    }

    /* تبويبات الفلترة */
    .cat-tab {
      background: #ffffff;
      color: #475569;
      border: 1px solid #e2e8f0;
      padding: 8px 18px;
      border-radius: 30px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
      white-space: nowrap;
    }

    .cat-tab:hover {
      background: #f1f5f9;
      color: #0f172a;
      border-color: #cbd5e1;
    }

    .cat-tab.active {
      background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
      color: white;
      border-color: #4f46e5;
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }

    .price-tag {
      background: #ecfdf5;
      color: #059669;
      border: 1px solid #a7f3d0;
      font-size: 12px;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .academics-section {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.02) 100%);
      border-radius: 48px;
      margin: 40px 16px;
      padding: 40px 16px;
      overflow: hidden;
    }

    @media (min-width: 768px) {
      .academics-section {
        margin: 40px 20px;
        padding: 60px 20px;
      }
    }

    .academicsSwiper {
      width: 100%;
      overflow: visible !important;
      padding: 10px 5px 40px 5px;
    }

    .swiper-slide {
      width: auto !important;
      max-width: 100%;
      height: auto;
    }

    .academic-card {
      background: var(--bg-card);
      border-radius: 28px;
      padding: 24px 16px;
      text-align: center;
      transition: all 0.4s;
      box-shadow: 0 15px 30px -8px rgba(0, 0, 0, 0.1);
      border: 1px solid var(--border-color);
      height: 100%;
      width: 100%;
      max-width: 280px;
      margin: 0 auto;
    }

    .academic-img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      margin: 0 auto 16px;
      border: 3px solid var(--primary-light);
      transition: all 0.3s;
    }

    .mobile-menu {
      position: fixed;
      top: 0;
      right: -100%;
      width: 280px;
      height: 100%;
      background: var(--bg-card);
      z-index: 999;
      transition: 0.3s ease;
      padding: 30px 20px;
      box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
    }

    .mobile-menu.active {
      right: 0;
    }

    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(3px);
      z-index: 998;
      visibility: hidden;
      opacity: 0;
      transition: 0.2s;
    }

    .overlay.active {
      visibility: visible;
      opacity: 1;
    }

    .fade-up {
      opacity: 0;
      transform: translateY(20px);
      animation: fadeUp 0.6s forwards;
    }

    @keyframes fadeUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }

    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
  </style>
</head>

<body class="overflow-x-hidden">

  <div class="overlay" id="menuOverlay"></div>

  <!-- شريط التنقل -->
  <nav class="relative flex items-center justify-between flex-wrap p-3 md:p-4 lg:px-8 bg-white border-b border-slate-200">
    <div class="flex items-center gap-2 text-xl md:text-2xl font-extrabold">
      <img src="image/eduroad_logo.png" alt="Eduroad" style="height:28px;width:auto" />
      <span class="text-slate-900">Eduroad</span>
    </div>

    <div class="hidden md:flex items-center gap-5 lg:gap-6 text-sm font-semibold">
      <a href="#features" class="text-slate-600 hover:text-indigo-600 transition">الخدمات والأقسام</a>
      <a href="#how-it-works" class="text-slate-600 hover:text-indigo-600 transition">كيف نعمل</a>
      <a href="#academics" class="text-slate-600 hover:text-indigo-600 transition">أكاديميونا</a>
      <a href="packages.php" class="text-slate-600 hover:text-indigo-600 transition">الباقات</a>
      <a href="reviews.php" class="text-slate-600 hover:text-indigo-600 transition">التقييمات</a>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
      <button class="dark-toggle text-xl bg-transparent border-none cursor-pointer px-1">🌙</button>
      <a href="login.php" class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-full border border-indigo-600 text-indigo-600 text-xs sm:text-sm font-bold hover:bg-indigo-600 hover:text-white transition whitespace-nowrap">تسجيل الدخول</a>
      <a href="register.php" class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-full bg-gradient-to-l from-indigo-600 to-indigo-700 text-white text-xs sm:text-sm font-bold shadow-md hover:scale-105 transition whitespace-nowrap">إنشاء حساب</a>
      <button id="menuToggle" class="md:hidden text-2xl focus:outline-none pr-1">☰</button>
    </div>
  </nav>

  <div class="mobile-menu flex flex-col gap-5" id="mobileMenu">
    <a href="#features" class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-2">الخدمات والأقسام</a>
    <a href="#how-it-works" class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-2">كيف نعمل</a>
    <a href="#academics" class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-2">أكاديميونا</a>
    <a href="packages.php" class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-2">الباقات</a>
    <a href="reviews.php" class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-2">التقييمات</a>
  </div>

  <!-- Hero Section -->
  <header class="hero fade-up px-4 py-12 md:py-20 text-center">
    <div class="max-w-3xl mx-auto">
      <span class="inline-block px-4 py-1.5 bg-indigo-50 text-indigo-600 rounded-full text-xs md:text-sm font-extrabold mb-4 md:mb-6">🚀 المنصة الأكاديمية رقم 1 في الوطن العربي</span>
      <h1 class="text-4xl md:text-5xl lg:text-6xl font-black mb-4 md:mb-6 bg-gradient-to-l from-slate-900 to-indigo-600 bg-clip-text text-transparent leading-tight">أنجز أبحاثك ومشاريعك الجامعية بكفاءة واحترافية</h1>
      <p class="text-base md:text-lg text-slate-600 mb-6 md:mb-10 leading-relaxed px-2">نربط الطلاب بنخبة من الأكاديميين المتخصصين لتقديم الدعم والمساندة في كافة متطلبات التحليل الإحصائي، الترجمة، التدقيق اللغوي، والمزيد الموثق.</p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
        <a href="register.php" class="w-full sm:w-auto text-base md:text-lg px-6 md:px-8 py-3 md:py-4 rounded-full bg-gradient-to-l from-indigo-600 to-indigo-700 text-white font-bold shadow-lg hover:scale-105 transition text-center">ابدأ الآن - مجاناً 🚀</a>
        <a href="#features" class="w-full sm:w-auto text-base md:text-lg px-6 md:px-8 py-3 md:py-4 rounded-full border-2 border-indigo-600 text-indigo-600 font-bold hover:bg-indigo-600 hover:text-white transition text-center">📋 تصفح الخدمات المتوفرة</a>
      </div>
      <p class="text-xs md:text-sm text-slate-400 mt-6 md:mt-8">🔒 خدمات موثقة بضمان الجودة | أكثر من 15,000 طالب استفادوا</p>
    </div>
  </header>

  <!-- قسم الخدمات والأقسام التفاعلية مع الفلترة والبحث المباشر -->
  <section id="features" class="py-12 md:py-20 px-4 max-w-7xl mx-auto">
    <div class="text-center mb-8 md:mb-12 fade-up">
      <span class="inline-block bg-indigo-50 text-indigo-600 px-4 py-1.5 rounded-full text-xs md:text-sm font-bold mb-3">خدماتنا الأكاديمية المباشرة</span>
      <h2 class="text-3xl md:text-4xl font-black mb-3 text-slate-900">اختر من بين تخصصاتنا وخدماتنا المعتمده</h2>
      <p class="text-slate-600 max-w-2xl mx-auto text-sm md:text-base">استعرض كافة الخدمات أو قم بالفلترة والبحث السريع للوصول إلى طلبك المحدد فوراً</p>
    </div>

    <?php if (!empty($featured_services)): ?>
    <!-- ⭐ الخدمات المميزة — تُعرض أولاً -->
    <div class="mb-2 fade-up">
      <div class="featured-divider">
        <span class="text-lg md:text-xl font-black text-amber-700">⭐ الخدمات المميزة</span>
      </div>
      <p class="text-center text-slate-500 text-xs md:text-sm mb-6">مختارات مميزة بعناية — ثم يمكنك استعراض باقي الخدمات بالزر أدناه</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 mb-4" id="featuredServicesGrid">
      <?php foreach ($featured_services as $i => $fs): ?>
        <div class="featured-card fade-up" style="animation-delay:<?= ($i % 6) * 0.08 ?>s;">
          <span class="featured-ribbon">مميزة ⭐</span>
          <div>
            <div class="flex items-center mb-3">
              <div class="feature-icon" style="margin-bottom:0;"><?= htmlspecialchars($fs['icon'] ?: '⭐') ?></div>
            </div>
            <h3 class="text-xl font-extrabold text-slate-900 mb-2"><?= htmlspecialchars($fs['name']) ?></h3>
            <div class="mb-3 flex items-center gap-2 flex-wrap">
              <?php if (!empty($fs['parent_name'])): ?>
                <span class="text-xs text-indigo-600 font-bold bg-indigo-50 px-2.5 py-1 rounded-full"><?= htmlspecialchars($fs['parent_name']) ?></span>
              <?php endif; ?>
              <?php if (!empty($fs['badge'])): ?>
                <span class="featured-badge-tag">🏷️ <?= htmlspecialchars($fs['badge']) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($fs['description'])): ?>
              <p class="text-slate-500 text-xs md:text-sm leading-6 mb-3"><?= htmlspecialchars(mb_strimwidth($fs['description'], 0, 140, '…')) ?></p>
            <?php endif; ?>
          </div>
          <div>
            <a href="register.php?service_id=<?= (int)$fs['service_id'] ?>" class="w-full inline-block text-center py-2.5 px-4 rounded-xl text-white font-bold text-sm transition shadow-sm" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
              اطلب الخدمة المميزة ⭐
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- زر عرض / إغلاق باقي الخدمات -->
    <div class="text-center mt-6 mb-2">
      <button type="button" id="toggleAllServicesBtn" class="toggle-all-btn" onclick="toggleAllServices()">
        <span id="toggleAllServicesLabel">📋 عرض باقي الخدمات (<?= count($all_services) ?>)</span>
        <span id="toggleAllServicesArrow">⬇️</span>
      </button>
    </div>
    <?php endif; ?>

    <!-- باقي الخدمات (مخفية افتراضياً عند وجود مميزة — وتُفتح بالزر ويمكن إغلاقها) -->
    <div id="allServicesWrapper" class="<?= !empty($featured_services) ? 'hidden-box' : 'shown-box' ?>" <?= !empty($featured_services) ? 'style="max-height:0;"' : '' ?>>

    <!-- أدوات الفلترة والبحث -->
    <div class="bg-white p-4 md:p-6 rounded-2xl border border-slate-200 shadow-sm mb-8 fade-up">
      <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <!-- شريط البحث المباشر -->
        <div class="w-full md:w-80 relative">
          <input type="text" id="publicSearchInput" placeholder="🔍 بحث عن خدمة أو قسم..."
            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 text-sm font-medium transition" oninput="onPublicSearchOrFilter()" />
        </div>

        <!-- تبويبات الفلترة حسب الأقسام الرئيسية (Level 1) -->
        <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto pb-2 md:pb-0 no-scrollbar" id="categoryTabs">
          <button class="cat-tab active" data-cat-id="all" onclick="selectCategoryTab('all', this)">
            🌐 الكل (جميع الخدمات)
          </button>
          <!-- يتم ملؤها بـ JS للأقسام الرئيسية -->
        </div>
      </div>
    </div>

    <!-- شبكة عرض الخدمات التفاعلية (باقي الخدمات — بدون المميزة) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8" id="publicServicesGrid">
      <!-- يتم ملؤها بالـ JavaScript -->
    </div>

    <?php if (!empty($featured_services)): ?>
    <!-- زر إغلاق في نهاية القائمة (حتى لا يحتاج المستخدم للعودة لأعلى) -->
    <div class="text-center mt-10">
      <button type="button" id="bottomCloseServicesBtn" class="close-bottom-btn" onclick="closeAllServices()">
        <span>🔼 إغلاق باقي الخدمات</span>
      </button>
    </div>
    <?php endif; ?>
    </div><!-- /#allServicesWrapper -->

    <?php if (!empty($featured_services)): ?>
    <!-- زر عائم ثابت لإغلاق القائمة — يظهر فقط عند فتح باقي الخدمات -->
    <button type="button" id="floatingCloseServicesBtn" class="floating-close-btn" style="display:none;" onclick="closeAllServices()">
      <span>🔼 إغلاق القائمة</span>
    </button>
    <?php endif; ?>
  </section>

  <!-- Academics Carousel -->
  <section id="academics" class="academics-section">
    <div class="max-w-7xl mx-auto">

      <!-- Section Header -->
      <div class="text-center mb-8 md:mb-12 fade-up">
        <span class="inline-block px-4 py-1.5 bg-[var(--primary-light)] text-[var(--primary)] rounded-full text-xs md:text-sm font-bold mb-3">
          نخبة من الخبراء
        </span>

        <h2 class="text-3xl md:text-4xl font-extrabold mb-3">
          الأستشاريين المتميزون
        </h2>

        <p class="text-[var(--text-secondary)] max-w-2xl mx-auto">
          تعاون مع أفضل الأساتذة والباحثين – اختر من بين خبرائنا المتخصصين
        </p>
      </div>

      <!-- Swiper -->
      <div class="swiper academicsSwiper">

        <div class="swiper-wrapper">

          <?php if (empty($all_featured)): ?>
            <!-- بطاقة افتراضية إن لم توجد بيانات -->
            <div class="swiper-slide">
              <div class="academic-card">
                <img src="/image/IMG-20260620-WA0014.jpg" alt="البروفيسور خليل سعيد الوجيه" class="academic-img">
                <h3 class="text-xl md:text-2xl font-extrabold">البروفيسور خليل سعيد الوجيه</h3>
                <p class="text-[var(--primary)] font-bold text-sm">دكتوراه في النمذجه والمحاكاة</p>
                <div class="stars">★★★★★</div>
                <p class="text-sm text-[var(--text-secondary)] mb-4">
                  الأستاذ الدكتور خليل سعيد الوجيه، رئيس جامعة الرازي، هو أكاديمي يمني بارز حاصل على الدكتوراه في النمذجة والمحاكاة من جامعة الموصل.
                </p>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($all_featured as $f): ?>
              <?php
              $f_name = e($f['name'] ?? '');
              $f_specialty = e($f['specialty'] ?? '');
              $f_bio = e($f['bio'] ?? '');
              $f_image = ($f['image'] ?? '') ? 'uploads/featured/' . ltrim(basename($f['image']), '/') : '';
              ?>
              <div class="swiper-slide">
                <div class="academic-card">
                  <?php if ($f_image): ?>
                    <img src="<?= $f_image ?>" alt="<?= $f_name ?>" class="academic-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="academic-img" style="display:none;align-items:center;justify-content:center;font-size:40px;">🎓</div>
                  <?php else: ?>
                    <div class="academic-img" style="display:flex;align-items:center;justify-content:center;font-size:40px;">🎓</div>
                  <?php endif; ?>

                  <h3 class="text-xl md:text-2xl font-extrabold"><?= $f_name ?></h3>

                  <p class="text-[var(--primary)] font-bold text-sm"><?= $f_specialty ?></p>

                  <div class="stars">★★★★★</div>

                  <p class="text-sm text-[var(--text-secondary)] mb-4"><?= $f_bio ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        </div>

        <!-- Navigation -->
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>

        <!-- Pagination -->
        <div class="swiper-pagination"></div>

      </div>


      <!-- Bottom CTA -->
      <div class="text-center mt-8 md:mt-10">

        <div class="inline-flex flex-wrap items-center justify-center gap-3 bg-[rgba(79,70,229,0.1)] px-4 md:px-8 py-3 rounded-full">

          <span class="text-2xl">🎓</span>

          <span class="text-sm md:text-base font-semibold">
            انضم إلى نخبة الأكاديميين ووسّع نطاق تأثيرك
          </span>

          <a
            href="register.php?role=academic"
            class="bg-[var(--primary)] text-white rounded-full px-5 py-2 text-sm font-bold transition hover:scale-105">
            تقديم طلب
          </a>

        </div>

      </div>

    </div>
  </section>>

  <!-- How It Works -->
  <section id="how-it-works" class="relative overflow-hidden py-16 md:py-24 px-4 bg-slate-50">

    <!-- Soft Background -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
      <div class="absolute -top-32 -right-32 w-80 h-80 bg-indigo-200/20 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-32 -left-32 w-80 h-80 bg-indigo-200/15 rounded-full blur-3xl"></div>
    </div>

    <div class="relative max-w-6xl mx-auto">

      <!-- Header -->
      <div class="text-center mb-14 md:mb-20 fade-up">

        <span class="inline-flex items-center gap-2
                   bg-indigo-50 text-indigo-600
                   px-4 py-2 rounded-full
                   text-xs md:text-sm font-bold mb-4">
          <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
          خطوات بسيطة
        </span>

        <h2 class="text-3xl md:text-5xl font-black text-slate-900 mb-4">
          آلية العمل بكل بساطة
        </h2>

        <p class="text-slate-500 text-sm md:text-base max-w-2xl mx-auto leading-7">
          ثلاث خطوات واضحة تفصلك عن إنجاز بحثك باحترافية وسهولة
        </p>

      </div>

      <!-- Steps -->
      <div class="relative grid grid-cols-1 md:grid-cols-3 gap-7 md:gap-8">

        <!-- Connecting Line -->
        <div class="hidden md:block absolute top-12 right-[18%] left-[18%] h-px
                  bg-gradient-to-r from-transparent via-indigo-200 to-transparent">
        </div>


        <!-- Step 1 -->
        <div
          class="group relative bg-white rounded-2xl p-8 text-center
               border border-slate-200
               shadow-[0_4px_20px_rgba(15,23,42,0.04)]
               hover:border-indigo-200
               hover:shadow-[0_15px_40px_rgba(79,70,229,0.10)]
               hover:-translate-y-1
               transition-all duration-300 fade-up"
          style="animation-delay:0.1s">

          <div class="relative z-10 mx-auto mb-6 w-20 h-20">

            <div class="w-20 h-20 rounded-2xl
                      bg-indigo-50 text-indigo-600
                      flex items-center justify-center
                      border border-indigo-100
                      group-hover:bg-indigo-600
                      group-hover:text-white
                      transition-all duration-300">

              <svg class="w-8 h-8" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9 12h6m-6 4h4M9 4h6l4 4v12H5V4h4Z" />
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15 4v4h4" />
              </svg>

            </div>

            <span class="absolute -top-2 -right-2
                       w-7 h-7 rounded-full
                       bg-indigo-600 text-white
                       text-xs font-black
                       flex items-center justify-center
                       border-4 border-white">
              1
            </span>

          </div>

          <h4 class="font-black text-xl text-slate-900 mb-3">
            اطلب الخدمة
          </h4>

          <p class="text-sm md:text-base text-slate-500 leading-7">
            حدد الخدمة وأضف جميع تفاصيل الطلب والمرفقات بكل سهولة ووضوح.
          </p>

        </div>


        <!-- Step 2 -->
        <div
          class="group relative bg-white rounded-2xl p-8 text-center
               border border-slate-200
               shadow-[0_4px_20px_rgba(15,23,42,0.04)]
               hover:border-indigo-200
               hover:shadow-[0_15px_40px_rgba(79,70,229,0.10)]
               hover:-translate-y-1
               transition-all duration-300 fade-up"
          style="animation-delay:0.2s">

          <div class="relative z-10 mx-auto mb-6 w-20 h-20">

            <div class="w-20 h-20 rounded-2xl
                      bg-indigo-50 text-indigo-600
                      flex items-center justify-center
                      border border-indigo-100
                      group-hover:bg-indigo-600
                      group-hover:text-white
                      transition-all duration-300">

              <svg class="w-8 h-8" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M7 18.5A8.5 8.5 0 1 1 18.5 7" />
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M7 18.5 4 20l1.5-3A8.4 8.4 0 0 1 7 18.5Z" />
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8 11h.01M12 11h.01M16 11h.01" />
              </svg>

            </div>

            <span class="absolute -top-2 -right-2
                       w-7 h-7 rounded-full
                       bg-indigo-600 text-white
                       text-xs font-black
                       flex items-center justify-center
                       border-4 border-white">
              2
            </span>

          </div>

          <h4 class="font-black text-xl text-slate-900 mb-3">
            اختيار الاكاديمي
          </h4>

          <p class="text-sm md:text-base text-slate-500 leading-7">
            يتم تحويل طلبك الى الاكاديمي المختص في مجال التخصص الدقيق للطلب </p>

        </div>


        <!-- Step 3 -->
        <div
          class="group relative bg-white rounded-2xl p-8 text-center
               border border-slate-200
               shadow-[0_4px_20px_rgba(15,23,42,0.04)]
               hover:border-indigo-200
               hover:shadow-[0_15px_40px_rgba(79,70,229,0.10)]
               hover:-translate-y-1
               transition-all duration-300 fade-up"
          style="animation-delay:0.3s">

          <div class="relative z-10 mx-auto mb-6 w-20 h-20">

            <div class="w-20 h-20 rounded-2xl
                      bg-indigo-50 text-indigo-600
                      flex items-center justify-center
                      border border-indigo-100
                      group-hover:bg-indigo-600
                      group-hover:text-white
                      transition-all duration-300">

              <svg class="w-8 h-8" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="m9 12.75 2.25 2.25L15 9.75" />
                <circle cx="12" cy="12" r="8.5" />
              </svg>

            </div>

            <span class="absolute -top-2 -right-2
                       w-7 h-7 rounded-full
                       bg-indigo-600 text-white
                       text-xs font-black
                       flex items-center justify-center
                       border-4 border-white">
              3
            </span>

          </div>

          <h4 class="font-black text-xl text-slate-900 mb-3">
            استلم مشروعك
          </h4>

          <p class="text-sm md:text-base text-slate-500 leading-7">
            راجع العمل بعد الإنجاز واستلم مشروعك وقيّم التجربة بكل سهولة.
          </p>

        </div>

      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-slate-900 text-white py-12 px-4">
    <div class="max-w-7xl mx-auto text-center">
      <div class="flex justify-center items-center gap-2 text-2xl font-black mb-4">
        <img src="image/eduroad_logo.png" alt="Eduroad" style="height:30px;width:auto" /> <span>Eduroad</span>
      </div>
      <p class="text-slate-400 text-sm max-w-md mx-auto mb-6">المنصة الأكاديمية الرائدة لدعم الباحثين والطلاب بالوطن العربي بأعلى معايير الجودة والسرية.</p>
      <div class="text-xs text-slate-500 border-t border-slate-800 pt-6">جميع الحقوق محفوظة © <?= date('Y') ?> منصة Eduroad</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script>
    // بيانات الخدمات المأخوذة من قاعدة البيانات
    const allServices = <?= $all_services_json ?>;
    // الخدمات المميزة + معرفاتها (لاستبعادها من شبكة "باقي الخدمات")
    const featuredIds = new Set(<?= $featured_ids_json ?>);
    const hasFeatured = featuredIds.size > 0;

    let selectedCatId = 'all';
    let allServicesOpen = !hasFeatured; // مغلق افتراضياً عند وجود مميزة، مفتوح عند عدم وجودها

    // استخراج الأقسام الرئيسية (Level 1) لإنشاء التبويبات
    function initCategoryTabs() {
      const tabsContainer = document.getElementById('categoryTabs');
      if (!tabsContainer || !allServices.length) return;

      const l1Categories = allServices.filter(s => s.level === 1);
      l1Categories.forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'cat-tab';
        btn.setAttribute('data-cat-id', cat.id);
        btn.onclick = function() {
          selectCategoryTab(cat.id, this);
        };
        btn.innerHTML = `${cat.icon || '📌'} ${cat.name}`;
        tabsContainer.appendChild(btn);
      });
    }

    // خريطة معرفة القسم الرئيسي (المستوى الأول) لكل خدمة
    function getL1CategoryId(service) {
      if (!service.parent_id) return null;
      const parent = allServices.find(s => s.id === service.parent_id);
      if (!parent) return null;
      if (parent.level === 1) return parent.id;
      // إذا كان الأب من المستوى 2، نرجع الأب الأعلى له من المستوى 1
      const grandParent = allServices.find(s => s.id === parent.parent_id);
      return grandParent ? grandParent.id : parent.id;
    }

    function selectCategoryTab(catId, btnEl) {
      selectedCatId = catId;
      document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
      btnEl.classList.add('active');
      renderPublicServices();
    }

    function onPublicSearchOrFilter() {
      renderPublicServices();
    }

    // ===== عرض / إغلاق باقي الخدمات =====
    function setAllServicesOpen(open) {
      const wrap = document.getElementById('allServicesWrapper');
      const btn = document.getElementById('toggleAllServicesBtn');
      const label = document.getElementById('toggleAllServicesLabel');
      const arrow = document.getElementById('toggleAllServicesArrow');
      if (!wrap) return;

      allServicesOpen = !!open;

      if (allServicesOpen) {
        wrap.classList.remove('hidden-box');
        wrap.classList.add('shown-box');
        wrap.style.display = 'block';       // إظهار فعلي مضمون
        wrap.style.maxHeight = 'none';      // ارتفاع تلقائي — يمنع قصّ المحتوى
        wrap.style.opacity = '1';
        if (btn) btn.classList.add('collapse');
        if (label) label.textContent = '🔼 إغلاق باقي الخدمات';
        if (arrow) arrow.textContent = '⬆️';
        updateFloatingCloseBtn();   // يظهر الزر العائم فقط إن كان السكشن ظاهراً على الشاشة
      } else {
        wrap.classList.add('hidden-box');
        wrap.classList.remove('shown-box');
        wrap.style.display = 'none';        // إخفاء فعلي مضمون
        wrap.style.maxHeight = '0px';
        wrap.style.opacity = '0';
        if (btn) btn.classList.remove('collapse');
        if (label) label.textContent = '📋 عرض باقي الخدمات';
        if (arrow) arrow.textContent = '⬇️';
        updateFloatingCloseBtn();
      }
    }

    function toggleAllServices() {
      setAllServicesOpen(!allServicesOpen);
    }

    // إغلاق القائمة (يُستخدم من الزر العائم وزر أسفل القائمة)
    function closeAllServices() {
      setAllServicesOpen(false);
      // إعادة المستخدم إلى زر العرض أعلى القسم ليرى أن القائمة أُغلقت
      const anchor = document.getElementById('toggleAllServicesBtn') || document.getElementById('features');
      if (anchor) anchor.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // ===== الزر العائم: يظهر فقط عند فتح القائمة + وجود سكشن الخدمات على الشاشة =====
    let featuresInView = false;

    function updateFloatingCloseBtn() {
      const floatingBtn = document.getElementById('floatingCloseServicesBtn');
      if (!floatingBtn) return;
      floatingBtn.style.display = (allServicesOpen && featuresInView) ? 'inline-flex' : 'none';
    }

    // مراقبة ظهور سكشن الخدمات في الشاشة (IntersectionObserver مع بديل scroll)
    function initFeaturesVisibilityWatcher() {
      const section = document.getElementById('features');
      if (!section) return;

      if ('IntersectionObserver' in window) {
        const obs = new IntersectionObserver(function (entries) {
          featuresInView = entries[0].isIntersecting;
          updateFloatingCloseBtn();
        }, { threshold: 0, rootMargin: '-60px 0px -60px 0px' });
        obs.observe(section);
      } else {
        // بديل للمتصفحات القديمة
        const check = function () {
          const r = section.getBoundingClientRect();
          const vh = window.innerHeight || document.documentElement.clientHeight;
          featuresInView = r.bottom > 60 && r.top < (vh - 60);
          updateFloatingCloseBtn();
        };
        window.addEventListener('scroll', check, { passive: true });
        window.addEventListener('resize', check);
        check();
      }
    }

    // بناء وعرض بطاقات الخدمات (المستوى الثالث فقط — بدون الخدمات المميزة المعروضة أعلاه)
    function renderPublicServices() {
      const grid = document.getElementById('publicServicesGrid');
      const wrap = document.getElementById('allServicesWrapper');
      const searchTerm = document.getElementById('publicSearchInput').value.trim().toLowerCase();
      if (!grid) return;

      // تصفية خدمات المستوى الثالث فقط — مع استبعاد المميزة (معروضة في الأعلى)
      let itemsToDisplay = allServices.filter(s => s.level === 3 && !featuredIds.has(Number(s.id)));

      // إذا لم توجد خدمات بمستوى 3، نمرر الخدمات المتاحة (بدون المميزة)
      if (!itemsToDisplay.length && allServices.length) {
        itemsToDisplay = allServices.filter(s => s.level !== 1 && !featuredIds.has(Number(s.id)));
      }

      // الفلترة حسب القسم الرئيسي المحدد بالتبويبات
      if (selectedCatId !== 'all') {
        const catIdNum = parseInt(selectedCatId);
        itemsToDisplay = itemsToDisplay.filter(s => {
          return getL1CategoryId(s) === catIdNum || s.parent_id === catIdNum || s.id === catIdNum;
        });
      }

      // البحث بالنص
      if (searchTerm) {
        itemsToDisplay = itemsToDisplay.filter(s => {
          const nameMatch = (s.name || '').toLowerCase().includes(searchTerm);
          const descMatch = (s.description || '').toLowerCase().includes(searchTerm);
          const parentMatch = (s.parent_name || '').toLowerCase().includes(searchTerm);
          return nameMatch || descMatch || parentMatch;
        });
      }

      if (!itemsToDisplay.length) {
        grid.innerHTML = `
          <div class="col-span-full bg-white rounded-2xl p-10 text-center border border-slate-200 text-slate-500">
            <span class="text-5xl block mb-3">🔍</span>
            <h3 class="text-lg font-bold text-slate-800 mb-1">لا توجد خدمات تطابق البحث</h3>
            <p class="text-xs text-slate-500">جرّب البحث باسم آخر أو اختيار قسم مختلف من الأعلى.</p>
          </div>
        `;
        return;
      }

      grid.innerHTML = itemsToDisplay.map((s, i) => {
        const parentTag = s.parent_name ? `<span class="text-xs text-indigo-600 font-bold bg-indigo-50 px-2.5 py-1 rounded-full">${s.parent_name}</span>` : '';

        return `
          <div class="feature-card fade-up" style="animation-delay:${(i % 6) * 0.08}s;">
            <div>
              <div class="flex items-center mb-3">
                <div class="feature-icon" style="margin-bottom:0;">${s.icon || '📚'}</div>
              </div>
              <h3 class="text-xl font-extrabold text-slate-900 mb-2">${s.name}</h3>
              <div class="mb-3">${parentTag}</div>
            </div>
            <div>
              <a href="register.php?service_id=${s.id}" class="w-full inline-block text-center py-2.5 px-4 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 transition shadow-sm">
                اطلب الخدمة الآن 🚀
              </a>
            </div>
          </div>
        `;
      }).join('');
      // تحديث ارتفاع الحاوية بعد إعادة العرض (حتى لا يُقص المحتوى عند الفلترة والبحث)
      if (wrap && allServicesOpen && wrap.style.maxHeight !== 'none') {
        wrap.style.maxHeight = wrap.scrollHeight + 'px';
      }
    }

    // Swiper Carousel
    const swiper = new Swiper('.academicsSwiper', {
      slidesPerView: 'auto',
      spaceBetween: 20,
      loop: true,
      autoplay: {
        delay: 3000,
        disableOnInteraction: false
      },
      speed: 700,
      pagination: {
        el: '.swiper-pagination',
        clickable: true
      },
      grabCursor: true,
    });

    const menuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('menuOverlay');

    function closeMenu() {
      mobileMenu.classList.remove('active');
      overlay.classList.remove('active');
    }

    function openMenu() {
      mobileMenu.classList.add('active');
      overlay.classList.add('active');
    }
    if (menuToggle) menuToggle.addEventListener('click', openMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);

    document.addEventListener('DOMContentLoaded', function() {
      initCategoryTabs();
      renderPublicServices();
      // الحالة الافتراضية: مغلقة عند وجود خدمات مميزة، ومفتوحة عند عدم وجودها
      setAllServicesOpen(!hasFeatured);
      // مراقبة ظهور قسم الخدمات لإظهار/إخفاء الزر العائم تلقائياً
      initFeaturesVisibilityWatcher();
    });
  </script>
</body>

</html>