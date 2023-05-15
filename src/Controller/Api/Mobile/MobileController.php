<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\Api\AbstractApiController;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\Chauffeur;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\DispatchReferenceArticle;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\Handling;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\NativeCountry;
use App\Entity\Nature;
use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Project;
use App\Entity\ReferenceArticle;
use App\Entity\Reserve;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\TransferOrder;
use App\Entity\Transporteur;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\FormException;
use App\Exceptions\NegativeQuantityException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Repository\ArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\TrackingMovementRepository;
use App\Service\ArrivageService;
use App\Service\ArticleDataService;
use App\Service\AttachmentService;
use App\Service\DeliveryRequestService;
use App\Service\DispatchService;
use App\Service\EmplacementDataService;
use App\Service\ExceptionLoggerService;
use App\Service\FreeFieldService;
use App\Service\GroupService;
use App\Service\HandlingService;
use App\Service\InventoryService;
use App\Service\LivraisonsManagerService;
use App\Service\MailerService;
use App\Service\MobileApiService;
use App\Service\MouvementStockService;
use App\Service\NatureService;
use App\Service\NotificationService;
use App\Service\OrdreCollecteService;
use App\Service\PackService;
use App\Service\PreparationsManagerService;
use App\Service\ProjectHistoryRecordService;
use App\Service\RefArticleDataService;
use App\Service\StatusHistoryService;
use App\Service\StatusService;
use App\Service\TrackingMovementService;
use App\Service\TransferOrderService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;
use Twig\Environment as Twig_Environment;


class MobileController extends AbstractApiController
{

    /** @Required */
    public NotificationService $notificationService;

    /** @Required */
    public MobileApiService $mobileApiService;

    /** @Required */
    public TrackingMovementService $trackingMovementService;

    /**
     * @Rest\Post("/api/api-key", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestVersionChecked()
     */
    public function postApiKey(Request $request,
                               EntityManagerInterface $entityManager,
                               UserService $userService)
    {

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $globalParametersRepository = $entityManager->getRepository(Setting::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        $mobileKey = $request->request->get('loginKey');

        $loggedUser = $utilisateurRepository->findOneBy(['mobileLoginKey' => $mobileKey, 'status' => true]);
        $data = [];

        if (!empty($loggedUser)) {
            $apiKey = $this->apiKeyGenerator();
            $loggedUser->setApiKey($apiKey);
            $entityManager->flush();

            $rights = $userService->getMobileRights($loggedUser);
            $parameters = $this->mobileApiService->getMobileParameters($globalParametersRepository);

            $channels = Stream::from($rights)
                ->filter(fn($val, $key) => $val && in_array($key, ["stock", "tracking", "group", "ungroup", "demande", "notifications"]))
                ->takeKeys()
                ->map(fn($right) => $_SERVER["APP_INSTANCE"] . "-" . $right)
                ->toArray();

            if (in_array($_SERVER["APP_INSTANCE"] . "-stock" , $channels)) {
                Stream::from($loggedUser->getDeliveryTypes())
                    ->each(function(Type $deliveryType) use (&$channels) {
                        $channels[] = $_SERVER["APP_INSTANCE"] . "-stock-delivery-" . $deliveryType->getId();
                    });
            }
            if (in_array($_SERVER["APP_INSTANCE"] . "-tracking" , $channels)) {
                Stream::from($loggedUser->getDispatchTypes())
                    ->each(function(Type $dispatchType) use (&$channels) {
                        $channels[] = $_SERVER["APP_INSTANCE"] . "-tracking-dispatch-" . $dispatchType->getId();
                    });
            }
            if (in_array($_SERVER["APP_INSTANCE"] . "-demande" , $channels)) {
                Stream::from($loggedUser->getHandlingTypes())
                    ->each(function(Type $handlingType) use (&$channels) {
                        $channels[] = $_SERVER["APP_INSTANCE"] . "-demande-handling-" . $handlingType->getId();
                    });
            }

            $channels[] = $_SERVER["APP_INSTANCE"] . "-" . $userService->getUserFCMChannel($loggedUser);

            $fieldsParam = Stream::from([FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::ENTITY_CODE_DEMANDE])
                ->keymap(fn(string $entityCode) => [$entityCode, $fieldsParamRepository->getByEntity($entityCode)])
                ->toArray();

            $data['success'] = true;
            $data['data'] = [
                'apiKey' => $apiKey,
                'notificationChannels' => $channels,
                'rights' => $rights,
                'parameters' => $parameters,
                'username' => $loggedUser->getUsername(),
                'userId' => $loggedUser->getId(),
                'fieldsParam' => $fieldsParam ?? [],
            ];
        } else {
            $data['success'] = false;
        }

        return new JsonResponse($data);
    }

    /**
     * @Rest\Post("/api/tracking-movements", name="api-post-tracking-movements", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function postTrackingMovements(Request                 $request,
                                          MailerService           $mailerService,
                                          ArrivageService         $arrivageDataService,
                                          EmplacementDataService  $locationDataService,
                                          MouvementStockService   $mouvementStockService,
                                          TrackingMovementService $trackingMovementService,
                                          ExceptionLoggerService $exceptionLoggerService,
                                          FreeFieldService $freeFieldService,
                                          AttachmentService $attachmentService,
                                          EntityManagerInterface $entityManager)
    {
        $successData = [];
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST');

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
        $statutRepository = $entityManager->getRepository(Statut::class);
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

        $trackingTypes = [
            TrackingMovement::TYPE_PRISE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_PRISE),
            TrackingMovement::TYPE_DEPOSE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_DEPOSE),
        ];

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
                    $statutRepository,
                    $trackingMovementRepository,
                    $packRepository,
                    $locationDataService,
                    $arrivageDataService,
                    &$mustReloadLocation,
                    $alreadySavedMovements,
                    $trackingTypes
                ) {
                    $trackingTypes = [
                        TrackingMovement::TYPE_PRISE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_PRISE),
                        TrackingMovement::TYPE_DEPOSE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_DEPOSE),
                    ];

                    if (empty($trackingTypes)) {
                        $trackingTypes = [
                            TrackingMovement::TYPE_PRISE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_PRISE),
                            TrackingMovement::TYPE_DEPOSE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_DEPOSE),
                        ];
                    }

                    $mouvementTraca1 = $alreadySavedMovements[$mvt['date']] ?? null;
                    if (!isset($mouvementTraca1)) {
                        $options = [
                            'uniqueIdForMobile' => $mvt['date'],
                            'entityManager' => $entityManager,
                        ];
                        $type = $trackingTypes[$mvt['type']];
                        $location = $locationDataService->findOrPersistWithCache($entityManager, $mvt['ref_emplacement'], $mustReloadLocation);

                        $dateArray = explode('_', $mvt['date']);

                        $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $dateArray[0]);

                        $options['natureId'] = $mvt['nature_id'] ?? null;
                        $options['quantity'] = $mvt['quantity'] ?? null;

                        $options += $trackingMovementService->treatTrackingData($mvt, $request->files, $index);

                        if($createTakeAndDrop){
                            $article = $articleRepository->findOneBy(['barCode' => $mvt['ref_article']]);
                            $createdPriseMvt = $trackingMovementService->createTrackingMovement(
                                $mvt['ref_article'],
                                $article->getCurrentLogisticUnit()->getLastDrop()->getEmplacement(),
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

                        if($type->getCode() === TrackingMovement::TYPE_PRISE && $associatedPack?->getArticle()?->getCurrentLogisticUnit()) {
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
                            $associatedGroup = $associatedPack->getParent();

                            if ($associatedGroup) {
                                $associatedGroup->removeChild($associatedPack);
                                if ($associatedGroup->getChildren()->isEmpty()) {
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
                        $arrivageDataService->sendMailForDeliveredPack($location, $associatedPack, $nomadUser, $type->getNom(), $date);

                        $entityManager->flush();

                        if ($type->getNom() === TrackingMovement::TYPE_DEPOSE) {
                            $finishMouvementTraca[] = $mvt['ref_article'];
                        }
                    }
                });
            } catch (Throwable $throwable) {
                if (!$entityManager->isOpen()) {
                    /** @var EntityManagerInterface $entityManager */
                    $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                    $entityManager->clear();
                    $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
                    $statutRepository = $entityManager->getRepository(Statut::class);
                    $nomadUser = $utilisateurRepository->find($nomadUser->getId());
                    $trackingTypes = [];
                    $mustReloadLocation = true;
                }

                if ($throwable->getMessage() === TrackingMovementService::INVALID_LOCATION_TO) {
                    $successData['data']['errors'][$mvt['ref_article']] = ($mvt['ref_article'] . " doit être déposé sur l'emplacement \"$invalidLocationTo\"");
                } else if ($throwable->getMessage() === Pack::PACK_IS_GROUP) {
                    $successData['data']['errors'][$mvt['ref_article']] = 'L\'unité logistique scannée est un groupe';
                } else {
                    $exceptionLoggerService->sendLog($throwable, $request);
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

        if (!empty($emptyGroups)) {
            $successData['data']['emptyGroups'] = $emptyGroups;
        }

        $response->setContent(json_encode($successData));
        return $response;
    }

    /**
     * @Rest\Post("/api/stock-movements", name="api-post-stock-movements", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function postStockMovements(Request                 $request,
                                       EmplacementDataService  $locationDataService,
                                       MouvementStockService   $mouvementStockService,
                                       ExceptionLoggerService  $exceptionLoggerService,
                                       TrackingMovementService $trackingMovementService,
                                       FreeFieldService        $freeFieldService,
                                       ProjectHistoryRecordService    $projectHistoryRecordService,
                                       AttachmentService       $attachmentService,
                                       EntityManagerInterface  $entityManager)
    {
        $successData = [];
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST');

        $nomadUser = $this->getUser();

        $numberOfRowsInserted = 0;
        $mouvementsNomade = json_decode($request->request->get('mouvements'), true);
        $finishMouvementTraca = [];
        $successData['data'] = [
            'errors' => [],
        ];

        $emptyGroups = [];
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $projectRepository = $entityManager->getRepository(Project::class);


        $mustReloadLocation = false;

        $uniqueIds = Stream::from($mouvementsNomade)
            ->filterMap(fn(array $movement) => $movement['date'])
            ->toArray();

        $alreadySavedMovements = !empty($uniqueIds)
            ? Stream::from($trackingMovementRepository->findBy(['uniqueIdForMobile' => $uniqueIds]))
                ->keymap(fn(TrackingMovement $trackingMovement) => [$trackingMovement->getUniqueIdForMobile(), $trackingMovement])
                ->toArray()
            : [];

        foreach ($mouvementsNomade as $index => $mvt) {
            $invalidLocationTo = '';
            try {
                $entityManager->transactional(function ()
                use (
                    $projectHistoryRecordService,
                    $projectRepository,
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
                    $trackingMovementService,
                    &$emptyGroups,
                    $emplacementRepository,
                    $packRepository,
                    $articleRepository,
                    $statutRepository,
                    $trackingMovementRepository,
                    $locationDataService,
                    &$mustReloadLocation,
                    $alreadySavedMovements,
                ) {
                    $trackingTypes = [
                        TrackingMovement::TYPE_PRISE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_PRISE),
                        TrackingMovement::TYPE_DEPOSE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_DEPOSE),
                    ];

                    if (!isset($alreadySavedMovements[$mvt['date']])) {
                        $options = [
                            'uniqueIdForMobile' => $mvt['date'],
                            'entityManager' => $entityManager,
                            'quantity' => $mvt['quantity'],
                            'commentaire' => isset($mvt['comment']) ? $mvt['comment'] : '',
                        ];

                        /** @var Statut $type */
                        $type = $trackingTypes[$mvt['type']];
                        $location = $locationDataService->findOrPersistWithCache($entityManager, $mvt['ref_emplacement'], $mustReloadLocation);

