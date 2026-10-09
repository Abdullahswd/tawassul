<?php
$student_js = __DIR__ . '/student/support-app.js';
$academic_js = __DIR__ . '/academics/support-app.js';
$admin_js = __DIR__ . '/admin/assets/js/support-admin.js';

function patchSupportAppJs($file) {
    if (!file_exists($file)) return;
    $content = file_get_contents($file);

    // Patch supAppend
    $content = preg_replace(
        '/function supAppend\(m\)\s*\{/',
        "function supAppend(m, isTemp = false) {\n    if (!isTemp) { document.querySelectorAll('.temp-msg').forEach(function(e){ e.remove(); }); }",
        $content
    );
    
    $content = preg_replace(
        '/wrap\.className = \([^;]+;/',
        "$0\n    if (isTemp) wrap.classList.add('temp-msg');\n    if (isTemp) wrap.style.opacity = '0.7';",
        $content
    );
    
    // Patch form submit
    $content = preg_replace(
        '/if \(!content && !hasFile\) return;\s*supportSending = true;/',
        "if (!content && !hasFile) return;\n      supAppend({sender_type: 'user', content: content || 'ملف مرفق...', created_at: new Date().toISOString()}, true);\n      supScroll();\n      supportSending = true;",
        $content
    );

    file_put_contents($file, $content);
    echo "Patched $file\n";
}

function patchAdminSupportJs($file) {
    if (!file_exists($file)) return;
    $content = file_get_contents($file);

    // Patch appendMsg
    $content = preg_replace(
        '/function appendMsg\(m\)\s*\{\s*if \(!chatBox\) return;/',
        "function appendMsg(m, isTemp = false) {\n    if (!chatBox) return;\n    if (!isTemp) { document.querySelectorAll('.temp-msg').forEach(function(e){ e.remove(); }); }",
        $content
    );
    
    $content = preg_replace(
        '/wrap\.setAttribute\(\'data-msg-id\', m\.id\);/',
        "$0\n    if (isTemp) wrap.classList.add('temp-msg');\n    if (isTemp) wrap.style.opacity = '0.7';",
        $content
    );
    
    // Patch form submit
    $content = preg_replace(
        '/if \(!txt && !hasFile\)[^;]+;\s*return;\s*\}/',
        "$0\n    appendMsg({sender_type: 'admin', content: txt || 'ملف مرفق...', created_at: new Date().toISOString()}, true);\n    scrollToBottom();",
        $content
    );

    // Remove reload
    $content = preg_replace(
        '/setTimeout\(function \(\) \{ location\.reload\(\); \}, 800\);/',
        "// location.reload() removed to prevent flicker",
        $content
    );

    file_put_contents($file, $content);
    echo "Patched $file\n";
}

patchSupportAppJs($student_js);
patchSupportAppJs($academic_js);
patchAdminSupportJs($admin_js);
