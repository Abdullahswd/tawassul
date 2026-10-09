const fs = require('fs');
let content = fs.readFileSync('academics/academic-orders.php', 'utf8');

// Replace loop
const loopRegex = /foreach \(\$orders as \$o\) \{\s*\$ordersJson\[\] = \[\s*'id' => \$o\['order_number'\],\s*'db_id' => \(int\)\$o\['id'\],\s*'student' => \$o\['student_name'\] \?\? '[^']*',\s*'service' => \$o\['service_name'\] \?\? '[^']*',\s*'package' => \$o\['package_name'\] \?\? '[^']*',\s*'amount' => \(float\)\$o\['amount'\],/;
const loopReplace = `foreach ($orders as $o) {
    $platform_pct = isset($o['platform_percent']) ? (float)$o['platform_percent'] : 0.00;
    $amount = (float)$o['amount'];
    $platform_fee = round($amount * $platform_pct / 100, 2);
    $academic_net = round($amount - $platform_fee, 2);

    $ordersJson[] = [
        'id' => $o['order_number'],
        'db_id' => (int)$o['id'],
        'student' => $o['student_name'] ?? 'طالب غير معروف',
        'service' => $o['service_name'] ?? 'خدمة عامة',
        'package' => $o['package_name'] ?? 'باقة فردية',
        'amount' => $amount,
        'academic_net' => $academic_net,
        'platform_pct' => $platform_pct,`;

content = content.replace(loopRegex, loopReplace);

// Table Header
content = content.replace(/<th>المبلغ<\/th>/g, '<th>السعر الأصلي</th><th>أرباحك (الصافي)</th>');

// JS row replacement
const trRegex = /<td><strong[^>]*>\$\{o\.amount\}.*?<\/strong><\/td>/g;
const trReplace = `<td><div style="font-size:13px;text-decoration:line-through;color:var(--text-secondary)">\${o.amount} ر.س</div></td>
        <td>
          <div style="font-size:15px;font-weight:700;color:#10b981">\${o.academic_net} ر.س</div>
          <div style="font-size:11px;color:var(--text-secondary)">عمولة المنصة \${o.platform_pct}%</div>
        </td>`;

content = content.replace(trRegex, trReplace);

fs.writeFileSync('academics/academic-orders.php', content);
console.log('done');
