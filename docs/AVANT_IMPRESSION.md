# Avant impression — flyers et affiches

Tout ce qu'il faut régler avant d'envoyer un fichier chez l'imprimeur, et
comment distribuer ensuite sans se mettre en faute.

---

## 1. Les fichiers

| Fichier | Format | Pages | Usage |
|---|---|---|---|
| `Flyer_Bars_Linkee.html` / `.pdf` | 111 × 154 mm (A6 + 3 mm de fond perdu) | 2 (recto, verso) | Le flyer à déposer dans les bars. C'est le fichier à envoyer à l'imprimeur. |
| `Flyer_Bars_Planche_A4.html` / `.pdf` | A4 | 2 (4 rectos, 4 versos) | Le même flyer à quatre exemplaires sur une A4, pour imprimer soi-même. Fichier **généré**, ne pas l'éditer. |
| `Affiches_Rue_Linkee.html` / `.pdf` | 303 × 426 mm (A3 + 3 mm de fond perdu) | 3 affiches | Les affiches. Trois accroches différentes, à alterner. |
| `qr_linkee.svg` | — | — | Le QR code, utilisé par tous les supports. |
| `generate_qr.py`, `generate_planche.py` | — | — | Les deux scripts qui régénèrent le QR et la planche A4. |

Le dessin du flyer n'existe qu'à un seul endroit : `Flyer_Bars_Linkee.html`.
La planche A4 en est dérivée par script. Modifier le flyer, relancer
`generate_planche.py`, réexporter les deux PDF — jamais l'inverse.

---

## 2. À compléter avant de lancer un tirage

Trois choses sont encore ouvertes dans les fichiers. Les deux premières
bloquent l'impression.

- [ ] **Le nom de domaine.** `linkee.fr` n'est pas réservé (cf.
      `fondateurs/SL-09_Protection_Marque_PI_Juridique.md`). Il figure en
      toutes lettres sur les trois affiches et sur les deux faces du flyer.
      Le réserver **avant** d'imprimer : un flyer qui renvoie vers une adresse
      qu'on ne possède pas est un flyer perdu, et l'adresse imprimée ne se
      rattrape pas.
- [ ] **Le QR code.** Il encode l'adresse ci-dessus. Dès que l'adresse
      définitive est connue, le régénérer puis réexporter les PDF (§ 3).
      Vérifier le QR imprimé avec deux téléphones différents avant de valider
      le bon à tirer.
- [ ] **Le contact partenaire** au dos du flyer, encadré en pointillés rouges
      (`contact à compléter`). Ce bloc s'adresse au patron du bar qui ramasse
      le flyer sur sa table — c'est une entrée commerciale gratuite, elle vaut
      la peine d'être remplie. Mettre une adresse e-mail dédiée, pas un
      numéro personnel.

Les pointillés rouges sont une convention du projet : ils marquent ce qui
reste à décider. S'il en subsiste un dans un PDF, le fichier n'est pas prêt.

---

## 3. Régénérer les fichiers

Depuis la racine du projet :

```bash
python docs/generate_qr.py https://ladresse-definitive.fr
```

```bash
python docs/generate_planche.py
```

Puis réexporter les trois PDF (Chrome ou Edge en mode sans interface) :

```bash
"C:/Program Files/Google/Chrome/Application/chrome.exe" --headless=new --disable-gpu --no-pdf-header-footer --run-all-compositor-stages-before-draw --virtual-time-budget=9000 --print-to-pdf="C:/wamp64/www/TitreRNCP/docs/Flyer_Bars_Linkee.pdf" "file:///C:/wamp64/www/TitreRNCP/docs/Flyer_Bars_Linkee.html"
```

Les polices (Unbounded, Instrument Sans, JetBrains Mono) sont chargées depuis Google Fonts :
il faut être connecté au moment de l'export, sinon le PDF sort avec les
polices de remplacement.

---

## 4. Ce qu'il faut demander à l'imprimeur

Les fichiers sont déjà au bon format : **format de coupe + 3 mm de fond
perdu sur chaque bord**, sans traits de coupe. C'est ce qu'attendent les
imprimeurs en ligne.

