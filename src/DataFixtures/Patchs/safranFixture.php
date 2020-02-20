<?php

namespace App\DataFixtures\Patchs;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class safranFixture extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
		$rolesLabels = [
			'Demandeur Safran'
		];

		foreach ($rolesLabels as $roleLabel) {
			$role = $this->roleRepository->findByLabel($roleLabel);

			if (empty($role)) {
				$role = new Role();
				$role
					->setLabel($roleLabel)
					->setActive(true);

				$manager->persist($role);
				dump("création du rôle " . $roleLabel);
			}
		}

		$manager->flush();
    }

    public static function getGroups():array {
        return ['safran'];
    }

}
