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

class TransfertDonneesCEAFixtures extends Fixture implements FixtureGroupInterface
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
        $siliType = $this->typeRepository->findOneByCategoryLabelAndLabel(CategoryType::ARTICLES_ET_REF_CEA, Type::LABEL_SILI);
        $clCodeProjet = $this->champsLibreRepository->findOneByLabel(ChampsLibre::LABEL_CP_ART);
        $clDestinataire = $this->champsLibreRepository->findOneByLabel(ChampsLibre::LABEL_DESTI_ART);
        $toPut = 'demande' . ';' . 'codeProjet' . ';' . 'destinataire' . PHP_EOL;
        $path = "src/DataFixtures/demandes.csv";
        file_put_contents($path, $toPut);

        foreach ($this->demandeRepository->findAll() as $demande) {
            $articles = $this->articleRepository->getByDemandeAndType($demande, $siliType);
            if (count($articles) > 0) {

                $article = $articles[0];
                $valeurCodeProjet = $this->valeurChampLibreRepository->findOneByChampLibreAndArticle($clCodeProjet->getId(), $article->getId())->getValeur();
                $valeurDestinataire = $this->valeurChampLibreRepository->findOneByChampLibreAndArticle($clDestinataire->getId(), $article->getId())->getValeur();

                $valeurDestinataire = str_replace('├®', 'é', $valeurDestinataire);
                $valeurDestinataire = str_replace('├¿', 'è', $valeurDestinataire);
                $valeurDestinataire = str_replace('├º', 'ç', $valeurDestinataire);
                $valeurDestinataire = str_replace('├á', 'à', $valeurDestinataire);
                $valeurDestinataire = str_replace('├½', 'ë', $valeurDestinataire);
                $valeurDestinataire = str_replace('├ë', 'É', $valeurDestinataire);
                $toPut = $demande->getId() . ';' . $valeurCodeProjet . ';' . $valeurDestinataire . PHP_EOL;
                dump($toPut);
                file_put_contents($path, $toPut, FILE_APPEND | LOCK_EX);
            }
        }
    }

    public static function getGroups():array {
        return ['transfertCEA'];
    }

}
