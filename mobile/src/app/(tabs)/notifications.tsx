/**
 * Notifications en pleine page — notifications.php.
 *
 * Le rond de retour, « Notifications », puis les éléments rangés en
 * « Aujourd'hui », « Cette semaine » et « Plus tôt ». Le point lave marque ce
 * qui est arrivé depuis la visite précédente ; ouvrir la page vaut lecture,
 * comme sur le site.
 */

import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, type Notification } from '../../api';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { BoutonRetour } from '../../composants/Elements';
import { ElementNotification, NotificationsVides } from '../../composants/Social';
import { Display, Mono } from '../../composants/Texte';
import { useJeton } from '../../session';
import { fs, lh } from '../../theme';
import { useTheme } from '../../useTheme';

const SECTIONS = ["Aujourd'hui", 'Cette semaine', 'Plus tôt'];

export default function Notifications() {
  const { c } = useTheme();
  const jeton = useJeton();
  const [items, setItems] = useState<Notification[] | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const r = await api.notifications(jeton);
      setItems(r.items);
      // Les pastilles sont calculées sur la lecture précédente ; celle-ci est
      // enregistrée pour la prochaine visite.
      api.marquerLues(jeton).catch(() => {});
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 14, marginBottom: 18 }}>
          <BoutonRetour vers="/" libelle="Retour au hub" />
        </View>
        <Display taille={fs[8]} interligne={lh.tight} accessibilityRole="header">Notifications</Display>
      </EnTete>

      <Contenu style={{ paddingTop: 0 }}>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : items === null ? <Chargement /> : (
          <>
            {SECTIONS.map((titre) => {
              const liste = items.filter((n) => n.section === titre);
              if (!liste.length) return null;
              return (
                <View key={titre} style={{ marginTop: 22 }} accessibilityLabel={titre}>
                  <Mono style={{ marginBottom: 10 }}>{titre}</Mono>
                  <View style={{ gap: 8 }}>
                    {liste.map((n, i) => (
                      <View key={`${n.type}-${n.ts}-${i}`}>
                        <ElementNotification n={n} onTraitee={() => setItems((l) => l && l.filter((x) => x !== n))} />
                        {n.nouvelle ? (
                          <View accessibilityLabel="Nouveau" style={{ position: 'absolute', top: 14, right: 14, width: 8, height: 8, borderRadius: 4, backgroundColor: c.rouge }} />
                        ) : null}
                      </View>
                    ))}
                  </View>
                </View>
              );
            })}
            {items.length === 0 ? <NotificationsVides onLien={() => router.navigate({ pathname: '/', params: { vue: 'people' } })} /> : null}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}
