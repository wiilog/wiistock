<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\InventoryEntry;
use App\Entity\Livraison;
use App\Entity\Manutention;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Repository\ColisRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\InventoryMissionRepository;
use App\Repository\LigneArticlePreparationRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\LivraisonRepository;
use App\Repository\MailerServerRepository;
use App\Repository\ManutentionRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\PreparationRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Service\AttachmentService;
use App\Service\InventoryService;
use App\Service\LivraisonsManagerService;
use App\Service\MailerService;
use App\Service\MouvementStockService;
use App\Service\MouvementTracaService;
use App\Service\PreparationsManagerService;
use App\Service\OrdreCollecteService;
use App\Service\UserService;
use DateTimeZone;
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


/**
 * Class ApiController
 * @package App\Controller
 */
class ApiController extends AbstractFOSRestController implements ClassResourceInterface
{

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

    /**
     * @var ColisRepository
     */
    private $colisRepository;

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
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

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
     * @var ManutentionRepository
     */
    private $manutentionRepository;

    /**
     * @var OrdreCollecteRepository
     */
    private $ordreCollecteRepository;

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
     * @param ManutentionRepository $manutentionRepository
     * @param OrdreCollecteService $ordreCollecteService
     * @param OrdreCollecteRepository $ordreCollecteRepository
     * @param InventoryService $inventoryService
     * @param UserService $userService
     * @param InventoryMissionRepository $inventoryMissionRepository
     * @param FournisseurRepository $fournisseurRepository
     * @param LivraisonRepository $livraisonRepository
     * @param StatutRepository $statutRepository
     * @param PreparationRepository $preparationRepository
     * @param LoggerInterface $logger
     * @param MailerServerRepository $mailerServerRepository
     * @param MailerService $mailerService
     * @param ColisRepository $colisRepository
     * @param MouvementTracaRepository $mouvementTracaRepository
     * @param ReferenceArticleRepository $referenceArticleRepository
     * @param UtilisateurRepository $utilisateurRepository
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param ArticleRepository $articleRepository
     * @param EmplacementRepository $emplacementRepository
     */
    public function __construct(InventoryEntryRepository $inventoryEntryRepository,
                                ManutentionRepository $manutentionRepository,
                                OrdreCollecteService $ordreCollecteService,
                                OrdreCollecteRepository $ordreCollecteRepository,
                                InventoryService $inventoryService,
                                UserService $userService,
                                InventoryMissionRepository $inventoryMissionRepository,
                                FournisseurRepository $fournisseurRepository,
                                LivraisonRepository $livraisonRepository,
                                StatutRepository $statutRepository,
                                PreparationRepository $preparationRepository,
                                LoggerInterface $logger,
                                MailerServerRepository $mailerServerRepository,
                                MailerService $mailerService,
                                ColisRepository $colisRepository,
                                MouvementTracaRepository $mouvementTracaRepository,
                                ReferenceArticleRepository $referenceArticleRepository,
                                UtilisateurRepository $utilisateurRepository,
                                UserPasswordEncoderInterface $passwordEncoder,
                                ArticleRepository $articleRepository,
                                EmplacementRepository $emplacementRepository)
    {
        $this->manutentionRepository = $manutentionRepository;
        $this->mailerServerRepository = $mailerServerRepository;
        $this->mailerService = $mailerService;
        $this->colisRepository = $colisRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->logger = $logger;
        $this->preparationRepository = $preparationRepository;
        $this->statutRepository = $statutRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->userService = $userService;
        $this->inventoryService = $inventoryService;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->ordreCollecteService = $ordreCollecteService;
        $this->inventoryEntryRepository = $inventoryEntryRepository;

        // TODO supprimer et faire localement dans les méthodes
        $this->successDataMsg = ['success' => false, 'data' => [], 'msg' => ''];
    }

