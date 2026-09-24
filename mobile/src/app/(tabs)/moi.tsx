/**
 * Moi — profil.php.
 *
 * L'identité (avatar lave, prénom, nom à l'encre, école · promo), les
 * centres d'intérêt en pilules, les compteurs d'abonnés, les trois tuiles
 * (sorties en lave, squads en dôme, économies en volt), puis les cartes :
 * « Mon Profil » (photo, école, promo, intérêts), XP et badges, Apparence,
 * liens légaux, compte et déconnexion.
 */

import React, { useCallback, useRef, useState } from 'react';
import { Pressable, ScrollView, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import * as WebBrowser from 'expo-web-browser';

import { adresseServeur, api, ErreurApi, type Badge, type ReponseMoi } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, Encart, Jauge } from '../../composants/Elements';
import { Case, Etiquette, SelecteurInterets, Selecteur } from '../../composants/Formulaire';
import { estIcone, Icone, type NomIcone } from '../../composants/Icone';
import { Display, Mono, T, TitreEcran } from '../../composants/Texte';
import { nombre } from '../../format';
import { useJeton, useSession } from '../../session';
import { couleurCss, fixe, fs, lh, mono, police, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

function CarteProfil({ children, style }: { children: React.ReactNode; style?: object }) {
  const { c, ombre } = useTheme();
  return (
    <View style={[{ backgroundColor: c.blanc, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, padding: 22, marginBottom: 20 }, ombre('base'), style]}>
      {children}
    </View>
  );
}

function TitreSection({ icone, children }: { icone: NomIcone; children: string }) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 16 }}>
      <View style={{ marginRight: 8 }}><Icone nom={icone} taille={20} couleur={c.noir} /></View>
      <Display taille={fs[6]} accessibilityRole="header">{children}</Display>
    </View>
  );
}

function PastilleBadge({ b, verrouille, largeur }: { b: Badge; verrouille?: boolean; largeur: number }) {
  const { c } = useTheme();
  return (
    <View style={{ width: largeur, alignItems: 'center', gap: 8 }} accessible accessibilityLabel={b.nom + (verrouille ? ', à débloquer' : '')}>
      {verrouille ? (
        <View style={{ width: 52, height: 52, borderRadius: 26, borderWidth: 2, borderStyle: 'dashed', borderColor: c.line2, alignItems: 'center', justifyContent: 'center' }}>
          <Text style={{ fontFamily: police.display, fontSize: fs[5], color: c.gris }}>?</Text>
        </View>
      ) : (
        <View style={{ width: 52, height: 52, borderRadius: 26, backgroundColor: couleurCss(b.couleur, c, c.lime), alignItems: 'center', justifyContent: 'center' }}>
          {estIcone(b.icon) ? <Icone nom={b.icon} taille={22} couleur={fixe.surLave} /> : null}
        </View>
      )}
      <T taille={fs[2]} interligne={lh.snug} couleur={verrouille ? c.gris : c.grisFonce} style={{ textAlign: 'center' }}>{b.nom}</T>
    </View>
  );
}

