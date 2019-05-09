<?php

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\CategorieStatut;

class RolesFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $rolesLabels = [
            'aucun accès'
        ];

        foreach ($rolesLabels as $roleLabel) {
            $role = new Role();
            $role
                ->setLabel($roleLabel)
                ->setActive(true);

            $manager->persist($role);
        }

        $manager->flush();
    }
}
