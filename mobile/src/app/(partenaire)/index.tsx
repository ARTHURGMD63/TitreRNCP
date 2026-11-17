/**
 * Tableau de bord partenaire — partenaire/dashboard.php.
 *
 * La prochaine soirée, inscrits/check-in, âge moyen, qui vient ce soir
 * (répartition par école), le conseil du jour, les check-in et les dernières
 * inscriptions, et l'accès au scan des pass.
 *
 * Refonte 2026-11 : même langage que l'espace étudiant — logo en tête,
 * titre surligné, la prochaine soirée en carte bleue (ses trois compteurs
 * et « Scanner un pass »), puis des cartes blanches sans bordure.
 */

import React, { useCallback, useState } from 'react';
import { Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, ErreurApi, type ReponseDashboardPartenaire } from '../../api';
import { Apparition, Compteur } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { BoutonAction, CarreIcone, CarteRefonte, EnTeteApp, Pastille, teinteAvatar, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, Jauge } from '../../composants/Elements';
import { Mono, T, TitreEcran, TitreSection } from '../../composants/Texte';
import { UsageMois } from '../../composants/UsageMois';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { fs, halo, rayon, sans, taille } from '../../theme';
import { useTheme } from '../../useTheme';

/** Une couleur par école dans « Qui vient ce soir ? ». */
const COULEURS_ECOLE = ['#1D55F0', '#7B4DF2', '#22B573', '#FF8A3D', '#F0508A', '#14B8A6'];

/** Une ligne de personne : avatar, nom, école · promo, et l'heure à droite. */
function LignePersonneSalle({ prenom, nom, ecole, promo, photo, heure, dernier }: {
  prenom: string; nom: string; ecole: string | null; promo: string | null; photo: string | null; heure: string | null; dernier: boolean;
}) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, borderBottomWidth: dernier ? 0 : 1, borderBottomColor: c.grisClair }}>
      <Avatar photo={photo} prenom={prenom} taille={40} fond={teinteAvatar(prenom + nom)} />
      <View style={{ flex: 1 }}>
        <T taille={fs[4]} poids={600}>{prenom} {nom}</T>
        <T taille={fs[4]} couleur={c.gris}>{ecole ?? '—'} · {promo ?? '—'}</T>
      </View>
      {heure ? <T taille={fs[4]} poids={600} couleur={c.gris}>{heure}</T> : null}
    </View>
  );
}

