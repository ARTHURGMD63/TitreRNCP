# -*- coding: utf-8 -*-
"""Genere la charte graphique StudentLink a partir des tokens reels de style.css."""
import io, re, os, datetime

CSS = 'assets/css/style.css'
OUT = 'docs/Charte_Graphique_StudentLink.html'

src = io.open(CSS, encoding='utf-8').read()

def block(sel):
    m = re.search(re.escape(sel) + r'\s*\{(.*?)\n\}', src, re.S)
    return m.group(1) if m else ''

def tokens(txt):
    d = {}
    for m in re.finditer(r'(--[a-z0-9-]+)\s*:\s*([^;]+);(?:\s*/\*(.*?)\*/)?', txt):
        d[m.group(1)] = (m.group(2).strip(), (m.group(3) or '').strip())
    return d

T = tokens(block(':root'))
D = tokens(block('[data-theme="dark"]'))

# ---------- contraste ----------
def lum(h):
    h = h.lstrip('#')
    r, g, b = (int(h[i:i+2], 16) / 255 for i in (0, 2, 4))
    f = lambda c: c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b)

def cr(a, b):
    l1, l2 = lum(a), lum(b)
    if l1 < l2:
        l1, l2 = l2, l1
    return (l1 + 0.05) / (l2 + 0.05)

def verdict(ratio, big=False):
    need = 3.0 if big else 4.5
    if ratio >= 7:      return ('AAA', 'ok')
    if ratio >= need:   return ('AA', 'ok')
    if ratio >= 3 and big: return ('AA', 'ok')
    return ('Echec', 'ko')

def v(name):
    return T[name][0]

def note(name, fallback=''):
    return T[name][1] or fallback

# ---------- groupes de couleurs ----------
MARQUE = [
    ('--rouge',       'Coral heros',      "Action principale, signature de marque, evenement flash"),
    ('--rouge-deep',  'Coral profond',    "Texte sur teinte claire, etat presse"),
    ('--bleu',        'Indigo ancre',     "Ancre froide : liens, focus, categorie Bar, avatars"),
    ('--bleu-deep',   'Indigo profond',   "Texte sur teinte claire, degrade de carte"),
    ('--lime',        'Or chaud',         "Accent tertiaire, points actifs, mise en avant"),
    ('--lime-deep',   'Or profond',       "Texte or sur fond clair (le --lime pur ne passe pas)"),
    ('--orange',      'Orange pont',      "Categorie Resto, liaison coral/or"),
    ('--orange-deep', 'Orange profond',   "Texte sur teinte claire"),
]
ENCRE = [
    ('--bg',         'Papier chaud',    "Fond d'application"),
    ('--blanc',      'Surface',         "Cartes, champs, surfaces principales"),
    ('--surface-2',  'Surface elevee',  "Survol de ligne, badges neutres"),
    ('--noir',       'Encre',           "Texte principal, boutons primaires"),
    ('--gris-fonce', 'Encre secondaire',"Texte secondaire, descriptions"),
    ('--gris',       'Encre tertiaire', "Legendes, placeholders, labels"),
    ('--gris-clair', 'Filet fin',       "Bordures de carte, separateurs"),
    ('--line-2',     'Filet marque',    "Bordures de champ, contours de pilules"),
]
TEINTES = [
    ('--rouge-clair',  '--rouge-deep',  'Badge Boite, badge Flash'),
    ('--bleu-clair',   '--bleu',        'Halo de focus, badge Bar'),
    ('--lime-clair',   '--lime-deep',   'Badge Afterwork'),
    ('--orange-clair', '--orange-deep', 'Badge Resto'),
]

SENS = [
    ('--succes', 'Succes', "Message de reussite, statut actif, avis possible"),
    ('--danger', 'Danger', "Erreur de formulaire, action destructrice"),
    ('--alerte', 'Alerte', "Avertissement, etat a surveiller"),
]

# ---------- contrastes mesures ----------
PAIRS = [
    ("Texte principal sur papier",        v('--noir'),       v('--bg'),      False),
    ("Texte principal sur surface",       v('--noir'),       v('--blanc'),   False),
    ("Texte secondaire sur papier",       v('--gris-fonce'), v('--bg'),      False),
    ("Texte tertiaire sur papier",        v('--gris'),       v('--bg'),      False),
    ("Texte tertiaire sur surface",       v('--gris'),       v('--blanc'),   False),
    ("Blanc sur coral (bouton)",          '#FFFFFF',         v('--rouge-deep'), False),
    ("Blanc sur indigo (bouton)",         '#FFFFFF',         v('--bleu'),    False),
    ("Encre sur or (bouton)",             v('--noir'),       v('--lime'),    False),
    ("Badge Bar : indigo / teinte",       v('--bleu-deep'),  v('--bleu-clair'),   False),
    ("Badge Boite : coral / teinte",      v('--rouge-deep'), v('--rouge-clair'),  False),
    ("Badge Resto : orange / teinte",     v('--orange-deep'),v('--orange-clair'), False),
    ("Badge Afterwork : or / teinte",     v('--lime-deep'),  v('--lime-clair'),   False),
    ("Or profond sur papier (.text-lime)",v('--lime-deep'),  v('--bg'),      False),
    ("Succes : texte sur teinte", v('--succes'), v('--succes-clair'), False),
    ("Danger : texte sur teinte", v('--danger'), v('--danger-clair'), False),
    ("Alerte : texte sur teinte", v('--alerte'), v('--alerte-clair'), False),
]
DARK_PAIRS = [
    ("Texte principal sur fond sombre",   D['--noir'][0],       D['--bg'][0],     False),
    ("Texte secondaire sur surface",      D['--gris-fonce'][0], D['--blanc'][0],  False),
    ("Texte tertiaire sur surface",       D['--gris'][0],       D['--blanc'][0],  False),
    ("Badge Bar : indigo / teinte sombre",D['--sur-bleu-clair'][0],  D['--bleu-clair'][0], False),
    ("Badge Boite : coral / teinte sombre",D['--sur-rouge-clair'][0], D['--rouge-clair'][0],False),
    ("Badge Afterwork : or / teinte sombre",D['--sur-lime-clair'][0], D['--lime-clair'][0], False),
]

