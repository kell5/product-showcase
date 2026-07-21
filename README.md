# LightMesh 灯联网展示站

这是「LightMesh 灯联网」的项目展示网站仓库，负责对外展示、项目入口、APK 下载和保留原有后台管理能力。

## 在线入口

- LightMesh 灯联网展示页：<https://www.lk-mcu.online/product-lightmesh.html>
- APK 下载：<https://www.lk-mcu.online/downloads/lightmesh-app-release.apk>
- GitHub 源码仓库：<https://github.com/kell5/esp32_ble_mesh>

## 页面组成

- `index.html`：首页，总览项目与入口
- `product-lightmesh.html`：LightMesh 灯联网单独展示页
- `product-lightmesh-doorbell.html`：可视化门铃独立详情页
- `product-yinghuo-zhilian.html`：旧入口兼容跳转页
- `admin.html`：后台管理页
- `product-*.html`：其他项目详情页
- `css/styles.css`：全站样式
- `js/main.js`：首页交互
- `js/admin.js`：后台管理逻辑
- `images/`：展示图目录

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

## 如何添加新项目

在首页 `全部项目总览` 区域复制一个 `mini-card` 块，再修改标题和链接即可。

## 部署说明

- 网站使用静态部署
- 后续如果更新 APK，只更新静态下载目录即可
- `downloads/` 只放公开下载资源；正式 APK 会随网站部署，临时文件不要放入仓库
