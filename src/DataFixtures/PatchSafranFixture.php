<?php

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\NonUniqueResultException;


class PatchSafranFixture extends Fixture implements FixtureGroupInterface
{

    /**
     * @param ObjectManager $manager
     * @throws NonUniqueResultException
     */
    public function load(ObjectManager $manager)
    {
		$rolesLabels = [
			'Demandeur Safran'
		];

        $roleRepository = $manager->getRepository(Role::class);

		foreach ($rolesLabels as $roleLabel) {
			$role = $roleRepository->findByLabel($roleLabel);

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
