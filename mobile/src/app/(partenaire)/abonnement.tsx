/**
 * Abonnement établissement — partenaire/abonnement.php.
 *
 * Aucun paiement en ligne, comme sur le site : choisir une formule pose
 * l'engagement et la date de première facturation, l'équipe Linkee contacte
 * ensuite pour le prélèvement. Passage obligé avant le tableau de bord tant
 * qu'aucune formule n'est choisie (voir partenaire_dashboard.php,
 * code `abonnement_requis`).
 */

import React, { useCallback, useState } from 'react';
import { Pressable, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, ErreurApi, type FormulePartenaireAbonnement, type ReponseAbonnementPartenaire } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Encart } from '../../composants/Elements';
import { Case } from '../../composants/Formulaire';
import { Display, Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { useJeton } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

export default function AbonnementPartenaire() {
  const { c } = useTheme();
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
      <EnTete>
        <TitreEcran lignes={['Choisis ta', 'formule.']} />
        <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 10 }}>
          Sans engagement de durée, résiliable au mois par simple message.
        </T>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {erreurEnvoi ? <Encart genre="erreur">{erreurEnvoi}</Encart> : null}

        {client ? (
          <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 18, marginBottom: 20 }}>
            <Mono couleur={c.gris} style={{ marginBottom: 4 }}>FORMULE EN COURS</Mono>
            <Display taille={fs[6]} style={{ marginBottom: 2 }}>{client.offre_libelle}</Display>
            <T taille={fs[3]} couleur={c.gris}>
              {client.mrr > 0 ? `${client.mrr.toLocaleString('fr-FR')} € HT / mois` : 'Gratuit'}
              {client.essai_jusquau ? ` · offert jusqu'au ${client.essai_jusquau.split('-').reverse().join('/')}` : ''}
            </T>
          </View>
        ) : null}

        <Mono style={{ marginBottom: 12 }}>{client ? 'Changer de formule' : 'Formules'}</Mono>
        {formules.map((f: FormulePartenaireAbonnement) => {
          const active = selection === f.code;
          return (
            <Pressable
              key={f.code}
              onPress={() => setSelection(f.code)}
              accessibilityRole="radio"
              accessibilityState={{ checked: active }}
              style={{
                backgroundColor: c.blanc, borderWidth: active ? 1.5 : 1, borderColor: active ? c.rouge : c.grisClair,
                borderRadius: rayon.base, padding: 18, marginBottom: 12,
              }}
            >
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 6 }}>
                <T taille={fs[4]} poids={700}>{f.libelle}</T>
                {f.code === 'fondateur' && f.places_restantes !== null ? (
                  <View style={{ backgroundColor: c.lime, borderRadius: rayon.pill, paddingHorizontal: 10, paddingVertical: 3 }}>
                    <T taille={fs[1]} poids={700}>{f.places_restantes} place{f.places_restantes > 1 ? 's' : ''} restante{f.places_restantes > 1 ? 's' : ''}</T>
                  </View>
                ) : null}
              </View>
              <Display taille={fs[7]} style={{ marginBottom: 4 }}>
                {f.tarif > 0 ? `${f.tarif} € ` : '0 € '}
                <T taille={fs[2]} couleur={c.gris}>HT / mois</T>
              </Display>
              <T taille={fs[3]} couleur={c.gris}>{f.note}</T>
              <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 8, paddingTop: 8, borderTopWidth: 1, borderTopColor: c.grisClair }}>
                {f.code === 'fondateur'
                  ? `Offert ${f.mois_essai} mois, puis tarif gelé tant que l'abonnement n'est pas interrompu.`
                  : "Offert jusqu'à ta première soirée test publiée et scannée."}
              </T>
            </Pressable>
          );
        })}

        <View style={{ backgroundColor: c.grisClair, borderRadius: rayon.md, padding: 16, marginTop: 8, marginBottom: 20 }}>
          <T taille={fs[3]} poids={700} style={{ marginBottom: 6 }}>Dans toutes les formules</T>
          <T taille={fs[3]} couleur={c.gris}>
            Soirées illimitées, page établissement avec photos, tableau de bord des inscrits et des présents,
            scan des pass à l&apos;entrée. Tu fixes ta remise étudiante, y compris à zéro.
          </T>
        </View>

        <Case coche={engagement} onChange={setEngagement} style={{ marginBottom: 16, alignItems: 'flex-start' }}>
          J&apos;ai pris connaissance du tarif et je souhaite souscrire. Facturation mensuelle,
          sans engagement de durée, résiliable par simple message.
        </Case>

        <T taille={fs[2]} couleur={c.gris} style={{ marginBottom: 18 }}>
          Aucune donnée bancaire n&apos;est demandée ici. L&apos;équipe Linkee te contacte pour la mise
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
