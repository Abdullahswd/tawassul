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

$notifsList = [];
$unreadCount = 0;
if ($currentAcademicId) {
    $dbNotifs = getNotifications($currentAcademicId, 'academic', false);
    $unreadCount = countUnreadNotifications($currentAcademicId, 'academic');
    foreach ($dbNotifs as $n) {
        $notifsList[] = [
            'id' => (int)$n['id'],
            'title' => $n['title'],
            'message' => $n['message'],
            'time' => date('Y/m/d H:i', strtotime($n['created_at'])),
            'read' => (bool)$n['is_read'],
            'icon' => $n['icon'] ?? '🔔'
        ];
    }
}
?>
<!--
  ============================================
  Academic Navbar Component
  ============================================
-->
<nav class="navbar" id="navbar">

  <!-- Sidebar toggle -->
  <button class="sidebar-toggle" id="sidebarToggle" aria-label="تبديل القائمة الجانبية">☰</button>

  <!-- Search (optional) -->
  <div class="navbar-search">
    <span class="s-icon">🔍</span>
    <input type="text" id="globalSearch" placeholder="بحث سريع..." aria-label="بحث"/>
  </div>

  <div class="navbar-spacer"></div>

  <!-- Actions -->
  <div class="navbar-actions">

    <!-- Dark mode -->
    <button class="nav-btn dark-toggle" title="تبديل المظهر" aria-label="تبديل المظهر الداكن">🌙</button>

    <!-- Notifications dropdown -->
    <div class="dropdown" id="notifDropdown">
      <button class="nav-btn" data-toggle aria-label="الإشعارات" style="position:relative">
        🔔
        <span class="n-badge" id="notifBadge"><?= $unreadCount ?></span>
      </button>
      <div class="dropdown-menu" style="right:0;left:auto;min-width:320px;padding:0;overflow:hidden">
        <!-- Header -->
        <div style="padding:14px 18px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:15px;font-weight:700;color:var(--text-primary)">الإشعارات</span>
          <button onclick="markAllRead()" style="font-size:12px;color:var(--primary);background:none;border:none;cursor:pointer;font-family:Tajawal,sans-serif">تعليم الكل مقروء</button>
        </div>
        <!-- List -->
        <div id="notifList" style="max-height:320px;overflow-y:auto"></div>
        <!-- Footer -->
        <div style="padding:12px;text-align:center;border-top:1px solid var(--border-color)">
          <a href="#" style="font-size:13px;color:var(--primary);font-weight:500">عرض جميع الإشعارات ←</a>
        </div>
      </div>
    </div>

    <!-- Divider -->
    <div style="width:1px;height:28px;background:var(--border-color)"></div>

    <!-- Profile dropdown -->
    <div class="dropdown" id="profileDropdown">
      <div class="admin-avatar-btn" data-toggle>
        <div class="avatar-circle" id="navAvatarCircle"><?= e($academicAvatar) ?></div>
        <div>
          <div class="avatar-name" id="navAvatarName"><?= e($academicName) ?></div>
          <div class="avatar-role">أكاديمي موثق ✓</div>
        </div>
        <span style="color:var(--text-secondary);font-size:12px;margin-right:4px">▾</span>
      </div>
      <div class="dropdown-menu" style="right:0;left:auto;min-width:200px">
        <div style="padding:8px">
          <a href="academic-profile.php?id=<?= $currentAcademicId ?>" class="dropdown-item" style="border-radius:8px">👤 ملفي الشخصي</a>
          <a href="academic-settings.php" class="dropdown-item" style="border-radius:8px">⚙️ الإعدادات</a>
          <a href="academic-earnings.php" class="dropdown-item" style="border-radius:8px">💰 الأرباح</a>
          <div class="dropdown-divider"></div>
          <a href="academics-list.php" class="dropdown-item" style="border-radius:8px">🌐 المنصة</a>
          <div class="dropdown-divider"></div>
          <a href="../logout.php" class="dropdown-item" style="border-radius:8px;color:var(--danger)">🚪 تسجيل الخروج</a>
        </div>
      </div>
    </div>

  </div>
</nav>

<script>
// This runs BEFORE main.js — pre-inject notifications so main.js picks them up via _preInjected
window.ACADEMICS_DATA = window.ACADEMICS_DATA || {};
window.ACADEMICS_DATA.notifications = <?= json_encode($notifsList, JSON_UNESCAPED_UNICODE) ?>;

// After main.js loads and calls DOMContentLoaded, we need to re-render with real badge count
document.addEventListener('DOMContentLoaded', function() {
  // Re-render notifications with the real data (main.js DOMContentLoaded also calls this,
  // but we call again here to be sure the badge reflects the PHP-computed unread count)
  const badge = document.getElementById('notifBadge');
  if (badge) badge.textContent = '<?= $unreadCount ?>';
  if (typeof renderNotifications === 'function') renderNotifications();
});

function markAllRead() {
  fetch('ajax/handler.php?action=mark_notifications_read')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        if (window.ACADEMICS_DATA) {
          window.ACADEMICS_DATA.notifications.forEach(n => n.read = true);
        }
        const badge = document.getElementById('notifBadge');
        if (badge) badge.textContent = '0';
        if (typeof renderNotifications === 'function') renderNotifications();
        if (typeof Toast !== 'undefined') Toast.show('تم تعليم جميع الإشعارات كمقروءة', 'success');
      }
    })
    .catch(() => {
      if (typeof Toast !== 'undefined') Toast.show('تعذر تحديث الإشعارات', 'error');
    });
}
</script>

