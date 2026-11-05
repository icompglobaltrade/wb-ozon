#!/usr/bin/env python3
"""Инфографика для новых карточек Маркета (05.11): два слайда 900x1200 на товар — «главное» и «характеристики».
Стиль как у y0NNa/b: тёмный градиент, салатовый акцент, Inter. Гарантия 12 месяцев на обоих слайдах."""
import json, os, sys
from PIL import Image, ImageDraw, ImageFont, ImageFilter

W, H = 900, 1200
F = '/usr/share/fonts/opentype/inter/Inter-%s.otf'
LIME, WHITE, GREY, CARD, LINE, VIOLET = (200, 240, 60), (245, 245, 247), (150, 150, 160), (24, 24, 30), (55, 55, 65), (130, 100, 230)


def font(w, s): return ImageFont.truetype(F % w, s)


def bg():
    im = Image.new('RGB', (W, H), (8, 8, 12))
    glow = Image.new('RGB', (W, H), (0, 0, 0)); d = ImageDraw.Draw(glow)
    d.ellipse((450, -350, 1350, 450), fill=(60, 30, 110)); d.ellipse((-450, 800, 350, 1600), fill=(40, 55, 10))
    glow = glow.filter(ImageFilter.GaussianBlur(160))
    return Image.blend(im, glow, .75)


def fit(d, text, w, maxs, minsz=30, weight='Bold'):
    s = maxs
    while s > minsz and d.textlength(text, font=font(weight, s)) > w: s -= 2
    return font(weight, s)


def header(d, brand, title, sub):
    d.text((50, 62), brand.upper(), font=font('Bold', 24), fill=LIME)
    f = fit(d, title, 800, 66)
    d.text((50, 104), title, font=f, fill=WHITE)
    d.text((50, 104 + f.size + 14), sub, font=font('Medium', 28), fill=GREY)


def rr(d, box, r=26, fill=CARD, outline=LINE, width=2): d.rounded_rectangle(box, r, fill=fill, outline=outline, width=width)


def slide_a(p):
    im = bg(); d = ImageDraw.Draw(im)
    header(d, p['brand'], p['title'], p['sub'])
    # крупный блок: главное отличие
    rr(d, (50, 250, 850, 610), 34, fill=(16, 16, 22))
    big, unit = p['hero']
    fb = fit(d, big, 560, 190, 90, 'Bold')
    bw = d.textlength(big, font=fb); uw = d.textlength(unit, font=font('Bold', 64))
    x = (W - bw - uw - 14) / 2
    d.text((x, 300), big, font=fb, fill=WHITE)
    d.text((x + bw + 14, 300 + fb.size - 78), unit, font=font('Bold', 64), fill=LIME)
    hc = p['hero_caption']; fc = font('Medium', 32)
    d.text(((W - d.textlength(hc, font=fc)) / 2, 540), hc, font=fc, fill=GREY)
    # 4 плитки
    for i, (v, u, cap) in enumerate(p['tiles'][:4]):
        cx, cy = 50 + (i % 2) * 410, 640 + (i // 2) * 168
        rr(d, (cx, cy, cx + 390, cy + 150))
        fv = fit(d, v, 250 if u else 330, 62, 34)
        d.text((cx + 26, cy + 24), v, font=fv, fill=WHITE)
        if u: d.text((cx + 26 + d.textlength(v, font=fv) + 10, cy + 24 + fv.size - 38), u, font=font('Bold', 34), fill=LIME)
        d.text((cx + 26, cy + 104), cap, font=font('Medium', 24), fill=GREY)
    # гарантия + чип
    y = 990
    rr(d, (50, y, 850, y + 96), 30, fill=(28, 34, 12), outline=LIME, width=3)
    d.text((86, y + 26), '✓', font=font('Bold', 40), fill=LIME)
    d.text((140, y + 28), 'Гарантия 12 месяцев', font=font('Bold', 38), fill=WHITE)
    chip = p['chip']; fcp = font('SemiBold', 26)
    cw = d.textlength(chip, font=fcp)
    rr(d, (50, y + 120, 50 + cw + 56, y + 176), 28, fill=(20, 16, 34), outline=VIOLET, width=2)
    d.text((78, y + 133), chip, font=fcp, fill=WHITE)
    return im


def slide_b(p):
    im = bg(); d = ImageDraw.Draw(im)
    header(d, p['brand'], p['title'], 'Характеристики')
    rows = p['specs'] + [('Гарантия', '12 месяцев')]
    top, rh = 240, min(112, (1110 - 240) // len(rows))
    rr(d, (50, top, 850, top + rh * len(rows) + 24), 30)
    for i, (k, v) in enumerate(rows):
        y = top + 22 + i * rh
        d.ellipse((80, y + 10, 92, y + 22), fill=LIME)
        d.text((108, y), k.upper(), font=font('Medium', 25), fill=GREY)
        d.text((108, y + 34), v, font=fit(d, v, 700, 36, 24, 'SemiBold'), fill=WHITE if k != 'Гарантия' else LIME)
        if i < len(rows) - 1: d.line((80, y + rh - 10, 820, y + rh - 10), fill=LINE, width=2)
    return im


if __name__ == '__main__':
    spec = json.load(open(sys.argv[1], encoding='utf-8')); out = sys.argv[2]
    os.makedirs(out, exist_ok=True)
    for oid, p in spec.items():
        slide_a(p).save(os.path.join(out, oid + '_a.jpg'), quality=90)
        slide_b(p).save(os.path.join(out, oid + '_b.jpg'), quality=90)
    print(len(spec), 'товаров')
