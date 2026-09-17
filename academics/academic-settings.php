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

// Bank accounts / wallets for this academic
$bankStmt = $db->prepare("SELECT * FROM academic_bank_accounts WHERE academic_id = ? ORDER BY id ASC");
$bankStmt->execute([$academicId]);
$bankAccounts = $bankStmt->fetchAll();

// Arabic labels for account types
$accountTypeLabels = ['bank' => '🏦 حساب بنكي', 'wallet' => '👛 محفظة إلكترونية'];

// المسار الأساسي للتطبيق (جذر المشروع) لبناء روابط الشعارات من أي مجلد
$bankScriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$bankScriptName = preg_replace('#/academics/[^/]+\.php$#', '', $bankScriptName);
$bankAppBaseUrl = rtrim($bankScriptName, '/');
// نفس المسار الأساسي (جذر المشروع) لبناء روابط الصور الشخصية
$appBaseUrl = rtrim($bankScriptName, '/');

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

    /* ── شجرة الخدمات ثلاثية المستويات ── */
    .svc-tree { display: flex; flex-direction: column; gap: 10px; }
    .svc-node { border: 1.5px solid var(--border-color); border-radius: 12px; overflow: hidden; background: var(--bg-card); }
    .svc-node-head {
      display: flex; align-items: center; justify-content: space-between;
      padding: 12px 14px; cursor: pointer; user-select: none;
      transition: background .2s;
    }
    .svc-node-head:hover { background: rgba(99,102,241,.04); }
    .svc-check-label { display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1; min-width: 0; }
    .svc-check { width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer; flex-shrink: 0; }
    .svc-node-icon { font-size: 18px; flex-shrink: 0; }
    .svc-node-name { font-size: 13.5px; font-weight: 700; color: var(--text-primary); overflow-wrap: anywhere; }
    /* اسم العقدة الأب قابل للنقر لفتح/إغلاق القائمة */
    .svc-node-head > .svc-node-name { flex: 1; cursor: pointer; }
    .svc-node-head .svc-check-label { flex: 0 0 auto; cursor: pointer; }
    .svc-arrow { color: var(--text-secondary); font-size: 14px; flex-shrink: 0; transition: transform .2s; }
    .svc-children {
      padding: 10px 12px 12px; display: flex; flex-direction: column; gap: 8px;
      border-top: 1px dashed var(--border-color); background: var(--bg-main);
    }
    .svc-node .svc-children .svc-node { background: var(--bg-card); }
    .svc-leaf {
      display: flex; align-items: center; gap: 10px; padding: 9px 12px;
      border: 1.5px solid var(--border-color); border-radius: 10px;
      background: var(--bg-card); cursor: pointer; transition: border-color .2s, background .2s;
    }
    .svc-leaf:hover { border-color: var(--primary); }
    .svc-leaf:has(input:checked) { border-color: var(--primary); background: rgba(99,102,241,.06); }
    .svc-node[data-level="2"] .svc-node-name { font-weight: 600; font-size: 13px; }
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

      <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-6">

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
            <div class="card p-6 mb-5">
              <h3 class="text-[17px] font-bold text-[var(--text-primary)] mb-5">👤 البيانات الشخصية</h3>

              <!-- Avatar + رفع الصورة الشخصية -->
              <div class="flex flex-col sm:flex-row items-center gap-5 mb-6 p-5 rounded-2xl" style="background:var(--bg-main)">
                <?php $avatar_uploaded = !empty($academicData['avatar']); ?>
                <div class="relative">
                  <div id="avatarDisplay" class="w-20 h-20 rounded-full flex items-center justify-center text-[30px] font-extrabold text-white relative bg-gradient-to-br from-indigo-500 to-indigo-400 overflow-hidden">
                    <?php if ($avatar_uploaded): ?>
                      <img id="avatarCurrentImg" src="<?= htmlspecialchars($appBaseUrl . '/' . ltrim($academicData['avatar'], '/'), ENT_QUOTES) ?>" alt="الصورة الشخصية" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none';document.getElementById('avatarInitials').style.display='flex'" />
                      <span id="avatarInitials" style="display:none"><?= e($academicAvatar) ?></span>
                    <?php else: ?>
                      <span id="avatarInitials" style="display:flex"><?= e($academicAvatar) ?></span>
                    <?php endif; ?>
                    <?php if ($avatar_uploaded): ?>
                      <button type="button" onclick="removeAvatar()" title="إزالة الصورة" style="position:absolute;top:0;left:0;background:rgba(239,68,68,.9);color:#fff;border:none;border-radius:0 0 6px 0;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center">✕</button>
                    <?php endif; ?>
                  </div>
                  <label for="avatarFile" title="رفع صورة شخصية" style="position:absolute;bottom:-4px;right:-4px;width:30px;height:30px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;cursor:pointer;box-shadow:0 2px 8px rgba(99,102,241,.4)">📷</label>
                  <input type="file" id="avatarFile" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none" onchange="handleAvatarUpload(this)" />
                </div>
                <div style="flex:1">
                  <div style="font-size:13px;color:var(--text-secondary)">أكاديمي موثق ✓</div>
                  <div id="avatarUploadText" style="font-size:12px;color:var(--text-secondary);margin-top:3px">اضغط 📷 لرفع صورة شخصية (JPG/PNG/GIF/WEBP)، ثم اضغط «حفظ التغييرات» في الأعلى.</div>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                  <div class="flex items-center gap-3 py-3 px-4 border-[1.5px] rounded-md" style="background:var(--bg-main);border-color:var(--border-color)">
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
              <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:8px">⚙️ إدارة الخدمات</h3>
              <p style="font-size:13px;color:var(--text-secondary);margin-bottom:18px">اختر قسمًا رئيسيًا لتحديد كل محتوياته، أو قسمًا فرعيًا لتحديد خدماته، أو خدمة مفردة</p>

              <?php
              // ── بناء شجرة الخدمات (L1 → L2 → L3) بنفس ترتيب لوحة الإدارة ──
              // فرز حسب sort_order داخل كل مستوى (نفس ترتيب admin/pages/services.php)
              usort($allServicesList, function ($a, $b) {
                  $la = (int)$a['level']; $lb = (int)$b['level'];
                  if ($la !== $lb) return $la - $lb;
                  $sa = (int)($a['sort_order'] ?? 0); $sb = (int)($b['sort_order'] ?? 0);
                  if ($sa !== $sb) return $sa - $sb;
                  return (int)$a['id'] - (int)$b['id'];
              });
              $svcChildrenMap = [];
              foreach ($allServicesList as $s) {
                  $svcChildrenMap[$s['parent_id'] ? (int)$s['parent_id'] : 0][] = $s;
              }

              $svcRenderTree = function ($parentId, $level) use (&$svcRenderTree, $svcChildrenMap, $selectedServices) {
                  foreach ($svcChildrenMap[$parentId] ?? [] as $s) {
                      $sid = (int)$s['id'];
                      $icon = e($s['icon'] ?: '🔬');
                      $name = e($s['name']);

                      if ($level < 3): ?>
                        <div class="svc-node" data-node="<?= $sid ?>">
                          <div class="svc-node-head" onclick="svcToggleCollapse(this)">
                            <label class="svc-check-label" onclick="event.stopPropagation()">
                              <input type="checkbox" class="svc-check svc-parent" data-node="<?= $sid ?>" data-level="<?= $level ?>" onchange="svcParentChanged(this)" />
                              <span class="svc-node-icon"><?= $icon ?></span>
                            </label>
                            <span class="svc-node-name"><?= $name ?></span>
                            <span class="svc-arrow">▾</span>
                          </div>
                          <div class="svc-children" style="display:none">
                            <?php $svcRenderTree($sid, $level + 1); ?>
                          </div>
                        </div>
                      <?php else: ?>
                        <label class="svc-leaf">
                          <input type="checkbox" name="services[]" value="<?= $sid ?>" class="svc-check svc-leaf-check"
                                 <?= in_array($sid, $selectedServices) ? 'checked' : '' ?>
                                 onchange="svcLeafChanged(this)" />
                          <span class="svc-node-icon"><?= $icon ?></span>
                          <span class="svc-node-name"><?= $name ?></span>
                        </label>
                      <?php endif;
                  }
              };
              ?>
              <div id="servicesEditTree" class="svc-tree mb-5">
                <?php $svcRenderTree(0, 1); ?>
              </div>
            </div>
          </div>

          <!-- Qualifications section -->
          <div id="sec-qualifications" class="s-section" style="display:none">
            <div class="card" style="padding:28px;margin-bottom:18px">
              
              <!-- Header -->
              <div class="flex items-center justify-between flex-wrap gap-4 pb-5 mb-6 border-b border-[var(--border-color)]">
                <div style="display:flex;align-items:center;gap:14px">
                  <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(129,140,248,0.1));border:1px solid rgba(99,102,241,0.25);display:flex;align-items:center;justify-content:center;font-size:24px">🎓</div>
                  <div>
                    <h3 style="font-size:18px;font-weight:800;color:var(--text-primary)">المؤهلات الأكاديمية والشهادات</h3>
                    <p style="font-size:13px;color:var(--text-secondary);margin-top:2px">استعرض وإدارة مؤهلاتك العلمية ووثائق الإثبات المعتمدة مع إمكانية الإضافة والتعديل والحذف</p>
                  </div>
                </div>
                <button class="btn btn-primary" onclick="openQualForm()" style="background:linear-gradient(135deg,#6366f1,#4f46e5);border:none;padding:10px 20px;border-radius:12px;font-weight:700;box-shadow:0 4px 14px rgba(99,102,241,0.35);display:inline-flex;align-items:center;gap:8px">
                  <span style="font-size:16px">+</span>
                  <span>إضافة مؤهل جديد</span>
                </button>
              </div>

              <div id="qualificationsList" style="display:flex;flex-direction:column;gap:16px">
                <!-- Dynamically rendered by JavaScript -->
              </div>
            </div>
          </div>

          <!-- Notifications section -->
          <div id="sec-notifications" class="s-section" style="display:none">
            <div class="card" style="padding:26px">
              <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:18px">🔔 إعدادات الإشعارات</h3>
              <div class="flex flex-col gap-0.5 border-[1.5px] border-[var(--border-color)] rounded-2xl overflow-hidden">
                <div class="flex justify-between items-center flex-wrap gap-2 p-4 border-b border-[var(--border-color)]">
                  <div><div style="font-weight:600;color:var(--text-primary)">إشعارات الطلبات الجديدة</div><div style="font-size:12px;color:var(--text-secondary)">تنبيه عند وصول طلب جديد</div></div>
                  <label class="toggle-switch"><input type="checkbox" checked/><span class="toggle-slider"></span></label>
                </div>
                <div class="flex justify-between items-center flex-wrap gap-2 p-4 border-b border-[var(--border-color)]">
                  <div><div style="font-weight:600;color:var(--text-primary)">إشعارات الأرباح</div><div style="font-size:12px;color:var(--text-secondary)">تنبيه عند استلام دفعة</div></div>
                  <label class="toggle-switch"><input type="checkbox" checked/><span class="toggle-slider"></span></label>
                </div>
                <div class="flex justify-between items-center flex-wrap gap-2 p-4">
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
              <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                <h3 style="font-size:17px;font-weight:700;color:var(--text-primary)">🏦 البيانات البنكية</h3>
                <button class="btn btn-primary" onclick="openBankForm()">+ إضافة حساب</button>
              </div>
              <div class="alert alert-info" style="margin-bottom:18px"><span>ℹ️</span><span>أضف حساباتك البنكية ومحافظك الإلكترونية لاستقبال الأرباح عبر التحويل</span></div>

              <!-- Bank accounts list -->
              <div id="bankAccountsList" class="flex flex-col gap-3">
                <?php if (empty($bankAccounts)): ?>
                    <p id="bankEmpty" style="color:var(--text-secondary);text-align:center;padding:32px 0">لا توجد حسابات بعد، اضغط "إضافة حساب" لإضافة حساب بنكي أو محفظة إلكترونية.</p>
                <?php else: ?>
                    <?php foreach ($bankAccounts as $acc):
                      $isWallet = ($acc['account_type'] === 'wallet');
                      $accent = $isWallet ? 'var(--secondary)' : 'var(--primary)';
                      $icon = $isWallet ? '👛' : '🏦';
                      ?>
                        <div class="bank-acc-item flex items-center justify-between gap-4 p-4 border-[1.5px] rounded-2xl flex-wrap" data-id="<?= (int) $acc['id'] ?>" style="border-color:var(--border-color);background:var(--bg-card)">
                          <div style="display:flex;align-items:center;gap:14px">
                            <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,<?= $isWallet ? '#0ea5e9,#38bdf8' : '#6366f1,#818cf8' ?>);display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;overflow:hidden">
                              <?php if (!empty($acc['logo'])): ?>
                                <img src="<?= htmlspecialchars($bankAppBaseUrl . '/' . ltrim($acc['logo'], '/'), ENT_QUOTES) ?>" alt="الشعار" style="width:100%;height:100%;object-fit:contain;padding:5px;background:#fff" onerror="this.remove()" />
                              <?php else: ?>
                                <?= $icon ?>
                              <?php endif; ?>
                            </div>
                            <div>
                              <div style="font-size:13px;font-weight:700;color:<?= $accent ?>"><?= $accountTypeLabels[$acc['account_type']] ?? ($acc['account_type']) ?></div>
                              <div style="font-weight:700;color:var(--text-primary)"><?= e($acc['account_name']) ?></div>
                              <div style="font-size:13px;color:var(--text-secondary)" dir="ltr" style="text-align:right"><?= e($acc['account_number']) ?><?= $acc['holder_name'] ? ' · ' . e($acc['holder_name']) : '' ?></div>
                            </div>
                          </div>
                          <div style="display:flex;gap:8px">
                            <button class="btn btn-outline btn-sm" onclick="openBankForm(<?= (int) $acc['id'] ?>)">✏️ تعديل</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteBankAccount(<?= (int) $acc['id'] ?>, '<?= e(addslashes($acc['account_name'])) ?>')">🗑️ حذف</button>
                          </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Qualification add/edit modal -->
