/**
 * Moi — profil.php.
 *
 * Refonte 2026-11 : une vitrine. Une carte bleue porte l'avatar, le nom,
 * « école · promo » et les compteurs d'abonnés et d'abonnements ; dessous,
 * les hashtags (les centres d'intérêt), « Modifier », et les trois tuiles
 * Sorties, Squads et Économies. Puis, comme avant : XP et badges, Apparence,
 * liens légaux, compte et déconnexion.
 *
 * Le formulaire « Mon Profil » est parti dans son propre écran, Modifier le
 * profil (profil/modifier.tsx), ouvert par « Modifier ».
 */

import React, { useCallback, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, type Badge, type ReponseMoi } from '../../api';
import { PAGES_LEGALES } from '../../legal';
import { Apparition, Compteur, PressableAnime } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { CarreIcone, EnTeteApp, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, Jauge } from '../../composants/Elements';
import { estIcone, Icone, type NomIcone } from '../../composants/Icone';
import { Display, Mono, T } from '../../composants/Texte';
import { nombre } from '../../format';
import { useJeton, useSession } from '../../session';
import { couleurCss, encreSur, fs, halo, lh, mono, police, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

function CarteProfil({ children, style }: { children: React.ReactNode; style?: object }) {
  const { c } = useTheme();
  return (
    <View style={[{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 20, marginBottom: 16 }, style]}>
      {children}
    </View>
  );
}

function TitreSection({ icone, children }: { icone: NomIcone; children: string }) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 16 }}>
      <View style={{ marginRight: 4 }}><Icone nom={icone} taille={20} couleur={c.noir} /></View>
      <T taille={19} poids={700} accessibilityRole="header" style={{ letterSpacing: -0.02 * 19 }}>{children}</T>
    </View>
  );
}

function PastilleBadge({ b, verrouille, largeur }: { b: Badge; verrouille?: boolean; largeur: number }) {
  const { c } = useTheme();
  return (
    <View style={{ width: largeur, alignItems: 'center', gap: 8 }} accessible accessibilityLabel={b.nom + (verrouille ? ', à débloquer' : '')}>
      {verrouille ? (
        <View style={{ width: 52, height: 52, borderRadius: 26, borderWidth: 2, borderStyle: 'dashed', borderColor: c.line2, alignItems: 'center', justifyContent: 'center' }}>
          <Text style={{ fontFamily: police.display, fontSize: fs[5], color: c.gris }}>?</Text>
        </View>
      ) : (
        <View style={{ width: 52, height: 52, borderRadius: 26, backgroundColor: couleurCss(b.couleur, c, c.lime), alignItems: 'center', justifyContent: 'center' }}>
          {estIcone(b.icon) ? <Icone nom={b.icon} taille={22} couleur={encreSur(couleurCss(b.couleur, c, c.lime))} /> : null}
        </View>
      )}
      <T taille={fs[2]} interligne={lh.snug} couleur={verrouille ? c.gris : c.grisFonce} style={{ textAlign: 'center' }}>{b.nom}</T>
    </View>
  );
}

