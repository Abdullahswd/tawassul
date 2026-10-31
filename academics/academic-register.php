<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_once __DIR__ . '/../config/academic_hierarchy.php';

// Clear dangling Google session if not coming from Google flow
if (isset($_SESSION['google_temp_user']) && !isset($_GET['google'])) {
    unset($_SESSION['google_temp_user']);
}

$db = db();
ensureHierarchyTables($db);
$hierarchyTree = getHierarchyFullTree(true);

// ── بناء شجرة الخدمات (L1 → L2 → L3) بنفس تصميم وترتيب الإعدادات ──
$allServicesList = getAllServices(true);
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

$svcRenderTree = function ($parentId, $level) use (&$svcRenderTree, $svcChildrenMap) {
    foreach ($svcChildrenMap[$parentId] ?? [] as $s) {
        $sid = (int)$s['id'];
        $icon = htmlspecialchars($s['icon'] ?: '🔬', ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8');

        if ($level < 3 && !empty($svcChildrenMap[$sid])): ?>
          <div class="svc-node" data-node="<?= $sid ?>" data-level="<?= $level ?>">
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
                   onchange="svcLeafChanged(this)" />
            <span class="svc-node-icon"><?= $icon ?></span>
            <span class="svc-node-name"><?= $name ?></span>
          </label>
        <?php endif;
    }
};
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>تسجيل أكاديمي - Eduroad</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/style.css"/>
  <style>
    .register-page{min-height:100vh;background:var(--bg-main)}
    .register-header{background:linear-gradient(135deg,#1e1b4b,#312e81);padding:0 32px;height:70px;display:flex;align-items:center;justify-content:space-between}
    .register-body{max-width:800px;margin:0 auto;padding:36px 20px}
    .degree-section{border:1.5px solid var(--border-color);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
    .degree-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:var(--bg-main);cursor:pointer;user-select:none}
    .degree-body{padding:20px;display:none}
    .degree-body.open{display:block}
    /* ── شجرة الخدمات ثلاثية المستويات (مطابقة لتصميم الإعدادات) ── */
    .svc-tree { display: flex; flex-direction: column; gap: 10px; }
    .svc-node { border: 1.5px solid var(--border-color); border-radius: 12px; overflow: hidden; background: var(--bg-card, #ffffff); }
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
    .svc-node-head > .svc-node-name { flex: 1; cursor: pointer; }
    .svc-node-head .svc-check-label { flex: 0 0 auto; cursor: pointer; }
    .svc-arrow { color: var(--text-secondary); font-size: 14px; flex-shrink: 0; transition: transform .2s; }
    .svc-children {
      padding: 10px 12px 12px; display: flex; flex-direction: column; gap: 8px;
      border-top: 1px dashed var(--border-color); background: var(--bg-main);
    }
    .svc-node .svc-children .svc-node { background: var(--bg-card, #ffffff); }
    .svc-leaf {
      display: flex; align-items: center; gap: 10px; padding: 9px 12px;
      border: 1.5px solid var(--border-color); border-radius: 10px;
      background: var(--bg-card, #ffffff); cursor: pointer; transition: border-color .2s, background .2s;
    }
    .svc-leaf:hover { border-color: var(--primary); }
    .svc-leaf:has(input:checked) { border-color: var(--primary); background: rgba(99,102,241,.06); }
    .svc-node[data-level="2"] .svc-node-name { font-weight: 600; font-size: 13px; }
  </style>
</head>
<body>

<div class="register-page">

  <!-- Header -->
  <div class="register-header">
    <div style="display:flex;align-items:center;gap:12px">
      <div class="logo-icon" style="font-size:22px;background:none;box-shadow:none;overflow:hidden;border-radius:10px"><img src="../image/eduroad_logo.png" alt="Eduroad" style="width:100%;height:100%;object-fit:contain" /></div>
      <span style="font-size:18px;font-weight:800;color:#fff">Eduroad</span>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <a href="academics-list.php" class="btn btn-ghost btn-sm" style="color:rgba(255,255,255,.7)">←عرض الأكاديميين</a>
      <button class="nav-btn dark-toggle" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#fff">🌙</button>
    </div>
  </div>

  <div class="register-body">

    <!-- Page Title -->
    <div style="text-align:center;margin-bottom:40px" class="anim-up">
      <div style="width:70px;height:70px;border-radius:20px;background:linear-gradient(135deg,#6366f1,#818cf8);display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 16px;box-shadow:0 8px 24px rgba(99,102,241,.3)">🎓</div>
      <h1 style="font-size:28px;font-weight:900;color:var(--text-primary);margin-bottom:8px">سجّل كأكاديمي في Eduroad</h1>
      <p style="color:var(--text-secondary);font-size:15px">انضم لمنصتنا وابدأ في تقديم خدماتك لآلاف الطلاب</p>
    </div>

    <!-- Progress Bar -->
    <div style="background:var(--border-color);border-radius:6px;height:4px;margin-bottom:36px;overflow:hidden">
      <div id="formProgressBar" style="height:100%;background:linear-gradient(90deg,#6366f1,#818cf8);border-radius:6px;width:0%;transition:width .4s ease"></div>
    </div>

    <!-- Step Indicators -->
    <div class="step-progress anim-up delay-1">
      <div class="step-item active" onclick="StepForm.goTo(1)" style="cursor:pointer">
        <div class="step-circle">1</div>
        <div class="step-label">البيانات الشخصية</div>
      </div>
      <div class="step-item" onclick="StepForm.goTo(2)" style="cursor:pointer">
        <div class="step-circle">2</div>
        <div class="step-label">المؤهلات</div>
      </div>
      <div class="step-item" onclick="StepForm.goTo(3)" style="cursor:pointer">
        <div class="step-circle">3</div>
        <div class="step-label">الخدمات</div>
      </div>
      <div class="step-item" onclick="StepForm.goTo(4)" style="cursor:pointer">
        <div class="step-circle">4</div>
        <div class="step-label">المراجعة</div>
      </div>
    </div>

    <!-- Form -->
    <form id="registerForm" onsubmit="return false">

      <!-- ===== STEP 1: Personal Info ===== -->
      <div class="form-step active" id="step-1">
        <div class="card" style="padding:28px">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;font-size:20px">👤</div>
            <div>
              <h2 style="font-size:18px;font-weight:800;color:var(--text-primary)">البيانات الشخصية</h2>
              <p style="font-size:13px;color:var(--text-secondary)">معلوماتك الأساسية</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-group" style="grid-column:span 2">
              <label class="form-label">الاسم الكامل <span style="color:var(--danger)">*</span></label>
              <input class="form-input" id="fullName" name="fullName" placeholder="ادخل اسمك الرباعي" value="<?= htmlspecialchars($_SESSION['google_temp_user']['name'] ?? '') ?>" required/>
            </div>

            <div class="form-group">
              <label class="form-label">الجنس <span style="color:var(--danger)">*</span></label>
              <select class="form-input form-select" style="padding-left:36px" name="gender" required>
                <option value="">اختر...</option>
                <option value="ذكر">ذكر</option>
                <option value="أنثى">أنثى</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">رقم الجوال <span style="color:var(--danger)">*</span></label>
              <input class="form-input" name="phone" type="tel" placeholder="ادخل رقم جوالك" dir="ltr" required/>
            </div>

            <div class="form-group">
              <label class="form-label">رقم واتساب <span style="color:var(--danger)">*</span></label>
              <input class="form-input" name="whatsapp" type="tel" placeholder="ادخل رقم واتساب" dir="ltr" required/>
            </div>

            <div class="form-group">
              <label class="form-label">البريد الإلكتروني <span style="color:var(--danger)">*</span></label>
              <input class="form-input" name="email" type="email" placeholder="example@email.com" dir="ltr" value="<?= htmlspecialchars($_SESSION['google_temp_user']['email'] ?? '') ?>" <?= isset($_SESSION['google_temp_user']) ? 'readonly style="background:#f3f4f6"' : '' ?> required/>
            </div>

            <?php if (!isset($_SESSION['google_temp_user'])): ?>
            <div class="form-group">
              <label class="form-label">كلمة المرور للدخول <span style="color:var(--danger)">*</span></label>
              <input class="form-input" id="regPassword" name="password" type="password" placeholder="••••••••" minlength="6" required/>
            </div>
            <div class="form-group">
              <label class="form-label">تأكيد كلمة المرور <span style="color:var(--danger)">*</span></label>
              <input class="form-input" id="regPasswordConfirm" name="password_confirm" type="password" placeholder="••••••••" minlength="6" required/>
              <p class="form-hint" id="passwordMatchHint" style="display:none;font-size:12px;margin-top:4px"></p>
            </div>
            <?php else: ?>
              <input type="hidden" name="password" value="<?= bin2hex(random_bytes(8)) ?>" />
              <input type="hidden" name="password_confirm" value="" />
              <input type="hidden" name="google_id" value="<?= htmlspecialchars($_SESSION['google_temp_user']['google_id']) ?>" />
            <?php endif; ?>

            <div class="form-group">
              <label class="form-label">مكان الميلاد</label>
              <input class="form-input" name="birthPlace" placeholder="ادخل مكان الميلاد"/>
            </div>

            <div class="form-group">
              <label class="form-label">تاريخ الميلاد <span style="color:var(--danger)">*</span></label>
              <input class="form-input" name="birthDate" type="date" required/>
            </div>

            <div class="form-group" style="grid-column:span 2">
              <label class="form-label">العنوان الحالي <span style="color:var(--danger)">*</span></label>
              <input class="form-input" name="address" placeholder="المدينة، الحي، الشارع" required/>
            </div>

            <div class="form-group" style="grid-column:span 2">
              <label class="form-label">نبذة تعريفية <span style="color:var(--danger)">*</span></label>
              <textarea class="form-input" name="bio" rows="4" placeholder="اكتب نبذة عن نفسك وخبراتك العلمية والأكاديمية..." required></textarea>
              <p class="form-hint">الحد الأدنى 20 حرفاً</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ===== STEP 2: Qualifications ===== -->
      <div class="form-step" id="step-2">
        <div class="card" style="padding:28px">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;font-size:20px">🎓</div>
            <div>
              <h2 style="font-size:18px;font-weight:800;color:var(--text-primary)">المؤهلات الأكاديمية</h2>
              <p style="font-size:13px;color:var(--text-secondary)">يمكنك إضافة شهادة واحدة أو أكثر</p>
            </div>
          </div>

          <!-- Bachelor -->
          <div class="degree-section">
            <div class="degree-header" onclick="toggleDegree('bachelor')">
              <div style="display:flex;align-items:center;gap:12px">
                <span style="font-size:22px">🏫</span>
                <div>
                  <div style="font-weight:700;color:var(--text-primary)">البكالوريوس</div>
                  <div style="font-size:12px;color:var(--text-secondary)">درجة البكالوريوس (الكلية ➜ القسم ➜ التخصص)</div>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <label class="toggle-switch" onclick="event.stopPropagation()">
                  <input type="checkbox" id="hasBachelor" onchange="if(this.checked)openDegree('bachelor')"/>
                  <span class="toggle-slider"></span>
                </label>
                <span id="bachelorArrow" style="color:var(--text-secondary);transition:transform .2s">▾</span>
              </div>
            </div>
            <div class="degree-body" id="bachelorBody">
              <input type="hidden" name="bsCollegeId" id="bsCollegeId" value=""/>
              <input type="hidden" name="bsDepartmentId" id="bsDepartmentId" value=""/>
              <input type="hidden" name="bsSpecializationId" id="bsSpecializationId" value=""/>

              <!-- Hierarchy Row: College -> Department -> Specialization -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="form-group">
                  <label class="form-label">الكلية <span style="color:var(--danger)">*</span></label>
                  <select class="form-input form-select" id="bsCollegeSelect" onchange="handleRegisterCollegeChange('bs')">
                    <option value="">-- اختر الكلية --</option>
                  </select>
                  <div id="bsCollegeCustomWrap" style="display:none;margin-top:6px">
                    <input class="form-input" id="bsCollegeCustom" placeholder="اكتب اسم الكلية يدوياً..."/>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">القسم <span style="color:var(--danger)">*</span></label>
                  <select class="form-input form-select" id="bsDeptSelect" onchange="handleRegisterDeptChange('bs')" disabled>
                    <option value="">-- اختر الكلية أولاً --</option>
                  </select>
                  <div id="bsDeptCustomWrap" style="display:none;margin-top:6px">
                    <input class="form-input" id="bsDeptCustom" placeholder="اكتب اسم القسم يدوياً..."/>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">التخصص <span style="color:var(--danger)">*</span></label>
                  <select class="form-input form-select" id="bsMajorSelect" onchange="handleRegisterMajorChange('bs')" disabled>
                    <option value="">-- اختر القسم أولاً --</option>
                  </select>
                  <div id="bsMajorCustomWrap" style="display:none;margin-top:6px">
                    <input class="form-input" id="bsMajorCustom" placeholder="اكتب اسم التخصص يدوياً..."/>
                  </div>
                </div>
              </div>

              <!-- General Info Row -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                  <label class="form-label">الجامعة <span style="color:var(--danger)">*</span></label>
                  <input class="form-input" name="bsUniversity" placeholder="ادخل اسم الجامعة"/>
                </div>
                <div class="form-group">
                  <label class="form-label">الدولة</label>
                  <input class="form-input" name="bsCountry" placeholder="ادخل اسم بلد التخرج"/>
                </div>
                <div class="form-group">
                  <label class="form-label">سنة التخرج</label>
                  <input class="form-input" name="bsYear" type="number" placeholder="ادخل سنة التخرج" min="1980" max="2030"/>
                </div>
                <div class="form-group">
                  <label class="form-label">وثيقة التخرج (شهادة/مستند - اختياري)</label>
                  <input class="form-input" name="bsDocument" type="file" accept=".pdf,.jpg,.jpeg,.png"/>
                </div>
              </div>
            </div>
          </div>

          <!-- Masters -->
          <div class="degree-section">
            <div class="degree-header" onclick="toggleDegree('masters')">
              <div style="display:flex;align-items:center;gap:12px">
                <span style="font-size:22px">📜</span>
                <div>
                  <div style="font-weight:700;color:var(--text-primary)">الماجستير</div>
                  <div style="font-size:12px;color:var(--text-secondary)">درجة الماجستير (الكلية ➜ القسم ➜ التخصص)</div>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <label class="toggle-switch" onclick="event.stopPropagation()">
                  <input type="checkbox" id="hasMasters" onchange="if(this.checked)openDegree('masters')"/>
                  <span class="toggle-slider"></span>
                </label>
                <span id="mastersArrow" style="color:var(--text-secondary)">▾</span>
              </div>
            </div>
            <div class="degree-body" id="mastersBody">
              <input type="hidden" name="msCollegeId" id="msCollegeId" value=""/>
              <input type="hidden" name="msDepartmentId" id="msDepartmentId" value=""/>
              <input type="hidden" name="msSpecializationId" id="msSpecializationId" value=""/>

              <!-- Hierarchy Row: College -> Department -> Specialization -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="form-group">
                  <label class="form-label">الكلية</label>
                  <select class="form-input form-select" id="msCollegeSelect" onchange="handleRegisterCollegeChange('ms')">
                    <option value="">-- اختر الكلية --</option>
                  </select>
                  <div id="msCollegeCustomWrap" style="display:none;margin-top:6px">
                    <input class="form-input" id="msCollegeCustom" placeholder="اكتب اسم الكلية يدوياً..."/>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">القسم</label>
                  <select class="form-input form-select" id="msDeptSelect" onchange="handleRegisterDeptChange('ms')" disabled>
                    <option value="">-- اختر الكلية أولاً --</option>
                  </select>
                  <div id="msDeptCustomWrap" style="display:none;margin-top:6px">
                    <input class="form-input" id="msDeptCustom" placeholder="اكتب اسم القسم يدوياً..."/>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">التخصص</label>
                  <select class="form-input form-select" id="msMajorSelect" onchange="handleRegisterMajorChange('ms')" disabled>
                    <option value="">-- اختر القسم أولاً --</option>
                  </select>
                  <div id="msMajorCustomWrap" style="display:none;margin-top:6px">
                    <input class="form-input" id="msMajorCustom" placeholder="اكتب اسم التخصص يدوياً..."/>
                  </div>
                </div>
              </div>

              <!-- General Info Row -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                  <label class="form-label">الجامعة</label>
                  <input class="form-input" name="msUniversity" placeholder="ادخل اسم الجامعة"/>
                </div>
                <div class="form-group">
                  <label class="form-label">الدولة</label>
                  <input class="form-input" name="msCountry" placeholder="ادخل اسم بلد التخرج"/>
                </div>
                <div class="form-group">
                  <label class="form-label">سنة التخرج</label>
                  <input class="form-input" name="msYear" type="number" placeholder="ادخل سنة التخرج"/>
                </div>
                <div class="form-group">
                  <label class="form-label">وثيقة التخرج (شهادة/مستند - اختياري)</label>
                  <input class="form-input" name="msDocument" type="file" accept=".pdf,.jpg,.jpeg,.png"/>
                </div>
              </div>
            </div>
          </div>

          <!-- PhD -->
          <div class="degree-section">
            <div class="degree-header" onclick="toggleDegree('phd')">
              <div style="display:flex;align-items:center;gap:12px">
                <span style="font-size:22px">🎓</span>
                <div>
                  <div style="font-weight:700;color:var(--text-primary)">الدكتوراه</div>
                  <div style="font-size:12px;color:var(--text-secondary)">درجة الدكتوراه (الكلية ➜ القسم ➜ التخصص)</div>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <label class="toggle-switch" onclick="event.stopPropagation()">
                  <input type="checkbox" id="hasPhd" onchange="if(this.checked)openDegree('phd')"/>
                  <span class="toggle-slider"></span>
                </label>
                <span id="phdArrow" style="color:var(--text-secondary)">▾</span>
              </div>
            </div>
            <div class="degree-body" id="phdBody">
              <input type="hidden" name="phdCollegeId" id="phdCollegeId" value=""/>
              <input type="hidden" name="phdDepartmentId" id="phdDepartmentId" value=""/>
              <input type="hidden" name="phdSpecializationId" id="phdSpecializationId" value=""/>

              <!-- Hierarchy Row: College -> Department -> Specialization -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="form-group">
                  <label class="form-label">الكلية</label>
                  <select class="form-input form-select" id="phdCollegeSelect" onchange="handleRegisterCollegeChange('phd')">
                    <option value="">-- اختر الكلية --</option>
                  </select>
                  <div id="phdCollegeCustomWrap" style="display:none;margin-top:6px">
                    <input class="form-input" id="phdCollegeCustom" placeholder="اكتب اسم الكلية يدوياً..."/>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">القسم</label>
                  <select class="form-input form-select" id="phdDeptSelect" onchange="handleRegisterDeptChange('phd')" disabled>
                    <option value="">-- اختر الكلية أولاً --</option>
                  </select>
                  <div id="phdDeptCustomWrap" style="display:none;margin-top:6px">
                    <input class="form-input" id="phdDeptCustom" placeholder="اكتب اسم القسم يدوياً..."/>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">التخصص الدقيق</label>
                  <select class="form-input form-select" id="phdMajorSelect" onchange="handleRegisterMajorChange('phd')" disabled>
                    <option value="">-- اختر القسم أولاً --</option>
                  </select>
                  <div id="phdMajorCustomWrap" style="display:none;margin-top:6px">
                    <input class="form-input" id="phdMajorCustom" placeholder="اكتب اسم التخصص يدوياً..."/>
                  </div>
                </div>
              </div>

              <!-- General Info Row -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                  <label class="form-label">الجامعة</label> 
                  <input class="form-input" name="phdUniversity" placeholder="ادخل اسم الجامعة"/>
                </div>
                <div class="form-group">
                  <label class="form-label">الدولة</label>
                  <input class="form-input" name="phdCountry" placeholder="ادخل اسم بلد التخرج"/>
                </div>
                <div class="form-group">
                  <label class="form-label">سنة التخرج</label>
                  <input class="form-input" name="phdYear" type="number" placeholder="ادخل سنة التخرج" min="1980" max="2030"/>
                </div>
                <div class="form-group">
                  <label class="form-label">وثيقة التخرج (شهادة/مستند - اختياري)</label>
                  <input class="form-input" name="phdDocument" type="file" accept=".pdf,.jpg,.jpeg,.png"/>
                </div>
              </div>
            </div>
          </div>

          <div class="alert alert-info"><span>ℹ️</span><span>يجب تفعيل شهادة واحدة على الأقل وتعبئتها لاستكمال التسجيل</span></div>
        </div>
      </div>

      <!-- ===== STEP 3: Services ===== -->
      <div class="form-step" id="step-3">
        <div class="card" style="padding:28px">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(245,158,11,.1);display:flex;align-items:center;justify-content:center;font-size:20px">⚙️</div>
            <div>
              <h2 style="font-size:18px;font-weight:800;color:var(--text-primary)">الخدمات المقدَّمة</h2>
              <p style="font-size:13px;color:var(--text-secondary)">اختر قسمًا رئيسيًا لتحديد كل محتوياته، أو قسمًا فرعيًا لتحديد خدماته، أو خدمة مفردة (على الأقل خدمة واحدة)</p>
            </div>
          </div>

          <!-- شجرة الخدمات ثلاثية المستويات بنفس تصميم الإعدادات -->
          <div id="servicesTree" class="svc-tree mb-5">
            <?php $svcRenderTree(0, 1); ?>
          </div>

          <!-- Pricing -->
          <div style="border:1.5px solid var(--border-color);border-radius:var(--radius);padding:20px;margin-top:20px">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:16px">وقت التسليم</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="form-group">
                <label class="form-label">متوسط وقت التسليم المعتاد</label>
                <select class="form-input form-select" name="deliveryTime" style="padding-left:36px">
                  <option>24 ساعة</option>
                  <option selected>2-3 أيام</option>
                  <option>أسبوع</option>
                  <option>أسبوعان</option>
                  <option>شهر</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ===== STEP 4: Review & Submit ===== -->
      <div class="form-step" id="step-4">
        <div class="card" style="padding:28px;margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;font-size:20px">✅</div>
            <div>
              <h2 style="font-size:18px;font-weight:800;color:var(--text-primary)">مراجعة البيانات</h2>
              <p style="font-size:13px;color:var(--text-secondary)">راجع معلوماتك قبل الإرسال</p>
            </div>
          </div>

          <div id="reviewSummary" style="display:flex;flex-direction:column;gap:16px"></div>

          <!-- Terms -->
          <div style="margin-top:24px;padding:18px;background:var(--bg-main);border-radius:12px">
            <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer">
              <input type="checkbox" id="agreeTerms" style="margin-top:4px;width:18px;height:18px;accent-color:var(--primary);flex-shrink:0"/>
              <span style="font-size:13px;color:var(--text-secondary);line-height:1.7">أوافق على <a href="#" style="color:var(--primary)">شروط وأحكام</a> المنصة وأتعهد بأن جميع المعلومات المقدمة صحيحة ودقيقة، وأن الشهادات الأكاديمية المرفوعة أصلية وموثقة.</span>
            </label>
          </div>
        </div>
      </div>

    </form><!-- /registerForm -->

    <!-- Success Message -->
    <div id="formSuccess" style="display:none">
      <div class="card" style="padding:60px 40px;text-align:center">
        <div style="font-size:72px;margin-bottom:20px">🎉</div>
        <h2 style="font-size:26px;font-weight:900;color:var(--text-primary);margin-bottom:12px">تم تسجيل حسابك بنجاح!</h2>
        <p style="color:var(--text-secondary);font-size:15px;margin-bottom:28px" id="formSuccessMsg">تم إرسال رمز التحقق (OTP) إلى بريدك الإلكتروني. جارٍ تحويلك لصفحة التحقق لتأكيد الحساب وإكمال الدخول...</p>
        <div style="display:flex;gap:12px;justify-content:center">
          <a href="../verify-otp.php" id="verifyOtpBtn" class="btn btn-primary" style="padding:12px 32px;font-weight:800;background:linear-gradient(135deg,#6366f1,#4f46e5)">🔐 إدخال رمز التحقق (OTP) والمتابعة →</a>
        </div>
      </div>
    </div>

    <!-- Navigation Buttons -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:24px;padding:0 4px" id="formNavButtons">
      <button class="btn btn-outline" id="prevBtn" onclick="StepForm.prev()" style="display:none">← السابق</button>
      <span id="stepCounter" style="font-size:13px;color:var(--text-secondary);margin:auto">الخطوة 1 من 4</span>
      <button class="btn btn-primary" id="nextBtn" onclick="StepForm.next()">التالي ←</button>
      <button class="btn btn-success" id="submitBtn" onclick="StepForm.submit()" style="display:none">✅ إرسال الطلب</button>
    </div>

  </div><!-- /register-body -->
</div>

<script src="assets/js/main.js"></script>
<script>
const csrfTokenJs = document.querySelector('meta[name="csrf-token"]')?.content || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>';

// Load dynamic hierarchy
const COLLEGES_HIERARCHY = <?= json_encode($hierarchyTree, JSON_UNESCAPED_UNICODE) ?> || [];

// ---- Initialize Hierarchy Selects ----
function initDegreeHierarchy(pfx) {
  const colSel = document.getElementById(pfx + 'CollegeSelect');
  if (!colSel) return;

  colSel.innerHTML = '<option value="">-- اختر الكلية --</option>' + 
    COLLEGES_HIERARCHY.map(c => `<option value="${c.id}">${c.icon || '🏛️'} ${c.name}</option>`).join('') +
    '<option value="__other__">➕ أخرى (كتابة يدوي)...</option>';
}

function handleRegisterCollegeChange(pfx) {
  const colSel = document.getElementById(pfx + 'CollegeSelect');
  const deptSel = document.getElementById(pfx + 'DeptSelect');
  const majorSel = document.getElementById(pfx + 'MajorSelect');
  const customWrap = document.getElementById(pfx + 'CollegeCustomWrap');
  const idInput = document.getElementById(pfx + 'CollegeId');

  const val = colSel.value;
  idInput.value = (val && val !== '__other__') ? val : '';

  // Auto-activate degree checkbox
  if (val) {
    if (pfx === 'bs' && document.getElementById('hasBachelor')) document.getElementById('hasBachelor').checked = true;
    if (pfx === 'ms' && document.getElementById('hasMasters')) document.getElementById('hasMasters').checked = true;
    if (pfx === 'phd' && document.getElementById('hasPhd')) document.getElementById('hasPhd').checked = true;
  }

  if (val === '__other__') {
    customWrap.style.display = 'block';
    deptSel.disabled = false;
    deptSel.innerHTML = '<option value="__other__">➕ أخرى (كتابة يدوي)...</option>';
    document.getElementById(pfx + 'DeptCustomWrap').style.display = 'block';
    majorSel.disabled = false;
    majorSel.innerHTML = '<option value="__other__">➕ أخرى (كتابة يدوي)...</option>';
    document.getElementById(pfx + 'MajorCustomWrap').style.display = 'block';
    return;
  }

  customWrap.style.display = 'none';
  document.getElementById(pfx + 'CollegeCustom').value = '';

  if (!val) {
    deptSel.disabled = true;
    deptSel.innerHTML = '<option value="">-- اختر الكلية أولاً --</option>';
    document.getElementById(pfx + 'DeptCustomWrap').style.display = 'none';
    majorSel.disabled = true;
    majorSel.innerHTML = '<option value="">-- اختر القسم أولاً --</option>';
    document.getElementById(pfx + 'MajorCustomWrap').style.display = 'none';
    return;
  }

  const college = COLLEGES_HIERARCHY.find(c => parseInt(c.id) === parseInt(val));
  const depts = (college && college.departments) ? college.departments : [];

  deptSel.disabled = false;
  deptSel.innerHTML = '<option value="">-- اختر القسم --</option>' +
    depts.map(d => `<option value="${d.id}">📂 ${d.name}</option>`).join('') +
    '<option value="__other__">➕ أخرى (كتابة يدوي)...</option>';
  document.getElementById(pfx + 'DeptCustomWrap').style.display = 'none';

  majorSel.disabled = true;
  majorSel.innerHTML = '<option value="">-- اختر القسم أولاً --</option>';
  document.getElementById(pfx + 'MajorCustomWrap').style.display = 'none';
}

function handleRegisterDeptChange(pfx) {
  const colSel = document.getElementById(pfx + 'CollegeSelect');
  const deptSel = document.getElementById(pfx + 'DeptSelect');
  const majorSel = document.getElementById(pfx + 'MajorSelect');
  const customWrap = document.getElementById(pfx + 'DeptCustomWrap');
  const idInput = document.getElementById(pfx + 'DepartmentId');

  const val = deptSel.value;
  idInput.value = (val && val !== '__other__') ? val : '';

  if (val === '__other__') {
    customWrap.style.display = 'block';
    majorSel.disabled = false;
    majorSel.innerHTML = '<option value="__other__">➕ أخرى (كتابة يدوي)...</option>';
    document.getElementById(pfx + 'MajorCustomWrap').style.display = 'block';
    return;
  }

  customWrap.style.display = 'none';
  document.getElementById(pfx + 'DeptCustom').value = '';

  if (!val) {
    majorSel.disabled = true;
    majorSel.innerHTML = '<option value="">-- اختر القسم أولاً --</option>';
    document.getElementById(pfx + 'MajorCustomWrap').style.display = 'none';
    return;
  }

  const college = COLLEGES_HIERARCHY.find(c => parseInt(c.id) === parseInt(colSel.value));
  const dept = (college && college.departments) ? college.departments.find(d => parseInt(d.id) === parseInt(val)) : null;
  const specs = (dept && dept.specializations) ? dept.specializations : [];

  majorSel.disabled = false;
  majorSel.innerHTML = '<option value="">-- اختر التخصص --</option>' +
    specs.map(s => `<option value="${s.id}">🎯 ${s.name}</option>`).join('') +
    '<option value="__other__">➕ أخرى (كتابة يدوي)...</option>';
  document.getElementById(pfx + 'MajorCustomWrap').style.display = 'none';
}

function handleRegisterMajorChange(pfx) {
  const majorSel = document.getElementById(pfx + 'MajorSelect');
  const customWrap = document.getElementById(pfx + 'MajorCustomWrap');
  const idInput = document.getElementById(pfx + 'SpecializationId');

  const val = majorSel.value;
  idInput.value = (val && val !== '__other__') ? val : '';

  if (val === '__other__') {
    customWrap.style.display = 'block';
  } else {
    customWrap.style.display = 'none';
    document.getElementById(pfx + 'MajorCustom').value = '';
  }
}

// Helper to get selected name or custom text cleanly
function getDegreeResolvedName(pfx, type) {
  const colSel = document.getElementById(pfx + 'CollegeSelect');
  const deptSel = document.getElementById(pfx + 'DeptSelect');
  const majorSel = document.getElementById(pfx + 'MajorSelect');

  if (type === 'College') {
    if (!colSel || !colSel.value) return '';
    if (colSel.value === '__other__') return document.getElementById(pfx + 'CollegeCustom')?.value.trim() || '';
    const found = Array.isArray(COLLEGES_HIERARCHY) ? COLLEGES_HIERARCHY.find(c => String(c.id) === String(colSel.value)) : null;
    if (found && found.name) return found.name.trim();
    const opt = colSel.options[colSel.selectedIndex];
    return opt ? opt.textContent.replace(/^[\s\S]*?[ \t]/, '').trim() || opt.textContent.trim() : '';
  }

  if (type === 'Dept') {
    if (!deptSel || !deptSel.value) return '';
    if (deptSel.value === '__other__') return document.getElementById(pfx + 'DeptCustom')?.value.trim() || '';
    const colId = colSel ? colSel.value : '';
    const college = Array.isArray(COLLEGES_HIERARCHY) ? COLLEGES_HIERARCHY.find(c => String(c.id) === String(colId)) : null;
    if (college && college.departments) {
      const found = college.departments.find(d => String(d.id) === String(deptSel.value));
      if (found && found.name) return found.name.trim();
    }
    const opt = deptSel.options[deptSel.selectedIndex];
    return opt ? opt.textContent.replace(/^[\s\S]*?[ \t]/, '').trim() || opt.textContent.trim() : '';
  }

  if (type === 'Major') {
    if (!majorSel || !majorSel.value) return '';
    if (majorSel.value === '__other__') return document.getElementById(pfx + 'MajorCustom')?.value.trim() || '';
    const colId = colSel ? colSel.value : '';
    const deptId = deptSel ? deptSel.value : '';
    const college = Array.isArray(COLLEGES_HIERARCHY) ? COLLEGES_HIERARCHY.find(c => String(c.id) === String(colId)) : null;
    if (college && college.departments) {
      const dept = college.departments.find(d => String(d.id) === String(deptId));
      if (dept && dept.specializations) {
        const found = dept.specializations.find(s => String(s.id) === String(majorSel.value));
        if (found && found.name) return found.name.trim();
      }
    }
    const opt = majorSel.options[majorSel.selectedIndex];
    return opt ? opt.textContent.replace(/^[\s\S]*?[ \t]/, '').trim() || opt.textContent.trim() : '';
  }

  return '';
}

// ---- Helper to extract full degree details for review ----
function getDegreeDetails(pfx, label, emoji) {
  const col = getDegreeResolvedName(pfx, 'College');
  const dept = getDegreeResolvedName(pfx, 'Dept');
  const maj = getDegreeResolvedName(pfx, 'Major');
  const univ = document.querySelector(`input[name="${pfx}University"]`)?.value?.trim() || '';
  const year = document.querySelector(`input[name="${pfx}Year"]`)?.value?.trim() || '';
  const country = document.querySelector(`input[name="${pfx}Country"]`)?.value?.trim() || '';

  const isChecked = (pfx === 'bs' && document.getElementById('hasBachelor')?.checked)
    || (pfx === 'ms' && document.getElementById('hasMasters')?.checked)
    || (pfx === 'phd' && document.getElementById('hasPhd')?.checked);

  const hasData = !!(col || dept || maj || univ);
  if (!isChecked && !hasData) return null;

  // Sync checkbox state if data provided
  if (hasData) {
    if (pfx === 'bs' && document.getElementById('hasBachelor')) document.getElementById('hasBachelor').checked = true;
    if (pfx === 'ms' && document.getElementById('hasMasters')) document.getElementById('hasMasters').checked = true;
    if (pfx === 'phd' && document.getElementById('hasPhd')) document.getElementById('hasPhd').checked = true;
  }

  return {
    pfx,
    label,
    emoji,
    college: col || 'غير محدد',
    department: dept || 'غير محدد',
    specialization: maj || 'غير محدد',
    university: univ,
    year: year,
    country: country
  };
}

// ---- Degree toggles ----
function toggleDegree(id) {
  const body = document.getElementById(id + 'Body');
  const arrow = document.getElementById(id + 'Arrow');
  const isOpen = body.classList.contains('open');
  body.classList.toggle('open', !isOpen);
  if (arrow) arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
}
function openDegree(id) {
  const body = document.getElementById(id + 'Body');
  const arrow = document.getElementById(id + 'Arrow');
  body.classList.add('open');
  if (arrow) arrow.style.transform = 'rotate(180deg)';
}

// ---- دوال شجرة الخدمات (مطابقة لـ academic-settings.php) ----
function svcToggleCollapse(headEl) {
  const node = headEl.closest('.svc-node');
  const children = node.querySelector(':scope > .svc-children');
  const arrow = headEl.querySelector('.svc-arrow');
  const isOpen = children.style.display !== 'none';
  children.style.display = isOpen ? 'none' : 'block';
  arrow.textContent = isOpen ? '▸' : '▾';
}

function svcParentChanged(cb) {
  const node = cb.closest('.svc-node');
  node.querySelectorAll('input[type="checkbox"]').forEach(c => {
    if (c !== cb) c.checked = cb.checked;
  });
  svcSyncAncestors(cb);
}

function svcLeafChanged(cb) {
  svcSyncAncestors(cb);
}

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

// ---- Render review summary (STEP 4) ----
function renderReviewSummary() {
  const summary = document.getElementById('reviewSummary');
  if (!summary) return;

  const name = document.getElementById('fullName')?.value || 'لم يتم إدخال الاسم';
  const email = document.querySelector('input[name="email"]')?.value || '';
  const phone = document.querySelector('input[name="phone"]')?.value || '';
  const checkedServices = [...document.querySelectorAll('input[name="services[]"]:checked')].length;

  const degrees = [
    getDegreeDetails('bs', 'البكالوريوس', '🏫'),
    getDegreeDetails('ms', 'الماجستير', '📜'),
    getDegreeDetails('phd', 'الدكتوراه', '🎓')
  ].filter(Boolean);

  let degreesHtml = '';
  if (degrees.length === 0) {
    degreesHtml = '<div style="color:var(--danger);font-size:13px;font-weight:600;padding:10px;background:rgba(239,68,68,.08);border-radius:8px">⚠️ لم يتم تحديد أي مؤهل أكاديمي (يرجى العودة للخطوة 2 واختيار الكلية والقسم والتخصص)</div>';
  } else {
    degreesHtml = degrees.map(d => `
      <div style="background:var(--bg-card,#fff);border:1.5px solid var(--border-color);border-radius:12px;padding:14px 16px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,.04)">
        <div style="display:flex;align-items:center;gap:8px;font-weight:800;color:var(--primary);font-size:14.5px;margin-bottom:10px">
          <span>${d.emoji}</span> <span>درجة ${d.label}</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;font-size:13px">
          <div><span style="color:var(--text-secondary)">🏛️ الكلية: </span><strong style="color:var(--text-primary)">${d.college}</strong></div>
          <div><span style="color:var(--text-secondary)">📂 القسم: </span><strong style="color:var(--text-primary)">${d.department}</strong></div>
          <div><span style="color:var(--text-secondary)">🎯 التخصص: </span><strong style="color:var(--text-primary)">${d.specialization}</strong></div>
          ${d.university ? `<div><span style="color:var(--text-secondary)">🎓 الجامعة: </span><strong style="color:var(--text-primary)">${d.university} ${d.year ? `(${d.year})` : ''}</strong></div>` : ''}
        </div>
      </div>
    `).join('');
  }

  summary.innerHTML = `
    <div style="padding:16px;background:var(--bg-main);border-radius:12px;display:flex;align-items:center;gap:14px">
      <span style="font-size:26px">👤</span>
      <div style="flex:1">
        <div style="font-size:12px;color:var(--text-secondary)">البيانات الشخصية</div>
        <div style="font-weight:800;color:var(--text-primary);font-size:15px">${name}</div>
        <div style="font-size:12.5px;color:var(--text-secondary);margin-top:2px" dir="ltr">${email} ${phone ? '• ' + phone : ''}</div>
      </div>
    </div>

    <div style="padding:16px;background:var(--bg-main);border-radius:12px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <span style="font-size:24px">🎓</span>
        <div style="font-weight:800;color:var(--text-primary);font-size:15px">المؤهلات الأكاديمية (الكلية ➜ القسم ➜ التخصص)</div>
      </div>
      <div>${degreesHtml}</div>
    </div>

    <div style="padding:16px;background:var(--bg-main);border-radius:12px;display:flex;align-items:center;gap:14px">
      <span style="font-size:26px">⚙️</span>
      <div>
        <div style="font-size:12px;color:var(--text-secondary)">الخدمات المقدَّمة</div>
        <div style="font-weight:800;color:var(--text-primary);font-size:15px">${checkedServices} خدمة محددة</div>
      </div>
    </div>
  `;
}

// Hook StepForm.update so Review Summary renders whenever Step 4 is reached (via next, goTo, etc.)
const _origUpdate = StepForm.update.bind(StepForm);
StepForm.update = function() {
  _origUpdate();
  if (this.currentStep === 4) {
    renderReviewSummary();
  }
};

// Override StepForm.next to validate Step 1 (passwords), Step 2 (qualifications), Step 3 (services)
const _origNext = StepForm.next.bind(StepForm);
StepForm.next = function() {
  // Step 1: Personal info & password validation
  if (StepForm.currentStep === 1) {
    const pwd = document.getElementById('regPassword');
    const pwdConfirm = document.getElementById('regPasswordConfirm');
    if (pwd && pwdConfirm) {
      if (pwd.value.length < 6) {
        Toast.show('يجب ألا تقل كلمة المرور عن 6 خانات', 'error');
        pwd.focus();
        return;
      }
      if (pwd.value !== pwdConfirm.value) {
        Toast.show('كلمتا المرور غير متطابقتين', 'error');
        pwdConfirm.focus();
        return;
      }
    }
  }

  // Step 2: Ensure at least one degree is entered
  if (StepForm.currentStep === 2) {
    const hasBs = document.getElementById('hasBachelor')?.checked || !!getDegreeResolvedName('bs', 'College') || !!document.querySelector('input[name="bsUniversity"]')?.value;
    const hasMs = document.getElementById('hasMasters')?.checked || !!getDegreeResolvedName('ms', 'College') || !!document.querySelector('input[name="msUniversity"]')?.value;
    const hasPhd = document.getElementById('hasPhd')?.checked || !!getDegreeResolvedName('phd', 'College') || !!document.querySelector('input[name="phdUniversity"]')?.value;
    if (!hasBs && !hasMs && !hasPhd) {
      Toast.show('يرجى تحديد وتعبئة مؤهل أكاديمي واحد على الأقل', 'error');
      return;
    }
  }

  // Step 3: Ensure at least one service is selected
  if (StepForm.currentStep === 3) {
    const checked = document.querySelectorAll('input[name="services[]"]:checked');
    if (checked.length === 0) {
      Toast.show('يرجى اختيار خدمة واحدة على الأقل للاستمرار', 'error');
      return;
    }
  }

  _origNext();
  if (StepForm.currentStep === 4) renderReviewSummary();
};

// Override StepForm.submit to perform actual DB insert via AJAX fetch
StepForm.submit = function() {
  if (!this.validate()) return;

  const pwd = document.getElementById('regPassword');
  const pwdConfirm = document.getElementById('regPasswordConfirm');
  if (pwd && pwdConfirm) {
    if (pwd.value.length < 6) {
      Toast.show('يجب ألا تقل كلمة المرور عن 6 خانات', 'error');
      StepForm.goTo(1);
      return;
    }
    if (pwd.value !== pwdConfirm.value) {
      Toast.show('كلمتا المرور غير متطابقتين', 'error');
      StepForm.goTo(1);
      return;
    }
  }

  const checkedServices = document.querySelectorAll('input[name="services[]"]:checked');
  if (checkedServices.length === 0) {
    Toast.show('يرجى اختيار خدمة واحدة على الأقل في قسم الخدمات', 'error');
    StepForm.goTo(3);
    return;
  }

  const agree = document.getElementById('agreeTerms').checked;
  if (!agree) {
    Toast.show('يجب الموافقة على الشروط والأحكام للاستمرار', 'error');
    return;
  }

  const btn = document.getElementById('submitBtn');
  if (btn) { btn.innerHTML = '⏳ جاري الإرسال...'; btn.disabled = true; }

  const form = document.getElementById('registerForm');
  const formData = new FormData(form);
  formData.append('csrf_token', csrfTokenJs);

  const hasBs = document.getElementById('hasBachelor')?.checked || !!getDegreeResolvedName('bs', 'College') || !!document.querySelector('input[name="bsUniversity"]')?.value;
  if (hasBs) {
    formData.append('hasBachelor', '1');
    formData.set('bsCollege', getDegreeResolvedName('bs', 'College'));
    formData.set('bsDepartment', getDegreeResolvedName('bs', 'Dept'));
    formData.set('bsMajor', getDegreeResolvedName('bs', 'Major'));
  }

  const hasMs = document.getElementById('hasMasters')?.checked || !!getDegreeResolvedName('ms', 'College') || !!document.querySelector('input[name="msUniversity"]')?.value;
  if (hasMs) {
    formData.append('hasMasters', '1');
    formData.set('msCollege', getDegreeResolvedName('ms', 'College'));
    formData.set('msDepartment', getDegreeResolvedName('ms', 'Dept'));
    formData.set('msMajor', getDegreeResolvedName('ms', 'Major'));
  }

  const hasPhd = document.getElementById('hasPhd')?.checked || !!getDegreeResolvedName('phd', 'College') || !!document.querySelector('input[name="phdUniversity"]')?.value;
  if (hasPhd) {
    formData.append('hasPhd', '1');
    formData.set('phdCollege', getDegreeResolvedName('phd', 'College'));
    formData.set('phdDepartment', getDegreeResolvedName('phd', 'Dept'));
    formData.set('phdMajor', getDegreeResolvedName('phd', 'Major'));
  }

  fetch('ajax/handler.php?action=register', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      document.getElementById('formSuccess').style.display = 'block';
      document.getElementById('registerForm').style.display = 'none';
      document.getElementById('formNavButtons').style.display = 'none';
      if (data.message && document.getElementById('formSuccessMsg')) {
        document.getElementById('formSuccessMsg').textContent = data.message;
      }
      Toast.show(data.message || 'تم إرسال رمز التحقق إلى بريدك الإلكتروني', 'success');

      if (data.requires_otp && data.redirect_url) {
        setTimeout(() => {
          window.location.href = data.redirect_url;
        }, 1200);
      }
    } else {
      if (btn) { btn.innerHTML = '✅ إرسال الطلب'; btn.disabled = false; }
      Toast.show(data.message, 'error');
    }
  })
  .catch(err => {
    if (btn) { btn.innerHTML = '✅ إرسال الطلب'; btn.disabled = false; }
    Toast.show('حدث خطأ في الاتصال بالخادم.', 'error');
  });
};

document.addEventListener('DOMContentLoaded', () => {
  ['bs', 'ms', 'phd'].forEach(initDegreeHierarchy);
  StepForm.totalSteps = 4;
  StepForm.init();

  // Password confirmation live match hint
  const p1 = document.getElementById('regPassword');
  const p2 = document.getElementById('regPasswordConfirm');
  const hint = document.getElementById('passwordMatchHint');
  function checkPasswordMatch() {
    if (!p1 || !p2 || !hint) return;
    if (!p2.value) {
      hint.style.display = 'none';
      p2.style.borderColor = '';
      return;
    }
    hint.style.display = 'block';
    if (p1.value === p2.value) {
      hint.textContent = '✓ كلمتا المرور متطابقتان';
      hint.style.color = 'var(--success, #10b981)';
      p2.style.borderColor = 'var(--success, #10b981)';
    } else {
      hint.textContent = '✗ كلمتا المرور غير متطابقتين';
      hint.style.color = 'var(--danger, #ef4444)';
      p2.style.borderColor = 'var(--danger, #ef4444)';
    }
  }
  if (p1 && p2) {
    p1.addEventListener('input', checkPasswordMatch);
    p2.addEventListener('input', checkPasswordMatch);
  }
});
</script>
</body>
</html>
