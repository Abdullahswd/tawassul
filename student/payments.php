<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db = db();

// Fetch summary metrics
$total_paid = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = " . $user['id'] . " AND status = 'paid'")->fetchColumn();
$total_pending = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = " . $user['id'] . " AND status = 'pending'")->fetchColumn();

// Fetch operations
$payments = getPaymentsByStudent($user['id']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>المدفوعات - تواصل</title>
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
        <a href="orders.php" class="nav-item">
          <span class="icon">📋</span>
          <span>طلباتي</span>
        </a>
        <a href="chat.php" class="nav-item">
          <span class="icon">💬</span>
          <span>المحادثات</span>
        </a>
        <a href="payments.php" class="nav-item active">
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
          <div class="h3">سجل المدفوعات</div>
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
      <div class="content-wrap" style="max-width:1100px;margin:0 auto">
        
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:32px">
          <div>
            <h1 class="h1" style="margin-bottom:8px">الفواتير والمدفوعات</h1>
            <p class="text-body">تابع جميع عمليات الدفع الخاصة بطلباتك، وحمل الفواتير للرجوع إليها.</p>
          </div>
        </div>

        <!-- Quick Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:24px;margin-bottom:32px">
          <div class="card" style="display:flex;flex-direction:column;justify-content:center">
            <div style="font-size:14px;color:var(--text-secondary);margin-bottom:8px;font-weight:700">إجمالي المدفوعات السابقة</div>
            <div style="font-size:32px;font-weight:900;color:var(--text-primary)"><?= formatMoney($total_paid) ?></div>
          </div>
          <div class="card" style="display:flex;flex-direction:column;justify-content:center;border-color:var(--warning)">
            <div style="font-size:14px;color:var(--warning);margin-bottom:8px;font-weight:700">مبالغ بانتظار الدفع</div>
            <div style="font-size:32px;font-weight:900;color:var(--text-primary)"><?= formatMoney($total_pending) ?></div>
          </div>
        </div>

        <!-- Payments Table -->
        <div class="card" style="padding:0;overflow:hidden">
          <div style="padding:20px 24px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center">
            <h3 class="h3">سجل العمليات المالية</h3>
          </div>
          <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:right">
              <thead>
                <tr>
                  <th style="padding:16px 24px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">رقم الفاتورة</th>
                  <th style="padding:16px 24px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">رقم الطلب المرتبط</th>
                  <th style="padding:16px 24px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">التاريخ</th>
                  <th style="padding:16px 24px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">المبلغ</th>
                  <th style="padding:16px 24px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">طريقة الدفع</th>
                  <th style="padding:16px 24px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">الحالة</th>
                  <th style="padding:16px 24px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr>
                    <td colspan="7" style="text-align:center;padding:32px;color:var(--text-secondary)">
                      لا توجد عمليات دفع مسجلة حالياً.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($payments as $p): 
                    $badge = paymentStatusLabel($p['status']);
                    $inv_no = 'INV-' . str_pad($p['id'], 6, '0', STR_PAD_LEFT);
                  ?>
                    <tr>
                      <td style="padding:16px 24px; border-bottom:1px solid var(--border-color); font-family:monospace;font-weight:700"><?= $inv_no ?></td>
                      <td style="padding:16px 24px; border-bottom:1px solid var(--border-color); color:var(--primary); font-weight:700">
                        <a href="order-details.php?id=<?= $p['order_id'] ?>"><?= e($p['order_number']) ?></a>
                      </td>
                      <td style="padding:16px 24px; border-bottom:1px solid var(--border-color); color:var(--text-secondary)"><?= formatDate($p['created_at']) ?></td>
                      <td style="padding:16px 24px; border-bottom:1px solid var(--border-color); font-weight:700"><?= formatMoney($p['amount']) ?></td>
                      <td style="padding:16px 24px; border-bottom:1px solid var(--border-color); color:var(--text-secondary)"><?= $p['method'] === 'credit_card' ? 'بطاقة ائتمانية' : e($p['method']) ?></td>
                      <td style="padding:16px 24px; border-bottom:1px solid var(--border-color)">
                        <span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                      </td>
                      <td style="padding:16px 24px; border-bottom:1px solid var(--border-color)">
                        <?php if ($p['status'] === 'pending'): ?>
                          <button class="btn btn-primary" style="padding:6px 12px;font-size:12px" onclick="alert('محاكاة بوابة الدفع: تم الدفع بنجاح!'); window.location.reload();">الدفع الآن</button>
                        <?php else: ?>
                          <button class="btn btn-outline" style="padding:6px 12px;font-size:12px" onclick="window.print()">الفاتورة ⬇</button>
                        <?php endif; ?>
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
