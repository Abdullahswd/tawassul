/**
 * ============================================
 * Admin Dashboard - Main JavaScript
 * Tawassul Academic Platform
 * ============================================
 */

/* ---- Mock Data ---- */
const MOCK_DATA = {
  stats: {
    students: 1248,
    academics: 86,
    orders: 342,
    revenue: 84600,
    ordersGrowth: 12.5,
    studentsGrowth: 8.3,
    academicsGrowth: 5.7,
    revenueGrowth: 22.1,
  },

  students: [
    { id: 1, name: 'أحمد محمد الزهراني', email: 'ahmed@email.com', phone: '0501234567', orders: 5, status: 'active', joined: '2024-01-15', avatar: 'أح' },
    { id: 2, name: 'سارة عبدالله الغامدي', email: 'sara@email.com', phone: '0507654321', orders: 3, status: 'active', joined: '2024-02-20', avatar: 'سع' },
    { id: 3, name: 'خالد ناصر العتيبي', email: 'khalid@email.com', phone: '0509876543', orders: 8, status: 'suspended', joined: '2024-03-10', avatar: 'خن' },
    { id: 4, name: 'نورة فهد القحطاني', email: 'noura@email.com', phone: '0512345678', orders: 2, status: 'active', joined: '2024-03-25', avatar: 'نف' },
    { id: 5, name: 'عمر سعد الدوسري', email: 'omar@email.com', phone: '0508765432', orders: 6, status: 'active', joined: '2024-04-05', avatar: 'عس' },
    { id: 6, name: 'ريم جاسم الشمري', email: 'reem@email.com', phone: '0503456789', orders: 1, status: 'inactive', joined: '2024-04-18', avatar: 'رج' },
    { id: 7, name: 'فيصل عمر البلوي', email: 'faisal@email.com', phone: '0506543210', orders: 4, status: 'active', joined: '2024-05-01', avatar: 'فع' },
  ],

  academics: [
    { id: 1, name: 'د. محمد علي السعيد', email: 'dr.mohammed@email.com', specialty: 'الرياضيات والإحصاء', degree: 'دكتوراه', orders: 18, rating: 4.9, status: 'approved', joined: '2024-01-05', avatar: 'مع' },
    { id: 2, name: 'أ. فاطمة يوسف', email: 'fatima@email.com', specialty: 'اللغة العربية', degree: 'ماجستير', orders: 12, rating: 4.7, status: 'approved', joined: '2024-02-10', avatar: 'في' },
    { id: 3, name: 'د. عبدالرحمن خالد', email: 'abdulrahman@email.com', specialty: 'علوم الحاسب', degree: 'دكتوراه', orders: 25, rating: 4.8, status: 'pending', joined: '2024-03-15', avatar: 'عخ' },
    { id: 4, name: 'أ. منيرة صالح', email: 'munira@email.com', specialty: 'الفيزياء', degree: 'ماجستير', orders: 8, rating: 4.5, status: 'approved', joined: '2024-04-01', avatar: 'مص' },
    { id: 5, name: 'د. حسن طارق', email: 'hassan@email.com', specialty: 'المحاسبة والمالية', degree: 'دكتوراه', orders: 15, rating: 4.6, status: 'rejected', joined: '2024-04-20', avatar: 'حط' },
  ],

  services: [
    { id: 1, name: 'الأبحاث والدراسات', icon: '🔬', orders: 142, active: true },
    { id: 2, name: 'الرسائل الجامعية', icon: '📜', orders: 98, active: true },
    { id: 3, name: 'الترجمة الأكاديمية', icon: '🌐', orders: 76, active: true },
    { id: 4, name: 'المراجعة والتدقيق', icon: '✏️', orders: 54, active: true },
    { id: 5, name: 'التحليل الإحصائي', icon: '📊', orders: 89, active: true },
    { id: 6, name: 'العروض التقديمية', icon: '📋', orders: 43, active: false },
    { id: 7, name: 'المناهج التعليمية', icon: '📚', orders: 32, active: true },
    { id: 8, name: 'برمجة المشاريع', icon: '💻', orders: 67, active: true },
    { id: 9, name: 'التصميم الأكاديمي', icon: '🎨', orders: 28, active: true },
    { id: 10, name: 'الاستشارات التعليمية', icon: '💬', orders: 91, active: true },
    { id: 11, name: 'النشر العلمي', icon: '📰', orders: 15, active: false },
    { id: 12, name: 'التدريب الأكاديمي', icon: '🎓', orders: 38, active: true },
  ],

  packages: [
    { id: 1, name: 'البداية', price: 149, color: '#64748b', features: ['مهمة واحدة', 'مراجعة بسيطة', 'تسليم خلال 7 أيام'], orders: 89, active: true },
    { id: 2, name: 'التطوير', price: 349, color: '#3b82f6', features: ['3 مهام', 'مراجعة متقدمة', 'تسليم خلال 5 أيام', 'دعم فني'], orders: 124, active: true },
    { id: 3, name: 'التحليل', price: 649, color: '#8b5cf6', features: ['5 مهام', 'تحليل شامل', 'تسليم خلال 3 أيام', 'دعم 24/7'], orders: 76, active: true },
    { id: 4, name: 'التسليم', price: 999, color: '#f59e0b', features: ['10 مهام', 'خدمة متميزة', 'تسليم خلال 48 ساعة', 'مدير خاص'], orders: 42, active: true },
    { id: 5, name: 'النخبة', price: 1999, color: '#ef4444', features: ['مهام غير محدودة', 'خدمة VIP', 'تسليم خلال 24 ساعة', 'فريق متخصص', 'ضمان النتائج'], orders: 11, active: true },
  ],

  orders: [
    { id: '#ORD-001', student: 'أحمد محمد', academic: 'د. محمد علي', service: 'الأبحاث والدراسات', package: 'التطوير', amount: 349, status: 'completed', date: '2024-05-01' },
    { id: '#ORD-002', student: 'سارة عبدالله', academic: 'أ. فاطمة يوسف', service: 'الرسائل الجامعية', package: 'التحليل', amount: 649, status: 'in_progress', date: '2024-05-03' },
    { id: '#ORD-003', student: 'خالد ناصر', academic: 'د. عبدالرحمن', service: 'برمجة المشاريع', package: 'النخبة', amount: 1999, status: 'new', date: '2024-05-05' },
    { id: '#ORD-004', student: 'نورة فهد', academic: 'أ. منيرة صالح', service: 'التحليل الإحصائي', package: 'البداية', amount: 149, status: 'completed', date: '2024-05-07' },
    { id: '#ORD-005', student: 'عمر سعد', academic: 'د. محمد علي', service: 'الترجمة الأكاديمية', package: 'التطوير', amount: 349, status: 'in_progress', date: '2024-05-10' },
    { id: '#ORD-006', student: 'ريم جاسم', academic: 'أ. فاطمة يوسف', service: 'المراجعة والتدقيق', package: 'التسليم', amount: 999, status: 'new', date: '2024-05-12' },
  ],

  payments: [
    { id: '#PAY-001', order: '#ORD-001', student: 'أحمد محمد', amount: 349, method: 'بطاقة ائتمانية', status: 'paid', date: '2024-05-01' },
    { id: '#PAY-002', order: '#ORD-002', student: 'سارة عبدالله', amount: 649, method: 'تحويل بنكي', status: 'paid', date: '2024-05-03' },
    { id: '#PAY-003', order: '#ORD-003', student: 'خالد ناصر', amount: 1999, method: 'محفظة إلكترونية', status: 'pending', date: '2024-05-05' },
    { id: '#PAY-004', order: '#ORD-004', student: 'نورة فهد', amount: 149, method: 'بطاقة ائتمانية', status: 'paid', date: '2024-05-07' },
    { id: '#PAY-005', order: '#ORD-005', student: 'عمر سعد', amount: 349, method: 'تحويل بنكي', status: 'failed', date: '2024-05-10' },
  ],

  notifications: [
    { id: 1, title: 'طلب جديد', message: 'وصل طلب جديد من أحمد محمد', time: 'منذ 5 دقائق', read: false, icon: '📋' },
    { id: 2, title: 'أكاديمي جديد', message: 'تسجيل أكاديمي جديد بحاجة للمراجعة', time: 'منذ 15 دقيقة', read: false, icon: '👤' },
    { id: 3, title: 'دفعة ناجحة', message: 'تمت معالجة الدفعة #PAY-004 بنجاح', time: 'منذ ساعة', read: true, icon: '💰' },
    { id: 4, title: 'شكوى مستخدم', message: 'شكوى من سارة عبدالله - طلب #ORD-002', time: 'منذ 3 ساعات', read: true, icon: '⚠️' },
  ],

  chartData: {
    months: ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
    orders: [28, 45, 38, 52, 61, 74, 58, 82, 95, 78, 88, 102],
    revenue: [4200, 6750, 5700, 7800, 9150, 11100, 8700, 12300, 14250, 11700, 13200, 15300],
  }
};

