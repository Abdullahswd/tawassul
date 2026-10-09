  <?php
  require_once __DIR__ . '/../../config/auth.php';
  require_once __DIR__ . '/../../config/functions.php';
  requireAdmin();

  $db = db();

  // Handle "Mark All as Read"
  if (isset($_POST['mark_all_read'])) {
      $db->query("UPDATE notifications SET is_read = 1 WHERE user_type = 'admin' AND is_read = 0");
      header('Location: notifications.php');
      exit;
  }

  // Handle "Mark Single as Read"
  if (isset($_POST['mark_read_id'])) {
      $id = (int)$_POST['mark_read_id'];
      $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_type = 'admin'");
      $stmt->execute([$id]);
      header('Location: notifications.php');
      exit;
  }

  // Fetch all notifications
  $notifications_stmt = $db->query("
      SELECT *
      FROM notifications
      WHERE user_type = 'admin'
      ORDER BY created_at DESC
      LIMIT 200
  ");
  $notifications = $notifications_stmt->fetchAll();

  $unread_count = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_type = 'admin' AND is_read = 0")->fetchColumn();
  ?>
  <!DOCTYPE html>
  <html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>الإشعارات - Eduroad Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>" />
    <style>
      .notif-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 16px 20px;
        display: flex;
        gap: 16px;
        align-items: flex-start;
        margin-bottom: 12px;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
      }
      .notif-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      }
      .notif-card.unread {
        background: var(--bg-main);
        border-right: 4px solid var(--primary);
      }
      .notif-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        background: rgba(99, 102, 241, 0.1);
        color: var(--primary);
        flex-shrink: 0;
      }
      .notif-content {
        flex: 1;
      }
      .notif-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 6px;
      }
      .notif-message {
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.5;
        margin-bottom: 8px;
      }
      .notif-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 11px;
        color: #94a3b8;
      }
      .notif-badge {
        position: absolute;
        top: 20px;
        left: 20px;
        width: 8px;
        height: 8px;
        background: var(--danger);
        border-radius: 50%;
        box-shadow: 0 0 0 4px rgba(239,68,68,0.1);
      }
    </style>
  </head>
  <body>
  <div class="mobile-overlay" id="mobileOverlay"></div>
  <div class="admin-layout">

    <?php include '../components/sidebar.php'; ?>

    <div class="main-content" id="mainContent">
      <?php include '../components/navbar.php'; ?>

      <div class="page-content">
        <div class="page-header animate-fadeInUp">
          <div>
            <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الإشعارات</span></div>
            <h1 class="page-header-title">سجل الإشعارات</h1>
            <p class="page-header-subtitle">جميع الإشعارات والتنبيهات الواردة للإدارة</p>
          </div>
          <div class="page-header-actions">
            <?php if ($unread_count > 0): ?>
              <form method="POST" style="margin:0">
                <input type="hidden" name="mark_all_read" value="1">
                <button type="submit" class="btn btn-primary" style="background:#6366f1">
                  ✓ تحديد الكل كمقروء
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="animate-fadeInUp delay-1" style="margin-bottom:20px; display:flex; align-items:center; gap:10px">
          <span style="font-size:14px; color:var(--text-secondary)">لديك <strong><?= $unread_count ?></strong> إشعار جديد</span>
        </div>

        <div class="notifications-list animate-fadeInUp delay-2">
          <?php if (empty($notifications)): ?>
            <div style="text-align:center; padding:60px 20px; background:var(--bg-card); border-radius:12px; border:1px dashed var(--border-color)">
              <div style="font-size:40px; margin-bottom:12px">📭</div>
              <h3 style="font-size:16px; font-weight:700; color:var(--text-primary); margin-bottom:6px">لا توجد إشعارات</h3>
              <p style="font-size:13px; color:var(--text-secondary)">صندوق الإشعارات الخاص بك فارغ حالياً.</p>
            </div>
          <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
              <div class="notif-card <?= $notif['is_read'] ? '' : 'unread' ?>">
                <?php if (!$notif['is_read']): ?>
                  <div class="notif-badge"></div>
                <?php endif; ?>
                
                <div class="notif-icon">
                  <?= e($notif['icon'] ?: '🔔') ?>
                </div>
                
                <div class="notif-content">
                  <div class="notif-title"><?= e($notif['title']) ?></div>
                  <div class="notif-message"><?= nl2br(e($notif['message'])) ?></div>
                  <div class="notif-meta">
                    <span>⌚ <?= date('Y/m/d h:i A', strtotime($notif['created_at'])) ?></span>
                    <?php if ($notif['link']): ?>
                      <span>•</span>
                      <a href="<?= e($notif['link'] ? 'read-notification.php?id=' . $notif['id'] : '#') ?>" style="color:var(--primary); font-weight:600; text-decoration:none">عرض التفاصيل ➔</a>
                    <?php endif; ?>
                  </div>
                </div>
                
                <?php if (!$notif['is_read']): ?>
                  <form method="POST" style="margin:0; align-self:center">
                    <input type="hidden" name="mark_read_id" value="<?= $notif['id'] ?>">
                    <button type="submit" class="btn btn-outline btn-sm" style="border-radius:20px; padding:4px 12px; font-size:11px">علامة مقروء</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

  <script src="../assets/js/main.js?v=<?= time() ?>"></script>
  </body>
  </html>
