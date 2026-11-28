#!/usr/bin/env python3
"""生成「励磁 LICI LAB」品牌标识：深绿圆角底 + 白色 PWM 脉冲波形。

脉冲波形直接对应电机调速（PWM），折线在小尺寸下比圆环更清晰。
同时输出 svg / png / 多尺寸 ico。
"""
import os
import struct
import io
from PIL import Image, ImageDraw

BG = (13, 26, 46, 255)      # #101C16
FG = (255, 255, 255, 255)
SS = 12                      # 超采样

# viewBox 0 0 100 100
RX_BG = 22
STROKE = 11                  # 波形线宽
# PWM 脉冲折线：两个上升沿，一个宽脉冲一个窄脉冲
PTS = [(13, 71), (31, 71), (31, 29), (55, 29), (55, 71), (73, 71), (73, 49), (88, 49)]


def draw(px):
    s = px / 100.0
    im = Image.new('RGBA', (px, px), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    d.rounded_rectangle([0, 0, px - 1, px - 1], radius=RX_BG * s, fill=BG)

    w = max(1, int(round(STROKE * s)))
    pts = [(x * s, y * s) for x, y in PTS]
    d.line(pts, fill=FG, width=w, joint='curve')
    # 两端补圆头
    for px_, py_ in (pts[0], pts[-1]):
        d.ellipse([px_ - w / 2, py_ - w / 2, px_ + w / 2, py_ + w / 2], fill=FG)
    return im


def render(size):
    return draw(size * SS).resize((size, size), Image.LANCZOS)


SVG = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" role="img" aria-label="LICI LAB">
  <rect x="0" y="0" width="100" height="100" rx="22" fill="#0D1A2E"/>
  <polyline points="13,71 31,71 31,29 55,29 55,71 73,71 73,49 88,49"
            fill="none" stroke="#FFFFFF" stroke-width="11"
            stroke-linecap="round" stroke-linejoin="round"/>
</svg>
'''

SVG_MONO = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" role="img" aria-label="LICI LAB">
  <polyline points="13,71 31,71 31,29 55,29 55,71 73,71 73,49 88,49"
            fill="none" stroke="currentColor" stroke-width="11"
            stroke-linecap="round" stroke-linejoin="round"/>
</svg>
'''


def build_ico(sizes, out_path):
    frames = []
    for sz in sizes:
        buf = io.BytesIO()
        render(sz).save(buf, 'PNG', optimize=True)
        frames.append((sz, buf.getvalue()))
    header = struct.pack('<HHH', 0, 1, len(frames))
    offset = 6 + 16 * len(frames)
    entries = blobs = b''
    for sz, data in frames:
        dim = 0 if sz >= 256 else sz
        entries += struct.pack('<BBBBHHII', dim, dim, 0, 0, 1, 32, len(data), offset)
        blobs += data
        offset += len(data)
    with open(out_path, 'wb') as f:
        f.write(header + entries + blobs)
    print(f'{out_path}  {", ".join(str(s) for s, _ in frames)}')


if __name__ == '__main__':
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    brand = os.path.join(root, 'images', 'brand')
    os.makedirs(brand, exist_ok=True)

    open(os.path.join(brand, 'mark.svg'), 'w', encoding='utf-8').write(SVG)
    open(os.path.join(brand, 'mark-mono.svg'), 'w', encoding='utf-8').write(SVG_MONO)
    open(os.path.join(brand, 'favicon.svg'), 'w', encoding='utf-8').write(SVG)
    print('svg written')

    for sz, name in ((32, 'favicon-32.png'), (16, 'favicon-16.png'), (48, 'favicon-48.png'),
                     (180, 'apple-touch-icon.png'), (192, 'icon-192.png')):
        render(sz).save(os.path.join(brand, name), 'PNG', optimize=True)
        print(name, sz)

    build_ico([16, 32, 48, 64], os.path.join(root, 'favicon.ico'))
