# 智能硬件工作室 · 展示站

嵌入式项目与毕设选题展示站，负责项目展示、选题库、留言板和后台管理。

## 在线入口

- LightMesh 灯联网展示页：<https://www.lk-mcu.online/product-lightmesh.html>
- APK 下载：<https://www.lk-mcu.online/downloads/lightmesh-app-release.apk>
- GitHub 源码仓库：<https://github.com/kell5/esp32_ble_mesh>

## 页面组成

- `index.html`：首页（Hero / 4 个精选作品 / 选题库入口 / 关于 / 留言）
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
- `api.php`：后台接口（产品 CRUD / 图片上传 / 登录鉴权 / 留言读写）
- `js/guestbook.js`、`css/guestbook.css`：留言板组件（自包含，页面只放容器）
- `tools/set-admin-password.php`：在服务器上设置后台密码（命令行执行）
- `deploy/nginx-security.conf`：服务器安全配置片段（必须手动贴到 nginx）

## 留言板

- 页面里只要有 `<div data-guestbook></div>`，再引入 `js/guestbook.js` 就会渲染出
  表单 + 留言列表，目前挂在 `index.html` 和 `thesis-topics.html`
- 接口：`list_messages`（公开，最多取 50 条）、`add_message`（公开）、
  `delete_message`（需登录）
- 防刷：昵称 20 字 / 内容 500 字上限；同 IP 20 秒一次、每小时 5 条；
  隐藏蜜罐字段 `website`（机器人填了就假装成功但不入库）；堆 3 个以上链接直接拒
- 数据存在 `data/messages.json`，最多保留 500 条；**不存储访客 IP**，
  限流只按 IP 哈希存一个短期计数
- 留言提交后立即公开，后台「留言管理」可删除不合适的内容

## 后台与安全

- 首次部署时 `.github/workflows/deploy.yml` 会写入一次初始密码（只写一次，
  由 `data/.pwd-init` 标记挡住重复写入）；**登录后第一件事就是在「系统设置」里改掉**，
  改完可以把部署脚本里那段初始化代码删掉。
- 也可以随时在服务器手动重置：
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
- 数据放在 `js/` 而不是 `data/`：`data/` 是运行时目录，会被接口改写，不适合放静态资源

## 如何添加新项目

两种方式：
1. 后台 `admin.html` → 添加产品 → 勾选「核心产品」，保存后自动出现在首页「精选作品」
2. 直接写死一个详情页，再在 `index.html` 的 `.products-grid` 里复制一张 `product-card`

## 部署说明

- 网站使用静态部署
- APK 不再进 Git：`downloads/*.apk` 已在 `.gitignore` 和 rsync 排除列表中，
  服务器上的 APK 不会被自动部署删掉，发新版本请直接传服务器或走 GitHub Release
- `downloads/` 只放公开下载资源；临时文件不要放入仓库
- 图片已统一压缩到 1600px 以内（原 14MB → 约 1.6MB），新增图片请先压缩再提交
