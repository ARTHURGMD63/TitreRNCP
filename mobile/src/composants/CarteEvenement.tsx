/**
 * La carte d'une soiree.
 *
 * Transposition de .event-card : etiquette de type et date, titre editorial,
 * lieu, amis qui y vont, places et taux d'inscription.
 */

import Feather from '@expo/vector-icons/Feather';
import { StyleSheet, Text, View } from 'react-native';

import type { Evenement } from '../api';
import { useTheme } from '../useTheme';
import { espace, rayon, taille } from '../theme';

/** Le jeton de couleur associe a un type de lieu, comme .type-bar & co. */
function accent(type: string, c: ReturnType<typeof useTheme>['c']): string {
  switch (type) {
    case 'bar':
      return c.surBleuClair;
    case 'boite':
      return c.surRougeClair;
    case 'resto':
      return c.surOrangeClair;
    default:
      return c.gris;
  }
}

/**
 * « Mer 23 Sept · 19h30 ».
 *
 * Ecrit a la main plutot qu'avec toLocaleDateString : le format du web est
 * abrege d'une facon precise — « Sept » et non « sept. », « 19h30 » et non
 * « 19:30 » — et deux formats de date pour un meme produit se remarquent.
 */
function dateFr(iso: string): string {
  const d = new Date(iso.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';

  const jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
  const mois = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin',
                'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

  const heure = `${d.getHours()}h${String(d.getMinutes()).padStart(2, '0')}`;

  return `${jours[d.getDay()]} ${d.getDate()} ${mois[d.getMonth()]} · ${heure}`;
}

/** « Hugo, Maxime et 3 autres y vont ». */
function phraseAmis(prenoms: string[], nb: number): string | null {
  if (nb === 0) return null;

  const montres = prenoms.slice(0, 2);
  const reste = nb - montres.length;

  if (reste <= 0) {
    return `${montres.join(' et ')} y ${nb > 1 ? 'vont' : 'va'}`;
  }

  return `${montres.join(', ')} et ${reste} autre${reste > 1 ? 's' : ''} y vont`;
}

export function CarteEvenement({ evenement: e }: { evenement: Evenement }) {
  const { c, ombre } = useTheme();
  const couleurType = accent(e.etablissement.type, c);

  const amis = phraseAmis(e.amis.prenoms, e.amis.nb);
  const taux =
    e.places.quota > 0 ? Math.round((e.places.inscrits / e.places.quota) * 100) : null;

  return (
    <View
      style={[
        s.carte,
        { backgroundColor: c.blanc, borderColor: c.grisClair },
        ombre('sm'),
      ]}
    >
      <View style={s.ligneEtiquette}>
        <View style={[s.point, { backgroundColor: couleurType }]} />
        <Text style={[s.etiquette, { color: couleurType }]}>
          {e.etablissement.type.toUpperCase()} · {dateFr(e.date_heure).toUpperCase()}
        </Text>

        {e.is_flash && (
          <View style={[s.badge, { backgroundColor: c.rougeClair }]}>
            <Text style={[s.badgeTexte, { color: c.surRougeClair }]}>FLASH</Text>
          </View>
        )}
        {e.sponsorise && (
          <View style={[s.badge, { backgroundColor: c.limeClair }]}>
            <Text style={[s.badgeTexte, { color: c.surLimeClair }]}>SPONSORISÉ</Text>
          </View>
        )}
      </View>

      <Text style={[s.titre, { color: c.noir }]}>{e.titre}</Text>
      <Text style={[s.lieu, { color: c.gris }]}>{e.etablissement.nom}</Text>

      {amis !== null && (
        <View style={s.ligneAmis}>
          <Feather name="users" size={14} color={c.surRougeClair} />
          <Text style={[s.amis, { color: c.surRougeClair }]}>{amis}</Text>
        </View>
      )}

      {e.description !== '' && (
        <Text style={[s.description, { color: c.grisFonce }]} numberOfLines={2}>
          {e.description}
        </Text>
      )}

      <View style={[s.pied, { borderTopColor: c.grisClair }]}>
        <Text style={[s.places, { color: c.noir }]}>
          {e.places.quota > 0
            ? `${e.places.inscrits}/${e.places.quota} places`
            : 'Places libres'}
        </Text>

        {/* `> 0` et non `!== null` : la base porte des soirees a reduction
            nulle — une entree gratuite, un evenement sans remise — et « −0% »
            s'affichait alors comme un argument commercial vide. */}
        {e.reduction !== null && e.reduction > 0 && (
          <Text style={[s.reduction, { color: c.rouge }]}>−{e.reduction}%</Text>
        )}

        {e.is_gratuit && (
          <Text style={[s.reduction, { color: c.succes }]}>Gratuit</Text>
        )}

        {e.deja_inscrit && (
          <View style={[s.inscrit, { backgroundColor: c.succesClair }]}>
            <Feather name="check" size={12} color={c.succes} />
            <Text style={[s.inscritTexte, { color: c.succes }]}>Inscrit</Text>
          </View>
        )}
      </View>

      {taux !== null && (
        <View style={[s.jauge, { backgroundColor: c.grisClair }]}>
          <View
            style={[
              s.jaugeRemplie,
              {
                // Borne a 100 % : une soiree sur-reservee ferait deborder la
                // barre de sa carte.
                width: `${Math.min(100, taux)}%`,
                backgroundColor: e.places.complet ? c.danger : c.rouge,
              },
            ]}
          />
        </View>
      )}
    </View>
  );
}

const s = StyleSheet.create({
  carte: {
    borderWidth: 1,
    borderRadius: rayon.base,
    padding: espace.md,
    marginBottom: espace.base,
  },
  ligneEtiquette: { flexDirection: 'row', alignItems: 'center', gap: espace.xs, flexWrap: 'wrap' },
  point: { width: 6, height: 6, borderRadius: 3 },
  etiquette: { fontSize: taille.xs, fontWeight: '700', letterSpacing: 0.5 },
  badge: { borderRadius: rayon.pill, paddingHorizontal: espace.sm, paddingVertical: 2 },
  badgeTexte: { fontSize: 9, fontWeight: '900', letterSpacing: 0.5 },
  titre: { fontSize: taille.titre, fontWeight: '900', marginTop: espace.sm },
  lieu: { fontSize: taille.texte, marginTop: 2 },
  ligneAmis: { flexDirection: 'row', alignItems: 'center', gap: espace.xs, marginTop: espace.sm },
  amis: { fontSize: taille.base, fontWeight: '700' },
  description: { fontSize: taille.texte, marginTop: espace.sm, lineHeight: 19 },
  pied: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espace.base,
    borderTopWidth: 1,
    marginTop: espace.base,
    paddingTop: espace.base,
  },
  places: { fontSize: taille.base, fontWeight: '700' },
  reduction: { fontSize: taille.corps, fontWeight: '900' },
  inscrit: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginLeft: 'auto',
    borderRadius: rayon.pill,
    paddingHorizontal: espace.sm,
    paddingVertical: 3,
  },
  inscritTexte: { fontSize: taille.xs, fontWeight: '700' },
  jauge: { height: 4, borderRadius: 2, marginTop: espace.base, overflow: 'hidden' },
  jaugeRemplie: { height: '100%', borderRadius: 2 },
});
