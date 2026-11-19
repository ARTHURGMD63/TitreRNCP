/**
 * Le billet PDF d'un pass : la page HTML qu'expo-print transforme en PDF
 * (billetPdf.ts). Ce fichier ne touche ni à React Native ni au réseau : il
 * reçoit le pass, le titulaire et le QR déjà dessiné, et rend une chaîne.
 * C'est ce qui permet de le tester sous Node (tests/billetModele.test.ts).
 *
 * La page suit la charte (docs/CHARTE_GRAPHIQUE_OUTSEE.md) : Onest, le logo
 * à la couleur de la rubrique (bleu pour une soirée, rouge Sport, violet
 * Culture), le bandeau pleine couleur du Pass de l'application, le QR
 * toujours sur fond blanc. Thème clair seulement : un billet s'imprime.
 *
 * Aucune donnée n'est inventée : une ligne dont le champ est vide (école,
 * promo, lieu) n'est pas écrite.
 */

import { dateFr } from './format.ts';
import { formatEuros, prixEvenement } from './regles.ts';
import { fixe, teintes, type Rubrique } from './theme.ts';

export type GenreBillet = 'soiree' | 'sport' | 'culture' | 'partie';

export type PassBillet = {
  genre?: GenreBillet;
  id: number;
  titre: string;
  etablissement: string;
  lieu: string;
  date_heure: string | null;
  reduction: number | null;
  prix_normal: number | null;
  is_gratuit?: boolean;
  /** Le code du pass, déjà contrôlé : 64 caractères hexadécimaux. */
  code: string;
};

export type TitulaireBillet = {
  prenom: string;
  nom: string;
  email?: string | null;
  ecole?: string | null;
  promo?: string | null;
};

export type OptionsBillet = {
  pass: PassBillet;
  titulaire: TitulaireBillet;
  /** Le QR en SVG (qrSvg), encodant « outsee:<code> ». */
  qrSvg: string;
  /** La date de génération, écrite en pied de page. */
  genereLe: Date;
  /** Onest en base64 (TTF), par graisse ; à défaut, la police système. */
  polices?: Partial<Record<400 | 600 | 700, string>>;
};

const GENRES: Record<GenreBillet, { surtitre: string; rubrique: Rubrique }> = {
  soiree: { surtitre: 'Pass étudiant', rubrique: 'bleu' },
  sport: { surtitre: 'Pass sport', rubrique: 'sport' },
  culture: { surtitre: 'Pass culture', rubrique: 'culture' },
  partie: { surtitre: 'Partie de sport', rubrique: 'sport' },
};

/** Échappe un texte pour le poser dans du HTML. */
export function echapper(s: string | null | undefined): string {
  return (s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!);
}

/** « 3FA2 9C1B » : les huit premiers caractères du code, pour s'y retrouver à l'entrée. */
export function referenceBillet(code: string): string {
  const r = code.slice(0, 8).toUpperCase();
  return `${r.slice(0, 4)} ${r.slice(4)}`;
}

/** Le tarif écrit sur le billet, avec la même règle que la carte du Pass. */
export function tarifBillet(p: PassBillet): string {
  const genre = p.genre ?? 'soiree';
  if (genre === 'partie') return 'Réglé sur place au club';
  if (genre === 'culture') return (p.prix_normal ?? 0) > 0 ? formatEuros(p.prix_normal!) : 'Entrée libre';
  const reduction = p.reduction ?? 0;
  const prix = prixEvenement({ prix_normal: p.prix_normal, reduction, is_gratuit: p.is_gratuit ?? false });
  if (prix) return prix.barre ? `${prix.final} au lieu de ${prix.barre} (-${reduction} %)` : prix.final;
  return reduction > 0 ? `-${reduction} % sur place` : 'Entrée gratuite';
}

/** « billet-outsee-soiree-mousse-22-11.pdf » : un nom de fichier lisible et sûr. */
export function nomFichierBillet(p: Pick<PassBillet, 'titre' | 'date_heure' | 'id'>): string {
  const titre = p.titre
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
    .slice(0, 40).replace(/-+$/, '');
  const jour = dateFr(p.date_heure, 'd-m');
  return ['billet-outsee', titre || `pass-${p.id}`, jour].filter(Boolean).join('-') + '.pdf';
}

