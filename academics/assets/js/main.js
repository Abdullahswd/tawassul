/**
 * ============================================
 * Academics Module - Main JavaScript
 * Eduroad Academic Platform
 * ============================================
 */

/* ============================================
   DATA STORE
   ============================================ */
// Capture anything pre-injected by PHP inline scripts (e.g. navbar.php) BEFORE this file runs
const _preInjected = window.ACADEMICS_DATA || {};

const ACADEMICS_DATA = {
  // Defaults — each PHP page overrides these via window.ACADEMICS_DATA.X = realData
  academics:     _preInjected.academics     || [],
  services:      _preInjected.services      || [],
  specialties:   _preInjected.specialties   || [
    'الرياضيات والإحصاء', 'اللغة العربية وآدابها', 'علوم الحاسب والذكاء الاصطناعي',
    'الفيزياء والهندسة', 'المحاسبة والمالية', 'علم النفس والتربية',
    'الكيمياء والأحياء', 'القانون والأنظمة', 'الطب والعلوم الصحية',
    'الاقتصاد وإدارة الأعمال',
  ],
  orders:        _preInjected.orders        || [],
  earnings:      Object.assign({
    total: 0, pending: 0, withdrawn: 0, thisMonth: 0, lastMonth: 0,
    monthly: [0,0,0,0,0,0,0,0,0,0,0,0],
    months: ['يناير','فبراير','مارس','إبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'],
    transactions: []
  }, _preInjected.earnings || {}),
  reviews:       _preInjected.reviews       || [],
  notifications: _preInjected.notifications || [],
};

/* ============================================
   HELPERS
   ============================================ */
function getAvatarColor(i) {
  const colors = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6'];
  return colors[i % colors.length];
}

function getStatusBadge(status) {
  const map = {
    new: '<span class="badge badge-info">★ جديد</span>',
    pending_assignment: '<span class="badge badge-warning" style="background:rgba(245,158,11,.15);color:#d97706;border-color:rgba(245,158,11,.3)">⏳ بانتظار تعيين الإدارة</span>',
    assigned: '<span class="badge badge-primary" style="background:rgba(99,102,241,.15);color:#6366f1;border-color:rgba(99,102,241,.3)">→ مسند إليك</span>',
    accepted: '<span class="badge badge-success" style="background:rgba(16,185,129,.15);color:#059669;border-color:rgba(16,185,129,.3)">✓ مقبول</span>',
    in_progress: '<span class="badge badge-warning">⟳ قيد التنفيذ</span>',
    revision: '<span class="badge badge-secondary">✍ تحت المراجعة</span>',
    completed: '<span class="badge badge-success">✓ مكتمل</span>',
    cancelled: '<span class="badge badge-danger">✗ ملغي</span>',
    approved: '<span class="badge badge-success">✓ مقبول</span>',
    pending: '<span class="badge badge-warning">⏳ قيد المراجعة</span>',
    rejected: '<span class="badge badge-danger">✗ مرفوض</span>',
    paid: '<span class="badge badge-success">✓ مدفوع</span>',
  };
  return map[status] || `<span class="badge badge-secondary">${status}</span>`;
}

function renderStars(rating) {
  let html = '<div class="stars">';
  for (let i = 1; i <= 5; i++) {
    html += `<span class="star${i > rating ? ' empty' : ''}">★</span>`;
  }
  html += '</div>';
  return html;
}

function animateCounter(el, target, prefix = '', suffix = '') {
  const duration = 1400;
  const start = performance.now();
  function update(now) {
    const p = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - p, 3);
    const val = Math.round(target * eased);
    el.textContent = prefix + val.toLocaleString('ar') + suffix;
    if (p < 1) requestAnimationFrame(update);
  }
  requestAnimationFrame(update);
}

function initCounters() {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const el = e.target;
        animateCounter(el, +el.dataset.counter, el.dataset.prefix || '', el.dataset.suffix || '');
        observer.unobserve(el);
      }
    });
  });
  document.querySelectorAll('[data-counter]').forEach(el => observer.observe(el));
}

/* ============================================
   SIDEBAR
   ============================================ */
