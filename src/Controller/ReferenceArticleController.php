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
use App\Entity\FreeField;
use App\Entity\Inventory\InventoryCategory;
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
use App\Service\SpecificService;
use App\Service\TranslationService;
use App\Service\UserService;
use App\Service\VisibleColumnService;
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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;


/**
 * @Route("/reference-article")
 */
class ReferenceArticleController extends AbstractController
{

    #[Required]
    public RefArticleDataService $refArticleDataService;

    #[Required]
    public ArticleDataService $articleDataService;

    #[Required]
    public UserService $userService;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public SpecificService $specificService;

    /**
     * @Route("/api-columns", name="ref_article_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON)
     */
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

    /**
     * @Route("/api", name="ref_article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request): Response {
        return $this->json($this->refArticleDataService->getRefArticleDataByParams($request->request));
    }

    /**
     * @Route("/creer", name="reference_article_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function new(Request $request,
                        UserService $userService,
                        FreeFieldService $champLibreService,
                        EntityManagerInterface $entityManager,
                        MouvementStockService $mouvementStockService,
                        RefArticleDataService $refArticleDataService,
                        ArticleFournisseurService $articleFournisseurService,
                        AttachmentService $attachmentService): Response
    {
        if (!$userService->hasRightFunction(Menu::STOCK, Action::CREATE)
            && !$userService->hasRightFunction(Menu::STOCK, Action::CREATE_DRAFT_REFERENCE)) {
            throw new FormException("Accès refusé");
        }

        if (($data = $request->request->all()) || ($data = json_decode($request->getContent(), true))) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

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
				->setBarCode($this->refArticleDataService->generateBarCode())
                ->setBuyer(isset($data['buyer']) ? $userRepository->find($data['buyer']) : null)
                ->setCreatedBy($loggedUser)
                ->setCreatedAt(new DateTime('now'))
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

            if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE &&
                $refArticle->getQuantiteStock() > 0 &&
                $refArticle->getStatut()->getCode() !== ReferenceArticle::DRAFT_STATUS) {
                $mvtStock = $mouvementStockService->createMouvementStock(
                    $loggedUser,
                    null,
                    $refArticle->getQuantiteStock(),
                    $refArticle,
                    MouvementStock::TYPE_ENTREE
                );
                $mouvementStockService->finishMouvementStock(
                    $mvtStock,
                    new DateTime('now'),
                    $emplacement
                );
                $entityManager->persist($mvtStock);
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
                $attachments = $attachmentService->createAttachments([$files->get('image')]);
                $entityManager->persist($attachments[0]);

                $refArticle->setImage($attachments[0]);
                $request->files->remove('image');
            }

            if($files->has('fileSheet')) {
                $attachments = $attachmentService->createAttachments([$files->get('fileSheet')]);
                $entityManager->persist($attachments[0]);

                $refArticle->setSheet($attachments[0]);
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

    /**
     * @Route("/", name="reference_article_index",  methods="GET|POST", options={"expose"=true})
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE})
     */
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
            'columnsVisibles' => $currentUser->getVisibleColumns()['reference'],
            'defaultLocation' => $settingsService->getParamLocation(Setting::DEFAULT_LOCATION_REFERENCE),
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
                    $refArticle->removeIfNotIn($data->all()['files'] ?? []);
                    $response = $this->refArticleDataService->editRefArticle($entityManager, $refArticle, $data, $currentUser, $request->files);
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

    /**
     * @Route("/supprimer", name="reference_article_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request                  $request,
                           EntityManagerInterface   $entityManager,
                           TranslationService       $translation): Response
    {
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

    /**
     * @Route("/addFournisseur", name="ajax_render_add_fournisseur", options={"expose"=true}, methods="GET", requirements={"currentIndex": "\d+"})
     * @HasPermission({Menu::STOCK, Action::EDIT})
     */
    public function addFournisseur(Request $request): Response
    {
        $currentIndex = $request->query->get('currentIndex');
        $currentIndexInt = $request->query->getInt('currentIndex');

        $json = $this->renderView('reference_article/fournisseurArticle.html.twig', [
            'multipleObjectIndex' => !empty($currentIndex) || $currentIndexInt === 0 ? ($currentIndexInt + 1) : 0
        ]);
        return new JsonResponse($json);
    }

    /**
     * @Route("/quantite", name="get_quantity_ref_article", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON))
     */
    public function getQuantityByRefArticleId(Request $request, EntityManagerInterface $entityManager)
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $settings = $entityManager->getRepository(Setting::class);
        $needsQuantitiesCheck = !$settings->getOneParamByLabel(Setting::MANAGE_PREPARATIONS_WITH_PLANNING);
        $quantity = false;