                        $dateArray = explode('_', $mvt['date']);
                        $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $dateArray[0]);

                        //trouve les ULs sans association à un article car les ULs
                        //associés a des articles SONT des articles donc on les traite normalement
                        $pack = $packRepository->findWithoutArticle($mvt['ref_article']);

                        //dans le cas d'une prise stock sur une UL, on ne peut pas créer de
                        //mouvement de stock sur l'UL donc on ignore la partie stock et
                        //on créé juste un mouvement de prise sur l'UL et ses articles
                        if($pack) {
                            $packMvt = $trackingMovementService->treatLUPicking(
                                $pack,
                                $location,
                                $nomadUser,
                                $date,
                                $mvt,
                                $type,
                                $options,
                                $entityManager,
                                $emptyGroups,
                                $numberOfRowsInserted
                            );
                            $trackingMovementService->manageTrackingMovementsForLU(
                                $pack,
                                $entityManager,
                                $mouvementStockService,
                                $mvt,
                                $type,
                                $nomadUser,
                                $location,
                                $date,
                                $emptyGroups,
                                $numberOfRowsInserted
                            );

                            if($type->getCode() === TrackingMovement::TYPE_PRISE){
                                if (isset($mvt['projectId'])) {
                                    $project = $projectRepository->find($mvt['projectId']);
                                    $projectHistoryRecordService->changeProject($entityManager, $pack, $project, $date);

                                    foreach ($pack->getChildArticles() as $article) {
                                        $projectHistoryRecordService->changeProject($entityManager, $article, $project, $date);
                                    }
                                }
                                $signatureFile = $request->files->get("signature_$index");
                                $photoFile = $request->files->get("photo_$index");
                                $fileNames = [];
                                if (!empty($signatureFile)) {
                                    $fileNames = array_merge($fileNames, $attachmentService->saveFile($signatureFile));
                                }
                                if (!empty($photoFile)) {
                                    $fileNames = array_merge($fileNames, $attachmentService->saveFile($photoFile));
                                }
                                $attachments = $attachmentService->createAttachements($fileNames);
                                foreach ($attachments as $attachment) {
                                    $entityManager->persist($attachment);
                                    $packMvt->addAttachment($attachment);
                                }
                            }

                            $entityManager->persist($packMvt);
                            $entityManager->flush();
                        } else { //cas mouvement stock classique sur un article ou une ref
                            $options += $trackingMovementService->treatStockMovement($entityManager, $type?->getCode(), $mvt, $nomadUser, $location, $date);
                            if ($options['invalidLocationTo'] ?? null) {
                                $invalidLocationTo = $options['invalidLocationTo'];
                                throw new Exception(TrackingMovementService::INVALID_LOCATION_TO);
                            }

                            $options += $trackingMovementService->treatTrackingData($mvt, $request->files, $index);

                            $createdMvt = $trackingMovementService->createTrackingMovement(
                                //either reference or article
                                $mvt["ref_article"],
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

                            if($type->getCode() === TrackingMovement::TYPE_PRISE && $associatedPack?->getArticle()?->getCurrentLogisticUnit()) {
                                $movement = $trackingMovementService->persistTrackingMovement(
                                    $entityManager,
                                    $associatedPack,
                                    $location,
                                    $nomadUser,
                                    $date,
                                    true,
                                    TrackingMovement::TYPE_PICK_LU,
                                    false,
                                    $options
                                )['movement'];
                                $logisticUnit = $associatedPack->getArticle()->getCurrentLogisticUnit();
                                $movement->setLogisticUnitParent($logisticUnit);
                                $createdMvt->setMainMovement($movement);
                                $associatedPack->getArticle()->setCurrentLogisticUnit(null);
                                $logisticUnit->setQuantity($logisticUnit->getQuantity() - $associatedPack->getQuantity());
                                $trackingMovementService->persistSubEntities($entityManager, $movement);
                                $entityManager->persist($movement);
                                $numberOfRowsInserted++;
                            }

                            if ($associatedPack) {
                                $associatedGroup = $associatedPack->getParent();

                                if ($associatedGroup) {
                                    $associatedGroup->removeChild($associatedPack);
                                    if ($associatedGroup->getChildren()->isEmpty()) {
                                        $emptyGroups[] = $associatedGroup->getCode();
                                    }
                                }
                            }

                            $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                            $entityManager->persist($createdMvt);
                            $numberOfRowsInserted++;

                            if ($type?->getCode() === TrackingMovement::TYPE_DEPOSE) {
                                $finishMouvementTraca[] = $mvt['ref_article'];
                            }
                        }
                    }
                });
            } catch (Throwable $throwable) {
                if (!$entityManager->isOpen()) {
                    /** @var EntityManagerInterface $entityManager */
                    $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                    $entityManager->clear();
                    $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
                    $statutRepository = $entityManager->getRepository(Statut::class);
                    $nomadUser = $utilisateurRepository->find($nomadUser->getId());
                    $trackingTypes = [];
                    $mustReloadLocation = true;
                    $trackingMovementService->stockStatuses = [];
                }

                if ($throwable->getMessage() === TrackingMovementService::INVALID_LOCATION_TO) {
                    $successData['data']['errors'][$mvt['ref_article']] = ($mvt['ref_article'] . " doit être déposé sur l'emplacement \"$invalidLocationTo\"");
                } else if ($throwable->getMessage() === Pack::PACK_IS_GROUP) {
                    $successData['data']['errors'][$mvt['ref_article']] = 'L\'unité logistique scannée est un groupe';
                } else {
                    $exceptionLoggerService->sendLog($throwable, $request);
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

        if (!empty($emptyGroups)) {
            $successData['data']['emptyGroups'] = $emptyGroups;
        }

        $response->setContent(json_encode($successData));
        return $response;
    }

    /**
     * @Rest\Post("/api/beginPrepa", name="api-begin-prepa", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function beginPrepa(Request $request,
                               EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $id = $request->request->get('id');
        $preparationRepository = $entityManager->getRepository(Preparation::class);
        $preparation = $preparationRepository->find($id);
        $data = [];

        if ($preparation->getStatut()?->getCode() == Preparation::STATUT_A_TRAITER ||
            $preparation->getUtilisateur() === $nomadUser) {
            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['msg'] = "Cette préparation a déjà été prise en charge par un opérateur.";
            $data['data'] = [];
        }

        return $this->json($data);
    }

    /**
     * @Rest\Post("/api/finishPrepa", name="api-finish-prepa", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishPrepa(Request $request,
                                ExceptionLoggerService $exceptionLoggerService,
                                LivraisonsManagerService $livraisonsManager,
                                TrackingMovementService $trackingMovementService,
                                PreparationsManagerService $preparationsManager,
                                EntityManagerInterface $entityManager)
    {
        $insertedPrepasIds = [];
        $statusCode = Response::HTTP_OK;

        $nomadUser = $this->getUser();

        $articleRepository = $entityManager->getRepository(Article::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);

        $resData = ['success' => [], 'errors' => [], 'data' => []];

        $preparations = json_decode($request->request->get('preparations'), true);

        // on termine les préparations
        // même comportement que LivraisonController.new()
        foreach ($preparations as $preparationArray) {
            $preparation = $preparationRepository->find($preparationArray['id']);
            if ($preparation) {
                // if it has not been begun
                try {
                    $dateEnd = DateTime::createFromFormat(DateTimeInterface::ATOM, $preparationArray['date_end']);
                    // flush auto at the end
                    $entityManager->transactional(function () use (
                        &$insertedPrepasIds,
                        $preparationsManager,
                        $livraisonsManager,
                        $preparationArray,
                        $preparation,
                        $nomadUser,
                        $dateEnd,
                        $entityManager,
                        $trackingMovementService
                    ) {

                        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                        $articleRepository = $entityManager->getRepository(Article::class);
                        $ligneArticlePreparationRepository = $entityManager->getRepository(PreparationOrderReferenceLine::class);
                        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

                        $preparationsManager->setEntityManager($entityManager);
                        $mouvementsNomade = $preparationArray['mouvements'];
                        $totalQuantitiesWithRef = [];
                        $livraison = $livraisonsManager->createLivraison($dateEnd, $preparation, $entityManager);
                        $emplacementPrepa = $emplacementRepository->findOneBy(['label' => $preparationArray['emplacement']]);
                        foreach ($mouvementsNomade as $mouvementNomade) {
                            if (!$mouvementNomade['is_ref'] && $mouvementNomade['selected_by_article']) {
                                /** @var Article $article */
                                $article = $articleRepository->findOneByReference($mouvementNomade['reference']);
                                $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                                if (!isset($totalQuantitiesWithRef[$refArticle->getReference()])) {
                                    $totalQuantitiesWithRef[$refArticle->getReference()] = 0;
                                }
                                $totalQuantitiesWithRef[$refArticle->getReference()] += $mouvementNomade['quantity'];
                            }
                            $preparationsManager->treatMouvementQuantities($mouvementNomade, $preparation);
                        }

                        $articlesToKeep = $preparationsManager->createMouvementsPrepaAndSplit($preparation, $nomadUser, $entityManager);
                        $movementRepository = $entityManager->getRepository(MouvementStock::class);
                        $movements = $movementRepository->findByPreparation($preparation);

                        foreach ($movements as $movement) {
                            if ($movement->getType() === MouvementStock::TYPE_TRANSFER) {
                                $preparationsManager->createMovementLivraison(
                                    $entityManager,
                                    $movement->getQuantity(),
                                    $nomadUser,
                                    $livraison,
                                    !empty($movement->getRefArticle()),
                                    $movement->getRefArticle() ?? $movement->getArticle(),
                                    $preparation,
                                    false,
                                    $emplacementPrepa
                                );
                                $code = $movement->getRefArticle() ? $movement->getRefArticle()->getBarCode() : $movement->getArticle()->getBarCode();
                                $trackingMovementPick = $trackingMovementService->createTrackingMovement(
                                    $code,
                                    $movement->getEmplacementFrom(),
                                    $nomadUser,
                                    $dateEnd,
                                    true,
                                    true,
                                    TrackingMovement::TYPE_PRISE,
                                    [
                                        'mouvementStock' => $movement,
                                        'preparation' => $preparation,
                                    ]
                                );
                                $entityManager->persist($trackingMovementPick);
                                $entityManager->flush();
                                $trackingMovementDrop = $trackingMovementService->createTrackingMovement(
                                    $code,
                                    $movement->getEmplacementTo(),
                                    $nomadUser,
                                    $dateEnd,
                                    true,
                                    true,
                                    TrackingMovement::TYPE_DEPOSE,
                                    [
                                        'mouvementStock' => $movement,
                                        'preparation' => $preparation,
                                    ],
                                );

                                $entityManager->persist($trackingMovementDrop);
                                $ulToMove[] = $movement->getArticle()?->getCurrentLogisticUnit();
                                $entityManager->flush();
                            }
                        }
                        if (isset($ulToMove)){
                            foreach (array_unique($ulToMove) as $lu) {
                                if ($lu != null){
                                    $pickTrackingMovement = $trackingMovementService->createTrackingMovement(
                                        $lu,
                                        $lu->getLastDrop()->getEmplacement(),
                                        $nomadUser,
                                        $dateEnd,
                                        true,
                                        true,
                                        TrackingMovement::TYPE_PRISE,
                                        ['preparation' => $preparation]

                                    );
                                    $DropTrackingMovement = $trackingMovementService->createTrackingMovement(
                                        $lu,
                                        $emplacementPrepa,
                                        $nomadUser,
                                        $dateEnd,
                                        true,
                                        true,
                                        TrackingMovement::TYPE_DEPOSE,
                                        ['preparation' => $preparation]
                                    );
                                    $entityManager->persist($pickTrackingMovement);
                                    $entityManager->persist($DropTrackingMovement);

                                    $lu->setLastDrop($DropTrackingMovement)->setLastTracking($DropTrackingMovement);
                                }
                            }
                        }

                        foreach ($totalQuantitiesWithRef as $ref => $quantity) {
                            $refArticle = $referenceArticleRepository->findOneBy(['reference' => $ref]);
                            $ligneArticle = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($refArticle, $preparation);
                            $preparationsManager->deleteLigneRefOrNot($ligneArticle, $preparation, $entityManager);
                        }

                        $insertedPreparation = $preparationsManager->treatPreparation($preparation, $nomadUser, $emplacementPrepa, [
                            "articleLinesToKeep" => $articlesToKeep,
                            "entityManager" => $entityManager
                        ]);

                        if ($insertedPreparation) {
                            $insertedPrepasIds[] = $insertedPreparation->getId();
                        }

                        if ($emplacementPrepa) {
                            $preparationsManager->closePreparationMouvement($preparation, $dateEnd, $emplacementPrepa);
                        } else {
                            throw new Exception(PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
                        }

                        $entityManager->flush();
                        if($livraison->getDemande()->getType()->isNotificationsEnabled()) {
                            $this->notificationService->toTreat($livraison);
                        }
                        $preparationsManager->updateRefArticlesQuantities($preparation, $entityManager);
                    });

                    $resData['success'][] = [
                        'numero_prepa' => $preparation->getNumero(),
                        'id_prepa' => $preparation->getId(),
                    ];
                } catch (Throwable $throwable) {
                    // we create a new entity manager because transactional() can call close() on it if transaction failed
                    if (!$entityManager->isOpen()) {
                        /** @var EntityManagerInterface $entityManager */
                        $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                        $preparationsManager->setEntityManager($entityManager);
                    }
                    $message = (
                    ($throwable instanceof NegativeQuantityException) ? "Une quantité en stock d\'un article est inférieure à sa quantité prélevée" :
                        (($throwable->getMessage() === PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION) ? "L'emplacement que vous avez sélectionné n'existe plus." :
                            (($throwable->getMessage() === PreparationsManagerService::ARTICLE_ALREADY_SELECTED) ? "L'article n'est pas sélectionnable" :
                                false))
                    );

                    if (!$message) {
                        $exceptionLoggerService->sendLog($throwable, $request);
                    }

                    $resData['errors'][] = [
                        'numero_prepa' => $preparation->getNumero(),
                        'id_prepa' => $preparation->getId(),
                        'message' => $message ?: 'Une erreur est survenue',
                    ];
                }
            }
        }

        if (!empty($insertedPrepasIds)) {
            $globalsParametersRepository = $entityManager->getRepository(Setting::class);
            $displayPickingLocation = $globalsParametersRepository->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION);

            $resData['data']['preparations'] = Stream::from($preparationRepository->getMobilePreparations($nomadUser, $insertedPrepasIds, $displayPickingLocation))
                ->map(function ($preparationArray) {
                    if (!empty($preparationArray['comment'])) {
                        $preparationArray['comment'] = substr(strip_tags($preparationArray['comment']), 0, 200);
                    }
                    return $preparationArray;
                })
                ->toArray();
            $resData['data']['articlesPrepa'] = $this->getArticlesPrepaArrays($entityManager, $insertedPrepasIds, true);
            $resData['data']['articlesPrepaByRefArticle'] = $articleRepository->getArticlePrepaForPickingByUser($nomadUser, $insertedPrepasIds);
        }

        $preparationsManager->removeRefMouvements();
        $entityManager->flush();

        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/beginLivraison", name="api-begin-livraison", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function beginLivraison(Request                  $request,
                                   EntityManagerInterface   $entityManager,
                                   TranslationService       $translation)
    {
        $nomadUser = $this->getUser();

        $livraisonRepository = $entityManager->getRepository(Livraison::class);

        $id = $request->request->get('id');
        $livraison = $livraisonRepository->find($id);

        $data = [];

        if ($livraison->getStatut()?->getCode() == Livraison::STATUT_A_TRAITER &&
            (empty($livraison->getUtilisateur()) || $livraison->getUtilisateur() === $nomadUser)) {
            // modif de la livraison
            $livraison->setUtilisateur($nomadUser);

            $entityManager->flush();

            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['msg'] = "Cette " . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)) . " a déjà été prise en charge par un opérateur.";
        }

        return new JsonResponse($data);
    }

    /**
     * @Rest\Post("/api/beginCollecte", name="api-begin-collecte", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function beginCollecte(Request $request,
                                  EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);

        $id = $request->request->get('id');
        $ordreCollecte = $ordreCollecteRepository->find($id);

        $data = [];

        if ($ordreCollecte->getStatut()?->getCode() == OrdreCollecte::STATUT_A_TRAITER &&
            (empty($ordreCollecte->getUtilisateur()) || $ordreCollecte->getUtilisateur() === $nomadUser)) {
            // modif de la collecte
            $ordreCollecte->setUtilisateur($nomadUser);

            $entityManager->flush();

            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['msg'] = "Cette collecte a déjà été prise en charge par un opérateur.";
        }

        return new JsonResponse($data);
    }

    /**
     * @Rest\Post("/api/handlings", name="api-validate-handling", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function postHandlings(Request $request,
                                  AttachmentService $attachmentService,
                                  EntityManagerInterface $entityManager,
                                  FreeFieldService $freeFieldService,
                                  StatusService $statusService,
                                  HandlingService $handlingService,
                                  StatusHistoryService $statusHistoryService)
    {
        $nomadUser = $this->getUser();

        $handlingRepository = $entityManager->getRepository(Handling::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $data = [];

        $commentaire = $request->request->get('commentaire');
        $id = $request->request->get('id');
        /** @var Handling $handling */
        $handling = $handlingRepository->find($id);
        $oldStatus = $handling->getStatus();

        if (!$oldStatus || !$oldStatus->isTreated()) {
            $statusId = $request->request->get('statusId');
            $newStatus = $statusRepository->find($statusId);
            if (!empty($newStatus)) {
                $statusHistoryService->updateStatus($entityManager, $handling, $newStatus);
            }

            $treatmentDelay = $request->request->get('treatmentDelay');
            if (!empty($commentaire)) {
                $previousComments = $handling->getComment() !== '<p><br></p>' ? "{$handling->getComment()}\n" : "";
                $dateStr = (new DateTime())->format('d/m/y H:i:s');
                $dateAndUser = "<strong>$dateStr - {$nomadUser->getUsername()} :</strong>";
                $commentaire = StringHelper::cleanedComment($commentaire);
                $handling->setComment("$previousComments $dateAndUser $commentaire");
            }

            if (!empty($treatmentDelay)) {
                $handling->setTreatmentDelay($treatmentDelay);
            }

            $maxNbFilesSubmitted = 10;
            $fileCounter = 1;
            // upload of photo_1 to photo_10
            do {
                $photoFile = $request->files->get("photo_$fileCounter");
                if (!empty($photoFile)) {
                    $attachments = $attachmentService->createAttachements([$photoFile]);
                    if (!empty($attachments)) {
                        $handling->addAttachment($attachments[0]);
                        $entityManager->persist($attachments[0]);
                    }
                }
                $fileCounter++;
            } while (!empty($photoFile) && $fileCounter <= $maxNbFilesSubmitted);

            $freeFieldValuesStr = $request->request->get('freeFields', '{}');
            $freeFieldValuesStr = json_decode($freeFieldValuesStr, true);
            $freeFieldService->manageFreeFields($handling, $freeFieldValuesStr, $entityManager);

            if (!$handling->getValidationDate()
                && $newStatus) {
                if ($newStatus->isTreated()) {
                    $handling
                        ->setValidationDate(new DateTime('now'));
                }
                $handling->setTreatedByHandling($nomadUser);
            }
            $entityManager->flush();

            if ((!$oldStatus && $newStatus)
                || (
                    $oldStatus
                    && $newStatus
                    && ($oldStatus->getId() !== $newStatus->getId())
                )) {
                $viewHoursOnExpectedDate = !$settingRepository->getOneParamByLabel(Setting::REMOVE_HOURS_DATETIME);
                $handlingService->sendEmailsAccordingToStatus($entityManager, $handling, $viewHoursOnExpectedDate);
            }

            $data['success'] = true;
            $data['state'] = $statusService->getStatusStateCode($handling->getStatus()->getState());
            $data['freeFields'] = json_encode($handling->getFreeFields());
        } else {
            $data['success'] = false;
            $data['message'] = "Cette demande de service a déjà été prise en charge par un opérateur.";
        }

        return new JsonResponse($data);
    }

    /**
     * @Rest\Post("/api/finishLivraison", name="api-finish-livraison", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishLivraison(Request                     $request,
                                    ExceptionLoggerService      $exceptionLoggerService,
                                    EntityManagerInterface      $entityManager,
                                    LivraisonsManagerService    $livraisonsManager,
                                    TranslationService          $translation)
    {
        $nomadUser = $this->getUser();

        $statusCode = Response::HTTP_OK;
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $livraisons = json_decode($request->request->get('livraisons'), true);
        $resData = ['success' => [], 'errors' => []];

        // on termine les livraisons
        // même comportement que LivraisonController.finish()
        foreach ($livraisons as $livraisonArray) {
            $livraison = $livraisonRepository->find($livraisonArray['id']);

            if ($livraison) {
                $dateEnd = DateTime::createFromFormat(DateTimeInterface::ATOM, $livraisonArray['date_end']);
                $location = $emplacementRepository->findOneBy(['label' => $livraisonArray['location']]);
                try {
                    if ($location) {
                        // flush auto at the end
                        $livraisonsManager->setEntityManager($entityManager);
                        $livraisonsManager->finishLivraison($nomadUser, $livraison, $dateEnd, $location, true);
                        $entityManager->flush();

                        $resData['success'][] = [
                            'numero_livraison' => $livraison->getNumero(),
                            'id_livraison' => $livraison->getId(),
                        ];
                    } else {
                        throw new Exception(LivraisonsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
                    }
                } catch (Throwable $throwable) {
                    // we create a new entity manager because transactional() can call close() on it if transaction failed
                    if (!$entityManager->isOpen()) {
                        $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                        $livraisonsManager->setEntityManager($entityManager);
                    }

                    $message = match ($throwable->getMessage()) {
                        LivraisonsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION => "L'emplacement que vous avez sélectionné n'existe plus.",
                        LivraisonsManagerService::LIVRAISON_ALREADY_BEGAN => "La " . mb_strtolower($translation->translate("Ordre", "Livraison", "Livraison", false)) . " a déjà été commencée",
                        default => false,
                    };

                    if($throwable->getCode() === LivraisonsManagerService::NATURE_NOT_ALLOWED){
                        $message = $throwable->getMessage();
                    }

                    if (!$message) {
                        $exceptionLoggerService->sendLog($throwable, $request);
                    }

                    $resData['errors'][] = [
                        'numero_livraison' => $livraison->getNumero(),
                        'id_livraison' => $livraison->getId(),

                        'message' => $message ?: 'Une erreur est survenue',
                    ];
                }
            }
        }

        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Get("/api/check-logistic-unit-content", name="api_check_logistic_unit_content", methods={"GET"},condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function checkLogisticUnitContent(Request                  $request,
                                             ExceptionLoggerService   $exceptionLoggerService,
                                             EntityManagerInterface   $entityManager,
                                             LivraisonsManagerService $livraisonsManager): Response
    {
        $logisticUnit = $entityManager->getRepository(Pack::class)->findOneBy(['code' => $request->query->get('logisticUnit')]);
        $articlesAllInTransit = Stream::from($logisticUnit->getChildArticles())->every(fn(Article $article) => $article->isInTransit());
        $articlesNotInTransit = [];
        if (!$articlesAllInTransit) {

            $articlesNotInTransit = Stream::from($logisticUnit->getChildArticles())
                    ->filter(fn(Article $article) => !$article->isInTransit())
                    ->map(fn(Article $article) => [
                        'barcode' => $article->getBarCode(),
                        'reference' => $article->getReference(),
                        'quantity' => $article->getQuantite(),
                        'label' => $article->getLabel(),
                        'location' => $article->getEmplacement()->getLabel(),
                        'currentLogisticUnitCode' => $article->getCurrentLogisticUnit()->getCode(),
                        'selected' => true
                    ])
                    ->values();
        }

        return $this->json([
            'extraArticles' => $articlesNotInTransit
        ]);
    }

    /**
     * @Rest\Get("/api/check-delivery-logistic-unit-content", name="api_check_delivery_logistic_unit_content", methods={"GET"},condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function checkDeliveryLogisticUnitContent(Request                  $request,
                                             ExceptionLoggerService   $exceptionLoggerService,
                                             EntityManagerInterface   $entityManager,
                                             LivraisonsManagerService $livraisonsManager): Response
    {
        $delivery = $entityManager->getRepository(Livraison::class)->findOneBy(['id' => $request->query->get('livraisonId')]);

        $articlesLines = $delivery->getDemande()->getArticleLines();
        $numberArticlesInLU = Stream::from($articlesLines)
            ->filter(fn(DeliveryRequestArticleLine $line) => $line->getPack())
            ->keymap(fn(DeliveryRequestArticleLine $line) => [$line->getPack()->getCode(), $line->getPack()->getChildArticles()->count()])
            ->toArray();

        return $this->json([
            'numberArticlesInLU' => $numberArticlesInLU
        ]);
    }

    /**
     * @Rest\Post("/api/group-trackings/{trackingMode}", name="api_post_pack_groups", condition="request.isXmlHttpRequest()", requirements={"trackingMode": "picking|drop"})
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function postGroupedTracking(Request $request,
                                        AttachmentService $attachmentService,
                                        EntityManagerInterface $entityManager,
                                        TrackingMovementService $trackingMovementService,
                                        string $trackingMode): JsonResponse {

        /** @var Utilisateur $nomadUser */
        $operator = $this->getUser();
        $packRepository = $entityManager->getRepository(Pack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $movementsStr = $request->request->get('mouvements');
        $movements = json_decode($movementsStr, true);

        $finishedMovements = ($trackingMode === 'drop');
        $movementType = $trackingMode === 'drop' ? TrackingMovement::TYPE_DEPOSE : TrackingMovement::TYPE_PRISE;

        $groupsArray = Stream::from($movements)
            ->map(function($movement) {
                $date = explode('+', $movement['date']);
                $date = $date[0] ?? $movement['date'];
                return [
                    'code' => $movement['ref_article'],
                    'location' => $movement['ref_emplacement'],
                    'nature_id' => $movement['nature_id'],
                    'date' => new DateTime($date ?? 'now'),
                    'type' => $movement['type'],
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

                    if ($finishedMovements) {
                        $res['finishedMovements'][] = $trackingMovementService->finishTrackingMovement($parent->getLastTracking());
                    }

                    /** @var Pack $child */
                    foreach ($parent->getChildren() as $child) {
                        if ($finishedMovements) {
                            $res['finishedMovements'][] = $trackingMovementService->finishTrackingMovement($child->getLastTracking());
                        }

                        $trackingMovement = $trackingMovementService->createTrackingMovement(
                            $child,
                            $location,
                            $operator,
                            $serializedGroup['date'],
                            true,
                            $finishedMovements,
                            $movementType,
                            array_merge(['parent' => $parent], $options)
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
                        $finishedMovements,
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
                        $attachments = $attachmentService->createAttachements($fileNames);
                        foreach ($attachments as $attachment) {
                            $entityManager->persist($attachment);
                            $movement->addAttachment($attachment);
                        }
                    }

                    $res['finishedMovements'] = Stream::from($res['finishedMovements'])
                        ->filter()
                        ->unique()
                        ->values();
                }
            }

            $entityManager->flush();

            $res['tracking'] = $trackingMovementService->getMobileUserPicking($entityManager, $operator);
        }
        catch (Throwable $throwable) {
            $res['success'] = false;
            $res['message'] = "Une erreur est survenue lors de l'enregistrement d'un mouvement";
        }

        return $this->json($res);
    }

    /**
     * @Rest\Post("/api/finishCollecte", name="api-finish-collecte", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishCollecte(Request $request,
                                   ExceptionLoggerService $exceptionLoggerService,
                                   OrdreCollecteService $ordreCollecteService,
                                   EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $statusCode = Response::HTTP_OK;

        $resData = ['success' => [], 'errors' => [], 'data' => []];

        $collectes = json_decode($request->request->get('collectes'), true);
        if (!$collectes) {
            $jsonData = json_decode($request->getContent(), true);
            $collectes = $jsonData['collectes'];
        }
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $refArticlesRepository = $entityManager->getRepository(ReferenceArticle::class);
        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        // on termine les collectes
        foreach ($collectes as $collecteArray) {
            $collecte = $ordreCollecteRepository->find($collecteArray['id']);
            try {
                $entityManager->transactional(function ()
                use (
                    $entityManager,
                    $collecteArray,
                    $collecte,
                    $nomadUser,
                    &$resData,
                    $trackingMovementRepository,
                    $articleRepository,
                    $refArticlesRepository,
                    $ordreCollecteRepository,
                    $emplacementRepository,
                    $ordreCollecteService
                ) {
                    $ordreCollecteService->setEntityManager($entityManager);
                    $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $collecteArray['date_end']);

                    foreach ($collecteArray['mouvements'] as $collectMovement) {
                        if ($collectMovement['is_ref'] == 0) {
                            $barcode = $collectMovement['barcode'];
                            $pickedQuantity = $collectMovement['quantity'];
                            if ($barcode) {
                                $isInCollect = !$collecte
                                    ->getArticles()
                                    ->filter(fn(Article $article) => $article->getBarCode() === $barcode)
                                    ->isEmpty();

                                if (!$isInCollect) {
                                    /** @var Article $article */
                                    $article = $articleRepository->findOneBy(['barCode' => $barcode]);
                                    if ($article) {
                                        $article->setQuantite($pickedQuantity);
                                        $collecte->addArticle($article);

                                        $referenceArticle = $article->getArticleFournisseur()->getReferenceArticle();
                                        foreach ($collecte->getOrdreCollecteReferences() as $ordreCollecteReference) {
                                            if ($ordreCollecteReference->getReferenceArticle() === $referenceArticle) {
                                                $ordreCollecteReference->setQuantite($ordreCollecteReference->getQuantite() - $pickedQuantity);
                                                if ($ordreCollecteReference->getQuantite() === 0) {
                                                    $collecte->removeOrdreCollecteReference($ordreCollecteReference);
                                                    $entityManager->remove($ordreCollecteReference);
                                                }
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $newCollecte = $ordreCollecteService->finishCollecte($collecte, $nomadUser, $date, $collecteArray['mouvements'], true);
                    $entityManager->flush();

                    if (!empty($newCollecte)) {
                        $newCollecteId = $newCollecte->getId();
                        $newCollecteArray = $ordreCollecteRepository->getById($newCollecteId);

                        $articlesCollecte = $articleRepository->getByOrdreCollecteId($newCollecteId);
                        $refArticlesCollecte = $refArticlesRepository->getByOrdreCollecteId($newCollecteId);
                        $articlesCollecte = array_merge($articlesCollecte, $refArticlesCollecte);
                    }

                    $resData['success'][] = [
                        'numero_collecte' => $collecte->getNumero(),
                        'id_collecte' => $collecte->getId(),
                    ];

                    $newTakings = $trackingMovementRepository->getPickingByOperatorAndNotDropped(
                        $nomadUser,
                        TrackingMovementRepository::MOUVEMENT_TRACA_STOCK,
                        [$collecte->getId()]
                    );

                    if (!empty($newTakings)) {
                        if (!isset($resData['data']['stockTakings'])) {
                            $resData['data']['stockTakings'] = [];
                        }
                        array_push(
                            $resData['data']['stockTakings'],
                            ...$newTakings
                        );
                    }

                    if (isset($newCollecteArray)) {
                        if (!isset($resData['data']['newCollectes'])) {
                            $resData['data']['newCollectes'] = [];
                        }
                        $resData['data']['newCollectes'][] = $newCollecteArray;
                    }

                    if (!empty($articlesCollecte)) {
                        if (!isset($resData['data']['articlesCollecte'])) {
                            $resData['data']['articlesCollecte'] = [];
                        }
                        array_push(
                            $resData['data']['articlesCollecte'],
                            ...$articlesCollecte
                        );
                    }
                });
            } catch (Throwable $throwable) {
                // we create a new entity manager because transactional() can call close() on it if transaction failed
                if (!$entityManager->isOpen()) {
                    $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                    $ordreCollecteService->setEntityManager($entityManager);

                    $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
                    $articleRepository = $entityManager->getRepository(Article::class);
                    $refArticlesRepository = $entityManager->getRepository(ReferenceArticle::class);
                    $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
                    $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                }

                $user = $collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : '';

                $message = (
                ($throwable instanceof ArticleNotAvailableException) ? ("Une référence de la collecte n'est pas active, vérifiez les transferts de stock en cours associés à celle-ci.") :
                    (($throwable->getMessage() === OrdreCollecteService::COLLECTE_ALREADY_BEGUN) ? ("La collecte " . $collecte->getNumero() . " a déjà été effectuée (par " . $user . ").") :
                        (($throwable->getMessage() === OrdreCollecteService::COLLECTE_MOUVEMENTS_EMPTY) ? ("La collecte " . $collecte->getNumero() . " ne contient aucun article.") :
                            false))
                );

                if (!$message) {
                    $exceptionLoggerService->sendLog($throwable, $request);
                }

                $resData['errors'][] = [
                    'numero_collecte' => $collecte->getNumero(),
                    'id_collecte' => $collecte->getId(),

                    'message' => $message ?: 'Une erreur est survenue',
                ];
            }
        }

        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/valider-manual-dl", name="api_validate_manual_dl", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function validateManualDL(Request                    $request,
                                     EntityManagerInterface     $entityManager,
                                     DeliveryRequestService     $demandeLivraisonService,
                                     LivraisonsManagerService   $livraisonsManagerService,
                                     MouvementStockService      $mouvementStockService,
                                     FreeFieldService           $freeFieldService,
                                     TrackingMovementService    $trackingMovementService,
                                     PreparationsManagerService $preparationsManagerService): Response
    {
        $articleRepository = $entityManager->getRepository(Article::class);
        $logisticUnitRepository = $entityManager->getRepository(Pack::class);

        $nomadUser = $this->getUser();
        $location = json_decode($request->request->get('location'), true);
        $delivery = json_decode($request->request->get('delivery'), true);
        $now = new DateTime();

        $request = $demandeLivraisonService->newDemande([
            'isManual' => true,
            'type' => $delivery['type'],
            'demandeur' => $nomadUser,
            'destination' => $location['id'],
            'expectedAt' => $delivery['expectedAt'] ?? $now->format('Y-m-d'),
            'project' => $delivery['project'] ?? null,
            'commentaire' => $delivery['comment'] ?? null,
        ], $entityManager, $freeFieldService, true);

        $entityManager->persist($request);

        $location = $entityManager->find(Emplacement::class, $location['id']);
        foreach ($delivery['articles'] as $article) {
            $barcode = $article['barCode'];
            $article = $articleRepository->findOneBy(['barCode' => $barcode]);

            if(!$article) {
                $logisticUnit = $logisticUnitRepository->findOneBy(['code' => $barcode]);
            }

            $articles = $article ? [$article] : $logisticUnit->getChildArticles();
            foreach ($articles as $art) {
                $line = $demandeLivraisonService->createArticleLine($art, $request, [
                    'quantityToPick' => $art->getQuantite(),
                    'pickedQuantity' => $art->getQuantite(),
                ]);
                $entityManager->persist($line);
            }
        }

        $entityManager->flush();
        $response = $demandeLivraisonService->checkDLStockAndValidate(
            $entityManager,
            ['demande' => $request],
            false,
            $freeFieldService,
            false,
            true
        );

        if (!$response['success']) {
            $entityManager->remove($request);
            $entityManager->flush();
            return new JsonResponse($response);
        }
        $preparationOrder = $request->getPreparations()->first();
        $deliveryOrder = $livraisonsManagerService->createLivraison($now, $preparationOrder, $entityManager);

        // articles in delivery  which the logistics unit is not in delivery
        foreach ($delivery['articles'] as $articleArray) {
            $barcode = $articleArray['barCode'];
            $article = $articleRepository->findOneBy(['barCode' => $articleArray['barCode']]);
            if($article && $article->getCurrentLogisticUnit()) {
                $article->setCurrentLogisticUnit(null);

                $trackingMovementService->persistTrackingMovement(
                    $entityManager,
                    $article->getTrackingPack() ?? $barcode,
                    $location,
                    $nomadUser,
                    $now,
                    false,
                    TrackingMovement::TYPE_PICK_LU,
                    false,
                    ["delivery" => $deliveryOrder]
                );
            }
        }

        foreach ($request->getArticleLines() as $articleLine) {
            $article = $articleLine->getArticle();
            $outMovement = $preparationsManagerService->createMovementLivraison(
                $entityManager,
                $article->getQuantite(),
                $nomadUser,
                $deliveryOrder,
                false,
                $article,
                $preparationOrder,
                true,
                $article->getEmplacement()
            );
            $entityManager->persist($outMovement);
            $mouvementStockService->finishMouvementStock($outMovement, $now, $request->getDestination());
        }
        $preparationsManagerService->treatPreparation($preparationOrder, $nomadUser, $request->getDestination(), [
            "entityManager" => $entityManager,
            "changeArticleLocation" => false,
        ]);
        $preparationsManagerService->updateRefArticlesQuantities($preparationOrder, $entityManager);
        $livraisonsManagerService->finishLivraison($nomadUser, $deliveryOrder, $now, $request->getDestination());
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
        ]);
    }

    /**
     * @Rest\Post("/api/valider-dl", name="api_validate_dl", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function checkAndValidateDL(Request                $request,
                                       EntityManagerInterface $entityManager,
                                       DeliveryRequestService $demandeLivraisonService,
                                       FreeFieldService       $champLibreService): Response
    {
        $nomadUser = $this->getUser();

        $demandeArray = json_decode($request->request->get('demande'), true);
        $demandeArray['demandeur'] = $nomadUser;

        $freeFields = json_decode($demandeArray["freeFields"], true);

        if (is_array($freeFields)) {
            foreach ($freeFields as $key => $value) {
                $demandeArray[(int)$key] = $value;
            }
        }

        unset($demandeArray["freeFields"]);

        $responseAfterQuantitiesCheck = $demandeLivraisonService->checkDLStockAndValidate(
            $entityManager,
            $demandeArray,
            true,
            $champLibreService
        );

        $responseAfterQuantitiesCheck['nomadMessage'] = $responseAfterQuantitiesCheck['nomadMessage']
            ?? $responseAfterQuantitiesCheck['msg']
            ?? '';

        return new JsonResponse($responseAfterQuantitiesCheck);
    }

    /**
     * @Rest\Post("/api/addInventoryEntries", name="api-add-inventory-entry", condition="request.isXmlHttpRequest()")
     * @Rest\Get("/api/addInventoryEntries")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function addInventoryEntries(Request $request, EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
        $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $numberOfRowsInserted = 0;

        $entries = json_decode($request->request->get('entries'), true);
        $newAnomalies = [];

        foreach ($entries as $entry) {
            $mission = $inventoryMissionRepository->find($entry['mission_id']);
            $location = $emplacementRepository->findOneBy(['label' => $entry['location']]);

            $articleToInventory = $entry['is_ref']
                ? $referenceArticleRepository->findOneBy(['barCode' => $entry['bar_code']])
                : $articleRepository->findOneBy(['barCode' => $entry['bar_code']]);

            $criteriaInventoryEntry = ['mission' => $mission];

            if (isset($articleToInventory)) {
                if ($articleToInventory instanceof ReferenceArticle) {
                    $criteriaInventoryEntry['refArticle'] = $articleToInventory;
                } else { // ($articleToInventory instanceof Article)
                    $criteriaInventoryEntry['article'] = $articleToInventory;
                }
            }

            $inventoryEntry = $inventoryEntryRepository->findOneBy($criteriaInventoryEntry);

            // On inventorie l'article seulement si les infos sont valides et si aucun inventaire de l'article
            // n'a encore été fait sur cette mission
            if (isset($mission) &&
                isset($location) &&
                isset($articleToInventory) &&
                !isset($inventoryEntry)) {
                $newDate = new DateTime($entry['date']);
                $inventoryEntry = new InventoryEntry();
                $inventoryEntry
                    ->setMission($mission)
                    ->setDate($newDate)
                    ->setQuantity($entry['quantity'])
                    ->setOperator($nomadUser)
                    ->setLocation($location);

                if ($articleToInventory instanceof ReferenceArticle) {
                    $inventoryEntry->setRefArticle($articleToInventory);
                    $isAnomaly = ($inventoryEntry->getQuantity() !== $articleToInventory->getQuantiteStock());
                } else {
                    $inventoryEntry->setArticle($articleToInventory);
                    $isAnomaly = ($inventoryEntry->getQuantity() !== $articleToInventory->getQuantite());
                }
                $inventoryEntry->setAnomaly($isAnomaly);

                if (!$isAnomaly) {
                    $articleToInventory->setDateLastInventory($newDate);
                }
                $entityManager->persist($inventoryEntry);

                if ($inventoryEntry->getAnomaly()) {
                    $newAnomalies[] = $inventoryEntry;
                }
                $numberOfRowsInserted++;
            }
        }
        $entityManager->flush();

        $newAnomaliesIds = array_map(
            function (InventoryEntry $inventory) {
                return $inventory->getId();
            },
            $newAnomalies
        );

        $s = $numberOfRowsInserted > 1 ? 's' : '';
        $data['success'] = true;
        $data['data']['status'] = ($numberOfRowsInserted === 0)
            ? "Aucune saisie d'inventaire à synchroniser."
            : ($numberOfRowsInserted . ' inventaire' . $s . ' synchronisé' . $s);
        $data['data']['anomalies'] = array_merge(
            $inventoryEntryRepository->getAnomaliesOnRef(true, $newAnomaliesIds),
            $inventoryEntryRepository->getAnomaliesOnArt(true, $newAnomaliesIds)
        );

        return $this->json($data);
    }

    /**
     * @Rest\Get("/api/demande-livraison-data", name="api_get_demande_livraison_data")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getDemandeLivraisonData(UserService $userService, EntityManagerInterface $entityManager): Response
    {
        $nomadUser = $this->getUser();

        $dataResponse = [];
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $httpCode = Response::HTTP_OK;
        $dataResponse['success'] = true;

        $rights = $userService->getMobileRights($nomadUser);
        if ($rights['demande']) {
            $dataResponse['data'] = [
                'demandeLivraisonArticles' => $referenceArticleRepository->getByNeedsMobileSync(),
                'demandeLivraisonTypes' => array_map(function (Type $type) {
                    return [
                        'id' => $type->getId(),
                        'label' => $type->getLabel(),
                    ];
                }, $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON])),
            ];
        } else {
            $dataResponse['data'] = [
                'demandeLivraisonArticles' => [],
                'demandeLivraisonTypes' => [],
            ];
        }

        return new JsonResponse($dataResponse, $httpCode);
    }

    /**
     * @Rest\Get("/api/default-article-values", name="api_get_default_article_values")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getDefaultArticleValues(EntityManagerInterface $entityManager): Response
    {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $articleDefaultLocationId = $settingRepository->getOneParamByLabel(Setting::ARTICLE_LOCATION);
        $articleDefaultLocation = $articleDefaultLocationId ? $locationRepository->find($articleDefaultLocationId) : null;

        $defaultValues = [
            'destination' => $articleDefaultLocation?->getId(),
            'type' => $settingRepository->getOneParamByLabel(Setting::ARTICLE_TYPE),
            'reference' => $settingRepository->getOneParamByLabel(Setting::ARTICLE_REFERENCE),
            'label' => $settingRepository->getOneParamByLabel(Setting::ARTICLE_LABEL),
            'quantity' => $settingRepository->getOneParamByLabel(Setting::ARTICLE_QUANTITY),
            'supplier' => $settingRepository->getOneParamByLabel(Setting::ARTICLE_SUPPLIER),
            'supplierReference' => $settingRepository->getOneParamByLabel(Setting::ARTICLE_SUPPLIER_REFERENCE),
        ];

        return $this->json([
            'success' => true,
            'defaultValues' => $defaultValues
        ]);
    }

    /**
     * @Rest\Get("/api/article-by-rfid-tag/{rfid}", name="api_get_article_by_rfid_tag")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getArticleByRFIDTag(EntityManagerInterface $entityManager, string $rfid): Response
    {
        $article = $entityManager->getRepository(Article::class)->findOneBy([
            'RFIDtag' => $rfid
        ]);
        return $this->json([
            'success' => true,
            'article' => $article?->getId()
        ]);
    }

    /**
     * @Rest\Get("/api/supplier_reference/{ref}/{supplier}", name="api_get_supplier_reference_by_ref_and_supplier")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getArticleFournisseursByRefAndSupplier(EntityManagerInterface $entityManager, int $ref, int $supplier): Response
    {
        $articlesFournisseurs = $entityManager->getRepository(ArticleFournisseur::class)->getByRefArticleAndFournisseur($ref, $supplier);
        $formattedReferences = Stream::from($articlesFournisseurs)
            ->map(function(ArticleFournisseur $supplier) {
                return [
                    'label' => $supplier->getReference(),
                    'id' => $supplier->getId(),
                ];
            })->toArray();

        return $this->json([
            'supplierReferences' => $formattedReferences
        ]);
    }

    /**
     * @Rest\Post("/api/create-article", name="api_post_article")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function postArticle(Request $request,
                                EntityManagerInterface $entityManager,
                                ArticleDataService $articleDataService,
                                MouvementStockService $mouvementStockService,
                                TrackingMovementService $trackingMovementService): Response
    {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $nativeCountryRepository = $entityManager->getRepository(NativeCountry::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $supplierArticleRepository = $entityManager->getRepository(ArticleFournisseur::class);

        $rfidPrefix = $settingRepository->getOneParamByLabel(Setting::RFID_PREFIX);
        $defaultLocationId = $settingRepository->getOneParamByLabel(Setting::ARTICLE_LOCATION);
        $defaultLocation = $defaultLocationId ? $locationRepository->find($defaultLocationId) : null;

        $now = new DateTime('now');

        $rfidTag = $request->request->get('rfidTag');
        $countryStr = $request->request->get('country');
        $destinationStr = $request->request->get('destination');
        $referenceStr = $request->request->get('reference');

        if (empty($rfidTag)) {
            throw new FormException("Le tag RFID est invalide.");
        }

        if (!empty($rfidPrefix) && !str_starts_with($rfidTag, $rfidPrefix)) {
            throw new FormException("Le tag RFID ne respecte pas le préfixe paramétré ($rfidPrefix).");
        }
        $article = $articleRepository->findOneBy(['RFIDtag' => $rfidTag]);

        if ($article) {
            throw new FormException("Tag RFID déjà existant en base.");
        }

        $typeStr = $request->request->get('type');
        $type = $typeStr
            ? $typeRepository->find($typeStr)
            : null;

        $statut = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);

        $fromMatrix = $request->request->getBoolean('fromMatrix');
        $destination = !empty($destinationStr)
            ? ($fromMatrix
                ? ($locationRepository->findOneBy(['label' => $destinationStr]) ?: $defaultLocation)
                : $locationRepository->find($destinationStr))
            : null;

        if (!$destination) {
            throw new FormException("L'emplacement de destination de l'article est inconnu.");
        }

        $countryFrom = !empty($countryStr)
            ? $nativeCountryRepository->findOneBy(['code' => $countryStr])
            : null;
        if (!$countryFrom && $countryStr) {
            throw new FormException("Le code pays est inconnu");
        }

        if ($fromMatrix) {
            $ref = $referenceArticleRepository->findOneBy([
                'reference' => $referenceStr,
                'typeQuantite' => ReferenceArticle::QUANTITY_TYPE_ARTICLE,
            ]);
        } else {
            $ref = $referenceArticleRepository->find($referenceStr);
            $articleSupplier = $supplierArticleRepository->find($request->request->get('supplier_reference'));
        }
        if (!$ref) {
            throw new FormException("Référence scannée (${referenceStr}) inconnue.");
        } else if ($fromMatrix) {
            $type = $ref->getType();
            if ($ref->getArticlesFournisseur()->isEmpty()) {
                throw new FormException("La référence scannée (${referenceStr}) n'a pas d'article fournisseur paramétré.");
            } else {
                $articleSupplier = $ref->getArticlesFournisseur()->first();
            }
        }
        $refTypeLabel = $ref->getType()->getLabel();
        if ($ref->getType()?->getId() !== $type?->getId()) {
            throw new FormException("Le type selectionné est différent de celui de la référence (${refTypeLabel})");
        }

        if (!$articleSupplier) {
            throw new FormException("Référence fournisseur inconnue.");
        }

        $expiryDateStr = $request->request->get('expiryDate');
        $expiryDate = $expiryDateStr
            ? ($fromMatrix
                ? DateTime::createFromFormat('dmY', $expiryDateStr)
                : new DateTime($expiryDateStr))
            : null;

        $manufacturingDateStr = $request->request->get('manufacturingDate');
        $manufacturingDate = $manufacturingDateStr
            ? ($fromMatrix
                ? DateTime::createFromFormat('dmY', $manufacturingDateStr)
                : new DateTime($manufacturingDateStr))
            : null;

        $productionDateStr = $request->request->get('productionDate');
        $productionDate = $productionDateStr
            ? ($fromMatrix
                ? DateTime::createFromFormat('dmY', $productionDateStr)
                : new DateTime($productionDateStr))
            : null;

        $labelStr = $request->request->get('label');
        $commentStr = $request->request->get('comment');
        $priceStr = $request->request->get('price');
        $quantityStr = $request->request->getInt('quantity');
        $deliveryLineStr = $request->request->get('deliveryLine');
        $commandNumberStr = $request->request->get('commandNumber');
        $batchStr = $request->request->get('batch');

        $article = new Article();
        $article
            ->setLabel($labelStr)
            ->setConform(true)
            ->setStatut($statut)
            ->setCommentaire(!empty($commentStr) ? StringHelper::cleanedComment($commentStr) : null)
            ->setPrixUnitaire(floatval($priceStr))
            ->setReference($ref)
            ->setQuantite($quantityStr)
            ->setEmplacement($destination)
            ->setArticleFournisseur($articleSupplier)
            ->setType($type)
            ->setBarCode($articleDataService->generateBarcode())
            ->setStockEntryDate($now)
            ->setDeliveryNote($deliveryLineStr)
            ->setNativeCountry($countryFrom)
            ->setProductionDate($productionDate)
            ->setManifacturingDate($manufacturingDate)
            ->setPurchaseOrder($commandNumberStr)
            ->setRFIDtag($rfidTag)
            ->setBatch($batchStr)
            ->setExpiryDate($expiryDate);

        $entityManager->persist($article);

        $stockMovement = $mouvementStockService->createMouvementStock(
            $this->getUser(),
            null,
            $article->getQuantite(),
            $article,
            MouvementStock::TYPE_ENTREE
        );

        $mouvementStockService->finishMouvementStock(
            $stockMovement,
            $now,
            $article->getEmplacement()
        );

        $entityManager->persist($stockMovement);

        $trackingMovement = $trackingMovementService->createTrackingMovement(
            $article->getTrackingPack() ?: $article->getBarCode(),
            $article->getEmplacement(),
            $this->getUser(),
            $now,
            true,
            true,
            TrackingMovement::TYPE_DEPOSE,
            [
                "refOrArticle" => $article,
                "mouvementStock" => $stockMovement,
            ]
        );

        $entityManager->persist($trackingMovement);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Article bien généré.'
        ]);
    }

    /**
     * @Rest\Post("/api/transfer/finish", name="transfer_finish")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishTransfers(Request $request,
                                    TransferOrderService $transferOrderService,
                                    EntityManagerInterface $entityManager): Response
    {
        $nomadUser = $this->getUser();

        $dataResponse = [];
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $transferOrderRepository = $entityManager->getRepository(TransferOrder::class);

        $httpCode = Response::HTTP_OK;
        $transfersToTreat = json_decode($request->request->get('transfers'), true) ?: [];
        Stream::from($transfersToTreat)
            ->each(function ($transfer) use ($locationRepository, $transferOrderRepository, $transferOrderService, $nomadUser, $entityManager) {
                $destination = $locationRepository->findOneBy(['label' => $transfer['destination']]);
                $transfer = $transferOrderRepository->find($transfer['id']);
                $transferOrderService->finish($transfer, $nomadUser, $entityManager, $destination);
            });

        $entityManager->flush();
        $dataResponse['success'] = $transfersToTreat;

        return new JsonResponse($dataResponse, $httpCode);
    }

    private function getDataArray(Utilisateur $user,
                                  UserService $userService,
                                  TrackingMovementService $trackingMovementService,
                                  Request $request,
                                  EntityManagerInterface $entityManager, KernelInterface $kernel): array
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
        $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $attachmentRepository = $entityManager->getRepository(Attachment::class);
        $transferOrderRepository = $entityManager->getRepository(TransferOrder::class);
        $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
        $inventoryLocationMissionRepository = $entityManager->getRepository(InventoryLocationMission::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $projectRepository = $entityManager->getRepository(Project::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $driverRepository = $entityManager->getRepository(Chauffeur::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);

        $rights = $userService->getMobileRights($user);
        $parameters = $this->mobileApiService->getMobileParameters($settingRepository);

        $status = $statutRepository->getMobileStatus($rights['tracking'], $rights['demande']);

        $fieldsParam = Stream::from([FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::ENTITY_CODE_TRUCK_ARRIVAL])
            ->keymap(fn(string $entityCode) => [$entityCode, $fieldsParamRepository->getByEntity($entityCode)])
            ->toArray();

        $mobileTypes = Stream::from($typeRepository->findByCategoryLabels([CategoryType::ARTICLE, CategoryType::DEMANDE_DISPATCH, CategoryType::DEMANDE_LIVRAISON]))
            ->map(fn(Type $type) => [
                'id' => $type->getId(),
                'label' => $type->getLabel(),
                'category' => $type->getCategory()->getLabel()
            ])->toArray();

        if ($rights['inventoryManager']) {
            $refAnomalies = $inventoryEntryRepository->getAnomaliesOnRef(true);
            $artAnomalies = $inventoryEntryRepository->getAnomaliesOnArt(true);
        }

        if ($rights['stock']) {
            // livraisons
            $deliveriesExpectedDateColors = [
                'after' => $settingRepository->getOneParamByLabel(Setting::DELIVERY_EXPECTED_DATE_COLOR_AFTER),
                'DDay' => $settingRepository->getOneParamByLabel(Setting::DELIVERY_EXPECTED_DATE_COLOR_D_DAY),
                'before' => $settingRepository->getOneParamByLabel(Setting::DELIVERY_EXPECTED_DATE_COLOR_BEFORE),
            ];
            $livraisons = Stream::from($livraisonRepository->getMobileDelivery($user))
                ->map(function ($deliveryArray) use ($deliveriesExpectedDateColors) {
                    $deliveryArray['color'] = $this->mobileApiService->expectedDateColor($deliveryArray['expectedAt'], $deliveriesExpectedDateColors);
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
            /// preparations
            $preparations = Stream::from($preparationRepository->getMobilePreparations($user))
                ->map(function ($preparationArray) use ($deliveriesExpectedDateColors) {
                    $preparationArray['color'] = $this->mobileApiService->expectedDateColor($preparationArray['expectedAt'], $deliveriesExpectedDateColors);
                    $preparationArray['expectedAt'] = $preparationArray['expectedAt']
                        ? $preparationArray['expectedAt']->format('d/m/Y')
                        : null;
                    if (!empty($preparationArray['comment'])) {
                        $preparationArray['comment'] = substr(strip_tags($preparationArray['comment']), 0, 200);
                    }
                    return $preparationArray;
                })
                ->toArray();

            $displayPickingLocation = $settingRepository->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION);
            // get article linked to a ReferenceArticle where type_quantite === 'article'
            $articlesPrepaByRefArticle = $articleRepository->getArticlePrepaForPickingByUser($user, [], $displayPickingLocation);

            $articlesPrepa = $this->getArticlesPrepaArrays($entityManager, $preparations);
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

            // inventory
            $articlesInventory = $inventoryMissionRepository->getCurrentMissionArticlesNotTreated();
            $refArticlesInventory = $inventoryMissionRepository->getCurrentMissionRefNotTreated();
            $inventoryMissions = $inventoryMissionRepository->getInventoryMissions();
            $inventoryLocationsZone = $inventoryLocationMissionRepository->getInventoryLocationZones();
            // prises en cours
            $stockTaking = $trackingMovementRepository->getPickingByOperatorAndNotDropped($user, TrackingMovementRepository::MOUVEMENT_TRACA_STOCK);

            $projects = Stream::from($projectRepository->findAll())
                ->map(fn(Project $project) => [
                    'id' => $project->getId(),
                    'code' => $project->getCode(),
                ])
                ->toArray();
        }

        if ($rights['demande']) {
            $handlingExpectedDateColors = [
                'after' => $settingRepository->getOneParamByLabel(Setting::HANDLING_EXPECTED_DATE_COLOR_AFTER),
                'DDay' => $settingRepository->getOneParamByLabel(Setting::HANDLING_EXPECTED_DATE_COLOR_D_DAY),
                'before' => $settingRepository->getOneParamByLabel(Setting::HANDLING_EXPECTED_DATE_COLOR_BEFORE),
            ];

            $handlings = $handlingRepository->getMobileHandlingsByUserTypes($user->getHandlingTypeIds());
            $removeHoursDesiredDate = $settingRepository->getOneParamByLabel(Setting::REMOVE_HOURS_DATETIME);
            $handlings = Stream::from($handlings)
                ->map(function (array $handling) use ($handlingExpectedDateColors, $removeHoursDesiredDate) {
                    $handling['color'] = $this->mobileApiService->expectedDateColor($handling['desiredDate'], $handlingExpectedDateColors);
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
                        'href' => $request->getSchemeAndHttpHost() . '/uploads/attachements/' . $attachment['fileName'],
                    ];
                },
                $attachmentRepository->getMobileAttachmentForHandling($handlingIds)
            );

            $requestFreeFields = $freeFieldRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_HANDLING]);

            $demandeLivraisonArticles = $referenceArticleRepository->getByNeedsMobileSync();
            $deliveryFreeFields = $freeFieldRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_LIVRAISON]);

            $dispatchTypes = Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]))
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $type->getLabel(),
                ])->toArray();

            $users = $userRepository->getAll();
        }

        if ($rights['tracking']) {
            $trackingTaking = $trackingMovementService->getMobileUserPicking($entityManager, $user);


            $carriers = Stream::from($carrierRepository->findAll())
                ->map(function (Transporteur $transporteur) use ($kernel) {
                    $attachment = $transporteur->getAttachments()->isEmpty() ? null : $transporteur->getAttachments()->first();
                    $logo = null;
                    if ($attachment && $transporteur->isRecurrent()) {
                        $path = $kernel->getProjectDir() . '/public/uploads/attachements/' . $attachment->getFileName();
                        $type = pathinfo($path, PATHINFO_EXTENSION);
                        $type = ($type === 'svg' ? 'svg+xml' : $type);
                        $data = file_get_contents($path);

                        $logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
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
            $trackingFreeFields = $freeFieldRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]);

            ['natures' => $natures] = $this->mobileApiService->getNaturesData($entityManager, $this->getUser());
            [
                'dispatches' => $dispatches,
                'dispatchPacks' => $dispatchPacks,
            ] = $this->mobileApiService->getDispatchesData($entityManager, $user);
        }

        if($rights['demande'] || $rights['stock']) {
            $demandeLivraisonTypes = Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]))
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $type->getLabel(),
                ])
                ->toArray();
        }

        ['translations' => $translations] = $this->mobileApiService->getTranslationsData($entityManager, $this->getUser());
        return [
            'locations' => $emplacementRepository->getLocationsArray(),
            'allowedNatureInLocations' => $allowedNatureInLocations ?? [],
            'freeFields' => Stream::from(
                $trackingFreeFields ?? [],
                $requestFreeFields ?? [],
                $deliveryFreeFields ?? []
            )
                ->map(fn (FreeField $freeField) => $freeField->serialize())
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
            'articlesInventaire' => array_merge(
                $articlesInventory ?? [],
                $refArticlesInventory ?? []
            ),
            'inventoryMission' => $inventoryMissions ?? [],
            'inventoryLocationZone' => $inventoryLocationsZone ?? [],
            'anomalies' => array_merge($refAnomalies ?? [], $artAnomalies ?? []),
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
        ];
    }

    /**
     * @Rest\Post("/api/getData", name="api-get-data")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getData(Request $request,
                            UserService $userService,
                            TrackingMovementService $trackingMovementService,
                            KernelInterface $kernel,
                            EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        return $this->json([
            "success" => true,
            "data" => $this->getDataArray($nomadUser, $userService, $trackingMovementService, $request, $entityManager, $kernel),
        ]);
    }

    /**
     * @Rest\Get("/api/previous-operator-movements", name="api_previous_operator_movements")
     * @Wii\RestVersionChecked()
     */
    public function getPreviousOperatorMovements(Request $request, EntityManagerInterface $manager) {
        $userRepository = $manager->getRepository(Utilisateur::class);
        $trackingMovementRepository = $manager->getRepository(TrackingMovement::class);

        $user = $userRepository->find($request->query->get("operator"));
        $movements = $trackingMovementRepository->getPickingByOperatorAndNotDropped(
            $user,
            TrackingMovementRepository::MOUVEMENT_TRACA_DEFAULT
        );

        return $this->json([
            "success" => true,
            "movements" => $movements,
        ]);
    }

    private function apiKeyGenerator()
    {
        return md5(microtime() . rand());
    }

    /**
     * @Rest\Get("/api/nomade-versions")
     */
    public function checkNomadeVersion(Request $request, ParameterBagInterface $parameterBag){
        return $this->json([
            "success" => true,
            "validVersion" => $this->mobileApiService->checkMobileVersion($request->get('nomadeVersion'), $parameterBag->get('nomade_version')),
        ]);
    }

    /**
     * @Rest\Post("/api/treatAnomalies", name= "api-treat-anomalies-inv", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function treatAnomalies(Request                  $request,
                                   InventoryService         $inventoryService,
                                   ExceptionLoggerService   $exceptionLoggerService,
                                   TranslationService       $translation)
    {

        $nomadUser = $this->getUser();

        $numberOfRowsInserted = 0;

        $anomalies = json_decode($request->request->get('anomalies'), true);
        $errors = [];
        $success = [];
        foreach ($anomalies as $anomaly) {
            try {
                $res = $inventoryService->doTreatAnomaly(
                    $anomaly['id'],
                    $anomaly['reference'],
                    $anomaly['is_ref'],
                    $anomaly['quantity'],
                    $anomaly['comment'] ?? null,
                    $nomadUser
                );

                $success = array_merge($success, $res['treatedEntries']);

                $numberOfRowsInserted++;
            } catch (ArticleNotAvailableException|RequestNeedToBeProcessedException $exception) {
                $errors[] = $anomaly['id'];
            } catch (Throwable $throwable) {
                $exceptionLoggerService->sendLog($throwable, $request);
                throw $throwable;
            }
        }

        $s = $numberOfRowsInserted > 1 ? 's' : '';
        $data = [];
        $data['success'] = $success;
        $data['errors'] = $errors;
        $data['data']['status'] = ($numberOfRowsInserted === 0)
            ? ($anomalies > 0
                ? 'Une ou plusieus erreurs, des ' . mb_strtolower($translation->translate("Ordre", "Livraison", "Ordre de livraison", false)) . ' sont en cours pour ces articles ou ils ne sont pas disponibles, veuillez recharger vos données'
                : "Aucune anomalie d'inventaire à synchroniser.")
            : ($numberOfRowsInserted . ' anomalie' . $s . ' d\'inventaire synchronisée' . $s);

        return $this->json($data);
    }

    /**
     * @Rest\Post("/api/inventory-missions/{inventoryMission}/summary/{zone}", name="api_zone_rfid_summary", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function rfidSummary(Request                $request,
                                InventoryMission       $inventoryMission,
                                Zone                   $zone,
                                EntityManagerInterface $entityManager,
                                InventoryService       $inventoryService): Response
    {



        $rfidTagsStr = $request->request->get('rfidTags');
        $rfidTags = json_decode($rfidTagsStr ?: '[]', true) ?: [];

        return $this->json([
            "success" => true,
            "data" => $inventoryService->summarizeLocationInventory(
                $entityManager,
                $inventoryMission,
                $zone,
                $rfidTags,
                $this->getUser()
            )
        ]);
    }


    /**
     * @Rest\Post("/api/finish-mission", name="api_finish_mission", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishMission(Request                $request,
                                  EntityManagerInterface $entityManager,
                                  MailerService          $mailerService,
                                  Twig_Environment       $templating): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $missionRepository = $entityManager->getRepository(InventoryMission::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $zoneRepository = $entityManager->getRepository(Zone::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $rfidPrefix = $settingRepository->getOneParamByLabel(Setting::RFID_PREFIX) ?: '';

        $mission = $missionRepository->find($request->request->get('mission'));
        $zones = $zoneRepository->findBy(["id" => json_decode($request->request->get('zones'))]);
        $locations = $locationRepository->findByMissionAndZone($zones, $mission);

        $tags = Stream::from(json_decode($request->request->get('tags')))
            ->filter(fn(string $tag) => str_starts_with($tag, $rfidPrefix))
            ->toArray();

        $articlesOnLocations = $articleRepository->findAvailableArticlesToInventory($tags, $locations, ['mode' => ArticleRepository::INVENTORY_MODE_FINISH]);

        $now = new DateTime('now');
        $validator = $this->getUser();
        $activeStatus = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
        $inactiveStatus = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);

        /** @var Article $article */
        foreach ($articlesOnLocations as $article) {
            if (in_array($article->getRFIDtag(), $tags)) {
                $presentArticle = $article;
                if ($presentArticle->getStatut()->getCode() !== Article::STATUT_ACTIF) {
                    $correctionMovement = new MouvementStock();
                    $correctionMovement
                        ->setType(MouvementStock::TYPE_INVENTAIRE_ENTREE)
                        ->setArticle($presentArticle)
                        ->setDate($now)
                        ->setQuantity($presentArticle->getQuantite())
                        ->setEmplacementFrom($presentArticle->getEmplacement())
                        ->setEmplacementTo($presentArticle->getEmplacement())
                        ->setUser($validator);
                    $entityManager->persist($correctionMovement);
                }
                $presentArticle
                    ->setFirstUnavailableDate(null)
                    ->setLastAvailableDate($now)
                    ->setStatut($activeStatus)
                    ->setDateLastInventory($now);
            } else {
                $missingArticle = $article;
                if ($missingArticle->getStatut()->getCode() !== Article::STATUT_INACTIF) {
                    $correctionMovement = new MouvementStock();
                    $correctionMovement
                        ->setType(MouvementStock::TYPE_INVENTAIRE_SORTIE)
                        ->setArticle($missingArticle)
                        ->setDate($now)
                        ->setQuantity($missingArticle->getQuantite())
                        ->setEmplacementFrom($missingArticle->getEmplacement())
                        ->setEmplacementTo($missingArticle->getEmplacement())
                        ->setUser($validator);
                    $entityManager->persist($correctionMovement);
                    $missingArticle
                        ->setFirstUnavailableDate($now);
                }
                $missingArticle
                    ->setStatut($inactiveStatus)
                    ->setDateLastInventory($now);
            }
        }

        $mission
            ->setValidatedAt($now)
            ->setValidator($validator)
            ->setDone(true);

        $entityManager->flush();

        if ($mission->getRequester()) {
            $mailerService->sendMail(
                "Follow GT // Validation d’une mission d’inventaire",
                $templating->render('mails/contents/mailInventoryMissionValidation.html.twig', [
                    'mission' => $mission,
                ]),
                $mission->getRequester()
            );
        }
        return $this->json([
            "success" => true,
            "data" => ""
        ]);
    }

    /**
     * @Rest\Post("/api/emplacement", name="api-new-emp", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function addEmplacement(Request $request, EntityManagerInterface $entityManager): Response
    {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        if (!$emplacementRepository->findOneBy(['label' => $request->request->get('label')])) {
            $toInsert = new Emplacement();
            $toInsert
                ->setLabel($request->request->get('label'))
                ->setIsActive(true)
                ->setDescription('')
                ->setIsDeliveryPoint((bool)$request->request->get('isDelivery'));
            $entityManager->persist($toInsert);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => $toInsert->getId(),
            ]);
        } else {
            throw new BadRequestHttpException("Un emplacement portant ce nom existe déjà");
        }
    }

    /**
     * @Rest\Get("/api/logistic-unit/articles", name="api_logistic_unit_articles", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getLogisticUnitArticles(Request $request, EntityManagerInterface $entityManager) {
        $packRepository = $entityManager->getRepository(Pack::class);

        $code = $request->query->get('code');

        $pack = $packRepository->findOneBy(['code' => $code]);

        $articles = $pack->getChildArticles()->map(fn(Article $article) => [
            'id' => $article->getId(),
            'barCode' => $article->getBarCode(),
            'label' => $article->getLabel(),
            'location' => $article->getEmplacement()?->getLabel(),
            'quantity' => $article->getQuantite(),
            'reference' => $article->getReference()
        ]);

        return $this->json($articles);
    }

    /**
     * @Rest\Get("/api/articles", name="api-get-articles", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getArticles(Request $request, EntityManagerInterface $entityManager): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $referenceActiveStatusId = $statutRepository
            ->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF)
            ->getId();

        $resData = [];

        $barCode = $request->query->get('barCode');
        $barcodes = $request->query->get('barcodes');
        $location = $request->query->get('location');
        $createIfNotExist = $request->query->get('createIfNotExist');

        if(!empty($barcodes)) {
            $barcodes = json_decode($barcodes, true);
            $articles = Stream::from($articleRepository->findBy(['barCode' => $barcodes]))
                ->map(fn(Article $article) => [
                    'barcode' => $article->getBarCode(),
                    'quantity' => $article->getQuantite(),
                    'reference' => $article->getArticleFournisseur()->getReferenceArticle()->getReference()
                ])->toArray();

            return $this->json([
                'articles' => $articles
            ]);
        } else if (!empty($barCode)) {
            $statusCode = Response::HTTP_OK;
            $referenceArticle = $referenceArticleRepository->findOneBy([
                'barCode' => $barCode,
            ]);
            if (!empty($referenceArticle) && (!$location || $referenceArticle->getEmplacement()->getLabel() === $location)) {
                $statusReferenceArticle = $referenceArticle->getStatut();
                $statusReferenceId = $statusReferenceArticle ? $statusReferenceArticle->getId() : null;
                // we can transfer if reference is active AND it is not linked to any active orders
                $referenceArticleArray = [
                    'can_transfer' => (
                        ($statusReferenceId === $referenceActiveStatusId)
                        && !$referenceArticleRepository->isUsedInQuantityChangingProcesses($referenceArticle)
                    ),
                    "id" => $referenceArticle->getId(),
                    "barCode" => $referenceArticle->getBarCode(),
                    "quantity" => $referenceArticle->getQuantiteDisponible(),
                    "is_ref" => "1"
                ];
                $resData['article'] = $referenceArticleArray;
            }
            else {
                $article = $articleRepository->getOneArticleByBarCodeAndLocation($barCode, $location);

                if (!empty($article)) {
                    $canAssociate = in_array($article['articleStatusCode'], [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE]);

                    $article['can_transfer'] = ($article['reference_status'] === ReferenceArticle::STATUT_ACTIF);
                    $article['can_associate'] = $canAssociate;
                    $resData['article'] = $canAssociate ? $article : null;
                } else {
                    $pack = $packRepository->getOneArticleByBarCodeAndLocation($barCode, $location);
                    if(!empty($pack)) {
                        $pack["can_transfer"] = 1;
                        $pack["articles"] = $pack["articles"] ? explode(";", $pack["articles"]) : null;
                    } else if ($createIfNotExist) {
                        $pack = [
                            'barCode' => $barCode,
                            'articlesCount' => null,
                            'is_lu' => "1",
                            'project' => null,
                            'location' => null,
                            'is_ref' => 0,
                        ];
                    }
                    $resData['article'] = $pack;
                }
            }

            if (!empty($resData['article'])) {
                $resData['article']['is_ref'] = (int)$resData['article']['is_ref'];
            }

            $resData['success'] = !empty($resData['article']);
        } else {
            throw new BadRequestHttpException();
        }
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/drop-in-lu", name="api-post-drop-in-lu", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
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
    /**
     * @Rest\Get("/api/tracking-drops", name="api-get-tracking-drops-on-location", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getTrackingDropsOnLocation(Request $request, EntityManagerInterface $entityManager): Response
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

    /**
     * @Rest\Get("/api/packs", name="api_get_pack_data", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getPackData(Request $request,
                                EntityManagerInterface $entityManager,
                                NatureService $natureService): Response
    {
        $code = $request->query->get('code');
        $includeNature = $request->query->getBoolean('nature');
        $includeGroup = $request->query->getBoolean('group');
        $res = ['success' => true];

        $packRepository = $entityManager->getRepository(Pack::class);
        $pack = !empty($code)
            ? $packRepository->findOneBy(['code' => $code])
            : null;

        if ($pack) {
            $isGroup = $pack->isGroup();
            $res['isGroup'] = $isGroup;
            $res['isPack'] = !$isGroup;

            if ($includeGroup) {
                $group = $isGroup ? $pack : $pack->getParent();
                $res['group'] = $group ? $group->serialize() : null;
            }

            if ($includeNature) {
                $nature = $pack->getNature();
                $res['nature'] = !empty($nature)
                    ? $natureService->serializeNature($nature, $this->getUser())
                    : null;
            }
        }
        else {
            $res['isGroup'] = false;
            $res['isPack'] = false;
        }

        return $this->json($res);
    }

    /**
     * @Rest\Get("/api/pack-groups", name="api_get_pack_groups", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getPacksGroups(Request $request, EntityManagerInterface $entityManager): Response {
        $code = $request->query->get('code');

        $packRepository = $entityManager->getRepository(Pack::class);

        $pack = !empty($code)
            ? $packRepository->findOneBy(['code' => $code])
            : null;

        if ($pack) {
            if (!$pack->isGroup()) {
                $isPack = true;
                $isSubPack = $pack->getParent() !== null;
                $packSerialized = $pack->serialize();
            }
            else {
                $isPack = false;
                $packGroupSerialized = $pack->serialize();
            }
        }

        return $this->json([
            "success" => true,
            "isPack" => $isPack ?? false,
            "isSubPack" => $isSubPack ?? false,
            "pack" => $packSerialized ?? null,
            "packGroup" => $packGroupSerialized ?? null,
        ]);
    }

    /**
     * @Rest\Get("/api/group", name="api_group", methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function group(Request $request,
                          PackService $packService,
                          EntityManagerInterface $entityManager,
                          GroupService $groupService,
                          TrackingMovementService $trackingMovementService): Response {
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
        } else if ($parentPack->getChildren()->isEmpty()) {
            $isNewGroupInstance = true;
            $parentPack->incrementGroupIteration();
        }

        $packs = json_decode($request->request->get("packs"), true);

        $datetimeFromDate = function ($dateStr) {
            return DateTime::createFromFormat("d/m/Y H:i:s", $dateStr)
                ?: DateTime::createFromFormat("d/m/Y H:i", $dateStr)
                ?: null;
        };

        $dateStr = $request->request->get("date");
        $groupDate = $datetimeFromDate($dateStr);

        if ($isNewGroupInstance && !empty($packs)) {
            $groupingTrackingMovement = $trackingMovementService->createTrackingMovement(
                $parentPack,
                null,
                $this->getUser(),
                $groupDate,
                true,
                true,
                TrackingMovement::TYPE_GROUP
            );

            $entityManager->persist($groupingTrackingMovement);
        }

        foreach ($packs as $data) {
            $pack = $packService->persistPack($entityManager, $data["code"], $data["quantity"], $data["nature_id"]);
            if (!$pack->getParent()) {
                $pack->setParent($parentPack);

                $groupingTrackingMovement = $trackingMovementService->createTrackingMovement(
                    $pack,
                    null,
                    $this->getUser(),
                    DateTime::createFromFormat("d/m/Y H:i:s", $data["date"]),
                    true,
                    true,
                    TrackingMovement::TYPE_GROUP,
                    ["parent" => $parentPack]
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

    /**
     * @Rest\Get("/api/ungroup", name="api_ungroup", methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function ungroup(Request $request, EntityManagerInterface $manager, GroupService $groupService): Response {
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

    /**
     * @Rest\Get("/api/collectable-articles", name="api_get_collectableçarticle", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getCollectableArticles(Request                $request,
                                           EntityManagerInterface $entityManager): Response {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $reference = $request->query->get('reference');
        $barcode = $request->query->get('barcode');

        /** @var ReferenceArticle $referenceArticle */
        $referenceArticle = $referenceArticleRepository->findOneBy(['reference' => $reference]);

        if ($referenceArticle) {
            return $this->json(['articles' => $articleRepository->getCollectableMobileArticles($referenceArticle, $barcode)]);
        }
        else {
            throw new NotFoundHttpException();
        }
    }

    /**
     * @Rest\Get("/api/server-images", name="api_images", condition="request.isXmlHttpRequest()")
     */
    public function getLogos(EntityManagerInterface $entityManager,
                             KernelInterface $kernel,
                             Request $request): Response
    {
        $logoKey = $request->get('key');
        if (!in_array($logoKey, [Setting::FILE_MOBILE_LOGO_HEADER, Setting::FILE_MOBILE_LOGO_LOGIN])) {
            throw new BadRequestHttpException('Unknown logo key');
        }

        $settingRepository = $entityManager->getRepository(Setting::class);
        $logo = $settingRepository->getOneParamByLabel($logoKey);

        if (!$logo) {
            return $this->json([
                "success" => false,
                'message' => 'Image non renseignée AAA',
            ]);
        }

        $projectDir = $kernel->getProjectDir();

        try {
            $imagePath = $projectDir . '/public/' . $logo;

            $type = pathinfo($imagePath, PATHINFO_EXTENSION);
            $type = ($type === 'svg' ? 'svg+xml' : $type);

            $data = file_get_contents($imagePath);
            $image = 'data:image/' . $type . ';base64,' . base64_encode($data);
        } catch (Throwable $ignored) {
            return $this->json([
                "success" => false,
                'message' => 'Image non renseignée',
            ]);
        }

        return $this->json([
            "success" => true,
            'image' => $image,
        ]);
    }

    /**
     * @Rest\Post("/api/dispatches", name="api_patch_dispatches", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function patchDispatches(Request $request,
                                    AttachmentService $attachmentService,
                                    DispatchService $dispatchService,
                                    EntityManagerInterface $entityManager): JsonResponse
    {
        $nomadUser = $this->getUser();

        $resData = [];

        $dispatches = json_decode($request->request->get('dispatches'), true);
        $dispatchPacksParam = json_decode($request->request->get('dispatchPacks'), true);

        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $entireTreatedDispatch = [];

        $dispatchPacksByDispatch = is_array($dispatchPacksParam)
            ? array_reduce($dispatchPacksParam, function (array $acc, array $current) {
                $id = (int)$current['id'];
                $natureId = $current['natureId'];
                $quantity = $current['quantity'];
                $dispatchId = (int)$current['dispatchId'];
                $photo1 = $current['photo1'] ?? null;
                $photo2 = $current['photo2'] ?? null;
                if (!isset($acc[$dispatchId])) {
                    $acc[$dispatchId] = [];
                }
                $acc[$dispatchId][] = [
                    'id' => $id,
                    'natureId' => $natureId,
                    'quantity' => $quantity,
                    'photo1' => $photo1,
                    'photo2' => $photo2,
                ];
                return $acc;
            }, [])
            : [];

        foreach ($dispatches as $dispatchArray) {
            /** @var Dispatch $dispatch */
            $dispatch = $dispatchRepository->find($dispatchArray['id']);
            $dispatchStatus = $dispatch->getStatut();
            if (!$dispatchStatus || !$dispatchStatus->isTreated()) {
                $treatedStatus = $statusRepository->find($dispatchArray['treatedStatusId']);
                if ($treatedStatus
                    && ($treatedStatus->isTreated() || $treatedStatus->isPartial())) {
                    $treatedPacks = [];
                    // we treat pack edits
                    if (!empty($dispatchPacksByDispatch[$dispatch->getId()])) {
                        foreach ($dispatchPacksByDispatch[$dispatch->getId()] as $packArray) {
                            $treatedPacks[] = $packArray['id'];
                            $packDispatch = $dispatchPackRepository->find($packArray['id']);
                            $pack = $packDispatch->getPack();
                            if (!empty($packDispatch)) {
                                if (!empty($packArray['natureId'])) {
                                    $nature = $natureRepository->find($packArray['natureId']);
                                    if ($nature) {
                                        $pack->setNature($nature);
                                    }
                                }

                                $quantity = (int)$packArray['quantity'];
                                if ($quantity > 0) {
                                    $packDispatch->setQuantity($quantity);
                                }

                                $code = $pack->getCode();
                                foreach (['photo1', 'photo2'] as $photoName) {
                                    $photoFile = $request->files->get("{$code}_{$photoName}");
                                    if ($photoFile) {
                                        $fileName = $attachmentService->saveFile($photoFile);
                                        $attachments = $attachmentService->createAttachements($fileName);
                                        foreach ($attachments as $attachment) {
                                            $entityManager->persist($attachment);
                                            $dispatch->addAttachment($attachment);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $dispatchService->treatDispatchRequest($entityManager, $dispatch, $treatedStatus, $nomadUser, true, $treatedPacks);

                    if (!$treatedStatus->isPartial()) {
                        $entireTreatedDispatch[] = $dispatch->getId();
                    }
                }
            }
        }
        $statusCode = Response::HTTP_OK;
        $resData['success'] = true;
        $resData['entireTreatedDispatch'] = $entireTreatedDispatch;

        return new JsonResponse($resData, $statusCode);
    }

    private function getArticlesPrepaArrays(EntityManagerInterface $entityManager, array $preparations, bool $isIdArray = false): array
    {
        /** @var ReferenceArticleRepository $referenceArticleRepository */
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        /** @var ArticleRepository $articleRepository */
        $articleRepository = $entityManager->getRepository(Article::class);

        $preparationsIds = !$isIdArray
            ? array_map(
                function ($preparationArray) {
                    return $preparationArray['id'];
                },
                $preparations
            )
            : $preparations;
        return array_merge(
            $articleRepository->getByPreparationsIds($preparationsIds),
            $referenceArticleRepository->getByPreparationsIds($preparationsIds)
        );
    }

    /**
     * @Rest\Post("/api/finish-grouped-signature", name="api_finish_grouped_signature")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishGroupedSignature(Request $request,
                                           EntityManagerInterface $manager,
                                           DispatchService $dispatchService) {

        $locationData = [
            'from' => $request->request->get('from') === "null" ? null : $request->request->get('from'),
            'to' => $request->request->get('to') === "null" ? null : $request->request->get('to'),
        ];
        $signatoryTrigramData = $request->request->get("signatoryTrigram");
        $signatoryPasswordData = $request->request->get("signatoryPassword");
        $statusData = $request->request->get("status");
        $commentData = $request->request->get("comment");
        $dispatchesToSignIds = explode(',', $request->request->get('dispatchesToSign'));

        $response = $dispatchService->finishGroupedSignature(
            $manager,
            $locationData,
            $signatoryTrigramData,
            $signatoryPasswordData,
            $statusData,
            $commentData,
            $dispatchesToSignIds,
            true,
            $this->getUser()
        );

        $manager->flush();

        return $this->json($response);
    }

    /**
     * @Rest\Post("/api/empty-round", name="api_empty_round", methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function emptyRound(Request $request, TrackingMovementService $trackingMovementService, EntityManagerInterface $manager): JsonResponse {
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
                    ['commentaire' => $emptyRound['comment'] ?? null]
                );

                $manager->persist($trackingMovement);
            }
        }
        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    private function expectedDateColor(?DateTime $date, array $colors): ?string {
        $nowStr = (new DateTime('now'))->format('Y-m-d');
        $dateStr = !empty($date) ? $date->format('Y-m-d') : null;
        $color = null;
        if ($dateStr) {
            if ($dateStr > $nowStr && isset($colors['after'])) {
                $color = $colors['after'];
            }
            if ($dateStr === $nowStr && isset($colors['DDay'])) {
                $color = $colors['DDay'];
            }
            if ($dateStr < $nowStr && isset($colors['before'])) {
                $color = $colors['before'];
            }
        }
        return $color;
    }

    /**
     * @Rest\Post("/api/dispatch-emergencies", name="api_dispatch_emergencies", methods="GET", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function dispatchEmergencies(EntityManagerInterface $manager): Response {
        $elements = $manager->getRepository(FieldsParam::class)->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY);
        $emergencies = Stream::from($elements)
            ->map(fn(string $element) => [
                'id' => $element,
                'label' => $element,
            ])->toArray();

        return $this->json($emergencies);
    }

    /**
     * @Rest\Post("/api/waybill/{dispatch}", name="api_waybill_dispatch", methods="POST", condition="request.isXmlHttpRequest()", options={"expose"=true})
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function dispatchWaybill(EntityManagerInterface $manager, Dispatch $dispatch, Request $request, DispatchService $dispatchService, KernelInterface $kernel): Response {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = $request->request->all();
        $wayBillAttachment = $dispatchService->generateWayBill($loggedUser, $dispatch, $manager, $data);
        $manager->flush();
        $file = '/uploads/attachements/' . $wayBillAttachment->getFileName();

        return $this->json([
            'filePath' => $file,
            'fileName' => $wayBillAttachment->getOriginalName(),
        ]);
    }

    /**
     * @Rest\Post("/api/new-dispatch", name="api_new_dispatch", methods="POST", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function newDispatch(Request $request,
                                EntityManagerInterface $manager,
                                UniqueNumberService $uniqueNumberService,
                                DispatchService $dispatchService,
                                MobileApiService $mobileApiService,
                                StatusHistoryService $statusHistoryService): Response {

        $typeRepository = $manager->getRepository(Type::class);
        $statusRepository = $manager->getRepository(Statut::class);
        $locationRepository = $manager->getRepository(Emplacement::class);
        $userRepository = $manager->getRepository(Utilisateur::class);
        $dispatchRepository = $manager->getRepository(Dispatch::class);

        $dispatchNumber = $uniqueNumberService->create($manager, Dispatch::NUMBER_PREFIX, Dispatch::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);
        $type = $typeRepository->find($request->request->get('type'));
        $draftStatuses = $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $type, [Statut::DRAFT]);
        $pickLocation = $request->request->get('pickLocation') ? $locationRepository->find($request->request->get('pickLocation')) : null;
        $dropLocation = $request->request->get('dropLocation') ? $locationRepository->find($request->request->get('dropLocation')) : null;
        $receiver = $request->request->get('receiver') ? $userRepository->find($request->request->get('receiver')) : null;
        $emails = $request->request->get('emails') ? explode(",", $request->request->get('emails')) : null;

        if(empty($draftStatuses)) {
            return $this->json([
                'success' => false,
                'msg' => "Il n'y a aucun statut brouillon paramétré pour ce type."
            ]);
        }

        $dispatch = (new Dispatch())
            ->setNumber($dispatchNumber)
            ->setCreationDate(new DateTime())
            ->setRequester($this->getUser())
            ->setType($type)
            ->setStatus($draftStatuses[0])
            ->setLocationFrom($pickLocation)
            ->setLocationTo($dropLocation)
            ->setCarrierTrackingNumber($request->request->get('carrierTrackingNumber'))
            ->setCommentaire($request->request->get('comment'))
            ->setEmergency($request->request->get('emergency'))
            ->setEmails($emails);

        if($receiver) {
            $dispatch->addReceiver($receiver);
        }

        $manager->persist($dispatch);

        $statusHistoryService->updateStatus($manager, $dispatch, $draftStatuses[0], [
            'setStatus' => false,
        ]);

        $manager->flush();

        if($request->request->get('emergency') && $receiver) {
            $dispatchService->sendEmailsAccordingToStatus($dispatch, false, false, $receiver, true);
        }

        $serializedDispatch = $dispatchRepository->getMobileDispatches(null, $dispatch);
        $serializedDispatch = Stream::from($serializedDispatch)
            ->reduce(fn(array $accumulator, array $dispatch) => $mobileApiService->serializeDispatch($accumulator, $dispatch), []);
        $serializedDispatch = $serializedDispatch[array_key_first($serializedDispatch)];
        return $this->json([
            'success' => true,
            'dispatch' => $serializedDispatch
        ]);
    }

    /**
     * @Rest\Post("/api/get-reference", name="api_get_reference", methods="GET", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getReference(Request $request, EntityManagerInterface $manager): Response {
        $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);

        $text = $request->query->get("reference");
        $reference = $referenceArticleRepository->findOneBy(['reference' => $request->query->get("reference")]);
        if($reference) {
            $description = $reference->getDescription();
            $serializedReference = [
                'reference' => $reference->getReference(),
                'outFormatEquipment' => $description['outFormatEquipment'] ?? '',
                'manufacturerCode' => $description['manufacturerCode'] ?? '',
                'width' => $description['width'] ?? '',
                'height' => $description['height'] ?? '',
                'length' => $description['length'] ?? '',
                'volume' => $description['volume'] ?? '',
                'weight' => $description['weight'] ?? '',
                'associatedDocumentTypes' => $description['associatedDocumentTypes'] ?? '',
                'exists' => true,
            ];
        } else {
            $serializedReference = [
                'reference' => $text,
                'exists' => false,
            ];
        }

        return $this->json([
            "success" => true,
            "reference" => $serializedReference
        ]);
    }

    /**
     * @Rest\Post("/api/dispatch-validate", name="api_dispatch_validate", methods="POST", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function dispatchValidate(Request                $request,
                                     EntityManagerInterface $entityManager,
                                     RefArticleDataService  $refArticleDataService,
                                     PackService            $packService,
                                     StatusHistoryService   $statusHistoryService,
                                     KernelInterface        $kernel): Response {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $defaultNature = $natureRepository->findOneBy(['defaultForDispatch' => true]);

        $references = json_decode($request->request->get('references'), true);
        $user = $this->getUser();
        $now = new DateTime();

        $dispatch = $entityManager->find(Dispatch::class, $request->request->get('dispatch'));
        $toTreatStatus = $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::NOT_TREATED])[0] ?? null;

        if($toTreatStatus) {
            foreach ($references as $data) {
                $creation = false;
                $reference = $referenceArticleRepository->findOneBy(['reference' => $data['reference']]);
                if(!$reference) {
                    $creation = true;
                    $dispatchNewReferenceType = $settingRepository->getOneParamByLabel(Setting::DISPATCH_NEW_REFERENCE_TYPE);
                    $dispatchNewReferenceStatus = $settingRepository->getOneParamByLabel(Setting::DISPATCH_NEW_REFERENCE_STATUS);
                    $dispatchNewReferenceQuantityManagement = $settingRepository->getOneParamByLabel(Setting::DISPATCH_NEW_REFERENCE_QUANTITY_MANAGEMENT);

                    if($dispatchNewReferenceType === null) {
                        return $this->json([
                            'success' => false,
                            'msg' => "Vous n'avez pas paramétré de type par défaut pour la création de références."
                        ]);
                    } elseif ($dispatchNewReferenceStatus === null) {
                        return $this->json([
                            'success' => false,
                            'msg' => "Vous n'avez pas paramétré de statut par défaut pour la création de références."
                        ]);
                    } elseif ($dispatchNewReferenceQuantityManagement === null) {
                        return $this->json([
                            'success' => false,
                            'msg' => "Vous n'avez pas paramétré de gestion de quantité par défaut pour la création de références."
                        ]);
                    }

                    $type = $typeRepository->find($dispatchNewReferenceType);
                    $status = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, $dispatchNewReferenceStatus);

                    $reference = (new ReferenceArticle())
                        ->setReference($data['reference'])
                        ->setLibelle($data['reference'])
                        ->setType($type)
                        ->setStatut($status)
                        ->setTypeQuantite($dispatchNewReferenceQuantityManagement == 0
                            ? ReferenceArticle::QUANTITY_TYPE_REFERENCE
                            : ReferenceArticle::QUANTITY_TYPE_ARTICLE
                        )
                        ->setCreatedBy($user)
                        ->setCreatedAt($now)
                        ->setBarCode($refArticleDataService->generateBarCode())
                        ->setQuantiteStock(0)
                        ->setQuantiteDisponible(0);

                    $entityManager->persist($reference);
                }

                $oldDescription = $reference->getDescription();
                $refArticleDataService->updateDescriptionField($entityManager, $reference, [
                    'outFormatEquipment' => $data['outFormatEquipment'],
                    'manufacturerCode' => $data['manufacturerCode'],
                    'volume' =>  $data['volume'],
                    'length' =>  $data['length'] ?: ($oldDescription['length'] ?? null),
                    'width' =>  $data['width'] ?: ($oldDescription['width'] ?? null),
                    'height' =>  $data['height'] ?: ($oldDescription['height'] ?? null),
                    'weight' => $data['weight'],
                    'associatedDocumentTypes' => $data['associatedDocumentTypes'],
                ]);

                if ($data['logisticUnit']) {
                    $logisticUnit = $packRepository->findOneBy(['code' => $data['logisticUnit']]) ?? $packService->createPackWithCode($data['logisticUnit']);

                    $logisticUnit->setNature($defaultNature);

                    $entityManager->persist($logisticUnit);

                    $dispatchPack = (new DispatchPack())
                        ->setDispatch($dispatch)
                        ->setPack($logisticUnit)
                        ->setTreated(false);

                    $entityManager->persist($dispatchPack);

                    $dispatchReferenceArticle = (new DispatchReferenceArticle())
                        ->setReferenceArticle($reference)
                        ->setDispatchPack($dispatchPack)
                        ->setQuantity($data['quantity'])
                        ->setBatchNumber($data['batchNumber'])
                        ->setSerialNumber($data['serialNumber'])
                        ->setSealingNumber($data['sealingNumber'])
                        ->setComment($data['comment'])
                        ->setADR(isset($data['adr']) && boolval($data['adr']));

                    $maxNbFilesSubmitted = 10;
                    $fileCounter = 1;
                    // upload of photo_1 to photo_10
                    do {
                        $photoFile = $data["photo_$fileCounter"] ?? [];
                        if (!empty($photoFile)) {
                            $name = uniqid();
                            $path = "{$kernel->getProjectDir()}/public/uploads/attachements/$name.jpeg";
                            file_put_contents($path, file_get_contents($photoFile));
                            $attachment = new Attachment();
                            $attachment
                                ->setOriginalName("photo_$fileCounter.jpeg")
                                ->setFileName("$name.jpeg")
                                ->setFullPath("/uploads/attachements/$name.jpeg");

                            $dispatchReferenceArticle->addAttachment($attachment);
                            $entityManager->persist($attachment);
                        }
                        $fileCounter++;
                    } while (!empty($photoFile) && $fileCounter <= $maxNbFilesSubmitted);

                    $entityManager->persist($dispatchReferenceArticle);
                }
            }
            $dispatch
                ->setValidationDate(new DateTime('now'));
            $statusHistoryService->updateStatus($entityManager, $dispatch, $toTreatStatus);

            $entityManager->flush();

            return $this->json([
                'success' => true
            ]);
        } else {
            return $this->json([
                'success' => false,
                'msg' => "Il n'y a aucun statut à traiter paramétré pour ce type."
            ]);
        }
    }

    /**
     * @Rest\Post("/api/get-associated-document-type-elements", name="api_get_associated_document_type_elements", methods="GET", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getAssociatedDocumentTypeElements(EntityManagerInterface $manager): Response {
        $settingRepository = $manager->getRepository(Setting::class);
        $associatedDocumentTypeElements = $settingRepository->getOneParamByLabel(Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES);

        return $this->json($associatedDocumentTypeElements);
    }

    /**
     * @Rest\Post("/api/get-associated-ref-intels/{packCode}/{dispatch}", name="api_get_associated_ref-intels", methods="GET", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getAssociatedPackIntels(EntityManagerInterface $manager, string $packCode, Dispatch $dispatch, KernelInterface $kernel): Response {

        $pack = $manager->getRepository(Pack::class)->findOneBy(['code' => $packCode]);

        $data = [];
        /** @var DispatchPack $line */
        $line = $dispatch
            ->getDispatchPacks()
            ->filter(fn (DispatchPack $linePack) => $linePack->getPack()->getId() === $pack->getId())
            ->first();

        if ($line) {
            /** @var DispatchReferenceArticle $ref */
            $ref = $line
                ->getDispatchReferenceArticles()
                ->first();
            if ($ref) {
                $photos = Stream::from($ref->getAttachments())
                    ->map(function(Attachment $attachment) use ($kernel) {
                        $path = $kernel->getProjectDir() . '/public/uploads/attachements/' . $attachment->getFileName();
                        $type = pathinfo($path, PATHINFO_EXTENSION);
                        $data = file_get_contents($path);

                        return 'data:image/' . $type . ';base64,' . base64_encode($data);
                    })->toArray();

                $data = [
                    'reference' => $ref->getReferenceArticle()->getReference(),
                    'quantity' => $ref->getQuantity(),
                    'outFormatEquipment' => $ref->getReferenceArticle()->getDescription()['outFormatEquipment'] ?? null,
                    'manufacturerCode' => $ref->getReferenceArticle()->getDescription()['manufacturerCode'] ?? null,
                    'sealingNumber' => $ref->getSealingNumber(),
                    'serialNumber' => $ref->getSerialNumber(),
                    'batchNumber' => $ref->getBatchNumber(),
                    'width' => $ref->getReferenceArticle()->getDescription()['width'] ?? null,
                    'height' => $ref->getReferenceArticle()->getDescription()['height'] ?? null,
                    'length' => $ref->getReferenceArticle()->getDescription()['length'] ?? null,
                    'weight' => $ref->getReferenceArticle()->getDescription()['weight'] ?? null,
                    'volume' => $ref->getReferenceArticle()->getDescription()['volume'] ?? null,
                    'adr' => $ref->isADR() ? 'Oui' : 'Non',
                    'associatedDocumentTypes' => $ref->getReferenceArticle()->getDescription()['associatedDocumentTypes'] ?? null,
                    'comment' => $ref->getCleanedComment() ?: $ref->getComment(),
                    'photos' => json_encode($photos)
                ];
            }
        }

        return $this->json($data);
    }

    /**
     * @Rest\Post("/api/inventory-mission-validate-zone", name="api_inventory_mission_validate_zone", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function postInventoryMissionValidateZone(Request $request, EntityManagerInterface $entityManager): Response
    {
        $zoneId = $request->request->getInt('zone');
        $missionId = $request->request->get('mission');
        $inventoryLocationMissionRepository = $entityManager->getRepository(InventoryLocationMission::class);
        $queryResult = $inventoryLocationMissionRepository->getInventoryLocationMissionsByMission($missionId);
        $inventoryLocationMissions = Stream::from($queryResult)
            ->filter(fn(InventoryLocationMission $inventoryLocationMission) => $inventoryLocationMission->getLocation()->getZone() && $inventoryLocationMission->getLocation()->getZone()->getId() === $zoneId)
            ->toArray();

        foreach ($inventoryLocationMissions as $inventoryLocationMission){
            $inventoryLocationMission->setDone(true);
        }
        $entityManager->flush();
        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Rest\Post("/api/get-waybill-data/{dispatch}", name="api_get_waybill_data", methods="GET", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getWayBillData(EntityManagerInterface $manager, Dispatch $dispatch, DispatchService $dispatchService): Response
    {
        return $this->json([
            'success' => true,
            'data' => $dispatchService->getWayBillDataForUser($this->getUser(), $dispatch, $manager)
        ]);
    }

    /**
     * @Rest\Get("/api/get-truck-arrival-default-unloading-location", name="api_get_truck_arrival_default_unloading_location", methods="GET", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getTruckArrivalDefaultUnloadingLocation(EntityManagerInterface $manager): Response {
        $settingRepository = $manager->getRepository(Setting::class);
        $truckArrivalDefaultUnloadingLocation = $settingRepository->getOneParamByLabel(Setting::TRUCK_ARRIVALS_DEFAULT_UNLOADING_LOCATION);

        return $this->json($truckArrivalDefaultUnloadingLocation);
    }

    /**
     * @Rest\Get("/api/get-truck-arrival-lines-number", name="api_get_truck_arrival_lines_number", methods="GET", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getTruckArrivalLinesNumber(EntityManagerInterface $manager): Response {
        $truckArrivalLineRepository = $manager->getRepository(TruckArrivalLine::class);
        $truckArrivalLinesNumber = $truckArrivalLineRepository->iterateAll();

        return $this->json($truckArrivalLinesNumber);
    }

    /**
     * @Rest\Post("/api/finish-truck-arrival", name="api_finish_truck_arrival", methods="POST", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishTruckArrival(Request                $request,
                                       EntityManagerInterface $entityManager,
                                       UniqueNumberService    $uniqueNumberService,
                                       KernelInterface        $kernel): Response {
        $data = $request->request;

        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $driverRepository = $entityManager->getRepository(Chauffeur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $registrationNumber = $data->get('registrationNumber');
        $truckArrivalReserves = json_decode($data->get('truckArrivalReserves'), true);
        $truckArrivalLines = json_decode($data->get('truckArrivalLines'), true);
        $signatures = json_decode($data->get('signatures'), true) ?: [];


        $carrier = $carrierRepository->find($data->get('carrierId'));
        $driver = null;
        if ($data->get('driverId')){
            $driver = $driverRepository->find($data->get('driverId'));
        }
        $unloadingLocation = $locationRepository->find($data->get('truckArrivalUnloadingLocationId'));

        try {
            $number = $uniqueNumberService->create($entityManager, null, TruckArrival::class, UniqueNumberService::DATE_COUNTER_FORMAT_TRUCK_ARRIVAL, new DateTime(), [$carrier->getCode()]);
        } catch(Exception $e){
            return $this->json([
                'success' => false,
                'msg' => $e->getMessage()
            ]);
        }

        $truckArrival = (new TruckArrival())
            ->setNumber($number)
            ->setCarrier($carrier)
            ->setOperator($this->getUser())
            ->setDriver($driver)
            ->setRegistrationNumber($registrationNumber)
            ->setUnloadingLocation($unloadingLocation)
            ->setCreationDate(new DateTime('now'));

        foreach($truckArrivalReserves as $truckArrivalReserve){
            $reserve = (new Reserve())
                ->setType($truckArrivalReserve['type'])
                ->setComment($truckArrivalReserve['comment'] ?? null)
                ->setQuantity($truckArrivalReserve['quantity'] ?? null)
                ->setQuantityType($truckArrivalReserve['quantityType'] ?? null);

            $truckArrival->addReserve($reserve);
            $entityManager->persist($reserve);
        }

        foreach($truckArrivalLines as $truckArrivalLine){
            $line = (new TruckArrivalLine())
                ->setNumber($truckArrivalLine['number']);

            if(isset($truckArrivalLine['reserve'])){
                $lineReserve = (new Reserve())
                    ->setType($truckArrivalLine['reserve']['type'])
                    ->setComment($truckArrivalLine['reserve']['comment'] ?? null);

                if($truckArrivalLine['reserve']['photos']){
                    foreach($truckArrivalLine['reserve']['photos'] as $photo){
                        $name = uniqid();
                        $path = "{$kernel->getProjectDir()}/public/uploads/attachements/$name.jpeg";
                        file_put_contents($path, file_get_contents($photo));
                        $attachment = new Attachment();
                        $attachment
                            ->setOriginalName("$name.jpeg")
                            ->setFileName("$name.jpeg")
                            ->setFullPath("/uploads/attachements/$name.jpeg");

                        $lineReserve->addAttachment($attachment);
                        $entityManager->persist($attachment);
                    }
                }

                $line->setReserve($lineReserve);
                $entityManager->persist($lineReserve);
            }


            $truckArrival->addTrackingLine($line);
            $entityManager->persist($line);
        }

        foreach($signatures as $signature){
            $name = uniqid();
            $path = "{$kernel->getProjectDir()}/public/uploads/attachements/$name.jpeg";
            file_put_contents($path, file_get_contents($signature));
            $attachment = new Attachment();
            $attachment
                ->setOriginalName($truckArrival->getNumber()."_signature_". array_search($signature, $signatures) .".jpeg")
                ->setFileName("$name.jpeg")
                ->setFullPath("/uploads/attachements/$name.jpeg");

            $truckArrival->addAttachment($attachment);
            $entityManager->persist($attachment);
        }


        $entityManager->persist($truckArrival);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "Enregistrement"
        ]);
    }
}