const AcademicSidebar = {
  collapsed: false,
  open: false,

  init() {
    this.sidebar = document.getElementById('sidebar');
    this.mainContent = document.getElementById('mainContent');
    this.navbar = document.getElementById('navbar');
    this.overlay = document.getElementById('mobileOverlay');
    const btn = document.getElementById('sidebarToggle');

    if (!this.sidebar) return;

    if (localStorage.getItem('acSidebarCollapsed') === 'true') this.collapse(false);

    btn?.addEventListener('click', () => this.toggle());
    this.overlay?.addEventListener('click', () => this.closeMobile());
    this.markActive();
  },

  toggle() {
    if (window.innerWidth > 1024) { this.collapsed ? this.expand() : this.collapse(); }
    else { this.open ? this.closeMobile() : this.openMobile(); }
  },

  collapse(save = true) {
    this.collapsed = true;
    this.sidebar.classList.add('collapsed');
    this.mainContent?.classList.add('collapsed');
    this.navbar?.classList.add('collapsed');
    if (save) localStorage.setItem('acSidebarCollapsed', 'true');
  },

  expand(save = true) {
    this.collapsed = false;
    this.sidebar.classList.remove('collapsed');
    this.mainContent?.classList.remove('collapsed');
    this.navbar?.classList.remove('collapsed');
    if (save) localStorage.setItem('acSidebarCollapsed', 'false');
  },

  openMobile() {
    this.open = true;
    this.sidebar.classList.add('open');
    this.overlay?.classList.add('active');
    document.body.style.overflow = 'hidden';
  },

  closeMobile() {
    this.open = false;
    this.sidebar.classList.remove('open');
    this.overlay?.classList.remove('active');
    document.body.style.overflow = '';
  },

  markActive() {
    const page = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(link => {
      const href = link.getAttribute('href') || '';
      if (href.includes(page)) link.classList.add('active');
    });
  }
};

/* ============================================
   DARK MODE
   ============================================ */
const DarkMode = {
  init() {
    if (localStorage.getItem('acDarkMode') === 'true') this.enable(false);
    document.querySelectorAll('.dark-toggle').forEach(btn => {
      btn.addEventListener('click', () => this.toggle());
    });
  },
  toggle() { document.documentElement.classList.contains('dark') ? this.disable() : this.enable(); },
  enable(save = true) {
    document.documentElement.classList.add('dark');
    document.querySelectorAll('.dark-toggle').forEach(b => b.textContent = '☀️');
    if (save) localStorage.setItem('acDarkMode', 'true');
  },
  disable(save = true) {
    document.documentElement.classList.remove('dark');
    document.querySelectorAll('.dark-toggle').forEach(b => b.textContent = '🌙');
    if (save) localStorage.setItem('acDarkMode', 'false');
  }
};

/* ============================================
   MODAL
   ============================================ */
const Modal = {
  init() {
    document.querySelectorAll('.modal-overlay').forEach(o => {
      o.addEventListener('click', e => { if (e.target === o) this.close(o.id); });
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.closest('.modal-overlay')?.id;
        if (id) this.close(id);
      });
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') this.closeAll(); });
  },
  open(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
  },
  close(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
  },
  closeAll() {
    document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    document.body.style.overflow = '';
  },
  confirm(title, msg, onOk) {
    const m = document.getElementById('confirmModal');
    if (!m) return;
    m.querySelector('#confirmTitle').textContent = title;
    m.querySelector('#confirmMsg').textContent = msg;
    m.querySelector('#confirmOkBtn').onclick = () => { onOk(); this.close('confirmModal'); };
    this.open('confirmModal');
  }
};

/* ============================================
   TOAST
   ============================================ */
