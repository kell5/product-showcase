/**
 * 留言板组件
 * 用法：页面里放一个容器
 *   <section class="section" id="guestbook">
 *     <div class="container"><div data-guestbook></div></div>
 *   </section>
 * 然后引入本脚本即可，脚本自己渲染表单和列表。
 */

(function () {
  'use strict';

  var API = 'api.php';
  var MAX_NAME = 20;
  var MAX_BODY = 500;

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatTime(iso) {
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    var diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return '刚刚';
    if (diff < 3600) return Math.floor(diff / 60) + ' 分钟前';
    if (diff < 86400) return Math.floor(diff / 3600) + ' 小时前';
    if (diff < 86400 * 7) return Math.floor(diff / 86400) + ' 天前';
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  function pad(n) {
    return n < 10 ? '0' + n : String(n);
  }

  var TEMPLATE = [
    '<div class="gb-head">',
    '  <span class="gb-icon">',
    '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">',
    '      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
    '    </svg>',
    '  </span>',
    '  <div>',
    '    <h2 class="section-title">留言<span class="gradient-text">板</span></h2>',
    '    <p class="gb-sub">选题上的疑问、项目里的坑、想看的教程方向，都可以写在这里。</p>',
    '  </div>',
    '</div>',
    '<form class="gb-form" novalidate>',
    '  <div class="gb-row">',
    '    <label class="gb-field">',
    '      <span>昵称</span>',
    '      <input type="text" name="name" maxlength="' + MAX_NAME + '" placeholder="怎么称呼你" required />',
    '    </label>',
    '    <label class="gb-field gb-field--grow">',
    '      <span>留言</span>',
    '      <textarea name="content" rows="3" maxlength="' + MAX_BODY + '" placeholder="例如：基于 ESP32 的智能门禁，如果改成离线人脸识别可行吗？" required></textarea>',
    '    </label>',
    '  </div>',
    '  <div class="gb-actions">',
    '    <span class="gb-hint" data-hint>内容会公开显示，请勿填写隐私信息</span>',
    '    <button type="submit" class="btn btn-primary">提交留言</button>',
    '  </div>',
    '  <div class="gb-hp" aria-hidden="true">',
    '    <label>网址<input type="text" name="website" tabindex="-1" autocomplete="off" /></label>',
    '  </div>',
    '</form>',
    '<div class="gb-list" data-list><div class="gb-empty">正在加载留言…</div></div>'
  ].join('\n');

  function renderList(listEl, messages) {
    if (!messages.length) {
      listEl.innerHTML = '<div class="gb-empty">还没有留言，来写第一条吧。</div>';
      return;
    }
    listEl.innerHTML = messages.map(function (m) {
      var initial = esc((m.name || '?').trim().charAt(0));
      return [
        '<article class="gb-item">',
        '  <div class="gb-avatar">' + initial + '</div>',
        '  <div class="gb-body">',
        '    <div class="gb-meta">',
        '      <strong>' + esc(m.name) + '</strong>',
        '      <time>' + esc(formatTime(m.createdAt)) + '</time>',
        '    </div>',
        '    <p class="gb-text">' + esc(m.content) + '</p>',
        '  </div>',
        '</article>'
      ].join('');
    }).join('');
  }

  function loadMessages(listEl) {
    fetch(API + '?action=list_messages', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        renderList(listEl, (json && json.messages) || []);
      })
      .catch(function () {
        listEl.innerHTML = '<div class="gb-empty">留言加载失败，刷新页面试试。</div>';
      });
  }

  function mount(root) {
    root.innerHTML = TEMPLATE;

    var form = root.querySelector('.gb-form');
    var listEl = root.querySelector('[data-list]');
    var hint = root.querySelector('[data-hint]');
    var button = form.querySelector('button[type="submit"]');
    var nameInput = form.querySelector('[name="name"]');
    var contentInput = form.querySelector('[name="content"]');
    var websiteInput = form.querySelector('[name="website"]');

    loadMessages(listEl);

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var name = (nameInput.value || '').trim();
      var content = (contentInput.value || '').trim();
      var website = websiteInput ? websiteInput.value : '';

      if (!name) {
        setHint('请先填个昵称', true);
        nameInput.focus();
        return;
      }
      if (content.length < 2) {
        setHint('留言内容太短了', true);
        contentInput.focus();
        return;
      }

      button.disabled = true;
      setHint('正在提交…', false, true);

      fetch(API + '?action=add_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name, content: content, website: website })
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, data: j }; }); })
        .then(function (res) {
          if (res.ok && res.data.success) {
            contentInput.value = '';
            setHint('留言已发布，谢谢！', false);
            loadMessages(listEl);
          } else {
            setHint((res.data && res.data.message) || '提交失败，请稍后再试', true);
          }
        })
        .catch(function () {
          setHint('网络异常，请稍后再试', true);
        })
        .then(function () {
          button.disabled = false;
        });
    });

    function setHint(text, isError, isLoading) {
      hint.textContent = text;
      hint.classList.toggle('is-error', !!isError);
      hint.classList.toggle('is-loading', !!isLoading);
    }
  }

  function init() {
    var hosts = document.querySelectorAll('[data-guestbook]');
    for (var i = 0; i < hosts.length; i++) {
      mount(hosts[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
