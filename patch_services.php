<?php
$file = 'admin/pages/services.php';
$content = file_get_contents($file);

// 1. Add csrfTokenJs definition at the top of the script
if (strpos($content, 'const csrfTokenJs') === false) {
    // We will inject it after the FIRST <script> tag
    $content = preg_replace(
        '/<script>/',
        "<script>\nconst csrfTokenJs = document.querySelector('meta[name=\"csrf-token\"]')?.content || '<?= htmlspecialchars(\$_SESSION[\"csrf_token\"] ?? \"\") ?>';\n",
        $content,
        1
    );
}

// 2. Append csrf_token to all formData instances
$content = preg_replace_callback(
    '/(const|let|var)\s+([a-zA-Z0-9_]+)\s*=\s*new\s+FormData\([^)]*\);/',
    function ($matches) {
        $varName = $matches[2];
        $original = $matches[0];
        return $original . "\n  {$varName}.append('csrf_token', csrfTokenJs);";
    },
    $content
);

file_put_contents($file, $content);
echo "File updated successfully.";
