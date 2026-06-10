<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAcademic();

$academicId = $_SESSION['academic_id'];
$academicData = getAcademicById($academicId);

$db = db();

// Selected services IDs for this academic
$selectedServices = $db->query("SELECT service_id FROM academic_services WHERE academic_id = $academicId")->fetchAll(PDO::FETCH_COLUMN);

// All services
$allServicesList = getAllServices(true);

// Qualifications for list
$qualsStmt = $db->prepare("SELECT * FROM academic_qualifications WHERE academic_id = ?");
$qualsStmt->execute([$academicId]);
$quals = $qualsStmt->fetchAll();

$academicName = $academicData['name'];
$academicAvatar = $academicData['avatar_initials'] ?? mb_substr($academicName, 0, 2);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>الإعدادات - لوحة تحكم الأكاديمي</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/style.css"/>
  <style>
    .s-menu-btn {
      display: flex; align-items: center; gap: 8px;
      width: 100%; padding: 11px 14px; border-radius: 10px;
      border: none; background: transparent; font-family: Tajawal, sans-serif;
      font-size: 14px; font-weight: 600; color: var(--text-secondary);
      cursor: pointer; text-align: right; transition: all .18s;
      margin-bottom: 2px;
    }
    .s-menu-btn:hover { background: var(--bg-main); color: var(--text-primary); }
    .s-menu-btn.active { background: rgba(99,102,241,.1); color: var(--primary); }
    .s-section { animation: fadeIn .25s ease; }
    @keyframes fadeIn { from { opacity:0; transform: translateY(6px); } to { opacity:1; transform: none; } }
  </style>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="page-layout">

  <!-- Sidebar -->
  <?php include 'components/academic-sidebar.php'; ?>

  <div class="main-content" id="mainContent">
    <?php include 'components/academic-navbar.php'; ?>

    <div class="page-body">
      <!-- Header -->
      <div class="page-header anim-up">
        <div>
          <div class="breadcrumb"><a href="academic-dashboard.php">الرئيسية</a><span>›</span><span>الإعدادات</span></div>
          <h1 class="page-title">إعدادات الحساب</h1>
          <p class="page-subtitle">إدارة بياناتك وتفضيلاتك الشخصية</p>
        </div>
        <button class="btn btn-primary" onclick="saveSettings()">💾 حفظ التغييرات</button>
      </div>

      <div style="display:grid;grid-template-columns:220px 1fr;gap:24px">

        <!-- Settings menu -->
        <div class="card anim-up delay-1" style="padding:10px;height:fit-content">
          <button class="s-menu-btn active" onclick="showSection('profile',this)">👤 البيانات الشخصية</button>
          <button class="s-menu-btn" onclick="showSection('services',this)">⚙️ الخدمات</button>
          <button class="s-menu-btn" onclick="showSection('qualifications',this)">🎓 المؤهلات</button>
          <button class="s-menu-btn" onclick="showSection('notifications',this)">🔔 الإشعارات</button>
          <button class="s-menu-btn" onclick="showSection('security',this)">🔒 كلمة المرور</button>
          <button class="s-menu-btn" onclick="showSection('bank',this)">🏦 البيانات البنكية</button>
        </div>

        <!-- Settings content -->
        <div>

          <!-- Profile section -->
          <div id="sec-profile" class="s-section">
            <div class="card" style="padding:26px;margin-bottom:18px">
              <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:18px">👤 البيانات الشخصية</h3>

              <!-- Avatar -->
              <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;padding:20px;background:var(--bg-main);border-radius:14px">
                <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#818cf8);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;color:#fff;position:relative">
                  <?= e($academicAvatar) ?>
                </div>
                <div>
                  <div style="font-size:16px;font-weight:700;color:var(--text-primary)"><?= e($academicName) ?></div>
                  <div style="font-size:13px;color:var(--text-secondary)">أكاديمي موثق ✓</div>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group" style="grid-column:span 2">
                  <label class="form-label">الاسم الكامل</label>
                  <input class="form-input" id="settingsName" value="<?= e($academicData['name']) ?>"/>
                </div>
                <div class="form-group">
                  <label class="form-label">البريد الإلكتروني</label>
                  <input class="form-input" id="settingsEmail" type="email" value="<?= e($academicData['email']) ?>" dir="ltr"/>
                </div>
                <div class="form-group">
                  <label class="form-label">رقم الجوال</label>
                  <input class="form-input" id="settingsPhone" type="tel" value="<?= e($academicData['phone']) ?>" dir="ltr"/>
                </div>
                <div class="form-group">
                  <label class="form-label">التوفر</label>
                  <div style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:var(--bg-main);border-radius:var(--radius-sm);border:1.5px solid var(--border-color)">
                    <label class="toggle-switch">
                      <input type="checkbox" id="settingsAvailability" <?= $academicData['availability'] === 'available' ? 'checked' : '' ?>/>
                      <span class="toggle-slider"></span>
                    </label>
                    <span style="font-size:14px;color:var(--text-primary)">متاح لاستقبال الطلبات</span>
                  </div>
                </div>
                <div class="form-group" style="grid-column:span 2">
                  <label class="form-label">نبذة تعريفية</label>
                  <textarea class="form-input" id="settingsBio" rows="4"><?= e($academicData['bio']) ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <!-- Services section -->
          <div id="sec-services" class="s-section" style="display:none">
            <div class="card" style="padding:26px;margin-bottom:18px">
              <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:18px">⚙️ إدارة الخدمات</h3>
              <p style="font-size:13px;color:var(--text-secondary);margin-bottom:18px">اختر الخدمات التي تقدمها لطلابك</p>
              
              <div id="servicesEditGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px">
                <?php foreach ($allServicesList as $srv): ?>
                  <?php $checked = in_array($srv['id'], $selectedServices) ? 'checked' : ''; ?>
                  <label class="service-checkbox" style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;border:1.5px solid <?= $checked ? 'var(--primary)' : 'var(--border-color)' ?>;cursor:pointer;background:<?= $checked ? 'rgba(99,102,241,.06)' : 'transparent' ?>">
                    <input type="checkbox" <?= $checked ?> value="<?= $srv['id'] ?>" style="width:16px;height:16px;accent-color:var(--primary);flex-shrink:0" onchange="this.closest('label').style.borderColor=this.checked?'var(--primary)':'var(--border-color)';this.closest('label').style.background=this.checked?'rgba(99,102,241,.06)':'transparent'"/>
                    <span style="font-size:20px"><?= e($srv['icon'] ?? '🔬') ?></span>
                    <span style="font-size:13px;color:var(--text-primary)"><?= e($srv['name']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>

              <div style="border-top:1px solid var(--border-color);padding-top:20px;margin-top:4px">
                <h4 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:14px">💰 إعدادات التسعير</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                  <div class="form-group">
                    <label class="form-label">السعر المبدئي (ر.س)</label>
                    <input class="form-input" id="settingsPrice" type="number" value="<?= intval($academicData['starting_price']) ?>" min="50"/>
                  </div>
                  <div class="form-group">
                    <label class="form-label">متوسط وقت التسليم</label>
                    <select class="form-input form-select" style="padding-left:36px">
                      <option>24 ساعة</option>
                      <option selected>2-3 أيام</option>
                      <option>أسبوع</option>
                      <option>أسبوعان</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Qualifications section -->
          <div id="sec-qualifications" class="s-section" style="display:none">
            <div class="card" style="padding:26px;margin-bottom:18px">
              <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:18px">🎓 المؤهلات الأكاديمية</h3>
              <div style="display:flex;flex-direction:column;gap:14px">
                <?php if (empty($quals)): ?>
                  <p style="color:var(--text-secondary);text-align:center;padding:20px;">لا توجد مؤهلات مسجلة.</p>
                <?php else: ?>
                  <?php foreach ($quals as $q): ?>
                    <?php
                    $icon = '🏫';
                    $color = 'var(--primary)';
                    if ($q['level'] === 'ماجستير') {
                        $icon = '📜';
                        $color = 'var(--success)';
                    } elseif ($q['level'] === 'دكتوراه') {
                        $icon = '🎓';
                        $color = 'var(--warning)';
                    }
                    ?>
                    <div style="padding:18px;border:1.5px solid var(--border-color);border-radius:14px;display:flex;justify-content:space-between;align-items:center">
                      <div style="display:flex;align-items:center;gap:14px">
                        <span style="font-size:28px"><?= $icon ?></span>
                        <div>
                          <div style="font-size:12px;font-weight:600;color:<?= $color ?>"><?= e($q['level']) ?></div>
                          <div style="font-weight:700;color:var(--text-primary)"><?= e($q['field']) ?></div>
                          <div style="font-size:12px;color:var(--text-secondary)"><?= e($q['university']) ?> · <?= e($q['graduation_year']) ?></div>
                        </div>
                      </div>
                      <div style="display:flex;gap:6px">
                        <?php if ($q['verified']): ?>
                          <span class="badge badge-success">موثّق ✓</span>
                        <?php else: ?>
                          <span class="badge badge-secondary">قيد المراجعة</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Notifications section -->
          <div id="sec-notifications" class="s-section" style="display:none">
            <div class="card" style="padding:26px">
              <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:18px">🔔 إعدادات الإشعارات</h3>
              <div style="display:flex;flex-direction:column;gap:2px;border:1.5px solid var(--border-color);border-radius:14px;overflow:hidden">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border-color)">
                  <div><div style="font-weight:600;color:var(--text-primary)">إشعارات الطلبات الجديدة</div><div style="font-size:12px;color:var(--text-secondary)">تنبيه عند وصول طلب جديد</div></div>
                  <label class="toggle-switch"><input type="checkbox" checked/><span class="toggle-slider"></span></label>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border-color)">
                  <div><div style="font-weight:600;color:var(--text-primary)">إشعارات الأرباح</div><div style="font-size:12px;color:var(--text-secondary)">تنبيه عند استلام دفعة</div></div>
                  <label class="toggle-switch"><input type="checkbox" checked/><span class="toggle-slider"></span></label>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px">
                  <div><div style="font-weight:600;color:var(--text-primary)">إشعارات التقييمات</div><div style="font-size:12px;color:var(--text-secondary)">تنبيه عند وصول تقييم جديد</div></div>
                  <label class="toggle-switch"><input type="checkbox" checked/><span class="toggle-slider"></span></label>
                </div>
              </div>
            </div>
          </div>

          <!-- Security section -->
          <div id="sec-security" class="s-section" style="display:none">
            <div class="card" style="padding:26px">
              <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:18px">🔒 تغيير كلمة المرور</h3>
              <div class="form-group">
                <label class="form-label">كلمة المرور الحالية</label>
                <input class="form-input" type="password" id="currentPass" placeholder="••••••••"/>
              </div>
              <div class="form-group">
                <label class="form-label">كلمة المرور الجديدة</label>
                <input class="form-input" type="password" id="newPass" placeholder="••••••••"/>
              </div>
              <div class="form-group">
                <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                <input class="form-input" type="password" id="confirmPass" placeholder="••••••••"/>
              </div>
              <button class="btn btn-primary" onclick="changePassword()">🔒 تحديث كلمة المرور</button>
            </div>
          </div>

          <!-- Bank section -->
          <div id="sec-bank" class="s-section" style="display:none">
            <div class="card" style="padding:26px">
              <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:18px">🏦 البيانات البنكية</h3>
              <div class="alert alert-info" style="margin-bottom:18px"><span>ℹ️</span><span>يُستخدم IBAN لاستقبال الأرباح عبر التحويل البنكي</span></div>
              <div class="form-group">
                <label class="form-label">رقم الحساب البنكي (IBAN)</label>
                <input class="form-input" id="settingsIban" value="<?= e($academicData['iban'] ?? '') ?>" dir="ltr" style="letter-spacing:1px" placeholder="SA00 0000 0000 0000 0000 0000"/>
              </div>
              <div class="form-group">
                <label class="form-label">اسم البنك</label>
                <select class="form-input form-select" id="settingsBankName" style="padding-left:36px">
                  <option value="">اختر البنك</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'بنك الراجحي' ? 'selected' : '' ?>>بنك الراجحي</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'البنك الأهلي' ? 'selected' : '' ?>>البنك الأهلي</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'بنك الرياض' ? 'selected' : '' ?>>بنك الرياض</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'بنك البلاد' ? 'selected' : '' ?>>بنك البلاد</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'بنك الإنماء' ? 'selected' : '' ?>>بنك الإنماء</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'البنك الأول' ? 'selected' : '' ?>>البنك الأول</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'بنك الجزيرة' ? 'selected' : '' ?>>بنك الجزيرة</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'بنك ساب' ? 'selected' : '' ?>>بنك ساب</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'بنك الاستثمار' ? 'selected' : '' ?>>بنك الاستثمار</option>
                  <option <?= ($academicData['bank_name'] ?? '') === 'البنك الفرنسي' ? 'selected' : '' ?>>البنك الفرنسي</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">اسم صاحب الحساب (كما في البنك)</label>
                <input class="form-input" id="settingsAccountName" value="<?= e($academicData['account_name'] ?? '') ?>" dir="ltr"/>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Confirm -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:420px"><div class="modal-header"><h3 class="modal-title" id="confirmTitle">تأكيد</h3><button class="modal-close" data-modal-close>✕</button></div><div class="modal-body" style="text-align:center;padding:32px 24px"><div style="font-size:56px;margin-bottom:12px">⚠️</div><p id="confirmMsg" style="color:var(--text-secondary)"></p></div><div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-danger" id="confirmOkBtn">تأكيد</button></div></div>
