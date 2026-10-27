"""Draws the WP BBS Slots logo, a 400x400 ANSI-art style SVG made only of square 'pixels',
so it looks the same in every browser with no fonts involved.

    python tools/build-logo.py assets/img/wp-bbs-slots-logo.svg
"""
import sys

FONT = {  # 5x7 bitmaps
    'W': ["10001", "10001", "10001", "10101", "10101", "11011", "10001"],
    'P': ["11110", "10001", "10001", "11110", "10000", "10000", "10000"],
    'B': ["11110", "10001", "10001", "11110", "10001", "10001", "11110"],
    'S': ["01111", "10000", "10000", "01110", "00001", "00001", "11110"],
    'L': ["10000", "10000", "10000", "10000", "10000", "10000", "11111"],
    'O': ["01110", "10001", "10001", "10001", "10001", "10001", "01110"],
    'T': ["11111", "00100", "00100", "00100", "00100", "00100", "00100"],
    '!': ["00100", "00100", "00100", "00100", "00100", "00000", "00100"],
    'U': ["10001", "10001", "10001", "10001", "10001", "10001", "01110"],
    'H': ["10001", "10001", "10001", "11111", "10001", "10001", "10001"],
    'E': ["11111", "10000", "10000", "11110", "10000", "10000", "11111"],
    'A': ["01110", "10001", "10001", "11111", "10001", "10001", "10001"],
    'N': ["10001", "11001", "10101", "10011", "10001", "10001", "10001"],
    'D': ["11110", "10001", "10001", "10001", "10001", "10001", "11110"],
    'R': ["11110", "10001", "10001", "11110", "10100", "10010", "10001"],
    '7': ["11111", "00001", "00010", "00100", "00100", "00100", "00100"],
    ' ': ["00000"] * 7,
}

# The 16-colour ANSI/VGA palette (the bright half, plus a few dark ones for shadows).
BLACK, DBLUE, DMAG, DCYAN, DGRAY = '#000000', '#0000AA', '#AA00AA', '#00AAAA', '#555555'
RED, GREEN, YELLOW, BLUE, MAG, CYAN, WHITE = '#FF5555', '#55FF55', '#FFFF55', '#5555FF', '#FF55FF', '#55FFFF', '#FFFFFF'

rects = []


def px(x, y, w, h, c):
    rects.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{c}"/>')


def text(s, x, y, scale, colors, shadow=None):
    """Draws s with its top-left at (x, y); colors gives one colour per glyph row (ANSI bands)."""
    for layer in ([('shadow', shadow)] if shadow else []) + [('main', None)]:
        off = scale // 2 if layer[0] == 'shadow' else 0
        cx = x
        for ch in s:
            for r, row in enumerate(FONT[ch]):
                for c, bit in enumerate(row):
                    if bit == '1':
                        col = layer[1] if layer[0] == 'shadow' else colors[r % len(colors)]
                        px(cx + c * scale + off, y + r * scale + off, scale, scale, col)
            cx += 6 * scale


def width(s, scale):
    return (6 * len(s) - 1) * scale


W = 400
px(0, 0, W, W, BLACK)

# Border: a ring of ANSI shade blocks cycling through the palette, like a BBS menu frame.
cycle = [MAG, RED, YELLOW, GREEN, CYAN, BLUE]
B = 10
n = W // B
ring = [(i, 0) for i in range(n)] + [(n - 1, j) for j in range(1, n)] + \
       [(i, n - 1) for i in range(n - 2, -1, -1)] + [(0, j) for j in range(n - 2, 0, -1)]
for k, (i, j) in enumerate(ring):
    px(i * B, j * B, B, B, cycle[(k // 3) % len(cycle)])
# A second, dithered ring inside it (the "shade" characters).
for k, (i, j) in enumerate(ring):
    if k % 2 == 0:
        x = i * B + (B if i == 0 else -B if i == n - 1 else 0)
        y = j * B + (B if j == 0 else -B if j == n - 1 else 0)
        px(x + 3, y + 3, 4, 4, DGRAY)

# Title, two lines, with ANSI colour bands and a dark drop shadow.
s1, s2, sc = 'WP BBS', 'SLOTS!', 9
text(s1, (W - width(s1, sc)) // 2, 36, sc, [CYAN, CYAN, BLUE, BLUE, MAG, MAG, MAG], shadow=DBLUE)
text(s2, (W - width(s2, sc)) // 2, 112, sc, [YELLOW, YELLOW, YELLOW, RED, RED, RED, MAG], shadow=DMAG)

# A shaded divider bar under the title: light to solid to light, in ANSI shade-block style.
y = 190
for i in range(4, n - 4):
    dist = abs(i - n / 2) / (n / 2)
    size = 10 if dist < 0.35 else 6 if dist < 0.6 else 3
    c = YELLOW if dist < 0.35 else RED if dist < 0.6 else MAG
    px(i * B + (10 - size) // 2, y + (10 - size) // 2, size, size, c)

# Three reels showing 7 7 7, each a framed window.
reel_w, reel_h, gap = 96, 112, 14
x0 = (W - (3 * reel_w + 2 * gap)) // 2
ry = 214
for k, col in enumerate([RED, YELLOW, CYAN]):
    rx = x0 + k * (reel_w + gap)
    px(rx - 4, ry - 4, reel_w + 8, reel_h + 8, WHITE)        # chrome frame
    px(rx, ry, reel_w, reel_h, DGRAY)
    px(rx + 4, ry + 4, reel_w - 8, reel_h - 8, BLACK)
    # payline markers
    px(rx - 4, ry + reel_h // 2 - 3, 4, 6, RED)
    px(rx + reel_w, ry + reel_h // 2 - 3, 4, 6, RED)
    sc7 = 12
    gx = rx + (reel_w - 5 * sc7) // 2
    gy = ry + (reel_h - 7 * sc7) // 2
    text('7', gx, gy, sc7, [col], shadow=DGRAY)

# Tag line.
tag, sct = 'PULL THE HANDLE!', 3
text(tag, (W - width(tag, sct)) // 2, 352, sct, [GREEN, GREEN, GREEN, GREEN, CYAN, CYAN, CYAN])

svg = (f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{W}" viewBox="0 0 {W} {W}" '
       f'shape-rendering="crispEdges" role="img" aria-labelledby="t">'
       f'<title id="t">WP BBS Slots!</title>' + ''.join(rects) + '</svg>\n')
open(sys.argv[1], 'w', encoding='utf-8', newline='\n').write(svg)
print(len(rects), 'rects,', len(svg), 'bytes')
