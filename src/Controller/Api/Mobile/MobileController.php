<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Attachment;
use App\Entity\CategoryType;
use App\Entity\Chauffeur;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fournisseur;
use App\Entity\FreeField\FreeFieldManagementRule;
use App\Entity\Handling;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Project;
use App\Entity\ReferenceArticle;
use App\Entity\ReserveType;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Repository\Tracking\TrackingMovementRepository;
use App\Service\DispatchService;
use App\Service\MobileApiService;
use App\Service\PreparationsManagerService;
use App\Service\SessionHistoryRecordService;
use App\Service\SettingsService;
use App\Service\Tracking\TrackingMovementService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class MobileController extends AbstractController {

    #[Route("/api-key", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function postApiKey(Request                     $request,
                               EntityManagerInterface      $entityManager,
                               MobileApiService            $mobileApiService,
                               UserService                 $userService,
                               SessionHistoryRecordService $sessionHistoryRecordService,
                               DispatchService             $dispatchService): JsonResponse {
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $mobileKey = $request->request->get('loginKey');

        $loggedUser = $utilisateurRepository->findOneBy(['mobileLoginKey' => $mobileKey, 'status' => true]);
        $data = [];

        if (!empty($loggedUser)) {
            if ($userService->hasRightFunction(Menu::NOMADE, Action::ACCESS_NOMADE_LOGIN, $loggedUser)) {
                $sessionHistoryRecordService->closeInactiveSessions($entityManager);
                if ($sessionHistoryRecordService->isLoginPossible($entityManager, $loggedUser)) {
                    $sessionType = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::SESSION_HISTORY, Type::LABEL_NOMADE_SESSION_HISTORY);
                    $apiKey = $this->apiKeyGenerator();
                    $sessionHistoryRecordService->closeOpenedSessionsByUserAndType($entityManager, $loggedUser, $sessionType);
                    $entityManager->flush();
                    $sessionHistoryRecordService->newSessionHistoryRecord($entityManager, $loggedUser, new DateTime('now'), $sessionType, $apiKey);
                    $entityManager->flush();

                    $rights = $userService->getMobileRights($loggedUser);
                    $parameters = $mobileApiService->getMobileParameters($entityManager);

                    $channels = Stream::from($rights)
                        ->filter(static fn($val, $key) => $val && in_array($key, ["handling", "collectOrder", "transferOrder", "dispatch", "preparation", "deliveryOrder", "group", "ungroup", "notifications"]))
                        ->takeKeys()
                        ->flatMap(static function(string $right) use ($loggedUser) {
                            return match ($right) {
                                "preparation" => Stream::from($loggedUser->getDeliveryTypes())
                                    ->map(static fn(Type $deliveryType) => "stock-preparation-order-{$deliveryType->getId()}")
                                    ->toArray(),
                                "deliveryOrder" => Stream::from($loggedUser->getDeliveryTypes())
                                    ->map(static fn(Type $deliveryType) => "stock-delivery-order-{$deliveryType->getId()}")
                                    ->toArray(),
                                "dispatch" => Stream::from($loggedUser->getDispatchTypes())
                                    ->map(static fn(Type $dispatchType) => "tracking-dispatch-{$dispatchType->getId()}")
                                    ->toArray(),
                                "handling" => Stream::from($loggedUser->getHandlingTypes())
                                    ->map(static fn(Type $handlingType) => "request-handling-{$handlingType->getId()}")
                                    ->toArray(),
                                "collectOrder" => ["stock-collect-order"],
                                "transferOrder" => ["stock-transfer-order"],
                                default => [$right],
                            };
                        })
                        ->map(static fn(string $channel) => "{$_SERVER["APP_INSTANCE"]}-$channel")
                        ->values();

                    $channels[] = "{$_SERVER["APP_INSTANCE"]}-{$userService->getUserFCMChannel($loggedUser)}";

                    $fieldsParam = Stream::from([FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::ENTITY_CODE_DEMANDE])
                        ->keymap(fn(string $entityCode) => [$entityCode, $fieldsParamRepository->getByEntity($entityCode)])
                        ->toArray();

                    $wayBillData = $dispatchService->getWayBillDataForUser($loggedUser, $entityManager);
                    $wayBillData['dispatch_id'] = null;

                    $data['success'] = true;
                    $data['data'] = [
                        'apiKey' => $apiKey,
                        'notificationChannels' => $channels,
                        'rights' => $rights,
                        'parameters' => $parameters,
                        'username' => $loggedUser->getUsername(),
                        'userId' => $loggedUser->getId(),
                        'fieldsParam' => $fieldsParam ?? [],
                        'dispatchDefaultWaybill' => $wayBillData ?? [],
                        'appContext' => $_SERVER['APP_CONTEXT'],
                    ];
                } else {
                    $data = [
                        'success' => false,
                        'msg' => "Le nombre de licences utilisées sur cette instance a déjà été atteint."
                    ];
                }
            } else {
                $data = [
                    'success' => false,
                    'msg' => "Cet utilisateur ne dispose pas des droits pour accéder à l'application mobile."
                ];
            }
        } else {
            $data['success'] = false;
        }

        return new JsonResponse($data);
    }

    #[Route("/logout", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function logout(EntityManagerInterface      $entityManager,
                           SessionHistoryRecordService $sessionHistoryRecordService): JsonResponse {
        $typeRepository = $entityManager->getRepository(Type::class);
        $nomadUser = $this->getUser();
        $mobileSessionType = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::SESSION_HISTORY, Type::LABEL_NOMADE_SESSION_HISTORY);
        $sessionHistoryRecordService->closeOpenedSessionsByUserAndType($entityManager, $nomadUser, $mobileSessionType);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
        ]);
    }

    private function getDataArray(Utilisateur                $user,
                                  UserService                $userService,
                                  MobileApiService           $mobileApiService,
                                  PreparationsManagerService $preparationsManager,
                                  TrackingMovementService    $trackingMovementService,
                                  Request                    $request,
                                  SettingsService            $settingsService,
                                  EntityManagerInterface     $entityManager,
                                  KernelInterface            $kernel): array {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
        $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $freeFieldManagementRuleRepository = $entityManager->getRepository(FreeFieldManagementRule::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $attachmentRepository = $entityManager->getRepository(Attachment::class);
        $transferOrderRepository = $entityManager->getRepository(TransferOrder::class);
        $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
        $inventoryLocationMissionRepository = $entityManager->getRepository(InventoryLocationMission::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $projectRepository = $entityManager->getRepository(Project::class);
        $fixedFieldStandardRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $driverRepository = $entityManager->getRepository(Chauffeur::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $reserveTypeRepository = $entityManager->getRepository(ReserveType::class);

        $rights = $userService->getMobileRights($user);
        $parameters = $mobileApiService->getMobileParameters($entityManager);

        $status = $statutRepository->getMobileStatus($rights['dispatch'], $rights['handling']);

        $fieldsParamStandard = Stream::from([FixedFieldStandard::ENTITY_CODE_DEMANDE, FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL])
            ->keymap(fn(string $entityCode) => [$entityCode, $fixedFieldStandardRepository->getByEntity($entityCode)])
            ->toArray();

        $fieldsParamByType = Stream::from([FixedFieldStandard::ENTITY_CODE_DISPATCH])
            ->keymap(fn(string $entityCode) => [$entityCode, $fixedFieldByTypeRepository->getByEntity($entityCode)])
            ->toArray();

        $fieldsParam = array_merge($fieldsParamStandard, $fieldsParamByType);

        $userAllowedTypeIds = Stream::from([$user->getDeliveryTypeIds(), $user->getDispatchTypeIds(), $user->getHandlingTypeIds()])
            ->flatten()
            ->toArray();

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE,CategoryType::DEMANDE_COLLECTE]);

        $typesInUser = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING, CategoryType::DEMANDE_DISPATCH, CategoryType::DEMANDE_LIVRAISON], null, [
            'idsToFind' => $userAllowedTypeIds,
        ]);

        $mobileTypes = Stream::from(array_merge($types, $typesInUser))
            ->map(fn(Type $type) => [
                'id' => $type->getId(),
                'label' => $type->getLabel(),
                'category' => $type->getCategory()->getLabel(),
                'suggestedDropLocations' => implode(',', $type->getSuggestedDropLocations() ?? []),
                'suggestedPickLocations' => implode(',', $type->getSuggestedPickLocations() ?? []),
                'reusableStatuses' => $type->hasReusableStatuses(),
                'active' => $type->isActive(),
            ])
            ->toArray();

        if ($rights['inventoryManager']) {
            $anomalies = $inventoryEntryRepository->getAnomalies(true);
        }

        // livraisons
        $deliveriesExpectedDateColors = [
            'after' => $settingsService->getValue($entityManager, Setting::DELIVERY_EXPECTED_DATE_COLOR_AFTER),
            'DDay' => $settingsService->getValue($entityManager, Setting::DELIVERY_EXPECTED_DATE_COLOR_D_DAY),
            'before' => $settingsService->getValue($entityManager, Setting::DELIVERY_EXPECTED_DATE_COLOR_BEFORE),
        ];
        if ($rights['deliveryOrder']) {
            $livraisons = Stream::from($livraisonRepository->getMobileDelivery($user))
                ->map(function ($deliveryArray) use ($deliveriesExpectedDateColors, $mobileApiService) {
                    $deliveryArray['color'] = $mobileApiService->expectedDateColor($deliveryArray['expectedAt'], $deliveriesExpectedDateColors);
                    $deliveryArray['expectedAt'] = $deliveryArray['expectedAt']
                        ? $deliveryArray['expectedAt']->format('d/m/Y')
                        : null;
                    if (!empty($deliveryArray['comment'])) {
                        $deliveryArray['comment'] = substr(strip_tags($deliveryArray['comment']), 0, 200);
                    }
                    return $deliveryArray;
                })
                ->toArray();

            $livraisonsIds = Stream::from($livraisons)
                ->map(function ($livraisonArray) {
                    return $livraisonArray['id'];
                })
                ->toArray();

            $articlesLivraison = $articleRepository->getByLivraisonsIds($livraisonsIds);
            $refArticlesLivraison = $referenceArticleRepository->getByLivraisonsIds($livraisonsIds);
        }

        if($rights['preparation']) {
            /// preparations
            $preparations = Stream::from($preparationRepository->getMobilePreparations($user))
                ->map(function ($preparationArray) use ($deliveriesExpectedDateColors, $mobileApiService) {
                    $preparationArray['color'] = $mobileApiService->expectedDateColor($preparationArray['expectedAt'], $deliveriesExpectedDateColors);
                    $preparationArray['expectedAt'] = $preparationArray['expectedAt']
                        ? $preparationArray['expectedAt']->format('d/m/Y')
                        : null;
                    if (!empty($preparationArray['comment'])) {
                        $preparationArray['comment'] = substr(strip_tags($preparationArray['comment']), 0, 200);
                    }
                    return $preparationArray;
                })
                ->toArray();

            $displayPickingLocation = $settingsService->getValue($entityManager, Setting::DISPLAY_PICKING_LOCATION);
            // get article linked to a ReferenceArticle where type_quantite === 'article'
            $articlesPrepaByRefArticle = $articleRepository->getArticlePrepaForPickingByUser($user, [], $displayPickingLocation);

            $articlesPrepa = $preparationsManager->getArticlesPrepaArrays($entityManager, $preparations);
        }

        if($rights['collectOrder']) {
            /// collecte
            $collectes = $ordreCollecteRepository->getMobileCollecte($user);

            /// On tronque le commentaire à 200 caractères (sans les tags)
            $collectes = array_map(function ($collecteArray) {
                if (!empty($collecteArray['comment'])) {
                    $collecteArray['comment'] = substr(strip_tags($collecteArray['comment']), 0, 200);
                }
                return $collecteArray;
            }, $collectes);

            $suppliers = $supplierRepository->getForNomade();
            $refs = $referenceArticleRepository->getForNomade();

            $collectesIds = Stream::from($collectes)
                ->map(function ($collecteArray) {
                    return $collecteArray['id'];
                })
                ->toArray();
            $articlesCollecte = $articleRepository->getByOrdreCollectesIds($collectesIds);
            $refArticlesCollecte = $referenceArticleRepository->getByOrdreCollectesIds($collectesIds);
        }

        if($rights['transferOrder']) {
            /// transferOrder
            $transferOrders = $transferOrderRepository->getMobileTransferOrders($user);
            $transferOrdersIds = Stream::from($transferOrders)
                ->map(function ($transferOrder) {
                    return $transferOrder['id'];
                })
                ->toArray();
            $transferOrderArticles = array_merge(
                $articleRepository->getByTransferOrders($transferOrdersIds),
                $referenceArticleRepository->getByTransferOrders($transferOrdersIds)
            );
        }

        if($rights['inventory']){
            // inventory
            $inventoryItems = array_merge(
                $inventoryMissionRepository->getInventoriableArticles(),
                $inventoryMissionRepository->getInventoriableReferences()
            );

            $inventoryMissions = $inventoryMissionRepository->getInventoryMissions();
            $inventoryLocationsZone = $inventoryLocationMissionRepository->getInventoryLocationZones();
            // prises en cours
            $stockTaking = $trackingMovementService->getMobileUserPicking($entityManager, $user, TrackingMovementRepository::MOUVEMENT_TRACA_STOCK);
        }

        $projects = Stream::from($projectRepository->findAll())
            ->map(fn(Project $project) => [
                'id' => $project->getId(),
                'code' => $project->getCode(),
            ])
            ->toArray();

        if ($rights['handling']) {
            $handlingExpectedDateColors = [
                'after' => $settingsService->getValue($entityManager, Setting::HANDLING_EXPECTED_DATE_COLOR_AFTER),
                'DDay' => $settingsService->getValue($entityManager, Setting::HANDLING_EXPECTED_DATE_COLOR_D_DAY),
                'before' => $settingsService->getValue($entityManager, Setting::HANDLING_EXPECTED_DATE_COLOR_BEFORE),
            ];

            $handlings = $handlingRepository->getMobileHandlingsByUserTypes($user->getHandlingTypeIds());
            $removeHoursDesiredDate = $settingsService->getValue($entityManager, Setting::REMOVE_HOURS_DATETIME);
            $handlings = Stream::from($handlings)
                ->map(function (array $handling) use ($handlingExpectedDateColors, $removeHoursDesiredDate, $mobileApiService) {
                    $handling['color'] = $mobileApiService->expectedDateColor($handling['desiredDate'], $handlingExpectedDateColors);
                    $handling['desiredDate'] = $handling['desiredDate']
                        ? $handling['desiredDate']->format($removeHoursDesiredDate
                            ? 'd/m/Y'
                            : 'd/m/Y H:i:s')
                        : null;
                    $handling['comment'] = $handling['comment'] ? strip_tags($handling['comment']) : null;
                    return $handling;
                })->toArray();

            $handlingIds = array_map(function (array $handling) {
                return $handling['id'];
            }, $handlings);
            $handlingAttachments = array_map(
                function (array $attachment) use ($request) {
                    return [
                        'handlingId' => $attachment['handlingId'],
                        'fileName' => $attachment['originalName'],
                        'href' => $request->getSchemeAndHttpHost() . '/uploads/attachments/' . $attachment['fileName'],
                    ];
                },
                $attachmentRepository->getMobileAttachmentForHandling($handlingIds)
            );

            $requestFreeFieldManagementRules = $freeFieldManagementRuleRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_HANDLING]);
        }

        if($rights['deliveryRequest']){
            $demandeLivraisonArticles = $referenceArticleRepository->getByNeedsMobileSync();
            $deliveryFreeFieldManagementRules = $freeFieldManagementRuleRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_LIVRAISON]);
        }

        $dispatchTypes = Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]))
            ->map(fn(Type $type) => [
                'id' => $type->getId(),
                'label' => $type->getLabel(),
                'active' => $type->isActive(),
            ])->toArray();

        $users = $userRepository->getAll();

        if($rights['truckArrival']){
            $reserveTypes = $reserveTypeRepository->getActiveReserveType();
        }

        if ($rights['movement']) {
            $trackingTaking = $trackingMovementService->getMobileUserPicking($entityManager, $user, TrackingMovementRepository::MOUVEMENT_TRACA_DEFAULT, [], true);
            $trackingFreeFieldManagementRules = $freeFieldManagementRuleRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]);
        }

        $carriers = Stream::from($carrierRepository->findAll())
            ->map(function (Transporteur $transporteur) use ($kernel) {
                $attachment = $transporteur->getAttachments()->isEmpty() ? null : $transporteur->getAttachments()->first();
                $logo = null;
                if ($attachment && $transporteur->isRecurrent()) {
                    $path = $kernel->getProjectDir() . '/public/uploads/attachments/' . $attachment->getFileName();
                    if (file_exists($path)) {
                        $type = pathinfo($path, PATHINFO_EXTENSION);
                        $type = ($type === 'svg' ? 'svg+xml' : $type);
                        $data = file_get_contents($path);

                        $logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    }
                }

                return [
                    'id' => $transporteur->getId(),
                    'label' => $transporteur->getLabel(),
                    'minTrackingNumberLength' => $transporteur->getMinTrackingNumberLength() ?? null,
                    'maxTrackingNumberLength' => $transporteur->getMaxTrackingNumberLength() ?? null,
                    'logo' => $logo,
                    'recurrent' => $transporteur->isRecurrent(),
                ];
            })
            ->toArray();

        $allowedNatureInLocations = $natureRepository->getAllowedNaturesIdByLocation();

        ['natures' => $natures] = $mobileApiService->getNaturesData($entityManager, $this->getUser());

        if($rights['deliveryRequest'] || $rights['deliveryOrder']) {
            $demandeLivraisonTypes = Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]))
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $type->getLabel(),
                    'active' => $type->isActive(),
                ])
                ->toArray();

        }

        if($rights['dispatch']){
            [
                'dispatches' => $dispatches,
                'dispatchPacks' => $dispatchPacks,
                'dispatchReferences' => $dispatchReferences,
            ] = $mobileApiService->getDispatchesData($entityManager, $user);
            $elements = $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_EMERGENCY);
            $dispatchEmergencies = Stream::from($elements)
                ->toArray();

            $associatedDocumentTypeElements = $settingsService->getValue($entityManager, Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES);
            $associatedDocumentTypes = Stream::explode(',', $associatedDocumentTypeElements ?? '')
                ->filter()
                ->toArray();
        }

        ['translations' => $translations] = $mobileApiService->getTranslationsData($entityManager, $this->getUser());
        return [
            'locations' => $emplacementRepository->getLocationsArray(),
            'allowedNatureInLocations' => $allowedNatureInLocations ?? [],
            'freeFields' => Stream::from(
                $trackingFreeFieldManagementRules ?? [],
                $requestFreeFieldManagementRules ?? [],
                $deliveryFreeFieldManagementRules ?? []
            )
                ->map(fn(FreeFieldManagementRule $freeFieldManagementRule) => $freeFieldManagementRule->serialize())
                ->toArray(),
            'preparations' => $preparations ?? [],
            'articlesPrepa' => $articlesPrepa ?? [],
            'articlesPrepaByRefArticle' => $articlesPrepaByRefArticle ?? [],
            'livraisons' => $livraisons ?? [],
            'articlesLivraison' => array_merge(
                $articlesLivraison ?? [],
                $refArticlesLivraison ?? []
            ),
            'collectes' => $collectes ?? [],
            'articlesCollecte' => array_merge(
                $articlesCollecte ?? [],
                $refArticlesCollecte ?? []
            ),
            'transferOrders' => $transferOrders ?? [],
            'transferOrderArticles' => $transferOrderArticles ?? [],
            'transportRounds' => $transportRounds ?? [],
            'transportRoundLines' => $transportRoundLines ?? [],
            'handlings' => $handlings ?? [],
            'handlingAttachments' => $handlingAttachments ?? [],
            'inventoryItems' => $inventoryItems ?? [],
            'inventoryMission' => $inventoryMissions ?? [],
            'inventoryLocationZone' => $inventoryLocationsZone ?? [],
            'anomalies' => $anomalies ?? [],
            'trackingTaking' => $trackingTaking ?? [],
            'stockTaking' => $stockTaking ?? [],
            'demandeLivraisonTypes' => $demandeLivraisonTypes ?? [],
            'demandeLivraisonArticles' => $demandeLivraisonArticles ?? [],
            'natures' => $natures ?? [],
            'rights' => $rights,
            'parameters' => $parameters,
            'translations' => $translations,
            'dispatches' => $dispatches ?? [],
            'dispatchPacks' => $dispatchPacks ?? [],
            'dispatchReferences' => $dispatchReferences ?? [],
            'status' => $status,
            'dispatchTypes' => $dispatchTypes ?? [],
            'users' => $users ?? [],
            'fieldsParam' => $fieldsParam ?? [],
            'projects' => $projects ?? [],
            'types' => $mobileTypes,
            'suppliers' => $suppliers ?? [],
            'reference_articles' => $refs ?? [],
            'drivers' => $driverRepository->getDriversArray(),
            'carriers' => $carriers ?? [],
            'dispatchEmergencies' => $dispatchEmergencies ?? [],
            'associatedDocumentTypes' => $associatedDocumentTypes ?? [],
            'reserveTypes' => $reserveTypes ?? [],
        ];
    }

    #[Route("/getData", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getData(Request                    $request,
                            UserService                $userService,
                            TrackingMovementService    $trackingMovementService,
                            PreparationsManagerService $preparationManager,
                            KernelInterface            $kernel,
                            MobileApiService           $mobileApiService,
                            SettingsService            $settingsService,
                            EntityManagerInterface     $entityManager): JsonResponse {
        $nomadUser = $this->getUser();

        return $this->json([
            "success" => true,
            "data" => $this->getDataArray(
                $nomadUser,
                $userService,
                $mobileApiService,
                $preparationManager,
                $trackingMovementService,
                $request,
                $settingsService,
                $entityManager,
                $kernel
            ),
        ]);
    }


    private function apiKeyGenerator()
    {
        return md5(microtime() . rand());
    }

    #[Route("/nomade-versions", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    public function checkNomadeVersion(Request $request,
                                       MobileApiService $mobileApiService,
                                       ParameterBagInterface $parameterBag): JsonResponse {
        return $this->json([
            "success" => true,
            "validVersion" => $mobileApiService->checkMobileVersion($request->get('nomadeVersion'), $parameterBag->get('nomade_version')),
        ]);
    }

    #[Route("/server-images", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    public function getLogos(EntityManagerInterface $entityManager,
                             SettingsService        $settingsService,
                             KernelInterface        $kernel,
                             Request                $request): Response
    {
        $logoKey = $request->get('key');
        if (!in_array($logoKey, [Setting::FILE_MOBILE_LOGO_HEADER, Setting::FILE_MOBILE_LOGO_LOGIN])) {
            throw new BadRequestHttpException('Unknown logo key');
        }

        $logo = $settingsService->getValue($entityManager, $logoKey);

        if (!$logo) {
            throw new FormException("Image non renseignée");
        }

        $projectDir = $kernel->getProjectDir();

        try {
            $imagePath = $projectDir . '/public/' . $logo;

            $type = pathinfo($imagePath, PATHINFO_EXTENSION);
            $type = ($type === 'svg' ? 'svg+xml' : $type);

            $data = file_get_contents($imagePath);
            $image = 'data:image/' . $type . ';base64,' . base64_encode($data);
        } catch (Throwable) {
            throw new FormException("Image non renseignée");
        }

        return $this->json([
            "success" => true,
            'image' => $image,
        ]);
    }
}