/* ============================================
   SIDEBAR MANAGEMENT
   ============================================ */
const AdminSidebar = {
  collapsed: false,
  mobileOpen: false,

  init() {
    this.sidebar = document.getElementById('sidebar');
    this.mainContent = document.getElementById('mainContent');
    this.navbar = document.getElementById('navbar');
    this.mobileOverlay = document.getElementById('mobileOverlay');
    this.toggleBtn = document.getElementById('sidebarToggle');

    if (!this.sidebar) return;

    // Restore state
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') this.collapse(false);
    else this.expand(false);

    // Events
    if (this.toggleBtn) {
      this.toggleBtn.addEventListener('click', () => this.toggle());
    }
    if (this.mobileOverlay) {
      this.mobileOverlay.addEventListener('click', () => this.closeMobile());
    }

    // Mark active link
    this.markActiveLink();
  },

  toggle() {
    if (window.innerWidth > 1024) {
      if (this.collapsed) this.expand();
      else this.collapse();
    } else {
      if (this.mobileOpen) this.closeMobile();
      else this.openMobile();
    }
  },

  collapse(save = true) {
    this.collapsed = true;
    this.sidebar?.classList.add('collapsed');
    this.mainContent?.classList.add('sidebar-collapsed');
    this.navbar?.classList.add('sidebar-collapsed');
    if (save) localStorage.setItem('sidebarCollapsed', 'true');
  },

  expand(save = true) {
    this.collapsed = false;
    this.sidebar?.classList.remove('collapsed');
    this.mainContent?.classList.remove('sidebar-collapsed');
    this.navbar?.classList.remove('sidebar-collapsed');
    if (save) localStorage.setItem('sidebarCollapsed', 'false');
  },

  openMobile() {
    this.mobileOpen = true;
    this.sidebar?.classList.add('mobile-open');
    this.mobileOverlay?.classList.add('active');
    document.body.style.overflow = 'hidden';
  },

  closeMobile() {
    this.mobileOpen = false;
    this.sidebar?.classList.remove('mobile-open');
    this.mobileOverlay?.classList.remove('active');
    document.body.style.overflow = '';
  },

  markActiveLink() {
    const current = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(link => {
      const href = link.getAttribute('href');
      if (href && href.includes(current)) {
        link.classList.add('active');
      }
    });
  }
};

