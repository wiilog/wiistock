<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\FreeField;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Setting;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;
use App\Entity\CategorieCL;
use App\Helper\FormatHelper;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;

class ArticleDataService
{
    private $templating;
    private $router;
    private $refArticleDataService;
    private $userService;
    private $entityManager;
    private $wantCLOnLabel;
	private $clWantedOnLabel;
	private $clIdWantedOnLabel;
	private $typeCLOnLabel;

    /** @Required */
    public CSVExportService $CSVExportService;

    /** @Required */
    public FreeFieldService $freeFieldService;

    private $visibleColumnService;

    private ?array $freeFieldsConfig = null;

    public function __construct(RouterInterface $router,
                                UserService $userService,
                                RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager,
                                VisibleColumnService $visibleColumnService,
                                Twig_Environment $templating) {
        $this->refArticleDataService = $refArticleDataService;
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->router = $router;
        $this->visibleColumnService = $visibleColumnService;
    }

    public function getCollecteArticleOrNoByRefArticle(Collecte $collect, ReferenceArticle $refArticle, Utilisateur $user)
    {
        $role = $user->getRole();

        if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
            $data = [
                'modif' => $this->refArticleDataService->getViewEditRefArticle($refArticle, true),
                'selection' => $this->templating->render('collecte/newRefArticleByQuantiteRefContent.html.twig'),
            ];
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            $data = [
                'selection' => $this->templating->render('collecte/newRefArticleByQuantiteRefContentTemp.html.twig', [
                    "collect" => $collect,
                    'roleIsHandlingArticles' => $role->getQuantityType() === ReferenceArticle::QUANTITY_TYPE_ARTICLE,
                ]),
            ];
        } else {
            $data = false; //TODO gérer erreur retour
        }

