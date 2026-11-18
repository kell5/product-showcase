/**
 * 站内 AI 助手（自包含组件）
 * 只要页面引入本文件，就会在右下角出现一个悬浮入口。
 * 也可以在任意按钮上加 data-ai-open 属性，点它同样能打开面板。
 *
 * 后端：api.php?action=ai_status / ai_chat
 * 回答只基于站内选题库与项目页做检索后生成，来源会以卡片形式列在回答下面。
 */
(function () {
  'use strict';

  var API = 'api.php';
  var MAX_Q = 200;

  var status = null;
  var isOpen = false;
  var busy = false;
  var mounted = false;
  var asked = 0;
  var history = [];       // 仅存在内存里，刷新即清空
  var ui = {};

  /* ─────────── 小工具 ─────────── */
  function esc(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /** 极简排版：**粗体**、[1] 引用、短横线列表、空行分段 */
  function formatAnswer(raw) {
    var safe = esc(raw);
    safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    safe = safe.replace(/\[(P?\d{1,2})\]/g, '<span class="aiw-cite">$1</span>');

    var lines = safe.split(/\n/);
    var out = [];
    var inList = false;

    lines.forEach(function (line) {
      var m = line.match(/^\s*(?:[-*·]|\d+[.、])\s+(.*)$/);
      if (m) {
        if (!inList) { out.push('<ul>'); inList = true; }
        out.push('<li>' + m[1] + '</li>');
      } else {
        if (inList) { out.push('</ul>'); inList = false; }
        if (line.trim()) out.push('<p>' + line + '</p>');
      }
    });
    if (inList) out.push('</ul>');
    return out.join('');
  }

  function citeLabel(cite) {
    return String(cite || '').indexOf('P') === 0 ? 'P' + cite.slice(1) : cite;
  }

  /* ─────────── 构建 DOM ─────────── */
  function build() {
    var launcher = document.createElement('button');
    launcher.type = 'button';
    launcher.className = 'aiw-launcher';
    launcher.setAttribute('aria-label', '打开站内助手');
    launcher.innerHTML =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.9 9.9 0 0 1-4.2-.9L3 21l1.9-4.6A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/>' +
      '<path d="M9 10h6"/><path d="M9 13.5h4"/></svg>' +
      '<span class="aiw-launcher-text">问选题</span>';

    var panel = document.createElement('div');
    panel.className = 'aiw-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', '站内助手');
    panel.innerHTML =
      '<div class="aiw-header">' +
        '<div class="aiw-head-main">' +
          '<span class="aiw-dot"></span>' +
          '<div>' +
            '<strong>站内助手</strong>' +
            '<span class="aiw-model">' + esc(status.model || 'GLM') + '</span>' +
          '</div>' +
        '</div>' +
        '<button type="button" class="aiw-close" aria-label="关闭">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>' +
        '</button>' +
      '</div>' +
      '<div class="aiw-body">' +
        '<div class="aiw-msg aiw-msg--bot">' +
          '<div class="aiw-bubble">' + formatAnswer(
            '我是这个站的助手，只根据站内的 699 个选题和 5 个项目页回答。\n' +
            '说说你手上的主控（STM32 / 51 / ESP32 / K210）、想做的方向，或者直接问某个题目怎么做。'
          ) + '</div>' +
        '</div>' +
        '<div class="aiw-chips" data-chips></div>' +
      '</div>' +
      '<form class="aiw-form">' +
        '<textarea class="aiw-input" rows="1" maxlength="' + MAX_Q + '" placeholder="例如：只有 51 单片机，做个门禁能行吗"></textarea>' +
        '<button type="submit" class="aiw-send" aria-label="发送">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>' +
        '</button>' +
      '</form>' +
      '<div class="aiw-foot"><span data-quota>答案由 AI 生成，仅供参考</span><a href="index.html#guestbook">没解决？留言</a></div>';

    document.body.appendChild(launcher);
    document.body.appendChild(panel);

    ui.launcher = launcher;
    ui.panel = panel;
    ui.body = panel.querySelector('.aiw-body');
    ui.form = panel.querySelector('.aiw-form');
    ui.input = panel.querySelector('.aiw-input');
    ui.send = panel.querySelector('.aiw-send');
    ui.chips = panel.querySelector('[data-chips]');
    ui.quota = panel.querySelector('[data-quota]');

    renderChips(status.presets || []);
    updateQuota();

    launcher.addEventListener('click', toggle);
    panel.querySelector('.aiw-close').addEventListener('click', close);
    ui.form.addEventListener('submit', onSubmit);
    ui.input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        submitCurrent();
      }
    });
    ui.input.addEventListener('input', function () {
      ui.input.style.height = 'auto';
      ui.input.style.height = Math.min(ui.input.scrollHeight, 120) + 'px';
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) close();
    });

    // 任意位置带 data-ai-open 的按钮都能打开面板
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest && e.target.closest('[data-ai-open]');
      if (trigger) {
        e.preventDefault();
        open();
      }
    });
  }

  function renderChips(list) {
    if (!ui.chips || !list.length) return;
    ui.chips.innerHTML = '';
    list.slice(0, 4).forEach(function (text) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'aiw-chip';
      b.textContent = text;
      b.addEventListener('click', function () {
        ui.input.value = text;
        submitCurrent();
      });
      ui.chips.appendChild(b);
    });
  }

  function updateQuota() {
    if (!ui.quota) return;
    if (status && typeof status.perIp === 'number') {
      var left = Math.max(0, status.perIp - asked);
      ui.quota.textContent = left > 0
        ? '今日还可问 ' + left + ' 次 · 答案由 AI 生成'
        : '今日次数已用完，可以明天再来或直接留言';
    }
  }

  /* ─────────── 交互 ─────────── */
  function open() {
    if (!mounted) return;
    isOpen = true;
    ui.panel.classList.add('is-open');
    ui.launcher.classList.add('is-hidden');
    document.body.classList.add('aiw-lock');
    if (window.innerWidth > 768) {
      setTimeout(function () { ui.input.focus(); }, 180);
    }
  }

  function close() {
    isOpen = false;
    ui.panel.classList.remove('is-open');
    ui.launcher.classList.remove('is-hidden');
    document.body.classList.remove('aiw-lock');
  }

  function toggle() {
    if (isOpen) close(); else open();
  }

  function onSubmit(e) {
    e.preventDefault();
    submitCurrent();
  }

  function scrollBottom() {
    ui.body.scrollTop = ui.body.scrollHeight;
  }

  function appendUser(text) {
    var wrap = document.createElement('div');
    wrap.className = 'aiw-msg aiw-msg--user';
    var bubble = document.createElement('div');
    bubble.className = 'aiw-bubble';
    bubble.textContent = text;
    wrap.appendChild(bubble);
    ui.body.appendChild(wrap);
    scrollBottom();
  }

  function appendTyping() {
    var wrap = document.createElement('div');
    wrap.className = 'aiw-msg aiw-msg--bot';
    wrap.setAttribute('data-typing', '1');
    wrap.innerHTML = '<div class="aiw-bubble aiw-typing"><span></span><span></span><span></span></div>';
    ui.body.appendChild(wrap);
    scrollBottom();
    return wrap;
  }

  function appendBot(text, sources) {
    var wrap = document.createElement('div');
    wrap.className = 'aiw-msg aiw-msg--bot';

    // 气泡与来源卡片竖排，否则 flex 行布局会把来源挤到气泡右边
    var stack = document.createElement('div');
    stack.className = 'aiw-stack';

    var bubble = document.createElement('div');
    bubble.className = 'aiw-bubble';
    bubble.innerHTML = formatAnswer(text);
    stack.appendChild(bubble);

    if (sources && sources.length) {
      var box = document.createElement('div');
      box.className = 'aiw-sources';
      var label = document.createElement('span');
      label.className = 'aiw-sources-label';
      label.textContent = '参考';
      box.appendChild(label);

      sources.forEach(function (s) {
        var a = document.createElement('a');
        a.className = 'aiw-source';
        a.href = s.url || '#';
        a.innerHTML =
          '<span class="aiw-source-cite">' + esc(citeLabel(s.cite)) + '</span>' +
          '<span class="aiw-source-body">' +
            '<span class="aiw-source-title">' + esc(s.title) + '</span>' +
            (s.meta ? '<span class="aiw-source-meta">' + esc(s.meta) + '</span>' : '') +
          '</span>';
        box.appendChild(a);
      });
      stack.appendChild(box);
    }

    wrap.appendChild(stack);
    ui.body.appendChild(wrap);
    scrollBottom();
  }

  function appendError(text) {
    var wrap = document.createElement('div');
    wrap.className = 'aiw-msg aiw-msg--bot';
    wrap.innerHTML = '<div class="aiw-bubble aiw-bubble--warn">' + esc(text) + '</div>';
    ui.body.appendChild(wrap);
    scrollBottom();
  }

  function setBusy(state) {
    busy = state;
    ui.send.disabled = state;
    ui.input.disabled = state;
    ui.panel.classList.toggle('is-busy', state);
  }

  function submitCurrent() {
    if (busy || !mounted) return;
    var text = (ui.input.value || '').trim();
    if (!text) return;

    if (status && typeof status.perIp === 'number' && asked >= status.perIp) {
      appendError('今天的提问次数已经用完了，可以明天再来，或直接在留言板提问。');
      return;
    }

    ui.input.value = '';
    ui.input.style.height = 'auto';
    if (ui.chips) ui.chips.remove();
    appendUser(text);
    history.push({ role: 'user', content: text });

    var typing = appendTyping();
    setBusy(true);

    fetch(API + '?action=ai_chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question: text, history: history.slice(-5, -1) })
    })
      .then(function (resp) {
        return resp.json().then(function (json) {
          return { ok: resp.ok, status: resp.status, json: json };
        });
      })
      .then(function (res) {
        typing.remove();
        if (!res.ok || !res.json || res.json.success !== true) {
          var msg = (res.json && res.json.message) || '助手暂时不可用，请稍后再试';
          appendError(msg);
          setBusy(false);
          return;
        }
        asked += 1;
        appendBot(res.json.answer || '', res.json.sources || []);
        history.push({ role: 'assistant', content: res.json.answer || '' });
        updateQuota();
        setBusy(false);
      })
      .catch(function () {
        typing.remove();
        appendError('网络好像断了，请稍后再试。');
        setBusy(false);
      });
  }

  /* ─────────── 启动 ─────────── */
  function mount() {
    if (mounted) return;
    mounted = true;
    build();
  }

  function init() {
    fetch(API + '?action=ai_status', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.enabled !== true) return;   // 后台没开就不显示入口
        status = res;
        mount();
      })
      .catch(function () { /* 接口异常时静默降级，不影响页面本身 */ });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
