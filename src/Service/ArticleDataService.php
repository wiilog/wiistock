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
use App\Entity\NativeCountry;
use App\Entity\Setting;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;
use App\Entity\CategorieCL;
use App\Exceptions\FormException;
use App\Entity\Zone;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\StringHelper;

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

    #[Required]
    public CSVExportService $CSVExportService;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public TranslationService $translation;

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
                $articles = $this->findAndSortActiveArticlesByRefArticle($refArticle, $this->entityManager);

                $articleIdsInRequest = $request->getArticleLines()
                    ->map(fn (DeliveryRequestArticleLine $line) => $line->getArticle()->getId())
                    ->toArray();

                $data = [
                    'selection' => $this->templating->render('demande/newRefArticleByQuantiteArticleContent.html.twig', [
                        'articles' => $articles,
                        'preselect' => isset($management),
                        'maximum' => $availableQuantity,
                        'deliveryRequest' => $request,
                        'articleIdsInRequest' => $articleIdsInRequest,
                    ])
                ];
            }
        } else {
            $data = false;
        }

        return $data;
    }

    public function findAndSortActiveArticlesByRefArticle(ReferenceArticle $refArticle, EntityManagerInterface $entityManager, ?Demande $demande = null){
        $articleRepository = $entityManager->getRepository(Article::class);
        $articles = $articleRepository->findActiveArticles($refArticle, null, null, null, $demande);
        $management = $refArticle->getStockManagement();
        return $management
            ? Stream::from($articles)
                ->sort(function (Article $article1, Article $article2) use ($management) {
                    $datesToCompare = [];
                    if ($management === ReferenceArticle::STOCK_MANAGEMENT_FIFO) {
                        $datesToCompare[0] = $article1->getStockEntryDate()?->format('Y-m-d');
                        $datesToCompare[1] = $article2->getStockEntryDate()?->format('Y-m-d');
                    } else if ($management === ReferenceArticle::STOCK_MANAGEMENT_FEFO) {
                        $datesToCompare[0] = $article1->getExpiryDate()?->format('Y-m-d');
                        $datesToCompare[1] = $article2->getExpiryDate()?->format('Y-m-d');
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
                })->toArray()
            : $articles ;
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

    public function editArticle($data) {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        $articleRepository = $this->entityManager->getRepository(Article::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        $article = $articleRepository->find(intval($data['idArticle'] ?? $data['article']));
        if ($article) {
            if ($this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {

                $expiryDate = !empty($data['expiry']) ? DateTime::createFromFormat("Y-m-d", $data['expiry']) : null;
                $price = max(0, $data['prix'] ?? 0);

                $article
                    ->setPrixUnitaire((float)$price)
                    ->setBatch($data['batch'] ?? null)
                    ->setExpiryDate($expiryDate ?: null)
                    ->setCommentaire(StringHelper::cleanedComment($data['commentaire'] ?? null));

                if (isset($data['conform'])) {
                    $article->setConform($data['conform'] == 1);
                }

                if (isset($data['statut'])) { // si on est dans une demande (livraison ou collecte), pas de champ statut
                    $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $data['statut']);
                    if ($statut) {
                        $article->setStatut($statut);
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

    public function newArticle(EntityManagerInterface $entityManager, array $data, array $options = []): Article {

        /** @var Article|null $existing */
        $existing = $options['existing'] ?? null;
        $excludeBarcodes = $options['excludeBarcodes'] ?? [];

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

        $refArticle = $referenceArticleRepository->find($data['refArticle']);
        if($refArticle->getTypeQuantite() !== ReferenceArticle::QUANTITY_TYPE_ARTICLE
            || $refArticle->getStatut()?->getCode() !== ReferenceArticle::STATUT_ACTIF) {
            throw new FormException('Impossible de créer un article pour une référence de type ' . $refArticle->getTypeQuantite() . ' ou dont le statut est ' . $refArticle->getStatut()?->getNom());
        }
        $refReferenceArticle = $refArticle->getReference();
        $formattedDate = (new DateTime())->format('ym');
        $references = $articleRepository->getReferencesByRefAndDate($refReferenceArticle, $formattedDate);

        $highestCpt = 0;
        foreach ($references as $reference) {
            $cpt = (int)substr($reference, -5, 5);
            if ($cpt > $highestCpt) $highestCpt = $cpt;
        }

        $i = $highestCpt + 1;
        $cpt = sprintf('%05u', $i);

        if (isset($data['emplacement'])) {
            $location = $emplacementRepository->find($data['emplacement']);
        } else {
            $location = $emplacementRepository->findOneBy(['label' => Emplacement::LABEL_A_DETERMINER]);
            if (!$location) {
                $zoneRepository = $entityManager->getRepository(Zone::class);
                $defaultZone = $zoneRepository->findOneBy(['name' => Zone::ACTIVITY_STANDARD_ZONE_NAME]);

                $location = new Emplacement();
                $location
                    ->setLabel(Emplacement::LABEL_A_DETERMINER)
                    ->setZone($defaultZone);
                $entityManager->persist($location);
            }
            $location->setIsActive(true);
        }

        $type = $refArticle->getType();
        $quantity = max((int)($data['quantite'] ?? -1), 0); // protection contre quantités négatives

        if ($existing) {
            $existing
                ->setRFIDtag($data['rfidTag'] ?? null)
                ->setExpiryDate(isset($data['expiry']) ? $this->formatService->parseDatetime($data['expiry'], ['Y-m-d', 'd/m/Y']) : null);
        } else {
            $article = (new Article())
                ->setLabel($data['libelle'] ?? $refArticle->getLibelle())
                ->setConform(isset($data['conform']) && !$data['conform'])
                ->setStatut($statut)
                ->setCommentaire(isset($data['commentaire']) ? StringHelper::cleanedComment($data['commentaire']) : null)
                ->setPrixUnitaire(isset($data['prix']) ? max(0, $data['prix']) : null)
                ->setReference("$refReferenceArticle$formattedDate$cpt")
                ->setQuantite($quantity)
                ->setEmplacement($location)
                ->setArticleFournisseur($articleFournisseurRepository->find($data['articleFournisseur']))
                ->setType($type)
                ->setBarCode($data['barcode'] ?? $this->generateBarcode($excludeBarcodes))
                ->setStockEntryDate(new DateTime("now"))
                ->setDeliveryNote($data['deliveryNoteLine'] ?? null)
                ->setProductionDate(isset($data['productionDate']) ? $this->formatService->parseDatetime($data['productionDate'], ['Y-m-d', 'd/m/Y']) : null)
                ->setManifacturingDate(isset($data['manufactureDate']) ? $this->formatService->parseDatetime($data['manufactureDate'], ['Y-m-d', 'd/m/Y']) : null)
                ->setPurchaseOrder($data['purchaseOrderLine'] ?? null)
                ->setRFIDtag($data['rfidTag'] ?? null)
                ->setBatch($data['batch'] ?? null);

            if(isset($data['nativeCountry'])) {
                $article->setNativeCountry($entityManager->find(NativeCountry::class, $data['nativeCountry']));
            }

            if (isset($data['expiry'])) {
                $article->setExpiryDate($data['expiry'] ? $this->formatService->parseDatetime($data['expiry'], ['Y-m-d', 'd/m/Y']) : null);
            }
            if (isset($data['productionDate'])) {
                $article->setProductionDate($data['productionDate'] ? $this->formatService->parseDatetime($data['productionDate'], ['Y-m-d', 'd/m/Y']) : null);
            }
            if (isset($data['manufactureDate'])) {
                $article->setManifacturingDate($data['manufactureDate'] ? $this->formatService->parseDatetime($data['manufactureDate'], ['Y-m-d', 'd/m/Y']) : null);
            }

            $article->setArticleFournisseur($articleFournisseurRepository->find($data['articleFournisseur']));
            $entityManager->persist($article);
        }

        $this->freeFieldService->manageFreeFields($existing ?? $article, $data, $entityManager);

        return $existing ?? $article;
    }

    public function getArticleDataByReceptionLigne(ReceptionReferenceArticle $ligne): array
    {
        $articles = $ligne->getArticles();
        $reception = $ligne->getReceptionLine()?->getReception();
        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowArticle($article, $reception);
        }

        return [
            'data' => $rows
        ];
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

        $queryResult = $articleRepository->findByParamsAndFilters($params, $filters, $user, $this->visibleColumnService);

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
        $status = $article->getStatut() ? $this->formatService->status($article->getStatut()) : 'Non défini';

        $supplierArticle = $article->getArticleFournisseur();
        $referenceArticle = $supplierArticle ? $supplierArticle->getReferenceArticle() : null;

        $lastMessage = $article->getLastMessage();
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper()) ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName() : null;
        $hasPairing = !$article->getSensorMessages()->isEmpty() || !$article->getPairings()->isEmpty();
        $ul = $article->getCurrentLogisticUnit();

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
            "lastAvailableDate" => $article->getLastAvailableDate() ? $article->getLastAvailableDate()->format('d/m/Y') : '',
            "firstUnavailableDate" => $article->getFirstUnavailableDate() ? $article->getFirstUnavailableDate()->format('d/m/Y') : '',
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
                'hasPairing' => $hasPairing,
                'targetBlank' => $reception !== null
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing
            ]),
            'lu' => $this->templating->render("lu_icon.html.twig", [
                'lu' => $ul,
            ]),
            'project' => $article->getCurrentLogisticUnit()?->getProject()?->getCode() ?? '',
            "manufactureDate" => $this->formatService->date($article->getManifacturingDate()),
            "productionDate" => $this->formatService->date($article->getProductionDate()),
            "deliveryNoteLine" => $article->getDeliveryNote() ?: '',
            "purchaseOrderLine" => $article->getPurchaseOrder() ?: '',
            "nativeCountry" => $article->getNativeCountry() ? $article->getNativeCountry()->getLabel() : '',
            'RFIDtag' => $article->getRFIDtag(),
            'manifacturingDate' => $article->getManifacturingDate() ? $article ->getManifacturingDate()->format('d/m/Y') : '',
            'purchaseOrder' => $article->getPurchaseOrder(),
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $article->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

	public function generateBarcode(array $excludeBarcodes = []): string
	{
        $now = new DateTime('now');
        $dateCode = $now->format('ym');

        $articleRepository = $this->entityManager->getRepository(Article::class);
        $highestBarCode = $articleRepository->getHighestBarCodeByDateCode($dateCode);
        $highestCounter = $highestBarCode ? (int) substr($highestBarCode, 7, 8) : 0;

        do {
            $highestCounter++;
            $newCounter = sprintf('%08u', $highestCounter);
            $generatedBarcode = Article::BARCODE_PREFIX . $dateCode . $newCounter;
        }
        while(in_array($generatedBarcode, $excludeBarcodes));

		return $generatedBarcode;
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

                $this->typeCLOnLabel = $champLibre?->getTypage();
                $this->clIdWantedOnLabel = $champLibre?->getId();
            }
        }

        $articleFournisseur = $article->getArticleFournisseur();
        $refArticle = $articleFournisseur?->getReferenceArticle();
        $refRefArticle = $refArticle?->getReference();
        $labelRefArticle = $refArticle?->getLibelle();

        $quantityArticle = $article->getQuantite();
        $labelArticle = $article->getLabel();
        $champLibreValue = $this->clIdWantedOnLabel ? $article->getFreeFieldValue($this->clIdWantedOnLabel) : '';
        $batchArticle = $article->getBatch() ?? '';
        $expirationDateArticle = $this->formatService->date($article->getExpiryDate());
        $stockEntryDateArticle = $this->formatService->date($article->getStockEntryDate());

        $wantsRecipient = $settingRepository->getOneParamByLabel(Setting::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL);
        $wantsRecipientDropzone = $settingRepository->getOneParamByLabel(Setting::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL);
        $wantDestinationLocation = $settingRepository->getOneParamByLabel(Setting::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL);

        // Récupération du username & dropzone de l'utilisateur
        $articleReception = $article->getReceptionReferenceArticle()?->getReceptionLine()?->getReception() ?: null;
        $articleReceptionRecipient = $articleReception?->getUtilisateur() ?: '';
        $articleReceptionRecipientUsername = ($articleReceptionRecipient && $wantsRecipient) ? $articleReceptionRecipient->getUsername() : '';
        $articleReceptionRecipientDropzone = $articleReceptionRecipient ? $articleReceptionRecipient->getDropzone() : '';
        $articleReceptionRecipientDropzoneLabel = ($articleReceptionRecipientDropzone && $wantsRecipientDropzone) ? $articleReceptionRecipientDropzone->getLabel() : '';

        $articleLinkedToTransferRequestToTreat = $article->getTransferRequests()->map(function (TransferRequest $transferRequest) use ($reception) {
            if ($reception && $transferRequest->getStatus()?->getCode() === TransferOrder::TO_TREAT) {
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
            "$username $separator $location",
            "L/R : $labelRefArticle",
            "C/R : $refRefArticle",
            "L/A : $labelArticle",
            !empty($this->typeCLOnLabel) && !empty($champLibreValue) ? $champLibreValue : '',
        ];

        $includeQuantity = $settingRepository->getOneParamByLabel(Setting::INCLUDE_QTT_IN_LABEL);
        $includeBatch = $settingRepository->getOneParamByLabel(Setting::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL);
        $includeExpirationDate = $settingRepository->getOneParamByLabel(Setting::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL);
        $includeStockEntryDate = $settingRepository->getOneParamByLabel(Setting::INCLUDE_STOCK_ENTRY_DATE_IN_ARTICLE_LABEL);

        if ($includeBatch && $batchArticle) {
            $labels[] = "N° lot : $batchArticle";
        }

        if ($includeExpirationDate && $expirationDateArticle) {
            $labels[] = "Date péremption : $expirationDateArticle";
        }

        if ($includeStockEntryDate && $stockEntryDateArticle) {
            $labels[] = "Date d'entrée en stock : $stockEntryDateArticle";
        }

        if ($includeQuantity) {
            $labels[] = "Qte : $quantityArticle";
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
        return in_array($article->getStatut()?->getCode(), [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE]);
    }

    public function getColumnVisibleConfig(EntityManagerInterface $entityManager,
                                           Utilisateur $currentUser): array {

        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::ARTICLE, CategorieCL::ARTICLE);

        $fieldConfig = [
            ['name' => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true],
            ["title" => "<span class='wii-icon wii-icon-pairing black'><span>", 'name' => "pairing"],
            ["title" => "<span class='wii-icon wii-icon-lu'><span>", 'name' => "lu"],
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
            ["title" => "Date de disponibilité constatée", "name" => "lastAvailableDate", 'searchable' => true],
            ["title" => "Date d'épuisement constaté", "name" => "firstUnavailableDate", 'searchable' => true],
            ["title" => "Lot", "name" => "batch"],
            ["title" => "Date d'entrée en stock", "name" => "stockEntryDate", 'searchable' => true],
            ["title" => "Date d'expiration", "name" => "expiryDate", 'searchable' => true],
            ["title" => "Commentaire", "name" => "comment", 'searchable' => true],
            ["title" => $this->translation->translate('Référentiel', 'Projet', 'Projet', false), "name" => "project", 'searchable' => true],
            ["title" => "Tag RFID", "name" => "RFIDtag", 'searchable' => true],
            ["title" => "Date de fabrication", "name" => "manufactureDate", 'searchable' => true],
            ["title" => "Date de production", "name" => "productionDate", 'searchable' => true],
            ["title" => "Ligne bon de livraison", "name" => "deliveryNoteLine", 'searchable' => true],
            ["title" => "Ligne commande d'achat", "name" => "purchaseOrderLine", 'searchable' => true],
            ["title" => "Pays d'origine", "name" => "nativeCountry", 'searchable' => true],
        ];

        return $this->visibleColumnService->getArrayConfig($fieldConfig, $freeFields, $currentUser->getVisibleColumns()['article']);
    }

    public function putArticleLine($handle,
                                   array $article,
                                   array $freeFieldsConfig) {
        $line = [
            $article['reference'],
            $article['label'],
            $article['nomFournisseur'],
            $article['RefArtFournisseur'],
            $article['RFIDtag'],
            $article['quantite'],
            $article['typeLabel'],
            $article['statusName'],
            $article['commentaire'] ? strip_tags($article['commentaire']) : '',
            $article['empLabel'],
            $article['barCode'],
            $article['dateLastInventory'] ? $article['dateLastInventory']->format('d/m/Y H:i:s') : '',
            $article['lastAvailableDate'] ? $article['lastAvailableDate']->format('d/m/Y H:i:s') : '',
            $article['firstUnavailableDate'] ? $article['firstUnavailableDate']->format('d/m/Y H:i:s') : '',
            $article['batch'],
            $article['stockEntryDate'] ? $article['stockEntryDate']->format('d/m/Y H:i:s') : '',
            $article['expiryDate'] ? $article['expiryDate']->format('d/m/Y') : '',
            $article['visibilityGroup'],
            $article['projectCode'],
            $article['prixUnitaire'],
            $article['purchaseOrder'],
            $article['deliveryNote'],
            $article['nativeCountryLabel'],
            $article['manifacturingDate'] ? $article['manifacturingDate']->format('d/m/Y') : '',
            $article['productionDate'] ? $article['productionDate']->format('d/m/Y') : '',
        ];

        foreach($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
            $line[] = $this->formatService->freeField($article['freeFields'][$freeFieldId] ?? '', $freeField);
        }

        $this->CSVExportService->putLine($handle, $line);
    }
}