/**
 * Le QR en SVG à partir de sa matrice (qrcode.create : `size` modules de
 * côté, `data` ligne par ligne, 1 = module sombre). Un seul tracé, modules
 * basalte sur fond blanc, deux modules de marge : la cible optique de la
 * carte du Pass, à l'identique.
 */
export function qrSvg(modules: { size: number; data: ArrayLike<number> }): string {
  const n = modules.size;
  const marge = 2;
  const cote = n + marge * 2;
  let d = '';
  for (let y = 0; y < n; y++) {
    for (let x = 0; x < n; x++) {
      if (modules.data[y * n + x]) d += `M${x + marge} ${y + marge}h1v1h-1z`;
    }
  }
  return `<svg viewBox="0 0 ${cote} ${cote}" xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges">`
    + `<rect width="${cote}" height="${cote}" fill="${fixe.qrClair}"/><path d="${d}" fill="${fixe.qrSombre}"/></svg>`;
}

/** Les anneaux du logo, cote pour cote avec Marque.tsx (Anneaux), en SVG brut. */
function logoSvg(couleur: string, taille: number): string {
  return `<svg width="${taille}" height="${taille}" viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">`
    + `<defs><clipPath id="moitieBasse"><rect x="0" y="255.5" width="512" height="256.5"/></clipPath></defs>`
    + `<rect width="512" height="512" rx="112" fill="${couleur}"/>`
    + `<circle cx="196.5" cy="255.5" r="88.5" stroke="${fixe.basalte}" stroke-width="43"/>`
    + `<circle cx="314.5" cy="255.5" r="88.5" stroke="${fixe.craie}" stroke-width="43"/>`
    + `<circle cx="196.5" cy="255.5" r="88.5" stroke="${fixe.basalte}" stroke-width="43" clip-path="url(#moitieBasse)"/>`
    + `</svg>`;
}

function policesCss(polices: OptionsBillet['polices']): string {
  if (!polices) return '';
  return (Object.entries(polices) as [string, string | undefined][])
    .filter(([, b64]) => !!b64)
    .map(([poids, b64]) => `@font-face{font-family:'Onest';font-weight:${poids};font-style:normal;src:url(data:font/ttf;base64,${b64}) format('truetype');}`)
    .join('');
}

/** Une ligne « libellé / valeur » d'un bloc ; rien si la valeur est vide. */
function ligne(libelle: string, valeur: string | null | undefined): string {
  const v = (valeur ?? '').trim();
  if (!v) return '';
  return `<div class="ligne"><span class="libelle">${echapper(libelle)}</span><span class="valeur">${echapper(v)}</span></div>`;
}

/**
 * « Dimanche 22 novembre 2026 » : le mois en minuscules dans une phrase
 * (dateFr l'écrit avec une capitale, pour les en-têtes de l'application).
 */
function dateEnLettres(valeur: string | Date | null): string {
  if (!dateFr(valeur, 'j')) return '';
  return `${dateFr(valeur, 'l j')} ${dateFr(valeur, 'F').toLocaleLowerCase('fr-FR')} ${dateFr(valeur, 'Y')}`;
}

