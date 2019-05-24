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
            CategorieCL::REFERENCE_CEA,
            CategorieCL::ARTICLE,
            CategorieCL::AUCUNE,
            CategorieCL::RECEPTION
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

//        // patch spécifique pour changement de nom de 'referenceArticle' à 'référence CEA'
//        $categorieCL = $this->categorieCLRepository->findOneBy(['label' => 'referenceArticle']);
//
//        if (empty($categorieCL)) {
//            $categorieCL = new CategorieCL();
//            dump("création de la catégorie " . CategorieCL::REFERENCE_CEA);
//        } else {
//            dump("renommage de la catégorie referenceArticle -> " . CategorieCL::REFERENCE_CEA);
//        }
//
//        $categorieCL->setLabel(CategorieCL::REFERENCE_CEA);

        $manager->flush();
    }

    public static function getGroups():array {
        return ['fixtures'];
    }

}