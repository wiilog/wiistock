<?php

namespace App\DataFixtures;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\LanguageService;
use App\Service\SpecificService;
use App\Service\UserService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Service\Attribute\Required;

class InitUserFixtures extends Fixture implements FixtureGroupInterface
{
    #[Required]
    public SpecificService $specificService;

    #[Required]
    public UserService $userService;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public UserPasswordHasherInterface $userPasswordEncoder;

    #[Required]
    public LanguageService $languageService;

    private ConsoleOutput $output;

    public function __construct() {
        $this->output = new ConsoleOutput();
    }

    public function load(ObjectManager $manager)
    {
        $uniqueMobileKey = $this->userService->createUniqueMobileLoginKey($this->entityManager);
        $roleRepository = $manager->getRepository(Role::class);
        $userRepository = $manager->getRepository(Utilisateur::class);
        $adminRole = $roleRepository->findByLabel(Role::SUPER_ADMIN);
        $adminEmail = 'admin@wiilog.fr';

        $existing = $userRepository->findBy(['username' => $adminEmail]);
        if (empty($existing)) {
            $user = new Utilisateur();
            $password = $this->userPasswordEncoder->hashPassword($user, "Admin1234");
            $language = $this->languageService->getNewUserLanguage();
            $user
                ->setUsername($adminEmail)
                ->setEmail($adminEmail)
                ->setRole($adminRole)
                ->setStatus(true)
                ->setLanguage($language)
                ->setDateFormat(Utilisateur::DEFAULT_DATE_FORMAT)
                ->setPassword($password)
                ->setMobileLoginKey($uniqueMobileKey);
            $manager->persist($user);
            $manager->flush();
            $this->output->writeln('admin@wiilog.fr user created');
        }
    }

    public static function getGroups(): array {
        return ['init-user'];
    }
}
