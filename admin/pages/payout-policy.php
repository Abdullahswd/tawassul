<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $min_withdrawal = $_POST['min_withdrawal'] ?? '200';
    $withdrawal_eligibility_days = $_POST['withdrawal_eligibility_days'] ?? '14';
    $payout_processing_time = $_POST['payout_processing_time'] ?? '3';

    // Insert or update settings
    $settings = [
        'min_withdrawal' => $min_withdrawal,
        'withdrawal_eligibility_days' => $withdrawal_eligibility_days,
        'payout_processing_time' => $payout_processing_time
    ];

    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value, $value]);
    }

    $success_msg = "تم حفظ سياسة الدفع بنجاح.";
}

// Fetch current settings
$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

function getSetting($settings, $key, $default = '') {
    return isset($settings[$key]) ? $settings[$key] : $default;
}

$min_withdrawal = getSetting($settings, 'min_withdrawal', '200');
$withdrawal_eligibility_days = getSetting($settings, 'withdrawal_eligibility_days', '14');
$payout_processing_time = getSetting($settings, 'payout_processing_time', '3');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>سياسة دفع الأكاديميين - Eduroad Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
  <div class="mobile-overlay" id="mobileOverlay"></div>
  <div class="admin-layout">

    <?php include '../components/sidebar.php'; ?>

    <div class="main-content" id="mainContent">
      <?php include '../components/navbar.php'; ?>

      <div class="page-content">
        <div class="page-header animate-fadeInUp">
          <div>
            <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>سياسة دفع الأكاديميين</span></div>
            <h1 class="page-header-title">سياسة دفع الأكاديميين</h1>
            <p class="page-header-subtitle">التحكم في شروط وقواعد سحب الأرباح الخاصة بالأكاديميين</p>
          </div>
        </div>

        <?php if (!empty($success_msg)): ?>
          <div class="alert alert-success mb-6 animate-fadeInUp">
            <?= htmlspecialchars($success_msg) ?>
          </div>
        <?php endif; ?>

        <div class="stat-card animate-fadeInUp" style="padding: 24px; display: block;">
          <form method="POST" action="payout-policy.php">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              
              <!-- a. الحد الأدنى للسحب -->
              <div class="form-group">
                <label class="form-label" style="font-weight: 700; color: var(--text-primary);">الحد الأدنى للسحب (ر.س)</label>
                <div class="text-xs text-slate-500 mb-2">أقل مبلغ يمكن للأكاديمي سحبه من رصيده المتاح.</div>
                <input type="number" name="min_withdrawal" class="form-input" value="<?= htmlspecialchars($min_withdrawal) ?>" required min="0" step="1" />
              </div>

              <!-- b. يحق له السحب بعد مدة -->
              <div class="form-group">
                <label class="form-label" style="font-weight: 700; color: var(--text-primary);">فترة تعليق الأرباح (أيام)</label>
                <div class="text-xs text-slate-500 mb-2">عدد الأيام المطلوبة بعد اكتمال الطلب حتى يصبح الرصيد قابلاً للسحب.</div>
                <input type="number" name="withdrawal_eligibility_days" class="form-input" value="<?= htmlspecialchars($withdrawal_eligibility_days) ?>" required min="0" step="1" />
              </div>

              <!-- c. تحديد زمن الدفع -->
              <div class="form-group">
                <label class="form-label" style="font-weight: 700; color: var(--text-primary);">زمن تنفيذ طلبات السحب (أيام عمل)</label>
                <div class="text-xs text-slate-500 mb-2">المدة الزمنية المتوقعة لتحويل المبلغ للأكاديمي بعد طلبه للسحب.</div>
                <input type="number" name="payout_processing_time" class="form-input" value="<?= htmlspecialchars($payout_processing_time) ?>" required min="0" step="1" />
              </div>

            </div>

            <div class="form-actions" style="border-top: 1px solid var(--border-color); padding-top: 20px; text-align: left;">
              <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">💾 حفظ السياسة</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <script src="../assets/js/main.js"></script>
</body>
</html>
