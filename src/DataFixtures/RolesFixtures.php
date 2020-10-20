<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class RolesFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    /**
     * @param ObjectManager $manager
     * @throws NonUniqueResultException
     */
    public function load(ObjectManager $manager)
    {
        $output = new ConsoleOutput();

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
                    ->setIsMailSendAccountCreation(false)
                    ->setActive(true);

                $manager->persist($role);
                $output->writeln("Création du rôle " . $roleLabel);

                if ($roleLabel == Role::SUPER_ADMIN) {
                    $actions = $actionRepository->findAll();
                    foreach ($actions as $action) {
                        $action->addRole($role);
                    }
                }
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
