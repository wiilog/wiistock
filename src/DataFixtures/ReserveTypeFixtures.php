<?php

namespace App\DataFixtures;

use App\Entity\ReserveType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ReserveTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $reserveTypeRepository = $manager->getRepository(ReserveType::class);

        if (!$reserveTypeRepository->findOneBy(["label" => ReserveType::DEFAULT_QUALITY_TYPE])) {
            $reserveType = new ReserveType();
            $reserveType
                ->setLabel(ReserveType::DEFAULT_QUALITY_TYPE)
                ->setDefault(true);
            $manager->persist($reserveType);
        }

        $manager->flush();
    }
}
