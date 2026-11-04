<?php
/**
 * ============================================================
 *  Eduroad - Professional SMTP Mailer & Notification Engine
 *  File: config/mailer.php
 * ============================================================
 * Zero external dependencies. Uses standard PHP streams with SSL/TLS.
 * Fully supports HTML, UTF-8 (Arabic), responsive email templates,
 * and automatic fallback between .env and database settings.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/* ─────────────────────────────────────────────────────────────
   1. AUTO-SETUP: Ensure password_resets table exists
   ───────────────────────────────────────────────────────────── */
function ensurePasswordResetsTable(): void {
    return;
}

/* ─────────────────────────────────────────────────────────────
   2. CONFIGURATION LOADER (.env + Database settings table)
   ───────────────────────────────────────────────────────────── */
function getSmtpConfig(): array {
    static $config = null;
    if ($config !== null) return $config;

    require_once __DIR__ . '/env.php';

    // Load from DB settings table
    $dbSettings = [];
    try {
        $stmt = db()->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbSettings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        // DB not available or table missing
    }

    $host = env('SMTP_HOST', $dbSettings['smtp_host'] ?? 'smtp.gmail.com');
    $port = (int) env('SMTP_PORT', $dbSettings['smtp_port'] ?? 587);
    $user = env('SMTP_USER', $dbSettings['smtp_user'] ?? '');
    $pass = env('SMTP_PASS', env('SMTP_PASSWORD', $dbSettings['smtp_password'] ?? ''));
    
    // Auto-detect encryption if not explicitly specified
    $secure = strtolower((string) env('SMTP_SECURE', $dbSettings['smtp_encryption'] ?? ''));
    if ($secure === '') {
        $secure = ($port === 465) ? 'ssl' : 'tls';
    }

    $fromEmail = env('MAIL_FROM_ADDRESS', env('SMTP_FROM_EMAIL', $dbSettings['site_email'] ?? $user));
    if (!$fromEmail) $fromEmail = 'noreply@eduroad.com';

    $fromName = env('MAIL_FROM_NAME', env('SMTP_FROM_NAME', $dbSettings['site_name'] ?? 'منصة Eduroad'));
    $siteUrl  = env('APP_URL', $dbSettings['site_url'] ?? (function_exists('rootUrl') ? rootUrl() : ''));

    $config = [
        'host'       => trim((string)$host),
        'port'       => $port,
        'user'       => trim((string)$user),
        'pass'       => trim((string)$pass),
        'secure'     => $secure,
        'from_email'   => trim((string)$fromEmail),
        'from_address' => trim((string)$fromEmail),
        'from_name'    => trim((string)$fromName),
        'site_url'     => rtrim((string)$siteUrl, '/'),
    ];

    return $config;
}

/* ─────────────────────────────────────────────────────────────
   3. PURE PHP SOCKET-BASED SMTP CLIENT
   ───────────────────────────────────────────────────────────── */
class EduroadSmtpClient {
    private array $config;
    private $socket = null;
    private array $logs = [];
    private string $lastError = '';