export default function Moi() {
  const { c, choisir, preference } = useTheme();
  const bleu = useRubrique('bleu');
  const violet = useRubrique('culture');
  const jeton = useJeton();
  const { deconnexion } = useSession();
  const [largeurGrille, setLargeurGrille] = useState(0);

  const [donnees, setDonnees] = useState<ReponseMoi | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.pageMoi(jeton));
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const modifier = () => router.push('/profil/modifier' as never);

  if (!donnees) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const { moi, xp } = donnees;

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp onNotifications={() => router.navigate('/notifications')} />
        </Apparition>
      </EnTete>

      <Contenu style={{ paddingTop: 0 }}>
        {/* La carte de profil : aplat bleu, texte blanc. */}
        <Apparition index={1}>
          <View style={[{ backgroundColor: bleu.accent, borderRadius: rayon.carte, padding: 20, gap: 18, marginBottom: 16 }, halo(bleu.accent)]}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 16 }}>
              <Avatar photo={moi.photo_url} prenom={moi.prenom} taille={72} fond="#FFFFFF" />
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text accessibilityRole="header" numberOfLines={2} style={{ fontFamily: sans(700), fontSize: 24, lineHeight: 24 * 1.15, letterSpacing: -0.035 * 24, color: '#FFFFFF' }}>
                  {moi.prenom} {moi.nom}
                </Text>
                <T taille={fs[5]} couleur={bleu.surAccent2} style={{ marginTop: 4 }}>{moi.ecole ?? '—'} · {moi.promo ?? '—'}</T>
              </View>
            </View>
            <View style={{ flexDirection: 'row', gap: 10 }}>
              {[
                { n: moi.nb_abonnes, l: moi.nb_abonnes > 1 ? 'abonnés' : 'abonné', type: 'abonnes' },
                { n: moi.nb_abonnements, l: moi.nb_abonnements > 1 ? 'abonnements' : 'abonnement', type: 'abonnements' },
              ].map((x, i) => (
                <PressableAnime
                  key={x.type}
                  onPress={() => router.navigate({ pathname: '/abonnements', params: { type: x.type } })}
                  accessibilityRole="link"
                  accessibilityLabel={`${x.n} ${x.l}`}
                  style={{ flex: 1, flexDirection: 'row', alignItems: 'baseline', gap: 8, minHeight: 52, paddingVertical: 12, paddingHorizontal: 16, borderRadius: rayon.bouton, backgroundColor: 'rgba(255,255,255,0.16)' }}
                >
                  <Compteur valeur={x.n} delai={i * 120} style={{ fontFamily: sans(700), fontSize: 22, color: '#FFFFFF', fontVariant: ['tabular-nums'] }} />
                  <T taille={fs[5]} couleur={bleu.surAccent2}>{x.l}</T>
                </PressableAnime>
              ))}
            </View>
          </View>
        </Apparition>

        {/* Les hashtags (centres d'intérêt) et « Modifier ». */}
        <Apparition index={2}>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 16 }}>
            {moi.interets.map((i) => (
              <PressableAnime key={i} onPress={() => router.navigate({ pathname: '/personnes', params: { interest: i } })} accessibilityRole="link"
                accessibilityLabel={`Voir les étudiants qui aiment ${i}`}
                style={{ minHeight: 44, justifyContent: 'center', paddingHorizontal: 16, backgroundColor: c.blanc, borderRadius: rayon.bouton }}>
                <T taille={fs[5]} poids={500}>#{i}</T>
              </PressableAnime>
            ))}
            <PressableAnime onPress={modifier} accessibilityRole="button" accessibilityLabel={moi.interets.length ? 'Modifier mon profil' : 'Ajouter mes centres d\'intérêt'}
              style={{ minHeight: 44, flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: 16, backgroundColor: c.noir, borderRadius: rayon.bouton }}>
              <Icone nom="crayon" taille={17} couleur={c.surEncre} />
              <T taille={fs[5]} poids={700} couleur={c.surEncre}>{moi.interets.length ? 'Modifier' : 'Ajoute tes centres d\'intérêt'}</T>
            </PressableAnime>
          </View>
        </Apparition>

        {/* Les trois tuiles. */}
        <Apparition index={3}>
          <View style={{ flexDirection: 'row', gap: 10, marginBottom: 24 }}>
            {[
              { l: 'Sorties', v: moi.nb_sorties, format: (n: number) => String(Math.round(n)), icone: 'musique' as const, fond: bleu.doux, encre: bleu.texteDoux },
              { l: 'Squads', v: moi.nb_squads, format: (n: number) => String(Math.round(n)), icone: 'personnes' as const, fond: violet.doux, encre: violet.texteDoux },
              { l: 'Économies', v: moi.economies, format: (n: number) => nombre(Math.round(n), 0, '') + ' €', icone: 'portefeuille' as const, fond: c.succesClair, encre: c.succes },
            ].map((t, i) => (
              <View key={t.l} style={{ flex: 1, backgroundColor: c.blanc, borderRadius: rayon.tuile, padding: 14, gap: 10 }}>
                <CarreIcone icone={t.icone} fond={t.fond} encre={t.encre} />
                <Compteur valeur={t.v} format={t.format} delai={240 + i * 120}
                  style={{ fontFamily: sans(700), fontSize: 22, letterSpacing: -0.03 * 22, color: c.noir, fontVariant: ['tabular-nums'] }} />
                <T taille={fs[4]} couleur={c.gris}>{t.l}</T>
              </View>
            ))}
          </View>
        </Apparition>

        {/* XP et badges */}
        <CarteProfil>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 16 }}>
            <View style={{ width: 56, height: 56, borderRadius: 28, backgroundColor: bleu.accent, alignItems: 'center', justifyContent: 'center' }}>
              <Display taille={fs[7]} couleur="#FFFFFF">{xp.niveau}</Display>
            </View>
            <View style={{ flex: 1, minWidth: 0 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline', gap: 8, marginBottom: 8 }}>
                <Mono couleur={c.grisFonce}>Niveau {xp.niveau}</Mono>
                <Text style={{ fontFamily: mono(500), fontSize: fs[4], color: bleu.texteDoux }}>{nombre(xp.total)} / {nombre(xp.suivant)} XP</Text>
              </View>
              <Jauge pourcentage={xp.progres} piste={c.surface2} remplissage={bleu.accent} hauteur={8} />
            </View>
          </View>

          <T taille={fs[4]} couleur={c.gris} style={{ marginTop: 16, marginBottom: 18 }}>
            {xp.stats.events} sorties · {xp.stats.squads} squads · {xp.stats.follows} abonnements · {xp.stats.avis} avis
          </T>

          {/* grid-template-columns: repeat(auto-fill, minmax(76px, 1fr)) ; gap: 16px 10px */}
          <View onLayout={(e) => setLargeurGrille(e.nativeEvent.layout.width)} style={{ flexDirection: 'row', flexWrap: 'wrap', rowGap: 16, columnGap: 10 }}>
            {largeurGrille > 0 ? (() => {
              const colonnes = Math.max(1, Math.floor((largeurGrille + 10) / (76 + 10)));
              // Arrondi vers le bas : un demi-pixel de trop renvoie la dernière colonne à la ligne.
              const largeur = Math.floor((largeurGrille - (colonnes - 1) * 10) / colonnes);
              return [
                ...xp.obtenus.map((b) => <PastilleBadge key={b.code} b={b} largeur={largeur} />),
                ...xp.a_debloquer.map((b) => <PastilleBadge key={b.code} b={b} verrouille largeur={largeur} />),
              ];
            })() : null}
          </View>

          <Bouton libelle="Voir le classement" icone="trophee" variante="contour" plein onPress={() => router.navigate('/classement')} style={{ marginTop: 18 }} />
        </CarteProfil>

        {/* Apparence */}
        <CarteProfil>
          <TitreSection icone="soleil">Apparence</TitreSection>
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Pressable onPress={() => choisir('systeme')} accessibilityRole="button" accessibilityState={{ selected: preference === 'systeme' }}
              style={{ flex: 1, minHeight: 48, paddingHorizontal: 6, borderRadius: rayon.bouton, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 5, backgroundColor: c.surface2, borderWidth: 2, borderColor: preference === 'systeme' ? bleu.accent : c.surface2 }}>
              <Icone nom="engrenage" taille={15} couleur={c.noir} />
              <Text numberOfLines={1} style={{ fontFamily: sans(700), fontSize: fs[4], color: c.noir }}>Système</Text>
            </Pressable>
            <Pressable onPress={() => choisir('clair')} accessibilityRole="button" accessibilityState={{ selected: preference === 'clair' }}
              style={{ flex: 1, minHeight: 48, paddingHorizontal: 6, borderRadius: rayon.bouton, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 5, backgroundColor: '#F2F2F5', borderWidth: 2, borderColor: preference === 'clair' ? bleu.accent : '#D4D4DC' }}>
              <Icone nom="soleil-petit" taille={15} couleur="#111116" />
              <Text numberOfLines={1} style={{ fontFamily: sans(700), fontSize: fs[4], color: '#111116' }}>Clair</Text>
            </Pressable>
            <Pressable onPress={() => choisir('sombre')} accessibilityRole="button" accessibilityState={{ selected: preference === 'sombre' }}
              style={{ flex: 1, minHeight: 48, paddingHorizontal: 6, borderRadius: rayon.bouton, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 5, backgroundColor: '#0E0E12', borderWidth: 2, borderColor: preference === 'sombre' ? bleu.accent : '#3A3A44' }}>
              <Icone nom="lune" taille={15} couleur="#F2F2F5" />
              <Text numberOfLines={1} style={{ fontFamily: sans(700), fontSize: fs[4], color: '#F2F2F5' }}>Sombre</Text>
            </Pressable>
          </View>
        </CarteProfil>

        {/* Légal */}
        <CarteProfil style={{ padding: 0, overflow: 'hidden' }}>
          {PAGES_LEGALES.map(({ slug, libelle: l }, i) => (
            <Pressable key={slug} onPress={() => router.push(`/legal/${slug}` as never)} accessibilityRole="link"
              style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', minHeight: 52, paddingVertical: 14, paddingHorizontal: 20, borderBottomWidth: i < 2 ? 1 : 0, borderBottomColor: c.grisClair }}>
              <T taille={fs[5]} poids={500}>{l}</T>
              <Icone nom="chevron-d" taille={18} couleur={c.gris} />
            </Pressable>
          ))}
        </CarteProfil>

        {/* Compte */}
        <CarteProfil style={{ padding: 0, overflow: 'hidden' }}>
          <View style={{ paddingVertical: 16, paddingHorizontal: 20, borderBottomWidth: 1, borderBottomColor: c.grisClair, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Mono>Email</Mono>
            <T taille={fs[2]} poids={600}>{moi.email}</T>
          </View>
          <View style={{ paddingVertical: 16, paddingHorizontal: 20, borderBottomWidth: 1, borderBottomColor: c.grisClair, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Mono>Depuis</Mono>
            <T taille={fs[2]} poids={600}>{moi.depuis}</T>
          </View>
          <Pressable onPress={deconnexion} accessibilityRole="button" testID="deconnexion" style={{ paddingVertical: 16, paddingHorizontal: 20, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
            <Icone nom="deconnexion" taille={16} couleur={c.surRougeClair} />
            <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: c.surRougeClair }}>Se déconnecter</Text>
          </Pressable>
          <Pressable onPress={() => router.push('/supprimer-compte' as never)} accessibilityRole="button" style={{ paddingVertical: 16, paddingHorizontal: 20, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderTopWidth: 1, borderTopColor: c.grisClair }}>
            <Icone nom="interdit" taille={16} couleur={c.gris} />
            <Text style={{ fontFamily: sans(600), fontSize: fs[3], color: c.gris }}>Supprimer mon compte</Text>
          </Pressable>
        </CarteProfil>
      </Contenu>
    </Ecran>
  );
}