/* ============================================
   DARK MODE
   ============================================ */
const DarkMode = {
  init() {
    const saved = localStorage.getItem('darkMode');
    if (saved === 'true') this.enable(false);
    else this.disable(false);

    document.querySelectorAll('.dark-toggle').forEach(btn => {
      btn.addEventListener('click', () => this.toggle());
    });
  },

  toggle() {
    if (document.documentElement.classList.contains('dark')) {
      this.disable();
    } else {
      this.enable();
    }
  },

  enable(save = true) {
    document.documentElement.classList.add('dark');
    document.querySelectorAll('.dark-toggle').forEach(btn => {
      btn.innerHTML = '☀️';
      btn.setAttribute('data-tooltip', 'الوضع الفاتح');
    });
    if (save) localStorage.setItem('darkMode', 'true');
  },

  disable(save = true) {
    document.documentElement.classList.remove('dark');
    document.querySelectorAll('.dark-toggle').forEach(btn => {
      btn.innerHTML = '🌙';
      btn.setAttribute('data-tooltip', 'الوضع الداكن');
    });
    if (save) localStorage.setItem('darkMode', 'false');
  }
};

/* ============================================
   MODAL SYSTEM
   ============================================ */
const Modal = {
  init() {
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) this.close(overlay.id);
      });
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        const modalId = btn.closest('.modal-overlay')?.id;
        if (modalId) this.close(modalId);
      });
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.closeAll();
    });
  },

  open(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  },

  close(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }
  },

  closeAll() {
    document.querySelectorAll('.modal-overlay.active').forEach(m => {
      m.classList.remove('active');
    });
    document.body.style.overflow = '';
  },

  confirm(title, message, onConfirm) {
    const modal = document.getElementById('confirmModal');
    if (!modal) return;
    modal.querySelector('#confirmTitle').textContent = title;
    modal.querySelector('#confirmMessage').textContent = message;
    const btn = modal.querySelector('#confirmBtn');
    btn.onclick = () => { onConfirm(); this.close('confirmModal'); };
    this.open('confirmModal');
  }
};

