# -*- coding: utf-8 -*-
"""
Generateur de QR code autonome -> SVG, sans aucune dependance externe.

Pourquoi ce fichier plutot qu'un generateur en ligne : un QR code imprime a
plusieurs milliers d'exemplaires ne doit pas dependre d'un service tiers qui
peut fermer, tracer les scans ou changer la redirection du jour au lendemain.
Le code est genere ici, a partir de l'URL en clair, et le SVG produit est
versionne avec les supports d'impression.

Usage :
    python docs/generate_qr.py                            # URL par defaut
    python docs/generate_qr.py https://exemple.fr out.svg

IMPORTANT — le domaine linkee.fr n'est pas encore reserve (cf.
docs/fondateurs/SL-09). Regenerer le QR des que l'adresse publique est fixee,
puis reexporter les PDF des flyers et des affiches.

Encodage : mode octet, niveau de correction Q (25 % de la surface peut etre
abimee, collee, dechiree ou recouverte sans perdre la lecture) — c'est le
niveau qui convient a de l'affichage en rue.
"""
import sys

# ---------------------------------------------------------------- GF(256) --
_EXP = [0] * 512
_LOG = [0] * 256
_x = 1
for _i in range(255):
    _EXP[_i] = _x
    _LOG[_x] = _i
    _x <<= 1
    if _x & 0x100:
        _x ^= 0x11D
for _i in range(255, 512):
    _EXP[_i] = _EXP[_i - 255]


def _mul(a, b):
    if a == 0 or b == 0:
        return 0
    return _EXP[_LOG[a] + _LOG[b]]


def _rs_divisor(degree):
    """Polynome generateur de Reed-Solomon du degre demande."""
    result = [0] * (degree - 1) + [1]
    root = 1
    for _ in range(degree):
        for j in range(degree):
            result[j] = _mul(result[j], root)
            if j + 1 < degree:
                result[j] ^= result[j + 1]
        root = _mul(root, 0x02)
    return result


def _rs_remainder(data, divisor):
    result = [0] * len(divisor)
    for b in data:
        factor = b ^ result.pop(0)
        result.append(0)
        for i in range(len(divisor)):
            result[i] ^= _mul(divisor[i], factor)
    return result


# ------------------------------------------------- tables de correction ----
# Index 0 = version 1. Versions 1 a 10 : largement suffisant pour une URL.
_ECC_PER_BLOCK = {
    'L': [7, 10, 15, 20, 26, 18, 20, 24, 30, 18],
    'M': [10, 16, 26, 18, 24, 16, 18, 22, 22, 26],
    'Q': [13, 22, 18, 26, 18, 24, 18, 22, 20, 24],
    'H': [17, 28, 22, 16, 22, 28, 26, 26, 24, 28],
}
_NUM_BLOCKS = {
    'L': [1, 1, 1, 1, 1, 2, 2, 2, 2, 4],
    'M': [1, 1, 1, 2, 2, 4, 4, 4, 5, 5],
    'Q': [1, 1, 2, 2, 4, 4, 6, 6, 8, 8],
    'H': [1, 1, 2, 4, 4, 4, 5, 6, 8, 8],
}
_FORMAT_BITS = {'L': 1, 'M': 0, 'Q': 3, 'H': 2}


def _raw_data_modules(ver):
    result = (16 * ver + 128) * ver + 64
    if ver >= 2:
        numalign = ver // 7 + 2
        result -= (25 * numalign - 10) * numalign - 55
        if ver >= 7:
            result -= 36
    return result


def _align_positions(ver):
    if ver == 1:
        return []
    numalign = ver // 7 + 2
    size = ver * 4 + 17
    step = (ver * 4 + numalign * 2 + 1) // (numalign * 2 - 2) * 2
    result = [size - 7 - i * step for i in range(numalign - 1)] + [6]
    return list(reversed(result))


def _bit(x, i):
    return (x >> i) & 1 != 0


