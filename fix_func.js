const fs = require('fs');
let content = fs.readFileSync('config/functions.php', 'utf8');

const find = `function createPayment(array $data): int {
    $fee    = round($data['amount'] * 0.15, 2);
    $net    = round($data['amount'] - $fee, 2);
    $number = generatePaymentNumber();

    $stmt = db()->prepare(
        'INSERT INTO payments
           (payment_number, order_id, student_id, amount, platform_fee,
            academic_net, method, status)
         VALUES (?,?,?,?,?,?,?,"pending")'
    );
    $stmt->execute([
        $number,
        $data['order_id'],
        $data['student_id'],
        $data['amount'],
        $fee,
        $net,
        $data['method']
    ]);
    return (int) db()->lastInsertId();
}`;

const replace = `function createPayment(array $data): int {
    $pct    = isset($data['platform_percent']) ? (float)$data['platform_percent'] : 15.0;
    $fee    = round($data['amount'] * ($pct / 100), 2);
    $net    = round($data['amount'] - $fee, 2);
    $number = generatePaymentNumber();
    $status = $data['status'] ?? 'pending';

    $stmt = db()->prepare(
        'INSERT INTO payments
           (payment_number, order_id, student_id, amount, platform_fee,
            academic_net, method, status, paid_at)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    
    $paid_at = ($status === 'paid') ? date('Y-m-d H:i:s') : null;
    
    $stmt->execute([
        $number,
        $data['order_id'],
        $data['student_id'],
        $data['amount'],
        $fee,
        $net,
        $data['method'],
        $status,
        $paid_at
    ]);
    return (int) db()->lastInsertId();
}`;

content = content.replace(find, replace);
fs.writeFileSync('config/functions.php', content);
console.log('done');
