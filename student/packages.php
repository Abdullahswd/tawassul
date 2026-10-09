<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db = db();

// جلب الباقات المفعلة بالنظام
$packages = [];
try {
    $stmt = $db->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب جميع الخدمات للربط
    $services_stmt = $db->query("SELECT id, name, icon, price FROM services WHERE is_active = 1");
    $all_services_raw = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
    $services_by_id = [];
    foreach ($all_services_raw as $srv) {
        $services_by_id[$srv['id']] = $srv;
    }

    foreach ($rows as $row) {
        $row['features'] = json_decode($row['features_json'] ?? '[]', true) ?: [];
        $row['service_ids'] = json_decode($row['service_ids'] ?? '[]', true) ?: [];
        $row['icon'] = !empty($row['icon']) ? $row['icon'] : '📦';
        $row['original_price'] = (float)($row['original_price'] ?? 0);
        $row['price'] = (float)($row['price'] ?? 0);
        
        $row['included_services'] = [];
        foreach ($row['service_ids'] as $sid) {
            if (isset($services_by_id[$sid])) {
                $row['included_services'][] = $services_by_id[$sid];
            }
        }
        $packages[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching packages for student: " . $e->getMessage());
}

/* ─────────────────────────────────────────────
   PACKAGE SUBSCRIPTION HANDLING (مع خطوة الدفع)
───────────────────────────────────────────── */
$paymentMethods = getActivePaymentMethods();
$appBase = rtrim(preg_replace('#/student/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

// معالجة الاشتراك: إنشاء الاشتراك + تسجيل إشعار الدفع المرفق
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_package_id'])) {
    $pid = (int) $_POST['subscribe_package_id'];
    $pkgStmt = $db->prepare('SELECT * FROM packages WHERE id = ? AND is_active = 1 LIMIT 1');
    $pkgStmt->execute([$pid]);
    $subPkg = $pkgStmt->fetch(PDO::FETCH_ASSOC);
    if ($subPkg) {
        // التحقق من إرفاق إشعار الدفع (إلزامي)
        $rcptPath = saveReceiptUpload($_FILES['payment_receipt'] ?? null);
        if ($rcptPath) {
            // السماح بالاشتراك في أكثر من باقة:
            // إن وُجد اشتراك نشط لنفس الباقة يُعاد استخدامه، وإلا يُنشأ اشتراك جديد
            $subId = subscribeStudentToPackage((int) $user['id'], $pid);

            $methodId = (int) ($_POST['payment_method_id'] ?? 0) ?: null;
            
            // محاولة جلب اسم طريقة الدفع
            $methodLabel = 'تحويل بنكي';
            foreach ($paymentMethods as $pm) {
                if ((int)$pm['id'] === $methodId) {
                    $methodLabel = $pm['account_name'];
                    break;
                }
            }

            savePaymentReceipt([
                'subscription_id' => $subId,
                'student_id'      => $user['id'],
                'method_id'       => $methodId,
                'method_label'    => $methodLabel,
                'amount'          => (float) ($subPkg['price'] ?? 0),
                'receipt_path'    => $rcptPath,
            ]);
            createNotification(1, 'admin', 'إشعار دفع باقة 🧾', 'أرفق الطالب ' . $user['name'] . ' إشعار دفع لاشتراك في باقة ' . $subPkg['name'] . '.', '🧾', 'admin/pages/payments.php#tab-students');
            header('Location: packages.php?subscribed=' . $pid);
            exit;
        } else {
            $uploadError = 'إرفاق إشعار الدفع الزامي لإتمام الاشتراك!';
        }
    }
}

// باقات الطالب النشطة حالياً + حالة مراجعة إشعار الدفع لكل باقة
$activeSubsByPkg  = []; // package_id => بيانات الاشتراك النشط + حالة الإشعار
$activeSubNames   = [];
foreach (getAllActivePackageSubscriptions((int) $user['id']) as $s) {
    $r = getReceiptForSubscription((int) $s['sub_id']);
    $s['receipt_status'] = $r['status'] ?? 'approved'; // بدون إشعار = مفعّل مباشرة
    $activeSubsByPkg[(int) $s['package_id']] = $s;
    if ($s['receipt_status'] === 'approved') {
        $activeSubNames[] = $s['package_name'];
    }
}
$subJustMadeId = isset($_GET['subscribed']) ? (int) $_GET['subscribed'] : 0;
?>
<?php
$extraCss = [
  '<style>
    .pkg-card {
      background: var(--bg-card);
      border-radius: 24px;
      border: 1px solid var(--border-color);
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
      padding: 24px;
    }
    .pkg-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 15px 35px -10px rgba(79,70,229,0.18);
      border-color: var(--primary);
    }

    .pkg-icon-wrap {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
    }

    .discount-badge {
      background: #ef4444;
      color: white;
      font-size: 11px;
      font-weight: 800;
      padding: 3px 10px;
      border-radius: 20px;
    }

    .price-box {
      margin: 16px 0;
      display: flex;
      align-items: baseline;
      gap: 8px;
    }
    .price-new { font-size: 32px; font-weight: 900; line-height: 1; }
    .price-old { font-size: 16px; color: #94a3b8; text-decoration: line-through; font-weight: 600; }

    .pkg-service-list {
      list-style: none;
      padding: 0;
      margin: 14px 0;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .pkg-service-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--text-secondary);
      font-weight: 600;
    }
    .pkg-service-item .check {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #dcfce7;
      color: #166534;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      flex-shrink: 0;
      font-weight: 800;
    }
  </style>',
];
$pageTitle  = 'الباقات المخصصة';
$activePage = 'packages';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>

      <!-- Page Content -->
      <div class="content-wrap" style="padding:24px;max-width:1200px;margin:0 auto">

        <?php if (!empty($uploadError)): ?>
          <div class="mb-6 rounded-2xl p-5 border" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;box-shadow:0 4px 14px rgba(0,0,0,0.04)">
            <div class="flex items-center gap-3">
              <div style="font-size:30px">❌</div>
              <div style="font-weight:800;font-size:16px"><?= e($uploadError) ?></div>
            </div>
          </div>
        <?php endif; ?>

        <div class="mb-8 bg-gradient-to-l from-indigo-900 to-slate-900 text-white rounded-3xl p-8 shadow-md">
          <div class="max-w-2xl">
            <span class="inline-block bg-indigo-500/20 text-indigo-300 px-3 py-1 rounded-full text-xs font-bold mb-3">وفر حتى 30% من التكلفة</span>
            <h1 class="text-2xl md:text-3xl font-black mb-3">اختر الباقة الأكاديمية المناسبة لمشروعك</h1>
            <p class="text-slate-300 text-sm leading-relaxed">
              تجمع لك الباقات أهم الخدمات الأكاديمية في حزمة واحدة بسعر مخفض مع أولوية في التنفيذ ومتابعة مستمرة.
            </p>
          </div>
        </div>

        <?php if ($subJustMadeId && isset($activeSubsByPkg[$subJustMadeId])): ?>
          <div class="mb-6 rounded-2xl p-5 border" style="background:#fef9c3;border-color:#fef08a;color:#854d0e;box-shadow:0 4px 14px rgba(0,0,0,0.04)">
            <div class="flex items-center gap-3">
              <div style="font-size:30px">⏳</div>
              <div>
                <div style="font-weight:800;font-size:16px">تم إرسال طلب اشتراكك في باقة «<?= e($activeSubsByPkg[$subJustMadeId]['package_name']) ?>» بنجاح</div>
                <div style="font-size:13px;opacity:0.9;margin-top:3px">
                  لقد استلمنا إشعار الدفع الخاص بك وهو الآن قيد المراجعة من قبل الإدارة. سيتم تفعيل الباقة فور الاعتماد.
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($activeSubNames)): ?>
          <div class="mb-6 text-sm" style="font-weight:700;color:var(--text-secondary)">
            🎁 باقاتك النشطة حالياً:
            <?php foreach ($activeSubNames as $i => $nm): ?>
              <span style="color:var(--primary)"><?= e($nm) ?></span><?= $i < count($activeSubNames) - 1 ? '، ' : '' ?>
            <?php endforeach; ?>
            — الباقات مفعلة وجاهزة للاستخدام (صالحة حتى استنفاد كافة الخدمات المشمولة).
          </div>
        <?php endif; ?>

        <!-- شبكة عرض الباقات -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php if (empty($packages)): ?>
            <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-dashed border-slate-200">
              <span class="text-6xl block mb-4">🎁</span>
              <h3 class="text-lg font-bold text-slate-800 mb-2">لا توجد باقات مفعلة حالياً</h3>
              <p class="text-slate-500 text-xs mb-6">يمكنك الاستفادة من الخدمات الفردية بطلب مباشر.</p>
              <a href="services.php" class="btn btn-primary">تصفح الخدمات 🚀</a>
            </div>
          <?php else: ?>
            <?php foreach ($packages as $idx => $pkg): 
              $color = !empty($pkg['color']) ? $pkg['color'] : '#6366f1';
              $origPrice = $pkg['original_price'] > 0 ? $pkg['original_price'] : $pkg['price'];
              $discountAmount = $origPrice > $pkg['price'] ? ($origPrice - $pkg['price']) : 0;
              $discountPercent = $origPrice > 0 ? round(($discountAmount / $origPrice) * 100) : 0;
              $included = $pkg['included_services'];
            ?>
            <div class="pkg-card" style="border-top: 5px solid <?= $color ?>;">
              <div>
                <div class="flex items-center justify-between mb-3">
                  <div class="pkg-icon-wrap" style="background: <?= $color ?>15; color: <?= $color ?>;">
                    <?= $pkg['icon'] ?>
                  </div>
                  <?php if ($discountPercent > 0): ?>
                    <span class="discount-badge">خصم <?= $discountPercent ?>%</span>
                  <?php endif; ?>
                </div>

                <h3 class="text-xl font-extrabold text-slate-900 mb-2"><?= htmlspecialchars($pkg['name']) ?></h3>

                <div class="price-box">
                  <span class="price-new" style="color: <?= $color ?>;"><?= number_format($pkg['price'], 2) ?> <span style="font-size:14px;font-weight:700;">ر.س</span></span>
                  <?php if ($discountAmount > 0): ?>
                    <span class="price-old"><?= number_format($origPrice, 2) ?> ر.س</span>
                  <?php endif; ?>
                </div>

                <!-- قائمة الخدمات المشمولة -->
                <div class="border-t border-slate-100 pt-3 mt-3">
                  <div class="text-xs font-bold text-slate-500 mb-2">الخدمات المتضمنة (<?= count($included) ?>):</div>
                  <ul class="pkg-service-list">
                    <?php if (!empty($included)): ?>
                      <?php foreach ($included as $srv): ?>
                        <li class="pkg-service-item">
                          <span class="check">✓</span>
                          <span><?= htmlspecialchars($srv['icon'] ?: '•') ?> <?= htmlspecialchars($srv['name']) ?></span>
                        </li>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($pkg['features'])): ?>
                      <?php foreach ($pkg['features'] as $feat): ?>
                        <li class="pkg-service-item">
                          <span class="check" style="background:#e0e7ff;color:#4338ca;">★</span>
                          <span><?= htmlspecialchars($feat) ?></span>
                        </li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>

              <div class="pt-4 border-t border-slate-100 mt-4">
              <?php $pkgSubStatus = $activeSubsByPkg[(int)$pkg['id']]['receipt_status'] ?? null; ?>
                <?php if ($pkgSubStatus === 'approved'): ?>
                  <div class="w-full inline-block text-center py-2.5 px-4 rounded-xl font-bold text-sm" style="background:#dcfce7;color:#166534;border:1px solid #86efac">
                    ✓ أنت مشترك — الباقة مفعلة حتى استنفاد خدماتها
                  </div>
                  <a href="services.php" class="w-full inline-block text-center py-2.5 px-4 rounded-xl font-bold text-sm shadow-sm transition hover:scale-[1.02] mt-2" style="background:var(--bg-body);color:var(--text-primary);border:1px solid var(--border-color);">
                    طلب الخدمات المشمولة 🚀
                  </a>
                <?php elseif ($pkgSubStatus === 'pending'): ?>
                  <div class="w-full inline-block text-center py-2.5 px-4 rounded-xl font-bold text-sm" style="background:#fef9c3;color:#854d0e;border:1px solid #fde047">
                    ⏳ طلب الاشتراك قيد المراجعة — بانتظار اعتماد إشعار الدفع من الإدارة
                  </div>
                <?php elseif ($pkgSubStatus === 'rejected'): ?>
                  <div class="w-full inline-block text-center py-2.5 px-4 rounded-xl font-bold text-sm mb-2" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5">
                    ❌ تم رفض إشعار الدفع — يرجى إعادة الإرسال أو التواصل مع الإدارة
                  </div>
                  <button type="button" class="w-full inline-block text-center py-2.5 px-4 rounded-xl font-bold text-white text-sm shadow-sm transition hover:scale-[1.02]" style="background: <?= $color ?>;" onclick="openPayModal(<?= (int)$pkg['id'] ?>, '<?= e($pkg['name']) ?>', <?= (float)$pkg['price'] ?>)">
                    إعادة إرسال إشعار الدفع 🚀
                  </button>
                <?php else: ?>
                  <button type="button" class="w-full inline-block text-center py-2.5 px-4 rounded-xl font-bold text-white text-sm shadow-sm transition hover:scale-[1.02]" style="background: <?= $color ?>;" onclick="openPayModal(<?= (int)$pkg['id'] ?>, '<?= e($pkg['name']) ?>', <?= (float)$pkg['price'] ?>)">
                    اشترك بالباقة الآن 🚀
                  </button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

  <!-- مودال خطوة الدفع للاشتراك بالباقة -->
  <div class="modal-overlay" id="pkgPayModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1000;justify-content:center;align-items:center;backdrop-filter:blur(4px)">
    <div style="background:var(--bg-card);border-radius:20px;max-width:560px;width:94%;max-height:90vh;overflow-y:auto;box-shadow:0 25px 50px -12px rgba(0,0,0,.3);border:1px solid var(--border-color)">
      <div style="padding:20px 24px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center">
        <h3 style="font-size:18px;font-weight:800">💳 الدفع والاشتراك بالباقة</h3>
        <button type="button" onclick="closePayModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted)">✕</button>
      </div>
      <form method="POST" action="packages.php" enctype="multipart/form-data">
        <div style="padding:24px">
          <input type="hidden" name="subscribe_package_id" id="payPkgId" />
          <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:14px;text-align:center;margin-bottom:18px">
            <span style="font-size:12px;color:#64748b;font-weight:700;display:block">الباقة</span>
            <strong id="payPkgName" style="font-size:16px"></strong>
            <div style="font-size:24px;font-weight:900;color:#4f46e5;margin-top:4px" id="payPkgAmount">0.00 ر.س</div>
          </div>

          <label style="display:block;font-size:13px;font-weight:800;margin-bottom:8px">اختر حساب المنصة الذي حوّلت إليه <span style="color:var(--danger)">*</span></label>
          <?php if (empty($paymentMethods)): ?>
            <div style="background:#fef3c7;color:#92400e;padding:12px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:12px">
              لا توجد حسابات دفع مفعّلة حالياً — سيتم التواصل معك لإتمام الدفع.
            </div>
          <?php else: ?>
            <div style="display:grid;gap:10px;margin-bottom:18px">
              <?php foreach ($paymentMethods as $pm): ?>
                <label style="display:flex;align-items:center;gap:12px;border:2px solid var(--border-color);border-radius:14px;padding:12px;cursor:pointer">
                  <input type="radio" name="payment_method_id" value="<?= (int)$pm['id'] ?>" style="accent-color:#4f46e5;width:18px;height:18px;flex-shrink:0" required>
                  <?php if (!empty($pm['logo'])): ?>
                    <img src="<?= e($appBase . '/' . $pm['logo']) ?>" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:8px;border:1px solid var(--border-color);flex-shrink:0">
                  <?php else: ?>
                    <span style="font-size:24px"><?= ($pm['account_type'] ?? 'bank') === 'wallet' ? '👛' : '🏦' ?></span>
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

          <label style="display:block;font-size:13px;font-weight:800;margin-bottom:8px">إرفاق إشعار الدفع (صورة أو PDF)</label>
          <input type="file" name="payment_receipt" class="form-input" accept="image/*,.pdf" style="margin-bottom:8px" required>
          <p style="font-size:12px;color:var(--text-secondary);line-height:1.6;margin-bottom:0">
            حوّل مبلغ الباقة إلى أحد حسابات المنصة أعلاه، ثم أرفق صورة/ملف إشعار التحويل. سيراجع فريق الإدارة الإشعار ويفعّل باقتك.
          </p>
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px">
          <button type="button" onclick="closePayModal()" style="padding:10px 18px;border-radius:12px;border:1px solid var(--border-color);background:var(--bg-card);font-weight:700;cursor:pointer">إلغاء</button>
          <button type="submit" style="padding:10px 22px;border-radius:12px;border:none;background:#4f46e5;color:#fff;font-weight:800;cursor:pointer">إتمام الاشتراك 🚀</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openPayModal(pkgId, pkgName, pkgPrice) {
      document.getElementById('payPkgId').value = pkgId;
      document.getElementById('payPkgName').textContent = pkgName;
      document.getElementById('payPkgAmount').textContent = Number(pkgPrice).toFixed(2) + ' ر.س';
      const m = document.getElementById('pkgPayModal');
      m.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
    function closePayModal() {
      document.getElementById('pkgPayModal').style.display = 'none';
      document.body.style.overflow = '';
    }
    document.getElementById('pkgPayModal').addEventListener('click', function(e) {
      if (e.target === this) closePayModal();
    });
  </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
