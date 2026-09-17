<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$services = getAllServices();
$db = db();

// الخدمة المحددة مسبقاً عبر رابط (services.php / service-details.php)
$prefillServiceId = (int) ($_GET['service_id'] ?? ($_GET['sid'] ?? 0));

/* ─────────────────────────────────────────────
   الاشتراكات النشطة بالباقات + الخدمات المشمولة
───────────────────────────────────────────── */
// مزايا الباقات لا تُفعّل إلا بعد اعتماد إشعار الدفع من الإدارة
$activeSubs = getApprovedActivePackageSubscriptions((int) $user['id']); // كل الباقات النشطة المعتمدة
$hasSub = !empty($activeSubs);
$includedIds = studentIncludedServiceIds((int) $user['id']); // اتحاد خدمات كل الباقات
// المشمولات المستهلكة سابقاً: يمكن طلبها مجدداً لكن بسعرها المحدد (كأنها خارج الباقة)
$usedIncludedIds = $hasSub ? usedPackageServiceIds((int) $user['id']) : [];
$remainingIncludedIds = array_values(array_diff($includedIds, $usedIncludedIds));
$includedIdsJs = $remainingIncludedIds;      // غير المستهلكة → مجانية لمرة واحدة
$usedIncludedIdsJs = $usedIncludedIds;       // المستهلكة → طلب مجدد بسعر الخدمة

// الخدمة المختارة مسبقاً (لعرض تفاصيلها داخل الصفحة)
$servicesById = [];
foreach ($services as $s) {
  $servicesById[(int) $s['id']] = $s;
}

$selectedService = null;
if ($prefillServiceId && isset($servicesById[$prefillServiceId])) {
  $selectedService = $servicesById[$prefillServiceId];
}

// السعر المبدئي المعروض لملخص الطلب قبل تنفيذ JS
$initialPrice = 150.0;
$initialIncluded = false;
if ($prefillServiceId !== 0 && $hasSub && in_array($prefillServiceId, $remainingIncludedIds, true)) {
  $initialIncluded = true;
  $initialPrice = 0.0;
} elseif ($selectedService) {
  $svPrice = (float) ($selectedService['price'] ?? 0);
  $initialPrice = $svPrice > 0 ? $svPrice : 150.0;
}

// بيانات الخدمات للعرض الديناميكي عبر JS (تفاصيل الخدمة المختارة)
$servicesDataMap = [];
foreach ($services as $s) {
  $servicesDataMap[(int) $s['id']] = [
    'id' => (int) $s['id'],
    'icon' => $s['icon'] ?? '',
    'name' => $s['name'],
    'price' => (float) ($s['price'] ?? 0),
    'description' => $s['description'] ?? '',
    'included' => in_array((int) $s['id'], $remainingIncludedIds, true),
    'used'     => in_array((int) $s['id'], $usedIncludedIds, true),
  ];
}
$servicesJson = json_encode($servicesDataMap, JSON_UNESCAPED_UNICODE);

/* ─────────────────────────────────────────────
   الكليات (التخصص الدقيق) — قائمة منسدلة للاختيار
───────────────────────────────────────────── */
$colleges = [
    'العلوم الطبية والصحية',
    'العلوم الإدارية والمالية والاقتصاد',
    'الهندسة والتقنية',
    'العلوم الإنسانية والاجتماعية',
    'العلوم التربوية والنفسية',
    'القانون والعلوم السياسية',
    'اللغات والترجمة والدراسات اللغوية',
    'العلوم الأساسية والتطبيقية',
    'الإعلام وفنون الاتصال',
    'الدراسات الإسلامية والشرعية',
];

$specialtyValue = trim($_POST['specialty'] ?? '');
if ($specialtyValue === 'أخرى') {
  $specialtyValue = trim($_POST['specialty_other'] ?? '');
}

