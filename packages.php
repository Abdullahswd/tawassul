<?php
require_once __DIR__ . '/config/db.php';

$db = db();

$packages = [];
try {
    $stmt = $db->prepare("SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $row['features'] = json_decode($row['features_json'], true) ?? [];
        $packages[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching packages: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>باقات الخدمات - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
  <style>
    /* تحسينات التصميم */
    .packages-header {
      background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.05) 100%);
      border-radius: 48px;
      padding: 48px 24px;
      margin-bottom: 48px;
    }
    .pkg-card {
      padding: 36px 20px;
      border-radius: 32px;
      transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      display: flex;
      flex-direction: column;
      position: relative;
      overflow: hidden;
      backdrop-filter: blur(2px);
    }
    .pkg-card:hover {
      transform: translateY(-10px) scale(1.02);
      box-shadow: 0 30px 40px -15px rgba(0, 0, 0, 0.2);
      border-color: var(--primary);
    }
    .pkg-icon {
      width: 80px;
      height: 80px;
      background: var(--primary-light);
      border-radius: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 42px;
      margin: 0 auto 24px;
      transition: all 0.3s;
    }
    .pkg-card:hover .pkg-icon {
      transform: scale(1.05);
      background: var(--primary);
      color: white;
    }
    .pkg-popular {
      border: 2px solid var(--primary);
      box-shadow: 0 10px 30px -10px rgba(79, 70, 229, 0.3);
      position: relative;
      background: linear-gradient(145deg, var(--bg-card), rgba(79, 70, 229, 0.02));
    }
    .popular-badge {
      position: absolute;
      top: 16px;
      right: 16px;
      background: var(--primary);
      color: white;
      padding: 6px 14px;
      border-radius: 40px;
      font-size: 12px;
      font-weight: 800;
      z-index: 2;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .pkg-price {
      font-size: 48px;
      font-weight: 900;
      color: var(--text-primary);
      margin: 20px 0;
      display: inline-flex;
      align-items: baseline;
      gap: 4px;
    }
    .price-currency {
      font-size: 20px;
      font-weight: 600;
      color: var(--text-muted);
    }
    .pkg-list {
      text-align: right;
      margin: 20px 0 32px;
      flex-grow: 1;
      list-style: none;
      padding: 0;
    }
    .pkg-list li {
      margin-bottom: 14px;
      color: var(--text-secondary);
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      line-height: 1.5;
    }
    .pkg-list li::before {
      content: "✔️";
      display: inline-block;
      width: 20px;
      color: #10b981;
      font-weight: bold;
    }
    .delivery-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--bg-main);
      padding: 6px 14px;
      border-radius: 40px;
      font-size: 12px;
      font-weight: 600;
      margin-top: 8px;
      color: var(--text-secondary);
      border: 1px solid var(--border-color);
    }
    .btn-package {
      transition: all 0.3s;
      font-weight: 700;
      letter-spacing: 0.3px;
    }
    .btn-package:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    .fade-up {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }
    @media (max-width: 640px) {
      .pkg-card {
        padding: 28px 16px;
      }
      .pkg-price {
        font-size: 36px;
      }
    }
  </style>
