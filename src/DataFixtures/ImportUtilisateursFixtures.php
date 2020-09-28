<?php

namespace App\DataFixtures;

use App\Entity\Emplacement;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\PasswordService;
use App\Service\UserService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
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
        $header = fgetcsv($file, 0, ",");

        $utilisateurRepository = $manager->getRepository(Utilisateur::class);
        $roleRepository = $manager->getRepository(Role::class);

        $role = $roleRepository->findOneBy(["label" => "Utilisateur Emerson"]);
        if(!$role) {
            $role = new Role();
            $role->setLabel("Utilisateur Emerson");
            $role->setIsMailSendAccountCreation(false);
            $role->setDashboardsVisible([]);
            $role->setActive(true);

            $manager->persist($role);
            $manager->flush();
        }


        while($line = fgetcsv($file, 0, ",")) {
            $matches = [];
            preg_match("/[a-z-_\.]+@[a-z]+\.[a-z]+/i", $line[1], $matches);

            if(isset($matches[0])) {
                $email = strtolower($matches[0]);
                $active = $line[2];

                $existing = $utilisateurRepository->findOneBy(['email' => $email]);

                if(!$existing && !isset($emails[$email])) {
                    $uniqueMobileKey = $this->userService->createUniqueMobileLoginKey($manager);

                    $user = new Utilisateur();
                    $user
                        ->setUsername($email)
                        ->setEmail($email)
                        ->setRole($role)
                        ->setStatus($active)
                        ->setRoles(['USER'])// évite bug -> champ roles ne doit pas être vide
                        ->setRechercheForArticle(Utilisateur::SEARCH_DEFAULT)
                        ->setRecherche(Utilisateur::SEARCH_DEFAULT)
                        ->setColumnVisible(Utilisateur::COL_VISIBLE_REF_DEFAULT)
                        ->setColumnsVisibleForArticle(Utilisateur::COL_VISIBLE_ARTICLES_DEFAULT)
                        ->setColumnsVisibleForArrivage(Utilisateur::COL_VISIBLE_ARR_DEFAULT)
                        ->setColumnsVisibleForDispatch(Utilisateur::COL_VISIBLE_DISPATCH_DEFAULT)
                        ->setColumnsVisibleForLitige(Utilisateur::COL_VISIBLE_LIT_DEFAULT)
                        ->setPassword(null)
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
