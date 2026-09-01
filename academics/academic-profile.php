<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

$db = db();

// Get academic by ID or default to first approved academic
$academicId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($academicId <= 0) {
    $firstAc = $db->query("SELECT id FROM academics WHERE status = 'approved' ORDER BY id ASC LIMIT 1")->fetchColumn();
    $academicId = $firstAc ? (int)$firstAc : 1;
}

$academic = getAcademicById($academicId);
if (!$academic || $academic['status'] !== 'approved') {
    die("الأكاديمي غير موجود أو غير معتمد.");
}

// Fetch qualifications
$qStmt = $db->prepare("SELECT * FROM academic_qualifications WHERE academic_id = ? ORDER BY graduation_year ASC");
$qStmt->execute([$academicId]);
$quals = $qStmt->fetchAll();

// Fetch services
$srvStmt = $db->prepare("
    SELECT s.*
    FROM services s
    JOIN academic_services acs ON s.id = acs.service_id
    WHERE acs.academic_id = ?
");
$srvStmt->execute([$academicId]);
$servicesList = $srvStmt->fetchAll();

$servicesJson = [];
foreach ($servicesList as $s) {
    $servicesJson[] = [
        'id' => (int)$s['id'],
        'name' => $s['name'],
        'icon' => $s['icon'] ?? '🔬'
    ];
}

// Fetch reviews
$rStmt = $db->prepare("
    SELECT r.*, u.name as student_name, u.avatar_initials
    FROM reviews r
    JOIN users u ON r.student_id = u.id
    WHERE r.academic_id = ?
    ORDER BY r.created_at DESC
");
$rStmt->execute([$academicId]);
$reviewsList = $rStmt->fetchAll();

$reviewsJson = [];
foreach ($reviewsList as $r) {
    $reviewsJson[] = [
        'id' => (int)$r['id'],
        'student' => $r['student_name'] ?? 'طالب',
        'avatar' => $r['avatar_initials'] ?? 'ط',
        'rating' => (int)$r['rating'],
        'text' => $r['comment'] ?? '',
        'date' => date('Y-m-d', strtotime($r['created_at']))
    ];
}

// Fetch similar approved academics
$simStmt = $db->prepare("SELECT * FROM academics WHERE status = 'approved' AND id != ? LIMIT 3");
$simStmt->execute([$academicId]);
$similarList = $simStmt->fetchAll();

$similarJson = [];
foreach ($similarList as $sim) {
    $similarJson[] = [
        'id' => (int)$sim['id'],
        'name' => $sim['name'],
        'avatar' => $sim['avatar_initials'] ?? mb_substr($sim['name'], 0, 2),
        'specialty' => $sim['specialty'] ?? 'عام',
        'rating' => (float)$sim['rating'],
        'color' => '#6366f1'
    ];
}

$academicName = $academic['name'];
$academicAvatar = $academic['avatar_initials'] ?? mb_substr($academicName, 0, 2);

// Map stats
$availabilityText = 'متاح حالياً';
$availabilityColor = '#10b981';
if ($academic['availability'] === 'busy') {
    $availabilityText = 'مشغول حالياً';
    $availabilityColor = '#f59e0b';
} elseif ($academic['availability'] === 'vacation') {
    $availabilityText = 'في إجازة';
    $availabilityColor = '#ef4444';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>الملف الشخصي للأكاديمي <?= e($academicName) ?> - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/style.css"/>
</head>
<body>

<!-- Header -->
<header class="public-header">
  <div class="flex items-center gap-3">
    <div class="logo-icon" style="font-size:22px">🎓</div>
    <span style="font-size:18px;font-weight:800;color:var(--text-primary)">تواصل</span>
  </div>
  <nav class="flex items-center gap-2 flex-wrap">
    <a href="academics-list.php" class="btn btn-ghost btn-sm">← الأكاديميون</a>
    <?php if (isLoggedIn()): ?>
      <?php if (isset($_SESSION['academic_id'])): ?>
        <a href="academic-dashboard.php" class="btn btn-primary btn-sm">لوحة التحكم</a>
      <?php else: ?>
        <a href="../student/student-dashboard.php" class="btn btn-primary btn-sm">لوحة التحكم</a>
      <?php endif; ?>
    <?php else: ?>
      <a href="../login.php" class="btn btn-primary btn-sm">تسجيل الدخول</a>
    <?php endif; ?>
    <button class="nav-btn dark-toggle" style="margin-right:8px">🌙</button>
  </nav>
</header>

<!-- Profile Hero -->
<div id="profileHero" style="background:linear-gradient(135deg,#1e1b4b,#312e81);padding:48px 32px 0;position:relative;overflow:hidden">
  <div style="position:absolute;inset:0;background:radial-gradient(circle at 20% 50%,rgba(99,102,241,.3),transparent 60%)"></div>
  <div style="max-width:1100px;margin:0 auto;position:relative">
    <div id="profileHeader" class="flex flex-col md:flex-row md:items-end gap-7 pb-0">
      <!-- Avatar -->
      <div id="profileAvatar" style="width:120px;height:120px;border-radius:24px;border:4px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:900;color:#fff;flex-shrink:0;box-shadow:0 8px 28px rgba(0,0,0,.3);background:#6366f1"><?= e($academicAvatar) ?></div>
      <!-- Info -->
      <div style="flex:1;padding-bottom:24px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
          <h1 id="profileName" style="font-size:28px;font-weight:900;color:#fff"><?= e($academicName) ?></h1>
          <span class="badge badge-success" style="background:rgba(16,185,129,.2);color:#6ee7b7">✓ موثّق</span>
        </div>
        <p id="profileSpec" style="color:rgba(255,255,255,.75);font-size:15px;margin-bottom:10px">📚 <?= e($academic['specialty']) ?> · 🎓 <?= e($academic['degree']) ?> · 🏛 <?= e($academic['university']) ?></p>
        <div class="flex items-center gap-5 flex-wrap">
          <span style="color:#f59e0b;font-weight:700;font-size:16px" id="profileRating">⭐ <?= number_format($academic['rating'], 1) ?> (<?= $academic['total_reviews'] ?> تقييم)</span>
          <span style="color:rgba(255,255,255,.65);font-size:14px" id="profileOrders">✅ <?= $academic['total_orders'] ?> طلب</span>
          <span style="color:rgba(255,255,255,.65);font-size:14px">🇸🇦 السعودية</span>
        </div>
      </div>
      <!-- Action box -->
      <div style="background:rgba(255,255,255,.08);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.15);border-radius:16px;padding:20px;min-width:220px;flex-shrink:0;margin-bottom:24px">
        <div style="font-size:28px;font-weight:900;color:#fff;margin-bottom:4px" id="profilePrice"><?= intval($academic['starting_price']) ?> ر.س</div>
        <div style="font-size:12px;color:rgba(255,255,255,.6);margin-bottom:16px">ابتداءً من / للطلب</div>
        <button class="btn btn-primary btn-block btn-lg" onclick="Modal.open('requestModal')" style="font-size:15px">📋 طلب خدمة</button>
      </div>
    </div>
    <!-- Tabs nav -->
    <div class="flex flex-wrap gap-1 mt-2" style="border-bottom:none">
      <button class="profile-tab-btn active" data-ptab="about" onclick="switchPTab('about',this)">نبذة</button>
      <button class="profile-tab-btn" data-ptab="quals" onclick="switchPTab('quals',this)">المؤهلات</button>
      <button class="profile-tab-btn" data-ptab="services" onclick="switchPTab('services',this)">الخدمات</button>
      <button class="profile-tab-btn" data-ptab="reviews" onclick="switchPTab('reviews',this)">التقييمات</button>
    </div>
  </div>
</div>

<!-- Main body -->
<div style="max-width:1100px;margin:0 auto;padding:32px">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-7">

    <!-- Left (Tab Panels) -->
    <div class="lg:col-span-2">

      <!-- About -->
      <div id="ptab-about" class="ptab-panel active">
        <div class="card" style="padding:24px;margin-bottom:20px">
          <h2 style="font-size:18px;font-weight:700;color:var(--text-primary);margin-bottom:14px">👤 نبذة تعريفية</h2>
          <p id="profileBio" style="color:var(--text-secondary);line-height:1.9;font-size:15px"><?= e($academic['bio']) ?></p>
        </div>

        <!-- Quick stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
          <div class="card" style="padding:20px;text-align:center">
            <div style="font-size:26px;font-weight:900;color:var(--primary)" data-counter="<?= $academic['total_orders'] ?>">0</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:4px">إجمالي الطلبات</div>
          </div>
          <div class="card" style="padding:20px;text-align:center">
            <div style="font-size:26px;font-weight:900;color:var(--success)">98%</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:4px">نسبة الإتمام</div>
          </div>
          <div class="card" style="padding:20px;text-align:center">
            <div style="font-size:26px;font-weight:900;color:#f59e0b">⭐ <?= number_format($academic['rating'], 1) ?></div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:4px">متوسط التقييم</div>
          </div>
        </div>
      </div>

      <!-- Qualifications -->
      <div id="ptab-quals" class="ptab-panel" style="display:none">
        <div class="card" style="padding:24px">
          <h2 style="font-size:18px;font-weight:700;color:var(--text-primary);margin-bottom:20px">🎓 المؤهلات الأكاديمية</h2>

          <?php if (empty($quals)): ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px;">لا توجد مؤهلات مسجلة في ملف الأكاديمي.</p>
          <?php else: ?>
            <?php foreach ($quals as $q): ?>
              <?php
              $icon = '🏫';
              $color = 'var(--primary)';
              $bg = 'rgba(99,102,241,.1)';
              if ($q['level'] === 'ماجستير') {
                  $icon = '📜';
                  $color = 'var(--success)';
                  $bg = 'rgba(16,185,129,.1)';
              } elseif ($q['level'] === 'دكتوراه') {
                  $icon = '🎓';
                  $color = 'var(--warning)';
                  $bg = 'rgba(245,158,11,.1)';
              }
              ?>
              <div class="flex flex-col sm:flex-row gap-4 p-4 mb-3" style="background:var(--bg-main);border-radius:14px">
                <div style="width:48px;height:48px;border-radius:12px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0"><?= $icon ?></div>
                <div>
                  <div style="font-size:12px;font-weight:600;color:<?= $color ?>;letter-spacing:1px;margin-bottom:4px"><?= e($q['level']) ?></div>
                  <div style="font-size:16px;font-weight:700;color:var(--text-primary)"><?= e($q['field']) ?></div>
                  <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">🏛 <?= e($q['university']) ?> · <?= e($q['country'] ?? 'السعودية') ?> · <?= e($q['graduation_year']) ?></div>
                </div>
                <div style="margin-right:auto">
                  <?php if ($q['verified']): ?>
                    <span class="badge badge-success">موثّق ✓</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">قيد التحقق</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </div>

      <!-- Services -->
      <div id="ptab-services" class="ptab-panel" style="display:none">
        <div class="card" style="padding:24px">
          <h2 style="font-size:18px;font-weight:700;color:var(--text-primary);margin-bottom:20px">⚙️ الخدمات المقدَّمة</h2>
          <div id="profileServices" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
        </div>
      </div>

      <!-- Reviews -->
      <div id="ptab-reviews" class="ptab-panel" style="display:none">
        <div class="card" style="padding:24px">
          <div class="flex items-center justify-between flex-wrap gap-4 mb-5">
            <h2 style="font-size:18px;font-weight:700;color:var(--text-primary)">⭐ التقييمات</h2>
            <div style="text-align:left">
              <div style="font-size:40px;font-weight:900;color:#f59e0b;line-height:1"><?= number_format($academic['rating'], 1) ?></div>
              <div style="font-size:12px;color:var(--text-secondary)">(<?= $academic['total_reviews'] ?> تقييم)</div>
            </div>
          </div>
          <div id="reviewsList"></div>
        </div>
      </div>

    </div>

    <!-- Right sidebar -->
    <div class="flex flex-col gap-5">

      <!-- Contact -->
      <div class="card" style="padding:20px">
        <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:16px">📞 التواصل</h3>
        <button class="btn btn-success btn-block" onclick="Toast.show('سيتواصل معك الأكاديمي عبر واتساب: <?= e($academic['phone']) ?>','success')">💬 واتساب</button>
      </div>

      <!-- Availability -->
      <div class="card" style="padding:20px">
        <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:14px">📅 التوفر</h3>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $availabilityColor ?>;display:inline-block"></span>
          <span style="font-size:14px;color:var(--text-primary)"><?= $availabilityText ?></span>
        </div>
        <div style="font-size:13px;color:var(--text-secondary)">⏰ وقت الرد: أقل من ساعة</div>
      </div>

      <!-- Similar -->
      <div class="card" style="padding:20px">
        <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:14px">🎓 أكاديميون مشابهون</h3>
        <div id="similarAcademics"></div>
      </div>

    </div>
  </div>
</div>

<!-- Request Service Modal -->
<div class="modal-overlay" id="requestModal">
  <div class="modal-box" style="max-width:540px">
    <div class="modal-header">
      <h3 class="modal-title">📋 طلب خدمة من <?= e($academicName) ?></h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">الخدمة المطلوبة</label>
        <select class="form-input form-select" id="requestServiceSelect" style="padding-left:36px" required>
          <option value="">اختر الخدمة</option>
          <?php foreach ($servicesList as $os): ?>
            <option value="<?= $os['id'] ?>"><?= e($os['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">الباقة</label>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-2" id="packageSelector">
          <label style="border:2px solid var(--border-color);border-radius:10px;padding:12px;text-align:center;cursor:pointer;transition:all .2s" onclick="selectPkg(this, 'البداية')">
            <div style="font-weight:700;color:var(--text-primary)">البداية</div>
            <div style="font-size:16px;font-weight:800;color:var(--primary);margin-top:4px">149 ر.س</div>
          </label>
          <label style="border:2px solid var(--primary);border-radius:10px;padding:12px;text-align:center;cursor:pointer;background:rgba(99,102,241,.05)" onclick="selectPkg(this, 'التطوير')">
            <div style="font-weight:700;color:var(--primary)">التطوير</div>
            <div style="font-size:16px;font-weight:800;color:var(--primary);margin-top:4px">349 ر.س</div>
            <div style="font-size:10px;color:var(--primary);margin-top:2px">الأشهر</div>
          </label>
          <label style="border:2px solid var(--border-color);border-radius:10px;padding:12px;text-align:center;cursor:pointer;transition:all .2s" onclick="selectPkg(this, 'النخبة')">
            <div style="font-weight:700;color:var(--text-primary)">النخبة</div>
            <div style="font-size:16px;font-weight:800;color:var(--primary);margin-top:4px">1999 ر.س</div>
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">تفاصيل الطلب ومتطلباتك</label>
        <textarea class="form-input" id="requestDesc" rows="4" placeholder="اشرح ما تحتاجه بالتفصيل..."></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">الموعد النهائي المطلوب</label>
        <input type="date" class="form-input" id="requestDeadline"/>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" onclick="submitRequest()">إرسال الطلب ودفع الرسوم 📋</button>
    </div>
  </div>
</div>

<style>
.profile-tab-btn{padding:12px 22px;background:none;border:none;border-bottom:3px solid transparent;margin-bottom:-3px;font-family:Tajawal,sans-serif;font-size:14px;font-weight:600;color:rgba(255,255,255,.6);cursor:pointer;transition:all .2s}
.profile-tab-btn:hover{color:rgba(255,255,255,.9)}
.profile-tab-btn.active{color:#fff;border-bottom-color:#fff}
.ptab-panel{display:none}
.ptab-panel.active{display:block;animation:fadeIn .3s ease}
</style>

<script src="assets/js/main.js"></script>
<script>
// Load dynamic profile details
window.ACADEMICS_DATA.services = <?= json_encode($servicesJson, JSON_UNESCAPED_UNICODE) ?>;
window.ACADEMICS_DATA.reviews = <?= json_encode($reviewsJson, JSON_UNESCAPED_UNICODE) ?>;
window.ACADEMICS_DATA.academics = [
  <?= json_encode([
      'id' => (int)$academicId,
      'name' => $academicName,
      'avatar' => $academicAvatar,
      'specialty' => $academic['specialty'],
      'degree' => $academic['degree'],
      'university' => $academic['university'],
      'services' => array_map('intval', array_column($servicesJson, 'id')),
      'price' => (float)$academic['starting_price']
  ], JSON_UNESCAPED_UNICODE) ?>
].concat(<?= json_encode($similarJson, JSON_UNESCAPED_UNICODE) ?>);

function switchPTab(tab, btn) {
  document.querySelectorAll('.ptab-panel').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.profile-tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('ptab-' + tab).style.display = 'block';
  btn.classList.add('active');
}

let selectedPkgName = 'التطوير';
function selectPkg(el, name) {
  el.closest('.modal-body').querySelectorAll('#packageSelector label').forEach(l => {
    l.style.borderColor = 'var(--border-color)';
    l.style.background = 'transparent';
    const d = l.querySelector('div');
    if (d) d.style.color = 'var(--text-primary)';
  });
  el.style.borderColor = 'var(--primary)';
  el.style.background = 'rgba(99,102,241,0.05)';
  const d = el.querySelector('div');
  if (d) d.style.color = 'var(--primary)';
  selectedPkgName = name;
}

function submitRequest() {
  const serviceId = document.getElementById('requestServiceSelect').value;
  const desc = document.getElementById('requestDesc').value;
  const deadline = document.getElementById('requestDeadline').value;

  if (!serviceId || !desc || !deadline) {
    Toast.show('يرجى ملء كافة حقول النموذج', 'error');
    return;
  }

  const formData = new FormData();
  formData.append('academic_id', <?= $academicId ?>);
  formData.append('service_id', serviceId);
  formData.append('description', desc);
  formData.append('deadline', deadline);
  formData.append('package_type', selectedPkgName);

  fetch('ajax/handler.php?action=create_order', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      Toast.show(data.message, 'success', 4000);
      Modal.close('requestModal');
      document.getElementById('requestDesc').value = '';
      document.getElementById('requestDeadline').value = '';
      setTimeout(() => location.reload(), 1800);
    } else {
      Toast.show(data.message, 'error');
    }
  });
}

function renderProfileServices() {
  const academic = ACADEMICS_DATA.academics[0];
  const container = document.getElementById('profileServices');
  if (!container) return;
  if (academic.services.length === 0) {
    container.innerHTML = '<p style="color:var(--text-secondary);grid-column:span 2;text-align:center">لا توجد خدمات محددة.</p>';
    return;
  }
  container.innerHTML = academic.services.map(sId => {
    const s = ACADEMICS_DATA.services.find(x => x.id === sId);
    if (!s) return '';
    return `<div style="display:flex;align-items:center;gap:12px;padding:14px;background:var(--bg-main);border-radius:12px;border:1px solid var(--border-color)"><span style="font-size:24px">${s.icon}</span><span style="font-size:14px;font-weight:600;color:var(--text-primary)">${s.name}</span></div>`;
  }).join('');
}

function renderReviews() {
  const list = document.getElementById('reviewsList');
  if (!list) return;
  if (ACADEMICS_DATA.reviews.length === 0) {
    list.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:24px">لا توجد تقييمات سابقة بعد.</p>';
    return;
  }
  list.innerHTML = ACADEMICS_DATA.reviews.map((r, i) => `
    <div style="padding:18px;background:var(--bg-main);border-radius:14px;margin-bottom:12px">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <div style="width:40px;height:40px;border-radius:50%;background:${getAvatarColor(i)};display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#fff">${r.avatar}</div>
        <div style="flex:1">
          <div style="font-weight:700;color:var(--text-primary)">${r.student}</div>
          <div style="font-size:12px;color:var(--text-secondary)">${r.date}</div>
        </div>
        <div style="color:#f59e0b;font-weight:700">⭐ ${r.rating}</div>
      </div>
      <p style="font-size:14px;color:var(--text-secondary);line-height:1.7">${r.text}</p>
    </div>
  `).join('');
}

function renderSimilar() {
  const list = document.getElementById('similarAcademics');
  if (!list) return;
  const similar = ACADEMICS_DATA.academics.slice(1);
  if (similar.length === 0) {
    list.innerHTML = '<p style="font-size:13px;color:var(--text-secondary)">لا يوجد أكاديميون مشابهون حالياً.</p>';
    return;
  }
  list.innerHTML = similar.map((a, i) => `
    <a href="academic-profile.php?id=${a.id}" style="display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px;transition:background .2s;text-decoration:none" onmouseover="this.style.background='var(--bg-main)'" onmouseout="this.style.background='transparent'">
      <div style="width:42px;height:42px;border-radius:50%;background:${a.color};display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff;flex-shrink:0">${a.avatar}</div>
      <div style="min-width:0">
        <div style="font-size:13px;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${a.name}</div>
        <div style="font-size:11px;color:var(--text-secondary)">⭐ ${a.rating.toFixed(1)} · ${a.specialty.slice(0,16)}...</div>
      </div>
    </a>
  `).join('');
}

document.getElementById('ptab-about').style.display = 'block';

document.addEventListener('DOMContentLoaded', () => {
  renderProfileServices();
  renderReviews();
  renderSimilar();
});
</script>
</body>
</html>
