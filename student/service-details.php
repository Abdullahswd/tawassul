<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
requireStudent();

$user = currentUser();
$service_id = (int)($_GET['id'] ?? 0);

if (!$service_id) {
    header('Location: services.php');
    exit;
}

$service = getServiceById($service_id);
if (!$service) {
    header('Location: services.php');
    exit;
}

// Fetch packages to show alongside this service
$packages = getAllPackages();

// Fetch presentation stats (completed orders + average rating) for this service
$db = db();
$stats_stmt = $db->prepare(
    'SELECT COUNT(DISTINCT o.id) AS total_orders,
            AVG(r.rating)        AS avg_rating,
            COUNT(DISTINCT r.id) AS total_reviews
     FROM orders o
     LEFT JOIN reviews r ON r.order_id = o.id
     WHERE o.service_id = ? AND o.status = "completed"'
);
$stats_stmt->execute([$service_id]);
$stats = $stats_stmt->fetch() ?: [];

// Normalise the derived values before using them in the template.
$completedCount = (int)($stats['total_orders'] ?? 0);
$avgRating      = (float)($stats['avg_rating'] ?? 0);
$hasRating      = $avgRating > 0;

// Real price from the services table (fallback to a friendly message if 0/NULL).
$servicePrice = (float)($service['price'] ?? 0);
$priceLabel   = $servicePrice > 0 ? formatMoney($servicePrice) : 'حسب المتطلبات';

// Features are optional (column may be absent) → build a safe, trimmed list.
$featureList = array_values(array_filter(
    array_map('trim', explode("\n", (string)($service['features'] ?? '')))
));
?>
<?php
$pageTitle  = 'تفاصيل الخدمة';
$activePage = 'services';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
?>

      <div style="max-width:1000px;margin:0 auto">
        
        <div style="margin-bottom:24px;">
          <a href="services.php" style="color:var(--text-secondary);font-size:14px">← العودة لقائمة الخدمات</a>
        </div>

        <div class="card" style="padding:40px;position:relative;overflow:hidden;margin-bottom:24px">
          <div style="position:absolute;top:0;left:0;right:0;height:120px;background:linear-gradient(135deg, var(--primary-light), transparent);z-index:0"></div>
          
          <div style="position:relative;z-index:1;display:flex;flex-direction:column;gap:24px">
            <div style="font-size:56px"><?= e($service['icon'] ?: '📦') ?></div>
            <h1 class="h1"><?= e($service['name']) ?></h1>
            
            <p style="font-size:16px;color:var(--text-secondary);line-height:1.8;max-width:800px">
              <?= e($service['description'] ?: 'خدمة أكاديمية متكاملة مقدمة من نخبة من المتخصصين والأكاديميين ذوي الخبرة.') ?>
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;background:var(--bg-body);padding:24px;border-radius:var(--radius-md);border:1px solid var(--border-color)">
              <div>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">يبدأ من</div>
                <div style="font-size:24px;font-weight:900;color:var(--primary)"><?= e($priceLabel) ?></div>
              </div>
              <div>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">مدة التنفيذ</div>
                <div style="font-size:18px;font-weight:700;color:var(--text-primary)">تعتمد على المتطلبات</div>
              </div>
              <div>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">عدد المشاريع المكتملة</div>
                <div style="font-size:18px;font-weight:700;color:var(--text-primary)">+<?= $completedCount ?> مشروع</div>
              </div>
              <div>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">متوسط التقييم</div>
                <?php if ($hasRating): ?>
                  <div style="font-size:18px;font-weight:700;color:#f59e0b">★ <?= number_format($avgRating, 1) ?> / 5</div>
                <?php else: ?>
                  <div style="font-size:16px;font-weight:700;color:var(--text-secondary)">لا توجد تقييمات بعد</div>
                <?php endif; ?>
              </div>
            </div>

            <div style="display:flex;gap:12px;margin-top:10px">
              <a href="create-order.php?service_id=<?= $service['id'] ?>" class="btn btn-primary">اطلب الخدمة الآن 🚀</a>
              <a href="chat.php" class="btn btn-outline" style="color:var(--text-secondary);border-color:var(--border-color)">💬 استفسار قبل الطلب</a>
            </div>

            <?php if (!empty($featureList)): ?>
            <div style="padding-top:24px">
              <h3 class="h3" style="margin-bottom:16px">مميزات الخدمة:</h3>
              <ul style="list-style:none;padding:0;color:var(--text-secondary);line-height:2">
                <?php foreach ($featureList as $feature): ?>
                  <li>✅ <?= e($feature) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- Available Packages -->
        <?php if (!empty($packages)): ?>
        <div class="card">
          <h2 class="h2" style="margin-bottom:20px">الباقات المتاحة</h2>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px">
            <?php foreach ($packages as $pkg): ?>
              <div style="border:2px solid var(--border-color);border-radius:var(--radius-md);padding:24px;transition:all 0.2s;cursor:pointer" 
                   onmouseover="this.style.borderColor='var(--primary)';this.style.transform='translateY(-2px)'" 
                   onmouseout="this.style.borderColor='var(--border-color)';this.style.transform='none'">
                <div style="font-size:28px;margin-bottom:12px"><?= e($pkg['icon'] ?? '📦') ?></div>
                <h3 style="font-weight:800;margin-bottom:8px"><?= e($pkg['name']) ?></h3>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px"><?= e($pkg['description'] ?? '') ?></p>
                <div style="font-size:24px;font-weight:900;color:var(--primary);margin-bottom:16px"><?= formatMoney($pkg['price']) ?></div>
                <a href="create-order.php?service_id=<?= $service['id'] ?>&package_id=<?= $pkg['id'] ?>" class="btn btn-primary" style="width:100%">اختر هذه الباقة</a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>

<?php require __DIR__ . '/partials/footer.php'; ?>
