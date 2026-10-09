<?php
$files_to_patch = [
    __DIR__ . '/academics/academic-support.php',
    __DIR__ . '/student/support.php',
    __DIR__ . '/admin/pages/support.php'
];

foreach ($files_to_patch as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Inject CSRF_TOKEN into window if not exists
        if (strpos($content, 'window.CSRF_TOKEN') === false) {
            $content = preg_replace('/(<script[^>]*>)/i', "$1\n  window.CSRF_TOKEN = '<?= htmlspecialchars(\$_SESSION[\"csrf_token\"] ?? \"\") ?>';", $content, 1);
            file_put_contents($file, $content);
            echo "Patched PHP: $file\n";
        }
    }
}

$js_files = [
    __DIR__ . '/academics/support-app.js',
    __DIR__ . '/student/support-app.js',
    __DIR__ . '/admin/assets/js/support-admin.js'
];

foreach ($js_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Let's replace "new FormData" with something that automatically appends CSRF_TOKEN
        $modified = false;
        
        $content = preg_replace_callback(
            '/(var|const|let)\s+([a-zA-Z0-9_]+)\s*=\s*new\s+FormData\(([^)]*)\);/',
            function ($matches) use (&$modified) {
                $modified = true;
                $var = $matches[2];
                $args = $matches[3];
                return $matches[0] . "\n      if(window.CSRF_TOKEN) { {$var}.append('csrf_token', window.CSRF_TOKEN); }";
            },
            $content
        );
        
        // Remove duplicates if already appended
        $content = preg_replace('/(if\(window\.CSRF_TOKEN\)\s*\{\s*[a-zA-Z0-9_]+\.append\(\'csrf_token\',\s*window\.CSRF_TOKEN\);\s*\})(\s*if\(window\.CSRF_TOKEN\)\s*\{\s*[a-zA-Z0-9_]+\.append\(\'csrf_token\',\s*window\.CSRF_TOKEN\);\s*\})+/', '$1', $content);
        
        if ($modified) {
            file_put_contents($file, $content);
            echo "Patched JS: $file\n";
        }
    }
}
echo "Done.\n";
