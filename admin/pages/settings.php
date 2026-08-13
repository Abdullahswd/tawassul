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
$site_name = getSetting($settings, 'site_name', 'تواصل الأكاديمي');
$site_email = getSetting($settings, 'site_email', 'info@tawassul.com');
$site_phone = getSetting($settings, 'site_phone', '+966 11 000 0000');
$site_url = getSetting($settings, 'site_url', 'https://tawassul.com');
$site_language = getSetting($settings, 'site_language', 'ar');
$site_timezone = getSetting($settings, 'site_timezone', 'Asia/Riyadh');
$site_description = getSetting($settings, 'site_description', '');
$commission_percentage = getSetting($settings, 'commission_percentage', '15');
$min_withdrawal = getSetting($settings, 'min_withdrawal', '200');
$payout_cycle = getSetting($settings, 'payout_cycle', 'monthly');
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
$smtp_user = getSetting($settings, 'smtp_user', 'noreply@tawassul.com');
$smtp_password = getSetting($settings, 'smtp_password', '');

$admin_name = $admin['name'] ?? 'المدير العام';
$admin_email = $admin['email'] ?? 'admin@tawassul.com';
$admin_phone = $admin['phone'] ?? '+966 5X XXX XXXX';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>الإعدادات - تواصل Admin</title>
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
              <button class="settings-menu-btn" onclick="showSection('profile',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">👤 الملف الشخصي</button>
              <button class="settings-menu-btn" onclick="showSection('security',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">🔒 الأمان</button>
              <button class="settings-menu-btn" onclick="showSection('notifications',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">🔔 الإشعارات</button>
              <button class="settings-menu-btn" onclick="showSection('payment',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">💳 بوابات الدفع</button>
              <button class="settings-menu-btn" onclick="showSection('appearance',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">🎨 المظهر</button>
              <button class="settings-menu-btn" onclick="showSection('email',this)" style="display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:10px;border:none;background:transparent;color:var(--text-secondary);font-family:Tajawal,sans-serif;font-size:14px;cursor:pointer;margin-bottom:4px;text-align:right">📧 إعدادات البريد</button>
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

                <!-- Logo Upload (مثال) -->
                <div class="form-group">
                  <label class="form-label">شعار المنصة</label>
                  <div style="display:flex;align-items:center;gap:20px;margin-top:8px">
                    <div style="width:80px;height:80px;border-radius:16px;background:linear-gradient(135deg,#6366f1,#818cf8);display:flex;align-items:center;justify-content:center;font-size:36px">🎓</div>
                    <div>
                      <button class="btn btn-outline btn-sm" onclick="Toast.show('ميزة رفع الشعار قيد التطوير','info')">📤 رفع شعار جديد</button>
                      <p style="font-size:12px;color:var(--text-secondary);margin-top:6px">PNG أو SVG، بحد أقصى 2MB</p>
                    </div>
                  </div>
                </div>

                <!-- Quick Toggles -->
                <div style="border: 1px solid var(--border-color);border-radius:14px;overflow:hidden;margin-top:8px">
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
              </div>

              <!-- Commission Settings -->
              <div class="stat-card" style="padding:24px">
                <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:4px">💰 إعدادات العمولة</h3>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px">تحديد نسبة عمولة المنصة</p>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                  <div class="form-group"><label class="form-label">نسبة العمولة (%)</label><input class="form-input" type="number" id="commission_percentage" value="<?= $commission_percentage ?>" min="0" max="50" /></div>
                  <div class="form-group"><label class="form-label">الحد الأدنى للسحب</label><input class="form-input" type="number" id="min_withdrawal" value="<?= $min_withdrawal ?>" /></div>
                  <div class="form-group"><label class="form-label">دورة الدفع</label>
                    <select class="form-input form-select" id="payout_cycle" style="padding-left:36px">
                      <option value="weekly" <?= $payout_cycle == 'weekly' ? 'selected' : '' ?>>أسبوعي</option>
                      <option value="monthly" <?= $payout_cycle == 'monthly' ? 'selected' : '' ?>>شهري</option>
                    </select>
                  </div>
                </div>
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
                    <button class="btn btn-outline btn-sm" style="margin-top:8px" onclick="Toast.show('ميزة تغيير الصورة قيد التطوير','info')">تغيير الصورة</button>
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

            <!-- Payment Gateways -->
            <div id="section-payment" class="settings-section" style="display:none">
              <div class="stat-card animate-fadeInUp" style="padding:24px">
                <h3 style="font-size:17px;font-weight:700;margin-bottom:20px;color:var(--text-primary)">💳 بوابات الدفع</h3>
                <div style="display:flex;flex-direction:column;gap:16px">
                  <div style="padding:20px;border:1px solid var(--border-color);border-radius:14px;display:flex;justify-content:space-between;align-items:center">
                    <div style="display:flex;align-items:center;gap:14px">
                      <div style="font-size:32px">💠</div>
                      <div>
                        <div style="font-size:15px;font-weight:700">Stripe</div>
                        <div style="font-size:12px;color:var(--text-secondary)">بوابة دفع دولية</div>
                      </div>
                    </div>
                    <div><label class="toggle-switch"><input type="checkbox" id="payment_stripe_enabled" <?= $payment_stripe_enabled == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label></div>
                  </div>
                  <div style="padding:20px;border:1px solid var(--border-color);border-radius:14px;display:flex;justify-content:space-between;align-items:center">
                    <div style="display:flex;align-items:center;gap:14px">
                      <div style="font-size:32px">🟢</div>
                      <div>
                        <div style="font-size:15px;font-weight:700">Moyasar</div>
                        <div style="font-size:12px;color:var(--text-secondary)">بوابة دفع سعودية</div>
                      </div>
                    </div>
                    <div><label class="toggle-switch"><input type="checkbox" id="payment_moyasar_enabled" <?= $payment_moyasar_enabled == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label></div>
                  </div>
                  <div style="padding:20px;border:1px solid var(--border-color);border-radius:14px;display:flex;justify-content:space-between;align-items:center">
                    <div style="display:flex;align-items:center;gap:14px">
                      <div style="font-size:32px">🔵</div>
                      <div>
                        <div style="font-size:15px;font-weight:700">PayPal</div>
                        <div style="font-size:12px;color:var(--text-secondary)">بوابة دفع عالمية</div>
                      </div>
                    </div>
                    <div><label class="toggle-switch"><input type="checkbox" id="payment_paypal_enabled" <?= $payment_paypal_enabled == '1' ? 'checked' : '' ?> /><span class="toggle-slider"></span></label></div>
                  </div>
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
                <h3 style="font-size:17px;font-weight:700;margin-bottom:20px;color:var(--text-primary)">📧 إعدادات SMTP</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                  <div class="form-group"><label class="form-label">SMTP Host</label><input class="form-input" id="smtp_host" value="<?= htmlspecialchars($smtp_host) ?>" /></div>
                  <div class="form-group"><label class="form-label">SMTP Port</label><input class="form-input" type="number" id="smtp_port" value="<?= $smtp_port ?>" /></div>
                  <div class="form-group"><label class="form-label">اسم المستخدم</label><input class="form-input" type="email" id="smtp_user" value="<?= htmlspecialchars($smtp_user) ?>" /></div>
                  <div class="form-group"><label class="form-label">كلمة المرور</label><input class="form-input" type="password" id="smtp_password" placeholder="••••••••" value="<?= htmlspecialchars($smtp_password) ?>" /></div>
                </div>
                <button class="btn btn-outline btn-sm" onclick="testSMTP()">🧪 اختبار الاتصال</button>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../assets/js/main.js"></script>
  <script>
    // Helper functions
    function showSection(id, btn) {
      document.querySelectorAll('.settings-section').forEach(s => s.style.display = 'none');
      document.getElementById('section-' + id).style.display = 'block';
      document.querySelectorAll('.settings-menu-btn').forEach(b => {
        b.style.background = 'transparent';
        b.style.color = 'var(--text-secondary)';
        b.style.fontWeight = '400';
      });
      btn.style.background = 'var(--primary)';
      btn.style.color = 'white';
      btn.style.fontWeight = '600';
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

    function setTheme(mode) {
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
      Toast.show(`تم تفعيل الوضع ${mode === 'dark' ? 'الداكن' : 'الفاتح'}`, 'success');
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

      // العمولة
      formData.append('commission_percentage', document.getElementById('commission_percentage').value);
      formData.append('min_withdrawal', document.getElementById('min_withdrawal').value);
      formData.append('payout_cycle', document.getElementById('payout_cycle').value);

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

      // بوابات الدفع
      formData.append('payment_stripe_enabled', document.getElementById('payment_stripe_enabled').checked ? '1' : '0');
      formData.append('payment_moyasar_enabled', document.getElementById('payment_moyasar_enabled').checked ? '1' : '0');
      formData.append('payment_paypal_enabled', document.getElementById('payment_paypal_enabled').checked ? '1' : '0');

      // المظهر
      formData.append('primary_color', document.getElementById('primary_color').value);
      formData.append('theme_mode', document.getElementById('theme_mode').value);

      // SMTP
      formData.append('smtp_host', document.getElementById('smtp_host').value);
      formData.append('smtp_port', document.getElementById('smtp_port').value);
      formData.append('smtp_user', document.getElementById('smtp_user').value);
      formData.append('smtp_password', document.getElementById('smtp_password').value);

      fetch('/tawassul/admin/ajax/manage_settings.php', {
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
          } else {
            Toast.show(res.message, 'error');
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

      fetch('/tawassul/admin/ajax/manage_settings.php', {
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

      fetch('/tawassul/admin/ajax/manage_settings.php', {
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

    // اختبار SMTP (يمكن تطويره لاحقاً)
    function testSMTP() {
      Toast.show('ميزة اختبار SMTP قيد التطوير', 'info');
    }

    // تطبيق الثيم واللون المحفوظ
    document.addEventListener('DOMContentLoaded', () => {
      const savedColor = document.getElementById('primary_color').value;
      if (savedColor) {
        document.documentElement.style.setProperty('--primary', savedColor);
        document.documentElement.style.setProperty('--primary-dark', savedColor);
      }
      const savedTheme = document.getElementById('theme_mode').value;
      if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
      } else {
        document.body.classList.remove('dark-mode');
      }
      setTheme(savedTheme); // لتحديث واجهة الاختيار
    });

    // إعداد القائمة النشطة
    document.querySelectorAll('.settings-menu-btn').forEach(btn => {
      if (btn.classList.contains('active')) {
        const id = btn.getAttribute('onclick').match(/'([^']+)'/)[1];
        document.getElementById('section-' + id).style.display = 'block';
      }
    });
  </script>
</body>

</html>