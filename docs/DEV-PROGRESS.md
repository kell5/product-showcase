# 开发进度交接文档 · 励磁实验室（LICI LAB）

> 最后更新：2026-11-28
> 用途：新会话第一件事读这份文件，30 秒接上进度，不用再翻历史对话。
> 本文档放在 `docs/`，CI 的 rsync 已 `--exclude='docs/'`，**不会上传到服务器**。

---

## 1. 一句话现状

**定位已改为「需求交付」而非「卖产品」**：嵌入式 / 物联网项目定制开发（方案设计 · 程序开发 · 硬件调试），
帮客户把想法做到能跑的样机；板卡降级为「交付样例」，不再说「自研 / 在售」。
首页首屏换成深蓝横幅，左侧放 STM32G474 电机控制板实拍（参考了同行那张板卡 banner 的版式，配色是本站的科技蓝）。
另一条业务线是学生指导（毕设 / 课设 + 699 条选题库）。

当前分支 `main` 干净，最新提交见 `git log`。

---

## 1.5 定位与信息架构（2026-11-28 定稿）

| 区块 | id | 说什么 |
|---|---|---|
| 深色横幅 | `#hero` | 嵌入式 / 物联网 项目定制开发；三个胶囊 = 方案设计 · 程序开发 · 硬件调试；口号「从想法到样机 · 高效对接 · 踏实落地」 |
| 四条交付线 | `#services` | 需求梳理与方案设计 / 原理图与 PCB / 程序开发 / 样机联调与移交，每张卡带「交付物」清单 |
| 交付样例 | `#deliverables` | 三块按需求做出来的板子，明确写「不是货架商品」（原 `#boards`，已改名） |
| 项目案例 | `#work` | 4 个方向的实际交付 |
| 学生指导 | `#students` | 699 条选题库 + 一对一指导（原 `#thesis-entry`） |
| 合作方式 | `#about` | 四步流程：聊需求 → 方案与报价 → 分段开发 → 验收与移交 |
| 留言/需求登记 | `#guestbook` | 加了 `.gb-intro` 引导语 |

**措辞红线**：不写「自研」「在售」「自有产品线」「套件」「购买」。板卡统一叫「交付样例 / 按需求定制的板子」。

---

## 2. 线上与部署

| 项 | 值 |
|---|---|
| 域名 | <https://www.lk-mcu.online/> |
| 服务器 | 阿里云 ECS `114.55.208.72`，Ubuntu，宝塔面板 |
| 站点根目录 | `/www/wwwroot/114.55.208.72/` |
| 仓库 | <https://github.com/kell5/product-showcase>（`origin`） |
| 本地工程 | `E:\workbuddylog\2026-11-18-10-57-34\repo-product-showcase` |
| 部署方式 | `git push` → GitHub Actions → rsync 到服务器（`.github/workflows/deploy.yml`） |

**部署链路要点**

- CI 先跑 `php -l` 语法检查，语法错直接拦住，不会把 500 推上线
- rsync 带 `--delete`，但同时排除了 `data/`、`uploads/`、`downloads/*.apk`、`docs/`、`tools/`、`deploy/`、`.github/`
- CI 会 `chown -R www:www data uploads`，否则 PHP-FPM 写不进文件（"Failed to open stream"）
- 后台密码：首次部署由 CI 写一次 sha256 初始密码，登录后已被用户改掉，**密码不写进本仓库**
- 忘密码就在服务器执行：`php tools/set-admin-password.php '新密码'`

**push 失败时**：代理 502 是常态，先 `git config http.sslBackend openssl`，再重试 3–5 次即可。

---

## 3. 仓库地图

