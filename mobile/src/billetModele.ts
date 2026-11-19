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
 *
 * Depuis la v44 : le numéro du billet (relié au QR, le même qu'au scan),
 * l'âge minimum, la fin et l'adresse de l'événement, et les conditions du
 * billet électronique en bas de page.
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
  /** « OS-000123-3FA2 » (v44) ; à défaut, la référence tirée du code. */
  numero_billet?: string | null;
  date_fin?: string | null;
  age_minimum?: number | null;
  /** Adresse de l'établissement. */
  adresse?: string | null;
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

/** Le numéro imprimé sous le QR : celui du serveur, sinon la référence tirée du code. */
export function numeroAffiche(p: Pick<PassBillet, 'numero_billet' | 'code'>): string {
  return p.numero_billet?.trim() || referenceBillet(p.code);
}

/**
 * Les conditions du billet électronique, imprimées au bas de chaque billet.
 * Rédigées pour Outsee : l'établissement organise et contrôle l'entrée,
 * Outsee met en relation et délivre le billet.
 */
export function conditionsBillet(ageMinimum: number): { titre: string; texte: string }[] {
  return [
    { titre: 'Billet nominatif', texte: "Ce billet est personnel : il n'est valable que pour la personne dont le nom y figure, sur présentation d'une pièce d'identité officielle avec photo. Il ne peut être ni cédé, ni revendu, ni échangé." },
    { titre: "Accès décidé par l'établissement", texte: "L'établissement organisateur reste seul maître de son entrée : il peut refuser l'accès, notamment pour l'âge, la tenue, le comportement, l'état d'ébriété, la sécurité ou la capacité de la salle, et appliquer son règlement intérieur. Si tu as obtenu ou acheté ce billet et que l'établissement te refuse l'entrée, Outsee n'en est pas responsable." },
    { titre: 'Un seul passage', texte: "Le QR code et le numéro de billet sont uniques. Ils ne sont valables qu'une fois : au premier scan, le billet est utilisé et toute copie (capture d'écran, photocopie, impression) est refusée. Ne le partage pas." },
    { titre: `Âge minimum : ${ageMinimum} ans`, texte: `L'entrée est réservée aux personnes âgées d'au moins ${ageMinimum} ans le jour de l'événement. Une personne qui n'a pas l'âge requis est refusée, même munie d'un billet.` },
    { titre: "Rôle d'Outsee", texte: "Outsee met en relation les étudiants et les établissements et délivre ce billet. L'événement, son contenu, ses horaires, ses tarifs et ses réductions relèvent de l'établissement qui l'organise." },
    { titre: 'Modification ou annulation', texte: "En cas de changement d'horaire ou d'annulation par l'établissement, tu es prévenu dans l'application. Tu ne viens plus ? Annule ton pass dans l'application pour libérer la place : ce billet ne sera alors plus valable." },
    { titre: 'Contrefaçon', texte: "Reproduire, falsifier ou utiliser frauduleusement ce billet est interdit et peut entraîner la suspension du compte, sans préjudice de poursuites." },
  ];
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
  const finLongue = pass.date_fin ? [dateEnLettres(pass.date_fin), dateFr(pass.date_fin, 'H\\hi')].filter(Boolean).join(' · ') : '';
  const lieu = [pass.etablissement, pass.lieu].map((s) => s?.trim()).filter(Boolean).join(' · ');
  const age = Math.max(18, Math.floor(Number(pass.age_minimum) || 18));
  const numero = numeroAffiche(pass);
  const conditions = conditionsBillet(age)
    .map((cond) => `<div class="cond"><b>${echapper(cond.titre)}.</b> ${echapper(cond.texte)}</div>`)
    .join('');

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
.page{width:595px;height:842px;overflow:hidden;padding:24px 32px 18px;display:flex;flex-direction:column;gap:10px}
.tete{display:flex;align-items:center;justify-content:space-between}
.marque{display:flex;align-items:center;gap:10px;font-weight:700;font-size:22px;letter-spacing:-0.035em}
.marque .ee{color:${t.accent}}
.ref{text-align:right;font-size:10px;color:#5A5A66;letter-spacing:.06em;text-transform:uppercase;font-weight:600}
.ref b{display:block;font-size:14px;color:${fixe.basalte};letter-spacing:.06em;margin-top:2px}
.carte{background:${t.accent};color:#FFFFFF;border-radius:24px;padding:16px 18px;display:flex;gap:16px;align-items:stretch}
.carte .gauche{flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:10px;min-width:0}
.surtitre{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:${t.surAccent2}}
.titulaire{font-size:18px;font-weight:700;letter-spacing:-0.03em;margin-top:3px}
.evenement{font-size:19px;font-weight:700;letter-spacing:-0.03em;line-height:1.15;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden}
.quand{font-size:13px;color:${t.surAccent2};margin-top:5px}
.age{display:inline-block;margin-top:8px;background:#FFFFFF;color:${fixe.basalte};border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700}
.qr{background:#FFFFFF;border-radius:18px;padding:10px 12px;display:flex;flex-direction:column;align-items:center;gap:5px;width:166px}
.qr svg{width:140px;height:140px;display:block}
.qr .num{font-size:10px;color:#5A5A66;text-transform:uppercase;letter-spacing:.06em;font-weight:600;text-align:center}
.qr .num b{display:block;font-size:13px;color:${fixe.basalte};letter-spacing:.04em;margin-top:1px}
.bloc{background:#FFFFFF;border-radius:18px;padding:12px 14px}
.bloc h2{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:${t.texteDoux};margin-bottom:8px}
.grille{display:grid;grid-template-columns:repeat(3,1fr);gap:8px 14px}
.ligne{display:flex;flex-direction:column;gap:1px;min-width:0}
.libelle{font-size:9.5px;color:#5A5A66}
.valeur{font-size:12px;font-weight:600;word-break:break-word;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
.conditions{background:#FFFFFF;border-radius:18px;padding:12px 14px;flex:1;min-height:0;overflow:hidden}
.conditions h2{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:${t.texteDoux};margin-bottom:7px}
.conditions .cols{column-count:2;column-gap:16px}
.cond{font-size:8.4px;line-height:1.38;color:#3A3A44;margin-bottom:5px;break-inside:avoid}
.cond b{color:${fixe.basalte}}
.pied{display:flex;justify-content:space-between;font-size:9px;color:#5A5A66}
</style></head>
<body><div class="page">
  <div class="tete">
    <div class="marque">${logoSvg(t.accent, 34)}<span>Outs<span class="ee">ee</span></span></div>
    <div class="ref">N° de billet<b>${echapper(numero)}</b></div>
  </div>

  <div class="carte">
    <div class="gauche">
      <div>
        <div class="surtitre">${echapper(infos.surtitre)} · billet électronique</div>
        <div class="titulaire">${echapper(nomComplet)}</div>
      </div>
      <div>
        <div class="evenement">${echapper(pass.etablissement)} — ${echapper(pass.titre)}</div>
        ${quand ? `<div class="quand">${echapper(quand)}</div>` : ''}
        <div class="age">${age} ans et plus · pièce d'identité exigée</div>
      </div>
    </div>
    <div class="qr">
      ${qrSvg}
      <div class="num">Billet<b>${echapper(numero)}</b></div>
    </div>
  </div>

  <div class="bloc">
    <h2>Événement et titulaire</h2>
    <div class="grille">
      ${ligne('Début', quand)}
      ${ligne('Fin', finLongue)}
      ${ligne('Âge minimum', `${age} ans`)}
      ${ligne('Lieu', lieu)}
      ${ligne('Adresse', pass.adresse)}
      ${ligne('Tarif', tarifBillet(pass))}
      ${ligne('Titulaire', nomComplet)}
      ${ligne('École', [titulaire.ecole, titulaire.promo].map((v) => v?.trim()).filter(Boolean).join(' · '))}
      ${ligne('E-mail', titulaire.email)}
    </div>
  </div>

  <div class="conditions">
    <h2>Conditions du billet électronique</h2>
    <div class="cols">${conditions}</div>
  </div>

  <div class="pied">
    <span>Outsee · billet généré le ${echapper(`${dateFr(genereLe, 'j')} ${dateFr(genereLe, 'F').toLocaleLowerCase('fr-FR')} ${dateFr(genereLe, 'Y')} à ${dateFr(genereLe, 'H\\hi')}`)}</span>
    <span>Présente ce QR code à l'entrée, depuis ce billet ou l'application</span>
  </div>
</div></body></html>`;
}
