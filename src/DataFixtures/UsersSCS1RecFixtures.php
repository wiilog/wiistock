<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\ChampsLibre;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\Role;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\RoleRepository;
use App\Repository\StatutRepository;
use App\Repository\ValeurChampsLibreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class UsersSCS1RecFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;


    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

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
     * @var ValeurChampsLibreRepository
     */
    private $valeurCLRepository;

    /**
     * @var RoleRepository
     */
    private $roleRepository;


    public function __construct(RoleRepository $roleRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $refArticleRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->roleRepository = $roleRepository;
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->valeurCLRepository = $valeurChampsLibreRepository;
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

        // à modifier pour faire imports successifs
        $rows = array_slice($rows, 0, 1000);
        $role = $this->roleRepository->findByLabel(Role::DEM_SAFRAN);
        foreach($rows as $row) {
            $utilisateur = new Utilisateur();
            $password = $this->encoder->encodePassword($utilisateur, 'DemSafran&123');
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
        $manager->flush();
        fclose($file);
    }

    public static function getGroups():array {
        return ['safran-users'];
    }

}
