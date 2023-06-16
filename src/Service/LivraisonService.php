<?php

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Livraison;

use App\Entity\Pack;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Setting;
use App\Entity\SubLineFieldsParam;
use App\Service\Document\TemplateDocumentService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class LivraisonService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    private $security;

    private $entityManager;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public TemplateDocumentService $wordTemplateDocument;

    #[Required]
    public PDFGeneratorService $PDFGeneratorService;

    #[Required]
    public SpecificService $specificService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security) {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->security = $security;
    }

    public function getDataForDatatable($params = null, $filterDemandId = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $livraisonRepository = $this->entityManager->getRepository(Livraison::class);

        if ($filterDemandId) {
            $filters = [
                [
                    'field' => FiltreSup::FIELD_DEMANDE,
                    'value' => $filterDemandId
                ]
            ];
        }
        else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ORDRE_LIVRAISON, $this->security->getUser());
        }
		$queryResult = $livraisonRepository->findByParamsAndFilters($params, $filters);

		$livraisons = $queryResult['data'];

		$rows = [];
		foreach ($livraisons as $livraison) {
			$rows[] = $this->dataRowLivraison($livraison);
		}
		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

    public function dataRowLivraison(Livraison $livraison)
    {
		$url['show'] = $this->router->generate('livraison_show', ['id' => $livraison->getId()]);

		$preparation = $livraison->getPreparation() ?? null;
		$lastMessage = $preparation ? $livraison->getPreparation()->getLastMessage() : null;
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper()) ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName() : null;
        $hasPairing = $preparation && !$preparation->getPairings()->isEmpty();

		$demande = $livraison->getDemande();

        return [
            'id' => $livraison->getId() ?? '',
            'Numéro' => $livraison->getNumero() ?? '',
            'Date' => $livraison->getDate() ? $livraison->getDate()->format('d/m/Y') : '',
            'Statut' => $livraison->getStatut() ? $this->formatService->status($livraison->getStatut()) : '',
            'Opérateur' => $livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : '',
            'Type' => $demande ? $this->formatService->type($demande->getType()) : '',
            'Actions' => $this->templating->render('livraison/datatableLivraisonRow.html.twig', ['url' => $url,
                'titleLogo' => !$livraison->getPreparation()->getPairings()->isEmpty() ? 'pairing' : null
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing,
            ]),
        ];
    }

    public function putLivraisonLine($handle,
                                      CSVExportService $csvService,
                                      Livraison $livraison)
    {
        $demande = $livraison->getDemande();
        $preparation = $livraison->getPreparation();
        if (isset($demande) && isset($preparation)) {
            $dataLivraison = [
                $livraison->getNumero() ?? '',
                $this->formatService->status($livraison->getStatut()),
                $this->formatService->datetime($livraison->getDate()),
                $this->formatService->datetime($livraison->getDateFin()),
                $this->formatService->date($demande->getValidatedAt()),
                $this->formatService->deliveryRequester($demande),
                $this->formatService->user($livraison->getUtilisateur()),
                $this->formatService->type($demande->getType()),
                $this->formatService->html($demande->getCommentaire())
            ];

            /** @var PreparationOrderReferenceLine $referenceLine */
            foreach ($preparation->getReferenceLines() as $referenceLine) {
                if ($referenceLine->getPickedQuantity() > 0) {
                    $referenceArticle = $referenceLine->getReference();
                    $line = array_merge($dataLivraison, [
                        $referenceArticle->getReference() ?? '',
                        $referenceArticle->getLibelle() ?? '',
                        $demande->getDestination() ? $demande->getDestination()->getLabel() : '',
                        $referenceLine->getQuantityToPick() ?? 0,
                        $referenceArticle->getQuantiteStock() ?? 0,
                        $referenceArticle->getBarCode(),
                    ]);
                    $csvService->putLine($handle, $line);
                }
            }

            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($preparation->getArticleLines() as $articleLine) {
                if ($articleLine->getPickedQuantity() > 0) {
                    $article = $articleLine->getArticle();
                    $articleFournisseur = $article->getArticleFournisseur();
                    $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
                    $reference = $referenceArticle ? $referenceArticle->getReference() : '';

                    $line = array_merge($dataLivraison, [
                        $reference,
                        $article->getLabel() ?? '',
                        $demande->getDestination() ? $demande->getDestination()->getLabel() : '',
                        $articleLine->getQuantityToPick() ?? 0,
                        $article->getQuantite() ?? 0,
                        $article->getBarCode(),
                    ]);
                    $csvService->putLine($handle, $line);
                }
            }
        }
    }

    public function createHeaderDetailsConfig(Livraison $deliveryOrder): array
    {
        return  [
            [ 'label' => 'Numéro', 'value' => $deliveryOrder->getNumero() ],
            [ 'label' => 'Statut', 'value' => $deliveryOrder->getStatut() ? ucfirst($this->formatService->status($deliveryOrder->getStatut())) : '' ],
            [ 'label' => 'Opérateur', 'value' => $this->formatService->user($deliveryOrder->getPreparation()->getUtilisateur()) ],
            [ 'label' => 'Demandeur', 'value' => $this->formatService->deliveryRequester($deliveryOrder->getDemande()) ],
            [ 'label' => 'Point de livraison', 'value' => $this->formatService->location($deliveryOrder->getDemande()->getDestination())],
            [ 'label' => 'Date de livraison', 'value' => $this->formatService->date($deliveryOrder->getDateFin()) ],
            [
                'label' => 'Date attendue',
                'value' => $this->formatService->date($deliveryOrder?->getDemande()->getExpectedAt()),
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_EXPECTED_AT]
            ],
            [
                'label' => $this->translation->translate('Référentiel', 'Projet', 'Projet', false),
                'value' => $this->formatService->project($deliveryOrder?->getDemande()?->getProject()),
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_DELIVERY_REQUEST_PROJECT]
            ],
            [
                'label' => 'Commentaire',
                'value' => $deliveryOrder->getDemande()->getCommentaire() ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ],
            [
                'label' => 'Pièces jointes',
                'value' => $deliveryOrder->getAttachments()->toArray(),
                'isAttachments' => true,
                'isNeededNotEmpty' => true
            ]
        ];
    }


    public function persistNewWaybillAttachment(EntityManagerInterface $entityManager,
                                                Livraison              $deliveryOrder): Attachment {

        $projectDir = $this->kernel->getProjectDir();
        $preparationOrder = $deliveryOrder->getPreparation();
        $articleLines = $preparationOrder->getArticleLines();

        $settingRepository = $entityManager->getRepository(Setting::class);

        $waybillTemplatePath = (
            $settingRepository->getOneParamByLabel(Setting::CUSTOM_DELIVERY_WAYBILL_TEMPLATE)
            ?: $settingRepository->getOneParamByLabel(Setting::DEFAULT_DELIVERY_WAYBILL_TEMPLATE)
        );

        $waybillData = $deliveryOrder->getWaybillData();

        $packsQuantity = Stream::from($articleLines)
            ->filter()
            ->keymap(fn(PreparationOrderArticleLine $articleLine) => [
                $articleLine->getArticle()->getCurrentLogisticUnit()->getId(),
                $articleLine->getPickedQuantity()
            ], true)
            ->map(fn($pickedQuantities) => Stream::from($pickedQuantities)->sum())
            ->toArray();

        $packs = Stream::from($articleLines)
            ->map(fn(PreparationOrderArticleLine $articleLine) => $articleLine->getArticle()->getCurrentLogisticUnit())
            ->unique()
            ->map(fn(Pack $pack) => [
                'quantity' => $packsQuantity[$pack->getId()],
                'code' => $pack->getCode(),
                'weight' => $pack->getWeight(),
                'volume' => $pack->getVolume(),
                'comment' => $pack->getComment(),
                'nature' => $this->formatService->nature($pack->getNature())
            ])
            ->values();

        $totalWeight = Stream::from($packs)
            ->map(fn(array $pack) => $pack['weight'])
            ->filter()
            ->sum();
        $totalVolume = Stream::from($packs)
            ->map(fn(array $pack) => $pack['volume'])
            ->filter()
            ->sum();
        $totalQuantities = Stream::from($packs)
            ->map(fn(array $pack) => $pack['quantity'])
            ->filter()
            ->sum();

        $waybillDate = $this->formatService->parseDatetime($waybillData['dispatchDate'] ?? null, ["Y-m-d"]);

        $variables = [
            "numordreliv" => $deliveryOrder->getNumero(),
            "qrcodenumordreliv" => $deliveryOrder->getNumero(),
            "typeliv" => $this->formatService->type($deliveryOrder->getDemande()->getType()),
            "demandeurliv" => $this->formatService->user($deliveryOrder->getDemande()->getUtilisateur()),
            "projetliv" => $this->formatService->project($deliveryOrder->getDemande()->getProject()),

            "UL" => Stream::from($packs)
                ->map(fn(array $pack) => [
                    "UL" => $pack['code'],
                    "nature" => $pack['nature'],
                    "quantite" => $pack['quantity'],
                    "poids" => $this->formatService->decimal($pack['weight'], [], '-'),
                    "volume" => $this->formatService->decimal($pack['volume'], [], '-'),
                    "commentaire" => strip_tags($pack['comment']) ?: '-',
                ])
                ->toArray(),
            "totalpoids" => $this->formatService->decimal($totalWeight, [], '-'),
            "totalvolume" => $this->formatService->decimal($totalVolume, [], '-'),
            "totalquantite" => $totalQuantities,

            // dispatch waybill data
            "dateacheminement" => $this->formatService->date($waybillDate),
            "transporteur" => $waybillData['carrier'] ?? '',
            "expediteur" => $waybillData['consignor'] ?? '',
            "destinataire" => $waybillData['receiver'] ?? '',
            "nomexpediteur" => $waybillData['consignorUsername'] ?? '',
            "telemailexpediteur" => $waybillData['consignorEmail'] ?? '',
            "nomdestinataire" => $waybillData['receiverUsername'] ?? '',
            "telemaildestinataire" => $waybillData['receiverEmail'] ?? '',
            "note" => $waybillData['notes'] ?? '',
            "lieuchargement" => $waybillData['locationFrom'] ?? '',
            "lieudechargement" => $waybillData['locationTo'] ?? '',
        ];

        $tmpDocxPath = $this->wordTemplateDocument->generateDocx(
            "${projectDir}/public/$waybillTemplatePath",
            $variables,
            ["barcodes" => ["qrcodenumordreliv"],]
        );

        $nakedFileName = uniqid();

        $waybillOutdir = "{$projectDir}/public/uploads/attachements";
        $docxPath = "{$waybillOutdir}/{$nakedFileName}.docx";
        rename($tmpDocxPath, $docxPath);

        $this->PDFGeneratorService->generateFromDocx($docxPath, $waybillOutdir);
        unlink($docxPath);

        $nowDate = new DateTime('now');

        $client = $this->specificService->getAppClientLabel();
        $title = "LDV - {$deliveryOrder->getNumero()} - {$client} - {$nowDate->format('dmYHis')}";

        $wayBillAttachment = new Attachment();
        $wayBillAttachment
            ->setDeliveryOrder($deliveryOrder)
            ->setFileName($nakedFileName . '.pdf')
            ->setOriginalName($title . '.pdf');

        $entityManager->persist($wayBillAttachment);

        return $wayBillAttachment;
    }

    public function getVisibleColumnsShow(EntityManagerInterface $entityManager, Demande $request) :Array {
        $columnsVisible = $request->getVisibleColumns();
        if ($columnsVisible === null) {
            $request->setVisibleColumns(Demande::DEFAULT_VISIBLE_COLUMNS);
            $entityManager->flush();
            $columnsVisible = $request->getVisibleColumns();
        }
        $subLineFieldsParamRepository = $entityManager->getRepository(SubLineFieldsParam::class);
        $fieldParams = $subLineFieldsParamRepository->getByEntity(SubLineFieldsParam::ENTITY_CODE_DEMANDE_REF_ARTICLE);
        $isProjectDisplayed = $fieldParams[SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_PROJECT]['displayed'] ?? false;
        $isCommentDisplayed = $fieldParams[SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT]['displayed'] ?? false;

        $columns= [
            ['name' => 'Actions', 'title' => '', 'className' => 'noVis', 'orderable' => false, 'alwaysVisible' => true],
            ['name' => 'reference', 'title' => 'Référence', 'alwaysVisible' => true],
            ['name' => 'barcode', 'title' => 'Code barre', 'alwaysVisible' => false],
            ['name' => 'label', 'title' => 'Libellé', 'alwaysVisible' => false],
            ['name' => 'quantity', 'title' => 'Quantité', 'alwaysVisible' => true],
            ['name' => 'project', 'title' => $this->translation->translate('Référentiel', 'Projet', 'Projet', false), 'alwaysVisible' => true, 'removeColumn' => !$isProjectDisplayed],
            ['name' => 'comment', 'title' => 'Commentaire', 'orderable' => false, 'alwaysVisible' => true, 'removeColumn' => !$isCommentDisplayed],
        ];

        $columns = Stream::from($columns)
            ->filter(fn (array $column) => !($column['removeColumn'] ?? false)) // display column if removeColumn not defined
            ->map(function (array $column) {
                unset($column['removeColumn']);
                return $column;
            })
            ->values();

        return $this->visibleColumnService->getArrayConfig($columns, [], $columnsVisible);
    }
}