</head>
<body>

  <nav class="public-nav">
    <div style="display:flex;align-items:center;gap:12px;font-size:24px;font-weight:900;color:var(--primary)">
      🎓 <span style="color:var(--text-primary)">تواصل</span>
    </div>
    <div class="nav-links hidden md:flex">
      <a href="index.php#features">المميزات</a>
      <a href="index.php#how-it-works">كيف نعمل</a>
      <a href="index.php#academics">أكاديميونا</a>
      <a href="packages.php">الباقات</a>
      <a href="reviews.php">التقييمات</a>
    </div>
    <div style="display:flex;gap:12px">
      <button class="dark-toggle" style="background:none;border:none;font-size:20px;cursor:pointer">🌙</button>
      <a href="login.php" class="btn btn-outline" style="padding:8px 16px;font-size:14px">دخول</a>
      <a href="register.php" class="btn btn-primary" style="padding:8px 16px;font-size:14px">إنشاء حساب</a>
    </div>
  </nav>

  <div style="padding:60px 20px;max-width:1400px;margin:0 auto">

    <!-- Header محسن -->
    <div class="packages-header fade-up" style="text-align:center">
      <span style="display:inline-block;background:var(--primary-light);color:var(--primary);padding:6px 18px;border-radius:40px;font-size:13px;font-weight:700;margin-bottom:20px">اختر مسارك الأكاديمي</span>
      <h1 style="font-size:44px;font-weight:900;margin-bottom:20px;background: linear-gradient(135deg, var(--primary), #dc5ab1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
        باقات الخدمات الأكاديمية الاحترافية
      </h1>
      <p style="font-size:18px;color:var(--text-secondary);max-width:700px;margin:0 auto;line-height:1.8">
        لقد صممنا باقاتنا لتغطي كافة احتياجاتك البحثية من الفكرة وحتى النشر. اختر الحزمة التي تناسب مرحلة بحثك الحالية بخصومات حصرية.
      </p>
    </div>

    <!-- الباقات -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(290px, 1fr));gap:32px">
      <?php if (empty($packages)): ?>
        <div class="text-center col-span-full py-12">
          <p class="text-gray-500">لا توجد باقات متاحة حالياً، يرجى العودة لاحقاً.</p>
        </div>
      <?php else: 
        $maxPrice = max(array_column($packages, 'price'));
        foreach ($packages as $index => $pkg): 
          $isPopular = ($pkg['price'] == $maxPrice);
          $cardClass = "pkg-card fade-up " . ($isPopular ? "pkg-popular" : "");
          $delay = 0.1 + ($index * 0.1);
          $primaryColor = !empty($pkg['color']) ? $pkg['color'] : 'var(--primary)';
      ?>
      <div class="<?= $cardClass ?>" style="animation-delay: <?= $delay ?>s; <?= $isPopular ? 'border-color: ' . $primaryColor . ';' : '' ?>">
        <?php if ($isPopular): ?>
          <div class="popular-badge">⭐ الأكثر تميزاً</div>
        <?php endif; ?>
        
        <div class="pkg-icon" style="<?= $isPopular ? 'background: ' . $primaryColor . '20; color:' . $primaryColor : '' ?>">
          <?php 
            $icon = '📦';
            $nameLower = mb_strtolower($pkg['name']);
            if (strpos($nameLower, 'بداية') !== false) $icon = '🏗️';
            elseif (strpos($nameLower, 'تطوير') !== false) $icon = '📝';
            elseif (strpos($nameLower, 'تحليل') !== false) $icon = '📊';
            elseif (strpos($nameLower, 'تسليم') !== false) $icon = '🔍';
            elseif (strpos($nameLower, 'نخبة') !== false || strpos($nameLower, 'vip') !== false) $icon = '👑';
            echo $icon;
          ?>
        </div>
        <h3 style="font-size:24px;font-weight:800; <?= $isPopular ? 'color: ' . $primaryColor . ';' : '' ?>"><?= htmlspecialchars($pkg['name']) ?></h3>
        <?php if (!empty($pkg['delivery_days'])): ?>
          <div class="delivery-badge">⏱️ التسليم خلال <?= (int)$pkg['delivery_days'] ?> يوم</div>
        <?php endif; ?>
        <div class="pkg-price">
          <?= number_format($pkg['price'], 0) ?>
          <span class="price-currency">ر.س</span>
        </div>

        <ul class="pkg-list">
          <?php 
            $features = $pkg['features'];
            if (empty($features)) {
              echo '<li>لا توجد مميزات محددة</li>';
            } else {
              foreach ($features as $feature) {
                echo '<li>' . htmlspecialchars($feature) . '</li>';
              }
            }
          ?>
          <?php if (!empty($pkg['max_tasks']) && $pkg['max_tasks'] !== null): ?>
            <li><strong>📋 المهام القصوى:</strong> <?= (int)$pkg['max_tasks'] ?></li>
          <?php elseif ($pkg['max_tasks'] === null): ?>
            <li><strong>♾️ مهام غير محدودة</strong></li>
          <?php endif; ?>
        </ul>

        <button class="btn <?= $isPopular ? 'btn-primary' : 'btn-outline' ?> btn-package" style="width:100%; <?= $isPopular ? 'background: ' . $primaryColor . '; border-color: ' . $primaryColor . ';' : '' ?>" 
                onclick="alert('جاري الانتقال لعملية طلب الباقة: <?= htmlspecialchars($pkg['name']) ?>')">
          اطلب الباقة الآن 🚀
        </button>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- قسم معلومات إضافية محسن -->
    <div style="margin-top:80px;background:linear-gradient(145deg, var(--bg-card), rgba(79, 70, 229, 0.03));border:1px solid var(--border-color);border-radius:48px;padding:48px 24px;text-align:center" class="fade-up">
      <h4 style="font-size:24px;font-weight:800;margin-bottom:16px">✨ لماذا تختار باقاتنا المجمعة؟</h4>
      <p style="color:var(--text-secondary);font-size:16px;max-width:800px;margin:0 auto 24px">
        توفر لك الباقات المجمعة خصماً يصل إلى 30% مقارنة بطلب كل خدمة على حدة، بالإضافة إلى أولوية في التنفيذ ومتابعة مستمرة من مدير حساب مخصص لمتابعة رحلتك الأكاديمية.
      </p>
      <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
        <a href="services.php" class="btn btn-link">تصفح الخدمات الفردية ←</a>
        <a href="contact.php" class="btn btn-outline">تواصل مع مستشارنا 💬</a>
      </div>
    </div>

  </div>

  <script src="assets/js/global.js"></script>
  <script>
    // تأثير الظهور عند التمرير
    const fadeElements = document.querySelectorAll('.fade-up');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    fadeElements.forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(30px)';
      observer.observe(el);
    });
  </script>
</body>
</html>