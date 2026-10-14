#!/bin/bash
# Vérifie que les commits d'une PR suivent les Conventional Commits.
#
#   verifier_commits.sh <base> <tete>
#
# Ne regarde que les commits de la PR (base..tete) : l'historique déjà publié
# n'est pas jugé, seul ce qui arrive. Les commits de fusion sont ignorés.
#
# Règles — les mêmes que CONTRIBUTING.md :
#   - un type connu, un scope facultatif, « ! » pour un changement cassant ;
#   - une description non vide, sans point final ;
#   - un titre de 72 caractères au plus (au-delà, GitHub le tronque) ;
#   - pas de « fixup! » ni « squash! » : à fusionner avant la PR.

set -euo pipefail

base="${1:?base manquante}"
tete="${2:?tete manquante}"

types='feat|fix|docs|style|refactor|perf|test|build|ci|chore|security'
motif="^(${types})(\\([a-z0-9][a-z0-9/_.-]*\\))?!?: .+"

erreurs=0
total=0

while IFS=$'\t' read -r sha titre; do
    [ -z "$sha" ] && continue
    total=$((total + 1))
    court="${sha:0:7}"

    if [[ "$titre" == fixup!* || "$titre" == squash!* ]]; then
        echo "::error::$court « $titre » : fixup!/squash! à fusionner avant la PR (git rebase -i --autosquash)."
        erreurs=$((erreurs + 1))
    elif ! [[ "$titre" =~ $motif ]]; then
        echo "::error::$court « $titre » : ce n'est pas un Conventional Commit (type(scope): description). Types : ${types//|/, }."
        erreurs=$((erreurs + 1))
    elif [ "${#titre}" -gt 72 ]; then
        echo "::error::$court « $titre » : ${#titre} caractères, 72 au plus."
        erreurs=$((erreurs + 1))
    elif [[ "$titre" == *. ]]; then
        echo "::error::$court « $titre » : pas de point final dans le titre."
        erreurs=$((erreurs + 1))
    fi
done < <(git log --no-merges --format='%H%x09%s' "$base..$tete")

if [ "$total" -eq 0 ]; then
    echo "Aucun commit à vérifier entre $base et $tete."
    exit 0
fi

if [ "$erreurs" -gt 0 ]; then
    echo
    echo "$erreurs commit(s) sur $total à corriger. Voir CONTRIBUTING.md, section « Conventional Commits »."
    exit 1
fi

echo "$total commit(s) conformes."
