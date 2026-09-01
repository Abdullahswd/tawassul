<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$order_id = (int)($_GET['id'] ?? 0);

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

$order = getOrderById($order_id);

if (!$order || $order['student_id'] !== $user['id']) {
    header('Location: orders.php');
    exit;
}

// Fetch assigned team of academics
$assignedTeam = getAssignedAcademics($order_id);

// Find active conversation
$db = db();
$convStmt = $db->prepare("SELECT id FROM conversations WHERE order_id = ? AND student_id = ? LIMIT 1");
$convStmt->execute([$order_id, $user['id']]);
$convId = (int)$convStmt->fetchColumn();

$badge = orderStatusLabel($order['status']);
?>
<?php
$extraCss = [
  '<style>
    /* Timeline styles */
    .timeline {
      display: flex;
      flex-direction: column;
      position: relative;
      padding-right: 20px;
    }
    .timeline::before {
      content: "";
      position: absolute;
      top: 0; bottom: 0; right: 0;
      width: 2px;
      background: var(--border-color);
    }
    .timeline-item {
      position: relative;
      margin-bottom: 32px;
      padding-right: 24px;
    }
    .timeline-item:last-child { margin-bottom: 0; }
    .timeline-item::before {
      content: "";
      position: absolute;
      right: -25px; top: 0px;
      width: 12px; height: 12px;
      border-radius: 50%;
      background: var(--bg-card);
      border: 3px solid var(--border-color);
      z-index: 1;
    }
    .timeline-item.active::before {
      border-color: var(--primary);
      background: var(--primary);
      box-shadow: 0 0 0 4px var(--primary-light);
    }
    .timeline-item.done::before {
      border-color: var(--success);
      background: var(--success);
    }
    .ti-title { font-size: 15px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; }
    .ti-desc { font-size: 13px; color: var(--text-secondary); }
    .ti-date { display: inline-block; font-size: 11px; padding: 2px 8px; background: var(--bg-hover); border-radius: 4px; margin-top: 8px; font-weight: 700; }
  </style>',
];
$pageTitle  = 'تفاصيل الطلب ' . $order['order_number'];
$activePage = 'orders';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>

      <!-- Page Content -->
      <div class="content-wrap" style="max-width:1200px;margin:0 auto">
        
        <div style="margin-bottom:24px">
          <a href="orders.php" style="color:var(--text-secondary);font-size:14px">← العودة لقائمة الطلبات</a>
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
          
          <!-- Left Column -->
          <div style="display:flex;flex-direction:column;gap:24px">
            
            <!-- Order Header Card -->
            <div class="card">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
                <div>
                  <span style="font-size:12px;color:var(--text-secondary);font-weight:700">رقم الطلب: <?= e($order['order_number']) ?></span>
                  <h1 class="h2" style="margin-top:4px"><?= e($order['service_icon']) ?> <?= e($order['service_name']) ?></h1>
                </div>
                <span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;background:var(--bg-body);padding:16px;border-radius:var(--radius-md);border:1px solid var(--border-color)">
                <div>
                  <div style="font-size:12px;color:var(--text-secondary)">التخصص المطلوب:</div>
                  <div style="font-size:14px;font-weight:700;color:var(--text-primary);margin-top:2px"><?= e($order['specialty']) ?></div>
                </div>
                <div>
                  <div style="font-size:12px;color:var(--text-secondary)">المبلغ الإجمالي:</div>
                  <div style="font-size:16px;font-weight:900;color:var(--primary);margin-top:2px"><?= formatMoney($order['amount']) ?></div>
                </div>
              </div>
            </div>

            <!-- Description Card -->
            <div class="card">
              <h2 class="h2" style="margin-bottom:16px">تفاصيل ومتطلبات الخدمة</h2>
              <div style="font-size:14px;color:var(--text-secondary);line-height:1.7;white-space:pre-line">
                <?= e($order['description'] ?: 'لا توجد تفاصيل إضافية مكتوبة.') ?>
              </div>
            </div>

            <!-- Attachments -->
            <div class="card">
              <h2 class="h2" style="margin-bottom:20px">الملفات المرفوعة</h2>
              <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">لا توجد ملفات مرفوعة حالياً للطلب.</p>

              <!-- Draggable file upload UI -->
              <div style="border:2px dashed var(--border-color);border-radius:var(--radius-sm);padding:24px;text-align:center;background:var(--bg-body);margin-top:20px;cursor:pointer">
                <h4 style="font-weight:700;margin-bottom:8px;color:var(--primary)">إضافة ملفات جديدة ➕</h4>
                <p style="font-size:12px;color:var(--text-secondary)">اسحب وأفلت الملفات هنا أو اضغط للاختيار</p>
              </div>
            </div>

          </div>

          <!-- Right Column -->
          <div style="display:flex;flex-direction:column;gap:24px">

          <!-- Academic Profile / Team -->
            <div class="card" style="padding:24px">
              <?php 
              $acceptedTeam = array_filter($assignedTeam, fn($m) => $m['assignment_status'] === 'accepted');
              $pendingTeam  = array_filter($assignedTeam, fn($m) => $m['assignment_status'] === 'assigned');
              ?>
              <?php if (!empty($acceptedTeam)): ?>
                <h3 class="h3" style="margin-bottom:16px">
                  🎓 <?= count($acceptedTeam) > 1 ? 'فريق الأكاديميين المُعيَّن' : 'الأكاديمي المُعيَّن' ?>
                </h3>
                <?php foreach ($acceptedTeam as $member): ?>
                  <div style="display:flex;align-items:center;gap:14px;background:var(--bg-body);border-radius:12px;padding:14px;margin-bottom:10px;border:1px solid var(--border-color)">
                    <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--secondary),var(--primary));color:white;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;flex-shrink:0">
                      <?= e($member['avatar_initials'] ?: mb_substr($member['name'], 0, 1, 'UTF-8')) ?>
                    </div>
                    <div style="flex:1">
                      <div style="font-weight:700;font-size:14px;color:var(--text-primary)"><?= e($member['name']) ?></div>
                      <div style="font-size:12px;color:var(--text-secondary)"><?= e($member['specialty']) ?> · <?= e($member['degree']) ?></div>
                      <div style="font-size:12px;color:#f59e0b;font-weight:600">⭐ <?= number_format((float)$member['rating'], 1) ?> تقييم</div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php if ($convId): ?>
                  <a href="chat.php?conv=<?= $convId ?>" class="btn btn-primary" style="width:100%;margin-top:8px;text-align:center">
                    💬 <?= count($acceptedTeam) > 1 ? 'محادثة الفريق' : 'تواصل مع الأكاديمي' ?>
                  </a>
                <?php else: ?>
                  <a href="chat.php?order_id=<?= $order_id ?>" class="btn btn-primary" style="width:100%;margin-top:8px;text-align:center">
                    💬 فتح المحادثة
                  </a>
                <?php endif; ?>

              <?php elseif (!empty($pendingTeam)): ?>
                <div style="text-align:center;padding:8px 0 16px">
                  <div style="width:64px;height:64px;border-radius:50%;background:rgba(245,158,11,0.1);border:2px solid #f59e0b;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 12px">⏳</div>
                  <h3 class="h3" style="margin-bottom:6px;color:var(--text-primary)">بانتظار رد الأكاديمي</h3>
                  <p style="font-size:13px;color:var(--text-secondary);line-height:1.5">
                    تم إرسال طلبك إلى <?= count($pendingTeam) ?> أكاديمي/أكاديميين. بانتظار قبول أحدهم للعمل عليه.
                  </p>
                </div>
              <?php else: ?>
                <div style="text-align:center;padding:8px 0 16px">
                  <div style="width:64px;height:64px;border-radius:50%;background:var(--bg-body);border:1.5px dashed var(--border-color);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 12px">⏳</div>
                  <h3 class="h3" style="margin-bottom:6px;color:var(--text-secondary)">قيد التعيين</h3>
                  <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;line-height:1.5">يقوم فريق الدعم بمراجعة الطلب لربطك بأفضل أكاديمي متخصص.</p>
                  <button class="btn btn-outline" style="width:100%" disabled>بانتظار الأكاديمي...</button>
                </div>
              <?php endif; ?>
            </div>

            <!-- Timeline -->
            <div class="card" style="padding:24px">
              <h3 class="h3" style="margin-bottom:24px">متابعة سير الطلب</h3>
              
              <div class="timeline">
                <div class="timeline-item done">
                  <div class="ti-title">تم تقديم الطلب</div>
                  <div class="ti-desc">تم تسجيل طلبك بنجاح على المنصة.</div>
                  <div class="ti-date"><?= formatDate($order['created_at']) ?></div>
                </div>
                <div class="timeline-item <?= in_array($order['status'], ['assigned', 'accepted', 'in_progress', 'revision', 'completed']) ? 'done' : (($order['status'] === 'pending_assignment') ? 'active' : '') ?>">
                  <div class="ti-title">مراجعة الإدارة</div>
                  <div class="ti-desc">
                    <?= ($order['status'] === 'pending_assignment') ? 'جاري مراجعة الطلب وتعيين الأكاديمي المناسب.' : 'تمت مراجعة الطلب من قِبل الإدارة.' ?>
                  </div>
                </div>
                <div class="timeline-item <?= in_array($order['status'], ['accepted', 'in_progress', 'revision', 'completed']) ? 'done' : (($order['status'] === 'assigned') ? 'active' : '') ?>">
                  <div class="ti-title">الربط والتعيين</div>
                  <div class="ti-desc">
                    <?= $order['academic_id'] ? 'تم تعيين الأكاديمي وقبول العمل.' : (($order['status'] === 'assigned') ? 'تم إرسال الطلب للأكاديمي، بانتظار القبول.' : 'جاري البحث عن أكاديمي متخصص.') ?>
                  </div>
                </div>
                <div class="timeline-item <?= ($order['status'] === 'completed') ? 'done' : (in_array($order['status'], ['in_progress', 'revision']) ? 'active' : '') ?>">
                  <div class="ti-title">قيد التنفيذ</div>
                  <div class="ti-desc">يقوم الأكاديمي حالياً بالعمل على الملفات.</div>
                </div>
                <div class="timeline-item <?= ($order['status'] === 'completed') ? 'done' : '' ?>">
                  <div class="ti-title">اكتمال وتسليم</div>
                  <div class="ti-desc">مراجعة الملفات النهائية وتنزيلها.</div>
                </div>
              </div>
            </div>

          </div>

        </div>

      </div>
<?php require __DIR__ . '/partials/footer.php'; ?>
