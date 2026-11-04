<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$db = db();

// جلب جميع الإعدادات من قاعدة البيانات
$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $settings[$row['setting_key']] = $row['setting_value'];
}

// جلب بيانات المدير من جدول users
$adminStmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = ? AND role = 'admin'");
$adminStmt->execute([$_SESSION['user_id']]);
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

// دالة مساعدة لاستخراج قيمة الإعداد مع قيمة افتراضية
function getSetting($settings, $key, $default = '')
{
  return isset($settings[$key]) ? $settings[$key] : $default;
}

// تعيين المتغيرات للاستخدام في HTML
$site_name = getSetting($settings, 'site_name', 'Eduroad');
$site_email = getSetting($settings, 'site_email', 'info@eduroad.com');
$site_phone = getSetting($settings, 'site_phone', '+966 11 000 0000');
$site_url = getSetting($settings, 'site_url', 'https://eduroad.com');
$site_language = getSetting($settings, 'site_language', 'ar');
$site_timezone = getSetting($settings, 'site_timezone', 'Asia/Riyadh');
$site_description = getSetting($settings, 'site_description', '');
$commission_percentage = getSetting($settings, 'commission_percentage', '15');
$min_withdrawal = getSetting($settings, 'min_withdrawal', '200');
$payout_cycle = getSetting($settings, 'payout_cycle', 'monthly');
$withdrawal_eligibility_days = getSetting($settings, 'withdrawal_eligibility_days', '14');
$auto_approve_hours = getSetting($settings, 'auto_approve_hours', '72');
$payout_processing_time = getSetting($settings, 'payout_processing_time', '3');
$maintenance_mode = getSetting($settings, 'maintenance_mode', '0');
$allow_registration = getSetting($settings, 'allow_registration', '1');
$email_verification = getSetting($settings, 'email_verification', '1');
$two_factor_auth = getSetting($settings, 'two_factor_auth', '1');
$session_logging = getSetting($settings, 'session_logging', '1');
$notify_new_order = getSetting($settings, 'notify_new_order', '1');
$notify_payment = getSetting($settings, 'notify_payment', '1');
$daily_summary_email = getSetting($settings, 'daily_summary_email', '0');
$notify_new_academic = getSetting($settings, 'notify_new_academic', '1');
$payment_stripe_enabled = getSetting($settings, 'payment_stripe_enabled', '1');
$payment_moyasar_enabled = getSetting($settings, 'payment_moyasar_enabled', '1');
$payment_paypal_enabled = getSetting($settings, 'payment_paypal_enabled', '0');
$primary_color = getSetting($settings, 'primary_color', '#6366f1');
$theme_mode = getSetting($settings, 'theme_mode', 'light');
$smtp_host = getSetting($settings, 'smtp_host', 'smtp.gmail.com');
$smtp_port = getSetting($settings, 'smtp_port', '587');
$smtp_encryption = getSetting($settings, 'smtp_encryption', 'tls');
$smtp_user = getSetting($settings, 'smtp_user', 'noreply@eduroad.com');
$smtp_password = getSetting($settings, 'smtp_password', '');

$admin_name = $admin['name'] ?? 'المدير العام';
$admin_email = $admin['email'] ?? 'admin@eduroad.com';
$admin_phone = $admin['phone'] ?? '+966 5X XXX XXXX';
$site_logo = getSetting($settings, 'site_logo', '');

// إحصائيات عمولة الخدمات الحالية
$commFloat = (float)$commission_percentage;
$totalServicesCount = (int)$db->query("SELECT COUNT(*) FROM services WHERE level = 3")->fetchColumn();
$zeroCommissionCount = (int)$db->query("SELECT COUNT(*) FROM services WHERE level = 3 AND (platform_percent = 0 OR platform_percent IS NULL)")->fetchColumn();
$matchStmt = $db->prepare("SELECT COUNT(*) FROM services WHERE level = 3 AND ABS(platform_percent - ?) < 0.01");
$matchStmt->execute([$commFloat]);
$matchCommissionCount = (int)$matchStmt->fetchColumn();
$customStmt = $db->prepare("SELECT COUNT(*) FROM services WHERE level = 3 AND platform_percent > 0 AND ABS(platform_percent - ?) >= 0.01");
$customStmt->execute([$commFloat]);
$customCommissionCount = (int)$customStmt->fetchColumn();

// Platform payment methods (طرق الدفع)
$paymentMethods = $db->query("SELECT * FROM platform_payment_methods ORDER BY sort_order ASC, id ASC")->fetchAll();
$pmTypeLabels = ['bank' => '🏦 حساب بنكي', 'wallet' => '👛 محفظة إلكترونية'];