// حسابات المنصة (بنوك/محافظ) لخطوة الدفع + جذر المشروع لبناء روابط الشعارات
$paymentMethods = getActivePaymentMethods();
$appBase = rtrim(preg_replace('#/student/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $service_id = (int) ($_POST['service_id'] ?? 0);
  $specialty = $specialtyValue; // الكلية المختارة من القائمة المنسدلة (أو النص عند اختيار "أخرى")
  $academic_level = $_POST['academic_level'] ?? 'بكالوريوس';
  $language = $_POST['language'] ?? 'العربية';
  $description = trim($_POST['description'] ?? '');

  // حساب السعر: المشمولة وغير المستهلكة → مجانية لمرة واحدة.
  // المشمولة لكن المستهلكة سابقاً (أو خارج الباقة) → بسعر الخدمة المحدد.
  $usedIncludedIds = $hasSub ? usedPackageServiceIds((int) $user['id']) : [];
  $amount = 150.0;
  $package_id = null;
  $isIncludedSvc = false;
  if ($service_id) {
    // تحديد الباقة النشطة المعتمدة التي تشمل هذه الخدمة (إن وُجدت)
    $coveringSub = null;
    if ($hasSub) {
      foreach ($activeSubs as $s) {
        if (in_array($service_id, activeSubscriptionServiceIds($s), true)) { $coveringSub = $s; break; }
      }
    }
    if ($coveringSub && !in_array($service_id, $usedIncludedIds, true)) {
      $isIncludedSvc = true;
      $amount = 0.0;
      $package_id = (int) $coveringSub['package_id'];
    } else {
      $stmt = $db->prepare('SELECT price FROM services WHERE id = ? LIMIT 1');
      $stmt->execute([$service_id]);
      $srvPrice = $stmt->fetchColumn();
      if ($srvPrice && $srvPrice > 0) {
        $amount = (float) $srvPrice;
      }
    }
  }

  if (!$service_id || !$specialty || !$description) {
    $error = 'يرجى ملء كافة الحقول المطلوبة بنجاح.';
  } elseif (!in_array($specialty, $colleges, true)) {
    $error = 'يرجى اختيار الكلية من القائمة المنسدلة (أو كتابة اسمها عند اختيار "أخرى").';
  } elseif ($amount > 0 && empty($_FILES['payment_receipt']['name'])) {
    $error = 'إرفاق إشعار الدفع إلزامي للطلبات المدفوعة — لن يُقبل الطلب بدونه.';
  } elseif ($amount > 0 && !empty($_FILES['payment_receipt']['name']) && ($_FILES['payment_receipt']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $error = 'فشل رفع إشعار الدفع — تأكد أن الملف صورة أو PDF بحجم أقل من 10MB ثم أعد المحاولة.';
  } else {
    $order_id = createOrder([
      'student_id' => $user['id'],
      'service_id' => $service_id,
      'package_id' => $package_id,
      'specialty' => $specialty,
      'academic_level' => $academic_level,
      'language' => $language,
      'description' => $description,
      'amount' => $amount,
    ]);

    // تسجيل استهلاك الخدمة من الباقة (مرة واحدة فقط)
    if ($order_id && $isIncludedSvc) {
      consumePackageService((int)$user['id'], $service_id);
    }

    // حفظ مرفقات الطلب المرفوعة من الطالب
    if ($order_id) {
      saveOrderAttachmentUploads($_FILES['order_attachments'] ?? null, (int) $order_id);
    }

    if ($order_id) {
      // إشعار نجاح الطلب
      createNotification(
        $user['id'],
        'student',
        'تم إنشاء الطلب بنجاح 📋',
        'لقد سجلنا طلبك بنجاح وجاري تعيين أكاديمي مناسب للعمل عليه.',
        '📋',
        'student/orders.php'
      );

      // سجل دفع (يُتخطَّى للخدمات المشمولة في الباقة لأنها مجانية)
      if ($amount > 0) {
        createPayment([
          'order_id' => $order_id,
          'student_id' => $user['id'],
          'amount' => $amount,
          'method' => 'credit_card'
        ]);
      }

      // تسجيل إشعار الدفع المرفق (خطوة الدفع) للطلبات المدفوعة
      if ($amount > 0) {
        $rcptPath = saveReceiptUpload($_FILES['payment_receipt'] ?? null);
        if ($rcptPath) {
          $methodId = (int) ($_POST['payment_method_id'] ?? 0) ?: null;
          savePaymentReceipt([
            'order_id'     => $order_id,
            'student_id'   => $user['id'],
            'method_id'    => $methodId,
            'method_label' => getPaymentMethodLabel($methodId),
            'amount'       => $amount,
            'receipt_path' => $rcptPath,
          ]);
          createNotification(
            1,
            'admin',
            'إشعار دفع جديد 🧾',
            'أرفق الطالب ' . $user['name'] . ' إشعار دفع مع الطلب ' . generateOrderNumber() . '.',
            '🧾',
            'admin/pages/payments.php'
          );
        }
      }
    }

    if ($order_id) {
      header('Location: orders.php?success=1');
      exit;
    } else {
      $error = 'حدث خطأ في النظام أثناء إنشاء الطلب. حاول مجدداً.';
    }
  }
}
?>
<?php
$extraCss = [
  '<style>
    /* Multi-step progress */
    .steps-container {
      display: flex;
      justify-content: space-between;
      position: relative;
      margin-bottom: 32px;
      padding: 0 20px;
    }
    .steps-container::before {
      content: "";
      position: absolute;
      top: 24px; left: 0; right: 0;
      height: 3px;
      background: var(--border-color);
      z-index: 0;
    }
    .step-indicator {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      z-index: 1;
      flex: 1;
    }
    .step-indicator .circle {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: var(--bg-card);
      border: 3px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 16px;
      color: var(--text-secondary);
      transition: all 0.3s;
    }
    .step-indicator.active .circle {
      border-color: var(--primary);
      background: var(--primary);
      color: white;
      box-shadow: 0 0 0 4px var(--primary-light);
    }
    .step-indicator.completed .circle {
      border-color: var(--primary);
      background: var(--primary);
      color: white;
    }
    .step-label {
      font-size: 13px;
      font-weight: 700;
      color: var(--text-secondary);
    }
    .step-indicator.active .step-label {
      color: var(--primary);
    }
    .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
  </style>',
];
$pageTitle = 'طلب خدمة جديدة';
$activePage = 'services';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>

<!-- Page Content -->
<div class="content-wrap" style="max-width:1000px;margin:0 auto">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:32px">
    <div>
      <h1 class="h1" style="margin-bottom:8px">إنشاء طلب خدمة</h1>
      <p class="text-body">حدد الخدمة المطلوبة وقم بتزويدنا بالتفاصيل لنقوم بالباقي.</p>
    </div>
    <a href="services.php" class="btn btn-outline">إلغاء والعودة</a>
  </div>

  <?php if ($error): ?>
    <div class="alert-error">⚠️ <?= e($error) ?></div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:24px">

    <!-- Form Section -->
    <div class="card" style="padding:32px">

      <div class="steps-container">
        <div class="step-indicator active">
          <div class="circle">1</div>
          <div class="step-label">نوع الخدمة</div>
        </div>
        <div class="step-indicator">
          <div class="circle">2</div>
          <div class="step-label">التفاصيل الأساسية</div>
        </div>
        <div class="step-indicator">
          <div class="circle">3</div>
          <div class="step-label">الملفات والشروحات</div>
        </div>
        <div class="step-indicator">
          <div class="circle">4</div>
          <div class="step-label">الدفع</div>
        </div>
      </div>

      <form method="POST" action="create-order.php" id="multiOrderForm" enctype="multipart/form-data">

        <!-- رسائل التحقق من إكمال الحقول الإلزامية في الخطوة الحالية -->
        <div id="stepErrorBox" class="alert-error" style="display:none"></div>

        <!-- STEP 1 -->
        <div class="form-step" id="step1">
          <h3 class="h3" style="margin-bottom:24px">اختيار الخدمة والتخصص</h3>

          <!-- القوائم المنسدلة المتتالية: قسم رئيسي ← قسم فرعي ← خدمة -->
          <?php if ($selectedService): ?>
            <input type="hidden" name="service_id" id="serviceIdInput" value="<?= (int) $selectedService['id'] ?>">
          <?php endif; ?>

          <!-- الخدمة المختارة — عرض ثابت (بدون أي قوائم) -->
          <?php if ($selectedService): ?>
            <div class="form-group" id="fixedServiceBox"
              style="border:2px solid var(--primary);border-radius:var(--radius-md);padding:18px;background:var(--primary-light)">
              <div style="display:flex;align-items:flex-start;gap:12px">
                <div style="font-size:34px;flex-shrink:0;line-height:1"><?= e($selectedService['icon'] ?: '') ?></div>
                <div style="flex:1;min-width:0">
                  <div style="font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:4px">الخدمة المختارة</div>
                  <div style="font-size:18px;font-weight:800;color:var(--text-primary);margin-bottom:8px" id="serviceDetailsName">
                    <?= e($selectedService['name']) ?>
                  </div>
                  <?php if (!empty($selectedService['description'])): ?>
                    <div style="font-size:13px;color:var(--text-secondary);line-height:1.7;margin-top:8px" id="serviceDetailsDesc">
                      <?= e($selectedService['description']) ?>
                    </div>
                  <?php endif; ?>
                  <div style="font-size:13px;font-weight:800;color:var(--primary);margin-top:10px" id="fixedServicePrice">
                    💵 السعر: <?= $initialIncluded ? 'مشمول بالباقة 🎁' : formatMoney($initialPrice) ?>
                  </div>
                  <div id="serviceIncludedBadge"
                    style="display:<?= $initialIncluded ? 'inline-block' : 'none' ?>;margin-top:10px;background:#dcfce7;color:#166534;font-size:12px;font-weight:800;padding:5px 12px;border-radius:20px">
                    ✅ هذه الخدمة مشمولة في باقتك
                  </div>
                  <div id="serviceUsedBadge"
                    style="display:<?= (!$initialIncluded && in_array((int) $selectedService['id'], $usedIncludedIdsJs, true)) ? 'inline-block' : 'none' ?>;margin-top:10px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:800;padding:5px 12px;border-radius:20px">
                    🔁 استُخدمت من باقتك سابقاً — سيُسعَّر هذا الطلب بسعر الخدمة المحدد
                  </div>
                  <div id="serviceNonIncludedBadge"
                    style="display:<?= !$initialIncluded ? 'inline-block' : 'none' ?>;margin-top:10px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:800;padding:5px 12px;border-radius:20px">
                    هذه الخدمة خارج باقتك وسيتم تسعيرها بشكل منفرد
                  </div>
                </div>
              </div>
              <div style="margin-top:14px;font-size:12px;color:var(--text-secondary);border-top:1px dashed var(--border-color);padding-top:10px">
                لتغيير الخدمة: <a href="services.php" style="color:var(--primary);font-weight:800">اختر خدمة أخرى من صفحة الخدمات</a>
              </div>
            </div>
          <?php else: ?>
            <!-- لم تُحدَّد خدمة (فتح الصفحة مباشرة بدون اختيار) -->
            <div class="form-group"
              style="border:2px dashed var(--border-color);border-radius:var(--radius-md);padding:22px;background:var(--bg-body);text-align:center">
              <div style="font-size:38px;margin-bottom:8px">📦</div>
              <div style="font-weight:800;color:var(--text-primary);margin-bottom:6px">لم يتم اختيار خدمة بعد</div>
              <div style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;line-height:1.7">
                اختر الخدمة المطلوبة من صفحة الخدمات ثم اضغط «اطلب الخدمة الآن» للعودة إلى هنا.
              </div>
              <a href="services.php" class="btn btn-primary" style="padding:10px 22px;border-radius:12px;font-weight:700">
                تصفح الخدمات واختر واحدة 🚀
              </a>
            </div>
          <?php endif; ?>

          <?php if ($hasSub && !empty($activeSubs)): ?>
            <!-- الطالب مشترك في باقة أو أكثر -->
            <div class="form-group"
              style="border:1px dashed var(--primary);border-radius:var(--radius-md);padding:16px;background:var(--primary-light);margin-top:4px">
              <div style="font-weight:800;color:var(--primary);margin-bottom:6px">
                🎁 باقاتك النشطة:
                <?php foreach ($activeSubs as $i => $s): ?>
                  <?= e($s['package_name']) ?><?= $i < count($activeSubs) - 1 ? '، ' : '' ?>
                <?php endforeach; ?>
                — سارية بشكل غير محدد (بدون تاريخ انتهاء)
              </div>
              <div style="font-size:13px;color:var(--text-secondary);line-height:1.7">
                إذا كانت الخدمة المختارة ضمن خدمات إحدى باقاتك فستكون <strong>مشمولة مجاناً</strong> في طلبك.
                إن لم تكن مشمولة، ستُسعَّر بشكل منفرد.
              </div>
            </div>
          <?php else: ?>
            <!-- الطالب غير مشترك → نحيله لصفحة الباقات للاشتراك -->
            <div class="form-group"
              style="border:1px solid var(--border-color);border-radius:var(--radius-md);padding:16px;background:var(--bg-body);margin-top:4px">
              <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="font-size:26px">🎁</div>
                <div style="flex:1;font-size:13px;color:var(--text-secondary);line-height:1.7">
                  <strong>تريد الاستفادة من باقة شهرية؟</strong> الاشتراك يوفر الخدمات المشمولة دون دفع إضافي طوال صلاحية
                  الباقة.
                </div>
                <a href="packages.php" class="btn btn-primary"
                  style="padding:8px 18px;border-radius:12px;font-weight:700">اشترك بباقة 🚀</a>
              </div>
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label class="form-label">الكلية (التخصص الدقيق) <span style="color:var(--danger)">*</span></label>
            <select class="form-input" id="specialtySelect" name="specialty" required onchange="onSpecialtyChange()">
              <option value="">اختر الكلية...</option>
              <?php foreach ($colleges as $cl): ?>
                <option value="<?= e($cl) ?>" <?= ($specialtyValue !== '' && $specialtyValue === $cl) ? 'selected' : '' ?>>
                  <?= e($cl) ?>
                </option>
              <?php endforeach; ?>
              <option value="أخرى" <?= ($specialtyValue !== '' && !in_array($specialtyValue, $colleges, true)) ? 'selected' : '' ?>>
                أخرى (اكتبها بنفسك)
              </option>
            </select>
          </div>

          <div class="form-group" id="specialtyOtherBox"
            style="display:<?= ($specialtyValue !== '' && !in_array($specialtyValue, $colleges, true)) ? 'block' : 'none' ?>">
            <label class="form-label">اكتب اسم الكلية <span style="color:var(--danger)">*</span></label>
            <input type="text" class="form-input" id="specialtyOther" name="specialty_other"
              placeholder="مثال: كلية علوم الحاسب"
              value="<?= ($specialtyValue !== '' && !in_array($specialtyValue, $colleges, true)) ? e($specialtyValue) : '' ?>">
          </div>

        </div>

        <!-- STEP 2 -->
        <div class="form-step" id="step2" style="display:none">
          <h3 class="h3" style="margin-bottom:24px">تفاصيل العمل المطلوبة</h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">مستوى البحث <span style="color:var(--danger)">*</span></label>
              <select name="academic_level" class="form-input" required>
                <option value="بكالوريوس">بكالوريوس</option>
                <option value="ماجستير">ماجستير</option>
                <option value="دكتوراه">دكتوراه</option>
                <option value="أخرى">أخرى</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">اللغة <span style="color:var(--danger)">*</span></label>
              <select name="language" class="form-input" required>
                <option value="العربية">العربية</option>
                <option value="الإنجليزية">الإنجليزية</option>
              </select>
            </div>
          </div>
        </div>

        <!-- STEP 3 -->
        <div class="form-step" id="step3" style="display:none">
          <h3 class="h3" style="margin-bottom:24px">الشروحات والمرفقات</h3>

          <div class="form-group">
            <label class="form-label">وصف دقيق للطلب وملاحظاتك <span style="color:var(--danger)">*</span></label>
            <textarea name="description" class="form-input" rows="5"
              placeholder="أدخل متطلبات الطلب بالتفصيل لتسهيل فهم الأكاديمي..." required></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">المرفقات</label>
            <div
              style="border:2px dashed var(--border-color);border-radius:var(--radius-lg);padding:32px;text-align:center;background:var(--bg-body);cursor:pointer;transition:all 0.2s"
              onmouseover="this.style.borderColor='var(--primary)'"
              onmouseout="this.style.borderColor='var(--border-color)'"
              onclick="document.getElementById('fileUpload').click()">
              <input type="file" id="fileUpload" name="order_attachments[]" multiple style="display:none"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif,.webp,.txt">
              <div style="font-size:32px;margin-bottom:12px">📤</div>
              <div style="font-weight:700">اضغط لرفع الملفات والتعليمات</div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:8px">PDF, DOCX, ZIP بحد أقصى 10MB لكل ملف</div>
            </div>
            <div id="fileUploadList" style="display:none;margin-top:12px;display:flex;flex-direction:column;gap:6px"></div>
            <script>
              document.getElementById('fileUpload').addEventListener('change', function () {
                const list = document.getElementById('fileUploadList');
                list.innerHTML = '';
                if (!this.files.length) { list.style.display = 'none'; return; }
                list.style.display = 'flex';
                Array.from(this.files).forEach(function (f) {
                  const row = document.createElement('div');
                  row.style.cssText = 'display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-secondary);background:var(--bg-body);border:1px solid var(--border-color);border-radius:10px;padding:8px 12px';
                  row.innerHTML = '<span>📎</span><span style="flex:1;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span><span style="flex-shrink:0">' + (f.size / 1024 / 1024).toFixed(2) + ' MB</span>';
                  row.querySelector('span:nth-child(2)').textContent = f.name;
                  list.appendChild(row);
                });
              });
            </script>
          </div>
        </div>

        <!-- STEP 4: الدفع -->
        <div class="form-step" id="step4" style="display:none">
          <h3 class="h3" style="margin-bottom:8px">خطوة الدفع 💳</h3>
          <p class="text-body" style="margin-bottom:20px">
            حوّل المبلغ إلى أحد حسابات المنصة أدناه، ثم اختر الحساب الذي حولت إليه وأرفق إشعار الدفع مع طلبك.
          </p>

          <div id="payStepFreeNote" style="display:none;background:#dcfce7;color:#166534;border:1px solid #86efac;padding:16px;border-radius:12px;font-weight:700;margin-bottom:16px">
            🎁 هذه الخدمة مشمولة في باقتك — الإجمالي مجاني، لا حاجة لخطوات الدفع. اضغط "إنشاء الطلب" لإتمام الطلب.
          </div>

          <div id="payStepContent">
            <div class="form-group">
              <label class="form-label">اختر حساب المنصة الذي حوّلت إليه <span style="color:var(--danger)">*</span></label>
              <?php if (empty($paymentMethods)): ?>
                <div style="background:#fef3c7;color:#92400e;padding:12px;border-radius:10px;font-size:13px;font-weight:600">
                  لا توجد حسابات دفع مفعّلة حالياً — سيتم التواصل معك لإتمام الدفع.
                </div>
              <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px">
                  <?php foreach ($paymentMethods as $pm): ?>
                    <label style="display:flex;align-items:center;gap:12px;border:2px solid var(--border-color);border-radius:14px;padding:14px;cursor:pointer;transition:all .2s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--border-color)'">
                      <input type="radio" name="payment_method_id" value="<?= (int)$pm['id'] ?>" style="accent-color:var(--primary);width:18px;height:18px;flex-shrink:0">
                      <?php if (!empty($pm['logo'])): ?>
                        <img src="<?= e($appBase . '/' . $pm['logo']) ?>" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:8px;background:#fff;border:1px solid var(--border-color);flex-shrink:0">
                      <?php else: ?>
                        <span style="font-size:26px"><?= ($pm['account_type'] ?? 'bank') === 'wallet' ? '👛' : '🏦' ?></span>
                      <?php endif; ?>
                      <span style="min-width:0">
                        <strong style="display:block;font-size:14px"><?= e($pm['account_name']) ?></strong>
                        <span style="font-size:12px;color:var(--text-secondary);direction:ltr;unicode-bidi:embed"><?= e($pm['account_number']) ?></span>
                        <?php if (!empty($pm['holder_name'])): ?>
                          <span style="display:block;font-size:11px;color:var(--text-secondary)"><?= e($pm['holder_name']) ?></span>
                        <?php endif; ?>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label">إرفاق إشعار الدفع (صورة أو PDF) <span style="color:var(--danger)">*</span></label>
              <input type="file" class="form-input" name="payment_receipt" accept="image/*,.pdf" required>
              <small style="color:var(--text-secondary);font-size:12px">إلزامي: بعد التحويل، صوّر إشعار التحويل أو إيصال الدفع وأرفقه هنا (حتى 10MB). لن يُقبل الطلب بدون إشعار الدفع.</small>
            </div>
          </div>
        </div>

        <div
          style="display:flex;justify-content:space-between;border-top:1px solid var(--border-color);padding-top:24px;margin-top:16px">
          <button type="button" class="btn btn-outline" id="prevBtn" style="display:none">السابق</button>
          <div style="margin-right:auto">
            <button type="button" class="btn btn-primary" id="nextBtn">التالي</button>
            <button type="submit" class="btn btn-primary" id="submitBtn"
              style="display:none;background:var(--success)">✅ إنشاء الطلب</button>
          </div>
        </div>

      </form>
    </div>

    <!-- Summary Sidebar -->
    <div>
      <div class="card" style="position:sticky;top:90px">
        <h3 class="h3" style="margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border-color)">ملخص
          الطلب</h3>

        <div
          style="display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;color:var(--text-secondary)">
          <span>السعر المبدئي للخدمة</span>
          <span id="basePrice"
            style="font-weight:700;color:var(--text-primary)"><?= formatMoney($initialPrice) ?></span>
        </div>

        <div
          style="margin-top:20px;border-top:1px dashed var(--border-color);padding-top:16px;display:flex;justify-content:space-between;align-items:center">
          <span style="font-weight:700">الإجمالي المتوقع</span>
          <span id="totalPrice"
            style="font-size:24px;font-weight:800;color:var(--primary)"><?= $initialIncluded ? 'مشمول بالباقة 🎁' : formatMoney($initialPrice) ?></span>
        </div>

        <div
          style="margin-top:24px;padding:12px;background:var(--primary-light);border-radius:var(--radius-sm);color:var(--primary);font-size:12px;line-height:1.6">
          <strong>ملاحظة:</strong>
          هذا تسعير مبدئي، وسيتم تأكيد السعر النهائي من الأكاديمي بناءً على متطلباتك الدقيقة.
        </div>

      </div>
    </div>

  </div>

  <?php
  $extraJs = ob_start() ? '' : '';
  ob_start();
  ?>
  <script>
    // Local flow control since we moved to standard forms
    const steps = document.querySelectorAll('.form-step');
    const indicators = document.querySelectorAll('.step-indicator');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const submitBtn = document.getElementById('submitBtn');
    const srvSelect = document.getElementById('serviceIdInput'); // حقل مخفي يحمل الخدمة المختارة (عرض ثابت)
    const fixedPriceEl = document.getElementById('fixedServicePrice');
    const includedBadgeEl = document.getElementById('serviceIncludedBadge');
    const usedBadgeEl = document.getElementById('serviceUsedBadge');
    const nonIncludedBadgeEl = document.getElementById('serviceNonIncludedBadge');
    const basePriceEl = document.getElementById('basePrice');
    const totalPriceEl = document.getElementById('totalPrice');

    // بيانات الخدمات + الخدمات المشمولة في الباقة (أُدخلت من PHP)
    const servicesData = <?= $servicesJson ?>;
    const includedIdsSet = new Set(<?= json_encode($includedIdsJs) ?>);      // المشمولات غير المستهلكة → مجانية
    const usedIncludedSet = new Set(<?= json_encode($usedIncludedIdsJs) ?>); // المستهلكة سابقاً → بسعر الخدمة

    const specialtySelect = document.getElementById('specialtySelect');
    const specialtyOtherBox = document.getElementById('specialtyOtherBox');
    const specialtyOtherInput = document.getElementById('specialtyOther');
    const stepErrorBox = document.getElementById('stepErrorBox');

    /* ── القوائم المنسدلة المتتالية ── */
    /* ── الخدمة ثابتة (لا قوائم): نحدّث الأوسمة والسعر فقط ── */
    // إظهار/إخفاء حقل "أخرى" للكلية
    function onSpecialtyChange() {
      if (!specialtySelect || !specialtyOtherBox) return;
      const isOther = specialtySelect.value === 'أخرى';
      specialtyOtherBox.style.display = isOther ? 'block' : 'none';
      if (specialtyOtherInput) {
        specialtyOtherInput.required = isOther;
        if (!isOther) specialtyOtherInput.value = '';
        else specialtyOtherInput.focus();
      }
    }

    let currentStep = 0;

    function getSelectedService() {
      if (!srvSelect || !srvSelect.value) return null;
      return servicesData[Number(srvSelect.value)] || null;
    }

    function updateServiceCard() {
      const svc = getSelectedService();
      if (!svc) {
        if (includedBadgeEl) includedBadgeEl.style.display = 'none';
        if (usedBadgeEl) usedBadgeEl.style.display = 'none';
        if (nonIncludedBadgeEl) nonIncludedBadgeEl.style.display = 'none';
        return;
      }
      // الخدمة معروضة بشكل ثابت من PHP — نحدّث الأوسمة فقط حسب حالة الباقة
      const included = includedIdsSet.has(svc.id);
      const usedBefore = usedIncludedSet.has(svc.id);
      if (includedBadgeEl) includedBadgeEl.style.display = included ? 'inline-block' : 'none';
      if (usedBadgeEl) usedBadgeEl.style.display = (!included && usedBefore) ? 'inline-block' : 'none';
      if (nonIncludedBadgeEl) nonIncludedBadgeEl.style.display = !included ? 'inline-block' : 'none';
    }

    function updatePricing() {
      const svc = getSelectedService();
      let price = 150;
      let included = false;
      if (svc) {
        included = includedIdsSet.has(svc.id);
        price = included ? 0 : (isNaN(svc.price) ? 150 : svc.price);
      }
      basePriceEl.textContent = price + ' ر.س';
      totalPriceEl.textContent = included ? 'مشمول بالباقة 🎁' : price + ' ر.س';
      // تحديث سطر السعر داخل بطاقة الخدمة الثابتة
      if (fixedPriceEl) {
        fixedPriceEl.textContent = '💵 السعر: ' + (included ? 'مشمول بالباقة 🎁' : price + ' ر.س');
      }
      updateServiceCard();
    }

    function renderStep() {
      steps.forEach((s, idx) => {
        s.style.display = (idx === currentStep) ? 'block' : 'none';

        // Update indicators
        const indicator = indicators[idx];
        const ci = indicator.querySelector('.circle');

        if (idx < currentStep) {
          indicator.classList.remove('active');
          indicator.classList.add('completed');
          ci.style.background = 'var(--primary)';
          ci.style.color = 'white';
          ci.style.borderColor = 'var(--primary)';
          ci.innerHTML = '✓';
        } else if (idx === currentStep) {
          indicator.classList.add('active');
          indicator.classList.remove('completed');
          ci.style.background = 'var(--primary)';
          ci.style.color = 'white';
          ci.style.borderColor = 'var(--primary)';
          ci.innerHTML = idx + 1;
        } else {
          indicator.classList.remove('active', 'completed');
          ci.style.background = 'var(--bg-card)';
          ci.style.color = 'var(--text-secondary)';
          ci.style.borderColor = 'var(--border-color)';
          ci.innerHTML = idx + 1;
        }
      });

      if (currentStep === 0) {
        prevBtn.style.display = 'none';
      } else {
        prevBtn.style.display = 'inline-flex';
      }

      if (currentStep === steps.length - 1) {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-flex';
        // خطوة الدفع: تُخفى تفاصيل الدفع إذا كانت الخدمة مجانية (مشمولة بالباقة)
        const svc = getSelectedService();
        const isFree = svc && includedIdsSet.has(svc.id);
        const freeNote = document.getElementById('payStepFreeNote');
        const payContent = document.getElementById('payStepContent');
        if (freeNote && payContent) {
          freeNote.style.display = isFree ? 'block' : 'none';
          payContent.style.display = isFree ? 'none' : 'block';
        }
      } else {
        nextBtn.style.display = 'inline-flex';
        submitBtn.style.display = 'none';
      }
    }

    /* ============================================================
       التحقق من إكمال الحقول الإلزامية في كل خطوة
       ============================================================ */
    function isPaymentFree() {
      const svc = getSelectedService();
      return !!(svc && includedIdsSet.has(svc.id));
    }

    // الحقول المطلوبة في خطوة معينة (مع تجاهل حقول الدفع عند بقاء الخدمة مشمولة بالباقة)
    function stepFields(step) {
      const payContent = document.getElementById('payStepContent');
      return Array.prototype.slice.call(step.querySelectorAll('input, select, textarea')).filter(function (f) {
        if (f.disabled || f.type === 'hidden' || f.type === 'file') return false;
        if (payContent && payContent.style.display === 'none' && payContent.contains(f)) return false;
        return true;
      });
    }

    // اسم الحقل من التسمية المجاورة (لرسالة واضحة)
    function fieldLabelOf(f) {
      const group = f.closest ? f.closest('.form-group') : null;
      const lbl = group ? group.querySelector('.form-label') : null;
      if (!lbl) return 'حقل مطلوب';
      return lbl.textContent.replace(/\*/g, '').replace(/\s+/g, ' ').trim();
    }

    function markInvalid(f, on) {
      if (!f) return;
      if (on) {
        f.style.borderColor = 'var(--danger)';
        f.style.boxShadow = '0 0 0 3px rgba(239,68,68,.12)';
      } else {
        f.style.borderColor = '';
        f.style.boxShadow = '';
      }
    }

    function showStepError(msg) {
      if (!stepErrorBox) return;
      if (msg) {
        stepErrorBox.textContent = '⚠️ ' + msg;
        stepErrorBox.style.display = 'block';
      } else {
        stepErrorBox.style.display = 'none';
        stepErrorBox.textContent = '';
      }
    }

    // يعيد { ok, missing[], first } مع تحديد الحقول الناقصة بإطار أحمر
    function validateStep(idx) {
      const step = steps[idx];
      if (!step) return { ok: true, missing: [], first: null };

      const missing = [];
      let firstEl = null;

      stepFields(step).forEach(function (f) {
        // أزرار الاختيار (طريقة الدفع) تُفحص كمجموعة في القاعدة الخاصة أدناه
        if (f.type === 'radio') return;
        if (!f.required) { markInvalid(f, false); return; }
        const val = (f.value || '').trim();
        if (!val) {
          markInvalid(f, true);
          if (!firstEl) firstEl = f;
          missing.push(fieldLabelOf(f));
        } else {
          markInvalid(f, false);
        }
      });

      // قاعدة خاصة: يجب وجود خدمة مختارة (حقل مخفي بعرض ثابت)
      if (idx === 0 && (!srvSelect || !srvSelect.value)) {
        missing.push('الخدمة المطلوبة');
      }

      // قاعدة خاصة: طريقة الدفع + إشعار الدفع مطلوبة في الخطوة الأخيرة للطلبات المدفوعة
      if (idx === steps.length - 1 && !isPaymentFree()) {
        const radios = step.querySelectorAll('input[type="radio"][name="payment_method_id"]');
        if (radios.length) {
          const anyChecked = Array.prototype.some.call(radios, function (r) { return r.checked; });
          if (!anyChecked) {
            missing.push('حساب المنصة الذي حوّلت إليه');
            if (!firstEl) firstEl = radios[0];
          }
        }
        const rcpt = step.querySelector('input[type="file"][name="payment_receipt"]');
        if (rcpt && (!rcpt.files || !rcpt.files.length)) {
          missing.push('إشعار الدفع (صورة أو PDF)');
          if (!firstEl) firstEl = rcpt;
        }
      }

      return { ok: missing.length === 0, missing: missing, first: firstEl };
    }

    nextBtn?.addEventListener('click', () => {
      const res = validateStep(currentStep);
      if (!res.ok) {
        showStepError('لا يمكن الانتقال للخطوة التالية قبل إكمال الحقول الإلزامية: ' + res.missing.join(' ، '));
        if (res.first) {
          res.first.focus();
          res.first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
      }
      showStepError('');
      if (currentStep < steps.length - 1) {
        currentStep++;
        renderStep();
      }
    });

    prevBtn?.addEventListener('click', () => {
      if (currentStep > 0) {
        currentStep--;
        showStepError('');
        renderStep();
      }
    });

    document.getElementById('multiOrderForm').addEventListener('submit', (e) => {
      // التحقق من كل الخطوات قبل الإرسال (الخطوات المخفية لا تُفحص تلقائياً)
      for (let i = 0; i < steps.length; i++) {
        const res = validateStep(i);
        if (!res.ok) {
          e.preventDefault();
          currentStep = i;
          renderStep();
          showStepError('يرجى إكمال الحقول الإلزامية في خطوة "' + (indicators[i] ? indicators[i].querySelector('.step-label').textContent.trim() : (i + 1)) + '": ' + res.missing.join(' ، '));
          if (res.first) {
            res.first.focus();
            res.first.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          return;
        }
      }
      showStepError('');
      submitBtn.textContent = 'جاري الإرسال...';
      submitBtn.disabled = true;
    });

    // تحديث الأوسمة والسعر للخدمة الثابتة + عرض الخطوة الحالية
    onSpecialtyChange();
    updatePricing();
    renderStep();
  </script>
  <?php
  $extraJs = ob_get_clean();
  require __DIR__ . '/partials/footer.php';
  ?>