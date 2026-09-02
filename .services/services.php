<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>الخدمات الأكاديمية - منصة Eduroad</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <!-- Header -->
  <header class="public-header">
    <div style="display:flex;align-items:center;gap:12px">
      <img src="../image/eduroad_logo.png" alt="Eduroad" style="height:28px;width:auto" />
      <span style="font-size:20px;font-weight:800;color:var(--text-primary)">Eduroad للخدمات</span>
    </div>
    <nav style="display:flex;gap:16px;align-items:center">
      <a href="services.php" style="color:var(--primary);font-weight:700">الخدمات</a>
      <a href="orders.php" style="color:var(--text-secondary);font-weight:500;text-decoration:none">طلباتي</a>
      <div style="width:1px;height:24px;background:var(--border-color)"></div>
      <button class="nav-btn dark-toggle" style="background:none;border:none;cursor:pointer;font-size:20px">🌙</button>
      <a href="create-order.php" class="btn btn-primary">طلب خدمة جديد</a>
    </nav>
  </header>

  <!-- Hero -->
  <div style="background:linear-gradient(135deg, var(--primary), #818cf8);color:white;padding:60px 20px;text-align:center">
    <h1 style="font-size:36px;font-weight:800;margin-bottom:16px">بوابة الخدمات الأكاديمية المتكاملة</h1>
    <p style="font-size:18px;opacity:0.9;max-width:600px;margin:0 auto">كل ما تحتاجه لإنجاز أبحاثك ومشاريعك الأكاديمية باحترافية وجودة عالية في مكان واحد.</p>
  </div>

  <!-- Content -->
  <div style="max-width:1200px;margin:0 auto;padding:40px 20px">
    
    <!-- Filter Tabs (Injected via JS) -->
    <div id="categoriesFilter" class="filter-tabs" style="margin-bottom:32px">
      <!-- Tabs will be rendered here dynamically -->
    </div>

    <!-- Services Grid -->
    <div id="servicesGrid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:24px">
      <!-- Services will be rendered here dynamically -->
    </div>

  </div>

  <script src="data/services.js"></script>
  <script src="assets/js/main.js"></script>
</body>
</html>

