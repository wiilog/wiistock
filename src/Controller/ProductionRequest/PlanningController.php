<?php

namespace App\Controller\ProductionRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Controller\FieldModesController;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Service\FormatService;
use App\Service\OperationHistoryService;
use App\Service\ProductionRequest\ProductionRequestService;
use App\Service\StatusService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route('/production/planning', name: 'production_request_planning_')]
class PlanningController extends AbstractController {

    #[Route('/index', name: 'index', methods: self::GET)]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST_PLANNING])]
    public function index(EntityManagerInterface   $entityManager,
                          StatusService            $statusService,
                          ProductionRequestService $productionRequestService): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $currentUser = $this->getUser();

        $types = $typeRepository->findByCategoryLabels([CategoryType::PRODUCTION]);

        $fields = $productionRequestService->getVisibleColumnsConfig($entityManager, $currentUser, FieldModesController::PAGE_PRODUCTION_REQUEST_PLANNING);

        return $this->render('production_request/planning/index.html.twig', [
            "types" => Stream::from($types)
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $this->getFormatter()->type($type)
                ])
                ->toArray(),
            "statuses" => $statusRepository->findByCategorieName(CategorieStatut::PRODUCTION),
            "fields" => $fields,
            "statusStateValues" => Stream::from($statusService->getStatusStatesValues())
                ->keymap(static fn(array $status) => [
                    $status['id'],
                    $status['label']
                ])
                ->toArray(),
            "token" => $_SERVER["APP_PRODUCTION_REQUEST_PLANNING_TOKEN"] ?? "",
        ]);
    }

    #[Route('/api-externe', name: 'api_external', options: ['expose' => true], methods: [self::GET])]
    public function apiExternal(EntityManagerInterface   $entityManager,
                                ProductionRequestService $productionRequestService,
                                Request                  $request): Response {
        if($request->query->get("token") !== $_SERVER["APP_PRODUCTION_REQUEST_PLANNING_TOKEN"]) {
           throw $this->createAccessDeniedException();
        }

        return $this->json([
            "success" => true,
            "template" => $this->renderView(
                'utils/planning/content.html.twig',
                $productionRequestService->createPlanningConfig($entityManager, $request, true)
            ),
        ]);
    }


    #[Route('/api', name: 'api', options: ['expose' => true], methods: [self::GET])]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST_PLANNING], mode: HasPermission::IN_JSON)]
    public function api(EntityManagerInterface   $entityManager,
                        ProductionRequestService $productionRequestService,
                        Request                  $request): Response {
        return $this->json([
            "success" => true,
            "template" => $this->renderView(
                'utils/planning/content.html.twig',
                $productionRequestService->createPlanningConfig($entityManager, $request, false)
            ),
        ]);
    }

    #[Route("/externe/{token}", name: "external")]
    public function external(string $token): Response {
        if ($token !== $_SERVER["APP_PRODUCTION_REQUEST_PLANNING_TOKEN"]) {
            return $this->redirectToRoute("access_denied");
        }

        return $this->render("production_request/planning/external.html.twig", [
            "token" => $token,
            "firstRefreshDate" => (new DateTime())->format("d/m/Y H:i"),
        ]);
    }

    #[Route("/update-expected-at/{productionRequest}/{date}/{order}", name: "update_expected_at", options: ["expose" => true], methods: self::PUT)]
    #[HasPermission([Menu::PRODUCTION, Action::EDIT_EXPECTED_DATE_FIELD_PRODUCTION_REQUEST])]
    public function updateExpectedAt(ProductionRequest       $productionRequest,
                                     string                  $date,
                                     string                  $order,
                                     EntityManagerInterface  $entityManager,
                                     FormatService           $formatService,
                                     OperationHistoryService $operationHistoryService): Response {

        $date = new DateTime($date);
        $order = json_decode($order);

        $productionRequestRepository = $entityManager->getRepository(ProductionRequest::class);

        $productionRequests = Stream::from($order)
            ->map(static function(?int $productionRequestId) use ($productionRequestRepository) {
                if($productionRequestId) {
                    return $productionRequestRepository->find($productionRequestId);
                } else {
                    return null;
                }
            })
            ->toArray();

        $emptyColumn = Stream::from($productionRequests)->filter()->isEmpty();
        $currentExpectedAt = $productionRequest->getExpectedAt();
        $defaultNewExpectedAt = new DateTime("{$date->format("Y-m-d")} {$currentExpectedAt->format("H:i:s")}");
        if($emptyColumn) {
            $newExpectedAt = $defaultNewExpectedAt;
        } else if(isset($productionRequests[0])) {
            $previousProductionRequest = $productionRequests[0];
            $newExpectedAt = $previousProductionRequest->getExpectedAt()->modify("+1 minute");
        } else {
            $nextProductionRequest = $productionRequests[1];

            $productionRequestExpectedAt = $productionRequest->getExpectedAt();
            $nextProductionRequestExpectedAt = $nextProductionRequest->getExpectedAt();
            $productionRequestExpectedAtTimeToSeconds = (
                ((int)$productionRequestExpectedAt->format("H") * 3600)
                + ((int)$productionRequestExpectedAt->format("i") * 60)
                + ((int)$productionRequestExpectedAt->format("s"))
            );

            $nextProductionRequestExpectedAtTimeToSeconds = (
                ((int)$nextProductionRequestExpectedAt->format("H") * 3600)
                + ((int)$nextProductionRequestExpectedAt->format("i") * 60)
                + ((int)$nextProductionRequestExpectedAt->format("s"))
            );

            if($productionRequestExpectedAtTimeToSeconds < $nextProductionRequestExpectedAtTimeToSeconds) {
                $newExpectedAt = $defaultNewExpectedAt;
            } else {
                $newExpectedAt = $nextProductionRequest->getExpectedAt()->modify("-1 minute");
            }
        }

        $productionRequest->setExpectedAt($newExpectedAt);
        $operationHistoryService->persistProductionHistory(
            $entityManager,
            $productionRequest,
            OperationHistoryService::TYPE_REQUEST_EDITED_DETAILS,
            [
                "user" => $this->getUser(),
                "message" => "<br>" . "<strong>" . FixedFieldEnum::expectedAt->value . "</strong> : " . $formatService->datetime($newExpectedAt, "", true) . "<br>",
            ]
        );

        $entityManager->flush();

        return $this->json([
            "success" => true,
        ]);
    }
}
