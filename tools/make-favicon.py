#!/usr/bin/env python3
"""按 images/brand/favicon.svg 的坐标重绘，输出根目录 favicon.ico（多尺寸）。

为什么不直接缩放 PNG：16x16 从 180px 缩下来，白色笔画会糊成一团。
这里每个尺寸都按矢量坐标单独超采样绘制，小尺寸也能看清标记。
"""
import os
import struct
import io
from PIL import Image, ImageDraw

BG = (16, 28, 22, 255)        # #101C16
FG = (255, 255, 255, 255)
SS = 12                        # 超采样倍数

# SVG 原始坐标（viewBox 0 0 100 100）
BG_RX = 22
RECTS = [
    (36, 20, 13, 60, 3),       # 主干
    (49, 24, 21, 21, 2),       # 上臂
    (23, 51, 13, 5.5, 1.5),    # 三条横杠
    (23, 62.5, 13, 5.5, 1.5),
    (23, 74, 13, 5.5, 1.5),
]


def draw(px, simplified=False):
    """在 px 像素画布上绘制图标，返回 RGBA 图。

    simplified=True 时省掉三条横杠——16px 下它们会糊成一坨，
    只留主干与上臂，缩到任务栏大小反而更好认。
    """
    s = px / 100.0
    im = Image.new('RGBA', (px, px), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    d.rounded_rectangle([0, 0, px - 1, px - 1], radius=BG_RX * s, fill=BG)

    # 白色组变换：x' = 50 + 0.82*(x-46.5)，y' = 50 + 0.82*(y-50)
    k, cx, cy = 0.82, 46.5, 50.0

    def tx(x):
        return (50 + k * (x - cx)) * s

    def ty(y):
        return (50 + k * (y - cy)) * s

    for x, y, w, h, rx in (RECTS[:2] if simplified else RECTS):
        d.rounded_rectangle(
            [tx(x), ty(y), tx(x + w), ty(y + h)],
            radius=max(0.6, rx * k * s),
            fill=FG,
        )
    return im


def render(size):
    big = draw(size * SS, simplified=(size <= 16))
    return big.resize((size, size), Image.LANCZOS)


def build_ico(sizes, out_path):
    """手写 ICO 容器：每帧塞入独立渲染的 PNG，避免 ico 内部缩放。"""
    frames = []
    for sz in sizes:
        buf = io.BytesIO()
        render(sz).save(buf, 'PNG', optimize=True)
        frames.append((sz, buf.getvalue()))

    header = struct.pack('<HHH', 0, 1, len(frames))
    offset = 6 + 16 * len(frames)
    entries, blobs = b'', b''
    for sz, data in frames:
        dim = 0 if sz >= 256 else sz
        entries += struct.pack('<BBBBHHII', dim, dim, 0, 0, 1, 32, len(data), offset)
        blobs += data
        offset += len(data)

    with open(out_path, 'wb') as f:
        f.write(header + entries + blobs)
    print(f'{out_path}: {", ".join(str(s) for s, _ in frames)}  ({os.path.getsize(out_path)} bytes)')


if __name__ == '__main__':
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    brand = os.path.join(root, 'images', 'brand')

    build_ico([16, 32, 48, 64], os.path.join(root, 'favicon.ico'))

    # 顺带补两张 PNG 尺寸，给 Bing / 社交卡片用
    for sz in (16, 48):
        p = os.path.join(brand, f'favicon-{sz}.png')
        render(sz).save(p, 'PNG', optimize=True)
        print(f'{p}: {sz}x{sz}')
