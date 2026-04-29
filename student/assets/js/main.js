/**
 * ============================================
 * Student Module - Main JS
 * ============================================
 */

const STUDENT_DATA = {
  profile: {
    name: 'أحمد الزهراني',
    initials: 'أ',
    email: 'ahmad@student.com',
    balance: '0'
  },
  
  stats: {
    totalOrders: 12,
    activeOrders: 2,
    completedOrders: 10
  },

  orders: [
    { id: '#ORD-1045', service: 'التحليل الإحصائي', date: '2026-04-18', status: 'progress', statusText: 'قيد التنفيذ', amount: 450, color: 'warning' },
    { id: '#ORD-1042', service: 'تصميم الاستبيانات', date: '2026-04-10', status: 'completed', statusText: 'مكتمل', amount: 200, color: 'success' },
    { id: '#ORD-1049', service: 'التدقيق اللغوي', date: '2026-04-20', status: 'new', statusText: 'جديد', amount: 150, color: 'info' }
  ],

  services: [
    { id: 1, name: 'التأسيس الأكاديمي', desc: 'بناء الهيكل الأساسي والمقترح البحثي وتطوير الأفكار الأولية باحترافية.', icon: '🏗️', price: 150 },
    { id: 2, name: 'التحليل الإحصائي', desc: 'تحليل البيانات المعقدة باستخدام SPSS ومعالجة نتائج الاستبيانات بوضوح.', icon: '📊', price: 400 },
    { id: 3, name: 'التدقيق والتحرير', desc: 'مراجعة لغوية شاملة وإصلاح الأخطاء وتنسيق الملفات وفقاً للأدلة المعتمدة.', icon: '✏️', price: 200 },
    { id: 4, name: 'الترجمة الأكاديمية', desc: 'ترجمة صياغية دقيقة للمراجع والنصوص بأسلوب علمي رصين.', icon: '🌐', price: 100 },
    { id: 5, name: 'الفحص النهائي', desc: 'فحص نسبة الاقتباس (Turnitin) وإعداد تقرير شامل قبل التسليم.', icon: '🔍', price: 50 },
    { id: 6, name: 'تصميم العروض', desc: 'تصميم عروض تقديمية (PowerPoint) مميزة للمناقشات العلمية.', icon: '📽️', price: 250 },
  ]
};

document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initDarkMode();
  injectComponents();

  // Page Specific Init
  const path = window.location.pathname;
  if (path.includes('student-dashboard.html')) initDashboard();
  if (path.includes('services.html')) initServices();
  if (path.includes('create-order.html')) initCreateOrder();
});

/* =======================================
   Layout & Components
   ======================================= */
function initSidebar() {
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('mobileOverlay');

  if (menuToggle && sidebar && overlay) {
    menuToggle.addEventListener('click', () => {
      sidebar.classList.add('open');
      overlay.classList.add('active');
    });

    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    });
  }

  // Active Link
  const links = document.querySelectorAll('.nav-item');
  const currentUrl = window.location.href;
  links.forEach(link => {
    if (currentUrl.includes(link.getAttribute('href'))) {
      link.classList.add('active');
    }
  });
}

function initDarkMode() {
  const isDark = localStorage.getItem('theme') === 'dark';
  if (isDark) document.documentElement.classList.add('dark');
  
  const toggleBtns = document.querySelectorAll('.dark-toggle');
  toggleBtns.forEach(btn => {
    btn.innerHTML = isDark ? '☀️' : '🌙';
    btn.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      const darkNow = document.documentElement.classList.contains('dark');
      localStorage.setItem('theme', darkNow ? 'dark' : 'light');
      toggleBtns.forEach(b => b.innerHTML = darkNow ? '☀️' : '🌙');
    });
  });
}

function injectComponents() {
  // If we had actual HTML component injection, it would happen here.
  // For static HTML, we ensure dynamic user data is filled.
  const nameEls = document.querySelectorAll('.user-name-fill');
  const initialsEls = document.querySelectorAll('.user-initials-fill');
  
  nameEls.forEach(el => el.textContent = STUDENT_DATA.profile.name);
  initialsEls.forEach(el => el.textContent = STUDENT_DATA.profile.initials);
}

/* =======================================
   Page: Dashboard
   ======================================= */
function initDashboard() {
  const tbody = document.getElementById('recentOrdersBody');
  if(!tbody) return;

  // Animate stats
  document.getElementById('statTotal').textContent = STUDENT_DATA.stats.totalOrders;
  document.getElementById('statActive').textContent = STUDENT_DATA.stats.activeOrders;
  document.getElementById('statCompleted').textContent = STUDENT_DATA.stats.completedOrders;

  // Render recent orders
  tbody.innerHTML = STUDENT_DATA.orders.slice(0, 3).map(o => `
    <tr>
      <td style="padding:16px; border-bottom:1px solid var(--border-color); font-weight:700">${o.id}</td>
      <td style="padding:16px; border-bottom:1px solid var(--border-color)">${o.service}</td>
      <td style="padding:16px; border-bottom:1px solid var(--border-color); color:var(--text-secondary)">${o.date}</td>
      <td style="padding:16px; border-bottom:1px solid var(--border-color)">
        <span class="status-badge status-${o.status}">
          <span style="width:8px;height:8px;border-radius:50%;background:currentColor"></span>
          ${o.statusText}
        </span>
      </td>
      <td style="padding:16px; border-bottom:1px solid var(--border-color)">
        <a href="order-details.html?id=${encodeURIComponent(o.id)}" class="btn btn-outline" style="padding:6px 12px;font-size:13px">التفاصيل</a>
      </td>
    </tr>
  `).join('');
}

