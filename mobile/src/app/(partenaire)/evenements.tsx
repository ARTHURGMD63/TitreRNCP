/**
 * Mes événements — partenaire/evenements.php.
 *
 * La liste de tout ce que l'établissement a publié : titre, type, date,
 * remplissage, check-in, statut dérivé (passé/complet/actif), modifier et
 * supprimer.
 *
 * Refonte 2026-11 : logo en tête, titre surligné, cartes blanches sans
 * bordure — pastilles de type et de statut, date à l'encre, jauge, puis les
 * trois actions en boutons de 44 px.
 */

import React, { useCallback, useState } from 'react';
import { ActivityIndicator, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, ErreurApi, type EvenementPartenaire, type UsageMoisPartenaire } from '../../api';
import { FORMATS_SPORT, SPORTS } from '../../catalogue';
import { Apparition, PressableAnime } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { CarreIcone, CarteRefonte, EnTeteApp, Pastille, useRubrique } from '../../composants/Design';
import { ResteMiseEnAvant } from '../../composants/ChronoMiseEnAvant';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Jauge } from '../../composants/Elements';
import { Icone, type NomIcone } from '../../composants/Icone';
import { T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { UsageMois } from '../../composants/UsageMois';
import { confirmer } from '../../confirmer';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { espace, fs, hauteurBouton, rayon, sans, taille } from '../../theme';
import { useTheme } from '../../useTheme';

const LIBELLES_TYPE: Record<string, string> = { bar: 'Bar', boite: 'Boîte', resto: 'Resto', afterwork: 'Afterwork', sport: 'Sport' };
const LIBELLES_STATUT: Record<EvenementPartenaire['statut'], string> = { passe: 'Passé', complet: 'Complet', actif: 'Actif', annule: 'Annulé' };

/**
 * Un bouton d'action de carte : 44 px, rayon 16. En cours, la roue prend la
 * place de l'icône et le libellé reste : la largeur ne bouge pas.
 */
function Action({ libelle, icone, onPress, fond, encre, filet, testID, chargement }: {
  libelle: string; icone: NomIcone; onPress?: () => void; fond: string; encre: string; filet?: string; testID?: string; chargement?: boolean;
}) {
  return (
    <PressableAnime
      onPress={onPress}
      disabled={!onPress || chargement}
      testID={testID}
      accessibilityRole="button"
      accessibilityLabel={libelle}
      accessibilityState={{ disabled: !onPress || !!chargement, busy: !!chargement }}
      style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, minHeight: hauteurBouton.normal, paddingHorizontal: 16, borderRadius: rayon.bouton, backgroundColor: fond, borderWidth: filet ? 1.5 : 0, borderColor: filet }}
    >
      {chargement ? <ActivityIndicator size="small" color={encre} style={{ width: 16, height: 16 }} /> : <Icone nom={icone} taille={16} couleur={encre} />}
      <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: encre }}>{libelle}</Text>
    </PressableAnime>
  );
}

