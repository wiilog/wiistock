<?php

namespace App\DataFixtures;

use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class VisibleColumnFixtures extends Fixture implements FixtureGroupInterface {

    //TODO : modifier le nom et modifier pour que ca passe
    public function load(ObjectManager $manager) {
        $users = $manager->getRepository(Utilisateur::class)->iterateAll();
        /** @var Utilisateur $user */
        foreach ($users as $user) {
            $visibleColumns = $user->getVisibleColumns() ?? Utilisateur::DEFAULT_FIELDS_MODES;
            $visibleColumnsIndexes = array_keys($visibleColumns);
            $missingKeys = array_diff(array_keys(Utilisateur::DEFAULT_FIELDS_MODES), $visibleColumnsIndexes);

            $missingVisibleColumns = [];
            foreach ($missingKeys as $key) {
                $missingVisibleColumns[$key] = Utilisateur::DEFAULT_FIELDS_MODES[$key];
            }

            $user->setVisibleColumns(array_merge($visibleColumns, $missingVisibleColumns));

            $manager->flush();
            $manager->clear();
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['fixtures'];
    }

}
