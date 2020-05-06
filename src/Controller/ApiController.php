<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Colis;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\InventoryEntry;
use App\Entity\InventoryMission;
use App\Entity\LigneArticlePreparation;
use App\Entity\Livraison;
use App\Entity\Manutention;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Repository\InventoryEntryRepository;
use App\Repository\InventoryMissionRepository;
use App\Repository\LivraisonRepository;
use App\Repository\MailerServerRepository;
use App\Repository\ManutentionRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\UtilisateurRepository;
use App\Service\AttachmentService;
use App\Service\InventoryService;
use App\Service\LivraisonsManagerService;
use App\Service\MailerService;
use App\Service\ManutentionService;
use App\Service\MouvementStockService;
use App\Service\MouvementTracaService;
use App\Service\PreparationsManagerService;
use App\Service\OrdreCollecteService;
use App\Service\UserService;
use DateTimeZone;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
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
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

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
     * @var LivraisonRepository
     */
    private $livraisonRepository;

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
     * @param InventoryService $inventoryService
     * @param UserService $userService
     * @param InventoryMissionRepository $inventoryMissionRepository
     * @param LivraisonRepository $livraisonRepository
     * @param LoggerInterface $logger
     * @param MailerServerRepository $mailerServerRepository
     * @param MailerService $mailerService
     * @param UtilisateurRepository $utilisateurRepository
     * @param UserPasswordEncoderInterface $passwordEncoder
     */
    public function __construct(InventoryEntryRepository $inventoryEntryRepository,
                                ManutentionRepository $manutentionRepository,
                                OrdreCollecteService $ordreCollecteService,
                                InventoryService $inventoryService,
                                UserService $userService,
                                InventoryMissionRepository $inventoryMissionRepository,
                                LivraisonRepository $livraisonRepository,
                                LoggerInterface $logger,
                                MailerServerRepository $mailerServerRepository,
                                MailerService $mailerService,
                                UtilisateurRepository $utilisateurRepository,
                                UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->manutentionRepository = $manutentionRepository;
        $this->mailerServerRepository = $mailerServerRepository;
        $this->mailerService = $mailerService;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->logger = $logger;
        $this->livraisonRepository = $livraisonRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->userService = $userService;
        $this->inventoryService = $inventoryService;
        $this->ordreCollecteService = $ordreCollecteService;
        $this->inventoryEntryRepository = $inventoryEntryRepository;

        // TODO supprimer et faire localement dans les méthodes
        $this->successDataMsg = ['success' => false, 'data' => [], 'msg' => ''];
    }

    /**
     * @Rest\Post("/api/connect", name="api-connect", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param UserService $userService
     * @return Response
     */
    public function connection(Request $request,
                               UserService $userService)
    {
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

                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data'] = [
                    'apiKey' => $apiKey,
                    'rights' => $this->getMenuRights($user, $userService)
                ];
            }
        }

        $response->setContent(json_encode($this->successDataMsg));
        return $response;
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
     * @param MouvementTracaService $mouvementTracaService
     * @param AttachmentService $attachmentService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function postMouvementsTraca(Request $request,
                                        MouvementStockService $mouvementStockService,
                                        MouvementTracaService $mouvementTracaService,
                                        AttachmentService $attachmentService,
                                        EntityManagerInterface $entityManager)
    {
        $successData = [];
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
                                                  use (
                                                      $mouvementStockService,
                                                      &$numberOfRowsInserted,
                                                      $mvt,
                                                      $nomadUser,
                                                      $request,
                                                      $attachmentService,
                                                      $index,
                                                      &$invalidLocationTo,
                                                      &$finishMouvementTraca,
                                                      $mouvementTracaService) {

                        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                        $articleRepository = $entityManager->getRepository(Article::class);
                        $statutRepository = $entityManager->getRepository(Statut::class);
                        $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
                        $colisRepository = $entityManager->getRepository(Colis::class);

                        $mouvementTraca1 = $mouvementTracaRepository->findOneByUniqueIdForMobile($mvt['date']);
                        if (!isset($mouvementTraca1)) {
                            $options = [
                                'commentaire' => null,
                                'mouvementStock' => null,
                                'fileBag' => null,
                                'from' => null,
                                'uniqueIdForMobile' => $mvt['date']
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
                                if ($type->getNom() === MouvementTraca::TYPE_PRISE) {
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

                                        $newMouvement = $mouvementStockService->createMouvementStock($nomadUser, $location, $quantiteMouvement, $article, MouvementStock::TYPE_TRANSFERT);
                                        $options['mouvementStock'] = $newMouvement;
                                        $entityManager->persist($newMouvement);

                                        $configStatus = ($article instanceof Article)
                                            ? [Article::CATEGORIE, Article::STATUT_EN_TRANSIT]
                                            : [ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_INACTIF];

                                        $status = $statutRepository->findOneByCategorieNameAndStatutCode($configStatus[0], $configStatus[1]);
                                        $article->setStatut($status);
                                    }
                                }
                                else { // MouvementTraca::TYPE_DEPOSE
                                    $mouvementTracaPrises = $mouvementTracaRepository->findBy(
                                        [
                                            'colis' => $mvt['ref_article'],
                                            'type' => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, MouvementTraca::TYPE_PRISE),
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
                                        if (isset($collecteOrder)
                                            && ($article instanceof ReferenceArticle)
                                            && $article->getEmplacement()
                                            && ($article->getEmplacement()->getId() !== $location->getId())) {
                                            $invalidLocationTo = ($article->getEmplacement() ? $article->getEmplacement()->getLabel() : '');
                                            throw new Exception(MouvementTracaService::INVALID_LOCATION_TO);
                                        }
                                        else {
                                            $options['mouvementStock'] = $mouvementStockPrise;
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

                            if (!empty($mvt['comment'])) {
                                $options['commentaire'] = $mvt['comment'];
                            }

                            $signatureFile = $request->files->get("signature_$index");
                            if (!empty($signatureFile)) {
                                $options['fileBag'] = [$signatureFile];
                            }

                            $createdMvt = $mouvementTracaService->createMouvementTraca(
                                $mvt['ref_article'],
                                $location,
                                $nomadUser,
                                $date,
                                true,
                                $mvt['finished'],
                                $type,
                                $options
                            );
                            foreach ($createdMvt->getAttachements() as $attachement) {
                                $entityManager->persist($attachement);
                            }
                            $entityManager->persist($createdMvt);
                            $numberOfRowsInserted++;

                            // envoi de mail si c'est une dépose + le colis existe + l'emplacement est un point de livraison
                            if ($location) {
                                $isDepose = ($mvt['type'] === MouvementTraca::TYPE_DEPOSE);
                                $colis = $colisRepository->findOneBy(['code' => $mvt['ref_article']]);

                                if ($isDepose && $colis && $location->getIsDeliveryPoint()) {
                                    $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
                                    $fournisseur = $fournisseurRepository->findOneByColis($colis);
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

            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);

            // Pour tous les mouvement de prise envoyés, on les marques en fini si un mouvement de dépose a été donné
            foreach ($mouvementsNomade as $index => $mvt) {
                /** @var MouvementTraca $mouvementTracaPriseToFinish */
                $mouvementTracaPriseToFinish = $mouvementTracaRepository->findOneByUniqueIdForMobile($mvt['date']);
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

    /**
     * @Rest\Post("/api/beginPrepa", name="api-begin-prepa", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function beginPrepa(Request $request,
                               EntityManagerInterface $entityManager)
    {
        $apiKey = $request->request->get('apiKey');
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {
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
     * @param PreparationsManagerService $preparationsManager
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function finishPrepa(Request $request,
                                PreparationsManagerService $preparationsManager,
                                EntityManagerInterface $entityManager)
    {
        $resData = [];
        $insertedPrepasIds = [];
        $statusCode = Response::HTTP_OK;
        $apiKey = $request->request->get('apiKey');
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

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
                            $preparationArray,
                            $preparation,
                            $nomadUser,
                            $dateEnd,
                            $entityManager) {

                            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                            $articleRepository = $entityManager->getRepository(Article::class);
                            $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);
                            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

                            $preparationsManager->setEntityManager($entityManager);
                            $mouvementsNomade = $preparationArray['mouvements'];
                            $totalQuantitiesWithRef = [];
                            $livraison = $preparationsManager->createLivraison($dateEnd, $preparation);
                            $entityManager->persist($livraison);
                            $articlesToKeep = [];
                            foreach ($mouvementsNomade as $mouvementNomade) {
                                if (!$mouvementNomade['is_ref'] && $mouvementNomade['selected_by_article']) {
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
                            $insertedPreparation = $preparationsManager->treatPreparation($preparation, $nomadUser, $emplacementPrepa, $articlesToKeep);

                            if ($insertedPreparation) {
                                $insertedPrepasIds[] = $insertedPreparation->getId();
                            }

                            if ($emplacementPrepa) {
                                $preparationsManager->closePreparationMouvement($preparation, $dateEnd, $emplacementPrepa);
                            } else {
                                throw new Exception(PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
                            }

                            $entityManager->flush();

                            $preparationsManager->updateRefArticlesQuantities($preparation);
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

            if (!empty($insertedPrepasIds)) {
                $resData['data']['preparations'] = $preparationRepository->getAvailablePreparations($nomadUser, $insertedPrepasIds);
                $resData['data']['articlesPrepa'] = $this->getArticlesPrepaArrays($insertedPrepasIds, true);
                $resData['data']['articlesPrepaByRefArticle'] = $articleRepository->getArticlePrepaForPickingByUser($nomadUser, $insertedPrepasIds);
            }

            $preparationsManager->removeRefMouvements();
            $entityManager->flush();
        }
        else {
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
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function beginLivraison(Request $request)
    {
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

    /**
     * @Rest\Post("/api/beginCollecte", name="api-begin-collecte", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function beginCollecte(Request $request,
                                  EntityManagerInterface $entityManager) {
        $apiKey = $request->request->get('apiKey');
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

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
     * @Rest\Post("/api/validateManut", name="api-validate-manut", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @param ManutentionService $manutentionService
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function validateManut(Request $request,
                                  ManutentionService $manutentionService) {
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

                $statutRepository = $em->getRepository(Statut::class);
                $manut->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MANUTENTION, Manutention::STATUT_TRAITE));
                $manut->setDateEnd(new DateTime('now', new DateTimeZone('Europe/Paris')));

                $em->flush();
                if ($manut->getStatut()->getNom() == Manutention::STATUT_TRAITE) {
                    $manutentionService->sendTreatedEmail($manut);
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
                                    LivraisonsManagerService $livraisonsManager) {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        $apiKey = $request->request->get('apiKey');
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            $livraisons = json_decode($request->request->get('livraisons'), true);
            $resData = ['success' => [], 'errors' => []];

            // on termine les livraisons
            // même comportement que LivraisonController.finish()
            foreach ($livraisons as $livraisonArray) {
                $livraison = $this->livraisonRepository->find($livraisonArray['id']);

                if ($livraison) {
                    $dateEnd = DateTime::createFromFormat(DateTime::ATOM, $livraisonArray['date_end']);
                    $emplacement = $emplacementRepository->findOneByLabel($livraisonArray['emplacement']);
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
     */
    public function finishCollecte(Request $request,
                                   OrdreCollecteService $ordreCollecteService,
                                   EntityManagerInterface $entityManager)
    {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        $apiKey = $request->request->get('apiKey');
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {
            $resData = ['success' => [], 'errors' => [], 'data' => []];

            $collectes = json_decode($request->request->get('collectes'), true);

            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $refArticlesRepository = $entityManager->getRepository(ReferenceArticle::class);
            $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            // on termine les collectes
            foreach ($collectes as $collecteArray) {
                $collecte = $ordreCollecteRepository->find($collecteArray['id']);
                try {
                    $entityManager->transactional(function ()
                                                  use ($entityManager,
                                                       $collecteArray,
                                                       $collecte,
                                                       $nomadUser,
                                                       &$resData,
                                                       $mouvementTracaRepository,
                                                       $articleRepository,
                                                       $refArticlesRepository,
                                                       $ordreCollecteRepository,
                                                       $emplacementRepository,
                                                       $ordreCollecteService) {
                        $ordreCollecteService->setEntityManager($entityManager);
                        $date = DateTime::createFromFormat(DateTime::ATOM, $collecteArray['date_end'], new DateTimeZone('Europe/Paris'));

                        $endLocation = $emplacementRepository->findOneByLabel($collecteArray['location_to']);
                        $newCollecte = $ordreCollecteService->finishCollecte($collecte, $nomadUser, $date, $endLocation, $collecteArray['mouvements'], true);
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

                        $newTakings = $mouvementTracaRepository->getTakingByOperatorAndNotDeposed(
                            $nomadUser,
                            MouvementTracaRepository::MOUVEMENT_TRACA_STOCK,
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
                } catch (Exception $exception) {
                    // we create a new entity manager because transactional() can call close() on it if transaction failed
                    if (!$entityManager->isOpen()) {
                        $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                        $ordreCollecteService->setEntityManager($entityManager);

                        $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
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
        return new JsonResponse($resData, $statusCode);
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
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {
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
                    ? $referenceArticleRepository->findOneByReference($entry['reference'])
                    : $articleRepository->findOneByReference($entry['reference']);

                $criteriaInventoryEntry = ['mission' => $mission];

                if (isset($articleToInventory)) {
                    if ($articleToInventory instanceof ReferenceArticle) {
                        $criteriaInventoryEntry['refArticle'] = $articleToInventory;
                    }
                    else { // ($articleToInventory instanceof Article)
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
                    }
                    else {
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
     * @param $user
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return array
     * @throws NonUniqueResultException
     */
    private function getDataArray($user,
                                  UserService $userService,
                                  EntityManagerInterface $entityManager)
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
        $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);

        $rights = $this->getMenuRights($user, $userService);

        if ($rights['inventoryManager']) {
            $refAnomalies = $inventoryEntryRepository->getAnomaliesOnRef(true);
            $artAnomalies = $inventoryEntryRepository->getAnomaliesOnArt(true);
        }
        else {
            $refAnomalies = [];
            $artAnomalies = [];
        }

        if ($rights['stock']) {
            // livraisons
            $livraisons = $this->livraisonRepository->getByStatusLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user);

            $livraisonsIds = array_map(function ($livraisonArray) {
                return $livraisonArray['id'];
            }, $livraisons);

            $articlesLivraison = $articleRepository->getByLivraisonsIds($livraisonsIds);
            $refArticlesLivraison = $referenceArticleRepository->getByLivraisonsIds($livraisonsIds);

            /// preparations
            $preparations = $preparationRepository->getAvailablePreparations($user);

            /// collecte
            $collectes = $ordreCollecteRepository->getByStatutLabelAndUser(OrdreCollecte::STATUT_A_TRAITER, $user);
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
            $stockTaking = $mouvementTracaRepository->getTakingByOperatorAndNotDeposed($user, MouvementTracaRepository::MOUVEMENT_TRACA_STOCK);
        }
        else {
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
            $statutRepository = $entityManager->getRepository(Statut::class);
            $manutentionRepository = $entityManager->getRepository(Manutention::class);
            $manutentions = $manutentionRepository->findByStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MANUTENTION, Manutention::STATUT_A_TRAITER));
        }
        else {
            $manutentions = [];
        }

        if ($rights['tracking']) {
            $trackingTaking = $mouvementTracaRepository->getTakingByOperatorAndNotDeposed($user, MouvementTracaRepository::MOUVEMENT_TRACA_DEFAULT);
        }
        else {
            $trackingTaking = [];
        }

        return [
            'emplacements' => $emplacementRepository->getIdAndNom(),
            'preparations' => $preparations,
            'articlesPrepa' => $this->getArticlesPrepaArrays($preparations),
            'articlesPrepaByRefArticle' => $articlesPrepaByRefArticle,
            'livraisons' => $livraisons,
            'articlesLivraison' => array_merge($articlesLivraison, $refArticlesLivraison),
            'collectes' => $collectes,
            'articlesCollecte' => array_merge($articlesCollecte, $refArticlesCollecte),
            'manutentions' => $manutentions,
            'inventoryMission' => array_merge($articlesInventory, $refArticlesInventory),
            'anomalies' => array_merge($refAnomalies, $artAnomalies),
            'trackingTaking' => $trackingTaking,
            'stockTaking' => $stockTaking,
            'rights' => $rights
        ];
    }

    /**
     * @Rest\Post("/api/getData", name="api-get-data", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function getData(Request $request,
                            UserService $userService,
                            EntityManagerInterface $entityManager)
    {
        $apiKey = $request->request->get('apiKey');
        $dataResponse = [];
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($apiKey)) {
            $httpCode = Response::HTTP_OK;
            $dataResponse['success'] = true;
            $dataResponse['data'] = $this->getDataArray($nomadUser, $userService, $entityManager);
        }
        else {
            $httpCode = Response::HTTP_UNAUTHORIZED;
            $dataResponse['success'] = false;
            $dataResponse['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
        }

        return new JsonResponse($dataResponse, $httpCode);
    }

    private function apiKeyGenerator() {
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
     * @Rest\Get("/api/treatAnomalies")
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function treatAnomalies(Request $request)
    {
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
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($request->request->get('apiKey'))) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

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

        $resData = [];
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($request->query->get('apiKey'))) {
            $barCode = $request->query->get('barCode');
            $location = $request->query->get('location');

            if (!empty($barCode) && !empty($location)) {
                $statusCode = Response::HTTP_OK;
                $resData['success'] = true;
                $resData['articles'] = array_merge(
                    $referenceArticleRepository->getReferenceByBarCodeAndLocation($barCode, $location),
                    $articleRepository->getArticleByBarCodeAndLocation($barCode, $location)
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
        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Get("/api/tracking-drops", name="api-get-tracking-drops-on-location", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws DBALException
     */
    public function getTrackingDropsOnLocation(Request $request,
                                               EntityManagerInterface $entityManager): Response
    {
        $resData = [];
        if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($request->query->get('apiKey'))) {
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
            }
            else {
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

    private function getArticlesPrepaArrays(array $preparations, bool $isIdArray = false): array {
        $entityManager = $this->getDoctrine()->getManager();
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
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

    private function getMenuRights($user, UserService $userService) {
        return [
            'stock' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_STOCK, $user),
            'tracking' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_TRACA, $user),
            'demande' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_MANUT, $user),
            'inventoryManager' => $userService->hasRightFunction(Menu::STOCK, Action::INVENTORY_MANAGER, $user)
        ];
    }

}
