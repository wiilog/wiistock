<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 28/03/2019
 * Time: 16:34
 */

namespace App\Service;


use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\InventoryCategory;
use App\Entity\LigneArticle;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Entity\CategorieCL;
use App\Entity\ArticleFournisseur;
use App\Repository\DemandeRepository;
use App\Repository\FiltreRefRepository;
use App\Repository\InventoryFrequencyRepository;
use App\Repository\CategorieCLRepository;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\NoResultException;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\NonUniqueResultException;
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
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

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


    public function __construct(DemandeRepository $demandeRepository,
                                RouterInterface $router,
                                UserService $userService,
                                CategorieCLRepository $categorieCLRepository,
                                EntityManagerInterface $entityManager,
                                FiltreRefRepository $filtreRefRepository,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage,
                                InventoryFrequencyRepository $inventoryFrequencyRepository)
    {
        $this->filtreRefRepository = $filtreRefRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken() ? $tokenStorage->getToken()->getUser() : null;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->router = $router;
        $this->demandeRepository = $demandeRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
    }

    /**
     * @param null $params
     * @return array
     * @throws DBALException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getRefArticleDataByParams($params = null)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $userId = $this->user->getId();
        $filters = $this->filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $referenceArticleRepository->findByFiltersAndParams($filters, $params, $this->user);
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
        $type = $articleRef->getType();

        $valeurChampLibreRepository = $this->entityManager->getRepository(ValeurChampLibre::class);

        if ($type) {
            $valeurChampLibre = $valeurChampLibreRepository->getByRefArticleAndType($articleRef->getId(), $type->getId());
        } else {
            $valeurChampLibre = [];
        }
        $totalQuantity = $articleRef->getQuantiteDisponible();
        return $data = [
            'listArticlesFournisseur' => array_reduce($articleRef->getArticlesFournisseur()->toArray(),
                function (array $carry, ArticleFournisseur $articleFournisseur) {
                    $carry[] = [
                        'fournisseurRef' => $articleFournisseur->getFournisseur()->getCodeReference(),
                        'label' => $articleFournisseur->getLabel(),
                        'fournisseurName' => $articleFournisseur->getFournisseur()->getNom(),
                        'quantity' => array_reduce($articleFournisseur->getArticles()->toArray(), function (int $carry, Article $article) {
                            return $article->getStatut()->getNom() === Article::STATUT_ACTIF
                                ? $carry + $article->getQuantite()
                                : $carry;
                        }, 0)
                    ];
                    return $carry;
                }, []),
            'totalQuantity' => $totalQuantity,
            'valeurChampLibre' => $valeurChampLibre
        ];
    }

    /**
     * @param ReferenceArticle $refArticle
     * @param bool $isADemand
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getViewEditRefArticle($refArticle, $isADemand = false)
    {
        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
        $typeRepository = $this->entityManager->getRepository(Type::class);
        $champLibreRepository = $this->entityManager->getRepository(ChampLibre::class);
        $valeurChampLibreRepository = $this->entityManager->getRepository(ValeurChampLibre::class);
        $inventoryCategoryRepository = $this->entityManager->getRepository(InventoryCategory::class);

        $data = $this->getDataEditForRefArticle($refArticle);
        $articlesFournisseur = $articleFournisseurRepository->findByRefArticle($refArticle->getId());
        $types = $typeRepository->findByCategoryLabel(CategoryType::ARTICLE);

        $categories = $inventoryCategoryRepository->findAll();
        $typeChampLibre = [];
        foreach ($types as $type) {
            $champsLibresComplet = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $champsLibres = [];

            foreach ($champsLibresComplet as $champLibre) {
                $valeurChampRefArticle = $valeurChampLibreRepository->findOneByRefArticleAndChampLibre($refArticle->getId(), $champLibre);
                $champsLibres[] = [
                    'id' => $champLibre->getId(),
                    'label' => $champLibre->getLabel(),
                    'typage' => $champLibre->getTypage(),
                    'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                    'defaultValue' => $champLibre->getDefaultValue(),
                    'valeurChampLibre' => $valeurChampRefArticle,
                ];
            }
            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
        }

        $view = $this->templating->render('reference_article/modalRefArticleContent.html.twig', [
            'articleRef' => $refArticle,
            'statut' => $refArticle->getStatut()->getNom(),
            'valeurChampLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
            'typeChampsLibres' => $typeChampLibre,
            'articlesFournisseur' => ($data['listArticlesFournisseur']),
            'totalQuantity' => $data['totalQuantity'],
            'articles' => $articlesFournisseur,
            'categories' => $categories,
            'isADemand' => $isADemand
        ]);
        return $view;
    }

    /**
     * @param ReferenceArticle $refArticle
     * @param string[] $data
     * @param Utilisateur $user
     * @return RedirectResponse
     * @throws DBALException
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function editRefArticle($refArticle, $data, Utilisateur $user)
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        $typeRepository = $this->entityManager->getRepository(Type::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
        $champLibreRepository = $this->entityManager->getRepository(ChampLibre::class);
        $valeurChampLibreRepository = $this->entityManager->getRepository(ValeurChampLibre::class);
        $inventoryCategoryRepository = $this->entityManager->getRepository(InventoryCategory::class);


        //vérification des champsLibres obligatoires
        $requiredEdit = true;
        $type = $typeRepository->find(intval($data['type']));
        $category = $inventoryCategoryRepository->find($data['categorie']);
        $price = max(0, $data['prix']);
        $emplacement = $emplacementRepository->find(intval($data['emplacement']));
        $CLRequired = $champLibreRepository->getByTypeAndRequiredEdit($type);
        foreach ($CLRequired as $CL) {
            if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                $requiredEdit = false;
            }
        }

        if ($requiredEdit) {
            //modification champsFixes
            $entityManager = $this->entityManager;
            if (isset($data['reference'])) $refArticle->setReference($data['reference']);
            if (isset($data['frl'])) {
                foreach ($data['frl'] as $frl) {
                    $fournisseurId = explode(';', $frl)[0];
                    $ref = explode(';', $frl)[1];
                    $label = explode(';', $frl)[2];
                    $fournisseur = $fournisseurRepository->find(intval($fournisseurId));
                    $articleFournisseur = new ArticleFournisseur();
                    $articleFournisseur
                        ->setReferenceArticle($refArticle)
                        ->setFournisseur($fournisseur)
                        ->setReference($ref)
                        ->setLabel($label);
                    $entityManager->persist($articleFournisseur);
                }
            }

            if (isset($data['categorie'])) $refArticle->setCategory($category);
            if (isset($data['urgence'])) {
                if ($data['urgence'] && $data['urgence'] !== $refArticle->getIsUrgent()) {
                    $refArticle->setUserThatTriggeredEmergency($user);
                } else if (!$data['urgence']) {
                    $refArticle->setUserThatTriggeredEmergency(null);
                }
                $refArticle->setIsUrgent($data['urgence']);
            }
            if (isset($data['prix'])) $refArticle->setPrixUnitaire($price);
            if (isset($data['emplacement'])) $refArticle->setEmplacement($emplacement);
            if (isset($data['libelle'])) $refArticle->setLibelle($data['libelle']);
            if (isset($data['commentaire'])) $refArticle->setCommentaire($data['commentaire']);
            if (isset($data['limitWarning'])) $refArticle->setLimitWarning($data['limitWarning']);
            if ($data['emergency-comment-input']) {
                $refArticle->setEmergencyComment($data['emergency-comment-input']);
            }
            if (isset($data['limitSecurity'])) $refArticle->setLimitSecurity($data['limitSecurity']);
            if (isset($data['quantite'])) $refArticle->setQuantiteStock(max(intval($data['quantite']), 0)); // protection contre quantités négatives
            if (isset($data['statut'])) {
                $statut = $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, $data['statut']);
                if ($statut) $refArticle->setStatut($statut);
            }
            if (isset($data['type'])) {
                $type = $typeRepository->find(intval($data['type']));
                if ($type) $refArticle->setType($type);
            }

            $entityManager->flush();
            //modification ou création des champsLibres
            $champsLibresKey = array_keys($data);
            foreach ($champsLibresKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $champLibreRepository->find($champ);
                    $valeurChampLibre = $valeurChampLibreRepository->findOneByRefArticleAndChampLibre($refArticle->getId(), $champLibre);
                    // si la valeur n'existe pas, on la crée
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampLibre();
                        $valeurChampLibre
                            ->addArticleReference($refArticle)
                            ->setChampLibre($champLibreRepository->find($champ));
                        $entityManager->persist($valeurChampLibre);
                    }
                    $valeurChampLibre->setValeur(is_array($data[$champ]) ? implode(";", $data[$champ]) : $data[$champ]);
                    $entityManager->flush();
                }
            }
            //recup de la row pour insert datatable
            $rows = $this->dataRowRefArticle($refArticle);
            $response['success'] = true;
            $response['id'] = $refArticle->getId();
            $response['edit'] = $rows;
        } else {
            $response['success'] = false;
            $response['msg'] = "Tous les champs obligatoires n'ont pas été renseignés.";
        }
        return $response;
    }


    /**
     * @param ReferenceArticle $refArticle
     * @return array
     * @throws LoaderError
	 * @throws RuntimeError
     * @throws SyntaxError
     * @throws DBALException
     */
    public function dataRowRefArticle(ReferenceArticle $refArticle)
    {
        $valeurChampLibreRepository = $this->entityManager->getRepository(ValeurChampLibre::class);
        $rows = $valeurChampLibreRepository->getLabelCLAndValueByRefArticle($refArticle);
        $rowCL = [];
        foreach ($rows as $row) {
            $rowCL[$row['label']] = $row['valeur'];
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
            "Seuil de sécurité" => $refArticle->getLimitSecurity() ?? "Non défini",
            "Seuil d'alerte" => $refArticle->getLimitWarning() ?? "Non défini",
            "Prix unitaire" => $refArticle->getPrixUnitaire() ?? "",
			"Dernier inventaire" => $refArticle->getDateLastInventory() ? $refArticle->getDateLastInventory()->format('d/m/Y') : '',
            "Actions" => $this->templating->render('reference_article/datatableReferenceArticleRow.html.twig', [
                'idRefArticle' => $refArticle->getId(),
                'isActive' => $refArticle->getStatut() ? $refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF : 0,
            ]),
        ];

        $rows = array_merge($rowCL, $rowCF);
        return $rows;
    }

    /**
     * @param array $data
     * @param ReferenceArticle $referenceArticle
     * @param Utilisateur $user
     * @return bool
     * @throws DBALException
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function addRefToDemand($data, $referenceArticle, Utilisateur $user)
    {
        $resp = true;

        $demandeRepository = $this->entityManager->getRepository(Demande::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $ligneArticleRepository = $this->entityManager->getRepository(LigneArticle::class);

        $demande = $demandeRepository->find($data['livraison']);

        // cas gestion quantité par référence
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            if ($ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                $ligneArticle = new LigneArticle();
                $ligneArticle
                    ->setReference($referenceArticle)
                    ->setDemande($demande)
                    ->setQuantite(max($data["quantitie"], 0)); // protection contre quantités négatives
                $this->entityManager->persist($ligneArticle);
            } else {
                $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($referenceArticle, $demande);
                $ligneArticle->setQuantite($ligneArticle->getQuantite() + max($data["quantitie"], 0)); // protection contre quantités négatives
            }
            $this->editRefArticle($referenceArticle, $data, $user);

            // cas gestion quantité par article
        } elseif ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            if ($this->userService->hasParamQuantityByRef()) {
                if ($ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                    $ligneArticle = new LigneArticle();
                    $ligneArticle
                        ->setQuantite(max($data["quantitie"], 0))// protection contre quantités négatives
                        ->setReference($referenceArticle)
                        ->setDemande($demande)
                        ->setToSplit(true);
                    $this->entityManager->persist($ligneArticle);
                } else {
                    $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemandeAndToSplit($referenceArticle, $demande);
                    $ligneArticle->setQuantite($ligneArticle->getQuantite() + max($data["quantitie"], 0));
                }
            } else {
                $article = $articleRepository->find($data['article']);
                /** @var Article $article */
                $article
                    ->setDemande($demande)
                    ->setQuantiteAPrelever(max($data["quantitie"], 0)); // protection contre quantités négatives
                $resp = 'article';
            }
        } else {
            $resp = false;
        }

        $this->entityManager->flush();
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

        $newBarcode = ReferenceArticle::BARCODE_PREFIX . $dateCode . $counter;

        return $newBarcode;
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
            'QuantiteStock' => $quantity,
            'typeQuantite' => $referenceArticle['typeQuantite'],
            'Type' => $referenceArticle['type'],
            'Date d\'alerte' => $referenceArticle['dateEmergencyTriggered']->format('d/m/Y H:i'),
            'SeuilSecurite' => (($referenceArticle['limitSecurity'] || $referenceArticle['limitSecurity'] == '0') ? $referenceArticle['limitSecurity'] : 'Non défini'),
            'SeuilAlerte' => (($referenceArticle['limitWarning'] || $referenceArticle['limitWarning'] == '0') ? $referenceArticle['limitWarning'] : 'Non défini'),
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
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function updateRefArticleQuantities(ReferenceArticle $referenceArticle) {
        $this->updateStockQuantity($referenceArticle);
        $this->updateReservedQuantity($referenceArticle);
        $referenceArticle->setQuantiteDisponible($referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee());
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @return void
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function updateStockQuantity(ReferenceArticle $referenceArticle): void {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $referenceArticle->setQuantiteStock($referenceArticleRepository->getStockQuantity($referenceArticle));
        }
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @return void
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function updateReservedQuantity(ReferenceArticle $referenceArticle): void {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $referenceArticle->setQuantiteReservee($referenceArticleRepository->getReservedQuantity($referenceArticle));
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
