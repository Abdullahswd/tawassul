<?php
$files = [
    __DIR__ . '/academics/academic-support.php',
    __DIR__ . '/student/support.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        $badStr = "<script src=\"https://cdn.tailwindcss.com\">\n  window.CSRF_TOKEN = '<?= htmlspecialchars(\$_SESSION[\"csrf_token\"] ?? \"\") ?>';</script>";
        $goodStr = "<script src=\"https://cdn.tailwindcss.com\"></script>";
        
        if (strpos($content, $badStr) !== false) {
            $content = str_replace($badStr, $goodStr, $content);
            file_put_contents($file, $content);
            echo "Fixed $file\n";
        } else {
            // let's try with different whitespace
            $content = preg_replace('/<script src="https:\/\/cdn\.tailwindcss\.com">\s*window\.CSRF_TOKEN = [^;]+;<\/script>/i', '<script src="https://cdn.tailwindcss.com"></script>', $content);
            file_put_contents($file, $content);
            echo "Regex fixed $file\n";
        }
    }
}
