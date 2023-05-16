<?php

namespace App\Controller\ShippingRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\ShippingRequest\ShippingRequestService;
use App\Service\TranslationService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route("/expeditions")]
class ShippingRequestController extends AbstractController {

    #[Route("/", name: "shipping_request_index")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function index(EntityManagerInterface $entityManager,
                          ShippingRequestService $service,
                          TranslationService $translationService): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $currentUser = $this->getUser();
        $fields = $service->getVisibleColumnsConfig($currentUser);


        $statutRepository = $entityManager->getRepository(Statut::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);

        $dateChoice = [
            [
                'name' => 'createdAt',
                'label' => $translationService->translate('Général', null, 'Zone liste', 'Date de création'),
            ],
            [
                'name' => 'requestCaredAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date de prise en charge souhaitée'),
            ],
            [
                'name' => 'validatedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date de validation'),
            ],
            [
                'name' => 'plannedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date de planification'),
            ],
            [
                'name' => 'expectedPickedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date d\'enlèvement prévu'),
            ],
            [
                'name' => 'treatedAt',
                'label' => $translationService->translate('Demande', 'Expédition', 'Date d\'expédition'),
            ],
        ];
        foreach ($dateChoice as &$choice) {
            $choice['default'] = (bool)$filtreSupRepository->findOnebyFieldAndPageAndUser('date-choice_'.$choice['name'], 'expedition', $currentUser);
        }
        if (Stream::from($dateChoice)->every(function ($choice) { return !$choice['default']; })) {
            $dateChoice[0]['default'] = true;
        }

        return $this->render('shipping_request/index.html.twig', [
            "fields" => $fields,
            "initial_visible_columns" => $this->apiColumns($service)->getContent(),
            "statuses" => $statutRepository->findByCategorieName(ShippingRequest::CATEGORIE, 'displayOrder'),
            "dateChoices" =>$dateChoice,
            "carriersForFilter" => $carrierRepository->findAll(),
        ]);
    }

    #[Route("/api-columns", name: "shipping_request_api_columns", options: ["expose" => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function apiColumns(ShippingRequestService $service): Response {
        $currentUser = $this->getUser();
        $columns = $service->getVisibleColumnsConfig($currentUser);

        return new JsonResponse($columns);
    }

    #[Route("/api", name: "shipping_request_api", options: ["expose" => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING], mode: HasPermission::IN_JSON)]
    public function api(Request                $request,
                        ShippingRequestService $service,
                        EntityManagerInterface $entityManager) {
        return $this->json($service->getDataForDatatable($request, $entityManager));
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
                             ShippingRequestService $shippingRequestService,
                             EntityManagerInterface $entityManager): Response {


        return $this->render('shipping_request/show.html.twig', [
            'shipping'=> $shippingRequest,
            'detailsTransportConfig' => $shippingRequestService->createHeaderTransportDetailsConfig($shippingRequest)
        ]);
    }

    #[Route("/get-transport-header-config/{id}", name:"get_transport_header_config", methods: ['GET', 'POST'], options:["expose"=>true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function getTransportHeaderConfig(ShippingRequest        $shippingRequest,
                                             ShippingRequestService $shippingRequestService): Response {
        return $this->json([
            'detailsTransportConfig' => $shippingRequestService->createHeaderTransportDetailsConfig($shippingRequest)
        ]);
    }

    #[Route("/csv", name: "get_shipping_requests_csv", options: ["expose" => true], methods: ['GET'])]
    public function exportShippingRequests(EntityManagerInterface $entityManager,
                                           CSVExportService       $csvService,
                                           DataExportService      $dataExportService) {
        $shippingRepository = $entityManager->getRepository(ShippingRequest::class);

        $csvHeader = $dataExportService->createShippingRequestHeader();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");
        $shippingRequestsIterator = $shippingRepository->iterateShippingRequests();

        return $csvService->streamResponse(function ($output) use ($dataExportService, $shippingRequestsIterator) {
            $dataExportService->exportShippingRequests($shippingRequestsIterator, $output);
        }, "export-expeditions_$today.csv", $csvHeader);
    }
}
