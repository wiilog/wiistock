<?php

namespace App\DataFixtures;

use App\Entity\Article;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
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
            $typeRepository = $manager->getRepository(Type::class);
            $statutRepository = $manager->getRepository(Statut::class);
            $siliType = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::ARTICLE, Type::LABEL_SILICIUM);
            $statutTransit = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT);
            $statutPreparationTodo = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_A_TRAITER);
            $statutPreparationDoing = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
            $statutLivraisonTodo = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_A_TRAITER);
            $statutConsomme = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);
            $refsToUpdate = [];
            if ($siliType) {
                $availableSILIArticles = $articleRepository
                    ->getByStatutAndTypeWithoutInProgressPrepaNorLivraison($statutTransit, $siliType, [
                        $statutPreparationTodo->getId(),
                        $statutPreparationDoing->getId()
                    ], $statutLivraisonTodo);
                foreach ($availableSILIArticles as $availableSILIArticle) {
                    dump($availableSILIArticle->getId());
                    $availableSILIArticle
                        ->setStatut($statutConsomme);
                    $referenceArticle = $availableSILIArticle->getArticleFournisseur()
                        ? $availableSILIArticle->getArticleFournisseur()->getReferenceArticle()
                        : null;
                    if ($referenceArticle && !isset($refsToUpdate[$referenceArticle->getId()])) {
                        $refsToUpdate[$referenceArticle->getId()] = $referenceArticle;
                    }
                }
            }
            foreach ($refsToUpdate as $refToUpdate) {
                $this->refArticleService->treatAlert($refToUpdate);
                $this->refArticleService->updateRefArticleQuantities($refToUpdate);
            }
            $manager->flush();
        }
    }

    public static function getGroups(): array
    {
        return ['cea-sili-fix'];
    }

}
