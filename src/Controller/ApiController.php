<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Demande;
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
use App\Repository\DemandeRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\InventoryMissionRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\LivraisonRepository;
use App\Repository\MailerServerRepository;
use App\Repository\ManutentionRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\PreparationRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Service\ArticleDataService;
use App\Service\InventoryService;
use App\Service\MailerService;
use App\Service\OrdreCollecteService;
use App\Service\UserService;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use DateTime;

/**
 * Class ApiController
 * @package App\Controller
 */
class ApiController extends FOSRestController implements ClassResourceInterface
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
     * @var PieceJointeRepository
     */
    private $pieceJointeRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var MouvementStockRepository
     */
    private $mouvementRepository;

    /**
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

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
     * @param LigneArticleRepository $ligneArticleRepository
     * @param MouvementStockRepository $mouvementRepository
     * @param LivraisonRepository $livraisonRepository
     * @param ArticleDataService $articleDataService
     * @param StatutRepository $statutRepository
     * @param PreparationRepository $preparationRepository
     * @param PieceJointeRepository $pieceJointeRepository
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
    public function __construct(InventoryEntryRepository $inventoryEntryRepository, ManutentionRepository $manutentionRepository, OrdreCollecteService $ordreCollecteService, OrdreCollecteRepository $ordreCollecteRepository, InventoryService $inventoryService, UserService $userService, InventoryMissionRepository $inventoryMissionRepository, FournisseurRepository $fournisseurRepository, LigneArticleRepository $ligneArticleRepository, MouvementStockRepository $mouvementRepository, LivraisonRepository $livraisonRepository, ArticleDataService $articleDataService, StatutRepository $statutRepository, PreparationRepository $preparationRepository, PieceJointeRepository $pieceJointeRepository, LoggerInterface $logger, MailerServerRepository $mailerServerRepository, MailerService $mailerService, ColisRepository $colisRepository, MouvementTracaRepository $mouvementTracaRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository)
    {
        $this->manutentionRepository = $manutentionRepository;
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->mailerServerRepository = $mailerServerRepository;
        $this->mailerService = $mailerService;
        $this->colisRepository = $colisRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->successDataMsg = ['success' => false, 'data' => [], 'msg' => ''];
        $this->logger = $logger;
        $this->preparationRepository = $preparationRepository;
        $this->statutRepository = $statutRepository;
        $this->articleDataService = $articleDataService;
        $this->livraisonRepository = $livraisonRepository;
        $this->mouvementRepository = $mouvementRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->userService = $userService;
        $this->inventoryService = $inventoryService;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->ordreCollecteService = $ordreCollecteService;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
    }

    /**
     * @Rest\Post("/api/connect", name= "api-connect")
     * @Rest\Get("/api/connect")
     * @Rest\View()
     */
    public function connection(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = new Response();

            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            $user = $this->utilisateurRepository->findOneBy(['username' => $data['login']]);

            if ($user !== null) {
                if ($this->passwordEncoder->isPasswordValid($user, $data['password'])) {
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
     * @Rest\Post("/api/ping", name= "api-ping")
     * @Rest\Get("/api/ping")
     * @Rest\View()
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
     * @Rest\Post("/api/addMouvementTraca", name="api-add-mouvement-traca")
     * @Rest\Get("/api/addMouvementTraca")
     * @Rest\View()
     */
    public function addMouvementTraca(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();
                $numberOfRowsInserted = 0;
                foreach ($data['mouvements'] as $mvt) {
                    if (!$this->mouvementTracaRepository->getOneByDate($mvt['date'])) {
                        $refEmplacement = $mvt['ref_emplacement'];
                        $refArticle = $mvt['ref_article'];
                        $type = $mvt['type'];

                        $toInsert = new MouvementTraca();
                        $toInsert
                            ->setRefArticle($refArticle)
                            ->setRefEmplacement($refEmplacement)
                            ->setOperateur($this->utilisateurRepository->findOneByApiKey($data['apiKey'])->getUsername())
                            ->setDate($mvt['date'])
                            ->setType($type);
                        $em->persist($toInsert);
                        $numberOfRowsInserted++;

                        $emplacement = $this->emplacementRepository->findOneByLabel($refEmplacement);

                        if ($emplacement) {

                            $isDepose = $type === MouvementTraca::TYPE_DEPOSE;
                            $colis = $this->colisRepository->findOneByCode($mvt['ref_article']);

                            if ($isDepose && $colis && $emplacement->getIsDeliveryPoint()) {
                                $fournisseur = $this->fournisseurRepository->findOneByColis($colis);
                                $arrivage = $colis->getArrivage();
                                $destinataire = $arrivage->getDestinataire();
                                if ($this->mailerServerRepository->findOneMailerServer()) {
                                    $dateArray = explode('_', $toInsert->getDate());
                                    $date = new DateTime($dateArray[0]);
                                    $this->mailerService->sendMail(
                                        'FOLLOW GT // Dépose effectuée',
                                        $this->renderView(
                                            'mails/mailDeposeTraca.html.twig',
                                            [
                                                'title' => 'Votre colis a été livré.',
                                                'colis' => $colis->getCode(),
                                                'emplacement' => $emplacement,
                                                'fournisseur' => $fournisseur ? $fournisseur->getNom() : '',
                                                'date' => $date,
                                                'operateur' => $toInsert->getOperateur(),
                                                'pjs' => $arrivage->getAttachements()
                                            ]
                                        ),
                                        $destinataire->getEmail()
                                    );
                                } else {
                                    $this->logger->critical('Parametrage mail non defini.');
                                }
                            }
                        } else {
                            $emplacement = new Emplacement();
                            $emplacement->setLabel($refEmplacement);
                            $em->persist($emplacement);
                            $em->flush();
                        }
                    }
                }
                $em->flush();

                $s = $numberOfRowsInserted > 0 ? 's' : '';
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0) ?
                    'Aucun mouvement à synchroniser.' : $numberOfRowsInserted . ' mouvement' . $s . ' synchronisé' . $s;

            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/setmouvement", name= "api-set-mouvement")
     * @Rest\View()
     */
    public function setMouvement(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
                $mouvementsR = $data['mouvement'];
                foreach ($mouvementsR as $mouvementR) {
                    $mouvement = new MouvementStock;
                    $mouvement
                        ->setType($mouvementR['type'])
                        ->setDate(DateTime::createFromFormat('j-M-Y', $mouvementR['date']))
                        ->setEmplacementFrom($this->emplacemnt->$mouvementR[''])
                        ->setUser($mouvementR['']);
                }
                $this->successDataMsg['success'] = true;
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/beginPrepa", name= "api-begin-prepa")
     * @Rest\View()
     */
    public function beginPrepa(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();

                $preparation = $this->preparationRepository->find($data['id']);

                if ($preparation->getStatut()->getNom() == Preparation::STATUT_A_TRAITER || $preparation->getUtilisateur() === $nomadUser) {

                    $demandes = $preparation->getDemandes();
                    $demande = $demandes[0];

                    // modification des articles de la demande
                    $articles = $demande->getArticles();
                    foreach ($articles as $article) {
                        $article->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
                        // scission des articles dont la quantité prélevée n'est pas totale
                        if ($article->getQuantite() !== $article->getQuantiteAPrelever()) {
                            $newArticle = [
                                'articleFournisseur' => $article->getArticleFournisseur()->getId(),
                                'libelle' => $article->getLabel(),
                                'prix' => $article->getPrixUnitaire(),
                                'conform' => !$article->getConform(),
                                'commentaire' => $article->getcommentaire(),
                                'quantite' => $article->getQuantite() - $article->getQuantiteAPrelever(),
                                'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
                                'statut' => Article::STATUT_ACTIF,
                                'refArticle' => isset($data['refArticle']) ? $data['refArticle'] : $article->getArticleFournisseur()->getReferenceArticle()->getId()
                            ];

                            foreach ($article->getValeurChampsLibres() as $valeurChampLibre) {
                                $newArticle[$valeurChampLibre->getChampLibre()->getId()] = $valeurChampLibre->getValeur();
                            }
                            $this->articleDataService->newArticle($newArticle);

                            $article->setQuantite($article->getQuantiteAPrelever(), 0);
                        }

                        // création des mouvements de préparation pour les articles
                        $mouvement = new MouvementStock();
                        $mouvement
                            ->setUser($nomadUser)
                            ->setArticle($article)
                            ->setQuantity($article->getQuantiteAPrelever())
                            ->setEmplacementFrom($article->getEmplacement())
                            ->setType(MouvementStock::TYPE_TRANSFERT)
                            ->setPreparationOrder($preparation)
                            ->setExpectedDate($preparation->getDate());
                        $em->persist($mouvement);
                        $em->flush();
                    }

                    // création des mouvements de préparation pour les articles de référence
                    foreach ($demande->getLigneArticle() as $ligneArticle) {
                        $articleRef = $ligneArticle->getReference();

                        $mouvement = new MouvementStock();
                        $mouvement
                            ->setUser($nomadUser)
                            ->setRefArticle($articleRef)
                            ->setQuantity($ligneArticle->getQuantite())
                            ->setEmplacementFrom($articleRef->getEmplacement())
                            ->setType(MouvementStock::TYPE_TRANSFERT)
                            ->setPreparationOrder($preparation)
                            ->setExpectedDate($preparation->getDate());
                        $em->persist($mouvement);
                        $em->flush();
                    }

                    // modif du statut de la préparation
                    $statutEDP = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
                    $preparation
                        ->setStatut($statutEDP)
                        ->setUtilisateur($nomadUser);
                    $em->flush();

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
     */
    public function finishPrepa(Request $request,
                                DemandeRepository $demandeRepository)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $entityManager = $this->getDoctrine()->getManager();

                $preparations = $data['preparations'];

                // on termine les préparations
                // même comportement que LivraisonController.new()
                foreach ($preparations as $preparationArray) {
                    $preparation = $this->preparationRepository->find($preparationArray['id']);
//                    $preparation->setCommentaire($preparationArray['comment']);

                    if ($preparation) {
                        $demandes = $preparation->getDemandes();
                        $demande = $demandes[0];

                        $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_A_TRAITER);
                        $livraison = new Livraison();

                        $date = DateTime::createFromFormat(DateTime::ATOM, $preparationArray['date_end']);
                        $livraison
                            ->setDate($date)
                            ->setNumero('L-' . $date->format('YmdHis'))
                            ->setStatut($statut);
                        $entityManager->persist($livraison);

                        $preparation
                            ->addLivraison($livraison)
                            ->setUtilisateur($nomadUser)
                            ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::PREPARATION, Preparation::STATUT_PREPARE));

                        $demande
                            ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_PREPARE))
                            ->setLivraison($livraison);

                        // on termine les mouvements de préparation
                        $mouvements = $this->mouvementRepository->findByPreparation($preparation);
                        $emplacementPrepa = $this->emplacementRepository->findOneByLabel($preparationArray['emplacement']);
                        foreach ($mouvements as $mouvement) {
                            if ($emplacementPrepa) {
                                $mouvement
                                    ->setDate($date)
                                    ->setEmplacementTo($emplacementPrepa);
                            } else {
                                $this->successDataMsg['success'] = false;
                                $this->successDataMsg['msg'] = "L'emplacement que vous avez sélectionné n'existe plus.";
                                return new JsonResponse($this->successDataMsg);
                            }
                        }

                        $entityManager->flush();
                    }
                }

                $mouvementsNomade = $data['mouvements'];
				$listMvtToRemove = [];

                // on crée les mouvements de livraison
                foreach ($mouvementsNomade as $mouvementNomade) {
                    $preparation = $this->preparationRepository->find($mouvementNomade['id_prepa']);
                    $livraison = $this->livraisonRepository->findOneByPreparationId($preparation->getId());
                    $emplacement = $this->emplacementRepository->findOneByLabel($mouvementNomade['location']);
                    $mouvement = new MouvementStock();
                    $mouvement
                        ->setUser($nomadUser)
                        ->setQuantity($mouvementNomade['quantity'])
                        ->setEmplacementFrom($emplacement)
                        ->setType(MouvementStock::TYPE_SORTIE)
                        ->setLivraisonOrder($livraison)
                        ->setExpectedDate($livraison->getDate());
                    $entityManager->persist($mouvement);

                    if ($mouvementNomade['is_ref']) {
                        $refArticle = $this->referenceArticleRepository->findOneByReference($mouvementNomade['reference']);
                        if ($refArticle) {
                            $mouvement->setRefArticle($refArticle);
                            $mouvement->setQuantity($this->mouvementRepository->findByRefAndPrepa($refArticle->getId(), $preparation->getId())->getQuantity());
                            $ligneArticle = $this->ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $livraison->getPreparation()->getDemandes()[0]);
                            $ligneArticle->setQuantite($mouvement->getQuantity());
                        }
                    } else {
                        $article = $this->articleRepository->findOneByReference($mouvementNomade['reference']);
                        if ($article) {
                            $isSelectedByArticle = (
                                isset($mouvementNomade['selected_by_article']) &&
                                $mouvementNomade['selected_by_article']
                            );

                            // si c'est un article sélectionné par l'utilisateur :
                            // on prend la quantité donnée dans le mouvement
                            // sinon on prend la quantité spécifiée dans le mouvement de transfert (créé dans beginPrepa)
                            $mouvementQuantity = (
                                $isSelectedByArticle
                                    ? $mouvementNomade['quantity']
                                    : $this->mouvementRepository->findByRefAndPrepa($article->getId(), $preparation->getId())->getQuantity()
                            );

                            $mouvement->setQuantity($mouvementQuantity);
                            $article->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT));
                            $mouvement->setArticle($article);
                            $article->setQuantiteAPrelever($mouvement->getQuantity());

                            if ($article->getQuantite() !== $article->getQuantiteAPrelever()) {
                                $newArticle = [
                                    'articleFournisseur' => $article->getArticleFournisseur()->getId(),
                                    'libelle' => $article->getLabel(),
                                    'conform' => !$article->getConform(),
                                    'commentaire' => $article->getcommentaire(),
                                    'quantite' => $article->getQuantite() - $article->getQuantiteAPrelever(),
                                    'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
                                    'statut' => Article::STATUT_ACTIF,
                                    'prix' => $article->getPrixUnitaire(),
                                    'refArticle' => $article->getArticleFournisseur()->getReferenceArticle()->getId()
                                ];

                                foreach ($article->getValeurChampsLibres() as $valeurChampLibre) {
                                    $newArticle[$valeurChampLibre->getChampLibre()->getId()] = $valeurChampLibre->getValeur();
                                }
                                $this->articleDataService->newArticle($newArticle);

                                $article->setQuantite($article->getQuantiteAPrelever());
                            }

                            if ($isSelectedByArticle) {
                                if ($article->getDemande()) {
                                    throw new BadRequestHttpException('article-already-selected');
                                } else {
									// on crée le lien entre l'article et la demande
                                    $demande = $demandeRepository->findOneByLivraison($livraison);
                                    $article->setDemande($demande);

									// et si ça n'a pas déjà été fait, on supprime le lien entre la réf article et la demande
                                    $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                                    $ligneArticle = $this->ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $demande);
									if (!empty($ligneArticle)) {
                                        $entityManager->remove($ligneArticle);
                                    }

									// on crée le mouvement de transfert de l'article
									$mouvementRef = $this->mouvementRepository->findByRefAndPrepa($refArticle, $preparation);
									$newMouvement = new MouvementStock();
									$newMouvement
										->setUser($nomadUser)
										->setArticle($article)
										->setQuantity($article->getQuantiteAPrelever())
										->setEmplacementFrom($article->getEmplacement())
										->setEmplacementTo($mouvementRef ? $mouvementRef->getEmplacementTo() : '')
										->setType(MouvementStock::TYPE_TRANSFERT)
										->setPreparationOrder($preparation)
										->setDate($mouvementRef ? $mouvementRef->getDate() : '')
										->setExpectedDate($preparation->getDate());
									$entityManager->persist($newMouvement);
									$entityManager->flush();
									if ($mouvementRef) {
									    $listMvtToRemove[$mouvementRef->getId()] = $mouvementRef;
                                    }
								}
                            }
                        }
                    }

                    $entityManager->flush();
                }

				// on supprime les mouvements de transfert créés pour les réf gérées à l'articles
				// (elles ont été remplacées plus haut par les mouvements de transfert des articles)
				foreach ($listMvtToRemove as $mvtToRemove){
					$entityManager->remove($mvtToRemove);
				}

                $entityManager->flush();
                $this->successDataMsg['success'] = true;

            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/beginLivraison", name= "api-begin-livraison")
     * @Rest\View()
     */
    public function beginLivraison(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();

                $livraison = $this->livraisonRepository->find($data['id']);

                if (
                    $livraison->getStatut()->getNom() == Livraison::STATUT_A_TRAITER &&
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
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();

                $ordreCollecte = $this->ordreCollecteRepository->find($data['id']);

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
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();

                $manut = $this->manutentionRepository->find($data['id']);

                if ($manut->getStatut()->getNom() == Livraison::STATUT_A_TRAITER) {
                    if ($data['commentaire'] !== "") {
                        $manut->setCommentaire($manut->getCommentaire() . "\n" . date('d/m/y H:i:s') . " - " . $nomadUser->getUsername() . " :\n" . $data['commentaire']);
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
     */
    public function finishLivraison(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $entityManager = $this->getDoctrine()->getManager();

                $livraisons = $data['livraisons'];

                // on termine les livraisons
                // même comportement que LivraisonController.finish()
                foreach ($livraisons as $livraisonArray) {
                    $livraison = $this->livraisonRepository->find($livraisonArray['id']);

                    if ($livraison) {
                        $date = DateTime::createFromFormat(DateTime::ATOM, $livraisonArray['date_end']);

                        $livraison
                            ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_LIVRE))
                            ->setUtilisateur($nomadUser)
                            ->setDateFin($date);

                        $demandes = $livraison->getDemande();
                        $demande = $demandes[0];

                        $statutLivre = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_LIVRE);
                        $demande->setStatut($statutLivre);

                        $this->mailerService->sendMail(
                            'FOLLOW GT // Livraison effectuée',
                            $this->renderView('mails/mailLivraisonDone.html.twig', [
                                'livraison' => $demande,
                                'title' => 'Votre demande a bien été livrée.',
                            ]),
                            $demande->getUtilisateur()->getEmail()
                        );

                        // quantités gérées à la référence
                        $ligneArticles = $demande->getLigneArticle();

                        foreach ($ligneArticles as $ligneArticle) {
                            $refArticle = $ligneArticle->getReference();
                            $refArticle->setQuantiteStock($refArticle->getQuantiteStock() - $ligneArticle->getQuantite());
                        }

                        // quantités gérées à l'article
                        $articles = $demande->getArticles();

                        foreach ($articles as $article) {
                            $article
                                ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_INACTIF))
                                ->setEmplacement($demande->getDestination());
                        }

                        // on termine les mouvements de livraison
                        $mouvements = $this->mouvementRepository->findByLivraison($livraison);
                        foreach ($mouvements as $mouvement) {
                            $emplacement = $this->emplacementRepository->findOneByLabel($livraisonArray['emplacement']);
                            if ($emplacement) {
                                $mouvement
                                    ->setDate($date)
                                    ->setEmplacementTo($emplacement);
                            } else {
                                $this->successDataMsg['success'] = false;
                                $this->successDataMsg['msg'] = "L'emplacement que vous avez sélectionné n'existe plus.";
                                return new JsonResponse($this->successDataMsg);
                            }
                        }

                        $entityManager->flush();
                    }
                }

                $this->successDataMsg['success'] = true;

            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/finishCollecte", name= "api-finish-collecte")
     * @Rest\View()
     */
    public function finishCollecte(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $collectes = $data['collectes'];

                // on termine les collectes
                foreach ($collectes as $collecteArray) {
                    $collecte = $this->ordreCollecteRepository->find($collecteArray['id']);

                    if ($collecte->getStatut() && $collecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER) {
                        $date = DateTime::createFromFormat(DateTime::ATOM, $collecteArray['date_end']);
                        $this->ordreCollecteService->finishCollecte($collecte, $nomadUser, $date);
                        $this->successDataMsg['success'] = true;
                    } else {
                        $user = $collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : '';
                        $this->successDataMsg['success'] = false;
                        $this->successDataMsg['msg'] = "La collecte " . $collecte->getNumero() . " a déjà été effectuée (par " . $user . ").";
                    }
                }
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/addInventoryEntries", name= "api-add-inventory-entry")
     * @Rest\Get("/api/addInventoryEntries")
     * @Rest\View()
     */
    public function addInventoryEntries(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();
                $numberOfRowsInserted = 0;

                foreach ($data['entries'] as $entry) {
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
                    }
                    $numberOfRowsInserted++;
                }
                $s = $numberOfRowsInserted > 1 ? 's' : '';
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0) ?
                    "Aucune saisie d'inventaire à synchroniser." : $numberOfRowsInserted . ' inventaire' . $s . ' synchronisé' . $s;
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

        $articles = $this->articleRepository->getIdRefLabelAndQuantity();
        $articlesRef = $this->referenceArticleRepository->getIdRefLabelAndQuantityByTypeQuantite(ReferenceArticle::TYPE_QUANTITE_REFERENCE);

        $articlesPrepa = $this->articleRepository->getByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user);
        $refArticlesPrepa = $this->referenceArticleRepository->getByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user);

        // get article linked to a ReferenceArticle where type_quantite === 'article'
        $articlesPrepaByRefArticle = $this->articleRepository->getRefArticleByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user);

        $articlesLivraison = $this->articleRepository->getByLivraisonStatutLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user);
        $refArticlesLivraison = $this->referenceArticleRepository->getByLivraisonStatutLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user);

        $articlesCollecte = $this->articleRepository->getByCollecteStatutLabelAndWithoutOtherUser(OrdreCollecte::STATUT_A_TRAITER, $user);
        $refArticlesCollecte = $this->referenceArticleRepository->getByCollecteStatutLabelAndWithoutOtherUser(OrdreCollecte::STATUT_A_TRAITER, $user);

        $articlesInventory = $this->inventoryMissionRepository->getCurrentMissionArticlesNotTreated();
        $refArticlesInventory = $this->inventoryMissionRepository->getCurrentMissionRefNotTreated();

        $manutentions = $this->manutentionRepository->findByStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MANUTENTION, Manutention::STATUT_A_TRAITER));

        $data = [
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
            'articles' => array_merge($articles, $articlesRef),
            'preparations' => $this->preparationRepository->getByStatusLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user, $userTypes),
            'articlesPrepa' => array_merge($articlesPrepa, $refArticlesPrepa),
            'articlesPrepaByRefArticle' => $articlesPrepaByRefArticle,
            'livraisons' => $this->livraisonRepository->getByStatusLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user),
            'articlesLivraison' => array_merge($articlesLivraison, $refArticlesLivraison),
            'collectes' => $this->ordreCollecteRepository->getByStatutLabelAndUser(OrdreCollecte::STATUT_A_TRAITER, $user),
            'articlesCollecte' => array_merge($articlesCollecte, $refArticlesCollecte),
            'inventoryMission' => array_merge($articlesInventory, $refArticlesInventory),
            'manutentions' => $manutentions,
            'anomalies' => array_merge($refAnomalies, $artAnomalies)
        ];

        return $data;
    }

    /**
     * @Rest\Post("/api/getManutentions", name= "api-get-manuts")
     * @param Request $request
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getManutentions(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
                return new JsonResponse([
                    'success' => true,
                    'manutentions' => $this->manutentionRepository->findByStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MANUTENTION, Manutention::STATUT_A_TRAITER))
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                ]);
            }
        } else {
            return new JsonResponse([
                'success' => false,
            ]);
        }
    }

    /**
     * @Rest\Post("/api/getManut", name= "api-get-manut")
     * @param Request $request
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getOneManutention(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
                return new JsonResponse([
                    'success' => true,
                    'manutention' => $this->manutentionRepository->findOneForAPI($data['id'])
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                ]);
            }
        }
    }

    /**
     * @Rest\Post("/api/getPreparations", name= "api-get-preps")
     * @param Request $request
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getPreparations(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
                $userTypes = [];
                foreach ($nomadUser->getTypes() as $type) {
                    $userTypes[] = $type->getId();
                }
                $articlesPrepa = $this->articleRepository->getByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $nomadUser);
                $refArticlesPrepa = $this->referenceArticleRepository->getByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $nomadUser);

                // get article linked to a ReferenceArticle where type_quantite === 'article'
                $articlesPrepaByRefArticle = $this->articleRepository->getRefArticleByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $nomadUser);
                return new JsonResponse([
                    'success' => true,
                    'preparations' => $this->preparationRepository->getByStatusLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $nomadUser, $userTypes),
                    'articlesPrepa' => array_merge($articlesPrepa, $refArticlesPrepa),
                    'articlesPrepaByRefArticle' => $articlesPrepaByRefArticle,
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                ]);
            }
        } else {
            return new JsonResponse([
                'success' => false,
            ]);
        }
    }

    /**
     * @Rest\Post("/api/getLivraisons", name= "api-get-livrs")
     * @param Request $request
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLivraisons(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
                $articlesLivraison = $this->articleRepository->getByLivraisonStatutLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $nomadUser);
                $refArticlesLivraison = $this->referenceArticleRepository->getByLivraisonStatutLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $nomadUser);
                return new JsonResponse([
                    'success' => true,
                    'livraisons' => $this->livraisonRepository->getByStatusLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $nomadUser),
                    'articlesLivraison' => array_merge($articlesLivraison, $refArticlesLivraison),
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                ]);
            }
        } else {
            return new JsonResponse([
                'success' => false,
            ]);
        }
    }

    /**
     * @Rest\Post("/api/getCollectes", name= "api-get-cols")
     * @param Request $request
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getCollectes(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $articlesCollecte = $this->articleRepository->getByCollecteStatutLabelAndWithoutOtherUser(OrdreCollecte::STATUT_A_TRAITER, $nomadUser);
                $refArticlesCollecte = $this->referenceArticleRepository->getByCollecteStatutLabelAndWithoutOtherUser(OrdreCollecte::STATUT_A_TRAITER, $nomadUser);
                return new JsonResponse([
                    'success' => true,
                    'collectes' => $this->ordreCollecteRepository->getByStatutLabelAndUser(OrdreCollecte::STATUT_A_TRAITER, $nomadUser),
                    'articlesCollecte' => array_merge($articlesCollecte, $refArticlesCollecte),
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                ]);
            }
        } else {
            return new JsonResponse([
                'success' => false,
            ]);
        }
    }

    /**
     * @Rest\Post("/api/getData", name= "api-get-data")
     */
    public function getData(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data'] = $this->getDataArray($nomadUser);

            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/getAnomalies", name="api-get-anomalies")
     */
    public function getAnomalies(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $anomaliesOnRef = $this->inventoryEntryRepository->getAnomaliesOnRef();
                $anomaliesOnArt = $this->inventoryEntryRepository->getAnomaliesOnArt();

                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data'] = array_merge($anomaliesOnRef, $anomaliesOnArt);

            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg);
        }
    }

    private function apiKeyGenerator()
    {
        $key = md5(microtime() . rand());
        return $key;
    }

    /**
     * @Rest\Post("/api/treatAnomalies", name= "api-treat-anomalies-inv")
     * @Rest\Get("/api/treatAnomalies")
     */
    public function treatAnomalies(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $numberOfRowsInserted = 0;

                foreach ($data['anomalies'] as $anomaly) {
                    $this->inventoryService->doTreatAnomaly($anomaly['id'], $anomaly['reference'], $anomaly['is_ref'], $anomaly['quantity'], $anomaly['comment'], $nomadUser);
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

}
