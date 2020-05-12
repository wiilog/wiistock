<?php

namespace App\DataFixtures;

use App\Entity\Wiilock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;


class WiilockFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $wiilockRepository = $manager->getRepository(Wiilock::class);

        $wiilocks = [
            Wiilock::DASHBOARD_FED_KEY => false
        ];

        foreach ($wiilocks as $key => $value) {
            $wiilock = $wiilockRepository->findOneBy([
                'lockKey' => $key
            ]);
            if (empty($wiilock)) {
                dump('Création du lock : ' . $key);
                $wiilock = new Wiilock();
                $wiilock
                    ->setValue($value)
                    ->setLockKey($key);
                $manager->persist($wiilock);
            }
        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['wiilock', 'fixtures'];
    }
}
