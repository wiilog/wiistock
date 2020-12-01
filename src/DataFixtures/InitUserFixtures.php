<?php

namespace App\DataFixtures;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\SpecificService;
use App\Service\UserService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class InitUserFixtures extends Fixture implements FixtureGroupInterface
{
    private $specificService;
    private $output;
    private $userService;
    private $entityManager;
    private $userPasswordEncoder;

    public function __construct(UserService $userService,
                                UserPasswordEncoderInterface $userPasswordEncoder,
                                EntityManagerInterface $entityManager,
                                SpecificService $specificService)
    {
        $this->userService = $userService;
        $this->userPasswordEncoder = $userPasswordEncoder;
        $this->entityManager = $entityManager;
        $this->specificService = $specificService;
        $this->output = new ConsoleOutput();
    }

    public function load(ObjectManager $manager)
    {
        $uniqueMobileKey = $this->userService->createUniqueMobileLoginKey($this->entityManager);
        $roleRepository = $manager->getRepository(Role::class);
        $adminRole = $roleRepository->findByLabel(Role::SUPER_ADMIN);

        $user = new Utilisateur();
        $password = $this->userPasswordEncoder->encodePassword($user, "Admin1234");
        $user
            ->setUsername('admin@wiilog.fr')
            ->setEmail('admin@wiilog.fr')
            ->setRole($adminRole)
            ->setStatus(true)
            ->setPassword($password)
            ->setMobileLoginKey($uniqueMobileKey);
        $manager->persist($user);
        $manager->flush();
        $this->output->writeln('admin@wiilog.fr user created !');
    }

    public static function getGroups(): array {
        return ['init-user'];
    }
}
