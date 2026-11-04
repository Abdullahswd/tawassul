<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

// التحقق مما إذا كان الزائر طالباً مسجل الدخول
$isStudent = (isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'student');
$user = $isStudent ? currentUser() : null;

$db = db();
$items = $db->query("SELECT * FROM policy_items WHERE policy_type = 'student' ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = 'سياسة الاستخدام والخصوصية';
$activePage = 'policy';

if ($isStudent) {
    require __DIR__ . '/partials/head.php';
    require __DIR__ . '/partials/sidebar.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:32px" class="anim-fade-up">
  <div>
    <h1 class="h1" style="margin-bottom:8px">سياسة الاستخدام والخصوصية 📜</h1>
    <p class="text-body">نلتزم في منصتنا بأعلى معايير الأمان لحماية بياناتك وحقوقك.</p>
  </div>
</div>

<div class="max-w-4xl">
  <?php if (empty($items)): ?>
    <div class="card text-center anim-fade-up" style="padding:40px; color:var(--text-secondary);">
      لا توجد بنود سياسة مضافة حالياً.
    </div>
  <?php else: ?>
    <?php foreach ($items as $idx => $item): 
       $icon = !empty($item['icon']) ? $item['icon'] : '📌';
    ?>
    <div class="card anim-fade-up" style="margin-bottom:20px; display:flex; gap:20px; align-items:flex-start; animation-delay:<?= ($idx * 0.1) ?>s;">
      <div style="width:54px; height:54px; border-radius:16px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:26px; flex-shrink:0;">
        <?= $icon ?>
      </div>
      <div>
        <h3 class="h3" style="margin-bottom:10px;"><?= e($item['title']) ?></h3>
        <p class="text-body" style="line-height:1.8; margin:0;"><?= nl2br(e($item['content'])) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php 
    require __DIR__ . '/partials/footer.php'; 
} else {
    // عرض مخصص للزوار بدون اشتراط تسجيل الدخول
    require __DIR__ . '/partials/head.php';
?>

  <!-- Header للزوار -->
  <header style="background:var(--bg-card); border-bottom:1px solid var(--border-color); position:sticky; top:0; z-index:50;">
    <div style="max-width:1000px; margin:0 auto; padding:16px 20px; display:flex; align-items:center; justify-content:space-between;">
      <a href="../index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
        <img src="../image/eduroad_logo.png" alt="Eduroad" style="height:34px; width:auto; object-fit:contain;">
        <span style="font-weight:900; font-size:20px; color:var(--text-primary);">Eduroad</span>
      </a>

      <div style="display:flex; align-items:center; gap:10px;">
        <button class="dark-toggle icon-btn" id="darkToggle" aria-label="تبديل المظهر" style="width:38px; height:38px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-hover); cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center;">🌙</button>
        <a href="../login.php" style="color:var(--text-primary); text-decoration:none; font-weight:700; font-size:14px; padding:8px 16px; border-radius:10px; border:1px solid var(--border-color); background:var(--bg-card);">تسجيل الدخول</a>
        <a href="../register.php" style="background:var(--primary); color:#ffffff; text-decoration:none; font-weight:700; font-size:14px; padding:8px 18px; border-radius:10px; box-shadow:var(--shadow-sm);">إنشاء حساب</a>
      </div>
    </div>
  </header>

  <!-- المحتوى الرئيسي للزوار -->
  <main style="min-height:calc(100vh - 140px); padding:40px 20px 60px; background:var(--bg-body);">
    <div style="max-width:850px; margin:0 auto;">
      
      <div style="text-align:center; margin-bottom:36px;" class="anim-fade-up">
        <div style="display:inline-flex; align-items:center; justify-content:center; width:60px; height:60px; border-radius:18px; background:var(--primary-light); color:var(--primary); font-size:28px; margin-bottom:14px;">
          📜
        </div>
        <h1 style="font-size:28px; font-weight:900; color:var(--text-primary); margin-bottom:10px;">سياسة الاستخدام والخصوصية</h1>
        <p style="font-size:15px; color:var(--text-secondary); max-width:580px; margin:0 auto; line-height:1.7;">
          نلتزم في منصة Eduroad بأعلى معايير الأمان والشفافية لحماية بياناتك وتوضيح حقوقك وواجباتك أثناء استخدام خدماتنا الأكاديمية.
        </p>
      </div>

      <?php if (empty($items)): ?>
        <div class="card text-center anim-fade-up" style="padding:40px; color:var(--text-secondary); background:var(--bg-card); border-radius:16px; border:1px solid var(--border-color);">
          لا توجد بنود سياسة مضافة حالياً.
        </div>
      <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:18px;">
          <?php foreach ($items as $idx => $item): 
             $icon = !empty($item['icon']) ? $item['icon'] : '📌';
          ?>
          <div class="card anim-fade-up" style="background:var(--bg-card); border:1px solid var(--border-color); border-radius:16px; padding:24px; display:flex; gap:20px; align-items:flex-start; animation-delay:<?= ($idx * 0.08) ?>s;">
            <div style="width:50px; height:50px; border-radius:14px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:24px; flex-shrink:0;">
              <?= $icon ?>
            </div>
            <div style="flex:1;">
              <h3 style="font-size:17px; font-weight:800; color:var(--text-primary); margin-bottom:8px;"><?= e($item['title']) ?></h3>
              <p style="font-size:14px; color:var(--text-secondary); line-height:1.8; margin:0;"><?= nl2br(e($item['content'])) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- دعوة للتسجيل في الأسفل -->
      <div class="anim-fade-up" style="margin-top:40px; text-align:center; padding:32px 20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:18px;">
        <h3 style="font-size:18px; font-weight:800; color:var(--text-primary); margin-bottom:8px;">جاهز لبدء رحلتك الأكاديمية؟</h3>
        <p style="font-size:14px; color:var(--text-secondary); margin-bottom:20px;">أنشئ حسابك الآن واستمتع بخدماتنا المتكاملة بكل سهولة وأمان.</p>
        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
          <a href="../register.php" style="background:var(--primary); color:#ffffff; text-decoration:none; font-weight:800; font-size:14px; padding:11px 26px; border-radius:25px;">
            إنشاء حساب جديد 🚀
          </a>
          <a href="../login.php" style="background:var(--bg-hover); color:var(--text-primary); text-decoration:none; font-weight:700; font-size:14px; padding:11px 22px; border-radius:25px; border:1px solid var(--border-color);">
            تسجيل الدخول
          </a>
        </div>
      </div>

      <div style="text-align:center; margin-top:32px; color:var(--text-muted); font-size:13px;">
        جميع الحقوق محفوظة &copy; <?= date('Y') ?> منصة Eduroad
      </div>

    </div>
  </main>

  <script src="assets/js/main.js"></script>
</body>
</html>
<?php 
}
?>
