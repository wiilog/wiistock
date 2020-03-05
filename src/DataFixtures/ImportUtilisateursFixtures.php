<?php

namespace App\DataFixtures;

use App\Entity\Emplacement;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Repository\EmplacementRepository;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use App\Service\PasswordService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ImportUtilisateursFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var PasswordService
     */
    private $passwordService;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $encoder;

    /**
     * @var RoleRepository
     */
    private $roleRepository;

    public function __construct(UtilisateurRepository $utilisateurRepository,
                                PasswordService $passwordService,
                                RoleRepository $roleRepository,
                                UserPasswordEncoderInterface $encoder,
                                EmplacementRepository $emplacementRepository)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->passwordService = $passwordService;
        $this->encoder = $encoder;
        $this->roleRepository = $roleRepository;
    }

    public function load(ObjectManager $manager)
    {
		$path = "src/DataFixtures/utilisateurs.csv";
		$file = fopen($path, "r");

        $firstRow = true;

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
				$utilisateur = $this->utilisateurRepository->findOneByMail($mail);
				if (empty($utilisateur)) {
                    $role = $this->roleRepository->findByLabel($roleStr);
                    $emplacement = $this->emplacementRepository->findOneByLabel($emplacementStr);
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
                        ->setPassword($this->encoder->encodePassword($utilisateur, $pass));

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
