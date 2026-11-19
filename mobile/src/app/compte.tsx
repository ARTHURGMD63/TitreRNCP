/**
 * Mon compte — changer son adresse e-mail et son mot de passe, et régler
 * les notifications (ReglagesNotifications).
 *
 * Valable pour un étudiant comme pour un établissement : c'est le même compte
 * côté serveur (api/v1/compte.php). Les deux changements demandent le mot de
 * passe actuel, parce qu'un téléphone resté ouvert ne doit pas suffire pour
 * prendre le compte de quelqu'un.
 *
 * Changer de mot de passe déconnecte les autres appareils. Changer d'adresse
 * se fait en deux temps : un code part vers la NOUVELLE adresse, et celle du
 * compte ne change qu'une fois ce code saisi ; l'ancienne adresse est alors
 * prévenue.
 */

import React, { useState } from 'react';
import { View } from 'react-native';

import { api, ErreurApi } from '../api';
import { Bouton } from '../composants/Bouton';
import { Contenu, Ecran, EnTete } from '../composants/Ecran';
import { BoutonRetour, Encart } from '../composants/Elements';
import { Champ } from '../composants/Formulaire';
import { ReglagesNotifications } from '../composants/ReglagesNotifications';
import { Display, T, TitreSection } from '../composants/Texte';
import { ecrireALEquipe } from '../contact';
import { useJeton, useSession } from '../session';
import { fs, rayon } from '../theme';
import { useTheme } from '../useTheme';

const MIN_MOT_DE_PASSE = 8;

