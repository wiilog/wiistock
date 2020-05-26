<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RolesFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $rolesLabels = [
            Role::NO_ACCESS_USER,
            Role::SUPER_ADMIN
        ];
        $actionRepository = $manager->getRepository(Action::class);
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

                if ($roleLabel == Role::SUPER_ADMIN) {
                    $actions = $actionRepository->findAll();
                    foreach ($actions as $action) {
                        $action->addRole($role);
                    }
                }
            }
        }
        $allRoles = $roleRepository->findAll();
        foreach ($allRoles as $allRole) {
            if (empty($allRole->getDashboardsVisible())) {
                $allRole->setDashboardsVisible([
                    "arrivage",
                    "quai",
                    "admin",
                    "emballage"
                ]);
            }
        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fixtures'];
    }

    public function getDependencies()
    {
        return [ActionsFixtures::class];
    }
}
