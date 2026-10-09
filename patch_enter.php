<?php
function patchFile($filePath, $pattern, $replacement) {
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        if (strpos($content, 'e.key === \'Enter\'') === false) {
            $content = preg_replace($pattern, $replacement, $content);
            file_put_contents($filePath, $content);
            echo "Patched $filePath\n";
        } else {
            echo "Already patched $filePath\n";
        }
    }
}

$userJs = <<<'JS'
  const supportInput = document.getElementById('supportInput');
  if (supportInput && supForm) {
    supportInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        supForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
      }
    });
  }
})();
JS;

patchFile(__DIR__ . '/student/support-app.js', '/\}\)\(\);$/s', $userJs);
patchFile(__DIR__ . '/academics/support-app.js', '/\}\)\(\);$/s', $userJs);

$adminJs = <<<'JS'
  var supReplyInput = document.getElementById('supReplyInput');
  if (supReplyInput) {
    supReplyInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (typeof window.sendSupReply === 'function') window.sendSupReply();
      }
    });
  }
})();
JS;

patchFile(__DIR__ . '/admin/assets/js/support-admin.js', '/\}\)\(\);$/s', $adminJs);
