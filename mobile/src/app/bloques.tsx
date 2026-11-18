/**
 * Personnes bloquées — ouverte depuis l'écran Moi.
 *
 * Bloquer quelqu'un le fait disparaître de partout, sa fiche comprise : sans
 * cette liste, le bouton « Débloquer » de la fiche n'était plus atteignable
 * et un blocage devenait définitif. Débloquer passe par api/moderation.php,
 * comme le blocage lui-même ; la personne réapparaît dans l'annuaire, mais
 * les abonnements rompus au moment du blocage ne reviennent pas.
 */

import React, { useCallback, useState } from 'react';
import { Text, View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { actions, api, ErreurApi, type ReponseBlocages } from '../api';
import { Bouton } from '../composants/Bouton';
import { teinteAvatar } from '../composants/Design';
import { Chargement, Contenu, Ecran, EnTete, Erreur } from '../composants/Ecran';
import { Avatar, BoutonRetour } from '../composants/Elements';
import { Display, T } from '../composants/Texte';
import { useToast } from '../composants/Toast';
import { confirmer } from '../confirmer';
import { court } from '../format';
import { useJeton } from '../session';
import { fs, lh, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';

type PersonneBloquee = ReponseBlocages['personnes'][number];

export default function PersonnesBloquees() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();

  const [liste, setListe] = useState<PersonneBloquee[] | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [enCours, setEnCours] = useState<number | null>(null);

  const charger = useCallback(async () => {
    setErreur(null);
    try {
      const rep = await api.blocages(jeton);
      setListe(rep.personnes);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Erreur réseau');
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function debloquer(p: PersonneBloquee) {
    const nom = court(p.prenom, p.nom);
    if (!(await confirmer(`Débloquer ${nom} ? Vous pourrez de nouveau voir vos profils respectifs. Vos anciens abonnements ne reviennent pas.`))) return;
    setEnCours(p.id);
    try {
      const rep = await actions.moderation(jeton, { action: 'unblock', target_id: p.id });
      toast(rep.message || `${nom} est débloqué·e`);
      setListe((l) => (l ?? []).filter((x) => x.id !== p.id));
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setEnCours(null);
    }
  }

  return (
    <Ecran avecBarre={false}>
      <EnTete>
        <BoutonRetour style={{ marginBottom: 18 }} />
        <Display taille={fs[8]}>Personnes{'\n'}bloquées.</Display>
        <T taille={fs[4]} couleur={c.gris} interligne={lh.snug} style={{ marginTop: 10 }}>
          Elles ne voient plus ton profil et tu ne vois plus le leur. Elles ne sont pas prévenues, ni quand tu les bloques, ni quand tu les débloques.
        </T>
      </EnTete>

      <Contenu>
        {erreur ? (
          <Erreur message={erreur} onReessayer={charger} />
        ) : liste === null ? (
          <Chargement />
        ) : liste.length === 0 ? (
          <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 18 }}>
            <T taille={fs[4]} poids={600}>Tu n&apos;as bloqué personne.</T>
            <T taille={fs[3]} couleur={c.gris} interligne={lh.snug} style={{ marginTop: 6 }}>
              Pour bloquer quelqu&apos;un, ouvre son profil puis touche « Bloquer », sous le bouton S&apos;abonner.
            </T>
          </View>
        ) : (
          <View style={{ gap: 10 }}>
            {liste.map((p) => (
              <View key={p.id} testID={`bloque-${p.id}`}
                style={{ backgroundColor: c.blanc, borderRadius: 18, paddingVertical: 12, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                <Avatar photo={p.photo_url} prenom={p.prenom} taille={40} fond={teinteAvatar(p.prenom + p.id)} />
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text numberOfLines={1} style={{ fontFamily: sans(600), fontSize: 15, color: c.noir }}>{court(p.prenom, p.nom)}</Text>
                  <T numberOfLines={1} taille={fs[3]} couleur={c.gris}>{p.ecole ?? ''}{p.promo ? ' · ' + p.promo : ''}</T>
                </View>
                <Bouton libelle="Débloquer" variante="contour" taillePolice={fs[3]} chargement={enCours === p.id}
                  desactive={enCours !== null && enCours !== p.id} onPress={() => debloquer(p)}
                  accessibilityLabel={`Débloquer ${court(p.prenom, p.nom)}`} />
              </View>
            ))}
          </View>
        )}
      </Contenu>
    </Ecran>
  );
}