export default function TableauDeBordPartenaire() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();

  const [donnees, setDonnees] = useState<ReponseDashboardPartenaire | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.partenaireDashboard(jeton));
    } catch (e) {
      // Aucune formule choisie : le tableau de bord n'a rien à montrer tant
      // que ce n'est pas réglé, comme exigerAbonnement() côté site.
      if (e instanceof ErreurApi && ['abonnement_requis', 'validation_requise', 'validation_refusee'].includes(e.code)) {
        router.replace('/abonnement');
        return;
      }
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
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp />
        </Apparition>
        <Apparition index={1}>
          <Mono style={{ marginBottom: 8 }}>{etablissement.nom} · {etablissement.ville}</Mono>
          <TitreEcran avant={`${stats.total} étudiant${stats.total > 1 ? 's' : ''},`} surligne="en route." style={{ marginBottom: 16 }} />
        </Apparition>
      </EnTete>

      <Contenu style={{ paddingTop: 0 }}>
        <Apparition index={2}>
          <UsageMois usage={donnees.usage} offreLibelle={donnees.offre.libelle} essaiJusquau={donnees.offre.essai_jusquau} />
        </Apparition>
        {!evenement ? (
          <Apparition index={2}>
            <CarteRefonte style={{ alignItems: 'center', paddingVertical: 24 }}>
              <CarreIcone icone="calendrier" fond={bleu.doux} encre={bleu.texteDoux} />
              <T taille={17} poids={700} style={{ textAlign: 'center' }}>Aucune soirée à venir</T>
              <T taille={fs[4]} couleur={c.gris} style={{ textAlign: 'center' }}>Crée un événement pour voir ses statistiques ici.</T>
            </CarteRefonte>
          </Apparition>
        ) : (
          <>
            {/* La prochaine soirée : la carte bleue de l'écran. */}
            <Apparition index={2}>
              <View style={[{ backgroundColor: bleu.accent, borderRadius: rayon.carte, padding: 14, gap: 14, marginBottom: 16 }, halo(bleu.accent)]}>
                <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                  <Text style={{ fontFamily: sans(600), fontSize: 12, letterSpacing: 0.06 * 12, textTransform: 'uppercase', color: bleu.surAccent2 }}>Prochaine soirée</Text>
                  <Pastille libelle={dateFr(evenement.date_heure, 'D j M · H\\hi')} fond="#FFFFFF" encre="#111116" />
                </View>
                <Text style={{ fontFamily: sans(700), fontSize: taille.titreCarte, lineHeight: taille.titreCarte * 1.15, letterSpacing: -0.03 * taille.titreCarte, color: '#FFFFFF' }}>{evenement.titre}</Text>
                <View style={{ flexDirection: 'row', gap: 8 }}>
                  {[
                    { l: 'Inscrits', v: stats.inscrits, format: (n: number) => String(Math.round(n)) },
                    { l: 'Check-in', v: stats.checkin, format: (n: number) => String(Math.round(n)) },
                    { l: 'Présence', v: stats.taux_presence ?? 0, format: (n: number) => `${Math.round(n)} %` },
                  ].map((s, i) => (
                    <View key={s.l} style={{ flex: 1, borderRadius: rayon.bouton, padding: 12, backgroundColor: 'rgba(255,255,255,0.16)', gap: 2 }}>
                      <Compteur valeur={s.v} format={s.format} delai={i * 120}
                        style={{ fontFamily: sans(700), fontSize: 20, color: '#FFFFFF', fontVariant: ['tabular-nums'] }} />
                      <T taille={fs[4]} couleur={bleu.surAccent2}>{s.l}</T>
                    </View>
                  ))}
                </View>
                <BoutonAction
                  libelle="Scanner un pass"
                  icone="qr"
                  accent={bleu.accent}
                  surAccent
                  testID="partenaire-scanner"
                  onPress={() => router.push({ pathname: '/scan', params: { evenementId: String(evenement.id) } })}
                  style={{ flex: 0 }}
                />
              </View>
            </Apparition>

            {stats.ecoles.length ? (
              <Apparition index={3}>
                <CarteRefonte>
                  <View>
                    <Mono>Profil de salle</Mono>
                    <TitreSection style={{ marginTop: 4 }}>Qui vient ?</TitreSection>
                  </View>
                  {stats.ecoles.map((s, i) => (
                    <View key={s.nom}>
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 }}>
                        <T taille={fs[4]} poids={600}>{s.nom}</T>
                        <T taille={fs[4]} couleur={c.gris}>{s.pourcentage}%</T>
                      </View>
                      <Jauge pourcentage={s.pourcentage} piste={c.surface2} remplissage={COULEURS_ECOLE[i % COULEURS_ECOLE.length]} hauteur={8} />
                    </View>
                  ))}
                </CarteRefonte>
              </Apparition>
            ) : null}

            <Apparition index={4}>
              <Bouton libelle="Suivi complet de cet événement" icone="activite" variante="contour" plein
                onPress={() => router.push({ pathname: '/evenement-suivi', params: { id: String(evenement.id) } })} style={{ marginBottom: 16 }} />
            </Apparition>

            {stats.checkins.length ? (
              <Apparition index={5}>
                <CarteRefonte style={{ gap: 4 }}>
                  <TitreSection style={{ marginBottom: 4 }}>Check-in · {stats.checkins.length}</TitreSection>
                  {stats.checkins.map((p, i) => (
                    <LignePersonneSalle key={p.id} prenom={p.prenom} nom={p.nom} ecole={p.ecole} promo={p.promo} photo={p.photo_url}
                      heure={p.checkin_le ? p.checkin_le.slice(11, 16).replace(':', 'h') : null} dernier={i === stats.checkins.length - 1} />
                  ))}
                </CarteRefonte>
              </Apparition>
            ) : null}

            {stats.recentes.length ? (
              <Apparition index={6}>
                <CarteRefonte style={{ gap: 4 }}>
                  <TitreSection style={{ marginBottom: 4 }}>Dernières inscriptions</TitreSection>
                  {stats.recentes.map((r, i) => (
                    <LignePersonneSalle key={i} prenom={r.prenom} nom={r.nom} ecole={r.ecole} promo={r.promo} photo={r.photo_url}
                      heure={r.heure} dernier={i === stats.recentes.length - 1} />
                  ))}
                </CarteRefonte>
              </Apparition>
            ) : null}
          </>
        )}

        {/* La création d'un événement passe par le bouton « + » de la barre
            (ou l'onglet Événements pour un club) : pas de second bouton ici. */}
        <Bouton libelle="Gérer les événements" variante="contour" plein onPress={() => router.navigate('/evenements')} />
      </Contenu>
    </Ecran>
  );
}
