<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreRef;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Language;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Project;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use App\Repository\PurchaseRequestLineRepository;
use App\Repository\ReceptionReferenceArticleRepository;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class RefArticleDataService
{

    private const REF_ARTICLE_FIELDS = [
        ["name" => "actions", "class" => "noVis", "alwaysVisible" => true, "orderable" => false],
        ["title" => "Image", "name" => "image", "type" => "image", "orderable" => false],
        ["title" => "Libellé", "name" => "label", "type" => "text", "searchable" => true],
        ["title" => "Référence", "name" => "reference", "type" => "text", "searchable" => true],
        ["title" => "Code barre", "name" => "barCode", "type" => "text", "searchable" => true],
        ["title" => "Urgence", "name" => "emergency", "type" => "booleen"],
        ["title" => "Type", "name" => "type", "type" => "list"],
        ["title" => "Statut", "name" => "status", "type" => "list"],
        ["title" => "Quantité stock", "name" => "stockQuantity", "type" => "number"],
        ["title" => "Quantité disponible", "name" => "availableQuantity", "type" => "number"],
        ["title" => "Acheteur", "name" => "buyer", "type" => "text", "searchable" => true],
        ["title" => "Emplacement", "name" => "location", "type" => "list"],
        ["title" => "Seuil de sécurité", "name" => "securityThreshold", "type" => "number"],
        ["title" => "Seuil d'alerte", "name" => "warningThreshold", "type" => "number"],
        ["title" => "Prix unitaire", "name" => "unitPrice", "type" => "number"],
        ["title" => "Synchronisation nomade", "name" => "mobileSync", "type" => "booleen"],
        ["title" => "Nom fournisseur", "name" => "supplierLabel", "type" => "text", "searchable" => true, "orderable" => false],
        ["title" => "Code fournisseur", "name" => "supplierCode", "type" => "text", "searchable" => true, "orderable" => false],
        ["title" => "Référence article fournisseur", "name" => "referenceSupplierArticle", "type" => "text", "searchable" => true, "hiddenColumn" => true],
        ["title" => "Dernier inventaire", "name" => "lastInventory", "searchable" => true, "type" => "date"],
        ["title" => "Gestion de stock", "name" => "stockManagement", "type" => "text", "searchable" => true],
        ["title" => "Gestionnaire(s)", "name" => "managers", "orderable" => false, "type" => "text", "searchable" => true],
        ["title" => "Commentaire", "name" => "comment", "type" => "text", "orderable" => false],
        ["title" => "Commentaire d'urgence", "name" => "emergencyComment", "type" => "text", "orderable" => false],
        ["title" => "Créée le", "name" => "createdAt", "type" => "date"],
        ["title" => "Créée par", "name" => "createdBy", "type" => "text"],
        ["title" => "Dernière modification le", "name" => "editedAt", "type" => "date"],
        ["title" => "Dernière modification par", "name" => "editedBy", "type" => "text"],
        ["title" => "Dernière entrée le", "name" => "lastStockEntry", "type" => "date"],
        ["title" => "Dernière sortie le", "name" => "lastStockExit", "type" => "date"],
        ["title" => "Inventaire à jour", "name" => "upToDateInventory", "type" => "booleen"],
        ["title" => FiltreRef::FIXED_FIELD_VISIBILITY_GROUP, "name" => "visibilityGroups", "type" => "list multiple", "orderable" => true],
    ];

    private $filtreRefRepository;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public UserService $userService;

    #[Required]
    public CSVExportService $CSVExportService;

    /**
     * @var object|string
     */
    private $user;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public ArticleFournisseurService $articleFournisseurService;

    #[Required]
    public AlertService $alertService;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public AttachmentService $attachmentService;

    #[Required]
    public MouvementStockService $mouvementStockService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public DeliveryRequestService $demandeLivraisonService;

    private ?array $freeFieldsConfig = null;

    public function __construct(TokenStorageInterface  $tokenStorage,
                                EntityManagerInterface $entityManager)
    {
        $this->user = $tokenStorage->getToken() ? $tokenStorage->getToken()->getUser() : null;
        $this->filtreRefRepository = $entityManager->getRepository(FiltreRef::class);
    }

    public function getRefArticleDataByParams(InputBag $params = null)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        /**
         * @var Utilisateur $currentUser
         */
        $currentUser = $this->user;
        $currentUserSearches = $currentUser->getSearches();
        $currentUserIndexes = $currentUser->getPageIndexes();
        if ($params->has('search')) {
            $currentUserSearches['reference'] = $params->all('search');
            $currentUser->setSearches($currentUserSearches);
            $this->entityManager->flush();
        } else {
            $currentUser->setSearches(null);
        }

        if ($params->has('start') && $params->has('length')) {
            $currentUserIndexes['reference'] =
                intval(intval($params->get('start')) / intval($params->get('length')))
                * intval($params->get('length'));
            $currentUser->setPageIndexes($currentUserIndexes);
            $this->entityManager->flush();
        }
        $userId = $currentUser->getId();
        $filters = $this->filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $referenceArticleRepository->findByFiltersAndParams($filters, $params, $currentUser);
        $refs = $queryResult['data'];
        $rows = [];
        foreach ($refs as $refArticle) {
            $rows[] = $this->dataRowRefArticle(is_array($refArticle) ? $refArticle[0] : $refArticle);
        }
        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $referenceArticleRepository->countAll()
        ];
    }

    public function getDataEditForRefArticle($articleRef, $articleRepository)
    {
        $totalQuantity = $articleRef->getQuantiteDisponible();
        return $data = [
            'listArticlesFournisseur' => array_reduce($articleRef->getArticlesFournisseur()->toArray(),
                function (array $carry, ArticleFournisseur $articleFournisseur) use ($articleRef, $articleRepository) {
                    $carry[] = [
                        'reference' => $articleFournisseur->getReference(),
                        'label' => $articleFournisseur->getLabel(),
                        'fournisseurCode' => $articleFournisseur->getFournisseur()->getCodeReference(),
                        'quantity' => $articleRepository->getQuantityForSupplier($articleFournisseur)
                    ];
                    return $carry;
                }, []),
            'totalQuantity' => $totalQuantity,
        ];
    }

    public function getViewEditRefArticle($refArticle,
                                          $isADemand = false,
                                          $preloadCategories = true,
                                          $showAttachments = false)
    {
        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $typeRepository = $this->entityManager->getRepository(Type::class);
        $inventoryCategoryRepository = $this->entityManager->getRepository(InventoryCategory::class);
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);
        $data = $this->getDataEditForRefArticle($refArticle, $articleRepository);
        $articlesFournisseur = $articleFournisseurRepository->findByRefArticle($refArticle->getId());
        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $editAttachments = $this->userService->hasRightFunction(Menu::STOCK, Action::EDIT);

        $categories = $preloadCategories
            ? $inventoryCategoryRepository->findBy([], ['label' => 'ASC'])
            : [];

        $freeFieldsGroupedByTypes = [];
        foreach ($types as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
            $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
        }
        $typeChampLibre = [];
        foreach ($types as $type) {
            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId()
            ];
        }
        return $this->templating->render('reference_article/modalRefArticleContent.html.twig', [
            'articleRef' => $refArticle,
            'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
            'Synchronisation nomade' => $refArticle->getNeedsMobileSync(),
            'statut' => $refArticle->getStatut()->getNom(),
            'typeChampsLibres' => $typeChampLibre,
            'articlesFournisseur' => $data['listArticlesFournisseur'],
            'totalQuantity' => $data['totalQuantity'],
            'articles' => $articlesFournisseur,
            'categories' => $categories,
            'isADemand' => $isADemand,
            'stockManagement' => [
                ReferenceArticle::STOCK_MANAGEMENT_FEFO,
                ReferenceArticle::STOCK_MANAGEMENT_FIFO
            ],
            'managers' => $refArticle->getManagers()
                ->map(function (Utilisateur $manager) {
                    $managerId = $manager->getId();
                    $managerUsername = $manager->getUsername();
                    return [
                        'managerId' => $managerId,
                        'managerUsername' => $managerUsername
                    ];
                }),
            'editAttachments' => $editAttachments,
            'showAttachments' => $showAttachments
        ]);
    }

    public function editRefArticle(EntityManagerInterface $entityManager,
                                   ReferenceArticle       $refArticle,
                                   ParameterBag           $data,
                                   Utilisateur            $user,
                                   ?FileBag               $fileBag = null) {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
        $supplierArticleRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $storageRuleRepository = $entityManager->getRepository(StorageRule::class);

        //modification champsFixes
        $supplierToRemove = $data->get('suppliers-to-remove');
        if (!empty($supplierToRemove)) {
            $suppliers = $supplierArticleRepository->findBy(['id' => explode(',', $supplierToRemove)]);
            foreach ($suppliers as $supplier) {
                $refArticle->removeArticleFournisseur($supplier);
            }
        }

        $isDangerousGood = $data->get('security') === "1";
        $fileSheetSubmitted = $fileBag->has('fileSheet') && !($data->get('fileSheet') === 'undefined');
        $fileSheetPreviouslySaved = $data->has('savedSheetFile');
        $fileSheetDeleted = $data->get('deletedSheetFile') === "1";

        if ($isDangerousGood && (!$fileSheetSubmitted && (!$fileSheetPreviouslySaved || $fileSheetDeleted))) {
            throw new FormException("La fiche sécurité est obligatoire pour les Marchandises dangereuses.");
        }
        $storageRuleToRemove = $data->get('storage-rules-to-remove');
        if (!empty($storageRuleToRemove)) {
            $storageRules = $storageRuleRepository->findBy(['id' => explode(',', $storageRuleToRemove)]);
            foreach ($storageRules as $storageRule) {
                $refArticle->removeStorageRule($storageRule);
            }
        }

        $wasDraft = $refArticle->getStatut()->getCode() === ReferenceArticle::DRAFT_STATUS;

        $sendMail = false;
        $statutId = $data->get('statut');
        $statut = $statutId ? $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, $statutId) : null;
        if ($statut) {
            $sendMail = $statut->getCode() !== ReferenceArticle::DRAFT_STATUS &&
                $refArticle->getStatut()->getCode() === ReferenceArticle::DRAFT_STATUS;
            $refArticle->setStatut($statut);
        }

        $this->updateDescriptionField($entityManager, $refArticle, $data->all());

        $isVisible = $refArticle->getStatut()->getCode() !== ReferenceArticle::DRAFT_STATUS;
        $supplierReferenceLines = json_decode($data->get('frl'), true) ?: [];
        foreach ($supplierReferenceLines as $supplierReferenceLine) {
            $referenceArticleFournisseur = $supplierReferenceLine['referenceFournisseur'];
            $existingSupplierArticle = $supplierArticleRepository->findOneBy([
                'reference' => $referenceArticleFournisseur
            ]);

            if (!isset($existingSupplierArticle)) {
                try {
                    $supplierArticle = $this->articleFournisseurService->createArticleFournisseur([
                        'fournisseur' => $supplierReferenceLine['fournisseur'],
                        'article-reference' => $refArticle,
                        'label' => $supplierReferenceLine['labelFournisseur'],
                        'reference' => $referenceArticleFournisseur,
                        'visible' => $isVisible
                    ]);

                    $entityManager->persist($supplierArticle);
                } catch (Exception $exception) {
                    if ($exception->getMessage() === ArticleFournisseurService::ERROR_REFERENCE_ALREADY_EXISTS) {
                        throw new FormException("La référence <strong>$referenceArticleFournisseur</strong> existe déjà pour un article fournisseur.");
                    }
                }
            } else if ($existingSupplierArticle->getReferenceArticle()) {
                $supplierArticleName = $existingSupplierArticle->getReference();
                $referenceName = $existingSupplierArticle->getReferenceArticle()->getReference();
                throw new FormException(
                    "L'article fournisseur <strong>$supplierArticleName</strong> est déjà lié à la référence <strong>$referenceName</strong>, vous ne pouvez pas l'ajouter."
                );
            } else {
                $existingSupplierArticle
                    ->setVisible($isVisible)
                    ->setReferenceArticle($refArticle);
            }
        }

        $storageRuleLines = json_decode($data->get('srl'), true) ?: [];
        foreach ($storageRuleLines as $storageRuleLine) {
            $storageRuleLocationId = $storageRuleLine['storageRuleLocation'] ?? null;
            $storageRuleSecurityQuantity = $storageRuleLine['storageRuleSecurityQuantity'] ?? null;
            $storageRuleConditioningQuantity = $storageRuleLine['storageRuleConditioningQuantity'] ?? null;
            if ($storageRuleLocationId && $storageRuleSecurityQuantity && $storageRuleConditioningQuantity) {
                $storageRuleLocation = $locationRepository->find($storageRuleLocationId);
                if (!$storageRuleLocation) {
                    throw new FormException("Une règle de stockage n'a pas pu être créée car l'emplacement n'a pas été trouvé.");
                }
                $storageRule = new StorageRule();
                $storageRule
                    ->setLocation($storageRuleLocation)
                    ->setSecurityQuantity($storageRuleSecurityQuantity)
                    ->setConditioningQuantity($storageRuleConditioningQuantity)
                    ->setReferenceArticle($refArticle);
                $entityManager->persist($storageRule);
            } else {
                throw new FormException("Une règle de stockage n'a pas pu être créée car un des champs requis n'a pas été renseigné.");
            }
        }
        foreach ($refArticle->getArticlesFournisseur() as $article) {
            $article->setVisible($isVisible);
        }

        $managersIds = $data->get('managers');
        $refArticle->getManagers()->clear();
        if (!empty($managersIds)) {
            $managers = is_string($managersIds)
                ? explode(',', $managersIds)
                : $managersIds;
            foreach ($managers as $manager) {
                $refArticle->addManager($userRepository->find($manager));
            }
        }

        $typeId = $data->getInt('type');
        $type = $typeId ? $typeRepository->find($typeId) : null;
        if ($type) {
            $refArticle->setType($type);
        }

        if($data->has('libelle')){
            $refArticle->setLibelle($data->get('libelle'));
        }

        $categoryId = $data->getInt('categorie');
        $category = $categoryId ? $inventoryCategoryRepository->find($categoryId) : null;

        $buyerId = $data->getInt('buyer');
        $buyer = $buyerId ? $userRepository->find($buyerId) : null;

        $visibilityGroupId = $data->getInt('visibility-group');
        $visibilityGroup = $visibilityGroupId ? $visibilityGroupRepository->find($visibilityGroupId) : null;

        $isUrgent = $data->getBoolean('urgence');

        $mobileSync= $data->getBoolean('mobileSync');
        if ($mobileSync) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $syncCount = $referenceArticleRepository->count(['needsMobileSync' => true]);
            if (!$refArticle->getNeedsMobileSync() && ($syncCount > ReferenceArticle::MAX_NOMADE_SYNC)) {
                return [
                    'success' => false,
                    'msg' => "Le nombre maximum de synchronisations a été atteint."
                ];
            }
        }

        $refArticle
            ->setCategory($category)
            ->setReference($data->get('reference'))
            ->setIsUrgent($isUrgent)
            ->setUserThatTriggeredEmergency($isUrgent ? $user : null)
            ->setEmergencyComment($isUrgent ? $data->get('emergencyComment') : '')
            ->setEmergencyQuantity($isUrgent ? ($data->getInt('emergencyQuantity') >= 0) ? $data->getInt('emergencyQuantity') : null : null)
            ->setPrixUnitaire(max(0, $data->get('prix')))
            ->setCommentaire($data->get('commentaire'))
            ->setNeedsMobileSync($mobileSync)
            ->setBuyer($buyer)
            ->setLimitWarning(($data->getInt('limitWarning') >= 0) ? $data->getInt('limitWarning') : null)
            ->setLimitSecurity(($data->getInt('limitSecurity') >= 0) ? $data->getInt('limitSecurity') : null)
            ->setStockManagement($data->get('stockManagement'))
            ->setNdpCode($data->get('ndpCode'))
            ->setDangerousGoods($data->getBoolean('security'))
            ->setOnuCode($data->get('onuCode'))
            ->setProductClass($data->get('productClass'))
            ->setProperties(['visibilityGroup' => $visibilityGroup]);

        if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE &&
            $refArticle->getQuantiteStock() > 0 &&
            $wasDraft && $refArticle->getStatut()->getCode() === ReferenceArticle::STATUT_ACTIF) {
            $mvtStock = $this->mouvementStockService->createMouvementStock(
                $user,
                null,
                $refArticle->getQuantiteStock(),
                $refArticle,
                MouvementStock::TYPE_ENTREE
            );
            $this->mouvementStockService->finishMouvementStock(
                $mvtStock,
                new DateTime('now'),
                $refArticle->getEmplacement()
            );
            $entityManager->persist($mvtStock);
        }

        $refArticle
            ->setEditedBy($user)
            ->setEditedAt(new DateTime('now'));

        $entityManager->persist($refArticle);
        //modification ou création des champsLibres
        $this->freeFieldService->manageFreeFields($refArticle, $data->all(), $entityManager);
        if ($fileBag) {
            if ($fileBag->has('image')) {
                $file = $fileBag->get('image');
                $attachments = $this->attachmentService->createAttachements([$file]);
                $entityManager->persist($attachments[0]);

                $refArticle->setImage($attachments[0]);
                $fileBag->remove('image');
            } elseif ($data->getBoolean('deletedImage')) {
                $image = $refArticle->getImage();
                if ($image) {
                    $this->attachmentService->deleteAttachment($image);
                    $refArticle->setImage(null);
                    $entityManager->remove($image);
                }
            }

            if ($fileBag->has('fileSheet')) {
                $file = $fileBag->get('fileSheet');
                $attachments = $this->attachmentService->createAttachements([$file]);
                $entityManager->persist($attachments[0]);

                $refArticle->setSheet($attachments[0]);
                $refArticle->setSheet($attachments[0]);
                $fileBag->remove('fileSheet');
            } elseif ($data->getBoolean('deletedSheetFile')) {
                $image = $refArticle->getSheet();
                if ($image) {
                    $this->attachmentService->deleteAttachment($image);
                    $refArticle->setSheet(null);
                    $entityManager->remove($image);
                }
            }
            $this->attachmentService->manageAttachments($entityManager, $refArticle, $fileBag);
        }

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getPrevious()->getMessage(), StorageRule::uniqueConstraintLocationReferenceArticleName)) {
                throw new FormException("Impossible de créer deux règles de stockage pour le même emplacement.");
            } else {
                throw new FormException("Une erreur est survenue lors de la sauvegarde. Veuillez réessayer.");
            }
        }

        //recup de la row pour insert datatable
        $rows = $this->dataRowRefArticle($refArticle);
        $response['success'] = true;
        $response['id'] = $refArticle->getId();
        $response['edit'] = $rows;
        if ($sendMail) {
            $this->sendMailCreateDraftOrDraftToActive($refArticle, $refArticle->getCreatedBy());
        }
        return $response;
    }

    public function dataRowRefArticle(ReferenceArticle $refArticle): array
    {
        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategorieCL::REFERENCE_ARTICLE, CategoryType::ARTICLE);
        }

        $providerCodes = Stream::from($refArticle->getArticlesFournisseur())
            ->map(function (ArticleFournisseur $articleFournisseur) {
                return $articleFournisseur->getFournisseur() ? $articleFournisseur->getFournisseur()->getCodeReference() : '';
            })
            ->unique()
            ->toArray();

        $providerLabels = Stream::from($refArticle->getArticlesFournisseur())
            ->map(fn(ArticleFournisseur $articleFournisseur) => FormatHelper::supplier($articleFournisseur->getFournisseur()))
            ->unique()
            ->toArray();

        $typeColor = $refArticle->getType()->getColor();

        $row = [
            "id" => $refArticle->getId(),
            "image" => $this->templating->render('datatable/image.html.twig', [
                "image" => $refArticle->getImage()
            ]),
            "label" => $refArticle->getLibelle() ?? "Non défini",
            "reference" => $refArticle->getReference() ?? "Non défini",
            "quantityType" => $refArticle->getTypeQuantite() ?? "Non défini",
            "type" => "<div class='d-flex align-items-center'><span class='dt-type-color mr-2' style='background-color: $typeColor;'></span>"
                . FormatHelper::type($refArticle->getType())
                . "</div>",
            "location" => FormatHelper::location($refArticle->getEmplacement()),
            "availableQuantity" => $refArticle->getQuantiteDisponible() ?? 0,
            "stockQuantity" => $refArticle->getQuantiteStock() ?? 0,
            "buyer" => $refArticle->getBuyer() ? $refArticle->getBuyer()->getUsername() : '',
            "emergencyComment" => $refArticle->getEmergencyComment(),
            "visibilityGroups" => FormatHelper::visibilityGroup($refArticle->getVisibilityGroup()),
            "barCode" => $refArticle->getBarCode() ?? "Non défini",
            "comment" => $refArticle->getCommentaire(),
            "status" => FormatHelper::status($refArticle->getStatut()),
            "securityThreshold" => $refArticle->getLimitSecurity() ?? "Non défini",
            "warningThreshold" => $refArticle->getLimitWarning() ?? "Non défini",
            "unitPrice" => $refArticle->getPrixUnitaire(),
            "emergency" => FormatHelper::bool($refArticle->getIsUrgent()),
            "mobileSync" => FormatHelper::bool($refArticle->getNeedsMobileSync()),
            'supplierLabel' => implode(",", $providerLabels),
            'supplierCode' => implode(",", $providerCodes),
            "lastInventory" => FormatHelper::date($refArticle->getDateLastInventory()),
            "stockManagement" => $refArticle->getStockManagement(),
            'referenceSupplierArticle' => Stream::from($refArticle->getArticlesFournisseur())
                ->map(fn(ArticleFournisseur $articleFournisseur) => $articleFournisseur->getReference())
                ->join(', '),
            "managers" => Stream::from($refArticle->getManagers())
                ->map(function (Utilisateur $manager) {
                    return $manager->getUsername() ?: '';
                })
                ->filter(function (string $username) {
                    return !empty($username);
                })
                ->unique()
                ->join(", "),
            "createdAt" => FormatHelper::datetime($refArticle->getCreatedAt()),
            "createdBy" => $refArticle->getCreatedBy() ? FormatHelper::user($refArticle->getCreatedBy()) : "-",
            "lastStockEntry" => FormatHelper::datetime($refArticle->getLastStockEntry()),
            "editedAt" => FormatHelper::datetime($refArticle->getEditedAt()),
            "editedBy" => FormatHelper::user($refArticle->getEditedBy()),
            "lastStockExit" => FormatHelper::datetime($refArticle->getLastStockExit()),
            "upToDateInventory" => $refArticle->hasUpToDateInventory() ? 'Oui' : 'Non',
            "actions" => $this->templating->render('reference_article/datatableReferenceArticleRow.html.twig', [
                "attachmentsLength" => $refArticle->getAttachments()->count(),
                "reference_id" => $refArticle->getId(),
                "active" => $refArticle->getStatut() ? $refArticle->getStatut()?->getCode() == ReferenceArticle::STATUT_ACTIF : 0,
            ]),
            "colorClass" => (
            $refArticle->getOrderState() === ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE ? 'table-light-orange' :
                ($refArticle->getOrderState() === ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE ? 'table-light-blue' : null)
            ),
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $refArticle->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = FormatHelper::freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

    public function addReferenceToRequest(array                  $data,
                                          ReferenceArticle       $referenceArticle,
                                          Utilisateur            $user,
                                          bool                   $fromNomade,
                                          EntityManagerInterface $entityManager,
                                          Demande                $demande,
                                                                 $editRef = true,
                                                                 $fromCart = false) {
        $resp = [];
        $articleRepository = $entityManager->getRepository(Article::class);
        $referenceLineRepository = $entityManager->getRepository(DeliveryRequestReferenceLine::class);

        $targetLocationPicking = isset($data['target-location-picking'])
            ? $entityManager->find(Emplacement::class, $data['target-location-picking'])
            : null;

        $loggedUserRole = $user->getRole();

        // cas gestion quantité par référence
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
            if ($fromNomade || $referenceLineRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                $line = new DeliveryRequestReferenceLine();
                $line
                    ->setReference($referenceArticle)
                    ->setRequest($demande)
                    ->setTargetLocationPicking($targetLocationPicking)
                    ->setQuantityToPick(max($data["quantity-to-pick"], 0)); // protection contre quantités négatives
                $entityManager->persist($line);
                $demande->addReferenceLine($line);
            } else {
                $line = $referenceLineRepository->findOneByRefArticleAndDemande($referenceArticle, $demande);
                $line->setQuantityToPick($line->getQuantityToPick() + max($data["quantity-to-pick"], 0)); // protection contre quantités négatives
            }

            if (!$fromNomade && $editRef) {
                $this->editRefArticle($entityManager, $referenceArticle, new ParameterBag($data), $user);
            }
            $resp['type'] = ReferenceArticle::QUANTITY_TYPE_REFERENCE;
        } else if($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            if($fromNomade || $loggedUserRole->getQuantityType() === ReferenceArticle::QUANTITY_TYPE_REFERENCE || $fromCart) {
                if($fromNomade || $referenceLineRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                    $line = new DeliveryRequestReferenceLine();
                    $line
                        ->setQuantityToPick(max($data["quantity-to-pick"], 0))// protection contre quantités négatives
                        ->setReference($referenceArticle)
                        ->setRequest($demande)
                        ->setTargetLocationPicking($targetLocationPicking);
                    $entityManager->persist($line);
                } else {
                    $line = $referenceLineRepository->findOneByRefArticleAndDemande($referenceArticle, $demande);
                    $line->setQuantityToPick($line->getQuantityToPick() + max($data["quantity-to-pick"], 0));
                }
            } else {
                $article = $articleRepository->find($data['article']);

                $line = $this->demandeLivraisonService->createArticleLine($article, $demande, [
                    'quantityToPick' => max($data["quantity-to-pick"], 0), // protection contre quantités négatives
                    'targetLocationPicking' => $targetLocationPicking
                ]);

                $entityManager->persist($line);
                $resp['type'] = ReferenceArticle::QUANTITY_TYPE_ARTICLE;
                $resp['article'] = true;
            }
            $resp['success'] = true;
        } else {
            $resp['success'] = false;
        }
        if (isset($line)) {
            $projectRepository = $entityManager->getRepository(Project::class);
            $project = ($data['project'] ?? null) ? $projectRepository->find($data['project']) : null;
            $line
                ->setNotes($data['notes'] ?? null)
                ->setProject($project);

            $resp['line'] = $line;
        }
        return $resp;
    }

    public function generateBarCode($counter = null)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $now = new DateTime('now');
        $dateCode = $now->format('ym');

        if (!isset($counter)) {
            $highestBarCode = $referenceArticleRepository->getHighestBarCodeByDateCode($dateCode);
            $highestCounter = $highestBarCode ? (int)substr($highestBarCode, 7, 8) : 0;
            $counter = sprintf('%08u', $highestCounter + 1);
        }

        return ReferenceArticle::BARCODE_PREFIX . $dateCode . $counter;
    }

    public function getAlerteDataByParams(InputBag $params, Utilisateur $user)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $alertRepository = $this->entityManager->getRepository(Alert::class);
        if ($params->has('managers') && !empty($params->get('managers')) ||
            $params->has('referenceTypes') && !empty($params->get('referenceTypes'))) {
            $filters = [
                [
                    'field' => 'multipleTypes',
                    'value' => $params->get('referenceTypes')
                ],
                [
                    'field' => 'utilisateurs',
                    'value' => $params->get('managers')
                ]
            ];
        } else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ALERTE, $this->user);
        }
        $results = $alertRepository->getAlertDataByParams($params, $filters, $user);
        $alerts = $results['data'];

        $rows = [];
        foreach ($alerts as $alert) {
            $alertWithQuantity = $alert[0];
            $alertWithQuantity->displayedQuantity = $alert["quantity"];

            $rows[] = $this->dataRowAlerteRef($alertWithQuantity);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $results['count'],
            'recordsTotal' => $results['total'],
        ];
    }

    public function dataRowAlerteRef(Alert $alert)
    {
        if ($entity = $alert->getReference()) {
            $referenceArticle = $entity;
            $reference = $entity->getReference();
            $code = $entity->getBarCode();
            $label = $entity->getLibelle();
            $quantityType = $entity->getTypeQuantite();
            $security = $entity->getLimitSecurity();
            $warning = $entity->getLimitWarning();
            $quantity = $entity->getQuantiteDisponible();
            $managers = Stream::from($entity->getManagers())
                ->map(function (Utilisateur $utilisateur) {
                    return $utilisateur->getUsername();
                })->toArray();
            $managers = count($managers) ? implode(",", $managers) : 'Non défini';
        } else if ($entity = $alert->getArticle()) {
            $referenceArticle = $entity->getArticleFournisseur()->getReferenceArticle();
            $reference = $referenceArticle ? $referenceArticle->getReference() : null;
            $code = $entity->getBarCode();
            $label = $entity->getLabel();
            $quantity = $entity->getQuantite();
            $expiry = $entity->getExpiryDate() ? $entity->getExpiryDate()->format("d/m/Y H:i") : "Non défini";
            $quantityType = $referenceArticle->getTypeQuantite();
            $managers = Stream::from($referenceArticle->getManagers())
                ->map(fn(Utilisateur $user) => $user->getUsername())
                ->toArray();
            $managers = count($managers) > 0 ? implode(",", $managers) : 'Non défini';
        } else {
            throw new RuntimeException("Invalid alert");
        }

        $referenceArticle = $alert->getReference()
            ?? $alert->getArticle()->getArticleFournisseur()->getReferenceArticle();
        $referenceArticleId = isset($referenceArticle) ? $referenceArticle->getId() : null;
        $referenceArticleStatus = isset($referenceArticle) ? $referenceArticle->getStatut() : null;
        $referenceArticleActive = $referenceArticleStatus ? ($referenceArticleStatus?->getCode() == ReferenceArticle::STATUT_ACTIF) : 0;

        return [
            'actions' => $this->templating->render('alerte_reference/datatableAlertRow.html.twig', [
                'referenceId' => $referenceArticleId,
                'active' => $referenceArticleActive
            ]),
            "type" => Alert::TYPE_LABELS[$alert->getType()],
            "reference" => $reference ?? "Non défini",
            "code" => $code ?? "Non défini",
            "label" => $label ?? "Non défini",
            "quantity" => $quantity ?? "Non définie",
            "quantityType" => ucfirst($quantityType ?? "Non défini"),
            "securityThreshold" => $security ?? "Non défini",
            "warningThreshold" => $warning ?? "Non défini",
            "expiry" => $expiry ?? "Non défini",
            "date" => $alert->getDate()->format("d/m/Y H:i"),
            "managers" => $managers,
            "colorClass" => (
            $referenceArticle->getOrderState() === ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE ? 'table-light-orange' :
                ($referenceArticle->getOrderState() === ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE ? 'table-light-blue' : null)
            ),
        ];
    }

    public function getBarcodeConfig(ReferenceArticle $referenceArticle): array
    {
        $labels = [
            $referenceArticle->getReference() ? ('L/R : ' . $referenceArticle->getReference()) : '',
            $referenceArticle->getLibelle() ? ('C/R : ' . $referenceArticle->getLibelle()) : ''
        ];
        return [
            'code' => $referenceArticle->getBarCode(),
            'labels' => array_filter($labels, function (string $label) {
                return !empty($label);
            })
        ];
    }

    public function updateRefArticleQuantities(EntityManagerInterface $entityManager,
                                               ReferenceArticle       $referenceArticle,
                                               bool                   $fromCommand = false)
    {
        $this->updateStockQuantity($entityManager, $referenceArticle);
        $this->updateReservedQuantity($entityManager, $referenceArticle, $fromCommand);
        $referenceArticle->setQuantiteDisponible($referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee());
    }

    public function updateInventoryStatus(EntityManagerInterface $entityManager, ReferenceArticle $referenceArticle)
    {
        $category = $referenceArticle->getCategory();
        if ($category) {
            $frequency = $category->getFrequency();
            $articlesRepository = $entityManager->getRepository(Article::class);
            $articles = $articlesRepository->findActiveArticles($referenceArticle);
            $now = new DateTime();
            $oldestInventoryDate = null;

            foreach ($articles as $article) {
                $inventoryDate = $article->getDateLastInventory();
                $oldestInventoryDate = !$oldestInventoryDate
                    ? $inventoryDate
                    : ($inventoryDate < $oldestInventoryDate
                        ? $inventoryDate
                        : $oldestInventoryDate
                    );
            }
            $diff = $oldestInventoryDate->diff($now);
            $referenceArticle->setUpToDateInventory($diff->m < $frequency->getNbMonths());
        }
    }

    private function updateStockQuantity(EntityManagerInterface $entityManager, ReferenceArticle $referenceArticle): void
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            $referenceArticle->setQuantiteStock($referenceArticleRepository->getStockQuantity($referenceArticle));
        }
    }

    private function updateReservedQuantity(EntityManagerInterface $entityManager,
                                            ReferenceArticle       $referenceArticle,
                                            bool                   $fromCommand = false): void
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            $referenceArticle->setQuantiteReservee($referenceArticleRepository->getReservedQuantity($referenceArticle));
        } else {
            $totalReservedQuantity = 0;
            $lignesArticlePrepaEnCours = $referenceArticle
                ->getPreparationOrderReferenceLines()
                ->filter(function (PreparationOrderReferenceLine $ligneArticlePreparation) use ($fromCommand) {
                    $preparation = $ligneArticlePreparation->getPreparation();
                    $livraison = $preparation->getLivraison();
                    return $preparation->getStatut()?->getCode() === Preparation::STATUT_EN_COURS_DE_PREPARATION
                        || $preparation->getStatut()?->getCode() === Preparation::STATUT_A_TRAITER
                        || (
                            $fromCommand &&
                            $livraison &&
                            $livraison->getStatut()?->getCode() === Livraison::STATUT_A_TRAITER
                        );
                });
            /**
             * @var PreparationOrderReferenceLine $ligneArticlePrepaEnCours
             */
            foreach ($lignesArticlePrepaEnCours as $ligneArticlePrepaEnCours) {
                $totalReservedQuantity += $ligneArticlePrepaEnCours->getQuantityToPick();
            }
            $referenceArticle->setQuantiteReservee($totalReservedQuantity);
        }
    }

    public function treatAlert(EntityManagerInterface $entityManager, ReferenceArticle $reference): void
    {
        if ($reference->getStatut()?->getCode() === ReferenceArticle::STATUT_INACTIF) {
            foreach ($reference->getAlerts() as $alert) {
                $entityManager->remove($alert);
            }
        } else {
            $now = new DateTime("now");
            $alertRepository = $entityManager->getRepository(Alert::class);

            if ($reference->getLimitSecurity() !== null && $reference->getLimitSecurity() >= $reference->getQuantiteStock()) {
                $type = Alert::SECURITY;
            } else if ($reference->getLimitWarning() !== null && $reference->getLimitWarning() >= $reference->getQuantiteStock()) {
                $type = Alert::WARNING;
            }

            $existing = $alertRepository->findForReference($reference, [Alert::SECURITY, Alert::WARNING]);

            //more than 1 security/warning alert is an invalid state -> reset
            if (count($existing) > 1) {
                foreach ($existing as $remove) {
                    $entityManager->remove($remove);
                }

                $existing = null;
            } else if (count($existing) == 1) {
                $existing = $existing[0];
            }

            if ($existing && (!isset($type) || $this->isDifferentThresholdType($existing, $type))) {
                $entityManager->remove($existing);
                $existing = null;
            }

            if (isset($type) && !$existing) {
                $alert = new Alert();
                $alert->setReference($reference);
                $alert->setType($type);
                $alert->setDate($now);

                $entityManager->persist($alert);

                $this->alertService->sendThresholdMails($reference, $entityManager);
            }
        }
    }

    private function isDifferentThresholdType($alert, $type)
    {
        return $alert->getType() == Alert::WARNING && $type == Alert::SECURITY ||
            $alert->getType() == Alert::SECURITY && $type == Alert::WARNING;
    }

    public function getColumnVisibleConfig(EntityManagerInterface $entityManager,
                                           Utilisateur            $currentUser): array
    {

        $freeFieldRepository = $entityManager->getRepository(FreeField::class);

        $columnVisible = $currentUser->getVisibleColumns()['reference'];
        $freeFields = $freeFieldRepository->findByCategoryTypeAndCategoryCL(CategoryType::ARTICLE, CategorieCL::REFERENCE_ARTICLE);

        $fields = self::REF_ARTICLE_FIELDS;
        if (!$currentUser->getVisibilityGroups()->isEmpty()) {
            $visibilityGroupsIndex = null;
            foreach ($fields as $index => $field) {
                if ($field["name"] === "visibilityGroups") {
                    $visibilityGroupsIndex = $index;
                    break;
                }
            }

            if ($visibilityGroupsIndex) {
                array_splice($fields, $visibilityGroupsIndex, 1);
            }
        }
        return $this->visibleColumnService->getArrayConfig($fields, $freeFields, $columnVisible);
    }

    public function getFieldTitle(string $fieldName): ?string
    {
        $title = null;
        foreach (self::REF_ARTICLE_FIELDS as $field) {
            if ($field['name'] === $fieldName) {
                $title = $field['title'] ?? null;
                break;
            }
        }
        return $title;
    }

    public function setStateAccordingToRelations(ReferenceArticle                    $reference,
                                                 PurchaseRequestLineRepository       $purchaseRequestLineRepository,
                                                 ReceptionReferenceArticleRepository $receptionReferenceArticleRepository)
    {
        $associatedLines = $receptionReferenceArticleRepository->findByReferenceArticleAndReceptionStatus(
            $reference,
            [Reception::STATUT_EN_ATTENTE, Reception::STATUT_RECEPTION_PARTIELLE],
        );
        if (!empty($associatedLines)) {
            $reference->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
        } else {
            $associatedLines = $purchaseRequestLineRepository->findByReferenceArticleAndPurchaseStatus(
                $reference,
                [Statut::NOT_TREATED, Statut::IN_PROGRESS]
            );
            if (!empty($associatedLines)) {
                $reference->setOrderState(ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE);
            } else {
                $reference->setOrderState(null);
            }
        }
    }

    public function sendMailCreateDraftOrDraftToActive(ReferenceArticle $refArticle, $to, bool $state = false)
    {
        $supplierArticles = $refArticle->getArticlesFournisseur();
        $title = $state ?
            "Une nouvelle référence vient d'être créée et attend d'être validée :" :
            "Votre référence vient d'être validée avec les informations suivantes :";

        $this->mailerService->sendMail(
            'FOLLOW GT // ' . ($state ? "Création d'une nouvelle référence" : "Validation de votre référence"),
            $this->templating->render(
                'mails/contents/mailCreateDraftOrDraftToActive.html.twig',
                [
                    'title' => $title,
                    'refArticle' => $refArticle,
                    'supplierArticles' => $supplierArticles,
                    'urlSuffix' => $this->router->generate("reference_article_show_page", ["id" => $refArticle->getId()])
                ]
            ),
            $to
        );
    }

    public function sendMailEntryStock(ReferenceArticle $refArticle, $to, $message = '')
    {
        $supplierArticles = $refArticle->getArticlesFournisseur();

        $this->mailerService->sendMail(
            'FOLLOW GT // Entrée de stock',
            $this->templating->render(
                'mails/contents/mailCreateDraftOrDraftToActive.html.twig',
                [
                    'title' => $message,
                    'refArticle' => $refArticle,
                    'supplierArticles' => $supplierArticles,
                    'urlSuffix' => $this->router->generate("reference_article_show_page", ["id" => $refArticle->getId()]),
                    'frenchSlug' => Language::FRENCH_SLUG
                ]
            ),
            $to
        );
    }

    private function extractIncomingPreparationsData(array $quantityByDatesWithEvents, array $preparations, ReferenceArticle $referenceArticle): array
    {
        foreach ($preparations as $preparation) {
            $reservedQuantity = Stream::from($preparation->getReferenceLines())
                ->filterMap(function (PreparationOrderReferenceLine $line) use ($referenceArticle) {
                    return $line->getReference()->getId() === $referenceArticle->getId()
                        ? $line->getQuantityToPick()
                        : null;
                })->sum();

            $date = $preparation->getExpectedAt()->format('d/m/Y');
            $number = $preparation->getNumero();

            if (!isset($quantityByDatesWithEvents[$date])) {
                $quantityByDatesWithEvents[$date] = [];
            }
            $quantityByDatesWithEvents[$date][$number] = [
                'quantity' => $reservedQuantity,
                'variation' => 'minus'
            ];
        }
        return $quantityByDatesWithEvents;
    }

    private function extractIncomingReceptionsData(array $quantityByDatesWithEvents, array $receptions, ReferenceArticle $referenceArticle): array
    {
        foreach ($receptions as $reception) {
            $reservedQuantity = Stream::from($reception->getLines())
                ->flatMap(fn(ReceptionLine $line) => $line->getReceptionReferenceArticles()->toArray())
                ->filterMap(function (ReceptionReferenceArticle $line) use ($referenceArticle) {
                    return $line->getReferenceArticle()->getId() === $referenceArticle->getId()
                        ? $line->getQuantiteAR() - $line->getQuantite()
                        : null;
                })->sum();

            $date = $reception->getDateAttendue()->format('d/m/Y');
            $number = $reception->getNumber();

            if (!isset($quantityByDatesWithEvents[$date])) {
                $quantityByDatesWithEvents[$date] = [];
            }
            $quantityByDatesWithEvents[$date][$number] = [
                'quantity' => $reservedQuantity,
                'variation' => 'plus'
            ];
        }
        return $quantityByDatesWithEvents;
    }

    private function formatExtractedIncomingData(array $quantityByDatesWithEvents, DateTime $end): array
    {
        $formattedQuantityPredictions = [];
        $lastQuantity = 0;

        uksort(
            $quantityByDatesWithEvents,
            fn(string $date1, string $date2) => strtotime(str_replace('/', '-', $date1)) - strtotime(str_replace('/', '-', $date2))
        );

        foreach ($quantityByDatesWithEvents as $date => $quantityByDatesWithEvent) {
            $formattedQuantityPredictions[$date] = [
                'quantity' => 0,
                'preparations' => 0,
                'receptions' => 0,
            ];
            foreach ($quantityByDatesWithEvent as $key => $item) {
                if ($key === 'initial') {
                    $lastQuantity = $item;
                } else {
                    $event = $item['variation'];
                    $quantity = $item['quantity'];

                    if ($event === 'plus') {
                        $lastQuantity += $quantity;
                        $formattedQuantityPredictions[$date]['receptions'] += 1;
                    } else {
                        $lastQuantity -= $quantity;
                        $formattedQuantityPredictions[$date]['preparations'] += 1;
                    }

                }
                $formattedQuantityPredictions[$date]['quantity'] = $lastQuantity;
            }
        }
        uksort(
            $formattedQuantityPredictions,
            fn(string $date1, string $date2) => strtotime(str_replace('/', '-', $date1)) - strtotime(str_replace('/', '-', $date2))
        );

        if (!isset($formattedQuantityPredictions[$end->format('d/m/Y')])) {
            $formattedQuantityPredictions[$end->format('d/m/Y')] = [
                "quantity" => $formattedQuantityPredictions[array_key_last($formattedQuantityPredictions)]['quantity'],
                "preparations" => 0,
                "receptions" => 0,
            ];
        }

        return $formattedQuantityPredictions;
    }

    public function getQuantityPredictions(EntityManagerInterface $entityManager, ReferenceArticle $referenceArticle, int $period)
    {
        $preparationRepository = $entityManager->getRepository(Preparation::class);
        $receptionRepository = $entityManager->getRepository(Reception::class);

        $start = new DateTime();
        $end = new DateTime("+$period month");
        $currentQuantity = $referenceArticle->getQuantiteStock();
        $quantityByDatesWithEvents = [
            $start->format('d/m/Y') => [
                'initial' => $currentQuantity
            ]
        ];

        $preparations = $preparationRepository->getValidatedWithReference($referenceArticle, $start, $end);
        $receptions = $receptionRepository->getAwaitingWithReference($referenceArticle, $start, $end);

        $quantityByDatesWithEvents = $this->extractIncomingPreparationsData($quantityByDatesWithEvents, $preparations, $referenceArticle);
        $quantityByDatesWithEvents = $this->extractIncomingReceptionsData($quantityByDatesWithEvents, $receptions, $referenceArticle);

        return $this->formatExtractedIncomingData($quantityByDatesWithEvents, $end);
    }

    public function getDraftDefaultReference(EntityManagerInterface $entityManager): string
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $prefix = "A DEFINIR";
        $referenceCount = $referenceArticleRepository->countByReference($prefix, null, "LIKE");
        return $prefix . ($referenceCount + 1);
    }

    public function putReferenceLine($handle,
                                     array $reference,
                                     array $freeFieldsConfig): void
    {
        $line = [
            $reference["reference"],
            $reference["libelle"],
            $reference["quantiteStock"],
            $reference["type"],
            $reference["buyer"],
            $reference["typeQuantite"],
            $reference["statut"],
            $reference["commentaire"] ? strip_tags($reference["commentaire"]) : "",
            $reference["emplacement"],
            $reference["limitSecurity"],
            $reference["limitWarning"],
            $reference["prixUnitaire"],
            $reference["barCode"],
            $reference["category"],
            $reference["dateLastInventory"] ? $reference["dateLastInventory"]->format("d/m/Y H:i:s") : "",
            $reference["needsMobileSync"],
            $reference["stockManagement"],
            $reference["managers"] ?? "",
            $reference["supplierLabels"] ?? "",
            $reference["supplierCodes"] ?? "",
            $reference["visibilityGroup"],
            $reference["createdAt"] ? $reference["createdAt"]->format("d/m/Y H:i:s") : "",
            $reference["createdBy"] ?? "-",
            $reference["editedAt"] ? $reference["editedAt"]->format("d/m/Y H:i:s") : "",
            $reference["editedBy"] ?? "",
            $reference["lastStockEntry"] ? $reference["lastStockEntry"]->format("d/m/Y H:i:s") : "",
            $reference["lastStockExit"] ? $reference["lastStockExit"]->format("d/m/Y H:i:s") : "",
        ];

        foreach ($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
            $line[] = FormatHelper::freeField($reference['freeFields'][$freeFieldId] ?? '', $freeField);
        }

        $this->CSVExportService->putLine($handle, $line);
    }

    public function getDescriptionConfig(EntityManagerInterface $entityManager, bool $isFromDispatch = false): array
    {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $associatedDocumentTypesStr = $settingRepository->getOneParamByLabel(Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES);
        $associatedDocumentTypes = $associatedDocumentTypesStr
            ? Stream::explode(',', $associatedDocumentTypesStr)
                ->filter()
                ->toArray()
            : [];

        $config = [
            "Matériel hors format" => [
                "name" => "outFormatEquipment",
                "type" => "bool",
                "persisted" => true,
            ],
            "Code fabriquant" => [
                "name" => "manufacturerCode",
                "type" => "text",
                "persisted" => true,
                "required" => $isFromDispatch,
            ],
            "Poids (kg)" => [
                "name" => "weight",
                "type" => "number",
                "step" => "0.01",
                "persisted" => true,
                "required" => $isFromDispatch,
            ],
            "Types de documents associés" => [
                "name" => "associatedDocumentTypes",
                "type" => "select",
                "values" => $associatedDocumentTypes,
                "persisted" => true,
                "required" => $isFromDispatch,
            ],
            "Longueur (cm)" => [
                "name" => "length",
                "type" => "number",
                "step" => "0.01",
                "persisted" => true,
            ],
            "Largeur (cm)" => [
                "name" => "width",
                "type" => "number",
                "step" => "0.01",
                "persisted" => true,
            ],
            "Hauteur (cm)" => [
                "name" => "height",
                "type" => "number",
                "step" => "0.01",
                "persisted" => true,
            ],
            "Volume (m3)" => [
                "name" => "volume",
                "type" => "number",
                "step" => "0.000001",
                "persisted" => true,
                "disabled" => true,
                "required" => $isFromDispatch,
            ],
        ];
        return $config;
    }

    public function updateDescriptionField(EntityManagerInterface $entityManager,
                                           ReferenceArticle       $referenceArticle,
                                           array                  $data): void
    {
        $descriptionConfig = $this->getDescriptionConfig($entityManager);
        $descriptionData = Stream::from($descriptionConfig)
            ->filter(fn(array $config) => $config["persisted"] ?? true)
            ->map(fn(array $attributes) => $attributes['name'])
            ->flip()
            ->intersect($data, true)
            ->keymap(fn($value, string $key) => [$key, !is_null($value) ? (string)$value : null])
            ->toArray();
        $referenceArticle->setDescription($descriptionData);
    }

}
