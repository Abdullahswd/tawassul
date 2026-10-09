<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$db = db();
$items = $db->query("SELECT * FROM policy_items WHERE policy_type = 'student' ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = 'سياسة الاستخدام والخصوصية';
$activePage = 'policy';
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

<?php require __DIR__ . '/partials/footer.php'; ?>
