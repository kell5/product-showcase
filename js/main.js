/**
 * 产品展示网站 - 主交互脚本
 * 模块：
 *   1. 滚动显现动画 (Intersection Observer)
 *   2. 导航栏滚动效果
 *   3. 滚动进度条
 *   4. 数字计数动画
 *   5. 光标追踪光效
 *   6. 平滑锚点跳转
 *   7. 移动端导航菜单
 *   8. 磁吸按钮（可选）
 *   9. 回到顶部按钮
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  /* ─────────── 0. 安全工具 ───────────
   * 后台录入的内容在拼接 innerHTML 前必须转义，
   * 否则产品名里写入 <img onerror=...> 就能在访客浏览器执行脚本。 */
  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // 只放行站内相对路径的图片地址，挡掉 javascript: / data: 等协议
  function safeImgUrl(value) {
    const url = String(value == null ? '' : value).trim();
    if (!url) return '';
    if (/^(https?:)?\/\//i.test(url) || /^[a-z0-9_\-\/\.]+\.(jpe?g|png|gif|webp|svg)$/i.test(url)) {
      return esc(url);
    }
    return '';
  }

  /* ─────────── 1. 滚动显现动画 ─────────── */
  const revealElements = document.querySelectorAll(
    '.reveal, .reveal-left, .reveal-right, .reveal-scale'
  );

  const revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          // 一旦可见后不再重复触发（性能友好）
          revealObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
  );

  revealElements.forEach((el) => revealObserver.observe(el));

  /* ─────────── 2. 导航栏滚动效果 ─────────── */
  const navbar = document.querySelector('.navbar');
  const onNavScroll = () => {
    if (!navbar) return;
    navbar.classList.toggle('scrolled', window.scrollY > 60);
  };
  window.addEventListener('scroll', onNavScroll, { passive: true });
  onNavScroll();

  /* ─────────── 3. 滚动进度条 ─────────── */
  const progressBar = document.querySelector('.scroll-progress');
  const onScrollProgress = () => {
    if (!progressBar) return;
    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = docHeight > 0 ? scrollTop / docHeight : 0;
    progressBar.style.transform = `scaleX(${progress})`;
  };
  window.addEventListener('scroll', onScrollProgress, { passive: true });

  /* ─────────── 4. 数字计数动画 ─────────── */
  const countElements = document.querySelectorAll('[data-count]');

  const countObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCount(entry.target);
          countObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 }
  );

  countElements.forEach((el) => countObserver.observe(el));

  function animateCount(el) {
    const target = parseInt(el.getAttribute('data-count'), 10);
    const suffix = el.getAttribute('data-suffix') || '';
    const prefix = el.getAttribute('data-prefix') || '';
    const duration = 2000;
    const start = performance.now();

    function tick(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      // easeOutExpo
      const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
      const current = Math.floor(eased * target);
      el.textContent = prefix + current.toLocaleString() + suffix;
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  /* ─────────── 5. 光标追踪光效 ─────────── */
  const cursorGlow = document.querySelector('.cursor-glow');
  if (cursorGlow && window.matchMedia('(pointer: fine)').matches) {
    document.addEventListener('mousemove', (e) => {
      cursorGlow.style.left = e.clientX + 'px';
      cursorGlow.style.top = e.clientY + 'px';
    });
  }

  /* ─────────── 6. 平滑锚点跳转 ─────────── */
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = anchor.getAttribute('href');
      if (targetId === '#') return;
      const target = document.querySelector(targetId);
      if (target) {
        const offsetTop = target.getBoundingClientRect().top + window.scrollY - 80;
        window.scrollTo({ top: offsetTop, behavior: 'smooth' });
      }
      // 关闭移动端菜单
      const navLinks = document.querySelector('.nav-links');
      if (navLinks) navLinks.classList.remove('open');
    });
  });

  /* ─────────── 7. 移动端导航菜单 ─────────── */
  const navToggle = document.querySelector('.nav-toggle');
  const navLinks = document.querySelector('.nav-links');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => {
      navLinks.classList.toggle('open');
      const isOpen = navLinks.classList.contains('open');
      const spans = navToggle.querySelectorAll('span');
      if (isOpen) {
        spans[0].style.transform = 'translateY(7px) rotate(45deg)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'translateY(-7px) rotate(-45deg)';
      } else {
        spans[0].style.transform = '';
        spans[1].style.opacity = '';
        spans[2].style.transform = '';
      }
    });
  }

  /* ─────────── 8. 磁吸按钮效果 ─────────── */
  document.querySelectorAll('.magnetic-area').forEach((area) => {
    area.addEventListener('mousemove', (e) => {
      const rect = area.getBoundingClientRect();
      const x = e.clientX - rect.left - rect.width / 2;
      const y = e.clientY - rect.top - rect.height / 2;
      area.style.transform = `translate(${x * 0.15}px, ${y * 0.15}px)`;
    });
    area.addEventListener('mouseleave', () => {
      area.style.transform = '';
    });
  });

  /* ─────────── 9. 视差滚动效果 ─────────── */
  const parallaxElements = document.querySelectorAll('[data-parallax]');
  if (parallaxElements.length) {
    window.addEventListener('scroll', () => {
      const scrollY = window.scrollY;
      parallaxElements.forEach((el) => {
        const speed = parseFloat(el.getAttribute('data-parallax')) || 0.1;
        const rect = el.getBoundingClientRect();
        const centerY = rect.top + rect.height / 2;
        const offset = (centerY - window.innerHeight / 2) * speed;
        el.style.transform = `translateY(${offset}px)`;
      });
    }, { passive: true });
  }

  /* ─────────── 10. 打字机效果（Hero 标题） ─────────── */
  const typewriterEl = document.querySelector('[data-typewriter]');
  if (typewriterEl) {
    const phrases = JSON.parse(typewriterEl.getAttribute('data-typewriter'));
    let phraseIndex = 0;
    let charIndex = 0;
    let isDeleting = false;
    const typeSpeed = 80;
    const deleteSpeed = 40;
    const pauseDuration = 2000;

    function typewriter() {
      const current = phrases[phraseIndex];
      if (isDeleting) {
        typewriterEl.textContent = current.substring(0, charIndex - 1);
        charIndex--;
      } else {
        typewriterEl.textContent = current.substring(0, charIndex + 1);
        charIndex++;
      }

      let delay = isDeleting ? deleteSpeed : typeSpeed;

      if (!isDeleting && charIndex === current.length) {
        delay = pauseDuration;
        isDeleting = true;
      } else if (isDeleting && charIndex === 0) {
        isDeleting = false;
        phraseIndex = (phraseIndex + 1) % phrases.length;
        delay = 400;
      }

      setTimeout(typewriter, delay);
    }

    setTimeout(typewriter, 1000);
  }

  /* ─────────── Tilt card effect on product cards ─────────── */
  document.querySelectorAll('.product-card').forEach((card) => {
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width - 0.5;
      const y = (e.clientY - rect.top) / rect.height - 0.5;
      card.style.transform = `
        translateY(-8px)
        perspective(1000px)
        rotateY(${x * 6}deg)
        rotateX(${-y * 6}deg)
      `;
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
    });
  });

  /* ─────────── 11. 动态加载管理后台添加的产品 ───────────
   * 首页的「精选作品」是静态写好的 4 张卡，后台把某个产品标为核心产品后
   * 会追加到这里。只在真正需要时才请求接口，避免每次访问都打一发空请求。 */
  let productsCache = null;

  async function fetchProducts() {
    if (productsCache !== null) return productsCache;
    try {
      const resp = await fetch('api.php?action=list');
      const json = await resp.json();
      productsCache = json.products || [];
    } catch {
      productsCache = [];
    }
    return productsCache;
  }

  async function loadDynamicProducts() {
    const productsGrid = document.querySelector('.products-grid');
    if (!productsGrid) return;

    const products = await fetchProducts();
    if (!products.length) return;

    const featured = products.filter(p => p.level === 'featured');
    if (!featured.length) return;

    // 静态 HTML 里已经存在的同名卡片不再重复渲染
    const staticTitles = new Set();
    productsGrid.querySelectorAll('.product-card-title').forEach(el => staticTitles.add(el.textContent.trim()));

    featured
      .filter(p => !staticTitles.has(String(p.name || '').trim()))
      .forEach((p, i) => {
        const detailUrl = 'product-detail.html?id=' + encodeURIComponent(p.id || '');
        const card = document.createElement('a');
        card.href = detailUrl;
        card.className = 'product-card reveal reveal-delay-' + (i % 3 + 1);
        const img = (p.images && p.images.length > 0) ? safeImgUrl(p.images[0]) : '';
        const desc = p.subtitle || (p.description ? String(p.description).substring(0, 80) : '');
        card.innerHTML = `
          <div class="product-card-image">
            ${img
              ? `<img src="${img}" alt="${esc(p.name)}" />`
              : `<div class="placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg><span>${esc(p.name)}</span></div>`
            }
            <div class="product-card-overlay"></div>
            <span class="product-card-badge">${esc(p.category || '')}</span>
          </div>
          <div class="product-card-body">
            <h3 class="product-card-title">${esc(p.name)}</h3>
            <p class="product-card-desc">${esc(desc)}</p>
            <div class="product-card-tags">
              ${(Array.isArray(p.tags) ? p.tags : []).slice(0, 4).map(t => `<span class="product-card-tag">${esc(t)}</span>`).join('')}
            </div>
          </div>
          <div class="product-card-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
          </div>
        `;
        productsGrid.appendChild(card);
        revealObserver.observe(card);
      });
  }

  loadDynamicProducts();

  /* ─────────── 14. 首屏板卡轮播 ───────────
   * 三块板卡的实拍图叠在一起，靠 is-active 切透明度。
   * 自动播放会在鼠标悬停 / 键盘聚焦 / 切到后台标签页时暂停，
   * 用户手动点过切换点之后就不再自动轮播，免得打断他看。 */
  (function initHeroSlides() {
    const root = document.querySelector('[data-hero]');
    if (!root) return;

    const slides = Array.from(root.querySelectorAll('.banner-slide'));
    const dots = Array.from(root.querySelectorAll('[data-hero-dot]'));
    const prevBtn = root.querySelector('[data-hero-prev]');
    const nextBtn = root.querySelector('[data-hero-next]');
    const tag = root.querySelector('[data-hero-tag]');
    if (slides.length < 2 || dots.length !== slides.length) return;

    const DELAY = 6500;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let index = 0;
    let timer = null;
    let paused = false;
    let locked = false;   // 用户手动切过，停止自动轮播

    function render(next) {
      index = (next + slides.length) % slides.length;
      slides.forEach((el, i) => el.classList.toggle('is-active', i === index));
      dots.forEach((el, i) => {
        const on = i === index;
        el.classList.toggle('is-active', on);
        if (on) el.setAttribute('aria-current', 'true');
        else el.removeAttribute('aria-current');
      });
      if (tag && dots[index].dataset.tag) tag.textContent = dots[index].dataset.tag;
    }

    function stop() {
      if (timer) { window.clearInterval(timer); timer = null; }
    }

    function start() {
      stop();
      if (reduceMotion || locked || paused || document.hidden) return;
      timer = window.setInterval(() => render(index + 1), DELAY);
    }

    dots.forEach((dot, i) => {
      dot.addEventListener('click', () => {
        locked = true;
        render(i);
        stop();
      });
      dot.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        e.preventDefault();
        const next = i + (e.key === 'ArrowRight' ? 1 : -1);
        locked = true;
        render(next);
        stop();
        dots[(next + dots.length) % dots.length].focus();
      });
    });

    // 左右箭头：手动翻一页，翻过之后同样停掉自动轮播
    function step(delta) {
      locked = true;
      render(index + delta);
      stop();
    }
    if (prevBtn) prevBtn.addEventListener('click', () => step(-1));
    if (nextBtn) nextBtn.addEventListener('click', () => step(1));

    // 焦点在箭头按钮上时，方向键也能切，不用来回挪鼠标
    root.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      if (!e.target.closest('.banner-arrows')) return;
      e.preventDefault();
      step(e.key === 'ArrowRight' ? 1 : -1);
    });

    const stage = root.querySelector('.banner-stage');
    if (stage) {
      stage.addEventListener('pointerenter', () => { paused = true; stop(); });
      stage.addEventListener('pointerleave', () => { paused = false; start(); });
    }
    root.addEventListener('focusin', () => { paused = true; stop(); });
    root.addEventListener('focusout', () => { paused = false; start(); });
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stop(); else start();
    });

    render(0);
    start();
  })();

  console.log('✨ 产品展示网站已加载');
});
