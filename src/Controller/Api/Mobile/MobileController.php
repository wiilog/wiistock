<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\Api\AbstractApiController;
use App\Entity\Article;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\Handling;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Nature;
use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\TransferOrder;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\NegativeQuantityException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Repository\ArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\TrackingMovementRepository;
use App\Service\ArrivageService;
use App\Service\AttachmentService;
use App\Service\DemandeLivraisonService;
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
use App\Service\PreparationsManagerService;
use App\Service\StatusHistoryService;
use App\Service\StatusService;
use App\Service\TrackingMovementService;
use App\Service\TransferOrderService;
use App\Service\UserService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;
use WiiCommon\Helper\Stream;


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

            $data['success'] = true;
            $data['data'] = [
                'apiKey' => $apiKey,
                'notificationChannels' => $channels,
                'rights' => $rights,
                'parameters' => $parameters,
                'username' => $loggedUser->getUsername(),
                'userId' => $loggedUser->getId(),
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
        $finishMouvementTraca = [];
        $successData['data'] = [
            'errors' => [],
        ];

        $emptyGroups = [];

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $packRepository = $entityManager->getRepository(Pack::class);

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
                        $associatedPack = $createdMvt->getPack();

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

                        // envoi de mail si c'est une dépose + le colis existe + l'emplacement est un point de livraison
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
                    $successData['data']['errors'][$mvt['ref_article']] = 'Le colis scanné est un groupe';
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
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);


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
                    $emptyGroups,
                    $emplacementRepository,
                    $articleRepository,
                    $statutRepository,
                    $trackingMovementRepository,
                    $locationDataService,
                    &$mustReloadLocation,
                    $alreadySavedMovements,
                    $trackingTypes
                ) {

                    $trackingTypes = [
                        TrackingMovement::TYPE_PRISE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_PRISE),
                        TrackingMovement::TYPE_DEPOSE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_DEPOSE),
                    ];

                    $mouvementTraca1 = $alreadySavedMovements[$mvt['date']] ?? null;
                    if (!isset($mouvementTraca1)) {
                        $options = [
                            'uniqueIdForMobile' => $mvt['date'],
                            'entityManager' => $entityManager,
                        ];

                        if (empty($trackingTypes)) {
                            $trackingTypes = [
                                TrackingMovement::TYPE_PRISE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_PRISE),
                                TrackingMovement::TYPE_DEPOSE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_DEPOSE),
                            ];
                        }

                        /** @var Statut $type */
                        $type = $trackingTypes[$mvt['type']];
                        $location = $locationDataService->findOrPersistWithCache($entityManager, $mvt['ref_emplacement'], $mustReloadLocation);

                        $dateArray = explode('_', $mvt['date']);

                        $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $dateArray[0]);

                        $options += $trackingMovementService->treatStockMovement($entityManager, $type?->getCode(), $mvt, $nomadUser, $location, $date);
                        if ($options['invalidLocationTo'] ?? null) {
                            $invalidLocationTo = $options['invalidLocationTo'];
                            throw new Exception(TrackingMovementService::INVALID_LOCATION_TO);
                        }

                        $options += $trackingMovementService->treatTrackingData($mvt, $request->files, $index);

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
                        $associatedPack = $createdMvt->getPack();

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
                    $successData['data']['errors'][$mvt['ref_article']] = 'Le colis scanné est un groupe';
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
                        $entityManager
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
                                    $movement->getQuantity(),
                                    $nomadUser,
                                    $livraison,
                                    !empty($movement->getRefArticle()),
                                    $movement->getRefArticle() ?? $movement->getArticle(),
                                    $preparation,
                                    false,
                                    $emplacementPrepa
                                );

                                $trackingMovementPrise = $this->trackingMovementService->createTrackingMovement(
                                    $movement->getRefArticle() ?? $movement->getArticle(),
                                    $movement->getEmplacementFrom(),
                                    $nomadUser,
                                    new DateTime('now'),
                                    true,
                                    true,
                                    TrackingMovement::TYPE_PRISE,
                                    [],
                                );
                                $trackingMovementPrise->setPreparation($preparation);
                                $entityManager->persist($trackingMovementPrise);

                                $trackingMovementDepose = $this->trackingMovementService->createTrackingMovement(
                                    $movement->getRefArticle() ?? $movement->getArticle(),
                                    $movement->getEmplacementTo(),
                                    $nomadUser,
                                    new DateTime('now'),
                                    true,
                                    true,
                                    TrackingMovement::TYPE_DEPOSE,
                                    [],
                                );
                                $trackingMovementDepose->setPreparation($preparation);
                                $entityManager->persist($trackingMovementDepose);

                                $entityManager->flush();
                            }
                        }

                        foreach ($totalQuantitiesWithRef as $ref => $quantity) {
                            $refArticle = $referenceArticleRepository->findOneBy(['reference' => $ref]);
                            $ligneArticle = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($refArticle, $preparation);
                            $preparationsManager->deleteLigneRefOrNot($ligneArticle, $preparation, $entityManager);
                        }

                        $insertedPreparation = $preparationsManager->treatPreparation($preparation, $nomadUser, $emplacementPrepa, $articlesToKeep, $entityManager);

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
    public function beginLivraison(Request $request, EntityManagerInterface $entityManager)
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
            $data['msg'] = "Cette livraison a déjà été prise en charge par un opérateur.";
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

            $commentaire = $request->request->get('comment');
            $treatmentDelay = $request->request->get('treatmentDelay');
            if (!empty($commentaire)) {
                $previousComments = $handling->getComment() !== '<p><br></p>' ? "{$handling->getComment()}\n" : "";
                $dateStr = (new DateTime())->format('d/m/y H:i:s');
                $dateAndUser = "<strong>$dateStr - {$nomadUser->getUsername()} :</strong>";
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
    public function finishLivraison(Request $request,
                                    ExceptionLoggerService $exceptionLoggerService,
                                    EntityManagerInterface $entityManager,
                                    LivraisonsManagerService $livraisonsManager)
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
                        $entityManager->transactional(function () use ($livraisonsManager, $entityManager, $nomadUser, $livraison, $dateEnd, $location) {
                            $livraisonsManager->setEntityManager($entityManager);
                            $livraisonsManager->finishLivraison($nomadUser, $livraison, $dateEnd, $location, true);
                            $entityManager->flush();
                        });

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

                    $message = (
                    ($throwable->getMessage() === LivraisonsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION) ? "L'emplacement que vous avez sélectionné n'existe plus." :
                        (($throwable->getMessage() === LivraisonsManagerService::LIVRAISON_ALREADY_BEGAN) ? "La livraison a déjà été commencée" :
                            false)
                    );

                    if (!$message) {
                        $exceptionLoggerService->sendLog($throwable, $request);
                    }

                    $resData['errors'][] = [
                        'numero_livraison' => $livraison->getNumero(),
                        'id_livraison' => $livraison->getId(),

                        'message' => $message ?: 'Une erreur est survenue',
                    ];
                }

                $entityManager->flush();
            }
        }

        return new JsonResponse($resData, $statusCode);
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
                                     DemandeLivraisonService    $demandeLivraisonService,
                                     LivraisonsManagerService   $livraisonsManagerService,
                                     MouvementStockService      $mouvementStockService,
                                     FreeFieldService           $freeFieldService,
                                     PreparationsManagerService $preparationsManagerService): Response
    {
        $articleRepository = $entityManager->getRepository(Article::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $nomadUser = $this->getUser();
        $location = json_decode($request->request->get('location'), true);
        $delivery = json_decode($request->request->get('delivery'), true);
        $now = new DateTime();
        $request = $demandeLivraisonService->newDemande([
            'isManual' => true,
            'type' => $delivery['type'],
            'demandeur' => $nomadUser,
            'destination' => $location['id'],
            'expectedAt' => $now->format('Y-m-d H:i:s'),
            'commentaire' => $delivery['comment'] ?? null,
        ], $entityManager, $freeFieldService, true);

        $entityManager->persist($request);
        foreach ($delivery['articles'] as $article) {
            $article = $articleRepository->findOneBy([
                'barCode' => $article['barCode']
            ]);

            $line = $demandeLivraisonService->createArticleLine($article, $request, $article->getQuantite(), $article->getQuantite());
            $entityManager->persist($line);
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
        $preparation = $request->getPreparations()->first();
        $order = $livraisonsManagerService->createLivraison($now, $preparation, $entityManager);

        foreach ($request->getArticleLines() as $articleLine) {
            $article = $articleLine->getArticle();
            $outMovement = $preparationsManagerService->createMovementLivraison(
                $article->getQuantite(),
                $nomadUser,
                $order,
                false,
                $article,
                $preparation,
                true,
                $article->getEmplacement()
            );
            $entityManager->persist($outMovement);
            $mouvementStockService->finishMouvementStock($outMovement, $now, $request->getDestination());
        }
        $preparationsManagerService->treatPreparation($preparation, $nomadUser, $request->getDestination(), [], $entityManager);
        $preparationsManagerService->updateRefArticlesQuantities($preparation, $entityManager);

        $livraisonsManagerService->finishLivraison($nomadUser, $order, $now, $request->getDestination());
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
    public function checkAndValidateDL(Request $request,
                                       EntityManagerInterface $entityManager,
                                       DemandeLivraisonService $demandeLivraisonService,
                                       FreeFieldService $champLibreService): Response
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
                                  EntityManagerInterface $entityManager): array
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
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
        $settingRepository = $entityManager->getRepository(Setting::class);

        $rights = $userService->getMobileRights($user);
        $parameters = $this->mobileApiService->getMobileParameters($settingRepository);

        $status = $statutRepository->getMobileStatus($rights['tracking'], $rights['demande']);

        if ($rights['inventoryManager']) {
            $refAnomalies = $inventoryEntryRepository->getAnomaliesOnRef(true);
            $artAnomalies = $inventoryEntryRepository->getAnomaliesOnArt(true);
        }

        if ($rights['stock']) {
            // livraisons
            $livraisons = Stream::from($livraisonRepository->getMobileDelivery($user))
                ->map(function ($deliveryArray) {
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
                ->map(function ($preparationArray) {
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

            // prises en cours
            $stockTaking = $trackingMovementRepository->getPickingByOperatorAndNotDropped($user, TrackingMovementRepository::MOUVEMENT_TRACA_STOCK);
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
        }

        if ($rights['tracking']) {
            $trackingTaking = $trackingMovementService->getMobileUserPicking($entityManager, $user);

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
            'inventoryMission' => array_merge(
                $articlesInventory ?? [],
                $refArticlesInventory ?? []
            ),
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
                            EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        return $this->json([
            "success" => true,
            "data" => $this->getDataArray($nomadUser, $userService, $trackingMovementService, $request, $entityManager),
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
    public function treatAnomalies(Request $request,
                                   InventoryService $inventoryService,
                                   ExceptionLoggerService $exceptionLoggerService)
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
                ? 'Une ou plusieus erreurs, des ordres de livraison sont en cours pour ces articles ou ils ne sont pas disponibles, veuillez recharger vos données'
                : "Aucune anomalie d'inventaire à synchroniser.")
            : ($numberOfRowsInserted . ' anomalie' . $s . ' d\'inventaire synchronisée' . $s);

        return $this->json($data);
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
     * @Rest\Get("/api/articles", name="api-get-articles", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getArticles(Request $request, EntityManagerInterface $entityManager): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $referenceActiveStatusId = $statutRepository
            ->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF)
            ->getId();

        $resData = [];

        $barCode = $request->query->get('barCode');
        $location = $request->query->get('location');

        if (!empty($barCode)) {
            $statusCode = Response::HTTP_OK;

            $referenceArticleArray = $referenceArticleRepository->getOneReferenceByBarCodeAndLocation($barCode, $location);
            if (!empty($referenceArticleArray)) {
                $referenceArticle = $referenceArticleRepository->find($referenceArticleArray['id']);
                $statusReferenceArticle = $referenceArticle->getStatut();
                $statusReferenceId = $statusReferenceArticle ? $statusReferenceArticle->getId() : null;
                // we can transfer if reference is active AND it is not linked to any active orders
                $referenceArticleArray['can_transfer'] = (
                    ($statusReferenceId === $referenceActiveStatusId)
                    && !$referenceArticleRepository->isUsedInQuantityChangingProcesses($referenceArticle)
                );
                $resData['article'] = $referenceArticleArray;
            } else {
                $article = $articleRepository->getOneArticleByBarCodeAndLocation($barCode, $location);
                if (!empty($article)) {
                    $article['can_transfer'] = ($article['reference_status'] === ReferenceArticle::STATUT_ACTIF);
                }
                $resData['article'] = $article;
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
            $pack = $trackingMovementService->persistPack($entityManager, $data["code"], $data["quantity"], $data["nature_id"]);
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
                ['commentaire' => $emptyRound['comment']]
            );

            $manager->persist($trackingMovement);
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

}
