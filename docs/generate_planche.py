# -*- coding: utf-8 -*-
"""
Impose le flyer A6 a quatre exemplaires sur une A4, pour une impression
maison (photocopieuse d'ecole, imprimante perso) sans passer par un
imprimeur.

Le fichier produit n'est jamais edite a la main : il est reconstruit a
partir de docs/Flyer_Bars_Linkee.html, qui reste la seule source du
dessin. Modifier le flyer, relancer ce script, reexporter les PDF.

    python docs/generate_planche.py

Trois details d'impression sont regles ici :

  * Les quatre exemplaires sont reduits a 88 % et centres, pour laisser
    une marge blanche qu'aucune imprimante de bureau ne sait imprimer.
    Les flyers sortent donc a 92 x 130 mm au lieu de 105 x 148 mm.
  * Le fond perdu du flyer (3 mm) est recadre ici : chaque cellule est
    une fenetre au format de coupe, et le dessin y est decale de -3 mm.
    Ce qu'on coupe sur la planche correspond exactement au trait de
    coupe de l'imprimeur.
  * Les quatre cellules d'une meme page sont identiques, donc l'ordre
    recto/verso n'a pas a etre inverse : on imprime en recto-verso,
    reliure sur le grand cote, sans se poser de question.
"""
import io
import os
import re

SRC = 'docs/Flyer_Bars_Linkee.html'
OUT = 'docs/Flyer_Bars_Planche_A4.html'

ECHELLE = 0.88          # reduction appliquee a chaque exemplaire
COUPE_L, COUPE_H = 105, 148   # format de coupe du flyer, en mm
FOND_PERDU = 3          # mm de fond perdu a recadrer


def main():
    src = io.open(SRC, encoding='utf-8').read()

    style = re.search(r'<style>(.*?)</style>', src, re.S)
    if not style:
        raise SystemExit('Feuille de style introuvable dans %s' % SRC)
    # On reprend la feuille de style du flyer telle quelle, sauf sa regle
    # @page : ici la page est une A4, pas un A6 avec fond perdu.
    css = re.sub(r'@page\s*\{[^}]*\}', '', style.group(1))

    sections = re.findall(r'<section class="page.*?</section>', src, re.S)
    if len(sections) != 2:
        raise SystemExit('Attendu 2 pages (recto, verso), trouve %d.' % len(sections))

    cell_l = COUPE_L * ECHELLE
    cell_h = COUPE_H * ECHELLE
    marge_x = (210 - cell_l * 2) / 2
    marge_y = (297 - cell_h * 2) / 2

    def planche(section, titre):
        cellules = '\n'.join(
            '      <div class="cellule"><div class="zoom"><div class="fenetre">\n%s\n'
            '      </div></div></div>' % section for _ in range(4))
        return (
            '  <!-- %s -->\n'
            '  <section class="planche">\n'
            '    <div class="grille">\n%s\n    </div>\n'
            '%s'
            '    <div class="note">Linkee — flyer A6 réduit à %d %%. '
            'Couper sur les repères. Page 1 : recto — page 2 : verso. '
            'Impression recto-verso, reliure sur le grand côté.</div>\n'
            '  </section>\n'
        ) % (titre, cellules, reperes(), int(ECHELLE * 100))

    def reperes():
        """Traits de coupe, traces dans la marge : ils ne touchent pas le dessin."""
        out = []
        for i in range(3):
            x = marge_x + cell_l * i
            out.append('    <i class="repere v" style="left:%.2fmm; top:%.2fmm;"></i>'
                       % (x, marge_y - 6))
            out.append('    <i class="repere v" style="left:%.2fmm; top:%.2fmm;"></i>'
                       % (x, marge_y + cell_h * 2 + 1))
        for i in range(3):
            y = marge_y + cell_h * i
            out.append('    <i class="repere h" style="top:%.2fmm; left:%.2fmm;"></i>'
                       % (y, marge_x - 6))
            out.append('    <i class="repere h" style="top:%.2fmm; left:%.2fmm;"></i>'
                       % (y, marge_x + cell_l * 2 + 1))
        return '\n'.join(out) + '\n'

    doc = u"""<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Linkee — Planche A4, 4 flyers</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;600;700;800&family=Instrument+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ============================================================
   FICHIER GENERE — ne pas modifier a la main.
   Source : docs/Flyer_Bars_Linkee.html
   Regenerer : python docs/generate_planche.py
   ============================================================ */
%(css)s

/* ── imposition ──────────────────────────────────────────── */
@page{ size:A4 portrait; margin:0; }
html,body{ background:#FFFFFF; }
.planche{ position:relative; width:210mm; height:297mm; overflow:hidden;
          background:#FFFFFF; page-break-after:always; break-after:page; }
.planche:last-child{ page-break-after:auto; }

.grille{ position:absolute; left:%(mx).2fmm; top:%(my).2fmm;
         display:grid; grid-template-columns:repeat(2, %(cl).2fmm);
         grid-template-rows:repeat(2, %(ch).2fmm); }

.cellule{ width:%(cl).2fmm; height:%(ch).2fmm; overflow:hidden; }
.zoom{ transform:scale(%(ech)s); transform-origin:top left; }
/* La fenetre est au format de coupe : le fond perdu du flyer tombe
   dehors, exactement comme sous le massicot de l'imprimeur. */
.fenetre{ width:%(coupe_l)dmm; height:%(coupe_h)dmm; overflow:hidden;
          position:relative; }
.fenetre > .page{ position:absolute; left:-%(fp)dmm; top:-%(fp)dmm;
                  page-break-after:auto !important; break-after:auto !important; }

.repere{ position:absolute; background:#6E6860; display:block; }
.repere.v{ width:.2mm; height:5mm; }
.repere.h{ height:.2mm; width:5mm; }

.note{ position:absolute; left:0; right:0; bottom:3.5mm; text-align:center;
       font-family:'Instrument Sans',sans-serif; font-size:5.6pt; color:#67626D;
       letter-spacing:.04em; }
</style>
</head>
<body>

%(recto)s
%(verso)s
</body>
</html>
""" % {
        'css': css.strip(),
        'mx': marge_x, 'my': marge_y, 'cl': cell_l, 'ch': cell_h,
        'ech': ECHELLE, 'coupe_l': COUPE_L, 'coupe_h': COUPE_H, 'fp': FOND_PERDU,
        'recto': planche(sections[0], 'PAGE 1 — RECTO x4'),
        'verso': planche(sections[1], 'PAGE 2 — VERSO x4'),
    }

    with io.open(OUT, 'w', encoding='utf-8') as f:
        f.write(doc)
    print('%s  ->  2 pages A4, 4 flyers par page, %d x %d mm apres coupe'
          % (OUT, round(COUPE_L * ECHELLE), round(COUPE_H * ECHELLE)))


if __name__ == '__main__':
    if not os.path.isfile(SRC):
        raise SystemExit('Lancer le script depuis la racine du projet.')
    main()
