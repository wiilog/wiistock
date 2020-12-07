<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\ArticleFournisseur;
use App\Entity\FreeField;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\Article;
use App\Entity\MouvementStock;
use App\Entity\TrackingMovement;
use App\Entity\ReferenceArticle;
use App\Entity\CategorieCL;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\ReceptionRepository;
use App\Service\CSVExportService;
use App\Service\DemandeLivraisonService;
use App\Service\GlobalParamService;
use App\Service\MouvementStockService;
use App\Service\PDFGeneratorService;
use App\Service\ArticleDataService;
use App\Service\PreparationsManagerService;
use App\Service\RefArticleDataService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use App\Service\FreeFieldService;

/**
 * @Route("/article")
 */
class ArticleController extends AbstractController
{

    const MAX_CSV_FILE_LENGTH = 5000;

    private const ARTICLE_IS_USED_MESSAGES = [
        Article::USED_ASSOC_COLLECTE => "Cet article est lié à une ou plusieurs collectes.",
        Article::USED_ASSOC_LITIGE => "Cet article est lié à un ou plusieurs litiges.",
        Article::USED_ASSOC_INVENTORY => "Cet article est lié à une ou plusieurs missions d'inventaire.",
        Article::USED_ASSOC_STATUT_NOT_AVAILABLE => "Cet article n'est pas disponible.",
        Article::USED_ASSOC_PREPA_IN_PROGRESS => "Cet article est dans une préparation en cours de traitement.",
        Article::USED_ASSOC_TRANSFERT_REQUEST => "Cet article est dans une ou plusieurs demande(s) de transfert.",
        Article::USED_ASSOC_COLLECT_ORDER => "Cet article est dans un ou plusieurs ordre(s) de collecte.",
        Article::USED_ASSOC_INVENTORY_ENTRY => "Cet article est dans une ou plusieurs entrée(s) d'inventaire."
    ];

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

	/**
	 * @var ParametrageGlobalRepository
	 */
	private $paramGlobalRepository;

    /**
     * @var FreeFieldService
     */
    private $champLibreService;

    public function __construct(Twig_Environment $templating,
                                GlobalParamService $globalParamService,
                                ArticleDataService $articleDataService,
                                ReceptionRepository $receptionRepository,
                                UserService $userService,
                                ParametrageGlobalRepository $parametrageGlobalRepository,
                                FreeFieldService $champLibreService )
    {
        $this->paramGlobalRepository = $parametrageGlobalRepository;
        $this->globalParamService = $globalParamService;
        $this->receptionRepository = $receptionRepository;
        $this->articleDataService = $articleDataService;
        $this->userService = $userService;
        $this->templating = $templating;
        $this->champLibreService = $champLibreService;
    }

    /**
     * @Route("/", name="article_index", methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param ArticleDataService $articleDataService
     * @return Response
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager, ArticleDataService $articleDataService): Response {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
            return $this->redirectToRoute('access_denied');
        }

        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $filter = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, FiltreSup::PAGE_ARTICLE, $currentUser);

        return $this->render('article/index.html.twig', [
            "fields" => $articleDataService->getColumnVisibleConfig($entityManager, $currentUser),
            "searches" => $currentUser->getRechercheForArticle(),
            "activeOnly" => !empty($filter) && ($filter->getValue() === $articleDataService->getActiveArticleFilterValue())
        ]);
    }

    /**
     * @Route("/show-actif-inactif", name="article_actif_inactif", options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @param ArticleDataService $articleDataService
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function displayActifOrInactif(EntityManagerInterface $entityManager,
                                          ArticleDataService $articleDataService,
                                          Request $request) : Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)){

            /** @var Utilisateur $user */
            $user = $this->getUser();

            $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

