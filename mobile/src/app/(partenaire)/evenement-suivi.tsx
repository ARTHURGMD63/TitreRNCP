/**
 * Suivi d'un événement — api/v1/partenaire_evenement_suivi.php.
 *
 * Tout ce qu'un établissement veut savoir d'une soirée, du plus important au
 * plus fin : le remplissage, la présence, la montée en charge des
 * inscriptions, qui vient, la mise en avant (et ses notifications) et ce que
 * l'événement coûte. Aucun chiffre estimé : ils viennent des inscriptions.
 */

import React, { useCallback, useState } from 'react';
import { Text, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api, ErreurApi, type SuiviEvenementPartenaire } from '../../api';
import { Apparition, Compteur } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { CarteRefonte, Pastille, useRubrique } from '../../composants/Design';
import { ChronoMiseEnAvant } from '../../composants/ChronoMiseEnAvant';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { BoutonRetour, Jauge } from '../../composants/Elements';
import { Mono, T, TitreEcran, TitreSection } from '../../composants/Texte';
import { euros } from '../../composants/UsageMois';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { fs, halo, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

const STATUTS = { passe: 'Passé', complet: 'Complet', actif: 'À venir' } as const;

/** Histogramme minimal des inscriptions par jour : une barre par jour. */
function Courbe({ points, couleur, piste }: { points: SuiviEvenementPartenaire['courbe']; couleur: string; piste: string }) {
  const max = Math.max(1, ...points.map((p) => p.inscriptions));
  return (
    <View style={{ flexDirection: 'row', alignItems: 'flex-end', gap: 3, height: 90 }}>
      {points.map((p) => (
        <View key={p.jour} accessibilityLabel={`${dateFr(p.jour, 'j M')} : ${p.inscriptions} inscription${p.inscriptions > 1 ? 's' : ''}`}
          style={{ flex: 1, minWidth: 3, height: Math.max(4, (p.inscriptions / max) * 90), borderRadius: 3, backgroundColor: p.inscriptions > 0 ? couleur : piste }} />
      ))}
    </View>
  );
}

export default function SuiviEvenement() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const { id } = useLocalSearchParams<{ id?: string }>();

  const [suivi, setSuivi] = useState<SuiviEvenementPartenaire | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setSuivi(await api.partenaireSuiviEvenement(jeton, Number(id)));
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton, id]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  if (!suivi) {
    return <Ecran>{erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}</Ecran>;
  }

  const { evenement: ev, chiffres: ch, courbe, ecoles, mise_en_avant: mea, cout } = suivi;
  const aVenir = ev.statut !== 'passe';

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete style={{ paddingBottom: 4 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 18 }}>
          <BoutonRetour vers="/evenements" libelle="Retour à mes événements" />
        </View>
        <Mono style={{ marginBottom: 8 }}>{dateFr(ev.date_heure, 'l j F · H\\hi')}{ev.lieu ? ` · ${ev.lieu}` : ''}</Mono>
        <TitreEcran avant="Suivi de" surligne={`${ev.titre}.`} style={{ marginBottom: 12 }} />
        <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap', marginBottom: 16 }}>
          <Pastille libelle={STATUTS[ev.statut]} fond={ev.statut === 'actif' ? c.succesClair : c.surface2} encre={ev.statut === 'actif' ? c.succes : c.gris} />
          {ev.is_flash ? <Pastille libelle="Flash" icone="eclair" fond={bleu.doux} encre={bleu.texteDoux} /> : null}
          {ev.is_gratuit ? <Pastille libelle="Gratuit" fond={c.noir} encre={c.surEncre} /> : null}
        </View>
      </EnTete>

      <Contenu style={{ paddingTop: 0 }}>
        {/* L'essentiel : trois compteurs, en carte bleue comme le tableau de bord. */}
        <Apparition index={1}>
          <View style={[{ backgroundColor: bleu.accent, borderRadius: rayon.carte, padding: 14, gap: 14, marginBottom: 16 }, halo(bleu.accent)]}>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              {[
                { l: 'Inscrits', v: ch.inscrits, format: (n: number) => String(Math.round(n)) },
                { l: 'Présents', v: ch.presents, format: (n: number) => String(Math.round(n)) },
                { l: 'Annulés', v: ch.annules, format: (n: number) => String(Math.round(n)) },
              ].map((s, i) => (
                <View key={s.l} style={{ flex: 1, borderRadius: rayon.bouton, padding: 12, backgroundColor: 'rgba(255,255,255,0.16)', gap: 2 }}>
                  <Compteur valeur={s.v} format={s.format} delai={i * 120}
                    style={{ fontFamily: sans(700), fontSize: 20, color: '#FFFFFF', fontVariant: ['tabular-nums'] }} />
                  <T taille={fs[4]} couleur={bleu.surAccent2}>{s.l}</T>
                </View>
              ))}
            </View>
            {aVenir ? (
              <Bouton libelle="Scanner un pass" icone="qr" variante="contour" plein
                onPress={() => router.push({ pathname: '/scan', params: { evenementId: String(ev.id) } })} />
            ) : null}
          </View>
        </Apparition>

        <Apparition index={2}>
          <CarteRefonte>
            <TitreSection>Remplissage et présence</TitreSection>
            <View style={{ gap: 6 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                <T taille={fs[4]} poids={700}>{ch.inscrits}{ch.quota > 0 ? ` / ${ch.quota}` : ''} places</T>
                <T taille={fs[4]} couleur={c.gris}>
                  {ch.taux_remplissage !== null ? `${ch.taux_remplissage} % rempli` : 'Entrée libre'}
                  {ch.places_restantes !== null ? ` · ${ch.places_restantes} restantes` : ''}
                </T>
              </View>
              <Jauge pourcentage={ch.taux_remplissage ?? 0} piste={c.surface2} remplissage={bleu.accent} hauteur={8} />
            </View>
            <View style={{ gap: 6 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                <T taille={fs[4]} poids={700}>{ch.presents} présents</T>
                <T taille={fs[4]} couleur={c.gris}>
                  {ch.taux_presence !== null ? `${ch.taux_presence} % des inscrits` : 'Aucun inscrit'}
                </T>
              </View>
              <Jauge pourcentage={ch.taux_presence ?? 0} piste={c.surface2} remplissage={c.succes} hauteur={8} />
            </View>
          </CarteRefonte>
        </Apparition>

        <Apparition index={3}>
          <CarteRefonte>
            <View>
              <Mono>Depuis la publication · {dateFr(ev.publie_le, 'j M')}</Mono>
              <TitreSection style={{ marginTop: 4 }}>Inscriptions par jour</TitreSection>
            </View>
            {courbe.length ? (
              <>
                <Courbe points={courbe} couleur={bleu.accent} piste={c.surface2} />
                <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                  <T taille={fs[4]} couleur={c.gris}>{dateFr(courbe[0].jour, 'j M')}</T>
                  <T taille={fs[4]} couleur={c.gris}>{dateFr(courbe[courbe.length - 1].jour, 'j M')}</T>
                </View>
              </>
            ) : (
              <T taille={fs[4]} couleur={c.gris}>Aucune inscription pour l&apos;instant.</T>
            )}
          </CarteRefonte>
        </Apparition>

        {ecoles.length ? (
          <Apparition index={4}>
            <CarteRefonte>
              <TitreSection>Qui vient ?</TitreSection>
              {ecoles.map((e) => (
                <View key={e.nom}>
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 }}>
                    <T taille={fs[4]} poids={600}>{e.nom}</T>
                    <T taille={fs[4]} couleur={c.gris}>{e.nombre} · {e.pourcentage} %</T>
                  </View>
                  <Jauge pourcentage={e.pourcentage} piste={c.surface2} remplissage={bleu.accent} hauteur={8} />
                </View>
              ))}
            </CarteRefonte>
          </Apparition>
        ) : null}

        <Apparition index={5}>
          <CarteRefonte>
            <TitreSection>Liste des inscrits ({suivi.participants.length})</TitreSection>
            {suivi.participants.length ? suivi.participants.map((p) => (
              <View key={p.id} style={{ gap: 2, paddingVertical: 6 }}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
                  <T taille={fs[4]} poids={700} style={{ flexShrink: 1 }}>{p.prenom} {p.nom}</T>
                  {p.present ? <T taille={fs[4]} poids={600} couleur={c.succes}>Présent</T> : null}
                </View>
                <T taille={fs[4]} couleur={c.gris} selectable>{p.email}</T>
                {p.ecole || p.promo ? <T taille={fs[4]} couleur={c.gris}>{[p.ecole, p.promo].filter(Boolean).join(' · ')}</T> : null}
              </View>
            )) : <T taille={fs[4]} couleur={c.gris}>Personne n&apos;est encore inscrit.</T>}
          </CarteRefonte>
        </Apparition>

        {mea.active ? (
          <Apparition index={6}>
            <CarteRefonte>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
                <TitreSection>Mise en avant</TitreSection>
                <Pastille libelle={mea.en_cours ? 'En cours' : 'Terminée'} fond={mea.en_cours ? c.succesClair : c.surface2} encre={mea.en_cours ? c.succes : c.gris} />
              </View>
              <T taille={fs[4]} poids={700}>{mea.libelle}{mea.offerte ? ' · offerte' : ''}</T>
              <ChronoMiseEnAvant
                debut={mea.debut}
                jusquau={mea.jusqu_au}
                restantS={mea.restant_s}
                dureeS={mea.duree_s}
                enCours={mea.en_cours}
                couleur={c.gris}
                piste={c.surface2}
                remplissage={c.succes}
              />
              {mea.notifications_incluses > 0 ? (
                <View style={{ gap: 4, marginTop: 4 }}>
                  <T taille={fs[4]} poids={700}>Notifications ({mea.notifications.length} / {mea.notifications_incluses})</T>
                  {mea.notifications.length === 0 ? (
                    <T taille={fs[4]} couleur={c.gris}>Aucune programmée. Elles sont comprises : programme-les depuis « Modifier ».</T>
                  ) : mea.notifications.map((n) => (
                    <T key={n.envoyer_le} taille={fs[4]} couleur={c.gris}>
                      {n.envoyee ? 'Envoyée' : 'Programmée'} · {dateFr(n.envoyer_le, 'l j F · H\\hi')}
                    </T>
                  ))}
                </View>
              ) : null}
            </CarteRefonte>
          </Apparition>
        ) : null}

        <Apparition index={6}>
          <CarteRefonte>
            <TitreSection>Ce que cet événement coûte</TitreSection>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
              <T taille={fs[4]}>Événement</T>
              <T taille={fs[4]} poids={600}>{cout.supplement > 0 ? `${euros(cout.supplement)} HT (supplément)` : 'Compris dans ta formule'}</T>
            </View>
            {mea.active ? (
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
                <T taille={fs[4]}>Mise en avant</T>
                <T taille={fs[4]} poids={600}>{cout.sponsoring > 0 ? `${euros(cout.sponsoring)} HT` : 'Offerte'}</T>
              </View>
            ) : null}
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', borderTopWidth: 1, borderTopColor: c.grisClair, paddingTop: 8 }}>
              <T taille={fs[4]} poids={700}>Total en plus de l&apos;abonnement</T>
              <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: cout.total > 0 ? c.danger : c.succes }}>
                {cout.total > 0 ? `${euros(cout.total)} HT` : '0 €'}
              </Text>
            </View>
          </CarteRefonte>
        </Apparition>

        <Bouton libelle="Modifier l'événement" icone="crayon" variante="contour" plein
          onPress={() => router.push({ pathname: '/evenement-form', params: { id: String(ev.id) } })} />
      </Contenu>
    </Ecran>
  );
}
