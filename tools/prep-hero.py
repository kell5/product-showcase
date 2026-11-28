#!/usr/bin/env python3
"""生成首页横幅左侧的主视觉：STM32G474 智能电机控制板。

素材是手机俯拍，板子下面铺着暖白桌面。横幅是深蓝底，直接放会像贴了张白纸，
所以要两件事：

1. **桌面换底**：暖白桌面（低饱和 + 高亮度）通过「阈值 → 四角连通填充 →
   羽化」得到一张软掩膜，只在板体外部生效，板内的白色丝印和 OLED 白字
   不会被误伤。桌面被换成深蓝，但保留原图的凹陷阴影，看起来仍是「放在
   深色台面上的板子」而不是硬抠图。
2. **整体压成蓝调**：抬蓝减红，把暖白桌面的黄调彻底去掉，板体的蓝阻焊层
   变成主色，绿色端子和红色线材降饱和，不与蓝色争。

输出 5:4（1250×1000），与横幅卡片的 aspect-ratio 完全一致，object-fit
cover 不裁任何一边。板体接近方形，竖一点的画布才装得满。
"""
import os

from PIL import Image, ImageChops, ImageDraw, ImageEnhance, ImageFilter

SRC = ('C:/Users/Administrator/.workbuddy/clipboard-images/'
       'clipboard-2026-11-28T01-59-11-029Z-f9a1589a.jpg')
OUT = 'images/hero'
BOX = (52, 372, 1398, 1920)      # 板体 + OLED + 旋钮完整，避开顶部显示器底座
CANVAS = (1250, 1000)             # 5:4，与横幅卡片比例一致，不被 cover 裁切
DEEP = (9, 18, 34)                # 深蓝底


def desk_mask(im):
    """返回桌面区域的软掩膜（255 = 桌面）。只在板体外部连通区生效。"""
    small = im.resize((im.width // 2, im.height // 2), Image.LANCZOS)
    r, g, b = small.split()
    mx = ImageChops.lighter(ImageChops.lighter(r, g), b)      # 明度上限
    mn = ImageChops.darker(ImageChops.darker(r, g), b)        # 明度下限
    # 暖白桌面：亮且不饱和。板体的蓝/绿阻焊饱和高，不会被选中
    bright = mx.point(lambda v: 255 if v > 102 else 0)
    flat = ImageChops.subtract(mx, mn).point(lambda d: 255 if d < 68 else 0)
    m = ImageChops.multiply(bright, flat)

    # 只保留与边框连通的部分：板内的白色丝印、金属件会被板体隔开，自动排除。
    # 种子沿四边多点采样——线材和 OLED 会把桌面切成几块，只从四角出发填不满
    seeds = []
    for x in range(0, m.width, 36):
        seeds += [(x, 0), (x, m.height - 1)]
    for y in range(0, m.height, 36):
        seeds += [(0, y), (m.width - 1, y)]
    for seed in seeds:
        if m.getpixel(seed) == 255:
            ImageDraw.floodfill(m, seed, 128)

    m = m.point(lambda p: 255 if p == 128 else 0)
    m = m.resize(im.size, Image.LANCZOS).filter(ImageFilter.GaussianBlur(3.5))
    return m


def blue_grade(im):
    """抬蓝减红，去掉桌面的黄调。"""
    r, g, b = im.split()
    r = r.point(lambda x: min(255, int(x * 0.88)))
    g = g.point(lambda x: min(255, int(x * 1.02)))
    b = b.point(lambda x: min(255, int(x * 1.12)))
    out = Image.merge('RGB', (r, g, b))
    out = ImageEnhance.Color(out).enhance(0.82)   # 绿端子/红线降饱和
    return out


def vignette(im, strength=0.42):
    w, h = im.size
    mask = Image.new('L', (w, h), 0)
    ImageDraw.Draw(mask).ellipse(
        [-w * 0.12, -h * 0.16, w * 1.12, h * 1.16], fill=255)
    mask = mask.filter(ImageFilter.GaussianBlur(w * 0.09))
    dark = ImageEnhance.Brightness(im).enhance(1 - strength)
    return Image.composite(im, dark, mask)


def main():
    im = Image.open(SRC).convert('RGB').crop(BOX)

    mask = desk_mask(im)
    im = blue_grade(im)

    # 桌面换成深蓝，保留 15% 原地纹理，让台面有落影而不是死平
    deep = Image.new('RGB', im.size, DEEP)
    im = Image.composite(deep, im, mask.point(lambda p: int(p * 0.88)))

    im = ImageEnhance.Brightness(im).enhance(1.06)
    im = ImageEnhance.Contrast(im).enhance(1.08)
    im = im.filter(ImageFilter.UnsharpMask(radius=1.1, percent=38, threshold=4))
    im = vignette(im)

    # 放进 3:2 画布：板图按高度缩放到 96%，两侧补深蓝
    cw, ch = CANVAS
    target_h = int(ch * 0.94)
    target_w = round(im.width * target_h / im.height)
    board = im.resize((target_w, target_h), Image.LANCZOS)
    board = vignette(board, 0.30)

    canvas = Image.new('RGB', CANVAS, DEEP)
    # 底：左上偏亮一点的对角渐变，别让大块纯色显平
    grad = Image.new('L', (1, ch), 0)
    for y in range(ch):
        grad.putpixel((0, y), int(26 - 16 * y / ch))
    canvas = Image.composite(
        Image.new('RGB', CANVAS, (16, 30, 54)), canvas,
        grad.resize(CANVAS, Image.BILINEAR))

    # 板体背后的蓝色辉光，把板子从背景里托起来
    glow = Image.new('L', CANVAS, 0)
    gd = ImageDraw.Draw(glow)
    gd.ellipse([cw * 0.26, ch * 0.02, cw * 0.74, ch * 1.04], fill=110)
    glow = glow.filter(ImageFilter.GaussianBlur(90))
    canvas = Image.composite(Image.new('RGB', CANVAS, (24, 62, 126)), canvas, glow)

    x = (cw - target_w) // 2
    y = (ch - target_h) // 2

    # 板下柔和投影，让它有落在台面上的重量而不是浮着
    shadow = Image.new('L', CANVAS, 0)
    ImageDraw.Draw(shadow).ellipse(
        [x - 40, y + target_h - 76, x + target_w + 40, y + target_h + 52],
        fill=170)
    shadow = shadow.filter(ImageFilter.GaussianBlur(46))
    dark = ImageEnhance.Brightness(canvas).enhance(0.20)
    canvas = Image.composite(dark, canvas, shadow)

    # 板图边缘羽化：它的底色比画布略亮，硬贴会露出矩形接缝
    feather = 38
    amask = Image.new('L', board.size, 0)
    ImageDraw.Draw(amask).rectangle(
        [feather, feather, board.width - feather, board.height - feather], fill=255)
    amask = amask.filter(ImageFilter.GaussianBlur(feather * 0.62))
    canvas.paste(board, (x, y), amask)

    os.makedirs(OUT, exist_ok=True)
    path = os.path.join(OUT, 'hero-card.jpg')
    canvas.save(path, 'JPEG', quality=88, optimize=True, progressive=True)
    print(f'{path}  {canvas.size}  {os.path.getsize(path)//1024}KB')
    canvas.save('../preview-2026-11-28/hero-card-preview.png')


if __name__ == '__main__':
    main()
