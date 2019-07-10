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
        	CategoryType::ARTICLES_ET_REF_CEA,
			CategoryType::RECEPTION,
			CategoryType::DEMANDE_LIVRAISON,
            CategoryType::DEMANDE_COLLECTE
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
        $manager->flush();
    }

    public static function getGroups():array {
        return ['types', 'fixtures'];
    }

}