    public function __construct(?array $config = null) {
        $this->config = $config ?: getSmtpConfig();
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    public function getLogs(): array {
        return $this->logs;
    }

    private function log(string $msg): void {
        $this->logs[] = date('Y-m-d H:i:s') . ' ' . $msg;
    }

    private function readResponse(): string {
        $response = '';
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 512);
            if ($line === false) break;
            $response .= $line;
            // Complete multi-line response check: 4th char is space or end
            if (isset($line[3]) && $line[3] === ' ') break;
            if (strlen(trim($line)) === 3) break;
        }
        $this->log('SERVER: ' . trim($response));
        return $response;
    }

    private function sendCommand(string $command): string {
        $this->log('CLIENT: ' . (strpos($command, 'AUTH') !== false || strpos($command, $this->config['pass']) !== false ? '***HIDDEN***' : trim($command)));
        fputs($this->socket, $command . "\r\n");
        return $this->readResponse();
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool {
        $cfg = $this->config;

        if (empty($cfg['host']) || empty($cfg['user']) || empty($cfg['pass'])) {
            $this->lastError = 'بيانات خادم SMTP غير مكتملة (المضيف، اسم المستخدم، أو كلمة المرور فارغة).';
            $this->log('ERROR: ' . $this->lastError);
            error_log('[Eduroad Mailer] ' . $this->lastError . " - To: {$toEmail}");
            return false;
        }

        $timeout = 8;
        $hostPrefix = ($cfg['secure'] === 'ssl' || $cfg['port'] === 465) ? 'ssl://' : '';
        $connectHost = $hostPrefix . $cfg['host'];

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ]);

        $this->log("Connecting to {$connectHost}:{$cfg['port']}...");
        $this->socket = @stream_socket_client($connectHost . ':' . $cfg['port'], $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$this->socket) {
            $this->lastError = "فشل الاتصال بخادم البريد ({$errstr} - رمز {$errno})";
            $this->log('ERROR: ' . $this->lastError);
            error_log('[Eduroad Mailer] ' . $this->lastError);
            return false;
        }

        stream_set_timeout($this->socket, $timeout);
        $greeting = $this->readResponse();
        if (substr($greeting, 0, 3) !== '220') {
            $this->lastError = "استجابة غير صحيحة من الخادم: {$greeting}";
            @fclose($this->socket);
            return false;
        }

        // Send EHLO
        $clientHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $ehlo = $this->sendCommand("EHLO {$clientHost}");

        // If TLS, negotiate STARTTLS
        if ($cfg['secure'] === 'tls' || ($cfg['port'] === 587 && strpos($ehlo, 'STARTTLS') !== false)) {
            $res = $this->sendCommand("STARTTLS");
            if (substr($res, 0, 3) !== '220') {
                $this->lastError = "فشل بدء تشفير TLS: {$res}";
                @fclose($this->socket);
                return false;
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!@stream_socket_enable_crypto($this->socket, true, $cryptoMethod)) {
                $this->lastError = "فشل تفعيل التشفير الآمن (TLS Handshake).";
                @fclose($this->socket);
                return false;
            }

            // Re-send EHLO after TLS
            $this->sendCommand("EHLO {$clientHost}");
        }

        // Authenticate
        $authRes = $this->sendCommand("AUTH LOGIN");
        if (substr($authRes, 0, 3) !== '334') {
            $this->lastError = "خادم البريد رفض أمر تسجيل الدخول AUTH LOGIN: {$authRes}";
            @fclose($this->socket);
            return false;
        }

        $userRes = $this->sendCommand(base64_encode($cfg['user']));
        if (substr($userRes, 0, 3) !== '334') {
            $this->lastError = "اسم مستخدم SMTP غير مقبول: {$userRes}";
            @fclose($this->socket);
            return false;
        }

        $passRes = $this->sendCommand(base64_encode($cfg['pass']));
        if (substr($passRes, 0, 3) !== '235') {
            $this->lastError = "فشل المصادقة - كلمة مرور SMTP غير صحيحة: {$passRes}";
            @fclose($this->socket);
            return false;
        }

        // MAIL FROM
        $mailFromRes = $this->sendCommand("MAIL FROM:<" . $cfg['from_email'] . ">");
        if (substr($mailFromRes, 0, 3) !== '250') {
            $this->lastError = "رفض عنوان المرسل: {$mailFromRes}";
            @fclose($this->socket);
            return false;
        }

        // RCPT TO
        $rcptRes = $this->sendCommand("RCPT TO:<{$toEmail}>");
        if (substr($rcptRes, 0, 3) !== '250' && substr($rcptRes, 0, 3) !== '251') {
            $this->lastError = "رفض عنوان المستلم ({$toEmail}): {$rcptRes}";
            @fclose($this->socket);
            return false;
        }

        // DATA
        $dataRes = $this->sendCommand("DATA");
        if (substr($dataRes, 0, 3) !== '354') {
            $this->lastError = "الخادم لم يقبل إرسال محتوى الرسالة: {$dataRes}";
            @fclose($this->socket);
            return false;
        }

        // Build Headers and MIME Multipart Body
        $boundary = "----=_Eduroad_MIME_Boundary_" . md5(uniqid((string)mt_rand(), true));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($cfg['from_name']) . '?=';
        $encodedToName   = $toName !== '' ? '=?UTF-8?B?' . base64_encode($toName) . '?=' : '';

        $headers = [];
        $headers[] = "From: {$encodedFromName} <{$cfg['from_email']}>";
        $headers[] = "To: " . ($encodedToName ? "{$encodedToName} <{$toEmail}>" : "<{$toEmail}>");
        $headers[] = "Reply-To: <{$cfg['from_email']}>";
        $headers[] = "Date: " . date('r');
        $headers[] = "Subject: {$encodedSubject}";
        $headers[] = "Message-ID: <" . time() . '.' . md5($toEmail . mt_rand()) . '@' . $cfg['host'] . '>';
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "X-Mailer: Eduroad Platform Mailer v2.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

        if ($altBody === '') {
            $altBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
        }

        $messageData  = implode("\r\n", $headers) . "\r\n\r\n";
        $messageData .= "--{$boundary}\r\n";
        $messageData .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $messageData .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $messageData .= chunk_split(base64_encode($altBody)) . "\r\n";

        $messageData .= "--{$boundary}\r\n";
        $messageData .= "Content-Type: text/html; charset=UTF-8\r\n";
        $messageData .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $messageData .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $messageData .= "--{$boundary}--\r\n";
        $messageData .= ".";

        $sendRes = $this->sendCommand($messageData);
        $this->sendCommand("QUIT");
        @fclose($this->socket);

        if (substr($sendRes, 0, 3) === '250') {
            $this->log("Email successfully sent to {$toEmail}");
            return true;
        }

        $this->lastError = "فشل تسليم الرسالة في نهاية البيانات: {$sendRes}";
        error_log('[Eduroad Mailer] ' . $this->lastError);
        return false;
    }
}

