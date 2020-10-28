<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Alert;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Demande;
use App\Entity\FiltreRef;
use App\Entity\FiltreSup;
use App\Entity\InventoryCategory;
use App\Entity\LigneArticle;
use App\Entity\LigneArticlePreparation;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\CategorieCL;
use App\Entity\ArticleFournisseur;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use App\Repository\FiltreRefRepository;
use App\Repository\InventoryFrequencyRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\NonUniqueResultException;
use RuntimeException;
use Twig\Environment as Twig_Environment;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Article;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class RefArticleDataService {

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

    /**
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    private $entityManager;

    /**
     * @var RouterInterface
     */
    private $router;
    private $freeFieldService;
    private $articleFournisseurService;
    private $alertService;

    public function __construct(RouterInterface $router,
                                UserService $userService,
                                FreeFieldService $champLibreService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage,
                                ArticleFournisseurService $articleFournisseurService,
                                InventoryFrequencyRepository $inventoryFrequencyRepository,
                                AlertService $alertService) {
        $this->filtreRefRepository = $entityManager->getRepository(FiltreRef::class);
        $this->freeFieldService = $champLibreService;
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken() ? $tokenStorage->getToken()->getUser() : null;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->router = $router;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->articleFournisseurService = $articleFournisseurService;
        $this->alertService = $alertService;
    }

    /**
     * @param null $params
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getRefArticleDataByParams($params = null) {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $champs = $this->freeFieldService->getFreeFieldsById($this->entityManager, CategorieCL::REFERENCE_ARTICLE, CategoryType::ARTICLE);

        $userId = $this->user->getId();
        $filters = $this->filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $referenceArticleRepository->findByFiltersAndParams($filters, $params, $this->user, $champs);
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

    /**
     * @param ReferenceArticle $articleRef
     * @return array
     */
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

    /**
     * @param ReferenceArticle $refArticle
     * @param bool $isADemand
     * @param bool $preloadCategories
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getViewEditRefArticle($refArticle,
                                          $isADemand = false,
                                          $preloadCategories = true) {
        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
        $typeRepository = $this->entityManager->getRepository(Type::class);
        $inventoryCategoryRepository = $this->entityManager->getRepository(InventoryCategory::class);
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);

        $data = $this->getDataEditForRefArticle($refArticle);
        $articlesFournisseur = $articleFournisseurRepository->findByRefArticle($refArticle->getId());
        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);

        $categories = $preloadCategories
            ? $inventoryCategoryRepository->findAll()
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
                })
        ]);
    }

    /**
     * @param ReferenceArticle $refArticle
     * @param string[] $data
     * @param Utilisateur $user
     * @param FreeFieldService $champLibreService
     * @return RedirectResponse
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function editRefArticle($refArticle,
                                   $data,
                                   Utilisateur $user,
                                   FreeFieldService $champLibreService) {
        if(!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        $typeRepository = $this->entityManager->getRepository(Type::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $inventoryCategoryRepository = $this->entityManager->getRepository(InventoryCategory::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        //modification champsFixes
        $entityManager = $this->entityManager;
        $category = $inventoryCategoryRepository->find($data['categorie']);
        $price = max(0, $data['prix']);
        if(isset($data['reference'])) $refArticle->setReference($data['reference']);
        if(isset($data['frl'])) {
            foreach($data['frl'] as $frl) {
                $referenceArticleFournisseur = $frl['referenceFournisseur'];

                try {
                    $articleFournisseur = $this->articleFournisseurService->createArticleFournisseur([
                        'fournisseur' => $frl['fournisseur'],
                        'article-reference' => $refArticle,
                        'label' => $frl['labelFournisseur'],
                        'reference' => $referenceArticleFournisseur
                    ]);

                    $entityManager->persist($articleFournisseur);
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
            }
            $refArticle->setIsUrgent($data['urgence']);
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
            $refArticle->setNeedsMobileSync($data['mobileSync']);
        }

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

        $managers = (array)$data['managers'];

        $existingManagers = $refArticle->getManagers();
        foreach($existingManagers as $manager) {
            $refArticle->removeManager($manager);
        }

        foreach($managers as $manager) {
            $refArticle->addManager($userRepository->find($manager));
        }

        $entityManager->flush();
        //modification ou création des champsLibres

        $champLibreService->manageFreeFields($refArticle, $data, $entityManager);
        $entityManager->flush();
        //recup de la row pour insert datatable
        $rows = $this->dataRowRefArticle($refArticle);
        $response['success'] = true;
        $response['id'] = $refArticle->getId();
        $response['edit'] = $rows;
        return $response;
    }

    /**
     * @param ReferenceArticle $refArticle
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function dataRowRefArticle(ReferenceArticle $refArticle) {
        $categorieCLRepository = $this->entityManager->getRepository(CategorieCL::class);
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);

        $ffCategory = $categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::ARTICLE, $ffCategory);

        $row = [
            "id" => $refArticle->getId(),
            "label" => $refArticle->getLibelle() ?? "Non défini",
            "reference" => $refArticle->getReference() ?? "Non défini",
            "quantityType" => $refArticle->getTypeQuantite() ?? "Non défini",
            "type" => FormatHelper::type($refArticle->getType()),
            "location" => FormatHelper::location($refArticle->getEmplacement()),
            "availableQuantity" => $refArticle->getQuantiteDisponible() ?? 0,
            "stockQuantity" => $refArticle->getQuantiteStock() ?? 0,
            "emergencyComment" => $refArticle->getEmergencyComment(),
            "barCode" => $refArticle->getBarCode() ?? "Non défini",
            "comment" => $refArticle->getCommentaire(),
            "status" => FormatHelper::status($refArticle->getStatut()),
            "securityThreshold" => $refArticle->getLimitSecurity() ?? "Non défini",
            "warningThreshold" => $refArticle->getLimitWarning() ?? "Non défini",
            "unitPrice" => $refArticle->getPrixUnitaire(),
            "emergency" => FormatHelper::bool($refArticle->getIsUrgent()),
            "mobileSync" => FormatHelper::bool($refArticle->getNeedsMobileSync()),
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
                ->join(),
            "actions" => $this->templating->render('reference_article/datatableReferenceArticleRow.html.twig', [
                "reference_id" => $refArticle->getId(),
                "active" => $refArticle->getStatut() ? $refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF : 0,
            ]),
        ];

        foreach($freeFields as $freeField) {
            $row[$freeField["id"]] = $this->freeFieldService->serializeValue([
                "valeur" => $refArticle->getFreeFieldValue($freeField["id"]),
                "typage" => $freeField["typage"],
            ]);
        }

        return $row;
    }

    /**
     * @param array $data
     * @param ReferenceArticle $referenceArticle
     * @param Utilisateur $user
     * @param bool $fromNomade
     * @param EntityManagerInterface $entityManager
     * @param Demande $demande
     * @param FreeFieldService $champLibreService
     * @return bool
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function addRefToDemand($data,
                                   $referenceArticle,
                                   Utilisateur $user,
                                   bool $fromNomade,
                                   EntityManagerInterface $entityManager,
                                   Demande $demande,
                                   FreeFieldService $champLibreService) {
        $resp = true;
        $articleRepository = $entityManager->getRepository(Article::class);
        $ligneArticleRepository = $entityManager->getRepository(LigneArticle::class);
        // cas gestion quantité par référence
        if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            if($fromNomade || $ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                $ligneArticle = new LigneArticle();
                $ligneArticle
                    ->setReference($referenceArticle)
                    ->setDemande($demande)
                    ->setQuantite(max($data["quantity-to-pick"], 0)); // protection contre quantités négatives
                $entityManager->persist($ligneArticle);
                $demande->addLigneArticle($ligneArticle);
            } else {
                $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($referenceArticle, $demande);
                $ligneArticle->setQuantite($ligneArticle->getQuantite() + max($data["quantity-to-pick"], 0)); // protection contre quantités négatives
            }

            if(!$fromNomade) {
                $this->editRefArticle($referenceArticle, $data, $user, $champLibreService);
            }
        } else if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            if($fromNomade || $this->userService->hasParamQuantityByRef()) {
                if($fromNomade || $ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                    $ligneArticle = new LigneArticle();
                    $ligneArticle
                        ->setQuantite(max($data["quantity-to-pick"], 0))// protection contre quantités négatives
                        ->setReference($referenceArticle)
                        ->setDemande($demande)
                        ->setToSplit(true);
                    $entityManager->persist($ligneArticle);
                    $demande->addLigneArticle($ligneArticle);
                } else {
                    $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemandeAndToSplit($referenceArticle, $demande);
                    $ligneArticle->setQuantite($ligneArticle->getQuantite() + max($data["quantity-to-pick"], 0));
                }
            } else {
                $article = $articleRepository->find($data['article']);
                /** @var Article $article */
                $article
                    ->setDemande($demande)
                    ->setQuantiteAPrelever(max($data["quantity-to-pick"], 0)); // protection contre quantités négatives
                $resp = 'article';
            }
        } else {
            $resp = false;
        }
        return $resp;
    }

    /**
     * @param null $counter
     * @return string
     * @throws Exception
     */
    public function generateBarCode($counter = null) {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $now = new \DateTime('now');
        $dateCode = $now->format('ym');

        if(!isset($counter)) {
            $highestBarCode = $referenceArticleRepository->getHighestBarCodeByDateCode($dateCode);
            $highestCounter = $highestBarCode ? (int)substr($highestBarCode, 7, 8) : 0;
            $counter = sprintf('%08u', $highestCounter + 1);
        }

        return ReferenceArticle::BARCODE_PREFIX . $dateCode . $counter;
    }

    public function getAlerteDataByParams($params, $user) {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $alertRepository = $this->entityManager->getRepository(Alert::class);

        $filtresAlerte = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ALERTE, $user);

        $results = $alertRepository->getAlertDataByParams($params, $filtresAlerte);
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

    /**
     * @param Alert $alert
     * @return array
     */
    public function dataRowAlerteRef(Alert $alert) {
        if($entity = $alert->getReference()) {
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
            $reference = $entity->getReference();
            $code = $entity->getBarCode();
            $label = $entity->getLabel();
            $expiry = $entity->getExpiryDate() ? $entity->getExpiryDate()->format("d/m/Y H:i") : "Non défini";
            $managers = Stream::from($entity->getArticleFournisseur()->getReferenceArticle()->getManagers())
                ->map(function(Utilisateur $utilisateur) {
                    return $utilisateur->getUsername();
                })->toArray();
            $managers = count($managers) ? implode(",", $managers) : 'Non défini';
        } else {
            throw new RuntimeException("Invalid alert");
        }

        return [
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
        ];
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @return array ['code' => string, 'labels' => string[]]
     */
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

    /**
     * @param ReferenceArticle $referenceArticle
     * @param bool $fromCommand
     */
    public function updateRefArticleQuantities(ReferenceArticle $referenceArticle, bool $fromCommand = false) {
        $this->updateStockQuantity($referenceArticle);
        $this->updateReservedQuantity($referenceArticle, $fromCommand);
        $referenceArticle->setQuantiteDisponible($referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee());
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @return void
     */
    private function updateStockQuantity(ReferenceArticle $referenceArticle): void {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $referenceArticle->setQuantiteStock($referenceArticleRepository->getStockQuantity($referenceArticle));
        }
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @param bool $fromCommand
     * @return void
     */
    private function updateReservedQuantity(ReferenceArticle $referenceArticle, bool $fromCommand = false): void {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $referenceArticle->setQuantiteReservee($referenceArticleRepository->getReservedQuantity($referenceArticle));
        } else {
            $totalReservedQuantity = 0;
            $lignesArticlePrepaEnCours = $referenceArticle
                ->getLigneArticlePreparations()
                ->filter(function(LigneArticlePreparation $ligneArticlePreparation) use ($fromCommand) {
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
             * @var LigneArticlePreparation $ligneArticlePrepaEnCours
             */
            foreach($lignesArticlePrepaEnCours as $ligneArticlePrepaEnCours) {
                $totalReservedQuantity += $ligneArticlePrepaEnCours->getQuantite();
            }
            $referenceArticle->setQuantiteReservee($totalReservedQuantity);
        }
    }

    /**
     * Create or delete security and limit alerts.
     *
     * @param ReferenceArticle $reference
     * @throws Exception
     */
    public function treatAlert(ReferenceArticle $reference): void {
        if($reference->getStatut()->getNom() === ReferenceArticle::STATUT_INACTIF) {
            foreach($reference->getAlerts() as $alert) {
                $this->entityManager->remove($alert);
            }
        } else {
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));
            $ar = $this->entityManager->getRepository(Alert::class);

            if($reference->getLimitSecurity() !== null && $reference->getLimitSecurity() >= $reference->getQuantiteDisponible()) {
                $type = Alert::SECURITY;
            } else if($reference->getLimitWarning() !== null && $reference->getLimitWarning() >= $reference->getQuantiteDisponible()) {
                $type = Alert::WARNING;
            }

            $existing = $ar->findForReference($reference, [Alert::SECURITY, Alert::WARNING]);

            //more than 1 security/warning alert is an invalid state -> reset
            if(count($existing) > 1) {
                foreach($existing as $remove) {
                    $this->entityManager->remove($remove);
                }

                $existing = null;
            } else if(count($existing) == 1) {
                $existing = $existing[0];
            }

            if($existing && (!isset($type) || $this->isDifferentThresholdType($existing, $type))) {
                $this->entityManager->remove($existing);
                $existing = null;
            }

            if(isset($type) && !$existing) {
                $alert = new Alert();
                $alert->setReference($reference);
                $alert->setType($type);
                $alert->setDate($now);

                $this->entityManager->persist($alert);

                $this->alertService->sendThresholdMails($reference);
            }
        }
    }

    private function isDifferentThresholdType($alert, $type) {
        return $alert->getType() == Alert::WARNING && $type == Alert::SECURITY ||
            $alert->getType() == Alert::SECURITY && $type == Alert::WARNING;
    }

}