const Toast = {
  show(message, type = 'info', duration = 3200) {
    let container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastContainer';
      container.style.cssText = 'position:fixed;bottom:24px;left:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
      document.body.appendChild(container);
    }
    const colors = { success:'#10b981', error:'#ef4444', warning:'#f59e0b', info:'#6366f1' };
    const icons  = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️' };
    const t = document.createElement('div');
    t.style.cssText = `background:var(--bg-card);border:1px solid ${colors[type]};border-right:4px solid ${colors[type]};border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:10px;font-family:Tajawal,sans-serif;font-size:14px;font-weight:500;color:var(--text-primary);box-shadow:0 8px 24px rgba(0,0,0,.12);min-width:240px;max-width:360px;transform:translateX(-24px);opacity:0;transition:all .3s ease;`;
    t.innerHTML = `<span style="font-size:18px">${icons[type]}</span><span>${message}</span>`;
    container.appendChild(t);
    requestAnimationFrame(() => { t.style.transform = 'translateX(0)'; t.style.opacity = '1'; });
    setTimeout(() => { t.style.transform = 'translateX(-24px)'; t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
  }
};

/* ============================================
   TABS
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
        container.querySelector('#' + target)?.classList.add('active');
      });
    });
  }
};

/* ============================================
   MULTI-STEP FORM
   ============================================ */
const StepForm = {
  currentStep: 1,
  totalSteps: 4,

  init() {
    this.update();
  },

  next() {
    if (!this.validate()) return;
    if (this.currentStep < this.totalSteps) {
      this.currentStep++;
      this.update();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  },

  prev() {
    if (this.currentStep > 1) {
      this.currentStep--;
      this.update();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  },

  goTo(step) {
    if (step <= this.currentStep) {
      this.currentStep = step;
      this.update();
    }
  },

  validate() {
    const stepEl = document.getElementById('step-' + this.currentStep);
    if (!stepEl) return true;
    let valid = true;
    stepEl.querySelectorAll('[required]').forEach(inp => {
      inp.classList.remove('error');
      if (!inp.value.trim()) {
        inp.classList.add('error');
        valid = false;
      }
    });
    if (!valid) Toast.show('يرجى ملء جميع الحقول المطلوبة', 'error');
    return valid;
  },

  update() {
    // Update step circles
    document.querySelectorAll('.step-item').forEach((item, i) => {
      const s = i + 1;
      item.classList.remove('active', 'done');
      if (s === this.currentStep) item.classList.add('active');
      else if (s < this.currentStep) item.classList.add('done');
    });
    // Show/hide step panels
    document.querySelectorAll('.form-step').forEach((panel, i) => {
      panel.classList.toggle('active', i + 1 === this.currentStep);
    });
    // Update progress bar
    const bar = document.getElementById('formProgressBar');
    if (bar) bar.style.width = ((this.currentStep - 1) / (this.totalSteps - 1) * 100) + '%';
    // Nav buttons
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    if (prevBtn) prevBtn.style.display = this.currentStep === 1 ? 'none' : 'flex';
    if (nextBtn) nextBtn.style.display = this.currentStep === this.totalSteps ? 'none' : 'flex';
    if (submitBtn) submitBtn.style.display = this.currentStep === this.totalSteps ? 'flex' : 'none';
    // Update step counter
    const counter = document.getElementById('stepCounter');
    if (counter) counter.textContent = `الخطوة ${this.currentStep} من ${this.totalSteps}`;
  },

  submit() {
    if (!this.validate()) return;
    const btn = document.getElementById('submitBtn');
    if (btn) { btn.innerHTML = '⏳ جاري الإرسال...'; btn.disabled = true; }
    setTimeout(() => {
      document.getElementById('formSuccess')?.classList.remove('hidden');
      document.getElementById('registerForm')?.classList.add('hidden');
    }, 1800);
  }
};

/* ============================================
   FILE UPLOAD UI
   ============================================ */
function initFileUploads() {
  document.querySelectorAll('.file-upload-area').forEach(area => {
    const input = area.querySelector('input[type=file]');
    area.addEventListener('click', () => input?.click());
    area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('drag-over'); });
    area.addEventListener('dragleave', () => area.classList.remove('drag-over'));
    area.addEventListener('drop', e => {
      e.preventDefault();
      area.classList.remove('drag-over');
      handleFiles(area, e.dataTransfer.files);
    });
    input?.addEventListener('change', () => handleFiles(area, input.files));
  });
}

function handleFiles(area, files) {
  if (!files || !files.length) return;
  const file = files[0];
  const preview = area.querySelector('.upload-preview');
  const info = area.querySelector('.upload-info');
  if (preview) {
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(file);
    }
  }
  if (info) info.textContent = `✅ ${file.name} (${(file.size / 1024).toFixed(0)} KB)`;
  area.style.borderColor = '#10b981';
  area.style.background = 'rgba(16,185,129,0.04)';
}

