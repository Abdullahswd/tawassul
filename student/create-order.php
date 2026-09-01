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
   الاشتراك النشط بالباقة + الخدمات المشمولة
───────────────────────────────────────────── */
$activeSub = getActivePackageSubscription((int) $user['id']);
$hasSub = (bool) $activeSub;
$includedIds = activeSubscriptionServiceIds($activeSub);
$includedIdsJs = empty($includedIds) ? [] : $includedIds;

// الخدمة المختارة مسبقاً (لعرض تفاصيلها داخل الصفحة)
$selectedService = null;
if ($prefillServiceId) {
  foreach ($services as $s) {
    if ((int) $s['id'] === $prefillServiceId) {
      $selectedService = $s;
      break;
    }
  }
}

// السعر المبدئي المعروض لملخص الطلب قبل تنفيذ JS
$initialPrice = 150.0;
$initialIncluded = false;
if ($prefillServiceId !== 0 && $hasSub && in_array($prefillServiceId, $includedIds, true)) {
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
    'included' => in_array((int) $s['id'], $includedIds, true),
  ];
}
$servicesJson = json_encode($servicesDataMap, JSON_UNESCAPED_UNICODE);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $service_id = (int) ($_POST['service_id'] ?? 0);
  $specialty = trim($_POST['specialty'] ?? '');
  $academic_level = $_POST['academic_level'] ?? 'بكالوريوس';
  $language = $_POST['language'] ?? 'العربية';
  $deadline = $_POST['deadline'] ?? '';
  $description = trim($_POST['description'] ?? '');

  // حساب السعر: الخدمات المشمولة في الباقة مجانية، وما عداها يُسعَّر بشكل منفرد.
  $amount = 150.0;
  $package_id = null;
  if ($service_id) {
    if ($hasSub && $activeSub && in_array($service_id, $includedIds, true)) {
      $amount = 0.0;
      $package_id = (int) $activeSub['package_id'];
    } else {
      $stmt = $db->prepare('SELECT price FROM services WHERE id = ? LIMIT 1');
      $stmt->execute([$service_id]);
      $srvPrice = $stmt->fetchColumn();
      if ($srvPrice && $srvPrice > 0) {
        $amount = (float) $srvPrice;
      }
    }
  }

  if (!$service_id || !$specialty || !$deadline || !$description) {
    $error = 'يرجى ملء كافة الحقول المطلوبة بنجاح.';
  } else {
    $order_id = createOrder([
      'student_id' => $user['id'],
      'service_id' => $service_id,
      'package_id' => $package_id,
      'specialty' => $specialty,
      'academic_level' => $academic_level,
      'language' => $language,
      'deadline' => $deadline,
      'description' => $description,
      'amount' => $amount,
    ]);

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
      </div>

      <form method="POST" action="create-order.php" id="multiOrderForm">

        <!-- STEP 1 -->
        <div class="form-step" id="step1">
          <h3 class="h3" style="margin-bottom:24px">اختيار الخدمة والتخصص</h3>

          <div class="form-group">
            <label class="form-label">الخدمة المطلوبة <span style="color:var(--danger)">*</span></label>
            <select class="form-input" id="serviceSelect" name="service_id" onchange="updatePricing()">
              <option value="">اختر الخدمة المطلوبة...</option>
              <?php foreach ($services as $s): ?>
                <option value="<?= $s['id'] ?>" data-price="<?= (float) ($s['price'] ?? 0) ?>"
                  <?= $prefillServiceId === (int) $s['id'] ? 'selected' : '' ?>>
                  <?= e($s['icon']) ?>   <?= e($s['name']) ?> (<?= formatMoney((float) ($s['price'] ?? 0)) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- تفاصيل الخدمة المختارة (تظهر مكان زر التفاصيل المحذوف) -->
          <div class="form-group" id="serviceDetailsBox"
            style="display:<?= $selectedService ? 'block' : 'none' ?>;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:16px;background:var(--bg-body);margin-top:4px">
            <?php if ($selectedService): ?>
              <div style="display:flex;align-items:flex-start;gap:10px">
                <div style="font-size:28px;flex-shrink:0"><?= e($selectedService['icon'] ?: '📦') ?></div>
                <div style="flex:1">
                  <div style="font-weight:800;margin-bottom:6px" id="serviceDetailsName">
                    <?= e($selectedService['name']) ?></div>
                  <div style="font-size:13px;color:var(--text-secondary);line-height:1.7" id="serviceDetailsDesc">
                    <?= e($selectedService['description'] ?: '') ?></div>
                  <div id="serviceIncludedBadge"
                    style="display:<?= ($selectedService && $initialIncluded) ? 'inline-block' : 'none' ?>;margin-top:10px;background:#dcfce7;color:#166534;font-size:12px;font-weight:800;padding:5px 12px;border-radius:20px">
                    ✅ هذه الخدمة مشمولة في باقتك
                  </div>
                  <div id="serviceNonIncludedBadge"
                    style="display:<?= ($selectedService && !$initialIncluded) ? 'inline-block' : 'none' ?>;margin-top:10px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:800;padding:5px 12px;border-radius:20px">
                    هذه الخدمة خارج باقتك وسيتم تسعيرها بشكل منفرد
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($hasSub && $activeSub): ?>
            <!-- الطالب مشترك في باقة -->
            <div class="form-group"
              style="border:1px dashed var(--primary);border-radius:var(--radius-md);padding:16px;background:var(--primary-light);margin-top:4px">
              <div style="font-weight:800;color:var(--primary);margin-bottom:6px">
                🎁 باقتك النشطة: <?= e($activeSub['package_name']) ?> — سارية حتى
                <?= formatDate($activeSub['expires_at']) ?>
              </div>
              <div style="font-size:13px;color:var(--text-secondary);line-height:1.7">
                إذا كانت الخدمة المختارة ضمن خدمات باقتك فستكون <strong>مشمولة مجاناً</strong> في طلبك.
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
            <label class="form-label">التخصص الدقيق <span style="color:var(--danger)">*</span></label>
            <input type="text" name="specialty" class="form-input" placeholder="مثال: إدارة الموارد البشرية" required>
          </div>

        </div>

        <!-- STEP 2 -->
        <div class="form-step" id="step2" style="display:none">
          <h3 class="h3" style="margin-bottom:24px">تفاصيل العمل المطلوبة</h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">المستوى الأكاديمي <span style="color:var(--danger)">*</span></label>
              <select name="academic_level" class="form-input" required>
                <option value="بكالوريوس">بكالوريوس</option>
                <option value="ماجستير">ماجستير</option>
                <option value="دكتوراه">دكتوراه</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">اللغة <span style="color:var(--danger)">*</span></label>
              <select name="language" class="form-input" required>
                <option value="العربية">العربية</option>
                <option value="الإنجليزية">الإنجليزية</option>
              </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">موعد التسليم المرغوب <span style="color:var(--danger)">*</span></label>
              <input type="date" name="deadline" class="form-input" required min="<?= date('Y-m-d') ?>">
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
              <input type="file" id="fileUpload" multiple style="display:none">
              <div style="font-size:32px;margin-bottom:12px">📤</div>
              <div style="font-weight:700">اضغط لرفع الملفات والتعليمات</div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:8px">PDF, DOCX, ZIP بحد أقصى 10MB</div>
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
    const srvSelect = document.getElementById('serviceSelect');
    const detailsBox = document.getElementById('serviceDetailsBox');
    const detailsName = document.getElementById('serviceDetailsName');
    const detailsDesc = document.getElementById('serviceDetailsDesc');
    const includedBadgeEl = document.getElementById('serviceIncludedBadge');
    const nonIncludedBadgeEl = document.getElementById('serviceNonIncludedBadge');
    const basePriceEl = document.getElementById('basePrice');
    const totalPriceEl = document.getElementById('totalPrice');

    // بيانات الخدمات + الخدمات المشمولة في الباقة (أُدخلت من PHP)
    const servicesData = <?= $servicesJson ?>;
    const includedIdsSet = new Set(<?= json_encode($includedIdsJs) ?>);

    let currentStep = 0;

    function getSelectedService() {
      if (!srvSelect || !srvSelect.value) return null;
      return servicesData[Number(srvSelect.value)] || null;
    }

    function updateServiceCard() {
      if (!detailsBox) return;
      const svc = getSelectedService();
      if (!svc) {
        detailsBox.style.display = 'none';
        if (includedBadgeEl) includedBadgeEl.style.display = 'none';
        if (nonIncludedBadgeEl) nonIncludedBadgeEl.style.display = 'none';
        return;
      }
      detailsBox.style.display = 'block';
      if (detailsName) detailsName.textContent = (svc.icon || '📦') + ' ' + svc.name;
      if (detailsDesc) detailsDesc.textContent = svc.description || '';
      const included = includedIdsSet.has(svc.id);
      if (includedBadgeEl) includedBadgeEl.style.display = included ? 'inline-block' : 'none';
      if (nonIncludedBadgeEl) nonIncludedBadgeEl.style.display = (included || includedIdsSet.size === 0) ? 'none' : 'inline-block';
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
      } else {
        nextBtn.style.display = 'inline-flex';
        submitBtn.style.display = 'none';
      }
    }

    nextBtn?.addEventListener('click', () => {
      if (currentStep === 0) {
        const hasService = srvSelect && srvSelect.value !== "";
        if (!hasService) {
          alert('يرجى اختيار خدمة أولاً');
          return;
        }
      }
      if (currentStep < steps.length - 1) {
        currentStep++;
        renderStep();
      }
    });

    prevBtn?.addEventListener('click', () => {
      if (currentStep > 0) {
        currentStep--;
        renderStep();
      }
    });

    document.getElementById('multiOrderForm').addEventListener('submit', () => {
      submitBtn.textContent = 'جاري الإرسال...';
      submitBtn.disabled = true;
    });

    updatePricing();
    renderStep();
  </script>
  <?php
  $extraJs = ob_get_clean();
  require __DIR__ . '/partials/footer.php';
  ?>