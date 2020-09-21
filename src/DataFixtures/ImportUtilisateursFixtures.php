<?php

namespace App\DataFixtures;

use App\Entity\Emplacement;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\PasswordService;
use App\Service\UserService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ImportUtilisateursFixtures extends Fixture implements FixtureGroupInterface
{

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
                                UserPasswordEncoderInterface $encoder)
    {
        $this->passwordService = $passwordService;
        $this->encoder = $encoder;
        $this->userService = $userService;
    }

    public function load(ObjectManager $manager)
    {
		$path = "src/DataFixtures/utilisateurs.csv";
		$file = fopen($path, "r");

        $firstRow = true;

        $emplacementRepository = $manager->getRepository(Emplacement::class);
        $roleRepository = $manager->getRepository(Role::class);
        $utilisateurRepository = $manager->getRepository(Utilisateur::class);

        while (($data = fgetcsv($file, 1000, ";")) !== false) {
        	if ($firstRow) {
        		$firstRow = false;
			} else {
				$row = array_map('utf8_encode', $data);
                $username = $row[2];
                $mail = $row[3];
                $emplacementStr = $row[4];
                $roleStr = $row[5];
                $pass = $this->passwordService->generateToken(8);
				$utilisateur = $utilisateurRepository->findOneBy(['email' => $mail]);
				if (empty($utilisateur)) {
                    $role = $roleRepository->findByLabel($roleStr);
                    $emplacement = $emplacementRepository->findOneByLabel($emplacementStr);
				    if (empty($role)) {
				        $role = new Role();
				        $role
                            ->setLabel($roleStr)
                            ->setActive(true);
                        $manager->persist($role);
                    }
                    if (empty($emplacement)) {
                        $emplacement = new Emplacement();
                        $emplacement
                            ->setLabel($emplacementStr)
                            ->setIsActive(true);
                        $manager->persist($emplacement);
                    }

                    $uniqueMobileKey = $this->userService->createUniqueMobileLoginKey($manager);

                    $utilisateur = new Utilisateur();
					$utilisateur
                        ->setUsername($username)
                        ->setEmail($mail)
                        ->setRole($role)
                        ->setStatus(true)
						->setRoles(['USER'])// évite bug -> champ roles ne doit pas être vide
						->setRechercheForArticle(Utilisateur::SEARCH_DEFAULT)
						->setRecherche(Utilisateur::SEARCH_DEFAULT)
						->setDropzone($emplacement)
						->setColumnVisible(Utilisateur::COL_VISIBLE_REF_DEFAULT)
						->setColumnsVisibleForArticle(Utilisateur::COL_VISIBLE_ARTICLES_DEFAULT)
                        ->setColumnsVisibleForArrivage(Utilisateur::COL_VISIBLE_ARR_DEFAULT)
                        ->setColumnsVisibleForDispatch(Utilisateur::COL_VISIBLE_DISPATCH_DEFAULT)
                        ->setColumnsVisibleForLitige(Utilisateur::COL_VISIBLE_LIT_DEFAULT)
                        ->setPassword($this->encoder->encodePassword($utilisateur, $pass))
                        ->setMobileLoginKey($uniqueMobileKey);

					$manager->persist($utilisateur);
        			$manager->flush();
				}
			}
        }
    }

	public static function getGroups(): array
	{
		return ['import-utilisateurs'];
	}
}