/* ============================================
   TABLE SEARCH & FILTER
   ============================================ */
const TableManager = {
  init(tableId, searchId, filterIds = []) {
    const table = document.getElementById(tableId);
    const searchInput = document.getElementById(searchId);
    if (!table || !searchInput) return;

    const rows = () => Array.from(table.querySelectorAll('tbody tr'));

    const filter = () => {
      const query = searchInput.value.toLowerCase().trim();
      const filters = filterIds.map(id => {
        const el = document.getElementById(id);
        return el ? el.value : '';
      });

      rows().forEach(row => {
        const text = row.textContent.toLowerCase();
        const matchSearch = !query || text.includes(query);
        const matchFilters = filters.every((f, i) => {
          if (!f || f === 'all') return true;
          const cell = row.querySelector(`[data-filter="${filterIds[i]}"]`);
          return cell ? cell.dataset.value === f : text.includes(f);
        });
        row.style.display = matchSearch && matchFilters ? '' : 'none';
      });

      this.updatePagination(tableId);
    };

    searchInput.addEventListener('input', filter);
    filterIds.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', filter);
    });
  },

  updatePagination(tableId) {
    // Placeholder for pagination update
  }
};

/* ============================================
   NOTIFICATIONS DROPDOWN
   ============================================ */
const Notifications = {
  init() {
    const btn = document.getElementById('notificationBtn');
    const dropdown = document.getElementById('notificationDropdown');
    if (!btn || !dropdown) return;

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('open');
    });

    document.addEventListener('click', () => {
      dropdown.classList.remove('open');
    });

    this.render();
  },

  render() {
    const list = document.getElementById('notificationList');
    if (!list) return;

    const unread = MOCK_DATA.notifications.filter(n => !n.read).length;
    const badge = document.getElementById('notificationBadge');
    if (badge) badge.textContent = unread;

    list.innerHTML = MOCK_DATA.notifications.map(n => `
      <div class="flex items-start gap-3 p-4 border-b border-[var(--border-color)] hover:bg-[var(--bg-main)] transition-colors cursor-pointer ${!n.read ? 'bg-indigo-50 dark:bg-indigo-900/10' : ''}">
        <span class="text-2xl">${n.icon}</span>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-[var(--text-primary)]">${n.title}</p>
          <p class="text-xs text-[var(--text-secondary)] mt-0.5">${n.message}</p>
          <p class="text-xs text-[var(--primary)] mt-1">${n.time}</p>
        </div>
        ${!n.read ? '<span class="w-2 h-2 rounded-full bg-indigo-500 mt-1 flex-shrink-0"></span>' : ''}
      </div>
    `).join('');
  }
};

/* ============================================
   TABS SYSTEM
   ============================================ */
const Tabs = {
  init() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        const container = btn.closest('[data-tabs]');
        if (!container) return;

        container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        container.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

        btn.classList.add('active');
        const panel = container.querySelector(`#${target}`);
        if (panel) panel.classList.add('active');
      });
    });
  }
};

/* ============================================
   CHARTS (using canvas)
   ============================================ */
