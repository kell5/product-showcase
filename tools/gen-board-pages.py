#!/usr/bin/env python3
"""按统一模板生成三块控制板的产品详情页。

内容全部来自实拍图与用户提供的信息，不编造未确认的参数；
不确定的项（如具体引脚数、电池容量）一律写功能描述而非数字。
"""
import os

VER = '20261201-1'

# 通用图标
IC = {
    'gear': '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.4a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.2-2.9H1a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 3 7a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 8 3h.1A1.7 1.7 0 0 0 9 1.1V1a2 2 0 1 1 4 0v.1A1.7 1.7 0 0 0 15 3a1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.7 1.7 0 0 0 21 8v.1a1.7 1.7 0 0 0 1.9.9H23a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    'wave': '<path d="M2 12h4l3-8 4 16 3-8h6"/>',
    'screen': '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
    'shield': '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
    'chip': '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/>',
    'save': '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
    'battery': '<rect x="2" y="7" width="16" height="10" rx="2"/><path d="M22 11v2"/><path d="M5 11v2M9 11v2M13 11v2"/>',
    'wifi': '<path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19.5" r="1"/>',
    'clock': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'card': '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'key': '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="8" cy="12" r="1.6"/><circle cx="14" cy="12" r="1.6"/>',
    'bolt': '<path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/>',
    'usb': '<rect x="2" y="8" width="20" height="8" rx="2"/><path d="M6 12h2M11 12h2M16 12h2"/>',
    'layers': '<path d="M12 2l9 5-9 5-9-5 9-5z"/><path d="M3 12l9 5 9-5"/><path d="M3 17l9 5 9-5"/>',
}

NAV = '''  <nav class="navbar">
    <div class="container">
      <a href="index.html" class="nav-brand">
        <span class="nav-brand-icon"></span>
        励磁实验室
      </a>
      <div class="nav-links">
        <a href="index.html">首页</a>
        <a href="index.html#services">服务内容</a>
        <a href="index.html#deliverables">交付样例</a>
        <a href="index.html#work">项目案例</a>
        <a href="thesis-topics.html">学生指导</a>
        <a href="index.html#contact" class="nav-contact-btn">聊聊需求</a>
      </div>
      <button class="nav-toggle" aria-label="菜单">
        <span></span><span></span><span></span>
      </button>
    </div>
  </nav>'''

FOOTER = '''  <footer class="site-footer">
    <div class="container">
      <div class="footer-top">
        <div>
          <div class="footer-brand">
            <span class="nav-brand-icon"></span>
            励磁实验室
          </div>
          <p class="footer-desc">嵌入式与物联网项目定制开发：方案设计、程序开发、硬件调试。以下板卡都是按需求交付的样例，原理图与固件随交付移交。</p>
        </div>
        <div class="footer-links-group">
          <h4>交付样例</h4>
          <a href="product-motor-ctrl.html">智能电机控制板</a>
          <a href="product-esp32-board.html">多协议联网开发板</a>
          <a href="product-servo-ctrl.html">舵机定时控制板</a>
        </div>
        <div class="footer-links-group">
          <h4>快速导航</h4>
          <a href="index.html">首页</a>
          <a href="index.html#services">服务内容</a>
          <a href="index.html#work">项目案例</a>
          <a href="thesis-topics.html">学生指导</a>
        </div>
      </div>
      <div class="footer-bottom">
        <span>&copy; 2026 励磁实验室. All rights reserved.</span>
        <span>Designed with passion for embedded systems.</span>
        <span><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none;">豫ICP备2026025181号-1</a></span>
      </div>
    </div>
  </footer>

  <script src="js/main.js?v={VER}"></script>
  <script src="js/ai-assistant.js?v={VER}" defer></script>'''


def feature_block(icon, title, desc):
    return f'''        <div class="feature-block reveal">
          <div class="feature-block-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{IC[icon]}</svg>
          </div>
          <h3>{title}</h3>
          <p>{desc}</p>
        </div>'''


