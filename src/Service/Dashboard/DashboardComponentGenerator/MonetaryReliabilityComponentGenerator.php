<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Article;
use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Service\Dashboard\DashboardService;
use Doctrine\ORM\EntityManagerInterface;

class MonetaryReliabilityComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $stockMovementRepository = $entityManager->getRepository(MouvementStock::class);

        $types = [
            MouvementStock::TYPE_INVENTAIRE_ENTREE,
            MouvementStock::TYPE_INVENTAIRE_SORTIE
        ];
        $nbStockInventoryMovements = $stockMovementRepository->countByTypes($types);
        $nbActiveRefAndArt = $referenceArticleRepository->countActiveTypeRefRef() + $articleRepository->countActiveArticles();
        $count = $nbActiveRefAndArt == 0 ? 0 : (1 - ($nbStockInventoryMovements / $nbActiveRefAndArt)) * 100;

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $meter
            ->setCount($count ?? 0);
    }
}