    /**
     * @Rest\Post("/api/connect", name="api-connect")
     * @Rest\View()
     * @param Request $request
     * @return Response
     */
    public function connection(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $response = new Response();

            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            $user = $this->utilisateurRepository->findOneBy(['username' => $request->request->get('login')]);

            if ($user !== null) {
                if ($this->passwordEncoder->isPasswordValid($user, $request->request->get('password'))) {
                    $apiKey = $this->apiKeyGenerator();

                    $user->setApiKey($apiKey);
                    $em = $this->getDoctrine()->getManager();
                    $em->flush();

                    $isInventoryManager = $this->userService->hasRightFunction(Menu::INVENTAIRE, Action::INVENTORY_MANAGER, $user);

                    $this->successDataMsg['success'] = true;
                    $this->successDataMsg['data'] = [
                        'isInventoryManager' => $isInventoryManager,
                        'apiKey' => $apiKey
                    ];
                }
            }

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

    /**
     * @Rest\Get("/api/ping")
     * @Rest\View()
     * @param Request $request
     * @return Response
     */
    public function ping(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $response = new Response();

            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
            $this->successDataMsg['success'] = true;

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/mouvements-traca", name="api-post-mouvements-traca")
     * @Rest\View()
     * @param Request $request
     * @param MouvementStockService $mouvementStockService
     * @param AttachmentService $attachmentService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function postMouvementsTraca(Request $request,
                                        MouvementStockService $mouvementStockService,
                                        AttachmentService $attachmentService,
                                        EntityManagerInterface $entityManager)
    {
        $successData = [];
        if (!$request->isXmlHttpRequest()) {
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST');

            $apiKey = $request->request->get('apiKey');

            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {
                $numberOfRowsInserted = 0;
                $mouvementsNomade = json_decode($request->request->get('mouvements'), true);
                $finishMouvementTraca = [];
                $successData['data'] = [
                    'errors' => []
                ];

                foreach ($mouvementsNomade as $index => $mvt) {
                    $invalidLocationTo = '';
                    try {
                        $entityManager->transactional(function (EntityManagerInterface $entityManager)
                                                      use ($mouvementStockService, &$numberOfRowsInserted, $mvt, $nomadUser, $request, $attachmentService, $index, &$invalidLocationTo, &$finishMouvementTraca) {
                            $mouvementTraca = $this->mouvementTracaRepository->findOneByUniqueIdForMobile($mvt['date']);
                            if (!isset($mouvementTraca)) {
                                $location = $this->emplacementRepository->findOneByLabel($mvt['ref_emplacement']);
                                $type = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MVT_TRACA, $mvt['type']);

                                // création de l'emplacement s'il n'existe pas
                                if (!$location) {
                                    $location = new Emplacement();
                                    $location->setLabel($mvt['ref_emplacement']);
                                    $entityManager->persist($location);
                                }

                                $dateArray = explode('_', $mvt['date']);

                                $date = DateTime::createFromFormat(DateTime::ATOM, $dateArray[0], new DateTimeZone('Europe/Paris'));

                                $mouvementTraca = new MouvementTraca();
                                $mouvementTraca
                                    ->setColis($mvt['ref_article'])
                                    ->setEmplacement($location)
                                    ->setOperateur($nomadUser)
                                    ->setUniqueIdForMobile($mvt['date'])
                                    ->setDatetime($date)
                                    ->setFinished($mvt['finished'])
                                    ->setType($type);


                                // set mouvement de stock
                                if (isset($mvt['fromStock']) && $mvt['fromStock']) {
                                    if ($type->getNom() === MouvementTraca::TYPE_PRISE) {
                                        $articles = $this->articleRepository->findArticleByBarCodeAndLocation($mvt['ref_article'], $mvt['ref_emplacement']);
                                        /** @var Article|null $article */
                                        $article = count($articles) > 0 ? $articles[0] : null;
                                        if (!isset($article)) {
                                            $references = $this->referenceArticleRepository->findReferenceByBarCodeAndLocation($mvt['ref_article'], $mvt['ref_emplacement']);
                                            /** @var ReferenceArticle|null $article */
                                            $article = count($references) > 0 ? $references[0] : null;
                                        }

                                        if (isset($article)) {
                                            $quantiteMouvement = ($article instanceof Article)
                                                ? $article->getQuantite()
                                                : $article->getQuantiteStock(); // ($article instanceof ReferenceArticle)

                                            $newMouvement = $mouvementStockService->createMouvementStock($nomadUser, $location, $quantiteMouvement, $article, MouvementStock::TYPE_TRANSFERT);
                                            $mouvementTraca->setMouvementStock($newMouvement);
                                            $entityManager->persist($newMouvement);

                                            $configStatus = ($article instanceof Article)
                                                ? [Article::CATEGORIE, Article::STATUT_EN_TRANSIT]
                                                : [ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_INACTIF];

                                            $status = $this->statutRepository->findOneByCategorieNameAndStatutName($configStatus[0], $configStatus[1]);
                                            $article->setStatut($status);
                                        }
                                    } else { // MouvementTraca::TYPE_DEPOSE
                                        $mouvementTracaPrises = $this->mouvementTracaRepository->findBy(
                                            [
                                                'colis' => $mouvementTraca->getColis(),
                                                'type' => $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MVT_TRACA, MouvementTraca::TYPE_PRISE),
                                                'finished' => false
                                            ],
                                            ['datetime' => 'DESC']
                                        );
                                        $mouvementTracaPrise = count($mouvementTracaPrises) > 0 ? $mouvementTracaPrises[0] : null;
                                        if (isset($mouvementTracaPrise)) {
                                            $mouvementStockPrise = $mouvementTracaPrise->getMouvementStock();
                                            $article = $mouvementStockPrise->getArticle()
                                                ? $mouvementStockPrise->getArticle()
                                                : $mouvementStockPrise->getRefArticle();

                                            $collecteOrder = $mouvementStockPrise->getCollecteOrder();
                                            if (isset($collecteOrder) &&
                                                ($article instanceof ReferenceArticle) &&
                                                $article->getEmplacement() &&
                                                $article->getEmplacement()->getId() !== $location->getId()) {
                                                $invalidLocationTo = ($article->getEmplacement() ? $article->getEmplacement()->getLabel() : '');
                                                throw new Exception(MouvementTracaService::INVALID_LOCATION_TO);
                                            } else {
                                                $mouvementTraca->setMouvementStock($mouvementStockPrise);
                                                $mouvementStockService->finishMouvementStock($mouvementStockPrise, $date, $location);

                                                $configStatus = ($article instanceof Article)
                                                    ? [Article::CATEGORIE, Article::STATUT_ACTIF]
                                                    : [ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF];

                                                $status = $this->statutRepository->findOneByCategorieNameAndStatutName($configStatus[0], $configStatus[1]);
                                                $article
                                                    ->setStatut($status)
                                                    ->setEmplacement($location);

                                                // we update quantity if it's reference article from collecte
                                                if (isset($collecteOrder) && ($article instanceof ReferenceArticle)) {
                                                    $article->setQuantiteStock($article->getQuantiteStock() + $mouvementStockPrise->getQuantity());
                                                }
                                            }
                                        }
                                    }
                                }

                                if (!empty($mvt['comment'])) {
                                    $mouvementTraca->setCommentaire($mvt['comment']);
                                }

                                $signatureFile = $request->files->get("signature_$index");
                                if (!empty($signatureFile)) {
                                    $attachmentService->addAttachements([$signatureFile], null, null, $mouvementTraca);
                                }

                                $entityManager->persist($mouvementTraca);
                                $numberOfRowsInserted++;

                                // envoi de mail si c'est une dépose + le colis existe + l'emplacement est un point de livraison
                                if ($location) {
                                    $isDepose = ($mvt['type'] === MouvementTraca::TYPE_DEPOSE);
                                    $colis = $this->colisRepository->findOneByCode($mvt['ref_article']);

                                    if ($isDepose && $colis && $location->getIsDeliveryPoint()) {
                                        $fournisseur = $this->fournisseurRepository->findOneByColis($colis);
                                        $arrivage = $colis->getArrivage();
                                        $destinataire = $arrivage->getDestinataire();
                                        if ($this->mailerServerRepository->findOneMailerServer()) {
                                            $this->mailerService->sendMail(
                                                'FOLLOW GT // Dépose effectuée',
                                                $this->renderView(
                                                    'mails/mailDeposeTraca.html.twig',
                                                    [
                                                        'title' => 'Votre colis a été livré.',
                                                        'colis' => $colis->getCode(),
                                                        'emplacement' => $location,
                                                        'fournisseur' => $fournisseur ? $fournisseur->getNom() : '',
                                                        'date' => $date,
                                                        'operateur' => $nomadUser->getUsername(),
                                                        'pjs' => $arrivage->getAttachements()
                                                    ]
                                                ),
                                                $destinataire->getEmail()
                                            );
                                        } else {
                                            $this->logger->critical('Parametrage mail non defini.');
                                        }
                                    }
                                }

                                if ($type->getNom() === MouvementTraca::TYPE_DEPOSE) {
                                    $finishMouvementTraca[] = $mvt['ref_article'];
                                }
                            }
                        });
                    }
                    catch (Exception $e) {
                        if (!$entityManager->isOpen()) {
                            $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                        }

                        if ($e->getMessage() === MouvementTracaService::INVALID_LOCATION_TO) {
                            $successData['data']['errors'][$mvt['ref_article']] = ($mvt['ref_article'] . " doit être déposé sur l'emplacement \"$invalidLocationTo\"");
                        }
                        else {
                            throw $e;
                        }
                    }
                }

                // Pour tous les mouvement de prise envoyés, on les marques en fini si un mouvement de dépose a été donné
                foreach ($mouvementsNomade as $index => $mvt) {
                    $mouvementTracaPriseToFinish = $this->mouvementTracaRepository->findOneByUniqueIdForMobile($mvt['date']);
                    if (isset($mouvementTracaPriseToFinish) &&
                        ($mouvementTracaPriseToFinish->getType()->getNom() === MouvementTraca::TYPE_PRISE) &&
                        in_array($mouvementTracaPriseToFinish->getColis(), $finishMouvementTraca) &&
                        !$mouvementTracaPriseToFinish->isFinished()) {
                        $mouvementTracaPriseToFinish->setFinished((bool) $mvt['finished']);
                    }
                }
                $entityManager->flush();

                $s = $numberOfRowsInserted > 0 ? 's' : '';
                $successData['success'] = true;
                $successData['data']['status'] = ($numberOfRowsInserted === 0)
                    ? 'Aucun mouvement à synchroniser.'
                    : ($numberOfRowsInserted . ' mouvement' . $s . ' synchronisé' . $s);

            }
            else {
                $successData['success'] = false;
                $successData['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            $response->setContent(json_encode($successData));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/beginPrepa", name= "api-begin-prepa")
     * @Rest\View()
     * @param Request $request
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function beginPrepa(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {
                $id = $request->request->get('id');
                $preparation = $this->preparationRepository->find($id);

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
    }

    /**
     * @Rest\Post("/api/finishPrepa", name= "api-finish-prepa")
     * @Rest\View()
     * @param Request $request
     * @param LigneArticleRepository $ligneArticleRepository
     * @param PreparationsManagerService $preparationsManager
     * @param EmplacementRepository $emplacementRepository
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function finishPrepa(Request $request,
                                LigneArticlePreparationRepository $ligneArticleRepository,
                                PreparationsManagerService $preparationsManager,
                                EmplacementRepository $emplacementRepository,
                                EntityManagerInterface $entityManager)
    {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        if (!$request->isXmlHttpRequest()) {
            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

                $resData = ['success' => [], 'errors' => []];

                $preparations = json_decode($request->request->get('preparations'), true);

                // on termine les préparations
                // même comportement que LivraisonController.new()
                foreach ($preparations as $preparationArray) {
                    $preparation = $this->preparationRepository->find($preparationArray['id']);
                    if ($preparation) {
                        // if it has not been begun
                        try {
                            $dateEnd = DateTime::createFromFormat(DateTime::ATOM, $preparationArray['date_end']);
                            // flush auto at the end
                            $entityManager->transactional(function () use (
                                $preparationsManager,
                                $preparationArray,
                                $preparation,
                                $nomadUser,
                                $dateEnd,
                                $emplacementRepository,
                                $entityManager,
                                $ligneArticleRepository) {

                                $preparationsManager->setEntityManager($entityManager);
                                $mouvementsNomade = $preparationArray['mouvements'];
                                $totalQuantitiesWithRef = [];
                                $livraison = $preparationsManager->persistLivraison($dateEnd, $preparation);
                                foreach ($mouvementsNomade as $mouvementNomade) {
                                    if (!$mouvementNomade['is_ref'] && $mouvementNomade['selected_by_article']) {
                                        $article = $this->articleRepository->findOneByReference($mouvementNomade['reference']);
                                        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                                        if (!isset($totalQuantitiesWithRef[$refArticle->getReference()])) {
                                            $totalQuantitiesWithRef[$refArticle->getReference()] = 0;
                                        }
                                        $totalQuantitiesWithRef[$refArticle->getReference()] += $mouvementNomade['quantity'];
                                    }
                                    $preparationsManager->treatMouvementQuantities($mouvementNomade, $preparation);
                                    $preparationsManager->createMouvementsPrepaAndSplit($preparation, $nomadUser);
                                    // on crée les mouvements de livraison
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
                                    $refArticle = $this->referenceArticleRepository->findOneByReference($ref);
                                    $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $preparation->getDemande());
                                    $preparationsManager->deleteLigneRefOrNot($ligneArticle);
                                }

                                $emplacementPrepa = $emplacementRepository->findOneByLabel($preparationArray['emplacement']);
                                $preparationsManager->treatPreparation($preparation, $nomadUser, $emplacementPrepa);
                                if ($emplacementPrepa) {
                                    $preparationsManager->closePreparationMouvement($preparation, $dateEnd, $emplacementPrepa);
                                } else {
                                    throw new Exception(PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
                                }
                                $entityManager->flush();
                            });

                            $resData['success'][] = [
                                'numero_prepa' => $preparation->getNumero(),
                                'id_prepa' => $preparation->getId()
                            ];
                        } catch (Exception $exception) {
                            // we create a new entity manager because transactional() can call close() on it if transaction failed
                            if (!$entityManager->isOpen()) {
                                $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                                $preparationsManager->setEntityManager($entityManager);
                            }
                            $resData['errors'][] = [
                                'numero_prepa' => $preparation->getNumero(),
                                'id_prepa' => $preparation->getId(),
//TODO  CG msg prépa vide
                                'message' => (
                                    ($exception->getMessage() === PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION) ? "L'emplacement que vous avez sélectionné n'existe plus." :
                                    (($exception->getMessage() === PreparationsManagerService::ARTICLE_ALREADY_SELECTED) ? "L'article n'est pas sélectionnable" :
                                        'Une erreur est survenue')
                                )
                            ];
                        }
                    }
                }

                $preparationsManager->removeRefMouvements();
                $entityManager->flush();
            } else {
                $statusCode = Response::HTTP_UNAUTHORIZED;
                $resData['success'] = false;
                $resData['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

        }
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/beginLivraison", name= "api-begin-livraison")
     * @Rest\View()
     */
    public function beginLivraison(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

                $em = $this->getDoctrine()->getManager();

                $id = $request->request->get('id');
                $livraison = $this->livraisonRepository->find($id);

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
    }

    /**
     * @Rest\Post("/api/beginCollecte", name= "api-begin-collecte")
     * @Rest\View()
     */
    public function beginCollecte(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

                $em = $this->getDoctrine()->getManager();

                $id = $request->request->get('id');
                $ordreCollecte = $this->ordreCollecteRepository->find($id);

                if (
                    $ordreCollecte->getStatut()->getNom() == OrdreCollecte::STATUT_A_TRAITER &&
                    (empty($ordreCollecte->getUtilisateur()) || $ordreCollecte->getUtilisateur() === $nomadUser)
                ) {
                    // modif de la collecte
                    $ordreCollecte->setUtilisateur($nomadUser);

                    $em->flush();

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
    }

    /**
     * @Rest\Post("/api/validateManut", name= "api-validate-manut")
     * @Rest\View()
     */
    public function validateManut(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

                $em = $this->getDoctrine()->getManager();

                $id = $request->request->get('id');
                $manut = $this->manutentionRepository->find($id);

                if ($manut->getStatut()->getNom() == Livraison::STATUT_A_TRAITER) {
                    $commentaire = $request->request->get('commentaire');
                    if (!empty($commentaire)) {
                        $manut->setCommentaire($manut->getCommentaire() . "\n" . date('d/m/y H:i:s') . " - " . $nomadUser->getUsername() . " :\n" . $commentaire);
                    }
                    $manut->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MANUTENTION, Manutention::STATUT_TRAITE));
                    $em->flush();
                    if ($manut->getStatut()->getNom() == Manutention::STATUT_TRAITE) {
                        $this->mailerService->sendMail(
                            'FOLLOW GT // Manutention effectuée',
                            $this->renderView('mails/mailManutentionDone.html.twig', [
                                'manut' => $manut,
                                'title' => 'Votre demande de manutention a bien été effectuée.',
                            ]),
                            $manut->getDemandeur()->getEmail()
                        );
                    }
                    $this->successDataMsg['success'] = true;
                } else {
                    $this->successDataMsg['success'] = false;
                    $this->successDataMsg['msg'] = "Cette manutention a déjà été prise en charge par un opérateur.";
                }
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }
            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/finishLivraison", name= "api-finish-livraison")
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
        if (!$request->isXmlHttpRequest()) {
            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

                $livraisons = json_decode($request->request->get('livraisons'), true);
                $resData = ['success' => [], 'errors' => []];

                // on termine les livraisons
                // même comportement que LivraisonController.finish()
                foreach ($livraisons as $livraisonArray) {
                    $livraison = $this->livraisonRepository->find($livraisonArray['id']);

                    if ($livraison) {
                        $dateEnd = DateTime::createFromFormat(DateTime::ATOM, $livraisonArray['date_end']);
                        $emplacement = $this->emplacementRepository->findOneByLabel($livraisonArray['emplacement']);
                        try {
                            if ($emplacement) {
                                // flush auto at the end
                                $entityManager->transactional(function ()
                                use ($livraisonsManager, $entityManager, $nomadUser, $livraison, $dateEnd, $emplacement) {
                                    $livraisonsManager->setEntityManager($entityManager);
                                    $livraisonsManager->finishLivraison($nomadUser, $livraison, $dateEnd, $emplacement);
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
        }
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/finishCollecte", name="api-finish-collecte")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    public function finishCollecte(Request $request,
                                   EntityManagerInterface $entityManager)
    {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        if (!$request->isXmlHttpRequest()) {
            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {
                $resData = ['success' => [], 'errors' => []];

                $collectes = json_decode($request->request->get('collectes'), true);

                // on termine les collectes
                foreach ($collectes as $collecteArray) {
                    $collecte = $this->ordreCollecteRepository->find($collecteArray['id']);
                    try {
                        $entityManager->transactional(function ()
                                                      use ($entityManager, $collecteArray, $collecte, $nomadUser, &$resData) {
                            $this->ordreCollecteService->setEntityManager($entityManager);
                            $date = DateTime::createFromFormat(DateTime::ATOM, $collecteArray['date_end'], new DateTimeZone('Europe/Paris'));

                            $endLocation = $this->emplacementRepository->findOneByLabel($collecteArray['location_to']);
                            $newCollecte = $this->ordreCollecteService->finishCollecte($collecte, $nomadUser, $date, $endLocation, $collecteArray['mouvements'], true);
                            $entityManager->flush();

                            if (!empty($newCollecte)) {
                                $newCollecteId = $newCollecte->getId();
                                $newCollecteArray = $this->ordreCollecteRepository->getById($newCollecteId);

                                $articlesCollecte = $this->articleRepository->getByOrdreCollecteId($newCollecteId);
                                $refArticlesCollecte = $this->referenceArticleRepository->getByOrdreCollecteId($newCollecteId);
                                $articlesCollecte = array_merge($articlesCollecte, $refArticlesCollecte);
                            }

                            $resData['success'][] = [
                                'numero_collecte' => $collecte->getNumero(),
                                'id_collecte' => $collecte->getId(),
                                'newCollecte' => $newCollecteArray ?? null,
                                'articlesCollecte' => $articlesCollecte ?? []
                            ];
                        });
                    } catch (Exception $exception) {
                        // we create a new entity manager because transactional() can call close() on it if transaction failed
                        if (!$entityManager->isOpen()) {
                            $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                            $this->ordreCollecteService->setEntityManager($entityManager);
                        }

                        $user = $collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : '';

                        $resData['errors'][] = [
                            'numero_collecte' => $collecte->getNumero(),
                            'id_collecte' => $collecte->getId(),

                            'message' => (
                            ($exception->getMessage() === OrdreCollecteService::COLLECTE_ALREADY_BEGUN) ? "La collecte " . $collecte->getNumero() . " a déjà été effectuée (par " . $user . ")." :
                                ($exception->getMessage() === OrdreCollecteService::COLLECTE_MOUVEMENTS_EMPTY) ? "La collecte " . $collecte->getNumero() . " ne contient aucun article." :
                                    'Une erreur est survenue'
                            )
                        ];
                    }
                }
            } else {
                $statusCode = Response::HTTP_UNAUTHORIZED;
                $resData['success'] = false;
                $resData['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }
        }
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/addInventoryEntries", name= "api-add-inventory-entry")
     * @Rest\Get("/api/addInventoryEntries")
     * @Rest\View()
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function addInventoryEntries(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

                $em = $this->getDoctrine()->getManager();
                $numberOfRowsInserted = 0;

                $entries = json_decode($request->request->get('entries'), true);
                $newAnomalies = [];

                foreach ($entries as $entry) {
                    $newEntry = new InventoryEntry();

                    $mission = $this->inventoryMissionRepository->find($entry['id_mission']);
                    $location = $this->emplacementRepository->findOneByLabel($entry['location']);

                    if ($mission && $location) {
                        $newDate = new DateTime($entry['date']);
                        $newEntry
                            ->setMission($mission)
                            ->setDate($newDate)
                            ->setQuantity($entry['quantity'])
                            ->setOperator($nomadUser)
                            ->setLocation($location);

                        if ($entry['is_ref']) {
                            $refArticle = $this->referenceArticleRepository->findOneByReference($entry['reference']);
                            $newEntry->setRefArticle($refArticle);
                            if ($newEntry->getQuantity() !== $refArticle->getQuantiteStock()) {
                                $newEntry->setAnomaly(true);
                            } else {
                                $refArticle->setDateLastInventory($newDate);
                                $newEntry->setAnomaly(false);
                            }
                            $em->flush();
                        } else {
                            $article = $this->articleRepository->findOneByReference($entry['reference']);
                            $newEntry->setArticle($article);

                            if ($newEntry->getQuantity() !== $article->getQuantite()) {
                                $newEntry->setAnomaly(true);
                            } else {
                                $newEntry->setAnomaly(false);
                            }
                            $em->flush();
                        }
                        $em->persist($newEntry);
                        $em->flush();

                        if ($newEntry->getAnomaly()) {
                            $newAnomalies[] = $newEntry->getId();
                        }
                    }
                    $numberOfRowsInserted++;
                }
                $s = $numberOfRowsInserted > 1 ? 's' : '';
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0)
                    ? "Aucune saisie d'inventaire à synchroniser."
                    : ($numberOfRowsInserted . ' inventaire' . $s . ' synchronisé' . $s);
                $this->successDataMsg['data']['anomalies'] = array_merge(
                    $this->inventoryEntryRepository->getAnomaliesOnRef($newAnomalies),
                    $this->inventoryEntryRepository->getAnomaliesOnArt($newAnomalies)
                );
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }


    private function getDataArray($user)
    {
        $userTypes = [];
        foreach ($user->getTypes() as $type) {
            $userTypes[] = $type->getId();
        }

        $refAnomalies = $this->inventoryEntryRepository->getAnomaliesOnRef();
        $artAnomalies = $this->inventoryEntryRepository->getAnomaliesOnArt();

        /// livraisons
        $livraisons = $this->livraisonRepository->getByStatusLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user);
        $livraisonsIds = array_map(function ($livraisonArray) {
            return $livraisonArray['id'];
        }, $livraisons);
        $articlesLivraison = $this->articleRepository->getByLivraisonsIds($livraisonsIds);
        $refArticlesLivraison = $this->referenceArticleRepository->getByLivraisonsIds($livraisonsIds);

        /// preparations
        $preparations = $this->preparationRepository->getByStatusLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user, $userTypes);
        $preparationsIds = array_map(function ($preparationArray) {
            return $preparationArray['id'];
        }, $preparations);
        $articlesPrepa = $this->articleRepository->getByPreparationsIds($preparationsIds);
        $refArticlesPrepa = $this->referenceArticleRepository->getByPreparationsIds($preparationsIds);

        /// collecte
        $collectes = $this->ordreCollecteRepository->getByStatutLabelAndUser(OrdreCollecte::STATUT_A_TRAITER, $user);
        $collectesIds = array_map(function ($collecteArray) {
            return $collecteArray['id'];
        }, $collectes);
        $articlesCollecte = $this->articleRepository->getByOrdreCollectesIds($collectesIds);
        $refArticlesCollecte = $this->referenceArticleRepository->getByOrdreCollectesIds($collectesIds);

        // get article linked to a ReferenceArticle where type_quantite === 'article'
        $articlesPrepaByRefArticle = $this->articleRepository->getRefArticleByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user);

        $articlesInventory = $this->inventoryMissionRepository->getCurrentMissionArticlesNotTreated();
        $refArticlesInventory = $this->inventoryMissionRepository->getCurrentMissionRefNotTreated();

        $manutentions = $this->manutentionRepository->findByStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MANUTENTION, Manutention::STATUT_A_TRAITER));

        return [
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
            'preparations' => $preparations,
            'articlesPrepa' => array_merge($articlesPrepa, $refArticlesPrepa),
            'articlesPrepaByRefArticle' => $articlesPrepaByRefArticle,
            'livraisons' => $livraisons,
            'articlesLivraison' => array_merge($articlesLivraison, $refArticlesLivraison),
            'collectes' => $collectes,
            'articlesCollecte' => array_merge($articlesCollecte, $refArticlesCollecte),
            'inventoryMission' => array_merge($articlesInventory, $refArticlesInventory),
            'manutentions' => $manutentions,
            'anomalies' => array_merge($refAnomalies, $artAnomalies),

            'trackingTaking' => $this->mouvementTracaRepository->getTakingByOperatorAndNotDeposed($user, MouvementTracaRepository::MOUVEMENT_TRACA_DEFAULT),
            'stockTaking' => $this->mouvementTracaRepository->getTakingByOperatorAndNotDeposed($user, MouvementTracaRepository::MOUVEMENT_TRACA_STOCK)
        ];
    }

    /**
     * @Rest\Post("/api/getData", name= "api-get-data")
     * @param Request $request
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function getData(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {
                $httpCode = Response::HTTP_OK;
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data'] = $this->getDataArray($nomadUser);
            } else {
                $httpCode = Response::HTTP_UNAUTHORIZED;
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg, $httpCode);
        }
    }

    private function apiKeyGenerator()
    {
        $key = md5(microtime() . rand());
        return $key;
    }

    /**
     * @Rest\Get("/api/nomade-versions")
     */
    public function getAvailableVersionsAction()
    {
        return new JsonResponse($this->getParameter('nomade_versions') ?? '*');
    }

    /**
     * @Rest\Post("/api/treatAnomalies", name= "api-treat-anomalies-inv")
     * @Rest\Get("/api/treatAnomalies")
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function treatAnomalies(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            $apiKey = $request->request->get('apiKey');
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

                $numberOfRowsInserted = 0;

                $anomalies = json_decode($request->request->get('anomalies'), true);
                foreach ($anomalies as $anomaly) {
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

                $s = $numberOfRowsInserted > 1 ? 's' : '';
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0) ?
                    "Aucune anomalie d'inventaire à synchroniser." : $numberOfRowsInserted . ' anomalie' . $s . ' d\'inventaire synchronisée' . $s;
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/emplacement", name= "api-new-emp")
     */
    public function addEmplacement(Request $request)
    {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        if (!$request->isXmlHttpRequest()) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($request->request->get('apiKey'))) {
                if (!$this->emplacementRepository->findOneByLabel($request->request->get('label'))) {
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
    }

    /**
     * @Rest\Get("/api/articles", name= "api-get-articles")
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getArticles(Request $request): Response
    {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        if (!$request->isXmlHttpRequest()) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($request->query->get('apiKey'))) {
                $barCode = $request->query->get('barCode');
                $location = $request->query->get('location');

                if (!empty($barCode) && !empty($location)) {
                    $statusCode = Response::HTTP_OK;
                    $resData['success'] = true;
                    $resData['articles'] = array_merge(
                        $this->referenceArticleRepository->getReferenceByBarCodeAndLocation($barCode, $location),
                        $this->articleRepository->getArticleByBarCodeAndLocation($barCode, $location)
                    );
                } else {
                    $statusCode = Response::HTTP_BAD_REQUEST;
                    $resData['success'] = false;
                    $resData['articles'] = [];
                }
            } else {
                $statusCode = Response::HTTP_UNAUTHORIZED;
                $resData['success'] = false;
                $resData['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }
        }
        return new JsonResponse($resData, $statusCode);
    }

}