export default function MonCompte() {
  const { c } = useTheme();
  const jeton = useJeton();
  const { profil, mettreAJour } = useSession();

  const [email, setEmail] = useState('');
  const [mdpEmail, setMdpEmail] = useState('');
  const [erreurEmail, setErreurEmail] = useState<string | null>(null);
  const [succesEmail, setSuccesEmail] = useState<string | null>(null);
  const [enCoursEmail, setEnCoursEmail] = useState(false);
  // Non nul entre la demande et la confirmation : un code est parti vers cette adresse.
  const [adresseEnAttente, setAdresseEnAttente] = useState<string | null>(null);
  const [codeEmail, setCodeEmail] = useState('');

  const [actuel, setActuel] = useState('');
  const [nouveau, setNouveau] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [erreurMdp, setErreurMdp] = useState<string | null>(null);
  const [succesMdp, setSuccesMdp] = useState<string | null>(null);
  const [enCoursMdp, setEnCoursMdp] = useState(false);

  // Premier temps : un code part vers la nouvelle adresse.
  async function demanderCodeEmail() {
    setSuccesEmail(null);
    const adresse = email.trim();
    if (!adresse || !adresse.includes('@')) {
      setErreurEmail('Saisis une adresse e-mail valide.');
      return;
    }
    if (!mdpEmail) {
      setErreurEmail('Saisis ton mot de passe pour confirmer.');
      return;
    }
    setEnCoursEmail(true);
    setErreurEmail(null);
    try {
      const rep = await api.changerEmail(jeton, mdpEmail, adresse);
      setAdresseEnAttente(rep.email);
      // En local rien ne part : le serveur rend le code pour pouvoir tester.
      if (rep.code_dev) setCodeEmail(rep.code_dev);
    } catch (e) {
      setErreurEmail(e instanceof ErreurApi ? e.message : 'Changement impossible, réessaie dans un instant.');
    } finally {
      setEnCoursEmail(false);
    }
  }

  // Second temps : le code reçu à la nouvelle adresse change celle du compte.
  async function confirmerEmail() {
    if (!codeEmail.trim()) {
      setErreurEmail('Saisis le code reçu par e-mail.');
      return;
    }
    setEnCoursEmail(true);
    setErreurEmail(null);
    try {
      const rep = await api.confirmerEmail(jeton, mdpEmail, codeEmail.trim());
      if (profil) mettreAJour({ ...profil, email: rep.email });
      setSuccesEmail(`Adresse changée. Connecte-toi désormais avec ${rep.email}. L'ancienne adresse a reçu un avis.`);
      annulerChangementEmail();
    } catch (e) {
      setErreurEmail(e instanceof ErreurApi ? e.message : 'Confirmation impossible, réessaie dans un instant.');
    } finally {
      setEnCoursEmail(false);
    }
  }

  function annulerChangementEmail() {
    setAdresseEnAttente(null);
    setCodeEmail('');
    setEmail('');
    setMdpEmail('');
    setErreurEmail(null);
  }

  async function changerMotDePasse() {
    setSuccesMdp(null);
    if (!actuel) {
      setErreurMdp('Saisis ton mot de passe actuel.');
      return;
    }
    if (nouveau.length < MIN_MOT_DE_PASSE) {
      setErreurMdp(`Le nouveau mot de passe doit faire au moins ${MIN_MOT_DE_PASSE} caractères.`);
      return;
    }
    if (nouveau !== confirmation) {
      setErreurMdp('Les deux mots de passe ne sont pas identiques.');
      return;
    }
    setEnCoursMdp(true);
    setErreurMdp(null);
    try {
      await api.changerMotDePasse(jeton, actuel, nouveau);
      setSuccesMdp('Mot de passe changé. Tes autres appareils ont été déconnectés.');
      setActuel('');
      setNouveau('');
      setConfirmation('');
    } catch (e) {
      setErreurMdp(e instanceof ErreurApi ? e.message : 'Changement impossible, réessaie dans un instant.');
    } finally {
      setEnCoursMdp(false);
    }
  }

  return (
    <Ecran avecBarre={false}>
      <EnTete>
        <BoutonRetour style={{ marginBottom: 18 }} />
        <Display taille={fs[8]}>Mon{'\n'}compte.</Display>
      </EnTete>

      <Contenu>
        <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 14, marginBottom: 16 }}>
          <TitreSection style={{ marginBottom: 6 }}>Adresse e-mail</TitreSection>
          <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 14 }}>
            Adresse actuelle : {profil?.email ?? '—'}
          </T>
          {succesEmail ? <Encart genre="ok">{succesEmail}</Encart> : null}
          {erreurEmail ? <Encart genre="erreur">{erreurEmail}</Encart> : null}
          {adresseEnAttente ? (
            <>
              <Encart genre="info">
                {`Un code à six chiffres vient d'être envoyé à ${adresseEnAttente}. Saisis-le pour confirmer : l'adresse de ton compte ne change qu'à ce moment-là.`}
              </Encart>
              <Champ
                etiquette="Code reçu par e-mail"
                value={codeEmail}
                onChangeText={setCodeEmail}
                placeholder="123456"
                keyboardType="number-pad"
                autoComplete="one-time-code"
                textContentType="oneTimeCode"
                returnKeyType="go"
                onSubmitEditing={confirmerEmail}
              />
              <Bouton libelle="Confirmer la nouvelle adresse" plein chargement={enCoursEmail} onPress={confirmerEmail} />
              <Bouton libelle="Annuler" variante="contour" plein onPress={annulerChangementEmail} style={{ marginTop: 10 }} />
            </>
          ) : (
            <>
              <Champ
                etiquette="Nouvelle adresse"
                value={email}
                onChangeText={setEmail}
                placeholder="nouvelle@adresse.fr"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                autoComplete="email"
                textContentType="emailAddress"
              />
              <Champ
                etiquette="Ton mot de passe actuel"
                value={mdpEmail}
                onChangeText={setMdpEmail}
                placeholder="••••••••"
                secureTextEntry
                autoComplete="current-password"
                textContentType="password"
              />
              <Bouton libelle="Recevoir un code de confirmation" plein chargement={enCoursEmail} onPress={demanderCodeEmail} />
            </>
          )}
        </View>

        <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 14, marginBottom: 16 }}>
          <TitreSection style={{ marginBottom: 14 }}>Mot de passe</TitreSection>
          {succesMdp ? <Encart genre="ok">{succesMdp}</Encart> : null}
          {erreurMdp ? <Encart genre="erreur">{erreurMdp}</Encart> : null}
          <Champ
            etiquette="Mot de passe actuel"
            value={actuel}
            onChangeText={setActuel}
            placeholder="••••••••"
            secureTextEntry
            autoComplete="current-password"
            textContentType="password"
          />
          <Champ
            etiquette="Nouveau mot de passe"
            aide={`Au moins ${MIN_MOT_DE_PASSE} caractères.`}
            value={nouveau}
            onChangeText={setNouveau}
            placeholder="••••••••"
            secureTextEntry
            autoComplete="new-password"
            textContentType="newPassword"
          />
          <Champ
            etiquette="Confirme le nouveau mot de passe"
            value={confirmation}
            onChangeText={setConfirmation}
            placeholder="••••••••"
            secureTextEntry
            autoComplete="new-password"
            textContentType="newPassword"
            returnKeyType="go"
            onSubmitEditing={changerMotDePasse}
          />
          <Bouton libelle="Changer de mot de passe" plein chargement={enCoursMdp} onPress={changerMotDePasse} />
        </View>

        <ReglagesNotifications />

        <Bouton libelle="Aide et contact" icone="info" variante="contour" plein onPress={() => ecrireALEquipe(profil?.email)} />
      </Contenu>
    </Ecran>
  );
}