/** La page HTML complète du billet, au format A4 (595 × 842 pt). */
export function htmlBillet({ pass, titulaire, qrSvg, genereLe, polices }: OptionsBillet): string {
  const genre = pass.genre ?? 'soiree';
  const infos = GENRES[genre];
  const t = teintes(infos.rubrique, 'clair');
  const nomComplet = `${titulaire.prenom} ${titulaire.nom}`.trim();
  const dateLongue = dateEnLettres(pass.date_heure);
  const heure = dateFr(pass.date_heure, 'H\\hi');
  const quand = [dateLongue, heure].filter(Boolean).join(' · ');
  const lieu = [pass.etablissement, pass.lieu].map((s) => s?.trim()).filter(Boolean).join(' · ');

  return `<!doctype html>
<html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=595">
<title>${echapper(`Billet Outsee · ${pass.titre}`)}</title>
<style>
${policesCss(polices)}
@page{size:A4;margin:0}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:${fixe.craie}}
body{font-family:'Onest',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:${fixe.basalte};-webkit-print-color-adjust:exact;print-color-adjust:exact}
.page{width:595px;height:842px;overflow:hidden;padding:28px 36px 22px;display:flex;flex-direction:column;gap:12px}
.tete{display:flex;align-items:center;justify-content:space-between}
.marque{display:flex;align-items:center;gap:10px;font-weight:700;font-size:24px;letter-spacing:-0.035em}
.marque .ee{color:${t.accent}}
.ref{text-align:right;font-size:11px;color:#5A5A66;letter-spacing:.06em;text-transform:uppercase;font-weight:600}
.ref b{display:block;font-size:14px;color:${fixe.basalte};letter-spacing:.08em;margin-top:2px}
.carte{background:${t.accent};color:#FFFFFF;border-radius:28px;padding:20px 22px;display:flex;flex-direction:column;gap:12px}
.surtitre{font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:${t.surAccent2}}
.titulaire{font-size:20px;font-weight:700;letter-spacing:-0.03em;margin-top:4px}
.qr{background:#FFFFFF;border-radius:20px;padding:16px;display:flex;flex-direction:column;align-items:center;gap:12px}
.qr svg{width:184px;height:184px;display:block}
.qr p{font-size:13px;color:#55555C;text-align:center}
.evenement{font-size:20px;font-weight:700;letter-spacing:-0.03em;line-height:1.15;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
.quand{font-size:14px;color:${t.surAccent2};margin-top:6px}
.blocs{display:flex;gap:14px}
.bloc{flex:1;background:#FFFFFF;border-radius:20px;padding:14px 16px;display:flex;flex-direction:column;gap:8px}
.bloc h2{font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:${t.texteDoux}}
.ligne{display:flex;flex-direction:column;gap:2px}
.libelle{font-size:11px;color:#5A5A66}
.valeur{font-size:14px;font-weight:600;word-break:break-word;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
.consignes{background:${t.doux};color:${t.texteDoux};border-radius:16px;padding:12px 16px;font-size:12px;line-height:1.45}
.pied{margin-top:auto;display:flex;justify-content:space-between;font-size:10px;color:#5A5A66}
</style></head>
<body><div class="page">
  <div class="tete">
    <div class="marque">${logoSvg(t.accent, 38)}<span>Outs<span class="ee">ee</span></span></div>
    <div class="ref">Réf. du pass<b>${echapper(referenceBillet(pass.code))}</b></div>
  </div>

  <div class="carte">
    <div>
      <div class="surtitre">${echapper(infos.surtitre)}</div>
      <div class="titulaire">${echapper(nomComplet)}</div>
    </div>
    <div class="qr">
      ${qrSvg}
      <p>Présente ce QR code à l'entrée, depuis ce billet ou l'application.</p>
    </div>
    <div>
      <div class="evenement">${echapper(pass.etablissement)} — ${echapper(pass.titre)}</div>
      ${quand ? `<div class="quand">${echapper(quand)}</div>` : ''}
    </div>
  </div>

  <div class="blocs">
    <div class="bloc">
      <h2>Titulaire</h2>
      ${ligne('Nom', nomComplet)}
      ${ligne('École', titulaire.ecole)}
      ${ligne('Promo', titulaire.promo)}
      ${ligne('E-mail', titulaire.email)}
    </div>
    <div class="bloc">
      <h2>Événement</h2>
      ${ligne('Date et heure', quand)}
      ${ligne('Lieu', lieu)}
      ${ligne('Tarif', tarifBillet(pass))}
    </div>
  </div>

  <div class="consignes">Pass nominatif, valable une seule fois. Une pièce d'identité peut t'être demandée à l'entrée. Tu ne viens plus ? Annule ton pass dans l'application pour libérer la place.</div>

  <div class="pied">
    <span>Outsee · billet généré le ${echapper(`${dateFr(genereLe, 'j')} ${dateFr(genereLe, 'F').toLocaleLowerCase('fr-FR')} ${dateFr(genereLe, 'Y')} à ${dateFr(genereLe, 'H\\hi')}`)}</span>
    <span>N° ${pass.id}</span>
  </div>
</div></body></html>`;
}
