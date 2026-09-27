/**
 * Tableau de bord partenaire — partenaire/dashboard.php.
 *
 * La prochaine soirée, inscrits/check-in, âge moyen, qui vient ce soir
 * (répartition par école), le conseil du jour, les dernières inscriptions,
 * et l'accès au scan des pass.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, ErreurApi, type ReponseDashboardPartenaire } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, Jauge } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { Display, Mono, T, TitreEcran } from '../../composants/Texte';
import { useJeton } from '../../session';
import { fixe, fs, lh, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

const COULEURS_ECOLE = ['#FF5424', '#5B8CFF', '#C8F547', '#FFC23D', '#8A858F'];

function Carte({ children, style }: { children: React.ReactNode; style?: object }) {
  const { c, ombre } = useTheme();
  return (
    <View style={[{ backgroundColor: c.blanc, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, padding: 20, marginBottom: 16 }, ombre('base'), style]}>
      {children}
    </View>
  );
}

export default function TableauDeBordPartenaire() {
  const { c } = useTheme();
  const jeton = useJeton();

  const [donnees, setDonnees] = useState<ReponseDashboardPartenaire | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.partenaireDashboard(jeton));
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
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

  const { etablissement, evenement, stats } = donnees;

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete>
        <Mono couleur={c.gris} style={{ marginBottom: 6 }}>{etablissement.nom.toUpperCase()} · {etablissement.ville.toUpperCase()}</Mono>
        <TitreEcran lignes={[`${stats.total} étudiant${stats.total > 1 ? 's' : ''},`, 'en route.']} />
      </EnTete>

      <Contenu style={{ paddingTop: 0 }}>
        {!evenement ? (
          <Carte>
            <T taille={fs[4]} poids={600} style={{ marginBottom: 4 }}>Aucune soirée à venir</T>
            <T taille={fs[3]} couleur={c.gris}>Crée un événement pour voir ses statistiques ici.</T>
          </Carte>
        ) : (
          <>
            <View style={{ flexDirection: 'row', gap: 10, marginBottom: 16 }}>
              <View style={{ flex: 1, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, padding: 14 }}>
                <Mono style={{ marginBottom: 6 }}>Inscrits</Mono>
                <Display taille={fs[7]}>{stats.inscrits}</Display>
              </View>
              <View style={{ flex: 1, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, padding: 14 }}>
                <Mono couleur={c.gris} style={{ marginBottom: 6 }}>Check-in</Mono>
                <Display taille={fs[7]}>{stats.checkin}</Display>
              </View>
              <View style={{ flex: 1, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, padding: 14 }}>
                <Mono style={{ marginBottom: 6 }}>Âge moy.</Mono>
                <Display taille={fs[7]}>{stats.age_moyen}</Display>
              </View>
            </View>

            <Pressable
              onPress={() => router.push({ pathname: '/scan', params: { evenementId: String(evenement.id) } })}
              accessibilityRole="button"
              style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, backgroundColor: c.rouge, borderRadius: rayon.pill, paddingVertical: 16, marginBottom: 16 }}
            >
              <Icone nom="qr" taille={18} couleur={fixe.surLave} />
              <Text style={{ fontFamily: sans(700), fontSize: fs[5], color: fixe.surLave }}>Scanner un pass</Text>
            </Pressable>

            <Carte>
              <T taille={fs[2]} couleur={c.gris} style={{ marginBottom: 4 }}>PROFIL DE SALLE</T>
              <Display taille={fs[6]} style={{ marginBottom: 14 }}>Qui vient ce soir ?</Display>
              {stats.ecoles.map((s, i) => (
                <View key={s.nom} style={{ marginBottom: 10 }}>
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 }}>
                    <T taille={fs[3]} poids={600}>{s.nom}</T>
                    <T taille={fs[3]} couleur={c.gris}>{s.pourcentage}%</T>
                  </View>
                  <Jauge pourcentage={s.pourcentage} piste={c.grisClair} remplissage={COULEURS_ECOLE[i % COULEURS_ECOLE.length]} hauteur={6} />
                </View>
              ))}
            </Carte>

            <View style={{ backgroundColor: '#FFF1D1', borderRadius: rayon.md, padding: 16, marginBottom: 16 }}>
              <T taille={fs[3]} poids={700} couleur="#8A5A00" style={{ marginBottom: 4 }}>CONSEIL</T>
              <T taille={fs[3]} couleur="#8A5A00" interligne={lh.snug}>{stats.conseil}</T>
            </View>

            {stats.recentes.length ? (
              <Carte>
                <T taille={fs[2]} couleur={c.gris} style={{ marginBottom: 12 }}>DERNIÈRES INSCRIPTIONS</T>
                {stats.recentes.map((r, i) => (
                  <View key={i} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 8, borderBottomWidth: i < stats.recentes.length - 1 ? 1 : 0, borderBottomColor: c.grisClair }}>
                    <Avatar photo={r.photo_url} prenom={r.prenom} taille={32} fond={c.bleu} />
                    <View style={{ flex: 1 }}>
                      <T taille={fs[4]} poids={600}>{r.prenom} {r.nom}</T>
                      <T taille={fs[2]} couleur={c.gris}>{r.ecole ?? '—'} · {r.promo ?? '—'}</T>
                    </View>
                    <Mono couleur={c.gris}>{r.heure}</Mono>
                  </View>
                ))}
              </Carte>
            ) : null}
          </>
        )}

        <Bouton libelle="Gérer les événements" variante="contour" plein onPress={() => router.navigate('/evenements')} style={{ marginBottom: 10 }} />
        <Bouton libelle="+ Créer un événement" plein onPress={() => router.push({ pathname: '/evenement-form' })} />
      </Contenu>
    </Ecran>
  );
}