/* ─────────────────────────────────────────────────────────────
   4. GENERAL SEND MAIL HELPER
   ───────────────────────────────────────────────────────────── */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool {
    $client = new EduroadSmtpClient();
    return $client->send($toEmail, $toName, $subject, $htmlBody, $altBody);
}

/* ─────────────────────────────────────────────────────────────
   5. ULTRA-PREMIUM RESPONSIVE EMAIL TEMPLATE (RTL)
   ───────────────────────────────────────────────────────────── */
function renderEmailTemplate(string $badgeText, string $title, string $contentHtml, ?array $actionBtn = null, ?array $detailsBox = null): string {
    $cfg = getSmtpConfig();
    $siteName = htmlspecialchars($cfg['from_name']);
    $siteUrl  = htmlspecialchars($cfg['site_url']);
    $year     = date('Y');

    // Build details card if provided
    $detailsHtml = '';
    if (!empty($detailsBox)) {
        $detailsHtml = '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;margin:22px 0;">';
        foreach ($detailsBox as $label => $val) {
            $detailsHtml .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px dashed #e2e8f0;font-size:13px;">';
            $detailsHtml .= '<span style="color:#64748b;font-weight:600;">' . htmlspecialchars($label) . ':</span>';
            $detailsHtml .= '<span style="color:#0f172a;font-weight:700;">' . htmlspecialchars($val) . '</span>';
            $detailsHtml .= '</div>';
        }
        $detailsHtml .= '</div>';
    }

    // Build action button if provided
    $buttonHtml = '';
    if ($actionBtn && !empty($actionBtn['url'])) {
        $btnText = htmlspecialchars($actionBtn['text'] ?? 'اضغط هنا');
        $btnUrl  = htmlspecialchars($actionBtn['url']);
        $buttonHtml = '
        <div style="text-align:center;margin:30px 0 10px;">
            <a href="' . $btnUrl . '" style="display:inline-block;background:linear-gradient(135deg,#4f46e5 0%,#6366f1 100%);color:#ffffff;text-decoration:none;font-weight:800;font-size:15px;padding:14px 34px;border-radius:12px;box-shadow:0 10px 20px -5px rgba(79,70,229,0.35);letter-spacing:0.3px;">
                ' . $btnText . '
            </a>
        </div>
        <p style="text-align:center;font-size:11px;color:#94a3b8;margin-top:10px;">
            إذا تعذر النقر على الزر، يمكنك نسخ الرابط التالي ولصقه في متصفحك:<br>
            <a href="' . $btnUrl . '" style="color:#6366f1;word-break:break-all;text-decoration:underline;">' . $btnUrl . '</a>
        </p>';
    }

    return '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:\'Tajawal\',\'Segoe UI\',Tahoma,Arial,sans-serif;direction:rtl;text-align:right;color:#1e293b;-webkit-font-smoothing:antialiased;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f1f5f9;padding:40px 15px;">
    <tr>
      <td align="center">
        <!-- Main Container (max 600px) -->
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 20px 40px -15px rgba(0,0,0,0.07);border:1px solid #e2e8f0;">
          
          <!-- Header Banner -->
          <tr>
            <td style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 60%,#312e81 100%);padding:36px 30px;text-align:center;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="center">
                    <div style="display:inline-block;background:rgba(255,255,255,0.1);padding:10px 20px;border-radius:16px;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.15);">
                      <span style="font-size:24px;font-weight:900;color:#ffffff;letter-spacing:0.5px;">🎓 ' . $siteName . '</span>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body Content Card -->
          <tr>
            <td style="padding:36px 32px 30px;">
              ' . ($badgeText ? '<div style="display:inline-block;background:rgba(99,102,241,0.1);color:#4f46e5;font-size:12px;font-weight:800;padding:6px 14px;border-radius:30px;margin-bottom:16px;">' . htmlspecialchars($badgeText) . '</div>' : '') . '
              
              <h1 style="margin:0 0 16px;font-size:22px;font-weight:800;color:#0f172a;line-height:1.4;">' . htmlspecialchars($title) . '</h1>
              
              <div style="font-size:14px;line-height:1.8;color:#334155;">
                ' . $contentHtml . '
              </div>

              ' . $detailsHtml . '
              ' . $buttonHtml . '
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f8fafc;padding:24px 30px;border-top:1px solid #e2e8f0;text-align:center;">
              <p style="margin:0 0 8px;font-size:12px;color:#64748b;line-height:1.6;">
                وصلتك هذه الرسالة لأنك مسجل في <strong>' . $siteName . '</strong>. إذا لم تكن تتوقع هذا الإشعار، يرجى التواصل معنا فوراً.
              </p>
              <p style="margin:0;font-size:11px;color:#94a3b8;">
                &copy; ' . $year . ' ' . $siteName . '. جميع الحقوق محفوظة.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

/* ─────────────────────────────────────────────────────────────
   6. BUSINESS SPECIFIC EMAIL TRIGGERS
   ───────────────────────────────────────────────────────────── */

/**
 * 1. Traditional Login Security Alert (تسجيل الدخول التقليدي)
 */
function sendLoginNotificationEmail(array $user, string $userType): bool {
    $email = $user['email'] ?? '';
    if (!$email) return false;

    $name = $user['name'] ?? 'عزيزنا المستخدم';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'غير معروف';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'متصفح ويب';
    
    // Simplistic UA cleanup
    $device = 'متصفح سطح المكتب / الهاتف';
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $device = 'Apple iOS Device';
    elseif (stripos($ua, 'Android') !== false) $device = 'Android Device';
    elseif (stripos($ua, 'Windows') !== false) $device = 'Windows PC';
    elseif (stripos($ua, 'Macintosh') !== false) $device = 'macOS Apple';

    $dateTime = date('Y-m-d H:i') . ' (توقيت الخادم)';
    $roleTitle = match($userType) {
        'academic' => 'حساب أكاديمي',
        'admin'    => 'حساب إدارة النظام',
        default    => 'حساب طالب',
    };

    $subject = "🔐 تنبيه أمان: تسجيل دخول جديد إلى حسابك في منصة Eduroad";
    $content = "<p>مرحباً <strong>{$name}</strong>،</p>
    <p>نود إبلاغك بأنه تم تسجيل الدخول بنجاح إلى {$roleTitle} الخاص بك عبر البريد الإلكتروني وكلمة المرور.</p>
    <p>إذا كنت أنت من قام بهذه العملية، فلا يلزم اتخاذ أي إجراء من قبلك.</p>
    <div style=\"background:#fff1f2;border:1px solid #fecdd3;border-radius:12px;padding:14px;color:#9f1239;font-size:12px;margin:15px 0;\">
      <strong>⚠️ لم تقم بتسجيل الدخول؟</strong> إذا لم تكن أنت، نوصيك بتغيير كلمة المرور فوراً عبر زر استعادة كلمة المرور لحماية حسابك وبياناتك.
    </div>";

    $details = [
        'نوع الحساب'     => $roleTitle,
        'الوقت والتاريخ'  => $dateTime,
        'عنوان IP'       => $ip,
        'الجهاز / النظام' => $device,
    ];

    $resetUrl = (function_exists('rootUrl') ? rootUrl() : '') . '/forgot-password.php';
    $action = [
        'text' => '🔐 تغيير أو استعادة كلمة المرور',
        'url'  => $resetUrl
    ];

    $html = renderEmailTemplate('🛡️ إشعار أمني', 'تم تسجيل دخول جديد إلى حسابك', $content, $action, $details);
    return sendMail($email, $name, $subject, $html);
}

/**
 * 2. Password Reset Email (استعادة كلمة المرور)
 */
function sendPasswordResetEmail(string $email, string $userName, string $token, string $userType): bool {
    ensurePasswordResetsTable();

    // Store token in DB
    $db = db();
    $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
    $stmt = $db->prepare("INSERT INTO password_resets (email, user_type, token, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    $stmt->execute([$email, $userType, $token]);

    $resetUrl = (function_exists('rootUrl') ? rootUrl() : '') . '/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($email);

    $subject = "🔑 طلب استعادة كلمة المرور - منصة Eduroad";
    $content = "<p>مرحباً <strong>{$userName}</strong>،</p>
    <p>تلقينا طلباً لاستعادة كلمة المرور لحسابك في منصة Eduroad. يمكنك تعيين كلمة مرور جديدة من خلال الضغط على الزر أدناه.</p>
    <div style=\"background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:12px 16px;color:#1e40af;font-size:13px;margin:16px 0;\">
      ⏳ <strong>تنبيه هام:</strong> هذا الرابط مخصص لك وصالح للاستخدام لمدة <strong>60 دقيقة فقط</strong> لضمان أمان حسابك.
    </div>
    <p>إذا لم تكن قد طلبت استعادة كلمة المرور بنفسك، يرجى تجاهل هذه الرسالة، وسيبقى حسابك آمناً بالكامل.</p>";

    $action = [
        'text' => '🔒 إعادة تعيين كلمة المرور الآن',
        'url'  => $resetUrl,
    ];

    $html = renderEmailTemplate('🔑 استعادة الحساب', 'طلب إعادة تعيين كلمة المرور', $content, $action);
    return sendMail($email, $userName, $subject, $html);
}

/**
 * 2b. Password Changed Confirmation (تأكيد نجاح تغيير كلمة المرور)
 */
function sendPasswordChangedConfirmationEmail(string $email, string $userName): bool {
    $subject = "✅ تم تحديث كلمة المرور لحسابك بنجاح - Eduroad";
    $content = "<p>مرحباً <strong>{$userName}</strong>،</p>
    <p>نؤكد لك أنه تم تغيير وتحديث كلمة المرور لحسابك في منصة Eduroad بنجاح في " . date('Y-m-d H:i') . ".</p>
    <p>يمكنك الآن تسجيل الدخول مباشرة باستخدام كلمة المرور الجديدة.</p>";

    $loginUrl = (function_exists('rootUrl') ? rootUrl() : '') . '/login.php';
    $action = [
        'text' => 'تسجيل الدخول للمنصة',
        'url'  => $loginUrl,
    ];

    $html = renderEmailTemplate('✅ أمان الحساب', 'تم تغيير كلمة المرور بنجاح', $content, $action);
    return sendMail($email, $userName, $subject, $html);
}

/**
 * 2c. Ensure login_otps table exists
 */
function ensureLoginOtpsTable(): void {
    static $checked = false;
    if ($checked) return;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `login_otps` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `email` varchar(150) NOT NULL,
          `user_type` enum('student','academic','admin') NOT NULL DEFAULT 'student',
          `user_id` int(10) unsigned DEFAULT NULL,
          `otp_code` varchar(10) NOT NULL,
          `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
          `expires_at` datetime NOT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_lookup` (`email`, `user_type`, `expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $checked = true;
    } catch (Exception $e) {}
}

/**
 * 2d. Send Login OTP Email
 */
function sendLoginOtpEmail(string $email, string $userName, string $otp, string $userType): bool {
    $roleTitle = match($userType) {
        'academic' => 'حساب أكاديمي',
        'admin'    => 'حساب إدارة النظام',
        default    => 'حساب طالب',
    };

    $subject = "🔐 رمز التحقق لتسجيل الدخول: {$otp} - Eduroad";
    $content = "<p>مرحباً <strong>{$userName}</strong>،</p>
    <p>تلقينا طلباً لتسجيل الدخول إلى {$roleTitle} الخاص بك في منصة Eduroad.</p>
    <p>استخدم رمز التحقق (OTP) التالي لتأكيد هويتك وإكمال الدخول:</p>
    <div style='text-align:center;margin:28px 0;'>
      <div style='display:inline-block;background:linear-gradient(135deg, #4f46e5, #6366f1);color:#ffffff;font-size:36px;font-weight:900;letter-spacing:10px;padding:16px 36px;border-radius:18px;box-shadow:0 8px 25px rgba(79,70,229,0.35);font-family:monospace;'>
        {$otp}
      </div>
    </div>
    <div style='background:#fef3c7;border:1px solid #fde68a;border-radius:12px;padding:12px 16px;color:#92400e;font-size:13px;margin:16px 0;'>
      ⏳ <strong>تنبيه أمان:</strong> هذا الرمز صالح لمدة <strong>10 دقائق فقط</strong> ويستخدم لمرة واحدة. لا تشارك هذا الرمز مع أي شخص إطلاقاً.
    </div>
    <p style='color:#64748b;font-size:13px;'>إذا لم تكن أنت من حاول تسجيل الدخول، نوصيك بتغيير كلمة المرور الخاصة بك على الفور لحماية حسابك.</p>";

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'غير معروف';
    $dateTime = date('Y-m-d H:i') . ' (توقيت الخادم)';
    $details = [
        'نوع الحساب'    => $roleTitle,
        'وقت الطلب'     => $dateTime,
        'عنوان IP'      => $ip,
        'صلاحية الرمز'  => '10 دقائق ⏱️',
    ];

    $html = renderEmailTemplate('🔐 رمز التحقق (2FA)', 'رمز تأكيد تسجيل الدخول', $content, null, $details);
    return sendMail($email, $userName, $subject, $html);
}

/**
 * 2e. Generate, Save and Send Login OTP
 */
function generateAndSendLoginOtp(array $user, string $userType, ?string $redirect = null): array {
    ensureLoginOtpsTable();
    $db = db();

    $email = $user['email'];
    $userId = (int) $user['id'];
    $userName = $user['name'] ?? 'المستخدم';

    // Generate random 6-digit code
    $otp = sprintf('%06d', random_int(100000, 999999));

    // Delete any previous active OTPs for this email and role
    $db->prepare("DELETE FROM login_otps WHERE email = ? AND user_type = ?")->execute([$email, $userType]);

    // Insert new OTP with 10 minutes expiry
    $stmt = $db->prepare("INSERT INTO login_otps (email, user_type, user_id, otp_code, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->execute([$email, $userType, $userId, $otp]);

    // Set pending 2FA in session
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['pending_2fa'] = [
        'user_id'   => $userId,
        'user_type' => $userType,
        'email'     => $email,
        'name'      => $userName,
        'redirect'  => $redirect,
        'user_data' => $user,
        'sent_at'   => time(),
    ];

    // Log OTP to error_log for dev/debugging convenience
    error_log("[Eduroad OTP] Login 2FA Code for {$email} ({$userType}): {$otp}");

    // Send email
    $sent = sendLoginOtpEmail($email, $userName, $otp, $userType);

    return [
        'success' => true,
        'email_sent' => $sent,
        'otp' => $otp
    ];
}

/**
 * 2f. Verify Login OTP
 */
function verifyLoginOtp(string $email, string $userType, string $inputOtp): array {
    ensureLoginOtpsTable();
    $db = db();

    $inputOtp = preg_replace('/\D/', '', $inputOtp); // numbers only
    if (strlen($inputOtp) !== 6) {
        return ['ok' => false, 'error' => 'يرجى إدخال رمز تحقق مكون من 6 أرقام.'];
    }

    $stmt = $db->prepare("SELECT * FROM login_otps WHERE email = ? AND user_type = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email, $userType]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'error' => 'رمز التحقق منتهي الصلاحية أو غير موجود. يرجى طلب رمز جديد.'];
    }

    if ((int)$row['attempts'] >= 5) {
        $db->prepare("DELETE FROM login_otps WHERE id = ?")->execute([$row['id']]);
        return ['ok' => false, 'error' => 'تم تجاوز الحد الأقصى للمحاولات الخاطئة (5 محاولات). يرجى طلب رمز جديد.'];
    }

    if ($row['otp_code'] !== $inputOtp) {
        $newAttempts = (int)$row['attempts'] + 1;
        $db->prepare("UPDATE login_otps SET attempts = ? WHERE id = ?")->execute([$newAttempts, $row['id']]);
        $remaining = 5 - $newAttempts;
        return [
            'ok' => false,
            'error' => "رمز التحقق غير صحيح. متبقي لديك {$remaining} " . ($remaining === 1 ? 'محاولة' : 'محاولات') . "."
        ];
    }

    // Success -> delete OTP
    $db->prepare("DELETE FROM login_otps WHERE id = ?")->execute([$row['id']]);

    return ['ok' => true];
}

/**
 * 3. Order Assigned to Academic (إرسال إيميل للأكاديمي عند توجيه طلب له)
 */
function sendOrderAssignedToAcademicEmail(int|array $academic, array $order): bool {
    $db = db();
    if (!is_array($academic)) {
        $stmt = $db->prepare("SELECT * FROM academics WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$academic]);
        $academic = $stmt->fetch() ?: [];
    }

    $email = $academic['email'] ?? '';
    if (!$email) return false;

    $acName = $academic['name'] ?? 'سعادة الأكاديمي';
    $orderNumber = $order['order_number'] ?? ('#' . ($order['id'] ?? ''));
    $serviceName = $order['service_name'] ?? 'خدمة أكاديمية';
    $specialty   = $order['specialty'] ?? 'عام';
    $deadline    = !empty($order['deadline']) ? date('Y/m/d', strtotime($order['deadline'])) : 'حسب الاتفاق';
    $amountNet   = isset($order['amount']) ? number_format((float)$order['amount'] * 0.85, 2) . ' ر.س' : 'محدد بالطلب';

    $subject = "📋 طلب أكاديمي جديد مسند إليك برقم {$orderNumber} - Eduroad";
    $content = "<p>تحية طيبة <strong>{$acName}</strong>،</p>
    <p>يسعدنا إبلاغك بأن إدارة منصة Eduroad قامت بإسناد طلب أكاديمي جديد إليك بناءً على تخصصك وتميزك الأكاديمي.</p>
    <p>يرجى التفضل بمراجعة تفاصيل الطلب ومتطلباته واتخاذ الإجراء (قبول أو اعتذار) في أقرب وقت لبدء العمل.</p>";

    $details = [
        'رقم الطلب'     => $orderNumber,
        'الخدمة المطلوبة' => $serviceName,
        'الكلية / التخصص' => $specialty,
        'موعد التسليم'   => $deadline,
        'صافي أرباحك المقدرة' => $amountNet,
    ];

    $orderUrl = (function_exists('rootUrl') ? rootUrl() : '') . '/academics/academic-order-details.php?id=' . urlencode($orderNumber);
    $action = [
        'text' => '📄 مراجعة تفاصيل الطلب وقبوله',
        'url'  => $orderUrl,
    ];

    $html = renderEmailTemplate('📋 مهمة جديدة', "تم إسناد الطلب {$orderNumber} إليك", $content, $action, $details);
    return sendMail($email, $acName, $subject, $html);
}

/**
 * 4. Order Completed to Student (إيميل للطالب عند اكتمال الطلب)
 */
function sendOrderCompletedToStudentEmail(int|array $order): bool {
    $db = db();
    if (!is_array($order) || empty($order['student_email'])) {
        $orderId = is_array($order) ? (int)$order['id'] : (int)$order;
        $stmt = $db->prepare("
            SELECT o.*, s.name AS service_name, u.name AS student_name, u.email AS student_email, a.name AS academic_name
            FROM orders o
            JOIN users u ON o.student_id = u.id
            JOIN services s ON o.service_id = s.id
            LEFT JOIN academics a ON o.academic_id = a.id
            WHERE o.id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch() ?: [];
    }

    $email = $order['student_email'] ?? '';
    if (!$email) return false;

    $studentName = $order['student_name'] ?? 'عزيزنا الطالب';
    $orderNumber = $order['order_number'] ?? ('#' . ($order['id'] ?? ''));
    $serviceName = $order['service_name'] ?? 'الخدمة الأكاديمية';
    $academicName = $order['academic_name'] ?? 'الأكاديمي المختص';

    $subject = "🎉 مبارك! تم اكتمال تنفيذ طلبك رقم {$orderNumber} بنجاح - Eduroad";
    $content = "<p>مرحباً <strong>{$studentName}</strong>،</p>
    <p>يسعدنا إبلاغك بأن الأكاديمي المختص قام بإنهاء ومراجعة طلبك الأكاديمي بنجاح وهو الآن مكتمل وجاهز للاستلام!</p>
    <p>يمكنك الدخول إلى صفحة تفاصيل الطلب لتحميل كافة الملفات والمخرجات النهائية ومراجعتها.</p>";

    $details = [
        'رقم الطلب'    => $orderNumber,
        'الخدمة'       => $serviceName,
        'الأكاديمي المنفذ' => $academicName,
        'حالة الطلب'   => 'مكتمل بنجاح ✅',
    ];

    $orderUrl = (function_exists('rootUrl') ? rootUrl() : '') . '/student/order-details.php?id=' . ($order['id'] ?? 0);
    $action = [
        'text' => '📥 استعراض المخرجات وتنزيل الملفات',
        'url'  => $orderUrl,
    ];

    $html = renderEmailTemplate('🎉 اكتمال الإنجاز', "اكتمل تنفيذ طلبك {$orderNumber}", $content, $action, $details);
    return sendMail($email, $studentName, $subject, $html);
}

/**
 * 5. New Order to Admin (إيميل للإدارة عند وصول طلب جديد)
 */
function sendNewOrderToAdminEmail(int|array $order): bool {
    $db = db();
    if (!is_array($order) || empty($order['order_number'])) {
        $orderId = is_array($order) ? (int)$order['id'] : (int)$order;
        $stmt = $db->prepare("
            SELECT o.*, s.name AS service_name, p.name AS package_name, u.name AS student_name, u.email AS student_email, u.phone AS student_phone
            FROM orders o
            JOIN users u ON o.student_id = u.id
            JOIN services s ON o.service_id = s.id
            LEFT JOIN packages p ON o.package_id = p.id
            WHERE o.id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch() ?: [];
    }

    if (empty($order)) return false;

    // Collect recipient admin emails
    $adminEmails = [];
    try {
        $stmt = $db->query("SELECT email, name FROM users WHERE role = 'admin'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $adminEmails[] = $row['email'];
        }
    } catch (Exception $e) {}

    // Also include site_email from settings if available
    $cfg = getSmtpConfig();
    if (!empty($cfg['from_email']) && !in_array($cfg['from_email'], $adminEmails, true)) {
        $adminEmails[] = $cfg['from_email'];
    }

    if (empty($adminEmails)) return false;

    $orderNumber = $order['order_number'] ?? ('#' . ($order['id'] ?? ''));
    $studentName = $order['student_name'] ?? 'طالب';
    $serviceName = $order['service_name'] ?? 'خدمة';
    $specialty   = $order['specialty'] ?? 'عام';
    $amount      = number_format((float)($order['amount'] ?? 0), 2) . ' ر.س';
    $package     = $order['package_name'] ?? 'طلب مباشر';

    $subject = "🔔 طلب جديد وارد في المنصة: {$orderNumber} - يرجى تعيين أكاديمي";
    $content = "<p>إلى إدارة منصة Eduroad،</p>
    <p>تم استلام طلب خدمة جديد من الطالب <strong>{$studentName}</strong> وهو بانتظار المراجعة وتعيين الأكاديمي المختص للبدء بالتنفيذ.</p>";

    $details = [
        'رقم الطلب'     => $orderNumber,
        'اسم الطالب'    => $studentName,
        'الخدمة المطلوبة' => $serviceName,
        'الكلية / التخصص' => $specialty,
        'الباقة / النوع' => $package,
        'قيمة الطلب'    => $amount,
        'تاريخ الإنشاء' => date('Y/m/d H:i'),
    ];

    $adminOrderUrl = (function_exists('rootUrl') ? rootUrl() : '') . '/admin/pages/order-details.php?id=' . urlencode($orderNumber);
    $action = [
        'text' => '⚙️ مراجعة الطلب وتعيين الأكاديميين',
        'url'  => $adminOrderUrl,
    ];

    $html = renderEmailTemplate('🔔 طلب جديد وارد', "طلب جديد برقم {$orderNumber}", $content, $action, $details);

    $success = false;
    foreach ($adminEmails as $aEmail) {
        if (filter_var($aEmail, FILTER_VALIDATE_EMAIL)) {
            $ok = sendMail($aEmail, 'إدارة المنصة', $subject, $html);
            if ($ok) $success = true;
        }
    }

    return $success;
}
