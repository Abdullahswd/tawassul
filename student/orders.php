<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$orders = getOrdersByStudent($user['id']);

// Simple filter logic via PHP array filters
$filter = $_GET['filter'] ?? 'all';
if ($filter === 'progress') {
    $orders = array_filter($orders, function($o) {
        return in_array($o['status'], ['new', 'pending_assignment', 'assigned', 'accepted', 'in_progress', 'revision']);
    });
} elseif ($filter === 'completed') {
    $orders = array_filter($orders, function($o) {
        return $o['status'] === 'completed';
    });
}

// Counts for filter bar
$db = db();
$cnt_all = (int) $db->query("SELECT COUNT(*) FROM orders WHERE student_id = " . $user['id'])->fetchColumn();
$cnt_progress = (int) $db->query("SELECT COUNT(*) FROM orders WHERE student_id = " . $user['id'] . " AND status IN ('new', 'pending_assignment', 'assigned', 'accepted', 'in_progress', 'revision')")->fetchColumn();
$cnt_completed = (int) $db->query("SELECT COUNT(*) FROM orders WHERE student_id = " . $user['id'] . " AND status = 'completed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>طلباتي - تواصل</title>
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
        <a href="student-dashboard.php" class="nav-item">
          <span class="icon">📊</span>
          <span>لوحة المعلومات</span>
        </a>
        <a href="services.php" class="nav-item">
          <span class="icon">📦</span>
          <span>الخدمات الأكاديمية</span>
        </a>
        <a href="packages.php" class="nav-item">
          <span class="icon">🎁</span>
          <span>الباقات المخصصة</span>
        </a>
        <a href="orders.php" class="nav-item active">
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
          <div class="h3">قائمة الطلبات</div>
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
      <div class="content-wrap">
        
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:32px">
          <div>
            <h1 class="h1" style="margin-bottom:8px">طلباتي</h1>
            <p class="text-body">إدارة ومتابعة كافة طلباتك السابقة والحالية.</p>
          </div>
          <a href="create-order.php" class="btn btn-primary">➕ طلب جديد</a>
        </div>

        <!-- Filters -->
        <div style="display:flex;gap:12px;margin-bottom:24px;overflow-x:auto;padding-bottom:8px">
          <a href="orders.php?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:40px;padding:8px 20px">الكل (<?= $cnt_all ?>)</a>
          <a href="orders.php?filter=progress" class="btn <?= $filter === 'progress' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:40px;padding:8px 20px;color:var(--text-secondary);border-color:var(--border-color)">قيد التنفيذ (<?= $cnt_progress ?>)</a>
          <a href="orders.php?filter=completed" class="btn <?= $filter === 'completed' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:40px;padding:8px 20px;color:var(--text-secondary);border-color:var(--border-color)">مكتملة (<?= $cnt_completed ?>)</a>
        </div>

        <!-- Orders Table -->
        <div class="card" style="padding:0;overflow:hidden">
          <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:right">
              <thead>
                <tr>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">رقم الطلب</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">الخدمة والتخصص</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">تاريخ الطلب</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">السعر الإجمالي</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">الحالة</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($orders)): ?>
                  <tr>
                    <td colspan="6" style="text-align:center;padding:40px;color:var(--text-secondary)">
                      لا توجد أي طلبات مطابقة للفلتر المحدد.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($orders as $o): 
                    $badge = orderStatusLabel($o['status']);
                  ?>
                    <tr>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color); font-weight:700"><?= e($o['order_number']) ?></td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color)">
                        <div style="font-weight:700;color:var(--text-primary)"><?= e($o['service_icon']) ?> <?= e($o['service_name']) ?></div>
                        <div style="font-size:12px;color:var(--text-secondary)"><?= e($o['specialty']) ?></div>
                      </td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color); color:var(--text-secondary)"><?= formatDate($o['created_at']) ?></td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color); font-weight:700; color:var(--primary)"><?= formatMoney($o['amount']) ?></td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color)">
                        <span class="badge <?= $badge['class'] ?>">
                          <?= $badge['label'] ?>
                        </span>
                      </td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color)">
                        <a href="order-details.php?id=<?= $o['id'] ?>" class="btn btn-outline" style="padding:6px 12px;font-size:13px">التفاصيل</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
