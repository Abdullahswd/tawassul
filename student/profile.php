<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

ensureUserAvatarColumn();

$user = currentUser();
$db = db();
$appBase = rtrim(preg_replace('#/student/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

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

            // رفع الصورة الشخصية (اختياري)
            if (!empty($_FILES['avatar']['name'])) {
                $oldAvatar = $userData['avatar_path'] ?? null;
                $newAvatar = saveAvatarUpload($_FILES['avatar']);
                if ($newAvatar) {
                    $db->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')
                       ->execute([$newAvatar, $user['id']]);
                    // حذف الصورة القديمة من القرص
                    if ($oldAvatar && strpos($oldAvatar, 'uploads/avatars/') === 0) {
                        @unlink(dirname(__DIR__) . '/' . $oldAvatar);
                    }
                    $_SESSION['user_avatar_path'] = $newAvatar;
                    $userData['avatar_path'] = $newAvatar;
                    $success = 'تم حفظ التغييرات وتحديث الصورة الشخصية بنجاح.';
                } else {
                    $error = 'فشل رفع الصورة — تأكد أن الملف صورة (JPG / PNG / WEBP / GIF) وبحجم أقل من 5MB.';
                }
            }

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
          <form method="POST" action="profile.php" enctype="multipart/form-data">
          <!-- Avatar Header -->
          <?php $avatarPath = $userData['avatar_path'] ?? ''; ?>
          <div style="padding:40px;background:var(--bg-body);border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:24px">
            <div style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:800;position:relative;flex-shrink:0;overflow:hidden">
              <?php if ($avatarPath): ?>
                <img id="avatarPreviewImg" src="<?= e($appBase . '/' . $avatarPath) ?>" alt="الصورة الشخصية" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0">
                <span style="position:relative;display:none"><?= e($user['avatar']) ?></span>
              <?php else: ?>
                <span id="avatarPreviewImg" style="position:relative"><?= e($user['avatar']) ?></span>
              <?php endif; ?>
              <!-- زر رفع الصورة -->
              <label for="avatarInput" title="تغيير الصورة الشخصية"
                style="position:absolute;bottom:0;left:0;width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.25);z-index:2">📷</label>
              <input type="file" id="avatarInput" name="avatar" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none"
                onchange="previewAvatar(this)">
            </div>
            <div>
              <h2 class="h2" style="margin-bottom:4px"><?= e($userData['name']) ?></h2>
              <div style="color:var(--text-secondary);margin-bottom:12px"><?= e($userData['email']) ?></div>
              <span class="status-badge status-completed">طالب موثق</span>
              <div style="font-size:12px;color:var(--text-muted);margin-top:8px">اضغط على أيقونة الكاميرا 📷 لرفع أو تغيير صورتك الشخصية (JPG / PNG — حتى 5MB)</div>
            </div>
          </div>
          <script>
            function previewAvatar(input) {
              if (!input.files || !input.files[0]) return;
              const img = document.getElementById('avatarPreviewImg');
              if (img && img.tagName === 'IMG') {
                img.src = URL.createObjectURL(input.files[0]);
              } else {
                // لا توجد صورة سابقة: أنشئ عنصر صورة مكان الحروف
                const wrap = input.closest('div');
                const newImg = document.createElement('img');
                newImg.id = 'avatarPreviewImg';
                newImg.style.cssText = 'width:100%;height:100%;object-fit:cover;position:absolute;inset:0';
                newImg.src = URL.createObjectURL(input.files[0]);
                const oldSpan = img;
                wrap.insertBefore(newImg, oldSpan);
                if (oldSpan && oldSpan.tagName !== 'IMG') oldSpan.style.display = 'none';
              }
            }
          </script>

          <!-- Tabs -->
          <div class="profile-tabs" style="padding:0 24px;margin-bottom:0">
            <div class="p-tab active">البيانات الشخصية</div>
          </div>

          <!-- Tab Content 1 -->
          <div style="padding:32px">
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
