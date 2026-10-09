<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
requireAdmin();

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db = db();
    
    // Fetch the notification to get its link
    $stmt = $db->prepare("SELECT link FROM notifications WHERE id = ? AND user_type = 'admin'");
    $stmt->execute([$id]);
    $notif = $stmt->fetch();
    
    if ($notif) {
        // Mark as read
        $update = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_type = 'admin'");
        $update->execute([$id]);
        
        // Redirect to the target link if it exists
        if (!empty($notif['link'])) {
            $targetUrl = rootUrl() . '/' . ltrim($notif['link'], '/');
            header("Location: $targetUrl");
            exit;
        }
    }
}

// Fallback redirect to notifications page
header('Location: notifications.php');
exit;
    