# ---------- echelle typo ----------
SCALE = []
for i in range(1, 11):
    key = '--fs-%d' % i
    val, cm = T[key]
    px = round(float(val.replace('rem', '')) * 16)
    SCALE.append((key, val, px, cm.split('—')[-1].strip() if '—' in cm else cm))

ROLES = [
    ('.t-hero',       'display', '--fs-9',  900, "Titre d'accueil, ecran d'onboarding"),
    ('.t-title',      'display', '--fs-8',  900, "Titre d'ecran, chiffre cle"),
    ('.t-section',    'display', '--fs-7',  800, "Titre de section"),
    ('.t-card-title', 'display', '--fs-6',  900, "Titre de carte, nom d'evenement"),
    ('.t-metric',     'display', '--fs-8',  900, "Compteur, montant, pourcentage"),
    ('.t-body-lg',    'sans',    '--fs-5',  400, "Paragraphe long, CGU, description"),
    ('.t-body',       'sans',    '--fs-4',  400, "Texte courant, libelle de bouton"),
    ('.t-caption',    'sans',    '--fs-3',  500, "Texte d'appui, sous-titre"),
    ('.t-meta',       'sans',    '--fs-2',  600, "Lieu, date, compteur de liste"),
    ('.t-overline',   'sans',    '--fs-1',  700, "Label capitales, badge, en-tete"),
]

WEIGHTS = [('--fw-regular', 400, 'sans', 'Corps de texte'),
           ('--fw-medium', 500, 'sans', "Texte d'appui"),
           ('--fw-semibold', 600, 'sans', 'Meta, accent leger'),
           ('--fw-bold', 700, 'sans', 'Boutons, labels, emphase — plafond de la sans'),
           ('--fw-display', 800, 'display', 'Titres de section, italique de marque'),
           ('--fw-black', 900, 'display', 'Titres, chiffres cles')]

LH = [('--lh-display', 'Titres XXL'), ('--lh-tight', 'Titres'),
      ('--lh-snug', 'Sous-titres, meta'), ('--lh-normal', 'Corps'),
      ('--lh-relaxed', 'Texte long')]
LS = [('--ls-display', 'Titres XXL'), ('--ls-tight', 'Titres'),
      ('--ls-normal', 'Corps'), ('--ls-wide', 'Petites capitales'),
      ('--ls-label', 'Labels capitales')]

TODAY = datetime.date.today().strftime('%d/%m/%Y')


def sens_rows():
    out = []
    for name, titre, usage in SENS:
        hexv = v(name); tinte = v(name + '-clair')
        out.append(
            '<tr><td class="sw"><span class="chip" style="background:%s"></span></td>'
            '<td><b>%s</b><br><code>%s</code></td>'
            '<td class="mono">%s</td><td class="usage">%s</td>'
            '<td class="mono ratio">%.2f</td></tr>'
            % (hexv, titre, name, hexv.upper(), usage, cr(hexv, tinte)))
    return chr(10).join(out)


def swatch_rows(rows):
    out = []
    for name, title, usage in rows:
        hexv = v(name)
        ratio_paper = cr(hexv, v('--bg'))
        out.append(
            '<tr><td class="sw"><span class="chip" style="background:%s"></span></td>'
            '<td><b>%s</b><br><code>%s</code></td>'
            '<td class="mono">%s</td>'
            '<td class="usage">%s</td>'
            '<td class="mono ratio">%.2f</td></tr>'
            % (hexv, title, name, hexv.upper(), usage, ratio_paper))
    return '\n'.join(out)


def pair_rows(pairs):
    out = []
    for label, fg, bg, big in pairs:
        r = cr(fg, bg)
        lab, cls = verdict(r, big)
        out.append(
            '<tr><td><span class="demo" style="background:%s;color:%s">Aa</span></td>'
            '<td>%s</td><td class="mono">%s <span class="on">sur</span> %s</td>'
            '<td class="mono ratio">%.2f</td>'
            '<td><span class="tag %s">%s</span></td></tr>'
            % (bg, fg, label, fg.upper(), bg.upper(), r, cls, lab))
    return '\n'.join(out)


def scale_rows():
    out = []
    for key, val, px, cm in SCALE:
        out.append(
            '<tr><td class="mono">%s</td><td class="mono">%s</td><td class="mono">%dpx</td>'
            '<td class="usage">%s</td>'
            '<td class="spec" style="font-size:%s;white-space:nowrap">Clermont</td></tr>'
            % (key, val, px, cm, val))
    return '\n'.join(out)


def role_rows():
    out = []
    for cls, fam, fs, fw, usage in ROLES:
        famcss = "var(--font-display)" if fam == 'display' else "var(--font-sans)"
        px = round(float(T[fs][0].replace('rem', '')) * 16)
        out.append(
            '<tr><td class="mono"><b>%s</b></td>'
            '<td class="mono small">%s<br>%s / %d</td>'
            '<td class="usage">%s</td>'
            '<td class="spec" style="font-family:%s;font-size:%s;font-weight:%d;line-height:1.1">Le Baron, ce soir</td></tr>'
            % (cls, 'serif' if fam == 'display' else 'sans', fs, fw, usage, famcss, T[fs][0], fw))
    return '\n'.join(out)