<div class="modal-overlay" id="qualModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h3 class="modal-title" id="qualModalTitle">إضافة مؤهل أكاديمي</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="qualId" value="" />
      <div class="form-group">
        <label class="form-label">الدرجة العلمية <span style="color:var(--danger)">*</span></label>
        <select class="form-input form-select" id="qualLevel" style="padding-left:36px">
          <option value="بكالوريوس">🏫 البكالوريوس</option>
          <option value="ماجستير">📜 الماجستير</option>
          <option value="دكتوراه">🎓 الدكتوراه</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">التخصص <span style="color:var(--danger)">*</span></label>
        <input class="form-input" id="qualField" placeholder="مثال: علوم الحاسب والمعلومات" />
      </div>
      <div class="form-group">
        <label class="form-label">الجامعة / المؤسسة التعليمية <span style="color:var(--danger)">*</span></label>
        <input class="form-input" id="qualUniversity" placeholder="مثال: جامعة الملك سعود" />
      </div>
      <div class="form-group">
        <label class="form-label">الدولة</label>
        <input class="form-input" id="qualCountry" placeholder="مثال: السعودية" />
      </div>
      <div class="form-group">
        <label class="form-label">سنة التخرج</label>
        <input class="form-input" id="qualYear" type="number" placeholder="2020" min="1970" max="2030" />
      </div>
      <div class="form-group">
        <label class="form-label">وثيقة إثبات المؤهل / الشهادة (PDF أو صورة)</label>
        <input class="form-input" type="file" id="qualDocument" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" />
        <p class="form-hint" id="qualDocHint">ارفق صورة الشهادة أو ملف الإثبات</p>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" onclick="saveQualification()">💾 حفظ المؤهل</button>
    </div>
  </div>
