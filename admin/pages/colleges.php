<?php
/**
 * ============================================================
 *  Eduroad Admin - Colleges, Departments & Specializations
 *  File: admin/pages/colleges.php
 * ============================================================
 */

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/academic_hierarchy.php';
requireAdmin();

$db = db();

// ============================================================
// 1. DATABASE SCHEMA & MIGRATION (Executes automatically on page load)
// ============================================================
ensureHierarchyTables($db);

// Fetch full hierarchy tree
$hierarchyTree = getHierarchyFullTree(false);

// Calculate overall statistics
$totalColleges = count($hierarchyTree);
$totalDepartments = 0;
$totalSpecializations = 0;

foreach ($hierarchyTree as $c) {
    $totalDepartments += count($c['departments'] ?? []);
    foreach ($c['departments'] ?? [] as $d) {
        $totalSpecializations += count($d['specializations'] ?? []);
    }
}

$academicsCount = (int) $db->query("SELECT COUNT(*) FROM academics")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" />
  <title>إدارة الكليات والأقسام والتخصصات - Eduroad Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <style>
    * { font-family: 'Tajawal', sans-serif; }
    body { background-color: #f8fafc; color: #0f172a; }
    .main-content { padding-top: 75px !important; }

    .page-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      padding: 24px 28px;
      border-radius: 20px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      margin-bottom: 24px;
    }

    /* Stats Grid */
    .stat-card {
      background: #ffffff;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      padding: 18px 20px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.02);
      transition: all 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
    .stat-icon {
      width: 48px; height: 48px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 24px; flex-shrink: 0;
    }

    /* Breadcrumbs / Active Step Bar */
    .cascade-breadcrumb {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 12px 20px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      font-size: 14px;
      font-weight: 600;
    }
    .cascade-breadcrumb .step-item {
      display: flex; align-items: center; gap: 6px; color: #64748b;
    }
    .cascade-breadcrumb .step-item.active {
      color: #4f46e5; font-weight: 800;
    }
    .cascade-breadcrumb .sep { color: #cbd5e1; margin: 0 4px; }

    /* Hierarchy 3-Columns Grid */
    .hierarchy-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 20px;
      align-items: start;
    }
    @media (max-width: 1024px) {
      .hierarchy-grid { grid-template-columns: 1fr; }
    }

    .hierarchy-panel {
      background: #ffffff;
      border-radius: 18px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 16px rgba(0,0,0,0.02);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-height: 580px;
    }

    .panel-header {
      padding: 16px 20px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      background: #fafafa;
    }
    .panel-title {
      font-size: 16px;
      font-weight: 800;
      color: #0f172a;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .panel-badge {
      font-size: 12px;
      padding: 2px 8px;
      border-radius: 20px;
      background: #eef2ff;
      color: #4f46e5;
      font-weight: 700;
    }

    .panel-search {
      padding: 12px 16px;
      border-bottom: 1px solid #f1f5f9;
      background: #ffffff;
    }
    .panel-search input {
      width: 100%;
      padding: 8px 14px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      font-size: 13px;
      outline: none;
      transition: all 0.2s;
    }
    .panel-search input:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
    }

    .panel-body {
      padding: 14px;
      overflow-y: auto;
      max-height: 520px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    /* Hierarchy Items */
    .h-item {
      padding: 12px 14px;
      border-radius: 12px;
      border: 1.5px solid #e2e8f0;
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      cursor: pointer;
      transition: all 0.2s ease;
      user-select: none;
    }
    .h-item:hover {
      border-color: #c7d2fe;
      background: #f8fafc;
      transform: translateX(-2px);
    }
    .h-item.active {
      border-color: #6366f1;
      background: #eef2ff;
      box-shadow: 0 4px 14px rgba(99,102,241,0.12);
    }
    .h-item.inactive {
      opacity: 0.6;
      background: #f8fafc;
    }

    .h-item-title {
      font-size: 14px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 3px;
    }
    .h-item.active .h-item-title {
      color: #4338ca;
    }
    .h-item-sub {
      font-size: 12px;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .h-actions {
      display: flex;
      align-items: center;
      gap: 4px;
      flex-shrink: 0;
    }
    .btn-action-icon {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      color: #64748b;
      background: #f1f5f9;
      border: none;
      cursor: pointer;
      transition: all 0.15s;
    }
    .btn-action-icon:hover {
      background: #e2e8f0;
      color: #0f172a;
    }
    .btn-action-icon.btn-del:hover {
      background: #fee2e2;
      color: #dc2626;
    }
    .btn-action-icon.btn-select-arrow {
      color: #6366f1;
      background: #e0e7ff;
      font-size: 14px;
    }

    /* Empty state box */
    .empty-panel-prompt {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 60px 20px;
      color: #94a3b8;
      height: 100%;
    }
    .empty-panel-prompt .icon {
      font-size: 48px;
      margin-bottom: 12px;
      opacity: 0.8;
    }
    .empty-panel-prompt p {
      font-size: 14px;
      line-height: 1.6;
      max-width: 260px;
    }

    /* Modal Overlay */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(15, 23, 42, 0.55);
      z-index: 999; justify-content: center; align-items: center;
      backdrop-filter: blur(4px); padding: 16px;
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
      background: #ffffff;
      border-radius: 20px;
      width: 100%; max-width: 480px;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
      overflow: hidden;
      animation: modalPop 0.25s ease;
    }
    .modal-header {
      padding: 18px 24px;
      border-bottom: 1px solid #f1f5f9;
      display: flex; align-items: center; justify-content: space-between;
      background: #fafafa;
    }
    .modal-body { padding: 24px; }
    .modal-footer {
      padding: 16px 24px;
      border-top: 1px solid #f1f5f9;
      display: flex; justify-content: flex-end; gap: 10px;
      background: #fafafa;
    }

    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; }
    .form-input {
      width: 100%; padding: 10px 14px;
      border: 1.5px solid #cbd5e1; border-radius: 10px;
      font-size: 14px; outline: none; transition: all 0.2s;
    }
    .form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

    /* Toast */
    .toast-container { position: fixed; bottom: 24px; left: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
    .toast { padding: 12px 20px; border-radius: 12px; color: white; font-weight: 600; box-shadow: 0 8px 24px rgba(0,0,0,0.15); animation: slideIn 0.3s ease; }
    .toast.success { background: #10b981; }
    .toast.error { background: #ef4444; }

    @keyframes modalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="admin-layout">
  <?php include '../components/sidebar.php'; ?>
  <div class="main-content" id="mainContent">
    <?php include '../components/navbar.php'; ?>

    <div class="page-content" style="padding: 24px;">

      <!-- Header -->
      <div class="page-header">
        <div class="flex flex-wrap justify-between items-center gap-4">
          <div>
            <div class="breadcrumb text-sm text-gray-500 mb-1">
              <a href="dashboard.php" class="text-indigo-600 font-bold hover:underline">الرئيسية</a> / <span>الكليات والأقسام والتخصصات</span>
            </div>
            <h1 class="text-2xl font-black text-slate-800 flex items-center gap-3">
              <span>🏛️</span> إدارة الكليات والأقسام والتخصصات
            </h1>
            <p class="text-sm text-slate-500 mt-1">
              هيكل تنظيمي متكامل: اختر الكلية ثم استعرض وأدر أقسامها، ثم اختر القسم لإدارة تخصصاته العلمية.
            </p>
          </div>
          <div class="flex items-center gap-2">
            <button onclick="openAddCollegeModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2.5 rounded-xl shadow-md transition">
              <span>➕</span> إضافة كلية جديدة
            </button>
          </div>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat-card">
          <div class="stat-icon" style="background:#eef2ff;color:#4f46e5">🏛️</div>
          <div>
            <div class="text-xs text-slate-500 font-bold">إجمالي الكليات</div>
            <div class="text-2xl font-black text-slate-800" id="statColleges"><?= number_format($totalColleges) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#ecfdf5;color:#059669">📂</div>
          <div>
            <div class="text-xs text-slate-500 font-bold">إجمالي الأقسام</div>
            <div class="text-2xl font-black text-slate-800" id="statDepartments"><?= number_format($totalDepartments) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#fffbeb;color:#d97706">🎯</div>
          <div>
            <div class="text-xs text-slate-500 font-bold">إجمالي التخصصات</div>
            <div class="text-2xl font-black text-slate-800" id="statSpecializations"><?= number_format($totalSpecializations) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#fdf2f8;color:#db2777">🎓</div>
          <div>
            <div class="text-xs text-slate-500 font-bold">الأكاديميون المسجلون</div>
            <div class="text-2xl font-black text-slate-800"><?= number_format($academicsCount) ?></div>
          </div>
        </div>
      </div>

      <!-- Active Path Breadcrumb -->
      <div class="cascade-breadcrumb" id="activePathBar">
        <div class="step-item active">
          <span>🏛️</span>
          <span id="pathCollegeName">يرجى اختيار كلية...</span>
        </div>
        <span class="sep">◀</span>
        <div class="step-item" id="stepDeptWrapper">
          <span>📂</span>
          <span id="pathDeptName">القسم...</span>
        </div>
        <span class="sep">◀</span>
        <div class="step-item" id="stepSpecWrapper">
          <span>🎯</span>
          <span id="pathSpecName">التخصصات</span>
        </div>
      </div>

      <!-- 3-Columns Cascade Hierarchy Grid -->
      <div class="hierarchy-grid">

        <!-- Column 1: Colleges -->
        <div class="hierarchy-panel" id="panelColleges">
          <div class="panel-header">
            <div class="panel-title">
              <span>🏛️</span> الكليات
              <span class="panel-badge" id="badgeCollegesCount"><?= $totalColleges ?></span>
            </div>
            <button onclick="openAddCollegeModal()" class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold px-2.5 py-1.5 rounded-lg transition">
              + إضافة
            </button>
          </div>
          <div class="panel-search">
            <input type="text" id="searchColleges" placeholder="بحث في الكليات..." onkeyup="filterColleges()" />
          </div>
          <div class="panel-body" id="collegesList">
            <!-- Dynamic Colleges Loaded Here -->
          </div>
        </div>

        <!-- Column 2: Departments -->
        <div class="hierarchy-panel" id="panelDepartments">
          <div class="panel-header">
            <div class="panel-title">
              <span>📂</span> الأقسام
              <span class="panel-badge" id="badgeDeptsCount">0</span>
            </div>
            <button id="btnAddDept" onclick="openAddDepartmentModal()" disabled class="text-xs bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold px-2.5 py-1.5 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed">
              + إضافة قسم
            </button>
          </div>
          <div class="panel-search">
            <input type="text" id="searchDepts" placeholder="بحث في الأقسام..." onkeyup="filterDepartments()" />
          </div>
          <div class="panel-body" id="departmentsList">
            <div class="empty-panel-prompt">
              <div class="icon">👈</div>
              <h4 class="font-bold text-slate-700 mb-1">اختر كلية أولاً</h4>
              <p>قم بالنقر على أي كلية في القائمة لعرض وإدارة الأقسام التابعة لها.</p>
            </div>
          </div>
        </div>

        <!-- Column 3: Specializations -->
        <div class="hierarchy-panel" id="panelSpecializations">
          <div class="panel-header">
            <div class="panel-title">
              <span>🎯</span> التخصصات العلمية
              <span class="panel-badge" id="badgeSpecsCount">0</span>
            </div>
            <button id="btnAddSpec" onclick="openAddSpecializationModal()" disabled class="text-xs bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold px-2.5 py-1.5 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed">
              + إضافة تخصص
            </button>
          </div>
          <div class="panel-search">
            <input type="text" id="searchSpecs" placeholder="بحث في التخصصات..." onkeyup="filterSpecializations()" />
          </div>
          <div class="panel-body" id="specializationsList">
            <div class="empty-panel-prompt">
              <div class="icon">👈</div>
              <h4 class="font-bold text-slate-700 mb-1">اختر قسماً أولاً</h4>
              <p>قم باختيار قسم من العمود السابق لإدارة واستعراض التخصصات التابعة له.</p>
            </div>
          </div>
        </div>

      </div><!-- /hierarchy-grid -->

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div><!-- /admin-layout -->

<!-- ============================================================
     MODALS
============================================================ -->

<!-- Modal: College (Add / Edit) -->
<div class="modal-overlay" id="collegeModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="font-black text-slate-800 text-lg" id="collegeModalTitle">إضافة كلية جديدة</h3>
      <button onclick="closeModal('collegeModal')" class="text-slate-400 hover:text-slate-600 font-bold text-xl">✕</button>
    </div>
    <form id="collegeForm" onsubmit="saveCollege(event)">
      <input type="hidden" id="collegeId" value="" />
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">اسم الكلية <span class="text-rose-500">*</span></label>
          <input type="text" id="collegeName" class="form-input" placeholder="مثال: كلية علوم الحاسب والمعلومات" required />
        </div>
        <div class="grid grid-cols-2 gap-3 form-group">
          <div>
            <label class="form-label">رمز الكلية (اختياري)</label>
            <input type="text" id="collegeCode" class="form-input" placeholder="مثال: CSIS" />
          </div>
          <div>
            <label class="form-label">أيقونة الكلية</label>
            <select id="collegeIcon" class="form-input">
              <option value="🏛️">🏛️ كلية عامة</option>
              <option value="💻">💻 حاسب وتقنية</option>
              <option value="⚙️">⚙️ هندسة</option>
              <option value="📈">📈 إدارة وأعمال</option>
              <option value="🩺">🩺 طب وصحة</option>
              <option value="🔬">🔬 علوم طبيعية</option>
              <option value="📚">📚 آداب وإنسانيات</option>
              <option value="⚖️">⚖️ قانون وشريعة</option>
              <option value="🎨">🎨 فنون وتصميم</option>
              <option value="🎓">🎓 دراسات عليا</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">وصف تعريفي (اختياري)</label>
          <textarea id="collegeDesc" class="form-input" rows="2" placeholder="نبذة مختصرة عن الكلية..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('collegeModal')" class="px-4 py-2 rounded-xl border border-slate-300 font-bold text-slate-600 hover:bg-slate-100 transition">إلغاء</button>
        <button type="submit" id="btnSubmitCollege" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition">حفظ الكلية</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Department (Add / Edit) -->
<div class="modal-overlay" id="deptModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="font-black text-slate-800 text-lg" id="deptModalTitle">إضافة قسم جديد</h3>
      <button onclick="closeModal('deptModal')" class="text-slate-400 hover:text-slate-600 font-bold text-xl">✕</button>
    </div>
    <form id="deptForm" onsubmit="saveDepartment(event)">
      <input type="hidden" id="deptId" value="" />
      <input type="hidden" id="deptCollegeId" value="" />
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">الكلية التابع لها</label>
          <div id="deptCollegeLabel" class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl text-indigo-900 font-bold text-sm">--</div>
        </div>
        <div class="form-group">
          <label class="form-label">اسم القسم <span class="text-rose-500">*</span></label>
          <input type="text" id="deptName" class="form-input" placeholder="مثال: قسم علوم الحاسب" required />
        </div>
        <div class="form-group">
          <label class="form-label">رمز القسم (اختياري)</label>
          <input type="text" id="deptCode" class="form-input" placeholder="مثال: CS" />
        </div>
        <div class="form-group">
          <label class="form-label">وصف تعريفي (اختياري)</label>
          <textarea id="deptDesc" class="form-input" rows="2" placeholder="نبذة مختصرة عن القسم..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('deptModal')" class="px-4 py-2 rounded-xl border border-slate-300 font-bold text-slate-600 hover:bg-slate-100 transition">إلغاء</button>
        <button type="submit" id="btnSubmitDept" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition">حفظ القسم</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Specialization (Add / Edit) -->
<div class="modal-overlay" id="specModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="font-black text-slate-800 text-lg" id="specModalTitle">إضافة تخصص جديد</h3>
      <button onclick="closeModal('specModal')" class="text-slate-400 hover:text-slate-600 font-bold text-xl">✕</button>
    </div>
    <form id="specForm" onsubmit="saveSpecialization(event)">
      <input type="hidden" id="specId" value="" />
      <input type="hidden" id="specDeptId" value="" />
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">القسم التابع له</label>
          <div id="specDeptLabel" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 font-bold text-sm">--</div>
        </div>
        <div class="form-group">
          <label class="form-label">اسم التخصص <span class="text-rose-500">*</span></label>
          <input type="text" id="specName" class="form-input" placeholder="مثال: هندسة البرمجيات" required />
        </div>
        <div class="form-group">
          <label class="form-label">رمز التخصص (اختياري)</label>
          <input type="text" id="specCode" class="form-input" placeholder="مثال: SE" />
        </div>
        <div class="form-group">
          <label class="form-label">وصف التخصص (اختياري)</label>
          <textarea id="specDesc" class="form-input" rows="2" placeholder="نبذة عن التخصص..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('specModal')" class="px-4 py-2 rounded-xl border border-slate-300 font-bold text-slate-600 hover:bg-slate-100 transition">إلغاء</button>
        <button type="submit" id="btnSubmitSpec" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold transition">حفظ التخصص</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <h3 class="font-black text-rose-600 text-lg">⚠️ تأكيد الحذف</h3>
      <button onclick="closeModal('deleteModal')" class="text-slate-400 hover:text-slate-600 font-bold text-xl">✕</button>
    </div>
    <div class="modal-body text-center py-6">
      <div class="text-5xl mb-3">🗑️</div>
      <h4 class="font-black text-slate-800 text-lg mb-2" id="deleteTitle">تأكيد الحذف</h4>
      <p class="text-sm text-slate-500 leading-relaxed" id="deleteMessage">هل أنت متأكد من رغبتك في حذف هذا العنصر؟</p>
    </div>
    <div class="modal-footer">
      <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2 rounded-xl border border-slate-300 font-bold text-slate-600 hover:bg-slate-100 transition">إلغاء</button>
      <button type="button" id="btnConfirmDelete" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold transition">نعم، احذف</button>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script src="../assets/js/main.js"></script>
<script>
const csrfTokenJs = document.querySelector('meta[name="csrf-token"]')?.content || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>';

// Initial injected hierarchy state
let HIERARCHY = <?= json_encode($hierarchyTree, JSON_UNESCAPED_UNICODE) ?> || [];

let selectedCollegeId = null;
let selectedDeptId = null;

// Helpers
function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

function openModal(id) {
  document.getElementById(id).classList.add('active');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('active');
}

// -------------------------------------------------------------
// RENDER COLLEGES
// -------------------------------------------------------------
function renderColleges(filterText = '') {
  const list = document.getElementById('collegesList');
  if (!list) return;

  const query = filterText.trim().toLowerCase();
  const filtered = HIERARCHY.filter(c => !query || c.name.toLowerCase().includes(query) || (c.code && c.code.toLowerCase().includes(query)));

  document.getElementById('badgeCollegesCount').textContent = HIERARCHY.length;
  document.getElementById('statColleges').textContent = HIERARCHY.length;

  if (filtered.length === 0) {
    list.innerHTML = `<div class="p-8 text-center text-slate-400 text-sm">لا توجد كليات مطابقة للبحث.</div>`;
    return;
  }

  list.innerHTML = filtered.map(c => {
    const isSelected = (selectedCollegeId === parseInt(c.id));
    const deptsCount = (c.departments || []).length;
    return `
      <div class="h-item ${isSelected ? 'active' : ''} ${c.is_active == 0 ? 'inactive' : ''}" onclick="selectCollege(${c.id})">
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <span style="font-size:22px">${c.icon || '🏛️'}</span>
          <div class="min-w-0 flex-1">
            <div class="h-item-title truncate">${escapeHtml(c.name)}</div>
            <div class="h-item-sub">
              <span>${deptsCount} أقسام</span>
              ${c.code ? `<span class="px-1.5 py-0.5 bg-slate-100 rounded text-[11px] font-mono">${escapeHtml(c.code)}</span>` : ''}
              ${c.is_active == 0 ? '<span class="text-rose-500 font-bold text-[11px]">(معطلة)</span>' : ''}
            </div>
          </div>
        </div>
        <div class="h-actions" onclick="event.stopPropagation()">
          <button class="btn-action-icon" title="تعديل" onclick="openEditCollegeModal(${c.id})">✏️</button>
          <button class="btn-action-icon btn-del" title="حذف" onclick="confirmDeleteCollege(${c.id}, '${escapeHtml(c.name)}')">🗑️</button>
          <button class="btn-action-icon btn-select-arrow" title="عرض الأقسام" onclick="selectCollege(${c.id})">◀</button>
        </div>
      </div>
    `;
  }).join('');
}

function filterColleges() {
  const val = document.getElementById('searchColleges').value;
  renderColleges(val);
}

// -------------------------------------------------------------
// SELECT COLLEGE
// -------------------------------------------------------------
function selectCollege(collegeId) {
  selectedCollegeId = parseInt(collegeId);
  selectedDeptId = null; // Reset child department

  const college = HIERARCHY.find(c => parseInt(c.id) === selectedCollegeId);
  if (!college) return;

  // Update Breadcrumbs
  document.getElementById('pathCollegeName').textContent = college.name;
  document.getElementById('pathCollegeName').parentElement.classList.add('active');
  document.getElementById('pathDeptName').textContent = 'اختر قسماً...';
  document.getElementById('pathDeptName').parentElement.classList.remove('active');
  document.getElementById('pathSpecName').textContent = 'التخصصات';
  document.getElementById('pathSpecName').parentElement.classList.remove('active');

  // Enable Add Dept button
  const btnAddDept = document.getElementById('btnAddDept');
  btnAddDept.disabled = false;

  // Disable Add Spec button until a dept is selected
  document.getElementById('btnAddSpec').disabled = true;

  // Render Highlights
  renderColleges(document.getElementById('searchColleges').value);
  renderDepartments();
  renderSpecializations(); // Reset specs
}

// -------------------------------------------------------------
// RENDER DEPARTMENTS
// -------------------------------------------------------------
function renderDepartments(filterText = '') {
  const list = document.getElementById('departmentsList');
  const badge = document.getElementById('badgeDeptsCount');

  if (!selectedCollegeId) {
    badge.textContent = '0';
    list.innerHTML = `
      <div class="empty-panel-prompt">
        <div class="icon">👈</div>
        <h4 class="font-bold text-slate-700 mb-1">اختر كلية أولاً</h4>
        <p>قم بالنقر على أي كلية في القائمة لعرض وإدارة الأقسام التابعة لها.</p>
      </div>
    `;
    return;
  }

  const college = HIERARCHY.find(c => parseInt(c.id) === selectedCollegeId);
  const depts = (college && college.departments) ? college.departments : [];

  const query = filterText.trim().toLowerCase();
  const filtered = depts.filter(d => !query || d.name.toLowerCase().includes(query) || (d.code && d.code.toLowerCase().includes(query)));

  badge.textContent = depts.length;

  if (filtered.length === 0) {
    list.innerHTML = `
      <div class="p-8 text-center text-slate-400 text-sm">
        ${depts.length === 0 ? 'لا توجد أقسام مسجلة في هذه الكلية حتى الآن. يمكنك إضافة قسم جديد بالنقر على زر "+ إضافة قسم".' : 'لا توجد نتائج مطابقة للبحث.'}
      </div>
    `;
    return;
  }

  list.innerHTML = filtered.map(d => {
    const isSelected = (selectedDeptId === parseInt(d.id));
    const specsCount = (d.specializations || []).length;
    return `
      <div class="h-item ${isSelected ? 'active' : ''} ${d.is_active == 0 ? 'inactive' : ''}" onclick="selectDepartment(${d.id})">
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <span style="font-size:20px">📂</span>
          <div class="min-w-0 flex-1">
            <div class="h-item-title truncate">${escapeHtml(d.name)}</div>
            <div class="h-item-sub">
              <span>${specsCount} تخصصات</span>
              ${d.code ? `<span class="px-1.5 py-0.5 bg-slate-100 rounded text-[11px] font-mono">${escapeHtml(d.code)}</span>` : ''}
              ${d.is_active == 0 ? '<span class="text-rose-500 font-bold text-[11px]">(معطل)</span>' : ''}
            </div>
          </div>
        </div>
        <div class="h-actions" onclick="event.stopPropagation()">
          <button class="btn-action-icon" title="تعديل" onclick="openEditDepartmentModal(${d.id})">✏️</button>
          <button class="btn-action-icon btn-del" title="حذف" onclick="confirmDeleteDepartment(${d.id}, '${escapeHtml(d.name)}')">🗑️</button>
          <button class="btn-action-icon btn-select-arrow" title="عرض التخصصات" onclick="selectDepartment(${d.id})">◀</button>
        </div>
      </div>
    `;
  }).join('');
}

function filterDepartments() {
  const val = document.getElementById('searchDepts').value;
  renderDepartments(val);
}

// -------------------------------------------------------------
// SELECT DEPARTMENT
// -------------------------------------------------------------
function selectDepartment(deptId) {
  selectedDeptId = parseInt(deptId);

  const college = HIERARCHY.find(c => parseInt(c.id) === selectedCollegeId);
  const dept = (college && college.departments) ? college.departments.find(d => parseInt(d.id) === selectedDeptId) : null;
  if (!dept) return;

  // Update Breadcrumbs
  document.getElementById('pathDeptName').textContent = dept.name;
  document.getElementById('pathDeptName').parentElement.classList.add('active');
  document.getElementById('pathSpecName').textContent = `تخصصات (${dept.name})`;
  document.getElementById('pathSpecName').parentElement.classList.add('active');

  // Enable Add Spec button
  document.getElementById('btnAddSpec').disabled = false;

  renderDepartments(document.getElementById('searchDepts').value);
  renderSpecializations();
}

// -------------------------------------------------------------
// RENDER SPECIALIZATIONS
// -------------------------------------------------------------
function renderSpecializations(filterText = '') {
  const list = document.getElementById('specializationsList');
  const badge = document.getElementById('badgeSpecsCount');

  if (!selectedDeptId) {
    badge.textContent = '0';
    list.innerHTML = `
      <div class="empty-panel-prompt">
        <div class="icon">👈</div>
        <h4 class="font-bold text-slate-700 mb-1">اختر قسماً أولاً</h4>
        <p>قم باختيار قسم من العمود السابق لإدارة واستعراض التخصصات التابعة له.</p>
      </div>
    `;
    return;
  }

  const college = HIERARCHY.find(c => parseInt(c.id) === selectedCollegeId);
  const dept = (college && college.departments) ? college.departments.find(d => parseInt(d.id) === selectedDeptId) : null;
  const specs = (dept && dept.specializations) ? dept.specializations : [];

  const query = filterText.trim().toLowerCase();
  const filtered = specs.filter(s => !query || s.name.toLowerCase().includes(query) || (s.code && s.code.toLowerCase().includes(query)));

  badge.textContent = specs.length;

  if (filtered.length === 0) {
    list.innerHTML = `
      <div class="p-8 text-center text-slate-400 text-sm">
        ${specs.length === 0 ? 'لا توجد تخصصات مسجلة في هذا القسم بعد. يمكنك إضافة تخصص جديد بالنقر على زر "+ إضافة تخصص".' : 'لا توجد نتائج مطابقة للبحث.'}
      </div>
    `;
    return;
  }

  list.innerHTML = filtered.map(s => `
    <div class="h-item ${s.is_active == 0 ? 'inactive' : ''}" style="cursor:default">
      <div class="flex items-center gap-3 min-w-0 flex-1">
        <span style="font-size:20px">🎯</span>
        <div class="min-w-0 flex-1">
          <div class="h-item-title truncate">${escapeHtml(s.name)}</div>
          <div class="h-item-sub">
            ${s.code ? `<span class="px-1.5 py-0.5 bg-slate-100 rounded text-[11px] font-mono">${escapeHtml(s.code)}</span>` : '<span class="text-slate-400">تخصص معتمد</span>'}
            ${s.is_active == 0 ? '<span class="text-rose-500 font-bold text-[11px]">(معطل)</span>' : ''}
          </div>
        </div>
      </div>
      <div class="h-actions">
        <button class="btn-action-icon" title="تعديل" onclick="openEditSpecializationModal(${s.id})">✏️</button>
        <button class="btn-action-icon btn-del" title="حذف" onclick="confirmDeleteSpecialization(${s.id}, '${escapeHtml(s.name)}')">🗑️</button>
      </div>
    </div>
  `).join('');
}

function filterSpecializations() {
  const val = document.getElementById('searchSpecs').value;
  renderSpecializations(val);
}

// -------------------------------------------------------------
// COLLEGE CRUD MODALS & ACTIONS
// -------------------------------------------------------------
function openAddCollegeModal() {
  document.getElementById('collegeForm').reset();
  document.getElementById('collegeId').value = '';
  document.getElementById('collegeModalTitle').textContent = 'إضافة كلية جديدة';
  document.getElementById('btnSubmitCollege').textContent = 'إضافة الكلية';
  openModal('collegeModal');
}

function openEditCollegeModal(id) {
  const college = HIERARCHY.find(c => parseInt(c.id) === parseInt(id));
  if (!college) return;

  document.getElementById('collegeId').value = college.id;
  document.getElementById('collegeName').value = college.name;
  document.getElementById('collegeCode').value = college.code || '';
  document.getElementById('collegeIcon').value = college.icon || '🏛️';
  document.getElementById('collegeDesc').value = college.description || '';
  document.getElementById('collegeModalTitle').textContent = 'تعديل بيانات الكلية';
  document.getElementById('btnSubmitCollege').textContent = 'حفظ التعديلات';
  openModal('collegeModal');
}

async function saveCollege(e) {
  e.preventDefault();
  const id = document.getElementById('collegeId').value;
  const action = id ? 'edit_college' : 'add_college';

  const formData = new FormData();
  formData.append('csrf_token', csrfTokenJs);
  formData.append('action', action);
  if (id) formData.append('id', id);
  formData.append('name', document.getElementById('collegeName').value.trim());
  formData.append('code', document.getElementById('collegeCode').value.trim());
  formData.append('icon', document.getElementById('collegeIcon').value);
  formData.append('description', document.getElementById('collegeDesc').value.trim());

  const btn = document.getElementById('btnSubmitCollege');
  btn.disabled = true;

  try {
    const res = await fetch('../ajax/manage_hierarchy.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfTokenJs
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      closeModal('collegeModal');
      await reloadHierarchyTree(id ? selectedCollegeId : data.id);
    } else {
      showToast(data.message || 'حدث خطأ في حفظ الكلية', 'error');
    }
  } catch (err) {
    console.error('saveCollege error:', err);
    showToast('حدث خطأ في الاتصال بالخادم: ' + (err.message || ''), 'error');
  } finally {
    btn.disabled = false;
  }
}

function confirmDeleteCollege(id, name) {
  document.getElementById('deleteTitle').textContent = 'حذف الكلية';
  document.getElementById('deleteMessage').textContent = `هل أنت متأكد من حذف كلية "${name}"؟ سيتم حذف جميع الأقسام والتخصصات التابعة لها بشكل نهائي.`;
  document.getElementById('btnConfirmDelete').onclick = async () => {
    const fd = new FormData();
    fd.append('csrf_token', csrfTokenJs);
    fd.append('action', 'delete_college');
    fd.append('id', id);
    try {
      const res = await fetch('../ajax/manage_hierarchy.php', {
        method: 'POST',
        body: fd,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrfTokenJs
        }
      });
      const data = await res.json();
      if (data.success) {
        showToast(data.message, 'success');
        closeModal('deleteModal');
        if (selectedCollegeId === parseInt(id)) {
          selectedCollegeId = null;
          selectedDeptId = null;
        }
        await reloadHierarchyTree();
      } else {
        showToast(data.message || 'تعذر الحذف', 'error');
      }
    } catch (err) {
      console.error('deleteCollege error:', err);
      showToast('تعذر الحذف: ' + (err.message || ''), 'error');
    }
  };
  openModal('deleteModal');
}

// -------------------------------------------------------------
// DEPARTMENT CRUD MODALS & ACTIONS
// -------------------------------------------------------------
function openAddDepartmentModal() {
  if (!selectedCollegeId) {
    showToast('يرجى اختيار كلية أولاً.', 'error');
    return;
  }
  const college = HIERARCHY.find(c => parseInt(c.id) === selectedCollegeId);
  document.getElementById('deptForm').reset();
  document.getElementById('deptId').value = '';
  document.getElementById('deptCollegeId').value = selectedCollegeId;
  document.getElementById('deptCollegeLabel').textContent = `${college.icon || '🏛️'} ${college.name}`;
  document.getElementById('deptModalTitle').textContent = 'إضافة قسم جديد';
  document.getElementById('btnSubmitDept').textContent = 'إضافة القسم';
  openModal('deptModal');
}

function openEditDepartmentModal(id) {
  const college = HIERARCHY.find(c => parseInt(c.id) === selectedCollegeId);
  const dept = (college && college.departments) ? college.departments.find(d => parseInt(d.id) === parseInt(id)) : null;
  if (!dept) return;

  document.getElementById('deptId').value = dept.id;
  document.getElementById('deptCollegeId').value = selectedCollegeId;
  document.getElementById('deptCollegeLabel').textContent = `${college.icon || '🏛️'} ${college.name}`;
  document.getElementById('deptName').value = dept.name;
  document.getElementById('deptCode').value = dept.code || '';
  document.getElementById('deptDesc').value = dept.description || '';
  document.getElementById('deptModalTitle').textContent = 'تعديل بيانات القسم';
  document.getElementById('btnSubmitDept').textContent = 'حفظ التعديلات';
  openModal('deptModal');
}

async function saveDepartment(e) {
  e.preventDefault();
  const id = document.getElementById('deptId').value;
  const action = id ? 'edit_department' : 'add_department';

  const formData = new FormData();
  formData.append('csrf_token', csrfTokenJs);
  formData.append('action', action);
  if (id) formData.append('id', id);
  formData.append('college_id', document.getElementById('deptCollegeId').value);
  formData.append('name', document.getElementById('deptName').value.trim());
  formData.append('code', document.getElementById('deptCode').value.trim());
  formData.append('description', document.getElementById('deptDesc').value.trim());

  const btn = document.getElementById('btnSubmitDept');
  btn.disabled = true;

  try {
    const res = await fetch('../ajax/manage_hierarchy.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfTokenJs
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      closeModal('deptModal');
      await reloadHierarchyTree(selectedCollegeId, id ? selectedDeptId : data.id);
    } else {
      showToast(data.message || 'حدث خطأ في حفظ القسم', 'error');
    }
  } catch (err) {
    console.error('saveDepartment error:', err);
    showToast('حدث خطأ في الاتصال بالخادم: ' + (err.message || ''), 'error');
  } finally {
    btn.disabled = false;
  }
}

function confirmDeleteDepartment(id, name) {
  document.getElementById('deleteTitle').textContent = 'حذف القسم';
  document.getElementById('deleteMessage').textContent = `هل أنت متأكد من حذف قسم "${name}"؟ سيتم حذف جميع التخصصات التابعة له بشكل نهائي.`;
  document.getElementById('btnConfirmDelete').onclick = async () => {
    const fd = new FormData();
    fd.append('csrf_token', csrfTokenJs);
    fd.append('action', 'delete_department');
    fd.append('id', id);
    try {
      const res = await fetch('../ajax/manage_hierarchy.php', {
        method: 'POST',
        body: fd,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrfTokenJs
        }
      });
      const data = await res.json();
      if (data.success) {
        showToast(data.message, 'success');
        closeModal('deleteModal');
        if (selectedDeptId === parseInt(id)) {
          selectedDeptId = null;
        }
        await reloadHierarchyTree(selectedCollegeId);
      } else {
        showToast(data.message || 'تعذر الحذف', 'error');
      }
    } catch (err) {
      console.error('deleteDepartment error:', err);
      showToast('تعذر الحذف: ' + (err.message || ''), 'error');
    }
  };
  openModal('deleteModal');
}

// -------------------------------------------------------------
// SPECIALIZATION CRUD MODALS & ACTIONS
// -------------------------------------------------------------
function openAddSpecializationModal() {
  if (!selectedDeptId) {
    showToast('يرجى اختيار قسم أولاً.', 'error');
    return;
  }
  const college = HIERARCHY.find(c => parseInt(c.id) === selectedCollegeId);
  const dept = (college && college.departments) ? college.departments.find(d => parseInt(d.id) === selectedDeptId) : null;

  document.getElementById('specForm').reset();
  document.getElementById('specId').value = '';
  document.getElementById('specDeptId').value = selectedDeptId;
  document.getElementById('specDeptLabel').textContent = `📂 ${dept.name} (${college.name})`;
  document.getElementById('specModalTitle').textContent = 'إضافة تخصص جديد';
  document.getElementById('btnSubmitSpec').textContent = 'إضافة التخصص';
  openModal('specModal');
}

function openEditSpecializationModal(id) {
  const college = HIERARCHY.find(c => parseInt(c.id) === selectedCollegeId);
  const dept = (college && college.departments) ? college.departments.find(d => parseInt(d.id) === selectedDeptId) : null;
  const spec = (dept && dept.specializations) ? dept.specializations.find(s => parseInt(s.id) === parseInt(id)) : null;
  if (!spec) return;

  document.getElementById('specId').value = spec.id;
  document.getElementById('specDeptId').value = selectedDeptId;
  document.getElementById('specDeptLabel').textContent = `📂 ${dept.name} (${college.name})`;
  document.getElementById('specName').value = spec.name;
  document.getElementById('specCode').value = spec.code || '';
  document.getElementById('specDesc').value = spec.description || '';
  document.getElementById('specModalTitle').textContent = 'تعديل بيانات التخصص';
  document.getElementById('btnSubmitSpec').textContent = 'حفظ التعديلات';
  openModal('specModal');
}

async function saveSpecialization(e) {
  e.preventDefault();
  const id = document.getElementById('specId').value;
  const action = id ? 'edit_specialization' : 'add_specialization';

  const formData = new FormData();
  formData.append('csrf_token', csrfTokenJs);
  formData.append('action', action);
  if (id) formData.append('id', id);
  formData.append('department_id', document.getElementById('specDeptId').value);
  formData.append('name', document.getElementById('specName').value.trim());
  formData.append('code', document.getElementById('specCode').value.trim());
  formData.append('description', document.getElementById('specDesc').value.trim());

  const btn = document.getElementById('btnSubmitSpec');
  btn.disabled = true;

  try {
    const res = await fetch('../ajax/manage_hierarchy.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfTokenJs
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      closeModal('specModal');
      await reloadHierarchyTree(selectedCollegeId, selectedDeptId);
    } else {
      showToast(data.message || 'حدث خطأ في حفظ التخصص', 'error');
    }
  } catch (err) {
    console.error('saveSpecialization error:', err);
    showToast('حدث خطأ في الاتصال بالخادم: ' + (err.message || ''), 'error');
  } finally {
    btn.disabled = false;
  }
}

function confirmDeleteSpecialization(id, name) {
  document.getElementById('deleteTitle').textContent = 'حذف التخصص';
  document.getElementById('deleteMessage').textContent = `هل أنت متأكد من حذف تخصص "${name}"؟`;
  document.getElementById('btnConfirmDelete').onclick = async () => {
    const fd = new FormData();
    fd.append('csrf_token', csrfTokenJs);
    fd.append('action', 'delete_specialization');
    fd.append('id', id);
    try {
      const res = await fetch('../ajax/manage_hierarchy.php', {
        method: 'POST',
        body: fd,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrfTokenJs
        }
      });
      const data = await res.json();
      if (data.success) {
        showToast(data.message, 'success');
        closeModal('deleteModal');
        await reloadHierarchyTree(selectedCollegeId, selectedDeptId);
      } else {
        showToast(data.message || 'تعذر الحذف', 'error');
      }
    } catch (err) {
      console.error('deleteSpecialization error:', err);
      showToast('تعذر الحذف: ' + (err.message || ''), 'error');
    }
  };
  openModal('deleteModal');
}

