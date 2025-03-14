<?php


namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Attachment;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Service\Document\TemplateDocumentService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class PurchaseRequestService {

    public function __construct(
        private SettingsService         $settingService,
        private Twig_Environment        $templating,
        private RouterInterface         $router,
        private TemplateDocumentService $wordTemplateDocument,
        private UniqueNumberService     $uniqueNumberService,
        private MailerService           $mailerService,
        private FormatService           $formatService,
        private PDFGeneratorService     $PDFGeneratorService,
        private EntityManagerInterface  $entityManager,
        private KernelInterface         $kernel,
        private ReceptionService        $receptionService,
        private ReceptionLineService    $receptionLineService,
        private UserService             $userService,
        private TranslationService      $translation,
    ) {
    }

    public function getDataForDatatable($params = null): array {
        $filters = $this->entityManager->getRepository(FiltreSup::class)
            ->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PURCHASE_REQUEST, $this->userService->getUser());

        $queryResult = $this->entityManager->getRepository(PurchaseRequest::class)
            ->findByParamsAndFilters($params, $filters);

        $requests = $queryResult['data'];

        $rows = [];
        foreach ($requests as $request) {
            $rows[] = $this->dataRowPurchaseRequest($request);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowPurchaseRequest(PurchaseRequest $request): array {
        $url = $this->router->generate('purchase_request_show', [
            "id" => $request->getId()
        ]);

        return [
            'id' => $request->getId(),
            'number' => $request->getNumber(),
            'status' => $this->formatService->status($request->getStatus()),
            'requester' => $this->formatService->user($request->getRequester()),
            'buyer' => $this->formatService->user($request->getBuyer()),
            'creationDate' => $this->formatService->datetime($request->getCreationDate()),
            'processingDate' => $this->formatService->datetime($request->getProcessingDate()),
            'validationDate' => $this->formatService->datetime($request->getValidationDate()),
            'considerationDate' => $this->formatService->datetime($request->getConsiderationDate()),
            'supplier' => $this->formatService->supplier($request->getSupplier()),
            'deliveryFee' => $request->getDeliveryFee(),
            'actions' => $this->templating->render('purchase_request/actions.html.twig', [
                'url' => $url,
            ]),
        ];
    }

    public function putPurchaseRequestLine($handle,
                                           CSVExportService $CSVExportService,
                                           array $request,
                                           array $line = []): void {
        $CSVExportService->putLine($handle, [
            $request['number'] ?? '',
            $request['statusName'] ?? '',
            $request['requester'] ?? '',
            $request['buyer'] ?? '',
            $this->formatService->datetime($request['creationDate'] ?? null),
            $this->formatService->datetime($request['validationDate'] ?? null),
            $this->formatService->datetime($request['considerationDate'] ?? null),
            $this->formatService->datetime($request['processingDate'] ?? null),
            $this->formatService->html($request['comment'] ?? null),
            $line['reference'] ?? '',
            $line['barcode'] ?? '',
            $line['label'] ?? '',
            $line['supplierName'] ?? '',
            $line['purchaseRequestLineUnitPrice'] ?? '',
            $request['deliveryFee'] ?? '',
        ]);
    }

    public function createHeaderDetailsConfig(PurchaseRequest $request): array {
        return [
            ['label' => 'Statut', 'value' => $this->formatService->status($request->getStatus())],
            ['label' => 'Demandeur', 'value' =>  $this->formatService->user($request->getRequester())],
            ['label' => 'Acheteur', 'value' => $this->formatService->user($request->getBuyer())],
            ['label' => 'Date de création', 'value' => $this->formatService->datetime($request->getCreationDate())],
            ['label' => 'Date de validation', 'value' => $this->formatService->datetime($request->getValidationDate())],
            ['label' => 'Date de prise en compte', 'value' => $this->formatService->datetime($request->getConsiderationDate())],
            ['label' => 'Date de traitement', 'value' => $this->formatService->datetime($request->getProcessingDate())],
            ['label' => 'Fournisseur', 'value' => $this->formatService->supplier($request->getSupplier())],
            ['label' => 'Frais de livraison', 'value' => $request->getDeliveryFee()],
            [
                'label' => 'Commentaire',
                'value' => $request->getComment() ?: "",
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ],
            [
                'label' => 'Pièces jointes',
                'value' => $request->getAttachments()->toArray(),
                'isAttachments' => true,
                'isNeededNotEmpty' => true
            ]
        ];
    }

    public function createPurchaseRequest(?Statut      $status,
                                          ?Utilisateur $requester,
                                          array        $options = []): PurchaseRequest {
        $comment = $options["comment"] ?? null;
        $validationDate = $options["validationDate"] ?? null;
        $buyer = $options["buyer"] ?? null;
        $supplier = $options["supplier"] ?? null;
        $now = new DateTime("now");
        $purchase = new PurchaseRequest();
        $purchaseRequestNumber = $this->uniqueNumberService->create($this->entityManager, PurchaseRequest::NUMBER_PREFIX, PurchaseRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);
        $purchase
            ->setCreationDate($now)
            ->setStatus($status)
            ->setRequester($requester)
            ->setComment($comment)
            ->setNumber($purchaseRequestNumber)
            ->setSupplier($supplier)
            ->setValidationDate($validationDate)
            ->setDeliveryFee($options["deliveryFee"] ?? null);

        if ($buyer) {
            $purchase->setBuyer($buyer);
        }

        return $purchase;
    }

    public function createPurchaseRequestLine(?ReferenceArticle $reference,
                                              ?int              $requestedQuantity,
                                              array             $options = []): PurchaseRequestLine {
        $supplier = $options["supplier"] ?? null;
        $purchaseRequest = $options["purchaseRequest"] ?? null;
        $location = $options["location"] ?? null;

        $purchaseLine = new PurchaseRequestLine();
        $purchaseLine
            ->setReference($reference)
            ->setRequestedQuantity($requestedQuantity)
            ->setSupplier($supplier)
            ->setLocation($location)
            ->setPurchaseRequest($purchaseRequest);

        return $purchaseLine;
    }

    public function sendMailsAccordingToStatus(EntityManagerInterface $entityManager,
                                               PurchaseRequest        $purchaseRequest,
                                               array                  $options = []): void {
        $customSubject = $options['customSubject'] ?? null;

        $articleRepository = $entityManager->getRepository(Article::class);

        /** @var Statut $status */
        $status = $purchaseRequest->getStatus();
        $emailRequesters = [];

        $buyerAbleToReceivedMail = $status->getSendNotifToBuyer();
        if ($buyerAbleToReceivedMail) {
            $buyer = $purchaseRequest->getBuyer();
            if ($buyer){
                $emailRequesters[] = $buyer;
            } else {
                $emailRequesters = Stream::from($purchaseRequest->getPurchaseRequestLines())
                    ->filterMap(fn(PurchaseRequestLine $line) => $line->getReference()->getBuyer())
                    ->concat($emailRequesters)
                    ->toArray();
            }
        }

        $requesterAbleToReceivedMail = $status->getSendNotifToDeclarant();
        if ($requesterAbleToReceivedMail) {
            $requester = $purchaseRequest->getRequester();
            if ($requester){
                $emailRequesters[] = $requester;
            }
        }

        if (!empty($emailRequesters)) {
            $subject = $customSubject ?: (
                $status->isTreated()
                    ? 'Traitement d\'une demande d\'achat'
                    : ($status->isNotTreated()
                        ? 'Création d\'une demande d\'achat'
                        : 'Changement de statut d\'une demande d\'achat')
            );

            $statusName = $this->formatService->status($status);
            $number = $purchaseRequest->getNumber();
            $processingDate = $this->formatService->datetime($purchaseRequest->getProcessingDate(), "", true);
            $title = $status->isTreated()
                ? "Demande d'achat {$number} traitée le {$processingDate} avec le statut {$statusName}"
                : ($status->isNotTreated()
                    ? 'Une demande d\'achat vous concerne'
                    : 'Changement de statut d\'une demande d\'achat vous concernant');

            $refsAndQuantities = Stream::from($purchaseRequest->getPurchaseRequestLines())
                ->keymap(function(PurchaseRequestLine $line) use ($articleRepository) {
                    $key = $line->getId();
                    $value = $line->getReference()->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE && $line->getLocation()
                        ? $articleRepository->quantityForRefOnLocation($line->getReference(), $line->getLocation())
                        : $line->getReference()->getQuantiteStock();
                    return [$key, $value];
                })
                ->toArray();

            $this->mailerService->sendMail(
                $entityManager,
                $this->translation->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR . $subject,
                $this->templating->render('mails/contents/mailPurchaseRequestEvolution.html.twig', [
                    'title' => $title,
                    'purchaseRequest' => $purchaseRequest,
                    'refsAndQuantities' => $refsAndQuantities
                ]),
                $emailRequesters
            );
        }
    }

    public function getDataForReferencesDatatable($params = null): array {
        $demande = $this->entityManager->find(PurchaseRequest::class, $params);
        $referenceLines = $demande->getPurchaseRequestLines();

        $rows = [];
        foreach ($referenceLines as $referenceLine) {
            $rows[] = $this->dataRowReference($referenceLine);
        }

        return [
            'data' => $rows,
            'recordsTotal' => count($rows),
        ];
    }

    public function dataRowReference(PurchaseRequestLine $line): array {
        return [
            'reference' => $line->getReference()->getReference(),
            'libelle' => $line->getReference()->getLibelle(),
            'quantity' => $line->getRequestedQuantity(),
        ];
    }

    public function getPurchaseRequestOrderData(EntityManagerInterface  $entityManager,
                                                PurchaseRequest         $purchaseRequest): Attachment {
        $projectDir = $this->kernel->getProjectDir();
        $reportTemplatePath = (
        $this->settingService->getValue($this->entityManager, Setting::CUSTOM_PURCHASE_ORDER_TEMPLATE)
            ?: $this->settingService->getValue($this->entityManager, Setting::DEFAULT_PURCHASE_ORDER_TEMPLATE)
        );

        $nowDate = new DateTime("now");
        $customPurchaseRequestOrderTitle = "Bon de commande - {$purchaseRequest->getNumber()} - {$nowDate->format('dmYHis')}";

        // sum all lines prices
        $totalPrice = Stream::from($purchaseRequest->getPurchaseRequestLines())
            ->map(static fn(PurchaseRequestLine $line) => $line->getUnitPrice() * $line->getOrderedQuantity())
            ->sum();
        // add delivery fee to total price
        $totalPrice += $purchaseRequest->getDeliveryFee();

        $variables = [
            "nomfournisseur" => $purchaseRequest->getSupplier()?->getNom() ?: "",
            "dategenerationdocument" => $this->formatService->datetime(new DateTime("now")),
            "fraislivraison" => $purchaseRequest->getDeliveryFee(),
            "acheteur" => $this->formatService->user($purchaseRequest->getBuyer()),
            "destinataire" => $this->formatService->user($purchaseRequest->getRequester()),
            "adressefournisseur" => $purchaseRequest->getSupplier()?->getAddress() ?: "",

            "destinatairefournisseur" => $purchaseRequest->getSupplier()?->getReceiver() ?: "",
            "telephonefournisseur" => $purchaseRequest->getSupplier()?->getPhoneNumber() ?: "",
            "emailfournisseur" => $purchaseRequest->getSupplier()?->getEmail() ?: "",
            "prixtotal" => $totalPrice,
        ];

        $variables['referencearticlefournisseur'] = $purchaseRequest->getPurchaseRequestLines()
            ->map(function (PurchaseRequestLine $line) {
                $reference = $line->getReference();
                $supplier = $line->getSupplier();
                $supplierArticle = $reference->getArticlesFournisseur()
                    ->filter(fn(ArticleFournisseur $supplierArticle) => $supplier && $supplierArticle->getFournisseur() && $supplierArticle->getFournisseur()->getId() === $supplier->getId() )
                    ->first() ?: null;

                return [
                    "reference" => $reference->getReference() ?: "",
                    "libellereference" => $reference->getLibelle() ?: "",
                    "libellearticlefournisseur" => $supplierArticle?->getLabel() ?: "",
                    "referencearticlefournisseur" => $supplierArticle?->getReference() ?: "",
                    "prixunitaire" => $line->getUnitPrice() ?: 0,
                    "quantitecommandee" => $line->getOrderedQuantity() ?: 0,
                    "numerocommande" => $line->getOrderNumber() ?: "",
                    "prixligne" => ($line->getUnitPrice() * $line->getOrderedQuantity()) ?: 0,
                    "datecommande" => $this->formatService->datetime($line->getOrderDate()),
                    "dateattendue" => $this->formatService->date($line->getExpectedDate()),
                ];
            })
            ->filter(fn($row) => !empty($row["referencearticlefournisseur"]))
            ->getValues();

        $tmpDocxPath = $this->wordTemplateDocument->generateDocx(
            "{$projectDir}/public/$reportTemplatePath",
            $variables,
            ["barcodes" => ["qrcodenumach"],]
        );

        $nakedFileName = uniqid();

        $reportOutdir = "{$projectDir}/public/uploads/attachments";
        $docxPath = "{$reportOutdir}/{$nakedFileName}.docx";

        rename($tmpDocxPath, $docxPath);
        $this->PDFGeneratorService->generateFromDocx($docxPath, $reportOutdir);
        unlink($docxPath);

        $purchaseRequestOrderAttachment = new Attachment();
        $purchaseRequestOrderAttachment
            ->setFileName($nakedFileName . '.pdf')
            ->setFullPath('/uploads/attachments/' . $nakedFileName . '.pdf')
            ->setOriginalName($customPurchaseRequestOrderTitle . '.pdf');
        $purchaseRequest->addAttachment($purchaseRequestOrderAttachment);

        $entityManager->persist($purchaseRequestOrderAttachment);

        return $purchaseRequestOrderAttachment;
    }

    /** Create reception if status allow it ($treatedStatus->getAutomaticReceptionCreation())
     * @param PurchaseRequest $purchaseRequest
     * @param EntityManager $entityManager
     * @return void
     */
    public function persistAutomaticReceptionWithStatus(EntityManagerInterface $entityManager, PurchaseRequest $purchaseRequest) :void {
            $locationRepository = $entityManager->getRepository(Emplacement::class);

            $receptionsWithCommand = [];

            $defaultLocationReceptionSetting = $this->settingService->getValue($this->entityManager,Setting::DEFAULT_LOCATION_RECEPTION);

            // To disable error in persistReception we check if default location setting is valid
            $defaultLocationReception = $defaultLocationReceptionSetting
                ? $locationRepository->find($defaultLocationReceptionSetting)
                : null;

            foreach ($purchaseRequest->getPurchaseRequestLines() as $purchaseRequestLine) {
                $orderNumber = $purchaseRequestLine->getOrderNumber() ?? null;
                $expectedDate = $this->formatService->date($purchaseRequestLine->getExpectedDate());

                $uniqueReceptionConstraint = [
                    'orderNumber' => $orderNumber,
                    'expectedDate' => $expectedDate,
                ];

                $orderDate = $this->formatService->date($purchaseRequestLine->getOrderDate());
                $reception = $this->receptionService->getAlreadySavedReception($this->entityManager, $receptionsWithCommand, $uniqueReceptionConstraint);
                $receptionData = [
                    "fournisseur"  => $purchaseRequestLine->getSupplier() ? $purchaseRequestLine->getSupplier()->getId() : '',
                    "orderNumber"  => $orderNumber,
                    "commentaire"  => $purchaseRequest->getComment() ?? '',
                    "dateAttendue" => $expectedDate,
                    "dateCommande" => $orderDate,
                    "location"     => $defaultLocationReception?->getId()
                ];
                if (!$reception) {
                    $reception = $this->receptionService->persistReception($this->entityManager, $this->userService->getUser(), $receptionData);
                    $this->receptionService->setAlreadySavedReception($receptionsWithCommand, $uniqueReceptionConstraint, $reception);
                } else if($reception->getFournisseur() !== $purchaseRequestLine->getSupplier()) {
                    $reception =  $this->receptionService->persistReception($this->entityManager, $this->userService->getUser(), $receptionData);
                }

                $receptionLine = $reception->getLine(null)
                    ?? $this->receptionLineService->persistReceptionLine($this->entityManager, $reception, null);

                $receptionReferenceArticle = new ReceptionReferenceArticle();
                $receptionReferenceArticle
                    ->setReceptionLine($receptionLine)
                    ->setReferenceArticle($purchaseRequestLine->getReference())
                    ->setQuantiteAR($purchaseRequestLine->getOrderedQuantity())
                    ->setCommande($orderNumber)
                    ->setQuantite(0)
                    ->setUnitPrice($purchaseRequestLine->getUnitPrice());

                $this->entityManager->persist($receptionReferenceArticle);
                $purchaseRequestLine->setReception($reception);
        }
    }


}
