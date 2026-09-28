/**
 * Finances — admin/finances.php, en lecture seule (api/v1/admin_finances.php).
 *
 * Trésorerie et son plancher (SL-13), MRR facturé/engagé, résultat du mois
 * courant, les vingt derniers mouvements. Créer, régler ou supprimer un
 * mouvement reste réservé au site (formulaire dense, graphique 12 mois) —
 * cet écran sert à consulter sans changer d'application, pas à tout refaire.
 */

import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { api, ErreurApi, type ReponseFinancesAdmin } from '../../api';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { BoutonRetour } from '../../composants/Elements';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { dateFr, nombre } from '../../format';
import { useJeton } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

function eur(montant: number): string {
  return nombre(Math.round(montant)) + ' €';
}

function Carte({ children }: { children: React.ReactNode }) {
  const { c, ombre } = useTheme();
  return (
    <View style={[{ backgroundColor: c.blanc, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, padding: 18, marginBottom: 14 }, ombre('sm')]}>
      {children}
    </View>
  );
}

export default function FinancesAdmin() {
  const { c } = useTheme();
  const jeton = useJeton();
  const [donnees, setDonnees] = useState<ReponseFinancesAdmin | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.adminFinances(jeton));
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
        <Contenu style={{ paddingTop: 36 }}><BoutonRetour /></Contenu>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const { tresorerie, mrr, mois_courant: mois, mouvements } = donnees;

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete>
        <BoutonRetour style={{ marginBottom: 16 }} />
        <TitreEcran lignes={['Finances.']} />
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <Carte>
          <Mono couleur={c.gris} style={{ marginBottom: 6 }}>Trésorerie</Mono>
          <T taille={fs[8]} poids={700} couleur={tresorerie.sous_le_plancher ? c.danger : undefined}>{eur(tresorerie.solde)}</T>
          <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 4 }}>
            plancher {eur(tresorerie.plancher)}
            {tresorerie.autonomie_mois !== null ? ` · ${tresorerie.autonomie_mois.toFixed(1)} mois d'autonomie` : ''}
          </T>
        </Carte>

        <View style={{ flexDirection: 'row', gap: 10, marginBottom: 14 }}>
          <View style={{ flex: 1 }}>
            <Carte>
              <Mono couleur={c.gris} style={{ marginBottom: 6 }}>MRR facturé</Mono>
              <T taille={fs[6]} poids={700}>{eur(mrr.facture)}</T>
              <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 4 }}>{mrr.clients_payants} client{mrr.clients_payants > 1 ? 's' : ''} payant{mrr.clients_payants > 1 ? 's' : ''}</T>
            </Carte>
          </View>
          <View style={{ flex: 1 }}>
            <Carte>
              <Mono couleur={c.gris} style={{ marginBottom: 6 }}>MRR engagé</Mono>
              <T taille={fs[6]} poids={700}>{eur(mrr.engage)}</T>
            </Carte>
          </View>
        </View>

        <Carte>
          <Mono couleur={c.gris} style={{ marginBottom: 10 }}>Ce mois-ci</Mono>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 }}>
            <T taille={fs[4]}>Recettes réglées</T>
            <T taille={fs[4]} poids={700} couleur={c.succes}>{eur(mois.recettes)}</T>
          </View>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 }}>
            <T taille={fs[4]}>Dépenses réglées</T>
            <T taille={fs[4]} poids={700} couleur={c.danger}>{eur(mois.depenses)}</T>
          </View>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingTop: 8, borderTopWidth: 1, borderTopColor: c.grisClair }}>
            <T taille={fs[4]} poids={700}>Résultat</T>
            <T taille={fs[4]} poids={700} couleur={mois.resultat >= 0 ? c.succes : c.danger}>{eur(mois.resultat)}</T>
          </View>
        </Carte>

        <Mono couleur={c.gris} style={{ marginBottom: 10 }}>Derniers mouvements</Mono>
        {mouvements.length === 0 ? (
          <T taille={fs[4]} couleur={c.gris} style={{ textAlign: 'center', paddingVertical: 24 }}>Aucun mouvement enregistré.</T>
        ) : (
          <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, paddingHorizontal: 16 }}>
            {mouvements.map((m, i) => (
              <View key={m.id} style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingVertical: 12, borderBottomWidth: i < mouvements.length - 1 ? 1 : 0, borderBottomColor: c.grisClair }}>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <T taille={fs[4]} poids={600}>{m.libelle}</T>
                  <T taille={fs[2]} couleur={c.gris}>
                    {dateFr(m.date, 'j M')} · {m.categorie}{m.client ? ' · ' + m.client : ''}{m.statut === 'prevu' ? ' · prévu' : ''}
                  </T>
                </View>
                <T taille={fs[4]} poids={700} couleur={m.sens === 'recette' ? c.succes : c.danger}>
                  {m.sens === 'recette' ? '+' : '−'}{eur(m.montant_ht)}
                </T>
              </View>
            ))}
          </View>
        )}
      </Contenu>
    </Ecran>
  );
}
