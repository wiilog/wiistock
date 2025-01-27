<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\Emplacement;
use App\Entity\FiltreRef;
use App\Entity\Fournisseur;
use App\Entity\FreeField\FreeField;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Kiosk;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\OrdreCollecte;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\ShippingRequest\ShippingRequestLine;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\FormException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Helper\FormatHelper;
use App\Service\ArticleDataService;
use App\Service\ArticleFournisseurService;
use App\Service\AttachmentService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\Kiosk\KioskService;
use App\Service\MouvementStockService;
use App\Service\NotificationService;
use App\Service\PDFGeneratorService;
use App\Service\RefArticleDataService;
use App\Service\SettingsService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;


#[Route('/reference-article')]
class ReferenceArticleController extends AbstractController
{

    #[Route('/api-columns', name: 'ref_article_api_columns', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_REFE], mode: HasPermission::IN_JSON)]
    public function apiColumns(RefArticleDataService $refArticleDataService,
                               EntityManagerInterface $entityManager): Response {

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $fields = Stream::from($refArticleDataService->getColumnVisibleConfig($entityManager, $currentUser))
            ->filter(function ($column) {
                return !isset($column['hiddenColumn']) || !$column['hiddenColumn'];
            })
            ->values();
        return $this->json([
            'columns' => $fields,
            'search' =>  $currentUser->getSearches()['reference']['value'] ?? "",
            'index' =>  $currentUser->getPageIndexes() && $currentUser->getPageIndexes()['reference'] ? intval($currentUser->getPageIndexes()['reference']) : 0,
        ]);
    }

    #[Route('/api', name: 'ref_article_api', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_REFE], mode: HasPermission::IN_JSON)]
    public function api(Request               $request,
                        RefArticleDataService $refArticleDataService): Response {
        return $this->json($refArticleDataService->getRefArticleDataByParams($request->request));
    }