function CarteEvenementPartenaire({ ev, onSupprime }: { ev: EvenementPartenaire; onSupprime: () => void }) {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const toast = useToast();
  const [suppression, setSuppression] = useState(false);
  const [annulation, setAnnulation] = useState(false);
  const pct = ev.quota > 0 ? Math.round((ev.inscrits / ev.quota) * 100) : 0;

  async function supprimer() {
    const aDesAchats = ev.supplement_tarif > 0 || ev.sponsor_tarif > 0 || ev.is_flash || ev.is_sponsorise;
    if (!(await confirmer(
      aDesAchats
        ? 'Supprimer cet événement ? Le quota, l\'offre flash et ce que tu as acheté ne sont pas rendus.'
        : 'Supprimer cet événement ? Il compte toujours dans ton quota du mois.'
    ))) return;
    setSuppression(true);
    try {
      await api.partenaireSupprimerEvenement(jeton, ev.id);
      toast('Événement supprimé.');
      onSupprime();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setSuppression(false);
    }
  }

  // Des pass en cours : la suppression est refusée (PAY-10), on annule et
  // les inscrits sont prévenus.
  async function annuler() {
    if (!(await confirmer(
      ev.inscrits === 1
        ? 'Annuler cet événement ? La personne inscrite sera prévenue et son pass annulé. C\'est définitif.'
        : `Annuler cet événement ? Les ${ev.inscrits} inscrits seront prévenus et leurs pass annulés. C'est définitif.`
    ))) return;
    setAnnulation(true);
    try {
      const rep = await api.partenaireAnnulerEvenement(jeton, ev.id);
      toast(rep.message);
      onSupprime();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAnnulation(false);
    }
  }

  const annule = ev.statut === 'annule';
  const statut = ev.statut === 'actif'
    ? { fond: c.succesClair, encre: c.succes, point: '#2BD67B' }
    : ev.statut === 'complet' || annule
      ? { fond: c.dangerClair, encre: c.danger, point: undefined }
      : { fond: c.surface2, encre: c.gris, point: undefined };

  // Passé : la carte recule (fond transparent, simple filet) au lieu de
  // pâlir — une opacité de 75 % faisait tomber le texte gris sous 4,5:1.
  return (
    <CarteRefonte style={ev.statut === 'passe' || annule ? { backgroundColor: 'transparent', borderWidth: 1.5, borderColor: c.line2 } : undefined}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, flexShrink: 1 }}>
          <Pastille libelle={ev.sport ? (SPORTS[ev.sport] ?? ev.sport) : (LIBELLES_TYPE[ev.type] ?? ev.type)} fond={c.surface2} encre={c.noir} />
          {ev.format ? <Pastille libelle={FORMATS_SPORT[ev.format] ?? ev.format} fond={c.surface2} encre={c.noir} /> : null}
          {ev.is_flash ? <Pastille libelle="Flash" icone="eclair" fond={bleu.doux} encre={bleu.texteDoux} /> : null}
          {ev.is_gratuit ? <Pastille libelle="Gratuit" fond={c.noir} encre={c.surEncre} /> : null}
          {ev.is_sponsorise ? <Pastille libelle={ev.sponsorise_actif ? 'Sponsorisé' : 'Sponso. terminé'} fond={c.surface2} encre={c.grisFonce} /> : null}
          {/* Aucun montant côté application (stores, constat MOB-05) : la pastille dit seulement que c'est en plus de la formule. */}
          {ev.supplement_tarif > 0 ? <Pastille libelle="Supplément" fond={c.alerteClair} encre={c.alerte} /> : null}
          {ev.is_sponsorise && ev.sponsor_tarif > 0 ? <Pastille libelle="Sponso. en plus" fond={c.alerteClair} encre={c.alerte} /> : null}
        </View>
        {ev.is_sponsorise && ev.sponsorise_actif ? (
          <View style={{ gap: 2, marginTop: 6 }}>
            {ev.sponsor_debut ? <T taille={fs[4]} couleur={c.gris}>Lancée le {dateFr(ev.sponsor_debut, 'l j F · H\\hi')}</T> : null}
            <ResteMiseEnAvant restantS={ev.sponsor_restant_s} couleur={c.grisFonce} />
          </View>
        ) : null}
        <Pastille libelle={LIBELLES_STATUT[ev.statut]} point={statut.point} fond={statut.fond} encre={statut.encre} />
      </View>

      <View style={{ gap: 6 }}>
        <Text style={{ fontFamily: sans(700), fontSize: taille.titreCarteSport, lineHeight: taille.titreCarteSport * 1.15, letterSpacing: -0.03 * taille.titreCarteSport, color: c.noir }}>{ev.titre}</Text>
        <T taille={fs[4]} couleur={c.gris}>{dateFr(ev.date_heure, 'l j F · H\\hi')}</T>
        {annule && ev.annule_motif ? <T taille={fs[4]} couleur={c.danger}>Motif : {ev.annule_motif}</T> : null}
        {ev.collaboration ? (
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
            <Icone nom="personnes" taille={14} couleur={c.gris} />
            <T taille={fs[4]} couleur={c.gris} style={{ flexShrink: 1 }} numberOfLines={1}>
              Avec <T taille={fs[4]} poids={700}>{ev.collaboration.nom}</T>
              {ev.collaboration.statut === 'acceptee' ? '' : ev.collaboration.statut === 'refusee' ? ' · invitation refusée' : ' · en attente de réponse'}
            </T>
          </View>
        ) : null}
      </View>

      <View style={{ gap: 8 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <T taille={fs[4]} poids={700}>{ev.inscrits}/{ev.quota} inscrits</T>
          <T taille={fs[4]} couleur={c.gris}>{ev.checkin} check-in</T>
        </View>
        <Jauge pourcentage={pct} piste={c.surface2} remplissage={bleu.accent} hauteur={8} />
      </View>

      <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap', marginTop: 4 }}>
        <Action libelle="Suivi" icone="activite" fond={bleu.doux} encre={bleu.texteDoux}
          onPress={() => router.push({ pathname: '/evenement-suivi', params: { id: String(ev.id) } })} />
        {ev.statut !== 'passe' && !annule ? (
          <Action libelle="Scanner" icone="qr" fond={bleu.accent} encre="#FFFFFF"
            onPress={() => router.push({ pathname: '/scan', params: { evenementId: String(ev.id) } })} />
        ) : null}
        {/* Terminé : il ne se modifie plus (le redater le republiait hors quota). */}
        {!annule && ev.statut !== 'passe' ? (
          <Action libelle="Modifier" icone="crayon" fond="transparent" encre={c.noir} filet={c.line2}
            onPress={() => router.push({ pathname: '/evenement-form', params: { id: String(ev.id) } })} />
        ) : null}
        {!annule && ev.statut !== 'passe' && ev.inscrits > 0 ? (
          <Action libelle="Annuler" icone="croix" fond="transparent" encre={c.danger} filet={c.dangerClair}
            chargement={annulation} onPress={annuler} />
        ) : !annule && ev.inscrits === 0 ? (
          <Action libelle="Supprimer" icone="croix" fond="transparent" encre={c.danger} filet={c.dangerClair}
            chargement={suppression} onPress={supprimer} />
        ) : null}
      </View>
    </CarteRefonte>
  );
}

