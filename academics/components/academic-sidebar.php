<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';

$currentAcademicId = $_SESSION['academic_id'] ?? null;
$academicData = null;
if ($currentAcademicId) {
    $academicData = getAcademicById($currentAcademicId);
}

$academicName = $academicData['name'] ?? 'أكاديمي';
$academicAvatar = $academicData['avatar_initials'] ?? mb_substr($academicName, 0, 2);
$academicAvailability = $academicData['availability'] ?? 'available';

$statusText = 'متاح';
$statusColor = '#22c55e';
if ($academicAvailability === 'busy') {
    $statusText = 'مشغول';
    $statusColor = '#f59e0b';
} elseif ($academicAvailability === 'vacation') {
    $statusText = 'في إجازة';
    $statusColor = '#ef4444';
}

$newOrdersCount = 0;
if ($currentAcademicId) {
    $newOrdersCount = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'assigned' AND id IN (SELECT order_id FROM order_assignments WHERE academic_id = $currentAcademicId)")->fetchColumn();
}
?>
<!--
  ============================================
  Academic Sidebar Component
  Usage: inject this HTML into any dashboard page
  ============================================
-->
<aside class="sidebar" id="sidebar">

  <!-- Logo -->
  <div class="sidebar-logo">
    <div class="logo-icon" style="background:none;box-shadow:none;overflow:hidden;border-radius:10px"><img src="../image/eduroad_logo.png" alt="Eduroad" style="width:100%;height:100%;object-fit:contain" /></div>
    <span class="logo-text">Eduroad</span>
  </div>

  <!-- Profile mini card -->
  <div style="padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.07)">
    <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-white/5">
      <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#818cf8);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff;flex-shrink:0"><?= e($academicAvatar) ?></div>
      <div class="logo-text" style="min-width:0">
        <div style="font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($academicName) ?></div>
        <div style="font-size:11px;color:<?= $statusColor ?>">● <?= $statusText ?></div>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">

    <p class="nav-label-group">لوحة التحكم</p>

    <a href="academic-dashboard.php" class="nav-item" id="nav-dashboard">
      <span class="nav-icon">📊</span>
      <span class="nav-text">الرئيسية</span>
    </a>

    <a href="academic-orders.php" class="nav-item" id="nav-orders">
      <span class="nav-icon">📋</span>
      <span class="nav-text">الطلبات</span>
      <?php if ($newOrdersCount > 0): ?>
      <span class="nav-text" style="margin-right:auto;background:#ef4444;color:#fff;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700"><?= $newOrdersCount ?></span>
      <?php endif; ?>
    </a>

    <a href="academic-earnings.php" class="nav-item" id="nav-earnings">
      <span class="nav-icon">💰</span>
      <span class="nav-text">الأرباح</span>
    </a>

    <p class="nav-label-group">الحساب</p>

    <a href="academic-settings.php" class="nav-item" id="nav-settings">
      <span class="nav-icon">⚙️</span>
      <span class="nav-text">الإعدادات</span>
    </a>

    <a href="academic-profile.php?id=<?= $currentAcademicId ?>" target="_blank" class="nav-item" id="nav-profile">
      <span class="nav-icon">👤</span>
      <span class="nav-text">عرض ملفي للطلاب</span>
    </a>

  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="academics-list.php" class="nav-item">
      <span class="nav-icon">🌐</span>
      <span class="nav-text">العودة للمنصة</span>
    </a>
    <a href="../logout.php" class="nav-item" style="color:var(--danger)">
      <div class="nav-icon" style="background:rgba(239,68,68,0.1);color:#ef4444">🚪</div>
      <span class="nav-text">تسجيل الخروج</span>
    </a>
  </div>

</aside>

<!--
  Include in page <head>:
  <link rel="stylesheet" href="../assets/css/style.css"/>
  <script src="../assets/js/main.js"></script>
  
  In <body>:
  <div class="mobile-overlay" id="mobileOverlay"></div>
  <div class="page-layout">
    // include this sidebar HTML here
    <div class="main-content" id="mainContent">
      // include academic-navbar.php here
      <div class="page-body">
        // your page content
      </div>
    </div>
  </div>

  Auto-highlight active link: AcademicSidebar.markActive() is called in main.js on DOMContentLoaded.
-->