HTML = u"""<!doctype html>
<html lang="fr"><head><meta charset="utf-8">
<title>Charte graphique - StudentLink</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700..900;1,700..900&family=DM+Sans:opsz,wght@9..40,400..700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg:__BG__; --blanc:__BLANC__; --surface2:__SURFACE2__; --noir:__NOIR__;
  --gris-fonce:__GRISF__; --gris:__GRIS__; --gris-clair:__GRISC__; --line2:__LINE2__;
  --rouge:__ROUGE__; --bleu:__BLEU__; --lime:__LIME__; --orange:__ORANGE__;
  --font-display:'Playfair Display',Georgia,serif;
  --font-sans:'DM Sans',system-ui,sans-serif;
  --mono:'JetBrains Mono',ui-monospace,Menlo,monospace;
}
*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{font-family:var(--font-sans);color:var(--noir);background:var(--blanc);font-size:9.5pt;line-height:1.5}
@page{size:A4;margin:0}
.page{page-break-after:always;min-height:297mm;position:relative;padding:13mm 15mm 16mm}
.page.cover-page{padding:0}
.page:last-child{page-break-after:auto}

/* --- couverture --- */
.cover{background:var(--noir);color:var(--bg);padding:34mm 20mm 24mm;min-height:297mm;display:flex;flex-direction:column;justify-content:space-between}
.cover h1{font-family:var(--font-display);font-weight:900;font-size:52pt;line-height:.95;letter-spacing:-.02em}
.cover h1 em{font-style:italic;color:var(--rouge);display:block}
.cover .kicker{font-size:8pt;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:__RGBA_BG_55__;margin-bottom:10mm}
.cover .lede{font-size:11pt;line-height:1.55;max-width:105mm;color:__RGBA_BG_80__;margin-top:9mm}
.cover .meta{display:flex;gap:14mm;font-size:8pt;letter-spacing:.1em;text-transform:uppercase;color:__RGBA_BG_55__;border-top:1px solid __RGBA_BG_20__;padding-top:6mm}
.cover .bar{display:flex;height:7mm;margin-top:11mm;border-radius:99px;overflow:hidden}
.cover .bar span{flex:1}

/* --- structure --- */
.ph{display:flex;align-items:baseline;justify-content:space-between;border-bottom:2px solid var(--noir);padding-bottom:3mm;margin-bottom:7mm}
.ph h2{font-family:var(--font-display);font-weight:900;font-size:21pt;letter-spacing:-.015em;line-height:1}
.ph .num{font-size:8pt;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--gris)}
.intro{font-size:9.5pt;color:var(--gris-fonce);max-width:150mm;margin-bottom:7mm;line-height:1.6}
h3{font-family:var(--font-display);font-weight:800;font-size:13pt;margin:7mm 0 2.5mm;letter-spacing:-.01em}
h3:first-of-type{margin-top:0}

table{width:100%;border-collapse:collapse;margin-bottom:4mm}
th{text-align:left;font-size:7pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gris);padding:0 3mm 2.5mm 0;border-bottom:1px solid var(--line2)}
td{padding:2.25mm 3mm 2.25mm 0;border-bottom:1px solid var(--gris-clair);vertical-align:middle;font-size:9pt}
.mono{font-family:var(--mono);font-size:7.8pt;letter-spacing:-.01em}
td.mono{white-space:nowrap}
.mono.small{font-size:7pt;color:var(--gris-fonce);line-height:1.35}
.ratio{text-align:right;font-weight:600;white-space:nowrap}
.usage{color:var(--gris-fonce);font-size:8.4pt;line-height:1.4}
code{font-family:var(--mono);font-size:7.4pt;color:var(--gris-fonce)}
.sw{width:15mm}
.chip{display:block;width:13mm;height:9mm;border-radius:3mm;border:1px solid __RGBA_NOIR_12__}
.demo{display:inline-flex;align-items:center;justify-content:center;width:11mm;height:8mm;border-radius:2mm;font-family:var(--font-display);font-weight:900;font-size:11pt;border:1px solid __RGBA_NOIR_12__}
.on{color:var(--gris);font-weight:400}
.tag{display:inline-block;font-size:7pt;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:1mm 2.5mm;border-radius:99px;white-space:nowrap}
.tag.ok{background:#E3F2E6;color:#1F6B34}
.tag.ko{background:__ROUGE_CLAIR__;color:__ROUGE_DEEP__}
.spec{line-height:1.1}

.tint{display:grid;grid-template-columns:repeat(4,1fr);gap:4mm;margin-bottom:5mm}
.tint .t{border-radius:4mm;padding:5mm;border:1px solid var(--gris-clair)}
.tint .t .lbl{font-size:7pt;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin-bottom:2mm}
.tint .t .hex{font-family:var(--mono);font-size:7pt;opacity:.75}
.tint .t .use{font-size:7.6pt;margin-top:2mm;line-height:1.35}

.dark{background:__DBG__;border-radius:5mm;padding:5mm 6mm;margin-bottom:4mm}
.dark h3{color:__DNOIR__;margin-top:0}
.dark table td{border-bottom-color:__DLINE__;color:__DNOIR__}
.dark table th{color:__DGRIS__;border-bottom-color:__DLINE__}
.dark .usage{color:__DGRISF__}
.dark code{color:__DGRISF__}

.families{display:grid;grid-template-columns:1fr 1fr;gap:6mm;margin-bottom:7mm}
.fam{border:1px solid var(--gris-clair);border-radius:4mm;padding:6mm}
.fam .name{font-size:7.5pt;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gris);margin-bottom:3mm}
.fam .glyphs{font-size:30pt;line-height:1.05;margin-bottom:3mm}
.fam .desc{font-size:8.4pt;color:var(--gris-fonce);line-height:1.5}
.fam .rule{font-size:8pt;margin-top:3mm;padding-top:3mm;border-top:1px solid var(--gris-clair);color:var(--noir)}

.rules{display:grid;grid-template-columns:1fr 1fr;gap:6mm}
.rules ul{list-style:none}
.rules li{font-size:8.6pt;line-height:1.5;padding:2.5mm 0 2.5mm 7mm;position:relative;border-bottom:1px solid var(--gris-clair)}
.rules li:before{position:absolute;left:0;top:2.5mm;font-weight:700;font-size:9pt}
.do li:before{content:"OUI";font-size:6.5pt;letter-spacing:.06em;color:#1F6B34;top:3.2mm}
.dont li:before{content:"NON";font-size:6.5pt;letter-spacing:.06em;color:__ROUGE_DEEP__;top:3.2mm}
.rules h3{margin-top:0}

.btns{display:flex;gap:4mm;flex-wrap:wrap;margin-bottom:5mm;align-items:center}
.btn{display:inline-flex;align-items:center;justify-content:center;font-family:var(--font-sans);font-weight:700;font-size:8.5pt;padding:3mm 6mm;border-radius:99px;border:1px solid transparent}
.badges{display:flex;gap:3mm;flex-wrap:wrap;margin-bottom:5mm}
.badge{font-size:7pt;font-weight:700;letter-spacing:.07em;text-transform:uppercase;padding:1.4mm 3mm;border-radius:99px}
.elev{display:grid;grid-template-columns:repeat(4,1fr);gap:5mm;margin:0 2mm 7mm;}
.elev .e{background:var(--blanc);border:1px solid var(--gris-clair);border-radius:4mm;padding:5mm;font-size:7.4pt;text-align:center}
.elev .e .n{font-family:var(--mono);font-size:7pt;margin-bottom:2mm;color:var(--gris-fonce)}
.radii{display:flex;gap:5mm;align-items:flex-end;margin-bottom:5mm}
.radii .r{background:var(--surface2);border:1px solid var(--line2);width:27mm;height:18mm;display:flex;align-items:flex-end;justify-content:center;padding-bottom:2mm;font-family:var(--mono);font-size:6.2pt;color:var(--gris-fonce);text-align:center;line-height:1.3}

.foot{position:absolute;bottom:8mm;left:15mm;right:15mm;display:flex;justify-content:space-between;font-size:7pt;letter-spacing:.12em;text-transform:uppercase;color:var(--gris);border-top:1px solid var(--gris-clair);padding-top:2.5mm}
.callout{background:var(--surface2);border-left:3px solid var(--rouge);border-radius:0 3mm 3mm 0;padding:4mm 5mm;font-size:8.4pt;line-height:1.55;color:var(--gris-fonce);margin-bottom:5mm}
.callout b{color:var(--noir)}
</style></head><body>

<!-- ============ COUVERTURE ============ -->
<div class="page cover-page"><div class="cover">
  <div>
    <div class="kicker">Systeme de design - Version 2.0</div>
    <h1>Charte<br>graphique<em>StudentLink</em></h1>
    <div class="lede">Reference unique des couleurs, de la typographie et des composants de l'application StudentLink. Toute valeur de ce document est extraite automatiquement de <code style="color:__RGBA_BG_80__">assets/css/style.css</code> : le document ne peut pas diverger du code.</div>
    <div class="bar">
      <span style="background:__ROUGE__"></span><span style="background:__ORANGE__"></span>
      <span style="background:__LIME__"></span><span style="background:__BLEU__"></span>
      <span style="background:__BG__"></span>
    </div>
  </div>
  <div class="meta"><div>Genere le __DATE__</div><div>Arthur Gramond</div><div>Clermont-Ferrand</div></div>
</div></div>

<!-- ============ PRINCIPES ============ -->
<div class="page">
  <div class="ph"><h2>Principes</h2><div class="num">01</div></div>
  <div class="intro">StudentLink met en relation des etudiants et des lieux de sortie. Le systeme vise une lecture rapide sur mobile, en soiree, dans de mauvaises conditions lumineuses : contraste eleve, hierarchie nette, surfaces calmes.</div>

  <h3>Les trois regles non negociables</h3>
  <table>
    <tr><td style="width:8mm" class="mono"><b>1</b></td><td><b>Une couleur = un sens.</b><span class="usage"> Le coral signe la marque et l'action principale. Il ne sert jamais a signaler une erreur ni a coder une categorie : ces roles ont leurs propres jetons.</span></td></tr>
    <tr><td class="mono"><b>2</b></td><td><b>La serif ne descend jamais dans l'interface.</b><span class="usage"> Playfair Display est reservee aux titres et aux chiffres cles. Tout le reste (boutons, labels, champs, meta) est en DM Sans, plafonnee a 700.</span></td></tr>
    <tr><td class="mono"><b>3</b></td><td><b>Aucune valeur hors jeton.</b><span class="usage"> Pas de <code>font-size:13.5px</code> ni de <code>#E5331A</code> dans une page. Dix crans de taille, six graisses, une palette. Ce qui n'est pas dans ce document n'existe pas.</span></td></tr>
  </table>

  <h3>Fondations</h3>
  <table>
    <tr><th>Fondation</th><th>Valeur</th><th>Consequence</th></tr>
    <tr><td>Base typographique</td><td class="mono">16px = 1rem</td><td class="usage">Les champs de saisie sont a 16px : en dessous, Safari iOS zoome automatiquement au focus.</td></tr>
    <tr><td>Largeur de coquille</td><td class="mono">440px mobile / 1120px desktop</td><td class="usage">L'application reste une colonne mobile jusqu'a 760px, puis passe en grille.</td></tr>
    <tr><td>Grille d'espacement</td><td class="mono">multiples de 4px</td><td class="usage">Les marges et gouttieres suivent 4 / 8 / 12 / 16 / 22 / 24px.</td></tr>
    <tr><td>Themes</td><td class="mono">clair + sombre</td><td class="usage">Bascule via <code>data-theme</code> sur <code>&lt;html&gt;</code>, memorisee en localStorage.</td></tr>
  </table>
  <div class="foot"><span>StudentLink - Charte graphique</span><span>01</span></div>
</div>

<!-- ============ COULEUR : MARQUE ============ -->
<div class="page">
  <div class="ph"><h2>Couleur - palette de marque</h2><div class="num">02</div></div>
  <div class="intro">Un trio analogue chaud (coral, orange, or) ancre par un indigo froid. Chaque teinte possede une variante <em>profonde</em> destinee au texte : la version pure ne passe pas le contraste sur fond clair.</div>
  <table>
    <tr><th colspan="2">Jeton</th><th>Hex</th><th>Role</th><th>Sur papier</th></tr>
    __MARQUE__
  </table>
  <div class="callout"><b>A retenir :</b> la colonne « sur papier » donne le ratio de contraste face au fond <code>--bg</code>. En dessous de 4,5 la couleur ne doit pas porter du texte courant : utiliser la variante <code>-deep</code>.</div>

  <h3>Teintes - fonds doux</h3>
  <div class="tint">__TINTS__</div>

  <div class="foot"><span>StudentLink - Charte graphique</span><span>02</span></div>
</div>

<div class="page">
  <div class="ph"><h2>Couleur - sens</h2><div class="num">02b</div></div>
  <h3>Couleurs de sens</h3>
  <div class="intro" style="margin-bottom:4mm">Hors palette de marque, volontairement. Le coral signe StudentLink : il ne doit jamais signifier « erreur », sinon un message d'echec porte exactement la couleur du bouton d'action principal.</div>
  <table>
    <tr><th colspan="2">Jeton</th><th>Hex</th><th>Role</th><th>Sur teinte</th></tr>
    __SENS__
  </table>

  <div class="callout"><b>Pourquoi les separer.</b> Avant cette refonte, <code>.toast.error</code>
  utilisait <code>--rouge</code> : un message d'erreur avait donc exactement la couleur du bouton
  d'action principal. Et deux « succes » coexistaient — un fond ambre dans les formulaires, un fond
  noir dans les notifications. Un sens, une couleur, partout.</div>

  <h3>Ou elles s'appliquent</h3>
  <table>
    <tr><th>Element</th><th>Avant</th><th>Maintenant</th></tr>
    <tr><td class="mono">.toast.error</td><td class="usage">coral de marque</td><td class="mono">--danger</td></tr>
    <tr><td class="mono">.toast.success</td><td class="usage">noir</td><td class="mono">--succes</td></tr>
    <tr><td class="mono">.form-error</td><td class="usage">teinte coral</td><td class="mono">--danger-clair</td></tr>
    <tr><td class="mono">.form-success</td><td class="usage">teinte ambre</td><td class="mono">--succes-clair</td></tr>
    <tr><td class="mono">statut « Actif »</td><td class="usage">#2e7d32 en dur</td><td class="mono">--succes</td></tr>
    <tr><td class="mono">pastille d'avis</td><td class="usage">--lime</td><td class="mono">--succes</td></tr>
  </table>
  <div class="foot"><span>StudentLink - Charte graphique</span><span>02b</span></div>
</div>

<!-- ============ COULEUR : ENCRE ============ -->
<div class="page">
  <div class="ph"><h2>Couleur - encre et surfaces</h2><div class="num">03</div></div>
  <div class="intro">La base neutre est chaude, jamais grise pure : le papier tire sur le beige et l'encre sur le brun. C'est ce qui donne a l'application son caractere editorial plutot que « dashboard ».</div>
  <table>
    <tr><th colspan="2">Jeton</th><th>Hex</th><th>Role</th><th>Sur papier</th></tr>
    __ENCRE__
  </table>

  <h3>Mode sombre</h3>
  <div class="dark">
    <table>
      <tr><th>Jeton</th><th>Clair</th><th>Sombre</th><th>Role</th></tr>
      __DARKROWS__
    </table>
  </div>
  <div class="foot"><span>StudentLink - Charte graphique</span><span>03</span></div>
</div>

<!-- ============ ACCESSIBILITE ============ -->
<div class="page">
  <div class="ph"><h2>Accessibilite - contrastes mesures</h2><div class="num">04</div></div>
  <div class="intro">Ratios calcules selon WCAG 2.1. Seuil retenu : <b>4,5:1</b> pour tout texte inferieur a 18,66px gras ou 24px normal — ce qui couvre la quasi-totalite de l'interface, badges et labels compris (11 a 14px).</div>

  <h3>Theme clair</h3>
  <table>
    <tr><th colspan="2">Combinaison</th><th>Couleurs</th><th>Ratio</th><th>Verdict</th></tr>
    __PAIRS__
  </table>

  <div class="foot"><span>StudentLink - Charte graphique</span><span>04</span></div>
</div>

<div class="page">
  <div class="ph"><h2>Accessibilite - theme sombre</h2><div class="num">04b</div></div>
  <div class="intro">Le theme sombre traite mieux le texte gris que le theme clair, mais degrade les badges : les teintes de fond sont assombries alors que les couleurs de texte restent celles du theme clair.</div>
  <table>
    <tr><th colspan="2">Combinaison</th><th>Couleurs</th><th>Ratio</th><th>Verdict</th></tr>
    __DPAIRS__
  </table>
  <div class="callout"><b>Points ouverts.</b> Les combinaisons marquees « Echec » concernent les badges de categorie et le texte tertiaire. Corrections proposees, sans modifier l'identite : assombrir <code>--gris</code> vers <code>#7A7365</code>, <code>--lime-deep</code> vers <code>#9A6B12</code>, et introduire des variantes de teinte propres au mode sombre pour <code>--bleu</code> et <code>--rouge</code>.</div>
  <div class="foot"><span>StudentLink - Charte graphique</span><span>04b</span></div>
</div>

<!-- ============ TYPO : FAMILLES ============ -->
<div class="page">
  <div class="ph"><h2>Typographie - les deux familles</h2><div class="num">05</div></div>
  <div class="intro">Deux familles, deux territoires stricts. Le contraste serif/sans porte toute la hierarchie : c'est ce qui permet a l'interface de rester sobre sans devenir plate.</div>
  <div class="families">
    <div class="fam">
      <div class="name">Titres - display</div>
      <div class="glyphs" style="font-family:var(--font-display);font-weight:900">Aa Bb Cc<br><span style="font-style:italic;color:var(--rouge)">0123456789</span></div>
      <div class="desc"><b>Playfair Display</b><br>Graisses chargees : 700, 800, 900 + italiques.<br>Serif didone a fort contraste, tres lisible en grand corps, signe la dimension editoriale de l'application.</div>
      <div class="rule"><b>Uniquement :</b> titres, noms d'evenements, chiffres cles, italique de marque.</div>
    </div>
    <div class="fam">
      <div class="name">Interface - sans</div>
      <div class="glyphs" style="font-family:var(--font-sans);font-weight:700">Aa Bb Cc<br><span style="color:var(--bleu)">0123456789</span></div>
      <div class="desc"><b>DM Sans</b><br>Graisses chargees : 400 a 700 (variable).<br>Grotesque geometrique a large hauteur d'x, concue pour les petits corps et les ecrans denses.</div>
      <div class="rule"><b>Tout le reste :</b> corps, boutons, labels, champs, meta, navigation.</div>
    </div>
  </div>
  <div class="callout"><b>Piege corrige.</b> DM Sans n'est chargee que jusqu'a 700. Toute demande de 800 ou 900 en sans declenchait un faux-gras synthetique du navigateur — plus lourd, plus flou, et different d'un appareil a l'autre. 37 occurrences ont ete ramenees a 700 ; les jetons <code>--fw-display</code> et <code>--fw-black</code> sont desormais reserves a la serif.</div>

  <h3>Chargement</h3>
  <table>
    <tr><th>Point</th><th>Valeur</th></tr>
    <tr><td>Source</td><td class="mono">fonts.googleapis.com (autorise par la CSP)</td></tr>
    <tr><td>Strategie</td><td class="mono">display=swap</td></tr>
    <tr><td>Axes</td><td class="mono">Playfair 700..900 + ital / DM Sans opsz 9..40, wght 400..700</td></tr>
    <tr><td>Repli</td><td class="mono">Georgia, Times New Roman, serif / system-ui, Segoe UI</td></tr>
  </table>
  <div class="foot"><span>StudentLink - Charte graphique</span><span>05</span></div>
</div>

<!-- ============ TYPO : ECHELLE ============ -->
<div class="page">
  <div class="ph"><h2>Typographie - l'echelle</h2><div class="num">06</div></div>
  <div class="intro">Dix crans, base 16px. C'est la liste complete : aucune taille en dehors de celle-ci n'est autorisee dans une page. L'application en comptait 37 avant refonte.</div>
  <table>
    <tr><th>Jeton</th><th>rem</th><th>px</th><th>Usage</th><th>Specimen</th></tr>
    __SCALE__
  </table>
  <div class="foot"><span>StudentLink - Charte graphique</span><span>06</span></div>
</div>

<!-- ============ TYPO : ROLES ============ -->
<div class="page">
  <div class="ph"><h2>Typographie - roles</h2><div class="num">07</div></div>
  <div class="intro">Chaque role est une classe utilitaire prete a l'emploi qui combine famille, taille, graisse, interligne et interlettrage. Une nouvelle page pioche ici plutot que de redeclarer une taille.</div>
  <table>
    <tr><th>Classe</th><th>Reglage</th><th>Usage</th><th>Specimen</th></tr>
    __ROLES__
  </table>
  <div class="callout"><b>Chiffres alignes.</b> Les roles porteurs de chiffres (<code>.t-metric</code>, montants, pourcentages, compteurs) activent <code>font-variant-numeric: tabular-nums</code> : les valeurs qui se rafraichissent en direct ne font plus sauter la mise en page.</div>
  <div class="foot"><span>StudentLink - Charte graphique</span><span>07</span></div>
</div>

<!-- ============ TYPO : REGLAGES ============ -->
<div class="page">
  <div class="ph"><h2>Typographie - graisses et rythme</h2><div class="num">08</div></div>
  <h3>Graisses</h3>
  <table>
    <tr><th>Jeton</th><th>Valeur</th><th>Famille</th><th>Usage</th><th>Specimen</th></tr>
    __WEIGHTS__
  </table>
  <h3>Interlignes</h3>
  <table>
    <tr><th>Jeton</th><th>Valeur</th><th>Usage</th></tr>
    __LH__
  </table>
  <h3>Interlettrages</h3>
  <table>
    <tr><th>Jeton</th><th>Valeur</th><th>Usage</th><th>Specimen</th></tr>
    __LS__
  </table>
  <div class="foot"><span>StudentLink - Charte graphique</span><span>08</span></div>
</div>

<!-- ============ COMPOSANTS ============ -->
<div class="page">
  <div class="ph"><h2>Composants</h2><div class="num">09</div></div>
  <h3>Boutons</h3>
  <div class="btns">
    <span class="btn" style="background:__NOIR__;color:__BLANC__">Bouton primaire</span>
    <span class="btn" style="background:__ROUGE__;color:#fff">Action heros</span>
    <span class="btn" style="background:__BLEU__;color:#fff">Action secondaire</span>
    <span class="btn" style="background:__LIME__;color:__NOIR__">Accent</span>
    <span class="btn" style="background:__BLANC__;color:__NOIR__;border-color:__LINE2__">Contour</span>
  </div>
  <div class="intro" style="margin-bottom:6mm">Rayon <code>--radius-pill</code>, graisse 700, taille <code>--fs-4</code>. Un seul bouton plein coral par ecran : c'est le geste principal.</div>

  <h3>Badges de categorie</h3>
  <div class="badges">
    <span class="badge" style="background:__BLEU_CLAIR__;color:__BLEU__">Bar</span>
    <span class="badge" style="background:__ROUGE_CLAIR__;color:__ROUGE__">Boite</span>
    <span class="badge" style="background:__ORANGE_CLAIR__;color:__ORANGE_DEEP__">Resto</span>
    <span class="badge" style="background:__LIME_CLAIR__;color:__LIME_DEEP__">Afterwork</span>
  </div>
  <div class="intro" style="margin-bottom:6mm">Taille <code>--fs-1</code>, capitales, interlettrage <code>--ls-wide</code>. Ces badges codent <b>uniquement</b> le type de lieu — un badge « Gratuit » doit avoir sa propre forme, sinon la couleur ment.</div>

  <h3>Elevation</h3>
  <div class="elev">
    <div class="e" style="box-shadow:0 1px 2px __RGBA_NOIR_05__"><div class="n">--shadow-xs</div>Tableaux, cartes de stats</div>
    <div class="e" style="box-shadow:0 1px 2px __RGBA_NOIR_05__,0 3px 8px __RGBA_NOIR_05__"><div class="n">--shadow-sm</div>Cartes standard</div>
    <div class="e" style="box-shadow:0 2px 4px __RGBA_NOIR_04__,0 10px 24px __RGBA_NOIR_08__"><div class="n">--shadow</div>Cartes evenement</div>
    <div class="e" style="box-shadow:0 6px 16px __RGBA_NOIR_07__,0 22px 48px __RGBA_NOIR_12__"><div class="n">--shadow-lg</div>Nav flottante, toasts</div>
  </div>

  <h3>Rayons</h3>
  <div class="radii">
    <div class="r" style="border-radius:12px">--radius-sm<br>12px</div>
    <div class="r" style="border-radius:18px">--radius<br>18px</div>
    <div class="r" style="border-radius:24px">--radius-lg<br>24px</div>
    <div class="r" style="border-radius:30px">--radius-xl<br>30px</div>
    <div class="r" style="border-radius:99px">--radius-pill</div>
  </div>
  <div class="foot"><span>StudentLink - Charte graphique</span><span>09</span></div>
</div>

<!-- ============ USAGE ============ -->
<div class="page">
  <div class="ph"><h2>Regles d'usage</h2><div class="num">10</div></div>
  <div class="rules">
    <div class="do">
      <h3>A faire</h3>
      <ul>
        <li>Piocher une taille parmi les dix crans <code>--fs-*</code>.</li>
        <li>Passer par les classes de role <code>.t-*</code> pour tout nouveau texte.</li>
        <li>Reserver Playfair aux titres et aux chiffres cles.</li>
        <li>Utiliser les variantes <code>-deep</code> des que la couleur porte du texte.</li>
        <li>Mettre les champs de saisie a <code>--fs-5</code> (16px) pour eviter le zoom iOS.</li>
        <li>Verifier chaque nouvelle paire couleur/fond a 4,5:1 minimum.</li>
        <li>Definir toute couleur via un jeton, y compris en style en ligne.</li>
      </ul>
    </div>
    <div class="dont">
      <h3>A ne pas faire</h3>
      <ul>
        <li>Ecrire une valeur hexadecimale dans une page PHP.</li>
        <li>Demander 800 ou 900 sur DM Sans : le faux-gras est garanti.</li>
        <li>Reutiliser un badge de categorie pour dire autre chose que la categorie.</li>
        <li>Employer le coral pour une erreur : c'est la couleur de la marque.</li>
        <li>Inventer une taille intermediaire (13,5px, 10,5px, 2,6rem...).</li>
        <li>Poser un fond ou un texte en dur qui casse le mode sombre.</li>
        <li>Melanger bordure dure et ombre douce sur un meme composant.</li>
      </ul>
    </div>
  </div>

  <h3>Reprise en main</h3>
  <table>
    <tr><th>Besoin</th><th>Ou regarder</th></tr>
    <tr><td>Ajouter une couleur</td><td class="mono">assets/css/style.css - bloc :root, puis ce document</td></tr>
    <tr><td>Styler un nouveau texte</td><td class="mono">classes .t-hero / .t-title / .t-body / .t-meta / .t-overline</td></tr>
    <tr><td>Verifier un contraste</td><td class="mono">page 04 de ce document</td></tr>
    <tr><td>Regenerer cette charte</td><td class="mono">le document se reconstruit depuis style.css</td></tr>
  </table>
  <div class="foot"><span>StudentLink - Charte graphique - __DATE__</span><span>10</span></div>
</div>

</body></html>"""

