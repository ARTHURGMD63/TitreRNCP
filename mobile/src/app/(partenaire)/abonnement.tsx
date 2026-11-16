/**
 * Abonnement établissement — partenaire/abonnement.php.
 *
 * Aucun paiement en ligne, comme sur le site : choisir une formule pose
 * l'engagement et la date de première facturation, l'équipe Outsee contacte
 * ensuite pour le prélèvement. Passage obligé avant le tableau de bord tant
 * qu'aucune formule n'est choisie (voir partenaire_dashboard.php,
 * code `abonnement_requis`).
 */

import React, { useCallback, useState } from 'react';
import { Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, ErreurApi, type FormulePartenaireAbonnement, type ReponseAbonnementPartenaire } from '../../api';
import { Apparition, PressableAnime } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { CarreIcone, EnTeteApp, Pastille, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Encart } from '../../composants/Elements';
import { Case } from '../../composants/Formulaire';
import { T, TitreEcran, TitreSection } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { useJeton } from '../../session';
import { fs, halo, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

export default function AbonnementPartenaire() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const toast = useToast();

  const [donnees, setDonnees] = useState<ReponseAbonnementPartenaire | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [selection, setSelection] = useState<string | null>(null);
  const [engagement, setEngagement] = useState(false);
  const [envoi, setEnvoi] = useState(false);
  const [erreurEnvoi, setErreurEnvoi] = useState<string | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const rep = await api.partenaireAbonnement(jeton);
      setDonnees(rep);
      setSelection((s) => s ?? rep.client?.offre ?? rep.formules[0]?.code ?? null);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function souscrire() {
    if (!selection) return;
    setEnvoi(true);
    setErreurEnvoi(null);
    try {
      const rep = await api.partenaireSouscrire(jeton, selection);
      toast(rep.message ?? 'Formule enregistrée.', 'success');
      setEngagement(false);
      router.replace('/');
    } catch (e) {
      setErreurEnvoi(e instanceof ErreurApi ? e.message : 'Erreur réseau');
    } finally {
      setEnvoi(false);
    }
  }

  if (!donnees) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const { client, formules } = donnees;

  return (
    <Ecran>
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp />
        </Apparition>
        <Apparition index={1}>
          <TitreEcran avant="Choisis ta" surligne="formule." style={{ marginBottom: 12 }} />
          <T taille={fs[5]} couleur={c.gris} style={{ marginBottom: 16 }}>
            Sans engagement de durée, résiliable au mois par simple message.
          </T>
        </Apparition>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {erreurEnvoi ? <Encart genre="erreur">{erreurEnvoi}</Encart> : null}

        {client ? (
          <View style={[{ backgroundColor: bleu.accent, borderRadius: rayon.carte, padding: 20, gap: 4, marginBottom: 20 }, halo(bleu.accent)]}>
            <Text style={{ fontFamily: sans(600), fontSize: 12, letterSpacing: 0.06 * 12, textTransform: 'uppercase', color: bleu.surAccent2 }}>Formule en cours</Text>
            <Text style={{ fontFamily: sans(700), fontSize: 26, letterSpacing: -0.03 * 26, color: '#FFFFFF' }}>{client.offre_libelle}</Text>
            <T taille={fs[5]} couleur={bleu.surAccent2}>
              {client.mrr > 0 ? `${client.mrr.toLocaleString('fr-FR')} € HT / mois` : 'Gratuit'}
              {client.essai_jusquau ? ` · offert jusqu'au ${client.essai_jusquau.split('-').reverse().join('/')}` : ''}
            </T>
          </View>
        ) : null}

        <TitreSection style={{ marginBottom: 12 }}>{client ? 'Changer de formule' : 'Formules'}</TitreSection>
        {formules.map((f: FormulePartenaireAbonnement) => {
          const active = selection === f.code;
          return (
            <PressableAnime
              key={f.code}
              onPress={() => setSelection(f.code)}
              accessibilityRole="radio"
              accessibilityState={{ checked: active }}
              style={{
                backgroundColor: c.blanc, borderWidth: 2, borderColor: active ? bleu.accent : c.blanc,
                borderRadius: rayon.carte, padding: 18, marginBottom: 12, gap: 6,
              }}
            >
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, flexShrink: 1 }}>
                  <View style={{ width: 22, height: 22, borderRadius: 11, borderWidth: 2, borderColor: active ? bleu.accent : c.line2, alignItems: 'center', justifyContent: 'center' }}>
                    {active ? <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: bleu.accent }} /> : null}
                  </View>
                  <T taille={fs[5]} poids={700} style={{ flexShrink: 1 }}>{f.libelle}</T>
                </View>
                {f.code === 'fondateur' && f.places_restantes !== null ? (
                  <Pastille libelle={`${f.places_restantes} place${f.places_restantes > 1 ? 's' : ''} restante${f.places_restantes > 1 ? 's' : ''}`} fond={c.succesClair} encre={c.succes} />
                ) : null}
              </View>
              <Text style={{ fontFamily: sans(700), fontSize: 26, letterSpacing: -0.03 * 26, color: c.noir }}>
                {f.tarif > 0 ? `${f.tarif} € ` : '0 € '}
                <Text style={{ fontFamily: sans(400), fontSize: fs[4], color: c.gris }}>HT / mois</Text>
              </Text>
              <T taille={fs[4]} couleur={c.gris}>{f.note}</T>
              <T taille={fs[4]} couleur={c.gris} style={{ marginTop: 4, paddingTop: 10, borderTopWidth: 1, borderTopColor: c.grisClair }}>
                {f.code === 'fondateur'
                  ? `Offert ${f.mois_essai} mois, puis tarif gelé tant que l'abonnement n'est pas interrompu.`
                  : f.code === 'club'
                    ? `Offert ${f.mois_essai} mois, puis facturé chaque mois.`
                    : "Offert jusqu'à ta première soirée test publiée et scannée."}
              </T>
            </PressableAnime>
          );
        })}

        <View style={{ flexDirection: 'row', gap: 12, alignItems: 'flex-start', backgroundColor: bleu.doux, borderRadius: rayon.carte, padding: 18, marginTop: 4, marginBottom: 20 }}>
          <CarreIcone icone="check" fond={bleu.accent} encre="#FFFFFF" taille={36} />
          <View style={{ flex: 1 }}>
          <T taille={fs[5]} poids={700} couleur={bleu.texteDoux} style={{ marginBottom: 6 }}>Dans toutes les formules</T>
          <T taille={fs[4]} couleur={bleu.texteDoux}>
            {donnees.est_club
              ? 'Événements sportifs et créneaux de terrain illimités, page établissement avec photos, tableau de bord des inscrits, scan des pass. Les joueurs règlent leurs terrains chez toi : Outsee ne prend aucune commission.'
              : 'Soirées illimitées, page établissement avec photos, tableau de bord des inscrits et des présents, scan des pass à l’entrée. Tu fixes ta remise étudiante, y compris à zéro.'}
          </T>
          </View>
        </View>

        <Case coche={engagement} onChange={setEngagement} style={{ marginBottom: 16, alignItems: 'flex-start' }}>
          J&apos;ai pris connaissance du tarif et je souhaite souscrire. Facturation mensuelle,
          sans engagement de durée, résiliable par simple message.
        </Case>

        <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 18 }}>
          Aucune donnée bancaire n&apos;est demandée ici. L&apos;équipe Outsee te contacte pour la mise
          en place du prélèvement avant la première échéance.
        </T>

        <Bouton
          libelle={client ? 'Changer de formule' : "Souscrire et accéder à mon espace"}
          plein
          desactive={!selection || !engagement}
          chargement={envoi}
          onPress={souscrire}
        />
      </Contenu>
    </Ecran>
  );
}
