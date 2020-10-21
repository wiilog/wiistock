<?php

namespace App\Controller;

use App\Entity\Dispatch;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\InventoryEntry;
use App\Entity\InventoryMission;
use App\Entity\LigneArticlePreparation;
use App\Entity\Livraison;
use App\Entity\Handling;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\TrackingMovement;
use App\Entity\OrdreCollecte;
use App\Entity\DispatchPack;
use App\Entity\Attachment;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Translation;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Exceptions\NegativeQuantityException;
use App\Helper\Stream;
use App\Repository\ArticleRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\InventoryMissionRepository;
use App\Repository\MailerServerRepository;
use App\Repository\TrackingMovementRepository;
use App\Repository\ReferenceArticleRepository;
use App\Service\DispatchService;
use App\Service\AttachmentService;
use App\Service\DemandeLivraisonService;
use App\Service\InventoryService;
use App\Service\LivraisonsManagerService;
use App\Service\MailerService;
use App\Service\HandlingService;
use App\Service\MouvementStockService;
use App\Service\TrackingMovementService;
use App\Service\NatureService;
use App\Service\PreparationsManagerService;
use App\Service\OrdreCollecteService;
use App\Service\UserService;
use App\Service\FreeFieldService;
use DateTimeZone;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Exception;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use DateTime;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * Class ApiController
 * @package App\Controller
 */
class ApiController extends AbstractFOSRestController implements ClassResourceInterface
{

    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    /**
     * @var array
     */
    private $successDataMsg;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var MailerServerRepository
     */
    private $mailerServerRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var InventoryService
     */
    private $inventoryService;

    /**
     * @var OrdreCollecteService
     */
    private $ordreCollecteService;

    /**
     * @var InventoryEntryRepository
     */
    private $inventoryEntryRepository;

    /**
     * ApiController constructor.
     * @param InventoryEntryRepository $inventoryEntryRepository
     * @param OrdreCollecteService $ordreCollecteService
     * @param InventoryService $inventoryService
     * @param UserService $userService
     * @param InventoryMissionRepository $inventoryMissionRepository
     * @param LoggerInterface $logger
     * @param MailerServerRepository $mailerServerRepository
     * @param MailerService $mailerService
     * @param UserPasswordEncoderInterface $passwordEncoder
     */
    public function __construct(InventoryEntryRepository $inventoryEntryRepository,
                                OrdreCollecteService $ordreCollecteService,
                                InventoryService $inventoryService,
                                UserService $userService,
                                InventoryMissionRepository $inventoryMissionRepository,
                                LoggerInterface $logger,
                                MailerServerRepository $mailerServerRepository,
                                MailerService $mailerService,
                                UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->mailerServerRepository = $mailerServerRepository;
        $this->mailerService = $mailerService;
        $this->passwordEncoder = $passwordEncoder;
        $this->logger = $logger;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->userService = $userService;
        $this->inventoryService = $inventoryService;
        $this->ordreCollecteService = $ordreCollecteService;
        $this->inventoryEntryRepository = $inventoryEntryRepository;

        // TODO supprimer et faire localement dans les méthodes
        $this->successDataMsg = ['success' => false, 'data' => [], 'msg' => ''];
    }

    /**
     * @Rest\Post("/api/api-key", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @return Response
     */
    public function postApiKey(Request $request,
                               EntityManagerInterface $entityManager,
                               UserService $userService)
    {

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $mobileKey = $request->request->get('loginKey');

        $loggedUser = $utilisateurRepository->findOneBy(['mobileLoginKey' => $mobileKey, 'status' => true]);
        $data = [];

        if (!empty($loggedUser)) {
            $apiKey = $this->apiKeyGenerator();
            $loggedUser->setApiKey($apiKey);
            $entityManager->flush();

            $data['success'] = true;
            $data['data'] = [
                'apiKey' => $apiKey,
                'rights' => $this->getMenuRights($loggedUser, $userService),
                'username' => $loggedUser->getUsername(),
                'userId' => $loggedUser->getId()
            ];
        }
        else {
            $data['success'] = false;
        }

        return new JsonResponse($data);
    }

    /**
     * @Rest\Get("/api/ping", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @return Response
     */
    public function ping()
    {
        $response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
        $this->successDataMsg['success'] = true;

        $response->setContent(json_encode($this->successDataMsg));
        return $response;
    }

    /**
     * @Rest\Post("/api/mouvements-traca", name="api-post-mouvements-traca", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param MouvementStockService $mouvementStockService
     * @param TrackingMovementService $trackingMovementService
     * @param FreeFieldService $freeFieldService
     * @param AttachmentService $attachmentService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    public function postTrackingMovement(Request $request,
                                         MouvementStockService $mouvementStockService,
                                         TrackingMovementService $trackingMovementService,
                                         FreeFieldService $freeFieldService,
                                         AttachmentService $attachmentService,
                                         EntityManagerInterface $entityManager)
    {
        $successData = [];
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST');

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $apiKey = $request->request->get('apiKey');

        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {
            $numberOfRowsInserted = 0;
            $mouvementsNomade = json_decode($request->request->get('mouvements'), true);
            $finishMouvementTraca = [];
            $successData['data'] = [
                'errors' => []
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
                                                      $trackingMovementService) {
                        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                        $articleRepository = $entityManager->getRepository(Article::class);
                        $statutRepository = $entityManager->getRepository(Statut::class);
                        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
                        $packRepository = $entityManager->getRepository(Pack::class);

                        $mouvementTraca1 = $trackingMovementRepository->findOneByUniqueIdForMobile($mvt['date']);
                        if (!isset($mouvementTraca1)) {
                            $options = [
                                'commentaire' => null,
                                'mouvementStock' => null,
                                'fileBag' => null,
                                'from' => null,
                                'uniqueIdForMobile' => $mvt['date'],
                                'entityManager' => $entityManager
                            ];
                            $location = $emplacementRepository->findOneByLabel($mvt['ref_emplacement']);
                            $type = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, $mvt['type']);

                            // création de l'emplacement s'il n'existe pas
                            if (!$location) {
                                $location = new Emplacement();
                                $location->setLabel($mvt['ref_emplacement']);
                                $entityManager->persist($location);
                            }

                            $dateArray = explode('_', $mvt['date']);

                            $date = DateTime::createFromFormat(DateTime::ATOM, $dateArray[0], new DateTimeZone('Europe/Paris'));

                            // set mouvement de stock
                            if (isset($mvt['fromStock']) && $mvt['fromStock']) {
                                if ($type->getNom() === TrackingMovement::TYPE_PRISE) {
                                    $articles = $articleRepository->findArticleByBarCodeAndLocation($mvt['ref_article'], $mvt['ref_emplacement']);
                                    /** @var Article|null $article */
                                    $article = count($articles) > 0 ? $articles[0] : null;
                                    if (!isset($article)) {
                                        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
                                        $references = $referenceArticleRepository->findReferenceByBarCodeAndLocation($mvt['ref_article'], $mvt['ref_emplacement']);
                                        /** @var ReferenceArticle|null $article */
                                        $article = count($references) > 0 ? $references[0] : null;
                                    }

