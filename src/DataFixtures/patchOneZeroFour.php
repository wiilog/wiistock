<?php

namespace App\DataFixtures;

use App\Entity\CategorieCL;
use App\Repository\CategorieCLRepository;
use App\Repository\CategoryTypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\CategoryType;

class patchOneZeroFour extends Fixture implements FixtureGroupInterface
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
        $categoryType = $this->categoryTypeRepository->findOneBy(['label' => 'typeArticle']);

        if (empty($categoryType)) {
            $categoryType = new CategoryType();
            dump("création de la catégorie " . CategoryType::TYPE_ARTICLES_ET_REF_CEA);
        } else {
            dump("renommage de la catégorie typeArticle -> " . CategoryType::TYPE_ARTICLES_ET_REF_CEA);
        }

        $categoryType->setLabel(CategoryType::TYPE_ARTICLES_ET_REF_CEA);


        $categorieCL = $this->categorieCLRepository->findOneBy(['label' => 'referenceArticle']);

        if (empty($categorieCL)) {
            $categorieCL = new CategorieCL();
            dump("création de la catégorie " . CategorieCL::REFERENCE_CEA);
        } else {
            dump("renommage de la catégorie referenceArticle -> " . CategorieCL::REFERENCE_CEA);
        }

        $categorieCL->setLabel(CategorieCL::REFERENCE_CEA);

        $manager->flush();
    }

    public static function getGroups():array {
        return ['1.0.4'];
    }

}
