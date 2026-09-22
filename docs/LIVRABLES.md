# Livrables lourds — où ils vivent, comment les refaire

Ce dépôt ne contient plus les fichiers binaires volumineux. Ils sont toujours
sur le disque de travail, mais Git ne les suit plus.

## Pourquoi

Le dépôt portait **44 Mo de binaires** pour 7,8 Mo de code et de
documentation. Trois raisons de les en sortir :

1. **Un binaire ne se « diffe » pas.** Git enregistre une copie complète du
   fichier à chaque modification. Réenregistrer un `.pptx` de 8 Mo après avoir
   corrigé une faute de frappe ajoute 8 Mo à l'historique — définitivement.
   Un dépôt ne rétrécit jamais tout seul.
2. **Chaque clone les télécharge tous.** Y compris les versions
   intermédiaires que plus personne n'ouvrira.
3. **L'application ne s'en sert pas.** Aucune ligne de PHP, de JavaScript ou
   de CSS ne référence un `.mp4`, un `.pptx` ou un PDF de `docs/`. Ce sont
   des livrables de communication, pas des ressources du produit.

## Ce qui a été retiré du suivi

| Fichier | Poids | Nature |
| --- | --- | --- |
| `StudentLink_Teaser.mp4` | 11,0 Mo | média |
| `Presentation_StudentLink.pptx` | 7,8 Mo | diaporama |
| `Presentation_StudentLink_v3.pptx` | 7,8 Mo | diaporama |
| `Presentation_StudentLink_v2.pptx` | 5,4 Mo | diaporama |
| `Diaporama_Soutenance_StudentLink.pptx` | 3,3 Mo | diaporama |
| `docs/*.pdf` (6 fichiers) | 8,1 Mo | **reconstructible** |
| `Script_Oral_StudentLink.pdf` | 0,7 Mo | **reconstructible** |

## Ce qui reste versionné, et pourquoi

- **Les `.docx` sources** (`Dossier_de_projet`, `Guide_Oral`, `Script_Oral`) —
  1,4 Mo au total. Ils ne se régénèrent depuis rien, et le dépôt est leur
  seule sauvegarde. Le coût est négligeable au regard du risque.
- **Tout le HTML de `docs/`** — c'est la source des PDF ci-dessus, et il se
  diffe normalement.
- **`Logo.png`** (18 Ko) — celui-là, l'application s'en sert.

## Refaire les PDF

Les six PDF de `docs/` sont des exports du HTML voisin, qui reste la seule
source du dessin :

| PDF | Source |
| --- | --- |
| `Affiches_Rue_StudentLink.pdf` | `Affiches_Rue_StudentLink.html` |
| `Charte_Graphique_StudentLink.pdf` | `Charte_Graphique_StudentLink.html` |
| `Flyer_Bars_StudentLink.pdf` | `Flyer_Bars_StudentLink.html` |
| `Flyer_Bars_Planche_A4.pdf` | `Flyer_Bars_StudentLink.html`, imposé par `generate_planche.py` |
| `Plaquette_Etudiants_StudentLink.pdf` | `Plaquette_Etudiants_StudentLink.html` |
| `Plaquette_Partenaires_StudentLink.pdf` | `Plaquette_Partenaires_StudentLink.html` |

Ouvrir le HTML dans un navigateur, puis « Imprimer → Enregistrer au format
PDF ». Les feuilles de style portent déjà les règles `@page` (format, fond
perdu, marges).

La charte graphique a une étape de plus : son HTML est lui-même généré depuis
les variables réelles de `assets/css/style.css`, pour qu'elle ne puisse pas
mentir sur les couleurs du produit.

```bash
python docs/generate_charte.py
```

La planche A4 de flyers s'impose depuis le flyer unitaire :

```bash
python docs/generate_planche.py
```

## Où déposer les livrables

Au choix, mais **pas dans le dépôt Git** :

- une **release GitHub** — la plus proche du code, elle attache des binaires à
  une version taguée sans les mettre dans l'historique ;
- un **espace partagé** (Drive, Nextcloud) — préférable pour les diaporamas,
  qui s'éditent à plusieurs et changent souvent ;
- **Git LFS**, si les binaires doivent absolument suivre les branches. À
  n'envisager que si le besoin est réel : LFS ajoute un serveur, un quota et
  une étape d'installation à chaque clone.

## Le garde-fou

`tests/Unit/DepotTest.php` échoue si un fichier suivi dépasse 2 Mo ou si un
format binaire lourd réapparaît dans l'index. Il tourne en intégration
continue : le dépôt ne peut plus regrossir sans que personne ne le remarque.

## Ce que cela ne fait pas

Les 44 Mo déjà enregistrés **restent dans l'historique**. Retirer un fichier
du suivi empêche la croissance future, mais ne réécrit pas le passé : un
`git clone` télécharge toujours l'historique complet.

Les effacer vraiment demande de réécrire l'historique
(`git filter-repo`, BFG) puis de forcer la publication — ce qui change tous
les identifiants de commit et oblige chaque copie existante du dépôt à être
reclonée. C'est une opération à décider, pas à subir : elle n'a pas été faite
ici.
