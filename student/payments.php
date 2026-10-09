  <?php
  require_once __DIR__ . '/../config/auth.php';
  require_once __DIR__ . '/../config/functions.php';
  requireStudent();

  $user = currentUser();
  $db = db();

  // Payment handling has been removed as it relies on receipt uploads during order creation
  $paid_success = isset($_GET['paid']);

  // إحصائيات مالية
  $total_paid = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM payment_receipts WHERE student_id = " . $user['id'] . " AND status = 'approved'")->fetchColumn();
  $total_pending = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM payment_receipts WHERE student_id = " . $user['id'] . " AND status = 'pending'")->fetchColumn();

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reupload_receipt_from_payments'])) {
      $receipt_id = (int)$_POST['receipt_id'];
      $pay_method = (int)($_POST['pay_method'] ?? 0);
      $msg = trim($_POST['reupload_message'] ?? '');
      
      $stmt = $db->prepare("SELECT id, order_id, subscription_id, receipt_path, method_id, method_label FROM payment_receipts WHERE id = ? AND student_id = ?");
      $stmt->execute([$receipt_id, $user['id']]);
      $rc = $stmt->fetch();
      
      if ($rc) {
          $fileName = $rc['receipt_path'];
          $methodId = $rc['method_id'];
          $methodLabel = $rc['method_label'];
          
          if ($pay_method > 0) {
              $methodId = $pay_method;
              $methodLabel = getPaymentMethodLabel($pay_method);
          }
          
          if (isset($_FILES['new_receipt']) && $_FILES['new_receipt']['error'] === UPLOAD_ERR_OK) {
              $fileExt = strtolower(pathinfo($_FILES['new_receipt']['name'], PATHINFO_EXTENSION));
              if (in_array($fileExt, ['jpg','jpeg','png','pdf'])) {
                  $newFile = uniqid('receipt_', true) . '.' . $fileExt;
                  if (move_uploaded_file($_FILES['new_receipt']['tmp_name'], __DIR__ . '/../uploads/receipts/' . $newFile)) {
                      $fileName = 'uploads/receipts/' . $newFile;
                  }
              }
          }
          
          $db->prepare("UPDATE payment_receipts SET receipt_path = ?, method_id = ?, method_label = ?, status = 'pending', rejection_reason = NULL, student_note = ? WHERE id = ?")
            ->execute([$fileName, $methodId, $methodLabel, $msg, $receipt_id]);
          
          if ($msg !== '' && $rc['order_id']) {
              $convStmt = $db->prepare("SELECT id FROM conversations WHERE order_id = ? AND student_id = ? LIMIT 1");
              $convStmt->execute([$rc['order_id'], $user['id']]);
              $cId = (int)$convStmt->fetchColumn();
              if ($cId) {
                  sendMessage($cId, $user['id'], 'student', $msg, null);
              }
          }
          
          $ref = $rc['order_id'] ? "طلب" : "باقة";
          createNotification(1, 'admin', 'إعادة رفع أو تصحيح إيصال دفع 🧾', 'قام الطالب ' . $user['name'] . ' بإجراء تصحيح على إيصال دفع لـ ' . $ref, '🧾', 'admin/pages/payments.php#tab-students');
          header("Location: payments.php?updated=1");
          exit;
      }
  }

  $stmt = $db->prepare("
      SELECT pr.*, 
            o.order_number, s.name AS service_name,
            pkg.name AS package_name
      FROM payment_receipts pr
      LEFT JOIN orders o ON pr.order_id = o.id
      LEFT JOIN services s ON o.service_id = s.id
      LEFT JOIN package_subscriptions ps ON pr.subscription_id = ps.id
      LEFT JOIN packages pkg ON ps.package_id = pkg.id
      WHERE pr.student_id = ?
      ORDER BY pr.created_at DESC
  ");
  $stmt->execute([$user['id']]);
  $payments = $stmt->fetchAll();

  $extraCss = [
    '<style>
      .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 999; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
      .modal-overlay.active { display: flex; }
      .modal-box { background: var(--bg-card); border-radius: 20px; max-width: 520px; width: 94%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: modalPop 0.25s ease-out; border: 1px solid var(--border-color); }
      .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
      .modal-title { font-size: 18px; font-weight: 800; color: var(--text-primary); }
      .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-muted); }
      .modal-body { padding: 24px; }
      .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }

      .bank-card {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
        color: white;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 10px 25px rgba(49,46,129,0.25);
        position: relative;
        overflow: hidden;
      }
      .bank-card::after {
        content: "🏦";
        position: absolute;
        left: -10px;
        bottom: -10px;
        font-size: 100px;
        opacity: 0.1;
      }

      @keyframes modalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>',
  ];
  $pageTitle  = 'المدفوعات وبيانات المنصة';
  $activePage = 'payments';
  require __DIR__ . '/partials/head.php';
  require __DIR__ . '/partials/sidebar.php';
  ?>

        <!-- Page Content -->
        <div class="content-wrap" style="max-width:1150px;margin:0 auto;padding:24px;">
          
          <?php if ($paid_success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 p-4 rounded-xl mb-6 font-bold flex items-center justify-between shadow-sm">
              <span>✅ تم تسجيل إشعار الدفع بنجاح ورُفعت الفاتورة لحساب المنصة!</span>
              <span class="text-xs text-emerald-600">تظهر العملية فورياً لدى الإدارة</span>
            </div>
          <?php endif; ?>

          <div class="flex flex-wrap justify-between items-center gap-4 mb-8">
            <div>
              <h1 class="text-2xl font-black text-slate-900 mb-1">الفواتير وحسابات المنصة</h1>
              <p class="text-slate-500 text-sm">تابع عمليات سداد فواتيرك واطلع على بيانات الحساب البنكي الرسمي للمنصة.</p>
            </div>
            <!-- زر إظهار الحسابات البنكية للمنصة -->
            <button onclick="openPlatformBankModal()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm shadow-md transition flex items-center gap-2">
              <span>🏦</span>
              <span>بيانات الحساب البنكي للمنصة</span>
            </button>
          </div>

          <!-- بطاقات المؤشرات المالية -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
            <div class="card p-6 border border-slate-200 shadow-sm rounded-2xl bg-white">
              <div class="text-xs font-bold text-slate-500 mb-2">إجمالي المدفوعات المسددة</div>
              <div class="text-3xl font-black text-emerald-600"><?= formatMoney($total_paid) ?></div>
            </div>
            <div class="card p-6 border border-amber-200 bg-amber-50/50 shadow-sm rounded-2xl">
              <div class="text-xs font-bold text-amber-700 mb-2">مبالغ بانتظار الدفع</div>
              <div class="text-3xl font-black text-amber-600"><?= formatMoney($total_pending) ?></div>
            </div>
          </div>

          <!-- جدول الفواتير -->
          <div class="card p-0 overflow-hidden border border-slate-200 rounded-2xl bg-white shadow-sm">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
              <h3 class="font-extrabold text-slate-800 text-base">سجل الفواتير والعمليات المالية</h3>
              <span class="text-xs text-slate-400 font-bold">تزامن فوري مع إدارة المنصة</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-right border-collapse">
                <thead>
                  <tr class="bg-slate-50 text-slate-500 text-xs border-b border-slate-200">
                    <th class="p-4">رقم العملية</th>
                    <th class="p-4">الغرض (الطلب / الباقة)</th>
                    <th class="p-4">التاريخ</th>
                    <th class="p-4">المبلغ</th>
                    <th class="p-4">حساب التحويل</th>
                    <th class="p-4">الحالة</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                  <?php if (empty($payments)): ?>
                    <tr>
                      <td colspan="7" class="text-center p-8 text-slate-400">
                        لا توجد فواتير أو عمليات دفع مسجلة بعد.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($payments as $p): 
                      $inv_no = 'TRX-' . str_pad($p['id'], 6, '0', STR_PAD_LEFT);
                      $purpose = '';
                      if ($p['order_id']) {
                          $purpose = '<a href="order-details.php?id=' . $p['order_id'] . '" class="text-indigo-600 hover:underline">طلب: ' . e($p['service_name']) . '</a>';
                      } elseif ($p['subscription_id']) {
                          $purpose = '<span class="text-emerald-600">باقة: ' . e($p['package_name']) . '</span>';
                      } else {
                          $purpose = '<span class="text-slate-500">غير معروف</span>';
                      }
                    ?>
                      <tr class="hover:bg-slate-50/70 transition">
                        <td class="p-4 font-mono font-bold text-slate-800"><?= $inv_no ?></td>
                        <td class="p-4 font-bold text-sm"><?= $purpose ?></td>
                        <td class="p-4 text-slate-500 text-xs"><?= formatDate($p['created_at']) ?></td>
                        <td class="p-4 font-extrabold text-slate-900"><?= formatMoney($p['amount']) ?></td>
                        <td class="p-4 text-slate-600 text-xs">
                          <?= e($p['method_label']) ?: 'غير محدد' ?>
                        </td>
                        <td class="p-4">
                          <?php if ($p['status'] === 'pending'): ?>
                            <span class="text-xs text-amber-600 font-bold bg-amber-50 px-3 py-1 rounded-full border border-amber-200">⏳ قيد مراجعة الإيصال</span>
                          <?php elseif ($p['status'] === 'rejected'): ?>
                            <div class="flex items-center gap-2">
                              <span class="text-xs text-red-600 font-bold bg-red-50 px-3 py-1 rounded-full border border-red-200">❌ مرفوض</span>
                              <button onclick="openReuploadModal(<?= $p['id'] ?>, '<?= e(addslashes($p['rejection_reason'] ?: 'بدون سبب')) ?>')" class="text-xs bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-700">تصحيح ✏️</button>
                            </div>
                          <?php else: ?>
                            <span class="text-xs text-emerald-600 font-bold bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">✓ معتمدة</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </main>
    </div>

    <!-- مودال بيانات الدفع البنكي الرسمية للمنصة -->
    <div class="modal-overlay" id="platformBankModal">
      <div class="modal-box">
        <div class="modal-header">
          <h3 class="modal-title">🏦 الحساب البنكي الرسمي لمنصة تواصل</h3>
          <button class="modal-close" data-modal-close>✕</button>
        </div>
        <div class="modal-body space-y-4">
          <p class="text-xs text-slate-600 leading-relaxed">
            يمكنك إجراء التحويل المباشر لمبلغ الفاتورة إلى أحد حسابات المنصة الرسمية المعتمدة أدناه:
          </p>

          <!-- بطاقة البنك -->
          <div class="bank-card space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-indigo-200">الحساب البنكي والرئيسي</span>
              <span class="text-xs bg-indigo-500/40 text-white px-2 py-0.5 rounded-full font-bold">معتمد 🔒</span>
            </div>

            <div>
              <div class="text-xs text-indigo-300">اسم الحساب الرسمي:</div>
              <div class="text-base font-extrabold text-white">شركة Eduroad للخدمات الأكاديمية والمشاريع</div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-indigo-500/40 text-xs">
              <div>
                <div class="text-indigo-300">البنك:</div>
                <div class="font-bold text-white">مصرف الراجحي / البنك الأهلي</div>
              </div>
              <div>
                <div class="text-indigo-300">رقم الحساب:</div>
                <div class="font-mono font-bold text-white select-all">10023456789</div>
              </div>
            </div>

            <div class="pt-2 border-t border-indigo-500/40">
              <div class="text-xs text-indigo-300 mb-1">رقم الآيبان (IBAN):</div>
              <div class="flex items-center justify-between bg-black/20 p-2 rounded-xl">
                <span class="font-mono font-bold text-white text-xs select-all">SA0380000000000000000000</span>
                <button onclick="copyIban('SA0380000000000000000000')" class="text-xs bg-indigo-500 hover:bg-indigo-600 text-white px-2.5 py-1 rounded-lg font-bold">نسخ 📋</button>
              </div>
            </div>
          </div>

          <div class="bg-amber-50 p-3 rounded-xl border border-amber-200 text-xs text-amber-800 font-medium">
            💡 <strong>ملاحظة:</strong> يتم إرفاق إيصال التحويل البنكي مباشرة عند تقديم طلب الخدمة أو الاشتراك في الباقة ليتم توثيقه من قبل الإدارة.
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-add-sub" data-modal-close>إغلاق</button>
        </div>
      </div>
    </div>



    <!-- مودال إعادة الرفع -->
    <div class="modal-overlay" id="reuploadModal">
      <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
          <h3 class="modal-title">إعادة رفع إيصال الدفع</h3>
          <button class="modal-close" data-modal-close>✕</button>
        </div>
        <form method="POST" action="payments.php" enctype="multipart/form-data">
          <div class="modal-body space-y-4">
            <input type="hidden" name="reupload_receipt_from_payments" value="1" />
            <input type="hidden" name="receipt_id" id="modalReuploadId" />

            <div class="bg-red-50 p-3 rounded-xl border border-red-200">
              <span class="text-xs text-red-800 font-bold block mb-1">سبب الرفض:</span>
              <span class="text-sm text-red-600" id="modalReuploadReason"></span>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">حساب التحويل (فقط إذا أردت تعديله)</label>
              <select name="pay_method" class="w-full p-2.5 rounded-xl border border-slate-300 text-sm font-bold focus:outline-none focus:border-indigo-600">
                <option value="">-- الإبقاء على الحساب السابق --</option>
                <?php
                $platform_methods = $db->query("SELECT * FROM platform_payment_methods WHERE is_active = 1")->fetchAll();
                foreach ($platform_methods as $m):
                    $icon = $m['account_type'] === 'wallet' ? '👛' : '🏦';
                ?>
                  <option value="<?= $m['id'] ?>"><?= $icon . ' ' . e($m['account_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">الإيصال الجديد (فقط إذا أردت تعديله)</label>
              <input type="file" name="new_receipt" accept=".jpg,.jpeg,.png,.pdf" class="w-full p-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-indigo-600">
            </div>
            
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">ملاحظة للإدارة (اختياري)</label>
              <textarea name="reupload_message" rows="2" class="w-full p-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-indigo-600" placeholder="ملاحظات حول التحويل..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-add-sub" data-modal-close>إلغاء</button>
            <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700">إرسال 🚀</button>
          </div>
        </form>
      </div>
    </div>

  <?php
  $extraJs = ob_start() ? '' : '';
  ob_start();
  ?>
    <script>
      const Modal = {
        open: (id) => { document.getElementById(id).classList.add('active'); },
        close: (id) => { document.getElementById(id).classList.remove('active'); },
      };

      function openPlatformBankModal() {
        Modal.open('platformBankModal');
      }

      function openReuploadModal(id, reason) {
        document.getElementById('modalReuploadId').value = id;
        document.getElementById('modalReuploadReason').textContent = reason;
        Modal.open('reuploadModal');
      }



      function copyIban(ibanText) {
        navigator.clipboard.writeText(ibanText).then(() => {
          alert('تم نسخ رقم الآيبان للحافظة بنجاح!');
        });
      }

      document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-modal-close]').forEach(btn => {
          btn.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if (modal) Modal.close(modal.id);
          });
        });
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
          overlay.addEventListener('click', function(e) {
            if (e.target === this) Modal.close(this.id);
          });
        });
      });
    </script>
  <?php
  $extraJs = ob_get_clean();
  require __DIR__ . '/partials/footer.php';
  ?>
