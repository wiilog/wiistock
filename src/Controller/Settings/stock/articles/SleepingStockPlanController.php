<?php

namespace App\Controller\Settings\stock\articles;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/parametrage/planification-alerte-stock-dormant', name: "settings_sleeping_stock_plan")]
class SleepingStockPlanController extends AbstractController {

    #[Route('/api/{type}', name: '_api', options: ['expose' => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::DISPLAY_ARTI], mode: HasPermission::IN_JSON)]
    public function sleepingStockRequestPlan(EntityManagerInterface $entityManager,
                                             Type                   $type): JsonResponse {
        $sleepingStockPlanRepository = $entityManager->getRepository(SleepingStockPlan::class);

        $sleepingStockPlan = $sleepingStockPlanRepository->findOneBy(["type" => $type]) ?? new SleepingStockPlan();

        return $this->json([
            "success" => true,
            "html" => $this->renderView('settings/stock/articles/sleeping_stock_plan_form.html.twig', [
                'sleepingStockPlan' => $sleepingStockPlan
            ]),
        ]);
    }
}
