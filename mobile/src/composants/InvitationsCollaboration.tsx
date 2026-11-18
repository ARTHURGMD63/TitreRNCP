/**
 * Les invitations à co-organiser reçues par un établissement (v39), sur son
 * tableau de bord : accepter ou refuser celles qui attendent, quitter une
 * collaboration déjà acceptée. Rien ne s'affiche quand il n'y en a aucune.
 *
 * Chargé à part du tableau de bord : une erreur ici (serveur d'avant la v39)
 * ne doit pas empêcher d'afficher le reste.
 */

import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { api, ErreurApi, type InvitationCollaboration } from '../api';
import { LIBELLES_TYPE } from '../catalogue';
import { confirmer } from '../confirmer';
import { dateFr } from '../format';
import { useJeton } from '../session';
import { fs, rayon } from '../theme';
import { useTheme } from '../useTheme';
import { Bouton } from './Bouton';
import { CarteRefonte, Pastille, useRubrique } from './Design';
import { Icone } from './Icone';
import { Mono, T, TitreSection } from './Texte';
import { useToast } from './Toast';

export function InvitationsCollaboration() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const toast = useToast();
  const [invitations, setInvitations] = useState<InvitationCollaboration[]>([]);
  const [enCours, setEnCours] = useState<number | null>(null);

  const charger = useCallback(async () => {
    try {
      setInvitations((await api.partenaireInvitationsCollaboration(jeton)).invitations);
    } catch {
      setInvitations([]);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function repondre(inv: InvitationCollaboration, action: 'accepter' | 'refuser' | 'quitter') {
    if (action === 'quitter' && !(await confirmer(`Ne plus co-organiser « ${inv.evenement.titre} » ? Ton nom disparaîtra de l'événement.`))) return;
    setEnCours(inv.id);
    try {
      const rep = await api.partenaireRepondreCollaboration(jeton, inv.id, action);
      toast(rep.message ?? 'Enregistré.', action === 'accepter' ? 'success' : undefined);
      await charger();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setEnCours(null);
    }
  }

  if (!invitations.length) return null;
  const enAttente = invitations.filter((i) => i.statut === 'en_attente').length;

  return (
    <CarteRefonte style={{ gap: 12 }}>
      <View>
        <Mono>Collaborations</Mono>
        <TitreSection style={{ marginTop: 4 }}>
          {enAttente ? `${enAttente} invitation${enAttente > 1 ? 's' : ''} à co-organiser` : 'Tes collaborations à venir'}
        </TitreSection>
      </View>
      {invitations.map((inv) => (
        <View key={inv.id} style={{ backgroundColor: c.surface2, borderRadius: rayon.sm, padding: 12, gap: 8 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <Icone nom="personnes" taille={16} couleur={c.noir} />
            <T taille={fs[4]} couleur={c.gris} style={{ flex: 1 }} numberOfLines={1}>
              <T taille={fs[4]} poids={700}>{inv.hote.nom}</T> · {LIBELLES_TYPE[inv.hote.type] ?? inv.hote.type}
            </T>
            {inv.statut === 'acceptee' ? <Pastille libelle="Acceptée" fond={c.succesClair} encre={c.succes} /> : null}
          </View>
          <T taille={17} poids={700} numberOfLines={2}>{inv.evenement.titre}</T>
          <T taille={fs[4]} couleur={c.gris}>
            {dateFr(inv.evenement.date_heure, 'l j F · H\\hi')}{inv.evenement.lieu ? ` · ${inv.evenement.lieu}` : ''}
          </T>
          {inv.statut === 'en_attente' ? (
            <View style={{ flexDirection: 'row', gap: 8, marginTop: 2 }}>
              <Bouton libelle="Accepter" icone="check" accent={bleu.accent} style={{ flex: 1 }}
                chargement={enCours === inv.id} desactive={enCours !== null} onPress={() => void repondre(inv, 'accepter')} />
              <Bouton libelle="Refuser" variante="contour" style={{ flex: 1 }}
                desactive={enCours !== null} onPress={() => void repondre(inv, 'refuser')} />
            </View>
          ) : (
            <Bouton libelle="Ne plus co-organiser" variante="texte" style={{ alignSelf: 'flex-start' }}
              desactive={enCours !== null} onPress={() => void repondre(inv, 'quitter')} />
          )}
        </View>
      ))}
    </CarteRefonte>
  );
}
