<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

$order_no = $_GET['id'] ?? '';
if (!$order_no) {
    header('Location: orders.php');
    exit;
}

$db = db();

// Fetch order
$stmt = $db->prepare("
    SELECT o.*, s.name AS service_name, s.icon AS service_icon, s.platform_percent AS service_platform_percent,
           p.name AS package_name, p.price AS package_price,
           u.name AS student_name, u.email AS student_email, u.phone AS student_phone,
           a.name AS academic_name, a.email AS academic_email, a.specialty AS academic_specialty, a.rating AS academic_rating
    FROM orders o
    JOIN users u ON o.student_id = u.id
    JOIN services s ON o.service_id = s.id
    LEFT JOIN academics a ON o.academic_id = a.id
    LEFT JOIN packages p ON o.package_id = p.id
    WHERE o.order_number = ? LIMIT 1
");
$stmt->execute([$order_no]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// مرفقات الطلب المرفوعة من الطالب
$attStmt = $db->prepare("SELECT * FROM order_attachments WHERE order_id = ? ORDER BY id ASC");
$attStmt->execute([(int) $order['id']]);
$orderAttachments = $attStmt->fetchAll();

// مسار التطبيق الأساسي لبناء روابط الملفات
$adminAppBase = rtrim(preg_replace('#/admin/pages/[^/]+$#', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = $_POST['status'] ?? '';
    if (in_array($new_status, ['pending_assignment', 'assigned', 'new', 'accepted', 'in_progress', 'revision', 'completed', 'cancelled'])) {
        updateOrderStatus($order['id'], $new_status);
        
        // Add a notification for the student
        createNotification(
            $order['student_id'],
            'student',
            'تحديث حالة الطلب 🔔',
            'تم تحديث حالة طلبك ' . $order['order_number'] . ' لتصبح: ' . orderStatusLabel($new_status)['label'],
            '🔔',
            'student/order-details.php?id=' . $order['id']
        );
        
        header('Location: order-details.php?id=' . $order['order_number'] . '&updated=1');
        exit;
    }
}

// Handle assignment updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_academics') {
    $academic_ids = $_POST['academic_ids'] ?? [];
    
    $db->beginTransaction();
    try {
        // Clear previous assignments
        $del = $db->prepare("DELETE FROM order_assignments WHERE order_id = ?");
        $del->execute([$order['id']]);
        
        if (!empty($academic_ids)) {
            $ins = $db->prepare("INSERT INTO order_assignments (order_id, academic_id) VALUES (?, ?)");
            foreach ($academic_ids as $ac_id) {
                $ins->execute([$order['id'], $ac_id]);
                
                // Notify academic
                createNotification(
                    $ac_id,
                    'academic',
                    'تم إسناد طلب جديد إليك 📋',
                    'قامت الإدارة بإسناد الطلب #' . $order['order_number'] . ' إليك. يرجى مراجعته وقبوله لبدء العمل.',
                    '📋',
                    'academics/academic-orders.php'
                );
            }
            
            // Set status to assigned
            $upd = $db->prepare("UPDATE orders SET status = 'assigned' WHERE id = ?");
            $upd->execute([$order['id']]);
            
            // Notify student
            createNotification(
                $order['student_id'],
                'student',
                'تحديث حالة الطلب 🔔',
                'تم تحويل طلبك #' . $order['order_number'] . ' إلى الأكاديميين المتخصصين للمراجعة والقبول.',
                '🔔',
                'student/order-details.php?id=' . $order['id']
            );
        } else {
            // Revert status to pending_assignment
            $upd = $db->prepare("UPDATE orders SET status = 'pending_assignment' WHERE id = ?");
            $upd->execute([$order['id']]);
        }
        
        $db->commit();
        header('Location: order-details.php?id=' . $order['order_number'] . '&updated=1');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = "حدث خطأ أثناء حفظ الإسناد: " . $e->getMessage();
    }
}

// Fetch approved academics who have the order's service
$academics_stmt = $db->prepare("
    SELECT DISTINCT a.*
    FROM academics a
    INNER JOIN academic_services acs ON a.id = acs.academic_id
    WHERE a.status = 'approved'
      AND acs.service_id = ?
    ORDER BY a.rating DESC
");
$academics_stmt->execute([$order['service_id']]);
$matching_academics = $academics_stmt->fetchAll();

// جلب جميع الأكاديميين المعتمدين (للإسناد اليدوي حتى لمن لا يقدم هذه الخدمة)
$all_academics = $db->query("SELECT * FROM academics WHERE status = 'approved' ORDER BY rating DESC, name ASC")->fetchAll();
$matching_ids = array_map('intval', array_column($matching_academics, 'id'));

// Fetch currently assigned academics for this order
$assigned_stmt = $db->prepare("SELECT academic_id FROM order_assignments WHERE order_id = ?");
$assigned_stmt->execute([$order['id']]);
$assigned_academic_ids = $assigned_stmt->fetchAll(PDO::FETCH_COLUMN);

$badge = orderStatusLabel($order['status']);

// Platform fees — نسبة العمولة تُجلب من عمود platform_percent الخاص بالخدمة
$commission_pct = isset($order['service_platform_percent']) ? (float)$order['service_platform_percent'] : 0.00;
$platform_fee = round($order['amount'] * $commission_pct / 100, 2);
$academic_net = round($order['amount'] - $platform_fee, 2);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>تفاصيل الطلب - Eduroad Admin</title>
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
          <div class="breadcrumb"><a href="dashboard.php">الرئيسية</a><span>›</span><a href="orders.php">الطلبات</a><span>›</span><span>تفاصيل الطلب</span></div>
          <h1 class="page-header-title">تفاصيل الطلب <?= e($order['order_number']) ?></h1>
          <p class="page-header-subtitle">تاريخ الطلب: <?= formatDate($order['created_at']) ?></p>
        </div>
        <div class="page-header-actions">
          <a href="orders.php" class="btn btn-outline">← العودة للطلبات</a>
          <button class="btn btn-primary" onclick="window.print()">🖨 طباعة</button>
        </div>
      </div>

      <div class="grid-2-1">

        <!-- Left Column -->
        <div style="display:flex;flex-direction:column;gap:20px">

          <!-- Order Summary -->
          <div class="stat-card animate-fadeInUp delay-1" style="padding:0;overflow:hidden">
            <div style="padding:20px 24px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:white;display:flex;align-items:center;justify-content:space-between">
              <div>
                <h3 style="font-size:18px;font-weight:800"><?= e($order['order_number']) ?></h3>
                <p style="font-size:13px;opacity:0.8;margin-top:2px"><?= e($order['service_icon']) ?> <?= e($order['service_name']) ?> · <?= e($order['package_name'] ?: 'طلب خدمة مخصصة') ?></p>
              </div>
              <div style="text-align:left">
                <div style="font-size:28px;font-weight:900"><?= formatMoney($order['amount']) ?></div>
                <span style="background:rgba(255,255,255,0.2);padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600"><?= $badge['label'] ?></span>
              </div>
            </div>

            <!-- Progress Steps -->
            <div style="padding:24px">
              <div style="display:flex;align-items:center;justify-content:space-between;position:relative;margin-bottom:24px">
                <div style="position:absolute;top:16px;right:40px;left:40px;height:2px;background:var(--border-color);z-index:0"></div>
                <div style="position:absolute;top:16px;right:40px;width:<?= $order['status'] === 'completed' ? '100%' : ($order['status'] === 'in_progress' ? '50%' : '25%') ?>;height:2px;background:var(--primary);z-index:1"></div>
                <div style="position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:8px">
                  <div style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700">✓</div>
                  <span style="font-size:12px;font-weight:600;color:var(--primary)">جديد</span>
                </div>
                <div style="position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:8px">
                  <div style="width:32px;height:32px;border-radius:50%;background:<?= in_array($order['status'], ['in_progress', 'revision', 'completed']) ? 'var(--primary)' : 'var(--border-color)' ?>;color:<?= in_array($order['status'], ['in_progress', 'revision', 'completed']) ? 'white' : 'var(--text-secondary)' ?>;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700">2</div>
                  <span style="font-size:12px;color:<?= in_array($order['status'], ['in_progress', 'revision', 'completed']) ? 'var(--primary)' : 'var(--text-secondary)' ?>">قيد التنفيذ</span>
                </div>
                <div style="position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:8px">
                  <div style="width:32px;height:32px;border-radius:50%;background:<?= in_array($order['status'], ['revision', 'completed']) ? 'var(--primary)' : 'var(--border-color)' ?>;color:<?= in_array($order['status'], ['revision', 'completed']) ? 'white' : 'var(--text-secondary)' ?>;display:flex;align-items:center;justify-content:center;font-size:14px">3</div>
                  <span style="font-size:12px;color:<?= in_array($order['status'], ['revision', 'completed']) ? 'var(--primary)' : 'var(--text-secondary)' ?>">مراجعة</span>
                </div>
                <div style="position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:8px">
                  <div style="width:32px;height:32px;border-radius:50%;background:<?= $order['status'] === 'completed' ? 'var(--success)' : 'var(--border-color)' ?>;color:<?= $order['status'] === 'completed' ? 'white' : 'var(--text-secondary)' ?>;display:flex;align-items:center;justify-content:center;font-size:14px">4</div>
                  <span style="font-size:12px;color:<?= $order['status'] === 'completed' ? 'var(--success)' : 'var(--text-secondary)' ?>">مكتمل</span>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div style="background:var(--bg-main);padding:14px;border-radius:12px">
                  <div class="form-label">تاريخ الإنشاء</div>
                  <div style="font-weight:600;color:var(--text-primary);margin-top:4px"><?= formatDate($order['created_at']) ?></div>
                </div>
                <div style="background:var(--bg-main);padding:14px;border-radius:12px">
                  <div class="form-label">الموعد النهائي</div>
                  <div style="font-weight:600;color:#ef4444;margin-top:4px"><?= formatDate($order['deadline']) ?></div>
                </div>
                <div style="background:var(--bg-main);padding:14px;border-radius:12px">
                  <div class="form-label">المبلغ الإجمالي</div>
                  <div style="font-weight:700;font-size:18px;color:var(--success);margin-top:4px"><?= formatMoney($order['amount']) ?></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Files -->
          <div class="stat-card animate-fadeInUp delay-2" style="padding:20px">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:16px">📎 الملفات المرفوعة</h3>
            <?php if (empty($orderAttachments)): ?>
              <p style="font-size:13px;color:var(--text-secondary)">لا يوجد أي ملفات مرفوعة حالياً للطلب.</p>
            <?php else: ?>
              <div style="display:flex;flex-direction:column;gap:10px">
                <?php foreach ($orderAttachments as $att):
                  $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                  $iconMap = [
                    'pdf' => '📕', 'doc' => '📄', 'docx' => '📄',
                    'xls' => '📊', 'xlsx' => '📊',
                    'ppt' => '📽️', 'pptx' => '📽️',
                    'zip' => '🗜️', 'rar' => '🗜️',
                    'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'webp' => '🖼️',
                    'txt' => '📝',
                  ];
                  $attIcon = $iconMap[$ext] ?? '📎';
                  $attSize = (float) $att['file_size'];
                  $attSizeTxt = $attSize >= 1048576
                    ? number_format($attSize / 1048576, 2) . ' MB'
                    : number_format($attSize / 1024, 1) . ' KB';
                  $attPath = $adminAppBase . '/' . $att['file_path'];
                  $attDate = !empty($att['created_at']) ? formatDate($att['created_at']) : '';
                ?>
                  <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--bg-main);border-radius:12px;border:1px solid var(--border-color)">
                    <div style="font-size:26px;flex-shrink:0"><?= $attIcon ?></div>
                    <div style="flex:1;min-width:0">
                      <div style="font-weight:700;font-size:13px;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($att['file_name']) ?>"><?= e($att['file_name']) ?></div>
                      <div style="font-size:11px;color:var(--text-secondary);margin-top:2px">
                        <?= e($attSizeTxt) ?><?= $attDate ? ' · ' . e($attDate) : '' ?>
                      </div>
                    </div>
                    <a href="<?= e($attPath) ?>" target="_blank" class="btn btn-outline" style="padding:6px 14px;font-size:12px;flex-shrink:0">عرض 👁️</a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Notes -->
          <div class="stat-card animate-fadeInUp delay-3" style="padding:20px">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:16px">📝 ملاحظات الطلب</h3>
            <div style="background:var(--bg-main);padding:16px;border-radius:12px;color:var(--text-secondary);font-size:14px;line-height:1.8;margin-bottom:16px">
              <?= nl2br(e($order['description'])) ?>
            </div>
          </div>

        </div>

        <!-- Right Column -->
        <div style="display:flex;flex-direction:column;gap:20px">

          <!-- Student Info -->
          <div class="stat-card animate-fadeInUp delay-1" style="padding:20px">
            <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:16px">👤 بيانات الطالب</h3>
            <div style="display:flex;align-items:center;gap:14px;padding:16px;background:var(--bg-main);border-radius:12px;margin-bottom:12px">
              <div class="table-avatar" style="width:52px;height:52px;border-radius:14px;font-size:20px;background:#6366f1">👨‍🎓</div>
              <div>
                <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?= e($order['student_name']) ?></div>
                <div style="font-size:12px;color:var(--text-secondary)"><?= e($order['student_email']) ?></div>
                <div style="font-size:12px;color:var(--text-secondary)"><?= e($order['student_phone']) ?></div>
              </div>
            </div>
          </div>

          <!-- Academic Info -->
          <div class="stat-card animate-fadeInUp delay-2" style="padding:20px">
            <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:16px">🎓 بيانات الأكاديمي</h3>
            <?php if ($order['academic_id']): ?>
              <div style="display:flex;align-items:center;gap:14px;padding:16px;background:var(--bg-main);border-radius:12px;margin-bottom:12px">
                <div class="table-avatar" style="width:52px;height:52px;border-radius:14px;font-size:20px;background:#10b981">👨‍🏫</div>
                <div>
                  <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?= e($order['academic_name']) ?></div>
                  <div style="font-size:12px;color:var(--text-secondary)"><?= e($order['academic_email']) ?></div>
                  <div style="font-size:12px;color:#f59e0b;font-weight:600">⭐ <?= number_format((float)$order['academic_rating'], 1) ?> تقييم</div>
                </div>
              </div>
              <div style="background:var(--bg-main);padding:10px;border-radius:10px;margin-bottom:8px">
                <div style="font-size:12px;color:var(--text-secondary)">التخصص</div>
                <div style="font-size:13px;font-weight:600;color:var(--text-primary)"><?= e($order['academic_specialty']) ?></div>
              </div>
            <?php else: ?>
              <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">لم يتم قبول الطلب من أكاديمي بعد.</p>
              
              <!-- إسناد الطلب للأكاديميين المرشحين -->
              <div style="border-top:1px dashed var(--border-color);padding-top:16px">
                <h4 style="font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:8px">📋 إسناد الطلب لأكاديمي أو أكثر</h4>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                  <p style="font-size:11px;color:var(--text-secondary);margin:0">الخدمة: <strong style="color:var(--primary)"><?= e($order['service_icon']) ?> <?= e($order['service_name']) ?></strong></p>
                  <button type="button" id="toggleAllBtn" class="btn btn-outline btn-sm" onclick="toggleAllAcademics()">👥 عرض كل الأكاديميين (<?= count($all_academics) ?>)</button>
                </div>
                <form method="POST" action="order-details.php?id=<?= e($order['order_number']) ?>">
                  <input type="hidden" name="action" value="assign_academics">

                  <!-- القائمة الافتراضية: الأكاديميون المتخصصون فقط -->
                  <div id="matchingList" style="display:flex;flex-direction:column;gap:8px;max-height:180px;overflow-y:auto;margin-bottom:12px;padding-left:4px">
                    <?php if (empty($matching_academics)): ?>
                      <p style="font-size:12px;color:var(--text-secondary)">لا يوجد أكاديميون معتمدون يقدّمون هذه الخدمة حالياً — استخدم زر «عرض كل الأكاديميين» للإسناد اليدوي.</p>
                    <?php else: ?>
                      <?php foreach ($matching_academics as $ac): ?>
                        <?php $checked = in_array($ac['id'], $assigned_academic_ids) ? 'checked' : ''; ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--bg-main);border-radius:8px;cursor:pointer;font-size:13px">
                          <input type="checkbox" name="academic_ids[]" value="<?= $ac['id'] ?>" <?= $checked ?> style="width:16px;height:16px;accent-color:var(--primary)" />
                          <div style="flex:1">
                            <span style="font-weight:600;color:var(--text-primary)"><?= e($ac['name']) ?></span>
                            <span style="font-size:10px;color:var(--text-secondary);display:block"><?= e($ac['specialty']) ?> · <?= e($ac['degree']) ?></span>
                          </div>
                        </label>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>

                  <!-- القائمة البديلة: جميع الأكاديميين المعتمدين -->
                  <div id="allAcademicsList" style="display:none;flex-direction:column;gap:8px;max-height:240px;overflow-y:auto;margin-bottom:12px;padding-left:4px">
                    <?php if (empty($all_academics)): ?>
                      <p style="font-size:12px;color:var(--text-secondary)">لا يوجد أكاديميون معتمدون في المنصة حالياً.</p>
                    <?php else: ?>
                      <?php
                      // ترتيب: من يقدم الخدمة أولاً ثم حسب التقييم
                      $sorted_all = $all_academics;
                      usort($sorted_all, function($a, $b) use ($matching_ids) {
                          $aMatch = in_array((int)$a['id'], $matching_ids) ? 1 : 0;
                          $bMatch = in_array((int)$b['id'], $matching_ids) ? 1 : 0;
                          if ($aMatch !== $bMatch) return $bMatch - $aMatch;
                          return (float)$b['rating'] <=> (float)$a['rating'];
                      });
                      foreach ($sorted_all as $ac):
                        $isMatch = in_array((int)$ac['id'], $matching_ids);
                        $checked = in_array($ac['id'], $assigned_academic_ids) ? 'checked' : '';
                      ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--bg-main);border-radius:8px;cursor:pointer;font-size:13px;<?= $isMatch ? 'border:1px solid var(--primary);' : '' ?>">
                          <input type="checkbox" name="academic_ids[]" value="<?= $ac['id'] ?>" <?= $checked ?> style="width:16px;height:16px;accent-color:var(--primary)" />
                          <div style="flex:1">
                            <span style="font-weight:600;color:var(--text-primary)"><?= e($ac['name']) ?></span>
                            <?php if ($isMatch): ?><span style="font-size:10px;color:var(--primary);font-weight:700"> ✓ يقدم هذه الخدمة</span><?php endif; ?>
                            <span style="font-size:10px;color:var(--text-secondary);display:block"><?= e($ac['specialty']) ?> · <?= e($ac['degree']) ?> · ⭐ <?= number_format((float)$ac['rating'], 1) ?></span>
                          </div>
                        </label>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>

                  <button type="submit" class="btn btn-primary btn-block btn-sm" style="justify-content:center;width:100%">💾 حفظ وإرسال الإسناد</button>
                </form>
              </div>
            <?php endif; ?>
          </div>

          <!-- Change Status -->
          <div class="stat-card animate-fadeInUp delay-3" style="padding:20px">
            <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:16px">🔄 تغيير حالة الطلب</h3>
            <form method="POST" action="order-details.php?id=<?= e($order['order_number']) ?>">
              <input type="hidden" name="action" value="update_status">
              <div style="display:flex;flex-direction:column;gap:8px">
                <button type="submit" name="status" value="pending_assignment" class="btn btn-info" style="justify-content:center;background:#6366f1">⏳ بانتظار التعيين</button>
                <button type="submit" name="status" value="new" class="btn btn-info" style="justify-content:center">⭐ طلب جديد</button>
                <button type="submit" name="status" value="in_progress" class="btn btn-warning" style="justify-content:center">⟳ قيد التنفيذ</button>
                <button type="submit" name="status" value="completed" class="btn btn-success" style="justify-content:center">✓ مكتمل</button>
                <hr class="divider" />
                <button type="submit" name="status" value="cancelled" class="btn btn-danger" style="justify-content:center">✕ إلغاء الطلب</button>
              </div>
            </form>
          </div>

          <!-- Payment Summary -->
          <div class="stat-card animate-fadeInUp delay-4" style="padding:20px">
            <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:16px">💰 ملخص الدفع</h3>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-color)">
              <span style="font-size:13px;color:var(--text-secondary)">سعر الباقة</span>
              <span style="font-weight:600"><?= formatMoney($order['amount']) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-color)">
              <span style="font-size:13px;color:var(--text-secondary)">العمولة (<?= number_format($commission_pct, 2) ?>%)</span>
              <span style="font-weight:600;color:var(--primary)"><?= formatMoney($platform_fee) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-color)">
              <span style="font-size:13px;color:var(--text-secondary)">نصيب الأكاديمي</span>
              <span style="font-weight:600;color:var(--success)"><?= formatMoney($academic_net) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0 0">
              <span style="font-size:15px;font-weight:700;color:var(--text-primary)">إجمالي الإيراد</span>
              <span style="font-size:20px;font-weight:800;color:var(--primary)"><?= formatMoney($order['amount']) ?></span>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script>
<script>
  // التبديل بين قائمة المتخصصين وقائمة جميع الأكاديميين عند الإسناد
  function toggleAllAcademics() {
    const matchList = document.getElementById('matchingList');
    const allList = document.getElementById('allAcademicsList');
    const btn = document.getElementById('toggleAllBtn');
    if (!matchList || !allList || !btn) return;

    const showAll = (allList.style.display === 'none' || allList.style.display === '');
    allList.style.display = showAll ? 'flex' : 'none';
    matchList.style.display = showAll ? 'none' : 'flex';
    btn.textContent = showAll ? '🎯 الأكاديميون المتخصصون فقط' : '👥 عرض كل الأكاديميين';
    btn.classList.toggle('btn-primary', showAll);
    btn.classList.toggle('btn-outline', !showAll);
  }
</script>
</body>
</html>
