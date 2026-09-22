/**
 * Wallet — les pass et les economies.
 *
 * Le QR code est dessine ici, a partir de la chaine rendue par l'API. Ses deux
 * couleurs viennent de `fixe` et jamais du theme : c'est la lecon du web, ou
 * elles etaient prises dans les jetons et donnaient, en mode sombre, un code
 * creme sur blanc — invisible a l'oeil et refuse par les lecteurs.
 */

import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import QRCode from 'react-native-qrcode-svg';

import { api, type Pass } from '../../api';
import { useJeton } from '../../session';
import { useChargement } from '../../useChargement';
import { useTheme } from '../../useTheme';
import { espace, fixe, rayon, taille } from '../../theme';
import { Vide } from '../../composants/Vide';

/** La carte d'un pass, avec son code revele au toucher. */
function CartePass({ pass }: { pass: Pass }) {
  const { c, ombre } = useTheme();
  const [revele, setRevele] = useState(false);

  return (
    <View style={[s.pass, { backgroundColor: c.blanc, borderColor: c.grisClair }, ombre('sm')]}>
      <View style={s.passEntete}>
        <Text style={[s.passEtiquette, { color: c.gris }]}>PASS ÉTUDIANT</Text>
        {pass.economie !== null && (
          <Text style={[s.passEconomie, { color: c.rouge }]}>
            −{pass.economie.toFixed(2).replace('.', ',')} €
          </Text>
        )}
      </View>

      <Pressable
        onPress={() => setRevele(true)}
        // Le code n'apparait qu'au toucher : un pass affiche en clair dans la
        // liste se photographie par-dessus l'epaule, et il vaut une entree.
        style={[
          s.zoneCode,
          {
            backgroundColor: revele ? fixe.qrClair : c.rouge,
          },
        ]}
      >
        {revele && pass.code_qr !== null ? (
          <QRCode
            value={pass.code_qr}
            size={180}
            color={fixe.qrSombre}
            backgroundColor={fixe.qrClair}
            // Niveau H : un quart du code peut etre masque — un doigt, un
            // reflet — sans perdre la lecture. Un pass se scanne dans un bar,
            // pas dans un studio.
            ecl="H"
          />
        ) : (
          <Text style={[s.zoneCodeTexte, { color: fixe.surMedia }]}>
            Appuyer{'\n'}pour afficher.
          </Text>
        )}
      </Pressable>

      <Text style={[s.passTitre, { color: c.noir }]}>
        {pass.etablissement} — {pass.titre}
      </Text>
      {pass.date_heure !== null && (
        <Text style={[s.passDate, { color: c.gris }]}>
          {new Date(pass.date_heure.replace(' ', 'T')).toLocaleDateString('fr-FR', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
          })}
          {pass.reduction !== null ? ` · −${pass.reduction}%` : ''}
        </Text>
      )}
    </View>
  );
}

