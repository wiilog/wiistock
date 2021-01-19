<?php

namespace App\DataFixtures;

use App\Entity\Article;

use App\Entity\CategorieStatut;
use App\Entity\Livraison;
use App\Entity\Preparation;
use App\Entity\Statut;
use App\Entity\Type;
use App\Service\RefArticleDataService;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ObjectManager;


class PatchCeaArticleFixtures extends Fixture implements FixtureGroupInterface
{

    private $specificService;
    private $refArticleService;
    private $entityManager;

    public function __construct(SpecificService $specificService,
                                EntityManagerInterface $entityManager,
                                RefArticleDataService $refArticleDataService)
    {
        $this->specificService = $specificService;
        $this->refArticleService = $refArticleDataService;
        $this->entityManager = $entityManager;
    }

    /**
     * @param ObjectManager $manager
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function load(ObjectManager $manager)
    {
        $isCEA = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI);

        if ($isCEA) {
            $articleRepository = $this->entityManager->getRepository(Article::class);
            $statutRepository = $this->entityManager->getRepository(Statut::class);
            $statutConsomme = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);
            $refsToUpdate = [];
            $articleFournIdToRefArticle = [];
            $availableSILIArticles = $articleRepository
                ->getByStatutAndTypeWithoutInProgressPrepaNorLivraison(
                    Article::STATUT_EN_TRANSIT,
                    Type::LABEL_CSP,
                    [
                        Preparation::STATUT_A_TRAITER,
                        Preparation::STATUT_EN_COURS_DE_PREPARATION
                    ],
                    [Livraison::STATUT_A_TRAITER]);

            $cpt = 0;
            $articleCount = count($availableSILIArticles);
            dump('Total des articles : ' . $articleCount);
            foreach ($availableSILIArticles as $availableSILIArticle) {
                $availableSILIArticle
                    ->setStatut($statutConsomme);
                $articleFournisseur = $availableSILIArticle->getArticleFournisseur();
                if (isset($articleFournisseur)) {
                    $articleFournisseurId = $articleFournisseur->getId();
                    if (!isset($articleFournIdTorefArticle[$articleFournisseurId])) {
                        $articleFournIdToRefArticle[$articleFournisseurId] = $articleFournisseur->getReferenceArticle();
                    }
                    $referenceArticle = $articleFournIdToRefArticle[$articleFournisseurId];
                    if ($referenceArticle && !isset($refsToUpdate[$referenceArticle->getId()])) {
                        $refsToUpdate[$referenceArticle->getId()] = $referenceArticle;
                    }
                }

                $cpt++;

                if (($cpt % 100) === 0) {
                    dump('Flush (' . $cpt . '/' . $articleCount . ') articles');
                    $this->entityManager->flush();
                }
            }

            dump('Flush (' . $articleCount . '/' . $articleCount . ') articles');
            $this->entityManager->flush();

            $cpt = 0;
            $refsToUpdateCount = count($refsToUpdate);
            dump('Total des réferences : ' . $refsToUpdateCount);
            foreach ($refsToUpdate as $refToUpdate) {
                $this->refArticleService->updateRefArticleQuantities($this->entityManager, $refToUpdate);
                $this->refArticleService->treatAlert($this->entityManager, $refToUpdate);

                $cpt++;
                if (($cpt % 100) === 0) {
                    dump('Flush (' . $cpt . '/' . $refsToUpdateCount . ') références');
                    $this->entityManager->flush();
                }
            }
            dump('Flush (' . $refsToUpdateCount . '/' . $refsToUpdateCount . ') références');
            $this->entityManager->flush();
        }
    }

    public static function getGroups(): array
    {
        return ['cea-article-fix'];
    }

}
