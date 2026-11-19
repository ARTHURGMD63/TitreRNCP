/**
 * Télécharger le billet PDF d'un pass, depuis le Pass (wallet.tsx).
 *
 * Tout se fait sur le téléphone, sans appel au serveur : les informations du
 * billet sont déjà là (le pass, le profil de la session). La page vient de
 * billetModele.ts ; expo-print la rend en PDF dans le cache de
 * l'application, puis la feuille de partage du système propose de
 * l'enregistrer (Fichiers sur iPhone, Téléchargements sur Android), de
 * l'imprimer ou de l'envoyer.
 *
 * Sur le web, il n'y a ni fichier local ni partage : la page s'ouvre dans la
 * boîte d'impression du navigateur, d'où l'on choisit « Enregistrer en PDF ».
 */

import { Onest_400Regular, Onest_600SemiBold, Onest_700Bold } from '@expo-google-fonts/onest';
import { Asset } from 'expo-asset';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import { Platform } from 'react-native';

import { htmlBillet, nomFichierBillet, qrSvg, type PassBillet, type TitulaireBillet } from './billetModele';

type ModuleQr = { create(texte: string, options: { errorCorrectionLevel: 'L' | 'M' | 'Q' | 'H' }): { modules: { size: number; data: ArrayLike<number> } } };
// qrcode n'a pas de types ; c'est la bibliothèque qu'utilise déjà react-native-qrcode-svg.
// eslint-disable-next-line @typescript-eslint/no-require-imports
const QRCode = require('qrcode') as ModuleQr;

type Polices = Partial<Record<400 | 600 | 700, string>>;
let policesEnCache: Polices | null = null;

/**
 * Onest en base64, pour l'embarquer dans la page : expo-print ne voit pas les
 * polices chargées par l'application. Lues une fois, puis gardées. Une
 * police introuvable n'empêche pas le billet : la page retombe sur la police
 * du système.
 */
async function polices(): Promise<Polices> {
  if (policesEnCache) return policesEnCache;
  const sortie: Polices = {};
  try {
    const { File } = await import('expo-file-system');
    const fichiers: [400 | 600 | 700, number][] = [[400, Onest_400Regular], [600, Onest_600SemiBold], [700, Onest_700Bold]];
    for (const [poids, module] of fichiers) {
      const asset = await Asset.fromModule(module).downloadAsync();
      if (asset.localUri) sortie[poids] = await new File(asset.localUri).base64();
    }
  } catch {
    // La police du système fera l'affaire.
  }
  policesEnCache = sortie;
  return sortie;
}

/**
 * Crée le billet PDF du pass et ouvre la feuille de partage pour
 * l'enregistrer. Lève une Error au message lisible si quelque chose échoue.
 */
export async function telechargerBillet(pass: PassBillet, titulaire: TitulaireBillet): Promise<void> {
  const qr = qrSvg(QRCode.create(`outsee:${pass.code}`, { errorCorrectionLevel: 'H' }).modules);

  if (Platform.OS === 'web') {
    await Print.printAsync({ html: htmlBillet({ pass, titulaire, qrSvg: qr, genereLe: new Date() }) });
    return;
  }

  const html = htmlBillet({ pass, titulaire, qrSvg: qr, genereLe: new Date(), polices: await polices() });
  const { uri } = await Print.printToFileAsync({ html, width: 595, height: 842, margins: { top: 0, right: 0, bottom: 0, left: 0 } });

  // expo-print nomme le fichier au hasard : on lui donne un nom lisible, qui
  // sera celui proposé à l'enregistrement.
  let fichier = uri;
  try {
    const { File, Paths } = await import('expo-file-system');
    const cible = new File(Paths.cache, nomFichierBillet(pass));
    if (cible.exists) cible.delete();
    await new File(uri).move(cible);
    fichier = cible.uri;
  } catch {
    // Le nom au hasard reste utilisable.
  }

  if (!(await Sharing.isAvailableAsync())) throw new Error('Le partage de fichiers n’est pas disponible sur ce téléphone.');
  await Sharing.shareAsync(fichier, { mimeType: 'application/pdf', UTI: 'com.adobe.pdf', dialogTitle: 'Ton billet Outsee' });
}
