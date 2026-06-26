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

  /* ─────────── 11. 动态加载管理后台添加的产品 ─────────── */
  async function loadDynamicProducts() {
    let products = [];
    try {
      const resp = await fetch('api.php?action=list');
      const json = await resp.json();
      products = json.products || [];
    } catch {
      return;
    }
    if (!products.length) return;

    // 渲染所有核心产品到 products-grid
    const featuredProducts = products.filter(p => p.level === 'featured');
    const productsGrid = document.querySelector('.products-grid');
    if (productsGrid && featuredProducts.length) {
      featuredProducts.forEach((p, i) => {
        const detailUrl = 'product-detail.html?id=' + p.id;
        const card = document.createElement('a');
        card.href = detailUrl;
        card.className = 'product-card reveal reveal-delay-' + (i % 3 + 1);
        card.innerHTML = `
          <div class="product-card-image">
            ${p.images && p.images.length > 0
              ? `<img src="${p.images[0]}" alt="${p.name}" />`
              : `<div class="placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg><span>${p.name}</span></div>`
            }
            <div class="product-card-overlay"></div>
            <span class="product-card-badge">${p.category || ''}</span>
          </div>
          <div class="product-card-body">
            <h3 class="product-card-title">${p.name}</h3>
            <p class="product-card-desc">${p.subtitle || (p.description ? p.description.substring(0, 80) : '')}</p>
            <div class="product-card-tags">
              ${(p.tags || []).slice(0, 4).map(t => `<span class="product-card-tag">${t}</span>`).join('')}
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

    // 渲染所有动态产品到全部项目区域（跳过HTML中已有的）
    const scrollGrid = document.querySelector('.projects-scroll-grid');
    if (scrollGrid) {
      const existingNames = new Set();
      scrollGrid.querySelectorAll('.mini-card-title').forEach(el => existingNames.add(el.textContent.trim()));
      products.filter(p => !existingNames.has(p.name) && p.level !== 'featured').forEach(p => {
        const miniCard = document.createElement('div');
        miniCard.className = 'mini-card reveal';
        miniCard.innerHTML = `
          <div class="mini-card-icon">
            ${p.images && p.images.length > 0
              ? `<img src="${p.images[0]}" alt="${p.name}" style="width:100%;height:100%;object-fit:cover;border-radius:6px;" />`
              : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/></svg>`
            }
          </div>
          <div class="mini-card-title">${p.name}</div>
        `;
        scrollGrid.appendChild(miniCard);
        revealObserver.observe(miniCard);
      });
    }
  }

  loadDynamicProducts();

  /* ─────────── 12. 让所有产品卡片可点击查看详情 ─────────── */
  async function makeCardsClickable() {
    let products = [];
    try {
      const resp = await fetch('api.php?action=list');
      const json = await resp.json();
      products = json.products || [];
    } catch { return; }
    if (!products.length) return;

    // 建立名称到产品的映射
    const nameMap = {};
    products.forEach(p => {
      nameMap[p.name] = p;
    });

    // 为所有 mini-card 添加点击
    document.querySelectorAll('.mini-card').forEach(card => {
      const titleEl = card.querySelector('.mini-card-title');
      if (!titleEl) return;
      const name = titleEl.textContent.trim();
      const product = nameMap[name];
      if (!product) return;

      card.style.cursor = 'pointer';
      card.addEventListener('click', () => {
        window.location.href = 'product-detail.html?id=' + product.id;
      });
    });
  }

  makeCardsClickable();

  /* ─────────── 13. Hero 卡片点击切换 + 动态加载 ─────────── */
  const heroCardStack = document.getElementById('heroCardStack');
  if (heroCardStack) {
    const heroCards = heroCardStack.querySelectorAll('.hero-float-card');
    const posClasses = ['hero-float-card--1', 'hero-float-card--2', 'hero-float-card--3'];

    heroCards.forEach(card => {
      card.addEventListener('click', () => {
        const clickedPos = posClasses.findIndex(cls => card.classList.contains(cls));
        if (clickedPos === 0) return; // 已经是主图
        // 交换点击卡片和主图的位置类
        const mainCard = heroCardStack.querySelector('.hero-float-card--1');
        if (!mainCard) return;
        mainCard.classList.remove('hero-float-card--1');
        mainCard.classList.add(posClasses[clickedPos]);
        card.classList.remove(posClasses[clickedPos]);
        card.classList.add('hero-float-card--1');
      });
    });

    // 从API加载 Hero 图片
    fetch('api.php?action=get_hero')
      .then(r => r.json())
      .then(data => {
        const images = data.images || [];
        if (images.length === 0) return;
        heroCards.forEach((card, i) => {
          if (images[i]) {
            const img = card.querySelector('img');
            if (img) img.src = images[i];
          }
        });
      })
      .catch(() => {});
  }

  console.log('✨ 产品展示网站已加载');
});
