# -*- coding: utf-8 -*-
"""首屏轮播图后处理。

输入：ImageGen 生成的 1536x1024 渲染图（板卡按真实实拍参考生成）
输出：images/hero/slide-1~3.jpg

三张图按同一个构图规则出片：板卡高度占画面 90%，左边距约 4%，
右侧留出背景给文字区。这样切换时板卡的视觉体量是稳定的。

关键点：裁切框都在 y<=950 以内，右下角的生成水印（约 y=950~1015）
自然被裁掉，不需要修补。
"""
import os

from PIL import Image, ImageDraw, ImageEnhance

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)

# 渲染源图归档在 tools/hero-src/，仓库里可复现；
# 换板卡时把新渲染图按同样尺寸放进去，再改下面的 JOBS
SRC = os.path.join(HERE, 'hero-src')
OUT = os.path.join(ROOT, 'images', 'hero')
PREV = os.path.join(ROOT, '..', 'preview-2026-11-28')

# 源文件, 输出名, 裁切框（源图像素）, 绿色偏移（0=不动，1=推到青蓝）
#
# 裁切框按「三块板子的视觉面积一致」反推，不是随手框：
#   slide-1 板卡 790x820 → 画面上约 570x592
#   slide-2 板卡 850x525 → 约 740x457（宽板，靠宽度占位）
#   slide-3 板卡 800x695 → 约 624x542
# 裁切框都避开右下角的生成水印区域（x>1400 或 y>950）。
JOBS = [
    ('hero-src-motor.jpg', 'slide-1.jpg',
     (60, 0, 1400, 985), 0.0),
    ('hero-src-esp32.jpg', 'slide-2.jpg',
     (0, 0, 1536, 917), 0.55),
    ('hero-src-servo.jpg', 'slide-3.jpg',
     (110, 0, 1380, 986), 0.0),
]

OUT_H = 1300


def greens_to_blue(im, amount):
    """把绿色阻焊往青蓝偏，跟站点蓝色主调合。"""
    if amount <= 0:
        return im
    hsv = im.convert('HSV')
    h, s, v = hsv.split()
    px = h.load()
    w, hh = h.size
    for y in range(hh):
        for x in range(w):
            val = px[x, y]
            if 80 <= val <= 152:          # PIL 的 H 是 0..255，绿色大约在这段
                px[x, y] = int(val + (178 - val) * amount)
    return Image.merge('HSV', (h, s, v)).convert('RGB')


def grade(im):
    """统一影调：略提对比、暗部加蓝。"""
    im = ImageEnhance.Contrast(im).enhance(1.05)
    im = ImageEnhance.Color(im).enhance(1.05)
    r, g, b = im.split()
    r = r.point(lambda v: int(v * 0.97))
    g = g.point(lambda v: min(255, int(v * 0.99)))
    b = b.point(lambda v: min(255, int(v * 1.05 + 2)))
    return Image.merge('RGB', (r, g, b))


def fade_edges(im, bg=(6, 12, 24)):
    """把右缘和底部渐变到页面底色。

    横幅背景就是 #060C18，图片边缘先自己淡到同一个色，图片铺多宽都不会
    出现硬边，文字区也不会被板卡压到。
    """
    w, h = im.size
    overlay = Image.new('RGBA', im.size, (255, 255, 255, 0))
    d = ImageDraw.Draw(overlay)

    # 右缘：从 62% 开始渐隐，96% 处完全落到页面底色
    x0, x1 = int(w * 0.62), int(w * 0.96)
    steps = 96
    for i in range(steps):
        x = x0 + (x1 - x0) * i // steps
        t = i / (steps - 1)
        a = int(242 * (t ** 1.5))
        d.rectangle([x, 0, x0 + (x1 - x0) * (i + 1) // steps + 1, h],
                    fill=bg + (a,))
    d.rectangle([x1, 0, w, h], fill=bg + (242,))

    # 顶部：图片上沿淡淡化开，防止板卡顶部出现一条硬边
    for i in range(40):
        y = h * 0.05 * i // 40
        a = int(225 * (1 - i / 39) ** 1.4)
        d.rectangle([0, y, w, h * 0.05 * (i + 1) // 40 + 1], fill=bg + (a,))

    # 底部：让板卡像落在台面上，而不是被切断
    y0, y1 = int(h * 0.92), h
    for i in range(64):
        y = y0 + (y1 - y0) * i // 64
        a = int(190 * ((i / 63) ** 1.6))
        d.rectangle([0, y, w, y0 + (y1 - y0) * (i + 1) // 64 + 1],
                    fill=bg + (a,))

    out = im.convert('RGBA')
    out.alpha_composite(overlay)
    return out.convert('RGB')


def main():
    os.makedirs(OUT, exist_ok=True)

    for name, out_name, box, green_shift in JOBS:
        im = Image.open(os.path.join(SRC, name)).convert('RGB')
        im = im.crop(box)
        im = greens_to_blue(im, green_shift)
        im = grade(im)

        im = im.resize((round(im.width * OUT_H / im.height), OUT_H), Image.LANCZOS)
        im = fade_edges(im)

        dst = os.path.join(OUT, out_name)
        im.save(dst, 'JPEG', quality=88, optimize=True, progressive=True)
        print(f'{out_name:12} {im.size}  {os.path.getsize(dst) // 1024} KB')

        # 首页「交付过的板卡」三张方图：从同一张图上切左边 1:1，
        # 右缘的渐隐正好落在方图里，卡片上看起来是板卡沉进暗处
        side = im.height
        square = im.crop((0, 0, side, side)).resize((1000, 1000), Image.LANCZOS)
        card = os.path.join(ROOT, 'images', 'products',
                            'board-card-' + out_name.split('-')[-1])
        square.save(card, 'JPEG', quality=86, optimize=True, progressive=True)
        print(f'  ↳ {os.path.basename(card)} {square.size}')

        im.save(os.path.join(PREV, 'preview-' + out_name.replace('.jpg', '.png')))


if __name__ == '__main__':
    main()
