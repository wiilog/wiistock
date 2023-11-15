#!/bin/bash


set -e

if [ -z $1 ]
then
    echo "Vous devez fournir un chemin de destination";
    exit 1;
fi

archive_target=$1

composer install
yarn install

rm -rf ./var/log/*
rm -rf ./var/cache/*

tar -czvf "$archive_target" ../FollowNexter_release/