// المسار الأساسي للتطبيق (جذر المشروع) لبناء روابط الشعارات من أي مجلد
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptName = preg_replace('#/admin/pages/[^/]+\.php$#', '', $scriptName);
$appBaseUrl = rtrim($scriptName, '/');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>الإعدادات - Eduroad Admin</title>
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
            <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><span>الإعدادات</span></div>
            <h1 class="page-header-title">إعدادات النظام</h1>
            <p class="page-header-subtitle">إدارة إعدادات المنصة والتكوينات العامة</p>
          </div>
          <button class="btn btn-primary" id="saveAllBtn" onclick="saveAllSettings()">💾 حفظ التغييرات</button>
        </div>

        <div class="settings-grid">

          <!-- Sidebar Menu -->
          <div class="stat-card animate-fadeInUp delay-1" style="padding:12px;height:fit-content">
            <div id="settingsMenu">
              <button class="settings-menu-btn active" onclick="showSection('general',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:var(--primary);color:white;font-family:Tajawal,sans-serif;font-size:14px;font-weight:600;cursor:pointer;margin-bottom:4px;text-align:right">🌐 الإعدادات العامة</button>
              <button class="settings-menu-btn" onclick="showSection('commission',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">💰 عمولة المنصة من الخدمات</button>
              <button class="settings-menu-btn" onclick="showSection('appearance',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">🎨 المظهر والتخصيص</button>
              <button class="settings-menu-btn" onclick="showSection('email',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">📧 إعدادات البريد (SMTP)</button>
              <button class="settings-menu-btn" onclick="showSection('payment',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">💳 طرق الدفع</button>
              <button class="settings-menu-btn" onclick="showSection('notifications',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">🔔 الإشعارات</button>
              <button class="settings-menu-btn" onclick="showSection('security',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">🔒 الأمان</button>
              <button class="settings-menu-btn" onclick="showSection('profile',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">👤 الملف الشخصي</button>
            </div>
          </div>

          <!-- Settings Content -->
          <div>

            <!-- General Settings -->
            <div id="section-general" class="settings-section animate-fadeInUp delay-2">
              <div class="stat-card" style="padding:24px;margin-bottom:20px">
                <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:4px">🌐 الإعدادات العامة</h3>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px">إعدادات المنصة الأساسية</p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                  <div class="form-group"><label class="form-label">اسم المنصة</label><input class="form-input" id="site_name" value="<?= htmlspecialchars($site_name) ?>" /></div>
                  <div class="form-group"><label class="form-label">البريد الرسمي</label><input class="form-input" type="email" id="site_email" value="<?= htmlspecialchars($site_email) ?>" /></div>
                  <div class="form-group"><label class="form-label">رقم التواصل</label><input class="form-input" id="site_phone" value="<?= htmlspecialchars($site_phone) ?>" /></div>
                  <div class="form-group"><label class="form-label">الموقع الإلكتروني</label><input class="form-input" id="site_url" value="<?= htmlspecialchars($site_url) ?>" /></div>
                  <div class="form-group"><label class="form-label">اللغة الافتراضية</label>
                    <select class="form-input form-select" id="site_language" style="padding-left:36px">
                      <option value="ar" <?= $site_language == 'ar' ? 'selected' : '' ?>>العربية</option>
                      <option value="en" <?= $site_language == 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                  </div>
                  <div class="form-group"><label class="form-label">المنطقة الزمنية</label>
                    <select class="form-input form-select" id="site_timezone" style="padding-left:36px">
                      <option value="Asia/Riyadh" <?= $site_timezone == 'Asia/Riyadh' ? 'selected' : '' ?>>توقيت الرياض (GMT+3)</option>
                      <option value="Asia/Dubai" <?= $site_timezone == 'Asia/Dubai' ? 'selected' : '' ?>>توقيت دبي (GMT+4)</option>
                    </select>
                  </div>
                </div>

                <div class="form-group"><label class="form-label">نبذة عن المنصة</label>
                  <textarea class="form-input" id="site_description" rows="3"><?= htmlspecialchars($site_description) ?></textarea>
                </div>

                <!-- Logo Upload -->
                <div class="form-group">
                  <label class="form-label">شعار المنصة (Logo)</label>
                  <div style="display:flex;align-items:center;gap:20px;margin-top:8px">
                    <div id="siteLogoPreviewBox" style="width:80px;height:80px;border-radius:16px;background:var(--bg-main);border:2px dashed var(--border-color);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
                      <?php if (!empty($site_logo)): ?>
                        <img id="siteLogoImg" src="<?= htmlspecialchars($appBaseUrl . '/' . ltrim($site_logo, '/')) ?>" alt="الشعار" style="width:100%;height:100%;object-fit:contain;padding:6px" />
                      <?php else: ?>
                        <div id="siteLogoFallback" style="font-size:36px">🎓</div>
                      <?php endif; ?>
                    </div>
                    <div>
                      <input type="file" id="siteLogoInput" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="display:none" onchange="uploadSiteLogo(this)" />
                      <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('siteLogoInput').click()">📤 رفع شعار جديد</button>
                      <p style="font-size:12px;color:var(--text-secondary);margin-top:6px">PNG أو SVG أو JPG، بحد أقصى 3MB</p>
                    </div>
                  </div>
                </div>

                <!-- Quick Toggles -->
                <div style="border: 1px solid var(--border-color);border-radius:14px;overflow:hidden;margin-top:16px;margin-bottom:20px">
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border-color)">
                    <div>
                      <div style="font-size:14px;font-weight:600;color:var(--text-primary)">وضع الصيانة</div>
                      <div style="font-size:12px;color:var(--text-secondary)">إيقاف الموقع مؤقتاً للصيانة</div>
                    </div>
                    <label class="toggle-switch"><input type="checkbox" id="maintenance_mode" <?= $maintenance_mode == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border-color)">
                    <div>
                      <div style="font-size:14px;font-weight:600;color:var(--text-primary)">قبول التسجيلات الجديدة</div>
                      <div style="font-size:12px;color:var(--text-secondary)">السماح للمستخدمين الجدد بالتسجيل</div>
                    </div>
                    <label class="toggle-switch"><input type="checkbox" id="allow_registration" <?= $allow_registration == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px">
                    <div>
                      <div style="font-size:14px;font-weight:600;color:var(--text-primary)">التحقق عبر البريد</div>
                      <div style="font-size:12px;color:var(--text-secondary)">إلزام المستخدمين بتأكيد بريدهم</div>
                    </div>
                    <label class="toggle-switch"><input type="checkbox" id="email_verification" <?= $email_verification == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                  </div>
                </div>

                <button class="btn btn-primary" onclick="saveAllSettings()" type="button">💾 حفظ الإعدادات العامة</button>
              </div>
            </div>

            <!-- Commission Section (قسم نسبة العمولة والمالية) -->
            <div id="section-commission" class="settings-section" style="display:none">
              <div class="stat-card animate-fadeInUp" style="padding:24px;margin-bottom:20px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
                  <div>
                    <h3 style="font-size:18px;font-weight:700;color:var(--text-primary);margin-bottom:4px">💰 نسبة عمولة المنصة والسياسة المالية</h3>
                    <p style="font-size:13px;color:var(--text-secondary)">تحديد نسبة عمولة المنصة الافتراضية والتحكم في تطبيقها ومزامنتها مع الخدمات تلقائياً.</p>
                  </div>
                  <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(16,185,129,0.1);color:#10b981;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700">
                    <span style="width:8px;height:8px;border-radius:50%;background:#10b981"></span>
                    مزامنة ذكية مفعلة
                  </span>
                </div>

                <!-- Live Stat Cards -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));gap:14px;margin-bottom:24px">
                  <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:12px;padding:16px">
                    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">📦 إجمالي الخدمات النشطة</div>
                    <div style="font-size:22px;font-weight:800;color:var(--text-primary)"><span id="statTotalServices"><?= $totalServicesCount ?></span> <span style="font-size:13px;font-weight:500;color:var(--text-secondary)">خدمة</span></div>
                  </div>
                  <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:12px;padding:16px">
                    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">✅ تطبق العمولة العامة (<?= $commFloat ?>%)</div>
                    <div style="font-size:22px;font-weight:800;color:var(--primary)"><span id="statMatchCommission"><?= $matchCommissionCount ?></span> <span style="font-size:13px;font-weight:500;color:var(--text-secondary)">خدمة</span></div>
                  </div>
                  <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:12px;padding:16px">
                    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">⭐ عمولة مخصصة (مستثناة)</div>
                    <div style="font-size:22px;font-weight:800;color:#f59e0b"><span id="statCustomCommission"><?= $customCommissionCount ?></span> <span style="font-size:13px;font-weight:500;color:var(--text-secondary)">خدمة</span></div>
                  </div>
                  <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:12px;padding:16px">
                    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">⚠️ عمولة غير محددة (0%)</div>
                    <div style="font-size:22px;font-weight:800;color:<?= $zeroCommissionCount > 0 ? '#ef4444' : '#10b981' ?>"><span id="statZeroCommission"><?= $zeroCommissionCount ?></span> <span style="font-size:13px;font-weight:500;color:var(--text-secondary)">خدمة</span></div>
                  </div>
                </div>

                <!-- Synchronization Rule Banner -->
                <div style="background:rgba(99,102,241,0.06);border:1.5px solid rgba(99,102,241,0.25);border-radius:14px;padding:18px 20px;margin-bottom:24px">
                  <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <span style="font-size:20px">⚡</span>
                    <h4 style="font-size:14px;font-weight:700;color:var(--text-primary);margin:0">قاعدة المزامنة الذكية عند التعديل:</h4>
                  </div>
                  <ul style="margin:0;padding-right:24px;font-size:13px;color:var(--text-secondary);line-height:1.8">
                    <li><strong>الخدمات الصفرية:</strong> أي خدمة عمولتها الحالية (0%) أو غير محددة سيتم إسناد هذه النسبة إليها مباشرة عند الحفظ.</li>
                    <li><strong>استثناء الخدمات المخصصة:</strong> أي خدمة تم تحديد عمولة خاصة لها مسبقاً في صفحة الخدمات يتم <strong>استثناؤها فوراً</strong> وحفظ قيمتها دون تعديل.</li>
                    <li><strong>تحديث الخدمات المطابقة:</strong> عند تعديل النسبة هنا، يقوم النظام بالبحث في كل الخدمات، وأي خدمة كانت عمولتها تساوي النسبة السابقة تُحدث فوراً إلى النسبة الجديدة.</li>
                  </ul>
                </div>

                <div style="margin-bottom:24px">
                  <div class="form-group" style="max-width:380px">
                    <label class="form-label" style="font-weight:700">نسبة عمولة المنصة الافتراضية (%)</label>
                    <div style="display:flex;align-items:center;gap:10px">
                      <input class="form-input" type="number" id="commission_percentage" value="<?= htmlspecialchars($commission_percentage) ?>" step="0.5" min="0" max="100" style="font-size:18px;font-weight:700" />
                      <span style="font-weight:700;color:var(--text-secondary);font-size:16px">%</span>
                    </div>
                    <div style="font-size:12px;color:var(--text-secondary);margin-top:6px">تُقتطع هذه النسبة من قيمة الخدمات وتُسند للخدمات غير المخصصة.</div>
                  </div>
                </div>

                <!-- سياسة الاعتماد التلقائي وأرباح وسحب الأكاديميين -->
                <div style="border-top:1px solid var(--border-color);padding-top:24px;margin-top:20px;margin-bottom:24px">
                  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
                    <span style="font-size:22px">⚙️</span>
                    <div>
                      <h4 style="font-size:16px;font-weight:800;color:var(--text-primary);margin:0">سياسة تسليم الطلبات وسحب أرباح الأكاديميين</h4>
                      <p style="font-size:12px;color:var(--text-secondary);margin:0">التحكم الكامل في مهلة الاعتماد التلقائي وفترة تجميد الأرباح للأمان والحد الأدنى للسحب</p>
                    </div>
                  </div>

                  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;margin-bottom:20px">
                    
                    <!-- 1. مهلة الاعتماد التلقائي بالساعات -->
                    <div style="background:rgba(99,102,241,0.04);border:1.5px solid rgba(99,102,241,0.25);padding:18px;border-radius:14px">
                      <label class="form-label" style="font-weight:700;display:flex;align-items:center;gap:6px;color:var(--text-primary)">
                        <span>⏱️</span> مهلة الاعتماد التلقائي للطلبات المسلمة
                      </label>
                      <div style="display:flex;align-items:center;gap:10px">
                        <input class="form-input" type="number" id="auto_approve_hours" value="<?= htmlspecialchars($auto_approve_hours) ?>" min="1" step="1" style="font-size:16px;font-weight:700" />
                        <span style="font-weight:700;color:var(--text-secondary);white-space:nowrap">ساعة</span>
                      </div>
                      <div style="font-size:11.5px;color:var(--text-secondary);margin-top:6px;line-height:1.5">
                        المهلة الممنوحة للطالب لمراجعة العمل بعد تسليمه؛ عند انقضائها دون رد يعتمد النظام الطلب تلقائياً وتتحول المستحقات للأكاديمي.
                      </div>
                    </div>

                    <!-- 2. فترة تعليق الأرباح بالأيام -->
                    <div style="background:rgba(245,158,11,0.04);border:1.5px solid rgba(245,158,11,0.25);padding:18px;border-radius:14px">
                      <label class="form-label" style="font-weight:700;display:flex;align-items:center;gap:6px;color:var(--text-primary)">
                        <span>⏳</span> فترة تجميد الأرباح قبل السحب
                      </label>
                      <div style="display:flex;align-items:center;gap:10px">
                        <input class="form-input" type="number" id="withdrawal_eligibility_days" value="<?= htmlspecialchars($withdrawal_eligibility_days) ?>" min="0" step="1" style="font-size:16px;font-weight:700" />
                        <span style="font-weight:700;color:var(--text-secondary);white-space:nowrap">يوم</span>
                      </div>
                      <div style="font-size:11.5px;color:var(--text-secondary);margin-top:6px;line-height:1.5">
                        عدد الأيام التي تبقى فيها أرباح الأكاديمي معلقة بعد إكمال الطلب كفترة أمان للمنصة ضد النزاعات، وتصبح قابلة للسحب بعدها.
                      </div>
                    </div>

                    <!-- 3. الحد الأدنى للسحب -->
                    <div style="background:var(--bg-main);border:1px solid var(--border-color);padding:18px;border-radius:14px">
                      <label class="form-label" style="font-weight:700;display:flex;align-items:center;gap:6px;color:var(--text-primary)">
                        <span>💰</span> الحد الأدنى للسحب البنكي
                      </label>
                      <div style="display:flex;align-items:center;gap:10px">
                        <input class="form-input" type="number" id="min_withdrawal" value="<?= htmlspecialchars($min_withdrawal) ?>" min="1" step="1" style="font-size:16px;font-weight:700" />
                        <span style="font-weight:700;color:var(--text-secondary);white-space:nowrap">ر.س</span>
                      </div>
                      <div style="font-size:11.5px;color:var(--text-secondary);margin-top:6px;line-height:1.5">
                        أقل مبلغ مؤهل يمكن للأكاديمي تقديم طلب سحب بنكي له من رصيده المتاح.
                      </div>
                    </div>

                    <!-- 4. زمن معالجة التحويل البنكي -->
                    <div style="background:var(--bg-main);border:1px solid var(--border-color);padding:18px;border-radius:14px">
                      <label class="form-label" style="font-weight:700;display:flex;align-items:center;gap:6px;color:var(--text-primary)">
                        <span>🏦</span> زمن تنفيذ السحب البنكي
                      </label>
                      <div style="display:flex;align-items:center;gap:10px">
                        <input class="form-input" type="number" id="payout_processing_time" value="<?= htmlspecialchars($payout_processing_time) ?>" min="1" step="1" style="font-size:16px;font-weight:700" />
                        <span style="font-weight:700;color:var(--text-secondary);white-space:nowrap">أيام عمل</span>
                      </div>
                      <div style="font-size:11.5px;color:var(--text-secondary);margin-top:6px;line-height:1.5">
                        المهلة الزمنية المتوقعة لتحويل المبلغ من المنصة لحساب الأكاديمي بعد طلبه.
                      </div>
                    </div>

                  </div>
                </div>

                <!-- Direct link to Payout Policy -->
                <div style="background:var(--bg-main);border:1px dashed var(--border-color);border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:24px">
                  <div style="display:flex;align-items:center;gap:12px">
                    <span style="font-size:26px">💼</span>
                    <div>
                      <div style="font-size:14px;font-weight:700;color:var(--text-primary)">صفحة سياسة دفع الأكاديميين المنفصلة</div>
                      <div style="font-size:12px;color:var(--text-secondary)">يمكنك أيضاً مراجعة وتعديل هذه الشروط مباشرة عبر الصفحة المخصصة لها.</div>
                    </div>
                  </div>
                  <a href="payout-policy.php" class="btn btn-outline btn-sm" style="display:inline-flex;align-items:center;gap:6px">
                    ⚙️ فتح صفحة سياسة الدفع
                  </a>
                </div>

                <button class="btn btn-primary" onclick="saveAllSettings()" type="button">💾 حفظ وتطبيق جميع الإعدادات والسياسات المالية</button>
              </div>
            </div>

            <!-- Profile Section -->
            <div id="section-profile" class="settings-section" style="display:none">
              <div class="stat-card animate-fadeInUp" style="padding:24px">
                <h3 style="font-size:17px;font-weight:700;margin-bottom:20px;color:var(--text-primary)">👤 الملف الشخصي للمدير</h3>
                <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;padding:20px;background:var(--bg-main);border-radius:14px">
                  <div class="admin-avatar" style="width:72px;height:72px;border-radius:18px;font-size:28px">أ</div>
                  <div>
                    <div style="font-size:18px;font-weight:700;color:var(--text-primary)" id="adminNameDisplay"><?= htmlspecialchars($admin_name) ?></div>
                    <div style="font-size:14px;color:var(--text-secondary)" id="adminEmailDisplay"><?= htmlspecialchars($admin_email) ?></div>
                    <input type="file" id="adminAvatarInput" accept="image/*" style="display:none" onchange="uploadAdminAvatar(this)" />
                    <button class="btn btn-outline btn-sm" style="margin-top:8px" onclick="document.getElementById('adminAvatarInput').click()">تغيير الصورة</button>
                  </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                  <div class="form-group"><label class="form-label">الاسم الكامل</label><input class="form-input" id="admin_name" value="<?= htmlspecialchars($admin_name) ?>" /></div>
                  <div class="form-group"><label class="form-label">البريد الإلكتروني</label><input class="form-input" type="email" id="admin_email" value="<?= htmlspecialchars($admin_email) ?>" /></div>
                  <div class="form-group"><label class="form-label">رقم الهاتف</label><input class="form-input" id="admin_phone" value="<?= htmlspecialchars($admin_phone) ?>" /></div>
                  <div class="form-group"><label class="form-label">الدور الوظيفي</label><input class="form-input" value="مدير النظام" readonly style="opacity:0.6" /></div>
                </div>
                <button class="btn btn-primary" style="margin-top:16px" onclick="updateProfile()">تحديث الملف الشخصي</button>
              </div>
            </div>

            <!-- Security Section -->
            <div id="section-security" class="settings-section" style="display:none">
              <div class="stat-card animate-fadeInUp" style="padding:24px;margin-bottom:20px">
                <h3 style="font-size:17px;font-weight:700;margin-bottom:20px;color:var(--text-primary)">🔒 تغيير كلمة المرور</h3>
                <div class="form-group"><label class="form-label">كلمة المرور الحالية</label><input class="form-input" type="password" id="current_password" placeholder="••••••••" /></div>
                <div class="form-group"><label class="form-label">كلمة المرور الجديدة</label><input class="form-input" type="password" id="new_password" placeholder="••••••••" /></div>
                <div class="form-group"><label class="form-label">تأكيد كلمة المرور</label><input class="form-input" type="password" id="confirm_password" placeholder="••••••••" /></div>
                <button class="btn btn-primary" onclick="changePassword()">تحديث كلمة المرور</button>
              </div>
              <div class="stat-card animate-fadeInUp" style="padding:24px">
                <h3 style="font-size:17px;font-weight:700;margin-bottom:20px;color:var(--text-primary)">🛡 خيارات الأمان</h3>
                <div style="border:1px solid var(--border-color);border-radius:14px;overflow:hidden">
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border-color)">
                    <div>
                      <div style="font-size:14px;font-weight:600;color:var(--text-primary)">التحقق بخطوتين (2FA)</div>
                      <div style="font-size:12px;color:var(--text-secondary)">تأمين إضافي عند تسجيل الدخول</div>
                    </div>
                    <label class="toggle-switch"><input type="checkbox" id="two_factor_auth" <?= $two_factor_auth == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px">
                    <div>
                      <div style="font-size:14px;font-weight:600;color:var(--text-primary)">تسجيل نشاط الجلسات</div>
                      <div style="font-size:12px;color:var(--text-secondary)">تتبع جميع عمليات تسجيل الدخول</div>
                    </div>
                    <label class="toggle-switch"><input type="checkbox" id="session_logging" <?= $session_logging == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Notifications Section -->
            <div id="section-notifications" class="settings-section" style="display:none">
              <div class="stat-card animate-fadeInUp" style="padding:24px">
                <h3 style="font-size:17px;font-weight:700;margin-bottom:20px;color:var(--text-primary)">🔔 إعدادات الإشعارات</h3>
                <div style="border:1px solid var(--border-color);border-radius:14px;overflow:hidden">
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border-color)">
                    <div>
                      <div style="font-size:14px;font-weight:600;color:var(--text-primary)">إشعارات الطلبات الجديدة</div>
                      <div style="font-size:12px;color:var(--text-secondary)">تنبيه عند وصول طلب جديد</div>
                    </div><label class="toggle-switch"><input type="checkbox" id="notify_new_order" <?= $notify_new_order == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border-color)">
                    <div>
                      <div style="font-size:14px;font-weight:600;color:var(--text-primary)">إشعارات الدفعات</div>
                      <div style="font-size:12px;color:var(--text-secondary)">تنبيه عند اكتمال دفعة</div>
                    </div><label class="toggle-switch"><input type="checkbox" id="notify_payment" <?= $notify_payment == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border-color)">
                    <div>
                      <div style="font-size:14px;font-weight:600;color:var(--text-primary)">بريد يومي ملخص</div>
                      <div style="font-size:12px;color:var(--text-secondary)">تقرير يومي بالنشاط</div>
                    </div><label class="toggle-switch"><input type="checkbox" id="daily_summary_email" <?= $daily_summary_email == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px">
                    <div>
                      <div style="font-size:14px;font-weight:600;color:var(--text-primary)">إشعارات تسجيل الأكاديميين</div>
                      <div style="font-size:12px;color:var(--text-secondary)">تنبيه عند تسجيل أكاديمي جديد</div>
                    </div><label class="toggle-switch"><input type="checkbox" id="notify_new_academic" <?= $notify_new_academic == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment Methods (طرق الدفع) -->
            <div id="section-payment" class="settings-section" style="display:none">
              <div class="stat-card animate-fadeInUp" style="padding:24px">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:8px">
                  <h3 style="font-size:17px;font-weight:700;color:var(--text-primary)">💳 طرق الدفع</h3>
                  <button class="btn btn-primary" onclick="openMethodForm()">+ إضافة طريقة دفع</button>
                </div>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:18px">حسابات المنصة البنكية والمحافظ التي يحوّل إليها الطلاب عند الدفع</p>

                <div id="paymentMethodsList" style="display:flex;flex-direction:column;gap:12px">
                  <?php if (empty($paymentMethods)): ?>
                    <p id="pmEmpty" style="color:var(--text-secondary);text-align:center;padding:30px 0">لا توجد طرق دفع بعد، اضغط "إضافة طريقة دفع" لإضافة حساب بنكي أو محفظة للمنصة.</p>
                  <?php else: ?>
                    <?php foreach ($paymentMethods as $pm):
                      $isWallet = ($pm['account_type'] === 'wallet');
                      $accent = $isWallet ? 'var(--secondary)' : 'var(--primary)';
                      $icon = $isWallet ? '👛' : '🏦';
                      $active = ((int)$pm['is_active'] === 1);
                    ?>
                      <div class="pm-item" data-id="<?= (int)$pm['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 18px;border:1.5px solid var(--border-color);border-radius:14px;background:var(--bg-card);flex-wrap:wrap;<?= $active ? '' : 'opacity:.55' ?>">
                        <div style="display:flex;align-items:center;gap:14px">
                          <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,<?= $isWallet ? '#0ea5e9,#38bdf8' : '#6366f1,#818cf8' ?>);display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;overflow:hidden">
                            <?php if (!empty($pm['logo'])): ?>
                              <img src="<?= htmlspecialchars($appBaseUrl . '/' . ltrim($pm['logo'], '/'), ENT_QUOTES) ?>" alt="الشعار" style="width:100%;height:100%;object-fit:contain;padding:5px;background:#fff" onerror="this.remove()" />
                            <?php else: ?>
                              <?= $icon ?>
                            <?php endif; ?>
                          </div>
                          <div>
                            <div style="font-size:13px;font-weight:700;color:<?= $accent ?>"><?= $pmTypeLabels[$pm['account_type']] ?? $pm['account_type'] ?></div>
                            <div style="font-weight:700;color:var(--text-primary)"><?= e($pm['account_name']) ?></div>
                            <div style="font-size:13px;color:var(--text-secondary)" dir="ltr"><?= e($pm['account_number']) ?><?= $pm['holder_name'] ? ' · ' . e($pm['holder_name']) : '' ?></div>
                          </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                          <label class="toggle-switch" title="<?= $active ? 'إيقاف' : 'تفعيل' ?>" style="margin-left:4px"><input type="checkbox" onchange="toggleMethod(<?= (int)$pm['id'] ?>)" <?= $active ? 'checked' : '' ?> /><span class="toggle-slider"></span></label>
                          <button class="btn btn-outline btn-sm" onclick="openMethodForm(<?= (int)$pm['id'] ?>)">✏️ تعديل</button>
                          <button class="btn btn-danger btn-sm" onclick="deleteMethod(<?= (int)$pm['id'] ?>, '<?= e($pm['account_name'], true) ?>')">🗑️ حذف</button>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Appearance Section -->
            <div id="section-appearance" class="settings-section" style="display:none">
              <div class="stat-card animate-fadeInUp" style="padding:24px">
                <h3 style="font-size:17px;font-weight:700;margin-bottom:20px;color:var(--text-primary)">🎨 المظهر والتخصيص</h3>
                <div class="form-group">
                  <label class="form-label">اللون الرئيسي للمنصة</label>
                  <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px">
                    <div style="width:44px;height:44px;border-radius:50%;background:#6366f1;cursor:pointer;border:3px solid white;box-shadow:0 0 0 2px <?= $primary_color == '#6366f1' ? '#6366f1' : 'transparent' ?>" onclick="selectColor('#6366f1')"></div>
                    <div style="width:44px;height:44px;border-radius:50%;background:#0ea5e9;cursor:pointer;border:3px solid white;box-shadow:0 0 0 2px <?= $primary_color == '#0ea5e9' ? '#0ea5e9' : 'transparent' ?>" onclick="selectColor('#0ea5e9')"></div>
                    <div style="width:44px;height:44px;border-radius:50%;background:#10b981;cursor:pointer;border:3px solid white;box-shadow:0 0 0 2px <?= $primary_color == '#10b981' ? '#10b981' : 'transparent' ?>" onclick="selectColor('#10b981')"></div>
                    <div style="width:44px;height:44px;border-radius:50%;background:#f59e0b;cursor:pointer;border:3px solid white;box-shadow:0 0 0 2px <?= $primary_color == '#f59e0b' ? '#f59e0b' : 'transparent' ?>" onclick="selectColor('#f59e0b')"></div>
                    <div style="width:44px;height:44px;border-radius:50%;background:#ef4444;cursor:pointer;border:3px solid white;box-shadow:0 0 0 2px <?= $primary_color == '#ef4444' ? '#ef4444' : 'transparent' ?>" onclick="selectColor('#ef4444')"></div>
                    <div style="width:44px;height:44px;border-radius:50%;background:#8b5cf6;cursor:pointer;border:3px solid white;box-shadow:0 0 0 2px <?= $primary_color == '#8b5cf6' ? '#8b5cf6' : 'transparent' ?>" onclick="selectColor('#8b5cf6')"></div>
                    <div style="width:44px;height:44px;border-radius:50%;background:#ec4899;cursor:pointer;border:3px solid white;box-shadow:0 0 0 2px <?= $primary_color == '#ec4899' ? '#ec4899' : 'transparent' ?>" onclick="selectColor('#ec4899')"></div>
                  </div>
                  <input type="hidden" id="primary_color" value="<?= $primary_color ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">وضع العرض الافتراضي</label>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px">
                    <div style="padding:16px;border:2px solid <?= $theme_mode == 'light' ? 'var(--primary)' : 'var(--border-color)'; ?>;border-radius:12px;cursor:pointer;background:var(--bg-main);display:flex;flex-direction:column;align-items:center;gap:8px" onclick="setTheme('light')">
                      <span style="font-size:28px">☀️</span>
                      <span style="font-size:13px;font-weight:600;color:<?= $theme_mode == 'light' ? 'var(--primary)' : 'var(--text-secondary)' ?>">الوضع الفاتح</span>
                    </div>
                    <div style="padding:16px;border:2px solid <?= $theme_mode == 'dark' ? 'var(--primary)' : 'var(--border-color)'; ?>;border-radius:12px;cursor:pointer;background:var(--bg-main);display:flex;flex-direction:column;align-items:center;gap:8px" onclick="setTheme('dark')">
                      <span style="font-size:28px">🌙</span>
                      <span style="font-size:13px;font-weight:600;color:<?= $theme_mode == 'dark' ? 'var(--primary)' : 'var(--text-secondary)' ?>">الوضع الداكن</span>
                    </div>
                  </div>
                  <input type="hidden" id="theme_mode" value="<?= $theme_mode ?>">
                </div>
              </div>
            </div>

            <!-- Email Settings -->
            <div id="section-email" class="settings-section" style="display:none">
              <div class="stat-card animate-fadeInUp" style="padding:24px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
                  <div>
                    <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:4px">📧 إعدادات خادم البريد (SMTP)</h3>
                    <p style="font-size:13px;color:var(--text-secondary)">تهيئة الاتصال بخادم البريد الإلكتروني لإرسال إشعارات المنصة، استعادة كلمات المرور، وتنبيهات الطلبات.</p>
                  </div>
                  <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(99,102,241,0.1);color:var(--primary);padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700">
                    <span style="width:8px;height:8px;border-radius:50%;background:var(--primary)"></span>
                    محرك SMTP أصلي
                  </span>
                </div>

                <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:flex-start;gap:12px">
                  <span style="font-size:22px;line-height:1">💡</span>
                  <div style="font-size:13px;color:var(--text-secondary);line-height:1.6">
                    <strong style="color:var(--text-primary);display:block;margin-bottom:2px">تلميح إعداد Gmail أو خوادم الاستضافة:</strong>
                    إذا كنت تستخدم <strong>Gmail</strong>، يُنصح بضبط المنفذ على <code style="direction:ltr;background:var(--bg-card);padding:2px 6px;border-radius:6px">587</code> ونوع التشفير <strong>TLS</strong> واستخدام <strong>كلمة مرور التطبيقات (App Password)</strong> المكونة من 16 حرفاً من حساب Google بدلاً من كلمة المرور العادية. أما لخوادم SSL المباشرة فاستخدم المنفذ <code style="direction:ltr;background:var(--bg-card);padding:2px 6px;border-radius:6px">465</code>.
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:16px;margin-bottom:24px">
                  <div class="form-group">
                    <label class="form-label">خادم SMTP (Host)</label>
                    <input class="form-input" id="smtp_host" placeholder="smtp.gmail.com" value="<?= htmlspecialchars($smtp_host) ?>" dir="ltr" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">نوع التشفير (Encryption)</label>
                    <select class="form-input form-select" id="smtp_encryption" onchange="handleEncryptionChange(this.value)">
                      <option value="tls" <?= $smtp_encryption === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (موصى به - منفذ 587)</option>
                      <option value="ssl" <?= $smtp_encryption === 'ssl' ? 'selected' : '' ?>>SSL (منفذ 465)</option>
                      <option value="none" <?= $smtp_encryption === 'none' ? 'selected' : '' ?>>بدون تشفير (منفذ 25 أو 2525)</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">منفذ SMTP (Port)</label>
                    <input class="form-input" type="number" id="smtp_port" value="<?= htmlspecialchars($smtp_port) ?>" dir="ltr" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">اسم المستخدم / البريد (Username / Email)</label>
                    <input class="form-input" type="text" id="smtp_user" placeholder="example@gmail.com" value="<?= htmlspecialchars($smtp_user) ?>" dir="ltr" />
                  </div>
                  <div class="form-group" style="grid-column: 1 / -1">
                    <label class="form-label">كلمة المرور / كلمة مرور التطبيق (Password / App Password)</label>
                    <input class="form-input" type="password" id="smtp_password" placeholder="••••••••••••••••" value="<?= htmlspecialchars($smtp_password) ?>" dir="ltr" />
                  </div>
                </div>

                <div style="border-top:1px dashed var(--border-color);padding-top:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
                  <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:280px">
                    <input class="form-input" type="email" id="smtp_test_email" placeholder="أدخل بريدك لتلقي رسالة تجريبية" value="<?= htmlspecialchars($admin_email) ?>" dir="ltr" style="flex:1" />
                    <button class="btn btn-outline" id="testSmtpBtn" onclick="testSMTP()" type="button" style="white-space:nowrap">
                      🧪 إرسال بريد تجريبي
                    </button>
                  </div>
                  <button class="btn btn-primary" onclick="saveAllSettings()" type="button">
                    💾 حفظ إعدادات البريد
                  </button>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Confirm Modal (required for Modal.confirm) -->
  <div class="modal-overlay" id="confirmModal">
    <div class="modal-box" style="max-width:420px">
      <div class="modal-header"><h3 class="modal-title" id="confirmTitle">تأكيد الحذف</h3><button class="modal-close" data-modal-close>✕</button></div>
      <div class="modal-body" style="text-align:center;padding:32px 24px"><div style="font-size:64px;margin-bottom:16px">⚠️</div><p id="confirmMessage" style="color:var(--text-secondary);font-size:15px"></p></div>
      <div class="modal-footer"><button class="btn btn-outline" data-modal-close>إلغاء</button><button class="btn btn-danger" id="confirmBtn">تأكيد</button></div>
    </div>
  </div>

  <!-- Payment method modal -->
  <div class="modal-overlay" id="methodModal">
    <div class="modal-box" style="max-width:480px">
      <div class="modal-header">
        <h3 class="modal-title" id="methodModalTitle">إضافة طريقة دفع</h3>
        <button class="modal-close" data-modal-close>✕</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="methodId" value="" />
        <div class="form-group">
          <label class="form-label">نوع الحساب</label>
          <select class="form-input form-select" id="methodType" onchange="updateMethodLabels()" style="padding-left:36px">
            <option value="bank">🏦 حساب بنكي</option>
            <option value="wallet">👛 محفظة إلكترونية</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" id="methodNameLabel">اسم البنك</label>
          <input class="form-input" id="methodName" placeholder="ادخل اسم المحفظه او البنك" />
        </div>
        <div class="form-group">
          <label class="form-label" id="methodNumberLabel">رقم الحساب (IBAN)</label>
          <input class="form-input" id="methodNumber" dir="ltr" placeholder="0000 0000 0000 0000 0000" />
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">اسم صاحب الحساب</label>
          <input class="form-input" id="methodHolder" placeholder="اسم صاحب الحساب كما في البنك" />
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">شعار البنك / المحفظة (اختياري)</label>
          <div id="logoUploadBox" style="display:flex;align-items:center;gap:12px;border:2px dashed var(--border-color);border-radius:12px;padding:12px 14px;background:var(--bg-main);">
            <div id="logoPreview" style="display:none;position:relative;width:52px;height:52px;border-radius:10px;overflow:hidden;border:1px solid var(--border-color);background:white;flex-shrink:0;">
              <img id="logoPreviewImg" src="" alt="الشعار" style="width:100%;height:100%;object-fit:contain;padding:4px;" />
              <button type="button" onclick="removeUploadedLogo()" title="إزالة الشعار" style="position:absolute;top:-7px;right:-7px;width:20px;height:20px;border-radius:50%;background:#ef4444;color:white;border:none;font-size:11px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
            </div>
            <label for="logoFile" style="display:inline-flex;align-items:center;gap:8px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:10px;padding:9px 16px;font-size:13px;font-weight:700;color:var(--text-primary);cursor:pointer;transition:.2s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-color)'">
              <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:var(--primary);color:white;font-size:14px;font-weight:700">＋</span>
              <span>رفع الشعار من الجهاز</span>
            </label>
            <input type="file" id="logoFile" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" style="display:none" onchange="handleLogoUpload(this)" />
          </div>
          <div style="font-size:11px;color:var(--text-secondary);margin-top:6px">إن لم ترفع شعاراً سيظهر رمز البنك/المحفظة الافتراضي.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" data-modal-close>إلغاء</button>
        <button class="btn btn-primary" onclick="saveMethod()">💾 حفظ</button>
      </div>
    </div>
  </div>

  <script src="../assets/js/main.js"></script>
  <script>
    function showSection(id, btn) {
      document.querySelectorAll('.settings-section').forEach(s => s.style.display = 'none');
      const target = document.getElementById('section-' + id);
      if (target) target.style.display = 'block';

      document.querySelectorAll('.settings-menu-btn').forEach(b => {
        b.style.background = 'transparent';
        b.style.color = 'var(--text-secondary)';
        b.style.fontWeight = '400';
        b.classList.remove('active');
      });

      if (btn) {
        btn.style.background = 'var(--primary)';
        btn.style.color = 'white';
        btn.style.fontWeight = '600';
        btn.classList.add('active');
      } else {
        const matchingBtn = document.querySelector(`.settings-menu-btn[onclick*="'${id}'"]`);
        if (matchingBtn) {
          matchingBtn.style.background = 'var(--primary)';
          matchingBtn.style.color = 'white';
          matchingBtn.style.fontWeight = '600';
          matchingBtn.classList.add('active');
        }
      }

      try {
        history.replaceState(null, null, '#' + id);
      } catch (e) {}
    }

    // رفع وتحديث شعار المنصة الفعلي
    function uploadSiteLogo(input) {
      if (!input.files || !input.files[0]) return;
      const file = input.files[0];
      const fd = new FormData();
      fd.append('action', 'upload_site_logo');
      fd.append('logo', file);

      Toast.show('جاري رفع الشعار...', 'info');

      fetch('../ajax/manage_settings.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          Toast.show(res.message, 'success');
          const box = document.getElementById('siteLogoPreviewBox');
          if (box) {
            box.innerHTML = `<img id="siteLogoImg" src="${res.logo_url}" alt="الشعار" style="width:100%;height:100%;object-fit:contain;padding:6px" />`;
          }
        } else {
          Toast.show(res.message || 'تعذر رفع الشعار', 'error');
        }
      })
      .catch(() => Toast.show('خطأ في الاتصال بالخادم أثناء رفع الشعار', 'error'));
    }

    // رفع وتحديث الصورة الشخصية للمدير
    function uploadAdminAvatar(input) {
      if (!input.files || !input.files[0]) return;
      const file = input.files[0];
      const fd = new FormData();
      fd.append('action', 'upload_avatar');
      fd.append('avatar', file);

      Toast.show('جاري رفع الصورة الشخصية...', 'info');

      fetch('../ajax/manage_settings.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          Toast.show(res.message, 'success');
          const avatarEl = document.querySelector('#section-profile .admin-avatar');
          if (avatarEl) {
            avatarEl.innerHTML = `<img src="${res.avatar_url}" alt="Admin" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" />`;
          }
        } else {
          Toast.show(res.message || 'تعذر رفع الصورة الشخصية', 'error');
        }
      })
      .catch(() => Toast.show('خطأ في الاتصال بالخادم أثناء رفع الصورة', 'error'));
    }

    function selectColor(color) {
      document.documentElement.style.setProperty('--primary', color);
      document.documentElement.style.setProperty('--primary-dark', color);
      document.getElementById('primary_color').value = color;
      // تحديث حدود المربعات
      document.querySelectorAll('#section-appearance div[style*="border-radius:50%"]').forEach(div => {
        div.style.boxShadow = `0 0 0 2px ${div.style.backgroundColor === color ? color : 'transparent'}`;
      });
      Toast.show('تم اختيار اللون', 'success');
    }

    function setTheme(mode, showToast = true) {
      document.getElementById('theme_mode').value = mode;
      if (mode === 'dark') {
        document.body.classList.add('dark-mode');
      } else {
        document.body.classList.remove('dark-mode');
      }
      // تحديث واجهة الاختيار
      const lightDiv = document.querySelector('#section-appearance div[onclick="setTheme(\'light\')"]');
      const darkDiv = document.querySelector('#section-appearance div[onclick="setTheme(\'dark\')"]');
      if (lightDiv && darkDiv) {
        lightDiv.style.borderColor = mode === 'light' ? 'var(--primary)' : 'var(--border-color)';
        darkDiv.style.borderColor = mode === 'dark' ? 'var(--primary)' : 'var(--border-color)';
        lightDiv.querySelector('span:last-child').style.color = mode === 'light' ? 'var(--primary)' : 'var(--text-secondary)';
        darkDiv.querySelector('span:last-child').style.color = mode === 'dark' ? 'var(--primary)' : 'var(--text-secondary)';
      }
      if (showToast) {
        Toast.show(`تم تفعيل الوضع ${mode === 'dark' ? 'الداكن' : 'الفاتح'}`, 'success');
      }
    }

    // حفظ جميع الإعدادات
    function saveAllSettings() {
      const btn = document.getElementById('saveAllBtn');
      btn.innerHTML = '⏳ جاري الحفظ...';
      btn.disabled = true;

      // جمع جميع قيم الحقول
      const formData = new FormData();
      formData.append('action', 'save_settings');

      // الإعدادات العامة
      formData.append('site_name', document.getElementById('site_name').value);
      formData.append('site_email', document.getElementById('site_email').value);
      formData.append('site_phone', document.getElementById('site_phone').value);
      formData.append('site_url', document.getElementById('site_url').value);
      formData.append('site_language', document.getElementById('site_language').value);
      formData.append('site_timezone', document.getElementById('site_timezone').value);
      formData.append('site_description', document.getElementById('site_description').value);

      // العمولة والسياسة المالية والطلبات
      const commEl = document.getElementById('commission_percentage');
      if (commEl) formData.append('commission_percentage', commEl.value);
      const minW = document.getElementById('min_withdrawal');
      if (minW) formData.append('min_withdrawal', minW.value);
      const pCycle = document.getElementById('payout_cycle');
      if (pCycle) formData.append('payout_cycle', pCycle.value);
      const aah = document.getElementById('auto_approve_hours');
      if (aah) formData.append('auto_approve_hours', aah.value);
      const wed = document.getElementById('withdrawal_eligibility_days');
      if (wed) formData.append('withdrawal_eligibility_days', wed.value);
      const ppt = document.getElementById('payout_processing_time');
      if (ppt) formData.append('payout_processing_time', ppt.value);

      // التبديلات
      formData.append('maintenance_mode', document.getElementById('maintenance_mode').checked ? '1' : '0');
      formData.append('allow_registration', document.getElementById('allow_registration').checked ? '1' : '0');
      formData.append('email_verification', document.getElementById('email_verification').checked ? '1' : '0');
      formData.append('two_factor_auth', document.getElementById('two_factor_auth').checked ? '1' : '0');
      formData.append('session_logging', document.getElementById('session_logging').checked ? '1' : '0');

      // الإشعارات
      formData.append('notify_new_order', document.getElementById('notify_new_order').checked ? '1' : '0');
      formData.append('notify_payment', document.getElementById('notify_payment').checked ? '1' : '0');
      formData.append('daily_summary_email', document.getElementById('daily_summary_email').checked ? '1' : '0');
      formData.append('notify_new_academic', document.getElementById('notify_new_academic').checked ? '1' : '0');

      // المظهر
      formData.append('primary_color', document.getElementById('primary_color').value);
      formData.append('theme_mode', document.getElementById('theme_mode').value);

      // SMTP
      formData.append('smtp_host', document.getElementById('smtp_host').value);
      formData.append('smtp_port', document.getElementById('smtp_port').value);
      formData.append('smtp_encryption', document.getElementById('smtp_encryption').value);
      formData.append('smtp_user', document.getElementById('smtp_user').value);
      formData.append('smtp_password', document.getElementById('smtp_password').value);

      fetch('../ajax/manage_settings.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(res => {
          btn.innerHTML = '💾 حفظ التغييرات';
          btn.disabled = false;
          if (res.success) {
            Toast.show(res.message, 'success');
            // تحديث عدادات قسم العمولة حياً
            if (res.services_updated !== undefined) {
              const total = parseInt(document.getElementById('statTotalServices')?.innerText || '0');
              const custom = res.custom_excepted !== undefined ? res.custom_excepted : 0;
              const matchEl = document.getElementById('statMatchCommission');
              const zeroEl = document.getElementById('statZeroCommission');
              const custEl = document.getElementById('statCustomCommission');
              if (zeroEl) zeroEl.innerText = '0';
              if (custEl) custEl.innerText = custom;
              if (matchEl) matchEl.innerText = Math.max(0, total - custom);
            }
          } else {
            Toast.show(res.message || 'حدث خطأ أثناء الحفظ', 'error');
          }
        })
        .catch(err => {
          btn.innerHTML = '💾 حفظ التغييرات';
          btn.disabled = false;
          Toast.show('خطأ في الاتصال', 'error');
        });
    }

    // تحديث الملف الشخصي
    function updateProfile() {
      const formData = new FormData();
      formData.append('action', 'update_profile');
      formData.append('name', document.getElementById('admin_name').value);
      formData.append('email', document.getElementById('admin_email').value);
      formData.append('phone', document.getElementById('admin_phone').value);

      fetch('../ajax/manage_settings.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            Toast.show(res.message, 'success');
            // تحديث الأسماء المعروضة
            document.getElementById('adminNameDisplay').innerText = document.getElementById('admin_name').value;
            document.getElementById('adminEmailDisplay').innerText = document.getElementById('admin_email').value;
          } else {
            Toast.show(res.message, 'error');
          }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'));
    }

    // تغيير كلمة المرور
    function changePassword() {
      const current = document.getElementById('current_password').value;
      const newPass = document.getElementById('new_password').value;
      const confirm = document.getElementById('confirm_password').value;

      if (!current || !newPass || !confirm) {
        Toast.show('جميع الحقول مطلوبة', 'warning');
        return;
      }
      if (newPass !== confirm) {
        Toast.show('كلمة المرور الجديدة غير متطابقة', 'warning');
        return;
      }
      if (newPass.length < 6) {
        Toast.show('كلمة المرور يجب أن تكون 6 أحرف على الأقل', 'warning');
        return;
      }

      const formData = new FormData();
      formData.append('action', 'change_password');
      formData.append('current_password', current);
      formData.append('new_password', newPass);
      formData.append('confirm_password', confirm);

      fetch('../ajax/manage_settings.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            Toast.show(res.message, 'success');
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
          } else {
            Toast.show(res.message, 'error');
          }
        })
        .catch(() => Toast.show('خطأ في الاتصال', 'error'));
    }

    function handleEncryptionChange(enc) {
      const portInput = document.getElementById('smtp_port');
      if (enc === 'ssl' && (!portInput.value || portInput.value === '587' || portInput.value === '25')) {
        portInput.value = '465';
      } else if (enc === 'tls' && (!portInput.value || portInput.value === '465' || portInput.value === '25')) {
        portInput.value = '587';
      } else if (enc === 'none' && (!portInput.value || portInput.value === '465' || portInput.value === '587')) {
        portInput.value = '25';
      }
    }

    // اختبار SMTP الفعلي عبر الخادم
    function testSMTP() {
      const testEmail = document.getElementById('smtp_test_email')?.value.trim();
      const btn = document.getElementById('testSmtpBtn');
      if (!testEmail) {
        Toast.show('يرجى كتابة عنوان بريد إلكتروني لإرسال التجربة إليه', 'warning');
        return;
      }
      
      const origHtml = btn ? btn.innerHTML : '';
      if (btn) {
        btn.innerHTML = '⏳ جاري الاتصال والإرسال...';
        btn.disabled = true;
      }

      const fd = new FormData();
      fd.append('action', 'test_smtp');
      fd.append('test_email', testEmail);

      fetch('../ajax/manage_settings.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(res => {
        if (btn) {
          btn.innerHTML = origHtml;
          btn.disabled = false;
        }
        if (res.success) {
          Toast.show(res.message, 'success');
        } else {
          Toast.show(res.message || 'فشل اختبار اتصال البريد', 'error');
        }
      })
      .catch(err => {
        if (btn) {
          btn.innerHTML = origHtml;
          btn.disabled = false;
        }
        Toast.show('حدث خطأ أثناء فحص اتصال الخادم', 'error');
      });
    }

    // تطبيق الثيم واللون المحفوظ والتنقل بالتبويبات
    document.addEventListener('DOMContentLoaded', () => {
      const savedColor = document.getElementById('primary_color')?.value;
      if (savedColor) {
        document.documentElement.style.setProperty('--primary', savedColor);
        document.documentElement.style.setProperty('--primary-dark', savedColor);
      }
      const savedTheme = document.getElementById('theme_mode')?.value;
      if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
      } else {
        document.body.classList.remove('dark-mode');
      }
      if (typeof setTheme === 'function') {
        setTheme(savedTheme || 'light', false);
      }

      // دعم الانتقال المباشر بالرابط مثل settings.php#commission
      const hash = (window.location.hash || '').replace('#', '');
      if (hash && document.getElementById('section-' + hash)) {
        showSection(hash);
      } else {
        const activeBtn = document.querySelector('.settings-menu-btn.active');
        if (activeBtn) {
          const match = activeBtn.getAttribute('onclick')?.match(/'([^']+)'/);
          if (match && match[1]) showSection(match[1], activeBtn);
        }
      }
    });

    /* =============================================
       PAYMENT METHODS (طرق الدفع)
       ============================================= */
    let methodStore = <?= json_encode($paymentMethods, JSON_UNESCAPED_UNICODE) ?>;

    function pmEsc(v) {
      return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function pmJs(v) {
      return String(v || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, ' ');
    }

    // حالة الشعار المرفوع لطريقة الدفع
    let selectedLogo = '';
    let selectedLogoCleared = false;
    // قاعدة مسار المشروع لبناء روابط الصور
    const PM_APP_BASE = '<?= htmlspecialchars($appBaseUrl, ENT_QUOTES) ?>';

    function pmAssetUrl(path) {
      if (!path) return '';
      if (/^(https?:)?\/\//.test(path)) return path;
      return PM_APP_BASE + '/' + String(path).replace(/^\/+/, '');
    }

    function handleLogoUpload(input) {
      const file = input.files && input.files[0];
      if (!file) return;
      const fd = new FormData();
      fd.append('action', 'upload_logo');
      fd.append('image', file);
      Toast.show('جارٍ رفع الشعار...', 'info');
      fetch('../ajax/manage_payment_methods.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            selectedLogo = res.image;
            selectedLogoCleared = false;
            showLogoPreview(res.image);
            Toast.show('تم رفع الشعار بنجاح', 'success');
          } else {
            input.value = '';
            Toast.show(res.message || 'فشل رفع الشعار', 'error');
          }
        })
        .catch(() => { input.value = ''; Toast.show('حدث خطأ في الاتصال بالخادم', 'error'); });
    }

    function removeUploadedLogo() {
      selectedLogo = '';
      selectedLogoCleared = true;
      document.getElementById('logoFile').value = '';
      resetLogoPreview();
    }

    function showLogoPreview(src) {
      const preview = document.getElementById('logoPreview');
      document.getElementById('logoPreviewImg').src = pmAssetUrl(src);
      preview.style.display = 'block';
      document.getElementById('logoUploadBox').style.borderColor = '#86efac';
      document.getElementById('logoUploadBox').style.background = '#f0fdf4';
    }

    function resetLogoPreview() {
      document.getElementById('logoPreview').style.display = 'none';
      document.getElementById('logoPreviewImg').removeAttribute('src');
      const box = document.getElementById('logoUploadBox');
      box.style.borderColor = 'var(--border-color)';
      box.style.background = 'var(--bg-main)';
    }

    function updateMethodLabels() {
      const isWallet = document.getElementById('methodType').value === 'wallet';
      document.getElementById('methodNameLabel').textContent = isWallet ? 'اسم المحفظة' : 'اسم البنك';
      document.getElementById('methodNumberLabel').textContent = isWallet ? 'رقم المحفظة' : 'رقم الحساب (IBAN)';
      document.getElementById('methodNumber').placeholder = isWallet ? 'xxxxxxxx' : '0000 0000 0000 0000 0000';
    }

    function openMethodForm(id) {
      const mId = id ? String(id) : '';
      document.getElementById('methodId').value = mId;
      if (mId) {
        const m = methodStore.find(x => String(x.id) === mId);
        if (!m) { Toast.show('طريقة الدفع غير موجودة', 'error'); return; }
        document.getElementById('methodModalTitle').textContent = 'تعديل طريقة دفع';
        document.getElementById('methodType').value = m.account_type;
        document.getElementById('methodName').value = m.account_name || '';
        document.getElementById('methodNumber').value = m.account_number || '';
        document.getElementById('methodHolder').value = m.holder_name || '';
        selectedLogo = m.logo || '';
        selectedLogoCleared = false;
        if (selectedLogo) showLogoPreview(selectedLogo); else resetLogoPreview();
      } else {
        document.getElementById('methodModalTitle').textContent = 'إضافة طريقة دفع';
        document.getElementById('methodType').value = 'bank';
        document.getElementById('methodName').value = '';
        document.getElementById('methodNumber').value = '';
        document.getElementById('methodHolder').value = '';
        selectedLogo = '';
        selectedLogoCleared = false;
        document.getElementById('logoFile').value = '';
        resetLogoPreview();
      }
      updateMethodLabels();
      Modal.open('methodModal');
    }

    function methodCard(m) {
      const isWallet = m.account_type === 'wallet';
      const accent = isWallet ? 'var(--secondary)' : 'var(--primary)';
      const icon = isWallet ? '👛' : '🏦';
      const typeLabel = isWallet ? '👛 محفظة إلكترونية' : '🏦 حساب بنكي';
      const grad = isWallet ? '#0ea5e9,#38bdf8' : '#6366f1,#818cf8';
      const active = m.is_active == 1;
      const holder = m.holder_name ? ' · ' + pmEsc(m.holder_name) : '';
      const logoHtml = m.logo
        ? '<img src="' + pmAssetUrl(m.logo) + '" alt="الشعار" style="width:100%;height:100%;object-fit:contain;padding:5px;background:#fff;border-radius:12px" onerror="this.remove()" />'
        : icon;
      return '<div class="pm-item" data-id="' + m.id + '" style="display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 18px;border:1.5px solid var(--border-color);border-radius:14px;background:var(--bg-card);flex-wrap:wrap;' + (active ? '' : 'opacity:.55') + '">'
        + '<div style="display:flex;align-items:center;gap:14px">'
        + '<div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,' + grad + ');display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;overflow:hidden">' + logoHtml + '</div>'
        + '<div>'
        + '<div style="font-size:13px;font-weight:700;color:' + accent + '">' + typeLabel + '</div>'
        + '<div style="font-weight:700;color:var(--text-primary)">' + pmEsc(m.account_name) + '</div>'
        + '<div style="font-size:13px;color:var(--text-secondary)" dir="ltr">' + pmEsc(m.account_number) + holder + '</div>'
        + '</div></div>'
        + '<div style="display:flex;gap:8px;align-items:center">'
        + '<label class="toggle-switch" style="margin-left:4px"><input type="checkbox" onchange="toggleMethod(' + m.id + ')"' + (active ? ' checked' : '') + ' /><span class="toggle-slider"></span></label>'
        + '<button class="btn btn-outline btn-sm" onclick="openMethodForm(' + m.id + ')">✏️ تعديل</button>'
        + '<button class="btn btn-danger btn-sm" onclick="deleteMethod(' + m.id + ', \'' + pmJs(m.account_name) + '\')">🗑️ حذف</button>'
        + '</div></div>';
    }

    function renderMethodsList() {
      const list = document.getElementById('paymentMethodsList');
      if (!list) return;
      if (!methodStore.length) {
        list.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:30px 0">لا توجد طرق دفع بعد، اضغط "إضافة طريقة دفع" لإضافة حساب بنكي أو محفظة للمنصة.</p>';
        return;
      }
      list.innerHTML = methodStore.map(methodCard).join('');
    }

    function loadMethods() {
      const fd = new FormData();
      fd.append('action', 'get_methods');
      fetch('../ajax/manage_payment_methods.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (!res.success) { Toast.show(res.message || 'تعذر جلب البيانات', 'error'); return; }
          methodStore = res.methods || [];
          renderMethodsList();
        })
        .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
    }

    function saveMethod() {
      const id = document.getElementById('methodId').value;
      const account_type = document.getElementById('methodType').value;
      const account_name = document.getElementById('methodName').value.trim();
      const account_number = document.getElementById('methodNumber').value.trim();
      const holder_name = document.getElementById('methodHolder').value.trim();

      if (!account_name || !account_number) {
        Toast.show('يرجى إدخال اسم البنك أو المحفظة والرقم', 'error');
        return;
      }

      const action = id ? 'update_method' : 'add_method';
      const fd = new FormData();
      fd.append('action', action);
      fd.append('id', id || 0);
      fd.append('account_type', account_type);
      fd.append('account_name', account_name);
      fd.append('account_number', account_number);
      fd.append('holder_name', holder_name);
      fd.append('logo', selectedLogo);
      fd.append('logo_cleared', selectedLogoCleared ? 1 : 0);

      fetch('../ajax/manage_payment_methods.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            Toast.show(res.message, 'success');
            Modal.close('methodModal');
            loadMethods();
          } else {
            Toast.show(res.message || 'حدث خطأ ما', 'error');
          }
        })
        .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
    }

    function deleteMethod(id, name) {
      Modal.confirm('حذف طريقة الدفع', 'هل تريد حذف "' + (name || '') + '" نهائياً؟', () => {
        const fd = new FormData();
        fd.append('action', 'delete_method');
        fd.append('id', id);
        fetch('../ajax/manage_payment_methods.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(res => {
            if (res.success) { Toast.show(res.message, 'success'); loadMethods(); }
            else Toast.show(res.message || 'حدث خطأ ما', 'error');
          })
          .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
      });
    }

    function toggleMethod(id) {
      const fd = new FormData();
      fd.append('action', 'toggle_method');
      fd.append('id', id);
      fetch('../ajax/manage_payment_methods.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (res.success) { Toast.show(res.message, 'info'); loadMethods(); }
          else { Toast.show(res.message || 'حدث خطأ ما', 'error'); loadMethods(); }
        })
        .catch(() => Toast.show('حدث خطأ في الاتصال بالخادم', 'error'));
    }
  </script>
</body>

</html>