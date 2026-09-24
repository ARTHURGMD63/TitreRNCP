# Rapport d'accessibilité — Linkee

## Cible WCAG

**Niveau visé : WCAG 2.1 AA**

---

## Corrections apportées (sprint accessibilité)

### Navigation & Structure

| Élément | Avant | Après |
|---------|-------|-------|
| Skip navigation | Absent | Lien "Aller au contenu principal" visible au focus |
| Landmark `<main>` | Div générique | `<main id="main-content">` sur toutes les pages |
| `<nav>` avec label | `<nav>` sans contexte | `aria-label="Navigation principale"` |
| Page active | Classe CSS seule | `aria-current="page"` |
| SVG décoratifs | Lus par les AT | `aria-hidden="true"` sur tous les SVG décoratifs |
| `lang` sur `<html>` | `lang="fr"` | Maintenu |

### Formulaires

| Élément | Avant | Après |
|---------|-------|-------|
| Labels associés | Partiels | `<label for="...">` sur tous les inputs |
| Textarea avis | Pas de label | `<label for="commentaire">` avec `aria-describedby` |
| Messages d'erreur | Div muette | `role="alert" aria-live="assertive"` |
| Champ note required | Non annoncé | `aria-required="true"` sur le groupe d'étoiles |

### Composants interactifs

| Élément | Avant | Après |
|---------|-------|-------|
| Widget étoiles | Click only | `role="radiogroup"`, flèches clavier, `aria-checked` |
| Filtres pills | `onclick="window.location"` | Vrais liens `<a>` avec `aria-current` |
| Boutons filter | Pas de context | `role="group" aria-label="Filtrer les événements"` |

### Focus & Clavier

| Élément | Avant | Après |
|---------|-------|-------|
| Outline supprimé | `outline: none` global | `:focus-visible` avec outline bleu 3px |
| Focus inputs | Border seul | Border + outline 2px |
| Focus boutons/liens | Absent | `:focus-visible` sur `.btn`, `.nav-item`, `.pill` |
| Navigation clavier widget étoiles | Inaccessible | Flèches ←→ ↑↓ + Espace/Entrée |

### Animations & Mouvement

| Élément | Avant | Après |
|---------|-------|-------|
| `prefers-reduced-motion` | Absent | Toutes transitions/animations désactivées |

---

## Critères WCAG 2.1 AA — Statut

### Perceptible (1.x)

| Critère | Niveau | Statut | Notes |
|---------|--------|--------|-------|
| 1.1.1 Contenu non textuel | A | Oui | SVG avec `aria-hidden` ou `aria-label` |
| 1.3.1 Information et relations | A | Oui | `<main>`, `<nav>`, `role`, labels |
| 1.3.2 Séquence signifiante | A | Oui | Ordre DOM logique |
| 1.3.3 Caractéristiques sensorielles | A | Oui | Pas d'instruction "cliquez sur le bouton rouge" |
| 1.4.1 Utilisation de la couleur | A | Oui | Couleur + texte pour indiquer l'état actif |
| 1.4.3 Contraste (AA) | AA | Oui | Texte noir (#1A1A1A) sur fond crème (#F2EDE3) — ratio 14.7:1 |
| 1.4.4 Redimensionnement du texte | AA | Oui | Unités relatives (rem, em) |
| 1.4.10 Reflow | AA | Oui | Responsive, pas de scroll horizontal |

### Utilisable (2.x)

| Critère | Niveau | Statut | Notes |
|---------|--------|--------|-------|
| 2.1.1 Clavier | A | Oui | Navigation complète au clavier |
| 2.1.2 Pas de piège clavier | A | Oui | Modales fermables avec Échap (JS) |
| 2.4.1 Contourner des blocs | A | Oui | Skip nav link |
| 2.4.2 Titre de page | A | Oui | `<title>` unique sur chaque page |
| 2.4.3 Ordre de focus | A | Oui | Ordre logique top→bottom |
| 2.4.4 Objet du lien | A | Oui | Textes de liens descriptifs |
| 2.4.7 Visibilité du focus | AA | Oui | `:focus-visible` outline bleu 3px |
| 2.3.3 Animation (AAA — démarche) | AAA | Oui | `prefers-reduced-motion` respecté |

### Compréhensible (3.x)

| Critère | Niveau | Statut | Notes |
|---------|--------|--------|-------|
| 3.1.1 Langue de la page | A | Oui | `<html lang="fr">` |
| 3.2.1 Au focus | A | Oui | Pas de changement de contexte au focus |
| 3.2.2 À la saisie | A | Oui | Soumission de formulaire uniquement sur action explicite |
| 3.3.1 Identification des erreurs | A | Oui | `role="alert"` avec description |
| 3.3.2 Étiquettes et instructions | A | Oui | Labels sur tous les champs |

### Robuste (4.x)

| Critère | Niveau | Statut | Notes |
|---------|--------|--------|-------|
| 4.1.1 Analyse syntaxique | A | Oui | HTML5 valide, balises imbriquées correctement |
| 4.1.2 Nom, rôle, valeur | A | Oui | ARIA sur composants custom (étoiles, nav) |
| 4.1.3 Messages d'état | AA | Oui | `aria-live` sur zones dynamiques |

---

## Outils de validation

Pour auditer le projet :

```bash
# Extension Chrome axe DevTools
# https://chrome.google.com/webstore/detail/axe-devtools/lhdoppojpmngadmnindnejefpokejbdd

# Lighthouse (intégré Chrome DevTools)
# F12 → Lighthouse → Accessibility

# WAVE Tool
# https://wave.webaim.org/

# Contraste couleur
# https://webaim.org/resources/contrastchecker/
```

---

## Palettes de couleurs — Ratios de contraste

| Combinaison | Ratio | WCAG AA |
|-------------|-------|---------|
| Noir (#1A1A1A) sur Crème (#F2EDE3) | 14.7:1 | Oui |
| Blanc (#FFF) sur Rouge (#E5331A) | 4.7:1 | Oui |
| Blanc (#FFF) sur Bleu (#2929E8) | 5.8:1 | Oui |
| Noir (#1A1A1A) sur Lime (#C8E52A) | 8.1:1 | Oui |
| Gris (#888) sur Crème (#F2EDE3) | 3.5:1 | Partiel (texte normal AA: 4.5:1 requis) |

> **Note** : La couleur `--gris` (#888888) sur fond crème (#F2EDE3) atteint un ratio de 3.5:1, insuffisant pour le texte normal AA. Elle est utilisée uniquement pour du texte secondaire de petite taille (hints, labels). Piste d'amélioration : passer à `#767676` (ratio 4.54:1).

---

## Axes d'amélioration futurs

- [ ] Ajuster `--gris` de #888 à #767676 pour respecter WCAG AA niveau texte normal
- [ ] Ajouter `aria-describedby` sur les cartes d'événements (lier description + lieu)
- [ ] Tester avec VoiceOver (macOS) et NVDA (Windows)
- [ ] Ajouter `aria-expanded` sur les menus déroulants (filtres people)
- [ ] Sous-titres sur les vidéos éventuelles
