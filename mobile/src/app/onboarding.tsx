/**
 * L'accueil après inscription — onboarding.php.
 *
 * Cinq écrans qui défilent au doigt : l'accroche (lave, les deux anneaux),
 * Explore (surface, deux cartes), Squads (dôme), Rencontres (moutarde),
 * « C'est parti » (volt, le pass). En bas, les points (le point actif
 * s'allonge en lave) et « Suivant », qui devient « Explorer » au dernier.
 * « Passer » en haut à droite, sur une pastille basalte lisible sur tous les
 * aplats.
 */

import React, { useRef, useState } from 'react';
import { Pressable, ScrollView, Text, View, useWindowDimensions, type NativeScrollEvent, type NativeSyntheticEvent } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import Svg, { Circle } from 'react-native-svg';
import { Icone } from '../composants/Icone';
import { Display, Mono, T } from '../composants/Texte';
import { useSession } from '../session';
import { fixe, fs, lh, lsEm, mono, police, rayon, sans, type Couleurs } from '../theme';
import { useTheme } from '../useTheme';

type Diapo = {
  fond: (c: Couleurs) => string;
  tag: string;
  couleurTag: (c: Couleurs) => string;
  titre: [string, string, string]; // avant, accent, après
  desc: string;
  visuel: (c: Couleurs, prenom: string) => React.ReactNode;
};

function Stat({ num, label, bas, colonne }: { num: string; label: string; bas?: boolean; colonne?: boolean }) {
  const fond = bas ? fixe.craie : fixe.basalte;
  const encre = bas ? fixe.basalte : fixe.craie;
  return (
    <View style={{ position: 'absolute', ...(bas ? { bottom: 20, right: 20 } : { top: 20, left: 20 }), backgroundColor: fond, borderRadius: rayon.md, paddingVertical: 10, paddingHorizontal: colonne ? 14 : 16, gap: colonne ? 2 : 0 }}>
      <Display taille={fs[6]} couleur={encre} interligne={lh.display}>{num}</Display>
      <Text style={{ fontFamily: sans(600), fontSize: fs[2], lineHeight: fs[2] * lh.snug, color: encre, opacity: 0.8 }}>{label}</Text>
    </View>
  );
}

function CarteMaquette({ c, tag, titre, meta, badge, badgeFond, decale }: { c: Couleurs; tag: string; titre: string; meta: string; badge: string; badgeFond?: string; decale?: boolean }) {
  return (
    <View style={{ width: 210, backgroundColor: c.bg, borderRadius: rayon.md, borderWidth: 1, borderColor: c.grisClair, paddingVertical: 14, paddingHorizontal: 16, ...(decale ? { transform: [{ translateX: 24 }], opacity: 0.7 } : null) }}>
      <Mono couleur={c.surRougeClair} style={{ marginBottom: 4 }}>{tag}</Mono>
      <Display taille={fs[5]} style={{ marginBottom: 6 }}>{titre}</Display>
      <T taille={fs[1]} poids={600} couleur={c.gris}>{meta}</T>
      <View style={{ alignSelf: 'flex-start', marginTop: 8, borderRadius: rayon.pill, backgroundColor: badgeFond ?? c.rouge, paddingVertical: 3, paddingHorizontal: 10 }}>
        <T taille={fs[2]} poids={700} couleur={fixe.surLave}>{badge}</T>
      </View>
    </View>
  );
}

