/**
 * 管理后台脚本
 * 功能: 登录验证、产品CRUD、图片管理、数据导入导出
 * 数据存储: 服务器端 api.php + JSON 文件
 */

(function () {
  'use strict';

  // ─────────── 常量 ───────────
  const API_BASE = 'api.php';
  let authToken = sessionStorage.getItem('shlab_token') || '';

  // ─────────── 工具函数 ───────────
  async function apiCall(action, data = null, method = 'POST') {
    const url = `${API_BASE}?action=${action}`;
    const options = {
      method,
      headers: {
        'Content-Type': 'application/json',
      }
    };
    if (authToken) {
      options.headers['Authorization'] = `Bearer ${authToken}`;
    }
    if (data && method === 'POST') {
      options.body = JSON.stringify(data);
    }
    const resp = await fetch(url, options);
    const json = await resp.json();
    if (resp.status === 401 && action !== 'login' && action !== 'verify') {
      showToast('登录已过期，请重新登录', 'error');
      logout();
      throw new Error('Unauthorized');
    }
    return { ok: resp.ok, status: resp.status, data: json };
  }

  async function uploadImage(file) {
    const formData = new FormData();
    formData.append('image', file);
    const resp = await fetch(`${API_BASE}?action=upload`, {
      method: 'POST',
      headers: authToken ? { 'Authorization': `Bearer ${authToken}` } : {},
      body: formData
    });
    const json = await resp.json();
    if (!resp.ok) throw new Error(json.message || '上传失败');
    return json.url;
  }

  function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `admin-toast admin-toast--${type}`;
    toast.innerHTML = `
      <span>${message}</span>
      <button class="admin-toast-close">&times;</button>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    const timer = setTimeout(() => removeToast(toast), 3000);
    toast.querySelector('.admin-toast-close').addEventListener('click', () => {
      clearTimeout(timer);
      removeToast(toast);
    });
  }

  function removeToast(toast) {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }

  // ─────────── 登录逻辑 ───────────
  const loginPanel = document.getElementById('loginPanel');
  const adminPanel = document.getElementById('adminPanel');
  const loginForm = document.getElementById('loginForm');
  const loginError = document.getElementById('loginError');

  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const pwd = document.getElementById('adminPassword').value;
    try {
      const res = await apiCall('login', { password: pwd });
      if (res.ok && res.data.success) {
        authToken = res.data.token;
        sessionStorage.setItem('shlab_token', authToken);
        loginPanel.style.display = 'none';
        adminPanel.style.display = 'flex';
        renderProductList();
      } else {
        loginError.textContent = res.data.message || '密码错误，请重试';
        loginError.style.display = 'block';
        document.getElementById('adminPassword').value = '';
      }
    } catch (err) {
      loginError.textContent = '连接服务器失败，请检查网络';
      loginError.style.display = 'block';
    }
  });

  // 检查是否已登录（验证 token）
  async function checkAuth() {
    if (!authToken) return;
    try {
      const res = await apiCall('verify', null, 'GET');
      if (res.data && res.data.valid) {
        loginPanel.style.display = 'none';
        adminPanel.style.display = 'flex';
        renderProductList();
      } else {
        sessionStorage.removeItem('shlab_token');
        authToken = '';
      }
    } catch {}
  }
  checkAuth();

  // 退出登录
  function logout() {
    // 通知服务端吊销当前 token（失败也不影响本地登出）
    if (authToken) {
      fetch(`${API_BASE}?action=logout`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${authToken}` }
      }).catch(() => {});
    }
    authToken = '';
    sessionStorage.removeItem('shlab_token');
    loginPanel.style.display = '';
    adminPanel.style.display = 'none';
    document.getElementById('adminPassword').value = '';
    loginError.style.display = 'none';
  }

  document.getElementById('logoutBtn').addEventListener('click', (e) => {
    e.preventDefault();
    logout();
  });

  // ─────────── Tab 切换 ───────────
  const navItems = document.querySelectorAll('.admin-nav-item[data-tab]');
  const tabs = document.querySelectorAll('.admin-tab');

  function switchTab(tabName) {
    navItems.forEach(item => item.classList.toggle('active', item.dataset.tab === tabName));
    tabs.forEach(tab => tab.classList.toggle('active', tab.id === `tab-${tabName}`));
    if (tabName === 'products') renderProductList();
    if (tabName === 'messages') renderMessageList();
  }

  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      switchTab(item.dataset.tab);
    });
  });

  document.getElementById('gotoAddBtn').addEventListener('click', () => {
    resetForm();
    switchTab('add');
  });

  document.getElementById('cancelEditBtn').addEventListener('click', () => {
    resetForm();
    switchTab('products');
  });

  // ─────────── 留言管理 ───────────
  function escHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatMsgTime(iso) {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    const p = n => (n < 10 ? '0' + n : n);
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
  }

  async function renderMessageList() {
    const box = document.getElementById('adminMessageList');
    if (!box) return;
    box.innerHTML = '<div class="admin-empty">正在加载…</div>';

    try {
      const res = await apiCall('list_messages', null, 'GET');
      const messages = (res.data && res.data.messages) || [];

      if (!messages.length) {
        box.innerHTML = '<div class="admin-empty">还没有留言</div>';
        return;
      }

      box.innerHTML = messages.map(m => `
        <div class="admin-message-item" data-id="${escHtml(m.id)}">
          <div class="admin-message-head">
            <strong>${escHtml(m.name)}</strong>
            <time>${escHtml(formatMsgTime(m.createdAt))}</time>
            <button class="admin-btn admin-btn-danger admin-msg-del" data-id="${escHtml(m.id)}">删除</button>
          </div>
          <p class="admin-message-text">${escHtml(m.content)}</p>
        </div>
      `).join('');

      box.querySelectorAll('.admin-msg-del').forEach(btn => {
        btn.addEventListener('click', async () => {
          if (!confirm('确定删除这条留言？删除后不可恢复。')) return;
          try {
            const del = await apiCall('delete_message', { id: btn.dataset.id });
            if (del.ok && del.data.success) {
              showToast('已删除');
              renderMessageList();
            } else {
              showToast((del.data && del.data.message) || '删除失败', 'error');
            }
          } catch {
            showToast('删除失败', 'error');
          }
        });
      });
    } catch {
      box.innerHTML = '<div class="admin-empty">加载失败，请重新登录后再试</div>';
    }
  }

  const reloadMessagesBtn = document.getElementById('reloadMessagesBtn');
  if (reloadMessagesBtn) {
    reloadMessagesBtn.addEventListener('click', renderMessageList);
  }

  // ─────────── 产品列表渲染 ───────────
  let cachedProducts = [];

  async function renderProductList() {
    const listEl = document.getElementById('productList');
    const statTotal = document.getElementById('statTotal');
    const statFeatured = document.getElementById('statFeatured');
    const statImages = document.getElementById('statImages');

    listEl.innerHTML = '<div class="admin-empty"><p>加载中...</p></div>';

    try {
      const res = await apiCall('list', null, 'GET');
      cachedProducts = res.data.products || [];
    } catch {
      listEl.innerHTML = '<div class="admin-empty"><p>加载失败，请刷新重试</p></div>';
      return;
    }

    const products = cachedProducts;
    statTotal.textContent = products.length;
    statFeatured.textContent = products.filter(p => p.level === 'featured').length;
    statImages.textContent = products.reduce((sum, p) => sum + (p.images ? p.images.length : 0), 0);

    if (products.length === 0) {
      listEl.innerHTML = `
        <div class="admin-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          <p>还没有产品，点击"添加产品"开始吧</p>
        </div>
      `;
      return;
    }

    listEl.innerHTML = products.map(p => `
      <div class="admin-product-item" data-id="${p.id}">
        <div class="admin-product-thumb">
          ${p.images && p.images.length > 0
            ? `<img src="${p.images[0]}" alt="${p.name}" />`
            : `<div class="admin-product-no-img"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="24" height="24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>`
          }
        </div>
        <div class="admin-product-info">
          <h3>${p.name}</h3>
          <p>${p.subtitle || (p.description ? p.description.substring(0, 60) : '')}</p>
          <div class="admin-product-meta">
            <span class="admin-badge admin-badge--${p.level === 'featured' ? 'primary' : 'default'}">${p.level === 'featured' ? '核心产品' : '普通项目'}</span>
            <span class="admin-badge">${p.category || '未分类'}</span>
            ${p.tags ? p.tags.slice(0, 3).map(t => `<span class="admin-badge admin-badge--tag">${t}</span>`).join('') : ''}
          </div>
        </div>
        <div class="admin-product-actions">
          <button class="admin-btn-icon" title="编辑" data-action="edit" data-id="${p.id}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="admin-btn-icon admin-btn-icon--danger" title="删除" data-action="delete" data-id="${p.id}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
        </div>
      </div>
    `).join('');

    // 绑定操作事件
    listEl.querySelectorAll('[data-action="edit"]').forEach(btn => {
      btn.addEventListener('click', () => editProduct(btn.dataset.id));
    });
    listEl.querySelectorAll('[data-action="delete"]').forEach(btn => {
      btn.addEventListener('click', () => deleteProduct(btn.dataset.id));
    });
  }

  // ─────────── 添加/编辑产品 ───────────
  let currentImages = [];

  const productForm = document.getElementById('productForm');
  const uploadArea = document.getElementById('uploadArea');
  const imageInput = document.getElementById('imageInput');
  const imagePreview = document.getElementById('imagePreview');

  // 图片上传（带裁剪功能）
  uploadArea.addEventListener('click', () => imageInput.click());
  uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
  });
  uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
  uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
  });
  imageInput.addEventListener('change', () => handleFiles(imageInput.files));

  // 裁剪相关
  const cropModal = document.getElementById('cropModal');
  const cropImage = document.getElementById('cropImage');
  const cropConfirm = document.getElementById('cropConfirm');
  const cropCancel = document.getElementById('cropCancel');
  const cropSkip = document.getElementById('cropSkip');
  let cropper = null;
  let pendingFiles = [];
  let currentOriginalFile = null;

  function openCropper(file) {
    currentOriginalFile = file;
    const url = URL.createObjectURL(file);
    cropImage.src = url;
    cropModal.style.display = 'flex';
    setTimeout(() => {
      if (cropper) { cropper.destroy(); cropper = null; }
      cropper = new Cropper(cropImage, {
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 0.9,
        responsive: true,
        restore: false,
        guides: true,
        center: true,
        highlight: false,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
      });
    }, 100);
  }

  function closeCropper() {
    cropModal.style.display = 'none';
    if (cropper) { cropper.destroy(); cropper = null; }
    if (cropImage.src.startsWith('blob:')) URL.revokeObjectURL(cropImage.src);
    cropImage.src = '';
    currentOriginalFile = null;
    // 处理下一个文件
    processNextFile();
  }

  // 比例按钮
  document.querySelectorAll('.crop-ratio-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.crop-ratio-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      if (!cropper) return;
      const ratio = btn.dataset.ratio;
      if (ratio === 'free') {
        cropper.setAspectRatio(NaN);
      } else {
        cropper.setAspectRatio(parseFloat(ratio));
      }
    });
  });

  // 确认裁剪
  cropConfirm.addEventListener('click', async () => {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas({
      maxWidth: 1920,
      maxHeight: 1920,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high',
    });
    canvas.toBlob(async (blob) => {
      try {
        showToast('正在上传裁剪后的图片...', 'warning');
        const croppedFile = new File([blob], currentOriginalFile.name, { type: 'image/jpeg' });
        const url = await uploadImage(croppedFile);
        currentImages.push(url);
        renderImagePreview();
        showToast('图片上传成功');
      } catch (err) {
        showToast('图片上传失败: ' + err.message, 'error');
      }
      closeCropper();
    }, 'image/jpeg', 0.9);
  });

  // 跳过裁剪（直接上传原图）
  cropSkip.addEventListener('click', async () => {
    if (!currentOriginalFile) { closeCropper(); return; }
    try {
      showToast('正在上传原图...', 'warning');
      const url = await uploadImage(currentOriginalFile);
      currentImages.push(url);
      renderImagePreview();
      showToast('图片上传成功');
    } catch (err) {
      showToast('图片上传失败: ' + err.message, 'error');
    }
    closeCropper();
  });

  // 取消裁剪
  cropCancel.addEventListener('click', () => {
    pendingFiles = [];
    closeCropper();
  });
  cropModal.querySelector('.crop-modal-backdrop').addEventListener('click', () => {
    pendingFiles = [];
    closeCropper();
  });

  function processNextFile() {
    if (pendingFiles.length === 0) return;
    const file = pendingFiles.shift();
    openCropper(file);
  }

  async function handleFiles(files) {
    const imageFiles = Array.from(files).filter(f => f.type.startsWith('image/'));
    if (imageFiles.length === 0) return;
    pendingFiles = imageFiles.slice(1);
    openCropper(imageFiles[0]);
  }

  function renderImagePreview() {
    imagePreview.innerHTML = currentImages.map((src, i) => `
      <div class="admin-img-thumb">
        <img src="${src}" alt="预览${i + 1}" />
        <button type="button" class="admin-img-remove" data-index="${i}">&times;</button>
      </div>
    `).join('');
    imagePreview.querySelectorAll('.admin-img-remove').forEach(btn => {
      btn.addEventListener('click', () => {
        currentImages.splice(parseInt(btn.dataset.index), 1);
        renderImagePreview();
      });
    });
  }

  // 表单提交
  productForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const editId = document.getElementById('editId').value;
    const product = {
      name: document.getElementById('prodName').value.trim(),
      subtitle: document.getElementById('prodSubtitle').value.trim(),
      category: document.getElementById('prodCategory').value,
      tags: document.getElementById('prodTags').value.split(',').map(t => t.trim()).filter(Boolean),
      description: document.getElementById('prodDesc').value.trim(),
      specs: document.getElementById('prodSpecs').value.trim(),
      level: document.querySelector('input[name="prodLevel"]:checked').value,
      images: currentImages,
    };

    try {
      if (editId) {
        product.id = editId;
        await apiCall('update', product);
        showToast('产品已更新');
      } else {
        await apiCall('add', product);
        showToast('产品添加成功');
      }
      resetForm();
      switchTab('products');
    } catch (err) {
      showToast('保存失败: ' + err.message, 'error');
    }
  });

  function editProduct(id) {
    const p = cachedProducts.find(item => item.id === id);
    if (!p) return;

    document.getElementById('editId').value = p.id;
    document.getElementById('formTitle').textContent = '编辑产品';
    document.getElementById('prodName').value = p.name || '';
    document.getElementById('prodSubtitle').value = p.subtitle || '';
    document.getElementById('prodCategory').value = p.category || '其他';
    document.getElementById('prodTags').value = (p.tags || []).join(', ');
    document.getElementById('prodDesc').value = p.description || '';
    document.getElementById('prodSpecs').value = p.specs || '';
    const levelRadio = document.querySelector(`input[name="prodLevel"][value="${p.level || 'normal'}"]`);
    if (levelRadio) levelRadio.checked = true;

    currentImages = p.images ? [...p.images] : [];
    renderImagePreview();
    switchTab('add');
  }

  async function deleteProduct(id) {
    if (!confirm('确定要删除这个产品吗？此操作不可恢复。')) return;
    try {
      await apiCall('delete', { id });
      showToast('产品已删除', 'warning');
      renderProductList();
    } catch (err) {
      showToast('删除失败', 'error');
    }
  }

  function resetForm() {
    productForm.reset();
    document.getElementById('editId').value = '';
    document.getElementById('formTitle').textContent = '添加新产品';
    currentImages = [];
    renderImagePreview();
  }

  // ─────────── 修改密码 ───────────
  document.getElementById('passwordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const oldPwd = document.getElementById('oldPassword').value;
    const newPwd = document.getElementById('newPassword').value;
    const confirmPwd = document.getElementById('confirmPassword').value;

    if (newPwd !== confirmPwd) {
      showToast('两次输入的密码不一致', 'error');
      return;
    }

    try {
      const res = await apiCall('change_password', { oldPassword: oldPwd, newPassword: newPwd });
      if (res.ok && res.data.success) {
        showToast('密码已更新，请重新登录');
        document.getElementById('passwordForm').reset();
        setTimeout(logout, 1200);
      } else {
        showToast(res.data.message || '修改失败', 'error');
      }
    } catch (err) {
      showToast('修改失败', 'error');
    }
  });

  // ─────────── 数据导入导出 ───────────
  document.getElementById('exportDataBtn').addEventListener('click', async () => {
    try {
      const res = await apiCall('export', null, 'GET');
      const data = JSON.stringify(res.data.products || [], null, 2);
      const blob = new Blob([data], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `shlab-products-${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      URL.revokeObjectURL(url);
      showToast('数据导出成功');
    } catch {
      showToast('导出失败', 'error');
    }
  });

  document.getElementById('importDataBtn').addEventListener('click', () => {
    document.getElementById('importFileInput').click();
  });

  document.getElementById('importFileInput').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async (ev) => {
      try {
        const data = JSON.parse(ev.target.result);
        if (!Array.isArray(data)) throw new Error('格式错误');
        await apiCall('import', { products: data });
        showToast(`成功导入 ${data.length} 个产品`);
        renderProductList();
      } catch {
        showToast('导入失败：文件格式错误', 'error');
      }
    };
    reader.readAsText(file);
    e.target.value = '';
  });

  // 清空数据
  document.getElementById('clearDataBtn').addEventListener('click', async () => {
    if (!confirm('确定要清空所有产品数据吗？此操作不可恢复！')) return;
    if (!confirm('再次确认：所有产品数据将被永久删除。')) return;
    try {
      await apiCall('clear');
      showToast('所有数据已清空', 'warning');
      renderProductList();
    } catch {
      showToast('操作失败', 'error');
    }
  });

  // ─────────── Hero 图片管理 ───────────
  let heroImages = ['', '', ''];
  let activeHeroSlot = 0;
  const heroFileInput = document.getElementById('heroFileInput');

  // 加载当前 Hero 图片
  async function loadHeroImages() {
    try {
      const res = await apiCall('get_hero', null, 'GET');
      const imgs = res.data.images || [];
      heroImages = [imgs[0] || '', imgs[1] || '', imgs[2] || ''];
      renderHeroPreviews();
    } catch {}
  }

  function renderHeroPreviews() {
    for (let i = 0; i < 3; i++) {
      const el = document.getElementById('heroPreview' + i);
      if (!el) continue;
      if (heroImages[i]) {
        el.innerHTML = `<img src="${heroImages[i]}" alt="展示图${i+1}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;" />`;
      } else {
        const labels = ['图片 1（主图）', '图片 2', '图片 3'];
        el.innerHTML = `<span class="hero-img-label">${labels[i]}</span>`;
      }
    }
  }

  // 点击更换按钮
  document.querySelectorAll('.hero-upload-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activeHeroSlot = parseInt(btn.dataset.slot);
      heroFileInput.click();
    });
  });

  // 选择文件后上传（带裁剪）
  heroFileInput.addEventListener('change', () => {
    const file = heroFileInput.files[0];
    if (!file || !file.type.startsWith('image/')) return;
    heroFileInput.value = '';

    // 使用裁剪器
    currentOriginalFile = file;
    const url = URL.createObjectURL(file);
    cropImage.src = url;
    cropModal.style.display = 'flex';

    // 临时替换确认按钮行为
    const origConfirmHandler = cropConfirm.onclick;
    const origSkipHandler = cropSkip.onclick;

    function restoreHandlers() {
      cropConfirm.onclick = origConfirmHandler;
      cropSkip.onclick = origSkipHandler;
    }

    cropConfirm.onclick = async () => {
      if (!cropper) return;
      const canvas = cropper.getCroppedCanvas({ maxWidth: 1920, maxHeight: 1920, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
      canvas.toBlob(async (blob) => {
        try {
          showToast('正在上传...', 'warning');
          const croppedFile = new File([blob], file.name, { type: 'image/jpeg' });
          const imgUrl = await uploadImage(croppedFile);
          heroImages[activeHeroSlot] = imgUrl;
          renderHeroPreviews();
          showToast('图片已更新');
        } catch (err) {
          showToast('上传失败: ' + err.message, 'error');
        }
        restoreHandlers();
        closeCropper();
      }, 'image/jpeg', 0.9);
    };

    cropSkip.onclick = async () => {
      try {
        showToast('正在上传...', 'warning');
        const imgUrl = await uploadImage(file);
        heroImages[activeHeroSlot] = imgUrl;
        renderHeroPreviews();
        showToast('图片已更新');
      } catch (err) {
        showToast('上传失败: ' + err.message, 'error');
      }
      restoreHandlers();
      closeCropper();
    };

    setTimeout(() => {
      if (cropper) { cropper.destroy(); cropper = null; }
      cropper = new Cropper(cropImage, {
        viewMode: 1, dragMode: 'move', autoCropArea: 0.9, responsive: true,
        restore: false, guides: true, center: true, highlight: false,
        cropBoxMovable: true, cropBoxResizable: true, toggleDragModeOnDblclick: false,
      });
    }, 100);
  });

  // 保存 Hero 图片
  document.getElementById('saveHeroBtn').addEventListener('click', async () => {
    try {
      await apiCall('set_hero', { images: heroImages });
      showToast('展示图片已保存');
    } catch {
      showToast('保存失败', 'error');
    }
  });

  // ─────────── AI 助手配置 ───────────
  let aiLoaded = false;

  // 后台页面可能被浏览器缓存成旧版（缺 aiBaseUrl / aiFb* 这些新元素）。
  // 一律走这三个函数取，缺元素时返回空值而不是抛 TypeError —— 否则整个
  // 表单提交会在赋值那一步静默中断，用户以为保存了、实际什么都没发出去。
  function aiEl(id) {
    return document.getElementById(id);
  }
  function aiGet(id) {
    const el = aiEl(id);
    return el ? String(el.value || '').trim() : '';
  }
  function aiSet(id, val) {
    const el = aiEl(id);
    if (el) el.value = val;
  }
  function aiOn(id) {
    const el = aiEl(id);
    return !!(el && el.checked);
  }
  function aiText(id, val) {
    const el = aiEl(id);
    if (el) el.textContent = val;
  }

  function renderAiUsage(d) {
    const u = d.usage || {};
    const stateEl = document.getElementById('aiStatState');
    stateEl.textContent = d.enabled ? '已开启' : '已关闭';
    stateEl.className = d.enabled ? 'is-on' : 'is-off';
    document.getElementById('aiStatToday').textContent =
      (u.today || 0) + ' / ' + (u.limit || 0);
    document.getElementById('aiStatTokens').textContent =
      Number(u.tokens || 0).toLocaleString('zh-CN');
    document.getElementById('aiStatHits').textContent = (u.hits || 0) + ' 次';
  }

  async function loadAiConfig(force) {
    if (aiLoaded && !force) return;
    try {
      const res = await apiCall('get_ai_config', null, 'GET');
      if (!res.ok || !res.data.success) {
        showToast('读取 AI 配置失败', 'error');
        return;
      }
      const d = res.data;
      aiLoaded = true;

      document.getElementById('aiEnabled').checked = !!d.enabled;
      aiSet('aiModel', d.model || '');
      aiSet('aiBaseUrl', d.baseUrl || '');
      aiSet('aiPerIp', d.perIp);
      aiSet('aiPerTotal', d.perTotal);

      const keyEl = aiEl('aiApiKey');
      if (keyEl) {
        keyEl.value = '';
        keyEl.placeholder = d.hasKey ? '已配置（' + d.keyMask + '），留空不修改' : '粘贴 API Key';
      }

      const hint = aiEl('aiKeyHint');
      if (hint) {
        if (d.hasKey) {
          hint.textContent = '当前 Key：' + d.keyMask + '（只显示首尾各 4 位）';
        } else if (d.keyFromEnv) {
          hint.textContent = '当前使用服务器环境变量里的 Key';
        } else if (String(d.baseUrl || '').indexOf('http://') === 0) {
          hint.textContent = '走本机自建服务时可以留空';
        } else {
          hint.textContent = '尚未配置 Key，助手不会工作';
        }
      }

      // 备用通道
      const fbEnabledEl = aiEl('aiFbEnabled');
      if (fbEnabledEl) fbEnabledEl.checked = !!d.fallbackEnabled;
      aiSet('aiFbUrl', d.fallbackUrl || '');
      aiSet('aiFbModel', d.fallbackModel || '');
      const fbKeyEl = aiEl('aiFbKey');
      if (fbKeyEl) {
        fbKeyEl.value = '';
        fbKeyEl.placeholder = d.fallbackHasKey ? '已配置（' + d.fallbackKeyMask + '），留空不修改' : '留空表示不校验';
      }
      aiText('aiFbKeyHint', d.fallbackHasKey
        ? '当前 Key：' + d.fallbackKeyMask
        : '未设置，服务端开启 --api-key 时再填');

      const list = aiEl('aiModelList');
      if (list) {
        list.innerHTML = (d.models || [])
          .map((m) => '<option value="' + m + '"></option>')
          .join('');
      }

      renderAiUsage(d);
    } catch {
      showToast('读取 AI 配置失败', 'error');
    }
  }

  document.getElementById('aiForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      apiKey: aiGet('aiApiKey'),
      baseUrl: aiGet('aiBaseUrl'),
      model: aiGet('aiModel'),
      perIp: Number(aiGet('aiPerIp')) || 30,
      perTotal: Number(aiGet('aiPerTotal')) || 800,
      enabled: aiOn('aiEnabled'),
      // 主表单保存时带上备用通道的现有值，避免被覆盖
      fallbackEnabled: aiOn('aiFbEnabled'),
      fallbackUrl: aiGet('aiFbUrl'),
      fallbackModel: aiGet('aiFbModel'),
      fallbackKey: aiGet('aiFbKey'),
    };
    if (payload.enabled && !payload.apiKey) {
      // 后端会自动沿用已保存的 Key，这里不拦，只提示一下
      const hint = aiEl('aiKeyHint');
      if (hint && hint.textContent.indexOf('尚未配置') !== -1) {
        showToast('先填 API Key 再开启', 'error');
        return;
      }
    }
    try {
      const res = await apiCall('set_ai_config', payload);
      if (res.ok && res.data.success) {
        showToast('AI 配置已保存');
        aiSet('aiApiKey', '');
        aiLoaded = false;
        loadAiConfig(true);
      } else {
        showToast(res.data.message || '保存失败', 'error');
      }
    } catch {
      showToast('保存失败', 'error');
    }
  });

  document.getElementById('aiTestBtn').addEventListener('click', async () => {
    const btn = document.getElementById('aiTestBtn');
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = '测试中…';
    try {
      const res = await apiCall('ai_test', {
        apiKey: aiGet('aiApiKey'),
        baseUrl: aiGet('aiBaseUrl'),
        model: aiGet('aiModel'),
      });
      if (res.ok && res.data.success) {
        showToast('连接成功 · ' + res.data.model + ' · ' + res.data.ms + 'ms');
      } else {
        showToast((res.data && res.data.message) || '连接失败', 'error');
      }
    } catch {
      showToast('连接失败', 'error');
    }
    btn.disabled = false;
    btn.textContent = label;
  });

  // 一键填入本地自建模型的默认参数
  const localPresetBtn = document.getElementById('aiLocalPreset');
  if (localPresetBtn) {
    localPresetBtn.addEventListener('click', () => {
      aiSet('aiBaseUrl', 'http://127.0.0.1:8080/v1');
      aiSet('aiModel', 'qwen3-1.7b');
      aiSet('aiApiKey', '');
      aiText('aiKeyHint', '走本机自建服务，Key 可留空（服务端开了 --api-key 才需要填）');
      showToast('已填入本地模型默认值，改完记得保存');
    });
  }

  // 保存备用通道
  const fbForm = document.getElementById('aiFbForm');
  if (fbForm) {
    fbForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        enabled: aiOn('aiEnabled'),
        model: aiGet('aiModel'),
        baseUrl: aiGet('aiBaseUrl'),
        apiKey: aiGet('aiApiKey'),
        perIp: Number(aiGet('aiPerIp')) || 30,
        perTotal: Number(aiGet('aiPerTotal')) || 800,
        fallbackEnabled: aiOn('aiFbEnabled'),
        fallbackUrl: aiGet('aiFbUrl'),
        fallbackModel: aiGet('aiFbModel'),
        fallbackKey: aiGet('aiFbKey'),
      };
      try {
        const res = await apiCall('set_ai_config', payload);
        if (res.ok && res.data.success) {
          showToast('备用通道已保存');
          aiSet('aiFbKey', '');
          aiSet('aiApiKey', '');
          aiLoaded = false;
          loadAiConfig(true);
        } else {
          showToast(res.data.message || '保存失败', 'error');
        }
      } catch {
        showToast('保存失败', 'error');
      }
    });
  }

  // 测试备用通道
  const fbTestBtn = document.getElementById('aiFbTestBtn');
  if (fbTestBtn) {
    fbTestBtn.addEventListener('click', async () => {
      const label = fbTestBtn.textContent;
      fbTestBtn.disabled = true;
      fbTestBtn.textContent = '测试中…';
      try {
        const res = await apiCall('ai_test', {
          useFallback: true,
          fallbackUrl: aiGet('aiFbUrl'),
          fallbackModel: aiGet('aiFbModel'),
          fallbackKey: aiGet('aiFbKey'),
        });
        if (res.ok && res.data.success) {
          showToast('备用通道连通 · ' + res.data.model + ' · ' + res.data.ms + 'ms');
        } else {
          showToast((res.data && res.data.message) || '备用通道连不上', 'error');
        }
      } catch {
        showToast('备用通道连不上', 'error');
      }
      fbTestBtn.disabled = false;
      fbTestBtn.textContent = label;
    });
  }

  // 进入设置页时加载 Hero 图片
  const origSwitchTab = switchTab;
  switchTab = function(tabName) {
    origSwitchTab(tabName);
    if (tabName === 'settings') loadHeroImages();
    if (tabName === 'ai') loadAiConfig();
  };

})();
