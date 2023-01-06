<?php

namespace App\DataFixtures;

use App\Entity\Pack;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class EmptyRoundFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $output = new ConsoleOutput();

        $existingPack = $manager->getRepository(Pack::class)->findOneBy(['code' => Pack::EMPTY_ROUND_PACK]);

        if(!$existingPack) {
            $pack = (new Pack())
                ->setCode('passageavide');

            $manager->persist($pack);
            $output->writeln('Création de l\'unité logistique passageavide');

            $manager->flush();
        }
    }

    public static function getGroups():array {
        return ['fixtures'];
    }

}