export default function Wallet() {
  const jeton = useJeton();
  const { c } = useTheme();
  const marges = useSafeAreaInsets();

  const { donnees, chargement, rafraichit, erreur, recharger, rafraichir } =
    useChargement(useCallback(() => api.wallet(jeton), [jeton]));

  if (chargement) {
    return (
      <View style={[s.centre, { backgroundColor: c.bg }]}>
        <ActivityIndicator color={c.rouge} />
      </View>
    );
  }

  if (erreur !== null) {
    return (
      <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: marges.top }}>
        <Vide
          icone="wifi-off"
          titre="Rien n'arrive"
          texte={erreur}
          action={{ libelle: 'Réessayer', onPress: recharger }}
        />
      </View>
    );
  }

  const actifs = donnees?.pass_actifs ?? [];

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: c.bg }}
      contentContainerStyle={{
        paddingTop: marges.top + espace.base,
        paddingHorizontal: espace.lg,
        paddingBottom: espace.xxl,
      }}
      refreshControl={
        <RefreshControl refreshing={rafraichit} onRefresh={rafraichir} tintColor={c.rouge} />
      }
    >
      <Text style={[s.marque, { color: c.noir }]}>
        StudentLink <Text style={{ color: c.rouge, fontStyle: 'italic' }}>/ Wallet</Text>
      </Text>

      <Text style={[s.titre, { color: c.noir }]}>
        Ton pass,{'\n'}
        <Text style={{ fontStyle: 'italic' }}>en poche.</Text>
      </Text>

      <View style={s.economies}>
        <View style={[s.tuile, { backgroundColor: c.rouge }]}>
          <Text style={[s.tuileLabel, { color: fixe.surMedia }]}>CE MOIS</Text>
          <Text style={[s.tuileValeur, { color: fixe.surMedia }]}>
            {(donnees?.economies.mois ?? 0).toFixed(0)} €
          </Text>
          <Text style={[s.tuileSous, { color: fixe.surMedia }]}>économisé</Text>
        </View>

        <View style={[s.tuile, { backgroundColor: c.blanc, borderColor: c.grisClair, borderWidth: 1 }]}>
          <Text style={[s.tuileLabel, { color: c.gris }]}>TOTAL ANNÉE</Text>
          <Text style={[s.tuileValeur, { color: c.noir }]}>
            {(donnees?.economies.annee ?? 0).toFixed(0)} €
          </Text>
          <Text style={[s.tuileSous, { color: c.gris }]}>depuis janvier</Text>
        </View>
      </View>

      {actifs.length === 0 ? (
        <Vide
          icone="credit-card"
          titre="Aucun pass"
          texte="Inscris-toi à une soirée depuis Explore, ton pass apparaîtra ici."
        />
      ) : (
        actifs.map((p) => <CartePass key={p.id} pass={p} />)
      )}

      {(donnees?.historique.length ?? 0) > 0 && (
        <>
          <Text style={[s.section, { color: c.gris, borderTopColor: c.grisClair }]}>
            {donnees?.historique.length} PASS PASSÉ
            {(donnees?.historique.length ?? 0) > 1 ? 'S' : ''}
          </Text>

          {donnees?.historique.map((p) => (
            <View key={p.id} style={[s.ligneHisto, { borderBottomColor: c.grisClair }]}>
              <View style={{ flex: 1 }}>
                <Text style={[s.histoTitre, { color: c.noir }]} numberOfLines={1}>
                  {p.etablissement}
                </Text>
                <Text style={[s.histoSous, { color: c.gris }]} numberOfLines={1}>
                  {p.titre}
                </Text>
              </View>
              {p.economie !== null && (
                <Text style={[s.histoMontant, { color: c.rouge }]}>
                  −{p.economie.toFixed(2).replace('.', ',')} €
                </Text>
              )}
            </View>
          ))}
        </>
      )}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  marque: { fontSize: taille.titre, fontWeight: '700' },
  titre: { fontSize: taille.grand, fontWeight: '900', lineHeight: 32, marginTop: espace.md },
  economies: { flexDirection: 'row', gap: espace.base, marginTop: espace.lg },
  tuile: { flex: 1, borderRadius: rayon.base, padding: espace.md },
  tuileLabel: { fontSize: taille.xs, fontWeight: '700', letterSpacing: 1 },
  tuileValeur: { fontSize: taille.grand, fontWeight: '900', marginTop: espace.xs },
  tuileSous: { fontSize: taille.base, opacity: 0.9 },

  pass: {
    borderWidth: 1,
    borderRadius: rayon.base,
    padding: espace.md,
    marginTop: espace.md,
  },
  passEntete: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  passEtiquette: { fontSize: taille.xs, fontWeight: '700', letterSpacing: 1 },
  passEconomie: { fontSize: taille.texte, fontWeight: '900' },
  zoneCode: {
    borderRadius: rayon.sm,
    marginTop: espace.base,
    minHeight: 210,
    alignItems: 'center',
    justifyContent: 'center',
    // La marge blanche autour du code est la « zone de silence » exigee par la
    // norme : sans elle, le lecteur ne trouve pas les reperes d'angle.
    padding: espace.md,
  },
  zoneCodeTexte: { fontSize: taille.titre, fontWeight: '900', fontStyle: 'italic', textAlign: 'center' },
  passTitre: { fontSize: taille.texte, fontWeight: '700', marginTop: espace.base },
  passDate: { fontSize: taille.base, marginTop: 2 },

  section: {
    fontSize: taille.xs,
    fontWeight: '700',
    letterSpacing: 1,
    borderTopWidth: 1,
    marginTop: espace.xl,
    paddingTop: espace.md,
  },
  ligneHisto: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espace.base,
    borderBottomWidth: 1,
    paddingVertical: espace.base,
  },
  histoTitre: { fontSize: taille.texte, fontWeight: '700' },
  histoSous: { fontSize: taille.base, marginTop: 1 },
  histoMontant: { fontSize: taille.texte, fontWeight: '700' },
});
