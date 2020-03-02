<?php


namespace App\DataFixtures;


use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Repository\DaysWorkedRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class SafranEmplacementFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $emplacementRepository = $manager->getRepository(Emplacement::class);
        $ecsArg = $emplacementRepository->findOneByLabel(Emplacement::LABEL_ECS_ARG);
        if (!$ecsArg) {
            $ecsArg = new Emplacement();
            $ecsArg
                ->setIsActive(true)
                ->setLabel(Emplacement::LABEL_ECS_ARG);
            $manager->persist($ecsArg);
        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fixtures'];
    }
}
