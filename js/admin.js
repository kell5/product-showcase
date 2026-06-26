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
    if (resp.status === 401 && action !== 'login') {
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
      const resp = await fetch(`${API_BASE}?action=verify&token=${authToken}`);
      const json = await resp.json();
      if (json.valid) {
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
        showToast('密码已更新');
        document.getElementById('passwordForm').reset();
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

  // 进入设置页时加载 Hero 图片
  const origSwitchTab = switchTab;
  switchTab = function(tabName) {
    origSwitchTab(tabName);
    if (tabName === 'settings') loadHeroImages();
  };

})();
