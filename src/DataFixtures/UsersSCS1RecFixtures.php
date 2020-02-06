<?php

namespace App\DataFixtures;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\RoleRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ValeurChampLibreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Repository\TypeRepository;
use App\Repository\ChampLibreRepository;

class UsersSCS1RecFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;


    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurCLRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var RoleRepository
     */
    private $roleRepository;


    public function __construct(UtilisateurRepository $utilisateurRepository, RoleRepository $roleRepository, ValeurChampLibreRepository $valeurChampLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampLibreRepository $champLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $refArticleRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->roleRepository = $roleRepository;
        $this->typeRepository = $typeRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->valeurCLRepository = $valeurChampLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "src/DataFixtures/Csv/users.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        $role = $this->roleRepository->findByLabel('Demandeur Safran');
        foreach($rows as $row) {
            $utilisateur = new Utilisateur();
            $password = $this->encoder->encodePassword($utilisateur, 'DemSafran&123');
            if ($this->utilisateurRepository->findOneByMail($row[2]) === null) {
                $utilisateur
                    ->setUsername($row[0] . ' ' . $row[1])
                    ->setEmail($row[2])
                    ->setRole($role)
                    ->setStatus(true)
                    ->setRoles(['USER'])// évite bug -> champ roles ne doit pas être vide
                    ->setPassword($password)
                    ->setColumnVisible(["Actions", "Libellé", "Référence", "Type", "Quantité", "Emplacement"])
                    ->setRecherche(["Libellé", "Référence"]);
                dump('insert user ' . $row[0] . ' ' . $row[1]);
                $manager->persist($utilisateur);
            }
        }
        $manager->flush();
        fclose($file);
    }

    public static function getGroups():array {
        return ['safran-users'];
    }

}
