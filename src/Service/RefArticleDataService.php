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
use App\Entity\FiltreSup;
use App\Entity\LigneArticle;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampLibre;
use App\Entity\CategorieCL;
use App\Entity\ArticleFournisseur;

use App\Repository\ArticleFournisseurRepository;
use App\Repository\ArticleRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\DemandeRepository;
use App\Repository\FiltreRefRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\InventoryCategoryRepository;
use App\Repository\InventoryFrequencyRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\FournisseurRepository;
use App\Repository\EmplacementRepository;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\DBALException;
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
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;
    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

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
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

    /**
     * @var object|string
     */
    private $user;

    /**
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    /**
     * @var InventoryCategoryRepository
     */
    private $inventoryCategoryRepository;

    private $em;

    /**
     * @var RouterInterface
     */
    private $router;


    public function __construct(DemandeRepository $demandeRepository,
                                ArticleRepository $articleRepository,
                                LigneArticleRepository $ligneArticleRepository,
                                EmplacementRepository $emplacementRepository,
                                RouterInterface $router,
                                UserService $userService,
                                ArticleFournisseurRepository $articleFournisseurRepository,
                                FournisseurRepository $fournisseurRepository,
                                CategorieCLRepository $categorieCLRepository,
                                TypeRepository $typeRepository,
                                StatutRepository $statutRepository,
                                EntityManagerInterface $em,
                                ValeurChampLibreRepository $valeurChampLibreRepository,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ChampLibreRepository $champLibreRepository,
                                FiltreRefRepository $filtreRefRepository,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage,
                                InventoryCategoryRepository $inventoryCategoryRepository,
                                FiltreSupRepository $filtreSupRepository,
                                InventoryFrequencyRepository $inventoryFrequencyRepository)
    {
        $this->filtreSupRepository = $filtreSupRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->statutRepository = $statutRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
        $this->filtreRefRepository = $filtreRefRepository;
        $this->typeRepository = $typeRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->templating = $templating;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->user = $tokenStorage->getToken() ? $tokenStorage->getToken()->getUser() : null;
        $this->em = $em;
        $this->userService = $userService;
        $this->router = $router;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->demandeRepository = $demandeRepository;
        $this->inventoryCategoryRepository = $inventoryCategoryRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
    }

    /**
     * @param null $params
     * @return array
     */
    public function getRefArticleDataByParams($params = null)
    {
        $userId = $this->user->getId();
        $filters = $this->filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $this->referenceArticleRepository->findByFiltersAndParams($filters, $params, $this->user);

        $refs = $queryResult['data'];

        $rows = [];
        foreach ($refs as $refArticle) {
            $rows[] = $this->dataRowRefArticle(is_array($refArticle) ? $refArticle[0] : $refArticle);
        }
        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $this->referenceArticleRepository->countAll()
        ];
    }


	/**
	 * @param ReferenceArticle $articleRef
	 * @return array
	 * @throws DBALException
	 */
    public function getDataEditForRefArticle($articleRef)
    {
        $type = $articleRef->getType();
        if ($type) {
            $valeurChampLibre = $this->valeurChampLibreRepository->getByRefArticleAndType($articleRef->getId(), $type->getId());
        } else {
            $valeurChampLibre = [];
        }
        $totalQuantity = $this->getAvailableQuantityForRef($articleRef);
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
	 * @throws DBALException
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function getViewEditRefArticle($refArticle, $isADemand = false)
    {
        $data = $this->getDataEditForRefArticle($refArticle);
        $articlesFournisseur = $this->articleFournisseurRepository->findByRefArticle($refArticle->getId());
        $types = $this->typeRepository->findByCategoryLabel(CategoryType::ARTICLE);

        $categories = $this->inventoryCategoryRepository->findAll();
        $typeChampLibre = [];
        foreach ($types as $type) {
            $champsLibresComplet = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $champsLibres = [];

            foreach ($champsLibresComplet as $champLibre) {
                $valeurChampRefArticle = $this->valeurChampLibreRepository->findOneByRefArticleAndChampLibre($refArticle->getId(), $champLibre);
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
	 * @return RedirectResponse
	 * @throws DBALException
	 * @throws LoaderError
	 * @throws NonUniqueResultException
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function editRefArticle($refArticle, $data)
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        //vérification des champsLibres obligatoires
        $requiredEdit = true;
        $type = $this->typeRepository->find(intval($data['type']));
        $category = $this->inventoryCategoryRepository->find($data['categorie']);
        $price = max(0, $data['prix']);
        $emplacement = $this->emplacementRepository->find(intval($data['emplacement']));
        $CLRequired = $this->champLibreRepository->getByTypeAndRequiredEdit($type);
        foreach ($CLRequired as $CL) {
            if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                $requiredEdit = false;
            }
        }

        if ($requiredEdit) {
            //modification champsFixes
            $entityManager = $this->em;
            if (isset($data['reference'])) $refArticle->setReference($data['reference']);
            if (isset($data['frl'])) {
                foreach ($data['frl'] as $frl) {
                    $fournisseurId = explode(';', $frl)[0];
                    $ref = explode(';', $frl)[1];
                    $label = explode(';', $frl)[2];
                    $fournisseur = $this->fournisseurRepository->find(intval($fournisseurId));
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
            if (isset($data['urgence'])) $refArticle->setIsUrgent($data['urgence']);
            if (isset($data['prix'])) $refArticle->setPrixUnitaire($price);
            if (isset($data['emplacement'])) $refArticle->setEmplacement($emplacement);
            if (isset($data['libelle'])) $refArticle->setLibelle($data['libelle']);
            if (isset($data['commentaire'])) $refArticle->setCommentaire($data['commentaire']);
            if (isset($data['limitWarning'])) $refArticle->setLimitWarning($data['limitWarning']);
            if (isset($data['limitSecurity'])) $refArticle->setLimitSecurity($data['limitSecurity']);
            if (isset($data['quantite'])) $refArticle->setQuantiteStock(max(intval($data['quantite']), 0)); // protection contre quantités négatives
            if (isset($data['statut'])) {
                $statut = $this->statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, $data['statut']);
                if ($statut) $refArticle->setStatut($statut);
            }
            if (isset($data['type'])) {
                $type = $this->typeRepository->find(intval($data['type']));
                if ($type) $refArticle->setType($type);
            }
            if (isset($data['type_quantite'])) $refArticle->setTypeQuantite($data['type_quantite']);
            $entityManager->flush();
            //modification ou création des champsLibres
            $champsLibresKey = array_keys($data);
            foreach ($champsLibresKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $this->champLibreRepository->find($champ);
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByRefArticleAndChampLibre($refArticle->getId(), $champLibre);
                    // si la valeur n'existe pas, on la crée
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampLibre();
                        $valeurChampLibre
                            ->addArticleReference($refArticle)
                            ->setChampLibre($this->champLibreRepository->find($champ));
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
        $rows = $this->valeurChampLibreRepository->getLabelCLAndValueByRefArticle($refArticle);
        $rowCL = [];
        foreach ($rows as $row) {
            $rowCL[$row['label']] = $row['valeur'];
        }

		$availableQuantity = $this->getAvailableQuantityForRef($refArticle);
		$quantityStock = $refArticle->getCalculatedStockQuantity();

        $rowCF = [
            "id" => $refArticle->getId(),
            "Libellé" => $refArticle->getLibelle() ? $refArticle->getLibelle() : 'Non défini',
            "Référence" => $refArticle->getReference() ? $refArticle->getReference() : 'Non défini',
            "Type" => ($refArticle->getType() ? $refArticle->getType()->getLabel() : ""),
            "Emplacement" => ($refArticle->getEmplacement() ? $refArticle->getEmplacement()->getLabel() : ""),
            "Quantité disponible" => $availableQuantity,
			"Quantité stock" => $quantityStock ?? 0,
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
     * @return bool
     * @throws NonUniqueResultException
     */
    public function addRefToDemand($data, $referenceArticle)
    {
        $resp = true;
        $demande = $this->demandeRepository->find($data['livraison']);

        // cas gestion quantité par référence
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            if ($this->ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                $ligneArticle = new LigneArticle();
                $ligneArticle
                    ->setReference($referenceArticle)
                    ->setDemande($demande)
                    ->setQuantite(max($data["quantitie"], 0)); // protection contre quantités négatives
                $this->em->persist($ligneArticle);
            } else {
                $ligneArticle = $this->ligneArticleRepository->findOneByRefArticleAndDemande($referenceArticle, $demande);
                $ligneArticle->setQuantite($ligneArticle->getQuantite() + max($data["quantitie"], 0)); // protection contre quantités négatives
            }
            $this->editRefArticle($referenceArticle, $data);

            // cas gestion quantité par article
        } elseif ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            if ($this->userService->hasParamQuantityByRef()) {
                if ($this->ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                    $ligneArticle = new LigneArticle();
                    $ligneArticle
                        ->setQuantite(max($data["quantitie"], 0))// protection contre quantités négatives
                        ->setReference($referenceArticle)
                        ->setDemande($demande)
                        ->setToSplit(true);
                    $this->em->persist($ligneArticle);
                } else {
                    $ligneArticle = $this->ligneArticleRepository->findOneByRefArticleAndDemandeAndToSplit($referenceArticle, $demande);
                    $ligneArticle->setQuantite($ligneArticle->getQuantite() + max($data["quantitie"], 0));
                }
            } else {
                $article = $this->articleRepository->find($data['article']);
                /** @var Article $article */
                $article
                    ->setDemande($demande)
                    ->setQuantiteAPrelever(max($data["quantitie"], 0)); // protection contre quantités négatives
                $resp = 'article';
            }
        } else {
            $resp = false;
        }

        $this->em->flush();
        return $resp;
    }

    /**
     * @param null $counter
     * @return string
     * @throws Exception
     */
    public function generateBarCode($counter = null)
    {
        $now = new \DateTime('now');
        $dateCode = $now->format('ym');

        if (!isset($counter)) {
            $highestBarCode = $this->referenceArticleRepository->getHighestBarCodeByDateCode($dateCode);
            $highestCounter = $highestBarCode ? (int)substr($highestBarCode, 7, 8) : 0;
            $counter = sprintf('%08u', $highestCounter + 1);
        }

        $newBarcode = ReferenceArticle::BARCODE_PREFIX . $dateCode . $counter;

        return $newBarcode;
    }

    public function getAlerteDataByParams($params, $user)
    {
        $filtresAlerte = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ALERTE, $user);

        $results = $this->referenceArticleRepository->getAlertDataByParams($params, $filtresAlerte);
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
	 * @throws DBALException
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function dataRowAlerteRef($referenceArticle)
    {
        $quantity = $this->getAvailableQuantityForRef($referenceArticle);
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
     * @return array Field barcode and barcodeLabel
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getBarcodeInformations(ReferenceArticle $referenceArticle): array
    {
        return [
            'barcode' => $referenceArticle->getBarCode(),
            'barcodeLabel' => $this->templating->render('reference_article/barcodeLabel.html.twig', [
                'refRef' => $referenceArticle->getReference(),
                'refLabel' => $referenceArticle->getLibelle(),
            ])
        ];
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
     * @param ReferenceArticle|array $referenceArticle
     * @return int
     * @throws DBALException
     */
    public function getAvailableQuantityForRef($referenceArticle): int
    {
        $referenceArticle = is_array($referenceArticle) ? $this->referenceArticleRepository->find($referenceArticle['id']) : $referenceArticle;
        return
            $referenceArticle->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_REFERENCE
                ? $referenceArticle->getQuantiteStock() - $this->referenceArticleRepository->getTotalQuantityReservedByRefArticle($referenceArticle)
                : $this->referenceArticleRepository->getTotalAvailableQuantityArticlesByRefArticle($referenceArticle);
    }

    /**
     * @param ReferenceArticle $refArticle
     * @throws DBALException
     * @throws Exception
     */
	public function treatAlert(ReferenceArticle $refArticle): void
	{
		$calculedAvailableQuantity = $this->getAvailableQuantityForRef($refArticle);
		$limitToCompare = empty($refArticle->getLimitWarning())
			? empty($refArticle->getLimitSecurity())
				? 0
				: $refArticle->getLimitSecurity()
			: $refArticle->getLimitWarning();
		$status = $refArticle->getStatut();
		$limitToCompare = intval($limitToCompare);
		if ($limitToCompare > 0) {
			if (!isset($status) ||
				($status->getNom() === ReferenceArticle::STATUT_INACTIF) ||
				($refArticle->getDateEmergencyTriggered() && ($calculedAvailableQuantity > $limitToCompare))) {
				$refArticle->setDateEmergencyTriggered(null);
			} else if (!$refArticle->getDateEmergencyTriggered() && $calculedAvailableQuantity <= $limitToCompare) {
				$refArticle->setDateEmergencyTriggered(new DateTime('now', new DateTimeZone("Europe/Paris")));
			}
		}
	}

}