</div>

<!-- Bank account add/edit modal -->
<div class="modal-overlay" id="bankModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h3 class="modal-title" id="bankModalTitle">إضافة حساب بنكي</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="bankAccountId" value="" />
      <div class="form-group">
        <label class="form-label">نوع الحساب</label>
        <select class="form-input form-select" id="bankAccountType" onchange="updateBankLabels()" style="padding-left:36px">
          <option value="bank">🏦 حساب بنكي</option>
          <option value="wallet">👛 محفظة إلكترونية</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" id="bankAccountNameLabel">اسم البنك</label>
        <input class="form-input" id="bankAccountName" placeholder="ادخل اسم البنك او المحفظة" />
      </div>
      <div class="form-group">
        <label class="form-label" id="bankAccountNumberLabel">رقم الحساب (IBAN)</label>
        <input class="form-input" id="bankAccountNumber" dir="ltr" placeholder="SA00 0000 0000 0000 0000 0000" />
      </div>
      <div class="form-group">
        <label class="form-label">اسم صاحب الحساب (اختياري)</label>
        <input class="form-input" id="bankHolderName" placeholder="كما هو مسجّل في البنك" />
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">شعار البنك / المحفظة (اختياري)</label>
        <div id="bankLogoBox" style="display:flex;align-items:center;gap:12px;border:2px dashed var(--border-color);border-radius:12px;padding:12px 14px;background:var(--bg-main);">
          <div id="bankLogoPreview" style="display:none;position:relative;width:52px;height:52px;border-radius:10px;overflow:hidden;border:1px solid var(--border-color);background:white;flex-shrink:0;">
            <img id="bankLogoPreviewImg" src="" alt="الشعار" style="width:100%;height:100%;object-fit:contain;padding:4px;" />
            <button type="button" onclick="removeUploadedBankLogo()" title="إزالة الشعار" style="position:absolute;top:-7px;right:-7px;width:20px;height:20px;border-radius:50%;background:#ef4444;color:white;border:none;font-size:11px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
          </div>
          <label for="bankLogoFile" style="display:inline-flex;align-items:center;gap:8px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:10px;padding:9px 16px;font-size:13px;font-weight:700;color:var(--text-primary);cursor:pointer;transition:.2s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-color)'">
            <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:var(--primary);color:white;font-size:14px;font-weight:700">＋</span>
            <span>رفع الشعار من الجهاز</span>
          </label>
          <input type="file" id="bankLogoFile" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" style="display:none" onchange="handleBankLogoUpload(this)" />
        </div>
        <p class="form-hint">إن لم ترفع شعاراً سيظهر رمز البنك/المحفظة الافتراضي.</p>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" onclick="saveBankAccount()">💾 حفظ</button>
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
  // حفظ القسم الحالي لاستعادته بعد تحديث الصفحة (مثلاً بعد الحفظ)
  try { sessionStorage.setItem('academicSettingsSection', id); } catch (e) {}
}

