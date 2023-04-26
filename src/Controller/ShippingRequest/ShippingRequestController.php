<?php

namespace App\Controller\ShippingRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Menu;
use App\Service\ShippingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/expeditions")
 */
class ShippingRequestController extends AbstractController {

    #[Route("/", name: "shipping_request_index")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function index(EntityManagerInterface $entityManager, ShippingService $service) {
        return $this->render('shipping/index.html.twig', [
            "initial_visible_columns" => $this->apiColumns($entityManager, $service)->getContent(),
        ]);
    }

    #[Route("/api-columns", name: "shipping_api_columns", options: ["expose" => true], methods: ['GET', 'POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function apiColumns(EntityManagerInterface $entityManager, ShippingService $service): Response {

        $currentUser = $this->getUser();
        $columns = $service->getVisibleColumnsConfig($entityManager, $currentUser);

        return new JsonResponse($columns);
    }

    #[Route("/api", name: "shipping_api", options: ["expose" => true], methods: ['GET', 'POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function api(EntityManagerInterface $entityManager, Request $request, ShippingService $service) {
        return $this->json($service->getDataForDatatable($entityManager, $request));
    }
}
