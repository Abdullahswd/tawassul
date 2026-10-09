<?php
// Script to test server environment and diagnose white screen issues.
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>نتيجة فحص السيرفر (Server Diagnostic Test)</h2>";

$functions_path = __DIR__ . '/config/functions.php';
if (!file_exists($functions_path)) {
    die("<h3 style='color:red;'>❌ ملف config/functions.php غير موجود في هذا المسار.</h3>");
}

require_once $functions_path;
echo "✅ تم تضمين ملف config/functions.php بنجاح.<br>";

// Test if functions exist
$funcs = ['updatePaymentReceiptStatus', 'getAllPaymentReceipts', 'createNotification', 'triggerPusherEvent'];
foreach ($funcs as $f) {
    if (function_exists($f)) {
        echo "✅ الدالة $f() موجودة.<br>";
    } else {
        echo "<b style='color:red;'>❌ الدالة $f() غير موجودة! (يبدو أن الملف لم يتم تحديثه بشكل كامل).</b><br>";
    }
}

// Test database connection and columns
try {
    $db = db();
    echo "✅ تم الاتصال بقاعدة البيانات بنجاح.<br>";
    
    // Check columns
    $tablesToCheck = [
        'payment_receipts' => ['status', 'rejection_reason', 'student_note', 'method_label'],
        'refund_requests' => ['id', 'status', 'admin_note']
    ];
    
    foreach ($tablesToCheck as $table => $columns) {
        $check = $db->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() > 0) {
            echo "✅ الجدول `$table` موجود.<br>";
            foreach ($columns as $col) {
                $colCheck = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
                if ($colCheck->rowCount() > 0) {
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ الحقل `$col` موجود.<br>";
                } else {
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;<b style='color:red;'>❌ الحقل `$col` مفقود!</b><br>";
                }
            }
        } else {
            echo "<b style='color:red;'>❌ الجدول `$table` غير موجود أصلاً!</b><br>";
        }
    }
} catch (Exception $e) {
    echo "<b style='color:red;'>❌ خطأ في قاعدة البيانات:</b> " . htmlspecialchars($e->getMessage());
}

echo "<h3>اذا كانت كل النتائج أعلاه عليها علامة (✅)، فقم بنسخ ولصق هذا الكود في أعلى ملف admin/pages/payments.php لطباعة الأخطاء بشكل مباشر عند الضغط على زر الاعتماد:</h3>";
echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ddd;'>
ini_set('display_errors', 1);
error_reporting(E_ALL);
</pre>";

echo "<br><br><b>يرجى تصوير أو نسخ نتيجة هذه الصفحة وإرسالها لي.</b>";
?>
