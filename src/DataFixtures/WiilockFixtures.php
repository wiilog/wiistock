<?php

namespace App\DataFixtures;

use App\Entity\Wiilock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class WiilockFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $output = new ConsoleOutput();

        $wiilockRepository = $manager->getRepository(Wiilock::class);

        $wiilocks = [
            Wiilock::DASHBOARD_FED_KEY => false
        ];

        $allLocks = $wiilockRepository->findAll();
        foreach ($allLocks as $lock) {
            $key = $lock->getLockKey();
            if (!isset($wiilocks[$key])) {
                $manager->remove($lock);
                $output->writeln('Suppression du lock "' . $key . '"');
            }
        }

        $manager->flush();

        foreach ($wiilocks as $key => $value) {
            $wiilock = $wiilockRepository->findOneBy([
                'lockKey' => $key
            ]);
            if (empty($wiilock)) {
                $output->writeln('Création du lock "' . $key . '"');
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
