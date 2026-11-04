<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

// التحقق مما إذا كان الزائر أكاديمياً مسجل الدخول
$isAcademic = (isset($_SESSION['academic_id']) && ($_SESSION['user_role'] ?? '') === 'academic');

$db = db();
$items = $db->query("SELECT * FROM policy_items WHERE policy_type = 'academic' ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>الشروط والأحكام وسياسة الاستخدام للأكاديميين - Eduroad</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/style.css"/>
  <style>
    .policy-card { 
      background: var(--bg-card); 
      border-radius: 16px; 
      padding: 26px; 
      margin-bottom: 20px; 
      box-shadow: var(--shadow); 
      border: 1px solid var(--border-color); 
      display: flex; 
      gap: 20px; 
      align-items: flex-start;
      transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; 
    }
    .policy-card:hover { 
      transform: translateY(-3px); 
      box-shadow: 0 10px 28px rgba(99, 102, 241, 0.12); 
      border-color: rgba(99, 102, 241, 0.3); 
    }
    .policy-icon { 
      width: 52px; 
      height: 52px; 
      border-radius: 14px; 
      background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(129,140,248,0.12)); 
      color: var(--primary); 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      font-size: 26px; 
      flex-shrink: 0; 
    }
    .policy-content h3 { 
      font-size: 18px; 
      font-weight: 700; 
      color: var(--text-primary); 
      margin-bottom: 10px; 
      font-family: 'Tajawal', sans-serif; 
    }
    .policy-content p { 
      font-size: 15px; 
      color: var(--text-secondary); 
      line-height: 1.85; 
      margin: 0; 
    }
  </style>
</head>
<body>

<?php if ($isAcademic): ?>
  <div class="mobile-overlay" id="mobileOverlay"></div>
  <div class="page-layout">
    <?php include 'components/academic-sidebar.php'; ?>
    <div class="main-content" id="mainContent">
      <?php include 'components/academic-navbar.php'; ?>
      <div class="page-body">
        <div class="page-header anim-up">
          <div>
            <div class="breadcrumb"><a href="academic-dashboard.php">الرئيسية</a><span>›</span><span>الشروط والأحكام والسياسات</span></div>
            <h1 class="page-title">شروط وأحكام وسياسة الاستخدام للأكاديميين 📜</h1>
          </div>
        </div>
        
        <div class="anim-up delay-1">
          <?php if (empty($items)): ?>
            <div style="text-align:center; padding:50px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border-color); color:var(--text-secondary);">
              لا توجد بنود حالياً.
            </div>
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

<?php else: ?>
  <!-- Header للزوار والمتقدمين الجدد بدون تسجيل دخول -->
  <header style="background:var(--navbar-bg); border-bottom:1px solid var(--border-color); position:sticky; top:0; z-index:50;">
    <div style="max-width:1000px; margin:0 auto; padding:16px 20px; display:flex; align-items:center; justify-content:space-between;">
      <a href="../index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
        <img src="../image/eduroad_logo.png" alt="Eduroad" style="height:34px; width:auto; object-fit:contain;">
        <span style="font-weight:900; font-size:20px; color:var(--text-primary);">Eduroad</span>
      </a>

      <div style="display:flex; align-items:center; gap:10px;">
        <button class="dark-toggle" aria-label="تبديل المظهر" style="width:38px; height:38px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-main); cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center;">🌙</button>
        <a href="../login.php" style="color:var(--text-primary); text-decoration:none; font-weight:700; font-size:14px; padding:8px 16px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-card);">تسجيل الدخول</a>
        <a href="academic-register.php" style="background:var(--primary); color:#ffffff; text-decoration:none; font-weight:700; font-size:14px; padding:8px 18px; border-radius:10px; box-shadow:0 4px 12px rgba(79, 70, 229, 0.25);">تسجيل أكاديمي</a>
      </div>
    </div>
  </header>

  <!-- المحتوى الرئيسي للزوار -->
  <main style="min-height:calc(100vh - 140px); padding:40px 20px 60px; background:var(--bg-main);">
    <div style="max-width:850px; margin:0 auto;">
      
      <div style="text-align:center; margin-bottom:36px;">
        <div style="display:inline-flex; align-items:center; justify-content:center; width:60px; height:60px; border-radius:18px; background:rgba(99,102,241,0.12); color:var(--primary); font-size:28px; margin-bottom:14px;">
          📜
        </div>
        <h1 style="font-size:28px; font-weight:900; color:var(--text-primary); margin-bottom:10px;">شروط وأحكام وسياسة الاستخدام للأكاديميين</h1>
        <p style="font-size:15px; color:var(--text-secondary); max-width:600px; margin:0 auto; line-height:1.75;">
          نلتزم في منصة Eduroad بأعلى معايير الشفافية والموثوقية لحفظ حقوق الأكاديميين وتوضيح ضوابط تقديم الخدمات والالتزامات المالية والمهنية.
        </p>
      </div>

      <?php if (empty($items)): ?>
        <div style="text-align:center; padding:50px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border-color); color:var(--text-secondary);">
          لا توجد بنود سياسة مضافة حالياً.
        </div>
      <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:18px;">
          <?php foreach ($items as $idx => $item): 
             $icon = !empty($item['icon']) ? $item['icon'] : '📌';
          ?>
          <div class="policy-card" style="margin-bottom:0;">
            <div class="policy-icon"><?= $icon ?></div>
            <div class="policy-content">
              <h3><?= e($item['title']) ?></h3>
              <p><?= nl2br(e($item['content'])) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- دعوة للمتابعة أو التسجيل -->
      <div style="margin-top:40px; text-align:center; padding:32px 20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:18px; box-shadow:var(--shadow);">
        <h3 style="font-size:18px; font-weight:800; color:var(--text-primary); margin-bottom:8px;">مستعد للانضمام لنخبة الأكاديميين في Eduroad؟</h3>
        <p style="font-size:14px; color:var(--text-secondary); margin-bottom:20px;">قدّم طلب انضمامك الآن وشارك خبراتك مع الطلاب والباحثين في بيئة عمل أكاديمية احترافية.</p>
        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
          <a href="academic-register.php" style="background:var(--primary); color:#ffffff; text-decoration:none; font-weight:800; font-size:14px; padding:11px 26px; border-radius:25px; box-shadow:0 4px 12px rgba(79, 70, 229, 0.25);">
            إكمال طلب التسجيل 🚀
          </a>
          <a href="../login.php" style="background:var(--bg-main); color:var(--text-primary); text-decoration:none; font-weight:700; font-size:14px; padding:11px 22px; border-radius:25px; border:1px solid var(--border-color);">
            تسجيل الدخول
          </a>
        </div>
      </div>

      <div style="text-align:center; margin-top:32px; color:var(--text-secondary); font-size:13px;">
        جميع الحقوق محفوظة &copy; <?= date('Y') ?> منصة Eduroad
      </div>

    </div>
  </main>
<?php endif; ?>

<script src="assets/js/main.js"></script>
</body>
</html>
