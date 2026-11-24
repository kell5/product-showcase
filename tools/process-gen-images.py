# -*- coding: utf-8 -*-
"""把 ImageGen 输出整理成站点图片：裁掉底部水印 -> 缩放 -> 存 JPG/WebP。
运行：python tools/process-gen-images.py
"""
import os
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
GEN = os.path.join(ROOT, 'images', '_gen')
OUT = os.path.join(ROOT, 'images')

# 源文件名前缀 -> (目标文件, 底部裁剪像素, 输出宽度)
MAP = {
    'Editorial_product_photograph_f_': ('hero-main.jpg', 0, 1400),
    'Editorial_product_photograph_o_2026-11-24T07-15-44': ('vision-tracker-main.jpg', 0, 1400),
    'Editorial_product_photograph_o_2026-11-24T07-16-22': ('gesture-arm-main.jpg', 0, 1400),
    'Editorial_product_photograph_o_2026-11-24T07-17-30': ('sound-core-main.jpg', 0, 1400),
    'Editorial_product_photograph_o_2026-11-24T07-18-58': ('doorbell-app-device.jpg', 0, 1400),
    'Extreme_macro_photograph_of_a__': ('sound-core-lcd.jpg', 0, 1400),
    'Macro_editorial_photograph_of__2026-11-24T07-16-04': ('gesture-arm-board.jpg', 0, 1400),
    'Macro_editorial_photograph_of__2026-11-24T07-17-04': ('vision-tracker-camera.jpg', 0, 1400),
    'Editorial_macro_photograph_of__': ('sound-core-board.jpg', 0, 1400),
    'Editorial_photograph_of_an_edg_': ('vision-tracker-tracking.jpg', 0, 1400),
    'Close_up_editorial_photograph__': ('gesture-arm-glove.jpg', 0, 1400),
    'Square_editorial_photograph_of_': ('avatar.jpg', 0, 900),
}

DOORBELL_STUDIO = {
    'Studio_product_render_of_a_cla_': 'yinghuo-light.webp',
    'Studio_product_render_of_a_com_': 'yinghuo-gateway.webp',
    'Studio_product_render_of_a_sma_': 'yinghuo-doorbell.webp',
}


def find(prefix):
    for name in sorted(os.listdir(GEN)):
        if name.startswith(prefix) and name.lower().endswith('.png'):
            return os.path.join(GEN, name)
    return None


def crop_bottom(im, px):
    w, h = im.size
    return im.crop((0, 0, w, h - px))


def fit_width(im, target):
    w, h = im.size
    if w <= target:
        return im
    ratio = target / w
    return im.resize((target, round(h * ratio)), Image.LANCZOS)


def patch_watermark(im):
    """覆盖右下角固定位置的 AI 水印（约 0.85w/0.84h 起），用上方条带翻转填充。"""
    from PIL import ImageFilter
    im = im.convert('RGB')
    w, h = im.size
    x0, x1 = int(w * 0.86), int(w * 0.999)
    y0, y1 = int(h * 0.895), h
    bh = y1 - y0
    src = im.crop((x0, y0 - bh, x1, y0)).transpose(Image.FLIP_TOP_BOTTOM)
    im.paste(src, (x0, y0))
    band = im.crop((x0 - 4, y0 - 6, x1, y1 + 6)).filter(ImageFilter.GaussianBlur(3.5))
    im.paste(band, (x0 - 4, y0 - 6))
    return im


def main():
    for prefix, (target, crop, width) in MAP.items():
        src = find(prefix)
        if not src:
            print('MISS', target)
            continue
        im = Image.open(src).convert('RGB')
        im = patch_watermark(im)
        if crop:
            im = crop_bottom(im, crop)
        im = fit_width(im, width)
        path = os.path.join(OUT, target)
        im.save(path, 'JPEG', quality=87, optimize=True, progressive=True)
        print('OK  ', target, im.size, round(os.path.getsize(path) / 1024), 'KB')

    for prefix, target in DOORBELL_STUDIO.items():
        src = find(prefix)
        if not src:
            print('MISS', target)
            continue
        im = Image.open(src).convert('RGB')
        im = patch_watermark(im)
        im = fit_width(im, 512)
        path = os.path.join(OUT, target)
        im.save(path, 'WEBP', quality=88, method=6)
        print('OK  ', target, im.size, round(os.path.getsize(path) / 1024), 'KB')


if __name__ == '__main__':
    main()
