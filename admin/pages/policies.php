<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();
$db = db();

// --- TEMPORARY DB MODIFICATIONS ---
try {
    $db->exec("DROP TABLE IF EXISTS policies;");
    $db->exec("CREATE TABLE IF NOT EXISTS policy_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        policy_type ENUM('student', 'academic') NOT NULL,
        icon VARCHAR(20) DEFAULT '📌',
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Add icon column if it doesn't exist
    try {
        $db->exec("ALTER TABLE policy_items ADD COLUMN icon VARCHAR(20) DEFAULT '📌' AFTER policy_type;");
    } catch (Exception $e) {}
    
    // Seed initial content if empty
    $count = (int)$db->query("SELECT COUNT(*) FROM policy_items")->fetchColumn();
    if ($count == 0) {
        $seeds = [
            ['academic', 'البيانات التي نجمعها', 'لضمان تقديمك كخبير موثوق أمام العملاء، نقوم بجمع البيانات المهنية، والشخصية للتواصل، والبيانات المالية.'],
            ['academic', 'السرية والأمانة العلمية', 'يُمنع منعاً باتاً مشاركة، أو نشر، أو تسريب أي ملفات، بيانات، أو خطط بحثية خاصة بالعملاء لأي طرف ثالث، سواء كان فرداً أو جهة أكاديمية.'],
            ['academic', 'حقوق الملكية الفكرية', 'جميع المخرجات، التقارير، والتحليلات التي يتم إنجازها وتسليمها للعميل عبر المنصة تصبح ملكية فكرية حصرية للعميل.'],
            ['academic', 'المستحقات المالية ونموذج العمل', 'تُضاف الأرصدة إلى محفظتك الإلكترونية في المنصة فور الاعتماد النهائي للمشروع. يحق لك تقديم "طلب سحب رصيد" متى ما بلغت أرباحك الحد الأدنى (200 ريال).'],
            
            ['student', 'سياسة الطلبات والتسليم', 'يلتزم الطالب بتوضيح جميع المتطلبات بشكل كامل ودقيق عند رفع الطلب. أي تغيير جوهري بعد الاتفاق قد يتطلب تكاليف إضافية.'],
            ['student', 'الاسترجاع والتعديلات', 'يحق للطالب طلب تعديلات مجانية خلال فترة الضمان المحددة، شرط أن لا تخرج عن نطاق الطلب الأساسي.'],
            ['student', 'سرية البيانات', 'نلتزم بحماية بياناتك الشخصية وتفاصيل طلباتك، ولا يتم مشاركتها إلا مع الأكاديمي المختص لإنجاز العمل.'],
        ];
        
        $ins = $db->prepare("INSERT INTO policy_items (policy_type, icon, title, content, sort_order) VALUES (?, ?, ?, ?, ?)");
        foreach($seeds as $i => $s) {
            $icon = '📌';
            if (mb_strpos($s[1], 'مال') !== false) $icon = '💰';
            else if (mb_strpos($s[1], 'ملكية') !== false) $icon = '⚖️';
            else if (mb_strpos($s[1], 'بيانات') !== false) $icon = '🗂️';
            else if (mb_strpos($s[1], 'استرجاع') !== false) $icon = '🔄';
            else if (mb_strpos($s[1], 'تسليم') !== false) $icon = '📦';
            else if (mb_strpos($s[1], 'سرية') !== false) $icon = '🔒';
            
            $ins->execute([$s[0], $icon, $s[1], $s[2], $i]);
        }
    }
} catch (Exception $e) {}
// --- END TEMPORARY DB MODIFICATIONS ---

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    if ($action === 'get_items') {
        $type = $_POST['type'] ?? 'student';
        $items = $db->prepare("SELECT * FROM policy_items WHERE policy_type = ? ORDER BY sort_order ASC, id ASC");
        $items->execute([$type]);
        echo json_encode(['success' => true, 'items' => $items->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    if ($action === 'save_item') {
        $id = intval($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? 'student';
        $title = trim($_POST['title'] ?? '');
        $icon = trim($_POST['icon'] ?? '📌');
        $content = trim($_POST['content'] ?? '');
        
        if ($id > 0) {
            $db->prepare("UPDATE policy_items SET title = ?, icon = ?, content = ? WHERE id = ?")->execute([$title, $icon, $content, $id]);
        } else {
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM policy_items WHERE policy_type = ?");
            $stmt->execute([$type]);
            $maxOrder = (int)$stmt->fetchColumn();
            $db->prepare("INSERT INTO policy_items (policy_type, icon, title, content, sort_order) VALUES (?, ?, ?, ?, ?)")->execute([$type, $icon, $title, $content, $maxOrder + 1]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'delete_item') {
        $id = intval($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM policy_items WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'reorder_items') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $db->prepare("UPDATE policy_items SET sort_order = ? WHERE id = ?");
            foreach ($order as $index => $id) {
                $stmt->execute([$index, $id]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>إدارة السياسات - Eduroad Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <style>
    .policy-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); }
    .policy-tab { padding: 12px 24px; font-weight: bold; color: var(--text-secondary); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; }
    .policy-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
    .policy-item { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 15px; display: flex; gap: 20px; align-items: flex-start; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.2s; }
    .policy-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-color: var(--primary); }
    .policy-controls { display: flex; flex-direction: column; gap: 8px; }
    .policy-content { flex: 1; }
    .policy-title { font-size: 18px; font-weight: bold; color: var(--text-primary); margin-bottom: 8px; }
    .policy-text { color: var(--text-secondary); line-height: 1.8; font-size: 14px; }
    .btn-icon { background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 6px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-primary); transition: 0.2s; }
    .btn-icon:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
    .btn-icon.danger:hover { background: var(--danger); border-color: var(--danger); }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include '../components/sidebar.php'; ?>
  <div class="main-content">
    <?php include '../components/navbar.php'; ?>
    <div class="page-content">
      <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <h1 class="page-header-title">سياسة الاستخدام والخصوصية</h1>
          <p class="page-header-subtitle">إدارة بنود السياسات المعروضة للأكاديميين والطلاب بشكل ديناميكي</p>
        </div>
        <button class="btn btn-primary" onclick="openPolicyModal()">➕ إضافة بند جديد</button>
      </div>
      
      <div class="policy-tabs">
        <div class="policy-tab active" data-type="academic" onclick="switchTab('academic')">سياسة الأكاديمي</div>
        <div class="policy-tab" data-type="student" onclick="switchTab('student')">سياسة الطالب</div>
      </div>
      
      <div id="policyContainer">
        <!-- Injected via JS -->
      </div>
      
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="policyModal">
  <div class="modal-box" style="max-width:600px;">
    <div class="modal-header">
      <h3 class="modal-title" id="policyModalTitle">إضافة بند جديد</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="policyId" value="0">
      <div style="display:flex; gap:15px; margin-bottom:15px;">
        <div class="form-group" style="width:100px;">
          <label class="form-label">الأيقونة</label>
          <input type="text" class="form-input text-center" id="policyIcon" placeholder="📌" maxlength="5" required style="font-size:24px;">
          <small style="font-size:11px; color:#888; display:block; margin-top:4px;">(Win + .)</small>
        </div>
        <div class="form-group" style="flex:1;">
          <label class="form-label">عنوان البند</label>
          <input type="text" class="form-input" id="policyTitle" placeholder="مثال: حقوق الملكية الفكرية" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">تفاصيل البند (المحتوى)</label>
        <textarea class="form-input" id="policyContent" rows="6" placeholder="اكتب الشرح هنا..." required style="line-height:1.8;"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" onclick="savePolicyItem()">💾 حفظ البند</button>
    </div>
  </div>
</div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-header">
      <h3 class="modal-title" id="confirmTitle">تأكيد الإجراء</h3>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body text-center" style="padding:30px 20px;">
      <div style="font-size:56px;margin-bottom:15px;">⚠️</div>
      <p id="confirmMessage" style="color:#64748b; font-size:16px; font-weight:500;"></p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close>إلغاء</button>
      <button class="btn btn-primary" style="background:#ef4444; border-color:#ef4444;" id="confirmBtn">حذف نهائي</button>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
const csrfTokenJs = document.querySelector('meta[name="csrf-token"]')?.content || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>';

let currentType = 'academic';
let currentItems = [];

function switchTab(type) {
    currentType = type;
    document.querySelectorAll('.policy-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.policy-tab[data-type="${type}"]`).classList.add('active');
    loadItems();
}

function loadItems() {
    const fd = new FormData();
  fd.append('csrf_token', csrfTokenJs);
    fd.append('action', 'get_items');
    fd.append('type', currentType);
    fetch('policies.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
          if (res.success) {
              currentItems = res.items;
              renderItems();
          }
      });
}

function renderItems() {
    const container = document.getElementById('policyContainer');
    container.innerHTML = '';
    if (currentItems.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#888;">لا توجد بنود حالياً. قم بإضافة بند جديد.</div>';
        return;
    }
    
    currentItems.forEach((item, idx) => {
        const isFirst = idx === 0;
        const isLast = idx === currentItems.length - 1;
        
        container.innerHTML += `
        <div class="policy-item">
          <div class="policy-controls">
            <button class="btn-icon" onclick="moveItem(${idx}, -1)" ${isFirst ? 'style="opacity:0.3;cursor:not-allowed;" disabled' : ''} title="تحريك لأعلى">▲</button>
            <button class="btn-icon" onclick="moveItem(${idx}, 1)" ${isLast ? 'style="opacity:0.3;cursor:not-allowed;" disabled' : ''} title="تحريك لأسفل">▼</button>
          </div>
          <div class="policy-content">
            <div class="policy-title"><span style="margin-left:8px;">${item.icon || '📌'}</span> ${escapeHtml(item.title)}</div>
            <div class="policy-text">${escapeHtml(item.content).replace(/\\n/g, '<br>')}</div>
          </div>
          <div class="policy-controls" style="flex-direction:row;">
            <button class="btn-icon" onclick="openPolicyModal(${idx})" title="تعديل">✏️</button>
            <button class="btn-icon danger" onclick="deleteItem(${item.id})" title="حذف">🗑️</button>
          </div>
        </div>
        `;
    });
}

function moveItem(index, direction) {
    if (index + direction < 0 || index + direction >= currentItems.length) return;
    
    // Swap in array
    const temp = currentItems[index];
    currentItems[index] = currentItems[index + direction];
    currentItems[index + direction] = temp;
    
    // Re-render UI immediately for responsiveness
    renderItems();
    
    // Send new order to server
    const newOrder = currentItems.map(item => item.id);
    const fd = new FormData();
  fd.append('csrf_token', csrfTokenJs);
    fd.append('action', 'reorder_items');
    fd.append('order', JSON.stringify(newOrder));
    fetch('policies.php', { method: 'POST', body: fd });
}

function openPolicyModal(index = -1) {
    if (index >= 0) {
        const item = currentItems[index];
        document.getElementById('policyId').value = item.id;
        document.getElementById('policyIcon').value = item.icon || '📌';
        document.getElementById('policyTitle').value = item.title;
        document.getElementById('policyContent').value = item.content;
        document.getElementById('policyModalTitle').textContent = 'تعديل البند';
    } else {
        document.getElementById('policyId').value = 0;
        document.getElementById('policyIcon').value = '📌';
        document.getElementById('policyTitle').value = '';
        document.getElementById('policyContent').value = '';
        document.getElementById('policyModalTitle').textContent = 'إضافة بند جديد';
    }
    Modal.open('policyModal');
}

function savePolicyItem() {
    const id = document.getElementById('policyId').value;
    const title = document.getElementById('policyTitle').value.trim();
    const icon = document.getElementById('policyIcon').value.trim();
    const content = document.getElementById('policyContent').value.trim();
    
    if (!title || !content) {
        Toast.show('الرجاء إدخال العنوان والتفاصيل', 'error');
        return;
    }
    
    const fd = new FormData();
  fd.append('csrf_token', csrfTokenJs);
    fd.append('action', 'save_item');
    fd.append('id', id);
    fd.append('type', currentType);
    fd.append('icon', icon || '📌');
    fd.append('title', title);
    fd.append('content', content);
    
    fetch('policies.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
          if (res.success) {
              Toast.show('تم الحفظ بنجاح', 'success');
              Modal.close('policyModal');
              loadItems();
          } else {
              Toast.show('حدث خطأ أثناء الحفظ', 'error');
          }
      });
}

function deleteItem(id) {
    Modal.confirm('تأكيد الحذف', 'هل أنت متأكد من حذف هذا البند بشكل نهائي؟', () => {
        const fd = new FormData();
  fd.append('csrf_token', csrfTokenJs);
        fd.append('action', 'delete_item');
        fd.append('id', id);
        
        fetch('policies.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(res => {
              if (res.success) {
                  Toast.show('تم الحذف بنجاح', 'success');
                  loadItems();
              }
          });
    });
}

function escapeHtml(unsafe) {
    return (unsafe || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', loadItems);
</script>
</body>
</html>
