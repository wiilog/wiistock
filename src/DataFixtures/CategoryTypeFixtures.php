<?php

namespace App\DataFixtures;

use App\Repository\CategoryTypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\CategoryType;

class CategoryTypeFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var CategoryTypeRepository
     */
    private $categoryTypeRepository;


    public function __construct(CategoryTypeRepository $categoryTypeRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->categoryTypeRepository = $categoryTypeRepository;
    }
    
    public function load(ObjectManager $manager)
    {
        $categoriesNames = [
           CategoryType::TYPE_ARTICLES_ET_REF_CEA,
           CategoryType::TYPE_RECEPTION
        ];

        foreach ($categoriesNames as $categorieName) {
            $categorie = $this->categoryTypeRepository->findOneBy(['label' => $categorieName]);

            if (empty($categorie)) {
                $categorie = new CategoryType();
                $categorie->setLabel($categorieName);
                $manager->persist($categorie);
                $this->addReference('type-' . $categorieName, $categorie);
                dump("création de la catégorie " . $categorieName);
            }
        }


        // patch spécifique pour changement de nom de 'typeArticle' à 'artices et références CEA'
        $categoryType = $this->categoryTypeRepository->findOneBy(['label' => 'typeArticle']);

        if (empty($categoryType)) {
            $categoryType = new CategoryType();
            dump("création de la catégorie " . CategoryType::TYPE_ARTICLES_ET_REF_CEA);
        } else {
            dump("renommage de la catégorie typeArticle -> " . CategoryType::TYPE_ARTICLES_ET_REF_CEA);
        }

        $categoryType->setLabel(CategoryType::TYPE_ARTICLES_ET_REF_CEA);
        $this->addReference('typeArticle', $categoryType);

        $manager->flush();
    }

    public static function getGroups():array {
        return ['types', 'fixtures', '1.0.4'];
    }

}
