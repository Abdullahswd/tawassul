<?php
$dir = new RecursiveDirectoryIterator(__DIR__);
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.(php|js)$/i', RecursiveRegexIterator::GET_MATCH);

$from = '💬';
$to = '💬';

foreach ($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    if (strpos($content, $from) !== false) {
        $content = str_replace($from, $to, $content);
        file_put_contents($path, $content);
        echo "Replaced in $path\n";
    }
}
