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
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Doctrine\Persistence\ObjectManager;


class KioskUserFixtures extends Fixture implements FixtureGroupInterface
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
        if ($this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI)){
            $uniqueMobileKey = $this->userService->createUniqueMobileLoginKey($this->entityManager);
            $roleRepository = $this->entityManager->getRepository(Role::class);
            $userRepository = $this->entityManager->getRepository(Utilisateur::class);

            $noAccessRole = $roleRepository->findByLabel(Role::NO_ACCESS_USER);
            $kioskEmail = 'kiosk';

            $existing = $userRepository->getKioskUser();
            if (empty($existing)) {
                $user = new Utilisateur();
                $password = $this->userPasswordEncoder->hashPassword($user, bin2hex(random_bytes(100)));
                $language = $this->languageService->getNewUserLanguage();
                $user
                    ->setUsername($kioskEmail)
                    ->setEmail($kioskEmail)
                    ->setRole($noAccessRole)
                    ->setStatus(true)
                    ->setLanguage($language)
                    ->setDateFormat(Utilisateur::DEFAULT_DATE_FORMAT)
                    ->setPassword($password)
                    ->setMobileLoginKey($uniqueMobileKey)
                    ->setKioskUser(true);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->output->writeln('Kiosk user created');
            }
        }
    }

    public static function getGroups(): array {
        return ["fixtures", "kioskUser"];
    }
}
