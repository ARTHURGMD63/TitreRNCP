/**
 * La carte d'une soiree — transposition de .event-card.
 *
 * L'ordre des elements suit celui du gabarit web, sans en sauter aucun :
 *
 *   1. meta        « BAR · Mer 23 Sept », plus les badges flash / sponsorise
 *   2. titre
 *   3. ligne lieu  le nom de l'etablissement, et « + SUIVRE » a sa droite —
 *                  le bouton suit le LIEU, pas la soiree, d'ou sa place ici
 *                  et non dans un coin au-dessus des badges
 *   4. amis        « Hugo, Maxime y vont »
 *   5. description
 *   6. pied        places a gauche, « Inviter » et « S'inscrire » a droite
 *   7. taux        « TAUX D'INSCRIPTION … 36 % » puis la jauge
 */

import { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import Feather from '@expo/vector-icons/Feather';
import { useRouter } from 'expo-router';

import { actions, ErreurApi, type Evenement } from '../api';
import { useJeton } from '../session';
import { useTheme } from '../useTheme';
import { espace, rayon, taille } from '../theme';

/** La couleur d'accent d'un type de lieu, comme .type-bar & co. */
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
 * « 19:30 ». Deux formats de date pour un meme produit se remarquent.
 */
function dateFr(iso: string, avecHeure = true): string {
  const d = new Date(iso.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';

  const jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
  const mois = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin',
                'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

  const base = `${jours[d.getDay()]} ${d.getDate()} ${mois[d.getMonth()]}`;
  if (!avecHeure) return base;

  return `${base} · ${d.getHours()}h${String(d.getMinutes()).padStart(2, '0')}`;
}

/** « Hugo, Maxime y vont » — trois prenoms au plus, puis un decompte. */
function phraseAmis(prenoms: string[], nb: number): string | null {
  if (nb === 0) return null;

  const montres = prenoms.slice(0, 2);
  const reste = nb - montres.length;

  if (reste <= 0) {
    return `${montres.join(' et ')} y ${nb > 1 ? 'vont' : 'va'}`;
  }

  return `${montres.join(', ')} et ${reste} autre${reste > 1 ? 's' : ''} y vont`;
}

type Props = {
  evenement: Evenement;
  /** Rappelé après une action qui change les données du serveur. */
  surAction: () => void;
  surInviter: (evenement: Evenement) => void;
};

export function CarteEvenement({ evenement: e, surAction, surInviter }: Props) {
  const jeton = useJeton();
  const { c, ombre } = useTheme();
  const router = useRouter();

  const [suit, setSuit] = useState(e.etablissement.suivi);
  const [enCoursSuivi, setEnCoursSuivi] = useState(false);
  const [enCoursInscription, setEnCoursInscription] = useState(false);

  const couleurType = accent(e.etablissement.type, c);
  const amis = phraseAmis(e.amis.prenoms, e.amis.nb);
  const taux = e.places.quota > 0 ? Math.round((e.places.inscrits / e.places.quota) * 100) : null;

  async function basculerSuivi() {
    if (enCoursSuivi) return;
    setEnCoursSuivi(true);

    // L'etat bascule avant la reponse : suivre un lieu doit paraitre
    // instantane. En cas d'echec on revient en arriere — mentir durablement
    // serait pire que d'attendre.
    const avant = suit;
    setSuit(!avant);

    try {
      await actions.suivreEtablissement(jeton, e.etablissement.id, !avant);
    } catch (err) {
      setSuit(avant);
      Alert.alert('Impossible', err instanceof ErreurApi ? err.message : 'Réessaie.');
    } finally {
      setEnCoursSuivi(false);
    }
  }

  async function sInscrire() {
    if (enCoursInscription || e.deja_inscrit) return;
    setEnCoursInscription(true);
    try {
      await actions.inscrire(jeton, e.id);
      surAction();
    } catch (err) {
      Alert.alert('Impossible', err instanceof ErreurApi ? err.message : 'Réessaie.');
    } finally {
      setEnCoursInscription(false);
    }
  }

  return (
    <View style={[s.carte, { backgroundColor: c.blanc, borderColor: c.grisClair }, ombre('sm')]}>
      {/* 1. Meta et badges */}
      <View style={s.ligneMeta}>
        <View style={[s.point, { backgroundColor: couleurType }]} />
        <Text style={[s.meta, { color: couleurType }]}>
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

      {/* 2. Titre — ouvre la fiche */}
      <Pressable onPress={() => router.push(`/evenement/${e.id}`)}>
        <Text style={[s.titre, { color: c.noir }]}>{e.titre}</Text>
      </Pressable>

      {/* 3. Lieu et bouton « + SUIVRE » */}
      <View style={s.ligneLieu}>
        <Pressable style={s.lieuPressable} onPress={() => router.push(`/evenement/${e.id}`)}>
          <Text style={[s.lieu, { color: c.gris }]} numberOfLines={1}>
            {e.etablissement.nom}
          </Text>
        </Pressable>

        <Pressable
          onPress={basculerSuivi}
          disabled={enCoursSuivi}
          style={({ pressed }) => [
            s.boutonSuivre,
            {
              borderColor: suit ? c.grisClair : c.noir,
              backgroundColor: suit ? c.surface2 : 'transparent',
              opacity: pressed ? 0.7 : 1,
            },
          ]}
        >
          <Text style={{ color: suit ? c.gris : c.noir, fontSize: taille.xs, fontWeight: '700' }}>
            {suit ? 'SUIVI' : '+ SUIVRE'}
          </Text>
        </Pressable>
      </View>

      {/* 4. Amis */}
      {amis !== null && (
        <View style={s.ligneAmis}>
          <Feather name="users" size={14} color={c.surRougeClair} />
          <Text style={[s.amis, { color: c.surRougeClair }]}>{amis}</Text>
        </View>
      )}

      {/* 5. Description */}
      {e.description !== '' && (
        <Text style={[s.description, { color: c.grisFonce }]} numberOfLines={2}>
          {e.description}
        </Text>
      )}

      {/* 6. Places, Inviter, S'inscrire */}
      <View style={s.pied}>
        <Text style={[s.places, { color: c.noir }]}>
          {e.places.quota > 0 ? `${e.places.inscrits}/${e.places.quota} places` : 'Places libres'}
        </Text>

        <View style={s.boutons}>
          <Pressable
            onPress={() => surInviter(e)}
            style={({ pressed }) => [
              s.boutonInviter,
              { borderColor: c.grisClair, opacity: pressed ? 0.7 : 1 },
            ]}
          >
            <Feather name="user-plus" size={13} color={c.noir} />
            <Text style={{ color: c.noir, fontSize: taille.xs, fontWeight: '700' }}>Inviter</Text>
          </Pressable>

          <Pressable
            onPress={sInscrire}
            disabled={e.deja_inscrit || e.places.complet || enCoursInscription}
            style={({ pressed }) => [
              s.boutonInscrire,
              {
                backgroundColor: e.deja_inscrit ? c.succesClair : c.noir,
                opacity: e.places.complet && !e.deja_inscrit ? 0.5 : pressed ? 0.85 : 1,
              },
            ]}
          >
            {enCoursInscription ? (
              <ActivityIndicator size="small" color={c.bg} />
            ) : e.deja_inscrit ? (
              <>
                <Feather name="check" size={13} color={c.succes} />
                <Text style={{ color: c.succes, fontSize: taille.base, fontWeight: '700' }}>
                  Inscrit
                </Text>
              </>
            ) : (
              <Text style={{ color: c.bg, fontSize: taille.base, fontWeight: '700' }}>
                {e.places.complet ? 'Complet' : "S'inscrire"}
              </Text>
            )}
          </Pressable>
        </View>
      </View>

      {/* 7. Taux d'inscription et jauge */}
      {taux !== null && (
        <>
          <View style={s.ligneTaux}>
            <Text style={[s.tauxLabel, { color: c.noir }]}>{"TAUX D'INSCRIPTION"}</Text>
            <Text style={[s.tauxLabel, { color: c.noir }]}>{taux}%</Text>
          </View>
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
        </>
      )}
    </View>
  );
}

const s = StyleSheet.create({
  carte: {
    borderWidth: 1,
    borderRadius: rayon.base,
    padding: espace.md,
    marginBottom: espace.lg,
  },

  ligneMeta: { flexDirection: 'row', alignItems: 'center', gap: espace.xs, flexWrap: 'wrap' },
  point: { width: 6, height: 6, borderRadius: 3 },
  meta: { fontSize: taille.xs, fontWeight: '700', letterSpacing: 0.5 },
  badge: { borderRadius: rayon.pill, paddingHorizontal: espace.sm, paddingVertical: 2 },
  badgeTexte: { fontSize: 9, fontWeight: '900', letterSpacing: 0.5 },

  titre: { fontSize: taille.titre, fontWeight: '900', marginTop: espace.sm },

  ligneLieu: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espace.sm,
    marginTop: espace.xs,
  },
  // min-width: 0 : sans cela, un nom de lieu long pousse le bouton hors de la
  // carte au lieu d'etre tronque — c'est la meme regle que celle qui faisait
  // deborder la rangee « Date & heure » du formulaire de squad.
  lieuPressable: { flex: 1, minWidth: 0 },
  lieu: { fontSize: taille.texte },
  boutonSuivre: {
    borderWidth: 1,
    borderRadius: rayon.bouton,
    paddingHorizontal: espace.base,
    paddingVertical: 6,
  },

  ligneAmis: { flexDirection: 'row', alignItems: 'center', gap: espace.xs, marginTop: espace.sm },
  amis: { fontSize: taille.base, fontWeight: '700', flexShrink: 1 },

  description: { fontSize: taille.base, marginTop: espace.base, lineHeight: 19 },

  pied: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espace.sm,
    marginTop: espace.base,
  },
  places: { fontSize: taille.xs, fontWeight: '700' },
  boutons: { flexDirection: 'row', gap: espace.sm, alignItems: 'center' },
  boutonInviter: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espace.xs,
    borderWidth: 1,
    borderRadius: rayon.bouton,
    paddingHorizontal: espace.base,
    paddingVertical: 7,
  },
  boutonInscrire: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espace.xs,
    borderRadius: rayon.bouton,
    paddingHorizontal: espace.md,
    paddingVertical: 8,
    minHeight: 34,
    minWidth: 92,
    justifyContent: 'center',
  },

  ligneTaux: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: espace.base,
    marginBottom: espace.xs,
  },
  tauxLabel: { fontSize: taille.xs, fontWeight: '700', letterSpacing: 0.5 },
  jauge: { height: 6, borderRadius: 3, overflow: 'hidden' },
  jaugeRemplie: { height: '100%', borderRadius: 3 },
});
