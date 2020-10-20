<?php

namespace App\DataFixtures;

use App\Entity\CategoryType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
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
        $output = new ConsoleOutput();

        $categorieCLRepository = $manager->getRepository(CategorieCL::class);
        $categorieTypeRepository = $manager->getRepository(CategoryType::class);

        $categoriesNames = [
            CategorieCL::REFERENCE_ARTICLE => CategoryType::ARTICLE,
            CategorieCL::ARTICLE => CategoryType::ARTICLE,
            CategorieCL::AUCUNE => '',
            CategorieCL::RECEPTION => CategoryType::RECEPTION,
            CategorieCL::DEMANDE_LIVRAISON => CategoryType::DEMANDE_LIVRAISON,
            CategorieCL::DEMANDE_DISPATCH => CategoryType::DEMANDE_DISPATCH,
            CategorieCL::DEMANDE_HANDLING => CategoryType::DEMANDE_HANDLING,
			CategorieCL::DEMANDE_COLLECTE => CategoryType::DEMANDE_COLLECTE,
            CategorieCL::ARRIVAGE => CategoryType::ARRIVAGE,
			CategorieCL::MVT_TRACA => CategoryType::MOUVEMENT_TRACA
        ];
        foreach ($categoriesNames as $index => $categorieName) {
            $categorie = $categorieCLRepository->findOneByLabel($index);
            $categorieType = $categorieTypeRepository->findOneBy(['label' => $categorieName]);

            if (empty($categorie)) {
                $categorie = new CategorieCL();
                $categorie->setLabel($index);
                $manager->persist($categorie);
                $output->writeln("Création de la catégorie " . $index);
            }

            if (!$categorie->getCategoryType()) {
                $categorie->setCategoryType($categorieType);
                $output->writeln('Liaison categorieCL ' . $index . ' à categorieType ' . $categorieName);
            }
        }
        $manager->flush();
    }

    public static function getGroups():array {
        return ['fixtures'];
    }

}
