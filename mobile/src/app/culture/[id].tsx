/**
 * La fiche d'un événement de l'espace Culture.
 *
 * Construite comme la fiche d'une soirée (evenement/[id].tsx) : un hero
 * arrondi avec retour et « Partager », l'étiquette « EXPO · JUSQU'AU 3 DÉC »,
 * le titre, l'organisateur et le prix en pilule ; puis les places, la
 * description, le lien officiel, les amis inscrits, la date et le lieu.
 *
 * En bas, l'inscription et l'invitation côte à côte : on vient ici pour
 * décider d'y aller, et d'y aller à plusieurs. Une fois inscrit, on peut se
 * désinscrire — il n'y a pas de pass à annuler, juste une place à rendre.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, Share, Text, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import Svg, { Defs, Pattern, Rect } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, type EvenementCultureDetail } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { etiquetteCulture, useInscriptionCulture } from '../../composants/CarteCulture';
import { PastilleEnCours } from '../../composants/CarteEvenement';
import { Chargement, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, BoutonRetour, Jauge } from '../../composants/Elements';
import { Icone, type NomIcone } from '../../composants/Icone';
import { FeuilleInvitation } from '../../composants/Social';
import { Display, Mono, T } from '../../composants/Texte';
import { confirmer } from '../../confirmer';
import { libelleBoutonCulture, libellePlaces, libellePrix, pourcentagePlaces, surPlusieursJours } from '../../culture';
import { dateFr, heure, ts } from '../../format';
import { useJeton } from '../../session';
import { fixe, fs, gutter, lh, mono, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

function Section({ icone, children }: { icone?: NomIcone; children: string }) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 12 }}>
      {icone ? <Icone nom={icone} taille={16} couleur={c.gris} /> : null}
      <Mono accessibilityRole="header">{children}</Mono>
    </View>
  );
}

/** Le même fond rayé que la fiche d'une soirée sans photo. */
function Rayures({ a, b }: { a: string; b: string }) {
  return (
    <Svg style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 }} width="100%" height="100%">
      <Defs>
        <Pattern id="rayures-culture" patternUnits="userSpaceOnUse" width={62.2} height={62.2} patternTransform="rotate(45)">
          <Rect x={0} y={0} width={31.1} height={62.2} fill={a} />
          <Rect x={31.1} y={0} width={31.1} height={62.2} fill={b} />
        </Pattern>
      </Defs>
      <Rect x={0} y={0} width="100%" height="100%" fill="url(#rayures-culture)" />
    </Svg>
  );
}