// استعادة القسم الأخير عند فتح الصفحة (بعد الحفظ أو التحديث)
document.addEventListener('DOMContentLoaded', () => {
  let savedSection = null;
  try { savedSection = sessionStorage.getItem('academicSettingsSection'); } catch (e) {}
  if (savedSection) {
    const btn = document.querySelector('.s-menu-btn[onclick*="showSection(\'' + savedSection + '\'"]');
    if (btn) { showSection(savedSection, btn); return; }
  }
});
/* ── شجرة الخدمات: طي/فتح + مزامنة الأب مع الأبناء ── */
function svcToggleCollapse(headEl) {
  const node = headEl.parentElement;
  const children = node.querySelector(':scope > .svc-children');
  if (!children) return;
  const isOpen = children.style.display !== 'none';
  children.style.display = isOpen ? 'none' : 'block';
  const arrow = headEl.querySelector('.svc-arrow');
  if (arrow) arrow.textContent = isOpen ? '▾' : '▴';
}

function svcSetDescendantLeaves(node, checked) {
  node.querySelectorAll(':scope > .svc-children input[type="checkbox"]').forEach(chk => {
    chk.checked = checked;
    if (chk.classList.contains('svc-parent')) {
      const childNode = chk.closest('.svc-node');
      if (childNode) svcSetDescendantLeaves(childNode, checked);
    }
  });
}

function svcUpdateParentStates(root) {
  root = root || document.getElementById('servicesEditTree');
  if (!root) return;
  // من الأسفل للأعلى: حالة كل أب تُحسب من أبنائه
  root.querySelectorAll('.svc-parent').forEach(pChk => {
    const node = pChk.closest('.svc-node');
    const leaves = node.querySelectorAll('.svc-leaf-check');
    if (!leaves.length) return;
    const checkedCount = Array.from(leaves).filter(c => c.checked).length;
    pChk.checked = checkedCount === leaves.length;
    pChk.indeterminate = checkedCount > 0 && checkedCount < leaves.length;
  });
}

function svcParentChanged(chk) {
  const node = chk.closest('.svc-node');
  if (node) svcSetDescendantLeaves(node, chk.checked);
  svcUpdateParentStates();
}

function svcLeafChanged() {
  svcUpdateParentStates();
}

// مزامنة أولية: حالة الأب عند التحميل حسب الخدمات المحفوظة مسبقاً
// (القوائم تبدأ كلها مغلقة افتراضياً)
document.addEventListener('DOMContentLoaded', () => {
  svcUpdateParentStates();
});

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
  if (document.getElementById('settingsPrice')) {
    formData.append('starting_price', document.getElementById('settingsPrice').value);
  }

  // Selected services (خدمات المستوى الثالث المحددة من الشجرة فقط)
  const checkedServices = document.querySelectorAll('#servicesEditTree input[name="services[]"]:checked');
  checkedServices.forEach(chk => {
    formData.append('services[]', chk.value);
  });

  // الصورة الشخصية: أرسلها فقط عند تغييرها حتى لا تُمسح عند حفظ تغييرات أخرى
  if (selectedAvatar) {
    formData.append('avatar', selectedAvatar);
  }
  formData.append('avatar_cleared', selectedAvatarCleared ? '1' : '0');

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

/* ─────────────────────────────────────────────
   QUALIFICATIONS (المؤهلات الأكاديمية)
   ───────────────────────────────────────────── */
let qualsStore = <?= json_encode($quals, JSON_UNESCAPED_UNICODE) ?>;