</div>

<script src="assets/js/main.js"></script>
<script>
function showSection(id, btn) {
  document.querySelectorAll('.s-section').forEach(s => s.style.display = 'none');
  document.querySelectorAll('.s-menu-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('sec-' + id).style.display = 'block';
  btn.classList.add('active');
}

function changePassword() {
  const current = document.getElementById('currentPass').value;
  const p1 = document.getElementById('newPass').value;
  const p2 = document.getElementById('confirmPass').value;

  if (!current || !p1 || !p2) { Toast.show('يرجى ملء كافة الحقول', 'error'); return; }
  if (p1 !== p2) { Toast.show('كلمتا المرور الجديدتان غير متطابقتين', 'error'); return; }
  if (p1.length < 6) { Toast.show('كلمة المرور يجب أن تكون 6 أحرف على الأقل', 'error'); return; }

  const formData = new FormData();
  formData.append('current', current);
  formData.append('new', p1);
  formData.append('confirm', p2);

  fetch('ajax/handler.php?action=change_password', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      Toast.show(data.message, 'success');
      document.getElementById('currentPass').value = '';
      document.getElementById('newPass').value = '';
      document.getElementById('confirmPass').value = '';
    } else {
      Toast.show(data.message, 'error');
    }
  });
}

function saveSettings() {
  const btn = event.target;
  btn.innerHTML = '⏳ جاري الحفظ...';
  btn.disabled = true;

  const formData = new FormData();
  formData.append('name', document.getElementById('settingsName').value);
  formData.append('email', document.getElementById('settingsEmail').value);
  formData.append('phone', document.getElementById('settingsPhone').value);
  formData.append('bio', document.getElementById('settingsBio').value);
  formData.append('availability', document.getElementById('settingsAvailability').checked ? 'available' : 'busy');
  formData.append('starting_price', document.getElementById('settingsPrice').value);
  formData.append('iban', document.getElementById('settingsIban').value);
  formData.append('bank_name', document.getElementById('settingsBankName').value);
  formData.append('account_name', document.getElementById('settingsAccountName').value);

  // Selected services checkboxes
  const checkboxes = document.querySelectorAll('#servicesEditGrid input[type=checkbox]');
  checkboxes.forEach(chk => {
    if (chk.checked) {
      formData.append('services[]', chk.value);
    }
  });

  fetch('ajax/handler.php?action=update_profile', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    btn.innerHTML = '💾 حفظ التغييرات';
    btn.disabled = false;
    if (data.success) {
      Toast.show(data.message, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      Toast.show(data.message, 'error');
    }
  });
}
</script>
</body>
</html>
