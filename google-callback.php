<?php
/**
 * google-callback.php — Handle response from Google OAuth2
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/google_oauth.php';

// Check if there's an error from Google
if (isset($_GET['error'])) {
    header('Location: login.php?error=' . urlencode('تم إلغاء تسجيل الدخول بواسطة Google.'));
    exit;
}

// Verify state
if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    header('Location: login.php?error=' . urlencode('حالة غير صالحة. يرجى المحاولة مرة أخرى.'));
    exit;
}

if (isset($_GET['code'])) {
    $tokenData = fetchGoogleAccessToken($_GET['code']);
    
    if ($tokenData && isset($tokenData['access_token'])) {
        $googleProfile = fetchGoogleUserProfile($tokenData['access_token']);
        
        if ($googleProfile && isset($googleProfile['id'], $googleProfile['email'])) {
            $googleId = $googleProfile['id'];
            $email = $googleProfile['email'];
            $name = $googleProfile['name'] ?? 'مستخدم جوجل';
            
            $pdo = db();
            
            // 1. Try to find by google_id in users
            $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = :gid");
            $stmt->execute(['gid' => $googleId]);
            $user = $stmt->fetch();
            
            if ($user) {
                if ($user['role'] === 'admin') {
                    header('Location: login.php?error=' . urlencode('لا يمكنك تسجيل الدخول بحساب الإدارة من هنا.'));
                    exit;
                }
                if ($user['status'] === 'suspended') {
                    header('Location: login.php?error=' . urlencode('تم تعليق حسابك. يرجى التواصل مع الدعم.'));
                    exit;
                }
                loginUser($user);
                header('Location: student/student-dashboard.php');
                exit;
            }
            
            // 2. Try to find by google_id in academics
            $stmt = $pdo->prepare("SELECT * FROM academics WHERE google_id = :gid");
            $stmt->execute(['gid' => $googleId]);
            $academic = $stmt->fetch();
            
            if ($academic) {
                if ($academic['status'] === 'rejected') {
                    header('Location: login.php?error=' . urlencode('تم رفض حسابك من قِبل الإدارة. للاستفسار تواصل معنا.'));
                    exit;
                }
                loginAcademic($academic);
                header('Location: academics/academic-dashboard.php');
                exit;
            }
            
            // 3. Not found by google_id. Let's check if the email already exists to link them.
            // Check users
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $userByEmail = $stmt->fetch();
            
            if ($userByEmail) {
                // Link account
                $pdo->prepare("UPDATE users SET google_id = :gid WHERE id = :id")->execute([
                    'gid' => $googleId,
                    'id' => $userByEmail['id']
                ]);
                
                if ($userByEmail['role'] === 'admin') {
                    header('Location: login.php?error=' . urlencode('لا يمكنك تسجيل الدخول بحساب الإدارة من هنا.'));
                    exit;
                }
                if ($userByEmail['status'] === 'suspended') {
                    header('Location: login.php?error=' . urlencode('تم تعليق حسابك. يرجى التواصل مع الدعم.'));
                    exit;
                }
                
                loginUser($userByEmail);
                header('Location: student/student-dashboard.php');
                exit;
            }
            
            // Check academics
            $stmt = $pdo->prepare("SELECT * FROM academics WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $academicByEmail = $stmt->fetch();
            
            if ($academicByEmail) {
                // Link account
                $pdo->prepare("UPDATE academics SET google_id = :gid WHERE id = :id")->execute([
                    'gid' => $googleId,
                    'id' => $academicByEmail['id']
                ]);
                
                if ($academicByEmail['status'] === 'rejected') {
                    header('Location: login.php?error=' . urlencode('تم رفض حسابك من قِبل الإدارة. للاستفسار تواصل معنا.'));
                    exit;
                }
                
                loginAcademic($academicByEmail);
                header('Location: academics/academic-dashboard.php');
                exit;
            }
            
            // 4. If totally new user -> redirect to choose role
            $_SESSION['google_temp_user'] = [
                'google_id' => $googleId,
                'email'     => $email,
                'name'      => $name
            ];
            
            header('Location: google-choose-role.php');
            exit;
            
        }
    }
}

// Fallback error
header('Location: login.php?error=' . urlencode('فشل تسجيل الدخول بواسطة Google. يرجى المحاولة مرة أخرى.'));
exit;