function CartePersonne({ c, initiale, fond, nom, meta, tags }: { c: Couleurs; initiale: string; fond: string; nom: string; meta: string; tags: string[] }) {
  return (
    <View style={{ alignSelf: 'stretch', backgroundColor: c.bg, borderRadius: rayon.md, borderWidth: 1, borderColor: c.grisClair, paddingVertical: 14, paddingHorizontal: 16 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
          <View style={{ width: 36, height: 36, borderRadius: 18, backgroundColor: fond, alignItems: 'center', justifyContent: 'center' }}>
            <Text style={{ fontFamily: police.display, fontSize: fs[4], color: fixe.surLave }}>{initiale}</Text>
          </View>
          <View>
            <T taille={fs[4]} poids={700}>{nom}</T>
            <T taille={fs[1]} couleur={c.gris}>{meta}</T>
          </View>
        </View>
        <View style={{ backgroundColor: c.rouge, paddingVertical: 5, paddingHorizontal: 12, borderRadius: rayon.pill }}>
          <T taille={fs[2]} poids={700} couleur={fixe.surLave}>+ Suivre</T>
        </View>
      </View>
      <View style={{ marginTop: 8, flexDirection: 'row', gap: 4 }}>
        {tags.map((t) => (
          <View key={t} style={{ borderWidth: 1, borderColor: c.line2, borderRadius: rayon.pill, paddingVertical: 2, paddingHorizontal: 9 }}>
            <T taille={fs[2]} poids={500}>{t}</T>
          </View>
        ))}
      </View>
    </View>
  );
}

function GrandeIcone({ children }: { children: React.ReactNode }) {
  return (
    <View style={{ width: 96, height: 96, borderRadius: 48, backgroundColor: 'rgba(17,16,19,0.1)', borderWidth: 1, borderColor: 'rgba(17,16,19,0.25)', alignItems: 'center', justifyContent: 'center' }}>
      {children}
    </View>
  );
}

const DIAPOS: Diapo[] = [
  {
    fond: (c) => c.rouge,
    tag: 'Bienvenue sur Linkee',
    couleurTag: (c) => c.surRougeClair,
    titre: ['La vie étudiante\nà prix ', 'réduit.', ''],
    desc: 'Bars, boîtes, restos — accède aux meilleures sorties de ta ville avec des réductions exclusives réservées aux étudiants.',
    visuel: () => (
      <>
        <Stat num="-50%" label={'sur tes\nsorties'} />
        <AnneauxAccueil />
        <Stat num="100%" label={'étudiant\ngratuit'} bas />
      </>
    ),
  },
  {
    fond: (c) => c.blanc,
    tag: 'Explore',
    couleurTag: (c) => c.surRougeClair,
    titre: ['Les bons plans\ndu ', 'moment.', ''],
    desc: "Découvre les événements près de chez toi, inscris-toi en un tap et reçois ton pass numérique directement dans l'app.",
    visuel: (c) => (
      <View style={{ gap: 12, alignItems: 'center' }}>
        <CarteMaquette c={c} tag="Bar · Ce soir" titre="Happy Hour" meta="Le Bec qui Pique — 18h" badge="-50% · Flash" />
        <CarteMaquette c={c} tag="Boîte · Vendredi" titre="Soirée Étudiante" meta="Le Baromètre — 23h" badge="Entrée gratuite" badgeFond={c.bleu} decale />
      </View>
    ),
  },
  {
    fond: (c) => c.bleu,
    tag: 'Squads',
    couleurTag: (c) => c.surBleuClair,
    titre: ['Bouge avec\nles ', 'bons.', ''],
    desc: "Running, vélo, muscu… Rejoins un groupe d'étudiants qui partagent tes passions. Ou crée le tien en 30 secondes.",
    visuel: () => (
      <>
        <Stat num="12" label={'squads\nactifs'} colonne />
        <GrandeIcone><Icone nom="personnes" taille={48} couleur={fixe.basalte} /></GrandeIcone>
        <Stat num="3" label={'sports\ndispo'} bas colonne />
      </>
    ),
  },
  {
    fond: (c) => c.orange,
    tag: 'Rencontres',
    couleurTag: (c) => c.surOrangeClair,
    titre: ['Trouve tes\nfuturs ', 'potes.', ''],
    desc: 'Découvre des étudiants qui partagent tes intérêts, vont aux mêmes événements que toi — et abonne-toi pour rester connecté.',
    visuel: (c) => (
      <View style={{ gap: 12, alignSelf: 'stretch', paddingHorizontal: 28 }}>
        <CartePersonne c={c} initiale="L" fond={c.bleu} nom="Léa M." meta="SIGMA · M1" tags={['#muscu', '#boites']} />
        <CartePersonne c={c} initiale="A" fond={c.rouge} nom="Arthur M." meta="UCA · L2" tags={['#running', '#bars']} />
      </View>
    ),
  },
  {
    fond: (c) => c.lime,
    tag: "C'est parti",
    couleurTag: (c) => c.surLimeClair,
    titre: ['Prêt à\n', 'kiffer', ' ?'],
    desc: "Ton pass numérique, tes événements, tes amis. Tout est là. Il ne reste plus qu'à sortir.",
    visuel: (c, prenom) => (
      <View style={{ alignItems: 'center', gap: 20 }}>
        <GrandeIcone><Icone nom="carte" taille={48} couleur={fixe.basalte} /></GrandeIcone>
        <View style={{ width: 210, backgroundColor: c.rouge, borderRadius: rayon.base, paddingVertical: 14, paddingHorizontal: 16 }}>
          <Mono couleur={fixe.surLave} style={{ marginBottom: 4 }}>linkee pass</Mono>
          <Display taille={fs[5]} couleur={fixe.surLave} style={{ marginBottom: 6 }}>{prenom}</Display>
          <T taille={fs[1]} poids={600} couleur="rgba(17,16,19,0.75)">Happy Hour · Ce soir</T>
          <View style={{ marginTop: 10, backgroundColor: '#FFFFFF', height: 48, width: 48, borderRadius: 10, alignItems: 'center', justifyContent: 'center' }}>
            <Icone nom="qr" taille={32} couleur={fixe.basalte} trait={1.5} />
          </View>
        </View>
      </View>
    ),
  },
];

/** Écran 1 : les deux anneaux, basalte et craie, sur la lave (.onb-anneaux, 190 px). */
function AnneauxAccueil() {
  return (
    <Svg width={190} height={190 * 32 / 52} viewBox="0 0 52 32" fill="none">
      <Circle cx={16} cy={18} r={12} stroke="#111013" strokeWidth={4.5} />
      <Circle cx={34} cy={14} r={12} stroke="#F5F1E8" strokeWidth={4.5} />
    </Svg>
  );
}

export default function Accueil() {
  const { c } = useTheme();
  const { profil, terminerAccueil } = useSession();
  const { width, height } = useWindowDimensions();
  const { top, bottom } = useSafeAreaInsets();
  const defilement = useRef<ScrollView>(null);
  const [courant, setCourant] = useState(0);
  const prenom = profil?.prenom ?? 'toi';
  const dernier = courant === DIAPOS.length - 1;

  const aller = (i: number) => {
    setCourant(i);
    defilement.current?.scrollTo({ x: i * width, animated: true });
  };

  const surFin = (e: NativeSyntheticEvent<NativeScrollEvent>) => {
    setCourant(Math.round(e.nativeEvent.contentOffset.x / width));
  };

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <ScrollView ref={defilement} horizontal pagingEnabled showsHorizontalScrollIndicator={false} onMomentumScrollEnd={surFin} style={{ flex: 1 }}>
        {DIAPOS.map((d, i) => (
          <View key={i} style={{ width, flex: 1 }}>
            <View style={{ height: height * 0.48, backgroundColor: d.fond(c), borderBottomLeftRadius: rayon.xl, borderBottomRightRadius: rayon.xl, alignItems: 'center', justifyContent: 'center', overflow: 'hidden', paddingTop: top }}>
              {d.visuel(c, prenom)}
            </View>
            <View style={{ flex: 1, paddingTop: 28, paddingHorizontal: 28, paddingBottom: 16, justifyContent: 'center' }}>
              <Text style={{ fontFamily: mono(500), fontSize: fs[1], letterSpacing: lsEm.label * fs[1], textTransform: 'uppercase', color: d.couleurTag(c), marginBottom: 10 }}>{d.tag}</Text>
              <Display taille={fs[8]} interligne={lh.tight} accessibilityRole="header" style={{ marginBottom: 14 }}>
                {d.titre[0]}
                <Text style={{ color: d.couleurTag(c) }}>{d.titre[1]}</Text>
                {d.titre[2]}
              </Display>
              <T taille={fs[5]} couleur={c.grisFonce} interligne={lh.relaxed}>{d.desc}</T>
            </View>
          </View>
        ))}
      </ScrollView>

      {/* « Passer » */}
      <Pressable
        onPress={terminerAccueil}
        accessibilityRole="button"
        style={{ position: 'absolute', top: top + 16, right: 20, backgroundColor: 'rgba(17,16,19,0.6)', borderRadius: rayon.pill, paddingVertical: 8, paddingHorizontal: 14 }}
      >
        <Text style={{ fontFamily: mono(500), fontSize: fs[1], letterSpacing: lsEm.label * fs[1], textTransform: 'uppercase', color: fixe.craie }}>Passer</Text>
      </Pressable>

      {/* Pied : points et bouton */}
      <View style={{ paddingTop: 16, paddingHorizontal: 28, paddingBottom: bottom + 20, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: c.bg }}>
        <View style={{ flexDirection: 'row', gap: 6, alignItems: 'center' }}>
          {DIAPOS.map((_, i) => (
            <View key={i} style={{ width: i === courant ? 24 : 8, height: 8, borderRadius: rayon.pill, backgroundColor: i === courant ? c.rouge : c.line2 }} />
          ))}
        </View>
        <Pressable
          onPress={() => (dernier ? terminerAccueil() : aller(courant + 1))}
          accessibilityRole="button"
          style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: c.rouge, borderColor: c.rouge, borderWidth: 1, borderRadius: rayon.pill, paddingVertical: 14, paddingHorizontal: 26, transform: pressed ? [{ scale: 0.98 }] : [] })}
        >
          <Text style={{ fontFamily: sans(700), fontSize: fs[5], color: fixe.surLave }}>{dernier ? 'Explorer' : 'Suivant'}</Text>
          <Icone nom="fleche-d" taille={14} couleur={fixe.surLave} trait={2.5} />
        </Pressable>
      </View>
    </View>
  );
}
