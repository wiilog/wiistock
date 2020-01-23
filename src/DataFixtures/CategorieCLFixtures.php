<?php

namespace App\DataFixtures;

use App\Repository\CategorieCLRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\CategorieCL;

class CategorieCLFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    public function __construct(CategorieCLRepository $categorieCLRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->categorieCLRepository = $categorieCLRepository;
    }
    
    public function load(ObjectManager $manager)
    {
        $categoriesNames = [
            CategorieCL::REFERENCE_ARTICLE,
            CategorieCL::ARTICLE,
            CategorieCL::AUCUNE,
            CategorieCL::RECEPTION,
			CategorieCL::DEMANDE_LIVRAISON,
			CategorieCL::DEMANDE_COLLECTE,
			CategorieCL::ARRIVAGE
        ];
        foreach ($categoriesNames as $categorieName) {
            $categorie = $this->categorieCLRepository->findOneByLabel($categorieName);

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