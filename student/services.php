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
<?php
$extraCss = [
  '<style>
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
    .cat-tab:hover { background: var(--border-color); color: var(--text-primary); }
    .cat-tab.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(79,70,229,0.25); }
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
    .service-card:hover { transform: translateY(-4px); border-color: var(--primary); box-shadow: 0 12px 25px rgba(0,0,0,0.06); }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  </style>',
];
$pageTitle  = 'الخدمات الأكاديمية';
$activePage = 'services';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>
        
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

<?php
$extraJs = ob_start() ? '' : '';
ob_start();
?>
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
      let items = allServices.filter(s => s.level === 3);
      if (!items.length && allServices.length) items = allServices.filter(s => s.level !== 1);
      if (selectedCatId !== 'all') {
        const catIdNum = parseInt(selectedCatId);
        items = items.filter(s => getL1CategoryId(s) === catIdNum || s.parent_id === catIdNum || s.id === catIdNum);
      }
      if (searchTerm) items = items.filter(s => (s.name||'').toLowerCase().includes(searchTerm) || (s.description||'').toLowerCase().includes(searchTerm) || (s.parent_name||'').toLowerCase().includes(searchTerm));
      if (!items.length) {
        grid.innerHTML = `<div style="grid-column:1/-1;background:var(--bg-card);border-radius:16px;padding:40px;text-align:center;color:var(--text-secondary);border:1px solid var(--border-color);"><div style="font-size:48px;margin-bottom:12px">🔍</div><h3 style="font-weight:700;font-size:16px">لا توجد خدمات تطابق خياراتك</h3></div>`;
        return;
      }
      grid.innerHTML = items.map((s, idx) => `
        <div class="service-card anim-fade-up" style="animation-delay:${idx * 0.05}s">
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
              <div style="font-size:36px">${s.icon || '📚'}</div>
              ${s.parent_name ? `<span style="font-size:11px;font-weight:700;background:var(--primary-light);color:var(--primary);padding:4px 10px;border-radius:20px">${s.parent_name}</span>` : ''}
            </div>
            <h3 style="font-weight:800;font-size:18px;color:var(--text-primary);margin-bottom:8px">${s.name}</h3>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border-color);padding-top:16px">
            <div>
              <div style="font-size:11px;color:var(--text-muted);font-weight:600">السعر المعين</div>
              <div style="font-weight:900;color:var(--success);font-size:18px">${Number(s.price).toFixed(2)} <span style="font-size:12px;font-weight:700">ر.س</span></div>
            </div>
            <a href="create-order.php?service_id=${s.id}" class="btn btn-primary" style="padding:8px 18px;border-radius:12px;font-weight:700">اطلب الآن 🚀</a>
          </div>
        </div>
      `).join('');
    }
    document.addEventListener('DOMContentLoaded', () => { initTabs(); renderStudentServices(); });
  </script>
<?php
$extraJs = ob_get_clean();
require __DIR__ . '/partials/footer.php';
?>
