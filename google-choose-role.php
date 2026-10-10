<?php
/**
 * google-choose-role.php — Ask new Google users whether they are students or academics
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

if (!isset($_SESSION['google_temp_user'])) {
    header('Location: login.php');
    exit;
}

$googleUser = $_SESSION['google_temp_user'];

// Handle Role Selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'])) {
    $role = $_POST['role'];

    if ($role === 'student') {
        // Create student account immediately
        $password = bin2hex(random_bytes(8)); // Random secure password

        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, google_id) 
            VALUES (:name, :email, :password, 'student', :google_id)
        ");
        $stmt->execute([
            'name' => $googleUser['name'],
            'email' => $googleUser['email'],
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'google_id' => $googleUser['google_id']
        ]);

        $userId = $pdo->lastInsertId();
        $user = getUserById($userId);

        // Welcome notification
        createNotification($userId, 'student', 'مرحباً بك في تواصل 🎉', 'تم إنشاء حسابك بنجاح عبر Google. ابدأ بتصفح الخدمات المتاحة.', '🎓', 'student/services.php');

        // Clean up session and login
        unset($_SESSION['google_temp_user']);
        loginUser($user);

        header('Location: student/student-dashboard.php');
        exit;
    } elseif ($role === 'academic') {
        // Keep the temp session and redirect to academic registration to complete fields
        header('Location: academics/academic-register.php?google=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استكمال التسجيل - Eduroad</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        body {
            background: linear-gradient(145deg, var(--bg-main) 0%, rgba(79, 70, 229, 0.05) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 40px;
            border-radius: 32px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.15);
        }

        .role-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 20px;
            font-size: 18px;
            font-weight: 700;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            background: transparent;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 16px;
            font-family: inherit;
        }

        .role-btn:hover {
            border-color: var(--primary);
            background: rgba(79, 70, 229, 0.05);
            transform: translateY(-2px);
        }

        .role-icon {
            font-size: 28px;
        }
    </style>
</head>

<body>

    <div class="card">
        <h1 style="font-size:24px;font-weight:800;color:var(--text-primary);margin-bottom:12px">مرحباً
            <?= htmlspecialchars($googleUser['name']) ?> 👋</h1>
        <p style="color:var(--text-secondary);font-size:15px;margin-bottom:32px">لقد قمت بتسجيل الدخول بحساب Google
            بنجاح. كيف تود استخدام المنصة؟</p>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>" />
            <button type="submit" name="role" value="student" class="role-btn">
                <span class="role-icon">📚</span>
                بصفتي طالب (أبحث عن خدمات)
            </button>
            <button type="submit" name="role" value="academic" class="role-btn">
                <span class="role-icon">🎓</span>
                بصفتي أكاديمي (أقدم خدمات)
            </button>
        </form>

        <div style="margin-top:24px">
            <a href="login.php" style="color:var(--text-muted);font-size:14px;text-decoration:none">إلغاء والعودة
                للرئيسية</a>
        </div>
    </div>

</body>

</html>