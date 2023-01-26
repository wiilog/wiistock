<?php


namespace App\DataFixtures;


use App\Entity\Zone;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ZoneFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $zoneRepository = $manager->getRepository(Zone::class);

        if($zoneRepository->count([]) === 0) {
            $zone = new Zone();
            $zone->setName(Zone::ACTIVITY_STANDARD_ZONE_NAME);
            $zone->setDescription(Zone::ACTIVITY_STANDARD_ZONE_NAME);
            $manager->persist($zone);
            $manager->flush();
        }
    }

    public static function getGroups(): array
    {
        return ['fixtures'];
    }
}