export default function FicheCulture() {
  const jeton = useJeton();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [e, setE] = useState<EvenementCultureDetail | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setE((await api.cultureEvenement(jeton, Number(id))).evenement);
    } catch (err) {
      setErreur(err instanceof Error ? err.message : 'Événement introuvable.');
    }
  }, [jeton, id]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  if (!e) {
    return (
      <Ecran avecBarre={false}>
        <View style={{ paddingHorizontal: gutter, paddingTop: 16 }}><BoutonRetour /></View>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  // La clé remonte la fiche quand l'inscription change côté serveur
  // (rafraîchissement) : l'état local repart de la réponse.
  return <Fiche key={`${e.id}-${e.deja_inscrit}`} e={e} onRafraichir={charger} />;
}

function Fiche({ e, onRafraichir }: { e: EvenementCultureDetail; onRafraichir: () => Promise<void> }) {
  const { c } = useTheme();
  const { top } = useSafeAreaInsets();
  const { etat, inscrits, inscrire, annuler } = useInscriptionCulture(e);
  const [invitation, setInvitation] = useState(false);

  const places = inscrits === null ? e.places : { ...e.places, inscrits, complet: e.places.quota > 0 && inscrits >= e.places.quota };
  const pct = pourcentagePlaces(places);
  const inscrit = etat === 'inscrit';
  const periode = surPlusieursJours(ts(e.date_heure), ts(e.date_fin));

  async function partager() {
    try {
      await Share.share({
        title: e.titre,
        message: `${e.titre} — ${e.lieu} · ${dateFr(e.date_heure, 'D j M')}${e.lien ? `\n${e.lien}` : ''}\nÀ découvrir dans l'espace Culture d'Outsee.`,
      });
    } catch {
      // Partage annulé : rien à signaler.
    }
  }

  async function seDesinscrire() {
    if (!(await confirmer('Te désinscrire de cet événement ?'))) return;
    await annuler();
  }

  const carteInfo = { flex: 1, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, padding: 16 } as const;

  return (
    <Ecran
      avecBarre={false}
      plein
      onRafraichir={onRafraichir}
      horsDefilement={<FeuilleInvitation cible={invitation ? { type: 'culture', id: e.id, nom: e.titre } : null} onClose={() => setInvitation(false)} />}
    >
      <View style={{ borderBottomLeftRadius: rayon.xl, borderBottomRightRadius: rayon.xl, overflow: 'hidden', backgroundColor: c.blanc }}>
        <Rayures a={c.blanc} b={c.surface2} />
        <View style={{ paddingTop: 100 + top, paddingHorizontal: 20, paddingBottom: 32 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 10 }}>
            {e.en_cours ? <PastilleEnCours quoi="Événement" /> : null}
            <Text style={{ fontFamily: mono(500), fontSize: fs[2], letterSpacing: 0.1 * fs[2], textTransform: 'uppercase', color: c.surRougeClair, flexShrink: 1 }}>
              {etiquetteCulture(e)}
            </Text>
          </View>
          <View accessibilityRole="header">
            <Display taille={fs[9]} interligne={lh.display}>{e.titre}</Display>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 10 }}>
              <Icone nom="musee" taille={18} couleur={c.grisFonce} />
              <T taille={fs[5]} poids={600} couleur={c.grisFonce} style={{ flexShrink: 1 }}>{e.organisateur}</T>
            </View>
          </View>
          <View style={{ alignSelf: 'flex-start', marginTop: 18, backgroundColor: c.rouge, borderRadius: rayon.pill, paddingVertical: 8, paddingHorizontal: 20 }}>
            <Display taille={fs[7]} interligne={1.2} couleur={fixe.surAccent}>{libellePrix(e.prix)}</Display>
          </View>
        </View>
      </View>

      <BoutonRetour media style={{ position: 'absolute', top: top + 36, left: 20 }} />
      <Pressable
        onPress={partager}
        accessibilityRole="button"
        style={{ position: 'absolute', top: top + 36, right: 20, flexDirection: 'row', alignItems: 'center', gap: 7, minHeight: 44, paddingHorizontal: 18, borderRadius: rayon.pill, backgroundColor: fixe.basalte }}
      >
        <Icone nom="partage" taille={16} couleur={fixe.craie} />
        <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: fixe.craie }}>Partager</Text>
      </Pressable>

      <View style={{ paddingHorizontal: gutter, marginTop: 24 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flexShrink: 1 }}>
            <Icone nom="personnes" taille={20} couleur={c.grisFonce} />
            <T taille={fs[4]} poids={700}>
              {places.quota > 0 ? `${places.inscrits} / ${places.quota} inscrits` : `${places.inscrits} inscrit${places.inscrits > 1 ? 's' : ''} · ${libellePlaces(places)}`}
            </T>
          </View>
          {pct !== null ? <Text style={{ fontFamily: mono(600), fontSize: fs[4], color: c.surRougeClair }}>{pct}%</Text> : null}
        </View>
        {pct !== null ? <Jauge pourcentage={pct} piste={c.grisClair} remplissage={c.rouge} hauteur={8} /> : null}

        {e.description ? (
          <View style={{ marginTop: 32 }}>
            <Section icone="info">À propos</Section>
            <T taille={fs[5]} interligne={lh.relaxed} couleur={c.grisFonce}>{e.description}</T>
          </View>
        ) : null}

        {e.lien ? (
          <Pressable
            onPress={() => WebBrowser.openBrowserAsync(e.lien!)}
            accessibilityRole="link"
            style={{ marginTop: 20, flexDirection: 'row', alignItems: 'center', gap: 8, minHeight: 44 }}
          >
            <Icone nom="lien" taille={16} couleur={c.surRougeClair} />
            <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: c.surRougeClair }}>Voir la page officielle</Text>
          </Pressable>
        ) : null}

        {e.amis_detail.length ? (
          <View style={{ marginTop: 32 }}>
            <Section>Tes potes qui y vont</Section>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 10 }}>
              {e.amis_detail.map((f) => (
                <Pressable key={f.id} onPress={() => router.push({ pathname: '/etudiant/[id]', params: { id: String(f.id) } })}
                  style={{ flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.pill, paddingVertical: 5, paddingLeft: 5, paddingRight: 14 }}>
                  <Avatar photo={f.photo_url} prenom={f.prenom} taille={24} />
                  <T taille={fs[3]} poids={600}>{f.prenom}</T>
                </Pressable>
              ))}
            </View>
          </View>
        ) : null}

        <View style={{ flexDirection: 'row', gap: 12, marginTop: 24 }}>
          <View style={carteInfo}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 8 }}>
              <Icone nom="horloge" taille={16} couleur={c.gris} />
              <Mono>{periode ? 'Période' : 'Date & heure'}</Mono>
            </View>
            {periode ? (
              <>
                <T taille={fs[4]} poids={700}>Du {dateFr(e.date_heure, 'j M')}</T>
                <T taille={fs[4]} poids={700}>au {dateFr(e.date_fin, 'j M Y')}</T>
              </>
            ) : (
              <>
                <T taille={fs[4]} poids={700}>{dateFr(e.date_heure, 'j M Y')}</T>
                <Text style={{ fontFamily: mono(400), fontSize: fs[4], color: c.grisFonce }}>
                  {heure(e.date_heure)}{e.date_fin ? ` – ${heure(e.date_fin)}` : ''}
                </Text>
              </>
            )}
          </View>
          <View style={carteInfo}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 8 }}>
              <Icone nom="epingle" taille={16} couleur={c.gris} />
              <Mono>Lieu</Mono>
            </View>
            <T taille={fs[4]} poids={700}>{e.lieu}</T>
            <T taille={fs[3]} couleur={c.gris}>{e.ville}</T>
          </View>
        </View>

        <View style={{ marginTop: 40, marginBottom: 60, gap: 12 }}>
          <Bouton
            plein
            testID="culture-inscription"
            libelle={libelleBoutonCulture(etat === 'attente' ? 'libre' : etat, places.complet, e.termine)}
            icone={inscrit ? 'check' : undefined}
            variante={inscrit ? 'contour' : 'primaire'}
            desactive={inscrit || places.complet || e.termine}
            chargement={etat === 'attente'}
            onPress={inscrire}
            style={{ paddingVertical: 18 }}
          />
          {!e.termine ? (
            <Bouton plein variante="contour" icone="ajout-personne" libelle="Inviter des potes" onPress={() => setInvitation(true)} />
          ) : null}
          {inscrit && !e.termine ? (
            <Bouton variante="texte" libelle="Me désinscrire" onPress={seDesinscrire} style={{ alignSelf: 'center' }} />
          ) : null}
        </View>
      </View>
    </Ecran>
  );
}
