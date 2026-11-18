/**
 * Validation des BDE — api/v1/admin_bde.php.
 *
 * Un compte BDE n'a ni formule ni publication tant qu'un administrateur ne l'a
 * pas validé : la formule BDE coûte moins cher que celles des bars, et rien ne
 * prouve à l'inscription qu'une association en est une. L'administrateur
 * vérifie hors de l'outil (existence du bureau, lien avec une école), puis
 * tranche ici : valider, ou refuser en disant pourquoi — le BDE lit la raison.
 */

import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { api, ErreurApi, type CompteBdeAdmin } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { CarteRefonte, Pastille } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { BoutonRetour } from '../../composants/Elements';
import { Champ } from '../../composants/Formulaire';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { espace, fs } from '../../theme';
import { useTheme } from '../../useTheme';

const ETATS = {
  en_attente: { libelle: 'En attente', fond: 'alerteClair', encre: 'alerte' },
  validee: { libelle: 'Validé', fond: 'succesClair', encre: 'succes' },
  refusee: { libelle: 'Refusé', fond: 'dangerClair', encre: 'danger' },
} as const;

function CarteBde({ b, onChange }: { b: CompteBdeAdmin; onChange: () => void }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [refus, setRefus] = useState(false);
  const [note, setNote] = useState('');
  const [envoi, setEnvoi] = useState(false);

  const etat = ETATS[b.verification as keyof typeof ETATS] ?? ETATS.en_attente;

  async function agir(action: 'valider' | 'refuser' | 'remettre') {
    setEnvoi(true);
    try {
      const rep = await api.adminBde(jeton, { action, id: b.id, note: action === 'refuser' ? note.trim() : undefined });
      toast(rep.message ?? 'Enregistré.', 'success');
      setRefus(false);
      setNote('');
      onChange();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <CarteRefonte>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
        <T taille={17} poids={700} style={{ flexShrink: 1 }}>{b.nom}</T>
        <Pastille libelle={etat.libelle} fond={c[etat.fond]} encre={c[etat.encre]} />
      </View>
      <View style={{ gap: 2 }}>
        <T taille={fs[4]}>{b.contact.prenom} {b.contact.nom} · {b.contact.email}</T>
        <T taille={fs[4]} couleur={c.gris}>
          {b.ville ? `${b.ville} · ` : ''}inscrit le {dateFr(b.inscrit_le, 'j M Y')}
          {b.evenements > 0 ? ` · ${b.evenements} événement${b.evenements > 1 ? 's' : ''}` : ''}
        </T>
        {b.verifie_le ? (
          <T taille={fs[4]} couleur={c.gris}>
            {b.verification === 'validee' ? 'Validé' : 'Traité'} le {dateFr(b.verifie_le, 'j M Y')}{b.verifie_par ? ` par ${b.verifie_par}` : ''}
          </T>
        ) : null}
        {b.note ? <T taille={fs[4]} couleur={c.danger}>Raison : {b.note}</T> : null}
      </View>

      {refus ? (
        <View style={{ gap: 8 }}>
          <Champ etiquette="Raison du refus *" value={note} onChangeText={setNote} multiligne numberOfLines={3}
            aide="Le BDE la verra dans son application." marge={0} />
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Bouton libelle="Confirmer le refus" variante="contour" desactive={note.trim() === ''} chargement={envoi} onPress={() => agir('refuser')} style={{ flex: 1 }} />
            <Bouton libelle="Annuler" variante="contour" onPress={() => setRefus(false)} style={{ flex: 1 }} />
          </View>
        </View>
      ) : (
        <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap' }}>
          {b.verification !== 'validee' ? (
            <Bouton libelle="Valider" chargement={envoi} onPress={async () => {
              if (await confirmer(`Valider « ${b.nom} » ? Il pourra choisir la formule BDE et publier.`)) await agir('valider');
            }} />
          ) : null}
          {b.verification === 'en_attente' ? <Bouton libelle="Refuser" variante="contour" onPress={() => setRefus(true)} /> : null}
          {b.verification !== 'en_attente' ? (
            <Bouton libelle="Remettre en attente" variante="contour" chargement={envoi} onPress={async () => {
              if (await confirmer(
                b.verification === 'validee'
                  ? `Remettre « ${b.nom} » en attente ? Il ne pourra plus publier tant qu'il n'est pas revalidé.`
                  : `Remettre « ${b.nom} » en attente ?`
              )) await agir('remettre');
            }} />
          ) : null}
        </View>
      )}
    </CarteRefonte>
  );
}

export default function ValidationBde() {
  const { c } = useTheme();
  const jeton = useJeton();
  const [comptes, setComptes] = useState<CompteBdeAdmin[] | null>(null);
  const [attente, setAttente] = useState(0);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const rep = await api.adminBdeListe(jeton);
      setComptes(rep.comptes);
      setAttente(rep.en_attente);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  if (!comptes) {
    return <Ecran>{erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}</Ecran>;
  }

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete style={{ paddingBottom: 4 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 18 }}>
          <BoutonRetour vers="/plus" libelle="Retour" />
        </View>
        <Mono style={{ marginBottom: 8 }}>{attente > 0 ? `${attente} en attente` : 'Rien en attente'}</Mono>
        <TitreEcran avant="Valider les" surligne="BDE." style={{ marginBottom: 12 }} />
        <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 16 }}>
          Un BDE n&apos;a ni formule ni publication tant que tu ne l&apos;as pas validé.
        </T>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {comptes.length === 0 ? (
          <CarteRefonte style={{ alignItems: 'center', ...espace.vide }}>
            <T taille={17} poids={700} style={{ textAlign: 'center' }}>Aucun BDE inscrit</T>
            <T taille={fs[4]} couleur={c.gris} style={{ textAlign: 'center' }}>Les nouvelles inscriptions apparaîtront ici.</T>
          </CarteRefonte>
        ) : comptes.map((b) => <CarteBde key={b.id} b={b} onChange={charger} />)}
      </Contenu>
    </Ecran>
  );
}
