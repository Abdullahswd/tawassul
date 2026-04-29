/**
 * Main Script for Services Module
 */

document.addEventListener('DOMContentLoaded', () => {
  // Common Initialization
  initDarkMode();
  
  // Specific Page Initialization
  const path = window.location.pathname;
  if (path.includes('services.html') || path.endsWith('/services/') || path.endsWith('/services')) {
    initServicesList();
  } else if (path.includes('service-details.html')) {
    initServiceDetails();
  } else if (path.includes('create-order.html')) {
    initCreateOrder();
  } else if (path.includes('orders.html')) {
    initOrdersList();
  } else if (path.includes('order-details.html')) {
    initOrderDetails();
  }
});

/** Dark Mode Logic */
function initDarkMode() {
  const isDark = localStorage.getItem('svDarkMode') === 'true';
  if (isDark) document.documentElement.classList.add('dark');
  
  document.querySelectorAll('.dark-toggle').forEach(btn => {
    btn.textContent = isDark ? '☀️' : '🌙';
    btn.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      const darkNow = document.documentElement.classList.contains('dark');
      localStorage.setItem('svDarkMode', darkNow);
      document.querySelectorAll('.dark-toggle').forEach(b => b.textContent = darkNow ? '☀️' : '🌙');
    });
  });
}

/** Services Listing Logic */
function initServicesList() {
  const catsContainer = document.getElementById('categoriesFilter');
  const srvContainer = document.getElementById('servicesGrid');
  if (!catsContainer || !srvContainer) return;

  // Render Categories
  catsContainer.innerHTML = `
    <button class="filter-tab active" data-id="all" onclick="filterServices('all')">الكل</button>
    ${TawassulServices.CATEGORIES.map(c => `<button class="filter-tab" data-id="${c.id}" onclick="filterServices('${c.id}')">${c.icon} ${c.name}</button>`).join('')}
  `;

  // Give access to window for inline onclicks
  window.filterServices = filterServices;

  // Initial render
  filterServices('all');
}

function filterServices(catId) {
  // Update active tab
  document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
  const activeBtn = document.querySelector(`.filter-tab[data-id="${catId}"]`);
  if (activeBtn) activeBtn.classList.add('active');

  const srvContainer = document.getElementById('servicesGrid');
  let data = catId === 'all' ? TawassulServices.SERVICES_DATA : TawassulServices.getServicesByCategory(catId);

  if (!data.length) {
    srvContainer.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-secondary)">لا توجد خدمات في هذا القسم حاليا.</div>';
    return;
  }

  srvContainer.innerHTML = data.map((s, idx) => `
    <div class="card fade-in" style="padding:24px;display:flex;flex-direction:column;animation-delay:${idx*0.05}s">
      <div style="font-size:32px;margin-bottom:16px;">${TawassulServices.getCategoryById(s.categoryId)?.icon || '📚'}</div>
      <h3 style="font-size:18px;font-weight:700;margin-bottom:12px">${s.name}</h3>
      <p style="font-size:14px;color:var(--text-secondary);flex-grow:1;line-height:1.6;margin-bottom:20px">${s.description.slice(0, 100)}...</p>
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:16px;border-top:1px solid var(--border-color)">
        <span style="font-weight:700;color:var(--primary)">يبدأ من ${s.price} ر.س</span>
        <a href="service-details.html?id=${s.id}" class="btn btn-outline" style="padding:6px 12px;font-size:14px">عرض التفاصيل ←</a>
      </div>
    </div>
  `).join('');
}


/** Service Details Logic */
function initServiceDetails() {
  const urlParams = new URLSearchParams(window.location.search);
  const id = urlParams.get('id') || 'srv-1'; // fallback to first
  const service = TawassulServices.getServiceById(id);
  const cat = TawassulServices.getCategoryById(service?.categoryId);

  if (!service) {
    document.getElementById('serviceContent').innerHTML = '<div style="text-align:center;padding:100px;font-size:20px">الخدمة غير موجودة</div>';
    return;
  }

  document.getElementById('sName').textContent = service.name;
  document.getElementById('sDesc').textContent = service.description;
  document.getElementById('sPrice').textContent = `${service.price} ر.س`;
  document.getElementById('sDuration').textContent = service.duration;
  document.getElementById('sCat').textContent = `${cat?.icon || ''} ${cat?.name || ''}`;
  
  // Set link to request form
  const orderBtn = document.getElementById('orderBtn');
  if (orderBtn) orderBtn.href = `create-order.html?serviceId=${service.id}`;
}


