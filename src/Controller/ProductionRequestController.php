<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\TypeRepository;
use App\Service\ProductionRequestService;
use App\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/production', name: 'production_request_')]
class ProductionRequestController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function index(Request $request,
                          EntityManagerInterface   $entityManager,
                          ProductionRequestService $service,
                          StatusService          $statusService
    ): Response
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $fields = $service->getVisibleColumnsConfig($currentUser);

        // repository
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $filterSupRepository = $entityManager->getRepository(FiltreSup::class);

        // data from request
        $query = $request->query;
        $typesFilter = $query->has('types') ? $query->all('types', '') : [];
        $statusesFilter = $query->has('statuses') ? $query->all('statuses', '') : [];

        // case type filter selected
        if (!empty($typesFilter)) {
            $typesFilter = Stream::from($typeRepository->findBy(['id' => $typesFilter]))
                ->filterMap(fn(Type $type) => $type->getLabelIn($currentUser->getLanguage()))
                ->toArray();
        }

        // case status filter selected
        if (!empty($statusesFilter)) {
            $statusesFilter = Stream::from($statutRepository->findBy(['id' => $statusesFilter]))
                ->map(fn(Statut $status) => $status->getId())
                ->toArray();
        }

        $types = $typeRepository->findByCategoryLabels([CategoryType::PRODUCTION]);
        $attachmentAssigned = (bool)$filterSupRepository->findOnebyFieldAndPageAndUser("attachmentsAssigned", 'production', $currentUser);

        $dateChoices =
            [
                [
                    'name' => 'createdAd',
                    'label' => 'Date de création',
                ],
                [
                    'name' => 'expectedAt',
                    'label' => 'Date de réalisation',
                ],
            ];

        foreach ($dateChoices as &$choice) {
            $choice['default'] = (bool)$filterSupRepository->findOnebyFieldAndPageAndUser("date-choice_{$choice['name']}", 'production', $currentUser);
        }

        $dateChoicesHasDefault = Stream::from($dateChoices)
            ->some(static fn($choice) => ($choice['default'] ?? false));

        if ($dateChoicesHasDefault) {
            $dateChoices[0]['default'] = true;
        }

        return $this->render('production_request/index.html.twig', [
            "fields" => $fields,
            'dateChoices' => $dateChoices,
            'types' => Stream::from($types)
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $this->getFormatter()->type($type)
                ])
                ->toArray(),
            'statusStateValues' => Stream::from($statusService->getStatusStatesValues())
                ->reduce(function($status, $item) {
                    $status[$item['id']] = $item['label'];
                    return $status;
                }, []),
            'typesFilter' => $typesFilter,
            'statusFilter' => $statusesFilter,
            'statuses' => $statutRepository->findByCategorieName(CategorieStatut::PRODUCTION, 'displayOrder'),
            "initial_visible_columns" => $this->apiColumns($service)->getContent(),
            "attachmentAssigned" => $attachmentAssigned,
        ]);
    }

    #[Route("/api-columns", name: "api_columns", options: ["expose" => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function apiColumns(ProductionRequestService $service): Response
    {
        $currentUser = $this->getUser();
        $columns = $service->getVisibleColumnsConfig($currentUser);

        return new JsonResponse($columns);
    }

    #[Route("/api", name: "api", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function api(Request                  $request,
                        ProductionRequestService $service,
                        EntityManagerInterface   $entityManager): Response
    {
        return $this->json($service->getDataForDatatable($entityManager, $request));
    }

    #[Route('/voir/{id}', name: 'show', methods: ['GET'])]
    public function show(ProductionRequest $productionRequest): Response
    {

        return $this->render('production_request/show.html.twig', [
            'production_request' => $productionRequest,
        ]);
    }
}
