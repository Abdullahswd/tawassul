<?php
/**
 * Unified Student HTML Head Partial
 *
 * Variables expected to be defined before including this file:
 *   $pageTitle   – string used for <title> tag
 *   $extraCss    – (optional) array of additional CSS <link> tags or inline CSS strings
 */
if (!isset($pageTitle)) $pageTitle = 'تواصل';
if (!isset($extraCss))  $extraCss  = [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> - تواصل</title>
  <meta name="description" content="منصة تواصل الأكاديمية - خدمات أكاديمية متخصصة">
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php foreach ($extraCss as $css): echo $css . "\n"; endforeach; ?>
</head>
<body>