```
repo-product-showcase/
├─ index.html                    首页（唯一需要重点维护的页面）
├─ product-motor-ctrl.html       ★板卡 1：STM32G474 电机控制（脚本生成）
├─ product-esp32-board.html      ★板卡 2：ESP32 多协议联网（脚本生成）
├─ product-servo-ctrl.html       ★板卡 3：CH32V003 舵机定时（脚本生成，含原理图）
├─ product-lightmesh.html        项目案例：LightMesh 灯联网（老页面，内容最厚）
├─ product-lightmesh-doorbell.html  案例：可视化门铃
├─ product-gesture-arm.html      案例：体感机械臂
├─ product-sound-core.html       案例：嵌入式音频终端
├─ product-vision-tracker.html   案例：视觉追踪臂
├─ product-yinghuo-zhilian.html  旧入口 302 跳转页（noindex）
├─ thesis-topics.html            699 条毕设选题库（流量入口，别动数据）
├─ admin.html                    后台（产品 CRUD / 图片上传 / 留言 / AI 配置）
├─ api.php                       全部后端接口（约 1800 行，单文件）
├─ css/  styles.css(3783) admin.css ai-assistant.css guestbook.css thesis-topics.css
├─ js/   main.js admin.js ai-assistant.js guestbook.js thesis-topics.js(+data)
├─ images/
│   ├─ hero/hero-card.jpg        ★首页横幅左侧板卡画面（prep-hero.py 生成）
│   ├─ products/                 板卡实拍（motor-ctrl-1 / esp32-board-1 /
│   │                              servo-ctrl-1 / servo-ctrl-schematic / hero-board-1~3）
│   ├─ brand/                    logo：mark.svg / favicon.svg / icon-192 / apple-touch-icon
│   └─ *.jpg|webp                老项目图 + avatar.jpg（无人工作室场景，非真人）
├─ favicon.ico                   根目录必须有，Bing/神马/RSS 只认这个
├─ bca3d855...txt                IndexNow 密钥文件，别删
├─ tools/                        生成脚本（不进部署）
│   ├─ gen-board-pages.py        ★三块板卡详情页生成器（数据驱动，改内容改这里再重跑）
│   ├─ prep-product-photos.py    ★实拍图裁切/调色（参数表 JOBS）
│   ├─ prep-hero.py              ★首页横幅图：桌面换深蓝、板体羽化、边缘压暗
│   ├─ make-logo.py              ★生成 logo svg/png/ico（含手写 ICO 容器）
│   ├─ make-favicon.py           按矢量坐标重绘 16/32/48/64 并打包 ICO
│   ├─ process-gen-images.py     AI 图去水印/调色
│   ├─ build-thesis-topics.py    Markdown → thesis-topics.data.js
│   └─ set-admin-password.php    服务器上重置后台密码
├─ deploy/nginx-security.conf    安全片段（手动贴进宝塔）
├─ docs/                         设计说明 / AI 助手文档 / 本文件（不上线）
└─ DEPLOY.md README.md           部署与使用说明
```

---

## 4. 页面清单与完成度

| 页面 | 状态 | 说明 |
|---|---|---|
| 首页 `index.html` | ✅ 定稿 | Hero → 三块自研板卡 `#boards` → 4 个项目案例 `#work` → 选题库入口 → 关于 `#about` → 留言板 → 页脚 |
| 三块板卡详情页 | ✅ 定稿 | 结构与参数由 `tools/gen-board-pages.py` 统一生成，含实拍 + 规格表 + 原理图 |
| LightMesh 灯联网 | ✅ 老页面保留 | 历史内容最厚，仍是搜索主入口之一 |
| 其余 4 个案例页 | ✅ 老页面保留 | 文案仍是旧口径的地方，后续可逐步改成团队口吻 |
| 毕设选题页 | ✅ 不动 | 699 条数据在 `js/thesis-topics.data.js`，脚本生成，禁手改 |
| 后台 `admin.html` | ✅ 可用 | 产品 CRUD、图片上传、留言管理、AI 助手配置、密码修改 |

**导航统一为**：首页 / 在售板卡 / 项目案例 / 毕设题目 / 联系我们。

---

## 5. 设计系统（改样式先看这里）

品牌标识：深蓝 `#0D1A2E` 圆角方底 + 白色 PWM 脉冲折线
`<polyline points="13,71 31,71 31,29 55,29 55,71 73,71 73,49 88,49">`
（两个上升沿，一宽一窄脉冲 —— 对应电机 PWM，16px 下也不糊）

核心 CSS 变量（`css/styles.css` 顶部）：

```css
--c-primary: #2B62D9;        /* 主蓝，按钮与强调 */
--c-accent:  #1289C9;        /* 青蓝，小字号也用这个（对比度够） */
--c-ink:     #0D1A2E;        /* 结构深色（logo 底色同值） */
--c-bg:      #F6F8FC;        /* 冷白底，配蓝色系 */
--c-text:    #14201A;
--c-text-muted: #5F6E63;
--font-sans: "Inter", -apple-system, "PingFang SC", "Microsoft YaHei", ...
```

