<?php


namespace App\DataFixtures;


use App\Repository\ArticleRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class ChampsLibresToFixesFixtures extends Fixture implements FixtureGroupInterface
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
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    public function __construct(ArticleRepository $articleRepository,
                                EmplacementRepository $emplacementRepository,
                                UserPasswordEncoderInterface $encoder,
                                TypeRepository $typeRepository,
                                ChampLibreRepository $champsLibreRepository,
                                FournisseurRepository $fournisseurRepository,
                                StatutRepository $statutRepository,
                                ReferenceArticleRepository $refArticleRepository,
                                CategorieCLRepository $categorieCLRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
    }

    public function load(ObjectManager $manager)
    {
        $allRefArticles = $this->refArticleRepository->findAll();
        $allArticles = $this->articleRepository->findAll();

        $counter = 0;

        $refArticleCount = count($allRefArticles);
        $articleCount = count($allArticles);

        foreach($allRefArticles as $refArticle){
            $valueAlerteSeuil = $this->refArticleRepository->getStockMiniClByRef($refArticle);
            $valueAlerteSecurity = $this->refArticleRepository->getStockAlerteClByRef($refArticle);
            $valuePrice = $this->refArticleRepository->getStockPriceClByRef($refArticle);

            if ($valueAlerteSecurity != null) {
                $refArticle->setLimitSecurity($valueAlerteSecurity[0]['valeur']);
            }
            if ($valueAlerteSeuil != null) {
                $refArticle->setLimitWarning($valueAlerteSeuil[0]['valeur']);
            }
            if ($valuePrice != null) {
                $refArticle->setPrixUnitaire($valuePrice[0]['valeur']);
            }
            $counter++;

            if (($counter % 1000) === 0) {
                dump("Flush ref articles : $counter / $refArticleCount");
                $manager->flush();
            }
        }

        if (($counter % 1000) !== 0) {
            dump("Flush ref articles : $counter / $refArticleCount");
            $manager->flush();
        }

        $counter = 0;

        foreach ($allArticles as $article) {
            $valuePriceArt = $this->articleRepository->getStockPriceClByArt($article);

            if ($valuePriceArt != null) {
                $article->setPrixUnitaire($valuePriceArt[0]['valeur']);
            }
            $counter++;

            if ($counter % 1000 === 0) {
                dump("Flush articles : $counter / $articleCount");
                $manager->flush();
            }
        }

        if ($counter % 1000 !== 0) {
            dump("Flush article : $counter / $articleCount");
            $manager->flush();
        }
    }

    public static function getGroups():array {
        return ['champsLibresToFixes'];
    }
}