export default function Moi() {
  const { c, choisir } = useTheme();
  const jeton = useJeton();
  const { deconnexion, mettreAJour } = useSession();
  const defilement = useRef<ScrollView>(null);
  const [yFormulaire, setYFormulaire] = useState(0);
  const [largeurGrille, setLargeurGrille] = useState(0);

  const [donnees, setDonnees] = useState<ReponseMoi | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  // Formulaire
  const [ecole, setEcole] = useState('UCA');
  const [promo, setPromo] = useState('L1');
  const [interets, setInterets] = useState<string[]>([]);
  const [photo, setPhoto] = useState<ImagePicker.ImagePickerAsset | null>(null);
  const [retirer, setRetirer] = useState(false);
  const [enregistrement, setEnregistrement] = useState(false);
  const [succes, setSucces] = useState<string | null>(null);
  const [erreurPhoto, setErreurPhoto] = useState<string | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const r = await api.pageMoi(jeton);
      setDonnees(r);
      // <select> sans valeur correspondante : le navigateur montre la première option.
      setEcole(r.moi.ecole && r.choix.ecoles.includes(r.moi.ecole) ? r.moi.ecole : r.choix.ecoles[0]);
      setPromo(r.moi.promo && r.choix.promos.includes(r.moi.promo) ? r.moi.promo : r.choix.promos[0]);
      setInterets(r.moi.interets);
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function choisirPhoto() {
    const choix = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: true, aspect: [1, 1], quality: 0.9 });
    if (!choix.canceled && choix.assets[0]) {
      setPhoto(choix.assets[0]);
      // Choisir une photo annule l'intention de la retirer.
      setRetirer(false);
    }
  }

  async function enregistrer() {
    setEnregistrement(true);
    setSucces(null);
    setErreurPhoto(null);
    try {
      const f = new FormData();
      f.append('ecole', ecole);
      f.append('promo', promo);
      interets.forEach((i) => f.append('interets[]', i));
      if (retirer) f.append('supprimer_photo', '1');
      if (photo && !retirer) {
        const nom = photo.fileName ?? 'photo.jpg';
        f.append('photo', { uri: photo.uri, name: nom, type: photo.mimeType ?? 'image/jpeg' } as unknown as Blob);
      }
      const rep = await api.enregistrerProfil(jeton, f);
      mettreAJour(rep.utilisateur);
      setSucces(rep.message);
      setErreurPhoto(rep.erreur_photo);
      setPhoto(null);
      setRetirer(false);
      await charger();
      defilement.current?.scrollTo({ y: 0, animated: true });
    } catch (e) {
      setErreurPhoto(e instanceof ErreurApi ? e.message : 'Erreur réseau');
    } finally {
      setEnregistrement(false);
    }
  }

  const ouvrirPage = (chemin: string) => WebBrowser.openBrowserAsync(`${adresseServeur()}${chemin}`);
  const allerAuFormulaire = () => defilement.current?.scrollTo({ y: yFormulaire, animated: true });

  if (!donnees) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const { moi, xp, choix } = donnees;
  const apercu = retirer ? null : photo?.uri ?? moi.photo_url;

  return (
    <Ecran defilementRef={defilement} rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete style={{ marginBottom: 24 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 16, marginBottom: 16 }}>
          <Avatar photo={moi.photo_url} prenom={moi.prenom} taille={72} fond={c.rouge} />
          <View style={{ flex: 1 }}>
            <TitreEcran lignes={[moi.prenom, moi.nom]} accent={c.noir} />
            <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 6 }}>{moi.ecole ?? '—'} · {moi.promo ?? '—'}</T>
          </View>
        </View>

        <View style={{ marginBottom: 14 }}>
          {moi.interets.length ? (
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, alignItems: 'center' }}>
              {moi.interets.map((i) => (
                <Pressable key={i} onPress={() => router.navigate({ pathname: '/', params: { vue: 'people', interest: i } })} accessibilityRole="link"
                  accessibilityLabel={`Voir les étudiants qui aiment ${i}`}
                  style={{ paddingVertical: 7, paddingHorizontal: 14, backgroundColor: c.noir, borderRadius: rayon.pill }}>
                  <T taille={fs[3]} poids={600} couleur={c.bg}>#{i}</T>
                </Pressable>
              ))}
              <Text onPress={allerAuFormulaire} style={{ fontFamily: sans(700), fontSize: fs[3], color: c.surRougeClair, marginLeft: 4 }}>Modifier</Text>
            </View>
          ) : (
            <Text onPress={allerAuFormulaire} style={{ fontFamily: sans(700), fontSize: fs[2], color: c.surRougeClair }}>+ Ajoute tes centres d&apos;intérêt</Text>
          )}
        </View>

        <View style={{ flexDirection: 'row', gap: 28, paddingVertical: 14, borderTopWidth: 1, borderTopColor: c.grisClair, marginBottom: 10 }}>
          {[
            { n: moi.nb_abonnes, l: 'Abonnés', type: 'abonnes' },
            { n: moi.nb_abonnements, l: 'Abonnements', type: 'abonnements' },
          ].map((x) => (
            <Pressable key={x.type} onPress={() => router.navigate({ pathname: '/abonnements', params: { type: x.type } })} accessibilityRole="link" style={{ alignItems: 'center' }}>
              <Display taille={fs[7]}>{x.n}</Display>
              <Mono>{x.l}</Mono>
            </Pressable>
          ))}
        </View>
      </EnTete>

      <Contenu style={{ paddingTop: 0 }}>
        <View style={{ flexDirection: 'row', gap: 10, marginTop: -20, marginBottom: 24 }}>
          {[
            { l: 'Sorties', v: String(moi.nb_sorties), couleur: c.surRougeClair },
            { l: 'Squads', v: String(moi.nb_squads), couleur: c.surBleuClair },
            { l: 'Économies', v: nombre(Math.round(moi.economies), 0, '') + '€', couleur: c.surLimeClair },
          ].map((t) => (
            <View key={t.l} style={{ flex: 1, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, paddingVertical: 14, paddingHorizontal: 12 }}>
              <Mono couleur={t.couleur} style={{ marginBottom: 6 }}>{t.l}</Mono>
              <Display taille={fs[7]}>{t.v}</Display>
            </View>
          ))}
        </View>

        {succes ? <Encart genre="ok">{succes}</Encart> : null}

        {/* Mon Profil */}
        <View onLayout={(e) => setYFormulaire(e.nativeEvent.layout.y)}>
          <CarteProfil>
            <TitreSection icone="personne">Mon Profil</TitreSection>
            {erreurPhoto ? <Encart genre="erreur">{erreurPhoto}</Encart> : null}

            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 16, marginBottom: 22 }}>
              <Avatar photo={apercu} prenom={moi.prenom} taille={72} fond={c.rouge} />
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono style={{ marginBottom: 9 }}>Photo de profil</Mono>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                  <Bouton variante="contour" icone="envoi" libelle={moi.photo_url ? 'Changer la photo' : 'Choisir une photo'} onPress={choisirPhoto} />
                  <T numberOfLines={1} taille={fs[2]} couleur={photo ? c.grisFonce : c.gris} style={{ maxWidth: 190 }}>
                    {photo ? photo.fileName ?? 'photo.jpg' : 'Aucune image choisie'}
                  </T>
                </View>
                <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 8 }}>JPG, PNG ou WebP — 2 Mo maximum.</T>
                {moi.photo_url ? (
                  <Case coche={retirer} onChange={(v) => { setRetirer(v); if (v) setPhoto(null); }} style={{ marginTop: 4 }}>Retirer ma photo</Case>
                ) : null}
              </View>
            </View>

            <View style={{ flexDirection: 'row', gap: 12, marginBottom: 16 }}>
              <Selecteur style={{ flex: 1 }} marge={0} etiquette="École" valeur={ecole} onChange={setEcole} options={choix.ecoles.map((e) => ({ valeur: e, libelle: e }))} />
              <Selecteur style={{ flex: 1 }} marge={0} etiquette="Promo" valeur={promo} onChange={setPromo} options={choix.promos.map((p) => ({ valeur: p, libelle: p }))} />
            </View>

            <View style={{ marginBottom: 20 }}>
              <Etiquette style={{ marginBottom: 12 }}>Centres d&apos;intérêt</Etiquette>
              <SelecteurInterets catalogue={choix.interets} choisis={interets} onChange={setInterets} />
            </View>

            <Bouton libelle="Enregistrer les modifications" plein chargement={enregistrement} onPress={enregistrer} />
          </CarteProfil>
        </View>

        {/* XP et badges */}
        <CarteProfil>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 16 }}>
            <View style={{ width: 56, height: 56, borderRadius: 28, backgroundColor: c.lime, alignItems: 'center', justifyContent: 'center' }}>
              <Display taille={fs[7]} couleur={fixe.surLave}>{xp.niveau}</Display>
            </View>
            <View style={{ flex: 1, minWidth: 0 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline', gap: 8, marginBottom: 8 }}>
                <Mono couleur={c.grisFonce}>Niveau {xp.niveau}</Mono>
                <Text style={{ fontFamily: mono(400), fontSize: fs[2], color: c.surLimeClair }}>{nombre(xp.total)} / {nombre(xp.suivant)} XP</Text>
              </View>
              <Jauge pourcentage={xp.progres} piste={c.grisClair} remplissage={c.lime} hauteur={8} />
            </View>
          </View>

          <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 16, marginBottom: 18 }}>
            {xp.stats.events} sorties · {xp.stats.squads} squads · {xp.stats.follows} abonnements · {xp.stats.avis} avis
          </T>

          {/* grid-template-columns: repeat(auto-fill, minmax(76px, 1fr)) ; gap: 16px 10px */}
          <View onLayout={(e) => setLargeurGrille(e.nativeEvent.layout.width)} style={{ flexDirection: 'row', flexWrap: 'wrap', rowGap: 16, columnGap: 10 }}>
            {largeurGrille > 0 ? (() => {
              const colonnes = Math.max(1, Math.floor((largeurGrille + 10) / (76 + 10)));
              // Arrondi vers le bas : un demi-pixel de trop renvoie la dernière colonne à la ligne.
              const largeur = Math.floor((largeurGrille - (colonnes - 1) * 10) / colonnes);
              return [
                ...xp.obtenus.map((b) => <PastilleBadge key={b.code} b={b} largeur={largeur} />),
                ...xp.a_debloquer.map((b) => <PastilleBadge key={b.code} b={b} verrouille largeur={largeur} />),
              ];
            })() : null}
          </View>

          <Bouton libelle="Voir le classement" icone="trophee" variante="contour" plein onPress={() => router.navigate('/classement')} style={{ marginTop: 18 }} />
        </CarteProfil>

        {/* Apparence */}
        <CarteProfil>
          <TitreSection icone="soleil">Apparence</TitreSection>
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <Pressable onPress={() => choisir('clair')} accessibilityRole="button"
              style={{ flex: 1, padding: 14, borderRadius: rayon.pill, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: '#F5F1E8', borderWidth: 1, borderColor: '#DCD5C7' }}>
              <Icone nom="soleil-petit" taille={16} couleur="#111013" />
              <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: '#111013' }}>Clair</Text>
            </Pressable>
            <Pressable onPress={() => choisir('sombre')} accessibilityRole="button"
              style={{ flex: 1, padding: 14, borderRadius: rayon.pill, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: '#111013', borderWidth: 1, borderColor: '#36323B' }}>
              <Icone nom="lune" taille={16} couleur="#F5F1E8" />
              <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: '#F5F1E8' }}>Sombre</Text>
            </Pressable>
          </View>
        </CarteProfil>

        {/* Légal */}
        <CarteProfil style={{ padding: 0, overflow: 'hidden' }}>
          {[
            ['Mentions légales', '/mentions-legales.php'],
            ['CGU', '/cgu.php'],
            ['Politique de confidentialité', '/confidentialite.php'],
          ].map(([l, chemin], i) => (
            <Pressable key={chemin} onPress={() => ouvrirPage(chemin)} accessibilityRole="link"
              style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 14, paddingHorizontal: 20, borderBottomWidth: i < 2 ? 1 : 0, borderBottomColor: c.grisClair }}>
              <T taille={fs[4]} poids={600}>{l}</T>
              <T taille={fs[4]} poids={600} couleur={c.gris}>→</T>
            </Pressable>
          ))}
        </CarteProfil>

        {/* Compte */}
        <CarteProfil style={{ padding: 0, overflow: 'hidden' }}>
          <View style={{ paddingVertical: 16, paddingHorizontal: 20, borderBottomWidth: 1, borderBottomColor: c.grisClair, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Mono>Email</Mono>
            <T taille={fs[2]} poids={600}>{moi.email}</T>
          </View>
          <View style={{ paddingVertical: 16, paddingHorizontal: 20, borderBottomWidth: 1, borderBottomColor: c.grisClair, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Mono>Depuis</Mono>
            <T taille={fs[2]} poids={600}>{moi.depuis}</T>
          </View>
          <Pressable onPress={deconnexion} accessibilityRole="button" style={{ paddingVertical: 16, paddingHorizontal: 20, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
            <Icone nom="deconnexion" taille={16} couleur={c.surRougeClair} />
            <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: c.surRougeClair }}>Se déconnecter</Text>
          </Pressable>
        </CarteProfil>
      </Contenu>
    </Ecran>
  );
}