                                    if (isset($article)) {
                                        $quantiteMouvement = ($article instanceof Article)
                                            ? $article->getQuantite()
                                            : $article->getQuantiteStock(); // ($article instanceof ReferenceArticle)

                                        $newMouvement = $mouvementStockService->createMouvementStock($nomadUser, $location, $quantiteMouvement, $article, MouvementStock::TYPE_TRANSFER);
                                        $options['mouvementStock'] = $newMouvement;
                                        $options['quantity'] = $newMouvement->getQuantity();
                                        $entityManager->persist($newMouvement);

                                        $configStatus = ($article instanceof Article)
                                            ? [Article::CATEGORIE, Article::STATUT_EN_TRANSIT]
                                            : [ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_INACTIF];

                                        $status = $statutRepository->findOneByCategorieNameAndStatutCode($configStatus[0], $configStatus[1]);
                                        $article->setStatut($status);
                                    }
                                }
                                else { // MouvementTraca::TYPE_DEPOSE
                                    $mouvementTracaPrises = $trackingMovementRepository->findLastTakingNotFinished($mvt['ref_article']);
                                    /** @var TrackingMovement|null $mouvementTracaPrise */
                                    $mouvementTracaPrise = count($mouvementTracaPrises) > 0 ? $mouvementTracaPrises[0] : null;
                                    if (isset($mouvementTracaPrise)) {
                                        $mouvementStockPrise = $mouvementTracaPrise->getMouvementStock();
                                        $article = $mouvementStockPrise->getArticle()
                                            ? $mouvementStockPrise->getArticle()
                                            : $mouvementStockPrise->getRefArticle();

                                        $collecteOrder = $mouvementStockPrise->getCollecteOrder();
                                        if (isset($collecteOrder)
                                            && ($article instanceof ReferenceArticle)
                                            && $article->getEmplacement()
                                            && ($article->getEmplacement()->getId() !== $location->getId())) {
                                            $invalidLocationTo = ($article->getEmplacement() ? $article->getEmplacement()->getLabel() : '');
                                            throw new Exception(TrackingMovementService::INVALID_LOCATION_TO);
                                        } else {
                                            $options['mouvementStock'] = $mouvementStockPrise;
                                            $options['quantity'] = $mouvementStockPrise->getQuantity();
                                            $mouvementStockService->finishMouvementStock($mouvementStockPrise, $date, $location);

                                            $configStatus = ($article instanceof Article)
                                                ? [Article::CATEGORIE, Article::STATUT_ACTIF]
                                                : [ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF];

                                            $status = $statutRepository->findOneByCategorieNameAndStatutCode($configStatus[0], $configStatus[1]);
                                            $article
                                                ->setStatut($status)
                                                ->setEmplacement($location);

                                            // we update quantity if it's reference article from collecte
                                            if (isset($collecteOrder) && ($article instanceof ReferenceArticle)) {
                                                $article->setQuantiteStock(($article->getQuantiteStock() ?? 0) + $mouvementStockPrise->getQuantity());
                                            }
                                        }
                                    }
                                }
                            }
                            else {
                                $options['natureId'] = $mvt['nature_id'] ?? null;
                                $options['quantity'] = $mvt['quantity'] ?? null;
                            }

                            if (!empty($mvt['comment'])) {
                                $options['commentaire'] = $mvt['comment'];
                            }

                            $signatureFile = $request->files->get("signature_$index");
                            $photoFile = $request->files->get("photo_$index");
                            if (!empty($signatureFile) || !empty($photoFile)) {
                                $options['fileBag'] = [];
                                if (!empty($signatureFile)) {
                                    $options['fileBag'][] = $signatureFile;
                                }

                                if (!empty($photoFile)) {
                                    $options['fileBag'][] = $photoFile;
                                }
                            }

                            $createdMvt = $trackingMovementService->createTrackingMovement(
                                $mvt['ref_article'],
                                $location,
                                $nomadUser,
                                $date,
                                true,
                                $mvt['finished'],
                                $type,
                                $options
                            );
                            $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                            $entityManager->persist($createdMvt);
                            $numberOfRowsInserted++;
                            if ((!isset($mvt['fromStock']) || !$mvt['fromStock'])
                                && $mvt['freeFields']) {
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
                            if ($location) {
                                $isDepose = ($mvt['type'] === TrackingMovement::TYPE_DEPOSE);
                                $colis = $packRepository->findOneBy(['code' => $mvt['ref_article']]);

                                if ($isDepose
                                    && $colis
                                    && $colis->getArrivage()
                                    && $location->getIsDeliveryPoint()) {
                                    $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
                                    $fournisseur = $fournisseurRepository->findOneByColis($colis);
                                    $arrivage = $colis->getArrivage();
                                    $destinataire = $arrivage->getDestinataire();
                                    if ($this->mailerServerRepository->findOneMailerServer()) {
                                        $this->mailerService->sendMail(
                                            'FOLLOW GT // Dépose effectuée',
                                            $this->renderView(
                                                'mails/contents/mailDeposeTraca.html.twig',
                                                [
                                                    'title' => 'Votre colis a été livré.',
                                                    'colis' => $colis->getCode(),
                                                    'emplacement' => $location,
                                                    'fournisseur' => $fournisseur ? $fournisseur->getNom() : '',
                                                    'date' => $date,
                                                    'operateur' => $nomadUser->getUsername(),
                                                    'pjs' => $arrivage->getAttachments()
                                                ]
                                            ),
                                            $destinataire->getMainAndSecondaryEmails()
                                        );
                                    } else {
                                        $this->logger->critical('Parametrage mail non defini.');
                                    }
                                }
                            }

                            if ($type->getNom() === TrackingMovement::TYPE_DEPOSE) {
                                $finishMouvementTraca[] = $mvt['ref_article'];
                            }
                        }
                    });
                }
                catch (Exception $e) {
                    if (!$entityManager->isOpen()) {
                        /** @var EntityManagerInterface $entityManager */
                        $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                        $entityManager->clear();
                        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
                        $nomadUser = $utilisateurRepository->findOneByApiKey($apiKey);
                    }

                    if ($e->getMessage() === TrackingMovementService::INVALID_LOCATION_TO) {
                        $successData['data']['errors'][$mvt['ref_article']] = ($mvt['ref_article'] . " doit être déposé sur l'emplacement \"$invalidLocationTo\"");
                    } else {
                        $successData['data']['errors'][$mvt['ref_article']] = 'Une erreur s\'est produite lors de l\'enregistrement de ' . $mvt['ref_article'];
                    }
                }
            }

            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

            // Pour tous les mouvement de prise envoyés, on les marques en fini si un mouvement de dépose a été donné
            foreach ($mouvementsNomade as $index => $mvt) {
                /** @var TrackingMovement $mouvementTracaPriseToFinish */
                $mouvementTracaPriseToFinish = $trackingMovementRepository->findOneByUniqueIdForMobile($mvt['date']);


                if (isset($mouvementTracaPriseToFinish)) {
                    $trackingPack = $mouvementTracaPriseToFinish->getPack();
                    $packCode = $trackingPack->getCode();
                    if (($mouvementTracaPriseToFinish->getType()->getNom() === TrackingMovement::TYPE_PRISE) &&
                        in_array($packCode, $finishMouvementTraca) &&
                        !$mouvementTracaPriseToFinish->isFinished()) {
                        $mouvementTracaPriseToFinish->setFinished((bool)$mvt['finished']);
                    }
                }
            }
            $entityManager->flush();

            $s = $numberOfRowsInserted > 0 ? 's' : '';
            $successData['success'] = true;
            $successData['data']['status'] = ($numberOfRowsInserted === 0)
                ? 'Aucun mouvement à synchroniser.'
                : ($numberOfRowsInserted . ' mouvement' . $s . ' synchronisé' . $s);

        } else {
            $successData['success'] = false;
            $successData['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }

        $response->setContent(json_encode($successData));
        return $response;
    }

    /**
     * @Rest\Post("/api/beginPrepa", name="api-begin-prepa", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function beginPrepa(Request $request,
                               EntityManagerInterface $entityManager)
    {
        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {
            $id = $request->request->get('id');
            $preparationRepository = $entityManager->getRepository(Preparation::class);
            $preparation = $preparationRepository->find($id);

            if (($preparation->getStatut()->getNom() == Preparation::STATUT_A_TRAITER) ||
                ($preparation->getUtilisateur() === $nomadUser)) {
                $this->successDataMsg['success'] = true;
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Cette préparation a déjà été prise en charge par un opérateur.";
                $this->successDataMsg['data'] = [];
            }
        } else {
            $this->successDataMsg['success'] = false;
            $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($this->successDataMsg);
    }

    /**
     * @Rest\Post("/api/finishPrepa", name="api-finish-prepa", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param LivraisonsManagerService $livraisonsManager
     * @param PreparationsManagerService $preparationsManager
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    public function finishPrepa(Request $request,
                                LivraisonsManagerService $livraisonsManager,
                                PreparationsManagerService $preparationsManager,
                                EntityManagerInterface $entityManager)
    {
        $resData = [];
        $insertedPrepasIds = [];
        $statusCode = Response::HTTP_OK;
        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {

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
                        $dateEnd = DateTime::createFromFormat(DateTime::ATOM, $preparationArray['date_end']);
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
                            $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);
                            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

                            $preparationsManager->setEntityManager($entityManager);
                            $mouvementsNomade = $preparationArray['mouvements'];
                            $totalQuantitiesWithRef = [];
                            $livraison = $livraisonsManager->createLivraison($dateEnd, $preparation, $entityManager);
                            $entityManager->persist($livraison);
                            $articlesToKeep = [];
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
                                // on crée les mouvements de livraison
                                if (empty($articlesToKeep)) {
                                    $articlesToKeep = $preparationsManager->createMouvementsPrepaAndSplit($preparation, $nomadUser);
                                } else {
                                    $preparationsManager->createMouvementsPrepaAndSplit($preparation, $nomadUser);
                                }
                                $emplacement = $emplacementRepository->findOneByLabel($mouvementNomade['location']);
                                $preparationsManager->createMouvementLivraison(
                                    $mouvementNomade['quantity'],
                                    $nomadUser,
                                    $livraison,
                                    $mouvementNomade['is_ref'],
                                    $mouvementNomade['reference'],
                                    $preparation,
                                    $mouvementNomade['selected_by_article'],
                                    $emplacement
                                );
                            }
                            foreach ($totalQuantitiesWithRef as $ref => $quantity) {
                                $refArticle = $referenceArticleRepository->findOneByReference($ref);
                                $ligneArticle = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($refArticle, $preparation->getDemande());
                                $preparationsManager->deleteLigneRefOrNot($ligneArticle);
                            }
                            $emplacementPrepa = $emplacementRepository->findOneByLabel($preparationArray['emplacement']);
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

                            $preparationsManager->updateRefArticlesQuantities($preparation, $entityManager);
                        });

                        $resData['success'][] = [
                            'numero_prepa' => $preparation->getNumero(),
                            'id_prepa' => $preparation->getId()
                        ];
                    } catch (Exception $exception) {
                        // we create a new entity manager because transactional() can call close() on it if transaction failed
                        if (!$entityManager->isOpen()) {
                            /** @var EntityManagerInterface $entityManager */
                            $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                            $preparationsManager->setEntityManager($entityManager);
                        }
                        $resData['errors'][] = [
                            'numero_prepa' => $preparation->getNumero(),
                            'id_prepa' => $preparation->getId(),
//TODO  CG msg prépa vide
                            'message' => (
                                ($exception instanceof NegativeQuantityException) ? "Une quantité en stock d\'un article est inférieure à sa quantité prélevée" :
                                (($exception->getMessage() === PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION) ? "L'emplacement que vous avez sélectionné n'existe plus." :
                                (($exception->getMessage() === PreparationsManagerService::ARTICLE_ALREADY_SELECTED) ? "L'article n'est pas sélectionnable" :
                                'Une erreur est survenue'))
                            )
                        ];
                    }
                }
            }

            if (!empty($insertedPrepasIds)) {
                $resData['data']['preparations'] = $preparationRepository->getMobilePreparations($nomadUser, $insertedPrepasIds);
                $resData['data']['articlesPrepa'] = $this->getArticlesPrepaArrays($insertedPrepasIds, true);
                $resData['data']['articlesPrepaByRefArticle'] = $articleRepository->getArticlePrepaForPickingByUser($nomadUser, $insertedPrepasIds);
            }

            $preparationsManager->removeRefMouvements();
            $entityManager->flush();
        } else {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $resData['success'] = false;
            $resData['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }

        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/beginLivraison", name="api-begin-livraison", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function beginLivraison(Request $request,
                                   EntityManagerInterface $entityManager)
    {
        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {

            $em = $this->getDoctrine()->getManager();

            $id = $request->request->get('id');
            $livraison = $livraisonRepository->find($id);

            if (
                ($livraison->getStatut()->getNom() == Livraison::STATUT_A_TRAITER) &&
                (empty($livraison->getUtilisateur()) || $livraison->getUtilisateur() === $nomadUser)
            ) {
                // modif de la livraison
                $livraison->setUtilisateur($nomadUser);

                $em->flush();

                $this->successDataMsg['success'] = true;
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Cette livraison a déjà été prise en charge par un opérateur.";
            }
        } else {
            $this->successDataMsg['success'] = false;
            $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($this->successDataMsg);
    }

    /**
     * @Rest\Post("/api/beginCollecte", name="api-begin-collecte", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function beginCollecte(Request $request,
                                  EntityManagerInterface $entityManager)
    {
        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {
            $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);

            $id = $request->request->get('id');
            $ordreCollecte = $ordreCollecteRepository->find($id);

            if ($ordreCollecte->getStatut()->getNom() == OrdreCollecte::STATUT_A_TRAITER &&
                (empty($ordreCollecte->getUtilisateur()) || $ordreCollecte->getUtilisateur() === $nomadUser)) {
                // modif de la collecte
                $ordreCollecte->setUtilisateur($nomadUser);

                $entityManager->flush();

                $this->successDataMsg['success'] = true;
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Cette collecte a déjà été prise en charge par un opérateur.";
            }
        } else {
            $this->successDataMsg['success'] = false;
            $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($this->successDataMsg);
    }

    /**
     * @Rest\Post("/api/handlings", name="api-validate-handling", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param AttachmentService $attachmentService
     * @param EntityManagerInterface $entityManager
     * @param FreeFieldService $freeFieldService
     * @param HandlingService $handlingService
     * @return JsonResponse
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function postHandlings(Request $request,
                                  AttachmentService $attachmentService,
                                  EntityManagerInterface $entityManager,
                                  FreeFieldService $freeFieldService,
                                  HandlingService $handlingService)
    {
        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $data = [];

        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {
            $id = $request->request->get('id');
            /** @var Handling $handling */
            $handling = $handlingRepository->find($id);
            $oldStatus = $handling->getStatus();

            if (!$oldStatus || !$oldStatus->isTreated()) {
                $statusId = $request->request->get('statusId');
                $newStatus = $statusRepository->find($statusId);
                if (!empty($newStatus)) {
                    $handling->setStatus($newStatus);
                }

                $commentaire = $request->request->get('comment');
                $treatmentDelay = $request->request->get('treatmentDelay');
                if (!empty($commentaire)) {
                    $handling->setComment($handling->getComment() . "\n" . date('d/m/y H:i:s') . " - " . $nomadUser->getUsername() . " :\n" . $commentaire);
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
                }
                while (!empty($photoFile) && $fileCounter <= $maxNbFilesSubmitted);

                $freeFieldValuesStr = $request->request->get('freeFields', '{}');
                $freeFieldValuesStr = json_decode($freeFieldValuesStr, true);
                $freeFieldService->manageFreeFields($handling, $freeFieldValuesStr, $entityManager);

                if (!$handling->getValidationDate()
                    && $newStatus
                    && $newStatus->isTreated()) {
                    $handling
                        ->setValidationDate(new DateTime('now', new DateTimeZone('Europe/Paris')))
                        ->setTreatedByHandling($nomadUser);
                };
                $entityManager->flush();

                if ((!$oldStatus && $newStatus)
                    || (
                        $oldStatus
                        && $newStatus
                        && ($oldStatus->getId() !== $newStatus->getId())
                    )) {
                    $handlingService->sendEmailsAccordingToStatus($handling);
                }

                $data['success'] = true;
            } else {
                $data['success'] = false;
                $data['message'] = "Cette demande de service a déjà été prise en charge par un opérateur.";
            }
        } else {
            $data['success'] = false;
            $data['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($data);
    }

    /**
     * @Rest\Post("/api/finishLivraison", name="api-finish-livraison", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param LivraisonsManagerService $livraisonsManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws Throwable
     */
    public function finishLivraison(Request $request,
                                    EntityManagerInterface $entityManager,
                                    LivraisonsManagerService $livraisonsManager)
    {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {
            $livraisons = json_decode($request->request->get('livraisons'), true);
            $resData = ['success' => [], 'errors' => []];

            // on termine les livraisons
            // même comportement que LivraisonController.finish()
            foreach ($livraisons as $livraisonArray) {
                $livraison = $livraisonRepository->find($livraisonArray['id']);

                if ($livraison) {
                    $dateEnd = DateTime::createFromFormat(DateTime::ATOM, $livraisonArray['date_end']);
                    $location = $emplacementRepository->findOneByLabel($livraisonArray['location']);
                    try {
                        if ($location) {
                            // flush auto at the end
                            $entityManager->transactional(function () use ($livraisonsManager, $entityManager, $nomadUser, $livraison, $dateEnd, $location) {
                                $livraisonsManager->setEntityManager($entityManager);
                                $livraisonsManager->finishLivraison($nomadUser, $livraison, $dateEnd, $location);
                                $entityManager->flush();
                            });

                            $resData['success'][] = [
                                'numero_livraison' => $livraison->getNumero(),
                                'id_livraison' => $livraison->getId()
                            ];
                        } else {
                            throw new Exception(LivraisonsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
                        }
                    } catch (Exception $exception) {
                        // we create a new entity manager because transactional() can call close() on it if transaction failed
                        if (!$entityManager->isOpen()) {
                            $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                            $livraisonsManager->setEntityManager($entityManager);
                        }

                        $resData['errors'][] = [
                            'numero_livraison' => $livraison->getNumero(),
                            'id_livraison' => $livraison->getId(),

                            'message' => (
                            ($exception->getMessage() === LivraisonsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION) ? "L'emplacement que vous avez sélectionné n'existe plus." :
                                (($exception->getMessage() === LivraisonsManagerService::LIVRAISON_ALREADY_BEGAN) ? "La livraison a déjà été commencée" :
                                    'Une erreur est survenue')
                            )
                        ];
                    }

                    $entityManager->flush();
                }
            }

            $this->successDataMsg['success'] = true;

        } else {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $resData['success'] = false;
            $resData['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/finishCollecte", name="api-finish-collecte", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param OrdreCollecteService $ordreCollecteService
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws Throwable
     */
    public function finishCollecte(Request $request,
                                   OrdreCollecteService $ordreCollecteService,
                                   EntityManagerInterface $entityManager)
    {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {
            $resData = ['success' => [], 'errors' => [], 'data' => []];

            $collectes = json_decode($request->request->get('collectes'), true);

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
                        $date = DateTime::createFromFormat(DateTime::ATOM, $collecteArray['date_end'], new DateTimeZone('Europe/Paris'));

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
                            'id_collecte' => $collecte->getId()
                        ];

                        $newTakings = $trackingMovementRepository->getTakingByOperatorAndNotDeposed(
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
                }
                catch (Exception $exception) {
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

                    $resData['errors'][] = [
                        'numero_collecte' => $collecte->getNumero(),
                        'id_collecte' => $collecte->getId(),

                        'message' => (
                            ($exception instanceof ArticleNotAvailableException) ? ("Une référence de la collecte n'est pas active, vérifiez les transferts de stock en cours associés à celle-ci.") :
                            (($exception->getMessage() === OrdreCollecteService::COLLECTE_ALREADY_BEGUN) ? ("La collecte " . $collecte->getNumero() . " a déjà été effectuée (par " . $user . ").") :
                            (($exception->getMessage() === OrdreCollecteService::COLLECTE_MOUVEMENTS_EMPTY) ? ("La collecte " . $collecte->getNumero() . " ne contient aucun article.") :
                            'Une erreur est survenue'))
                        )
                    ];
                }
            }
        } else {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $resData['success'] = false;
            $resData['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/valider-dl", name="api_validate_dl", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DemandeLivraisonService $demandeLivraisonService
     * @param FreeFieldService $champLibreService
     * @return Response
     * @throws ArticleNotAvailableException
     * @throws DBALException
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RequestNeedToBeProcessedException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function checkAndValidateDL(Request $request,
                                       EntityManagerInterface $entityManager,
                                       DemandeLivraisonService $demandeLivraisonService,
                                       FreeFieldService $champLibreService): Response
    {
        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $nomadUser = $utilisateurRepository->findOneByApiKey($apiKey);
        if ($nomadUser) {
            $demandeArray = json_decode($request->request->get('demande'), true);
            $demandeArray['demandeur'] = $nomadUser;
            $responseAfterQuantitiesCheck = $demandeLivraisonService->checkDLStockAndValidate(
                $entityManager,
                $demandeArray,
                true,
                $champLibreService
            );
        } else {
            $responseAfterQuantitiesCheck = [
                'success' => false,
                'message' => "Vous n'avez pas pu être autentifié, veuillez vous reconnecter.",
            ];
        }
        return new JsonResponse($responseAfterQuantitiesCheck);
    }

    /**
     * @Rest\Post("/api/addInventoryEntries", name="api-add-inventory-entry", condition="request.isXmlHttpRequest()")
     * @Rest\Get("/api/addInventoryEntries")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function addInventoryEntries(Request $request, EntityManagerInterface $entityManager)
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {
            $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
            $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $numberOfRowsInserted = 0;

            $entries = json_decode($request->request->get('entries'), true);
            $newAnomalies = [];

            foreach ($entries as $entry) {
                $mission = $inventoryMissionRepository->find($entry['id_mission']);
                $location = $emplacementRepository->findOneByLabel($entry['location']);

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
                        $inventoryEntry->setAnomaly($isAnomaly);

                        if (!$isAnomaly) {
                            $articleToInventory->setDateLastInventory($newDate);
                        }
                    } else {
                        $inventoryEntry->setArticle($articleToInventory);
                        $isAnomaly = ($inventoryEntry->getQuantity() !== $articleToInventory->getQuantite());
                        $inventoryEntry->setAnomaly($isAnomaly);

                        if (!$isAnomaly) {
                            $articleToInventory->setDateLastInventory($newDate);
                        }
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
            $this->successDataMsg['success'] = true;
            $this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0)
                ? "Aucune saisie d'inventaire à synchroniser."
                : ($numberOfRowsInserted . ' inventaire' . $s . ' synchronisé' . $s);
            $this->successDataMsg['data']['anomalies'] = array_merge(
                $inventoryEntryRepository->getAnomaliesOnRef(true, $newAnomaliesIds),
                $inventoryEntryRepository->getAnomaliesOnArt(true, $newAnomaliesIds)
            );
        } else {
            $this->successDataMsg['success'] = false;
            $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }

        $response->setContent(json_encode($this->successDataMsg));
        return $response;
    }

    /**
     * @Rest\Get("/api/demande-livraison-data", name="api_get_demande_livraison_data")
     * @Rest\View()
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getDemandeLivraisonData(Request $request,
                                            UserService $userService,
                                            EntityManagerInterface $entityManager): Response
    {

        $apiKey = $request->query->get('apiKey');
        $dataResponse = [];
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {
            $httpCode = Response::HTTP_OK;
            $dataResponse['success'] = true;

            $rights = $this->getMenuRights($nomadUser, $userService);
            if ($rights['demande']) {
                $dataResponse['data'] = [
                    'demandeLivraisonArticles' => $referenceArticleRepository->getByNeedsMobileSync(),
                    'demandeLivraisonTypes' => array_map(function (Type $type) {
                        return [
                            'id' => $type->getId(),
                            'label' => $type->getLabel(),
                        ];
                    }, $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]))
                ];
            } else {
                $dataResponse['data'] = [
                    'demandeLivraisonArticles' => [],
                    'demandeLivraisonTypes' => []
                ];
            }
        } else {
            $httpCode = Response::HTTP_UNAUTHORIZED;
            $dataResponse['success'] = false;
            $dataResponse['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }

        return new JsonResponse($dataResponse, $httpCode);
    }


    /**
     * @param Utilisateur $user
     * @param UserService $userService
     * @param NatureService $natureService
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return array
     * @throws Exception
     */
    private function getDataArray(Utilisateur $user,
                                  UserService $userService,
                                  NatureService $natureService,
                                  Request $request,
                                  EntityManagerInterface $entityManager)
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
        $translationsRepository = $entityManager->getRepository(Translation::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $attachmentRepository = $entityManager->getRepository(Attachment::class);

        $rights = $this->getMenuRights($user, $userService);

        $status = $statutRepository->getMobileStatus($rights['tracking'], $rights['demande']);

        if ($rights['inventoryManager']) {
            $refAnomalies = $inventoryEntryRepository->getAnomaliesOnRef(true);
            $artAnomalies = $inventoryEntryRepository->getAnomaliesOnArt(true);
        } else {
            $refAnomalies = [];
            $artAnomalies = [];
        }

        if ($rights['stock']) {
            // livraisons
            $livraisons = $livraisonRepository->getMobileDelivery($user);

            $livraisonsIds = array_map(function ($livraisonArray) {
                return $livraisonArray['id'];
            }, $livraisons);

            $articlesLivraison = $articleRepository->getByLivraisonsIds($livraisonsIds);
            $refArticlesLivraison = $referenceArticleRepository->getByLivraisonsIds($livraisonsIds);

            /// preparations
            $preparations = $preparationRepository->getMobilePreparations($user);

            /// collecte
            $collectes = $ordreCollecteRepository->getMobileCollecte($user);

            /// On tronque le commentaire à 200 caractères (sans les tags)
            $collectes = array_map(function ($collecteArray) {
                if(!empty($collecteArray['comment'])) {
                    $collecteArray['comment'] = substr(strip_tags($collecteArray['comment']), 0, 200);
                }
                return $collecteArray;
            }, $collectes);

            $collectesIds = array_map(function ($collecteArray) {
                return $collecteArray['id'];
            }, $collectes);
            $articlesCollecte = $articleRepository->getByOrdreCollectesIds($collectesIds);
            $refArticlesCollecte = $referenceArticleRepository->getByOrdreCollectesIds($collectesIds);

            // get article linked to a ReferenceArticle where type_quantite === 'article'
            $articlesPrepaByRefArticle = $articleRepository->getArticlePrepaForPickingByUser($user);

            // inventory
            $articlesInventory = $this->inventoryMissionRepository->getCurrentMissionArticlesNotTreated();
            $refArticlesInventory = $this->inventoryMissionRepository->getCurrentMissionRefNotTreated();

            // prises en cours
            $stockTaking = $trackingMovementRepository->getTakingByOperatorAndNotDeposed($user, TrackingMovementRepository::MOUVEMENT_TRACA_STOCK);
        } else {
            // livraisons
            $livraisons = [];
            $articlesLivraison = [];
            $refArticlesLivraison = [];

            /// preparations
            $preparations = [];

            /// collecte
            $collectes = [];
            $articlesCollecte = [];
            $refArticlesCollecte = [];

            // get article linked to a ReferenceArticle where type_quantite === 'article'
            $articlesPrepaByRefArticle = [];

            // inventory
            $articlesInventory = [];
            $refArticlesInventory = [];

            // prises en cours
            $stockTaking = [];
        }

        if ($rights['demande']) {
            $handlings = $handlingRepository->getMobileHandlingsByUserTypes($user->getHandlingTypeIds());
            $handlingIds = array_map(function (array $handling) {
                return $handling['id'];
            }, $handlings);
            $handlingAttachments = array_map(
                function (array $attachment) use ($request) {
                    return [
                        'handlingId' => $attachment['handlingId'],
                        'fileName' => $attachment['originalName'],
                        'href' => $request->getSchemeAndHttpHost() . '/uploads/attachements/' . $attachment['fileName']
                    ];
                },
                $attachmentRepository->getMobileAttachmentForHandling($handlingIds)
            );

            $requestFreeFields = $freeFieldRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_HANDLING]);

            $demandeLivraisonArticles = $referenceArticleRepository->getByNeedsMobileSync();
            $demandeLivraisonTypes = array_map(function (Type $type) {
                return [
                    'id' => $type->getId(),
                    'label' => $type->getLabel(),
                ];
            }, $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]));
        } else {
            $handlings = [];
            $requestFreeFields = [];
            $handlingAttachments = [];
            $demandeLivraisonArticles = [];
            $demandeLivraisonTypes = [];
        }

        if ($rights['tracking']) {
            $trackingTaking = $trackingMovementRepository->getTakingByOperatorAndNotDeposed($user, TrackingMovementRepository::MOUVEMENT_TRACA_DEFAULT);
            $natures = array_map(
                function (Nature $nature) use ($natureService) {
                    return $natureService->serializeNature($nature);
                },
                $natureRepository->findAll()
            );
            $allowedNatureInLocations = $natureRepository->getAllowedNaturesIdByLocation();
            $trackingFreeFields = $freeFieldRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]);

            $dispatches = $dispatchRepository->getMobileDispatches($user);
            $dispatchPacks = $dispatchPackRepository->getMobilePacksFromDispatches(array_map(function ($dispatch) {
                return $dispatch['id'];
            }, $dispatches));

        } else {
            $trackingTaking = [];
            $natures = [];
            $allowedNatureInLocations = [];
            $trackingFreeFields = [];
            $dispatches = [];
            $dispatchPacks = [];
            $status = [];
        }

        return [
            'locations' => $emplacementRepository->getLocationsArray(),
            'allowedNatureInLocations' => $allowedNatureInLocations,
            'freeFields' => Stream::from($trackingFreeFields, $requestFreeFields)
                ->map(function (FreeField $freeField) {
                    return $freeField->serialize();
                })
                ->toArray(),
            'preparations' => $preparations,
            'articlesPrepa' => $this->getArticlesPrepaArrays($preparations),
            'articlesPrepaByRefArticle' => $articlesPrepaByRefArticle,
            'livraisons' => $livraisons,
            'articlesLivraison' => array_merge($articlesLivraison, $refArticlesLivraison),
            'collectes' => $collectes,
            'articlesCollecte' => array_merge($articlesCollecte, $refArticlesCollecte),
            'handlings' => $handlings,
            'handlingAttachments' => $handlingAttachments,
            'inventoryMission' => array_merge($articlesInventory, $refArticlesInventory),
            'anomalies' => array_merge($refAnomalies, $artAnomalies),
            'trackingTaking' => $trackingTaking,
            'stockTaking' => $stockTaking,
            'demandeLivraisonTypes' => $demandeLivraisonTypes,
            'demandeLivraisonArticles' => $demandeLivraisonArticles,
            'natures' => $natures,
            'rights' => $rights,
            'translations' => $translationsRepository->findAllObjects(),
            'dispatches' => $dispatches,
            'dispatchPacks' => $dispatchPacks,
            'status' => $status
        ];
    }

    /**
     * @Rest\Post("/api/getData", name="api-get-data")
     * @param Request $request
     * @param UserService $userService
     * @param NatureService $natureService
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function getData(Request $request,
                            UserService $userService,
                            NatureService $natureService,
                            EntityManagerInterface $entityManager)
    {
        $apiKey = $request->request->get('apiKey');
        $dataResponse = [];
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {
            $httpCode = Response::HTTP_OK;
            $dataResponse['success'] = true;
            $dataResponse['data'] = $this->getDataArray($nomadUser, $userService, $natureService, $request, $entityManager);
        } else {
            $httpCode = Response::HTTP_UNAUTHORIZED;
            $dataResponse['success'] = false;
            $dataResponse['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }

        return new JsonResponse($dataResponse, $httpCode);
    }

    private function apiKeyGenerator()
    {
        return md5(microtime() . rand());
    }

    /**
     * @Rest\Get("/api/nomade-versions", condition="request.isXmlHttpRequest()")
     */
    public function getAvailableVersionsAction()
    {
        return new JsonResponse($this->getParameter('nomade_versions') ?? '*');
    }

    /**
     * @Rest\Post("/api/treatAnomalies", name= "api-treat-anomalies-inv", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function treatAnomalies(Request $request,
                                   EntityManagerInterface $entityManager)
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

        $apiKey = $request->request->get('apiKey');
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($apiKey)) {

            $numberOfRowsInserted = 0;

            $anomalies = json_decode($request->request->get('anomalies'), true);
            $errors = [];
            foreach ($anomalies as $anomaly) {
                try {
                    $this->inventoryService->doTreatAnomaly(
                        $anomaly['id'],
                        $anomaly['reference'],
                        $anomaly['is_ref'],
                        $anomaly['quantity'],
                        $anomaly['comment'],
                        $nomadUser
                    );
                    $numberOfRowsInserted++;
                }
                catch (ArticleNotAvailableException|RequestNeedToBeProcessedException $exception) {
                    $errors[] = $anomaly['id'];
                }
            }

            $s = $numberOfRowsInserted > 1 ? 's' : '';
            $this->successDataMsg['success'] = true;
            $this->successDataMsg['errors'] = $errors;
            $this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0)
                ? ($anomalies > 0
                    ? 'Une ou plusieus erreurs, des ordres de livraison sont en cours pour ces articles ou ils ne sont pas disponibles, veuillez recharger vos données'
                    : "Aucune anomalie d'inventaire à synchroniser.")
                : ($numberOfRowsInserted . ' anomalie' . $s . ' d\'inventaire synchronisée' . $s);
        } else {
            $this->successDataMsg['success'] = false;
            $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }

        $response->setContent(json_encode($this->successDataMsg));
        return $response;
    }

    /**
     * @Rest\Post("/api/emplacement", name="api-new-emp", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function addEmplacement(Request $request,
                                   EntityManagerInterface $entityManager)
    {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($request->request->get('apiKey'))) {
            if (!$emplacementRepository->findOneByLabel($request->request->get('label'))) {
                $toInsert = new Emplacement();
                $toInsert
                    ->setLabel($request->request->get('label'))
                    ->setIsActive(true)
                    ->setDescription('')
                    ->setIsDeliveryPoint((bool)$request->request->get('isDelivery'));
                $em = $this->getDoctrine()->getManager();
                $em->persist($toInsert);
                $em->flush();
                $resData['success'] = true;
                $resData['msg'] = $toInsert->getId();
            } else {
                $statusCode = Response::HTTP_BAD_REQUEST;
                $resData['success'] = false;
                $resData['msg'] = "Un emplacement portant ce nom existe déjà.";
            }
        } else {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $resData['success'] = false;
            $resData['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Get("/api/articles", name="api-get-articles", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getArticles(Request $request, EntityManagerInterface $entityManager): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $referenceActiveStatusId = $statutRepository
            ->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF)
            ->getId();

        $resData = [];
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($request->query->get('apiKey'))) {
            $barCode = $request->query->get('barCode');
            $location = $request->query->get('location');

            if (!empty($barCode) && !empty($location)) {
                $statusCode = Response::HTTP_OK;

                $referenceArticleArray = $referenceArticleRepository->getOneReferenceByBarCodeAndLocation($barCode, $location);
                if (!empty($referenceArticleArray)) {
                    $referenceArticle = $referenceArticleRepository->find($referenceArticleArray['id']);
                    $statusReferenceArticle = $referenceArticle->getStatut();
                    $statusReferenceId = $statusReferenceArticle ? $statusReferenceArticle->getId() : null;
                    // we can transfer if reference is active AND it is not linked to any active orders
                    $referenceArticleArray['can_transfer'] = (
                        ($statusReferenceId === $referenceActiveStatusId)
                        && !$referenceArticle->isUsedInQuantityChangingProcesses()
                    );
                    $resData['article'] = $referenceArticleArray;
                }
                else {
                    $article = $articleRepository->getOneArticleByBarCodeAndLocation($barCode, $location);
                    if (!empty($article)) {
                        $article['can_transfer'] = ($article['reference_status'] === ReferenceArticle::STATUT_ACTIF);
                    }
                    $resData['article'] = $article;
                }

                if (!empty($resData['article'])) {
                    $resData['article']['is_ref'] = (int) $resData['article']['is_ref'];
                }

                $resData['success'] = !empty($resData['article']);
            } else {
                $statusCode = Response::HTTP_BAD_REQUEST;
                $resData['success'] = false;
                $resData['article'] = null;
            }
        } else {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $resData['success'] = false;
            $resData['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Get("/api/tracking-drops", name="api-get-tracking-drops-on-location", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getTrackingDropsOnLocation(Request $request,
                                               EntityManagerInterface $entityManager): Response
    {
        $resData = [];
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        if ($nomadUser = $utilisateurRepository->findOneByApiKey($request->query->get('apiKey'))) {
            $statusCode = Response::HTTP_OK;

            $locationLabel = $request->query->get('location');
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            $location = !empty($locationLabel)
                ? $emplacementRepository->findOneByLabel($locationLabel)
                : null;

            if (!empty($locationLabel) && !isset($location)) {
                $location = $emplacementRepository->find($locationLabel);
            }

            if (!empty($location)) {
                $resData['success'] = true;
                $resData['trackingDrops'] = [];
                // TODO AB : mettre en place la pagination si volume de données tro volumineux
//                $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
                // $resData['trackingDrops'] = $mouvementTracaRepository->getLastTrackingMovementsOnLocations([$location]);
            } else {
                $resData['success'] = true;
                $resData['trackingDrops'] = [];
            }
        } else {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $resData['success'] = false;
            $resData['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Get("/api/packs/{code}/nature", name="api_pack_nature", condition="request.isXmlHttpRequest()")
     * @param EntityManagerInterface $entityManager
     * @param NatureService $natureService
     * @param string $code
     * @return JsonResponse
     */
    public function getPackNature(EntityManagerInterface $entityManager,
                                  NatureService $natureService,
                                  string $code): JsonResponse {
        $packRepository = $entityManager->getRepository(Pack::class);
        $packs = $packRepository->findBy(['code' => $code]);

        if (!empty($packs)) {
            $pack = $packs[0];
            $nature = $pack->getNature();
        }

        return new JsonResponse([
            'success' => true,
            'nature' => !empty($nature)
                ? $natureService->serializeNature($nature)
                : null
        ]);
    }

    /**
     * @Rest\Post("/api/dispatches", name="api_patch_dispatches", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param DispatchService $dispatchService
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function patchDispatches(Request $request,
                                    DispatchService $dispatchService,
                                    EntityManagerInterface $entityManager): JsonResponse {

        $resData = [];
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $nomadUser = $utilisateurRepository->findOneByApiKey($request->request->get('apiKey'));
        if ($nomadUser) {
            $dispatches = json_decode($request->request->get('dispatches'), true);
            $dispatchPacksParam = json_decode($request->request->get('dispatchPacks'), true);

            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $statusRepository = $entityManager->getRepository(Statut::class);
            $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
            $natureRepository = $entityManager->getRepository(Nature::class);

            $entireTreatedDispatch = [];

            $dispatchPacksByDispatch = is_array($dispatchPacksParam)
                ? array_reduce($dispatchPacksParam, function (array $acc, array $current) {
                    $id = (int) $current['id'];
                    $natureId = $current['natureId'];
                    $quantity = $current['quantity'];
                    $dispatchId = (int) $current['dispatchId'];
                    if (!isset($acc[$dispatchId])) {
                        $acc[$dispatchId] = [];
                    }
                    $acc[$dispatchId][] = [
                        'id' => $id,
                        'natureId' => $natureId,
                        'quantity' => $quantity
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
                                if (!empty($packDispatch)) {
                                    if (!empty($packArray['natureId'])) {
                                        $nature = $natureRepository->find($packArray['natureId']);
                                        if ($nature) {
                                            $pack = $packDispatch->getPack();
                                            $pack->setNature($nature);
                                        }
                                    }

                                    $quantity = (int) $packArray['quantity'];
                                    if ($quantity > 0) {
                                        $packDispatch->setQuantity($quantity);
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
        }
        else {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $resData['success'] = false;
            $resData['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }
        return new JsonResponse($resData, $statusCode);
    }

    private function getArticlesPrepaArrays(array $preparations, bool $isIdArray = false): array
    {
        $entityManager = $this->getDoctrine()->getManager();
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

    private function getMenuRights($user, UserService $userService)
    {
        return [
            'demoMode' => $userService->hasRightFunction(Menu::NOMADE, Action::DEMO_MODE, $user),
            'stock' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_STOCK, $user),
            'tracking' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_TRACA, $user),
            'demande' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_HAND, $user),
            'inventoryManager' => $userService->hasRightFunction(Menu::STOCK, Action::INVENTORY_MANAGER, $user)
        ];
    }

}
