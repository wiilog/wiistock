<?php

namespace App\DataFixtures;

use App\Entity\CategorieCL;
use App\Entity\Type\CategoryType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class CategorieCLFixtures extends Fixture implements FixtureGroupInterface
{

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
            CategorieCL::COLLECT_TRANSPORT => CategoryType::COLLECT_TRANSPORT,
            CategorieCL::DELIVERY_TRANSPORT => CategoryType::DELIVERY_TRANSPORT,
            CategorieCL::DEMANDE_COLLECTE => CategoryType::DEMANDE_COLLECTE,
            CategorieCL::ARRIVAGE => CategoryType::ARRIVAGE,
            CategorieCL::MVT_TRACA => CategoryType::MOUVEMENT_TRACA,
            CategorieCL::SENSOR => CategoryType::SENSOR,
            CategorieCL::PRODUCTION_REQUEST => CategoryType::PRODUCTION,
            CategorieCL::STOCK_EMERGENCY => CategoryType::STOCK_EMERGENCY,
            CategorieCL::TRACKING_EMERGENCY => CategoryType::TRACKING_EMERGENCY,
        ];
        foreach ($categoriesNames as $index => $categorieName) {
            $categorie = $categorieCLRepository->findOneBy(['label' => $index]);
            $categorieType = $categorieTypeRepository->findOneBy(['label' => $categorieName]);

            if (empty($categorie)) {
                $categorie = new CategorieCL();
                $categorie->setLabel($index);
                $manager->persist($categorie);
                $output->writeln("Création de la catégorie " . $index);
            }

            if ($categorie->getCategoryType() === null && $categorieType !== null) {
                $categorie->setCategoryType($categorieType);
                $output->writeln("Liaison categorieCL $index à categorieType $categorieName");
            }
        }
        $manager->flush();
    }

    public static function getGroups():array {
        return ['fixtures'];
    }

}