**风格定位**：冷白 + 科技蓝 + 青色点缀，实拍为主、没必要的插画不上。
深色首屏用 `#080F1E`，浅色区用 `#F6F8FC`，两段落差明显。

> 11-28 晚间改版：配色从「森林绿 + 金」整体换成蓝，因为主推的电机板是蓝色阻焊，
> 绿底配蓝板互相打架。改色时先把 `#2F7447` 一类全站映射替换，
> **注意还有一批写成 `rgba(47,116,71, ...)` 无空格的变体**，肉眼 grep 会漏，
> 要用正则 `rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)` 兜一遍。
> logo 与 favicon 的颜色写死在 `tools/make-logo.py`、`tools/make-favicon.py` 里，
> 改完 CSS 必须同步改这两个脚本再重跑，否则 SVG 会被生成脚本写回旧色。
字体栈不引 Google Fonts（国内会阻塞渲染）。

---

## 6. 已经踩过的坑（**这一节最值钱**）

### 缓存

- nginx 对静态图 `Cache-Control: max-age=2592000`（30 天），**新图必须带版本号**，否则用户看到的是旧图
- 全站静态资源统一 `?v=20261128-1`，下次改图/改样式记得整体升版本号（`grep -rl 'v=20261128-1' *.html css/*.css | xargs sed -i 's/20261128-1/20261205-1/g'`）
- `scroll-behavior: smooth` 已删除（和锚点跳转体验冲突）

### 搜索图标

- Bing / 神马 / RSS **只请求根目录 `/favicon.ico`**，不解析 `<link rel="icon">`。所以根目录必须有实体文件，光写 link 没用
- ICO 多尺寸必须**每帧独立渲染**再手工塞容器；PIL 的 `save(sizes=...)` 是从单张缩，16px 必糊
- 16px 用简化版（去掉三条横杠），32px 以上用完整标记

### 图片处理

- **自动白平衡不可用**：四角采样会取到板子和手（板卡照四角均值 168，背景实际 240），改用固定暖调参数 `warm(im, k)` 抬红减蓝
- 手机截图里社交软件头像、半透明点赞按钮压在板子上，修不掉 → 只能收窄裁切框避开（x<700）
- OLED 屏显示 `SD MOUNT FAIL` 报错的产品图不能用 → 重裁聚焦板体
- AI 生成图右下角有水印（对号 / 小方块），去法：取**上方同宽条带**纵向拉伸覆盖右下角 + 2–3px 接缝羽化。注意别取左侧深色区域，会把暗带带进画面
- 原理图白底校正到 `#FAF8F2` 并放大 1.6×，嵌页面才没有色块边界

### 布局

- 首屏 Hero 的 inline `style="display:flex"` 会压过媒体查询，导致移动端横向溢出 → 一律移进 CSS 类
- `.reveal` 元素靠 IntersectionObserver 加 `is-visible`，**无头截图会全白**，必须先注入 `.reveal{opacity:1!important}` 并关掉 animation
- Edge headless 的 `--window-size` 有最小宽度（实测 390 会被当成 504 布局再裁图），**窄屏验证要按 504 截**，否则误判横向溢出
- 首页横幅是深色，未滚动时导航要反白：给 `<body>` 加 `.has-dark-hero`，Logo 换 `mark-reverse.svg`。滚过 60px 后 `.navbar.scrolled` 会自动切回浅色

### 无头截图（Edge）

```bash
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" \
  --headless=new --disable-gpu --window-size=1440,1000 \
  --virtual-time-budget=4000 --screenshot=out.png "file:///E:/.../index.html"
```
截图存 `preview-YYYY-MM-DD/` 供自查，不进仓库。

### SEO

- `sitemap.xml` 目前 10 条，三块板卡页 lastmod 2026-11-28
- IndexNow 已接：POST `https://api.indexnow.org/indexnow`，返回 200/202 即已接受
- Bing 的图标与标题快照有 3–14 天延迟，急的话去 bing.com/webmasters 手动「请求编制索引」
- 每个页面都要有 canonical、og:image、JSON-LD，新加页面时照抄现有页的 head

---

## 7. 里程碑

