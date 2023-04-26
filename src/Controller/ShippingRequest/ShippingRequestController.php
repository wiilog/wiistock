<?php

namespace App\Controller\ShippingRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Utilisateur;
use App\Service\ShippingRequest\ShippingRequestService;
use App\Service\TranslationService;
use App\Service\VisibleColumnService;
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
    public function index(ShippingRequestService $service) {
        $currentUser = $this->getUser();
        $fields = $service->getVisibleColumnsConfig($currentUser);

        return $this->render('shipping_request/index.html.twig', [
            "fields" => $fields,
            "initial_visible_columns" => $this->apiColumns($service)->getContent(),
        ]);
    }

    #[Route("/api-columns", name: "shipping_api_columns", options: ["expose" => true], methods: ['GET', 'POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function apiColumns(ShippingRequestService $service): Response {
        $currentUser = $this->getUser();
        $columns = $service->getVisibleColumnsConfig($currentUser);

        return new JsonResponse($columns);
    }

    #[Route("/api", name: "shipping_api", options: ["expose" => true], methods: ['GET', 'POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function api(Request $request, ShippingRequestService $service) {
        return $this->json($service->getDataForDatatable($request));
    }

    #[Route("/colonne-visible", name: "save_column_visible_for_shipping_request", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function saveColumnVisible(Request                $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService   $visibleColumnService,
                                      TranslationService     $translationService): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $visibleColumnService->setVisibleColumns('shippingRequest', $fields, $currentUser);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => $translationService->translate('Général', null, 'Zone liste', 'Vos préférences de colonnes à afficher ont bien été sauvegardées', false)
        ]);
    }

    #[Route("/voir/{id}", name:"shipping_show_page", options:["expose"=>true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function showPage(Request                $request,
                             ShippingRequest        $shippingRequest,
                             EntityManagerInterface $entityManager): Response {


        return $this->render('shipping_request/show.html.twig', [
            'shipping'=> $shippingRequest,
        ]);
    }
}
