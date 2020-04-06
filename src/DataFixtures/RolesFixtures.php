<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class RolesFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder) {
        $this->encoder = $encoder;
    }

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

        $manager->flush();
    }

    public static function getGroups():array {
        return ['fixtures'];
    }
}
