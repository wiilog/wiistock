<?php


namespace App\DataFixtures;


use App\Entity\DaysWorked;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class SetDaysFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $daysWorkedRepository = $manager->getRepository(DaysWorked::class);

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $index => $dayString) {
        	$day = $daysWorkedRepository->findOneBy(['day' => $dayString]);

        	if (!$day) {
				$day = new DaysWorked();
				$day
					->setDisplayOrder($index + 1)
					->setDay($dayString)
					->setWorked(1);
				$manager->persist($day);
			}
        }

        $manager->flush();

    }

    public static function getGroups(): array
    {
        return ['fixtures'];
    }
}
