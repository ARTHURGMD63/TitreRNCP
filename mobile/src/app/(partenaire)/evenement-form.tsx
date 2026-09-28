/**
 * Créer / modifier un événement — partenaire/create_event.php et
 * edit_event.php, réunis en un seul écran comme le fait déjà
 * api/v1/partenaire_evenement.php côté serveur.
 *
 * Mêmes validations qu'en PHP, mais uniquement côté affichage : le serveur
 * revérifie tout (le quota flash en particulier), jamais confiance au
 * client sur le tarif de sponsoring.
 */

import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api, ErreurApi, type ReponseFormulaireEvenementPartenaire } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Encart } from '../../composants/Elements';
import { Case, Champ, ChampDate, isoDateHeure, Selecteur } from '../../composants/Formulaire';
import { Mono, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { useJeton } from '../../session';

const TYPES = [
  { valeur: 'bar', libelle: 'Bar' },
  { valeur: 'boite', libelle: 'Boîte de nuit' },
  { valeur: 'resto', libelle: 'Restaurant' },
  { valeur: 'afterwork', libelle: 'Afterwork' },
];

export default function FormulaireEvenementPartenaire() {
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
  const [date, setDate] = useState<Date | null>(null);
  const [dateFin, setDateFin] = useState<Date | null>(null);
  const [lieu, setLieu] = useState('');
  const [quota, setQuota] = useState('100');
  const [prixNormal, setPrixNormal] = useState('0');
  const [reduction, setReduction] = useState('0');
  const [isGratuit, setIsGratuit] = useState(false);
  const [isFlash, setIsFlash] = useState(false);
  const [flashExpiry, setFlashExpiry] = useState<Date | null>(null);
  const [isSponsorise, setIsSponsorise] = useState(false);
  const [sponsorFormule, setSponsorFormule] = useState('');

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
        setDate(new Date(e.date_heure.replace(' ', 'T')));
        setDateFin(e.date_fin ? new Date(e.date_fin.replace(' ', 'T')) : null);
        setLieu(e.lieu);
        setQuota(String(e.quota));
        setPrixNormal(String(e.prix_normal));
        setReduction(String(e.reduction));
        setIsGratuit(e.is_gratuit);
        setIsFlash(e.is_flash);
        setFlashExpiry(e.flash_expiry ? new Date(e.flash_expiry.replace(' ', 'T')) : null);
        setIsSponsorise(e.is_sponsorise);
        setSponsorFormule(e.sponsor_formule ?? rep.formules_sponsoring[0]?.code ?? '');
      } else {
        setLieu(rep.lieu_defaut);
        setSponsorFormule(rep.formules_sponsoring[0]?.code ?? '');
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
        style_musique: styleMusique || undefined,
        date_heure: date ? isoDateHeure(date) : '',
        date_fin: dateFin ? isoDateHeure(dateFin) : undefined,
        lieu, quota: Number(quota), prix_normal: Number(prixNormal), reduction: Number(reduction),
        is_gratuit: isGratuit, is_flash: isFlash,
        flash_expiry: isFlash && flashExpiry ? isoDateHeure(flashExpiry) : undefined,
        is_sponsorise: isSponsorise, sponsor_formule: isSponsorise ? sponsorFormule : undefined,
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

  return (
    <Ecran>
      <EnTete>
        <TitreEcran lignes={[evenementId ? 'Modifier' : 'Créer', 'un événement.']} />
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {erreurEnregistrement ? <Encart genre="erreur">{erreurEnregistrement}</Encart> : null}

        <Mono style={{ marginBottom: 12 }}>Informations générales</Mono>
        <Champ etiquette="Titre *" value={titre} onChangeText={setTitre} placeholder="ex : Soirée Étudiants — DJ Groove" />
        <View style={{ flexDirection: 'row', gap: 12 }}>
          <Selecteur style={{ flex: 1 }} etiquette="Type *" valeur={type} onChange={setType} options={TYPES} />
          <Selecteur style={{ flex: 1 }} etiquette="Style de musique" valeur={styleMusique} onChange={setStyleMusique} options={optionsMusique} />
        </View>
        <ChampDate etiquette="Début *" mode="datetime" valeur={date} onChange={setDate} min={new Date()} />
        <ChampDate etiquette="Fin" mode="datetime" valeur={dateFin} onChange={setDateFin} min={date ?? new Date()} />
        <Champ etiquette="Description" value={description} onChangeText={setDescription} multiligne numberOfLines={3}
          placeholder="Ambiance, animations, dress code..." />
        <Champ etiquette="Lieu / Salle" value={lieu} onChangeText={setLieu} />

        <Mono style={{ marginTop: 8, marginBottom: 12 }}>Capacité & tarifs</Mono>
        <View style={{ flexDirection: 'row', gap: 12 }}>
          <Champ style={{ flex: 1 }} etiquette="Quota (places max) *" value={quota} onChangeText={setQuota} keyboardType="number-pad" />
          <Champ style={{ flex: 1 }} etiquette="Prix normal (€)" value={prixNormal} onChangeText={setPrixNormal} keyboardType="decimal-pad" />
        </View>
        <Champ etiquette="Réduction étudiants (%)" value={reduction} onChangeText={setReduction} keyboardType="number-pad" aide="Affichée sur le pass étudiant." />
        <Case coche={isGratuit} onChange={setIsGratuit} style={{ marginBottom: 20 }}>Entrée gratuite — le pass sera 100% offert</Case>

        <Mono style={{ marginBottom: 12 }}>Options avancées</Mono>
        <Case coche={isFlash} onChange={setIsFlash} style={{ marginBottom: isFlash ? 12 : 20 }}>
          Event Flash — offre limitée dans le temps, en tête du fil
        </Case>
        {isFlash ? (
          <ChampDate etiquette="Expiration de l'offre flash" mode="datetime" valeur={flashExpiry} onChange={setFlashExpiry} min={new Date()} />
        ) : null}
        {!donnees.flash_illimite ? (
          <Mono couleur="#67626D" style={{ marginTop: -12, marginBottom: 16 }}>{donnees.flash_par_mois} offres flash / mois incluses dans ta formule</Mono>
        ) : null}

        <Case coche={isSponsorise} onChange={setIsSponsorise} style={{ marginBottom: isSponsorise ? 12 : 20 }}>
          Post sponsorisé — remonte en tête du fil Explore
        </Case>
        {isSponsorise ? (
          <Selecteur
            etiquette="Formule de sponsoring"
            valeur={sponsorFormule}
            onChange={setSponsorFormule}
            options={donnees.formules_sponsoring.map((f) => ({ valeur: f.code, libelle: `${f.nom} — ${f.tarif} €` }))}
          />
        ) : null}

        <Bouton libelle={evenementId ? 'Enregistrer les modifications' : "Publier l'événement"} plein
          chargement={enregistrement} onPress={enregistrer} style={{ marginTop: 12 }} />
      </Contenu>
    </Ecran>
  );
}
