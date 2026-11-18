/**
 * Créer / modifier un événement — partenaire/create_event.php et
 * edit_event.php, réunis en un seul écran comme le fait déjà
 * api/v1/partenaire_evenement.php côté serveur.
 *
 * Mêmes validations qu'en PHP, mais uniquement côté affichage : le serveur
 * revérifie tout (le quota flash en particulier), jamais confiance au
 * client sur le tarif de sponsoring.
 *
 * Pour un club sportif (est_club, v28), le formulaire devient celui d'un
 * événement sportif : un sport et un format (tournoi, stage…) à la place du
 * type et du style de musique, et ni offre flash ni sponsoring — tous deux
 * visent le fil des soirées, où un événement sportif n'apparaît pas.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api, ErreurApi, type ReponseFormulaireEvenementPartenaire } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { ChronoMiseEnAvant } from '../../composants/ChronoMiseEnAvant';
import { useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { BoutonRetour, Encart } from '../../composants/Elements';
import { Case, Champ, ChampDate, isoDateHeure, Selecteur } from '../../composants/Formulaire';
import { T, TitreEcran, TitreSection } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const TYPES = [
  { valeur: 'bar', libelle: 'Bar' },
  { valeur: 'boite', libelle: 'Boîte de nuit' },
  { valeur: 'resto', libelle: 'Restaurant' },
  { valeur: 'afterwork', libelle: 'Afterwork' },
];

export default function FormulaireEvenementPartenaire() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const toast = useToast();
  const { id } = useLocalSearchParams<{ id?: string }>();
  const evenementId = id ? Number(id) : null;

  const [donnees, setDonnees] = useState<ReponseFormulaireEvenementPartenaire | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [enregistrement, setEnregistrement] = useState(false);
  const [erreurEnregistrement, setErreurEnregistrement] = useState<string | null>(null);

  const [titre, setTitre] = useState('');
  const [description, setDescription] = useState('');
  const [type, setType] = useState('bar');
  const [styleMusique, setStyleMusique] = useState('');
  const [sport, setSport] = useState('padel');
  const [format, setFormat] = useState('tournoi');
  const [date, setDate] = useState<Date | null>(null);
  const [dateFin, setDateFin] = useState<Date | null>(null);
  const [clotureInscriptions, setClotureInscriptions] = useState<Date | null>(null);
  const [lieu, setLieu] = useState('');
  const [quota, setQuota] = useState('100');
  const [prixNormal, setPrixNormal] = useState('0');
  const [reduction, setReduction] = useState('0');
  const [isGratuit, setIsGratuit] = useState(false);
  const [isFlash, setIsFlash] = useState(false);
  const [flashExpiry, setFlashExpiry] = useState<Date | null>(null);
  const [isSponsorise, setIsSponsorise] = useState(false);
  const [sponsorFormule, setSponsorFormule] = useState('');
  const [sponsorNotifs, setSponsorNotifs] = useState<(Date | null)[]>([]);
  // Mise en avant en cours : payée jusqu'à sa fin, elle ne se change ni ne se retire.
  const [sponsorActif, setSponsorActif] = useState<{ formule: string; jusquau: string; debut: string | null; restantS: number | null; envoyees: string[] } | null>(null);
  // L'instant d'ouverture de l'écran : base de la fenêtre annoncée (pas d'horloge dans le rendu).
  const [maintenant] = useState(() => Date.now());
  const [sponsorTermine, setSponsorTermine] = useState<string | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const rep = await api.partenaireFormulaireEvenement(jeton, evenementId ?? undefined);
      setDonnees(rep);
      if (rep.evenement) {
        const e = rep.evenement;
        setTitre(e.titre);
        setDescription(e.description);
        setType(e.type);
        setStyleMusique(e.style_musique ?? '');
        if (e.sport) setSport(e.sport);
        if (e.format) setFormat(e.format);
        setDate(new Date(e.date_heure.replace(' ', 'T')));
        setDateFin(e.date_fin ? new Date(e.date_fin.replace(' ', 'T')) : null);
        setClotureInscriptions(e.inscriptions_jusqu_au ? new Date(e.inscriptions_jusqu_au.replace(' ', 'T')) : null);
        setLieu(e.lieu);
        setQuota(String(e.quota));
        setPrixNormal(String(e.prix_normal));
        setReduction(String(e.reduction));
        setIsGratuit(e.is_gratuit);
        setIsFlash(e.is_flash);
        setFlashExpiry(e.flash_expiry ? new Date(e.flash_expiry.replace(' ', 'T')) : null);
        setIsSponsorise(e.is_sponsorise);
        setSponsorFormule(e.sponsor_formule ?? rep.formules_sponsoring.find((f) => f.offrable)?.code ?? '');
        setSponsorActif(e.sponsor_actif && e.sponsor_formule && e.sponsor_jusqu_au
          ? { formule: e.sponsor_formule, jusquau: e.sponsor_jusqu_au, debut: e.sponsor_debut, restantS: e.sponsor_restant_s, envoyees: e.sponsor_notifications_envoyees } : null);
        setSponsorTermine(e.sponsor_termine ? e.sponsor_jusqu_au : null);
        setSponsorNotifs(e.sponsor_notifications.map((h) => new Date(h.replace(' ', 'T'))));
      } else {
        setLieu(rep.lieu_defaut);
        setSponsorFormule(rep.formules_sponsoring.find((f) => f.offrable)?.code ?? '');
      }
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    }
  }, [jeton, evenementId]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function enregistrer() {
    setEnregistrement(true);
    setErreurEnregistrement(null);
    try {
      const rep = await api.partenaireEnregistrerEvenement(jeton, {
        id: evenementId ?? undefined,
        titre, description, type,
        style_musique: donnees?.est_club ? undefined : styleMusique || undefined,
        sport: donnees?.est_club ? sport : undefined,
        format: donnees?.est_club ? format : undefined,
        date_heure: date ? isoDateHeure(date) : '',
        date_fin: dateFin ? isoDateHeure(dateFin) : undefined,
        // Chaîne vide : pas de clôture anticipée (on retire celle d'avant).
        inscriptions_jusqu_au: clotureInscriptions ? isoDateHeure(clotureInscriptions) : '',
        lieu, quota: Number(quota), prix_normal: Number(prixNormal), reduction: Number(reduction),
        is_gratuit: isGratuit, is_flash: !donnees?.est_club && isFlash,
        flash_expiry: !donnees?.est_club && isFlash && flashExpiry ? isoDateHeure(flashExpiry) : undefined,
        is_sponsorise: !donnees?.est_club && isSponsorise,
        sponsor_formule: !donnees?.est_club && isSponsorise ? sponsorFormule : undefined,
        sponsor_notifications: !donnees?.est_club && isSponsorise
          ? sponsorNotifs.filter((d): d is Date => d !== null).map(isoDateHeure) : undefined,
        // L'application ne vend rien : aucun supplément payant ne s'accepte ici.
        accepte_supplement: false,
      });
      toast(rep.message ?? 'Enregistré.', 'success');
      router.replace('/evenements');
    } catch (e) {
      setErreurEnregistrement(e instanceof ErreurApi ? e.message : 'Erreur réseau');
    } finally {
      setEnregistrement(false);
    }
  }

  if (!donnees) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const optionsMusique = [{ valeur: '', libelle: 'Non précisé' }, ...donnees.styles_musique.map((s) => ({ valeur: s.code, libelle: s.libelle }))];
  const club = donnees.est_club;

  // L'application ne vend rien (règles des stores) : au-delà du quota du mois,
  // rien ne se publie d'ici, et seule la mise en avant comprise dans la formule
  // se prend ici. Le serveur revérifie tout et ne croit jamais le client.
  const quotaAtteint = !evenementId && donnees.supplement.prochain;
  const formuleChoisie = donnees.formules_sponsoring.find((f) => f.code === sponsorFormule);
  const verrouille = sponsorActif !== null;
  const sponsoNouveau = !club && isSponsorise && formuleChoisie !== undefined && !verrouille;
  // La mise en avant offerte de l'abonnement ne couvre que le Coup de projecteur.
  const formulesOffertes = donnees.formules_sponsoring.filter((f) => f.offrable === true);
  const peutOffrir = formulesOffertes.length > 0 && donnees.usage.mises_en_avant.offertes_restantes > 0;
  const offerte = sponsoNouveau && formuleChoisie?.offrable === true && donnees.usage.mises_en_avant.offertes_restantes > 0;

  // La vraie fenêtre, annoncée avant l'achat : elle court à partir de maintenant
  // et s'arrête au plus tard à la fin de l'événement (comme sur le serveur).
  const finEvenement = date ? (dateFin ?? new Date(date.getTime() + donnees.duree_defaut_heures * 3600_000)) : null;
  const finMiseEnAvant = sponsoNouveau && formuleChoisie && finEvenement
    ? new Date(Math.min(maintenant + formuleChoisie.heures * 3600_000, finEvenement.getTime())) : null;
  const heuresReelles = finMiseEnAvant ? Math.max(0, Math.round((finMiseEnAvant.getTime() - maintenant) / 3600_000)) : null;
  const dejaTermine = finEvenement !== null && finEvenement.getTime() <= maintenant;
  const nbEnvoyees = sponsorActif?.envoyees.length ?? 0;
  const nbNotifs = Math.max(0, (formuleChoisie?.notifications ?? 0) - nbEnvoyees);
  const publiable = !quotaAtteint && (!sponsoNouveau || offerte);
  const flashImpossible = donnees.flash_par_mois === 0;

  return (
    <Ecran>
      <EnTete style={{ paddingBottom: 4 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 18 }}>
          <BoutonRetour vers="/evenements" libelle="Retour à mes événements" />
        </View>
        <TitreEcran avant={evenementId ? 'Modifier' : 'Créer'} surligne="un événement." rubrique={club ? 'sport' : 'bleu'} style={{ marginBottom: 16 }} />
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {erreurEnregistrement ? <Encart genre="erreur">{erreurEnregistrement}</Encart> : null}

        {!evenementId ? (
          quotaAtteint ? (
            <View style={{ backgroundColor: c.alerteClair, borderRadius: rayon.carte, padding: 16, gap: 10, marginBottom: 16 }}>
              <T taille={fs[4]} poids={700} couleur={c.alerte}>Quota du mois atteint</T>
              <T taille={fs[4]} couleur={c.alerte}>
                Tu as publié {donnees.usage.evenements.publies} événements sur les {donnees.usage.evenements.quota} compris dans ta formule ce mois-ci.
              </T>
              <T taille={fs[4]} couleur={c.alerte}>
                Pour publier un événement de plus, connecte-toi sur le site outsee.fr avec les mêmes identifiants : l&apos;événement supplémentaire se règle là-bas, au moment de le publier. Une mise en avant payante se règle de la même façon.
              </T>
            </View>
          ) : (
            <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 12 }}>
              Compris dans ta formule : il te reste {donnees.usage.evenements.restants} événement{donnees.usage.evenements.restants > 1 ? 's' : ''} ce mois-ci.
            </T>
          )
        ) : null}

        <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 14, marginBottom: 16 }}>
        <TitreSection style={{ marginBottom: 14 }}>Informations générales</TitreSection>
        <Champ etiquette="Titre *" value={titre} onChangeText={setTitre} placeholder={club ? 'ex : Open étudiant de padel' : 'ex : Soirée Étudiants — DJ Groove'} />
        {club ? (
          <View style={{ flexDirection: 'row', gap: 12 }}>
            <Selecteur style={{ flex: 1 }} etiquette="Sport *" valeur={sport} onChange={setSport} options={donnees.sports.map((s) => ({ valeur: s.code, libelle: s.libelle }))} />
            <Selecteur style={{ flex: 1 }} etiquette="Format *" valeur={format} onChange={setFormat} options={donnees.formats.map((f) => ({ valeur: f.code, libelle: f.libelle }))} />
          </View>
        ) : (
          <View style={{ flexDirection: 'row', gap: 12 }}>
            {donnees.type_libre ? (
              <Selecteur style={{ flex: 1 }} etiquette="Type *" valeur={type} onChange={setType} options={TYPES} />
            ) : null}
            <Selecteur style={{ flex: 1 }} etiquette="Style de musique" valeur={styleMusique} onChange={setStyleMusique} options={optionsMusique} />
          </View>
        )}
        <ChampDate etiquette="Début *" mode="datetime" valeur={date} onChange={setDate} min={new Date()} />
        <ChampDate etiquette="Fin" mode="datetime" valeur={dateFin} onChange={setDateFin} min={date ?? new Date()} />
        <ChampDate
          etiquette="Clôture des inscriptions"
          mode="datetime"
          valeur={clotureInscriptions}
          onChange={setClotureInscriptions}
          min={new Date()}
          max={date ?? undefined}
          marge={clotureInscriptions ? 4 : 16}
          aide="Facultatif. Sans date, on peut s'inscrire jusqu'au début."
        />
        {clotureInscriptions ? (
          <Pressable onPress={() => setClotureInscriptions(null)} accessibilityRole="button" style={{ alignSelf: 'flex-start', marginBottom: 16, paddingVertical: 4 }}>
            <T taille={fs[3]} poids={600} couleur={c.gris}>Retirer la clôture</T>
          </Pressable>
        ) : null}
        <Champ etiquette="Description" value={description} onChangeText={setDescription} multiligne numberOfLines={3}
          placeholder={club ? 'Tableau, niveau attendu, lots, matériel...' : 'Ambiance, animations, dress code...'} />
        <Champ etiquette={club ? 'Lieu / Terrains' : 'Lieu / Salle'} value={lieu} onChangeText={setLieu} marge={0} />
        </View>

        <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 14, marginBottom: 16 }}>
        <TitreSection style={{ marginBottom: 14 }}>Capacité &amp; tarifs</TitreSection>
        <View style={{ flexDirection: 'row', gap: 12 }}>
          <Champ style={{ flex: 1 }} etiquette="Quota (places max) *" value={quota} onChangeText={setQuota} keyboardType="number-pad" />
          <Champ style={{ flex: 1 }} etiquette="Prix normal (€)" value={prixNormal} onChangeText={setPrixNormal} keyboardType="decimal-pad" />
        </View>
        <Champ etiquette="Réduction étudiants (%)" value={reduction} onChangeText={setReduction} keyboardType="number-pad" aide="Affichée sur le pass étudiant." />
        <Case coche={isGratuit} onChange={setIsGratuit}>
          {club ? 'Inscription gratuite — le pass sera 100% offert' : 'Entrée gratuite — le pass sera 100% offert'}
        </Case>
        </View>

        {club ? null : (<View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 14, paddingBottom: 4, marginBottom: 16 }}>
        <TitreSection style={{ marginBottom: 14 }}>Options avancées</TitreSection>
        {flashImpossible && !isFlash ? (
          <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 16 }}>
            Offre flash : non comprise dans ta formule (Fondateur et Premium en incluent une par mois).
          </T>
        ) : (
          <Case coche={isFlash} onChange={setIsFlash} style={{ marginBottom: isFlash ? 12 : 20 }}>
            Event Flash — offre limitée dans le temps, en tête du fil
          </Case>
        )}
        {isFlash ? (
          <ChampDate etiquette="Expiration de l'offre flash" mode="datetime" valeur={flashExpiry} onChange={setFlashExpiry} min={new Date()} />
        ) : null}
        {!donnees.flash_illimite ? (
          <T taille={fs[4]} couleur={c.gris} style={{ marginTop: -8, marginBottom: 16 }}>Offre flash : {donnees.usage.flash.utilises} utilisée{donnees.usage.flash.utilises > 1 ? 's' : ''} sur {donnees.flash_par_mois} ce mois-ci</T>
        ) : null}

        {verrouille && sponsorActif ? (
          <View style={{ backgroundColor: bleu.doux, borderRadius: rayon.carte, padding: 14, gap: 4, marginBottom: 16 }}>
            <T taille={fs[4]} poids={700} couleur={bleu.texteDoux}>
              Mise en avant en cours · {donnees.formules_sponsoring.find((f) => f.code === sponsorActif.formule)?.nom}
            </T>
            <ChronoMiseEnAvant
              debut={sponsorActif.debut}
              jusquau={sponsorActif.jusquau}
              restantS={sponsorActif.restantS}
              dureeS={null}
              enCours
              couleur={bleu.texteDoux}
              piste={bleu.accent}
              remplissage={bleu.accent}
            />
            <T taille={fs[4]} couleur={bleu.texteDoux}>
              Elle est payée jusque-là : elle ne se change ni ne se retire.
            </T>
          </View>
        ) : peutOffrir ? (
          <>
            {sponsorTermine ? (
              <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 12 }}>
                Mise en avant terminée le {dateFr(sponsorTermine, 'j M')}. Coche la case pour en relancer une.
              </T>
            ) : null}
            <Case coche={isSponsorise} onChange={setIsSponsorise} style={{ marginBottom: isSponsorise ? 12 : 20 }}>
              Mise en avant comprise dans ta formule — remonte en tête du fil Explore
            </Case>
            {isSponsorise ? (
              <Selecteur
                etiquette="Mise en avant"
                valeur={sponsorFormule}
                onChange={setSponsorFormule}
                options={formulesOffertes.map((f) => ({ valeur: f.code, libelle: f.nom }))}
              />
            ) : null}
          </>
        ) : null}
        {isSponsorise && formuleChoisie ? (
          <View style={{ marginBottom: 16, gap: 4 }}>
            {!verrouille ? <T taille={fs[4]} couleur={c.gris}>{formuleChoisie.resume}</T> : null}
            {finMiseEnAvant && heuresReelles !== null ? (
              dejaTermine ? (
                <T taille={fs[4]} poids={700} couleur={c.danger}>Cet événement est déjà terminé : il ne peut pas être mis en avant.</T>
              ) : (
                <T taille={fs[4]} poids={700} couleur={heuresReelles < formuleChoisie.heures ? c.alerte : c.noir}>
                  Visible en tête jusqu&apos;au {dateFr(finMiseEnAvant, 'l j F · H\\hi')} ({heuresReelles} h
                  {heuresReelles < formuleChoisie.heures ? ` au lieu de ${formuleChoisie.heures} h : l'événement se termine avant` : ''}).
                </T>
              )
            ) : null}
            {formuleChoisie.notifications > 0 ? (
              <>
                <T taille={fs[4]} poids={700} style={{ marginTop: 8 }}>
                  {formuleChoisie.notifications} notification{formuleChoisie.notifications > 1 ? 's' : ''} comprise{formuleChoisie.notifications > 1 ? 's' : ''}
                  {nbEnvoyees > 0 ? ` · ${nbEnvoyees} déjà envoyée${nbEnvoyees > 1 ? 's' : ''}` : ''}
                </T>
                {sponsorActif?.envoyees.map((h) => (
                  <T key={h} taille={fs[4]} couleur={c.gris}>Envoyée le {dateFr(h, 'l j F · H\\hi')}</T>
                ))}
                {Array.from({ length: nbNotifs }, (_, i) => (
                  <ChampDate key={i} etiquette={`Notification ${nbEnvoyees + i + 1}`} mode="datetime" valeur={sponsorNotifs[i] ?? null}
                    onChange={(d) => setSponsorNotifs((prev) => { const n = [...prev]; n[i] = d; return n; })} min={new Date()} />
                ))}
                {nbNotifs > 0 ? (
                  <T taille={fs[4]} couleur={c.gris}>
                    Elles partent vers les abonnés de ton établissement, pendant la mise en avant et avant le début de l&apos;événement. Laisse vide pour ne pas en envoyer.
                  </T>
                ) : null}
              </>
            ) : null}
          </View>
        ) : null}
        </View>)}

        <Bouton libelle={evenementId ? 'Enregistrer les modifications' : "Publier l'événement"} plein
          desactive={!publiable || (sponsoNouveau && dejaTermine)}
          variante={club ? 'sport' : 'primaire'} chargement={enregistrement} onPress={enregistrer} style={{ marginTop: 4 }} />
      </Contenu>
    </Ecran>
  );
}
