<?php

namespace App\DataFixtures;

use App\Entity\Role;
use App\Repository\RoleRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class RolesFixtures extends Fixture
{
    private $encoder;

    /**
     * @var RoleRepository
     */
    private $roleRepository;

    public function __construct(RoleRepository $roleRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->roleRepository = $roleRepository;
    }

    public function load(ObjectManager $manager)
    {
        $rolesLabels = [
            Role::NO_ACCESS_USER
        ];

        foreach ($rolesLabels as $roleLabel) {
            $role = $this->roleRepository->findByLabel(Role::NO_ACCESS_USER);

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
}
