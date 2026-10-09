const fs = require('fs');
let content = fs.readFileSync('student/create-order.php', 'utf8');

const find = `      // سجل دفع (يُتخطَّى للخدمات المشمولة في الباقة لأنها مجانية)
      if ($student_payable > 0) {
        $methodId = (int) ($_POST['payment_method_id'] ?? 0);
        $methodType = 'bank_transfer'; // افتراضي
        if ($methodId) {
            foreach ($paymentMethods as $pm) {
                if ((int)$pm['id'] === $methodId) {
                    $methodType = $pm['account_type'] ?? 'bank_transfer';
                    break;
                }
            }
        }
        createPayment([
          'order_id' => $order_id,
          'student_id' => $user['id'],
          'amount' => $student_payable,
          'method' => $methodType
        ]);
      }`;

const replace = `      // جلب نسبة المنصة للخدمة المطلوبة
      $stmtPct = $db->prepare('SELECT platform_percent FROM services WHERE id = ? LIMIT 1');
      $stmtPct->execute([$service_id]);
      $platformPercent = (float) $stmtPct->fetchColumn();

      // سجل دفع
      if ($student_payable > 0) {
        // دفع تقليدي
        $methodId = (int) ($_POST['payment_method_id'] ?? 0);
        $methodType = 'bank_transfer'; // افتراضي
        if ($methodId) {
            foreach ($paymentMethods as $pm) {
                if ((int)$pm['id'] === $methodId) {
                    $methodType = $pm['account_type'] ?? 'bank_transfer';
                    break;
                }
            }
        }
        createPayment([
          'order_id' => $order_id,
          'student_id' => $user['id'],
          'amount' => $student_payable,
          'method' => $methodType,
          'platform_percent' => $platformPercent,
          'status' => 'pending'
        ]);
      } elseif ($amount > 0 && $isIncludedSvc) {
        // دفع عن طريق الباقة (يتم تسجيله كمدفوع مباشرة لضمان أرباح الأكاديمي)
        createPayment([
          'order_id' => $order_id,
          'student_id' => $user['id'],
          'amount' => $amount,
          'method' => 'wallet', // أو يمكن إضافة نوع جديد payment_method مثل package
          'platform_percent' => $platformPercent,
          'status' => 'paid'
        ]);
      }`;

content = content.replace(find, replace);
fs.writeFileSync('student/create-order.php', content);
console.log('done');
