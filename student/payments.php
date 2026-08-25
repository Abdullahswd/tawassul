<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db = db();

// معالجة الدفع وتسجيلها بحساب المنصة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $pay_method = trim($_POST['pay_method'] ?? 'bank_transfer');
    if ($payment_id) {
        $check = $db->prepare('SELECT id FROM payments WHERE id = ? AND student_id = ? AND status = ? LIMIT 1');
        $check->execute([$payment_id, $user['id'], 'pending']);
        if ($check->fetch()) {
            $upd = $db->prepare("UPDATE payments SET status = 'paid', method = ?, paid_at = NOW() WHERE id = ?");
            $upd->execute([$pay_method, $payment_id]);
            
            // إشعار الأدمن فورياً بتمام الدفع
            createNotification(1, 'admin', 'دفع جديد للمنصة ✅', 'قام الطالب ' . $user['name'] . ' بإتمام عملية الدفع بنجاح.', '💳', 'admin/pages/payments.php');
        }
    }
    header('Location: payments.php?paid=1');
    exit;
}

$paid_success = isset($_GET['paid']);

// إحصائيات مالية
$total_paid = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = " . $user['id'] . " AND status = 'paid'")->fetchColumn();
$total_pending = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = " . $user['id'] . " AND status = 'pending'")->fetchColumn();

