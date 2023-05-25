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
use App\Entity\Nature;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\ShippingRequest\ShippingRequestLine;
use App\Entity\ShippingRequest\ShippingRequestPack;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\MouvementStockService;
use App\Repository\MouvementStockRepository;
use App\Service\ShippingRequest\ShippingRequestService;
use App\Service\StatusHistoryService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Compute\Reference;
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
                         ShippingRequestService $shippingRequestService,
                         EntityManagerInterface $entityManager): Response {
        $natureRepository = $entityManager->getRepository(Nature::class);
        $packingPackNature = $natureRepository->findOneBy(['default' => true]) ?: $natureRepository->findOneBy([]);

        return $this->render('shipping_request/show.html.twig', [
            'shipping'=> $shippingRequest,
            'packingPackNature' => $packingPackNature,
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
            $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::SHIPPING_REQUEST, ShippingRequest::STATUS_DRAFT),
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
    public function checkExpectedLinesData(ShippingRequest          $shippingRequest,
                                           ShippingRequestService   $shippingRequestService): JsonResponse
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
            'expectedLines' => $shippingRequestService->formatExpectedLinesForPacking($expectedLines)
        ]);
    }

    #[Route("/delete-shipping-request/{id}", name: "delete_shipping_request", options: ["expose" => true], methods: ['DELETE'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function deleteShippingRequest(ShippingRequest         $shippingRequest,
                                          EntityManagerInterface  $entityManager,
                                          UserService             $userService,
                                          MouvementStockService   $mouvementStockService,
                                          TrackingMovementService $trackingMovementService): Response
    {

        $user = $this->getUser();
        $statusHistoryRepository = $entityManager->getRepository(StatusHistory::class);

        // status
        $isDraftOrTreat = $shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_TO_TREAT
            || $shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_TO_TREAT;
        $isScheduled = $shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_SCHEDULED;
        $isShipped = $shippingRequest->getStatus()->getCode() === ShippingRequest::STATUS_SHIPPED;

        // right
        $hasRightDeleteDraftOrTreat = ($userService->hasRightFunction(Menu::DEM, Action::DELETE_TO_TREAT_SHIPPING, $user)) ||
            ($userService->hasRightFunction(Menu::DEM, Action::DELETE, $user));
        $hasRightDeleteScheduled = $userService->hasRightFunction(Menu::DEM, Action::DELETE_PLANIFIED_SHIPPING, $user);
        $hasRightDeleteShipped = $userService->hasRightFunction(Menu::DEM, Action::DELETE_SHIPPED_SHIPPING);

        // remove status to treat only if user has right and shipping request is to treat
        if ($isDraftOrTreat) {
            if ($hasRightDeleteDraftOrTreat) {

                // remove 'ShippingRequesExpectedtLine'
                foreach ($shippingRequest->getExpectedLines() as $expectedLine) {
                    $shippingRequest->removeExpectedLine($expectedLine);
                    $entityManager->remove($expectedLine);
                }

                // remove status_history
                $statusHistoryToRemove = $statusHistoryRepository->findBy(['shippingRequest' => $shippingRequest->getId()]);
                foreach ($statusHistoryToRemove as $status) {
                    $entityManager->remove($status);
                }

                $entityManager->remove($shippingRequest);
            } else {
                return $this->json([
                    'success' => false,
                    'msg' => 'Vous n\'avez pas pas la permission pour supprimer cette expédition (' . ShippingRequest::STATUS_TO_TREAT . ')',
                ]);
            }
            $entityManager->flush();
            return $this->json([
                "success" => true,
            ]);
        }
        // remove status scheduled only if user has right and shipping request is scheduled
        else if ($isScheduled) {
            if ($hasRightDeleteScheduled) {
                //todo tache collisage + verif autre part les mouvements sont ajouté dans la shippingRequest

                throw new FormException("Vous ne pouvez pas supprimer une expédition qui n'est pas en brouillon.");

                // remove status_history
                $statusHistoryToRemove = $statusHistoryRepository->findBy(['shippingRequest' => $shippingRequest->getId()]);
                foreach ($statusHistoryToRemove as $status) {
                    $entityManager->remove($status);
                }

                /* @var ShippingRequestPack $packLine */
                foreach ($shippingRequest->getPackLines() as $packLine) {

                    $parentpack = $packLine->getPack();

                    /* @var ShippingRequestLine $requestLine */
                    foreach ($packLine->getLines() as $requestLine) {

                        $articleOrReference = $requestLine->getArticleOrReference();
                        if ($articleOrReference instanceof Article) {

                            // remove mvt track before deleting trackingPack todo : que article
                            foreach ($articleOrReference->getTrackingPack()->getTrackingMovements() as $trackingPackMovement) {
                                $entityManager->remove($trackingPackMovement);
                                $articleOrReference->getTrackingPack()->removeTrackingMovement($trackingPackMovement);
                            }

                            //remove mvt stock (article)
                            foreach ($articleOrReference->getMouvements() as $stockMovement) {
                                $mouvementStockService->manageMouvementStockPreRemove($stockMovement, $entityManager);
                                $entityManager->remove($stockMovement);
                                $articleOrReference->removeMouvement($stockMovement);
                            }

                        } else if ($articleOrReference instanceof ReferenceArticle) {

                            $newStock = $articleOrReference->getQuantiteStock() - $requestLine->getQuantity();
                            $articleOrReference->setQuantiteStock($newStock);

                            //create mvt sortie todo : demander adrien
                            $mouvementStockService->createMouvementStock(
                                $this->getUser(),
                                $articleOrReference->getEmplacement(),
                                $requestLine->getQuantity(),
                                $articleOrReference,
                                MouvementStock::TYPE_SORTIE,
                            );
                        }

                        if ($articleOrReference instanceof Article) {
                            $entityManager->remove($articleOrReference->getTrackingPack());
                            $entityManager->remove($articleOrReference); // remove trackingPack cascade
                            $articleOrReference->setTrackingPack(null);
                            $articleOrReference->setCurrentLogisticUnit(null);
                        }

                        $packLine->removeLine($requestLine);
                        $entityManager->remove($requestLine);
                        $requestLine->getExpectedLine()->removeLine($requestLine);
                    }

                    // remove mvt track (pack)
                    foreach ($parentpack->getTrackingMovements() as $trackingMovement) {
                        $entityManager->remove($trackingMovement);
                        $parentpack->removeTrackingMovement($trackingMovement);
                    }

                    $entityManager->remove($parentpack); // cascade remove article
                    $entityManager->remove($packLine);
                }

                // remove 'ShippingRequesExpectedtLine'
                foreach ($shippingRequest->getExpectedLines() as $expectedLine) {
                    $entityManager->remove($expectedLine);
                    $shippingRequest->removeExpectedLine($expectedLine);
                }

                if ($isShipped && $hasRightDeleteShipped) {
                    //todo del mvt stock & track?????
                }

                $entityManager->remove($shippingRequest);
            } else {
                return $this->json([
                    'success' => false,
                    'msg' => 'Vous n\'avez pas pas la permission pour supprimer cette expédition (' . ShippingRequest::STATUS_TO_TREAT . ')',
                ]);
            }
            $entityManager->flush();
            return $this->json([
                "success" => true,
            ]);
        }
        return $this->json([
            'success' => false,
            'Cette expédition ne peux pas être supprimer.'
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

        $newStatusForShippingRequest = $entityManager
            ->getRepository(Statut::class)
            ->findOneByCategorieNameAndStatutCode(
                CategorieStatut::SHIPPING_REQUEST,
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

    #[Route("/get-header-config/{id}", name: "shipping_request_header_config", options: ["expose"=>true], methods: ['GET'])]
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

    #[Route("/submit-packing/{id}", name: "shipping_request_submit_packing", options: ["expose" => true], methods: ['POST'])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_SHIPPING])]
    public function postSubmitPacking(ShippingRequest         $shippingRequest,
                                      Request                 $request,
                                      EntityManagerInterface  $entityManager,
                                      ShippingRequestService  $shippingRequestService,
                                      ArticleDataService      $articleDataService,
                                      TrackingMovementService $trackingMovementService,
                                      MouvementStockService   $stockMovementService,
                                      StatusHistoryService    $statusHistoryService): Response {
        $data = json_decode($request->getContent(), true);
        if (!count($data)) {
            throw new FormException("Une Erreur est survenue lors de la récupération des données.");
        }

        $now = new DateTime('now');
        $ShippingRequestExpectedLineRepository = $entityManager->getRepository(ShippingRequestExpectedLine::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $quantityByExpectedLine = [];

        $packLocationId = $settingRepository->getOneParamByLabel(Setting::SHIPPING_LOCATION_FROM);
        $packLocation = $locationRepository->find($packLocationId);

        if (!$packLocation) {
            throw new FormException("L'emplacement d'expédition par défaut n'est pas paramétré");
        }

        $generatedBarcode = [];

        Stream::from($data)
            ->each(function ($pack, $index) use (&$generatedBarcode, $stockMovementService, $trackingMovementService, $packLocation, $articleDataService, $now, $shippingRequestService, $shippingRequest, $entityManager, $ShippingRequestExpectedLineRepository, &$quantityByExpectedLine) {
                if (!count(($pack['lines'] ?? []))) {
                    throw new FormException('Une Erreur est survenue lors de la récupération des données.');
                }

                $shippingPack = $shippingRequestService->createShippingRequestPack($entityManager, $shippingRequest, $index + 1, $pack['size'], $packLocation, ['date' => $now]);
                $entityManager->persist($shippingPack);

                Stream::from($pack['lines'])
                    ->each(function ($line) use (&$generatedBarcode, $stockMovementService, $now, $trackingMovementService, $shippingPack, $packLocation, $entityManager, $articleDataService, $ShippingRequestExpectedLineRepository, &$quantityByExpectedLine) {
                        if (!isset($line['lineId']) || !isset($line['quantity'])) {
                            throw new FormException();
                        }
                        $expectedLineId = $line['lineId'];
                        $pickedQuantity = $line['quantity'];

                        $requestExpectedLine = $ShippingRequestExpectedLineRepository->find($expectedLineId);
                        if (!$requestExpectedLine) {
                            throw new FormException('Une Erreur est survenue lors de la récupération des données.');
                        }

                        $referenceArticle = $requestExpectedLine->getReferenceArticle();

                        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                            $referenceArticle->setQuantiteReservee($referenceArticle->getQuantiteReservee() + $pickedQuantity);
                            $referenceArticle->setQuantiteStock($referenceArticle->getQuantiteStock() + $pickedQuantity);
                        } else {
                            $article = $articleDataService->newArticle(
                                $entityManager,
                                [
                                    'statut' => Article::STATUT_EN_TRANSIT,
                                    'refArticle' => $referenceArticle,
                                    'emplacement' => $packLocation,
                                    'quantite' => $pickedQuantity,
                                    'prix' => $requestExpectedLine->getPrice(),
                                    'articleFournisseur' => $requestExpectedLine->getReferenceArticle()->getArticlesFournisseur()->first()->getId(),
                                    'currentLogisticUnit' => $shippingPack->getPack(),
                                ],
                                [
                                    'excludeBarcodes' => $generatedBarcode,
                                ]);
                            $generatedBarcode[] = $article->getBarCode();
                        }

                        $stockMovement = $stockMovementService->createMouvementStock(
                            $this->getUser(),
                            null,
                            isset($article) ? $article->getQuantite() : $pickedQuantity,
                            $article ?? $referenceArticle,
                            MouvementStock::TYPE_ENTREE,
                            [
                                'date' => $now,
                                'locationTo' => $packLocation
                            ]
                        );
                        $entityManager->persist($stockMovement);

                        $trackingMovementDrop = $trackingMovementService->createTrackingMovement(
                            ($article ?? $referenceArticle)->getBarCode(),
                            $packLocation,
                            $this->getUser(),
                            $now,
                            false,
                            true,
                            TrackingMovement::TYPE_DEPOSE,
                            [
                                'refOrArticle' => $article ?? $referenceArticle,
                                'mouvementStock' => $stockMovement,
                                'logisticUnitParent' => $shippingPack->getPack()
                            ]
                        );
                        $entityManager->persist($trackingMovementDrop);

                        if(isset($article)) {
                            $trackingMovement = $trackingMovementService->createTrackingMovement(
                                $trackingMovementDrop->getPack(),
                                $packLocation,
                                $this->getUser(),
                                $now,
                                false,
                                true,
                                TrackingMovement::TYPE_DROP_LU,
                                [
                                    'refOrArticle' => $article,
                                    'mouvementStock' => $stockMovement,
                                    'logisticUnitParent' => $shippingPack->getPack()
                                ]
                            );
                            $entityManager->persist($trackingMovement);

                        }

                        $requestLine = new ShippingRequestLine();
                        $requestLine
                            ->setQuantity($pickedQuantity)
                            ->setArticleOrReference($article ?? $referenceArticle)
                            ->setShippingPack($shippingPack)
                            ->setExpectedLine($requestExpectedLine);

                        $entityManager->persist($requestLine);
                        $quantityByExpectedLine[$expectedLineId] = ($quantityByExpectedLine[$expectedLineId] ?? 0) + $pickedQuantity;
                    });
            });

        Stream::from($shippingRequest->getExpectedLines())
            ->each(function (ShippingRequestExpectedLine $expectedLine) use ($quantityByExpectedLine) {
                $expectedLineId = $expectedLine->getId();
                if (($quantityByExpectedLine[$expectedLineId] ?? 0) !== $expectedLine->getQuantity()) {
                    throw new FormException('Une Erreur est survenue lors du traitement des données.');
                }
            });

        $statusHistoryService->updateStatus(
            $entityManager,
            $shippingRequest,
            $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::SHIPPING_REQUEST, ShippingRequest::STATUS_SCHEDULED),
            ['setStatus' => true, 'date' => $now],
        );

        $shippingRequest
            ->setPlannedBy($this->getUser())
            ->setPlannedAt($now);

        $entityManager->flush();

        return $this->json([
            'success' => false,
        ]);
    }
}
