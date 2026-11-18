# LightMesh 灯联网展示站

这是「LightMesh 灯联网」的项目展示网站仓库，负责对外展示、项目入口、APK 下载和保留原有后台管理能力。

## 在线入口

- LightMesh 灯联网展示页：<https://www.lk-mcu.online/product-lightmesh.html>
- APK 下载：<https://www.lk-mcu.online/downloads/lightmesh-app-release.apk>
- GitHub 源码仓库：<https://github.com/kell5/esp32_ble_mesh>

## 页面组成

- `index.html`：首页，总览项目与入口
- `product-lightmesh.html`：LightMesh 灯联网单独展示页
- `product-lightmesh-doorbell.html`：可视化门铃详情页
- `product-yinghuo-zhilian.html`：旧入口兼容跳转页
- `thesis-topics.html`：毕设题目参考专栏（699 个选题，支持方向筛选与关键词搜索）
- `admin.html`：后台管理页
- `product-*.html`：其他项目详情页
- `css/styles.css`：全站样式
- `js/main.js`：首页交互
- `js/admin.js`：后台管理逻辑
- `images/`：展示图目录
- `api.php`：后台接口（产品 CRUD / 图片上传 / 登录鉴权）
- `tools/set-admin-password.php`：在服务器上设置后台密码（命令行执行）
- `deploy/nginx-security.conf`：服务器安全配置片段（必须手动贴到 nginx）

## 后台与安全

- **没有默认密码**。首次部署后必须在服务器执行：
  ```bash
  php tools/set-admin-password.php '你的新密码'
  ```
- 密码用 bcrypt 加盐存储；登录后签发 24 小时有效的 token，多端可同时登录，
  改密码会让所有旧会话失效；同一 IP 连续 5 次密码错误封 15 分钟。
- 图片上传按文件真实内容判断类型（`getimagesize`），不信任客户端 MIME 和文件名。
- `data/` 目录存放运行时数据，nginx 侧必须用 `deploy/nginx-security.conf` 拒绝直接访问。
- 详见 `DEPLOY.md` 的「第零步」。

## 设计风格

- 主色调：冷白、冰蓝、星紫
- 目标气质：简约、科技、企业级
- 页面要求：保留原有项目展示和后台管理结构，不丢风格，不丢功能

## LightMesh 灯联网页面说明

- 这是 ESP32 BLE Mesh 灯控与多设备 OTA 项目的独立展示页
- 重点展示：设备架构、App、可视化门铃详情页、网关、OTA、开源下载
- APK 放在网站静态目录，GitHub 仓库保留源码和文档

## 图片命名建议

把图片放入 `images/`，并尽量按下面命名：

### 首页 Hero

- `hero-main.jpg`
- `hero-sub1.jpg`
- `hero-sub2.jpg`

### 首页卡片缩略图

- `gesture-arm-thumb.jpg`
- `sound-core-thumb.jpg`
- `vision-tracker-thumb.jpg`
- `yinghuo-light.webp`

### 详情页图片

- `gesture-arm-main.jpg`
- `gesture-arm-glove.jpg`
- `gesture-arm-board.jpg`
- `sound-core-main.jpg`
- `sound-core-lcd.jpg`
- `sound-core-board.jpg`
- `vision-tracker-main.jpg`
- `vision-tracker-camera.jpg`
- `vision-tracker-tracking.jpg`
- `yinghuo-zhilian-main.jpg`
- `yinghuo-zhilian-app.jpg`
- `yinghuo-zhilian-ota.jpg`

## 如何替换图片

1. 把新图片放进 `images/`
2. 在对应 HTML 里找到旧文件名
3. 替换 `<img src="...">`
4. 删除旧的占位块

## 毕设题目参考专栏

- 页面：`thesis-topics.html`，样式 `css/thesis-topics.css`，交互 `js/thesis-topics.js`
- 数据：`js/thesis-topics.data.js`（挂载在 `window.THESIS_TOPICS`，由脚本生成，不要手改）
- 更新数据：把新的「毕业设计题目分类整理」Markdown 放到本地，执行

  ```bash
  python3 tools/build-thesis-topics.py /path/to/毕业设计题目分类整理_宣传选题版.md
  ```

  Markdown 结构要求：`## <序号>. <方向名>` 小节 + 四列表格（序号 / 题目 / 技术栈 / 功能亮点）
- 数据放在 `js/` 而不是 `data/`：服务器上 `data/` 目录被 `Deny from all` 限制，静态 JS 才能被浏览器直接加载

## 如何添加新项目

在首页 `全部项目总览` 区域复制一个 `mini-card` 块，再修改标题和链接即可。

## 部署说明

- 网站使用静态部署
- APK 不再进 Git：`downloads/*.apk` 已在 `.gitignore` 和 rsync 排除列表中，
  服务器上的 APK 不会被自动部署删掉，发新版本请直接传服务器或走 GitHub Release
- `downloads/` 只放公开下载资源；临时文件不要放入仓库
- 图片已统一压缩到 1600px 以内（原 14MB → 约 1.6MB），新增图片请先压缩再提交
