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

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ObjectManager;


class PatchCeaSILIFixtures extends Fixture implements FixtureGroupInterface
{

    private $specificService;
    private $refArticleService;

    public function __construct(SpecificService $specificService, RefArticleDataService $refArticleDataService)
    {
        $this->specificService = $specificService;
        $this->refArticleService = $refArticleDataService;
    }

    /**
     * @param ObjectManager $manager
     * @throws NonUniqueResultException
     * @throws \Exception
     */
    public function load(ObjectManager $manager)
    {
        $isCEA = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI);

        if ($isCEA) {
            $articleRepository = $manager->getRepository(Article::class);
            $statutRepository = $manager->getRepository(Statut::class);
            $statutConsomme = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);
            $refsToUpdate = [];
            $availableSILIArticles = $articleRepository
                ->getByStatutAndTypeWithoutInProgressPrepaNorLivraison(
                    Article::STATUT_EN_TRANSIT,
                    Type::LABEL_SILICIUM,
                    [
                        Preparation::STATUT_A_TRAITER,
                        Preparation::STATUT_EN_COURS_DE_PREPARATION
                    ],
                    [Livraison::STATUT_A_TRAITER]);
            foreach ($availableSILIArticles as $availableSILIArticle) {
                $availableSILIArticle
                    ->setStatut($statutConsomme);
                $referenceArticle = $availableSILIArticle->getArticleFournisseur()
                    ? $availableSILIArticle->getArticleFournisseur()->getReferenceArticle()
                    : null;
                if ($referenceArticle && !isset($refsToUpdate[$referenceArticle->getId()])) {
                    $refsToUpdate[$referenceArticle->getId()] = $referenceArticle;
                }
            }
            $manager->flush();
            foreach ($refsToUpdate as $refToUpdate) {
                $this->refArticleService->updateRefArticleQuantities($refToUpdate);
                $this->refArticleService->treatAlert($refToUpdate);
            }
            $manager->flush();
        }
    }

    public static function getGroups(): array
    {
        return ['cea-sili-fix'];
    }

}
