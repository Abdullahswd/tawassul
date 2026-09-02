<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>طلباتي - منصة Eduroad</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .tbl { width: 100%; border-collapse: collapse; }
    .tbl th { background: var(--bg-main); font-size: 13px; color: var(--text-secondary); font-weight: 700; text-align: right; padding: 16px; border-bottom: 1px solid var(--border-color); }
    .tbl td { padding: 16px; font-size: 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
    .tbl tr:hover td { background: var(--bg-main); transition: background 0.2s; }
  </style>
</head>
<body>

  <!-- Header -->
  <header class="public-header">
    <div style="display:flex;align-items:center;gap:12px">
      <img src="../image/eduroad_logo.png" alt="Eduroad" style="height:28px;width:auto" />
      <span style="font-size:20px;font-weight:800;color:var(--text-primary)">Eduroad للخدمات</span>
    </div>
    <nav style="display:flex;gap:16px;align-items:center">
      <a href="services.php" style="color:var(--text-secondary);font-weight:500;text-decoration:none">الخدمات</a>
      <a href="orders.php" style="color:var(--primary);font-weight:700">طلباتي</a>
      <div style="width:1px;height:24px;background:var(--border-color)"></div>
      <button class="nav-btn dark-toggle" style="background:none;border:none;cursor:pointer;font-size:20px">🌙</button>
      <a href="create-order.php" class="btn btn-primary">طلب خدمة جديد</a>
    </nav>
  </header>

  <!-- Content -->
  <div style="max-width:1100px;margin:40px auto;padding:0 20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
      <div>
        <h1 style="font-size:28px;font-weight:900;color:var(--text-primary);margin-bottom:8px">إدارة طلباتي</h1>
        <p style="color:var(--text-secondary)">متابعة حالة الطلبات والتواصل مع الأكاديميين</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-tabs" style="margin-bottom:24px">
      <button class="filter-tab active" onclick="if(window.filterOrders) filterOrders(); this.parentNode.querySelectorAll('button').forEach(b=>b.classList.remove('active')); this.classList.add('active');">الكل</button>
      <button class="filter-tab" onclick="if(window.filterOrders) filterOrders('new'); this.parentNode.querySelectorAll('button').forEach(b=>b.classList.remove('active')); this.classList.add('active');">طلبات جديدة</button>
      <button class="filter-tab" onclick="if(window.filterOrders) filterOrders('in_progress'); this.parentNode.querySelectorAll('button').forEach(b=>b.classList.remove('active')); this.classList.add('active');">قيد التنفيذ</button>
      <button class="filter-tab" onclick="if(window.filterOrders) filterOrders('completed'); this.parentNode.querySelectorAll('button').forEach(b=>b.classList.remove('active')); this.classList.add('active');">مكتملة</button>
    </div>

    <!-- Table -->
    <div class="card" style="overflow-x:auto">
      <table class="tbl">
        <thead>
          <tr>
            <th>رقم الطلب</th>
            <th>الخدمة</th>
            <th>تاريخ الطلب</th>
            <th>الحالة</th>
            <th>الإجمالي</th>
            <th>إجراء</th>
          </tr>
        </thead>
        <tbody id="ordersTbody">
          <!-- Populated by JS -->
        </tbody>
      </table>
    </div>
  </div>

  <script src="data/services.js"></script>
  <script src="assets/js/main.js"></script>
</body>
</html>