const Charts = {
  drawLineChart(canvasId, data, labels, color = '#6366f1') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.offsetWidth;
    const H = canvas.height = canvas.offsetHeight || 280;

    const padX = 50, padY = 30, padBottom = 40;
    const chartW = W - padX * 2;
    const chartH = H - padY - padBottom;

    const max = Math.max(...data) * 1.1;
    const min = 0;

    ctx.clearRect(0, 0, W, H);

    // Grid lines
    ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--border-color') || '#e2e8f0';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
      const y = padY + (chartH / 4) * i;
      ctx.beginPath();
      ctx.moveTo(padX, y);
      ctx.lineTo(W - padX, y);
      ctx.setLineDash([4, 4]);
      ctx.stroke();
      ctx.setLineDash([]);
      // Y label
      ctx.fillStyle = '#94a3b8';
      ctx.font = '11px Tajawal, sans-serif';
      ctx.textAlign = 'right';
      const val = Math.round(max - (max / 4) * i);
      ctx.fillText(val.toLocaleString('ar'), padX - 6, y + 4);
    }

    // X labels
    ctx.fillStyle = '#94a3b8';
    ctx.font = '11px Tajawal, sans-serif';
    ctx.textAlign = 'center';
    labels.forEach((label, i) => {
      const x = padX + (chartW / (labels.length - 1)) * i;
      ctx.fillText(label, x, H - 10);
    });

    // Gradient fill
    const gradient = ctx.createLinearGradient(0, padY, 0, padY + chartH);
    gradient.addColorStop(0, color + '33');
    gradient.addColorStop(1, color + '00');

    const points = data.map((v, i) => ({
      x: padX + (chartW / (data.length - 1)) * i,
      y: padY + chartH - ((v - min) / (max - min)) * chartH
    }));

    // Fill area
    ctx.beginPath();
    ctx.moveTo(points[0].x, padY + chartH);
    points.forEach(p => ctx.lineTo(p.x, p.y));
    ctx.lineTo(points[points.length - 1].x, padY + chartH);
    ctx.closePath();
    ctx.fillStyle = gradient;
    ctx.fill();

    // Line
    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    for (let i = 1; i < points.length; i++) {
      const xc = (points[i - 1].x + points[i].x) / 2;
      const yc = (points[i - 1].y + points[i].y) / 2;
      ctx.quadraticCurveTo(points[i - 1].x, points[i - 1].y, xc, yc);
    }
    ctx.lineTo(points[points.length - 1].x, points[points.length - 1].y);
    ctx.strokeStyle = color;
    ctx.lineWidth = 2.5;
    ctx.stroke();

    // Dots
    points.forEach(p => {
      ctx.beginPath();
      ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
      ctx.fillStyle = color;
      ctx.fill();
      ctx.strokeStyle = '#fff';
      ctx.lineWidth = 2;
      ctx.stroke();
    });
  },

  drawBarChart(canvasId, data, labels, color = '#6366f1') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.offsetWidth;
    const H = canvas.height = canvas.offsetHeight || 220;

    const padX = 50, padY = 20, padBottom = 40;
    const chartW = W - padX * 2;
    const chartH = H - padY - padBottom;
    const max = Math.max(...data) * 1.15;
    const barW = (chartW / data.length) * 0.55;
    const gap = (chartW / data.length) * 0.45;

    ctx.clearRect(0, 0, W, H);

    data.forEach((v, i) => {
      const barH = (v / max) * chartH;
      const x = padX + (chartW / data.length) * i + gap / 2;
      const y = padY + chartH - barH;

      const gradient = ctx.createLinearGradient(0, y, 0, y + barH);
      gradient.addColorStop(0, color);
      gradient.addColorStop(1, color + '88');

      ctx.beginPath();
      ctx.roundRect(x, y, barW, barH, 6);
      ctx.fillStyle = gradient;
      ctx.fill();

      ctx.fillStyle = '#94a3b8';
      ctx.font = '11px Tajawal, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(labels[i], x + barW / 2, H - 10);
    });
  },

  drawDonutChart(canvasId, data, labels, colors) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.offsetWidth;
    const H = canvas.height = canvas.offsetHeight || 220;
    const cx = W / 2, cy = H / 2;
    const r = Math.min(cx, cy) - 30;
    const inner = r * 0.55;
    const total = data.reduce((a, b) => a + b, 0);

    ctx.clearRect(0, 0, W, H);
    let startAngle = -Math.PI / 2;

    data.forEach((v, i) => {
      const angle = (v / total) * Math.PI * 2;
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r, startAngle, startAngle + angle);
      ctx.closePath();
      ctx.fillStyle = colors[i];
      ctx.fill();
      startAngle += angle;
    });

    // Hole
    ctx.beginPath();
    ctx.arc(cx, cy, inner, 0, Math.PI * 2);
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--bg-card') || '#fff';
    ctx.fill();

    // Center text
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-primary') || '#1e293b';
    ctx.font = 'bold 22px Tajawal, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(total.toLocaleString('ar'), cx, cy + 4);
    ctx.fillStyle = '#94a3b8';
    ctx.font = '13px Tajawal, sans-serif';
    ctx.fillText('إجمالي', cx, cy + 22);
  }
};

