/**
 * Inviter un autre établissement (un bar, un club, un BDE) à co-organiser un
 * événement (v39) — la section « Collaboration » du formulaire partenaire.
 *
 * On cherche par nom ou par ville, on choisit, et l'invitation part avec
 * l'événement quand on l'enregistre. Le nom de l'invité ne s'affiche côté
 * étudiant qu'une fois l'invitation acceptée : la pastille de statut le dit.
 */

import React, { useEffect, useState } from 'react';
import { Pressable, View } from 'react-native';

import { api, ErreurApi, type CollaborationEnvoyee, type EtablissementInvitable } from '../api';
import { LIBELLES_TYPE } from '../catalogue';
import { useJeton } from '../session';
import { fs, rayon } from '../theme';
import { useTheme } from '../useTheme';
import { Pastille } from './Design';
import { Champ } from './Formulaire';
import { Icone } from './Icone';
import { T } from './Texte';

/** Le collaborateur choisi : `statut` n'existe que pour une invitation déjà envoyée. */
export type CollaborateurChoisi = Omit<CollaborationEnvoyee, 'statut'> & { statut?: CollaborationEnvoyee['statut'] };

const STATUTS: Record<CollaborationEnvoyee['statut'], string> = {
  en_attente: 'En attente de réponse',
  acceptee: 'Acceptée',
  refusee: 'Refusée',
};

export function ChampCollaboration({ valeur, onChange }: { valeur: CollaborateurChoisi | null; onChange: (v: CollaborateurChoisi | null) => void }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const [q, setQ] = useState('');
  const [resultats, setResultats] = useState<EtablissementInvitable[]>([]);
  // La recherche à laquelle `resultats` répond : « aucun » n'apparaît qu'une fois elle revenue.
  const [repondu, setRepondu] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  // Recherche au fil de la frappe, 300 ms après la dernière touche.
  useEffect(() => {
    if (valeur || q.trim().length < 2) return;
    let annule = false;
    const minuterie = setTimeout(() => {
      api.partenaireRechercherCollaborateurs(jeton, q.trim())
        .then((rep) => { if (!annule) { setResultats(rep.etablissements.slice(0, 6)); setRepondu(q.trim()); setErreur(null); } })
        .catch((e) => { if (!annule) setErreur(e instanceof ErreurApi ? e.message : 'Recherche impossible.'); });
    }, 300);
    return () => { annule = true; clearTimeout(minuterie); };
  }, [q, valeur, jeton]);

  // Moins de deux lettres : on n'affiche rien, quoi qu'ait rendu la recherche d'avant.
  const visibles = !valeur && q.trim().length >= 2 ? resultats : [];

  if (valeur) {
    const teinte = valeur.statut === 'acceptee'
      ? { fond: c.succesClair, encre: c.succes }
      : valeur.statut === 'refusee' ? { fond: c.dangerClair, encre: c.danger } : { fond: c.surface2, encre: c.grisFonce };
    return (
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, borderRadius: rayon.sm, backgroundColor: c.surface2 }}>
        <Icone nom="personnes" taille={18} couleur={c.noir} />
        <View style={{ flex: 1, minWidth: 0, gap: 4 }}>
          <T taille={fs[4]} poids={700} numberOfLines={1}>{valeur.nom}</T>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
            <Pastille libelle={LIBELLES_TYPE[valeur.type] ?? valeur.type} fond={c.blanc} encre={c.grisFonce} />
            {valeur.statut ? <Pastille libelle={STATUTS[valeur.statut]} fond={teinte.fond} encre={teinte.encre} /> : null}
          </View>
        </View>
        <Pressable onPress={() => { onChange(null); setQ(''); }} accessibilityRole="button" accessibilityLabel="Retirer la collaboration" hitSlop={8}>
          <Icone nom="croix" taille={18} couleur={c.gris} />
        </Pressable>
      </View>
    );
  }

  return (
    <View>
      <Champ
        etiquette="Inviter un établissement ou un BDE"
        value={q}
        onChangeText={setQ}
        placeholder="Nom ou ville"
        autoCorrect={false}
        marge={visibles.length || erreur ? 8 : 0}
      />
      {erreur ? <T taille={fs[3]} couleur={c.danger}>{erreur}</T> : null}
      {visibles.map((r, i) => (
        <Pressable
          key={r.id}
          onPress={() => onChange({ id: r.id, nom: r.nom, type: r.type })}
          accessibilityRole="button"
          accessibilityLabel={`Inviter ${r.nom}`}
          style={{ flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 10, borderBottomWidth: i === visibles.length - 1 ? 0 : 1, borderBottomColor: c.grisClair }}
        >
          <View style={{ flex: 1, minWidth: 0 }}>
            <T taille={fs[4]} poids={600} numberOfLines={1}>{r.nom}</T>
            <T taille={fs[3]} couleur={c.gris} numberOfLines={1}>{LIBELLES_TYPE[r.type] ?? r.type}{r.ville ? ` · ${r.ville}` : ''}</T>
          </View>
          <Icone nom="plus" taille={16} couleur={c.noir} />
        </Pressable>
      ))}
      {q.trim().length >= 2 && repondu === q.trim() && !visibles.length && !erreur ? (
        <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 8 }}>Aucun établissement trouvé.</T>
      ) : null}
    </View>
  );
}
