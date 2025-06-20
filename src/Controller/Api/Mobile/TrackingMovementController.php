<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Repository\Tracking\TrackingMovementRepository;
use App\Serializer\SerializerUsageEnum;
use App\Service\ArrivageService;
use App\Service\AttachmentService;
use App\Service\Cache\CacheService;
use App\Service\LocationService;
use App\Service\ExceptionLoggerService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\GroupService;
use App\Service\MailerService;
use App\Service\MouvementStockService;
use App\Service\NatureService;
use App\Service\SettingsService;
use App\Service\Tracking\PackService;
use App\Service\Tracking\TrackingMovementService;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class TrackingMovementController extends AbstractController {

    #[Route("/tracking-movements", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function postTrackingMovements(Request                 $request,
                                          NormalizerInterface     $normalizer,
                                          CacheService            $cacheService,
                                          MailerService           $mailerService,
                                          ArrivageService         $arrivageDataService,
                                          LocationService         $locationService,
                                          MouvementStockService   $mouvementStockService,
                                          TrackingMovementService $trackingMovementService,
                                          ExceptionLoggerService  $exceptionLoggerService,
                                          FreeFieldService        $freeFieldService,
                                          AttachmentService       $attachmentService,
                                          EntityManagerInterface  $entityManager): Response {
        $successData = [];

        $nomadUser = $this->getUser();

        $numberOfRowsInserted = 0;
        $mouvementsNomade = json_decode($request->request->get('mouvements'), true);
        $createTakeAndDrop = $request->request->getBoolean('createTakeAndDrop');
        $finishMouvementTraca = [];
        $successData['data'] = [
            'errors' => [],
        ];

        $emptyGroups = [];

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $mustReloadLocation = false;


        $uniqueIds = Stream::from($mouvementsNomade)
            ->filterMap(fn(array $movement) => $movement['date'])
            ->toArray();
        $alreadySavedMovements = !empty($uniqueIds)
            ? Stream::from($trackingMovementRepository->findBy(['uniqueIdForMobile' => $uniqueIds]))
                ->keymap(fn(TrackingMovement $trackingMovement) => [$trackingMovement->getUniqueIdForMobile(), $trackingMovement])
                ->toArray()
            : [];

        $persistedMovements = [];

        foreach ($mouvementsNomade as $index => $mvt) {
            $invalidLocationTo = '';
            try {
                $entityManager->transactional(function ()
                use (
                    $articleRepository,
                    $createTakeAndDrop,
                    $mailerService,
                    $freeFieldService,
                    $mouvementStockService,
                    &$numberOfRowsInserted,
                    $mvt,
                    $nomadUser,
                    $request,
                    $attachmentService,
                    $index,
                    &$invalidLocationTo,
                    &$finishMouvementTraca,
                    $entityManager,
                    $exceptionLoggerService,
                    $trackingMovementService,
                    $emptyGroups,
                    $emplacementRepository,
                    $trackingMovementRepository,
                    $packRepository,
                    $locationService,
                    $arrivageDataService,
                    &$mustReloadLocation,
                    $alreadySavedMovements,
                    $cacheService,
                    &$persistedMovements,
                ) {

                    $trackingTypes = [
                        TrackingMovement::TYPE_PRISE => $cacheService->getEntity($entityManager, Statut::class, CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_PRISE),
                        TrackingMovement::TYPE_DEPOSE => $cacheService->getEntity($entityManager, Statut::class, CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_DEPOSE),
                    ];

                    $mouvementTraca1 = $alreadySavedMovements[$mvt['date']] ?? null;
                    if (!isset($mouvementTraca1)) {
                        $options = [
                            'uniqueIdForMobile' => $mvt['date'],
                            'entityManager' => $entityManager,
                        ];
                        $type = $trackingTypes[$mvt['type']];
                        $location = $locationService->findOrPersistWithCache($entityManager, $mvt['ref_emplacement'], $mustReloadLocation);

                        $dateArray = explode('_', $mvt['date']);

                        $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $dateArray[0]);

                        $options['natureId'] = $mvt['nature_id'] ?? null;
                        $options['quantity'] = $mvt['quantity'] ?? null;

                        $options['manualDelayStart'] = $type->getCode() === TrackingMovement::TYPE_PRISE && $mvt['manualDelayStart']
                            ? $this->formatService->parseDatetime($mvt['manualDelayStart'])
                            : null;

                        $options += $trackingMovementService->treatTrackingData($mvt, $request->files, $index);

                        if ($createTakeAndDrop) {
                            $article = $articleRepository->findOneBy(['barCode' => $mvt['ref_article']]);
                            $createdPriseMvt = $trackingMovementService->createTrackingMovement(
                                $mvt['ref_article'],
                                $article->getCurrentLogisticUnit()->getLastOngoingDrop()->getEmplacement(),
                                $nomadUser,
                                $date,
                                true,
                                true,
                                TrackingMovement::TYPE_PRISE,
                                $options,
                            );
                            $article->setCurrentLogisticUnit(null);
                            $entityManager->persist($createdPriseMvt);
                        }

                        $createdMvt = $trackingMovementService->createTrackingMovement(
                            $mvt['ref_article'],
                            $location,
                            $nomadUser,
                            $date,
                            true,
                            $mvt['finished'],
                            $type,
                            $options,
                        );
                        $entityManager->persist($createdMvt);

                        $associatedPack = $createdMvt->getPack();
                        $createdMvt->setLogisticUnitParent($associatedPack?->getArticle()?->getCurrentLogisticUnit());

                        if ($type->getCode() === TrackingMovement::TYPE_PRISE && $associatedPack?->getArticle()?->getCurrentLogisticUnit()) {
                            $movement = $trackingMovementService->persistTrackingMovement(
                                $entityManager,
                                $associatedPack ?? $mvt['ref_article'],
                                $location,
                                $nomadUser,
                                $date,
                                true,
                                TrackingMovement::TYPE_PICK_LU,
                                false,
                                $options
                            )['movement'];
                            $movement->setLogisticUnitParent($associatedPack->getArticle()->getCurrentLogisticUnit());
                            $createdMvt->setMainMovement($movement);
                            $associatedPack->getArticle()->setCurrentLogisticUnit(null);
                            $trackingMovementService->persistSubEntities($entityManager, $movement);
                            $entityManager->persist($movement);
                            $numberOfRowsInserted++;
                        }

                        if ($associatedPack) {
                            $associatedGroup = $associatedPack->getGroup();

                            if ($associatedGroup) {
                                $associatedGroup->removeContent($associatedPack);
                                if ($associatedGroup->getContent()->isEmpty()) {
                                    $emptyGroups[] = $associatedGroup->getCode();
                                }
                            }
                        }

                        $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                        $entityManager->persist($createdMvt);
                        $numberOfRowsInserted++;
                        if ($mvt['freeFields']) {
                            $givenFreeFields = json_decode($mvt['freeFields'], true);
                            $smartFreeFields = array_reduce(
                                array_keys($givenFreeFields),
                                function (array $acc, $id) use ($givenFreeFields) {
                                    if (gettype($id) === 'integer' || ctype_digit($id)) {
                                        $acc[(int)$id] = $givenFreeFields[$id];
                                    }
                                    return $acc;
                                },
                                []
                            );
                            if (!empty($smartFreeFields)) {
                                $freeFieldService->manageFreeFields($createdMvt, $smartFreeFields, $entityManager);
                            }
                        }

                        // envoi de mail si c'est une dépose + l'UL existe + l'emplacement est un point de livraison
                        $arrivageDataService->sendMailForDeliveredPack($entityManager, $location, $associatedPack, $nomadUser, $type->getNom(), $date);

                        $entityManager->flush();

                        if ($type->getNom() === TrackingMovement::TYPE_DEPOSE) {
                            $finishMouvementTraca[] = $mvt['ref_article'];
                        }

                        $persistedMovements[] = $createdMvt;
                    }
                });
            } catch (Throwable $throwable) {
                if (!$entityManager->isOpen()) {
                    /** @var EntityManagerInterface $entityManager */
                    $entityManager = new EntityManager($entityManager->getConnection(), $entityManager->getConfiguration());
                    $entityManager->clear();
                    $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
                    $nomadUser = $utilisateurRepository->find($nomadUser->getId());
                    $mustReloadLocation = true;
                }

                if ($throwable->getMessage() === TrackingMovementService::INVALID_LOCATION_TO) {
                    $successData['data']['errors'][$mvt['ref_article']] = ($mvt['ref_article'] . " doit être déposé sur l'emplacement \"$invalidLocationTo\"");
                } else if ($throwable->getMessage() === Pack::PACK_IS_GROUP) {
                    $successData['data']['errors'][$mvt['ref_article']] = 'L\'unité logistique scannée est un groupe';
                } else {
                    $exceptionLoggerService->sendLog($throwable);
                    $successData['data']['errors'][$mvt['ref_article']] = 'Une erreur s\'est produite lors de l\'enregistrement de ' . $mvt['ref_article'];
                }
            }
        }

        $trackingMovementService->clearTrackingMovement($mouvementsNomade, $finishMouvementTraca, $alreadySavedMovements);
        $entityManager->flush();

        $s = $numberOfRowsInserted > 1 ? 's' : '';
        $successData['success'] = true;
        $successData['data']['status'] = ($numberOfRowsInserted === 0)
            ? 'Aucun mouvement à synchroniser.'
            : ($numberOfRowsInserted . ' mouvement' . $s . ' synchronisé' . $s);
        $successData['data']['movementCounter'] = $numberOfRowsInserted;
        $successData['data']['persistedMovements'] = $normalizer->normalize($persistedMovements, null, ["usage" => SerializerUsageEnum::MOBILE_DROP_MENU]);

        if (!empty($emptyGroups)) {
            $successData['data']['emptyGroups'] = $emptyGroups;
        }

        return $this->json($successData);
    }


    #[Route("/group-trackings/{trackingMode}", requirements: ["trackingMode" => "picking|drop"], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function postGroupedTracking(Request                 $request,
                                        AttachmentService       $attachmentService,
                                        FreeFieldService        $freeFieldService,
                                        EntityManagerInterface  $entityManager,
                                        TrackingMovementService $trackingMovementService,
                                        string                  $trackingMode): JsonResponse
    {

        /** @var Utilisateur $operator */
        $operator = $this->getUser();
        $packRepository = $entityManager->getRepository(Pack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $movementsStr = $request->request->get('mouvements');
        $movements = json_decode($movementsStr, true);

        $isMovementFinished = ($trackingMode === 'drop');
        $movementType = $trackingMode === 'drop' ? TrackingMovement::TYPE_DEPOSE : TrackingMovement::TYPE_PRISE;

        $groupsArray = Stream::from($movements)
            ->map(function ($movement) {
                $date = explode('+', $movement['date']);
                $date = $date[0] ?? $movement['date'];
                return [
                    'code' => $movement['packGroup'] ?? $movement['ref_article'],
                    'location' => $movement['ref_emplacement'],
                    'nature_id' => $movement['nature_id'],
                    'date' => new DateTime($date ?? 'now'),
                    'type' => $movement['type'],
                    'freeFields' => $movement['freeFields'] ?? '',
                ];
            })
            ->toArray();

        $res = [
            'success' => true,
            'finishedMovements' => [],
        ];

        try {
            foreach ($groupsArray as $groupIndex => $serializedGroup) {
                $newMovements = [];

                /** @var Pack $parent */
                $parent = $packRepository->findOneBy(['code' => $serializedGroup['code']]);
                if ($parent) {
                    if (isset($serializedGroup['nature_id'])) {
                        $nature = $natureRepository->find($serializedGroup['nature_id']);
                        $parent->setNature($nature);
                    }

                    $location = $locationRepository->findOneBy(['label' => $serializedGroup['location']]);

                    $options = ['disableUngrouping' => true];

                    if ($isMovementFinished) {
                        $res['finishedMovements'][] = $trackingMovementService->finishTrackingMovement($parent->getLastAction());
                    }

                    /** @var Pack $child */
                    foreach ($parent->getContent() as $child) {
                        if ($isMovementFinished) {
                            $res['finishedMovements'][] = $trackingMovementService->finishTrackingMovement($child->getLastAction());
                        }

                        $trackingMovement = $trackingMovementService->createTrackingMovement(
                            $child,
                            $location,
                            $operator,
                            $serializedGroup['date'],
                            true,
                            $isMovementFinished,
                            $movementType,
                            array_merge(
                                [
                                    'parent' => $parent,
                                ],
                                $options)
                        );

                        $newMovements[] = $trackingMovement;

                        $entityManager->persist($trackingMovement);
                        $trackingMovementService->persistSubEntities($entityManager, $trackingMovement);
                    }

                    $trackingMovement = $trackingMovementService->createTrackingMovement(
                        $parent,
                        $location,
                        $operator,
                        $serializedGroup['date'],
                        true,
                        $isMovementFinished,
                        $movementType,
                        $options
                    );

                    $newMovements[] = $trackingMovement;

                    $entityManager->persist($trackingMovement);
                    $trackingMovementService->persistSubEntities($entityManager, $trackingMovement);

                    $signatureFile = $request->files->get("signature_$groupIndex");
                    $photoFile = $request->files->get("photo_$groupIndex");
                    $fileNames = [];
                    if (!empty($signatureFile)) {
                        $fileNames = array_merge($fileNames, $attachmentService->saveFile($signatureFile));
                    }
                    if (!empty($photoFile)) {
                        $fileNames = array_merge($fileNames, $attachmentService->saveFile($photoFile));
                    }

                    foreach ($newMovements as $movement) {
                        $attachments = $attachmentService->createAttachmentsDeprecated($fileNames);
                        foreach ($attachments as $attachment) {
                            $entityManager->persist($attachment);
                            $movement->addAttachment($attachment);
                        }
                    }

                    $freeFields = json_decode($serializedGroup['freeFields'], true);
                    if(!empty($freeFields)){
                        $freeFieldService->manageFreeFields($trackingMovement, $freeFields, $entityManager);
                    }

                    $res['finishedMovements'] = Stream::from($res['finishedMovements'])
                        ->filter()
                        ->unique()
                        ->values();
                }
            }

            $entityManager->flush();

            $res['tracking'] = $trackingMovementService->getMobileUserPicking($entityManager, $operator, TrackingMovementRepository::MOUVEMENT_TRACA_DEFAULT);
        } catch (Throwable $throwable) {
            $res['success'] = false;
            $res['message'] = "Une erreur est survenue lors de l'enregistrement d'un mouvement";
        }

        return $this->json($res);
    }

    #[Route("/tracking-drops", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getTrackingDropsOnLocation(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $resData = [];

        $locationLabel = $request->query->get('location');
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $location = !empty($locationLabel)
            ? $emplacementRepository->findOneBy(['label' => $locationLabel])
            : null;

        if (!empty($locationLabel) && !isset($location)) {
            $location = $emplacementRepository->find($locationLabel);
        }

        if (!empty($location)) {
            if ($location instanceof Emplacement && $location->isOngoingVisibleOnMobile()) {
                $resData['success'] = true;
                $packMaxNumber = 50;
                $packRepository = $entityManager->getRepository(Pack::class);
                $ongoingPackIds = Stream::from($packRepository->getCurrentPackOnLocations(
                    [$location],
                    [
                        'order' => 'asc',
                        'isCount' => false,
                        'limit' => $packMaxNumber,
                    ]
                ))
                    ->map(fn(array $pack) => $pack['id'])
                    ->toArray();

                $resData['trackingDrops'] = $packRepository->getPacksById($ongoingPackIds);
            } else {
                $resData['trackingDrops'] = [];
            }
        } else {
            $resData['success'] = true;
            $resData['trackingDrops'] = [];
        }

        return new JsonResponse($resData, Response::HTTP_OK);
    }

    #[Route("/packs", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getPackData(Request                $request,
                                EntityManagerInterface $entityManager,
                                NatureService          $natureService,
                                PackService            $packService,
                                NormalizerInterface    $normalizer): JsonResponse
    {
        $code = $request->query->get('code');
        $includeNature = $request->query->getBoolean('nature');
        $includeGroup = $request->query->getBoolean('group');
        $includeLocation = $request->query->getBoolean('location');
        $includeExisting = $request->query->getBoolean('existing');
        $includePack = $request->query->getBoolean('pack');
        $includeMovements = $request->query->getBoolean('movements');
        $includeSplitCount = $request->query->getBoolean('splitCount');
        $includeTrackingDelayData = $request->query->getBoolean('trackingDelayData');
        $res = ['success' => true];

        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $packRepository = $entityManager->getRepository(Pack::class);

        $pack = !empty($code)
            ? $packRepository->findOneBy(['code' => $code])
            : null;

        if ($pack) {
            $isGroup = $pack->isGroup();
            $res['isGroup'] = $isGroup;
            $res['isPack'] = !$isGroup;
            $res['isGroupCandidate'] = $pack->isGroupCandidate();

            if ($includeTrackingDelayData) {
                $trackingDelayData = $packService->formatTrackingDelayData($pack);
                $res['trackingDelayData'] = isset($trackingDelayData["color"]) && isset($trackingDelayData["delay"])
                    ? [
                        'color' => $trackingDelayData["color"],
                        'delay' => $trackingDelayData["delayHTML"],
                        'limitTreatmentDate' => $trackingDelayData["dateHTML"] ?? null,
                    ]
                    : [];
            }

            if ($includeGroup) {
                $group = $isGroup ? $pack : $pack->getGroup();
                $res['group'] = $group
                    ? $normalizer->normalize($group, null, ["usage" => SerializerUsageEnum::MOBILE])
                    : null;

            }

            if ($includePack) {
                $res['pack'] = $normalizer->normalize($pack, null, ["usage" => SerializerUsageEnum::MOBILE]);
            }

            if($includeMovements) {
                $movements = $trackingMovementRepository->findBy(
                    ['pack' => $pack],
                    [
                        'datetime' => Order::Descending->value,
                        'orderIndex' => Order::Descending->value,
                        'id' => Order::Descending->value,
                    ],
                    50
                );
                $normalizedMovements = Stream::from($movements)
                    ->map(static fn(TrackingMovement $movement) => $normalizer->normalize($movement, null, ["usage" => SerializerUsageEnum::MOBILE_READING_MENU]))
                    ->toArray();
                $res['movements'] = $normalizedMovements;
            }

            if ($includeNature) {
                $nature = $pack->getNature();
                $res['nature'] = !empty($nature)
                    ? $natureService->serializeNature($nature, $this->getUser())
                    : null;
            }

            if ($includeLocation) {
                $location = $pack->getLastAction()?->getEmplacement();
                $res["location"] = $location?->getId();
            }

            if($includeExisting) {
                $res['existing'] = true;
            }

            if($includeSplitCount) {
                $res["splitCount"] = $pack->getSplitTargets()->count();
            }
        } else {
            $res['isGroup'] = false;
            $res['isPack'] = false;
            $res['location'] = null;
            $res['existing'] = false;
        }

        return $this->json($res);
    }

    #[Route("/group", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function group(Request                 $request,
                          PackService             $packService,
                          EntityManagerInterface  $entityManager,
                          GroupService            $groupService,
                          SettingsService         $settingsService,
                          FormatService           $formatService,
                          TrackingMovementService $trackingMovementService): JsonResponse
    {
        $operator = $this->getUser();

        $packRepository = $entityManager->getRepository(Pack::class);

        /** @var Pack $parentPack */
        $parentPack = $packRepository->findOneBy(['code' => $request->request->get("code")]);
        $isNewGroupInstance = false;
        if (!$parentPack) {
            $isNewGroupInstance = true;
            $parentPack = $groupService->createParentPack([
                'parent' => $request->request->get("code"),
            ]);

            $entityManager->persist($parentPack);
        } else if ($parentPack->getContent()->isEmpty()) {
            $isNewGroupInstance = true;
            $parentPack->incrementGroupIteration();
        }

        $packs = json_decode($request->request->get("packs"), true);
        $packCodes = Stream::from($packs)
            ->map(static fn (array $data) => $data['code'])
            ->toArray();
        $groupChildren = $trackingMovementService->findAllPacks($entityManager, $packCodes);

        $dateStr = $request->request->get("date");
        $groupDate = $formatService->parseDatetime($dateStr);

        if ($isNewGroupInstance && !empty($packs)) {
            if ($settingsService->getValue($entityManager, Setting::DROP_MOVEMENT_ON_GROUPING) == 1) {
                $trackingMovementService->retrieveDataFromChildPackToTreatMostRapidly($parentPack, $operator, $groupDate, Stream::from($groupChildren)->filter()->values(), $dropTrackingMovement);
                if ($dropTrackingMovement) {
                    $entityManager->persist($dropTrackingMovement);
                }
            }
            $groupingTrackingMovement = $trackingMovementService->createTrackingMovement(
                $parentPack,
                null,
                $operator,
                $groupDate,
                true,
                true,
                TrackingMovement::TYPE_GROUP
            );

            $entityManager->persist($groupingTrackingMovement);
        }

        $countContent = $parentPack->getContent()->count() + count($packs);
        if($countContent > Pack::GROUPING_LIMIT) {
            $limit = Pack::GROUPING_LIMIT;
            $packParentCode = $parentPack->getCode();
            throw new FormException("Le groupe $packParentCode ne peut pas contenir plus de $limit unités logistiques.");
        }

        foreach ($packs as $data) {
            $childGroupDate = $formatService->parseDatetime($data["date"]);
            $splitFromId = $data["splitFromId"] ?? null;
            $splitFrom = $splitFromId ? $packRepository->findOneBy(['id' => $splitFromId]) : null;

            $pack = $groupChildren[$data["code"]]
                ?? $packService->persistPack(
                    $entityManager,
                    $data["code"],
                    $data["quantity"],
                    $data["nature_id"] ?? null,
                    false,
                    [
                        'fromPackSplit' => isset($splitFrom),
                    ]
                );

            if (isset($data["comment"])) {
                $pack->setComment($data["comment"]);
            }

            if (isset($splitFrom)) {
                $trackingMovementService->manageSplitPack(
                    $entityManager,
                    $splitFrom,
                    $pack,
                    $childGroupDate
                );
            }

            if (!$pack->getGroup()) {
                $pack->setGroup($parentPack);

                $groupingTrackingMovement = $trackingMovementService->createTrackingMovement(
                    $pack,
                    null,
                    $operator,
                    $childGroupDate,
                    true,
                    true,
                    TrackingMovement::TYPE_GROUP,
                    [
                        'parent' => $parentPack,
                    ]
                );

                $entityManager->persist($groupingTrackingMovement);
            }
        }

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Groupage synchronisé",
        ]);
    }

    #[Route("/ungroup", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function ungroup(Request $request, EntityManagerInterface $manager, GroupService $groupService): Response
    {
        $locationRepository = $manager->getRepository(Emplacement::class);
        $packRepository = $manager->getRepository(Pack::class);

        $date = DateTime::createFromFormat("d/m/Y H:i:s", $request->request->get("date"));
        $location = $locationRepository->find($request->request->get("location"));
        $group = $packRepository->find($request->request->get("group"));

        $groupService->ungroup($manager, $group, $location, $this->getUser(), $date);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Dégroupage synchronisé",
        ]);
    }

    #[Route("/empty-round", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function emptyRound(Request $request, TrackingMovementService $trackingMovementService, EntityManagerInterface $manager): JsonResponse
    {
        $emptyRounds = $request->request->get('params')
            ? json_decode($request->request->get('params'), true)
            : [$request->request->all()];

        $packRepository = $manager->getRepository(Pack::class);
        $locationRepository = $manager->getRepository(Emplacement::class);
        $user = $this->getUser();

        foreach ($emptyRounds as $emptyRound) {
            if (isset($emptyRound['date']) && isset($emptyRound['location'])) {
                $date = DateTime::createFromFormat("d/m/Y H:i:s", $emptyRound['date']);

                $emptyRoundPack = $packRepository->findOneBy(['code' => Pack::EMPTY_ROUND_PACK]);
                $location = $locationRepository->findOneBy(['label' => $emptyRound['location']]);

                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $emptyRoundPack,
                    $location,
                    $user,
                    $date,
                    true,
                    true,
                    TrackingMovement::TYPE_EMPTY_ROUND,
                    [
                        'commentaire' => $emptyRound['comment'] ?? null,
                        'quantity' => 1
                    ]
                );

                $manager->persist($trackingMovement);
            }
        }
        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route("/drop-in-lu", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function postDropInLu(Request $request, EntityManagerInterface $entityManager, TrackingMovementService $trackingMovementService): Response
    {
        $articlesRepository = $entityManager->getRepository(Article::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $packRepository = $entityManager->getRepository(Pack::class);

        $articles = json_decode($request->request->get('articles'));

        $articlesEntites = Stream::from($articles)
            ->map(fn(string $barCode) => $articlesRepository->findOneBy(['barCode' => $barCode]))
            ->filter()
            ->toArray();

        $luToDropIn = $request->request->get('lu');

        $luToDropInEntity = $packRepository->findOneBy([
            'code' => $luToDropIn
        ]);

        $location = $request->request->get('location');

        $trackingMovementService->persistLogisticUnitMovements(
            $entityManager,
            $luToDropInEntity ?? $luToDropIn,
            $locationRepository->findOneBy(['label' => $location]),
            $articlesEntites,
            $this->getUser(),
            [
                'entityManager' => $entityManager
            ]
        );

        $entityManager->flush();

        return new JsonResponse([
            'success' => true
        ], Response::HTTP_OK);
    }

    #[Route("/pick-and-drop-tracking-movements", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function postPickAndDropTrackingMovements(Request                 $request,
                                                     EntityManagerInterface  $entityManager,
                                                     TrackingMovementService $trackingMovementService,
                                                     AttachmentService       $attachmentService,
                                                     FreeFieldService        $freeFieldService) {
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $data = $request->request;
        $movementsToCreate = json_decode($data->get('mouvements'), true);

        $pickLocationId = $data->getInt('pickLocation');
        $pickLocation = $locationRepository->find($pickLocationId);

        $dropLocationId = $data->getInt('dropLocation');
        $dropLocation = $locationRepository->find($dropLocationId);

        $nomadUser = $this->getUser();

        $attachments = $attachmentService->createAttachmentsDeprecated($request->files);

        foreach ($movementsToCreate ?? [] as $index => $movementToCreate) {
            $options = [
                'uniqueIdForMobile' => $movementToCreate['date'],
                'entityManager' => $entityManager,
                'attachments' => Stream::from($attachments)
                    ->filter(fn(Attachment $attachment) => in_array($attachment->getOriginalName(), ["signature_$index.jpeg", "photo_$index.jpeg"]))
                    ->toArray(),
            ];

            $dateArray = explode('_', $movementToCreate['date']);

            $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $dateArray[0]);

            $options['natureId'] = $movementToCreate['nature_id'] ?? null;
            $options['quantity'] = $movementToCreate['quantity'] ?? null;
            $options['commentaire'] = $movementToCreate['comment'] ?? null;

            $options['manualDelayStart'] = isset($movementToCreate['manualDelayStart'])
                ? $this->formatService->parseDatetime($movementToCreate['manualDelayStart'])
                : null;

            $createdPickMvt = $trackingMovementService->createTrackingMovement(
                $movementToCreate['ref_article'],
                $pickLocation,
                $nomadUser,
                $date,
                true,
                true,
                TrackingMovement::TYPE_PRISE,
                $options,
            );
            $entityManager->persist($createdPickMvt);
            $trackingMovementService->persistSubEntities($entityManager, $createdPickMvt);

            $createdDropMvt = $trackingMovementService->createTrackingMovement(
                $createdPickMvt->getPack(),
                $dropLocation,
                $nomadUser,
                $date,
                true,
                $movementToCreate['finished'],
                TrackingMovement::TYPE_DEPOSE,
                $options,
            );
            $entityManager->persist($createdDropMvt);
            $trackingMovementService->persistSubEntities($entityManager, $createdDropMvt);

            if (isset($movementToCreate['freeFields'])) {
                $smartFreeFields = Stream::from(json_decode($movementToCreate['freeFields'], true))
                    ->keymap(static function ($value, int $freeFieldId) {
                        return gettype($freeFieldId) === 'integer' || ctype_digit($freeFieldId)
                            ? [$freeFieldId, $value]
                            : null;
                    })
                    ->toArray();
                if (!empty($smartFreeFields)) {
                    $freeFieldService->manageFreeFields($createdPickMvt, $smartFreeFields, $entityManager);
                    $freeFieldService->manageFreeFields($createdDropMvt, $smartFreeFields, $entityManager);
                }
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true
        ], Response::HTTP_OK);
    }
}