function openQualForm(id) {
  const qId = id ? String(id) : '';
  document.getElementById('qualId').value = qId;
  document.getElementById('qualDocument').value = '';

  if (qId) {
    const q = qualsStore.find(item => String(item.id) === qId);
    if (!q) { Toast.show('المؤهل غير موجود', 'error'); return; }
    document.getElementById('qualModalTitle').textContent = 'تعديل المؤهل الأكاديمي';
    document.getElementById('qualLevel').value = q.level || 'بكالوريوس';
    document.getElementById('qualField').value = q.field || '';
    document.getElementById('qualUniversity').value = q.university || '';
    document.getElementById('qualCountry').value = q.country || '';
    document.getElementById('qualYear').value = q.graduation_year || '';
    document.getElementById('qualDocHint').textContent = q.document_file ? 'تم رفع وثيقة سابقة. اختر ملفاً جديداً إذا كنت تود استبدالها.' : 'ارفق صورة الشهادة أو ملف الإثبات';
  } else {
    document.getElementById('qualModalTitle').textContent = 'إضافة مؤهل أكاديمي';
    document.getElementById('qualLevel').value = 'بكالوريوس';
    document.getElementById('qualField').value = '';
    document.getElementById('qualUniversity').value = '';
    document.getElementById('qualCountry').value = '';
    document.getElementById('qualYear').value = '';
    document.getElementById('qualDocHint').textContent = 'ارفق صورة الشهادة أو ملف الإثبات';
  }
  Modal.open('qualModal');
}

function renderQualificationCard(q) {
  let icon = '🏫';
  let iconBg = 'linear-gradient(135deg, rgba(99,102,241,0.15), rgba(129,140,248,0.08))';
  let iconBorder = 'rgba(99,102,241,0.2)';
  let levelColor = '#6366f1';
  
  if (q.level === 'ماجستير') {
    icon = '📜';
    iconBg = 'linear-gradient(135deg, rgba(16,185,129,0.15), rgba(52,211,153,0.08))';
    iconBorder = 'rgba(16,185,129,0.2)';
    levelColor = '#10b981';
  } else if (q.level === 'دكتوراه') {
    icon = '🎓';
    iconBg = 'linear-gradient(135deg, rgba(245,158,11,0.15), rgba(251,191,36,0.08))';
    iconBorder = 'rgba(245,158,11,0.2)';
    levelColor = '#f59e0b';
  }

  const countryYear = [q.country, q.graduation_year].filter(Boolean).join(' · ');

  const docPill = q.document_file 
    ? '<a href="../' + escapeBankHtml(q.document_file) + '" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:8px;color:var(--primary);font-size:12px;font-weight:700;text-decoration:none;transition:all .2s" onmouseover="this.style.background=\'rgba(99,102,241,0.15)\'" onmouseout="this.style.background=\'rgba(99,102,241,0.08)\'"><span>📎</span><span>عرض وثيقة الإثبات المرفقة</span><span style="font-size:10px">↗</span></a>'
    : '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.15);border-radius:8px;color:#ef4444;font-size:12px;font-weight:600"><span>⚠️</span><span>لم يتم إرفاق وثيقة إثبات</span></span>';

  const statusBadge = q.verified == 1
    ? '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);border-radius:20px;color:#059669;font-size:11px;font-weight:700">✓ موثّق</span>'
    : '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:20px;color:#d97706;font-size:11px;font-weight:700">⏳ قيد المراجعة</span>';

  return '<div class="qual-card" data-id="' + q.id + '" style="background:var(--bg-card);border:1.5px solid var(--border-color);border-radius:16px;padding:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;transition:all 0.25s ease;box-shadow:0 4px 12px rgba(0,0,0,0.02)">'
    + '<div style="display:flex;align-items:flex-start;gap:16px;flex:1;min-width:260px">'
    + '<div style="width:52px;height:52px;border-radius:14px;background:' + iconBg + ';border:1px solid ' + iconBorder + ';display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0">' + icon + '</div>'
    + '<div style="flex:1">'
    + '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap">'
    + '<span style="font-size:12px;font-weight:800;color:' + levelColor + ';letter-spacing:0.3px;background:rgba(0,0,0,0.03);padding:3px 10px;border-radius:8px;border:1px solid ' + iconBorder + '">' + escapeBankHtml(q.level) + '</span>'
    + statusBadge
    + '</div>'
    + '<h4 style="font-size:16px;font-weight:800;color:var(--text-primary);margin:0 0 6px 0">' + escapeBankHtml(q.field) + '</h4>'
    + '<p style="font-size:13px;color:var(--text-secondary);margin:0 0 10px 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap">'
    + '<span>🏛️ ' + escapeBankHtml(q.university) + '</span>'
    + (countryYear ? '<span style="color:var(--text-secondary);opacity:0.5">•</span><span>' + escapeBankHtml(countryYear) + '</span>' : '')
    + '</p>'
    + '<div>' + docPill + '</div>'
    + '</div>'
    + '</div>'
    + '<div style="display:flex;align-items:center;gap:8px;align-self:center">'
    + '<button class="btn btn-sm" onclick="openQualForm(' + q.id + ')" style="background:var(--bg-main);border:1.5px solid var(--border-color);color:var(--text-primary);padding:8px 14px;border-radius:10px;font-weight:700;font-size:13px;display:inline-flex;align-items:center;gap:6px;transition:all .2s">'
    + '<span>✏️</span><span>تعديل</span>'
    + '</button>'
    + '<button class="btn btn-sm" onclick="deleteQualification(' + q.id + ', \'' + bankJsName(q.field) + '\')" style="background:rgba(239,68,68,0.08);border:1.5px solid rgba(239,68,68,0.2);color:#ef4444;padding:8px 14px;border-radius:10px;font-weight:700;font-size:13px;display:inline-flex;align-items:center;gap:6px;transition:all .2s">'
    + '<span>🗑️</span><span>حذف</span>'
    + '</button>'
    + '</div>'
    + '</div>';
}

