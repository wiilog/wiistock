<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\ShippingRequest\ShippingRequestLine;
use App\Entity\TagTemplate;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\FormException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Service\ArticleDataService;
use App\Service\MouvementStockService;
use App\Service\PDFGeneratorService;
use App\Service\SettingsService;
use App\Service\TagTemplateService;
use App\Service\Tracking\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UserService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route("/article")]
class ArticleController extends AbstractController {
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

    #[Route("/", name: "article_index", methods: [self::GET])]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_ARTI])]
    public function index(Request                $request,
                          EntityManagerInterface $entityManager,
                          ArticleDataService     $articleDataService,
                          TagTemplateService     $tagTemplateService): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $referenceFilter = $request->query->getInt("referenceFilter");
        $reference = $referenceFilter
            ? $entityManager->find(ReferenceArticle::class, $referenceFilter)
            : null;

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $filter = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, FiltreSup::PAGE_ARTICLE, $currentUser);

        return $this->render('article/index.html.twig', [
            "fields" => $articleDataService->getColumnVisibleConfig($entityManager, $currentUser),
            "searches" => $currentUser->getRechercheForArticle(),
            "tag_templates" => $tagTemplateService->serializeTagTemplates($entityManager, CategoryType::ARTICLE),
            "activeOnly" => !empty($filter) && ($filter->getValue() === $articleDataService->getActiveArticleFilterValue()),
            "referenceFilter" => $reference?->getReference(),
        ]);
    }

    #[Route("/show-actif-inactif", name: "article_actif_inactif", options: ["expose" => true], methods: [self::POST], condition: 'request.isXmlHttpRequest()')]
    public function displayActifOrInactif(EntityManagerInterface $entityManager,
                                          ArticleDataService $articleDataService,
                                          Request $request) : Response {
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

    #[Route("/voir/{id}", name: "article_show_page", options: ['expose' => true], methods: [self::GET])]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_ARTI])]
    public function showPage(Article $article, EntityManagerInterface $manager): Response {
        $fieldsParamRepository = $manager->getRepository(FixedFieldStandard::class);
        $freeFieldRepository = $manager->getRepository(FreeField::class);
        $trackingMovementRepository = $manager->getRepository(TrackingMovement::class);

        $type = $article->getType();
        $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::ARTICLE);
        $hasMovements = $trackingMovementRepository->countByArticle($article) > 0;
        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_ARTICLE);

        return $this->render("article/show/index.html.twig", [
            'article' => $article,
            'hasMovements' => $hasMovements,
            'freeFields' => $freeFields,
            'fieldsParam' => $fieldsParam,
        ]);
    }

    #[Route("/api", name: "article_api", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_ARTI], mode: HasPermission::IN_JSON)]
    public function api(Request $request,
                        ArticleDataService $articleDataService): Response
    {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = $articleDataService->getArticleDataByParams($request->request, $loggedUser);

        return new JsonResponse($data);
    }

    #[Route("/api-columns", name: "article_api_columns", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_ARTI], mode: HasPermission::IN_JSON)]
    public function apiColumns(ArticleDataService $articleDataService,
                               EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        return new JsonResponse(
            $articleDataService->getColumnVisibleConfig($entityManager, $currentUser)
        );
    }

    #[Route("/voir", name: "article_show", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    public function show(Request                $request,
                         ArticleDataService     $articleDataService,
                         EntityManagerInterface $entityManager): JsonResponse
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
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_ARTICLE);

        $barcode = $articleDataService->generateBarcode();

        return $this->render("article/form/new.html.twig", [
            "new_article" => new Article(),
            "submit_url" => $this->generateUrl("article_new"),
            "types" => $types,
            "fieldsParam" => $fieldsParam,
            "barcode" => $barcode
        ]);
    }

    #[Route("/nouveau", name: "article_new", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        SettingsService $settingsService,
                        MouvementStockService $mouvementStockService,
                        ArticleDataService $articleDataService,
                        TrackingMovementService $trackingMovementService): Response {
        $data = $request->request->all();

        $barcode = $data['barcode'];
        $existingArticle = $entityManager->getRepository(Article::class)->findOneBy(['barCode' => $barcode]);
        if(!$existingArticle) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();
            $articleRepository = $entityManager->getRepository(Article::class);

            $rfidPrefix = $settingsService->getValue($entityManager, Setting::RFID_PREFIX);

            if (isset($data['rfidTag'])) {
                if(!empty($rfidPrefix) && !str_starts_with($data['rfidTag'], $rfidPrefix)) {
                    throw new FormException("Le tag RFID ne respecte pas le préfixe paramétré ($rfidPrefix).");
                }

                $articleWithSameTag = $articleRepository->findOneBy(['RFIDtag' => $data['rfidTag']]);
                if ($articleWithSameTag) {
                    throw new FormException("Le tag RFID {$data['rfidTag']} est déja utilisé.");
                }
            }

            $article = $articleDataService->newArticle($entityManager, $data);
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

                $mouvementStockService->finishStockMovement(
                    $stockMovement,
                    new DateTime('now'),
                    $article->getEmplacement()
                );

                $entityManager->persist($stockMovement);
                $entityManager->flush();

                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $article->getTrackingPack() ?: $article->getBarCode(),
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
                'barcode' => $articleDataService->generateBarcode()
            ]);
        }
    }

    #[Route("/api-modifier", name: "article_edit", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request                $request,
                         EntityManagerInterface $entityManager,
                         ArticleDataService     $articleDataService,
                         TranslationService     $translation): Response {
        $data = $request->request;
        if ($data->all()) {
            $article = $entityManager->getRepository(Article::class)->find($data->get('id'));
            try {
                $article = $articleDataService->newArticle($entityManager, $data, [
                    "existing" => $article,
                ]);
                $response = [
                    'success' => true,
                    'articleId' => $data->get('id'),
                    'barcode' => $article->getBarCode(),
                ];
                $entityManager->flush();
            } /** @noinspection PhpRedundantCatchClauseInspection */
            catch (ArticleNotAvailableException) {
                $response = [
                    'success' => false,
                    'msg' => "Vous ne pouvez pas modifier un article qui n'est pas disponible."
                ];
            } /** @noinspection PhpRedundantCatchClauseInspection */
            catch (RequestNeedToBeProcessedException) {
                $response = [
                    'success' => false,
                    'msg' => "Vous ne pouvez pas modifier un article qui est dans une " . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . "."
                ];
            } /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException) {
                $response = [
                    'success' => false,
                    'msg' => "Le tag RFID {$data->get('rfidTag')} est déja utilisé.",
                ];
            }

            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/supprimer", name: "article_delete", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request $request,
                           MouvementStockService $mouvementStockService,
                           EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $shippingRequestLineRepository = $entityManager->getRepository(ShippingRequestLine::class);

            /** @var Article $article */
            $article = $articleRepository->find($data['article']);
            $articleBarCode = $article->getBarCode();

            $locationMissionCounter = $articleRepository->countInventoryLocationMission($article);
            $shippingRequestLineCounter = $shippingRequestLineRepository->count(['article' => $article]);

            $trackingPack = $article->getTrackingPack();

            if ($article->getCollectes()->isEmpty()
                && $article->getPreparationOrderLines()->isEmpty()
                && $article->getOrdreCollecte()->isEmpty()
                && $article->getTransferRequests()->isEmpty()
                && $article->getInventoryMissions()->isEmpty()
                && $article->getInventoryEntries()->isEmpty()
                && $locationMissionCounter === 0
                && $shippingRequestLineCounter === 0) {

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

#[Route("/verification", name: "article_check_delete", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_ARTI], mode: HasPermission::IN_JSON)]

    public function checkArticleCanBeDeleted(Request $request,
                                             UserService $userService,
                                             EntityManagerInterface $entityManager): Response {
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
                $hasRightToDeleteOrders = $userService->hasRightFunction(Menu::ORDRE, Action::DELETE);
                $hasRightToDeleteRequests = $userService->hasRightFunction(Menu::DEM, Action::DELETE);
                $hasRightToDeleteTraca = $userService->hasRightFunction(Menu::TRACA, Action::DELETE);
                $hasRightToDeleteStock = $userService->hasRightFunction(Menu::STOCK, Action::DELETE);

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

    #[Route("/autocompleteArticleFournisseur", name: "get_articleRef_fournisseur", options: ["expose" => true], condition: "request.isXmlHttpRequest()")]
    public function getRefArticles(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $search = $request->query->get('term');

        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $articleFournisseur = $articleFournisseurRepository->findBySearch($search);

        return new JsonResponse(['results' => $articleFournisseur]);
    }

    #[Route("/autocomplete-art", name: "get_articles", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    public function getArticles(EntityManagerInterface $entityManager, Request $request): JsonResponse {
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

    #[Route("/get-article-collecte/{collect}", name: "collecte_article_by_refArticle", options: ["expose" => true], methods: [self::POST])]
    public function getCollecteArticleByRefArticle(
        Request                 $request,
        EntityManagerInterface  $entityManager,
        ArticleDataService      $articleDataService,
        Collecte                $collect ): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $refArticle = null;
            if ($data['referenceArticle']) {
                $refArticle = $referenceArticleRepository->find($data['referenceArticle']);
            }
            if ($refArticle) {
                $json = $articleDataService->getCollecteArticleOrNoByRefArticle($collect, $refArticle, $this->getUser());
            } else {
                $json = false; //TODO gérer erreur retour
            }

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/get-article-demande", name: "demande_article_by_refArticle", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    public function getLivraisonArticlesByRefArticle(Request                $request,
                                                     ArticleDataService     $articleDataService,
                                                     SettingsService        $settingsService,
                                                     EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $requestRepository = $entityManager->getRepository(Demande::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $referenceArticleRepository->find($data['refArticle']);
            $deliveryRequest = $requestRepository->find($data['deliveryRequestId']);

            $needsQuantitiesCheck = !$settingsService->getValue($entityManager, Setting::MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY, false);

            if ($refArticle && $deliveryRequest) {
                /** @var Utilisateur $currentUser */
                $currentUser = $this->getUser();
                $json = $articleDataService->getLivraisonArticlesByRefArticle($refArticle, $deliveryRequest, $currentUser, $needsQuantitiesCheck);
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/etiquettes", name: "article_print_bar_codes", options: ["expose" => true], methods: [self::GET])]
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

    #[Route("/{article}/etiquette", name: "article_single_bar_code_print", options: ["expose" => true], methods: [self::GET])]
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

    #[Route("/get-article-tracking-movements", name: "get_article_tracking_movements", options: ["expose" => true], methods: [self::GET], condition: "request.isXmlHttpRequest()")]
    public function getTrackingMovements(EntityManagerInterface $manager, Request $request): Response {
        $article = $request->query->get('article');

        $movements = $manager->getRepository(TrackingMovement::class)->getArticleTrackingMovements($article, ['mainMovementOnly' => true]);

        return $this->json([
            'template' => $this->renderView('article/show/timeline.html.twig', [
                'movements' => $movements,
            ]),
        ]);
    }

    #[Route("/modifier-page/{article}", name: "article_edit_page", options: ["expose" => true], methods: [self::GET])]
    public function editTemplate(EntityManagerInterface $manager, Article $article): Response {
        $typeRepository = $manager->getRepository(Type::class);
        $freeFieldRepository = $manager->getRepository(FreeField::class);
        $fieldsParamRepository = $manager->getRepository(FixedFieldStandard::class);
        $hasMovements = count($manager->getRepository(TrackingMovement::class)->getArticleTrackingMovements($article->getId()));

        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_ARTICLE);

        return $this->render("article/form/edit.html.twig", [
            "article" => $article,
            "submit_url" => $this->generateUrl("article_edit"),
            "hasMovements" => $hasMovements,
            "fieldsParam" => $fieldsParam,
        ]);
    }

    #[Route("/get-free-fields-by-type", name: "get_free_fields_by_type", options: ["expose" => true], methods: [self::GET])]
    public function getFreefieldsByType(Request $request, EntityManagerInterface $manager): Response {
        $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);
        $freeFieldRepository = $manager->getRepository(FreeField::class);

        $reference = $request->query->has('referenceId')
            ? $referenceArticleRepository->find($request->query->get('referenceId'))
            : null;

        return $this->json([
            'success' => true,
            'template' => $this->renderView('article/form/free-fields.html.twig', [
                'type' => $reference?->getType(),
            ]),
            'type' => $reference?->getType()?->getLabel()
        ]);
    }

    #[Route("/est-dans-ul/{barcode}", name: "article_is_in_lu", options: ["expose" => true], methods: [self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_MOUV], mode: HasPermission::IN_JSON)]
    public function isInLU(EntityManagerInterface $manager, string $barcode): Response {
        $article = $manager->getRepository(Article::class)->isInLogisticUnit($barcode);

        return $this->json([
            "success" => true,
            "in_logistic_unit" => !empty($article),
            "logistic_unit" => $article?->getCurrentLogisticUnit()?->getCode(),
        ]);
    }
}
