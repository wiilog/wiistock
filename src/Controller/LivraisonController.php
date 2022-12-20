<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Exceptions\NegativeQuantityException;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\LivraisonService;
use App\Service\LivraisonsManagerService;
use App\Service\PDFGeneratorService;
use App\Service\PreparationsManagerService;
use App\Service\SpecificService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;
use WiiCommon\Helper\Stream;


/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController {

    /**
     * @Route("/liste/{demandId}", name="livraison_index", methods={"GET", "POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR})
     */
    public function index(EntityManagerInterface $entityManager,
                          string                 $demandId = null): Response {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);

        $filterDemand = $demandId
            ? $demandeRepository->find($demandId)
            : null;

        return $this->render('livraison/index.html.twig', [
            'filterDemandId' => isset($filterDemand) ? $demandId : null,
            'filterDemandValue' => isset($filterDemand) ? $filterDemand->getNumero() : null,
            'filtersDisabled' => isset($filterDemand),
            'displayDemandFilter' => true,
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ORDRE_LIVRAISON),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
        ]);
    }

    /**
     * @Route("/finir/{id}", name="livraison_finish", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function finish(Livraison                $livraison,
                           LivraisonsManagerService $livraisonsManager,
                           EntityManagerInterface   $entityManager): Response {
        if ($livraison->getStatut()?->getCode() === Livraison::STATUT_A_TRAITER) {
            try {
                $dateEnd = new DateTime('now');
                /** @var Utilisateur $user */
                $user = $this->getUser();
                $livraisonsManager->finishLivraison(
                    $user,
                    $livraison,
                    $dateEnd,
                    $livraison->getDemande()->getDestination(),
                    false,
                );
                $entityManager->flush();
            } catch (NegativeQuantityException $exception) {
                $barcode = $exception->getArticle()->getBarCode();
                return new JsonResponse([
                    'success' => false,
                    'message' => "La quantité en stock de l'article $barcode est inférieure à la quantité prélevée.",
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('livraison_show', [
                'id' => $livraison->getId(),
            ]),
        ]);
    }

    /**
     * @Route("/api", name="livraison_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function api(Request          $request,
                        LivraisonService $livraisonService): Response {
        $filterDemandId = $request->request->get('filterDemand');
        $data = $livraisonService->getDataForDatatable($request->request, $filterDemandId);
        return new JsonResponse($data);
    }

    #[Route("/delivery-order-logistic-units-api", name: "delivery_order_logistic_units_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR], mode: HasPermission::IN_JSON)]
    public function logisticUnitsApi(Request $request, EntityManagerInterface $manager): Response {
        $deliveryOrder = $manager->find(Livraison::class, $request->query->get('id'));
        $preparationOrder = $deliveryOrder->getPreparation();

        $lines = Stream::from($preparationOrder->getArticleLines())
            ->map(fn(PreparationOrderArticleLine $articleLine) => $articleLine->getPack())
            ->unique()
            // null packs in first
            ->sort(fn(?Pack $logisticUnit1, ?Pack $logisticUnit2) => ($logisticUnit1?->getCode() <=> $logisticUnit2?->getCode()))
            ->map(fn(?Pack $logisticUnit) => [
                "pack" => $logisticUnit
                    ? [
                        "packId" => $logisticUnit->getId(),
                        "code" => $logisticUnit->getCode(),
                        "location" => $this->formatService->location($logisticUnit->getLastDrop()?->getEmplacement()),
                        "project" => $logisticUnit->getProject()?->getCode(),
                        "nature" => $this->formatService->nature($logisticUnit->getNature()),
                        "color" => $logisticUnit?->getNature()->getColor(),
                        "currentQuantity" => Stream::from($preparationOrder->getArticleLines()
                            ->filter(fn(PreparationOrderArticleLine $line) => $line->getArticle()->getCurrentLogisticUnit() === $logisticUnit))
                            ->count(),
                        "totalQuantity" => $logisticUnit->getChildArticles()->count(),
                    ]
                    : null,
                "articles" => Stream::from($preparationOrder->getArticleLines())
                    ->filter(fn(PreparationOrderArticleLine $line) => $line->getPack()?->getId() === $logisticUnit?->getId())
                    ->map(function(PreparationOrderArticleLine $line) {
                        $article = $line->getArticle();
                        return [
                            "reference" => $article->getArticleFournisseur()->getReferenceArticle()->getReference(),
                            "barCode" => $article->getBarCode() ?: '',
                            "label" => $article->getLabel() ?: '',
                            "quantity" => $line->getPickedQuantity(),
                            "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                                'id' => $article->getId(),
                            ]),
                        ];
                    })
                    ->values(),
            ])
            ->values();

        $references = Stream::from($preparationOrder->getReferenceLines())
            ->map(function(PreparationOrderReferenceLine $line) {
                $reference = $line->getReference();
                return [
                    "reference" => $reference->getReference(),
                    "label" => $reference->getLibelle(),
                    "barCode" => $reference->getBarCode() ?: '',
                    "location" => $this->formatService->location($reference->getEmplacement()),
                    "quantity" => $line->getPickedQuantity(),
                    "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                        'refArticleId' => $reference->getId(),
                    ]),
                ];
            })
            ->toArray();

        if (!isset($lines[0]) || $lines[0]['pack'] !== null) {
            array_unshift($lines, [
                'pack' => null,
                'articles' => [],
            ]);
        }

        $lines[0]['articles'] = array_merge($lines[0]['articles'], $references);
        return $this->json([
            "success" => true,
            "html" => $this->renderView("livraison/line-list.html.twig", [
                "lines" => $lines,
            ]),
        ]);
    }

    /**
     * @Route("/api-article/{id}", name="livraison_article_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function apiArticle(Livraison $livraison): Response {
        $preparation = $livraison->getPreparation();
        $data = [];
        if ($preparation) {
            $rows = [];
            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($preparation->getArticleLines() as $articleLine) {
                $article = $articleLine->getArticle();
                if ($articleLine->getQuantityToPick() !== 0 && $articleLine->getPickedQuantity() !== 0 && !$article->getCurrentLogisticUnit()) {
                    $rows[] = [
                        "reference" => $article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                        "barCode" => $article->getBarCode() ?: '',
                        "label" => $article->getLabel() ?: '',
                        "location" => FormatHelper::location($article->getEmplacement()),
                        "quantity" => $articleLine->getPickedQuantity(),
                        "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                            'id' => $article->getId(),
                        ]),
                    ];
                }
            }

            /** @var PreparationOrderReferenceLine $referenceLine */
            foreach ($preparation->getReferenceLines() as $referenceLine) {
                if ($referenceLine->getPickedQuantity() > 0) {
                    $reference = $referenceLine->getReference();
                    $rows[] = [
                        "reference" => $reference->getReference(),
                        "label" => $reference->getLibelle(),
                        "barCode" => $reference->getBarCode() ?: '',
                        "location" => FormatHelper::location($reference->getEmplacement()),
                        "quantity" => $referenceLine->getPickedQuantity(),
                        "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                            'refArticleId' => $reference->getId(),
                        ]),
                    ];
                }
            }

            $data['data'] = $rows;
        }
        else {
            $data = false; //TODO gérer retour message erreur
        }
        return new JsonResponse($data);
    }

    /**
     * @Route("/voir/{id}", name="livraison_show", options={"expose"=true}, methods={"GET","POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR})
     */
    public function show(Livraison $livraison, LivraisonService $livraisonService): Response
    {
        $headerDetailsConfig = $livraisonService->createHeaderDetailsConfig($livraison);

        return $this->render('livraison/show.html.twig', [
            'livraison' => $livraison,
            'finished' => $livraison->isCompleted(),
            'headerConfig' => $headerDetailsConfig
        ]);
    }

    /**
     * @Route("/{livraison}", name="livraison_delete", options={"expose"=true}, methods={"DELETE"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request                    $request,
                           Livraison                  $livraison,
                           LivraisonsManagerService   $livraisonsManager,
                           PreparationsManagerService $preparationsManager,
                           EntityManagerInterface     $entityManager): Response {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $preparation = $livraison->getpreparation();

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $livraisonStatus = $livraison->getStatut();
        $demande = $livraison->getDemande();

        $articleDestinationId = $request->request->get('dropLocation');
        $articlesDestination = !empty($articleDestinationId) ? $emplacementRepository->find($articleDestinationId) : null;
        if (empty($articlesDestination)) {
            $articlesDestination = isset($demande) ? $demande->getDestination() : null;
        }

        if (isset($livraisonStatus)
            && isset($articlesDestination)) {
            $livraisonsManager->resetStockMovementsOnDelete(
                $livraison,
                $articlesDestination,
                $user,
                $entityManager
            );
        }

        $preparationsManager->resetPreparationToTreat($preparation, $entityManager);

        $entityManager->flush();

        $preparation->setLivraison(null);
        $entityManager->remove($livraison);
        $entityManager->flush();

        return new JsonResponse ([
            'success' => true,
            'redirect' => $this->generateUrl('preparation_show', [
                'id' => $preparation->getId(),
            ]),
        ]);
    }

    /**
     * @Route("/csv", name="get_delivery_order_csv", options={"expose"=true}, methods={"GET"})
     */
    public function getDeliveryOrderCSV(Request                $request,
                                        CSVExportService       $CSVExportService,
                                        EntityManagerInterface $entityManager,
                                        LivraisonService       $livraisonService): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }
        if (isset($dateTimeMin) && isset($dateTimeMax)) {

            $csvHeader = [
                'numéro',
                'statut',
                'date création',
                'date de livraison',
                'date de la demande',
                'demandeur',
                'opérateur',
                'type',
                'commentaire',
                'référence',
                'libellé',
                'emplacement',
                'quantité à livrer',
                'quantité en stock',
                'code-barre',
            ];

            return $CSVExportService->streamResponse(
                function($output) use ($entityManager, $dateTimeMin, $dateTimeMax, $CSVExportService, $livraisonService) {
                    $livraisonRepository = $entityManager->getRepository(Livraison::class);
                    $deliveryIterator = $livraisonRepository->iterateByDates($dateTimeMin, $dateTimeMax);

                    foreach ($deliveryIterator as $delivery) {
                        $livraisonService->putLivraisonLine($output, $CSVExportService, $delivery);
                    }
                },
                'export_Ordres_Livraison.csv',
                $csvHeader
            );
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/{deliveryOrder}/api-delivery-note", name="api_delivery_note_livraison", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param Livraison $deliveryOrder
     * @return JsonResponse
     */
    public function apiDeliveryNote(Request $request,
                                    EntityManagerInterface $manager,
                                    Livraison $deliveryOrder): JsonResponse {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        $maxNumberOfPacks = 10;

        $settingRepository = $manager->getRepository(Setting::class);

        $preparationArticleLines = $deliveryOrder->getPreparation()->getArticleLines();
        $deliveryOrderHasPacks = Stream::from($preparationArticleLines)
            ->some(fn(PreparationOrderArticleLine $line) => $line->getPack());

        if(!$deliveryOrderHasPacks) {
            throw new FormException('Des unités logistiques sont nécessaires pour générer un bon de livraison');
        }

        $packs = Stream::from($preparationArticleLines)
            ->filterMap(fn(PreparationOrderArticleLine $line) => $line->getPack())
            ->reindex()
            ->slice(0, $maxNumberOfPacks)
            ->map(fn(Pack $pack) => [
                "code" => $pack->getCode(),
                "quantity" => $pack->getQuantity(),
                "comment" => $pack->getComment(),
            ])
            ->values();

        $userSavedData = $loggedUser->getSavedDeliveryOrderDeliveryNoteData();
        $dispatchSavedData = $deliveryOrder->getDeliveryNoteData();
        $defaultData = [
            'deliveryNumber' => $deliveryOrder->getNumero(),
            'username' => $loggedUser->getUsername(),
            'packs' => $packs,
            'consignor' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONSIGNER),
            'consignor2' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONSIGNER),
            'userPhone' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL),
        ];

        $deliveryNoteData = array_reduce(
            array_keys(Livraison::DELIVERY_NOTE_DATA),
            function(array $carry, string $dataKey) use ($request, $userSavedData, $dispatchSavedData, $defaultData) {
                $carry[$dataKey] = (
                    $dispatchSavedData[$dataKey]
                    ?? ($userSavedData[$dataKey]
                        ?? ($defaultData[$dataKey]
                            ?? null))
                );

                return $carry;
            },
            []
        );

        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);

        $html = $this->renderView('dispatch/modalPrintDeliveryNoteContent.html.twig', array_merge($deliveryNoteData, [
            'dispatchEmergencyValues' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_EMERGENCY),
            'fromDelivery' => $request->query->getBoolean('fromDelivery'),
        ]));

        return $this->json([
            "success" => true,
            "html" => $html
        ]);
    }

    /**
     * @Route("/{deliveryOrder}/delivery-note", name="delivery_note_delivery_order", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param EntityManagerInterface $entityManager
     * @param Livraison $deliveryOrder
     * @param PDFGeneratorService $pdf
     * @param Request $request
     * @return JsonResponse
     */
    public function postDeliveryNoteDeliveryOrder(EntityManagerInterface $entityManager,
                                     Livraison $deliveryOrder,
                                     PDFGeneratorService $pdf,
                                     Request $request,
                                     SpecificService $specificService,
                                     LivraisonService $livraisonService): JsonResponse {

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $userDataToSave = [];
        $dispatchDataToSave = [];

        // force dispatch number
        $data['deliveryNumber'] = $deliveryOrder->getNumero();

        foreach(array_keys(Livraison::DELIVERY_NOTE_DATA) as $deliveryNoteKey) {
            if(isset(Livraison::DELIVERY_NOTE_DATA[$deliveryNoteKey])) {
                $value = $data[$deliveryNoteKey] ?? null;
                $dispatchDataToSave[$deliveryNoteKey] = $value;
                if(Livraison::DELIVERY_NOTE_DATA[$deliveryNoteKey]) {
                    $userDataToSave[$deliveryNoteKey] = $value;
                }
            }
        }

        $loggedUser->setSavedDeliveryOrderDeliveryNoteData($userDataToSave);
        $deliveryOrder->setDeliveryNoteData($dispatchDataToSave);

        $entityManager->flush();

        $settingRepository = $entityManager->getRepository(Setting::class);
        $logo = $settingRepository->getOneParamByLabel(Setting::DELIVERY_NOTE_LOGO);

        $nowDate = new DateTime('now');
        $client = SpecificService::CLIENTS[$specificService->getAppClient()];

        $documentTitle = "BL - {$deliveryOrder->getNumero()} - {$client} - {$nowDate->format('dmYHis')}";

        $fileName = $pdf->generatePDFDeliveryNote($documentTitle, $logo, $deliveryOrder);

        $deliveryNoteAttachment = new Attachment();
        $deliveryNoteAttachment
            ->setDeliveryOrder($deliveryOrder)
            ->setFileName($fileName)
            ->setOriginalName($documentTitle . '.pdf');

        $entityManager->persist($deliveryNoteAttachment);

        $entityManager->flush();

        $detailsConfig = $livraisonService->createHeaderDetailsConfig($deliveryOrder);

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le téléchargement de votre bon de livraison va commencer...',
            'headerDetailsConfig' => $this->renderView("livraison/livraison-show-header.html.twig", [
                'livraison' => $deliveryOrder,
                'showDetails' => $detailsConfig,
                'finished' => $deliveryOrder->isCompleted(),
            ]),
            'attachmentId' => $deliveryNoteAttachment->getId()
        ]);

    }

    /**
     * @Route("/{deliveryOrder}/delivery-note/{attachment}", name="print_delivery_note_delivery_order", options={"expose"=true}, methods="GET")
     * @param Livraison $deliveryOrder
     * @param KernelInterface $kernel
     * @param Attachment $attachment
     * @return PdfResponse
     */
    public function printDeliveryNote(Livraison $deliveryOrder,
                                      KernelInterface $kernel,
                                      Attachment $attachment): Response {
        if(!$deliveryOrder->getDeliveryNoteData()) {
            return $this->json([
                "success" => false,
                "msg" => 'Le bon de livraison n\'existe pas pour cette ordre de livraison'
            ]);
        }

        $response = new BinaryFileResponse(($kernel->getProjectDir() . '/public/uploads/attachements/' . $attachment->getFileName()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName());

        return $response;
    }

    /**
     * @Route("/{deliveryOrder}/check-waybill", name="check_delivery_waybill", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param Livraison $deliveryOrder
     * @return JsonResponse
     */
    public function checkDeliveryWaybill(Livraison $deliveryOrder) {
        $preparationArticleLines = $deliveryOrder->getPreparation()->getArticleLines();
        $deliveryOrderHasPacks = Stream::from($preparationArticleLines)
            ->some(fn(PreparationOrderArticleLine $articleLine) => $articleLine->getArticle()->getCurrentLogisticUnit());

        if(!$deliveryOrderHasPacks) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Des unités logistiques sont nécessaires pour générer une lettre de voiture.'
            ]);
        } else {
            return new JsonResponse([
                "success" => true,
            ]);
        }
    }

    /**
     * @Route("/{deliveryOrder}/api-waybill", name="api_delivery_waybill", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function apiDeliveryWaybill(Request $request,
                               EntityManagerInterface $entityManager,
                               Livraison $deliveryOrder): JsonResponse {

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $settingRepository = $entityManager->getRepository(Setting::class);

        $userSavedData = $loggedUser->getSavedDeliveryWaybillData();
        $wayBillSavedData = $deliveryOrder->getWaybillData();

        $now = new DateTime('now');

        $consignorUsername = $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_NAME);
        $consignorUsername = $consignorUsername !== null && $consignorUsername !== ''
            ? $consignorUsername
            : null;

        $consignorEmail = $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL);
        $consignorEmail = $consignorEmail !== null && $consignorEmail !== ''
            ? $consignorEmail
            :  null;

        $defaultData = [
            'carrier' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CARRIER),
            'dispatchDate' => $now->format('Y-m-d'),
            'consignor' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONSIGNER),
            'receiver' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_RECEIVER),
            'locationFrom' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_LOCATION_FROM),
            'locationTo' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_LOCATION_TO),
            'consignorUsername' => $consignorUsername,
            'consignorEmail' => $consignorEmail,
            'receiverUsername' => null,
            'receiverEmail' => null
        ];

        $wayBillData = array_reduce(
            array_keys(Livraison::WAYBILL_DATA),
            function(array $carry, string $dataKey) use ($request, $userSavedData, $wayBillSavedData, $defaultData) {
                $carry[$dataKey] = (
                    $wayBillSavedData[$dataKey]
                    ?? ($userSavedData[$dataKey]
                        ?? ($defaultData[$dataKey]
                            ?? null))
                );

                return $carry;
            },
            []
        );

        $html = $this->renderView('dispatch/modalPrintWayBillContent.html.twig', array_merge($wayBillData, [
            'packsCounter' => Stream::from($deliveryOrder->getPreparation()->getArticleLines())
                ->some(fn(PreparationOrderArticleLine $articleLine) => $articleLine->getArticle()->getCurrentLogisticUnit())
        ]));

        return $this->json([
            "success" => true,
            "html" => $html
        ]);
    }

    /**
     * @Route("/{deliveryOrder}/waybill", name="post_delivery_waybill", options={"expose"=true}, condition="request.isXmlHttpRequest()", methods="POST")
     */
    public function postDispatchWaybill(EntityManagerInterface $entityManager,
                                        Livraison $deliveryOrder,
                                        PDFGeneratorService $pdf,
                                        LivraisonService $livraisonService,
                                        Request $request,
                                        TranslationService $translationService,
                                        SpecificService $specificService): JsonResponse {
        $preparationArticleLines = $deliveryOrder->getPreparation()->getArticleLines();
        $deliveryOrderHasPacks = Stream::from($preparationArticleLines)
            ->some(fn(PreparationOrderArticleLine $articleLine) => $articleLine->getArticle()->getCurrentLogisticUnit());

        if($deliveryOrderHasPacks > LivraisonService::WAYBILL_MAX_PACK) {
            $message = $translationService->translate('Demande', 'Acheminements', 'Général', "Attention : L'acheminement contient plus de {1} unités logistiques, cette lettre de voiture ne peut contenir plus de {1} lignes.", [
                1 => LivraisonService::WAYBILL_MAX_PACK
            ]);
            $success = false;
        } else {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

            $data = json_decode($request->getContent(), true);

            $userDataToSave = [];
            $deliveryDataToSave = [];
            foreach(array_keys(Livraison::WAYBILL_DATA) as $wayBillKey) {
                if(isset(Livraison::WAYBILL_DATA[$wayBillKey])) {
                    $value = $data[$wayBillKey] ?? null;
                    $deliveryDataToSave[$wayBillKey] = $value;
                    if(Livraison::WAYBILL_DATA[$wayBillKey]) {
                        $userDataToSave[$wayBillKey] = $value;
                    }
                }
            }
            $loggedUser->setSavedDeliveryWaybillData($userDataToSave);
            $deliveryOrder->setWaybillData($deliveryDataToSave);

            $entityManager->flush();

            $message = 'Le téléchargement de votre lettre de voiture va commencer...';
            $success = true;
        }

        $settingRepository = $entityManager->getRepository(Setting::class);
        $logo = $settingRepository->getOneParamByLabel(Setting::FILE_WAYBILL_LOGO);

        $nowDate = new DateTime('now');

        $client = SpecificService::CLIENTS[$specificService->getAppClient()];
        $title = "LDV - {$deliveryOrder->getNumero()} - {$client} - {$nowDate->format('dmYHis')}";

        $packsQuantity = Stream::from($deliveryOrder->getPreparation()->getArticleLines())
            ->filter()
            ->reduce(function(array $carry, PreparationOrderArticleLine $articleLine) {
                if(isset($carry[$articleLine->getArticle()->getCurrentLogisticUnit()->getId()])){
                    $carry[$articleLine->getArticle()->getCurrentLogisticUnit()->getId()] += $articleLine->getPickedQuantity();
                } else {
                    $carry[$articleLine->getArticle()->getCurrentLogisticUnit()->getId()] = $articleLine->getPickedQuantity();

                }
                return $carry;
            }, []) ;


        $packs = Stream::from($deliveryOrder->getPreparation()->getArticleLines())
            ->map(fn(PreparationOrderArticleLine $articleLine) => $articleLine->getArticle()->getCurrentLogisticUnit())
            ->unique()
            ->map(fn(Pack $pack) => [
                'quantity' => $packsQuantity[$pack->getId()],
                'code' => $pack->getCode(),
                'weight' => $pack->getWeight(),
                'volume' => $pack->getVolume(),
                'comment' => $pack->getComment(),
                'nature' => $this->getFormatter()->nature($pack->getNature(), "", $loggedUser)
            ])->toArray();

        $fileName = $pdf->generatePDFWaybill($title, $logo, $deliveryOrder, $packs);

        $wayBillAttachment = new Attachment();
        $wayBillAttachment
            ->setDeliveryOrder($deliveryOrder)
            ->setFileName($fileName)
            ->setOriginalName($title . '.pdf');

        $entityManager->persist($wayBillAttachment);

        $entityManager->flush();

        $detailsConfig =  $livraisonService->createHeaderDetailsConfig($deliveryOrder);

        return new JsonResponse([
            'success' => $success,
            'msg' => $message,
            'headerDetailsConfig' => $this->renderView("livraison/livraison-show-header.html.twig", [
                'livraison' => $deliveryOrder,
                'showDetails' => $detailsConfig,
                'finished' => $deliveryOrder->isCompleted(),
            ]),
            'attachmentId' => $wayBillAttachment->getId()
        ]);
    }

    /**
     * @Route("/{deliveryOrder}/waybill/{attachment}", name="print_waybill_delivery", options={"expose"=true}, methods="GET")
     */
    public function printWaybillNote(Livraison $deliveryOrder,
                                     Attachment $attachment,
                                     TranslationService $translationService,
                                     KernelInterface $kernel): Response {
        if(!$deliveryOrder->getWaybillData()) {
            return $this->json([
                "success" => false,
                "msg" => $translationService->translate('Demande', 'Acheminements', 'Lettre de voiture', 'La lettre de voiture n\'existe pas pour cet acheminement', false),
            ]);
        }

        $response = new BinaryFileResponse(($kernel->getProjectDir() . '/public/uploads/attachements/' . $attachment->getFileName()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName());

        return $response;
    }
}
