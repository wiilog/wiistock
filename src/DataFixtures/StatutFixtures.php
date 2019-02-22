<?php

namespace App\DataFixtures;

use App\Entity\Statut;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class StatutFixtures extends Fixture implements DependentFixtureInterface
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        // catégorie article
        $statutsNames = [
            'en cours de réception',
            'demande de mise en stock',
            'en stock',
            'déstockage',
            'anomalie',
            'demande de sortie',
            'collecté',
            'en livraison',
            'récupéré'
        ];

        foreach ($statutsNames as $statutName) {
            $statut = new Statut();
            $statut
                ->setNom($statutName)
                ->setCategorie($this->getReference('statut-article'));
            $manager->persist($statut);
        }


        // catégorie collecte
        $statutsNames = [
            'fin',
            'en cours de collecte',
            'demande de collecte'
        ];

        foreach ($statutsNames as $statutName) {
            $statut = new Statut();
            $statut
                ->setNom($statutName)
                ->setCategorie($this->getReference('statut-collecte'));
            $manager->persist($statut);
        }


        // catégorie demande
        $statutsNames = [
            'en cours',
            'terminée',
            'à traiter'
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
            'en cours de livraison',
            'demande de livraison',
            'livraison terminée'
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
            'en cours de préparation',
            'nouvelle préparation',
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
            'en cours de réception',
            'terminée',
        ];

        foreach ($statutsNames as $statutName) {
            $statut = new Statut();
            $statut
                ->setNom($statutName)
                ->setCategorie($this->getReference('statut-reception'));
            $manager->persist($statut);
        }

        $manager->flush();

    }

    public function getDependencies()
    {
        return [CategorieStatutFixtures::class];
    }


}