/** Create Order Logic (Dynamic Form) */
function initCreateOrder() {
  const catSelect = document.getElementById('categorySelect');
  const srvSelect = document.getElementById('serviceSelect');
  const dForm = document.getElementById('dynamicFields');
  const basePriceEl = document.getElementById('basePrice');
  const totalPriceEl = document.getElementById('totalPrice');

  // Populate categories
  catSelect.innerHTML = '<option value="">اختر القسم...</option>' + 
    TawassulServices.CATEGORIES.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

  // Handle category change
  catSelect.addEventListener('change', (e) => {
    const catId = e.target.value;
    const services = TawassulServices.getServicesByCategory(catId);
    srvSelect.innerHTML = '<option value="">اختر الخدمة...</option>' + 
      services.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    srvSelect.disabled = !catId;
    updateDynamicFields(null); // clear fields
    updatePrice(0);
  });

  // Handle service change
  srvSelect.addEventListener('change', (e) => {
    const service = TawassulServices.getServiceById(e.target.value);
    updateDynamicFields(service);
    if(service) {
      updatePrice(service.price);
    }
  });

  // Check query params to pre-select
  const urlParams = new URLSearchParams(window.location.search);
  const sId = urlParams.get('serviceId');
  if (sId) {
    const s = TawassulServices.getServiceById(sId);
    if (s) {
      catSelect.value = s.categoryId;
      // Trigger event manually to populate services
      catSelect.dispatchEvent(new Event('change'));
      srvSelect.value = s.id;
      srvSelect.dispatchEvent(new Event('change'));
    }
  }

  function updateDynamicFields(service) {
    if (!service) {
      dForm.innerHTML = '';
      return;
    }
    
    // Default shared fields
    let html = `
      <div class="form-group">
        <label class="form-label">مستوى الدراسة</label>
        <select class="form-input">
          <option>بكالوريوس</option>
          <option>ماجستير</option>
          <option>دكتوراه</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">لغة البحث/الخدمة</label>
        <select class="form-input">
          <option>العربية</option>
          <option>الإنجليزية</option>
        </select>
      </div>
    `;

    // Dynamic fields based on category/service
    if (service.categoryId === 'cat-5') { // الإحصاء
      html += `
        <div class="form-group">
          <label class="form-label">نوع التحليل المطلوب</label>
          <input class="form-input" placeholder="مثل: كاي تربيع، انوفا، الخ..."/>
        </div>
      `;
    } else if (service.categoryId === 'cat-11') { // الترجمة
      html += `
        <div class="form-group">
          <label class="form-label">عدد الصفحات</label>
          <input type="number" class="form-input" value="1" min="1" id="pagesCount"/>
        </div>
      `;
    } else { // عام
      html += `
        <div class="form-group">
          <label class="form-label">عدد الكلمات أو الصفحات المتوقع</label>
          <input type="number" class="form-input" placeholder="اختياري"/>
        </div>
      `;
    }

    dForm.innerHTML = html;

    // Attach listeners for dynamic price calc
    const pagesCount = document.getElementById('pagesCount');
    if (pagesCount) {
      pagesCount.addEventListener('input', (e) => {
        const p = parseInt(e.target.value) || 1;
        updatePrice(service.price * p);
      });
    }
  }

  function updatePrice(val) {
    if(basePriceEl) basePriceEl.textContent = val + ' ر.س';
    if(totalPriceEl) totalPriceEl.textContent = val + ' ر.س'; // Could add VAT later
  }

  // Handle submit form mock
  const form = document.getElementById('orderForm');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const btn = document.getElementById('submitBtn');
      btn.textContent = 'جاري إرسال الطلب...';
      btn.disabled = true;
      setTimeout(() => {
        alert('تم إرسال الطلب بنجاح! سيتم تحويلك إلى قائمة طلباتك.');
        window.location.href = 'orders.html';
      }, 1500);
    });
  }
}

/** Orders Listing */
function initOrdersList() {
  const tbody = document.getElementById('ordersTbody');
  if (!tbody) return;

  function render(status) {
    const list = status ? TawassulServices.MOCK_ORDERS.filter(o => o.status === status) : TawassulServices.MOCK_ORDERS;
    tbody.innerHTML = list.map(o => `
      <tr>
        <td style="font-weight:700;color:var(--primary)"><a href="order-details.html?id=${encodeURIComponent(o.id)}">${o.id}</a></td>
        <td>${o.serviceName}</td>
        <td>${o.date}</td>
        <td><span class="badge ${getBadgeClass(o.status)}">${getStatusText(o.status)}</span></td>
        <td><strong>${o.total} ر.س</strong></td>
        <td><a href="order-details.html?id=${encodeURIComponent(o.id)}" class="btn btn-outline" style="padding:4px 8px;font-size:12px">التفاصيل</a></td>
      </tr>
    `).join('');
  }

  render();
  window.filterOrders = render;
}

function getStatusText(status) {
  const m = { new:'جديد', in_progress:'قيد التنفيذ', completed:'مكتمل' };
  return m[status] || status;
}
function getBadgeClass(status) {
  const m = { new:'badge-new', in_progress:'badge-progress', completed:'badge-completed' };
  return m[status] || '';
}

/** Order Details */
function initOrderDetails() {
  const id = new URLSearchParams(window.location.search).get('id') || '#ORD-9021';
  const o = TawassulServices.MOCK_ORDERS.find(x => x.id === id);
  if (!o) return;
  
  if(document.getElementById('oId')) document.getElementById('oId').textContent = o.id;
  if(document.getElementById('oService')) document.getElementById('oService').textContent = o.serviceName;
  if(document.getElementById('oDate')) document.getElementById('oDate').textContent = o.date;
  if(document.getElementById('oTotal')) document.getElementById('oTotal').textContent = o.total + ' ر.س';
  if(document.getElementById('oStatus')) {
    document.getElementById('oStatus').textContent = getStatusText(o.status);
    document.getElementById('oStatus').className = `badge ${getBadgeClass(o.status)}`;
  }

  // Timeline UI Mock
  const steps = document.querySelectorAll('.timeline-step');
  let reachIdx = 0;
  if(o.status === 'in_progress') reachIdx = 1;
  if(o.status === 'completed') reachIdx = 3;

  steps.forEach((st, idx) => {
    if(idx <= reachIdx) {
      st.classList.add('active');
    }
  });
}
