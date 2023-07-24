<?php

namespace App\DataFixtures;

use App\Entity\ReserveType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ReserveTypeFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $reserveTypeRepository = $manager->getRepository(ReserveType::class);

        if (!$reserveTypeRepository->findOneBy(["label" => ReserveType::DEFAULT_QUALITY_TYPE])) {
            $reserveType = new ReserveType();
            $reserveType
                ->setLabel(ReserveType::DEFAULT_QUALITY_TYPE)
                ->setDefaultReserveType(true);
            $manager->persist($reserveType);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fixtures'];
    }
}
