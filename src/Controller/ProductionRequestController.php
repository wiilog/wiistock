<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Service\ProductionRequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/production', name: 'production_request_')]
class ProductionRequestController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function index(EntityManagerInterface $entityManager,
                          ProductionRequestService $service): Response {

        $currentUser = $this->getUser();
        $fields = $service->getVisibleColumnsConfig($currentUser);

        return $this->render('production_request/index.html.twig', [
            "fields" => $fields,
            "initial_visible_columns" => $this->apiColumns($service)->getContent(),
        ]);
    }

    #[Route("/api-columns", name: "api_columns", options: ["expose" => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function apiColumns(ProductionRequestService $service): Response {
        $currentUser = $this->getUser();
        $columns = $service->getVisibleColumnsConfig($currentUser);

        return new JsonResponse($columns);
    }

    #[Route("/api", name: "api", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST])]
    public function api(Request                $request,
                        ProductionRequestService $service,
                        EntityManagerInterface $entityManager): Response {
        return $this->json($service->getDataForDatatable( $entityManager, $request));
    }

    #[Route('/voir/{id}', name: 'show', methods: ['GET'])]
    public function show(ProductionRequest $productionRequest): Response {

        return $this->render('production_request/show.html.twig', [
            'production_request' => $productionRequest,
        ]);
    }
}
