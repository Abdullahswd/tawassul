    <?php
    require_once __DIR__ . '/../config/auth.php';
    require_once __DIR__ . '/../config/functions.php';
    requireAcademic();

    $db = db();
    $items = $db->query("SELECT * FROM policy_items WHERE policy_type = 'academic' ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
      <meta charset="UTF-8"/>
      <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
      <title>سياسة الاستخدام والخصوصية - لوحة تحكم الأكاديمي</title>
      <script src="https://cdn.tailwindcss.com"></script>
      <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet"/>
      <link rel="stylesheet" href="assets/css/style.css"/>
      <style>
        .policy-card { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.04); display: flex; gap: 20px; transition: transform 0.2s, box-shadow 0.2s; }
        .policy-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(99, 102, 241, 0.08); border-color: rgba(99, 102, 241, 0.2); }
        .policy-icon { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(129,140,248,0.1)); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        .policy-content h3 { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 10px; font-family: Tajawal, sans-serif; }
        .policy-content p { font-size: 15px; color: #64748b; line-height: 1.8; margin: 0; }
      </style>
    </head>
    <body>
    <div class="mobile-overlay" id="mobileOverlay"></div>
    <div class="page-layout">
      <?php include 'components/academic-sidebar.php'; ?>
      <div class="main-content" id="mainContent">
        <?php include 'components/academic-navbar.php'; ?>
        <div class="page-body">
          <div class="page-header anim-up">
            <div>
              <div class="breadcrumb"><a href="academic-dashboard.php">الرئيسية</a><span>›</span><span>سياسة الاستخدام والخصوصية</span></div>
              <h1 class="page-title">سياسة الاستخدام والخصوصية للأكاديمي</h1>
            </div>
          </div>
          
          <div class="anim-up delay-1">
            <?php if (empty($items)): ?>
              <div style="text-align:center; padding:50px; background:#fff; border-radius:12px; color:#94a3b8;">لا توجد بنود حالياً.</div>
            <?php else: ?>
              <?php foreach ($items as $idx => $item): 
                $icon = !empty($item['icon']) ? $item['icon'] : '📌';
              ?>
              <div class="policy-card" style="animation-delay: <?= ($idx * 0.1) ?>s">
                <div class="policy-icon"><?= $icon ?></div>
                <div class="policy-content">
                  <h3><?= e($item['title']) ?></h3>
                  <p><?= nl2br(e($item['content'])) ?></p>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          
        </div>
      </div>
    </div>
    <script src="assets/js/main.js"></script>
    </body>
    </html>