# ---------- teintes ----------
tint_html = []
for bgk, fgk, usage in TEINTES:
    tint_html.append(
        '<div class="t" style="background:%s;color:%s">'
        '<div class="lbl">%s</div><div class="hex">%s</div>'
        '<div class="use">%s</div></div>'
        % (v(bgk), v(fgk), bgk.replace('--', ''), v(bgk).upper(), usage))

dark_rows = []
for k in ['--bg', '--blanc', '--noir', '--gris-fonce', '--gris', '--gris-clair']:
    dark_rows.append(
        '<tr><td class="mono">%s</td><td class="mono">%s</td><td class="mono">%s</td>'
        '<td class="usage">%s</td></tr>'
        % (k, v(k).upper(), D[k][0].upper(), note(k, '-')))

w_rows = []
for key, val, fam, usage in WEIGHTS:
    famcss = 'var(--font-display)' if fam == 'display' else 'var(--font-sans)'
    w_rows.append('<tr><td class="mono">%s</td><td class="mono">%d</td><td>%s</td>'
                  '<td class="usage">%s</td>'
                  '<td class="spec" style="font-family:%s;font-weight:%d;font-size:13pt">Clermont</td></tr>'
                  % (key, val, 'serif' if fam == 'display' else 'sans', usage, famcss, val))

