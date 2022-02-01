<?php

namespace App\DataFixtures;

use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\PasswordService;
use App\Service\UserService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ImportUtilisateursFixtures extends Fixture implements FixtureGroupInterface {

    /**
     * @var PasswordService
     */
    private $passwordService;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $encoder;
    private $userService;

    public function __construct(PasswordService $passwordService,
                                UserService $userService,
                                UserPasswordEncoderInterface $encoder) {
        $this->passwordService = $passwordService;
        $this->encoder = $encoder;
        $this->userService = $userService;
    }

    public function load(ObjectManager $manager) {
        $emails = [];

        $path = "src/DataFixtures/utilisateurs.csv";
        $file = fopen($path, "r");

        $utilisateurRepository = $manager->getRepository(Utilisateur::class);
        $roleRepository = $manager->getRepository(Role::class);

        $role = $roleRepository->findOneBy(["label" => "Utilisateur Safran"]);
        if(!$role) {
            $role = new Role();
            $role->setLabel("Utilisateur Safran");
            $role->setIsMailSendAccountCreation(false);
            $role->setQuantityType(ReferenceArticle::QUANTITY_TYPE_REFERENCE);

            $manager->persist($role);
            $manager->flush();
        }


        while($line = fgetcsv($file, 0, ",")) {
            $matches = [];
            preg_match("/[a-z-_\.]+@[a-z]+\.[a-z]+/i", $line[1], $matches);

            if(isset($matches[0])) {
                $email = strtolower($matches[0]);

                $existing = $utilisateurRepository->findOneBy(['email' => $email]);

                if(!$existing && !isset($emails[$email])) {
                    $uniqueMobileKey = $this->userService->createUniqueMobileLoginKey($manager);

                    $user = new Utilisateur();
                    $user
                        ->setUsername($line[0])
                        ->setEmail($email)
                        ->setRole($role)
                        ->setStatus(true)
                        ->setPassword("")
                        ->setMobileLoginKey($uniqueMobileKey);

                    $manager->persist($user);

                    $emails[$email] = true;
                }
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['import-utilisateurs'];
    }
}