/* ============================================
   CHARTS (pure canvas)
   ============================================ */
const Charts = {
  drawLine(id, data, labels, color = '#6366f1') {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.offsetWidth;
    const H = canvas.height = canvas.offsetHeight || 260;
    const px = 50, py = 24, pb = 36;
    const cW = W - px * 2, cH = H - py - pb;
    const max = Math.max(...data) * 1.12;

    ctx.clearRect(0, 0, W, H);

    // Grid
    for (let i = 0; i <= 4; i++) {
      const y = py + (cH / 4) * i;
      ctx.strokeStyle = 'rgba(100,116,139,0.15)';
      ctx.lineWidth = 1;
      ctx.setLineDash([4, 4]);
      ctx.beginPath(); ctx.moveTo(px, y); ctx.lineTo(W - px, y); ctx.stroke();
      ctx.setLineDash([]);
      ctx.fillStyle = '#94a3b8'; ctx.font = '11px Tajawal,sans-serif'; ctx.textAlign = 'right';
      ctx.fillText(Math.round(max - (max / 4) * i).toLocaleString('ar'), px - 6, y + 4);
    }

    const pts = data.map((v, i) => ({
      x: px + (cW / (data.length - 1)) * i,
      y: py + cH - (v / max) * cH
    }));

    // Gradient fill
    const grad = ctx.createLinearGradient(0, py, 0, py + cH);
    grad.addColorStop(0, color + '44');
    grad.addColorStop(1, color + '00');
    ctx.beginPath();
    ctx.moveTo(pts[0].x, py + cH);
    pts.forEach(p => ctx.lineTo(p.x, p.y));
    ctx.lineTo(pts[pts.length - 1].x, py + cH);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();

    // Line
    ctx.beginPath();
    ctx.moveTo(pts[0].x, pts[0].y);
    for (let i = 1; i < pts.length; i++) {
      const xc = (pts[i-1].x + pts[i].x) / 2;
      const yc = (pts[i-1].y + pts[i].y) / 2;
      ctx.quadraticCurveTo(pts[i-1].x, pts[i-1].y, xc, yc);
    }
    ctx.lineTo(pts[pts.length-1].x, pts[pts.length-1].y);
    ctx.strokeStyle = color; ctx.lineWidth = 2.5; ctx.stroke();

    // Dots
    pts.forEach(p => {
      ctx.beginPath(); ctx.arc(p.x, p.y, 4, 0, Math.PI*2);
      ctx.fillStyle = color; ctx.fill();
      ctx.strokeStyle = 'var(--bg-card)' || '#fff'; ctx.lineWidth = 2; ctx.stroke();
    });

    // X Labels
    ctx.fillStyle = '#94a3b8'; ctx.font = '11px Tajawal,sans-serif'; ctx.textAlign = 'center';
    labels.forEach((lbl, i) => {
      ctx.fillText(lbl, px + (cW / (labels.length - 1)) * i, H - 6);
    });
  },

  drawBar(id, data, labels, color = '#6366f1') {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.offsetWidth;
    const H = canvas.height = canvas.offsetHeight || 200;
    const px = 50, py = 20, pb = 36;
    const cW = W - px * 2, cH = H - py - pb;
    const max = Math.max(...data) * 1.15;
    const bW = (cW / data.length) * 0.55;
    const gap = (cW / data.length) * 0.45;

    ctx.clearRect(0, 0, W, H);
    data.forEach((v, i) => {
      const bH = (v / max) * cH;
      const x = px + (cW / data.length) * i + gap / 2;
      const y = py + cH - bH;
      const grad = ctx.createLinearGradient(0, y, 0, y + bH);
      grad.addColorStop(0, color);
      grad.addColorStop(1, color + '88');
      ctx.beginPath();
      ctx.roundRect(x, y, bW, bH, 6);
      ctx.fillStyle = grad;
      ctx.fill();
      ctx.fillStyle = '#94a3b8'; ctx.font = '10px Tajawal,sans-serif'; ctx.textAlign = 'center';
      ctx.fillText(labels[i], x + bW/2, H - 8);
    });
  },

  drawDonut(id, data, labels, colors) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.offsetWidth;
    const H = canvas.height = canvas.offsetHeight || 200;
    const cx = W/2, cy = H/2, r = Math.min(cx,cy) - 20, inner = r * 0.55;
    const total = data.reduce((a,b) => a+b, 0);
    let angle = -Math.PI/2;
    ctx.clearRect(0, 0, W, H);
    data.forEach((v, i) => {
      const slice = (v / total) * Math.PI * 2;
      ctx.beginPath(); ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r, angle, angle + slice);
      ctx.closePath(); ctx.fillStyle = colors[i]; ctx.fill();
      angle += slice;
    });
    ctx.beginPath(); ctx.arc(cx, cy, inner, 0, Math.PI*2);
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--bg-card') || '#fff';
    ctx.fill();
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-primary') || '#1e293b';
    ctx.font = 'bold 20px Tajawal,sans-serif'; ctx.textAlign = 'center';
    ctx.fillText(total.toLocaleString('ar'), cx, cy + 4);
    ctx.fillStyle = '#94a3b8'; ctx.font = '12px Tajawal,sans-serif';
    ctx.fillText('إجمالي', cx, cy + 22);
  }
};

