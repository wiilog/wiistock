<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\FiltreRef;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\InventoryCategory;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Helper\FormatHelper;
use App\Repository\FiltreRefRepository;
use App\Repository\PurchaseRequestLineRepository;
use App\Repository\ReceptionReferenceArticleRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class RefArticleDataService {

    private const REF_ARTICLE_FIELDS = [
        ["name" => "actions", "class" => "noVis", "alwaysVisible" => true, "orderable" => false],
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
        ["title" => "Gestionnaire(s)", "name" => "managers", "orderable" => false, "type" => "text"],
        ["title" => "Commentaire", "name" => "comment", "type" => "text", "orderable" => false],
        ["title" => "Commentaire d'urgence", "name" => "emergencyComment", "type" => "text", "orderable" => false],
        ["title" => FiltreRef::FIXED_FIELD_VISIBILITY_GROUP, "name" => "visibilityGroups", "type" => "list", "orderable" => true],
    ];

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var object|string
     */
    private $user;

    private $entityManager;

    /**
     * @var RouterInterface
     */
    private $router;
    private $freeFieldService;
    private $articleFournisseurService;
    private $alertService;
    private $visibleColumnService;
    private $attachmentService;

    public function __construct(RouterInterface $router,
                                UserService $userService,
                                FreeFieldService $champLibreService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                VisibleColumnService $visibleColumnService,
                                TokenStorageInterface $tokenStorage,
                                ArticleFournisseurService $articleFournisseurService,
                                AlertService $alertService,
                                AttachmentService $attachmentService) {
        $this->filtreRefRepository = $entityManager->getRepository(FiltreRef::class);
        $this->freeFieldService = $champLibreService;
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken() ? $tokenStorage->getToken()->getUser() : null;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->router = $router;
        $this->articleFournisseurService = $articleFournisseurService;
        $this->alertService = $alertService;
        $this->visibleColumnService = $visibleColumnService;
        $this->attachmentService = $attachmentService;
    }

    public function getRefArticleDataByParams($params = null) {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $userId = $this->user->getId();
        $filters = $this->filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $referenceArticleRepository->findByFiltersAndParams($filters, $params, $this->user);
        $refs = $queryResult['data'];
        $rows = [];
        foreach($refs as $refArticle) {
            $rows[] = $this->dataRowRefArticle(is_array($refArticle) ? $refArticle[0] : $refArticle);
        }
        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $referenceArticleRepository->countAll()
        ];
    }

    public function getDataEditForRefArticle($articleRef) {
        $totalQuantity = $articleRef->getQuantiteDisponible();
        return $data = [
            'listArticlesFournisseur' => array_reduce($articleRef->getArticlesFournisseur()->toArray(),
                function(array $carry, ArticleFournisseur $articleFournisseur) use ($articleRef) {
                    $articles = $articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE
                        ? $articleFournisseur->getArticles()->toArray()
                        : [];
                    $carry[] = [
                        'reference' => $articleFournisseur->getReference(),
                        'label' => $articleFournisseur->getLabel(),
                        'fournisseurCode' => $articleFournisseur->getFournisseur()->getCodeReference(),
                        'quantity' => array_reduce($articles, function(int $carry, Article $article) {
                            return ($article->getStatut() && $article->getStatut()->getNom() === Article::STATUT_ACTIF)
                                ? $carry + $article->getQuantite()
                                : $carry;
                        }, 0)
                    ];
                    return $carry;
                }, []),
            'totalQuantity' => $totalQuantity,
        ];
    }

    public function getViewEditRefArticle($refArticle,
                                          $isADemand = false,
                                          $preloadCategories = true,
                                          $showAttachments = false) {
        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
        $typeRepository = $this->entityManager->getRepository(Type::class);
        $inventoryCategoryRepository = $this->entityManager->getRepository(InventoryCategory::class);
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);

        $data = $this->getDataEditForRefArticle($refArticle);
        $articlesFournisseur = $articleFournisseurRepository->findByRefArticle($refArticle->getId());
        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $editAttachments = $this->userService->hasRightFunction(Menu::STOCK, Action::EDIT);

        $categories = $preloadCategories
            ? $inventoryCategoryRepository->findBy([], ['label' => 'ASC'])
            : [];

        $freeFieldsGroupedByTypes = [];
        foreach($types as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
            $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
        }
        $typeChampLibre = [];
        foreach($types as $type) {
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
                ->map(function(Utilisateur $manager) {
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

    public function editRefArticle(ReferenceArticle $refArticle,
                                   $data,
                                   Utilisateur $user,
                                   FreeFieldService $champLibreService,
                                   $request = null) {
        if(!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }
        $typeRepository = $this->entityManager->getRepository(Type::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $inventoryCategoryRepository = $this->entityManager->getRepository(InventoryCategory::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);
        $visibilityGroupRepository = $this->entityManager->getRepository(VisibilityGroup::class);

        //modification champsFixes
        $entityManager = $this->entityManager;
        $category = $inventoryCategoryRepository->find($data['categorie']);
        $price = max(0, $data['prix']);
        if(isset($data['reference'])) $refArticle->setReference($data['reference']);
        if(isset($data['frl'])) {
            $supplierReferenceLines = json_decode($data['frl'], true);
            foreach($supplierReferenceLines as $supplierReferenceLine) {
                $referenceArticleFournisseur = $supplierReferenceLine['referenceFournisseur'];

                try {
                    $supplierArticle = $this->articleFournisseurService->createArticleFournisseur([
                        'fournisseur' => $supplierReferenceLine['fournisseur'],
                        'article-reference' => $refArticle,
                        'label' => $supplierReferenceLine['labelFournisseur'],
                        'reference' => $referenceArticleFournisseur
                    ]);

                    $entityManager->persist($supplierArticle);
                } catch(Exception $exception) {
                    if($exception->getMessage() === ArticleFournisseurService::ERROR_REFERENCE_ALREADY_EXISTS) {
                        $response['success'] = false;
                        $response['msg'] = "La référence '$referenceArticleFournisseur' existe déjà pour un article fournisseur.";
                        return $response;
                    }
                }
            }
        }

        if(isset($data['categorie'])) {
            $refArticle->setCategory($category);
        }

        if(isset($data['urgence'])) {
            if($data['urgence'] && $data['urgence'] !== $refArticle->getIsUrgent()) {
                $refArticle->setUserThatTriggeredEmergency($user);
            } else if(!$data['urgence']) {
                $refArticle->setUserThatTriggeredEmergency(null);
                $refArticle->setEmergencyComment('');
            }
            $refArticle->setIsUrgent($data['urgence'] === "true");
        }

        if(isset($data['prix'])) {
            $refArticle->setPrixUnitaire($price);
        }

        if(isset($data['libelle'])) {
            $refArticle->setLibelle($data['libelle']);
        }

        if(isset($data['commentaire'])) {
            $refArticle->setCommentaire($data['commentaire']);
        }

        if(isset($data['mobileSync'])) {
            $refArticle->setNeedsMobileSync($data['mobileSync'] === "true");
        }

        $refArticle->setBuyer(isset($data['buyer']) ? $userRepository->find($data['buyer']) : null);
        $refArticle->setLimitWarning((empty($data['limitWarning']) && $data['limitWarning'] !== 0 && $data['limitWarning'] !== '0') ? null : intval($data['limitWarning']));
        $refArticle->setLimitSecurity((empty($data['limitSecurity']) && $data['limitSecurity'] !== 0 && $data['limitSecurity'] !== '0') ? null : intval($data['limitSecurity']));

        if($data['emergency-comment-input']) {
            $refArticle->setEmergencyComment($data['emergency-comment-input']);
        }

        if(isset($data['statut'])) {
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, $data['statut']);
            if($statut) {
                $refArticle->setStatut($statut);
            }
        }

        if(isset($data['type'])) {
            $type = $typeRepository->find(intval($data['type']));
            if($type) $refArticle->setType($type);
        }

        $refArticle->setStockManagement($data['stockManagement'] ?? null);


        $refArticle->getManagers()->clear();
        if (!empty($data["managers"])) {
            $managers = is_string($data["managers"]) ? explode(',', $data['managers']) : $data["managers"];
            foreach ($managers as $manager) {
                $refArticle->addManager($userRepository->find($manager));
            }
        }
        $entityManager->flush();
        if (isset($data["visibility-group"])) {
            $refArticle->setVisibilityGroup($data['visibility-group'] ? $visibilityGroupRepository->find(intval($data['visibility-group'])) : null);
        }

        $entityManager->flush();
        //modification ou création des champsLibres

        $champLibreService->manageFreeFields($refArticle, $data, $entityManager);
        if(isset($request)) {
            $this->attachmentService->manageAttachments($entityManager, $refArticle, $request->files);
        }
        $entityManager->flush();
        //recup de la row pour insert datatable
        $rows = $this->dataRowRefArticle($refArticle);
        $response['success'] = true;
        $response['id'] = $refArticle->getId();
        $response['edit'] = $rows;
        return $response;
    }

    public function dataRowRefArticle(ReferenceArticle $refArticle) {
        $categorieCLRepository = $this->entityManager->getRepository(CategorieCL::class);
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);

        $ffCategory = $categorieCLRepository->findOneBy(['label' => CategorieCL::REFERENCE_ARTICLE]);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::ARTICLE, $ffCategory);

        $providerCodes = Stream::from($refArticle->getArticlesFournisseur())
            ->map(function(ArticleFournisseur $articleFournisseur) {
                return $articleFournisseur->getFournisseur() ? $articleFournisseur->getFournisseur()->getCodeReference() : '';
            })
            ->unique()
            ->toArray();

        $providerLabels = Stream::from($refArticle->getArticlesFournisseur())
            ->map(function(ArticleFournisseur $articleFournisseur) {
                return $articleFournisseur->getFournisseur() ? $articleFournisseur->getFournisseur()->getNom() : '';
            })
            ->unique()
            ->toArray();

        $row = [
            "id" => $refArticle->getId(),
            "label" => $refArticle->getLibelle() ?? "Non défini",
            "reference" => $refArticle->getReference() ?? "Non défini",
            "quantityType" => $refArticle->getTypeQuantite() ?? "Non défini",
            "type" => FormatHelper::type($refArticle->getType()),
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
            "managers" => Stream::from($refArticle->getManagers())
                ->map(function(Utilisateur $manager) {
                    return $manager->getUsername() ?: '';
                })
                ->filter(function(string $username) {
                    return !empty($username);
                })
                ->unique()
                ->join(", "),
            "actions" => $this->templating->render('reference_article/datatableReferenceArticleRow.html.twig', [
                "attachmentsLength" => $refArticle->getAttachments()->count(),
                "reference_id" => $refArticle->getId(),
                "active" => $refArticle->getStatut() ? $refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF : 0,
            ]),
            "colorClass" => (
                $refArticle->getOrderState() === ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE ? 'table-light-orange' :
                ($refArticle->getOrderState() === ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE ? 'table-light-blue' : null)
            ),
        ];

        foreach($freeFields as $freeField) {
            $freeFieldId = $freeField["id"];
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $row[$freeFieldName] = $this->freeFieldService->serializeValue([
                "valeur" => $refArticle->getFreeFieldValue($freeFieldId),
                "typage" => $freeField["typage"],
            ]);
        }

        return $row;
    }

    public function addRefToDemand($data,
                                   $referenceArticle,
                                   Utilisateur $user,
                                   bool $fromNomade,
                                   EntityManagerInterface $entityManager,
                                   Demande $demande,
                                   ?FreeFieldService $champLibreService, $editRef = true, $fromCart = false) {
        $resp = true;
        $articleRepository = $entityManager->getRepository(Article::class);
        $referenceLineRepository = $entityManager->getRepository(DeliveryRequestReferenceLine::class);
        // cas gestion quantité par référence
        if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            if($fromNomade || $referenceLineRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                $line = new DeliveryRequestReferenceLine();
                $line
                    ->setReference($referenceArticle)
                    ->setRequest($demande)
                    ->setQuantityToPick(max($data["quantity-to-pick"], 0)); // protection contre quantités négatives
                $entityManager->persist($line);
                $demande->addReferenceLine($line);
            } else {
                $line = $referenceLineRepository->findOneByRefArticleAndDemande($referenceArticle, $demande);
                $line->setQuantityToPick($line->getQuantityToPick() + max($data["quantity-to-pick"], 0)); // protection contre quantités négatives
            }

            if(!$fromNomade && $editRef) {
                $this->editRefArticle($referenceArticle, $data, $user, $champLibreService);
            }
        } else if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            if($fromNomade || $this->userService->hasParamQuantityByRef() || $fromCart) {
                if($fromNomade || $referenceLineRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                    $line = new DeliveryRequestReferenceLine();
                    $line
                        ->setQuantityToPick(max($data["quantity-to-pick"], 0))// protection contre quantités négatives
                        ->setReference($referenceArticle)
                        ->setRequest($demande);
                    $entityManager->persist($line);
                } else {
                    $line = $referenceLineRepository->findOneByRefArticleAndDemande($referenceArticle, $demande, true);
                    $line->setQuantityToPick($line->getQuantityToPick() + max($data["quantity-to-pick"], 0));
                }
            } else {
                $article = $articleRepository->find($data['article']);
                $line = new DeliveryRequestArticleLine();
                $line
                    ->setQuantityToPick(max($data["quantity-to-pick"], 0))// protection contre quantités négatives
                    ->setArticle($article)
                    ->setRequest($demande);
                $entityManager->persist($line);
                $resp = 'article';
            }
        } else {
            $resp = false;
        }
        return $resp;
    }

    public function generateBarCode($counter = null) {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $now = new DateTime('now');
        $dateCode = $now->format('ym');

        if(!isset($counter)) {
            $highestBarCode = $referenceArticleRepository->getHighestBarCodeByDateCode($dateCode);
            $highestCounter = $highestBarCode ? (int)substr($highestBarCode, 7, 8) : 0;
            $counter = sprintf('%08u', $highestCounter + 1);
        }

        return ReferenceArticle::BARCODE_PREFIX . $dateCode . $counter;
    }

    public function getAlerteDataByParams($params, Utilisateur $user) {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $alertRepository = $this->entityManager->getRepository(Alert::class);

        $filtresAlerte = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ALERTE, $user);

        $results = $alertRepository->getAlertDataByParams($params, $filtresAlerte, $user);
        $alerts = $results['data'];

        $rows = [];
        foreach($alerts as $alert) {
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

    public function dataRowAlerteRef(Alert $alert) {
        if($entity = $alert->getReference()) {
            $referenceArticle = $entity;
            $reference = $entity->getReference();
            $code = $entity->getBarCode();
            $label = $entity->getLibelle();
            $quantityType = $entity->getTypeQuantite();
            $security = $entity->getLimitSecurity();
            $warning = $entity->getLimitWarning();
            $quantity = $entity->getQuantiteDisponible();
            $managers = Stream::from($entity->getManagers())
                ->map(function(Utilisateur $utilisateur) {
                    return $utilisateur->getUsername();
                })->toArray();
            $managers = count($managers) ? implode(",", $managers) : 'Non défini';
        } else if($entity = $alert->getArticle()) {
            $referenceArticle = $entity->getArticleFournisseur()->getReferenceArticle();
            $reference = $referenceArticle ? $referenceArticle->getReference() : null;
            $code = $entity->getBarCode();
            $label = $entity->getLabel();
            $expiry = $entity->getExpiryDate() ? $entity->getExpiryDate()->format("d/m/Y H:i") : "Non défini";
            $quantityType = $referenceArticle->getTypeQuantite();
            $managers = Stream::from($referenceArticle->getManagers())
                ->map(fn (Utilisateur $user) => $user->getUsername())
                ->toArray();
            $managers = count($managers) > 0 ? implode(",", $managers) : 'Non défini';
        } else {
            throw new RuntimeException("Invalid alert");
        }

        $referenceArticle = $alert->getReference()
            ?? $alert->getArticle()->getArticleFournisseur()->getReferenceArticle();
        $referenceArticleId = isset($referenceArticle) ? $referenceArticle->getId() : null;
        $referenceArticleStatus = isset($referenceArticle) ? $referenceArticle->getStatut() : null;
        $referenceArticleActive = $referenceArticleStatus ? ($referenceArticleStatus->getNom() == ReferenceArticle::STATUT_ACTIF) : 0;

        return [
            'actions' => $this->templating->render('alerte_reference/datatableAlertRow.html.twig', [
                'referenceId' => $referenceArticleId,
                'active' => $referenceArticleActive
            ]),
            "type" => Alert::TYPE_LABELS[$alert->getType()],
            "reference" => $reference ?? "Non défini",
            "code" => $code ?? "Non défini",
            "label" => $label ?? "Non défini",
            "quantity" => $quantity ?? "0",
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

    public function getBarcodeConfig(ReferenceArticle $referenceArticle): array {
        $labels = [
            $referenceArticle->getReference() ? ('L/R : ' . $referenceArticle->getReference()) : '',
            $referenceArticle->getLibelle() ? ('C/R : ' . $referenceArticle->getLibelle()) : ''
        ];
        return [
            'code' => $referenceArticle->getBarCode(),
            'labels' => array_filter($labels, function(string $label) {
                return !empty($label);
            })
        ];
    }

    public function updateRefArticleQuantities(EntityManagerInterface $entityManager,
                                               ReferenceArticle $referenceArticle,
                                               bool $fromCommand = false) {
        $this->updateStockQuantity($entityManager, $referenceArticle);
        $this->updateReservedQuantity($entityManager, $referenceArticle, $fromCommand);
        $referenceArticle->setQuantiteDisponible($referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee());
    }

    private function updateStockQuantity(EntityManagerInterface $entityManager, ReferenceArticle $referenceArticle): void {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $referenceArticle->setQuantiteStock($referenceArticleRepository->getStockQuantity($referenceArticle));
        }
    }

    private function updateReservedQuantity(EntityManagerInterface $entityManager,
                                            ReferenceArticle $referenceArticle,
                                            bool $fromCommand = false): void {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $referenceArticle->setQuantiteReservee($referenceArticleRepository->getReservedQuantity($referenceArticle));
        } else {
            $totalReservedQuantity = 0;
            $lignesArticlePrepaEnCours = $referenceArticle
                ->getPreparationOrderReferenceLines()
                ->filter(function(PreparationOrderReferenceLine $ligneArticlePreparation) use ($fromCommand) {
                    $preparation = $ligneArticlePreparation->getPreparation();
                    $livraison = $preparation->getLivraison();
                    return $preparation->getStatut()->getNom() === Preparation::STATUT_EN_COURS_DE_PREPARATION
                        || $preparation->getStatut()->getNom() === Preparation::STATUT_A_TRAITER
                        || (
                            $fromCommand &&
                            $livraison &&
                            $livraison->getStatut()->getNom() === Livraison::STATUT_A_TRAITER
                        );
                });
            /**
             * @var PreparationOrderReferenceLine $ligneArticlePrepaEnCours
             */
            foreach($lignesArticlePrepaEnCours as $ligneArticlePrepaEnCours) {
                $totalReservedQuantity += $ligneArticlePrepaEnCours->getQuantityToPick();
            }
            $referenceArticle->setQuantiteReservee($totalReservedQuantity);
        }
    }

    public function treatAlert(EntityManagerInterface $entityManager, ReferenceArticle $reference): void {
        if($reference->getStatut()->getNom() === ReferenceArticle::STATUT_INACTIF) {
            foreach($reference->getAlerts() as $alert) {
                $entityManager->remove($alert);
            }
        } else {
            $now = new DateTime("now");
            $alertRepository = $entityManager->getRepository(Alert::class);

            if($reference->getLimitSecurity() !== null && $reference->getLimitSecurity() >= $reference->getQuantiteStock()) {
                $type = Alert::SECURITY;
            } else if($reference->getLimitWarning() !== null && $reference->getLimitWarning() >= $reference->getQuantiteStock()) {
                $type = Alert::WARNING;
            }

            $existing = $alertRepository->findForReference($reference, [Alert::SECURITY, Alert::WARNING]);

            //more than 1 security/warning alert is an invalid state -> reset
            if(count($existing) > 1) {
                foreach($existing as $remove) {
                    $entityManager->remove($remove);
                }

                $existing = null;
            } else if(count($existing) == 1) {
                $existing = $existing[0];
            }

            if($existing && (!isset($type) || $this->isDifferentThresholdType($existing, $type))) {
                $entityManager->remove($existing);
                $existing = null;
            }

            if(isset($type) && !$existing) {
                $alert = new Alert();
                $alert->setReference($reference);
                $alert->setType($type);
                $alert->setDate($now);

                $entityManager->persist($alert);

                $this->alertService->sendThresholdMails($reference, $entityManager);
            }
        }
    }

    private function isDifferentThresholdType($alert, $type) {
        return $alert->getType() == Alert::WARNING && $type == Alert::SECURITY ||
            $alert->getType() == Alert::SECURITY && $type == Alert::WARNING;
    }

    public function getColumnVisibleConfig(EntityManagerInterface $entityManager,
                                           Utilisateur $currentUser): array {

        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $categorieCL = $categorieCLRepository->findOneBy(['label' => CategorieCL::REFERENCE_ARTICLE]);
        $freeFields = $freeFieldRepository->getByCategoryTypeAndCategoryCL(CategoryType::ARTICLE, $categorieCL);

        $fields = self::REF_ARTICLE_FIELDS;
        if(!$currentUser->getVisibilityGroups()->isEmpty()) {
            $visibilityGroupsIndex = null;
            foreach($fields as $index => $field) {
                if($field["name"] === "visibilityGroups") {
                    $visibilityGroupsIndex = $index;
                    break;
                }
            }

            if($visibilityGroupsIndex) {
                array_splice($fields, $visibilityGroupsIndex, 1);
            }
        }

        return $this->visibleColumnService->getArrayConfig($fields, $freeFields, $currentUser->getColumnVisible());
    }

    public function getFieldTitle(string $fieldName): ?string {
        $title = null;
        foreach (self::REF_ARTICLE_FIELDS as $field) {
            if ($field['name'] === $fieldName) {
                $title = $field['title'] ?? null;
                break;
            }
        }
        return $title;
    }

    public function setStateAccordingToRelations(ReferenceArticle $reference,
                                                  PurchaseRequestLineRepository $purchaseRequestLineRepository,
                                                  ReceptionReferenceArticleRepository $receptionReferenceArticleRepository) {
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

}
