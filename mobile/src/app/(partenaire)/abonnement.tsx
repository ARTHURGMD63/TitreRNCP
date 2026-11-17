/**
 * Formule de l'établissement, en lecture seule.
 *
 * L'application ne vend rien (règles Apple 3.1.1 / 3.1.3 et Google Play
 * Billing) : la formule, les événements supplémentaires et les mises en avant
 * se règlent hors de l'application. Cet écran affiche seulement l'état du
 * compte, tel que le serveur le connaît — formule, période offerte, usage du
 * mois — et suit tout seul : un paiement enregistré par le serveur apparaît
 * au prochain chargement de l'écran.
 *
 * Aucun prix, aucun lien d'achat ici : c'est ce que demandent les stores.
 * Reste le passage obligé tant qu'aucune formule n'est active (voir
 * partenaire_dashboard.php, code `abonnement_requis`).
 */

import React, { useCallback, useState } from 'react';
import { Text, View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { api, ErreurApi, type ReponseAbonnementPartenaire } from '../../api';
import { Apparition } from '../../composants/Animations';
import { CarreIcone, EnTeteApp, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Encart } from '../../composants/Elements';
import { T, TitreEcran } from '../../composants/Texte';
import { UsageMois } from '../../composants/UsageMois';
import { useJeton } from '../../session';
import { fs, halo, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

export default function AbonnementPartenaire() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();

  const [donnees, setDonnees] = useState<ReponseAbonnementPartenaire | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.partenaireAbonnement(jeton));
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  if (!donnees) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const { client } = donnees;

  return (
    <Ecran>
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp />
        </Apparition>
        <Apparition index={1}>
          <TitreEcran avant="Ta" surligne="formule." style={{ marginBottom: 16 }} />
        </Apparition>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {!donnees.validation.valide ? (
          <Encart genre={donnees.validation.statut === 'refusee' ? 'erreur' : 'info'}>
            {donnees.validation.statut === 'refusee'
              ? `Ton compte BDE n'a pas été validé${donnees.validation.note ? ` : ${donnees.validation.note}` : '.'} Écris-nous si c'est une erreur.`
              : "Ton compte BDE est en attente de validation par l'équipe Outsee. Tu pourras publier dès qu'il sera validé."}
          </Encart>
        ) : null}

        {client ? (
          <View style={[{ backgroundColor: bleu.accent, borderRadius: rayon.carte, padding: 14, gap: 4, marginBottom: 20 }, halo(bleu.accent)]}>
            <Text style={{ fontFamily: sans(600), fontSize: 12, letterSpacing: 0.06 * 12, textTransform: 'uppercase', color: bleu.surAccent2 }}>Formule en cours</Text>
            <Text style={{ fontFamily: sans(700), fontSize: 19, letterSpacing: -0.03 * 19, color: '#FFFFFF' }}>{client.offre_libelle}</Text>
            {client.essai_jusquau ? (
              <T taille={fs[4]} couleur={bleu.surAccent2}>
                Période offerte jusqu&apos;au {client.essai_jusquau.split('-').reverse().join('/')}
              </T>
            ) : null}
          </View>
        ) : donnees.validation.valide ? (
          <Encart genre="info">
            Aucune formule active sur ce compte pour le moment. Dès qu&apos;elle le sera, ton espace s&apos;ouvrira ici.
          </Encart>
        ) : null}

        {client ? (
          <UsageMois usage={donnees.usage} offreLibelle={client.offre_libelle} essaiJusquau={client.essai_jusquau} club={donnees.est_club} />
        ) : null}

        <View style={{ flexDirection: 'row', gap: 12, alignItems: 'flex-start', backgroundColor: bleu.doux, borderRadius: rayon.carte, padding: 18, marginTop: 4, marginBottom: 20 }}>
          <CarreIcone icone="check" fond={bleu.accent} encre="#FFFFFF" taille={36} />
          <View style={{ flex: 1 }}>
            <T taille={fs[4]} poids={700} couleur={bleu.texteDoux} style={{ marginBottom: 6 }}>Dans toutes les formules</T>
            <T taille={fs[4]} couleur={bleu.texteDoux}>
              {donnees.est_club
                ? 'Créneaux de terrain illimités, page établissement avec photos, tableau de bord des inscrits, scan des pass. Les joueurs règlent leurs terrains chez toi : Outsee ne prend aucune commission.'
                : 'Page établissement avec photos, tableau de bord des inscrits et des présents, scan des pass à l’entrée. Tu fixes ta remise étudiante, y compris à zéro.'}
            </T>
          </View>
        </View>

        <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 18 }}>
          La formule et la facturation ne se gèrent pas dans l&apos;application.
        </T>
      </Contenu>
    </Ecran>
  );
}