/* ============================================
   DROPDOWN (profile / notifications)
   ============================================ */
function initDropdowns() {
  document.querySelectorAll('.dropdown [data-toggle]').forEach(trigger => {
    trigger.addEventListener('click', e => {
      e.stopPropagation();
      const dd = trigger.closest('.dropdown');
      const isOpen = dd.classList.contains('open');
      document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
      if (!isOpen) dd.classList.add('open');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  });
}

/* ============================================
   NOTIFICATIONS RENDER
   ============================================ */
function renderNotifications() {
  const list = document.getElementById('notifList');
  if (!list) return;
  const unread = ACADEMICS_DATA.notifications.filter(n => !n.read).length;
  const badge = document.getElementById('notifBadge');
  if (badge) badge.textContent = unread;
  list.innerHTML = ACADEMICS_DATA.notifications.map(n => `
    <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border-color);${!n.read?'background:rgba(99,102,241,0.04)':''};cursor:pointer;transition:background .2s" onmouseover="this.style.background='var(--bg-main)'" onmouseout="this.style.background='${!n.read?'rgba(99,102,241,0.04)':'transparent'}'">
      <span style="font-size:22px">${n.icon}</span>
      <div style="flex:1">
        <p style="font-size:14px;font-weight:600;color:var(--text-primary)">${n.title}</p>
        <p style="font-size:12px;color:var(--text-secondary);margin-top:2px">${n.message}</p>
        <p style="font-size:11px;color:var(--primary);margin-top:4px">${n.time}</p>
      </div>
      ${!n.read ? '<span style="width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:4px"></span>' : ''}
    </div>
  `).join('');
}

/* ============================================
   INIT
   ============================================ */
document.addEventListener('DOMContentLoaded', () => {
  AcademicSidebar.init();
  DarkMode.init();
  Modal.init();
  Tabs.init();
  initCounters();
  initDropdowns();
  renderNotifications();
  initFileUploads();
});

// Force reload if page is served from Back-Forward Cache (BFCache)
window.onpageshow = function(event) {
  if (event.persisted) {
    window.location.reload();
  }
};

// Export globals — window.ACADEMICS_DATA is now the single source of truth;
// PHP pages inject real data via window.ACADEMICS_DATA.X = [...] AFTER this line
window.ACADEMICS_DATA        = ACADEMICS_DATA;
window.AcademicSidebar       = AcademicSidebar;
window.DarkMode              = DarkMode;
window.Modal                 = Modal;
window.Toast                 = Toast;
window.Charts                = Charts;
window.StepForm              = StepForm;
window.getAvatarColor        = getAvatarColor;
window.getStatusBadge        = getStatusBadge;
window.renderStars           = renderStars;
window.renderNotifications   = renderNotifications;