**Flyers**
- Format fini A6 (105 × 148 mm), recto-verso quadri.
- Couché **mat 300 g** plutôt que brillant : le recto est un aplat sombre,
  le brillant y montre toutes les traces de doigts, et un bar est un endroit
  où l'on touche les choses avec les mains grasses.
- Pas de pelliculage nécessaire pour un tirage d'essai.

**Affiches**
- Format fini A3 (297 × 420 mm), recto seul quadri.
- **135 à 170 g** suffit pour de l'intérieur (vitrine, panneau de campus).
- Pour de l'extérieur, demander un papier résistant à l'humidité — sinon la
  première pluie fait gondoler puis décoller.
- Le fichier monte en A2 et en A1 sans retouche : même proportion, typographie
  vectorielle. Demander simplement une mise à l'échelle.

**À vérifier sur le bon à tirer**, dans cet ordre : le QR se scanne ; l'adresse
est la bonne ; aucun encadré en pointillés ne subsiste ; les aplats sombres ne
sont pas « bouchés » (le dégradé du recto doit rester lisible).

Demander deux ou trois devis : les écarts de prix entre imprimeurs en ligne
sont importants sur ces petites quantités, et un imprimeur clermontois peut
être compétitif une fois la livraison comptée.

---

## 5. Déposer les flyers dans les bars

Un flyer laissé sur une table sans un mot au patron finit à la poubelle en
fin de service. Le dépôt se demande, et il se rend utile.

- **Demander au gérant, pas au serveur.** Trente secondes : ce que c'est, qui
  ça amène chez lui, et qu'on ne lui demande rien.
- **Le dos du flyer lui parle aussi.** Le bloc « Vous tenez ce bar ? » est là
  pour ça : le flyer fait à la fois l'acquisition étudiante et la prospection
  partenaire.
- **Viser les moments creux** pour passer : entre 15 h et 17 h, personne n'a
  le temps de discuter à 22 h un vendredi.
- **Repasser au bout de deux semaines.** S'il en reste une pile intacte, le
  problème n'est pas la quantité mais l'emplacement — comptoir plutôt que
  table, à côté de la caisse plutôt que près de la porte.
- Les bars déjà partenaires passent en premier : le flyer y est cohérent avec
  ce que le personnel raconte à l'entrée.

---

## 6. Coller les affiches — le cadre légal

**À lire avant de sortir avec un pot de colle.** L'affichage sauvage est une
infraction, pas une zone grise.

L'affichage extérieur est encadré par les articles **L.581-1 et suivants du
code de l'environnement**. Coller sur du mobilier urbain, un mur privé, un
poteau ou un panneau de chantier sans autorisation expose à une amende
administrative qui peut atteindre plusieurs milliers d'euros, à laquelle
s'ajoute le coût du nettoyage. Certaines communes verbalisent activement.

Les **panneaux d'affichage libre** que les communes doivent mettre à
disposition (art. L.581-13) sont réservés à l'affichage d'opinion et aux
**activités des associations sans but lucratif**. Linkee est un projet
commercial : ces panneaux ne lui sont en principe pas ouverts, sauf si
l'affiche est portée par une association partenaire (un BDE, par exemple) et
annonce son événement.

Les emplacements praticables, par ordre de simplicité :

1. **Les vitrines et les intérieurs de commerces** — avec l'accord du gérant.
   C'est le plus efficace et le plus simple : un A3 dans l'entrée d'un bar
   partenaire est vu par exactement le bon public.
2. **Les panneaux des campus, des BDE, des résidences et du CROUS** — soumis à
   l'accord de l'établissement ou du CROUS, qui ont chacun leurs règles
   d'affichage. Passer par le BDE facilite beaucoup les choses.
3. **Les panneaux associatifs municipaux** — seulement via une association
   partenaire, et en respectant le règlement d'affichage local.
4. **L'affichage payant** (sucettes, panneaux municipaux commerciaux,
   affichage en salles de sport ou en cinémas) — à comparer en devis.

Avant toute campagne en extérieur, consulter le **règlement local de publicité
de Clermont-Ferrand** auprès de la mairie : il fixe les emplacements, les
formats et les périodes autorisés, et il prime sur les généralités ci-dessus.

Une dernière chose, non juridique : les affiches se décollent aussi. Prévoir
qui va les retirer en fin de campagne, y compris celles qui ont été posées
avec autorisation.
