<?php

namespace App\Controller\ShippingRequest;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\Setting;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\ShippingRequest\ShippingRequestLine;
use App\Entity\ShippingRequest\ShippingRequestPack;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Service\FormatService;
use App\Exceptions\FormException;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\MouvementStockService;
use App\Service\ShippingRequest\ShippingRequestService;
use App\Service\StatusHistoryService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\VisibleColumnService;
use Cassandra\Set;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\AdMob\Date;
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
                'label' => 'Date de création',
            ],
            [
                'name' => 'requestCaredAt',
                'label' => 'Date de prise en charge souhaitée',
            ],
            [
                'name' => 'validatedAt',
                'label' => 'Date de validation',
            ],
            [
                'name' => 'plannedAt',
                'label' => 'Date de planification',
            ],
            [
                'name' => 'expectedPickedAt',
                'label' => 'Date d\'enlèvement prévu',
            ],
            [
                'name' => 'treatedAt',
                'label' => 'Date d\'expédition',
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
            "shipping" => new ShippingRequest(),
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
        return $this->json($service->getDataForDatatable( $entityManager, $request));
    }

    #[Route("/voir/{id}", name:"shipping_request_show", options: ["expose" => true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function show(ShippingRequest        $shippingRequest,
                         ShippingRequestService $shippingRequestService): Response {
        return $this->render('shipping_request/show.html.twig', [
            'shipping'=> $shippingRequest,
            'detailsTransportConfig' => $shippingRequestService->createHeaderTransportDetailsConfig($shippingRequest)
        ]);
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

    #[Route("/new", name: "shipping_request_new", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::CREATE_SHIPPING], mode: HasPermission::IN_JSON)]
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        ShippingRequestService $shippingRequestService,
                        UniqueNumberService    $uniqueNumberService,
                        StatusHistoryService   $statusHistoryService): JsonResponse {
        $data = $request->request;
        $now = new \DateTime('now');

        $statusRepository = $entityManager->getRepository(Statut::class);
        $shippingRequest = new ShippingRequest();
        $shippingRequest
            ->setNumber($uniqueNumberService->create($entityManager, ShippingRequest::NUMBER_PREFIX, ShippingRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_TRANSPORT))
            ->setCreatedAt($now)
            ->setCreatedBy($this->getUser());

        $statusHistoryService->updateStatus(
            $entityManager,
            $shippingRequest,
            $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::SHIPMENT, ShippingRequest::STATUS_DRAFT),
            ['setStatus' => true, 'date' => $now],
        );

        $entityManager->persist($shippingRequest);

        $success = $shippingRequestService->updateShippingRequest($entityManager, $shippingRequest, $data);
        if($success){
            $entityManager->flush();
            return $this->json([
                'success' => true,
                'msg' => 'Votre demande d\'expédition a bien été créée.',
                'shippingRequestId' => $shippingRequest->getId(),
            ]);
        } else {
            throw new FormException();
        }
    }

    #[Route("/edit", name: "shipping_request_edit", options: ["expose" => true], methods: ['POST'], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::CREATE_SHIPPING], mode: HasPermission::IN_JSON)]
    public function edit(Request                $request,
                         EntityManagerInterface $entityManager,
                         ShippingRequestService $shippingRequestService): JsonResponse {
        $data = $request->request;
        $shippingRequestId = $request->get('shippingRequestId');
        if ($shippingRequestId) {
            $shippingRequestRepository = $entityManager->getRepository(ShippingRequest::class);
            $shippingRequest = $shippingRequestRepository->find($shippingRequestId);
        } else {
            throw new FormException('La demande d\'expédition n\'a pas été trouvée.');
        }

        $success = $shippingRequestService->updateShippingRequest($entityManager, $shippingRequest, $data);
        if($success){
            $entityManager->flush();
            return $this->json([
                'success' => true,
                'msg' => 'Votre demande d\'expédition a bien été enregistrée',
                'shippingRequestId' => $shippingRequest->getId(),
            ]);
        } else {
            throw new FormException();
        }
    }

    #[Route("/check_expected_lines_data/{id}", name: 'check_expected_lines_data', options: ["expose" => true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function checkExpectedLinesData(ShippingRequest $shippingRequest,): JsonResponse
    {

        $expectedLines = $shippingRequest->getExpectedLines();

        if ($expectedLines->count() <= 0) {
            return $this->json([
                'success' => false,
                'msg' => 'Veuillez ajouter au moins une référence.',
            ]);
        }

        /** @var ShippingRequestExpectedLine $expectedLine */
        foreach ($expectedLines as $expectedLine) {
            $referenceArticle = $expectedLine->getReferenceArticle();

            //FDS & codeOnu & classeProduct needed if it's dangerousGoods
            if ($referenceArticle->isDangerousGoods()
                && (!$referenceArticle->getSheet()
                    || !$referenceArticle->getOnuCode()
                    || !$referenceArticle->getProductClass())) {

                return $this->json([
                    'success' => false,
                    'msg' => "Des informations sont manquantes sur la référence " . $referenceArticle->getReference() . " afin de pouvoir effectuer la planification"
                ]);
            }

            //codeNdp needed if shipment is international
            if ($shippingRequest->getShipment() === ShippingRequest::SHIPMENT_INTERNATIONAL
                && !$referenceArticle->getNdpCode()) {

                return $this->json([
                    'success' => false,
                    'msg' => "Des informations sont manquantes sur la référence " . $referenceArticle->getReference() . " afin de pouvoir effectuer la planification"
                ]);
            }
        }

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route("/validateShippingRequest/{id}", name:'shipping_request_validation', options:["expose"=>true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function shippingRequestValidation(ShippingRequest        $shippingRequest,
                                              StatusHistoryService   $statusHistoryService,
                                              EntityManagerInterface $entityManager,
                                              TranslationService $translationService): JsonResponse
    {
        $currentUser = $this->getUser();

        // shippingRequest need at least 1 expectedLines (ref)
        if($shippingRequest->getExpectedLines()->count() <= 0){
            return $this->json([
                'success'=>false,
                'msg'=> 'Veuillez ajouter au moins une référence.',
            ]);
        }

        $newStatusForShippingRequest = $entityManager->getRepository(Statut::class)
                                                     ->findOneByCategorieNameAndStatutCode(
                                                         CategorieStatut::SHIPMENT,
                                                         ShippingRequest::STATUS_TO_TREAT
                                                     );

        $shippingRequest
            ->setValidatedAt(new \DateTime())
            ->setValidatedBy($currentUser)
        ;

        $statusHistoryService->updateStatus(
            $entityManager,
            $shippingRequest,
            $newStatusForShippingRequest,
            ['setStatus'=> true],
        );

        // Check that the status has been updated
        if($shippingRequest->getStatus() !== $newStatusForShippingRequest){
            return $this->json([
                'success'=>false,
                'msg'=> 'Une erreur est survenue lors du changement de statut.',
            ]);
        }

        $entityManager->flush();

        return $this->json([
            "success"=> true,
            'msg'=> 'La validation de votre ' . mb_strtolower($translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . ' a bien été prise en compte. ',
        ]);
    }

    #[Route("/get-transport-header-config/{id}", name: "get_transport_header_config", options: ["expose"=>true], methods: ['GET', 'POST'])]
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

    #[Route(["/form/{id}", "/form"], name: "shipping_request_form", options: ["expose" => true], methods: ['GET'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function getForm(?ShippingRequest $shippingRequest): Response {
        $shippingRequest = $shippingRequest ?? new ShippingRequest();

        return $this->json([
            'success' => true,
            'html' => $this->renderView('shipping_request/form.html.twig', [
                'shipping' => $shippingRequest,
            ]),
        ]);
    }

    #[Route("/shipped_shipping_request-shipping/{id}", name: "shipped_shipping_request", options: ["expose" => true])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function shippedShippingRequest(ShippingRequest         $shippingRequest,
                                           StatusHistoryService    $statusHistoryService,
                                           EntityManagerInterface  $entityManager,
                                           MouvementStockService   $mouvementStockService,
                                           TrackingMovementService $trackingMovementService): JsonResponse
    {
        $user = $this->getUser();
        $dateNow = new DateTime('now');

        //repository
        $statusRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        //location
        $shippingLocationFromId = $settingRepository->getOneParamByLabel(Setting::SHIPPING_LOCATION_FROM);
        $shippingLocationFrom = $emplacementRepository->findOneBy(['id'=>$shippingLocationFromId]);
        $shippingLocationToId = $settingRepository->getOneParamByLabel(Setting::SHIPPING_LOCATION_TO);
        $shippingLocationTo = $emplacementRepository->findOneBy(['id'=>$shippingLocationToId]);


        //status
        $newStatusForShippingRequest = $statusRepository->findOneByCategorieNameAndStatutCode(
            CategorieStatut::SHIPMENT,
            ShippingRequest::STATUS_SHIPPED);

        $consumeStatusForArticles = $statusRepository->findOneByCategorieNameAndStatutCode(
            CategorieStatut::ARTICLE,
            Article::STATUT_INACTIF
        );

        //block process if "emplacement d'expé pas rempli"
        if (!$shippingLocationFromId || !$shippingLocationToId) {
            return $this->json([
                'success' => false,
                'msg' => 'Veuillez remplir le paramètre "Emplacements par défaut.',
            ]);
        }

        $shippingRequest
            ->setTreatedAt(new \DateTime())
            ->setTreatedBy($user);

        // update status & create mvt stock / track
        if ($shippingRequest->getStatus()->getCode() === $shippingRequest::STATUS_SCHEDULED) {

            $statusHistoryService->updateStatus(
                $entityManager,
                $shippingRequest,
                $newStatusForShippingRequest,
                ['setStatus' => true]
            );

            /** @var ShippingRequestPack $packLines */
            foreach ($shippingRequest->getPackLines() as $packLines) {

                //mvt prise UL
                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $packLines->getPack(),
                    $packLines->getPack()->getLastDrop()->getEmplacement(),
                    $user,
                    $dateNow,
                    false,
                    false,
                    TrackingMovement::TYPE_PRISE,
                );
                $entityManager->persist($trackingMovement);

                //mvt depose UL
                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $packLines->getPack(),
                    $shippingLocationTo,
                    $user,
                    $dateNow,
                    false,
                    false,
                    TrackingMovement::TYPE_DEPOSE,
                );
                $entityManager->persist($trackingMovement);

                /** @var ShippingRequestLine $shippingRequestLine */
                foreach ($packLines->getLines() as $shippingRequestLine) {
                    $article = $shippingRequestLine->getArticle();

                    $article->setStatut($consumeStatusForArticles);

                    // mvt stock
                    $newMouvementStock = $mouvementStockService->createMouvementStock(
                        $user,
                        $shippingLocationFrom,
                        $shippingRequestLine->getQuantity(),
                        $article,
                        MouvementStock::TYPE_SORTIE,
                        [
                            "locationTo" => $shippingLocationTo,
                        ]);
                    $entityManager->persist($newMouvementStock);

                    //mvt prise article
                    $trackingMovement = $trackingMovementService->createTrackingMovement(
                        $article->getBarCode(),
                        $shippingLocationFrom,
                        $user,
                        $dateNow,
                        false,
                        false,
                        TrackingMovement::TYPE_PRISE,
                        [
                            'mouvementStock' => $newMouvementStock
                        ]
                    );
                    $entityManager->persist($trackingMovement);

                    // mvt depose article
                    $trackingMovement = $trackingMovementService->createTrackingMovement(
                        $article->getBarCode(),
                        $shippingLocationFrom,
                        $user,
                        $dateNow,
                        false,
                        false,
                        TrackingMovement::TYPE_DEPOSE,
                        [
                            'mouvementStock' => $newMouvementStock
                        ]
                    );
                    $entityManager->persist($trackingMovement);
                }
            }
            // Check that the status has been updated
            if ($shippingRequest->getStatus() !== $newStatusForShippingRequest) {
                return $this->json([
                    'success' => false,
                    'msg' => 'Une erreur est survenue lors du changement de statut.',
                ]);
            }
        }

        $entityManager->flush();

        return $this->json(
            [
                'success' => true,
                'msg' => "La demande d'expédition a été expédié."
            ]
        );
    }
}
