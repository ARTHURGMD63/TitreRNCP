/**
 * Le profil d'un autre étudiant — view_profile.php.
 *
 * Le rond de retour, l'avatar dôme de 100 px, prénom et nom, école et promo,
 * les deux compteurs, le grand bouton de suivi (« + DEMANDER À SUIVRE »,
 * « DEMANDE ENVOYÉE · ANNULER », « ABONNÉ »), Signaler / Bloquer, les squads
 * en commun, ses passions et ses prochaines sorties — visibles seulement
 * après acceptation, comme sur le site.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { actions, api, ErreurApi, type Etudiant } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, BoutonRetour } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Champ, Selecteur } from '../../composants/Formulaire';
import { Icone } from '../../composants/Icone';
import { Display, Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { majuscules } from '../../format';
import { useJeton } from '../../session';
import { fixe, fs, lh, lsEm, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';
import { couleursSuivi, useSuivi } from '../../composants/Social';

const MOTIFS = [
  { valeur: 'harcelement', libelle: 'Harcèlement ou intimidation' },
  { valeur: 'contenu_inapproprie', libelle: 'Contenu inapproprié' },
  { valeur: 'usurpation', libelle: "Usurpation d'identité" },
  { valeur: 'spam', libelle: 'Spam ou publicité' },
  { valeur: 'autre', libelle: 'Autre' },
];

function CarteSection({ children }: { children: React.ReactNode }) {
  const { c } = useTheme();
  return <View style={{ backgroundColor: c.blanc, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, padding: 20, marginBottom: 20 }}>{children}</View>;
}

function GrandBoutonSuivre({ u }: { u: Etudiant }) {
  const { c } = useTheme();
  const { etat, attente, basculer } = useSuivi(u.id, u.etat_suivi);
  const { fond, encre } = couleursSuivi(etat, c);
  const libelle = etat === 'accepted' ? 'ABONNÉ' : etat === 'pending' ? 'DEMANDE ENVOYÉE · ANNULER' : '+ DEMANDER À SUIVRE';
  return (
    <Pressable
      onPress={basculer}
      accessibilityRole="button"
      style={({ pressed }) => ({
        minHeight: 52, padding: 16, borderRadius: rayon.pill, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8,
        backgroundColor: fond, borderWidth: 1.5, borderColor: etat === 'pending' && u.etat_suivi === 'pending' ? c.line2 : 'transparent',
        transform: pressed ? [{ translateY: 1 }, { scale: 0.99 }] : [],
      })}
    >
      {attente ? <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: encre }}>...</Text> : (
        <>
          {etat === 'accepted' ? <Icone nom="check" taille={17} couleur={encre} /> : null}
          <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: encre }}>{libelle}</Text>
        </>
      )}
    </Pressable>
  );
}

export default function ProfilEtudiant() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [u, setU] = useState<Etudiant | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [bloque, setBloque] = useState(false);
  const [blocage, setBlocage] = useState(false);

  const [signalement, setSignalement] = useState(false);
  const [motif, setMotif] = useState('harcelement');
  const [details, setDetails] = useState('');
  const [envoi, setEnvoi] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const r = (await api.etudiant(jeton, Number(id))).etudiant;
      setU(r);
      setBloque(r.je_bloque);
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Étudiant introuvable.');
    }
  }, [jeton, id]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function basculerBlocage() {
    if (!u) return;
    if (!bloque && !(await confirmer('Bloquer cette personne ? Vous ne verrez plus vos profils respectifs et vos abonnements seront supprimés.'))) return;
    setBlocage(true);
    try {
      const rep = await actions.moderation(jeton, { action: bloque ? 'unblock' : 'block', target_id: u.id });
      toast(rep.message || 'Erreur');
      if (!bloque) setTimeout(() => router.navigate({ pathname: '/', params: { vue: 'people' } }), 700);
      else setBloque(false);
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setBlocage(false);
    }
  }

  async function signaler() {
    if (!u) return;
    setEnvoi(true);
    try {
      const rep = await actions.moderation(jeton, { action: 'report', target_id: u.id, motif, details });
      toast(rep.message || 'Erreur');
      setSignalement(false);
      setMotif('harcelement');
      setDetails('');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setEnvoi(false);
    }
  }

  if (!u) {
    return (
      <Ecran avecBarre={false}>
        <Contenu style={{ paddingTop: 36 }}><BoutonRetour /></Contenu>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  // .btn-report-user / .btn-block-user : cible isolée, 44 px de haut.
  const lienDiscret = { fontFamily: sans(600), fontSize: fs[2], color: c.gris, textDecorationLine: 'underline' as const, paddingHorizontal: 8, paddingVertical: 13.5, minHeight: 44 };

  return (
    <Ecran
      avecBarre={false}
      horsDefilement={
        <Feuille visible={signalement} onClose={() => setSignalement(false)}>
          <Display taille={fs[7]} interligne={lh.tight} style={{ marginBottom: 6, letterSpacing: lsEm.tight * fs[7] }}>Signaler ce profil</Display>
          <T taille={fs[3]} poids={500} couleur={c.grisFonce} interligne={lh.snug} style={{ marginBottom: 20 }}>
            Ton signalement est envoyé à l&apos;équipe de modération. Il reste anonyme pour {u.prenom}.
          </T>
          <Selecteur etiquette="Motif" valeur={motif} onChange={setMotif} options={MOTIFS} />
          <Champ etiquette="Précisions (facultatif)" value={details} onChangeText={setDetails} multiligne maxLength={500}
            placeholder="Ce qui s'est passé, si tu veux le préciser." numberOfLines={3} />
          <Bouton libelle="Envoyer le signalement" plein chargement={envoi} onPress={signaler} />
          <Bouton libelle="Annuler" variante="contour" plein onPress={() => setSignalement(false)} style={{ marginTop: 12 }} />
        </Feuille>
      }
    >
      <Contenu>
        <View style={{ paddingTop: 36, alignItems: 'flex-start', gap: 22 }}>
          <BoutonRetour />
          <Avatar photo={u.photo_url} prenom={u.prenom} taille={100} fond={c.bleu} />
        </View>

        <View style={{ marginTop: 16, marginBottom: 20 }}>
          <TitreEcran lignes={[u.prenom, u.nom]} accent={c.noir} interligne={lh.display} />
          <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 8 }}>{u.ecole ?? ''} · Promo {u.promo ?? ''}</T>

          <View style={{ flexDirection: 'row', gap: 12, marginTop: 12 }}>
            {[{ n: u.nb_abonnes, l: 'Abonnés' }, { n: u.nb_abonnements, l: 'Abonnements' }].map((x) => (
              <View key={x.l} style={{ flex: 1, backgroundColor: c.blanc, borderRadius: rayon.md, borderWidth: 1, borderColor: c.grisClair, padding: 14 }}>
                {/* Pas d'interligne imposé sur le site : « normal », soit ~1,24 pour Unbounded. */}
                <Display taille={fs[7]} interligne={1.24}>{x.n}</Display>
                <Mono>{x.l}</Mono>
              </View>
            ))}
          </View>
        </View>

        <GrandBoutonSuivre u={u} />
        <View style={{ marginTop: 10, marginBottom: 24, flexDirection: 'row', gap: 16, justifyContent: 'center' }}>
          <Text onPress={() => setSignalement(true)} accessibilityRole="button" style={lienDiscret}>Signaler</Text>
          <Text onPress={blocage ? undefined : basculerBlocage} accessibilityRole="button" style={lienDiscret}>{bloque ? 'Débloquer' : 'Bloquer'}</Text>
        </View>

        {u.squads_communs.length ? (
          <CarteSection>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 10 }}>
              <Icone nom="eclair" taille={18} couleur={c.surLimeClair} />
              <T taille={fs[4]} poids={700} couleur={c.surLimeClair}>En commun</T>
            </View>
            <T taille={fs[4]} couleur={c.grisFonce}>
              Vous faites partie de <T taille={fs[4]} poids={700}>{u.squads_communs.length} Squads</T> ensemble.
            </T>
            <View style={{ marginTop: 10, flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
              {u.squads_communs.map((s, i) => (
                <View key={i} style={{ backgroundColor: c.bleu, paddingVertical: 4, paddingHorizontal: 12, borderRadius: rayon.pill }}>
                  <T taille={fs[2]} poids={700} couleur={fixe.surLave}>{majuscules(s.type)}</T>
                </View>
              ))}
            </View>
          </CarteSection>
        ) : null}

        <CarteSection>
          <Mono style={{ marginBottom: 14 }}>Ses passions</Mono>
          {u.interets.length === 0 ? (
            <T taille={fs[3]} couleur={c.gris}>Cet étudiant n&apos;a pas encore ajouté d&apos;intérêts.</T>
          ) : (
            <View style={{ flexDirection: 'row', flexWrap: 'wrap' }}>
              {u.interets.map((i) => (
                <View key={i} style={{ marginRight: 6, marginBottom: 8, paddingVertical: 7, paddingHorizontal: 14, borderRadius: rayon.pill, borderWidth: 1, borderColor: c.line2 }}>
                  <T taille={fs[3]} poids={600}>{i}</T>
                </View>
              ))}
            </View>
          )}
        </CarteSection>

        <CarteSection>
          <Mono style={{ marginBottom: 14 }}>Ses prochaines sorties</Mono>
          {!u.activite_visible ? (
            <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 12 }}>
              <View style={{ marginTop: 2 }}><Icone nom="cadenas" taille={20} couleur={c.gris} /></View>
              <View style={{ flex: 1 }}>
                <T taille={fs[3]} poids={600}>Visible après acceptation</T>
                <T taille={fs[2]} couleur={c.grisFonce} interligne={lh.snug} style={{ marginTop: 4 }}>
                  {u.prenom} décide qui voit ses sorties. {u.etat_suivi === 'pending' ? 'Ta demande est en attente de réponse.' : 'Demande à suivre pour y accéder.'}
                </T>
              </View>
            </View>
          ) : u.sorties.length === 0 ? (
            <T taille={fs[3]} couleur={c.gris}>Aucune sortie prévue pour le moment.</T>
          ) : (
            u.sorties.map((ev, i) => (
              <View key={i} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, borderBottomWidth: i < u.sorties.length - 1 ? 1 : 0, borderBottomColor: c.grisClair }}>
                <View style={{ width: 4, height: 30, borderRadius: 2, backgroundColor: c.rouge }} />
                <View style={{ flex: 1 }}>
                  <T taille={fs[3]} poids={700}>{ev.titre}</T>
                  <T taille={fs[2]} couleur={c.gris}>{ev.etablissement} · {ev.date}</T>
                </View>
              </View>
            ))
          )}
        </CarteSection>
      </Contenu>
    </Ecran>
  );
}
