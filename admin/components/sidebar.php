<?php
/**
 * Sidebar Component - Tawassul Admin
 * 
 * تعليق: تم إضافة require_once لملف functions.php لتجنب خطأ "undefined function db()"
 */

// تضمين دوال قاعدة البيانات والمساعدات
require_once __DIR__ . '/../../config/functions.php';

// بدء الجلسة إذا لم تكن قد بدأت (للتأكد من وجود بيانات المستخدم إن لزم)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// استعلامات العد للشارات (badges)
$sb_db = db();
$sb_students_cnt = (int) $sb_db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$sb_academics_cnt = (int) $sb_db->query("SELECT COUNT(*) FROM academics")->fetchColumn();
$sb_orders_cnt = (int) $sb_db->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn();
?>
<!-- ============================================
     Sidebar Component - Tawassul Admin
     ============================================ -->
<aside class="sidebar" id="sidebar">

  <!-- Logo -->
  <div class="sidebar-logo">
    <div class="logo-icon">🎓</div>
    <span class="logo-text">تواصل Admin</span>
    <button class="sidebar-mobile-close" id="sidebarMobileClose" title="إغلاق القائمة">✕</button>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">

    <!-- Main -->
    <p class="nav-section-title">القائمة الرئيسية</p>

    <a href="../pages/dashboard.php" class="nav-item" id="nav-dashboard">
      <span class="nav-icon">📊</span>
      <span class="nav-label">الرئيسية</span>
    </a>

    <!-- Users -->
    <p class="nav-section-title">إدارة المستخدمين</p>

    <a href="../pages/users.php" class="nav-item" id="nav-users">
      <span class="nav-icon">👥</span>
      <span class="nav-label">الطلاب</span>
      <span class="nav-badge"><?= number_format($sb_students_cnt) ?></span>
    </a>

    <a href="../pages/academics.php" class="nav-item" id="nav-academics">
      <span class="nav-icon">🎓</span>
      <span class="nav-label">الأكاديميون</span>
      <span class="nav-badge"><?= number_format($sb_academics_cnt) ?></span>
    </a>

    <!-- Operations -->
    <p class="nav-section-title">العمليات</p>

    <a href="../pages/services.php" class="nav-item" id="nav-services">
      <span class="nav-icon">⚙️</span>
      <span class="nav-label">الخدمات</span>
    </a>

    <a href="../pages/packages.php" class="nav-item" id="nav-packages">
      <span class="nav-icon">💎</span>
      <span class="nav-label">الباقات</span>
    </a>

    <a href="../pages/orders.php" class="nav-item" id="nav-orders">
      <span class="nav-icon">📋</span>
      <span class="nav-label">الطلبات</span>
      <?php if ($sb_orders_cnt > 0): ?>
        <span class="nav-badge" style="background:#ef4444"><?= $sb_orders_cnt ?></span>
      <?php endif; ?>
    </a>

    <!-- Finance -->
    <p class="nav-section-title">المالية والتقارير</p>

    <a href="../pages/payments.php" class="nav-item" id="nav-payments">
      <span class="nav-icon">💰</span>
      <span class="nav-label">المدفوعات</span>
    </a>

    <a href="../pages/reports.php" class="nav-item" id="nav-reports">
      <span class="nav-icon">📈</span>
      <span class="nav-label">التقارير</span>
    </a>

    <!-- System -->
    <p class="nav-section-title">النظام</p>

    <a href="../pages/settings.php" class="nav-item" id="nav-settings">
      <span class="nav-icon">🔧</span>
      <span class="nav-label">الإعدادات</span>
    </a>

  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="<?= rootUrl() ?>/logout.php" class="nav-item">
      <span class="nav-icon">🚪</span>
      <span class="nav-label">تسجيل الخروج</span>
    </a>
  </div>

</aside>