lh_rows = ['<tr><td class="mono">%s</td><td class="mono">%s</td><td class="usage">%s</td></tr>'
           % (k, v(k), u) for k, u in LH]
ls_rows = ['<tr><td class="mono">%s</td><td class="mono">%s</td><td class="usage">%s</td>'
           '<td class="spec" style="letter-spacing:%s;font-size:10pt;font-weight:600">SOIREE ETUDIANTE</td></tr>'
           % (k, v(k), u, v(k)) for k, u in LS]

def rgba(hexv, a):
    h = hexv.lstrip('#')
    return 'rgba(%d,%d,%d,%s)' % (int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16), a)

REPL = {
    '__BG__': v('--bg'), '__BLANC__': v('--blanc'), '__SURFACE2__': v('--surface-2'),
    '__NOIR__': v('--noir'), '__GRISF__': v('--gris-fonce'), '__GRIS__': v('--gris'),
    '__GRISC__': v('--gris-clair'), '__LINE2__': v('--line-2'),
    '__ROUGE__': v('--rouge'), '__BLEU__': v('--bleu'), '__LIME__': v('--lime'),
    '__ORANGE__': v('--orange'),
    '__ROUGE_CLAIR__': v('--rouge-clair'), '__ROUGE_DEEP__': v('--rouge-deep'),
    '__BLEU_CLAIR__': v('--bleu-clair'), '__LIME_CLAIR__': v('--lime-clair'),
    '__LIME_DEEP__': v('--lime-deep'), '__ORANGE_CLAIR__': v('--orange-clair'),
    '__ORANGE_DEEP__': v('--orange-deep'),
    '__DBG__': D['--bg'][0], '__DNOIR__': D['--noir'][0], '__DLINE__': D['--gris-clair'][0],
    '__DGRIS__': D['--gris'][0], '__DGRISF__': D['--gris-fonce'][0],
    '__RGBA_BG_55__': rgba(v('--bg'), '.55'), '__RGBA_BG_80__': rgba(v('--bg'), '.8'),
    '__RGBA_BG_20__': rgba(v('--bg'), '.2'),
    '__RGBA_NOIR_12__': rgba(v('--noir'), '.12'), '__RGBA_NOIR_05__': rgba(v('--noir'), '.05'),
    '__RGBA_NOIR_04__': rgba(v('--noir'), '.04'), '__RGBA_NOIR_08__': rgba(v('--noir'), '.08'),
    '__RGBA_NOIR_07__': rgba(v('--noir'), '.07'),
    '__DATE__': TODAY,
    '__MARQUE__': swatch_rows(MARQUE), '__ENCRE__': swatch_rows(ENCRE),
    '__TINTS__': '\n'.join(tint_html), '__SENS__': sens_rows(), '__DARKROWS__': '\n'.join(dark_rows),
    '__PAIRS__': pair_rows(PAIRS), '__DPAIRS__': pair_rows(DARK_PAIRS),
    '__SCALE__': scale_rows(), '__ROLES__': role_rows(),
    '__WEIGHTS__': '\n'.join(w_rows), '__LH__': '\n'.join(lh_rows), '__LS__': '\n'.join(ls_rows),
}
out = HTML
for k, val in REPL.items():
    out = out.replace(k, val)

if not os.path.isdir('docs'):
    os.makedirs('docs')
io.open(OUT, 'w', encoding='utf-8').write(out)
left = re.findall(r'__[A-Z0-9_]+__', out)
print('ecrit:', OUT, '| placeholders non remplaces:', sorted(set(left)) or 'aucun')