| 日期 | 事项 |
|---|---|
| 11-18 | 站点搭建：静态展示 + PHP 接口 + 后台 + 留言板 + AI 站内助手；上阿里云 ECS，GitHub Actions 自动化部署跑通 |
| 11-24 | 切 LK Studio 暖色亮色主题；团队化改造（机器人/AI/创客定位）；图片加版本号修缓存；根目录 favicon.ico 修 Bing 地球图标；IndexNow 接入 |
| 11-28 | **改名励磁实验室 LICI LAB**；新 logo + 全套 favicon；三块实拍板卡上线；团队照改无人工作室场景；品牌名 48 处统一替换 |
| 11-28 | **配色转蓝 + 首屏换电机板**：全站绿金映射为科技蓝（含 rgba 无空格变体）；logo/favicon 底改 `#0D1A2E`；横幅图换 STM32G474 实拍，桌面转深蓝台面、板体羽化；移动端首屏恢复实物图 |
| 11-28 | **定位改为需求交付**：全站去掉「自研 / 在售」，首页重排为 深色横幅 → 四条交付线 → 交付样例 → 项目案例 → 学生指导 → 合作流程；`#boards` 改名 `#deliverables`；新增 `tools/prep-hero.py` |

---

## 8. 待办（按优先级）

### 用户侧动作（不占开发时间）

1. 三张实拍光线条件差很大（冷白桌面 / 暗环境 / 暖白），**建议日后统一白背景 + 侧光重拍**，是页面质感最大短板
2. Bing / 百度站长后台提交 sitemap，手动请求编制索引
3. 清理服务器历史 APK，确认后台密码已改

### 可开发项

4. 老案例页（gesture-arm / sound-core / vision-tracker / lightmesh）文案仍是旧「工作室」口吻，逐步改成团队口径
5. README 与 `api.php` 里 `aiSiteProjects()` 的页面描述要跟着新增页同步（上次已补到 8 个页面）
6. 板卡页可加「立即咨询 / 索取原理图」CTA，把流量导向留言板（目前只有文字说明，转化路径弱）
7. 三块板卡可加参数对比表（首页 / 一个 `boards-compare` 区块），面向选型用户
8. 图片若有条件重拍，`tools/prep-product-photos.py` 的 `JOBS` 参数表直接复用，只改文件名
9. 三块交付样例的实拍色调不统一（白桌 / 暗场 / 特写），同屏并排时差异明显，重拍能解决一大半
10. 详情页 CTA 目前只跳到其他板卡，缺「这类需求也能做，聊聊」的转化入口

---

## 9. 命令速查

```bash
# 进工程
cd /e/workbuddylog/2026-11-18-10-57-34/repo-product-showcase

# 改板卡页内容（改脚本里的数据，再重跑）
python tools/gen-board-pages.py

# 处理新实拍图（改 JOBS 里的文件名与裁切参数）
python tools/prep-product-photos.py

# 重新生成首页横幅图（换板卡或换裁切框时）
python tools/prep-hero.py

# 重新生成 logo / favicon
python tools/make-logo.py
python tools/make-favicon.py

# 本地起个静态预览
python -m http.server 8080

# 部署
git add -A && git commit -m "feat: ..." && git push origin main
# push 502 时：
git config http.sslBackend openssl
```

线上核验：

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://www.lk-mcu.online/
curl -s -o /dev/null -w "%{http_code}\n" https://www.lk-mcu.online/product-motor-ctrl.html
curl -sI https://www.lk-mcu.online/favicon.ico | grep -i content-type
```

---

## 10. 命名决策记录

已逐个查重排除：墨斗（东莞/广州/江苏同名 + 埃夫特 IDE）、枢机（深圳枢机科技、合肥枢机机器人）、稳态（台湾穩態光電）、阶跃（阶跃星辰 StepFun）、脉宽（通用技术词，显著性弱）。

**定名：励磁 / LICI LAB** —— 取电机励磁绕组之意，「给想法通电」，和电机控制板产品线直接咬合，无同名硬件品牌。
备选未用：电枢 ARMATURE、焊点 SOLDER POINT、铸衡 ZHUHENG。

---

## 11. 下次开场怎么问

直接说其中一句，能省掉全部背景介绍：

- 「读 `docs/DEV-PROGRESS.md`，接着做待办第 N 项」
- 「把老案例页文案改成需求交付口径」
- 「首页横幅标题再大一点 / 换一张板卡做主视觉」
- 「首页加板卡参数对比表」
- 「板卡页加咨询 CTA，接到留言板」
