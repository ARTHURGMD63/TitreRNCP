/**
 * Mon compte — le numéro de mobile d'un étudiant (v43).
 *
 * Facultatif pour les comptes créés avant la v43, obligatoire à
 * l'inscription depuis. Deux temps, comme l'adresse e-mail : un code SMS
 * part vers le numéro, qui n'est enregistré qu'une fois ce code saisi. Si
 * le serveur n'envoie pas encore de SMS, le numéro est enregistré tout de
 * suite (api/v1/compte.php, actions telephone et telephone_confirmer).
 */

import React, { useState } from 'react';
import { View } from 'react-native';

import { api, ErreurApi } from '../api';
import { useJeton, useSession } from '../session';
import { afficherTelephone, normaliserTelephone } from '../telephone';
import { fs, rayon } from '../theme';
import { useTheme } from '../useTheme';
import { Bouton } from './Bouton';
import { Encart } from './Elements';
import { Champ } from './Formulaire';
import { T, TitreSection } from './Texte';

export function SectionTelephone() {
  const { c } = useTheme();
  const jeton = useJeton();
  const { profil, mettreAJour } = useSession();

  const [saisie, setSaisie] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [code, setCode] = useState('');
  // Non nul entre l'envoi du code et sa saisie : le numéro visé.
  const [enAttente, setEnAttente] = useState<string | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [succes, setSucces] = useState<string | null>(null);
  const [enCours, setEnCours] = useState(false);

  const actuel = profil?.telephone ?? null;

  function enregistre(numero: string) {
    if (profil) mettreAJour({ ...profil, telephone: numero });
    setSucces(`Numéro enregistré : ${afficherTelephone(numero)}.`);
    setEnAttente(null);
    setSaisie('');
    setCode('');
    setMotDePasse('');
  }

  async function demander() {
    setSucces(null);
    const numero = normaliserTelephone(saisie);
    if (!numero) {
      setErreur('Saisis un numéro de mobile français (06 ou 07).');
      return;
    }
    if (!motDePasse) {
      setErreur('Saisis ton mot de passe pour confirmer.');
      return;
    }
    setEnCours(true);
    setErreur(null);
    try {
      const rep = await api.changerTelephone(jeton, motDePasse, numero);
      if (!rep.verification) {
        enregistre(rep.telephone);
        return;
      }
      setEnAttente(rep.telephone);
      if (rep.code_dev) setCode(rep.code_dev);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Envoi impossible, réessaie dans un instant.');
    } finally {
      setEnCours(false);
    }
  }

  async function confirmer() {
    if (!enAttente) return;
    if (code.trim().length !== 6) {
      setErreur('Saisis le code à six chiffres reçu par SMS.');
      return;
    }
    setEnCours(true);
    setErreur(null);
    try {
      const rep = await api.confirmerTelephone(jeton, motDePasse, enAttente, code.trim());
      enregistre(rep.telephone);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Confirmation impossible, réessaie dans un instant.');
    } finally {
      setEnCours(false);
    }
  }

  function annuler() {
    setEnAttente(null);
    setCode('');
    setErreur(null);
  }

  return (
    <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 14, marginBottom: 16 }}>
      <TitreSection style={{ marginBottom: 6 }}>Téléphone mobile</TitreSection>
      <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 14 }}>
        {actuel
          ? `Numéro actuel : ${afficherTelephone(actuel)}`
          : 'Aucun numéro enregistré. Il permet à l’établissement d’un événement auquel tu t’inscris de te joindre à son sujet.'}
      </T>
      {succes ? <Encart genre="ok">{succes}</Encart> : null}
      {erreur ? <Encart genre="erreur">{erreur}</Encart> : null}
      {enAttente ? (
        <>
          <Encart genre="info">{`Un code à six chiffres vient d'être envoyé par SMS au ${afficherTelephone(enAttente)}.`}</Encart>
          <Champ
            etiquette="Code reçu par SMS"
            value={code}
            onChangeText={(v) => setCode(v.replace(/\D/g, '').slice(0, 6))}
            placeholder="123456"
            keyboardType="number-pad"
            autoComplete="sms-otp"
            textContentType="oneTimeCode"
            maxLength={6}
            returnKeyType="go"
            onSubmitEditing={confirmer}
          />
          <Bouton libelle="Confirmer le numéro" plein chargement={enCours} onPress={confirmer} />
          <Bouton libelle="Annuler" variante="contour" plein onPress={annuler} style={{ marginTop: 10 }} />
        </>
      ) : (
        <>
          <Champ
            etiquette={actuel ? 'Nouveau numéro' : 'Ton numéro'}
            value={saisie}
            onChangeText={setSaisie}
            placeholder="06 12 34 56 78"
            keyboardType="phone-pad"
            autoComplete="tel"
            textContentType="telephoneNumber"
          />
          <Champ
            etiquette="Ton mot de passe actuel"
            value={motDePasse}
            onChangeText={setMotDePasse}
            placeholder="••••••••"
            secureTextEntry
            autoComplete="current-password"
            textContentType="password"
          />
          <Bouton libelle="Enregistrer le numéro" plein chargement={enCours} onPress={demander} />
        </>
      )}
    </View>
  );
}