def render(page):
    tags = '\n'.join(f'        <span class="page-hero-tag">{t}</span>' for t in page['tags'])
    feats = '\n'.join(feature_block(*f) for f in page['features'])
    specs = '\n'.join(f'        <tr><td>{k}</td><td>{v}</td></tr>' for k, v in page['specs'])

    # 画廊：单张大图，或额外挂原理图
    if page.get('schematic'):
        gallery = f'''      <div class="product-gallery-wrap reveal">
        <figure class="product-shot">
          <img src="{page['shot']}?v={VER}" alt="{page['shot_alt']}" />
        </figure>
        <figure class="product-shot product-shot--schematic">
          <img src="{page['schematic']}?v={VER}" alt="{page['schematic_alt']}" />
          <figcaption>{page['schematic_caption']}</figcaption>
        </figure>
      </div>'''
    else:
        gallery = f'''      <div class="product-gallery-wrap reveal">
        <figure class="product-shot">
          <img src="{page['shot']}?v={VER}" alt="{page['shot_alt']}" />
        </figure>
      </div>'''

    others = [p for p in PAGES if p['slug'] != page['slug']]
    cta_btns = '\n'.join(
        f'      <a href="{o["slug"]}" class="btn {"btn-primary" if i == 0 else "btn-outline"}">{o["name"]}</a>'
        for i, o in enumerate(others)
    )

    return f'''<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="/favicon.ico?v={VER}" sizes="any" />
  <link rel="icon" href="images/brand/favicon.svg" type="image/svg+xml" />
  <link rel="icon" href="images/brand/favicon-32.png" sizes="32x32" type="image/png" />
  <link rel="apple-touch-icon" href="images/brand/apple-touch-icon.png" />
  <meta name="theme-color" content="#FAF8F2" />
  <title>{page['name']} · {page['model']} | 励磁实验室</title>
  <meta name="description" content="{page['desc']}" />
  <link rel="canonical" href="https://www.lk-mcu.online/{page['slug']}" />
  <link rel="stylesheet" href="css/styles.css?v={VER}" />
  <link rel="stylesheet" href="css/ai-assistant.css?v={VER}" />
</head>
<body>

  <div class="scroll-progress"></div>
  <div class="cursor-glow"></div>

{NAV}

  <section class="page-hero">
    <div class="hero-bg">
      <div class="hero-glow hero-glow--1"></div>
      <div class="hero-glow hero-glow--2"></div>
      <div class="hero-grid-lines"></div>
    </div>
    <div class="container">
      <div class="page-hero-breadcrumb">
        <a href="index.html">首页</a>
        <span class="sep">/</span>
        <a href="index.html#deliverables">交付样例</a>
        <span class="sep">/</span>
        <span>{page['name']}</span>
      </div>
      <h1 class="page-hero-title">
        <span class="gradient-text">{page['model']}</span><br/>{page['name']}
      </h1>
      <p class="page-hero-subtitle">{page['subtitle']}</p>
      <div class="page-hero-tags">
{tags}
      </div>
    </div>
  </section>

  <section class="product-gallery">
    <div class="container">
{gallery}
    </div>
  </section>

  <section class="product-features">
    <div class="container">
      <div class="reveal" style="text-align:center;">
        <span class="section-label">Capabilities</span>
        <h2 class="section-title">这块板<span class="gradient-text">能做什么</span></h2>
      </div>
      <div class="features-grid">
{feats}
      </div>
    </div>
  </section>

  <section class="product-specs">
    <div class="container">
      <div class="reveal" style="text-align:center;">
        <span class="section-label">Specs</span>
        <h2 class="section-title">技术<span class="gradient-text">参数</span></h2>
      </div>
      <table class="product-specs-table reveal">
{specs}
      </table>
    </div>
  </section>

  <section class="product-cta">
    <div class="container reveal">
      <h2 class="section-title">看<span class="gradient-text">另外两块</span></h2>
      <p class="section-desc">需要改接口、换芯片或做整机，也可以直接聊。</p>
{cta_btns}
    </div>
  </section>

{FOOTER}
</body>
</html>
'''


