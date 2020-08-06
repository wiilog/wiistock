<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\CategorieCL;

class CategorieCLFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $categorieCLRepository = $manager->getRepository(CategorieCL::class);
        $categoriesNames = [
            CategorieCL::REFERENCE_ARTICLE,
            CategorieCL::ARTICLE,
            CategorieCL::AUCUNE,
            CategorieCL::RECEPTION,
			CategorieCL::DEMANDE_LIVRAISON,
			CategorieCL::DEMANDE_COLLECTE,
            CategorieCL::ARRIVAGE,
			CategorieCL::MVT_TRACA
        ];
        foreach ($categoriesNames as $categorieName) {
            $categorie = $categorieCLRepository->findOneByLabel($categorieName);

            if (empty($categorie)) {
                $categorie = new CategorieCL();
                $categorie->setLabel($categorieName);
                $manager->persist($categorie);
                dump("création de la catégorie " . $categorieName);
            }
        }
        $manager->flush();
    }

    public static function getGroups():array {
        return ['fixtures'];
    }

}
