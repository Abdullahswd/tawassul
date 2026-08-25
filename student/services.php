<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db = db();

// جلب جميع الخدمات المفعلة من قاعدة البيانات مع بيانات الأب والمستوى
$services_stmt = $db->query("
    SELECT s.id, s.parent_id, s.name, s.icon, s.description, s.price, s.level, s.is_active,
           (SELECT name FROM services p WHERE p.id = s.parent_id) AS parent_name
    FROM services s
    WHERE s.is_active = 1
    ORDER BY s.level ASC, s.sort_order ASC, s.id ASC
");
$all_services = $services_stmt->fetchAll();

if (!is_array($all_services)) {
    $all_services = [];
}

$all_services_json = json_encode($all_services, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>الخدمات الأكاديمية - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    * { font-family: 'Tajawal', sans-serif; }
    
    .cat-tab {
      background: var(--bg-card);
      color: var(--text-secondary);
      border: 1px solid var(--border-color);
      padding: 8px 18px;
      border-radius: 40px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    .cat-tab:hover {
      background: var(--border-color);
      color: var(--text-primary);
    }
    .cat-tab.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
      box-shadow: 0 4px 12px rgba(79,70,229,0.25);
    }

    .service-card {
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: 20px;
      padding: 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.25s ease;
    }
    .service-card:hover {
      transform: translateY(-4px);
      border-color: var(--primary);
      box-shadow: 0 12px 25px rgba(0,0,0,0.06);
    }

    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
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
        <a href="services.php" class="nav-item active">
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
          <div class="h3">📦 كتل الخدمات الأكاديمية</div>
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
      <div class="content-wrap" style="padding:24px;">
        
        <!-- أدوات الفلترة والبحث -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
          <!-- شريط البحث السريع -->
          <div class="w-full md:w-80 relative">
            <input type="text" id="searchInput" placeholder="🔍 بحث عن خدمة أكاديمية..." 
                   class="w-full px-4 py-2 rounded-xl border border-slate-200 text-sm font-medium focus:outline-none focus:border-indigo-600 transition" oninput="renderStudentServices()" />
          </div>

          <!-- تبويبات الأقسام الرئيسية -->
          <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto pb-2 md:pb-0 no-scrollbar" id="categoryTabs">
            <button class="cat-tab active" data-cat-id="all" onclick="selectTab('all', this)">
              🌐 الكل (جميع الخدمات)
            </button>
          </div>
        </div>

        <!-- شبكة عرض الخدمات (المستوى الثالث فقط) -->
        <div id="studentServicesGrid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:24px"></div>

      </div>
    </main>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    const allServices = <?= $all_services_json ?>;
    let selectedCatId = 'all';

    function initTabs() {
      const container = document.getElementById('categoryTabs');
      if (!container || !allServices.length) return;

      const l1Categories = allServices.filter(s => s.level === 1);
      l1Categories.forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'cat-tab';
        btn.onclick = function() { selectTab(cat.id, this); };
        btn.innerHTML = `${cat.icon || '📌'} ${cat.name}`;
        container.appendChild(btn);
      });
    }

    function selectTab(catId, btnEl) {
      selectedCatId = catId;
      document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
      btnEl.classList.add('active');
      renderStudentServices();
    }

    function getL1CategoryId(service) {
      if (!service.parent_id) return null;
      const parent = allServices.find(s => s.id === service.parent_id);
      if (!parent) return null;
      if (parent.level === 1) return parent.id;
      const grandParent = allServices.find(s => s.id === parent.parent_id);
      return grandParent ? grandParent.id : parent.id;
    }

    function renderStudentServices() {
      const grid = document.getElementById('studentServicesGrid');
      const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
      if (!grid) return;

      // تصفية الخدمات ذات المستوى الثالث فقط
      let items = allServices.filter(s => s.level === 3);

      // في حالة خلو المستوى 3، إرجاع باقي الخدمات للاتساق
      if (!items.length && allServices.length) {
        items = allServices.filter(s => s.level !== 1);
      }

      // التصفية حسب القسم الرئيسي
      if (selectedCatId !== 'all') {
        const catIdNum = parseInt(selectedCatId);
        items = items.filter(s => getL1CategoryId(s) === catIdNum || s.parent_id === catIdNum || s.id === catIdNum);
      }

      // التصفية بحسب البحث
      if (searchTerm) {
        items = items.filter(s => {
          const nameMatch = (s.name || '').toLowerCase().includes(searchTerm);
          const descMatch = (s.description || '').toLowerCase().includes(searchTerm);
          const parentMatch = (s.parent_name || '').toLowerCase().includes(searchTerm);
          return nameMatch || descMatch || parentMatch;
        });
      }

      if (!items.length) {
        grid.innerHTML = `
          <div style="grid-column:1/-1;background:white;border-radius:16px;padding:40px;text-align:center;color:#64748b;border:1px border-slate-200;">
            <div style="font-size:48px;margin-bottom:12px">🔍</div>
            <h3 style="font-weight:700;font-size:16px;color:#0f172a">لا توجد خدمات تطابق خياراتك</h3>
            <p style="font-size:13px;margin-top:4px">يمكنك تغيير القسم المحدد أو مسح نص البحث.</p>
          </div>
        `;
        return;
      }

      grid.innerHTML = items.map((s, idx) => `
        <div class="service-card anim-fade-up" style="animation-delay:${idx * 0.05}s">
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
              <div style="font-size:36px">${s.icon || '📚'}</div>
              ${s.parent_name ? `<span style="font-size:11px;font-weight:700;background:#eef2ff;color:#4f46e5;padding:4px 10px;border-radius:20px">${s.parent_name}</span>` : ''}
            </div>
            <h3 style="font-weight:800;font-size:18px;color:#0f172a;margin-bottom:8px">${s.name}</h3>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid #f1f5f9;padding-top:16px">
            <div>
              <div style="font-size:11px;color:#94a3b8;font-weight:600">السعر المعين</div>
              <div style="font-weight:900;color:#059669;font-size:18px">${Number(s.price).toFixed(2)} <span style="font-size:12px;font-weight:700">ر.س</span></div>
            </div>
            <a href="create-order.php?service_id=${s.id}" class="btn btn-primary" style="padding:8px 18px;border-radius:12px;font-weight:700">اطلب الآن 🚀</a>
          </div>
        </div>
      `).join('');
    }

    document.addEventListener('DOMContentLoaded', () => {
      initTabs();
      renderStudentServices();
    });
  </script>
</body>
</html>
