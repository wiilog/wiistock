#!/bin/bash

recettes=(recette-cea recette-safran-cs recette-safran-ed recette-collins)

git checkout test
git pull
git push

for i in "${recettes[@]}"; do
  git checkout "$i"
  git pull
  git merge test
  git push
  echo ">>>>> branche origin/$i à jour"
done