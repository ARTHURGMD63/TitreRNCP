/**
 * Un créneau de terrain — réserver, compléter, gérer sa partie.
 *
 * Trois situations, décidées par le serveur (peut_reserver, peut_rejoindre,
 * peut_quitter) plutôt que recalculées ici :
 *   - terrain libre : je réserve, seul ou avec des amis (des personnes que
 *     je suis, demande acceptée), et je choisis d'ouvrir ou non les places
 *     restantes aux inconnus, avec un niveau ;
 *   - partie ouverte d'un autre : je prends une place, sans validation ;
 *   - ma partie : je l'ouvre ou la referme, ou je la quitte — l'organisateur
 *     qui quitte libère le terrain.
 *
 * Le prix est affiché, réglé au club : rien n'est payé dans l'application.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api, ErreurApi, type CreneauDetail, type NiveauSport, type Personne } from '../../api';
import { NIVEAUX_SQUAD } from '../../catalogue';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, BoutonRetour, Encart, Pilule } from '../../composants/Elements';
import { Case } from '../../composants/Formulaire';
import { Icone } from '../../composants/Icone';
import { etiquetteCreneau, libelleSport } from '../../composants/Sport';
import { Display, Mono, T } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { court, dateFr } from '../../format';
import { amisMax, formatEuros, libellePlaces, prixParJoueur } from '../../regles';
import { useJeton } from '../../session';
import { fs, gutter, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const NIVEAUX: NiveauSport[] = ['tous', 'debutant', 'inter', 'avance'];

export default function FicheCreneau() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const creneauId = Number(id);

  const [cr, setCr] = useState<CreneauDetail | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [amis, setAmis] = useState<Personne[] | null>(null);
  const [choisis, setChoisis] = useState<number[]>([]);
  const [ouvert, setOuvert] = useState(true);
  const [niveau, setNiveau] = useState<NiveauSport>('tous');
  const [envoi, setEnvoi] = useState(false);

  const appliquer = useCallback((nouveau: CreneauDetail) => {
    setCr(nouveau);
    if (nouveau.statut === 'reserve') {
      setOuvert(nouveau.ouvert);
      setNiveau(nouveau.niveau);
    }
  }, []);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      appliquer((await api.creneau(jeton, creneauId)).creneau);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    }
  }, [jeton, creneauId, appliquer]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  // Les amis ne servent qu'à la réservation : chargés seulement si elle est possible.
  React.useEffect(() => {
    if (!cr?.peut_reserver || amis !== null) return;
    api.abonnements(jeton, { type: 'abonnements' })
      .then((r) => setAmis(r.personnes.filter((p) => p.etat_suivi === 'accepted')))
      .catch(() => setAmis([]));
  }, [cr?.peut_reserver, amis, jeton]);

  async function agir(action: () => Promise<{ message?: string; creneau: CreneauDetail }>) {
    setEnvoi(true);
    try {
      const rep = await action();
      appliquer(rep.creneau);
      setChoisis([]);
      toast(rep.message ?? 'Enregistré.', 'success');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
      if (e instanceof ErreurApi && (e.statut === 404 || e.statut === 409)) void charger();
    } finally {
      setEnvoi(false);
    }
  }

  async function quitter() {
    if (!cr) return;
    const texte = cr.je_organise
      ? 'Annuler ta réservation ? Le terrain redevient libre et tes joueurs perdent leur place.'
      : 'Quitter cette partie ?';
    if (!(await confirmer(texte))) return;
    await agir(() => api.quitterCreneau(jeton, cr.id));
  }

  function basculerAmi(idAmi: number) {
    if (!cr) return;
    setChoisis((liste) => {
      if (liste.includes(idAmi)) return liste.filter((x) => x !== idAmi);
      if (liste.length >= amisMax(cr.places.total)) {
        toast(`Ce terrain se joue à ${cr.places.total}.`, 'error');
        return liste;
      }
      return [...liste, idAmi];
    });
  }

  if (!cr) {
    return (
      <Ecran avecBarre={false}>
        <EnTete><BoutonRetour /></EnTete>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const restantesApresMoi = cr.places.total - 1 - choisis.length;
  const nomsInvitants = new Map(cr.joueurs.map((j) => [j.id, j.prenom]));

  const choixNiveau = (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginBottom: 16 }} contentContainerStyle={{ gap: 8 }}>
      {NIVEAUX.map((n) => (
        <Pilule key={n} libelle={NIVEAUX_SQUAD[n] ?? n} actif={niveau === n} accent={c.bleu} onPress={() => setNiveau(n)} />
      ))}
    </ScrollView>
  );

  return (
    <Ecran avecBarre={false}>
      <EnTete>
        <BoutonRetour style={{ marginBottom: 18 }} />
        <Mono couleur={c.surBleuClair} style={{ marginBottom: 6 }}>{etiquetteCreneau(cr)}</Mono>
        <Display taille={fs[7]} style={{ marginBottom: 4 }}>{cr.club.nom}</Display>
        <T taille={fs[3]} couleur={c.grisFonce}>
          {dateFr(cr.debut, 'l j F')} · {dateFr(cr.debut, 'H\\hi')} – {dateFr(cr.fin, 'H\\hi')}
        </T>
        {cr.club.adresse ? <T taille={fs[3]} couleur={c.gris}>{cr.club.adresse}{cr.club.ville ? `, ${cr.club.ville}` : ''}</T> : null}
      </EnTete>

      <Contenu style={{ paddingTop: 0 }}>
        {cr.statut === 'annule' ? (
          <Encart genre="erreur">Le club a annulé ce créneau. Rapproche-toi de lui pour en trouver un autre.</Encart>
        ) : null}

        <View style={{ flexDirection: 'row', gap: 10, marginBottom: 20 }}>
          {[
            { titre: 'Sport', valeur: libelleSport(cr.sport) },
            { titre: 'Joueurs', valeur: String(cr.places.total) },
            { titre: 'Terrain', valeur: formatEuros(cr.prix) },
          ].map((b) => (
            <View key={b.titre} style={{ flex: 1, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, padding: 12 }}>
              <Mono couleur={c.gris} style={{ marginBottom: 4 }}>{b.titre}</Mono>
              <T taille={fs[4]} poids={700}>{b.valeur}</T>
            </View>
          ))}
        </View>
        <T taille={fs[3]} couleur={c.grisFonce} style={{ marginBottom: 20 }}>
          {prixParJoueur(cr.prix, cr.places.total)}. Rien n&apos;est payé dans l&apos;application : le règlement se fait au club.
        </T>

        {cr.statut !== 'libre' ? (
          <>
            <Mono accessibilityRole="header" style={{ marginBottom: 12 }}>
              {cr.ouvert ? `Partie ouverte · ${NIVEAUX_SQUAD[cr.niveau] ?? cr.niveau}` : 'Partie privée'} · {libellePlaces(cr.places.restantes)}
            </Mono>
            <View style={{ gap: 8, marginBottom: 20 }}>
              {cr.joueurs.map((j) => (
                <View key={j.id} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, backgroundColor: c.blanc }}>
                  <Avatar photo={j.photo_url} prenom={j.prenom} taille={36} fond={c.bleu} />
                  <View style={{ flex: 1 }}>
                    <T taille={fs[4]} poids={700}>{court(j.prenom, j.nom)}</T>
                    <T taille={fs[2]} couleur={c.gris}>
                      {cr.organisateur?.id === j.id
                        ? 'Organisateur'
                        : j.invite_par
                          ? `Invité par ${nomsInvitants.get(j.invite_par) ?? 'un ami'}`
                          : 'Venu compléter'}
                    </T>
                  </View>
                </View>
              ))}
              {Array.from({ length: cr.places.restantes }, (_, i) => (
                <View key={`v${i}`} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, borderWidth: 1, borderStyle: 'dashed', borderColor: c.line2, borderRadius: rayon.md }}>
                  <View style={{ width: 36, height: 36, borderRadius: 18, borderWidth: 1.5, borderStyle: 'dashed', borderColor: c.gris, alignItems: 'center', justifyContent: 'center' }}>
                    <Icone nom="plus" taille={14} couleur={c.gris} />
                  </View>
                  <T taille={fs[4]} couleur={c.gris}>Place libre</T>
                </View>
              ))}
            </View>
          </>
        ) : null}

        {cr.peut_reserver ? (
          <>
            <Mono accessibilityRole="header" style={{ marginBottom: 8 }}>Avec qui tu viens ?</Mono>
            <T taille={fs[3]} couleur={c.gris} style={{ marginBottom: 12 }}>
              Ajoute jusqu&apos;à {amisMax(cr.places.total)} ami{amisMax(cr.places.total) > 1 ? 's' : ''} parmi les personnes que tu suis.
            </T>
            {amis === null ? <Chargement /> : amis.length === 0 ? (
              <T taille={fs[3]} couleur={c.gris} style={{ marginBottom: 16 }}>Tu ne suis personne encore : tu peux réserver seul et ouvrir la partie.</T>
            ) : (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginHorizontal: -gutter, marginBottom: 16 }} contentContainerStyle={{ gap: 10, paddingHorizontal: gutter }}>
                {amis.map((a) => {
                  const actif = choisis.includes(a.id);
                  return (
                    <Pressable
                      key={a.id}
                      onPress={() => basculerAmi(a.id)}
                      accessibilityRole="checkbox"
                      accessibilityState={{ checked: actif }}
                      accessibilityLabel={court(a.prenom, a.nom)}
                      style={{ width: 84, alignItems: 'center', gap: 6, paddingVertical: 10, borderRadius: rayon.md, borderWidth: actif ? 1.5 : 1, borderColor: actif ? c.bleu : c.grisClair, backgroundColor: c.blanc }}
                    >
                      <Avatar photo={a.photo_url} prenom={a.prenom} taille={40} />
                      <T numberOfLines={1} taille={fs[2]} poids={actif ? 700 : 500} style={{ maxWidth: 76 }}>{a.prenom}</T>
                      {actif ? <Icone nom="check" taille={14} couleur={c.surBleuClair} /> : <View style={{ height: 14 }} />}
                    </Pressable>
                  );
                })}
              </ScrollView>
            )}

            {restantesApresMoi > 0 ? (
              <>
                <Mono accessibilityRole="header" style={{ marginBottom: 12 }}>
                  {libellePlaces(restantesApresMoi)} à remplir
                </Mono>
                <Case coche={ouvert} onChange={setOuvert} style={{ marginBottom: 12, alignItems: 'flex-start' }}>
                  Ouvrir aux autres joueurs : la partie apparaît dans « Parties à compléter » et n&apos;importe qui peut prendre une place.
                </Case>
                {ouvert ? choixNiveau : null}
              </>
            ) : null}

            <Bouton
              libelle={`Réserver · ${prixParJoueur(cr.prix, cr.places.total)}`}
              variante="bleu"
              plein
              chargement={envoi}
              onPress={() => agir(() => api.reserverCreneau(jeton, cr.id, { amis: choisis, ouvert: restantesApresMoi > 0 && ouvert, niveau }))}
            />
          </>
        ) : null}

        {cr.peut_rejoindre ? (
          <Bouton
            libelle={`Prendre ma place · ${prixParJoueur(cr.prix, cr.places.total)}`}
            variante="bleu"
            plein
            chargement={envoi}
            onPress={() => agir(() => api.rejoindreCreneau(jeton, cr.id))}
          />
        ) : null}

        {cr.je_organise && cr.statut === 'reserve' && cr.peut_quitter ? (
          <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 16, marginBottom: 16 }}>
            <Mono style={{ marginBottom: 12 }}>Ma partie</Mono>
            <Case coche={ouvert} onChange={setOuvert} style={{ marginBottom: 12, alignItems: 'flex-start' }}>
              Ouverte aux autres joueurs
            </Case>
            {ouvert ? choixNiveau : null}
            <Bouton libelle="Enregistrer" variante="contour" plein chargement={envoi} onPress={() => agir(() => api.modifierPartie(jeton, cr.id, ouvert, niveau))} />
          </View>
        ) : null}

        {cr.peut_quitter ? (
          <Bouton
            libelle={cr.je_organise ? 'Annuler ma réservation' : 'Quitter la partie'}
            variante="alerte"
            plein
            onPress={quitter}
            style={{ marginTop: 8 }}
          />
        ) : null}

        {!cr.peut_reserver && !cr.peut_rejoindre && !cr.peut_quitter && cr.statut !== 'annule' ? (
          <T taille={fs[3]} couleur={c.gris} style={{ textAlign: 'center', marginTop: 8 }}>
            {cr.places.restantes === 0 ? 'La partie est complète.' : 'Ce créneau n’est plus réservable.'}
          </T>
        ) : null}

        <Bouton libelle="Retour au sport" variante="texte" plein onPress={() => router.navigate('/squads')} style={{ marginTop: 12 }} />
      </Contenu>
    </Ecran>
  );
}
