/**
 * Supprimer son compte.
 *
 * Exigé par les boutiques d'applications comme par le RGPD (droit à
 * l'effacement) : la suppression se lance depuis l'application, sans écrire à
 * personne.
 *
 *  - étudiant   : effacement immédiat. L'écran dit ce qui part et ce qui reste
 *                 (des présences sans nom, pour les statistiques des
 *                 partenaires), demande le mot de passe, puis une dernière
 *                 confirmation.
 *  - partenaire : un compte établissement porte un abonnement et des soirées
 *                 auxquelles d'autres étudiants sont inscrits. La demande est
 *                 transmise à l'équipe ; le compte reste ouvert d'ici là.
 */

import React, { useState } from 'react';
import { View } from 'react-native';
import { router } from 'expo-router';

import { api, ErreurApi } from '../api';
import { Bouton } from '../composants/Bouton';
import { Contenu, Ecran, EnTete } from '../composants/Ecran';
import { BoutonRetour, Encart } from '../composants/Elements';
import { Champ } from '../composants/Formulaire';
import { Display, Mono, T } from '../composants/Texte';
import { confirmer } from '../confirmer';
import { useJeton, useSession } from '../session';
import { fs, lh } from '../theme';
import { useTheme } from '../useTheme';

const EFFACE = [
  'Ton nom, ton email, ta photo, ta ville et ta date de naissance',
  'Tes centres d’intérêt, tes abonnements et tes abonnés',
  'Tes squads, tes avis et tes photos de soirée',
  'Tes pass à venir (les places sont libérées)',
];

function Liste({ titre, items }: { titre: string; items: string[] }) {
  const { c } = useTheme();
  return (
    <View style={{ marginBottom: 20 }}>
      <Mono couleur={c.gris} style={{ marginBottom: 8 }}>{titre}</Mono>
      {items.map((item) => (
        <View key={item} style={{ flexDirection: 'row', gap: 10, marginBottom: 6 }}>
          <T taille={fs[4]} couleur={c.gris}>•</T>
          <T taille={fs[4]} couleur={c.grisFonce} interligne={lh.normal} style={{ flex: 1 }}>{item}</T>
        </View>
      ))}
    </View>
  );
}

export default function SupprimerCompte() {
  const { c } = useTheme();
  const jeton = useJeton();
  const { profil, deconnexion } = useSession();
  const partenaire = profil?.type === 'partenaire';

  const [motDePasse, setMotDePasse] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);
  const [enCours, setEnCours] = useState(false);
  const [demandeEnvoyee, setDemandeEnvoyee] = useState(false);

  async function supprimer() {
    if (!motDePasse) {
      setErreur('Saisis ton mot de passe pour confirmer.');
      return;
    }
    const question = partenaire
      ? 'Envoyer la demande de suppression de ce compte établissement ?'
      : 'Supprimer définitivement ton compte ? Cette action ne peut pas être annulée.';
    if (!(await confirmer(question))) return;

    setEnCours(true);
    setErreur(null);
    try {
      const rep = await api.supprimerCompte(jeton, motDePasse);
      if (rep.supprime) {
        // Le compte n'existe plus : on repart de zéro. La déconnexion vide la
        // session, et la mise en page racine renvoie vers la connexion.
        await deconnexion();
      } else {
        setDemandeEnvoyee(true);
        setMotDePasse('');
      }
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Suppression impossible, réessaie dans un instant.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <Ecran avecBarre={false}>
      <EnTete>
        <BoutonRetour style={{ marginBottom: 18 }} />
        <Display taille={fs[8]}>Supprimer{'\n'}mon compte.</Display>
      </EnTete>

      <Contenu>
        {demandeEnvoyee ? (
          <Encart genre="ok">
            Demande envoyée. L’équipe Linkee la traite et te répond par email. D’ici là, ton compte reste ouvert.
          </Encart>
        ) : (
          <>
            {partenaire ? (
              <T taille={fs[4]} couleur={c.grisFonce} interligne={lh.normal} style={{ marginBottom: 20 }}>
                Un compte établissement porte un abonnement et des soirées auxquelles des étudiants sont inscrits. Pour ne rien casser, la suppression est traitée par l’équipe Linkee : ta demande lui est transmise, avec ton mot de passe comme confirmation.
              </T>
            ) : (
              <>
                <Encart genre="alerte">
                  Cette action est définitive. Ton compte ne pourra pas être récupéré.
                </Encart>

                <Liste titre="Ce qui est effacé" items={EFFACE} />
                <Liste
                  titre="Ce qui reste, sans ton nom"
                  items={['Tes présences passées aux soirées, avec ton école et ta promo, qui servent aux statistiques des établissements. Elles ne désignent plus personne.']}
                />
              </>
            )}

            {erreur ? <Encart genre="erreur">{erreur}</Encart> : null}

            <Champ
              etiquette="Ton mot de passe"
              value={motDePasse}
              onChangeText={setMotDePasse}
              placeholder="••••••••"
              secureTextEntry
              autoComplete="current-password"
              textContentType="password"
              returnKeyType="go"
              onSubmitEditing={supprimer}
            />

            <Bouton
              libelle={partenaire ? 'Envoyer la demande' : 'Supprimer définitivement'}
              variante="alerte"
              plein
              onPress={supprimer}
              chargement={enCours}
              style={{ marginTop: 8 }}
            />
            <Bouton libelle="Annuler" variante="contour" plein onPress={() => router.back()} style={{ marginTop: 12 }} />
          </>
        )}
      </Contenu>
    </Ecran>
  );
}
