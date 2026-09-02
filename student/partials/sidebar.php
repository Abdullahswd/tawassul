<?php
/**
 * Unified Student Sidebar Partial
 *
 * Variables expected to be defined before including this file:
 *   $user        – current user array (from currentUser())
 *   $activePage  – string key identifying active nav item:
 *                  'dashboard' | 'services' | 'packages' | 'orders' | 'chat' | 'payments' | 'profile'
 *   $pageTitle   – string shown in the top navbar
 */

if (!isset($user))       $user       = currentUser();
if (!isset($activePage)) $activePage = '';
if (!isset($pageTitle))  $pageTitle  = 'لوحة التحكم';

$_navItems = [
    ['key' => 'dashboard', 'href' => 'student-dashboard.php', 'icon' => '📊', 'label' => 'لوحة المعلومات'],
    ['key' => 'services',  'href' => 'services.php',          'icon' => '📦', 'label' => 'الخدمات الأكاديمية'],
    ['key' => 'packages',  'href' => 'packages.php',          'icon' => '🎁', 'label' => 'الباقات المخصصة'],
    ['key' => 'orders',    'href' => 'orders.php',            'icon' => '📋', 'label' => 'طلباتي'],
    ['key' => 'chat',      'href' => 'chat.php',              'icon' => '💬', 'label' => 'المحادثات'],
    ['key' => 'payments',  'href' => 'payments.php',          'icon' => '💳', 'label' => 'المدفوعات'],
];

$_unread = countUnreadNotifications($user['id'], 'student');
?>

<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="app-container">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">

    <div class="sidebar-header">
      <img class="logo-icon" src="../image/eduroad_logo.png" alt="Eduroad" style="height:30px;width:auto;object-fit:contain;flex-shrink:0" />
      <div class="logo-text">Eduroad</div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">القائمة الرئيسية</div>

      <?php foreach ($_navItems as $_item): ?>
        <a href="<?= $_item['href'] ?>"
           class="nav-item<?= ($activePage === $_item['key']) ? ' active' : '' ?>"
           id="nav-<?= $_item['key'] ?>">
          <span class="nav-icon"><?= $_item['icon'] ?></span>
          <span class="nav-label"><?= $_item['label'] ?></span>
          <?php if ($_item['key'] === 'chat' && $_unread > 0): ?>
            <span class="nav-badge"><?= $_unread ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <div class="nav-section-label" style="margin-top:20px">إعدادات الحساب</div>
      <a href="profile.php"
         class="nav-item<?= ($activePage === 'profile') ? ' active' : '' ?>"
         id="nav-profile">
        <span class="nav-icon">👤</span>
        <span class="nav-label">الملف الشخصي</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="../logout.php" class="nav-item nav-item--danger" id="nav-logout">
        <span class="nav-icon">🚪</span>
        <span class="nav-label">تسجيل الخروج</span>
      </a>
    </div>

  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-area">

    <header class="top-navbar">
      <div class="navbar-start">
        <button class="menu-toggle" id="menuToggle" aria-label="فتح القائمة">☰</button>
        <div class="h3"><?= e($pageTitle) ?></div>
      </div>

      <div class="navbar-actions">
        <button class="icon-btn dark-toggle" aria-label="تبديل المظهر" id="darkToggle">🌙</button>

        <button class="icon-btn" aria-label="الإشعارات" id="notifBtn">
          🔔
          <?php if ($_unread > 0): ?>
            <span class="badge-dot"><?= $_unread ?></span>
          <?php endif; ?>
        </button>

        <div class="navbar-divider"></div>

        <a href="profile.php" class="user-profile">
          <div class="user-info">
            <span class="user-name"><?= e($user['name']) ?></span>
            <span class="user-role">طالب</span>
          </div>
          <div class="user-avatar"><?= e($user['avatar'] ?? mb_substr($user['name'], 0, 1, 'UTF-8')) ?></div>
        </a>
      </div>
    </header>

    <!-- PAGE CONTENT START -->
    <div class="content-wrap">