function renderQualificationsList() {
  const list = document.getElementById('qualificationsList');
  if (!list) return;
  if (!qualsStore.length) {
    list.innerHTML = '<div style="text-align:center;padding:48px 20px;background:var(--bg-main);border:2px dashed var(--border-color);border-radius:18px">'
      + '<div style="width:64px;height:64px;border-radius:20px;background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(99,102,241,0.05));border:1px solid rgba(99,102,241,0.2);display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 16px">🎓</div>'
      + '<h4 style="font-size:16px;font-weight:800;color:var(--text-primary);margin-bottom:6px">لم تقم بإضافة أي مؤهلات أكاديمية حتى الآن</h4>'
      + '<p style="font-size:13px;color:var(--text-secondary);max-width:400px;margin:0 auto 20px;line-height:1.6">أضف شهاداتك العلمية ووثائق الإثبات الخاصة بك لتعزيز ملفك الشخصي وموثوقيتك أمام الطلاب.</p>'
      + '<button class="btn btn-primary" onclick="openQualForm()" style="background:linear-gradient(135deg,#6366f1,#4f46e5);border:none;padding:10px 22px;border-radius:12px;font-weight:700">+ إضافة أول مؤهل أكاديمي</button>'
      + '</div>';
    return;
  }
  list.innerHTML = qualsStore.map(q => renderQualificationCard(q)).join('');
}

function loadQualifications() {
  // منع التخزين المؤقت للمتصفح: طلب GET قد يعيد قائمة قديمة (بلا المؤهل الجديد)
  // حتى بعد الحفظ، فنضيف معلمة فريدة + no-store لضمان جلب بيانات محدّثة.
  fetch('ajax/handler.php?action=get_qualifications&_=' + Date.now(), { cache: 'no-store' })
    .then(r => r.json())
    .then(res => {
      if (!res.success) { Toast.show(res.message, 'error'); return; }
      qualsStore.length = 0;
      (res.qualifications || []).forEach(q => qualsStore.push(q));
      renderQualificationsList();
    })
    .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
}

function saveQualification() {
  const id = document.getElementById('qualId').value;
  const level = document.getElementById('qualLevel').value;
  const field = document.getElementById('qualField').value.trim();
  const university = document.getElementById('qualUniversity').value.trim();
  const country = document.getElementById('qualCountry').value.trim();
  const graduation_year = document.getElementById('qualYear').value.trim();
  const docFile = document.getElementById('qualDocument').files[0];

  if (!field || !university) {
    Toast.show('يرجى إدخال التخصص والجامعة', 'error');
    return;
  }

  const action = id ? 'update_qualification' : 'add_qualification';
  const fd = new FormData();
  fd.append('action', action);
  fd.append('id', id || 0);
  fd.append('level', level);
  fd.append('field', field);
  fd.append('university', university);
  fd.append('country', country);
  fd.append('graduation_year', graduation_year);
  if (docFile) {
    fd.append('document_file', docFile);
  }

  fetch('ajax/handler.php?action=' + action, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        Toast.show(res.message, 'success');
        Modal.close('qualModal');
        loadQualifications();
      } else {
        Toast.show(res.message || 'حدث خطأ ما', 'error');
      }
    })
    .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
}

function deleteQualification(id, title) {
  Modal.confirm('حذف المؤهل', 'هل تريد حذف مؤهل "' + (title || '') + '" نهائياً؟', () => {
    const fd = new FormData();
    fd.append('action', 'delete_qualification');
    fd.append('id', id);
    fetch('ajax/handler.php?action=delete_qualification', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          Toast.show(res.message, 'success');
          loadQualifications();
        } else {
          Toast.show(res.message || 'حدث خطأ ما', 'error');
        }
      })
      .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
  });
}

document.addEventListener('DOMContentLoaded', () => {
  renderQualificationsList();
});

/* ─────────────────────────────────────────────
   BANK ACCOUNTS (البيانات البنكية)
   ───────────────────────────────────────────── */
let bankAccountsStore = <?= json_encode($bankAccounts, JSON_UNESCAPED_UNICODE) ?>;

