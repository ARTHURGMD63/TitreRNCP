/**
 * Scan des pass à l'entrée — la modale de partenaire/dashboard.php, en
 * plein écran natif.
 *
 * Même règle que le site : un scan à la fois (on ignore les suivants tant
 * que la requête précédente n'a pas répondu), un bip et une carte de
 * profil sur succès (photo, nom, école — pas qu'un texte), un message rouge
 * sur échec, puis reprise automatique — le scanner reste ouvert pour
 * enchaîner les entrées, contrairement au site qui recharge toute la page.
 *
 * La liste de qui a été check-in reste visible pendant tout le scan (pastille
 * en haut, feuille au tap) : c'est elle qui répond à « qui j'ai eu ce soir ».
 *
 * « Valider manuellement » (feuille dédiée) cherche parmi les inscrits pas
 * encore check-in de CET événement et les valide sans scan — pour quand le
 * QR code ne marche pas (lumière, code abîmé, plusieurs soirées le même
 * soir). Voir api/v1/partenaire_invites.php et le paramètre inscription_id
 * de api/v1/partenaire_scan.php.
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Linking, Pressable, Text, TextInput, View } from 'react-native';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, ErreurApi, type PersonneCheckin } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { teinteAvatar } from '../../composants/Design';
import { Avatar } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Icone } from '../../composants/Icone';
import { Display, T } from '../../composants/Texte';
import { ajouterCheckin, delaiResultatScan, lireCodePass, lireIdEvenement, MESSAGE_QR_ETRANGER, messageEchecScan, retirerInvite } from '../../regles';
import { useToast } from '../../composants/Toast';
import { useJeton } from '../../session';
import { fixe, fs, lh, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

type Personne = { id: number; prenom: string; nom: string; ecole: string | null; promo: string | null; photo_url: string | null };

export default function ScanPass() {
  // Les feuilles (liste, validation manuelle) suivent le thème ; l'écran
  // caméra, lui, reste sombre.
  const { c } = useTheme();
  const jeton = useJeton();
  const { top, bottom } = useSafeAreaInsets();
  const { evenementId } = useLocalSearchParams<{ evenementId: string }>();
  // null : adresse sans événement valable. L'écran d'erreur s'affiche plus
  // bas, une fois tous les hooks appelés (constat BUG-07).
  const idEvenement = lireIdEvenement(evenementId);
  const [permission, demanderPermission] = useCameraPermissions();

  const toast = useToast();

  const [messageEchec, setMessageEchec] = useState<string | null>(null);
  const [personneValidee, setPersonneValidee] = useState<Personne | null>(null);
  const [checkins, setCheckins] = useState<PersonneCheckin[]>([]);
  const [listeOuverte, setListeOuverte] = useState(false);
  const enTraitement = useRef(false);
  // La reprise après un résultat : annulée si l'écran se ferme avant, pour ne
  // pas modifier l'état d'un écran démonté.
  const reprise = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => () => { if (reprise.current) clearTimeout(reprise.current); }, []);

  // ── Validation manuelle : quand le scan ne fonctionne pas ──
  const [manuelOuvert, setManuelOuvert] = useState(false);
  const [rechercheManuelle, setRechercheManuelle] = useState('');
  const [invitesManuels, setInvitesManuels] = useState<{ inscription_id: number; prenom: string; nom: string; ecole: string | null; promo: string | null; photo_url: string | null }[] | null>(null);
  const [validationEnCours, setValidationEnCours] = useState<number | null>(null);

  const chercherInvites = useCallback(async (q: string) => {
    if (idEvenement === null) return;
    try {
      setInvitesManuels((await api.partenaireInvites(jeton, idEvenement, q || undefined)).invites);
    } catch {
      setInvitesManuels([]);
    }
  }, [jeton, idEvenement]);

  async function validerManuellement(invite: { inscription_id: number; prenom: string; nom: string; ecole: string | null; promo: string | null; photo_url: string | null }) {
    if (idEvenement === null) return;
    setValidationEnCours(invite.inscription_id);
    try {
      const rep = await api.partenaireScan(jeton, { inscription_id: invite.inscription_id, event_id: idEvenement });
      setCheckins((liste) => ajouterCheckin(liste, { ...rep.personne, checkin_le: new Date().toISOString() }));
      setInvitesManuels((liste) => retirerInvite(liste, invite.inscription_id));
      toast(`${rep.personne.prenom} ${rep.personne.nom} validé·e`, 'success');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setValidationEnCours(null);
    }
  }

  // La liste connue au moment d'ouvrir le scanner, pour CET événement
  // (partenaire_checkins.php) : elle s'enrichit ensuite localement à chaque
  // scan réussi. Avant, l'écran rechargeait tout le tableau de bord, qui ne
  // parle que de la prochaine soirée : scanner pour une autre affichait 0
  // (constat MOB-15). Les entrées déjà connues localement sont gardées si la
  // réponse arrive après un premier scan.
  useEffect(() => {
    if (idEvenement === null) return;
    let annule = false;
    api.partenaireCheckins(jeton, idEvenement).then((rep) => {
      if (annule) return;
      setCheckins((locales) => locales.reduceRight((liste, p) => ajouterCheckin(liste, p), rep.checkins));
    }).catch(() => {});
    return () => { annule = true; };
  }, [jeton, idEvenement]);

  const scanner = useCallback(async (resultat: BarcodeScanningResult) => {
    if (enTraitement.current || idEvenement === null) return;
    enTraitement.current = true;

    let succes = false;
    // Un QR code qui n'est pas un pass (menu, site, code abîmé) est écarté
    // ici, sans appel : le refus est immédiat, même sans réseau.
    const code = lireCodePass(resultat.data);
    if (!code) {
      setMessageEchec(MESSAGE_QR_ETRANGER);
      reprise.current = setTimeout(() => { enTraitement.current = false; setMessageEchec(null); }, delaiResultatScan(false));
      return;
    }
    try {
      const rep = await api.partenaireScan(jeton, { qr_code: `outsee:${code}`, event_id: idEvenement });
      succes = true;
      setPersonneValidee(rep.personne);
      setCheckins((liste) => ajouterCheckin(liste, { ...rep.personne, checkin_le: new Date().toISOString() }));
    } catch (e) {
      setMessageEchec(messageEchecScan(e instanceof ErreurApi ? e : null));
    } finally {
      reprise.current = setTimeout(() => {
        enTraitement.current = false;
        setPersonneValidee(null);
        setMessageEchec(null);
      }, delaiResultatScan(succes));
    }
  }, [jeton, idEvenement]);

  // Une adresse sans événement valable (lien abîmé, paramètre absent) : rien
  // à scanner, et surtout aucun appel avec un identifiant inventé.
  if (idEvenement === null) {
    return (
      <View style={{ flex: 1, backgroundColor: fixe.basalte, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 32, paddingTop: top, paddingBottom: bottom }}>
        <Icone nom="echec" taille={40} couleur={fixe.craie} />
        <Display taille={fs[5]} couleur={fixe.craie} style={{ textAlign: 'center', marginTop: 16, marginBottom: 10 }}>
          Événement introuvable
        </Display>
        <T taille={fs[4]} couleur={fixe.craie} interligne={lh.normal} style={{ textAlign: 'center', marginBottom: 24, opacity: 0.8 }}>
          Ouvre le scanner depuis la fiche de ta soirée, dans Événements.
        </T>
        <Bouton libelle="Retour" variante="contour" plein onPress={() => router.back()}
          styleTexte={{ color: fixe.craie }} style={{ borderColor: fixe.craie }} />
      </View>
    );
  }

  if (!permission) {
    return <View style={{ flex: 1, backgroundColor: fixe.basalte }} />;
  }

  if (!permission.granted) {
    return (
      <View style={{ flex: 1, backgroundColor: fixe.basalte, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 32, paddingTop: top, paddingBottom: bottom }}>
        <Icone nom="appareil" taille={40} couleur={fixe.craie} />
        <Display taille={fs[5]} couleur={fixe.craie} style={{ textAlign: 'center', marginTop: 16, marginBottom: 10 }}>
          Accès à la caméra nécessaire
        </Display>
        <T taille={fs[4]} couleur={fixe.craie} interligne={lh.normal} style={{ textAlign: 'center', marginBottom: 24, opacity: 0.8 }}>
          Pour scanner les pass à l&apos;entrée, autorise Outsee à utiliser la caméra.
        </T>
        {/* Refusée pour de bon : le système ne repose plus la question, seuls
            les réglages du téléphone peuvent la rendre. */}
        <Bouton
          libelle={permission.canAskAgain ? 'Autoriser la caméra' : 'Ouvrir les réglages'}
          plein
          onPress={() => void (permission.canAskAgain ? demanderPermission() : Linking.openSettings().catch(() => {}))}
          style={{ marginBottom: 12 }}
        />
        <Bouton libelle="Retour" variante="contour" plein onPress={() => router.back()}
          styleTexte={{ color: fixe.craie }} style={{ borderColor: fixe.craie }} />
      </View>
    );
  }

  const cadre = personneValidee ? fixe.enCours : messageEchec ? '#FF4D6A' : fixe.craie;

  return (
    <View style={{ flex: 1, backgroundColor: fixe.basalte }}>
      <CameraView
        style={{ flex: 1 }}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={(r) => void scanner(r)}
      />

      <Pressable
        onPress={() => router.back()}
        accessibilityRole="button"
        accessibilityLabel="Fermer le scanner"
        style={{ position: 'absolute', top: top + 16, right: 20, width: 46, height: 46, borderRadius: rayon.bouton, backgroundColor: 'rgba(17,17,22,0.6)', alignItems: 'center', justifyContent: 'center' }}
      >
        <Icone nom="croix" taille={20} couleur={fixe.craie} />
      </Pressable>

      <Pressable
        onPress={() => setListeOuverte(true)}
        accessibilityRole="button"
        accessibilityLabel={`${checkins.length} personnes déjà check-in, voir la liste`}
        style={{ position: 'absolute', top: top + 16, left: 20, flexDirection: 'row', alignItems: 'center', gap: 7, height: 46, paddingHorizontal: 16, borderRadius: rayon.bouton, backgroundColor: 'rgba(17,17,22,0.6)' }}
      >
        <Icone nom="personnes" taille={16} couleur={fixe.craie} />
        <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: fixe.craie }}>{checkins.length}</Text>
      </Pressable>

      <Pressable
        onPress={() => { setManuelOuvert(true); setRechercheManuelle(''); void chercherInvites(''); }}
        accessibilityRole="button"
        accessibilityLabel="Valider une présence manuellement"
        testID="scan-manuel"
        hitSlop={{ top: 1, bottom: 1 }}
        style={{ position: 'absolute', bottom: bottom + 100, left: 24, right: 24, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, minHeight: 42, paddingHorizontal: 12, borderRadius: rayon.bouton, backgroundColor: 'rgba(17,17,22,0.6)' }}
      >
        <Icone nom="outil" taille={16} couleur={fixe.craie} />
        <Text style={{ fontFamily: sans(700), fontSize: fs[3], color: fixe.craie }}>Le scan ne marche pas ? Valider manuellement</Text>
      </Pressable>

      <View
        pointerEvents="none"
        style={{
          position: 'absolute', left: 40, right: 40, top: '50%', marginTop: -125,
          height: 250, borderRadius: rayon.carte, borderWidth: 3, borderColor: cadre,
        }}
      />

      {personneValidee ? (
        <View style={{ position: 'absolute', left: 24, right: 24, bottom: bottom + 40, backgroundColor: '#FFFFFF', borderRadius: rayon.carte, padding: 16, flexDirection: 'row', alignItems: 'center', gap: 14 }}>
          <Avatar photo={personneValidee.photo_url} prenom={personneValidee.prenom} taille={52} fond="#C7DCFF" />
          <View style={{ flex: 1 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 2 }}>
              <Icone nom="valide" taille={16} couleur="#137A44" />
              <Text style={{ fontFamily: sans(700), fontSize: fs[2], letterSpacing: 0.06 * fs[2], color: '#137A44' }}>CHECK-IN VALIDÉ</Text>
            </View>
            <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: fixe.basalte }}>{personneValidee.prenom} {personneValidee.nom}</Text>
            <Text style={{ fontFamily: sans(500), fontSize: fs[4], color: '#5A5A66' }}>
              {personneValidee.ecole ?? '—'}{personneValidee.promo ? ' · ' + personneValidee.promo : ''}
            </Text>
          </View>
        </View>
      ) : messageEchec ? (
        <View style={{ position: 'absolute', left: 0, right: 0, bottom: bottom + 40, alignItems: 'center', paddingHorizontal: 24 }}>
          <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: '#FF4D6A', textAlign: 'center' }}>{messageEchec}</Text>
        </View>
      ) : (
        <View style={{ position: 'absolute', left: 0, right: 0, bottom: bottom + 40, alignItems: 'center', paddingHorizontal: 24 }}>
          <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: fixe.craie, textAlign: 'center' }}>En attente de scan...</Text>
        </View>
      )}

      <Feuille visible={listeOuverte} onClose={() => setListeOuverte(false)}>
        <T taille={20} poids={700} style={{ marginBottom: 16, letterSpacing: -0.03 * 20 }}>
          {checkins.length} check-in{checkins.length > 1 ? 's' : ''}
        </T>
        {checkins.length === 0 ? (
          <T taille={fs[4]} couleur={c.gris}>Personne pour l&apos;instant.</T>
        ) : (
          checkins.map((p, i) => (
            <View key={`${p.id}-${i}`} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, borderBottomWidth: i < checkins.length - 1 ? 1 : 0, borderBottomColor: c.grisClair }}>
              <Avatar photo={p.photo_url} prenom={p.prenom} taille={40} fond={teinteAvatar(p.prenom + p.id)} />
              <View style={{ flex: 1 }}>
                <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: c.noir }}>{p.prenom} {p.nom}</Text>
                <Text style={{ fontFamily: sans(400), fontSize: fs[4], color: c.gris }}>{p.ecole ?? '—'}{p.promo ? ' · ' + p.promo : ''}</Text>
              </View>
            </View>
          ))
        )}
      </Feuille>

      <Feuille visible={manuelOuvert} onClose={() => setManuelOuvert(false)}>
        <T taille={20} poids={700} style={{ marginBottom: 6, letterSpacing: -0.03 * 20 }}>Valider manuellement</T>
        <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 16 }}>
          Cherche la personne parmi les inscrits pas encore validés, si le QR code ne fonctionne pas.
        </T>
        <TextInput
          value={rechercheManuelle}
          onChangeText={(v) => { setRechercheManuelle(v); void chercherInvites(v); }}
          placeholder="Prénom ou nom..."
          testID="scan-manuel-recherche"
          placeholderTextColor={c.gris}
          autoFocus
          accessibilityLabel="Chercher un inscrit par son prénom ou son nom"
          style={{ minHeight: 46, paddingVertical: 12, paddingHorizontal: 18, borderWidth: 1, borderColor: c.line2, borderRadius: rayon.bouton, backgroundColor: c.surface2, fontFamily: sans(400), fontSize: fs[4], color: c.noir, marginBottom: 16 }}
        />
        {invitesManuels === null ? (
          <T taille={fs[4]} couleur={c.gris}>Chargement...</T>
        ) : invitesManuels.length === 0 ? (
          <T taille={fs[4]} couleur={c.gris}>Personne à faire correspondre.</T>
        ) : (
          invitesManuels.map((invite, i) => (
            <View key={invite.inscription_id} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, borderBottomWidth: i < invitesManuels.length - 1 ? 1 : 0, borderBottomColor: c.grisClair }}>
              <Avatar photo={invite.photo_url} prenom={invite.prenom} taille={40} fond={teinteAvatar(invite.prenom + invite.inscription_id)} />
              <View style={{ flex: 1 }}>
                <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: c.noir }}>{invite.prenom} {invite.nom}</Text>
                <Text style={{ fontFamily: sans(400), fontSize: fs[4], color: c.gris }}>{invite.ecole ?? '—'}{invite.promo ? ' · ' + invite.promo : ''}</Text>
              </View>
              <Bouton
                libelle="Valider"
                onPress={() => void validerManuellement(invite)}
                chargement={validationEnCours === invite.inscription_id}
                variante="primaire"
                testID={`scan-manuel-valider-${invite.inscription_id}`}
              />
            </View>
          ))
        )}
      </Feuille>
    </View>
  );
}
