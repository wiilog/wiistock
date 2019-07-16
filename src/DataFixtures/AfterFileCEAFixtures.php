<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampsLibre;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\ValeurChampsLibre;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ArticleRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\DemandeRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\ValeurChampsLibreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation\Category;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class AfterFileCEAFixtures extends Fixture implements FixtureGroupInterface
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
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampLibreRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    public function __construct(ArticleRepository $articleRepository, DemandeRepository $demandeRepository, ValeurChampsLibreRepository $valeurChampLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, FournisseurRepository $fournisseurRepository, EmplacementRepository $emplacementRepository, CategorieCLRepository $categorieCLRepository, ReferenceArticleRepository $refArticleRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository)
    {
        $this->articleRepository = $articleRepository;
        $this->demandeRepository = $demandeRepository;
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $clCodeProjet = $this->champsLibreRepository->findOneByLabel(ChampsLibre::LABEL_CP_DEM);
        $clDestinataire = $this->champsLibreRepository->findOneByLabel(ChampsLibre::LABEL_DESTI_DEM);
        $siliTypeDemande = $this->typeRepository->findOneByCategoryLabelAndLabel(CategoryType::DEMANDE_LIVRAISON, Type::SILI_DEM);
        $path = "src/DataFixtures/demandes.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = $data;
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes
        foreach ($rows as $row) {
            if (empty($row[0])) continue;
            $demande = $this->demandeRepository->find($row[0]);
            if ($demande->getType() !== $siliTypeDemande) $demande->setType($siliTypeDemande);
            $valeurChampLibreCP = $this->valeurChampLibreRepository->findOneByDemandeLivraisonAndChampsLibre($demande, $clCodeProjet);
            $valeurChampLibreDestinataire = $this->valeurChampLibreRepository->findOneByDemandeLivraisonAndChampsLibre($demande, $clDestinataire);
            if (empty($valeurChampLibreCP)) {
                $valeurChampLibreCP = new ValeurChampsLibre();
                $valeurChampLibreCP
                    ->setValeur($row[1])
                    ->addDemandesLivraison($demande)
                    ->setChampLibre($clCodeProjet);
                $manager->persist($valeurChampLibreCP);
            } else {
                $valeurChampLibreCP
                    ->setValeur($row[1])
                    ->addDemandesLivraison($demande)
                    ->setChampLibre($clCodeProjet);
            }
            if (empty($valeurChampLibreDestinataire)) {
                $valeurChampLibreDestinataire = new ValeurChampsLibre();
                $valeurChampLibreDestinataire
                    ->setValeur($row[2])
                    ->addDemandesLivraison($demande)
                    ->setChampLibre($clDestinataire);
                $manager->persist($valeurChampLibreDestinataire);
            } else {
                $valeurChampLibreDestinataire
                    ->setValeur($row[2])
                    ->addDemandesLivraison($demande)
                    ->setChampLibre($clDestinataire);
            }
            dump('Setted cp and desti CL ' . $row[1] . ' - ' . $row[2] . ' for demande n°' . $demande->getId());
        }
        $manager->flush();
    }

    public static function getGroups():array {
        return ['transfertCEA'];
    }

}