// -------------------------------------------------------------
// RELOAD FULL DATA FROM SERVER
// -------------------------------------------------------------
async function reloadHierarchyTree(maintainCollegeId = null, maintainDeptId = null) {
  try {
    const res = await fetch('../ajax/manage_hierarchy.php?action=get_tree', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (data.success && data.data) {
      HIERARCHY = data.data;

      // Update counters
      let totalDepts = 0;
      let totalSpecs = 0;
      HIERARCHY.forEach(c => {
        totalDepts += (c.departments || []).length;
        (c.departments || []).forEach(d => {
          totalSpecs += (d.specializations || []).length;
        });
      });
      const statDeptsEl = document.getElementById('statDepartments');
      const statSpecsEl = document.getElementById('statSpecializations');
      if (statDeptsEl) statDeptsEl.textContent = totalDepts;
      if (statSpecsEl) statSpecsEl.textContent = totalSpecs;

      renderColleges(document.getElementById('searchColleges')?.value || '');

      if (maintainCollegeId) {
        selectCollege(maintainCollegeId);
        if (maintainDeptId) {
          selectDepartment(maintainDeptId);
        }
      } else if (HIERARCHY.length > 0 && !selectedCollegeId) {
        selectCollege(HIERARCHY[0].id);
      } else {
        renderDepartments();
        renderSpecializations();
      }
    }
  } catch (err) {
    console.error('Error reloading hierarchy:', err);
  }
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// On Page Load
document.addEventListener('DOMContentLoaded', () => {
  renderColleges();
  if (HIERARCHY.length > 0) {
    selectCollege(HIERARCHY[0].id);
  }
});
</script>
</body>
</html>