/* ============================================
   COUNTER ANIMATION
   ============================================ */
function animateCounter(el, target, prefix = '', suffix = '') {
  const duration = 1500;
  const start = performance.now();
  const startVal = 0;

  function update(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const val = Math.round(startVal + (target - startVal) * eased);
    el.textContent = prefix + val.toLocaleString('ar') + suffix;
    if (progress < 1) requestAnimationFrame(update);
  }
  requestAnimationFrame(update);
}

function initCounters() {
  document.querySelectorAll('[data-counter]').forEach(el => {
    const target = parseInt(el.dataset.counter);
    const prefix = el.dataset.prefix || '';
    const suffix = el.dataset.suffix || '';
    const observer = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) {
        animateCounter(el, target, prefix, suffix);
        observer.disconnect();
      }
    });
    observer.observe(el);
  });
}

/* ============================================
   TOAST NOTIFICATIONS
   ============================================ */
const Toast = {
  show(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toastContainer') || (() => {
      const el = document.createElement('div');
      el.id = 'toastContainer';
      el.style.cssText = 'position:fixed;bottom:24px;left:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
      document.body.appendChild(el);
      return el;
    })();

    const colors = {
      success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#6366f1'
    };
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };

    const toast = document.createElement('div');
    toast.style.cssText = `
      background: var(--bg-card);
      border: 1px solid ${colors[type]};
      border-right: 4px solid ${colors[type]};
      border-radius: 12px;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-family: Tajawal, sans-serif;
      font-size: 14px;
      font-weight: 500;
      color: var(--text-primary);
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
      min-width: 240px;
      max-width: 360px;
      transform: translateX(-20px);
      opacity: 0;
      transition: all 0.3s ease;
    `;
    toast.innerHTML = `<span style="font-size:18px">${icons[type]}</span><span>${message}</span>`;
    container.appendChild(toast);

    requestAnimationFrame(() => {
      toast.style.transform = 'translateX(0)';
      toast.style.opacity = '1';
    });

    setTimeout(() => {
      toast.style.transform = 'translateX(-20px)';
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }
};

/* ============================================
   STATUS HELPERS
   ============================================ */
function getStatusBadge(status) {
  const map = {
    active: '<span class="badge badge-success">✓ نشط</span>',
    suspended: '<span class="badge badge-danger">⊘ معلق</span>',
    inactive: '<span class="badge badge-secondary">○ غير نشط</span>',
    approved: '<span class="badge badge-success">✓ مقبول</span>',
    pending: '<span class="badge badge-warning">⏳ قيد المراجعة</span>',
    rejected: '<span class="badge badge-danger">✗ مرفوض</span>',
    new: '<span class="badge badge-info">★ جديد</span>',
    in_progress: '<span class="badge badge-warning">⟳ قيد التنفيذ</span>',
    completed: '<span class="badge badge-success">✓ مكتمل</span>',
    paid: '<span class="badge badge-success">✓ مدفوع</span>',
    pending_pay: '<span class="badge badge-warning">⏳ معلق</span>',
    failed: '<span class="badge badge-danger">✗ فاشل</span>',
  };
  return map[status] || `<span class="badge badge-secondary">${status}</span>`;
}

function getAvatarColor(index) {
  const colors = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
  return colors[index % colors.length];
}

/* ============================================
   INIT
   ============================================ */
document.addEventListener('DOMContentLoaded', () => {
  AdminSidebar.init();
  DarkMode.init();
  Modal.init();
  Tabs.init();
  Notifications.init();
  initCounters();

  // Auto-animate page elements
  document.querySelectorAll('.animate-fadeInUp').forEach((el, i) => {
    el.style.animationDelay = `${i * 0.08}s`;
  });
});

// Export for use in other files
window.MOCK_DATA = MOCK_DATA;
window.AdminSidebar = AdminSidebar;
window.DarkMode = DarkMode;
window.Modal = Modal;
window.Toast = Toast;
window.Charts = Charts;
window.TableManager = TableManager;
window.getStatusBadge = getStatusBadge;
window.getAvatarColor = getAvatarColor;
