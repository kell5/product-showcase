#!/usr/bin/env python3
"""生成首页横幅左侧的板卡画面（卡片式，不再全幅铺底）。

选 ESP32 那块板的暗环境实拍：背后是显示器上的 PCB 布线图，本身背景就黑，
板载红/绿 LED 有光感，绿板正好对上品牌主色 —— 放进深色横幅里不用抠图也不违和。

只做三件事：
1. 裁掉顶部空档和底部那块显示 "SD MOUNT FAIL" 的 OLED（产品图不能带报错）
2. 降一点饱和，压掉刺眼的红蓝，让画面服从品牌绿
3. 底部做一层压暗，掩掉板底与桌面之间那条亮边
"""
import os
from PIL import Image, ImageDraw, ImageEnhance, ImageFilter

SRC = 'C:/Users/Administrator/.workbuddy/clipboard-images/clipboard-2026-11-28T01-59-11-031Z-cb65b7d6.jpg'
OUT = 'images/hero'
BOX = (0, 762, 1440, 1542)     # 板体完整，去掉顶部空档与底部报错屏
WIDTH = 1400


def bottom_fade(im, ratio=0.16, strength=0.9):
    """底部压暗到接近纯黑，掩掉板底那条亮边。"""
    w, h = im.size
    grad = Image.new('L', (1, h), 0)
    start = int(h * (1 - ratio))
    for y in range(h):
        if y < start:
            grad.putpixel((0, y), 255)
        else:
            t = (y - start) / max(1, h - start)
            grad.putpixel((0, y), int(255 * (1 - t ** 1.4)))
    mask = grad.resize((w, h), Image.BILINEAR)
    dark = ImageEnhance.Brightness(im).enhance(1 - strength)
    return Image.composite(im, dark, mask)


def main():
    im = Image.open(SRC).convert('RGB').crop(BOX)
    im = im.resize((WIDTH, round(im.height * WIDTH / im.width)), Image.LANCZOS)

    im = ImageEnhance.Brightness(im).enhance(1.05)
    im = ImageEnhance.Contrast(im).enhance(1.07)
    im = ImageEnhance.Color(im).enhance(0.86)
    im = im.filter(ImageFilter.UnsharpMask(radius=1.2, percent=45, threshold=4))
    im = bottom_fade(im)

    os.makedirs(OUT, exist_ok=True)
    path = os.path.join(OUT, 'hero-card.jpg')
    im.save(path, 'JPEG', quality=87, optimize=True, progressive=True)
    print(f'{path}  {im.size}  {os.path.getsize(path)//1024}KB')
    im.save('../preview-2026-11-28/hero-card-preview.png')


if __name__ == '__main__':
    main()
