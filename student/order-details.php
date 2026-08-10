<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$order_id = (int)($_GET['id'] ?? 0);

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

$order = getOrderById($order_id);

if (!$order || $order['student_id'] !== $user['id']) {
    header('Location: orders.php');
    exit;
}

$badge = orderStatusLabel($order['status']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تفاصيل الطلب - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* Timeline styles */
    .timeline {
      display: flex;
      flex-direction: column;
      position: relative;
      padding-right: 20px;
    }
    .timeline::before {
      content: "";
      position: absolute;
      top: 0; bottom: 0; right: 0;
      width: 2px;
      background: var(--border-color);
    }
    .timeline-item {
      position: relative;
      margin-bottom: 32px;
      padding-right: 24px;
    }
    .timeline-item:last-child { margin-bottom: 0; }
    .timeline-item::before {
      content: "";
      position: absolute;
      right: -25px; top: 0px;
      width: 12px; height: 12px;
      border-radius: 50%;
      background: var(--bg-card);
      border: 3px solid var(--border-color);
      z-index: 1;
    }
    .timeline-item.active::before {
      border-color: var(--primary);
      background: var(--primary);
      box-shadow: 0 0 0 4px var(--primary-light);
    }
    .timeline-item.done::before {
      border-color: var(--success);
      background: var(--success);
    }
    .ti-title { font-size: 15px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; }
    .ti-desc { font-size: 13px; color: var(--text-secondary); }
    .ti-date { display: inline-block; font-size: 11px; padding: 2px 8px; background: var(--bg-hover); border-radius: 4px; margin-top: 8px; font-weight: 700; }
  </style>
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
          <div class="h3">تفاصيل الطلب <?= e($order['order_number']) ?></div>
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
      <div class="content-wrap" style="max-width:1200px;margin:0 auto">
        
        <div style="margin-bottom:24px;">
          <a href="orders.php" style="color:var(--text-secondary);font-size:14px">← العودة للطلبات</a>
        </div>

        <div class="card" style="padding:24px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
          <div>
            <h1 class="h1" style="margin-bottom:8px"><?= e($order['service_icon']) ?> <?= e($order['service_name']) ?></h1>
            <p class="text-body" style="font-weight:700">تخصص: <?= e($order['specialty']) ?></p>
          </div>
          <div style="text-align:left">
            <span class="badge <?= $badge['class'] ?>" style="font-size:14px;padding:8px 16px;margin-bottom:8px">
              الحالة: <?= $badge['label'] ?>
            </span>
            <div style="font-size:12px;color:var(--text-secondary)">موعد التسليم المرغوب: <?= formatDate($order['deadline']) ?></div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 340px;gap:24px">
          
          <!-- Left Column -->
          <div style="display:flex;flex-direction:column;gap:24px">
            
            <!-- Details -->
            <div class="card">
              <h2 class="h2" style="margin-bottom:20px">تفاصيل الطلب</h2>
              
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:24px">
                <div>
                  <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px">المستوى الأكاديمي</div>
                  <div style="font-weight:700"><?= e($order['academic_level']) ?></div>
                </div>
                <div>
                  <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px">لغة العمل</div>
                  <div style="font-weight:700"><?= e($order['language']) ?></div>
                </div>
                <div>
                  <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px">الباقة المحددة</div>
                  <div style="font-weight:700"><?= e($order['package_name'] ?: 'خدمة مخصصة') ?></div>
                </div>
              </div>

              <div>
                <div style="font-size:12px;color:var(--text-secondary);margin-bottom:8px">الوصف والملاحظات</div>
                <div style="background:var(--bg-body);padding:16px;border-radius:var(--radius-sm);border:1px solid var(--border-color);line-height:1.7;font-size:14px">
                  <?= nl2br(e($order['description'])) ?>
                </div>
              </div>
            </div>

            <!-- Attachments -->
            <div class="card">
              <h2 class="h2" style="margin-bottom:20px">الملفات المرفوعة</h2>
              <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">لا توجد ملفات مرفوعة حالياً للطلب.</p>

              <!-- Draggable file upload UI -->
              <div style="border:2px dashed var(--border-color);border-radius:var(--radius-sm);padding:24px;text-align:center;background:var(--bg-body);margin-top:20px;cursor:pointer">
                <h4 style="font-weight:700;margin-bottom:8px;color:var(--primary)">إضافة ملفات جديدة ➕</h4>
                <p style="font-size:12px;color:var(--text-secondary)">اسحب وأفلت الملفات هنا أو اضغط للاختيار</p>
              </div>
            </div>

          </div>

          <!-- Right Column -->
          <div style="display:flex;flex-direction:column;gap:24px">
            
            <!-- Academic Profile -->
            <div class="card" style="text-align:center;padding:32px 24px">
              <?php if ($order['academic_id']): ?>
                <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg, var(--secondary), var(--primary));color:white;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;margin:0 auto 16px">
                  <?= e(mb_substr($order['academic_name'], 0, 1, 'UTF-8')) ?>
                </div>
                <h3 class="h3" style="margin-bottom:4px"><?= e($order['academic_name']) ?></h3>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px">تم تعيين الأكاديمي للطلب.</p>
                <a href="chat.php?order_id=<?= $order['id'] ?>" class="btn btn-primary" style="width:100%">💬 تواصل مع الأكاديمي</a>
              <?php else: ?>
                <div style="width:80px;height:80px;border-radius:50%;background:var(--bg-body);border:1.5px dashed var(--border-color);display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 16px">⏳</div>
                <h3 class="h3" style="margin-bottom:4px;color:var(--text-secondary)">قيد التعيين</h3>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;line-height:1.5">يقوم فريق الدعم بمراجعة الطلب لربطك بأفضل أكاديمي متخصص.</p>
                <button class="btn btn-outline" style="width:100%" disabled>بانتظار الأكاديمي...</button>
              <?php endif; ?>
            </div>

            <!-- Timeline -->
            <div class="card" style="padding:24px">
              <h3 class="h3" style="margin-bottom:24px">متابعة سير الطلب</h3>
              
              <div class="timeline">
                <div class="timeline-item done">
                  <div class="ti-title">تم تقديم الطلب</div>
                  <div class="ti-desc">تم تسجيل طلبك بنجاح على المنصة.</div>
                  <div class="ti-date"><?= formatDate($order['created_at']) ?></div>
                </div>
                <div class="timeline-item <?= in_array($order['status'], ['assigned', 'accepted', 'in_progress', 'revision', 'completed']) ? 'done' : (($order['status'] === 'pending_assignment') ? 'active' : '') ?>">
                  <div class="ti-title">مراجعة الإدارة</div>
                  <div class="ti-desc">
                    <?= ($order['status'] === 'pending_assignment') ? 'جاري مراجعة الطلب وتعيين الأكاديمي المناسب.' : 'تمت مراجعة الطلب من قِبل الإدارة.' ?>
                  </div>
                </div>
                <div class="timeline-item <?= in_array($order['status'], ['accepted', 'in_progress', 'revision', 'completed']) ? 'done' : (($order['status'] === 'assigned') ? 'active' : '') ?>">
                  <div class="ti-title">الربط والتعيين</div>
                  <div class="ti-desc">
                    <?= $order['academic_id'] ? 'تم تعيين الأكاديمي وقبول العمل.' : (($order['status'] === 'assigned') ? 'تم إرسال الطلب للأكاديمي، بانتظار القبول.' : 'جاري البحث عن أكاديمي متخصص.') ?>
                  </div>
                </div>
                <div class="timeline-item <?= ($order['status'] === 'completed') ? 'done' : (in_array($order['status'], ['in_progress', 'revision']) ? 'active' : '') ?>">
                  <div class="ti-title">قيد التنفيذ</div>
                  <div class="ti-desc">يقوم الأكاديمي حالياً بالعمل على الملفات.</div>
                </div>
                <div class="timeline-item <?= ($order['status'] === 'completed') ? 'done' : '' ?>">
                  <div class="ti-title">اكتمال وتسليم</div>
                  <div class="ti-desc">مراجعة الملفات النهائية وتنزيلها.</div>
                </div>
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
