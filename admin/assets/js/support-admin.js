/* Eduroad Admin - Support Page App (v5 complete) */
(function () {
  'use strict';
  var APP_BASE = window.ADMIN_APP_BASE || '';
  var TAB = (window.ADMIN_TAB === 'academics') ? 'academics' : 'students';
  var CONV = window.ADMIN_CONV || 0;
  var POLL_URL = window.ADMIN_POLL_URL || '../ajax/support-poll.php';
  var AJAX_URL = window.ADMIN_AJAX_URL || '../ajax/support.php';
  var lastMid = window.ADMIN_LASTID || 0;
  var sending = false;
  var chatBox = document.getElementById('supChatBox');
    var STATUSES = window.ADMIN_STATUSES || {open:['مفتوحة','#16a34a','#dcfce7'],pending:['قيد المراجعة','#d97706','#fef3c7'],closed:['مغلقة','#64748b','#e2e8f0']};

  var flInput = document.getElementById('supReplyFile');
  if (flInput) {
    flInput.addEventListener('change', function() {
      var fn = document.getElementById('supFileName');
      if (!fn) return;
      if (this.files.length) { fn.textContent = '📄 ' + this.files[0].name; fn.style.display = 'block'; }
      else fn.style.display = 'none';
    });
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = (s == null ? '' : String(s));
    return d.innerHTML;
  }
  function scrollToBottom() {
    if (chatBox) chatBox.scrollTo({top: chatBox.scrollHeight});
  }
    function toast(msg, type) {
    if (window.Toast) { window.Toast.show(msg, type || 'info'); }
    else { alert(msg); }
  }

  /* ── إضافة رسالة إلى صندوق الدردشة ── */
  function appendMsg(m, isTemp = false) {
    if (!chatBox) return;
    if (!isTemp) { document.querySelectorAll('.temp-msg').forEach(function(e){ e.remove(); }); }
    var ph = document.getElementById('supPlaceholder');
    if (ph) ph.remove();
    var isAdmin = (m.sender_type === 'admin');
    var wrap = document.createElement('div');
    wrap.className = 'sup-msg ' + (isAdmin ? 'sup-admin-msg' : 'sup-user-msg');
    wrap.setAttribute('data-msg-id', m.id);
    if (isTemp) wrap.classList.add('temp-msg');
    if (isTemp) wrap.style.opacity = '0.7';

    var nm = document.createElement('div');
    nm.style.cssText = 'font-size:11px;font-weight:700;margin-bottom:3px;';
    nm.textContent = isAdmin ? 'فريق الدعم' : (TAB === 'academics' ? 'الأكاديمي' : 'الطالب');
    wrap.appendChild(nm);

    if (m.file_name) {
      var row = document.createElement('div');
      row.style.marginBottom = m.content ? '6px' : '0';
      var a = document.createElement('a');
      a.href = APP_BASE + '/' + (m.file_path || '');
      a.target = '_blank';
      a.download = '';
      a.style.cssText = 'display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;max-width:220px;background:' + (isAdmin ? 'rgba(255,255,255,0.2)' : 'var(--primary-light)') + ';color:' + (isAdmin ? '#fff' : 'var(--primary)');
      a.innerHTML = '<span>📎</span><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(m.file_name) + '</span>';
      row.appendChild(a);
      wrap.appendChild(row);
    }

    if (m.content) {
      var t = document.createElement('div');
      t.innerHTML = esc(m.content).replace(/\n/g, '<br>');
      wrap.appendChild(t);
    }

    var time = document.createElement('div');
    time.className = 'sup-time';
    time.textContent = (m.created_at || '').substring(11, 16);
    wrap.appendChild(time);

    chatBox.appendChild(wrap);
  }

  /* ── استقصاء الرسائل والقوائم (كل 4 ثوانٍ) ── */
  async function poll() {
    if (document.hidden || sending) return;
    var res, data;
    try {
      res = await fetch(POLL_URL + '?tab=' + encodeURIComponent(TAB) + '&conv=' + CONV + '&after=' + lastMid, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
    } catch (e) { return; }
    if (!res.ok) return;
    try { data = await res.json(); } catch (e) { return; }
    if (!data || !data.ok) return;

    if (data.messages && data.messages.length) {
      data.messages.forEach(appendMsg);
      lastMid = data.last_id || lastMid;
      scrollToBottom();
    }

    /* تحديث قائمة التذاكر والشارات */
    if (window.__renderConvList) {
      var list = (TAB === 'academics') ? data.academics : data.students;
      try { window.__renderConvList(list || [], TAB); } catch (e) {}
    }
    var bS = document.getElementById('badgeStudents');
    var bA = document.getElementById('badgeAcademics');
    if (bS) { bS.textContent = data.unread_students || 0; bS.style.display = (data.unread_students > 0) ? '' : 'none'; }
    if (bA) { bA.textContent = data.unread_academics || 0; bA.style.display = (data.unread_academics > 0) ? '' : 'none'; }
  }
  setInterval(poll, 4000);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) poll();
  });
  scrollToBottom();

  /* ── إرسال رد الإدارة ── */
  window.sendSupReply = async function () {
    if (sending) return;
    var ta = document.getElementById('supReplyInput');
    var fl = document.getElementById('supReplyFile');
    var txt = (ta && ta.value) ? ta.value.trim() : '';
    var hasFile = fl && fl.files && fl.files.length > 0;

    if (!CONV) { toast('❌ اختر تذكرة أولاً', 'error'); return; }
    if (!txt && !hasFile) { toast('❌ اكتب ردك أو أرفق ملفاً', 'error'); return; }
    appendMsg({sender_type: 'admin', content: txt || 'ملف مرفق...', created_at: new Date().toISOString()}, true);
    scrollToBottom();

    sending = true;
    var btn = document.querySelector('button[onclick="sendSupReply()"]');
    if (btn) btn.disabled = true;

    try {
      var fd = new FormData();
      if(window.CSRF_TOKEN) { fd.append('csrf_token', window.CSRF_TOKEN); }
      fd.append('action', 'send_reply');
      fd.append('conv_id', CONV);
      fd.append('content', txt);
      if (hasFile) fd.append('file', fl.files[0]);

      var res = await fetch(AJAX_URL, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      var raw = await res.text();
      var d = null;
      try { d = JSON.parse(raw); } catch (e) { d = null; }

      if (d && d.ok) {
        if (ta) ta.value = '';
        if (fl) fl.value = '';
        var fn = document.getElementById('supFileName');
        if (fn) fn.style.display = 'none';
        await poll();
        toast('✅ تم الإرسال', 'success');
        // location.reload() removed to prevent flicker
      } else if (d && d.error) {
        toast('❌ ' + d.error, 'error');
      } else if (raw !== '') {
        toast('❌ فشل الإرسال (HTTP ' + res.status + '): ' + raw.substring(0, 300), 'error');
      } else {
        toast('❌ فشل الإرسال، حاول مجدداً.', 'error');
      }
    } catch (e) {
      toast('❌ خطأ شبكة: ' + e.message, 'error');
    } finally {
      sending = false;
      if (btn) btn.disabled = false;
    }
  };

  /* ── إغلاق / إعادة فتح تذكرة ── */
  window.closeConv = async function (id) {
    if (!confirm('🔒 إغلاق هذه التذكرة؟')) return;
    await updateStatus(id, 'closed');
  };
  window.reopenConv = async function (id) {
    await updateStatus(id, 'open');
  };
  async function updateStatus(id, status) {
    try {
      var fd = new FormData();
      if(window.CSRF_TOKEN) { fd.append('csrf_token', window.CSRF_TOKEN); }
      fd.append('action', 'set_status');
      fd.append('conv_id', id);
      fd.append('status', status);
      var res = await fetch(AJAX_URL, { method: 'POST', body: fd });
      var d = await res.json();
      if (d.ok) location.reload();
      else toast('❌ ' + (d.error || 'خطأ'), 'error');
    } catch (e) {
      toast('❌ خطأ شبكة', 'error');
    }
  }

  /* ─-- إدارة الأسئلة الشائعة ─-- */
  window.openFaqModal = function (id) {
    var faqId = document.getElementById('faqId');
    if (faqId) faqId.value = id || 0;
    var title = document.getElementById('faqModalTitle');
    if (title) title.textContent = id ? 'تعديل سؤال' : 'إضافة سؤال شائع';
    var m = document.getElementById('faqModal');
    if (m) {
      if (window.Modal) window.Modal.open('faqModal');
      else m.classList.add('active');
    }
  };
  window.saveFaq = async function () {
    var fd = new FormData();
      if(window.CSRF_TOKEN) { fd.append('csrf_token', window.CSRF_TOKEN); }
    fd.append('action', 'save_faq');
    fd.append('id', document.getElementById('faqId').value);
    fd.append('audience', document.getElementById('faqAudience').value);
    fd.append('question', document.getElementById('faqQuestion').value);
    fd.append('answer', document.getElementById('faqAnswer').value);
    fd.append('sort_order', document.getElementById('faqSort').value);
    fd.append('is_active', document.getElementById('faqActive').value);
    var d = await postJson(fd);
    if (d && d.ok) {
      if (window.Modal) window.Modal.close('faqModal');
      else document.getElementById('faqModal').classList.remove('active');
      location.reload();
    } else if (d) {
      toast('❌ ' + (d.error || 'خطأ'), 'error');
    }
  };
  window.deleteFaq = async function (id) {
    if (!confirm('🗑️ حذف هذا السؤال؟')) return;
    var fd = new FormData();
      if(window.CSRF_TOKEN) { fd.append('csrf_token', window.CSRF_TOKEN); }
    fd.append('action', 'delete_faq');
    fd.append('id', id);
    var d = await postJson(fd);
    if (d && d.ok) location.reload();
    else if (d) toast('❌ ' + (d.error || 'خطأ'), 'error');
  };

  /* ─-- إدارة قنوات التواصل ─-- */
  window.openChannelModal = function (id) {
    var chId = document.getElementById('channelId');
    if (chId) chId.value = id || 0;
    var title = document.getElementById('channelModalTitle');
    if (title) title.textContent = id ? 'تعديل قناة' : 'إضافة قناة تواصل';
    var m = document.getElementById('channelModal');
    if (m) {
      if (window.Modal) window.Modal.open('channelModal');
      else m.classList.add('active');
    }
  };
  window.saveChannel = async function () {
    var fd = new FormData();
      if(window.CSRF_TOKEN) { fd.append('csrf_token', window.CSRF_TOKEN); }
    fd.append('action', 'save_channel');
    fd.append('id', document.getElementById('channelId').value);
    fd.append('type', document.getElementById('channelType').value);
    fd.append('label', document.getElementById('channelLabel').value);
    fd.append('value', document.getElementById('channelValue').value);
    fd.append('sort_order', document.getElementById('channelSort').value);
    fd.append('is_active', document.getElementById('channelActive').value);
    var d = await postJson(fd);
    if (d && d.ok) {
      if (window.Modal) window.Modal.close('channelModal');
      else document.getElementById('channelModal').classList.remove('active');
      location.reload();
    } else if (d) {
      toast('❌ ' + (d.error || 'خطأ'), 'error');
    }
  };
  window.deleteChannel = async function (id) {
    if (!confirm('🗑️ حذف هذه القناة؟')) return;
    var fd = new FormData();
      if(window.CSRF_TOKEN) { fd.append('csrf_token', window.CSRF_TOKEN); }
    fd.append('action', 'delete_channel');
    fd.append('id', id);
    var d = await postJson(fd);
    if (d && d.ok) location.reload();
    else if (d) toast('❌ ' + (d.error || 'خطأ'), 'error');
  };

  /* ─-- دوال مساعدة ─-- */
  async function postJson(fd) {
    try {
      var res = await fetch(AJAX_URL, { method: 'POST', body: fd });
      var txt = await res.text();
      try { return JSON.parse(txt); } catch (e) { return { ok: false, error: txt.substring(0, 300) }; }
    } catch (e) {
      return { ok: false, error: 'Network error: ' + e.message };
    }
  }
  scrollToBottom();
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