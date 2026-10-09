<?php
$files = [
    __DIR__ . '/academics/academic-support.php',
    __DIR__ . '/student/support.php',
    __DIR__ . '/admin/pages/support.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        $content = preg_replace('/support-app\.js\?v=\d+/', 'support-app.js?v=<?= time() ?>', $content);
        $content = preg_replace('/support-admin\.js\?v=\d+/', 'support-admin.js?v=<?= time() ?>', $content);
        
        file_put_contents($file, $content);
        echo "Updated JS version to time() in: " . basename($file) . "\n";
    }
}
