<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$db = db();

// Fetch fresh details from database
$stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!$name) {
        $error = 'الاسم بالكامل مطلوب.';
    } else {
        // Update user
        $update = $db->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
        if ($update->execute([$name, $phone, $user['id']])) {
            $success = 'تم حفظ التغييرات بنجاح.';
            
            // Refresh session details
            $userData['name'] = $name;
            $userData['phone'] = $phone;
            $_SESSION['user_name'] = $name;
            $initials = mb_substr($name, 0, 1, 'UTF-8') . mb_substr(explode(' ', $name)[1] ?? '', 0, 1, 'UTF-8');
            $_SESSION['user_avatar'] = $initials;
            $user = currentUser();
        } else {
            $error = 'حدث خطأ أثناء حفظ التحديثات.';
        }
    }
}
?>
<?php
$extraCss = [
  '<style>
    .profile-tabs {
      display: flex;
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 24px;
    }
    .p-tab {
      padding: 12px 24px;
      font-weight: 700;
      color: var(--text-secondary);
      border-bottom: 3px solid transparent;
      cursor: pointer;
    }
    .p-tab.active {
      color: var(--primary);
      border-bottom-color: var(--primary);
    }
    .alert-error   { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
    .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
  </style>',
];
$pageTitle  = 'الملف الشخصي';
$activePage = 'profile';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>

      <div style="max-width:900px;margin:0 auto">
        
        <h1 class="h1" style="margin-bottom:32px">إعدادات الحساب</h1>

        <?php if ($error): ?>
          <div class="alert-error">⚠️ <?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert-success">✓ <?= e($success) ?></div>
        <?php endif; ?>

        <div class="card" style="padding:0;overflow:hidden">
          
          <!-- Avatar Header -->
          <div style="padding:40px;background:var(--bg-body);border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:24px">
            <div style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:800;position:relative">
              <span><?= e($user['avatar']) ?></span>
            </div>
            <div>
              <h2 class="h2" style="margin-bottom:4px"><?= e($userData['name']) ?></h2>
              <div style="color:var(--text-secondary);margin-bottom:12px"><?= e($userData['email']) ?></div>
              <span class="status-badge status-completed">طالب موثق</span>
            </div>
          </div>

          <!-- Tabs -->
          <div class="profile-tabs" style="padding:0 24px;margin-bottom:0">
            <div class="p-tab active">البيانات الشخصية</div>
          </div>

          <!-- Tab Content 1 -->
          <div style="padding:32px">
            <form method="POST" action="profile.php">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
                
                <div class="form-group">
                  <label class="form-label">الاسم بالكامل</label>
                  <input type="text" name="name" class="form-input" value="<?= e($userData['name']) ?>" required>
                </div>
                
                <div class="form-group">
                  <label class="form-label">البريد الإلكتروني (غير قابل للتعديل)</label>
                  <input type="email" class="form-input" value="<?= e($userData['email']) ?>" dir="ltr" readonly style="background:var(--bg-body);cursor:not-allowed">
                </div>
                
                <div class="form-group" style="grid-column:1/-1">
                  <label class="form-label">رقم الجوال</label>
                  <input type="tel" name="phone" class="form-input" value="<?= e($userData['phone']) ?>" dir="ltr">
                </div>

              </div>

              <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
              </div>
            </form>
          </div>

        </div>

      </div>

<?php require __DIR__ . '/partials/footer.php'; ?>