class QrCode(object):
    def __init__(self, text, ecl='Q'):
        data = text.encode('utf-8')
        self.ecl = ecl

        # Plus petite version qui contient la donnee.
        capacity = countbits = 0
        for ver in range(1, 11):
            nblocks = _NUM_BLOCKS[ecl][ver - 1]
            ecclen = _ECC_PER_BLOCK[ecl][ver - 1]
            capacity = (_raw_data_modules(ver) // 8) - ecclen * nblocks
            countbits = 8 if ver <= 9 else 16
            needed = (4 + countbits + 8 * len(data) + 7) // 8
            if needed <= capacity:
                break
        else:
            raise ValueError('Donnee trop longue (ce generateur va jusqu a la version 10).')

        self.version = ver
        self.size = ver * 4 + 17
        self._modules = [[False] * self.size for _ in range(self.size)]
        self._isfunc = [[False] * self.size for _ in range(self.size)]

        # --- flux binaire ---------------------------------------------------
        bits = []

        def push(value, length):
            for i in reversed(range(length)):
                bits.append(_bit(value, i))

        push(0b0100, 4)
        push(len(data), countbits)
        for b in data:
            push(b, 8)
        push(0, min(4, capacity * 8 - len(bits)))
        push(0, -len(bits) % 8)
        for pad in [0xEC, 0x11] * capacity:
            if len(bits) >= capacity * 8:
                break
            push(pad, 8)

        codewords = bytearray()
        for i in range(0, len(bits), 8):
            byte = 0
            for j in range(8):
                byte = byte << 1 | (1 if bits[i + j] else 0)
            codewords.append(byte)

        self._draw_function_patterns()
        self._draw_codewords(self._add_ecc_and_interleave(codewords))
        self._apply_best_mask()

    # ------------------------------------------------------------ motifs ---
    def _set_func(self, x, y, dark):
        self._modules[y][x] = dark
        self._isfunc[y][x] = True

    def _draw_function_patterns(self):
        size = self.size
        for i in range(size):
            self._set_func(6, i, i % 2 == 0)
            self._set_func(i, 6, i % 2 == 0)

        for (cx, cy) in ((3, 3), (size - 4, 3), (3, size - 4)):
            for dy in range(-4, 5):
                for dx in range(-4, 5):
                    x, y = cx + dx, cy + dy
                    if 0 <= x < size and 0 <= y < size:
                        self._set_func(x, y, max(abs(dx), abs(dy)) not in (2, 4))

        pos = _align_positions(self.version)
        n = len(pos)
        skip = ((0, 0), (0, n - 1), (n - 1, 0))
        for i in range(n):
            for j in range(n):
                if (i, j) not in skip:
                    for dy in range(-2, 3):
                        for dx in range(-2, 3):
                            self._set_func(pos[j] + dx, pos[i] + dy,
                                           max(abs(dx), abs(dy)) != 1)

        self._draw_format_bits(0)
        self._draw_version_bits()

    def _draw_format_bits(self, mask):
        data = _FORMAT_BITS[self.ecl] << 3 | mask
        rem = data
        for _ in range(10):
            rem = (rem << 1) ^ ((rem >> 9) * 0x537)
        bits = (data << 10 | rem) ^ 0x5412
        size = self.size
        for i in range(6):
            self._set_func(8, i, _bit(bits, i))
        self._set_func(8, 7, _bit(bits, 6))
        self._set_func(8, 8, _bit(bits, 7))
        self._set_func(7, 8, _bit(bits, 8))
        for i in range(9, 15):
            self._set_func(14 - i, 8, _bit(bits, i))
        for i in range(8):
            self._set_func(size - 1 - i, 8, _bit(bits, i))
        for i in range(8, 15):
            self._set_func(8, size - 15 + i, _bit(bits, i))
        self._set_func(8, size - 8, True)

    def _draw_version_bits(self):
        if self.version < 7:
            return
        rem = self.version
        for _ in range(12):
            rem = (rem << 1) ^ ((rem >> 11) * 0x1F25)
        bits = self.version << 12 | rem
        for i in range(18):
            b = _bit(bits, i)
            a = self.size - 11 + i % 3
            c = i // 3
            self._set_func(a, c, b)
            self._set_func(c, a, b)

    # --------------------------------------------------------- codewords ---
    def _add_ecc_and_interleave(self, data):
        ver, ecl = self.version, self.ecl
        nblocks = _NUM_BLOCKS[ecl][ver - 1]
        ecclen = _ECC_PER_BLOCK[ecl][ver - 1]
        rawcw = _raw_data_modules(ver) // 8
        numshort = nblocks - rawcw % nblocks
        shortlen = rawcw // nblocks

        blocks = []
        divisor = _rs_divisor(ecclen)
        k = 0
        for i in range(nblocks):
            length = shortlen - ecclen + (0 if i < numshort else 1)
            dat = list(data[k:k + length])
            k += length
            ecc = _rs_remainder(dat, divisor)
            if i < numshort:
                dat.append(0)
            blocks.append(dat + ecc)

        result = bytearray()
        for i in range(len(blocks[0])):
            for (j, blk) in enumerate(blocks):
                if i != shortlen - ecclen or j >= numshort:
                    result.append(blk[i])
        return result

    def _draw_codewords(self, data):
        size = self.size
        i = 0
        for right in range(size - 1, 0, -2):
            if right <= 6:
                right -= 1
            for vert in range(size):
                for j in range(2):
                    x = right - j
                    upward = (right + 1) & 2 == 0
                    y = (size - 1 - vert) if upward else vert
                    if not self._isfunc[y][x] and i < len(data) * 8:
                        self._modules[y][x] = _bit(data[i >> 3], 7 - (i & 7))
                        i += 1

    # ------------------------------------------------------------ masque ---
    def _apply_mask(self, mask):
        for y in range(self.size):
            for x in range(self.size):
                if self._isfunc[y][x]:
                    continue
                if mask == 0:
                    inv = (x + y) % 2 == 0
                elif mask == 1:
                    inv = y % 2 == 0
                elif mask == 2:
                    inv = x % 3 == 0
                elif mask == 3:
                    inv = (x + y) % 3 == 0
                elif mask == 4:
                    inv = (y // 2 + x // 3) % 2 == 0
                elif mask == 5:
                    inv = x * y % 2 + x * y % 3 == 0
                elif mask == 6:
                    inv = (x * y % 2 + x * y % 3) % 2 == 0
                else:
                    inv = ((x + y) % 2 + x * y % 3) % 2 == 0
                self._modules[y][x] ^= inv

    def _apply_best_mask(self):
        best, bestscore = 0, None
        for mask in range(8):
            self._apply_mask(mask)
            self._draw_format_bits(mask)
            score = self._penalty()
            if bestscore is None or score < bestscore:
                best, bestscore = mask, score
            self._apply_mask(mask)  # involution : on annule
        self._apply_mask(best)
        self._draw_format_bits(best)
        self.mask = best

    def _penalty(self):
        size, m = self.size, self._modules
        score = 0
        finder = [True, False, True, True, True, False, True,
                  False, False, False, False]

        def line_penalty(line):
            s, run, prev = 0, 1, line[0]
            for c in line[1:]:
                if c == prev:
                    run += 1
                else:
                    if run >= 5:
                        s += 3 + (run - 5)
                    run, prev = 1, c
            if run >= 5:
                s += 3 + (run - 5)
            for i in range(len(line) - 10):
                window = line[i:i + 11]
                if window == finder or window == finder[::-1]:
                    s += 40
            return s

        for y in range(size):
            score += line_penalty(m[y])
        for x in range(size):
            score += line_penalty([m[y][x] for y in range(size)])

        for y in range(size - 1):
            for x in range(size - 1):
                if m[y][x] == m[y][x + 1] == m[y + 1][x] == m[y + 1][x + 1]:
                    score += 3

        dark = sum(row.count(True) for row in m)
        total = size * size
        k = (abs(dark * 20 - total * 10) + total - 1) // total - 1
        score += max(k, 0) * 10
        return score

    # --------------------------------------------------------------- SVG ---
    def to_svg(self, border=4, dark='#1C1916', light='#FFFFFF', url=''):
        size = self.size
        dim = size + border * 2
        parts = []
        for y in range(size):
            x = 0
            while x < size:
                if self._modules[y][x]:
                    run = 1
                    while x + run < size and self._modules[y][x + run]:
                        run += 1
                    parts.append('M%d,%dh%dv1h-%dz' % (x + border, y + border, run, run))
                    x += run
                else:
                    x += 1
        light_rect = ''
        if light:
            light_rect = '<rect width="%d" height="%d" fill="%s"/>' % (dim, dim, light)
        return (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<!-- QR code Linkee - genere par docs/generate_qr.py -->\n'
            '<!-- Contenu : %s -->\n'
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" '
            'shape-rendering="crispEdges" role="img" aria-label="QR code vers %s">'
            '%s<path d="%s" fill="%s"/></svg>\n'
        ) % (url, dim, dim, url, light_rect, ''.join(parts), dark)


def main():
    url = sys.argv[1] if len(sys.argv) > 1 else 'https://linkee.fr'
    out = sys.argv[2] if len(sys.argv) > 2 else 'docs/qr_linkee.svg'
    qr = QrCode(url, 'Q')
    with open(out, 'w', encoding='utf-8') as f:
        f.write(qr.to_svg(url=url))
    print('%s  ->  version %d (%d x %d modules), correction Q, masque %d'
          % (out, qr.version, qr.size, qr.size, qr.mask))
    print('URL encodee : %s' % url)


if __name__ == '__main__':
    main()
