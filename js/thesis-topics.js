/**
 * 毕设题目参考专栏
 * 数据来源：js/thesis-topics.data.js（window.THESIS_TOPICS）
 * 默认视图：按方向分区块展示精选题目，可逐块展开全部
 * 检索视图：选择单一方向或输入关键词时切换为平铺列表 + 分批加载
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  initWechatButton();

  const data = window.THESIS_TOPICS;
  const grid = document.getElementById('thesisGrid');
  if (!data || !grid) return;

  const PAGE_SIZE = 24;
  const PREVIEW_SIZE = 6;

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
      setCategory(cat.id);
      document.getElementById('topics').scrollIntoView({ behavior: 'smooth', block: 'start' });
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
    setCategory(0);
  });

  moreBtn.addEventListener('click', () => renderNextPage());

  /* ─────────── 卡片交互：复制题目 / 展开方向 ─────────── */
  grid.addEventListener('click', (e) => {
    const expandBtn = e.target.closest('.thesis-cat-expand');
    if (expandBtn) {
      expandCategoryBlock(expandBtn);
      return;
    }
    const moreLink = e.target.closest('.thesis-cat-all');
    if (moreLink) {
      setCategory(Number(moreLink.dataset.category));
      document.getElementById('topics').scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
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
  function setCategory(id) {
    activeCategory = id;
    filtersBox.querySelectorAll('.thesis-chip').forEach((el) => {
      el.classList.toggle('active', el.dataset.category === String(id));
    });
    applyFilters();
  }

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

    const grouped = activeCategory === 0 && !keyword;
    grid.classList.toggle('thesis-grid--grouped', grouped);

    if (grouped) {
      moreBtn.hidden = true;
      renderGrouped();
    } else {
      renderNextPage();
    }
  }

  /* 默认视图：每个方向一个区块，先展示精选题目 */
  function renderGrouped() {
    const frag = document.createDocumentFragment();
    data.categories.forEach((cat) => {
      const block = document.createElement('div');
      block.className = 'thesis-cat-block';
      block.dataset.category = String(cat.id);

      const head = document.createElement('div');
      head.className = 'thesis-cat-head';
      head.innerHTML =
        '<div class="thesis-cat-heading">' +
          '<h3 class="thesis-cat-name">' + escapeHtml(cat.name) + '</h3>' +
          '<span class="thesis-cat-count">' + cat.count + ' 个题目</span>' +
        '</div>' +
        '<div class="thesis-cat-actions">' +
          (cat.count > PREVIEW_SIZE
            ? '<button type="button" class="thesis-cat-expand">展开全部 ' + cat.count + ' 个</button>'
            : '') +
          '<button type="button" class="thesis-cat-all" data-category="' + cat.id + '">只看这个方向</button>' +
        '</div>';
      block.appendChild(head);

      const inner = document.createElement('div');
      inner.className = 'thesis-cat-grid';
      pickPreview(cat).forEach((topic) => {
        inner.appendChild(buildCard(withCategory(topic, cat)));
      });
      block.appendChild(inner);
      frag.appendChild(block);
    });
    grid.appendChild(frag);
  }

  /* 精选：在方向内均匀取样，避免每个方向都只看到开头几条 */
  function pickPreview(cat) {
    const list = cat.topics;
    if (list.length <= PREVIEW_SIZE) return list.slice();
    const step = list.length / PREVIEW_SIZE;
    const picked = [];
    for (let i = 0; i < PREVIEW_SIZE; i += 1) {
      picked.push(list[Math.floor(i * step)]);
    }
    return picked;
  }

  function expandCategoryBlock(btn) {
    const block = btn.closest('.thesis-cat-block');
    const catId = Number(block.dataset.category);
    const cat = data.categories.filter((c) => c.id === catId)[0];
    const inner = block.querySelector('.thesis-cat-grid');
    inner.innerHTML = '';
    const frag = document.createDocumentFragment();
    cat.topics.forEach((topic) => frag.appendChild(buildCard(withCategory(topic, cat))));
    inner.appendChild(frag);
    btn.remove();
  }

  function withCategory(topic, cat) {
    return {
      no: topic.no,
      title: topic.title,
      stack: topic.stack,
      highlights: topic.highlights || [],
      categoryName: cat.name,
    };
  }

  /* 检索视图：平铺 + 分批加载 */
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

  /* 咨询区：点击显示并复制微信号 */
  function initWechatButton() {
    const btn = document.getElementById('thesisWechatBtn');
    const tip = document.getElementById('thesisWechatTip');
    if (!btn || !tip) return;
    const wechat = btn.dataset.wechat;
    btn.addEventListener('click', () => {
      tip.hidden = false;
      copyText(wechat).then((ok) => {
        tip.textContent = ok
          ? '微信号 ' + wechat + ' 已复制，打开微信「添加朋友」粘贴即可'
          : '微信号：' + wechat + '（长按或选中复制）';
      });
    });
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
  }
});
