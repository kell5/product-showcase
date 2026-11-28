#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""螃蟹洗手机（ESP32 超声波洗手监督）宣传图预处理。

源图是 ImageGen 出的 3:2 影棚图（1536x1024，右下角带水印）。
首页交付样例卡是 1:1 方图，所以这里把画布纵向延展成正方形：
模糊放大的自身当底，原图居中羽化叠加，水印所在的最外圈直接被裁掉。

用法：
    python tools/prep-crab-card.py
输出：
    images/products/board-card-4.jpg  (1200x1200)
"""
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "tools" / "hero-src" / "crab-washer-src.jpg"
OUT = ROOT / "images" / "products" / "board-card-4.jpg"
OUT_HERO = ROOT / "images" / "products" / "crab-washer-1.jpg"

SIZE = 1200          # 交付边长
FEATHER = 90         # 原图与底图的融合羽化宽度
VIGNETTE = 0.34      # 四角压暗强度
ZOOM = 1.18          # 主体放大（影棚图留白多，卡片里要撑起来）
FOCUS = (0.5, 0.52)  # 放大锚点


def build_square(src: Image.Image, size: int) -> Image.Image:
    side = src.width                       # 以宽度为边长 -> 1536x1536
    pad = (side - src.height) // 2         # 上下各补 256

    # 底图：自身放大铺满后强模糊，等于背景光的自然延续
    base = src.resize((side, side), Image.LANCZOS).filter(
        ImageFilter.GaussianBlur(side * 0.055)
    )

    # 羽化遮罩：中间实、四周渐隐，避免出现矩形接缝
    mask = Image.new("L", (src.width, src.height), 0)
    ImageDraw.Draw(mask).rectangle(
        (FEATHER, FEATHER, src.width - FEATHER, src.height - FEATHER), fill=255
    )
    mask = mask.filter(ImageFilter.GaussianBlur(FEATHER * 0.42))

    canvas = base.copy()
    canvas.paste(src, (0, pad), mask)

    # 四角压暗，让白壳更跳
    if VIGNETTE > 0:
        vg = Image.new("L", (side, side), 0)
        d = ImageDraw.Draw(vg)
        d.ellipse(
            (-side * 0.16, -side * 0.16, side * 1.16, side * 1.16), fill=255
        )
        vg = vg.filter(ImageFilter.GaussianBlur(side * 0.1))
        dark = Image.new("RGB", (side, side), (2, 6, 14))
        canvas = Image.composite(canvas, dark, vg.point(
            lambda v: int(255 - (255 - v) * VIGNETTE)
        ))

    square = canvas.resize((size, size), Image.LANCZOS)

    # 再往里收一点，让主体在 1:1 卡片里占满一些（留白太多会显小）
    if ZOOM > 1:
        w = int(size / ZOOM)
        cx, cy = FOCUS[0] * size, FOCUS[1] * size
        x = max(0, min(size - w, int(cx - w / 2)))
        y = max(0, min(size - w, int(cy - w / 2)))
        square = square.crop((x, y, x + w, y + w)).resize((size, size), Image.LANCZOS)

    return square


def main() -> None:
    if not SRC.exists():
        raise SystemExit(f"源图不存在：{SRC}")
    src = Image.open(SRC).convert("RGB")

    # 右下角 AI 水印从 x≈1412 起，右切 130px 刚好整条去掉，蟹钳尖还留着
    clean = src.crop((0, 0, src.width - 130, src.height))
    src = clean

    card = build_square(src, SIZE)
    card = ImageChops.multiply(card, Image.new("RGB", card.size, (250, 251, 255)))

    OUT.parent.mkdir(parents=True, exist_ok=True)
    card.save(OUT, "JPEG", quality=88, optimize=True, progressive=True)
    print(f"✓ {OUT.relative_to(ROOT)}  {card.size[0]}x{card.size[1]}")

    # 详情页主图：横版原比例，只做轻微提亮
    hero = ImageChops.multiply(src, Image.new("RGB", src.size, (252, 253, 255)))
    hero.thumbnail((1500, 1500), Image.LANCZOS)
    hero.save(OUT_HERO, "JPEG", quality=88, optimize=True, progressive=True)
    print(f"✓ {OUT_HERO.relative_to(ROOT)}  {hero.size[0]}x{hero.size[1]}")


if __name__ == "__main__":
    main()