        $refArticleId = $request->request->get('refArticleId');
        $refArticle = $referenceArticleRepository->find($refArticleId);

        if ($refArticle && $needsQuantitiesCheck) {
            if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                $quantity = $refArticle->getQuantiteDisponible();
            }
        }

        return new JsonResponse($quantity);
    }

    /**
     * @Route("/autocomplete-ref", name="get_ref_articles", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getRefArticles(Request $request,
                                   EntityManagerInterface $entityManager)
    {
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

    /**
     * @Route("/{reference}/data", name="get_reference_data", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function getReferenceData(ReferenceArticle $reference, EntityManagerInterface $entityManager)
    {
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

    /**
     * @Route("/colonne-visible", name="save_column_visible", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request $request,
                                      EntityManagerInterface $manager,
                                      VisibleColumnService $visibleColumnService): Response
    {
            $data = json_decode($request->getContent(), true);
            $fields = array_keys($data);
            /** @var $user Utilisateur */
            $user  = $this->getUser();

            $visibleColumnService->setVisibleColumns('reference', $fields, $user);

            $manager->flush();

            return $this->json([
                'success' => true,
                'msg' => 'Vos préférences de colonnes à afficher ont bien été sauvegardées'
            ]);
    }

    /**
     * @Route("/voir", name="reference_article_show", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON))
     */
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

    /**
     * @Route("/quantites/{id}/{period}", name="reference_article_quantity_variations", options={"expose"=true}, methods="GET")
     */
    public function quantities(ReferenceArticle $referenceArticle,
                               int $period,
                               RefArticleDataService $refArticleDataService,
                               EntityManagerInterface $manager): Response {
        return $this->json([
            'data' => $refArticleDataService->getQuantityPredictions($manager, $referenceArticle, $period)
        ]);

    }

    /**
     * @Route("/voir/{id}", name="reference_article_show_page", options={"expose"=true})
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE})
     */
    public function showPage(Request                $request,
                             ReferenceArticle       $referenceArticle,
                             RefArticleDataService  $refArticleDataService,
                             EntityManagerInterface $entityManager): Response {
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
            'descriptionConfig' => $refArticleDataService->getDescriptionConfig($entityManager)
        ]);
    }

    /**
     * @Route("/type-quantite", name="get_quantity_type", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getQuantityType(Request $request, EntityManagerInterface $entityManager)
	{
		if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $reference = $referenceArticleRepository->find($data['id']);

			$quantityType = $reference ? $reference->getTypeQuantite() : '';

			return new JsonResponse($quantityType);
		}
		throw new BadRequestHttpException();
	}

    /**
     * @Route("/etiquettes", name="reference_article_bar_codes_print", options={"expose"=true})
     */
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
        $queryResult = $referenceArticleRepository->findByFiltersAndParams($filters, $request->query, $user);
        $refs = $queryResult['data'];
        $refs = array_map(function($refArticle) {
            return is_array($refArticle) ? $refArticle[0] : $refArticle;
        }, $refs);
        $barcodeConfigs = array_map(
            function (ReferenceArticle $reference) use ($refArticleDataService) {
                return $refArticleDataService->getBarcodeConfig($reference);
            },
            $refs
        );

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

    /**
     * @Route("/{reference}/etiquette", name="reference_article_single_bar_code_print", options={"expose"=true})
     */
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

    /**
     * @Route("/mouvements/api/{referenceArticle}", name="ref_mouvements_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiMouvements(EntityManagerInterface $entityManager,
                                  MouvementStockService $mouvementStockService,
                                  ReferenceArticle $referenceArticle): Response
    {
        $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $mouvements = $mouvementStockRepository->findByRef($referenceArticle);

        $data['data'] = array_map(
            function(MouvementStock $mouvement) use ($mouvementStockService) {
                $fromColumnConfig = $mouvementStockService->getFromColumnConfig($mouvement);
                $from = $fromColumnConfig['from'];
                $fromPath = $fromColumnConfig['path'];
                $fromPathParams = $fromColumnConfig['pathParams'];

                return [
                    'Date' => $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : 'aucune',
                    'Quantity' => $mouvement->getQuantity(),
                    'Origin' => $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : 'aucun',
                    'Destination' => $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : 'aucun',
                    'Type' => $mouvement->getType(),
                    'Operator' => $mouvement->getUser() ? $mouvement->getUser()->getUsername() : 'aucun',
                    'from' => $this->templating->render('mouvement_stock/datatableMvtStockRowFrom.html.twig', [
                        'from' => $from,
                        'mvt' => $mouvement,
                        'path' => $fromPath,
                        'pathParams' => $fromPathParams
                    ]),
                    'ArticleCode' => $mouvement->getArticle() ? $mouvement->getArticle()->getBarCode() : $mouvement->getRefArticle()->getBarCode()
                ];
            },
            $mouvements
        );
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

    /**
     * @Route("/{referenceArticle}/quantity", name="update_qte_refarticle", options={"expose"=true}, methods="PATCH", condition="request.isXmlHttpRequest()")
     */
    public function updateQuantity(EntityManagerInterface $entityManager,
                                   ReferenceArticle $referenceArticle,
                                   RefArticleDataService $refArticleDataService) {

        $refArticleDataService->updateRefArticleQuantities($entityManager, $referenceArticle, true);
        $entityManager->flush();
        $refArticleDataService->treatAlert($entityManager, $referenceArticle);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/nouveau-page", name="reference_article_new_page", options={"expose"=true})
     */
    public function newTemplate(Request                $request,
                                EntityManagerInterface $entityManager,
                                RefArticleDataService  $refArticleDataService,
                                SettingsService        $settingsService) {
        $typeRepository = $entityManager->getRepository(Type::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $inventoryCategories = $inventoryCategoryRepository->findAll();

        $typeChampLibre = [];
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

        $shippingSettingsDefaultValues = [];
        if($request->query->has('shipping')){
            $shippingSettingsDefaultValues = [
                'type' => $settingRepository->getOneParamByLabel(Setting::SHIPPING_REFERENCE_DEFAULT_TYPE),
                'supplier' => $settingRepository->getOneParamByLabel(Setting::SHIPPING_SUPPLIER_LABEL_REFERENCE_CREATE) ? $supplierRepository->find($settingRepository->getOneParamByLabel(Setting::SHIPPING_SUPPLIER_LABEL_REFERENCE_CREATE)) : null,
                'supplierCode' => $settingRepository->getOneParamByLabel(Setting::SHIPPING_SUPPLIER_REFERENCE_CREATE) ? $supplierRepository->find($settingRepository->getOneParamByLabel(Setting::SHIPPING_SUPPLIER_REFERENCE_CREATE)) : null,
                'refArticleSupplierEqualsReference' => boolval($settingRepository->getOneParamByLabel(Setting::SHIPPING_REF_ARTICLE_SUPPLIER_EQUALS_REFERENCE)),
                'articleSupplierLabelEqualsReferenceLabel' => boolval($settingRepository->getOneParamByLabel(Setting::SHIPPING_ARTICLE_SUPPLIER_LABEL_EQUALS_REFERENCE_LABEL)),
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
            'defaultLocation' => $settingsService->getParamLocation(Setting::DEFAULT_LOCATION_REFERENCE),
            'draftDefaultReference' => $refArticleDataService->getDraftDefaultReference($entityManager),
            "stockManagement" => [
                ReferenceArticle::STOCK_MANAGEMENT_FEFO,
                ReferenceArticle::STOCK_MANAGEMENT_FIFO
            ],
            "categories" => $inventoryCategories,
            "freeFieldTypes" => $typeChampLibre,
            "freeFieldsGroupedByTypes" => $freeFieldsGroupedByTypes,
            "descriptionConfig" => $refArticleDataService->getDescriptionConfig($entityManager),
            "shippingSettingsDefaultValues" => $shippingSettingsDefaultValues,
        ]);
    }

    /**
     * @Route("/modifier-page/{reference}", name="reference_article_edit_page", options={"expose"=true})
     */
    public function editTemplate(EntityManagerInterface $entityManager,
                                 RefArticleDataService  $refArticleDataService,
                                 ReferenceArticle       $reference) {
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
    public function validateEntryStock(Request $request,
                                  EntityManagerInterface $entityManager,
                                  ArticleFournisseurService $articleFournisseurService,
                                  ArticleDataService $articleDataService,
                                  RefArticleDataService $refArticleDataService,
                                  KioskService $kioskService,
                                  FreeFieldService $freeFieldService,
                                  NotificationService $notificationService): Response {
        $refArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $data = $request->query->all();

        $type = $typeRepository->find($settingRepository->getOneParamByLabel(Setting::TYPE_REFERENCE_CREATE));
        $status = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, $settingRepository->getOneParamByLabel(Setting::STATUT_REFERENCE_CREATE));
        $applicant = $userRepository->find($data['applicant']);
        $follower = $userRepository->find($data['follower']);
        $articleSuccessMessage = $settingRepository->getOneParamByLabel(Setting::VALIDATION_ARTICLE_ENTRY_MESSAGE);
        $referenceSuccessMessage = $settingRepository->getOneParamByLabel(Setting::VALIDATION_REFERENCE_ENTRY_MESSAGE);

        $reference = $refArticleRepository->findOneBy(['reference' => $data['reference']]) ?? new ReferenceArticle();
        $referenceExist = isset($data['article']);
        $reference
            ->setReference($data['reference'])
            ->setLibelle($data['label'])
            ->setStatut($status)
            ->setCommentaire($data['comment'])
            ->setCreatedBy($userRepository->getKioskUser())
            ->setCreatedAt(new DateTime());

        if(!$referenceExist){
            $reference
                ->setType($type)
                ->setTypeQuantite(ReferenceArticle::QUANTITY_TYPE_ARTICLE);
        }

        foreach ([$applicant, $follower] as $user) {
            $reference->addManager($user);
        }
        if(!$referenceExist){
            $reference->setBarCode($refArticleDataService->generateBarCode());
        }

        if($settingRepository->getOneParamByLabel(Setting::VISIBILITY_GROUP_REFERENCE_CREATE)){
            $visibilityGroup = $visibilityGroupRepository->find($settingRepository->getOneParamByLabel(Setting::VISIBILITY_GROUP_REFERENCE_CREATE));
            $reference->setProperties(['visibilityGroup' => $visibilityGroup]);
        }
        if($settingRepository->getOneParamByLabel(Setting::INVENTORIES_CATEGORY_REFERENCE_CREATE)){
            $inventoryCategory = $inventoryCategoryRepository->find($settingRepository->getOneParamByLabel(Setting::INVENTORIES_CATEGORY_REFERENCE_CREATE));
            $reference->setCategory($inventoryCategory);
        }

        $entityManager->persist($reference);
        if(!$referenceExist) {
            $provider = $entityManager->getRepository(Fournisseur::class)->find($settingRepository->getOneParamByLabel(Setting::FOURNISSEUR_REFERENCE_CREATE));
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

        if(!empty($data['freeField'])){
            $freeFieldService->manageFreeFields($reference, [
                $data['freeField'][0] => $data['freeField'][1]
            ], $entityManager);
        }
        $barcodesToPrint = [];
        try {
            $number = 'C-' . (new DateTime('now'))->format('YmdHis');
            $collecte = new Collecte();
            $collecte
                ->setNumero($number)
                ->setDemandeur($userRepository->getKioskUser())
                ->setDate(new DateTime())
                ->setValidationDate(new DateTime())
                ->setType($typeRepository->find($settingRepository->getOneParamByLabel(Setting::COLLECT_REQUEST_TYPE)))
                ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_A_TRAITER))
                ->setPointCollecte($emplacementRepository->find($settingRepository->getOneParamByLabel(Setting::COLLECT_REQUEST_POINT_COLLECT)))
                ->setObjet($settingRepository->getOneParamByLabel(Setting::COLLECT_REQUEST_OBJECT))
                ->setstockOrDestruct($settingRepository->getOneParamByLabel(Setting::COLLECT_REQUEST_DESTINATION));

            $collecteReference = new CollecteReference();
            $collecteReference
                ->setCollecte($collecte)
                ->setReferenceArticle($reference)
                ->setQuantite($settingRepository->getOneParamByLabel(Setting::COLLECT_REQUEST_ARTICLE_QUANTITY_TO_COLLECT) ?? 1);
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

            if(!$referenceExist){
                $entityManager->flush();
                $article = $articleDataService->newArticle($entityManager, [
                    'statut' => Article::STATUT_INACTIF,
                    'refArticle' => $reference->getId(),
                    'emplacement' => $settingRepository->getOneParamByLabel(Setting::COLLECT_REQUEST_POINT_COLLECT),
                    'articleFournisseur' => $supplierArticle->getId(),
                    'libelle' => $reference->getLibelle(),
                    'quantite' => 1,
                ]);
                $article
                    ->setReference($reference->getReference())
                    ->setInactiveSince($date)
                    ->setCreatedOnKioskAt($date);
                $entityManager->persist($article);
            } else {
                $article = $entityManager->getRepository(Article::class)->findOneBy(['barCode' => $data['article']]);
                $article->setQuantite(1)->setCreatedOnKioskAt($date);
            }

            $barcodesToPrint[] = [
                'text' => $kioskService->getTextForLabel($article, $entityManager),
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
            $articleSuccessMessage = str_replace('@reference', $data['reference'], str_replace('@codearticle', '<span style="color: #3353D7;">'.$data['article'].'</span>', $articleSuccessMessage));
            $message = strip_tags(str_replace('@reference', $data['reference'], str_replace('@codearticle', $data['article'], $articleSuccessMessage)));
        } else {
            $referenceSuccessMessage = str_replace('@reference', '<span style="color: #3353D7;">'.$data['reference'].'</span>', $referenceSuccessMessage);
            $message = strip_tags(str_replace('@reference', $data['reference'], $referenceSuccessMessage));
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
}
