<?php

namespace App\DataFixtures;

use App\Entity\IOT\SensorProfile;
use App\Service\IOT\IOTService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class IotFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager)
    {
        $output = new ConsoleOutput();
        // SensorProfile
        $sensorProfileRepository = $manager->getRepository(SensorProfile::class);
        foreach (IOTService::PROFILE_TO_TYPE as $profileName => $type) {
            $profile = $sensorProfileRepository->findOneBy(['name' => $profileName]);
            if (!$profile) {
                $profile = new SensorProfile();
                $profile->setName($profileName);
                $profile->setMaxTriggers(IOTService::PROFILE_TO_MAX_TRIGGERS[$profileName] ?? 1);
                $manager->persist($profile);
                $output->writeln("Création du profil de capteur $profileName");
            }
        }
        $manager->flush();

    }

    public static function getGroups(): array
    {
        return ['types', 'fixtures'];
    }
}
