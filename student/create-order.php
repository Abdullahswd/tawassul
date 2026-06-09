<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$services = getAllServices();
$packages = getAllPackages();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id     = (int)($_POST['service_id'] ?? 0);
    $specialty      = trim($_POST['specialty'] ?? '');
    $academic_level = $_POST['academic_level'] ?? 'بكالوريوس';
    $language       = $_POST['language'] ?? 'العربية';
    $deadline       = $_POST['deadline'] ?? '';
    $description    = trim($_POST['description'] ?? '');
    $package_id     = isset($_POST['package_id']) && $_POST['package_id'] !== '' ? (int)$_POST['package_id'] : null;

    // Calculate default or package price
    $amount = 150.00; // Base starting price
    if ($package_id) {
        $db = db();
        $stmt = $db->prepare('SELECT price FROM packages WHERE id = ? LIMIT 1');
        $stmt->execute([$package_id]);
        $pkgPrice = $stmt->fetchColumn();
        if ($pkgPrice) {
            $amount = (float)$pkgPrice;
        }
    }

    if (!$service_id || !$specialty || !$deadline || !$description) {
        $error = 'يرجى ملء كافة الحقول المطلوبة بنجاح.';
    } else {
        $order_id = createOrder([
            'student_id'     => $user['id'],
            'service_id'     => $service_id,
            'package_id'     => $package_id,
            'specialty'      => $specialty,
            'academic_level' => $academic_level,
            'language'       => $language,
            'deadline'       => $deadline,
            'description'    => $description,
            'amount'         => $amount,
        ]);

        if ($order_id) {
            // Create welcome/success notification
            createNotification(
                $user['id'],
                'student',
                'تم إنشاء الطلب بنجاح 📋',
                'لقد سجلنا طلبك بنجاح وجاري تعيين أكاديمي مناسب للعمل عليه.',
                '📋',
                'student/orders.php'
            );

            // Insert a pending payment record
            createPayment([
                'order_id'   => $order_id,
                'student_id' => $user['id'],
                'amount'     => $amount,
                'method'     => 'credit_card'
            ]);

            header('Location: orders.php?success=1');
            exit;
        } else {
            $error = 'حدث خطأ في النظام أثناء إنشاء الطلب. حاول مجدداً.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>طلب خدمة جديدة - تواصل</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
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
      top: 20px;
      left: 30px;
      right: 30px;
      height: 2px;
      background: var(--border-color);
      z-index: 0;
    }
    .step-indicator {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      position: relative;
      z-index: 1;
    }
    .step-indicator .circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--bg-card);
      border: 2px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      color: var(--text-secondary);
      transition: all 0.3s;
    }
    .step-indicator.active .circle {
      border-color: var(--primary);
      background: var(--primary);
      color: white;
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
        <a href="services.php" class="nav-item active">
          <span class="icon">📦</span>
          <span>الخدمات الأكاديمية</span>
        </a>
        <a href="orders.php" class="nav-item">
          <span class="icon">📋</span>
          <span>طلباتي</span>
        </a>
        <a href="chat.php" class="nav-item">
          <span class="icon">💬</span>
          <span>المحادثات</span>
        </a>
        <a href="payments.php" class="nav-item">
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
          <div class="h3">طلب جديد</div>
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
              <div class="step-indicator active"><div class="circle">1</div><div class="step-label">نوع الخدمة</div></div>
              <div class="step-indicator"><div class="circle">2</div><div class="step-label">التفاصيل الأساسية</div></div>
              <div class="step-indicator"><div class="circle">3</div><div class="step-label">الملفات والشروحات</div></div>
            </div>

            <form method="POST" action="create-order.php" id="multiOrderForm">
              
              <!-- STEP 1 -->
              <div class="form-step" id="step1">
                <h3 class="h3" style="margin-bottom:24px">اختيار الخدمة والتخصص</h3>
                
                <div class="form-group">
                  <label class="form-label">الخدمة المطلوبة <span style="color:var(--danger)">*</span></label>
                  <select class="form-input" id="serviceSelect" name="service_id" required onchange="updatePricing()">
                    <option value="">اختر الخدمة المطلوبة...</option>
                    <?php foreach ($services as $s): ?>
                      <option value="<?= $s['id'] ?>" data-price="150" <?= (isset($_GET['sid']) && $_GET['sid'] == $s['id']) ? 'selected' : '' ?>>
                        <?= e($s['icon']) ?> <?= e($s['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">الباقة المرغوبة (اختياري)</label>
                  <select class="form-input" id="packageSelect" name="package_id" onchange="updatePricing()">
                    <option value="" data-price="150">طلب خدمة مخصصة (تبدأ من 150 ر.س)</option>
                    <?php foreach ($packages as $pkg): ?>
                      <option value="<?= $pkg['id'] ?>" data-price="<?= $pkg['price'] ?>">
                        <?= e($pkg['name']) ?> (<?= formatMoney($pkg['price']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

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
                  <textarea name="description" class="form-input" rows="5" placeholder="أدخل متطلبات الطلب بالتفصيل لتسهيل فهم الأكاديمي..." required></textarea>
                </div>

                <div class="form-group">
                  <label class="form-label">المرفقات</label>
                  <div style="border:2px dashed var(--border-color);border-radius:var(--radius-lg);padding:32px;text-align:center;background:var(--bg-body);cursor:pointer;transition:all 0.2s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-color)'" onclick="document.getElementById('fileUpload').click()">
                    <input type="file" id="fileUpload" multiple style="display:none">
                    <div style="font-size:32px;margin-bottom:12px">📤</div>
                    <div style="font-weight:700">اضغط لرفع الملفات والتعليمات</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:8px">PDF, DOCX, ZIP بحد أقصى 10MB</div>
                  </div>
                </div>
              </div>

              <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border-color);padding-top:24px;margin-top:16px">
                <button type="button" class="btn btn-outline" id="prevBtn" style="display:none">السابق</button>
                <div style="margin-right:auto">
                  <button type="button" class="btn btn-primary" id="nextBtn">التالي</button>
                  <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none;background:var(--success)">✅ إنشاء الطلب</button>
                </div>
              </div>

            </form>
          </div>

          <!-- Summary Sidebar -->
          <div>
            <div class="card" style="position:sticky;top:90px">
              <h3 class="h3" style="margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border-color)">ملخص الطلب</h3>
              
              <div style="display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;color:var(--text-secondary)">
                <span>السعر المبدئي للخدمة</span>
                <span id="basePrice" style="font-weight:700;color:var(--text-primary)">150 ر.س</span>
              </div>
              
              <div style="margin-top:20px;border-top:1px dashed var(--border-color);padding-top:16px;display:flex;justify-content:space-between;align-items:center">
                <span style="font-weight:700">الإجمالي المتوقع</span>
                <span id="totalPrice" style="font-size:24px;font-weight:800;color:var(--primary)">150 ر.س</span>
              </div>

              <div style="margin-top:24px;padding:12px;background:var(--primary-light);border-radius:var(--radius-sm);color:var(--primary);font-size:12px;line-height:1.6">
                <strong>ملاحظة:</strong>
                هذا تسعير مبدئي، وسيتم تأكيد السعر النهائي من الأكاديمي بناءً على متطلباتك الدقيقة.
              </div>

            </div>
          </div>

        </div>

      </div>
    </main>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    // Local flow control since we moved to standard forms
    const steps = document.querySelectorAll('.form-step');
    const indicators = document.querySelectorAll('.step-indicator');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const submitBtn = document.getElementById('submitBtn');
    const srvSelect = document.getElementById('serviceSelect');
    const pkgSelect = document.getElementById('packageSelect');
    const basePriceEl = document.getElementById('basePrice');
    const totalPriceEl = document.getElementById('totalPrice');

    let currentStep = 0;

    function updatePricing() {
      let price = 150;
      if (pkgSelect && pkgSelect.value !== "") {
        price = parseFloat(pkgSelect.options[pkgSelect.selectedIndex].getAttribute('data-price'));
      }
      basePriceEl.textContent = price + ' ر.س';
      totalPriceEl.textContent = price + ' ر.س';
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
      if (currentStep === 0 && srvSelect && !srvSelect.value) {
        alert('يرجى اختيار خدمة أولاً');
        return;
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
</body>
</html>
