/* ============================================================
   Eduroad - Support Page App (student + academic)
   Requires: window.SUPPORT_APP_BASE, window.SUPPORT_CONV,
             window.SUPPORT_LASTID
   ============================================================ */
(function () {
  'use strict';

  // ── أكورديون الأسئلة الشائعة ──
  window.toggleFaq = function (btn) {
    const item = btn.parentElement;
    const answer = item.querySelector('.faq-answer');
    const arrow = btn.querySelector('span:last-child');
    const open = answer.style.display === 'block';
    item.parentElement.querySelectorAll('.faq-answer').forEach(a => a.style.display = 'none');
    item.parentElement.querySelectorAll('span:last-child').forEach(s => s.style.transform = 'rotate(0deg)');
    if (!open) { answer.style.display = 'block'; arrow.style.transform = 'rotate(180deg)'; }
  };

  const APP_BASE = window.SUPPORT_APP_BASE || '';
  const SUP_CONV = window.SUPPORT_CONV || 0;
  let lastMid = window.SUPPORT_LASTID || 0;
  let supportSending = false;
  const supBox = document.getElementById('supportMessages');
  const supFile = document.getElementById('supportFileInput');

  // إظهار اسم الملف المحدد
  if (supFile) supFile.addEventListener('change', function () {
    const fn = document.getElementById('supportFileName');
    if (!fn) return;
    if (supFile.files.length) { fn.textContent = '📄 ' + supFile.files[0].name; fn.style.display = ''; }
    else fn.style.display = 'none';
  });

  function supEscape(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function supScroll() { if (supBox) supBox.scrollTo({ top: supBox.scrollHeight }); }

  function supAppend(m) {
    const ph = document.getElementById('supportPlaceholder');
    if (ph) ph.remove();
    const isUser = m.sender_type === 'user';
    const wrap = document.createElement('div');
    wrap.className = (supBox.classList.contains('sup-box') ? 'sup-msg ' : 'msg ') + (isUser
      ? (supBox.classList.contains('sup-box') ? 'sup-user' : 'msg-sent')
      : (supBox.classList.contains('sup-box') ? 'sup-admin' : 'msg-received'));

    if (!isUser) {
      const nm = document.createElement('div');
      nm.style.cssText = 'font-size:11px;font-weight:700;color:var(--primary);margin-bottom:3px';
      nm.textContent = 'فريق الدعم 🛟';
      wrap.appendChild(nm);
    }
    if (m.file_path) {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;justify-content:' + (isUser ? 'flex-end' : 'flex-start') + ';text-align:' + (isUser ? 'right' : 'left') + ';margin-bottom:' + (m.content ? '6px' : '0');
      const a = document.createElement('a');
      a.href = APP_BASE + '/' + m.file_path; a.target = '_blank'; a.download = '';
      a.style.cssText = 'display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;max-width:220px;background:' + (isUser ? 'rgba(255,255,255,0.18)' : 'var(--primary-light)') + ';color:' + (isUser ? '#fff' : 'var(--primary)');
      const ic = document.createElement('span'); ic.textContent = '📎';
      const nm2 = document.createElement('span'); nm2.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap'; nm2.textContent = m.file_name || 'ملف';
      a.appendChild(ic); a.appendChild(nm2); row.appendChild(a); wrap.appendChild(row);
    }
    if (m.content) { const t = document.createElement('div'); t.innerHTML = supEscape(m.content).replace(/\n/g, '<br>'); wrap.appendChild(t); }
    const time = document.createElement('div');
    time.className = supBox.classList.contains('sup-box') ? 'sup-time' : 'msg-time';
    time.style.textAlign = isUser ? 'left' : 'right';
    time.textContent = (m.created_at || '').substring(11, 16);
    wrap.appendChild(time);
    supBox.appendChild(wrap);
  }

  async function supportPoll() {
    if (document.hidden || !SUP_CONV || supportSending) return;
    try {
      const res = await fetch('ajax/support-poll.php?conv=' + SUP_CONV + '&after=' + lastMid, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.ok) return;
      if (data.messages && data.messages.length) {
        data.messages.forEach(supAppend);
        lastMid = data.last_id || lastMid;
        supScroll();
      }
    } catch (e) { /* تجاهل أخطاء الشبكة المؤقتة */ }
  }
  setInterval(supportPoll, 5000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) supportPoll(); });
  supScroll();

  // إرسال رسالة بدون إعادة تحميل
  const supForm = document.getElementById('supportForm');
  if (supForm) {
    supForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      if (supportSending) return;
      const input = document.getElementById('supportInput');
      const content = (input.value || '').trim();
      const hasFile = supFile && supFile.files.length > 0;
      if (!content && !hasFile) return;
      supportSending = true;
      try {
        const fd = new FormData(supForm);
        const res = await fetch(supForm.getAttribute('action'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (res.ok) {
          input.value = '';
          if (supFile) supFile.value = '';
          const fn = document.getElementById('supportFileName'); if (fn) fn.style.display = 'none';
          input.focus();
          await supportPoll();
        }
      } catch (err) { supForm.submit(); }
      finally { supportSending = false; }
    });
  }
})();