PAGES = [
    {
        'slug': 'product-motor-ctrl.html',
        'name': '智能电机控制板',
        'model': 'MotoDrive',
        'desc': '基于 STM32G474 的双向电机控制板，支持正反转、编码器调速、固定输出、惯性减速与定时反转，配备中文 OLED 菜单与 500V/48V/5V 三路电压监测。',
        'subtitle': '一块板覆盖五种运行模式：正反转、编码器调速、固定输出、惯性减速与定时反转。'
                    '中文 OLED 菜单实时显示三路电压，双路自动泄放与急停保护在手，参数掉电不丢。',
        'tags': ['STM32G474', '双 H 桥', '编码器调速', '中文 OLED', '500V 监测'],
        'shot': 'images/products/motor-ctrl-1.jpg',
        'shot_alt': 'STM32G474 智能电机控制板实物，OLED 显示运行状态与 26.2V 设定值',
        'features': [
            ('gear', '五种运行模式',
             '正转、反转、固定输出、惯性减速、定时反转，旋钮一键切换，不用重新烧固件。'),
            ('wave', '编码器无级调速',
             '板载旋钮编码器调节转速，PWM 载频 1–5 kHz 可调，低速大扭矩场景也能压住噪声。'),
            ('screen', '中文 OLED 菜单',
             '128×64 屏直接显示模式、状态与故障码，同时监测 500V 母线、48V 与 5V 三路电压。'),
            ('chip', '三种 H 桥驱动兼容',
             '同一套固件适配双 PWM、双 IO 与 PWM + 方向三种驱动方式，换驱动板不用改代码。'),
            ('shield', '双路自动泄放与急停',
             '母线电容断电后自动泄放，急停按钮直接切断输出，调试时不会带着高压上手。'),
            ('save', '参数掉电保存',
             '设定值写入片内 Flash，断电重启后仍是上次的参数，现场恢复不用重新调。'),
        ],
        'specs': [
            ('主控', 'STM32G474'),
            ('驱动方式', '双 PWM / 双 IO / PWM + 方向（三种兼容）'),
            ('PWM 载频', '1–5 kHz 可调'),
            ('运行模式', '正转 / 反转 / 固定输出 / 惯性减速 / 定时反转'),
            ('电压监测', '500V 母线 / 48V / 5V 三路'),
            ('人机交互', '旋钮编码器 + 中文 OLED 菜单 + 按键'),
            ('保护', '急停保护、双路自动泄放'),
            ('参数保存', '掉电保存（片内 Flash）'),
        ],
    },
    {
        'slug': 'product-esp32-board.html',
        'name': '多协议联网开发板',
        'model': 'ESP32',
        'desc': '基于 ESP32 的多协议联网开发板，支持 Wi-Fi 与 BLE 双模通信，板载 TF 卡槽与多路独立按键，适合联网控制、数据采集与离线运行原型。',
        'subtitle': 'Wi-Fi 与 BLE 双模通信，板载 TF 卡槽做本地存储，多路按键与 IO 全部引出 — '
                    '联网控制、数据采集、离线缓存的活，一块板就能撑起来。',
        'tags': ['ESP32', 'Wi-Fi + BLE', 'TF 卡', 'MQTT', '多协议'],
        'shot': 'images/products/esp32-board-1.jpg',
        'shot_alt': 'ESP32 多协议联网开发板实物，板载天线与 TF 卡槽',
        'features': [
            ('wifi', 'Wi-Fi + BLE 双模',
             '2.4G Wi-Fi 与蓝牙低功耗并发，既能连云也能就近配网，手机直接连上调试。'),
            ('card', 'TF 卡本地存储',
             '板载 TF 卡槽，日志、配置与采集数据落盘，断网期间照常记录，恢复后补传。'),
            ('layers', '多协议支持',
             'MQTT、HTTP、WebSocket 等常用协议在 SDK 内直接可用，接自建服务或公有云都行。'),
            ('key', '多路独立按键',
             '板载多组按键，配网、复位、模式切换不用外接，演示和现场调试都省事。'),
            ('chip', 'IO 全引出',
             '排针把可用 IO 全部引出，接传感器、继电器、屏幕直接插杜邦线。'),
            ('usb', 'Type-C 供电与下载',
             '一根 Type-C 线完成供电、烧录与串口日志，不用另接下载器。'),
        ],
        'specs': [
            ('主控', 'ESP32（板载 PCB 天线）'),
            ('无线', 'Wi-Fi 2.4 GHz + BLE'),
            ('存储', 'TF 卡槽'),
            ('协议', 'MQTT / HTTP / WebSocket 等'),
            ('人机交互', '多路独立按键'),
            ('扩展', 'IO 排针全引出'),
            ('供电与烧录', 'Type-C 单线供电 + 下载 + 串口日志'),
        ],
    },
    {
        'slug': 'product-servo-ctrl.html',
        'name': '舵机定时控制板',
        'model': 'ServoTimer',
        'desc': '基于 CH32V003 的低功耗舵机定时控制板，板载 RTC 与锂电池充放电管理，按预设时间自动驱动舵机动作，适合长期值守与免布线场景。',
        'subtitle': '按设定时间自动驱动舵机，板载 RTC 走时与锂电池充放电管理 — '
                    '不插电也能长期值守，喂食器、定时开合、展示装置这类活正好合适。',
        'tags': ['CH32V003', 'RISC-V', 'RTC 定时', '锂电管理', '低功耗'],
        'shot': 'images/products/servo-ctrl-1.jpg',
        'shot_alt': 'CH32 舵机定时控制板实物，板载电解电容与舵机接口',
        'schematic': 'images/products/servo-ctrl-schematic.jpg',
        'schematic_alt': 'CH32 舵机定时控制板原理图，含 RTC、锂电充电与舵机电源开关',
        'schematic_caption': '整板原理图随板提供（嘉立创 EDA 绘制），含 RTC 走时、锂电池充电管理与舵机电源开关电路，可自行改接口与外壳。',
        'features': [
            ('clock', 'RTC 定时唤醒',
             '板载独立 RTC 芯片负责走时，主控平时休眠，到点才唤醒驱动舵机，待机电流压得很低。'),
            ('battery', '锂电池充放电管理',
             '板载充电管理与升压电路，单节锂电供电，插 USB 即充，不用额外配电源板。'),
            ('bolt', '舵机电源独立开关',
             '舵机由 MOS 管单独通断，动作结束彻底断电，避免舵机持续堵转吃掉电量。'),
            ('chip', 'CH32V003 低成本核心',
             'RISC-V 内核，资源够用、单价低，量产时 BOM 成本可控。'),
            ('layers', '原理图完整开放',
             '整板原理图随板交付，接口、供电、定时逻辑都能照着改，做二次开发不用反推。'),
            ('key', 'SWD 调试口',
             '板载 SWD 排针，接上调试器就能单步跟代码，改逻辑不用反复拆焊。'),
        ],
        'specs': [
            ('主控', 'CH32V003（RISC-V）'),
            ('定时', '板载 RTC 独立走时'),
            ('供电', '单节锂电池 + 板载充电管理'),
            ('输出', '舵机 PWM 控制'),
            ('功耗管理', '空闲休眠 + 舵机电源独立通断'),
            ('调试', 'SWD 排针'),
            ('设计文件', '整板原理图随板提供（嘉立创 EDA）'),
        ],
    },
]

if __name__ == '__main__':
    for p in PAGES:
        html = render(p)
        with open(p['slug'], 'w', encoding='utf-8') as f:
            f.write(html)
        print(f"{p['slug']}  {len(html)} bytes")
