<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db = db();

// Compute stats
$total_orders = (int) $db->query("SELECT COUNT(*) FROM orders WHERE student_id = " . $user['id'])->fetchColumn();
$active_orders = (int) $db->query("SELECT COUNT(*) FROM orders WHERE student_id = " . $user['id'] . " AND status IN ('new', 'pending_assignment', 'assigned', 'accepted', 'in_progress', 'revision')")->fetchColumn();
$completed_orders = (int) $db->query("SELECT COUNT(*) FROM orders WHERE student_id = " . $user['id'] . " AND status = 'completed'")->fetchColumn();

// Fetch last 3 orders
$orders_stmt = $db->prepare("
    SELECT o.*, s.name AS service_name, s.icon AS service_icon
    FROM orders o
    JOIN services s ON o.service_id = s.id
    WHERE o.student_id = ?
    ORDER BY o.created_at DESC
    LIMIT 3
");
$orders_stmt->execute([$user['id']]);
$recent_orders = $orders_stmt->fetchAll();

// Fetch last 3 notifications
$notifications = getNotifications($user['id'], 'student');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>لوحة الطالب - تواصل</title>
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
        <a href="student-dashboard.php" class="nav-item active">
          <span class="icon">📊</span>
          <span>لوحة المعلومات</span>
        </a>
        <a href="services.php" class="nav-item">
          <span class="icon">📦</span>
          <span>الخدمات الأكاديمية</span>
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
          <div class="h3">لوحة التحكم</div>
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
        
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:32px" class="anim-fade-up">
          <div>
            <h1 class="h1" style="margin-bottom:8px">مرحباً بك مجدداً، <?= e($user['name']) ?>! 👋</h1>
            <p class="text-body">إليك ملخص سريع لحالة طلباتك ونشاطك على المنصة.</p>
          </div>
          <div>
            <a href="create-order.php" class="btn btn-primary">➕ طلب خدمة جديدة</a>
          </div>
        </div>

        <!-- Stats Grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:24px;margin-bottom:40px">
          <div class="card anim-fade-up" style="animation-delay:0.1s;display:flex;align-items:center;gap:20px;border-bottom:4px solid var(--primary)">
            <div style="width:60px;height:60px;border-radius:16px;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:28px">📋</div>
            <div>
              <div class="text-body" style="font-weight:700">إجمالي الطلبات</div>
              <div class="h1"><?= $total_orders ?></div>
            </div>
          </div>
          
          <div class="card anim-fade-up" style="animation-delay:0.2s;display:flex;align-items:center;gap:20px;border-bottom:4px solid var(--warning)">
            <div style="width:60px;height:60px;border-radius:16px;background:rgba(245,158,11,0.1);color:var(--warning);display:flex;align-items:center;justify-content:center;font-size:28px">⏳</div>
            <div>
              <div class="text-body" style="font-weight:700">طلبات قيد التنفيذ</div>
              <div class="h1"><?= $active_orders ?></div>
            </div>
          </div>
          
          <div class="card anim-fade-up" style="animation-delay:0.3s;display:flex;align-items:center;gap:20px;border-bottom:4px solid var(--success)">
            <div style="width:60px;height:60px;border-radius:16px;background:rgba(16,185,129,0.1);color:var(--success);display:flex;align-items:center;justify-content:center;font-size:28px">✅</div>
            <div>
              <div class="text-body" style="font-weight:700">الطلبات المكتملة</div>
              <div class="h1"><?= $completed_orders ?></div>
            </div>
          </div>
        </div>

        <!-- Recent Orders & Activities -->
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
          
          <!-- Recent Orders -->
          <div class="card anim-fade-up" style="animation-delay:0.4s;padding:0;overflow:hidden">
            <div style="padding:24px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center">
              <h2 class="h2">آخر الطلبات</h2>
              <a href="orders.php" class="btn btn-outline" style="padding:8px 16px;font-size:13px">عرض الكل ←</a>
            </div>
            <div style="overflow-x:auto">
              <table style="width:100%;border-collapse:collapse;text-align:right">
                <thead>
                  <tr>
                    <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">رقم الطلب</th>
                    <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">الخدمة</th>
                    <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">التاريخ</th>
                    <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">الحالة</th>
                    <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">إجراء</th>
                  </tr>
                </thead>
                <tbody id="recentOrdersBody">
                  <?php if (empty($recent_orders)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:32px">ليس لديك أي طلبات حالياً.</td></tr>
                  <?php else: ?>
                    <?php foreach ($recent_orders as $o): 
                      $badge = orderStatusLabel($o['status']);
                    ?>
                      <tr>
                        <td style="padding:16px; border-bottom:1px solid var(--border-color); font-weight:700"><?= e($o['order_number']) ?></td>
                        <td style="padding:16px; border-bottom:1px solid var(--border-color)"><?= e($o['service_icon']) ?> <?= e($o['service_name']) ?></td>
                        <td style="padding:16px; border-bottom:1px solid var(--border-color); color:var(--text-secondary)"><?= formatDate($o['created_at']) ?></td>
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

          <!-- Quick Actions / Notifications -->
          <div style="display:flex;flex-direction:column;gap:24px">
            
            <div class="card anim-fade-up" style="animation-delay:0.5s;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;border:none">
              <h3 style="font-size:20px;font-weight:800;margin-bottom:16px">تحتاج مساعدة في بحثك؟</h3>
              <p style="font-size:14px;opacity:0.9;margin-bottom:24px">اكتشف خدماتنا الأكاديمية المتنوعة وابدأ بطلب خدمتك الآن.</p>
              <a href="create-order.php" class="btn" style="background:white;color:var(--primary);width:100%">🚀 اطلب خدمة جديدة</a>
            </div>

            <div class="card anim-fade-up" style="animation-delay:0.6s">
              <h3 class="h3" style="margin-bottom:16px">آخر الإشعارات</h3>
              <div style="display:flex;flex-direction:column;gap:12px">
                <?php if (empty($notifications)): ?>
                  <div style="font-size:13px;color:var(--text-secondary);text-align:center;padding:16px">لا توجد إشعارات جديدة.</div>
                <?php else: ?>
                  <?php foreach ($notifications as $n): ?>
                    <div style="display:flex;gap:12px;align-items:flex-start">
                      <div style="font-size:18px;flex-shrink:0"><?= e($n['icon'] ?: '🔔') ?></div>
                      <div>
                        <div style="font-size:14px;font-weight:700"><?= e($n['title']) ?></div>
                        <div style="font-size:13px;color:var(--text-secondary)"><?= e($n['message']) ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

          </div>

        </div>

      </div>
    </main>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