// سجل الفواتير
$payments = getPaymentsByStudent($user['id']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>المدفوعات وبيانات المنصة - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    * { font-family: 'Tajawal', sans-serif; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 999; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
    .modal-overlay.active { display: flex; }
    .modal-box { background: white; border-radius: 20px; max-width: 520px; width: 94%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: modalPop 0.25s ease-out; }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 18px; font-weight: 800; color: #0f172a; }
    .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8; }
    .modal-body { padding: 24px; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; }

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
  </style>
</head>
<body>
  
  <div class="mobile-overlay" id="mobileOverlay"></div>

  <div class="app-container">
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="logo-icon">🎓</div>
        <div class="logo-text">تواصل</div>
      </div>
      
      <nav class="sidebar-nav">
        <div style="font-size:12px;color:var(--text-muted);font-weight:700;margin-bottom:8px;padding:0 8px">القائمة الرئيسية</div>
        <a href="student-dashboard.php" class="nav-item">
          <span class="icon">📊</span>
          <span>لوحة المعلومات</span>
        </a>
        <a href="services.php" class="nav-item">
          <span class="icon">📦</span>
          <span>الخدمات الأكاديمية</span>
        </a>
        <a href="packages.php" class="nav-item">
          <span class="icon">🎁</span>
          <span>الباقات المخصصة</span>
        </a>
        <a href="orders.php" class="nav-item">
          <span class="icon">📋</span>
          <span>طلباتي</span>
        </a>
        <a href="chat.php" class="nav-item">
          <span class="icon">💬</span>
          <span>المحادثات</span>
        </a>
        <a href="payments.php" class="nav-item active">
          <span class="icon">💳</span>
          <span>المدفوعات</span>
        </a>
        
        <div style="font-size:12px;color:var(--text-muted);font-weight:700;margin-top:24px;margin-bottom:8px;padding:0 8px">إعدادات الحساب</div>
        <a href="profile.php" class="nav-item">
          <span class="icon">👤</span>
          <span>الملف الشخصي</span>
        </a>
      </nav>
      
      <div style="padding:20px;border-top:1px solid var(--border-color)">
        <a href="../logout.php" class="nav-item" style="color:var(--danger)">
          <span class="icon">🚪</span>
          <span>تسجيل الخروج</span>
        </a>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-area">
      
      <!-- Top Navbar -->
      <header class="top-navbar">
        <div style="display:flex;align-items:center;gap:16px">
          <button class="menu-toggle" id="menuToggle">☰</button>
          <div class="h3">💳 الفواتير وبيانات دفع المنصة</div>
        </div>

        <div class="navbar-actions">
          <button class="icon-btn dark-toggle" aria-label="تبديل المظهر">🌙</button>
          <button class="icon-btn" aria-label="الإشعارات">
            🔔<span class="badge-dot"><?= countUnreadNotifications($user['id'], 'student') ?></span>
          </button>
          <div style="width:1px;height:30px;background:var(--border-color);margin:0 8px"></div>
          <div class="user-profile">
            <div class="user-info" style="text-align:left">
              <span class="user-name"><?= e($user['name']) ?></span>
              <span class="user-role">طالب</span>
            </div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
          </div>
        </div>
      </header>

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
                  <th class="p-4">رقم الفاتورة</th>
                  <th class="p-4">الطلب المرتبط</th>
                  <th class="p-4">التاريخ</th>
                  <th class="p-4">المبلغ</th>
                  <th class="p-4">طريقة السداد</th>
                  <th class="p-4">الحالة</th>
                  <th class="p-4">إجراءات</th>
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
                    $badge = paymentStatusLabel($p['status']);
                    $inv_no = 'INV-' . str_pad($p['id'], 6, '0', STR_PAD_LEFT);
                  ?>
                    <tr class="hover:bg-slate-50/70 transition">
                      <td class="p-4 font-mono font-bold text-slate-800"><?= $inv_no ?></td>
                      <td class="p-4 font-bold text-indigo-600">
                        <a href="order-details.php?id=<?= $p['order_id'] ?>">ORD-<?= str_pad($p['order_id'], 6, '0', STR_PAD_LEFT) ?></a>
                      </td>
                      <td class="p-4 text-slate-500 text-xs"><?= formatDate($p['created_at']) ?></td>
                      <td class="p-4 font-extrabold text-slate-900"><?= formatMoney($p['amount']) ?></td>
                      <td class="p-4 text-slate-600 text-xs">
                        <?= $p['method'] === 'bank_transfer' ? '🏦 تحويل بنكي للمنصة' : ($p['method'] === 'credit_card' ? '💳 بطاقة ائتمانية' : e($p['method'])) ?>
                      </td>
                      <td class="p-4">
                        <span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                      </td>
                      <td class="p-4">
                        <?php if ($p['status'] === 'pending'): ?>
                          <button onclick="openPayModal(<?= $p['id'] ?>, <?= $p['amount'] ?>)" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-700 transition">
                            💳 سداد وتثبيت الفاتورة
                          </button>
                        <?php else: ?>
                          <span class="text-xs text-emerald-600 font-bold">✓ مكتملة ومعتمدة</span>
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
            <div class="text-base font-extrabold text-white">شركة تواصل للخدمات الأكاديمية والمشاريع</div>
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
          💡 <strong>ملاحظة:</strong> بعد إتمام التحويل، يرجى النقر على زر "سداد وتثبيت الفاتورة" المقابل للفاتورة لإبلاغ الإدارة بتوثيق العملية.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-add-sub" data-modal-close>إغلاق</button>
      </div>
    </div>
  </div>

  <!-- مودال تنفيذ الدفع للفاتورة -->
  <div class="modal-overlay" id="payInvoiceModal">
    <div class="modal-box" style="max-width:440px;">
      <div class="modal-header">
        <h3 class="modal-title">تثبيت عملية الدفع للمنصة</h3>
        <button class="modal-close" data-modal-close>✕</button>
      </div>
      <form method="POST" action="payments.php">
        <div class="modal-body space-y-4">
          <input type="hidden" name="pay_now" value="1" />
          <input type="hidden" name="payment_id" id="modalPaymentId" />

          <div class="bg-indigo-50 p-4 rounded-xl text-center border border-indigo-100">
            <span class="text-xs text-slate-500 font-bold block mb-1">المبلغ المطلوب سداده</span>
            <span class="text-2xl font-black text-indigo-600" id="modalPaymentAmount">0.00 ر.س</span>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">اختر طريقة السداد المُستخدمة</label>
            <select name="pay_method" class="w-full p-2.5 rounded-xl border border-slate-300 text-sm font-bold focus:outline-none focus:border-indigo-600">
              <option value="bank_transfer">🏦 تحويل بنكي لحساب المنصة (آيبان)</option>
              <option value="credit_card">💳 بطاقة ائتمانية (مدى / فيزا / ماستر)</option>
              <option value="wallet">👛 المحفظة الإلكترونية</option>
            </select>
          </div>

          <div class="text-xs text-slate-500 leading-relaxed">
            عند النقر على تأكيد السداد، سيتم إرسال إشعار الدفع مباشرة للوحة تحكم إدارة المنصة لتفعيل الطلب.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-add-sub" data-modal-close>إلغاء</button>
          <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700">تأكيد السداد 🚀</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    const Modal = {
      open: (id) => { document.getElementById(id).classList.add('active'); },
      close: (id) => { document.getElementById(id).classList.remove('active'); },
    };

    function openPlatformBankModal() {
      Modal.open('platformBankModal');
    }

    function openPayModal(payId, amount) {
      document.getElementById('modalPaymentId').value = payId;
      document.getElementById('modalPaymentAmount').textContent = Number(amount).toFixed(2) + ' ر.س';
      Modal.open('payInvoiceModal');
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
</body>
</html>
