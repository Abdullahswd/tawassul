<?php
$files_to_patch = [
    __DIR__ . '/academics/academic-support.php',
    __DIR__ . '/student/support.php',
    __DIR__ . '/admin/pages/support.php'
];

foreach ($files_to_patch as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Remove the invalid injection
        $content = preg_replace('/(<script[^>]*>)\s*window\.CSRF_TOKEN\s*=\s*\'[^<]+\'\s*;/is', '$1', $content);
        
        // Insert a new valid script tag before the closing </head>
        if (strpos($content, 'window.CSRF_TOKEN =') === false) {
            $content = str_replace(
                '</head>',
                "<script>\n  window.CSRF_TOKEN = '<?= htmlspecialchars(\$_SESSION[\"csrf_token\"] ?? \"\") ?>';\n</script>\n</head>",
                $content
            );
            file_put_contents($file, $content);
            echo "Fixed PHP: $file\n";
        }
    }
}
