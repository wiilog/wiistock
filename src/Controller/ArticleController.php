<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\ArticleFournisseur;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\Article;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\Setting;
use App\Entity\TagTemplate;
use App\Entity\TrackingMovement;
use App\Entity\ReferenceArticle;
use App\Entity\CategorieCL;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Exceptions\FormException;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Service\DemandeLivraisonService;
use App\Service\MouvementStockService;
use App\Service\PDFGeneratorService;
use App\Service\ArticleDataService;
use App\Service\PreparationsManagerService;
use App\Service\RefArticleDataService;
use App\Service\SettingsService;
use App\Service\TagTemplateService;
use App\Service\TrackingMovementService;
use App\Service\UserService;
use App\Annotation\HasPermission;

use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use WiiCommon\Helper\Stream;

/**
 * @Route("/article")
 */
class ArticleController extends AbstractController
{

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
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(ArticleDataService $articleDataService,
                                UserService $userService)
    {
        $this->articleDataService = $articleDataService;
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="article_index", methods={"GET", "POST"})
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ARTI})
     */
    public function index(EntityManagerInterface $entityManager, ArticleDataService $articleDataService, TagTemplateService $tagTemplateService): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $filter = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, FiltreSup::PAGE_ARTICLE, $currentUser);

        return $this->render('article/index.html.twig', [
            "fields" => $articleDataService->getColumnVisibleConfig($entityManager, $currentUser),
            "searches" => $currentUser->getRechercheForArticle(),
            "tag_templates" => $tagTemplateService->serializeTagTemplates($entityManager, CategoryType::ARTICLE),
            "activeOnly" => !empty($filter) && ($filter->getValue() === $articleDataService->getActiveArticleFilterValue())
        ]);
    }

    /**
     * @Route("/show-actif-inactif", name="article_actif_inactif", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function displayActifOrInactif(EntityManagerInterface $entityManager,
                                          ArticleDataService $articleDataService,
                                          Request $request) : Response
    {
        if ($data = json_decode($request->getContent(), true)){

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
     * @Route("/voir/{id}", name="article_show_page", options={"expose"=true})
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ARTI})
     */
    public function showPage(Article $article, EntityManagerInterface $manager): Response {
        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);
        $type = $article->getType();
        $freeFields = $manager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($type, CategorieCL::ARTICLE);
        $hasMovements = count($manager->getRepository(TrackingMovement::class)->getArticleTrackingMovements($article->getId()));
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARTICLE);

        return $this->render("article/show/index.html.twig", [
            'article' => $article,
            'hasMovements' => $hasMovements,
            'freeFields' => $freeFields,
            'fieldsParam' => $fieldsParam,
        ]);
    }

    /**
     * @Route("/api", name="article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ARTI}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        ArticleDataService $articleDataService): Response
    {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = $articleDataService->getArticleDataByParams($request->request, $loggedUser);

        return new JsonResponse($data);
    }

    /**
     * @Route("/api-columns", name="article_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ARTI}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(ArticleDataService $articleDataService,
                               EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        return new JsonResponse(
            $articleDataService->getColumnVisibleConfig($entityManager, $currentUser)
        );
    }

    /**
     * @Route("/voir", name="article_show", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function show(Request $request,
                            ArticleDataService $articleDataService,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
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

    #[Route("/nouveau-page", name: "article_new_page", options: ["expose" => true])]
    public function newTemplate(EntityManagerInterface $entityManager, ArticleDataService $articleDataService): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARTICLE);

        $barcode = $articleDataService->generateBarCode();

        return $this->render("article/form/new.html.twig", [
            "new_article" => new Article(),
            "submit_url" => $this->generateUrl("article_new"),
            "types" => $types,
            "fieldsParam" => $fieldsParam,
            "barcode" => $barcode
        ]);
    }

    /**
     * @Route("/nouveau", name="article_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        MouvementStockService $mouvementStockService,
                        ArticleDataService $articleDataService,
                        TrackingMovementService $trackingMovementService): Response {
        $data = $request->request->all();

        $barcode = $data['barcode'];
        $existingArticle = $entityManager->getRepository(Article::class)->findOneBy(['barCode' => $barcode]);
        if(!$existingArticle) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();
            $settingRepository = $entityManager->getRepository(Setting::class);
            $rfidPrefix = $settingRepository->getOneParamByLabel(Setting::RFID_PREFIX);
            if (isset($data['rfidTag']) && !empty($rfidPrefix) && !str_starts_with($data['rfidTag'], $rfidPrefix)) {
                return $this->json([
                    'success' => false,
                    'msg' => "Le tag RFID ne respecte pas le préfixe paramétré ($rfidPrefix)."
                ]);
            }

            $article = $this->articleDataService->newArticle($data, $entityManager);
            $refArticleId = $data["refArticle"];
            $refArticleFournisseurId = $article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getId() : '';

            if ($refArticleId != $refArticleFournisseurId) {
                throw new FormException("La référence article fournisseur ne correspond pas à la référence article");
            }

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

                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $article,
                    $article->getEmplacement(),
                    $this->getUser(),
                    new DateTime('now'),
                    false,
                    true,
                    TrackingMovement::TYPE_DEPOSE
                );

                $trackingMovement->setMouvementStock($stockMovement);

                $entityManager->persist($trackingMovement);
                $entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'articleId' => $article->getId(),
            ]);
        } else {
            return $this->json([
                'success' => false,
                'msg' => "Le code barre de l'article a été actualisé, veuillez valider de nouveau le formulaire.",
                'barcode' => $articleDataService->generateBarCode()
            ]);
        }
    }

    /**
     * @Route("/api-modifier", name="article_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = $request->request->all()) {
            $article = $entityManager->getRepository(Article::class)->find($data['id']);
                try {
                    $article = $this->articleDataService->newArticle($data, $entityManager, $article);
                    $response = [
                        'success' => true,
                        'articleId' => $data['id'],
                        'barcode' => $article->getBarCode(),
                    ];
                    $entityManager->flush();
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
            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="article_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           MouvementStockService $mouvementStockService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);

            /** @var Article $article */
            $article = $articleRepository->find($data['article']);
            $articleBarCode = $article->getBarCode();

            $locationMissionCounter = $articleRepository->countInventoryLocationMission($article);

            $trackingPack = $article->getTrackingPack();

            if ($article->getCollectes()->isEmpty()
                && $article->getPreparationOrderLines()->isEmpty()
                && $article->getOrdreCollecte()->isEmpty()
                && $article->getTransferRequests()->isEmpty()
                && $article->getInventoryMissions()->isEmpty()
                && $article->getInventoryEntries()->isEmpty()
                && $locationMissionCounter === 0) {

                if ($trackingPack) {
                    $trackingPack->setArticle(null);
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

                /** @var DeliveryRequestArticleLine[] $line */
                $lines = $article->getDeliveryRequestLines()->toArray();

                $requests = Stream::from($lines)
                    ->map(fn(DeliveryRequestArticleLine $line) => $line->getRequest())
                    ->filter()
                    ->unique()
                    ->values();

                /** @var Request $request */
                foreach ($requests as $request) {
                    $entityManager->remove($request);
                }

                /** @var DeliveryRequestArticleLine $line */
                foreach ($article->getDeliveryRequestLines()->toArray() as $line) {
                    $line->setRequest(null);
                    $entityManager->remove($line);
                }
                $entityManager->remove($article);
                $entityManager->flush();

                return new JsonResponse([
                    'delete' => $rows,
                    'success' => true,
                    'msg' => "L'article <strong>$articleBarCode</strong> a bien été supprimé."
                ]);
            }
            else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "L'article <strong>$articleBarCode</strong> est utilisé, vous ne pouvez pas le supprimer."
                ]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="article_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ARTI}, mode=HasPermission::IN_JSON)
     */
    public function checkArticleCanBeDeleted(Request $request,
                                             EntityManagerInterface $entityManager): Response
    {
        if ($articleId = json_decode($request->getContent(), true)) {
            $isFromReception = $request->query->getBoolean('fromReception');

            $articleRepository = $entityManager->getRepository(Article::class);

            /** @var Article $article */
            $article = $articleRepository->find($articleId);

            $articleAssociations = $article->getUsedAssociation();

            if (!$articleAssociations) {
                $locationMissionCounter = $articleRepository->countInventoryLocationMission($article);
                if ($locationMissionCounter > 0) {
                    $articleAssociations = Article::USED_ASSOC_INVENTORY_ENTRY;
                }
            }

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
                $deliveryRequestLines = $article->getDeliveryRequestLines();
                $preparationOrderLines = $article->getPreparationOrderLines();

                /**
                 * @var DeliveryRequestArticleLine $lastDeliveryRequestLine
                 */
                $lastDeliveryRequestLine = $deliveryRequestLines->last();

                /**
                 * @var PreparationOrderArticleLine $lastPreparationOrderLine
                 */
                $lastPreparationOrderLine = $preparationOrderLines->last();

                $isNotUsedInAssoc = ($articlesMvtTracaIsEmpty && $articlesMvtStockIsEmpty && !$lastDeliveryRequestLine && !$lastPreparationOrderLine);

                if (($hasRightToDeleteTraca || $articlesMvtTracaIsEmpty)
                    && ($hasRightToDeleteStock || $articlesMvtStockIsEmpty)
                    && ($hasRightToDeleteRequests || $lastDeliveryRequestLine)
                    && ($hasRightToDeleteOrders || $lastPreparationOrderLine)) {
                    return new JsonResponse([
                        'delete' => ($isFromReception || $isNotUsedInAssoc),
                        'html' => $this->renderView('article/modalDeleteArticleRight.html.twig', [
                            "prepa" => $lastPreparationOrderLine ? $lastPreparationOrderLine->getPreparation()->getNumero() : null,
                            "request" => $lastDeliveryRequestLine ? $lastDeliveryRequestLine->getRequest()->getNumero() : null,
                            "mvtStockIsEmpty" => $articlesMvtStockIsEmpty,
                            "mvtTracaIsEmpty" => $articlesMvtTracaIsEmpty,
                            "askQuestion" => $isFromReception,
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
     */
    public function getRefArticles(Request $request, EntityManagerInterface $entityManager)
    {
        $search = $request->query->get('term');

        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $articleFournisseur = $articleFournisseurRepository->findBySearch($search);

        return new JsonResponse(['results' => $articleFournisseur]);
    }

    /**
     * @Route("/autocomplete-art", name="get_articles", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getArticles(EntityManagerInterface $entityManager, Request $request)
    {
        $search = $request->query->get('term');
        $referenceArticleReference = $request->query->get('referenceArticleReference');
        $activeOnly = $request->query->getBoolean('activeOnly');
        $activeReferenceOnly = $request->query->getBoolean('activeReferenceOnly');

        $articleRepository = $entityManager->getRepository(Article::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $articles = $articleRepository->getIdAndRefBySearch($search, $activeOnly, 'barCode', $referenceArticleReference, $activeReferenceOnly, $user);

        return new JsonResponse(['results' => $articles]);
    }

    /**
     * @Route("/get-article-collecte/{collect}", name="get_collecte_article_by_refArticle", options={"expose"=true})
     */
    public function getCollecteArticleByRefArticle(Request $request, EntityManagerInterface $entityManager, Collecte $collect): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $refArticle = null;
            if ($data['referenceArticle']) {
                $refArticle = $referenceArticleRepository->find($data['referenceArticle']);
            }
            if ($refArticle) {
                $json = $this->articleDataService->getCollecteArticleOrNoByRefArticle($collect, $refArticle, $this->getUser());
            } else {
                $json = false; //TODO gérer erreur retour
            }

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/get-article-demande", name="demande_article_by_refArticle", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getLivraisonArticlesByRefArticle(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $requestRepository = $entityManager->getRepository(Demande::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $referenceArticleRepository->find($data['refArticle']);
            $deliveryRequest = $requestRepository->find($data['deliveryRequestId']);
            $settings = $entityManager->getRepository(Setting::class);
            $needsQuantitiesCheck = !$settings->getOneParamByLabel(Setting::MANAGE_PREPARATIONS_WITH_PLANNING);

            if ($refArticle && $deliveryRequest) {
                /** @var Utilisateur $currentUser */
                $currentUser = $this->getUser();
                $json = $this->articleDataService->getLivraisonArticlesByRefArticle($refArticle, $deliveryRequest, $currentUser, $needsQuantitiesCheck);
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_article", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ARTI}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService $visibleColumnService): Response
    {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        /** @var $user Utilisateur */
        $user = $this->getUser();

        $visibleColumnService->setVisibleColumns('article', $fields, $user);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Vos préférences de colonnes à afficher ont bien été sauvegardées'
        ]);
    }

    /**
     * @Route("/get-article-fournisseur", name="demande_reference_by_fournisseur", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getRefArticleByFournisseur(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($fournisseur = json_decode($request->getContent(), true)) {
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
     */
    public function ajaxFournisseurByRefArticle(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $referenceArticleRepository->find($data['refArticle']);
            if ($refArticle && $refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
                $articleFournisseurs = $refArticle->getArticlesFournisseur();
                $fournisseurs = [];
                foreach ($articleFournisseurs as $articleFournisseur) {
                    $fournisseurs[] = $articleFournisseur->getFournisseur();
                }
                $fournisseursUnique = array_unique($fournisseurs);
                $json = $this->renderView('article/optionFournisseurNewArticle.html.twig', [
                    'fournisseurs' => $fournisseursUnique
                ]);
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/etiquettes", name="article_print_bar_codes", options={"expose"=true}, methods={"GET"})
     */
    public function printArticlesBarCodes(Request $request,
                                          EntityManagerInterface $entityManager,
                                          PDFGeneratorService $PDFGeneratorService,
                                          ArticleDataService $articleDataService): Response {
        $articleRepository = $entityManager->getRepository(Article::class);
        $forceTagEmpty = $request->query->get('forceTagEmpty', false);
        $tag = $forceTagEmpty
            ? null
            : ($request->query->get('template')
                ? $entityManager->getRepository(TagTemplate::class)->find($request->query->get('template'))
                : null);
        $listArticles = $request->query->all('listArticles') ?: [];
        $articles = $articleRepository->findBy(['id' => $listArticles]);
        $barcodeConfigs = Stream::from($articles)
            ->filter(function(Article $article) use ($forceTagEmpty, $tag) {
                return
                    (!$forceTagEmpty || $article->getType()?->getTags()?->isEmpty()) &&
                    (empty($tag) || in_array($article->getType(), $tag->getTypes()->toArray()));
            })
            ->map(fn(Article $article) => $articleDataService->getBarcodeConfig($article))
            ->toArray();

        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'article', $tag ? $tag->getPrefix() : 'ETQ');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs, false, $tag),
            $fileName
        );
    }

    /**
     * @Route("/{article}/etiquette", name="article_single_bar_code_print", options={"expose"=true})
     */
    public function getSingleArticleBarCode(Article $article,
                                            ArticleDataService $articleDataService,
                                            PDFGeneratorService $PDFGeneratorService): Response {
        $tag = $article->getType()->getTags()->first() ?: null;
        $barcodeConfigs = [$articleDataService->getBarcodeConfig($article)];
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'article', $tag ? $tag->getPrefix() : 'ETQ');
        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs, false, $tag),
            $fileName
        );
    }

    #[Route("/get-article-tracking-movements", name: "get_article_tracking_movements", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    public function getTrackingMovements(EntityManagerInterface $manager, Request $request): Response {
        $article = $request->query->get('article');

        $movements = $manager->getRepository(TrackingMovement::class)->getArticleTrackingMovements($article, ['mainMovementOnly'=>true]);

        return $this->json([
            'template' => $this->renderView('article/show/timeline.html.twig', [
                'movements' => $movements,
            ]),
        ]);
    }

    /**
     * @Route("/modifier-page/{article}", name="article_edit_page", options={"expose"=true})
     */
    public function editTemplate(EntityManagerInterface $manager, Article $article) {
        $typeRepository = $manager->getRepository(Type::class);
        $freeFieldRepository = $manager->getRepository(FreeField::class);
        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $freeFieldsGroupedByTypes = [];
        $hasMovements = count($manager->getRepository(TrackingMovement::class)->getArticleTrackingMovements($article->getId()));
        foreach ($types as $type) {
            $champsLibres = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::ARTICLE);
            $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
        }

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARTICLE);

        return $this->render("article/form/edit.html.twig", [
            "article" => $article,
            "submit_url" => $this->generateUrl("article_edit"),
            "freeFieldsGroupedByTypes" => $freeFieldsGroupedByTypes,
            "hasMovements" => $hasMovements,
            "fieldsParam" => $fieldsParam,
        ]);
    }

    #[Route("/get-free-fields-by-type", name: "get_free_fields_by_type", options: ["expose" => true], methods: "GET")]
    public function getFreefieldsByType(Request $request, EntityManagerInterface $manager): Response {
        $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);
        $freeFieldRepository = $manager->getRepository(FreeField::class);

        $reference = $request->query->has('referenceId')
            ? $referenceArticleRepository->find($request->query->get('referenceId'))
            : null;

        $freeFields = [];
        if($reference) {
            $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($reference->getType(), CategorieCL::ARTICLE);
        }

        return $this->json([
            'success' => true,
            'template' => $this->renderView('article/form/free-fields.html.twig', [
                'freeFields' => $freeFields
            ]),
            'type' => $reference?->getType()?->getLabel()
        ]);
    }
}
