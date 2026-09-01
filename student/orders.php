<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$orders = getOrdersByStudent($user['id']);

// Simple filter logic via PHP array filters
$filter = $_GET['filter'] ?? 'all';
if ($filter === 'progress') {
    $orders = array_filter($orders, function($o) {
        return in_array($o['status'], ['new', 'pending_assignment', 'assigned', 'accepted', 'in_progress', 'revision']);
    });
} elseif ($filter === 'completed') {
    $orders = array_filter($orders, function($o) {
        return $o['status'] === 'completed';
    });
}

// Counts for filter bar
$db = db();
$cnt_all = (int) $db->query("SELECT COUNT(*) FROM orders WHERE student_id = " . $user['id'])->fetchColumn();
$cnt_progress = (int) $db->query("SELECT COUNT(*) FROM orders WHERE student_id = " . $user['id'] . " AND status IN ('new', 'pending_assignment', 'assigned', 'accepted', 'in_progress', 'revision')")->fetchColumn();
$cnt_completed = (int) $db->query("SELECT COUNT(*) FROM orders WHERE student_id = " . $user['id'] . " AND status = 'completed'")->fetchColumn();

$pageTitle  = 'طلباتي';
$activePage = 'orders';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>

      <!-- PAGE CONTENT -->
      <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:32px">
          <div>
            <h1 class="h1" style="margin-bottom:8px">طلباتي</h1>
            <p class="text-body">إدارة ومتابعة كافة طلباتك السابقة والحالية.</p>
          </div>
          <a href="create-order.php" class="btn btn-primary">➕ طلب جديد</a>
        </div>

        <!-- Filters -->
        <div style="display:flex;gap:12px;margin-bottom:24px;overflow-x:auto;padding-bottom:8px">
          <a href="orders.php?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:40px;padding:8px 20px">الكل (<?= $cnt_all ?>)</a>
          <a href="orders.php?filter=progress" class="btn <?= $filter === 'progress' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:40px;padding:8px 20px;color:var(--text-secondary);border-color:var(--border-color)">قيد التنفيذ (<?= $cnt_progress ?>)</a>
          <a href="orders.php?filter=completed" class="btn <?= $filter === 'completed' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:40px;padding:8px 20px;color:var(--text-secondary);border-color:var(--border-color)">مكتملة (<?= $cnt_completed ?>)</a>
        </div>

        <!-- Orders Table -->
        <div class="card" style="padding:0;overflow:hidden">
          <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:right">
              <thead>
                <tr>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">رقم الطلب</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">الخدمة والتخصص</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">تاريخ الطلب</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">السعر الإجمالي</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">الحالة</th>
                  <th style="padding:16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-color);background:var(--bg-body)">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($orders)): ?>
                  <tr>
                    <td colspan="6" style="text-align:center;padding:40px;color:var(--text-secondary)">
                      لا توجد أي طلبات مطابقة للفلتر المحدد.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($orders as $o): 
                    $badge = orderStatusLabel($o['status']);
                  ?>
                    <tr>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color); font-weight:700"><?= e($o['order_number']) ?></td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color)">
                        <div style="font-weight:700;color:var(--text-primary)"><?= e($o['service_icon']) ?> <?= e($o['service_name']) ?></div>
                        <div style="font-size:12px;color:var(--text-secondary)"><?= e($o['specialty']) ?></div>
                      </td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color); color:var(--text-secondary)"><?= formatDate($o['created_at']) ?></td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color); font-weight:700; color:var(--primary)"><?= formatMoney($o['amount']) ?></td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color)">
                        <span class="badge <?= $badge['class'] ?>">
                          <?= $badge['label'] ?>
                        </span>
                      </td>
                      <td style="padding:16px; border-bottom:1px solid var(--border-color)">
                        <a href="order-details.php?id=<?= $o['id'] ?>" class="btn btn-outline" style="padding:6px 12px;font-size:13px">التفاصيل</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

<?php require __DIR__ . '/partials/footer.php'; ?>