function escapeBankHtml(v) {
  return String(v || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function bankJsName(v) {
  return String(v || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, ' ');
}

function updateBankLabels() {
  const isWallet = document.getElementById('bankAccountType').value === 'wallet';
  document.getElementById('bankAccountNameLabel').textContent = isWallet ? 'اسم المحفظة' : 'اسم البنك';
  document.getElementById('bankAccountNumberLabel').textContent = isWallet ? 'رقم المحفظة' : 'رقم الحساب (IBAN)';
  document.getElementById('bankAccountNumber').placeholder = isWallet ? '01xxxxxxxx' : 'SA00 0000 0000 0000 0000 0000';
}

// حالة شعار الحساب البنكي (اختياري)
let selectedBankLogo = '';
let selectedBankLogoCleared = false;
const BANK_APP_BASE = '<?= htmlspecialchars($bankAppBaseUrl, ENT_QUOTES) ?>';

function bankAssetUrl(path) {
  if (!path) return '';
  if (/^(https?:)?\/\//.test(path)) return path;
  return BANK_APP_BASE + '/' + String(path).replace(/^\/+/, '');
}

function handleBankLogoUpload(input) {
  const file = input.files && input.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('action', 'upload_bank_logo');
  fd.append('image', file);
  Toast.show('جارٍ رفع الشعار...', 'info');
  fetch('ajax/handler.php?action=upload_bank_logo', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        selectedBankLogo = res.image;
        selectedBankLogoCleared = false;
        showBankLogoPreview(res.image);
        Toast.show('تم رفع الشعار بنجاح', 'success');
      } else {
        input.value = '';
        Toast.show(res.message || 'فشل رفع الشعار', 'error');
      }
    })
    .catch(() => { input.value = ''; Toast.show('حدث خطأ في الاتصال بالخادم', 'error'); });
}

function removeUploadedBankLogo() {
  selectedBankLogo = '';
  selectedBankLogoCleared = true;
  document.getElementById('bankLogoFile').value = '';
  resetBankLogoPreview();
}

function showBankLogoPreview(src) {
  document.getElementById('bankLogoPreviewImg').src = bankAssetUrl(src);
  document.getElementById('bankLogoPreview').style.display = 'block';
  const box = document.getElementById('bankLogoBox');
  box.style.borderColor = '#86efac';
  box.style.background = '#f0fdf4';
}

function resetBankLogoPreview() {
  document.getElementById('bankLogoPreview').style.display = 'none';
  document.getElementById('bankLogoPreviewImg').removeAttribute('src');
  const box = document.getElementById('bankLogoBox');
  box.style.borderColor = 'var(--border-color)';
  box.style.background = 'var(--bg-main)';
}

function openBankForm(id) {
  const accId = id ? String(id) : '';
  document.getElementById('bankAccountId').value = accId;

  if (accId) {
    const acc = bankAccountsStore.find(a => String(a.id) === accId);
    if (!acc) { Toast.show('الحساب غير موجود', 'error'); return; }
    document.getElementById('bankModalTitle').textContent = 'تعديل الحساب';
    document.getElementById('bankAccountType').value = acc.account_type;
    document.getElementById('bankAccountName').value = acc.account_name || '';
    document.getElementById('bankAccountNumber').value = acc.account_number || '';
    document.getElementById('bankHolderName').value = acc.holder_name || '';
    selectedBankLogo = acc.logo || '';
    selectedBankLogoCleared = false;
    if (selectedBankLogo) showBankLogoPreview(selectedBankLogo); else resetBankLogoPreview();
  } else {
    document.getElementById('bankModalTitle').textContent = 'إضافة حساب بنكي';
    document.getElementById('bankAccountType').value = 'bank';
    document.getElementById('bankAccountName').value = '';
    document.getElementById('bankAccountNumber').value = '';
    document.getElementById('bankHolderName').value = '';
    selectedBankLogo = '';
    selectedBankLogoCleared = false;
    document.getElementById('bankLogoFile').value = '';
    resetBankLogoPreview();
  }
  updateBankLabels();
  Modal.open('bankModal');
}

function renderBankAccountCard(acc) {
  const isWallet = acc.account_type === 'wallet';
  const accent = isWallet ? 'var(--secondary)' : 'var(--primary)';
  const icon = isWallet ? '👛' : '🏦';
  const typeLabel = isWallet ? '👛 محفظة إلكترونية' : '🏦 حساب بنكي';
  const grad = isWallet ? '#0ea5e9,#38bdf8' : '#6366f1,#818cf8';
  const holder = acc.holder_name ? ' · ' + escapeBankHtml(acc.holder_name) : '';
  const logoHtml = acc.logo
    ? '<img src="' + bankAssetUrl(acc.logo) + '" alt="الشعار" style="width:100%;height:100%;object-fit:contain;padding:5px;background:#fff;border-radius:12px" onerror="this.remove()" />'
    : icon;

  return '<div class="bank-acc-item" data-id="' + acc.id + '" style="display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 18px;border:1.5px solid var(--border-color);border-radius:14px;background:var(--bg-card);flex-wrap:wrap">'
    + '<div style="display:flex;align-items:center;gap:14px">'
    + '<div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,' + grad + ');display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;overflow:hidden">' + logoHtml + '</div>'
    + '<div>'
    + '<div style="font-size:13px;font-weight:700;color:' + accent + '">' + typeLabel + '</div>'
    + '<div style="font-weight:700;color:var(--text-primary)">' + escapeBankHtml(acc.account_name) + '</div>'
    + '<div style="font-size:13px;color:var(--text-secondary)" dir="ltr">' + escapeBankHtml(acc.account_number) + holder + '</div>'
    + '</div></div>'
    + '<div style="display:flex;gap:8px">'
    + '<button class="btn btn-outline btn-sm" onclick="openBankForm(' + acc.id + ')">✏️ تعديل</button>'
    + '<button class="btn btn-danger btn-sm" onclick="deleteBankAccount(' + acc.id + ', \'' + bankJsName(acc.account_name) + '\')">🗑️ حذف</button>'
    + '</div></div>';
}

function renderBankAccountsList() {
  const list = document.getElementById('bankAccountsList');
  if (!list) return;
  if (!bankAccountsStore.length) {
    list.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:32px 0">لا توجد حسابات بعد، اضغط "إضافة حساب" لإضافة حساب بنكي أو محفظة إلكترونية.</p>';
    return;
  }
  list.innerHTML = bankAccountsStore.map(a => renderBankAccountCard(a)).join('');
}

function loadBankAccounts() {
  fetch('ajax/handler.php?action=get_bank_accounts')
    .then(r => r.json())
    .then(res => {
      if (!res.success) { Toast.show(res.message, 'error'); return; }
      bankAccountsStore.length = 0;
      (res.accounts || []).forEach(a => bankAccountsStore.push(a));
      renderBankAccountsList();
    })
    .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
}

function saveBankAccount() {
  const id = document.getElementById('bankAccountId').value;
  const account_type = document.getElementById('bankAccountType').value;
  const account_name = document.getElementById('bankAccountName').value.trim();
  const account_number = document.getElementById('bankAccountNumber').value.trim();
  const holder_name = document.getElementById('bankHolderName').value.trim();

  if (!account_name || !account_number) {
    Toast.show('يرجى إدخال اسم البنك أو المحفظة والرقم', 'error');
    return;
  }

  const action = id ? 'update_bank_account' : 'add_bank_account';
  const fd = new FormData();
  fd.append('action', action);
  fd.append('id', id || 0);
  fd.append('account_type', account_type);
  fd.append('account_name', account_name);
  fd.append('account_number', account_number);
  fd.append('holder_name', holder_name);
  fd.append('logo', selectedBankLogo);
  fd.append('logo_cleared', selectedBankLogoCleared ? 1 : 0);

  fetch('ajax/handler.php?action=' + action, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        Toast.show(res.message, 'success');
        Modal.close('bankModal');
        loadBankAccounts();
      } else {
        Toast.show(res.message || 'حدث خطأ ما', 'error');
      }
    })
    .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
}

