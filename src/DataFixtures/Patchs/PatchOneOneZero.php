<?php

namespace App\DataFixtures\Patchs;

use App\Entity\CategorieCL;
use App\Repository\CategorieCLRepository;
use App\Repository\CategoryTypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\CategoryType;

class PatchOneOneZero extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var CategoryTypeRepository
     */
    private $categoryTypeRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;


    public function __construct(CategorieCLRepository $categorieCLRepository, CategoryTypeRepository $categoryTypeRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->categoryTypeRepository = $categoryTypeRepository;
        $this->categorieCLRepository = $categorieCLRepository;
    }

    public function load(ObjectManager $manager)
    {
        // patch spécifique pour changement de nom de 'typeArticle' à 'artices et références CEA'
        $categoryType = $this->categoryTypeRepository->findOneBy(['label' => 'typeArticle']);

        if (!empty($categoryType)) {
            $categoryType->setLabel(CategoryType::ARTICLE);
            dump("renommage de la catégorie typeArticle -> " . CategoryType::ARTICLE);
        }


        // patch spécifique pour changement de nom de 'referenceArticle' à 'référence CEA'
        $categorieCL = $this->categorieCLRepository->findOneBy(['label' => 'referenceArticle']);

        if (!empty($categorieCL)) {
            $categorieCL->setLabel(CategorieCL::REFERENCE_ARTICLE);
            dump("renommage de la catégorie referenceArticle -> " . CategorieCL::REFERENCE_ARTICLE);
        }


        $manager->flush();
    }

    public static function getGroups():array {
        return ['1.1.0'];
    }

}
