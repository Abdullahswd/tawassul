<?php
$dirs = [
    __DIR__ . '/admin/pages',
    __DIR__ . '/academics',
    __DIR__ . '/student',
];

$filesPatched = 0;

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $originalContent = $content;
        
        $modified = false;
        
        // 1. Check if we need to add the CSRF token definition
        // We only add it if there are AJAX requests using FormData
        if (preg_match('/new\s+FormData/', $content)) {
            if (strpos($content, 'const csrfTokenJs') === false && strpos($content, '<script>') !== false) {
                // Add csrfTokenJs definition at the top of the FIRST <script> tag
                $content = preg_replace(
                    '/<script>/',
                    "<script>\nconst csrfTokenJs = document.querySelector('meta[name=\"csrf-token\"]')?.content || '<?= htmlspecialchars(\$_SESSION[\"csrf_token\"] ?? \"\") ?>';\n",
                    $content,
                    1
                );
                $modified = true;
            }
            
            // 2. Append csrf_token to all formData instances if not already appended
            $content = preg_replace_callback(
                '/(const|let|var)\s+([a-zA-Z0-9_]+)\s*=\s*new\s+FormData\([^)]*\);/',
                function ($matches) use (&$modified) {
                    $varName = $matches[2];
                    $original = $matches[0];
                    return $original . "\n  {$varName}.append('csrf_token', csrfTokenJs);";
                },
                $content
            );
            
            // 3. Fix potential double appending if we run it multiple times
            // Remove duplicates
            $content = preg_replace('/(\.append\(\'csrf_token\',\s*csrfTokenJs\);)(\s*[a-zA-Z0-9_]+\.append\(\'csrf_token\',\s*csrfTokenJs\);)+/', '$1', $content);
            
            if ($content !== $originalContent) {
                file_put_contents($file, $content);
                $filesPatched++;
                echo "Patched: " . basename($file) . "\n";
            }
        }
    }
}
echo "Total files patched: $filesPatched\n";