            $filter = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, FiltreSup::PAGE_ARTICLE, $user);
            $activeOnly = $data['activeOnly'];

            if ($activeOnly) {
            	if (empty($filter)) {
					$filter = new FiltreSup();
					$filter
						->setUser($user)
						->setField(FiltreSup::FIELD_STATUT)
						->setPage(FiltreSup::PAGE_ARTICLE);
					$entityManager->persist($filter);
				}
                $filter
                    ->setValue($articleDataService->getActiveArticleFilterValue());
			} else {
            	if (!empty($filter)) {
            		$entityManager->remove($filter);
				}
			}

            $entityManager->flush();

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", name="article_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->articleDataService->getDataForDatatable($request->request, $this->getUser());
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-columns", name="article_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param ArticleDataService $articleDataService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiColumns(ArticleDataService $articleDataService,
                               EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
            return $this->redirectToRoute('access_denied');
        }

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        return new JsonResponse(
            $articleDataService->getColumnVisibleConfig($entityManager, $currentUser)
        );
    }

    /**
     * @Route("/voir", name="article_show", options={"expose"=true},  methods="GET|POST")
     *
     * @param Request $request
     * @param ArticleDataService $articleDataService
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function editApi(Request $request,
                            ArticleDataService $articleDataService,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);

            $id = is_array($data) ? $data['id'] : $data;
            $isADemand = is_array($data) ? ($data['isADemand'] ?? false) : false;
            $article = $articleRepository->find($id);
            if ($article) {
                $json = $articleDataService->getViewEditArticle($article, $isADemand);
            } else {
                $json = false;
            }

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/nouveau", name="article_new", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param MouvementStockService $mouvementStockService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        MouvementStockService $mouvementStockService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();
            $article = $this->articleDataService->newArticle($data, $entityManager);
            $entityManager->flush();

            $quantity = $article->getQuantite();
            if ($quantity > 0) {
                $stockMovement = $mouvementStockService->createMouvementStock(
                    $loggedUser,
                    null,
                    $quantity,
                    $article,
                    MouvementStock::TYPE_ENTREE
                );

                $mouvementStockService->finishMouvementStock(
                    $stockMovement,
                    new DateTime('now'),
                    $article->getEmplacement()
                );

                $entityManager->persist($stockMovement);
                $entityManager->flush();
            }

            return new JsonResponse(!empty($article));
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="article_edit", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($data['article']) {
                try {
                    $this->articleDataService->editArticle($data);
                    $response = ['success' => true];
                }
                /** @noinspection PhpRedundantCatchClauseInspection */
                catch(ArticleNotAvailableException $exception) {
                    $response = [
                        'success' => false,
                        'msg' => "Vous ne pouvez pas modifier un article qui n'est pas disponible."
                    ];
                }
                /** @noinspection PhpRedundantCatchClauseInspection */
                catch(RequestNeedToBeProcessedException $exception) {
                    $response = [
                        'success' => false,
                        'msg' => "Vous ne pouvez pas modifier un article qui est dans une demande de livraison."
                    ];
                }
            } else {
                $response = ['success' => false];
            }
            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="article_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param MouvementStockService $mouvementStockService
     * @param PreparationsManagerService $preparationsManagerService
     * @param DemandeLivraisonService $demandeLivraisonService
     * @param RefArticleDataService $refArticleDataService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           MouvementStockService $mouvementStockService,
                           PreparationsManagerService $preparationsManagerService,
                           DemandeLivraisonService $demandeLivraisonService,
                           RefArticleDataService $refArticleDataService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleRepository = $entityManager->getRepository(Article::class);

            /** @var Article $article */
            $article = $articleRepository->find($data['article']);
            $articleBarCode = $article->getBarCode();

            $trackingPack = $article->getTrackingPack();

            if ($article->getCollectes()->isEmpty()
                && $article->getOrdreCollecte()->isEmpty()
                && $article->getTransferRequests()->isEmpty()
                && $article->getInventoryMissions()->isEmpty()
                && $article->getInventoryEntries()) {

                if ($trackingPack) {
                    if (!$trackingPack->getDispatchPacks()->isEmpty()
                        || !$trackingPack->getLitiges()->isEmpty()
                        || $trackingPack->getArrivage()) {
                        $trackingPack->setArticle(null);
                    }
                }

                $receptionReferenceArticle = $article->getReceptionReferenceArticle();
                if (isset($receptionReferenceArticle)) {
                    $articleQuantity = $article->getQuantite();
                    $receivedQuantity = $receptionReferenceArticle->getQuantite();
                    $receptionReferenceArticle->setQuantite(max($receivedQuantity - $articleQuantity, 0));
                }

                $rows = $article->getId();

                // Delete mvt traca
                /** @var TrackingMovement $trackingMovement */
                foreach ($article->getTrackingMovements()->toArray() as $trackingMovement) {
                    $entityManager->remove($trackingMovement);
                }

                // Delete mvt stock
                /** @var MouvementStock $mouvementStock */
                foreach ($article->getMouvements()->toArray() as $mouvementStock) {
                    $mouvementStockService->manageMouvementStockPreRemove($mouvementStock, $entityManager);
                    $article->removeMouvement($mouvementStock);
                    $entityManager->remove($mouvementStock);
                }
                $entityManager->flush();

                // Delete prepa
                $preparation = $article->getPreparation();
                if ($preparation) {
                    $refToUpdate = $preparationsManagerService->managePreRemovePreparation($preparation, $entityManager);
                    $entityManager->flush();
                    $entityManager->remove($preparation);

                    // il faut que la preparation soit supprimée avant une maj des articles
                    $entityManager->flush();

                    foreach ($refToUpdate as $reference) {
                        $refArticleDataService->updateRefArticleQuantities($reference);
                    }

                    $entityManager->flush();
                }
                // Delete demande

                $demande = $article->getDemande();
                if ($demande) {
                    $demandeLivraisonService->managePreRemoveDeliveryRequest($demande, $entityManager);
                    $entityManager->remove($demande);
                    $entityManager->flush();
                }

                $entityManager->remove($article);
                $entityManager->flush();

                $response['delete'] = $rows;
                return new JsonResponse([
                    'delete' => $rows,
                    'success' => true,
                    'msg' => 'L\'article <strong>' . $articleBarCode . '</strong> a bien été supprimé.'
                ]);
            }
            else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'L\'article <strong>' . $articleBarCode . '</strong> est utilisé, vous ne pouvez pas le supprimer.'
                ]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="article_check_delete", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function checkArticleCanBeDeleted(Request $request,
                                             EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $articleId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
                return $this->redirectToRoute('access_denied');
            }

            $isFromReception = $request->query->getBoolean('fromReception');

            $articleRepository = $entityManager->getRepository(Article::class);

            /** @var Article $article */
            $article = $articleRepository->find($articleId);

            $articleAssociations = $article->getUsedAssociation();

            if ($articleAssociations !== null) {
                return new JsonResponse([
                    'delete' => false,
                    'html' => $this->renderView('article/modalDeleteArticleWrong.html.twig', [
                        'msg' => self::ARTICLE_IS_USED_MESSAGES[$articleAssociations]
                    ])
                ]);
            } else {
                $hasRightToDeleteOrders = $this->userService->hasRightFunction(Menu::ORDRE, Action::DELETE);
                $hasRightToDeleteRequests = $this->userService->hasRightFunction(Menu::DEM, Action::DELETE);
                $hasRightToDeleteTraca = $this->userService->hasRightFunction(Menu::TRACA, Action::DELETE);
                $hasRightToDeleteStock = $this->userService->hasRightFunction(Menu::STOCK, Action::DELETE);

                $articlesMvtTracaIsEmpty = $article->getTrackingMovements()->isEmpty();
                $articlesMvtStockIsEmpty = $article->getMouvements()->isEmpty();
                $articleRequest = $article->getDemande();
                $articlePrepa = $article->getPreparation();
                $isNotUsedInAssoc = $articlesMvtTracaIsEmpty && $articlesMvtStockIsEmpty && empty($articleRequest) && empty($articlePrepa);
                if (($hasRightToDeleteTraca || $articlesMvtTracaIsEmpty)
                    && ($hasRightToDeleteStock || $articlesMvtStockIsEmpty)
                    && ($hasRightToDeleteRequests || empty($articleRequest))
                    && ($hasRightToDeleteOrders || empty($articlePrepa))) {
                    return new JsonResponse([
                        'delete' => $isFromReception || $isNotUsedInAssoc,
                        'html' => $this->renderView('article/modalDeleteArticleRight.html.twig', [
                            'prepa' => $articlePrepa ? $articlePrepa->getNumero() : null,
                            'request' => $articleRequest ? $articleRequest->getNumero() : null,
                            'mvtStockIsEmpty' => $articlesMvtStockIsEmpty,
                            'mvtTracaIsEmpty' => $articlesMvtTracaIsEmpty,
                            'askQuestion' => $isFromReception
                        ])
                    ]);
                } else {
                    return new JsonResponse([
                        'delete' => false,
                        'html' => $this->renderView('article/modalDeleteArticleWrong.html.twig', [
                            'msg' => 'Vous ne disposez pas de tous les droits de suppression sur la traçabilité/demande/ordre/stock pour pouvoir supprimer l\'article.'
                        ])
                    ]);
                }
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/autocompleteArticleFournisseur", name="get_articleRef_fournisseur", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function getRefArticles(Request $request, EntityManagerInterface $entityManager)
    {
        $search = $request->query->get('term');

        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $articleFournisseur = $articleFournisseurRepository->findBySearch($search);

        return new JsonResponse(['results' => $articleFournisseur]);
    }

    /**
     * @Route("/autocomplete-art", name="get_articles", options={"expose"=true}, methods="GET|POST")
     *
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return JsonResponse
     */
    public function getArticles(EntityManagerInterface $entityManager, Request $request)
    {
        if ($request->isXmlHttpRequest()) {

            $search = $request->query->get('term');
            $referenceArticleReference = $request->query->get('referenceArticleReference');
            $activeOnly = $request->query->getBoolean('activeOnly');
            $activeReferenceOnly = $request->query->getBoolean('activeReferenceOnly');

            $articleRepository = $entityManager->getRepository(Article::class);
            $articles = $articleRepository->getIdAndRefBySearch($search, $activeOnly, 'barCode', $referenceArticleReference, $activeReferenceOnly);

            return new JsonResponse(['results' => $articles]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/get-article-collecte", name="get_collecte_article_by_refArticle", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getCollecteArticleByRefArticle(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $refArticle = null;
            if ($data['referenceArticle']) {
                $refArticle = $referenceArticleRepository->find($data['referenceArticle']);
            }
            if ($refArticle) {
                $json = $this->articleDataService->getCollecteArticleOrNoByRefArticle($refArticle);
            } else {
                $json = false; //TODO gérer erreur retour
            }

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/get-article-demande", name="demande_article_by_refArticle", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getLivraisonArticlesByRefArticle(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $refArticle = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $referenceArticleRepository->find($refArticle);

            if ($refArticle) {
                /** @var Utilisateur $currentUser */
                $currentUser = $this->getUser();
                $json = $this->articleDataService->getLivraisonArticlesByRefArticle($refArticle, $currentUser);
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_article", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function saveColumnVisible(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = json_decode($request->getContent(), true);
            $champs = array_keys($data);
            $user = $this->getUser();
            /** @var $user Utilisateur */
            $user->setColumnsVisibleForArticle($champs);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/get-article-fournisseur", name="demande_reference_by_fournisseur", options={"expose"=true})

     * @param Request $request
     * @param EntityManagerInterface $entityManager

     * @return Response
     */
    public function getRefArticleByFournisseur(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $fournisseur = json_decode($request->getContent(), true)) {
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);

            $fournisseur = $fournisseurRepository->find($fournisseur);

            if ($fournisseur) {
                $json = $this->renderView('article/modalNewArticleContent.html.twig', [
                    'references' => $articleFournisseurRepository->getByFournisseur($fournisseur),
                    'champsLibres' => [],
                ]);
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajax_article_new_content", name="ajax_article_new_content", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function ajaxArticleNewContent(Request $request,
                                          EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {

            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $refArticle = $referenceArticleRepository->find($data['referenceArticle']);
            $articleFournisseur = $articleFournisseurRepository
                ->findByRefArticleAndFournisseur($data['referenceArticle'], $data['fournisseur']);

            if (count($articleFournisseur) === 0) {
                $json = [
                    'error' => 'Aucune référence fournisseur trouvée.'
                ];
            } elseif (count($articleFournisseur) > 0) {
                $typeArticle = $refArticle->getType();

                $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
                $json = [
                    'content' => $this->renderView(
                        'article/modalNewArticleContent.html.twig',
                        [
                            'typeArticle' => $typeArticle->getLabel(),
                            'champsLibres' => $champsLibres,
                            'references' => $articleFournisseur,
                        ]
                    ),
                ];
            } else {
                $json = false;
            }

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajax-fournisseur-by-refarticle", name="ajax_fournisseur_by_refarticle", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function ajaxFournisseurByRefArticle(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $referenceArticleRepository->find($data['refArticle']);
            if ($refArticle && $refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $articleFournisseurs = $refArticle->getArticlesFournisseur();
                $fournisseurs = [];
                foreach ($articleFournisseurs as $articleFournisseur) {
                    $fournisseurs[] = $articleFournisseur->getFournisseur();
                }
                $fournisseursUnique = array_unique($fournisseurs);
                $json = $this->renderView(
                    'article/optionFournisseurNewArticle.html.twig',
                    [
                        'fournisseurs' => $fournisseursUnique
                    ]
                );
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/exporter-articles", name="export_all_arts", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param FreeFieldService $freeFieldService
     * @param CSVExportService $csvService
     * @return Response
     */
    public function exportAllArticles(EntityManagerInterface $entityManager,
                                      FreeFieldService $freeFieldService,
                                      CSVExportService $csvService): Response
    {
        $ffConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::ARTICLE]);

        $header = array_merge([
            'reference',
            'libelle',
            'quantité',
            'type',
            'statut',
            'commentaire',
            'emplacement',
            'code barre',
            'date dernier inventaire',
            'lot',
            'date d\'entrée en stock',
            'date de péremption',
        ], $ffConfig['freeFieldsHeader']);

        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");

        return $csvService->streamResponse(function($output) use ($entityManager, $csvService, $freeFieldService, $ffConfig) {
            $articleRepository = $entityManager->getRepository(Article::class);

            $articles = $articleRepository->iterateAll();
            foreach($articles as $article) {
                $this->putArticleLine($output, $csvService, $freeFieldService, $ffConfig, $article);
            }
        }, "export-articles-$today.csv", $header);
    }


    private function putArticleLine($handle,
                                    CSVExportService $csvService,
                                    FreeFieldService $freeFieldService,
                                    array $ffConfig,
                                    array $article) {
        $line = [
            $article['reference'],
            $article['label'],
            $article['quantite'],
            $article['typeLabel'],
            $article['statutName'],
            $article['commentaire'] ? strip_tags($article['commentaire']) : '',
            $article['empLabel'],
            $article['barCode'],
            $article['dateLastInventory'] ? $article['dateLastInventory']->format('d/m/Y H:i:s') : '',
            $article['batch'],
            $article['stockEntryDate'] ? $article['stockEntryDate']->format('d/m/Y H:i:s') : '',
            $article['expiryDate'] ? $article['expiryDate']->format('d/m/Y') : '',
        ];

        foreach ($ffConfig['freeFieldIds'] as $freeFieldId) {
            $line[] = $freeFieldService->serializeValue([
                'typage' => $ffConfig['freeFieldsIdToTyping'][$freeFieldId],
                'valeur' => $article['freeFields'][$freeFieldId] ?? ''
            ]);
        }

        $csvService->putLine($handle, $line);
    }

    /**
     * @Route("/etiquettes", name="article_print_bar_codes", options={"expose"=true}, methods={"GET"})

     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param PDFGeneratorService $PDFGeneratorService
     * @param ArticleDataService $articleDataService

     * @return Response

     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printArticlesBarCodes(Request $request,
                                          EntityManagerInterface $entityManager,
                                          PDFGeneratorService $PDFGeneratorService,
                                          ArticleDataService $articleDataService): Response {
        $articleRepository = $entityManager->getRepository(Article::class);
        $listArticles = $request->query->get('listArticles') ?: [];
        $barcodeConfigs = array_slice(
            array_map(
                function (Article $article) use ($articleDataService) {
                    return $articleDataService->getBarcodeConfig($article);
                },
                $articleRepository->findByIds($listArticles)
            ),
            $request->query->get('start'),
            $request->query->get('length')
        );
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'article');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    /**
     * @Route("/{article}/etiquette", name="article_single_bar_code_print", options={"expose"=true})
     * @param Article $article
     * @param ArticleDataService $articleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getSingleArticleBarCode(Article $article,
                                            ArticleDataService $articleDataService,
                                            PDFGeneratorService $PDFGeneratorService): Response {
        $barcodeConfigs = [$articleDataService->getBarcodeConfig($article)];
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'article');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }
}
