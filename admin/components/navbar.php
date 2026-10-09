<?php
$curr_user = currentUser();
$user_name = $curr_user ? htmlspecialchars($curr_user['name']) : 'المدير العام';
$user_email = $curr_user ? htmlspecialchars($curr_user['email']) : 'admin@eduroad.com';
$user_avatar = $curr_user ? htmlspecialchars($curr_user['avatar']) : 'أ';
$user_avatar_html = ($curr_user && !empty($curr_user['avatar_path'])) 
    ? '<img src="' . rootUrl() . '/' . htmlspecialchars($curr_user['avatar_path']) . '" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">' 
    : $user_avatar;
$user_role = ($curr_user && $curr_user['role'] === 'admin') ? 'Super Admin' : 'طالب';

// Fetch unread notifications count dynamically for this admin
$nb_db = db();
$nb_unread_notifications = (int) $nb_db->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND user_type = 'admin'")->fetchColumn();

// Fetch latest 5 notifications for the dropdown
$nb_latest_notifs = $nb_db->query("SELECT * FROM notifications WHERE user_type = 'admin' ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!-- ============================================
     Navbar Component - Eduroad Admin
     ============================================ -->
<nav class="navbar" id="navbar">

  <!-- Toggle Sidebar Button -->
  <button class="navbar-toggle" id="sidebarToggle" title="تبديل القائمة">
    ☰
  </button>

  <!-- Search -->
  <div class="navbar-search">
    <div style="position:relative;">
      <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:16px;">🔍</span>
      <input type="search" placeholder="بحث سريع..." style="width:100%;padding:8px 36px 8px 12px;border-radius:10px;background:var(--bg-main);border:1px solid var(--border-color);color:var(--text-primary);font-family:'Tajawal',sans-serif;font-size:14px;outline:none;" />
    </div>
  </div>

  <!-- Spacer -->
  <div style="flex:1;"></div>

  <!-- Action Buttons -->
  <div class="navbar-actions">

    <!-- Dark Mode -->
    <button class="nav-btn dark-toggle" title="الوضع الداكن">
      🌙
    </button>

    <!-- Notifications -->
    <div style="position:relative;" id="notificationContainer">
      <button class="nav-btn" id="notificationBtn" title="الإشعارات">
        🔔
        <?php if ($nb_unread_notifications > 0): ?>
          <span class="badge" id="notificationBadge"><?= $nb_unread_notifications ?></span>
        <?php endif; ?>
      </button>

      <!-- Notification Dropdown -->
      <div id="notif-dropdown-custom" style="display:none; position:absolute; top:calc(100% + 10px); left:-160px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.15); width:320px; z-index:9999;">
        <div style="padding:14px 20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
          <span style="font-size:15px; font-weight:700; color:var(--text-primary);">الإشعارات</span>
        </div>
        <div style="max-height:300px; overflow-y:auto;">
          <?php if (empty($nb_latest_notifs)): ?>
            <div style="padding:20px; text-align:center; color:var(--text-secondary); font-size:13px;">لا توجد إشعارات حالياً</div>
          <?php else: ?>
            <?php foreach ($nb_latest_notifs as $n): ?>
              <a href="<?= e($n['link'] ? '../pages/read-notification.php?id=' . $n['id'] : '../pages/notifications.php') ?>" style="display:flex; align-items:flex-start; gap:12px; padding:12px 16px; border-bottom:1px solid var(--border-color); text-decoration:none; transition:background 0.2s; <?= !$n['is_read'] ? 'background:rgba(99,102,241,0.05);' : '' ?>" onmouseover="this.style.background='var(--bg-main)'" onmouseout="this.style.background='<?= !$n['is_read'] ? 'rgba(99,102,241,0.05)' : 'transparent' ?>'">
                <div style="width:36px; height:36px; border-radius:10px; background:rgba(99,102,241,0.1); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;">
                  <?= htmlspecialchars($n['icon'] ?: '🔔') ?>
                </div>
                <div style="flex:1;">
                  <div style="font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:4px; white-space:normal; line-height:1.4; word-break:break-word;"><?= htmlspecialchars($n['title']) ?></div>
                  <div style="font-size:12px; color:var(--text-secondary); line-height:1.5; margin-bottom:6px; white-space:normal; word-break:break-word;"><?= htmlspecialchars($n['message']) ?></div>
                  <div style="font-size:10px; color:var(--text-secondary);"><?= date('Y/m/d h:i A', strtotime($n['created_at'])) ?></div>
                </div>
                <?php if (!$n['is_read']): ?>
                  <div style="width:8px; height:8px; border-radius:50%; background:var(--danger); margin-top:6px; flex-shrink:0;"></div>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div style="padding:12px 20px; text-align:center; border-top:1px solid var(--border-color);">
          <a href="../pages/notifications.php" style="font-size:14px; color:var(--primary); font-weight:600; text-decoration:none;">عرض كل الإشعارات</a>
        </div>
      </div>
    </div>

    <!-- Divider -->
    <div style="width:1px;height:28px;background:var(--border-color);margin:0 4px;"></div>

    <!-- Admin Profile -->
    <div class="admin-profile dropdown" id="profileDropdown">
      <div class="admin-avatar" style="overflow:hidden;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;"><?= $user_avatar_html ?></div>
      <div class="admin-info">
        <div class="admin-name"><?= $user_name ?></div>
        <div class="admin-role"><?= $user_role ?></div>
      </div>
      <span style="color:var(--text-secondary);font-size:12px;margin-right:4px;">▾</span>

      <!-- Profile Dropdown -->
      <div class="dropdown-menu" style="right:0;left:auto;min-width:200px;">
        <div style="padding:16px;border-bottom:1px solid var(--border-color);">
          <p style="font-size:14px;font-weight:700;color:var(--text-primary);"><?= $user_name ?></p>
          <p style="font-size:12px;color:var(--text-secondary);"><?= $user_email ?></p>
        </div>
        <div style="padding:8px;">
          <a href="../pages/settings.php?tab=profile" style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:var(--text-primary);text-decoration:none;font-size:14px;transition:background 0.2s;" onmouseover="this.style.background='var(--bg-main)'" onmouseout="this.style.background='transparent'">
            <span>👤</span> الملف الشخصي
          </a>
          <a href="../pages/settings.php" style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:var(--text-primary);text-decoration:none;font-size:14px;transition:background 0.2s;" onmouseover="this.style.background='var(--bg-main)'" onmouseout="this.style.background='transparent'">
            <span>⚙️</span> الإعدادات
          </a>
          <a href="<?= rootUrl() ?>/logout.php" style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:#ef4444;text-decoration:none;font-size:14px;transition:background 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.05)'" onmouseout="this.style.background='transparent'">
            <span>🚪</span> تسجيل الخروج
          </a>
        </div>
      </div>
    </div>

  </div>

</nav>

<script>
// Profile dropdown toggle
document.getElementById('profileDropdown')?.addEventListener('click', function(e) {
  e.stopPropagation();
  this.classList.toggle('open');
  document.getElementById('notif-dropdown-custom').style.display = 'none';
});

// Notifications Custom Toggle
document.getElementById('notificationBtn')?.addEventListener('click', function(e) {
  e.stopPropagation();
  const nd = document.getElementById('notif-dropdown-custom');
  if (nd) {
    nd.style.display = (nd.style.display === 'none' || nd.style.display === '') ? 'block' : 'none';
  }
  document.getElementById('profileDropdown')?.classList.remove('open');
});
document.getElementById('notif-dropdown-custom')?.addEventListener('click', function(e) {
  e.stopPropagation();
});

// Close all on outside click
document.addEventListener('click', () => {
  document.getElementById('profileDropdown')?.classList.remove('open');
  const nd = document.getElementById('notif-dropdown-custom');
  if (nd) nd.style.display = 'none';
});
</script>
