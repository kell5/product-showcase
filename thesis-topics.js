/**
 * 毕设题目参考专栏
 * 数据来源：js/thesis-topics.data.js（window.THESIS_TOPICS）
 * 功能：方向筛选 + 关键词搜索 + 分批渲染 + 点击复制题目
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const data = window.THESIS_TOPICS;
  const grid = document.getElementById('thesisGrid');
  if (!data || !grid) return;

  const PAGE_SIZE = 24;

  const filtersBox = document.getElementById('thesisFilters');
  const searchInput = document.getElementById('thesisSearch');
  const countLabel = document.getElementById('thesisCount');
  const resetBtn = document.getElementById('thesisReset');
  const emptyBox = document.getElementById('thesisEmpty');
  const moreBtn = document.getElementById('thesisMore');

  const allTopics = [];
  data.categories.forEach((cat) => {
    cat.topics.forEach((topic) => {
      allTopics.push({
        no: topic.no,
        title: topic.title,
        stack: topic.stack,
        highlights: topic.highlights || [],
        categoryId: cat.id,
        categoryName: cat.name,
        haystack: (topic.title + ' ' + topic.stack + ' ' + (topic.highlights || []).join(' ')).toLowerCase(),
      });
    });
  });

  let activeCategory = 0; // 0 = 全部
  let keyword = '';
  let shown = 0;
  let filtered = allTopics;

  /* ─────────── 方向筛选按钮 ─────────── */
  const categories = [{ id: 0, name: '全部方向', count: allTopics.length }].concat(
    data.categories.map((c) => ({ id: c.id, name: c.name, count: c.count }))
  );

  categories.forEach((cat) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'thesis-chip' + (cat.id === 0 ? ' active' : '');
    btn.dataset.category = String(cat.id);
    btn.innerHTML = escapeHtml(cat.name) + '<span class="thesis-chip-num">' + cat.count + '</span>';
    btn.addEventListener('click', () => {
      activeCategory = cat.id;
      filtersBox.querySelectorAll('.thesis-chip').forEach((el) => {
        el.classList.toggle('active', el.dataset.category === String(cat.id));
      });
      applyFilters();
    });
    filtersBox.appendChild(btn);
  });

  /* ─────────── 搜索 ─────────── */
  let searchTimer = null;
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      keyword = searchInput.value.trim().toLowerCase();
      applyFilters();
    }, 160);
  });

  resetBtn.addEventListener('click', () => {
    searchInput.value = '';
    keyword = '';
    activeCategory = 0;
    filtersBox.querySelectorAll('.thesis-chip').forEach((el) => {
      el.classList.toggle('active', el.dataset.category === '0');
    });
    applyFilters();
  });

  moreBtn.addEventListener('click', () => renderNextPage());

  /* ─────────── 点击卡片复制题目 ─────────── */
  grid.addEventListener('click', (e) => {
    const card = e.target.closest('.thesis-card');
    if (!card) return;
    copyText(card.dataset.title).then((ok) => {
      const tip = card.querySelector('.thesis-card-copy');
      if (!tip) return;
      tip.textContent = ok ? '已复制题目' : '复制失败，请手动选中';
      card.classList.add('copied');
      setTimeout(() => {
        card.classList.remove('copied');
        tip.textContent = '点击复制题目';
      }, 1600);
    });
  });

  applyFilters();

  /* ─────────── 逻辑 ─────────── */
  function applyFilters() {
    const words = keyword ? keyword.split(/\s+/).filter(Boolean) : [];
    filtered = allTopics.filter((t) => {
      if (activeCategory !== 0 && t.categoryId !== activeCategory) return false;
      return words.every((w) => t.haystack.indexOf(w) !== -1);
    });

    grid.innerHTML = '';
    shown = 0;
    countLabel.textContent =
      filtered.length === allTopics.length
        ? '共 ' + allTopics.length + ' 个题目'
        : '筛选到 ' + filtered.length + ' / ' + allTopics.length + ' 个题目';
    emptyBox.hidden = filtered.length !== 0;
    renderNextPage();
  }

  function renderNextPage() {
    const slice = filtered.slice(shown, shown + PAGE_SIZE);
    const frag = document.createDocumentFragment();
    slice.forEach((topic) => frag.appendChild(buildCard(topic)));
    grid.appendChild(frag);
    shown += slice.length;
    moreBtn.hidden = shown >= filtered.length;
    moreBtn.textContent = '加载更多题目（剩余 ' + Math.max(filtered.length - shown, 0) + '）';
  }

  function buildCard(topic) {
    const card = document.createElement('article');
    card.className = 'thesis-card';
    card.dataset.title = topic.title;
    card.setAttribute('title', '点击复制题目');

    const tags = topic.highlights
      .slice(0, 4)
      .map((h) => '<span class="thesis-tag">' + escapeHtml(h) + '</span>')
      .join('');

    card.innerHTML =
      '<div class="thesis-card-top">' +
        '<span class="thesis-card-cat">' + escapeHtml(topic.categoryName) + '</span>' +
        '<span class="thesis-card-no">#' + topic.no + '</span>' +
      '</div>' +
      '<h3 class="thesis-card-title">' + escapeHtml(topic.title) + '</h3>' +
      '<p class="thesis-card-stack">' + escapeHtml(topic.stack) + '</p>' +
      '<div class="thesis-card-tags">' + tags + '</div>' +
      '<div class="thesis-card-copy">点击复制题目</div>';
    return card;
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).then(() => true).catch(() => fallbackCopy(text));
    }
    return Promise.resolve(fallbackCopy(text));
  }

  function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    let ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (err) {
      ok = false;
    }
    document.body.removeChild(ta);
    return ok;
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
  }
});