export default function EvenementsPartenaire() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const [evenements, setEvenements] = useState<EvenementPartenaire[] | null>(null);
  const [usage, setUsage] = useState<UsageMoisPartenaire | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const rep = await api.partenaireEvenements(jeton);
      setEvenements(rep.evenements);
      setUsage(rep.usage);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  if (!evenements) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp />
        </Apparition>
        <Apparition index={1}>
          <TitreEcran avant={`Mes ${evenements.length}`} surligne={`événement${evenements.length > 1 ? 's' : ''}.`} style={{ marginBottom: 16 }} />
        </Apparition>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {usage ? <Apparition index={2}><UsageMois usage={usage} /></Apparition> : null}
        <Apparition index={2}>
          <Bouton libelle="Créer un événement" icone="plus" plein onPress={() => router.push({ pathname: '/evenement-form' })} style={{ marginBottom: 16 }} />
        </Apparition>
        {evenements.length === 0 ? (
          <CarteRefonte style={{ alignItems: 'center', ...espace.vide }}>
            <CarreIcone icone="calendrier" fond={bleu.doux} encre={bleu.texteDoux} />
            <T taille={17} poids={700} style={{ textAlign: 'center' }}>Aucun événement pour l&apos;instant</T>
            <T taille={fs[4]} couleur={c.gris} style={{ textAlign: 'center' }}>Publie ta première soirée : elle apparaîtra dans le fil des étudiants.</T>
          </CarteRefonte>
        ) : (
          evenements.map((ev, i) => (
            <Apparition key={ev.id} index={3 + Math.min(i, 3)}>
              <CarteEvenementPartenaire ev={ev} onSupprime={charger} />
            </Apparition>
          ))
        )}
      </Contenu>
    </Ecran>
  );
}
