<?php
$file = 'academics/academic-settings.php';
$content = file_get_contents($file);

// 1. Add csrfTokenJs definition at the top of the script
if (strpos($content, 'const csrfTokenJs') === false) {
    $content = preg_replace(
        '/<script>/',
        "<script>\nconst csrfTokenJs = document.querySelector('meta[name=\"csrf-token\"]')?.content || '<?= htmlspecialchars(\$_SESSION[\'csrf_token\'] ?? \'\') ?>';\n",
        $content
    );
}

// 2. Append csrf_token to all formData instances
// We will find all lines containing `new FormData()` and insert `\n  SOMEVAR.append('csrf_token', csrfTokenJs);` after it.
// We match `(const|let|var)\s+([a-zA-Z0-9_]+)\s*=\s*new\s+FormData\([^)]*\);`
$content = preg_replace_callback(
    '/(const|let|var)\s+([a-zA-Z0-9_]+)\s*=\s*new\s+FormData\([^)]*\);/',
    function ($matches) {
        $varName = $matches[2];
        $original = $matches[0];
        // Don't append if it's already there (though the regex won't catch the next line, we can just replace safely since we know it's missing)
        return $original . "\n  {$varName}.append('csrf_token', csrfTokenJs);";
    },
    $content
);

file_put_contents($file, $content);
echo "File updated successfully.";
