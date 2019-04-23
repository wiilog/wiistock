<?php

namespace App\DataFixtures;

use App\Entity\Statut;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class StatutFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
//        categorie referenceArticle
          $statutsNames = [
             'actif',
             'inactif'
         ];

         foreach ($statutsNames as $statutName) {
             $statut = new Statut();
             $statut
                 ->setNom($statutName)
                 ->setCategorie($this->getReference('statut-referenceArticle'));
             $manager->persist($statut);
         }

         // catégorie article
         $statutsNames = [
             'actif',
             'inactif'
         ];

         foreach ($statutsNames as $statutName) {
             $statut = new Statut();
             $statut
                 ->setNom($statutName)
                 ->setCategorie($this->getReference('statut-article'));
             $manager->persist($statut);
         }


         // catégorie demande de collecte
         $statutsNames = [
             'brouillon',
             'à traiter',
             'collecté',
         ];

         foreach ($statutsNames as $statutName) {
             $statut = new Statut();
             $statut
                 ->setNom($statutName)
                 ->setCategorie($this->getReference('statut-collecte'));
             $manager->persist($statut);
         }


         // catégorie demande de livraison
         $statutsNames = [
             'brouillon',
             'à traiter',
             'préparé',
             'livré',
         ];

         foreach ($statutsNames as $statutName) {
             $statut = new Statut();
             $statut
                 ->setNom($statutName)
                 ->setCategorie($this->getReference('statut-demande'));
             $manager->persist($statut);
         }


         // catégorie livraison
         $statutsNames = [
             'à traiter',
             'livré'
         ];

         foreach ($statutsNames as $statutName) {
             $statut = new Statut();
             $statut
                 ->setNom($statutName)
                 ->setCategorie($this->getReference('statut-livraison'));
             $manager->persist($statut);
         }


         // catégorie préparation
         $statutsNames = [
             'à traiter',
             'préparé',
         ];

         foreach ($statutsNames as $statutName) {
             $statut = new Statut();
             $statut
                 ->setNom($statutName)
                 ->setCategorie($this->getReference('statut-preparation'));
             $manager->persist($statut);
         }

         $manager->flush();


         // catégorie réception
         $statutsNames = [
             'en attente de réception',
             'réception partielle',
             'réception totale',
             'anomalie'
         ];

         foreach ($statutsNames as $statutName) {
             $statut = new Statut();
             $statut
                 ->setNom($statutName)
                 ->setCategorie($this->getReference('statut-reception'));
             $manager->persist($statut);
         }

         $manager->flush();

        // catégorie service
        $statutsNames = [
            'à traiter',
            'traité',
            'brouillon'
        ];

        foreach ($statutsNames as $statutName) {
            $statut = new Statut();
            $statut
                ->setNom($statutName)
                ->setCategorie($this->getReference('statut-manutention'));
            $manager->persist($statut);
        }

        $manager->flush();


    }

    public function getDependencies()
    {
        return [CategorieStatutFixtures::class];
    }

    public static function getGroups():array {
        return ['status'];
    }

}