function deleteBankAccount(id, name) {
  Modal.confirm('حذف الحساب', 'هل تريد حذف "' + (name || '') + '" نهائياً؟', () => {
    const fd = new FormData();
    fd.append('action', 'delete_bank_account');
    fd.append('id', id);
    fetch('ajax/handler.php?action=delete_bank_account', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          Toast.show(res.message, 'success');
          loadBankAccounts();
        } else {
          Toast.show(res.message || 'حدث خطأ ما', 'error');
        }
      })
      .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
  });
}

/* ── شجرة الخدمات: تحديد هرمي ثلاثي المستويات ── */
// طي/فتح الأقسام (النقر على الترويسة وليس الـ checkbox)
function svcToggleCollapse(headEl) {
  const node = headEl.closest('.svc-node');
  const children = node.querySelector(':scope > .svc-children');
  const arrow = headEl.querySelector('.svc-arrow');
  const isOpen = children.style.display !== 'none';
  children.style.display = isOpen ? 'none' : 'block';
  arrow.textContent = isOpen ? '▸' : '▾';
}

// عند تغيير قسم (L1/L2): تحديد أو إلغاء كل ما داخله
function svcParentChanged(cb) {
  const node = cb.closest('.svc-node');
  node.querySelectorAll('input[type="checkbox"]').forEach(c => {
    if (c !== cb) c.checked = cb.checked;
  });
  svcSyncAncestors(cb);
}

// عند تغيير خدمة مفردة: مزامنة حالة الأباء (محدد/شبه محدد)
function svcLeafChanged(cb) {
  svcSyncAncestors(cb);
}

// مزامنة الأباء الصاعدي: checked إذا كل الأبناء محددون، indeterminate إذا جزئياً
function svcSyncAncestors(el) {
  let current = el.closest('.svc-node');
  while (current) {
    const parentCb = current.querySelector(':scope > .svc-node-head .svc-parent');
    if (parentCb) {
      const leafBoxes = current.querySelectorAll('input[name="services[]"]');
      const total = leafBoxes.length;
      const checkedCount = Array.from(leafBoxes).filter(c => c.checked).length;
      parentCb.checked = total > 0 && checkedCount === total;
      parentCb.indeterminate = checkedCount > 0 && checkedCount < total;
    }
    current = current.parentElement ? current.parentElement.closest('.svc-node') : null;
  }
}
// الأحرف الأولى للاسم (تُستخدم كاحتياط عند إزالة الصورة)
const AV_INITIALS = '<?= e($academicAvatar) ?>';

/* ── البيانات الشخصية: رفع الصورة + الحفظ ── */
let selectedAvatar = '';
let selectedAvatarCleared = false;
const AV_APP_BASE = '<?= htmlspecialchars($appBaseUrl, ENT_QUOTES) ?>';

function avAssetUrl(path) {
  if (!path) return '';
  if (/^(https?:)?\/\//.test(path)) return path;
  return AV_APP_BASE + '/' + String(path).replace(/^\/+/, '');
}

// رفع الصورة الشخصية عبر AJAX
function handleAvatarUpload(input) {
  const file = input.files && input.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('action', 'upload_avatar');
  fd.append('image', file);
  Toast.show('جارٍ رفع الصورة...', 'info');
  fetch('ajax/handler.php?action=upload_avatar', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        selectedAvatar = res.image;
        selectedAvatarCleared = false;
        showAvatarPreview(res.image);
        Toast.show('تم رفع الصورة — اضغط «حفظ التغييرات» لتأكيد الحفظ', 'success');
      } else {
        input.value = '';
        Toast.show(res.message || 'فشل رفع الصورة', 'error');
      }
    })
    .catch(() => { input.value = ''; Toast.show('حدث خطأ في الاتصال بالخادم', 'error'); });
}

// إزالة الصورة والعودة للأحرف الأولى
function removeAvatar() {
  selectedAvatar = '';
  selectedAvatarCleared = true;
  document.getElementById('avatarFile').value = '';
  document.getElementById('avatarDisplay').innerHTML = '<span style="display:flex">' + AV_INITIALS + '</span>';
  const uploadText = document.getElementById('avatarUploadText');
  if (uploadText) uploadText.textContent = 'تمت إزالة الصورة — اضغط «حفظ التغييرات» للتأكيد';
}

// معاينة فورية للصورة بعد الرفع
function showAvatarPreview(src) {
  const display = document.getElementById('avatarDisplay');
  const esc = src.replace(/'/g, "\\'");
  display.innerHTML = '<img src="' + avAssetUrl(esc) + '" alt="الصورة الشخصية" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display=\'none\';document.getElementById(\'avatarInitials\').style.display=\'flex\'" /><span id="avatarInitials" style="display:none">' + AV_INITIALS + '</span>';
  const uploadText = document.getElementById('avatarUploadText');
  if (uploadText) uploadText.textContent = 'تم اختيار صورة جديدة — اضغط «حفظ التغييرات»';
}

// مزامنة أولية عند تحميل الصفحة (حالة الأباء حسب الخدمات المحفوظة)
(function svcInitTree() {
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.svc-parent').forEach(cb => {
      const node = cb.closest('.svc-node');
      const leafBoxes = node.querySelectorAll('input[name="services[]"]');
      const checkedCount = Array.from(leafBoxes).filter(c => c.checked).length;
      const total = leafBoxes.length;
      cb.checked = total > 0 && checkedCount === total;
      cb.indeterminate = checkedCount > 0 && checkedCount < total;
    });
  });
})();
</script>
</body>
</html>
