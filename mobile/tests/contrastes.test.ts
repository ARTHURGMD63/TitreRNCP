/**
 * Les contrastes du thème (WCAG 2.1, 4,5:1 pour le texte courant) : ces tests
 * empêchent qu'un jeton de texte secondaire retombe sous le seuil, comme le
 * rose de Sport (3,8:1) et le lilas de Culture (4,1:1) avant le 2026-11-18.
 *
 * Lancement : npm test (lanceur intégré à Node, aucune dépendance).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { couleurs, fixe, teintes, type Mode, type Rubrique } from '../src/theme.ts';

function luminance(hex: string): number {
  const v = hex.replace('#', '');
  const canal = (i: number) => {
    const x = parseInt(v.slice(i, i + 2), 16) / 255;
    return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * canal(0) + 0.7152 * canal(2) + 0.0722 * canal(4);
}

function rapport(a: string, b: string): number {
  const [x, y] = [luminance(a), luminance(b)].sort((m, n) => n - m);
  return (x + 0.05) / (y + 0.05);
}

/** Une couleur rgba(r,g,b,a) posée sur un fond opaque. */
function poser(rgba: string, fond: string): string {
  const m = /rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/.exec(rgba)!;
  const a = Number(m[4]);
  const f = fond.replace('#', '');
  return '#' + [1, 2, 3].map((i, k) => {
    const v = Math.round(Number(m[i]) * a + parseInt(f.slice(k * 2, k * 2 + 2), 16) * (1 - a));
    return v.toString(16).padStart(2, '0');
  }).join('');
}

const MODES: Mode[] = ['clair', 'sombre'];
const RUBRIQUES: Rubrique[] = ['bleu', 'sport', 'culture'];
const AA = 4.5;

test('texte secondaire (gris, gris foncé) sur chaque surface, dans les deux thèmes', () => {
  for (const mode of MODES) {
    const c = couleurs[mode];
    for (const surface of [c.bg, c.blanc, c.surface2]) {
      for (const encre of [c.gris, c.grisFonce]) {
        assert.ok(rapport(encre, surface) >= AA, `${mode} : ${encre} sur ${surface} = ${rapport(encre, surface).toFixed(2)}`);
      }
    }
  }
});

test('texte blanc et texte secondaire sur l\'aplat de chaque rubrique', () => {
  for (const mode of MODES) {
    for (const r of RUBRIQUES) {
      const t = teintes(r, mode);
      assert.ok(rapport(fixe.surAccent, t.accent) >= AA, `${r} : blanc sur ${t.accent}`);
      assert.ok(rapport(t.surAccent2, t.accent) >= AA, `${r} ${mode} : ${t.surAccent2} sur ${t.accent} = ${rapport(t.surAccent2, t.accent).toFixed(2)}`);
      assert.ok(rapport(fixe.surAccent, t.accentDeep) >= AA, `${r} : blanc sur ${t.accentDeep}`);
    }
  }
});

test('pastille voilée sur un aplat de rubrique : le texte blanc reste lisible', () => {
  for (const r of RUBRIQUES) {
    const accent = teintes(r, 'clair').accent;
    const fond = poser(fixe.voileAccent, accent);
    assert.ok(rapport(fixe.surAccent, fond) >= AA, `${r} : blanc sur voile = ${rapport(fixe.surAccent, fond).toFixed(2)}`);
  }
});

test('pastille douce : texte de la rubrique sur son fond doux', () => {
  for (const mode of MODES) {
    for (const r of RUBRIQUES) {
      const t = teintes(r, mode);
      assert.ok(rapport(t.texteDoux, t.doux) >= AA, `${r} ${mode} : ${t.texteDoux} sur ${t.doux} = ${rapport(t.texteDoux, t.doux).toFixed(2)}`);
    }
  }
});
