/**
 * Mes événements — partenaire/evenements.php.
 *
 * La liste de tout ce que l'établissement a publié : titre, type, date,
 * remplissage, check-in, statut dérivé (passé/complet/actif), modifier et
 * supprimer.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, ErreurApi, type EvenementPartenaire } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Badge, Jauge } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { dateFr, majuscules } from '../../format';
import { useJeton } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const LIBELLES_TYPE: Record<string, string> = { bar: 'Bar', boite: 'Boîte', resto: 'Resto', afterwork: 'Afterwork' };
const LIBELLES_STATUT: Record<EvenementPartenaire['statut'], string> = { passe: 'Passé', complet: 'Complet', actif: 'Actif' };

function CarteEvenementPartenaire({ ev, onSupprime }: { ev: EvenementPartenaire; onSupprime: () => void }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [suppression, setSuppression] = useState(false);
  const pct = ev.quota > 0 ? Math.round((ev.inscrits / ev.quota) * 100) : 0;

  async function supprimer() {
    if (!(await confirmer('Supprimer cet événement ?'))) return;
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

  const couleurStatut = ev.statut === 'actif' ? c.succes : ev.statut === 'complet' ? c.danger : c.gris;

  return (
    <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 16, marginBottom: 12 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 10, marginBottom: 8 }}>
        <View style={{ flex: 1 }}>
          <T taille={fs[5]} poids={700} style={{ marginBottom: 4 }}>{ev.titre}</T>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
            <Badge libelle={majuscules(LIBELLES_TYPE[ev.type] ?? ev.type)} fond={c.surface2} encre={c.grisFonce} filet={c.line2} />
            {ev.is_flash ? <Badge libelle="FLASH" fond={c.rouge} encre={c.blanc} /> : null}
            {ev.is_gratuit ? <Badge libelle="GRATUIT" fond={c.noir} encre={c.bg} /> : null}
            {ev.is_sponsorise ? <Badge libelle={ev.sponsorise_actif ? 'SPONSORISÉ' : 'SPONSO. TERMINÉ'} fond={c.surface2} encre={c.grisFonce} filet={c.line2} /> : null}
          </View>
        </View>
        <T taille={fs[2]} poids={700} couleur={couleurStatut}>{LIBELLES_STATUT[ev.statut]}</T>
      </View>

      <Mono couleur={c.gris} style={{ marginBottom: 10 }}>{dateFr(ev.date_heure, 'D j M · H\\hi')}</Mono>

      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 12 }}>
        <T taille={fs[3]} poids={700}>{ev.inscrits}/{ev.quota}</T>
        <View style={{ flex: 1 }}><Jauge pourcentage={pct} piste={c.grisClair} remplissage={c.rouge} hauteur={6} /></View>
        <T taille={fs[3]} couleur={c.gris}>{ev.checkin} check-in</T>
      </View>

      <View style={{ flexDirection: 'row', gap: 10 }}>
        <Pressable
          onPress={() => router.push({ pathname: '/evenement-form', params: { id: String(ev.id) } })}
          accessibilityRole="button"
          style={{ flexDirection: 'row', alignItems: 'center', gap: 6, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.pill, paddingVertical: 9, paddingHorizontal: 16 }}
        >
          <Icone nom="outil" taille={14} couleur={c.noir} />
          <T taille={fs[3]} poids={700}>Modifier</T>
        </Pressable>
        <Pressable
          onPress={suppression ? undefined : supprimer}
          accessibilityRole="button"
          style={{ flexDirection: 'row', alignItems: 'center', gap: 6, borderWidth: 1, borderColor: c.dangerClair, borderRadius: rayon.pill, paddingVertical: 9, paddingHorizontal: 16 }}
        >
          <Icone nom="croix" taille={14} couleur={c.danger} />
          <T taille={fs[3]} poids={700} couleur={c.danger}>{suppression ? '...' : 'Supprimer'}</T>
        </Pressable>
      </View>
    </View>
  );
}

export default function EvenementsPartenaire() {
  const jeton = useJeton();
  const [evenements, setEvenements] = useState<EvenementPartenaire[] | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setEvenements((await api.partenaireEvenements(jeton)).evenements);
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
      <EnTete>
        <TitreEcran lignes={[`Mes ${evenements.length}`, `événement${evenements.length > 1 ? 's' : ''}.`]} />
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <Bouton libelle="+ Créer un événement" plein onPress={() => router.push({ pathname: '/evenement-form' })} style={{ marginBottom: 20 }} />
        {evenements.length === 0 ? (
          <T taille={fs[4]} couleur="#67626D" style={{ textAlign: 'center', marginTop: 24 }}>Aucun événement pour l&apos;instant.</T>
        ) : (
          evenements.map((ev) => <CarteEvenementPartenaire key={ev.id} ev={ev} onSupprime={charger} />)
        )}
      </Contenu>
    </Ecran>
  );
}