    #[Route("/creer", name: "reference_article_new", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    public function new(Request                   $request,
                        UserService               $userService,
                        FreeFieldService          $champLibreService,
                        EntityManagerInterface    $entityManager,
                        MouvementStockService     $mouvementStockService,
                        TrackingMovementService   $trackingMovementService,
                        RefArticleDataService     $refArticleDataService,
                        ArticleFournisseurService $articleFournisseurService,
                        AttachmentService         $attachmentService): Response {
        if (!$userService->hasRightFunction(Menu::STOCK, Action::CREATE)
            && !$userService->hasRightFunction(Menu::STOCK, Action::CREATE_DRAFT_REFERENCE)) {
            throw new FormException("Accès refusé");
        }

        if (($data = $request->request->all()) || ($data = json_decode($request->getContent(), true))) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();
            $now = new DateTime('now');

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $referenceArticleRepository->countByReference($data['reference']);
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, $data['statut']);

            if(isset($data['security']) && $data['security'] == "1" && isset($data['fileSheet']) && $data['fileSheet'] === "undefined") {
                throw new FormException("La fiche sécurité est obligatoire pour les références notées en Marchandise dangereuse.");
            }

            if ($refAlreadyExist) {
                $errorData = [
                    'success' => false
                ];

                if ($statut->getCode() === ReferenceArticle::DRAFT_STATUS) {
                    $errorData['msg'] = 'Une référence avec le même code a été créée en même temps. Le code a été actualisé, veuillez enregistrer de nouveau.';
                    $errorData['draftDefaultReference'] = $refArticleDataService->getDraftDefaultReference($entityManager);
                } else {
                    $errorData['msg'] = 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.';
                    $errorData['invalidFieldsSelector'] = 'input[name="reference"]';
                }

                return new JsonResponse($errorData);
            }

            $type = $typeRepository->find($data['type']);

            if ($data['emplacement'] ?? false) {
                $emplacement = $emplacementRepository->find($data['emplacement']);
            } else {
                $emplacement = null; //TODO gérer message erreur (faire un return avec msg erreur adapté -> à ce jour un return false correspond forcément à une réf déjà utilisée)
            }

            $typeArticle = match ($data['type_quantite']) {
                'reference' => ReferenceArticle::QUANTITY_TYPE_REFERENCE,
                default => ReferenceArticle::QUANTITY_TYPE_ARTICLE,
            };

            $needsMobileSync = filter_var($data['mobileSync'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($needsMobileSync && ($referenceArticleRepository->count(['needsMobileSync' => true]) > ReferenceArticle::MAX_NOMADE_SYNC && $data['mobileSync'])) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Le nombre maximum de synchronisations nomade a été atteint.'
                ]);
            }
            $refArticle = new ReferenceArticle();
            $refArticle
                ->setNeedsMobileSync($needsMobileSync)
                ->setLibelle($data['libelle'])
                ->setReference($data['reference'])
                ->setCommentaire($data['commentaire'] ?? null)
                ->setTypeQuantite($typeArticle)
                ->setPrixUnitaire(max(0, $data['prix'] ?? null))
                ->setType($type)
                ->setIsUrgent(filter_var($data['urgence'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->setEmplacement($emplacement)
				->setBarCode($refArticleDataService->generateBarCode())
                ->setBuyer(isset($data['buyer']) ? $userRepository->find($data['buyer']) : null)
                ->setCreatedBy($loggedUser)
                ->setCreatedAt($now)
                ->setNdpCode($data['ndpCode'] ?? null)
                ->setDangerousGoods(filter_var($data['security'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->setOnuCode($data['onuCode'] ?? null)
                ->setProductClass($data['productClass'] ?? null);

            $refArticleDataService->updateDescriptionField($entityManager, $refArticle, $data);

            $refArticle->setProperties(['visibilityGroup' => ($data['visibility-group'] ?? null) ? $visibilityGroupRepository->find(intval($data['visibility-group'] ?? null)) : null]);


            if ($refArticle->getIsUrgent()) {
                $refArticle->setUserThatTriggeredEmergency($loggedUser);
            }

            if (!empty($data['limitSecurity'])) {
            	$refArticle->setLimitSecurity($data['limitSecurity']);
			}
            if (!empty($data['limitWarning'])) {
            	$refArticle->setLimitWarning($data['limitWarning']);
			}
            if (!empty($data['emergencyComment'])) {
                $refArticle->setEmergencyComment($data['emergencyComment']);
            }
            if (!empty($data['emergencyQuantity'])) {
                $refArticle->setEmergencyQuantity($data['emergencyQuantity']);
            }
            if (!empty($data['categorie'])) {
            	$category = $inventoryCategoryRepository->find($data['categorie']);
            	if ($category) {
                    $refArticle->setCategory($category);
                }
			}
            if ($statut) {
                $refArticle->setStatut($statut);
            }

            if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                $refArticle->setQuantiteStock($data['quantite'] ? max($data['quantite'], 0) : 0); // protection contre quantités négatives
            } else {
                $refArticle->setQuantiteStock(0);
            }
            $refArticle->setQuantiteReservee(0);
            $refArticle->setStockManagement($data['stockManagement'] ?? null);

            $managersIds = Stream::explode(",", $data["managers"])
                ->filter()
                ->toArray();
            foreach ($managersIds as $managerId) {
                $manager = $userRepository->find($managerId);
                if ($manager) {
                    $refArticle->addManager($manager);
                }
            }

            $supplierReferenceLines = is_array($data['frl']) ? $data['frl'] : json_decode($data['frl'], true);
            if (!empty($supplierReferenceLines)) {
                foreach ($supplierReferenceLines as $supplierReferenceLine) {
                    $referenceArticleFournisseur = $supplierReferenceLine['referenceFournisseur'];
                    try {
                        $supplierArticle = $articleFournisseurService->createArticleFournisseur([
                            'fournisseur' => $supplierReferenceLine['fournisseur'],
                            'article-reference' => $refArticle,
                            'label' => $supplierReferenceLine['labelFournisseur'],
                            'reference' => $referenceArticleFournisseur,
                            'visible' => $refArticle->getStatut()->getCode() !== ReferenceArticle::DRAFT_STATUS
                        ]);
                        $entityManager->persist($supplierArticle);
                    } catch (Exception $exception) {
                        if ($exception->getMessage() === ArticleFournisseurService::ERROR_REFERENCE_ALREADY_EXISTS) {
                            return new JsonResponse([
                                'success' => false,
                                'msg' => "La référence '$referenceArticleFournisseur' existe déjà pour un article fournisseur."
                            ]);
                        }
                    }
                }
            }

            if(isset($data['srl'])) {
                $storageRuleLines = json_decode($data['srl'], true);
                foreach ($storageRuleLines as $storageRuleLine) {
                    $storageRuleLocationId = $storageRuleLine['storageRuleLocation'] ?? null;
                    $storageRuleSecurityQuantity = $storageRuleLine['storageRuleSecurityQuantity'] ?? null;
                    $storageRuleConditioningQuantity = $storageRuleLine['storageRuleConditioningQuantity'] ?? null;
                    if ($storageRuleLocationId && $storageRuleSecurityQuantity && $storageRuleConditioningQuantity) {
                        $storageRuleLocation = $emplacementRepository->find($storageRuleLocationId);
                        if(!$storageRuleLocation) {
                            return new JsonResponse([
                                'success' => false,
                                'msg' => "Une règle de stockage n'a pas pu être créée car l'emplacement n'a pas été trouvé."
                            ]);
                        }
                        $storageRule = new StorageRule();
                        $storageRule
                            ->setLocation($storageRuleLocation)
                            ->setSecurityQuantity($storageRuleSecurityQuantity)
                            ->setConditioningQuantity($storageRuleConditioningQuantity)
                            ->setReferenceArticle($refArticle);
                        $entityManager->persist($storageRule);
                    } else {
                        return new JsonResponse([
                            'success' => false,
                            'msg' => "Une règle de stockage n'a pas pu être créée car un des champs requis n'a pas été renseigné."
                        ]);
                    }
                }
            }

            if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE
                && $refArticle->getQuantiteStock() > 0
                && $refArticle->getStatut()->getCode() !== ReferenceArticle::DRAFT_STATUS) {
                $mvtStock = $mouvementStockService->createMouvementStock(
                    $loggedUser,
                    null,
                    $refArticle->getQuantiteStock(),
                    $refArticle,
                    MouvementStock::TYPE_ENTREE
                );
                $mouvementStockService->finishStockMovement(
                    $mvtStock,
                    $now,
                    $emplacement
                );
                $traceMovement = $trackingMovementService->createTrackingMovement(
                    $refArticle->getTrackingPack() ?: $refArticle->getBarCode(),
                    $refArticle->getEmplacement(),
                    $loggedUser,
                    $now,
                    false,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        "mouvementStock" => $mvtStock,
                        "quantity" => $refArticle->getQuantiteStock(),
                        "entityManager" => $entityManager,
                        "refOrArticle" => $refArticle,
                    ]
                );
                $entityManager->persist($mvtStock);
                $entityManager->persist($traceMovement);
            }

            $entityManager->persist($refArticle);

            try{
                $entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                if (str_contains($e->getPrevious()->getMessage(), StorageRule::uniqueConstraintLocationReferenceArticleName)) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => "Impossible de créer deux règles de stockage pour le même emplacement."
                    ]);
                } else {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => "Une erreur est survenue lors de la sauvegarde. Veuillez réessayer."
                    ]);
                }
            }

            $champLibreService->manageFreeFields($refArticle, $data, $entityManager);

            $files = $request->files;
            if($files->has('image')) {
                $imageAttachment = $attachmentService->persistAttachment($entityManager, $files->get("image"));
                $refArticle->setImage($imageAttachment);
                $request->files->remove('image');
            }

            if($files->has('fileSheet')) {
                $sheetAttachment = $attachmentService->persistAttachment($entityManager, $files->get("fileSheet"));
                $refArticle->setSheet($sheetAttachment);
                $request->files->remove('fileSheet');
            }
            $attachmentService->manageAttachments($entityManager, $refArticle, $request->files);

            $entityManager->flush();

            if ($refArticle->getStatut()->getCode() === ReferenceArticle::DRAFT_STATUS){
                $refArticleDataService->sendMailCreateDraftOrDraftToActive($refArticle, $userRepository->getUserMailByReferenceValidatorAction(), true);
            }

            return $this->json([
                'success' => true,
                'msg' => 'La référence ' . $refArticle->getReference() . ' a bien été créée',
                'data' => [ // for reference created in reception-show
                    'id' => $refArticle->getId(),
                    'reference' => $refArticle->getReference(),
                ],
                'redirect' => match ($request->query->get("from")) {
                    "reception_add_line" => $this->generateUrl("reception_show", [
                        "id" => $request->query->get("reception"),
                        "open-modal" => "new",
                        "reference" => $refArticle->getId(),
                        "label" => $refArticle->getReference(),
                        "is_article" => $refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE,
                    ]),
                    "dispatch_add_line" => $this->generateUrl("dispatch_show", [
                        "id" => $request->query->get("dispatch"),
                        "open-modal" => "#addReferenceModalButton",
                    ]),
                    default => null,
                },
            ]);
        }

        throw new BadRequestHttpException();
    }

    #[Route('/', name: 'reference_article_index', methods: 'GET|POST', options: ['expose' => true])]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_REFE])]
    public function index(RefArticleDataService $refArticleDataService,
                          SettingsService $settingsService,
                          EntityManagerInterface $entityManager): Response {

        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $filtreRefRepository = $entityManager->getRepository(FiltreRef::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();


        $typeQuantite = [
            [
                'const' => 'QUANTITE_AR',
                'label' => 'référence',
            ],
            [
                'const' => 'QUANTITE_A',
                'label' => 'article',
            ]
        ];

        $fields = $refArticleDataService->getColumnVisibleConfig($entityManager, $user);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $inventoryCategories = $inventoryCategoryRepository->findAll();
        $typeChampLibre =  [];
        $freeFieldsGroupedByTypes = [];

        foreach ($types as $type) {
            $champsLibres = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $typeChampLibre[] = [
                'typeLabel' =>  $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
            $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
        }

        $filter = $filtreRefRepository->findOneByUserAndChampFixe($user, FiltreRef::FIXED_FIELD_STATUS);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        return $this->render('reference_article/index.html.twig', [
            "fields" => $fields,
            "searches" => $user->getRecherche(),
            'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
            'defaultLocation' => $settingsService->getParamLocation($entityManager, Setting::DEFAULT_LOCATION_REFERENCE),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            'typeQuantite' => $typeQuantite,
            'categories' => $inventoryCategories,
            'wantActive' => !empty($filter) && $filter->getValue() === ReferenceArticle::STATUT_ACTIF,
            'stockManagement' => [
                ReferenceArticle::STOCK_MANAGEMENT_FEFO,
                ReferenceArticle::STOCK_MANAGEMENT_FIFO
            ],
        ]);
    }

    #[Route(path: "/modifier", name: "reference_article_edit",  options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    public function edit(Request                $request,
                         EntityManagerInterface $entityManager,
                         UserService            $userService,
                         RefArticleDataService  $refArticleDataService,
                         AttachmentService      $attachmentService,
                         TranslationService     $translation): Response {
        if (!$userService->hasRightFunction(Menu::STOCK, Action::EDIT)
            && !$userService->hasRightFunction(Menu::STOCK, Action::EDIT_PARTIALLY)) {
            return $this->json([
                "success" => false,
                "msg" => "Accès refusé",
            ]);
        }
        $data = $request->request;
        if ($data->all()) {
            $refId = $data->getInt('idRefArticle');
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $referenceArticleRepository->find($refId);

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $referenceArticleRepository->countByReference($data->get('reference'), $refId);
            if ($refAlreadyExist) {
                throw new FormException("Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.");
            }
            if ($refArticle) {
                try {
                    /** @var Utilisateur $currentUser */
                    $currentUser = $this->getUser();
                    $attachmentService->removeAttachments($entityManager, $refArticle, $data->all()['files'] ?? []);
                    $response = $refArticleDataService->editRefArticle($entityManager, $refArticle, $data, $currentUser, $request->files);
                }
                catch (ArticleNotAvailableException $exception) {
                    throw new FormException("Vous ne pouvez pas modifier la quantité d'une référence inactive.");
                }
                catch (RequestNeedToBeProcessedException $exception) {
                    throw new FormException("Vous ne pouvez pas modifier la quantité d'une référence qui est dans un " . mb_strtolower($translation->translate("Ordre", "Livraison", "Ordre de livraison", false)) . " en cours.");
                }
            } else {
                throw new FormException("Une erreur s'est produite lors de la modification de la référence.");
            }
            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    #[Route('/supprimer', name: 'reference_article_delete', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::STOCK, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request                  $request,
                           EntityManagerInterface   $entityManager,
                           TranslationService       $translation): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $shippingRequestLineRepository = $entityManager->getRepository(ShippingRequestLine::class);

            /** @var ReferenceArticle $refArticle */
            $refArticle = $referenceArticleRepository->find($data['refArticle']);
            if(!$refArticle->getInventoryEntries()->isEmpty()){
                return new JsonResponse([
                    'success' => false,
                    'msg' => "
                        Cette référence est liée à une ou plusieurs entrées d'inventaire.<br>
                        Vous ne pouvez pas la supprimer.
                    "
                ]);
            } else if (!($refArticle->getCollecteReferences()->isEmpty())
                || !($refArticle->getDeliveryRequestLines()->isEmpty())
                || !($refArticle->getReceptionReferenceArticles()->isEmpty())
                || !($refArticle->getMouvements()->isEmpty())
                || !($refArticle->getArticlesFournisseur()->isEmpty())
                || !($refArticle->getTransferRequests()->isEmpty())
                || !($refArticle->getTransferRequests()->isEmpty())
                || $refArticle->getTrackingPack()
                || $shippingRequestLineRepository->count(['reference' => $refArticle]) > 0
                || $refArticle->hasTrackingMovements()) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => '
                        Cette référence article est lié à une unité logistique, des mouvements, une collecte,
                        une ' . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)) . ', une réception, une expédition ou un article fournisseur et ne peut pas être supprimée.
                    '
                ]);
            }
            $entityManager->remove($refArticle);
            $entityManager->flush();

            return new JsonResponse(['success' => true]);
        }
        throw new BadRequestHttpException();
    }

    #[Route('/addFournisseur', name: 'ajax_render_add_fournisseur', options: ['expose' => true], methods: 'GET', requirements: ['currentIndex' => '\d+'])]
    #[HasPermission([Menu::STOCK, Action::EDIT])]
    public function addFournisseur(Request $request): Response {
        $currentIndex = $request->query->get('currentIndex');
        $currentIndexInt = $request->query->getInt('currentIndex');

        $json = $this->renderView('reference_article/fournisseurArticle.html.twig', [
            'multipleObjectIndex' => !empty($currentIndex) || $currentIndexInt === 0 ? ($currentIndexInt + 1) : 0
        ]);
        return new JsonResponse($json);
    }

    #[Route("/{referenceArticle}/quantity", name: "get_quantity_ref_article", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function getQuantityByRefArticleId(SettingsService        $settingsService,
                                              EntityManagerInterface $entityManager,
                                              ReferenceArticle       $referenceArticle): JsonResponse {
        $needsQuantitiesCheck = !$settingsService->getValue($entityManager, Setting::MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY, false);
        $quantity = 0;

        if ($needsQuantitiesCheck) {
            if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                $quantity = $referenceArticle->getQuantiteDisponible();
            }
        }

        return new JsonResponse($quantity);
    }

    #[Route('/autocomplete-ref', name: 'get_ref_articles', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    public function getRefArticles(Request $request,
                                   EntityManagerInterface $entityManager): JsonResponse {
        $search = $request->query->get('term');
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        $activeOnly = $request->query->getBoolean('activeOnly', false);
        $minQuantity = $request->query->get('minQuantity');
        $typeQuantity = $request->query->get('typeQuantity');
        $field = $request->query->get('field', 'reference');
        $locationFilter = $request->query->get('locationFilter');
        $buyerFilter = $request->query->get('buyerFilter');

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $refArticles = $referenceArticleRepository->getIdAndRefBySearch(
            $search,
            $user,
            $activeOnly,
            $minQuantity !== null ? (int) $minQuantity : null,
            $typeQuantity,
            $field,
            $locationFilter,
            $buyerFilter
        );

        return new JsonResponse(['results' => $refArticles]);
    }

    #[Route('/{reference}/data', name: 'get_reference_data', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    public function getReferenceData(ReferenceArticle $reference, EntityManagerInterface $entityManager): JsonResponse {
        $articleRepository = $entityManager->getRepository(Article::class);
        $locations = [];
        if ($reference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            $locations = Stream::from($reference->getStorageRules())
                ->map(function (StorageRule $rule) use ($articleRepository, $reference) {
                    $location = $rule->getLocation();
                    $quantity = $articleRepository->quantityForRefOnLocation($reference, $location);

                    return [
                        'location' => [
                            'id' => $location->getId(),
                            'label' => $location->getLabel(),
                        ],
                        'quantity' => intval($quantity),
                    ];
                })
                ->toArray();
        }
        return $this->json([
            'label' => $reference->getLibelle(),
            'buyer' => FormatHelper::user($reference->getBuyer()),
            'stockQuantity' => $reference->getQuantiteStock(),
            'quantityType' => $reference->getTypeQuantite(),
            'locations' => json_encode($locations),
        ]);
    }

    #[Route('/voir', name: 'reference_article_show', options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_REFE], mode: HasPermission::IN_JSON)]
    public function show(Request $request,
                         RefArticleDataService $refArticleDataService,
                         EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle  = $referenceArticleRepository->find($data);
            $json = $refArticle
                ? $refArticleDataService->getViewEditRefArticle($refArticle, false, false, true)
                : false;
            return new JsonResponse($json);
        }

        throw new BadRequestHttpException();
    }

    #[Route('/quantites/{id}/{period}', name: 'reference_article_quantity_variations', options: ['expose' => true], methods: 'GET')]
    public function quantities(ReferenceArticle $referenceArticle,
                               int $period,
                               RefArticleDataService $refArticleDataService,
                               EntityManagerInterface $manager): Response {
        return $this->json([
            'data' => $refArticleDataService->getQuantityPredictions($manager, $referenceArticle, $period)
        ]);

    }

    #[Route("voir/{id}", name: "reference_article_show_page", options: ["expose" => true])]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_REFE])]
    public function showPage(Request                $request,
                             ReferenceArticle       $referenceArticle,
                             RefArticleDataService  $refArticleDataService,
                             EntityManagerInterface $entityManager): Response {
        $hasIaParams = $_SERVER['STOCK_FORECAST_URL'] ?? false;

        $type = $referenceArticle->getType();
        $showOnly = $request->query->getBoolean('showOnly');
        $freeFields = $entityManager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
        $articleRepository = $entityManager->getRepository(Article::class);

        $providerArticles = Stream::from($referenceArticle->getArticlesFournisseur())
            ->reduce(function(array $carry, ArticleFournisseur $providerArticle) use ($referenceArticle, $articleRepository) {
                $carry[] = [
                    'providerName' => $providerArticle->getFournisseur()->getNom(),
                    'providerCode' => $providerArticle->getFournisseur()->getCodeReference(),
                    'reference' => $providerArticle->getReference(),
                    'label' => $providerArticle->getLabel(),
                    'quantity' => $articleRepository->getQuantityForSupplier($providerArticle)
                ];
                return $carry;
                }, []);
        return $this->render('reference_article/show/show.html.twig', [
            'referenceArticle' => $referenceArticle,
            'providerArticles' => $providerArticles,
            'freeFields' => $freeFields,
            'showOnly' => $showOnly,
            'descriptionConfig' => $refArticleDataService->getDescriptionConfig($entityManager),
            'hasIaParams' => $hasIaParams,
        ]);
    }

    #[Route('/type-quantite', name: 'get_quantity_type', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    public function getQuantityType(Request $request, EntityManagerInterface $entityManager): JsonResponse {
		if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $reference = $referenceArticleRepository->find($data['id']);

			$quantityType = $reference ? $reference->getTypeQuantite() : '';

			return new JsonResponse($quantityType);
		}
		throw new BadRequestHttpException();
	}

    #[Route('/etiquettes', name: 'reference_article_bar_codes_print', options: ['expose' => true])]
    public function getBarCodes(Request $request,
                                RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager,
                                PDFGeneratorService $PDFGeneratorService): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $filtreRefRepository = $entityManager->getRepository(FiltreRef::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $userId = $user->getId();
        $filters = $filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $referenceArticleRepository->findByFiltersAndParams($filters, $request->query, $user, $this->getFormatter());

        $barcodeConfigs = Stream::from($queryResult['data'])
            ->map(static fn($refArticle) => is_array($refArticle) ? $refArticle[0] : $refArticle)
            ->map(static fn(ReferenceArticle $reference) => $refArticleDataService->getBarcodeConfig($reference))
            ->toArray();

        $barcodeCounter = count($barcodeConfigs);

        if ($barcodeCounter > 0) {
            $fileName = $PDFGeneratorService->getBarcodeFileName(
                $barcodeConfigs,
                'reference' . ($barcodeCounter > 1 ? 's' : '')
            );

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
                $fileName
            );
        }
        else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    #[Route('/{reference}/etiquette', name: 'reference_article_single_bar_code_print', options: ['expose' => true])]
    public function getSingleBarCodes(ReferenceArticle $reference,
                                      RefArticleDataService $refArticleDataService,
                                      PDFGeneratorService $PDFGeneratorService): Response {
        $barcodeConfigs = [$refArticleDataService->getBarcodeConfig($reference)];
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'reference');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    #[Route('/mouvements/api/{referenceArticle}', name: 'ref_mouvements_api', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    public function apiMouvements(EntityManagerInterface $entityManager,
                                  MouvementStockService  $mouvementStockService,
                                  Twig_Environment       $templating,
                                  ReferenceArticle       $referenceArticle): Response
    {
        $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $mouvements = $mouvementStockRepository->findByRef($referenceArticle);

        $data['data'] = Stream::from($mouvements)
            ->map(static function(MouvementStock $movement) use ($mouvementStockService, $templating) {
                $fromColumnConfig = $mouvementStockService->getFromColumnConfig($movement);
                $from = $fromColumnConfig['from'];
                $fromPath = $fromColumnConfig['path'];
                $fromPathParams = $fromColumnConfig['pathParams'];

                return [
                    'Date' => $movement->getDate() ? $movement->getDate()->format('d/m/Y H:i:s') : 'aucune',
                    'Quantity' => $movement->getQuantity(),
                    'Origin' => $movement->getEmplacementFrom() ? $movement->getEmplacementFrom()->getLabel() : 'aucun',
                    'Destination' => $movement->getEmplacementTo() ? $movement->getEmplacementTo()->getLabel() : 'aucun',
                    'Type' => $movement->getType(),
                    'Operator' => $movement->getUser() ? $movement->getUser()->getUsername() : 'aucun',
                    'from' => $templating->render('mouvement_stock/datatableMvtStockRowFrom.html.twig', [
                        'from' => $from,
                        'mvt' => $movement,
                        'path' => $fromPath,
                        'pathParams' => $fromPathParams
                    ]),
                    'ArticleCode' => $movement->getArticle()?->getBarCode() ?: $movement->getRefArticle()?->getBarCode()
                ];
            })
            ->toArray();
        return new JsonResponse($data);
    }

    #[Route("/purchaseRequests/api/{referenceArticle}", name: "ref_purchase_requests_api", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS], mode: HasPermission::IN_JSON)]
    public function apiPurchaseRequests(EntityManagerInterface $entityManager,
                                        ReferenceArticle       $referenceArticle,
                                        Request                $request,
                                        FormatService          $formatService): Response {

        $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
        $params = $request->request;
        $filters = [[
            "field" => "referenceArticle",
            "value" => $referenceArticle->getId(),
        ]];
        $data = $purchaseRequestRepository->findByParamsAndFilters($params, $filters);
        $data ["recordsTotal"] = $data ["count"];
        $data["recordsFiltered"] = 0;
        $data['data'] = Stream::from($data['data'])
            ->flatMap(static function (PurchaseRequest $purchaseRequest) use ($referenceArticle, $formatService, &$data) {
                $lines = Stream::from($purchaseRequest->getPurchaseRequestLines())
                    ->filter(fn(PurchaseRequestLine $purchaseRequestLine) => $purchaseRequestLine->getReference()->getId() === $referenceArticle->getId())
                    ->map(fn(PurchaseRequestLine $purchaseRequestLine) => [
                        "creationDate" => $formatService->datetime($purchaseRequest->getCreationDate()),
                        "from" => "<div class='pointer' data-purchase-request-id='" . $purchaseRequest->getId() . "'><div class='wii-icon wii-icon-export mr-2'></div>" . $purchaseRequest->getNumber() . "</div>",
                        "requester" => $formatService->user($purchaseRequest->getRequester()),
                        "status" => $formatService->status($purchaseRequest->getStatus()),
                        "requestedQuantity" => $purchaseRequestLine->getRequestedQuantity(),
                        "orderedQuantity" => $purchaseRequestLine->getOrderedQuantity(),
                    ])
                    ->toArray();
                $data["recordsFiltered"] += count($lines);
                return $lines;
            })
            ->toArray();
        return new JsonResponse($data);
    }

    #[Route('/{referenceArticle}/quantity', name: 'update_qte_refarticle', options: ['expose' => true], methods: 'PATCH', condition: 'request.isXmlHttpRequest()')]
    public function updateQuantity(EntityManagerInterface $entityManager,
                                   ReferenceArticle $referenceArticle,
                                   RefArticleDataService $refArticleDataService): JsonResponse {

        $refArticleDataService->updateRefArticleQuantities($entityManager, [$referenceArticle], true);
        $entityManager->flush();
        $refArticleDataService->treatAlert($entityManager, $referenceArticle);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/nouveau-page', name: 'reference_article_new_page', options: ['expose' => true])]
    public function newTemplate(Request                $request,
                                EntityManagerInterface $entityManager,
                                RefArticleDataService  $refArticleDataService,
                                SettingsService        $settingsService): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $inventoryCategories = $inventoryCategoryRepository->findAll();

        $shippingSettingsDefaultValues = [];
        if($request->query->has('shipping')){
            $supplierLabelReferenceCreate = $settingsService->getValue($entityManager, Setting::SHIPPING_SUPPLIER_LABEL_REFERENCE_CREATE);
            $supplierReferenceCreate = $settingsService->getValue($entityManager, Setting::SHIPPING_SUPPLIER_REFERENCE_CREATE);

            $shippingSettingsDefaultValues = [
                'type' => $settingsService->getValue($entityManager, Setting::SHIPPING_REFERENCE_DEFAULT_TYPE),
                'supplier' => $supplierLabelReferenceCreate
                    ? $supplierRepository->find($supplierLabelReferenceCreate)
                    : null,
                'supplierCode' => $supplierReferenceCreate
                    ? $supplierRepository->find($supplierReferenceCreate)
                    : null,
                'refArticleSupplierEqualsReference' => boolval($settingsService->getValue($entityManager, Setting::SHIPPING_REF_ARTICLE_SUPPLIER_EQUALS_REFERENCE)),
                'articleSupplierLabelEqualsReferenceLabel' => boolval($settingsService->getValue($entityManager, Setting::SHIPPING_ARTICLE_SUPPLIER_LABEL_EQUALS_REFERENCE_LABEL)),
            ];
        }

        return $this->render("reference_article/form/new.html.twig", [
            "new_reference" => (new ReferenceArticle())
                ->setTypeQuantite(ReferenceArticle::QUANTITY_TYPE_REFERENCE),
            "submit_route" => "reference_article_new",
            "submit_params" =>  json_encode([
                "from" => $request->query->get("from"),
                "reception" => $request->query->get("reception"),
                "dispatch" => $request->query->get("dispatch"),
            ]),
            "types" => $types,
            'defaultLocation' => $settingsService->getParamLocation($entityManager, Setting::DEFAULT_LOCATION_REFERENCE),
            'draftDefaultReference' => $refArticleDataService->getDraftDefaultReference($entityManager),
            "stockManagement" => [
                ReferenceArticle::STOCK_MANAGEMENT_FEFO,
                ReferenceArticle::STOCK_MANAGEMENT_FIFO
            ],
            "categories" => $inventoryCategories,
            "descriptionConfig" => $refArticleDataService->getDescriptionConfig($entityManager),
            "shippingSettingsDefaultValues" => $shippingSettingsDefaultValues,
        ]);
    }

    #[Route('/modifier-page/{reference}', name: 'reference_article_edit_page', options: ['expose' => true])]
    public function editTemplate(EntityManagerInterface $entityManager,
                                 RefArticleDataService  $refArticleDataService,
                                 ReferenceArticle       $reference): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $inventoryCategories = $inventoryCategoryRepository->findAll();

        $freeFieldsGroupedByTypes = [];

        foreach ($types as $type) {
            $freeFieldsGroupedByTypes[$type->getId()] = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
        }

        return $this->render("reference_article/form/edit.html.twig", [
            "reference" => $reference,
            "submit_route" => "reference_article_edit",
            "types" => $types,
            "stockManagement" => [
                ReferenceArticle::STOCK_MANAGEMENT_FEFO,
                ReferenceArticle::STOCK_MANAGEMENT_FIFO
            ],
            "categories" => $inventoryCategories,
            "freeFieldsGroupedByTypes" => $freeFieldsGroupedByTypes,
            "descriptionConfig" => $refArticleDataService->getDescriptionConfig($entityManager),
        ]);
    }

    #[Route("/api-check-stock", name: "reference_article_check_quantity", options: ["expose" => true], methods: ["GET"])]
    public function checkQuantity(Request                $request,
                                  EntityManagerInterface $entityManager): Response {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $scannedReference = $request->query->get('scannedReference');
        if(str_starts_with($scannedReference, 'ART')){
            $article = $articleRepository->findOneBy(['barCode' => $scannedReference]);
            $reference = $article->getArticleFournisseur()->getReferenceArticle();
        } else {
            $article = null;
            $reference = $referenceArticleRepository->findOneBy(['barCode' => $scannedReference])
                ?? $referenceArticleRepository->findOneBy(['reference' => $scannedReference]);
        }

        return $this->json([
            'referenceForErrorModal' => $reference ? $reference->getReference() : '',
            'codeArticle' => $article ? $article->getBarCode() : 'Non défini',
            'exists' => $reference !== null,
            'inStock' => $reference?->getQuantiteStock() > 0,
        ]);
    }

    #[Route("/validate-stock-entry", name: "entry_stock_validate", options: ["expose" => true], methods: ["GET|POST"])]
    public function validateEntryStock(Request                   $request,
                                       EntityManagerInterface    $entityManager,
                                       SettingsService           $settingsService,
                                       ArticleFournisseurService $articleFournisseurService,
                                       ArticleDataService        $articleDataService,
                                       RefArticleDataService     $refArticleDataService,
                                       UniqueNumberService       $uniqueNumberService,
                                       KioskService              $kioskService,
                                       FreeFieldService          $freeFieldService,
                                       NotificationService       $notificationService): JsonResponse {
        // get data from request
        $data = $request->query;

        $kioskRepository = $entityManager->getRepository(Kiosk::class);
        $token = $data->get('token');
        $kiosk = $kioskRepository->findOneBy(['token' => $token]);
        if (!$kiosk) {
            throw new FormException("La borne n'a pas été trouvée. Veuillez réessayer.");
        }

        // repositories
        $refArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);

        $type = $typeRepository->find($settingsService->getValue($entityManager, Setting::TYPE_REFERENCE_CREATE));
        $articleSuccessMessage = $settingsService->getValue($entityManager, Setting::VALIDATION_ARTICLE_ENTRY_MESSAGE);
        $referenceSuccessMessage = $settingsService->getValue($entityManager, Setting::VALIDATION_REFERENCE_ENTRY_MESSAGE);

        $applicant = $userRepository->find($data->get('applicant'));
        $follower = $userRepository->find($data->get('follower'));
        $reference = $refArticleRepository->findOneBy(['reference' => $data->get('reference')]);
        $referenceExist = $data->has('article') && $reference;

        if (!$reference) {
            $status = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, $settingsService->getValue($entityManager, Setting::STATUT_REFERENCE_CREATE));

            $reference = (new ReferenceArticle())
                ->setReference($data->get('reference'))
                ->setLibelle($data->get('label'))
                ->setCreatedBy($this->getUser())
                ->setCreatedAt(new DateTime())
                ->setBarCode($refArticleDataService->generateBarCode())
                ->setStatut($status)
                ->setType($type)
                ->setTypeQuantite(ReferenceArticle::QUANTITY_TYPE_ARTICLE);
        }

        $reference->setCommentaire($data->get('comment'));

        if($applicant){
            $reference->addManager($applicant);
        }

        if($follower){
            $reference->addManager($follower);
        }

        if($settingsService->getValue($entityManager, Setting::VISIBILITY_GROUP_REFERENCE_CREATE)){
            $visibilityGroup = $visibilityGroupRepository->find($settingsService->getValue($entityManager, Setting::VISIBILITY_GROUP_REFERENCE_CREATE));
            $reference->setProperties(['visibilityGroup' => $visibilityGroup]);
        }
        if($settingsService->getValue($entityManager, Setting::INVENTORIES_CATEGORY_REFERENCE_CREATE)){
            $inventoryCategory = $inventoryCategoryRepository->find($settingsService->getValue($entityManager, Setting::INVENTORIES_CATEGORY_REFERENCE_CREATE));
            $reference->setCategory($inventoryCategory);
        }

        $entityManager->persist($reference);
        if(!$referenceExist) {
            $provider = $supplierRepository->find($settingsService->getValue($entityManager, Setting::FOURNISSEUR_REFERENCE_CREATE));
            $supplierArticle = $articleFournisseurService->createArticleFournisseur([
                'fournisseur' => $provider,
                'article-reference' => $reference,
                'label' => $reference->getReference(),
                'reference' => $reference->getReference(),
                'visible' => $reference->getStatut()->getCode() !== ReferenceArticle::DRAFT_STATUS
            ], true);
            $entityManager->persist($supplierArticle);
            $reference->addArticleFournisseur($supplierArticle);
        }

        if(!empty($data->all('freeField'))) {
            $freeFieldService->manageFreeFields($reference, [
                $data->all('freeField')[0] => $data->all('freeField')[1]
            ], $entityManager);
        }
        $barcodesToPrint = [];
        try {
            $number = $uniqueNumberService->create($entityManager, Collecte::NUMBER_PREFIX, Collecte::class, UniqueNumberService::DATE_COUNTER_FORMAT_COLLECT);;
            $collecte = (new Collecte())
                ->setNumero($number)
                ->setDemandeur($this->getUser())
                ->setDate(new DateTime())
                ->setValidationDate(new DateTime())
                ->setType($kiosk->getPickingType())
                ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_A_TRAITER))
                ->setPointCollecte($kiosk->getPickingLocation())
                ->setObjet($kiosk->getSubject())
                ->setKiosk($kiosk)
                ->setstockOrDestruct($kiosk->getDestination() === 'destruction' ? Collecte::DESTRUCT_STATE : Collecte::STOCKPILLING_STATE);

            $newQuantity = $kiosk->getQuantityToPick();

            $collecteReference = new CollecteReference();
            $collecteReference
                ->setCollecte($collecte)
                ->setReferenceArticle($reference)
                ->setQuantite($newQuantity);
            $entityManager->persist($collecteReference);
            $collecte->addCollecteReference($collecteReference);
            $entityManager->persist($collecte);


            $ordreCollecte = new OrdreCollecte();
            $date = new DateTime('now');
            $ordreCollecte
                ->setDate($date)
                ->setNumero('C-' . $date->format('YmdHis'))
                ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_A_TRAITER))
                ->setDemandeCollecte($collecte);

            if (!$referenceExist) {
                $entityManager->flush();
                $article = $articleDataService->newArticle($entityManager, [
                    'statut' => Article::STATUT_INACTIF,
                    'refArticle' => $reference->getId(),
                    'emplacement' => $kiosk->getPickingLocation(),
                    'articleFournisseur' => $supplierArticle->getId(),
                    'libelle' => $reference->getLibelle(),
                    'quantite' => $newQuantity,
                ]);
                $article
                    ->setReference($reference->getReference())
                    ->setInactiveSince($date)
                    ->setCreatedOnKioskAt($date)
                    ->setKiosk($kiosk);
                $entityManager->persist($article);
            } else {
                $article = $entityManager->getRepository(Article::class)->findOneBy(['barCode' =>$data->get('article')]);
                $article
                    ->setQuantite($newQuantity)
                    ->setKiosk($kiosk)
                    ->setCreatedOnKioskAt($date);
            }

            $barcodesToPrint[] = [
                'text' => $kioskService->getTextForLabel($article),
                'barcode' => $article->getBarCode()
            ];
            $ordreCollecte->addArticle($article);
            $entityManager->persist($ordreCollecte);
        } catch(Exception $exception) {
            return new JsonResponse([
                'success' => false,
                'msg' => $exception->getMessage(),
            ]);
        }

        $entityManager->flush();

        if ($ordreCollecte->getDemandeCollecte()->getType()->isNotificationsEnabled()) {
            $notificationService->toTreat($ordreCollecte);
        }

        $to = Stream::from($reference->getManagers())
            ->map(fn(Utilisateur $manager) => $manager->getEmail())
            ->toArray();

        if($referenceExist) {
            $articleSuccessMessage = str_replace('@reference', $data->get('reference'), str_replace('@codearticle', '<span style="color: #3353D7;">'.$data->get('article').'</span>', $articleSuccessMessage));
            $message = strip_tags(str_replace('@reference', $data->get('reference'), str_replace('@codearticle', $data->get('article'), $articleSuccessMessage)));
        } else {
            $referenceSuccessMessage = str_replace('@reference', '<span style="color: #3353D7;">'.$data->get('reference').'</span>', $referenceSuccessMessage);
            $message = strip_tags(str_replace('@reference', $data->get('reference'), $referenceSuccessMessage));
        }
        $refArticleDataService->sendMailEntryStock($reference, $to, $message);
        return new JsonResponse([
                'success' => true,
                'msg' => "Validation d'entrée de stock",
                'barcodesToPrint' => json_encode($barcodesToPrint),
                "referenceExist" => $referenceExist,
                "successMessage" => $referenceExist ? $articleSuccessMessage : $referenceSuccessMessage,
            ]
        );
    }

    #[Route("/get-stock-forecast/{referenceArticle}", name: "reference_article_get_stock_forecast", options: ["expose" => true], methods: ["GET"])]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_REFE], mode: HasPermission::IN_JSON)]
    public function getStockForecast(Request $request, HttpClientInterface $client, string $referenceArticle, EntityManagerInterface $entityManager): JsonResponse {
        $settingRepository = $entityManager->getRepository(Setting::class);

        $apiURL = $_SERVER['STOCK_FORECAST_URL'];

        if(!$apiURL) {
            throw new FormException("La configuration de l'instance permettant la prévision de stock est invalide");
        }

        $formData = new FormDataPart([
            'Content-Type'=> 'application/json',
        ]);

        $headers = $formData->getPreparedHeaders()->toArray();
        try {
            $apiRequest = $client->request('POST', $apiURL, [
                "headers" => $headers,
                "body" => json_encode([
                    "reference" => $referenceArticle,
                ]),
            ]);

            $apiOutput = $apiRequest->getContent();
        } catch (\Throwable $e) {
            throw new FormException( $e->getMessage() ?: "Une erreur s'est produite lors de la prévision de stock");
        }

        $apiOutput = json_decode($apiOutput, true);

        return new JsonResponse([
            "success" => true,
            "html" => $apiOutput["html"] ?? "",
        ]);
    }
}
