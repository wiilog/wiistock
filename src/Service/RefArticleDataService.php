<?php

namespace App\Service;


use App\Entity\Action;
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
use App\Repository\FiltreRefRepository;
use App\Repository\InventoryFrequencyRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\NonUniqueResultException;
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

class RefArticleDataService
{

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


    public function __construct(RouterInterface $router,
                                UserService $userService,
                                FreeFieldService $champLibreService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage,
                                ArticleFournisseurService $articleFournisseurService,
                                InventoryFrequencyRepository $inventoryFrequencyRepository)
    {
        $this->filtreRefRepository = $entityManager->getRepository(FiltreRef::class);
        $this->freeFieldService = $champLibreService;
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken() ? $tokenStorage->getToken()->getUser() : null;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->router = $router;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->articleFournisseurService = $articleFournisseurService;
    }

    /**
     * @param null $params
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getRefArticleDataByParams($params = null)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $champs = $this->freeFieldService->getFreeFieldLabelToId($this->entityManager, CategorieCL::REFERENCE_ARTICLE, CategoryType::ARTICLE);

        $userId = $this->user->getId();
        $filters = $this->filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $referenceArticleRepository->findByFiltersAndParams($filters, $params, $this->user, $champs);
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


    /**
     * @param ReferenceArticle $articleRef
     * @return array
     */
    public function getDataEditForRefArticle($articleRef)
    {
        $totalQuantity = $articleRef->getQuantiteDisponible();
        return $data = [
            'listArticlesFournisseur' => array_reduce($articleRef->getArticlesFournisseur()->toArray(),
                function (array $carry, ArticleFournisseur $articleFournisseur) use ($articleRef) {
                    $articles = $articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE
                        ? $articleFournisseur->getArticles()->toArray()
                        : [];
                    $carry[] = [
                        'reference' => $articleFournisseur->getReference(),
                        'label' => $articleFournisseur->getLabel(),
                        'fournisseurCode' => $articleFournisseur->getFournisseur()->getCodeReference(),
                        'quantity' => array_reduce($articles, function (int $carry, Article $article) {
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
                                          $preloadCategories = true)
    {
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
        foreach ($types as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $typeChampLibre[] = [
                'typeLabel' =>  $type->getLabel(),
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
            'Synchronisation nomade' =>$refArticle->getNeedsMobileSync(),
            'statut' => $refArticle->getStatut()->getNom(),
            'typeChampsLibres' => $typeChampLibre,
            'articlesFournisseur' => $data['listArticlesFournisseur'],
            'totalQuantity' => $data['totalQuantity'],
            'articles' => $articlesFournisseur,
            'categories' => $categories,
            'isADemand' => $isADemand
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
                                   FreeFieldService $champLibreService)
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        $typeRepository = $this->entityManager->getRepository(Type::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $inventoryCategoryRepository = $this->entityManager->getRepository(InventoryCategory::class);

        //modification champsFixes
        $entityManager = $this->entityManager;
        $category = $inventoryCategoryRepository->find($data['categorie']);
        $price = max(0, $data['prix']);
        if (isset($data['reference'])) $refArticle->setReference($data['reference']);
        if (isset($data['frl'])) {
            foreach ($data['frl'] as $frl) {
                $referenceArticleFournisseur = $frl['referenceFournisseur'];

                try {
                    $articleFournisseur = $this->articleFournisseurService->createArticleFournisseur([
                        'fournisseur' => $frl['fournisseur'],
                        'article-reference' => $refArticle,
                        'label' => $frl['labelFournisseur'],
                        'reference' => $referenceArticleFournisseur
                    ]);

                    $entityManager->persist($articleFournisseur);
                } catch (Exception $exception) {
                    if ($exception->getMessage() === ArticleFournisseurService::ERROR_REFERENCE_ALREADY_EXISTS) {
                        $response['success'] = false;
                        $response['msg'] = "La référence '$referenceArticleFournisseur' existe déjà pour un article fournisseur.";
                        return $response;
                    }
                }
            }
        }

        if (isset($data['categorie'])) {
            $refArticle->setCategory($category);
        }

        if (isset($data['urgence'])) {
            if ($data['urgence'] && $data['urgence'] !== $refArticle->getIsUrgent()) {
                $refArticle->setUserThatTriggeredEmergency($user);
            } else if (!$data['urgence']) {
                $refArticle->setUserThatTriggeredEmergency(null);
            }
            $refArticle->setIsUrgent($data['urgence']);
        }

        if (isset($data['prix'])) {
            $refArticle->setPrixUnitaire($price);
        }

        if (isset($data['libelle'])) {
            $refArticle->setLibelle($data['libelle']);
        }

        if (isset($data['commentaire'])) {
            $refArticle->setCommentaire($data['commentaire']);
        }

        if (isset($data['mobileSync'])) {
            $refArticle->setNeedsMobileSync($data['mobileSync']);
        }

        $refArticle->setLimitWarning((empty($data['limitWarning']) && $data['limitWarning'] !== 0 && $data['limitWarning'] !== '0') ? null : intval($data['limitWarning']));
        $refArticle->setLimitSecurity((empty($data['limitSecurity']) && $data['limitSecurity'] !== 0 && $data['limitSecurity'] !== '0') ? null : intval($data['limitSecurity']));

        if ($data['emergency-comment-input']) {
            $refArticle->setEmergencyComment($data['emergency-comment-input']);
        }

        if (isset($data['statut'])) {
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, $data['statut']);
            if ($statut) {
                $refArticle->setStatut($statut);
            }
        }

        if (isset($data['type'])) {
            $type = $typeRepository->find(intval($data['type']));
            if ($type) $refArticle->setType($type);
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
    public function dataRowRefArticle(ReferenceArticle $refArticle)
    {
        $categorieCLRepository = $this->entityManager->getRepository(CategorieCL::class);
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);

        $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE);

        $category = CategoryType::ARTICLE;
        $champs = $champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        $rowCL = [];
        /** @var FreeField $champ */
        foreach ($champs as $champ) {
            $rowCL[strval($champ['label'])] = $this->freeFieldService->serializeValue([
                'valeur' => $refArticle->getFreeFieldValue($champ['id']),
                "typage" => $champ['typage'],
            ]);
        }

        $availableQuantity = $refArticle->getQuantiteDisponible();
        $quantityStock = $refArticle->getQuantiteStock();

        $rowCF = [
            "id" => $refArticle->getId(),
            "Libellé" => $refArticle->getLibelle() ? $refArticle->getLibelle() : 'Non défini',
            "Référence" => $refArticle->getReference() ? $refArticle->getReference() : 'Non défini',
            "Type" => ($refArticle->getType() ? $refArticle->getType()->getLabel() : ""),
            "Emplacement" => ($refArticle->getEmplacement() ? $refArticle->getEmplacement()->getLabel() : ""),
            "Quantité disponible" => $availableQuantity ?? 0,
            "Quantité stock" => $quantityStock ?? 0,
            'Commentaire d\'urgence' => $refArticle->getEmergencyComment(),
            "Code barre" => $refArticle->getBarCode() ?? 'Non défini',
            "Commentaire" => $refArticle->getCommentaire() ?? '',
            "Statut" => $refArticle->getStatut() ? $refArticle->getStatut()->getNom() : "",
            "limitSecurity" => $refArticle->getLimitSecurity() ?? "Non défini",
            "limitWarning" => $refArticle->getLimitWarning() ?? "Non défini",
            "Prix unitaire" => $refArticle->getPrixUnitaire() ?? "",
            'Urgence' => $refArticle->getIsUrgent() ? 'Oui' : 'Non',
            'Synchronisation nomade' => $refArticle->getNeedsMobileSync() ? 'Oui' : 'Non',
            "Dernier inventaire" => $refArticle->getDateLastInventory() ? $refArticle->getDateLastInventory()->format('d/m/Y') : '',
            "Actions" => $this->templating->render('reference_article/datatableReferenceArticleRow.html.twig', [
                'idRefArticle' => $refArticle->getId(),
                'isActive' => $refArticle->getStatut() ? $refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF : 0,
            ]),
        ];
        return array_merge($rowCL, $rowCF);
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
                                   FreeFieldService $champLibreService)
    {
        $resp = true;
        $articleRepository = $entityManager->getRepository(Article::class);
        $ligneArticleRepository = $entityManager->getRepository(LigneArticle::class);
        // cas gestion quantité par référence
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            if ($fromNomade || $ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
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

            if (!$fromNomade) {
                $this->editRefArticle($referenceArticle, $data, $user, $champLibreService);
            }
        }
        else if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            if ($fromNomade || $this->userService->hasParamQuantityByRef()) {
                if ($fromNomade || $ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
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
    public function generateBarCode($counter = null)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $now = new \DateTime('now');
        $dateCode = $now->format('ym');

        if (!isset($counter)) {
            $highestBarCode = $referenceArticleRepository->getHighestBarCodeByDateCode($dateCode);
            $highestCounter = $highestBarCode ? (int)substr($highestBarCode, 7, 8) : 0;
            $counter = sprintf('%08u', $highestCounter + 1);
        }

        return ReferenceArticle::BARCODE_PREFIX . $dateCode . $counter;
    }

    public function getAlerteDataByParams($params, $user)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $filtresAlerte = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ALERTE, $user);

        $results = $referenceArticleRepository->getAlertDataByParams($params, $filtresAlerte);
        $referenceArticles = $results['data'];

        $rows = [];
        foreach ($referenceArticles as $referenceArticle) {
            $rows[] = $this->dataRowAlerteRef($referenceArticle);
        }
        return [
            'data' => $rows,
            'recordsFiltered' => $results['count'],
            'recordsTotal' => $results['total'],
        ];
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowAlerteRef($referenceArticle)
    {
        $quantity = $referenceArticle['quantiteDisponible'];
        $row = [
            'Référence' => ($referenceArticle['reference'] ? $referenceArticle['reference'] : 'Non défini'),
            'Label' => ($referenceArticle['libelle'] ? $referenceArticle['libelle'] : 'Non défini'),
            'Quantité stock' => $quantity,
            'typeQuantite' => $referenceArticle['typeQuantite'],
            'Type' => $referenceArticle['type'],
            'Date d\'alerte' => $referenceArticle['dateEmergencyTriggered']->format('d/m/Y H:i'),
            'limitSecurity' => (($referenceArticle['limitSecurity'] || $referenceArticle['limitSecurity'] == '0') ? $referenceArticle['limitSecurity'] : 'Non défini'),
            'limitWarning' => (($referenceArticle['limitWarning'] || $referenceArticle['limitWarning'] == '0') ? $referenceArticle['limitWarning'] : 'Non défini'),
            'Actions' => $this->templating->render('alerte_reference/datatableAlerteRow.html.twig', [
                'quantite' => $quantity,
                'seuilSecu' => $referenceArticle['limitSecurity'],
                'seuilAlerte' => $referenceArticle['limitWarning'],
            ]),
        ];
        return $row;
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @return array ['code' => string, 'labels' => string[]]
     */
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

    /**
     * @param ReferenceArticle $referenceArticle
     * @param bool $fromCommand
     */
    public function updateRefArticleQuantities(ReferenceArticle $referenceArticle, bool $fromCommand = false)
    {
        $this->updateStockQuantity($referenceArticle);
        $this->updateReservedQuantity($referenceArticle, $fromCommand);
        $referenceArticle->setQuantiteDisponible($referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee());
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @return void
     */
    private function updateStockQuantity(ReferenceArticle $referenceArticle): void
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $referenceArticle->setQuantiteStock($referenceArticleRepository->getStockQuantity($referenceArticle));
        }
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @param bool $fromCommand
     * @return void
     */
    private function updateReservedQuantity(ReferenceArticle $referenceArticle, bool $fromCommand = false): void
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $referenceArticle->setQuantiteReservee($referenceArticleRepository->getReservedQuantity($referenceArticle));
        } else {
            $totalReservedQuantity = 0;
            $lignesArticlePrepaEnCours = $referenceArticle
                ->getLigneArticlePreparations()
                ->filter(function (LigneArticlePreparation $ligneArticlePreparation) use ($fromCommand) {
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
            foreach ($lignesArticlePrepaEnCours as $ligneArticlePrepaEnCours) {
                $totalReservedQuantity += $ligneArticlePrepaEnCours->getQuantite();
            }
            $referenceArticle->setQuantiteReservee($totalReservedQuantity);
        }
    }

    /**
     * @param ReferenceArticle $refArticle
     * @throws Exception
     */
    public function treatAlert(ReferenceArticle $refArticle): void
    {
        $calculedAvailableQuantity = $refArticle->getQuantiteDisponible();
        $limitToCompare = empty($refArticle->getLimitWarning())
            ? (empty($refArticle->getLimitSecurity())
                ? 0
                : $refArticle->getLimitSecurity())
            : $refArticle->getLimitWarning();
        $status = $refArticle->getStatut();
        $limitToCompare = intval($limitToCompare);
        if ($limitToCompare > 0) {
            if (!isset($status)
                || ($status->getNom() === ReferenceArticle::STATUT_INACTIF)
                || ($refArticle->getDateEmergencyTriggered()
                    && ($calculedAvailableQuantity > $limitToCompare))) {
                $refArticle->setDateEmergencyTriggered(null);
            } else if (!$refArticle->getDateEmergencyTriggered() && $calculedAvailableQuantity <= $limitToCompare) {
                $refArticle->setDateEmergencyTriggered(new DateTime('now', new DateTimeZone("Europe/Paris")));
            }
        }
    }

}
