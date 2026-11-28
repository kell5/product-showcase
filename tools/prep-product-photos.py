#!/usr/bin/env python3
"""把手机实拍素材裁成统一方图，做基础色彩校正后输出网站用图。

三张素材条件差异很大，不套统一参数：
- 图1 白桌面冷光，需要按四角背景做白平衡拉回暖白
- 图2 暗环境 + 彩色 PCB 背景，只压暗角、降背景饱和，不做白平衡（背景不是纯色）
- 图3 是手机截图，右侧有社交软件的头像与半透明按钮压在板子上，只能收窄裁切框避开
"""
import os
from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageStat

SRC = 'C:/Users/Administrator/.workbuddy/clipboard-images'
OUT = 'images/products'
os.makedirs(OUT, exist_ok=True)


def bg_white_balance(im, target=(252, 248, 238), probe=110):
    """取四角背景的平均色，把背景校正到暖白。板子占比小，不受影响。"""
    w, h = im.size
    boxes = [
        (0, 0, probe, probe),
        (w - probe, 0, w, probe),
        (0, h - probe, probe, h),
        (w - probe, h - probe, w, h),
    ]
    sums = [0.0, 0.0, 0.0]
    for b in boxes:
        st = ImageStat.Stat(im.crop(b))
        for i in range(3):
            sums[i] += st.mean[i]
    avg = [s / len(boxes) for s in sums]
    gains = [min(1.35, max(0.72, target[i] / max(1.0, avg[i]))) for i in range(3)]
    chans = im.split()
    out = [c.point(lambda v, g=g: min(255, int(v * g))) for c, g in zip(chans, gains)]
    return Image.merge('RGB', out), [round(g, 3) for g in gains]


def warm(im, strength):
    """轻微暖调：抬红减蓝。"""
    r, g, b = im.split()
    r = r.point(lambda v: min(255, int(v * (1 + 0.05 * strength))))
    b = b.point(lambda v: int(v * (1 - 0.06 * strength)))
    return Image.merge('RGB', (r, g, b))


def vignette(im, strength=0.32):
    w, h = im.size
    mask = Image.new('L', (w, h), 0)
    ImageDraw.Draw(mask).ellipse([-w * 0.14, -h * 0.14, w * 1.14, h * 1.14], fill=255)
    mask = mask.filter(ImageFilter.GaussianBlur(w * 0.10))
    dark = ImageEnhance.Brightness(im).enhance(1 - strength)
    return Image.composite(im, dark, mask)


# (源文件, 输出名, 裁切框, 亮度, 对比, 饱和, 暗角, 暖调强度)
JOBS = [
    (
        'clipboard-2026-11-28T01-59-11-029Z-f9a1589a.jpg',
        'motor-ctrl-1.jpg',
        (0, 415, 1440, 1855),
        1.03, 1.05, 1.00, 0.00, 0.30,
    ),
    (
        'clipboard-2026-11-28T01-59-11-031Z-cb65b7d6.jpg',
        'esp32-board-1.jpg',
        (0, 430, 1440, 1870),
        1.18, 1.02, 0.55, 0.40, 0.12,
    ),
    (
        'clipboard-2026-11-28T01-59-11-033Z-147c4863.jpg',
        'servo-ctrl-1.jpg',
        (0, 360, 700, 1060),
        1.05, 1.05, 0.95, 0.00, 0.28,
    ),
]


def process(job):
    src, name, box, bright, contrast, sat, vig, warm_k = job
    im = Image.open(os.path.join(SRC, src)).convert('RGB').crop(box)

    w, h = im.size
    side = min(w, h)
    im = im.crop(((w - side) // 2, (h - side) // 2, (w - side) // 2 + side, (h - side) // 2 + side))
    im = im.resize((1000, 1000), Image.LANCZOS)

    im = ImageEnhance.Brightness(im).enhance(bright)
    im = ImageEnhance.Contrast(im).enhance(contrast)
    im = ImageEnhance.Color(im).enhance(sat)

    im = warm(im, warm_k)
    gains = None
    if vig:
        im = vignette(im, vig)
    im = im.filter(ImageFilter.UnsharpMask(radius=1.5, percent=45, threshold=3))

    path = os.path.join(OUT, name)
    im.save(path, 'JPEG', quality=88, optimize=True, progressive=True)
    print(f'{name}  {im.size}  {os.path.getsize(path)//1024}KB  wb={gains}')


if __name__ == '__main__':
    for j in JOBS:
        process(j)
