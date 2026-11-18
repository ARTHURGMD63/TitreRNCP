/**
 * La barre de recherche des onglets Explorer et Personnes.
 *
 * La recherche part toute seule pendant la frappe, une fois qu'on marque
 * une courte pause (ATTENTE_MS) : plus besoin de valider au clavier. La
 * touche « Rechercher » la lance tout de suite, la croix vide le champ et
 * la liste revient aussitôt au complet. Une petite roue remplace la loupe
 * pendant le chargement, sans vider la liste déjà à l'écran.
 */

import React, { useEffect, useRef } from 'react';
import { ActivityIndicator, Pressable, TextInput, View } from 'react-native';

import { Icone } from './Icone';
import { fs, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';

/** La pause de frappe après laquelle la recherche part. */
export const ATTENTE_MS = 300;

export function BarreRecherche({ valeur, onChange, onChercher, placeholder, accessibilityLabel, chargement = false }: {
  valeur: string;
  onChange: (texte: string) => void;
  /** Reçoit le terme nettoyé (espaces retirés), à chaque pause et à la validation. */
  onChercher: (terme: string) => void;
  placeholder: string;
  accessibilityLabel: string;
  chargement?: boolean;
}) {
  const { c } = useTheme();
  const minuteur = useRef<ReturnType<typeof setTimeout> | null>(null);

  const annuler = () => {
    if (minuteur.current) clearTimeout(minuteur.current);
    minuteur.current = null;
  };

  // Une recherche en attente ne doit pas partir après la fermeture.
  useEffect(() => annuler, []);

  function saisir(texte: string) {
    onChange(texte);
    annuler();
    minuteur.current = setTimeout(() => onChercher(texte.trim()), ATTENTE_MS);
  }

  function valider() {
    annuler();
    onChercher(valeur.trim());
  }

  function vider() {
    annuler();
    onChange('');
    onChercher('');
  }

  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', minHeight: 52, borderRadius: rayon.bouton, backgroundColor: c.blanc, paddingLeft: 16 }}>
      <View style={{ width: 22, alignItems: 'center' }}>
        {chargement ? <ActivityIndicator size="small" color={c.gris} /> : <Icone nom="loupe" taille={18} couleur={c.gris} />}
      </View>
      <TextInput
        autoFocus
        value={valeur}
        onChangeText={saisir}
        onSubmitEditing={valider}
        placeholder={placeholder}
        placeholderTextColor={c.gris}
        returnKeyType="search"
        autoCorrect={false}
        autoCapitalize="none"
        clearButtonMode="never"
        accessibilityLabel={accessibilityLabel}
        style={{ flex: 1, minHeight: 52, paddingVertical: 14, paddingLeft: 10, paddingRight: 8, fontFamily: sans(400), fontSize: fs[5], color: c.noir }}
      />
      {valeur.length > 0 ? (
        <Pressable onPress={vider} accessibilityRole="button" accessibilityLabel="Effacer la recherche" hitSlop={6}
          style={{ width: 44, height: 44, marginRight: 4, alignItems: 'center', justifyContent: 'center' }}>
          <View style={{ width: 24, height: 24, borderRadius: 12, backgroundColor: c.surface2, alignItems: 'center', justifyContent: 'center' }}>
            <Icone nom="croix" taille={13} couleur={c.grisFonce} trait={2.5} />
          </View>
        </Pressable>
      ) : null}
    </View>
  );
}
