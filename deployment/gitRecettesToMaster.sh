#!/bin/bash

declare -A instances

instances[recette-collins]=master-collins
instances[recette-cea]=master-cea
instances[recette-safran-cs]=master-safran-cs
instances[recette-safran-ed]=master-safran-ed

git checkout test
git pull
git push
echo ">>>>> branche origin/test à jour"

for i in "${!instances[@]}"; do
  echo $i
  echo ${instances[$i]}
  git checkout "$i"
  git pull
  git merge test
  git push
  echo ">>>>> branche origin/$i à jour"
  git checkout "${instances[$i]}"
  git pull
  git merge "$i"
  git push
  echo ">>>>> branche origin/${instances[$i]} à jour"
done