        return $data;
    }

    public function getLivraisonArticlesByRefArticle(ReferenceArticle $refArticle, Demande $request, Utilisateur $user, $needsQuantitiesCheck)
    {
        if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
            $data = [
                'modif' => $this->refArticleDataService->getViewEditRefArticle($refArticle, true),
                'selection' => $this->templating->render('demande/newRefArticleByQuantiteRefContent.html.twig', [
                    'maximum' => $refArticle->getQuantiteDisponible(),
                    'needsQuantitiesCheck' => $needsQuantitiesCheck,
                ]),
            ];
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            $articleRepository = $this->entityManager->getRepository(Article::class);

            $articles = $articleRepository->findActiveArticles($refArticle);
            $role = $user->getRole();

            $availableQuantity = $refArticle->getQuantiteDisponible();
            if ($role->getQuantityType() == ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                $data = [
                    'selection' => $this->templating->render('demande/choiceContent.html.twig', [
                        'maximum' => $availableQuantity,
                        'showTargetLocationPicking' => $this->entityManager->getRepository(Setting::class)->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION)
                    ])];
            } else {
                $management = $refArticle->getStockManagement();
                if ($management) {
                    $articles = Stream::from($articles)
                        ->sort(function (Article $article1, Article $article2) use ($management) {
                            $datesToCompare = [];
                            if ($management === ReferenceArticle::STOCK_MANAGEMENT_FIFO) {
                                $datesToCompare[0] = $article1->getStockEntryDate() ? $article1->getStockEntryDate()->format('Y-m-d') : null;
                                $datesToCompare[1] = $article2->getStockEntryDate() ? $article2->getStockEntryDate()->format('Y-m-d') : null;
                            } else if ($management === ReferenceArticle::STOCK_MANAGEMENT_FEFO) {
                                $datesToCompare[0] = $article1->getExpiryDate() ? $article1->getExpiryDate()->format('Y-m-d') : null;
                                $datesToCompare[1] = $article2->getExpiryDate() ? $article2->getExpiryDate()->format('Y-m-d') : null;
                            }
                            if ($datesToCompare[0] && $datesToCompare[1]) {
                                if (strtotime($datesToCompare[0]) === strtotime($datesToCompare[1])) {
                                    return 0;
                                }
                                return strtotime($datesToCompare[0]) < strtotime($datesToCompare[1]) ? -1 : 1;
                            } else if ($datesToCompare[0]) {
                                return -1;
                            } else if ($datesToCompare[1]) {
                                return 1;
                            }
                            return 0;
                        })->toArray();
                }

                $articleIdsInRequest = $request->getArticleLines()
                    ->map(fn (DeliveryRequestArticleLine $line) => $line->getArticle()->getId())
                    ->toArray();

                $data = [
                    'selection' => $this->templating->render('demande/newRefArticleByQuantiteArticleContent.html.twig', [
                        'articles' => $articles,
                        'preselect' => isset($management),
                        'maximum' => $availableQuantity,
                        'deliveryRequest' => $request,
                        'articleIdsInRequest' => $articleIdsInRequest
                    ])
                ];
            }
        } else {
            $data = false; //TODO gérer erreur retour
        }

        return $data;
    }

    public function getViewEditArticle($article,
                                       $isADemand = false)
    {
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);

        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $typeArticle = $refArticle->getType();
        $typeArticleLabel = $typeArticle->getLabel();

		$articleFreeFields = $champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);

        return $this->templating->render('article/modalArticleContent.html.twig', [
            'typeChampsLibres' => [
                'type' => $typeArticleLabel,
                'champsLibres' => $articleFreeFields,
            ],
            'typeArticle' => $typeArticleLabel,
            'typeArticleId' => $typeArticle->getId(),
            'article' => $article,
            'statut' => $article->getStatut() ? $article->getStatut()->getNom() : '',
            'isADemand' => $isADemand,
            'invCategory' => $refArticle->getCategory()
        ]);
    }

    public function editArticle($data)
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        $articleRepository = $this->entityManager->getRepository(Article::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);


        $article = $articleRepository->find($data['article']);
        if ($article) {
            if ($this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {

                $expiryDate = !empty($data['expiry']) ? DateTime::createFromFormat("Y-m-d", $data['expiry']) : null;
                $price = max(0, $data['prix'] ?? 0);
                if (isset($data['label'])) {
                    $article
                        ->setPrixUnitaire($price)
                        ->setLabel($data['label'])
                        ->setConform(!$data['conform'])
                        ->setBatch($data['batch'] ?? null)
                        ->setExpiryDate($expiryDate ? $expiryDate : null)
                        ->setCommentaire($data['commentaire']);

                    if (isset($data['statut'])) { // si on est dans une demande (livraison ou collecte), pas de champ statut
                        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $data['statut']);
                        if ($statut) {
                            $article->setStatut($statut);
                        }
                    }
                }
            }

            $this->freeFieldService->manageFreeFields($article, $data, $this->entityManager);
            $this->entityManager->flush();
            return true;
        } else {
            return false;
        }
    }

    public function newArticle($data, EntityManagerInterface $entityManager) {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $statusLabel = $data['statut'] ?? Article::STATUT_ACTIF;
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $statusLabel);
        if (!isset($statut)) {
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
        }
        $date = new DateTime('now');
        $formattedDate = $date->format('ym');

        $refArticle = $referenceArticleRepository->find($data['refArticle']);
        $refReferenceArticle = $refArticle->getReference();
        $references = $articleRepository->getReferencesByRefAndDate($refReferenceArticle, $formattedDate);

        $highestCpt = 0;
        foreach ($references as $reference) {
            $cpt = (int)substr($reference, -5, 5);
            if ($cpt > $highestCpt) $highestCpt = $cpt;
        }

        $i = $highestCpt + 1;
        $cpt = sprintf('%05u', $i);

        $toInsert = new Article();
        $price = isset($data['prix']) ? max(0, $data['prix']) : null;

        $type = $articleFournisseurRepository->find($data['articleFournisseur'])->getReferenceArticle()->getType();

        if (isset($data['emplacement'])) {
			$location = $emplacementRepository->find($data['emplacement']);
		} else {
        	$location = $emplacementRepository->findOneBy(['label' => Emplacement::LABEL_A_DETERMINER]);
        	if (!$location) {
        		$location = new Emplacement();
        		$location
					->setLabel(Emplacement::LABEL_A_DETERMINER);
        		$entityManager->persist($location);
			}
        	$location->setIsActive(true);
		}

        $quantity = max((int)$data['quantite'], 0); // protection contre quantités négatives
        $toInsert
            ->setLabel($data['libelle'] ?? $refArticle->getLibelle())
            ->setConform(!isset($data['conform']) || !$data['conform'])
            ->setStatut($statut)
            ->setCommentaire($data['commentaire'] ?? null)
            ->setPrixUnitaire($price)
            ->setReference($refReferenceArticle . $formattedDate . $cpt)
            ->setQuantite($quantity)
            ->setEmplacement($location)
            ->setArticleFournisseur($articleFournisseurRepository->find($data['articleFournisseur']))
            ->setType($type)
            ->setBarCode($this->generateBarCode())
            ->setStockEntryDate(new DateTime("now"));

        if (isset($data['batch'])) {
            $toInsert->setBatch($data['batch']);
        }

        if (isset($data['expiry'])) {
            $toInsert->setExpiryDate($data['expiry'] ? DateTime::createFromFormat("Y-m-d", $data['expiry']) : null);
        }
        $entityManager->persist($toInsert);
        $this->freeFieldService->manageFreeFields($toInsert, $data, $entityManager);

        return $toInsert;
    }

    public function getArticleDataByReceptionLigne(ReceptionReferenceArticle $ligne)
    {
        $articles = $ligne->getArticles();
        $reception = $ligne->getReception();
        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowArticle($article, $reception);
        }
        return ['data' => $rows];
    }

    public function getArticleDataByParams(InputBag $params, Utilisateur $user) {
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);

		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ARTICLE, $user);

        // l'utilisateur qui n'a pas le droit de modifier le stock ne doit pas voir les articles inactifs
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            $filters = [[
                'field' => FiltreSup::FIELD_STATUT,
                'value' => $this->getActiveArticleFilterValue()
            ]];
        }

        $queryResult = $articleRepository->findByParamsAndFilters($params, $filters, $user);

        $articles = $queryResult['data'];

        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowArticle(is_array($article) ? $article[0] : $article);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $articleRepository->countAll()
        ];
    }

    public function dataRowArticle(Article $article, Reception $reception = null)
    {
        $deliveryRequestRepository = $this->entityManager->getRepository(Demande::class);

        $url['edit'] = $this->router->generate('demande_article_edit', ['id' => $article->getId()]);
        $status = $article->getStatut() ? $article->getStatut()->getNom() : 'Non défini';

        $supplierArticle = $article->getArticleFournisseur();
        $referenceArticle = $supplierArticle ? $supplierArticle->getReferenceArticle() : null;

        $lastMessage = $article->getLastMessage();
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper()) ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName() : null;
        $hasPairing = !$article->getSensorMessages()->isEmpty() || !$article->getPairings()->isEmpty();

        $lastDeliveryRequest = $deliveryRequestRepository->findOneByArticle($article, $reception);

        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategorieCL::ARTICLE, CategoryType::ARTICLE);
        }

        $row = [
            "id" => $article->getId() ?? "Non défini",
            "label" => $article->getLabel() ?? "Non défini",
            "articleReference" => $referenceArticle ? $referenceArticle->getReference() : "Non défini",
            "supplierReference" => $supplierArticle ? $supplierArticle->getReference() : "Non défini",
            "barCode" => $article->getBarCode() ?? "Non défini",
            "type" => $article->getType() ? $article->getType()->getLabel() : '',
            "status" => $status,
            "quantity" => $article->getQuantite() ?? 0,
            "location" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : ' Non défini',
            "unitPrice" => $article->getPrixUnitaire(),
            "dateLastInventory" => $article->getDateLastInventory() ? $article->getDateLastInventory()->format('d/m/Y') : '',
            "batch" => $article->getBatch(),
            "stockEntryDate" => $article->getStockEntryDate() ? $article->getStockEntryDate()->format('d/m/Y H:i') : '',
            "expiryDate" => $article->getExpiryDate() ? $article->getExpiryDate()->format('d/m/Y') : '',
            "comment" => $article->getCommentaire(),
            "actions" => $this->templating->render('article/datatableArticleRow.html.twig', [
                'url' => $url,
                'articleId' => $article->getId(),
                'demandeId' => $lastDeliveryRequest ? $lastDeliveryRequest->getId() : null,
                'articleFilter' => $article->getBarCode(),
                'fromReception' => isset($reception),
                'receptionId' => $reception ? $reception->getId() : null,
                'hasPairing' => $hasPairing
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing
            ]),
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $article->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = FormatHelper::freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

	public function generateBarCode()
	{
        $articleRepository = $this->entityManager->getRepository(Article::class);

		$now = new DateTime('now');
		$dateCode = $now->format('ym');

		$highestBarCode = $articleRepository->getHighestBarCodeByDateCode($dateCode);
		$highestCounter = $highestBarCode ? (int)substr($highestBarCode, 7, 8) : 0;

		$newCounter =  sprintf('%08u', $highestCounter+1);
		return Article::BARCODE_PREFIX . $dateCode . $newCounter;
	}

    public function getBarcodeConfig(Article $article, Reception $reception = null): array {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $deliveryRequestRepository = $this->entityManager->getRepository(Demande::class);

        if (!isset($this->wantCLOnLabel)
            && !isset($this->clWantedOnLabel)
            && !isset($this->typeCLOnLabel)) {

            $champLibreRepository = $this->entityManager->getRepository(FreeField::class);
            $categoryCLRepository = $this->entityManager->getRepository(CategorieCL::class);
            $this->clWantedOnLabel = $settingRepository->getOneParamByLabel(Setting::CL_USED_IN_LABELS);
            $this->wantCLOnLabel = (bool) $settingRepository->getOneParamByLabel(Setting::INCLUDE_BL_IN_LABEL);

            if (isset($this->clWantedOnLabel)) {
                $champLibre = $champLibreRepository->findOneBy([
                    'categorieCL' => $categoryCLRepository->findOneBy(['label' => CategoryType::ARTICLE]),
                    'label' => $this->clWantedOnLabel
                ]);

                $this->typeCLOnLabel = isset($champLibre) ? $champLibre->getTypage() : null;
                $this->clIdWantedOnLabel = isset($champLibre) ? $champLibre->getId() : null;
            }
        }

        $articleFournisseur = $article->getArticleFournisseur();
        $refArticle = isset($articleFournisseur) ? $articleFournisseur->getReferenceArticle() : null;
        $refRefArticle = isset($refArticle) ? $refArticle->getReference() : null;
        $labelRefArticle = isset($refArticle) ? $refArticle->getLibelle() : null;

        $quantityArticle = $article->getQuantite();
        $labelArticle = $article->getLabel();
        $champLibreValue = $this->clIdWantedOnLabel ? $article->getFreeFieldValue($this->clIdWantedOnLabel) : '';
        $batchArticle = $article->getBatch() ?? '';
        $expirationDateArticle = $article->getExpiryDate() ? $article->getExpiryDate()->format('d/m/Y') : '';

        $wantsRecipient = $settingRepository->getOneParamByLabel(Setting::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL);
        $wantsRecipientDropzone = $settingRepository->getOneParamByLabel(Setting::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL);
        $wantDestinationLocation = $settingRepository->getOneParamByLabel(Setting::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL);

        // Récupération du username & dropzone de l'utilisateur
        $articleReception = $article->getReceptionReferenceArticle() ? $article->getReceptionReferenceArticle()->getReception() : '';
        $articleReceptionRecipient = $articleReception ? $articleReception->getUtilisateur() : '';
        $articleReceptionRecipientUsername = ($articleReceptionRecipient && $wantsRecipient) ? $articleReceptionRecipient->getUsername() : '';
        $articleReceptionRecipientDropzone = $articleReceptionRecipient ? $articleReceptionRecipient->getDropzone() : '';
        $articleReceptionRecipientDropzoneLabel = ($articleReceptionRecipientDropzone && $wantsRecipientDropzone) ? $articleReceptionRecipientDropzone->getLabel() : '';

        $articleLinkedToTransferRequestToTreat = $article->getTransferRequests()->map(function (TransferRequest $transferRequest) use ($reception) {
            if ($reception && $transferRequest->getStatus()->getNom() === TransferOrder::TO_TREAT) {
                $transferRequestLocation = $reception->getStorageLocation() ? $reception->getStorageLocation()->getLabel() : '';
            } else {
                $transferRequestLocation = '';
            }
            return $transferRequestLocation;
        });

        if (isset($reception) && $wantDestinationLocation && !empty($articleLinkedToTransferRequestToTreat[0])) {
            $location = $reception->getStorageLocation() ? $reception->getStorageLocation()->getLabel() : '';
        }
        else if (isset($reception) && $wantDestinationLocation) {
            $lastDeliveryRequest = $deliveryRequestRepository->findOneByArticle($article, $reception);
            $location = $lastDeliveryRequest ? $lastDeliveryRequest->getDestination()->getLabel() : '';
        }
        else if ($wantsRecipientDropzone
                && $articleReceptionRecipient
                && isset($reception)
                && !$wantDestinationLocation) {
            $location = $articleReceptionRecipientDropzoneLabel;
        }
        else {
            $location = '';
        }

        if ($wantsRecipient && isset($reception) && !$reception->getDemandes()->isEmpty()) {
            $username = $articleReceptionRecipientUsername;
        }
        else {
            $username = '';
        }

        $separator = ($location && $username) ? ' / ' : '';

        $labels = [
            $username . $separator . $location,
            !empty($labelRefArticle) ? ('L/R : ' . $labelRefArticle) : '',
            !empty($refRefArticle) ? ('C/R : ' . $refRefArticle) : '',
            !empty($labelArticle) ? ('L/A : ' . $labelArticle) : '',
            (!empty($this->typeCLOnLabel) && !empty($champLibreValue)) ? ($champLibreValue) : '',
        ];

        $wantsQTT = $settingRepository->getOneParamByLabel(Setting::INCLUDE_QTT_IN_LABEL);
        $wantsBatchArticle = $settingRepository->getOneParamByLabel(Setting::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL);
        $wantsExpirationDateArticle = $settingRepository->getOneParamByLabel(Setting::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL);

        if ($wantsBatchArticle) {
            $labels[] = !empty($batchArticle) ? ('N° lot : '. $batchArticle) : '';
        }

        if ($wantsExpirationDateArticle) {
            $labels[] = !empty($expirationDateArticle) ? ('Date péremption : '. $expirationDateArticle) : '';
        }

        if ($wantsQTT) {
            $labels[] = !empty($quantityArticle) ? ('Qte : '. $quantityArticle) : '';
        }

        return [
            'code' => $article->getBarCode(),
            'labels' => array_filter($labels, function (string $label) {
                return !empty($label);
            })
        ];
    }

    public function getActiveArticleFilterValue(): string {
        return Article::STATUT_ACTIF . ',' . Article::STATUT_EN_TRANSIT . ',' . Article::STATUT_EN_LITIGE;
    }

    public function articleCanBeAddedInDispute(Article $article): bool {
        return in_array($article->getStatut()->getNom(), [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE]);
    }

    public function getColumnVisibleConfig(EntityManagerInterface $entityManager,
                                           Utilisateur $currentUser): array {

        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $categorieCL = $categorieCLRepository->findOneBy(['label' => CategorieCL::ARTICLE]);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::ARTICLE, $categorieCL);

        $fieldConfig = [
            ['name' => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true],
            ['name' => "pairing", "alwaysVisible" => true],
            ["title" => "Libellé", "name" => "label", 'searchable' => true],
            ["title" => "Référence article", "name" => "articleReference", 'searchable' => true],
            ["title" => "Référence fournisseur", "name" => "supplierReference", 'searchable' => true],
            ["title" => "Code barre", "name" => "barCode", 'searchable' => true],
            ["title" => "Type", "name" => "type", 'searchable' => true],
            ["title" => "Statut", "name" => "status", 'searchable' => true],
            ["title" => "Quantité", "name" => "quantity", 'searchable' => true],
            ["title" => "Emplacement", "name" => "location", 'searchable' => true],
            ["title" => "Prix unitaire", "name" => "unitPrice"],
            ["title" => "Dernier inventaire", "name" => "dateLastInventory", 'searchable' => true],
            ["title" => "Lot", "name" => "batch"],
            ["title" => "Date d'entrée en stock", "name" => "stockEntryDate", 'searchable' => true],
            ["title" => "Date d'expiration", "name" => "expiryDate", 'searchable' => true],
            ["title" => "Commentaire", "name" => "comment", 'searchable' => true]
        ];

        return $this->visibleColumnService->getArrayConfig($fieldConfig, $freeFields, $currentUser->getVisibleColumns()['article']);
    }

    public function putArticleLine($handle,
                                    array $article,
                                    array $freeFieldsConfig) {
        $line = [
            $article['reference'],
            $article['label'],
            $article['quantite'],
            $article['typeLabel'],
            $article['statutName'],
            $article['commentaire'] ? strip_tags($article['commentaire']) : '',
            $article['empLabel'],
            $article['barCode'],
            $article['dateLastInventory'] ? $article['dateLastInventory']->format('d/m/Y H:i:s') : '',
            $article['batch'],
            $article['stockEntryDate'] ? $article['stockEntryDate']->format('d/m/Y H:i:s') : '',
            $article['expiryDate'] ? $article['expiryDate']->format('d/m/Y') : '',
            $article['visibilityGroup'],
        ];

        foreach($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
            $line[] = FormatHelper::freeField($article['freeFields'][$freeFieldId] ?? '', $freeField);
        }

        $this->CSVExportService->putLine($handle, $line);
    }
}