/* =======================================
   Page: Services
   ======================================= */
function initServices() {
  const grid = document.getElementById('servicesGrid');
  if(!grid) return;

  grid.innerHTML = STUDENT_DATA.services.map((s, idx) => `
    <div class="card anim-fade-up" style="animation-delay:${idx*0.05}s; display:flex; flex-direction:column;">
      <div style="font-size:40px;margin-bottom:16px">${s.icon}</div>
      <h3 class="h3" style="margin-bottom:8px">${s.name}</h3>
      <p class="text-body" style="flex-grow:1;margin-bottom:24px">${s.desc}</p>
      
      <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border-color);padding-top:16px">
        <div>
          <div style="font-size:12px;color:var(--text-secondary)">تبدأ من</div>
          <div style="font-weight:800;color:var(--primary);font-size:18px">${s.price} ر.س</div>
        </div>
        <a href="create-order.html?sid=${s.id}" class="btn btn-primary">اطلب الآن</a>
      </div>
    </div>
  `).join('');
}

/* =======================================
   Page: Create Order (Multi-Step Form)
   ======================================= */
function initCreateOrder() {
  const steps = document.querySelectorAll('.form-step');
  const indicators = document.querySelectorAll('.step-indicator');
  const nextBtn = document.getElementById('nextBtn');
  const prevBtn = document.getElementById('prevBtn');
  const submitBtn = document.getElementById('submitBtn');
  const srvSelect = document.getElementById('serviceSelect');
  const basePriceEl = document.getElementById('basePrice');
  const totalPriceEl = document.getElementById('totalPrice');
  
  if(!steps.length) return;

  let currentStep = 0;

  // Populate Services Dropdown
  if (srvSelect) {
    srvSelect.innerHTML = '<option value="">اختر الخدمة المطلوبة...</option>' + 
      STUDENT_DATA.services.map(s => `<option value="${s.id}" data-price="${s.price}">${s.name}</option>`).join('');
    
    // Auto-select if sid in query param
    const sid = new URLSearchParams(window.location.search).get('sid');
    if (sid) {
      srvSelect.value = sid;
      updatePricing();
    }

    srvSelect.addEventListener('change', updatePricing);
  }

  function updatePricing() {
    if (!srvSelect || !srvSelect.options[srvSelect.selectedIndex]) return;
    const price = srvSelect.options[srvSelect.selectedIndex].getAttribute('data-price');
    if(price) {
      basePriceEl.textContent = price + ' ر.س';
      totalPriceEl.textContent = price + ' ر.س';
    } else {
      basePriceEl.textContent = '0 ر.س';
      totalPriceEl.textContent = '0 ر.س';
    }
  }

  function renderStep() {
    steps.forEach((s, idx) => {
      s.style.display = (idx === currentStep) ? 'block' : 'none';
      
      // Update indicators
      const indicator = indicators[idx];
      const ci = indicator.querySelector('.circle');
      
      if (idx < currentStep) {
        indicator.classList.remove('active');
        indicator.classList.add('completed');
        ci.style.background = 'var(--primary)';
        ci.style.color = 'white';
        ci.style.borderColor = 'var(--primary)';
        ci.innerHTML = '✓';
      } else if (idx === currentStep) {
        indicator.classList.add('active');
        indicator.classList.remove('completed');
        ci.style.background = 'var(--primary)';
        ci.style.color = 'white';
        ci.style.borderColor = 'var(--primary)';
        ci.innerHTML = idx + 1;
      } else {
        indicator.classList.remove('active', 'completed');
        ci.style.background = 'var(--bg-card)';
        ci.style.color = 'var(--text-secondary)';
        ci.style.borderColor = 'var(--border-color)';
        ci.innerHTML = idx + 1;
      }
    });

    if (currentStep === 0) {
      prevBtn.style.display = 'none';
    } else {
      prevBtn.style.display = 'inline-flex';
    }

    if (currentStep === steps.length - 1) {
      nextBtn.style.display = 'none';
      submitBtn.style.display = 'inline-flex';
    } else {
      nextBtn.style.display = 'inline-flex';
      submitBtn.style.display = 'none';
    }
  }

  nextBtn?.addEventListener('click', () => {
    // Basic validation
    if (currentStep === 0 && srvSelect && !srvSelect.value) {
      alert('يرجى اختيار خدمة أولاً');
      return;
    }
    if (currentStep < steps.length - 1) {
      currentStep++;
      renderStep();
    }
  });

  prevBtn?.addEventListener('click', () => {
    if (currentStep > 0) {
      currentStep--;
      renderStep();
    }
  });

  document.getElementById('multiOrderForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    submitBtn.textContent = 'جاري الإرسال...';
    submitBtn.disabled = true;
    setTimeout(() => {
      alert('تم إنشاء الطلب بنجاح! سيتم تحويلك إلى صفحة طلباتك.');
      window.location.href = 'orders.html';
    }, 1500);
  });

  renderStep();
}
