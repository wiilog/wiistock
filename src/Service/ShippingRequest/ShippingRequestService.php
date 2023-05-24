<?php

namespace App\Service\ShippingRequest;

use App\Entity\Attachment;
use App\Entity\FiltreSup;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\ShippingRequest\ShippingRequestLine;
use App\Entity\ShippingRequest\ShippingRequestPack;
use App\Entity\Utilisateur;
use App\Service\Document\TemplateDocumentService;
use App\Service\FormatService;
use App\Service\PDFGeneratorService;
use App\Service\SpecificService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class ShippingRequestService {

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public Security $security;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public SpecificService $specificService;

    #[Required]
    public TemplateDocumentService $wordTemplateDocument;

    #[Required]
    public PDFGeneratorService $PDFGeneratorService;

    public function getVisibleColumnsConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getVisibleColumns()['shippingRequest'];
        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => 'Numéro', 'name' => 'number'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Date de création', 'name' => 'createdAt'],
            ['title' => 'Date de prise en charge souhaitée', 'name' => 'requestCaredAt'],
            ['title' => 'Date de validation', 'name' => 'validatedAt'],
            ['title' => 'Date de planification', 'name' => 'plannedAt'],
            ['title' => 'Date d\'enlèvement prévu', 'name' => 'expectedPickedAt'],
            ['title' => 'Date d\'expédition', 'name' => 'treatedAt'],
            ['title' => 'Demandeur', 'name' => 'requesters'],
            ['title' => 'N° commande client', 'name' => 'customerOrderNumber'],
            ['title' => 'Livraison à titre gracieux', 'name' => 'freeDelivery'],
            ['title' => 'Articles conformes', 'name' => 'compliantArticles'],
            ['title' => 'Client', 'name' => 'customerName'],
            ['title' => 'A l\'attention de', 'name' => 'customerRecipient'],
            ['title' => 'Téléphone', 'name' => 'customerPhone'],
            ['title' => 'Adresse de livraison', 'name' => 'customerAddress'],
            ['title' => 'Transporteur', 'name' => 'carrier'],
            ['title' => 'Numéro tracking', 'name' => 'trackingNumber'],
            ['title' => 'Envoi', 'name' => 'shipment'],
            ['title' => 'Port', 'name' => 'carrying'],
            ['title' => 'Commentaire', 'name' => 'comment'],
            ['title' => 'Poids brut (kg)', 'name' => 'grossWeight'],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, [], $columnsVisible);
    }

    public function getDataForDatatable(Request $request, EntityManager $entityManager) : array {
        $shippingRepository = $entityManager->getRepository(ShippingRequest::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_SHIPPING, $this->security->getUser());

        $queryResult = $shippingRepository->findByParamsAndFilters(
            $request->request,
            $filters,
            $this->visibleColumnService,
            [
                'user' => $this->security->getUser(),
            ]
        );

        $shippingRequests = $queryResult['data'];

        $rows = [];
        foreach ($shippingRequests as $shipping) {
            $rows[] = $this->dataRowShipping($shipping);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowShipping(ShippingRequest $shipping): array
    {
        $url = $this->router->generate('shipping_show_page', [
            "id" => $shipping->getId()
        ]);

        $formatService = $this->formatService;

        $row = [
            "actions" => $this->templating->render('shipping_request/actions.html.twig', [
                'url' => $url,
            ]),
            "number" => $shipping->getNumber(),
            "status" => $formatService->status($shipping->getStatus()),
            "createdAt" => $formatService->datetime($shipping->getCreatedAt()),
            "requestCaredAt" => $formatService->datetime($shipping->getRequestCaredAt()),
            "validatedAt" => $formatService->datetime($shipping->getValidatedAt()),
            "plannedAt" => $formatService->datetime($shipping->getPlannedAt()),
            "expectedPickedAt" => $formatService->datetime($shipping->getExpectedPickedAt()),
            "treatedAt" => $formatService->datetime($shipping->getTreatedAt()),
            "requesters" => $formatService->users($shipping->getRequesters()),
            "customerOrderNumber" => $shipping->getCustomerOrderNumber(),
            "freeDelivery" => $formatService->bool($shipping->isFreeDelivery()),
            "compliantArticles" => $formatService->bool($shipping->isCompliantArticles()),
            "customerName" => $shipping->getCustomerName(),
            "customerRecipient" => $shipping->getCustomerRecipient(),
            "customerPhone" => $shipping->getCustomerPhone(),
            "customerAddress" => $shipping->getCustomerAddress(),
            "carrier" => $formatService->carrier($shipping->getCarrier()),
            "trackingNumber" => $shipping->getTrackingNumber(),
            "shipment" => ShippingRequest::SHIPMENT_LABELS[$shipping->getShipment()] ?? '',
            "carrying" => ShippingRequest::CARRYING_LABELS[$shipping->getCarrying()] ?? '',
            "comment" => $shipping->getComment(),
            "grossWeight" => $shipping->getGrossWeight(),
        ];

        return $row;
    }

    public function createHeaderTransportDetailsConfig(ShippingRequest $shippingRequest){
        return $this->templating->render('shipping_request/show-transport-header.html.twig', [
            'shipping' => $shippingRequest,
            'packsCount' => $shippingRequest->getPackCount(),
            'totalValue' => Stream::from($shippingRequest->getExpectedLines())
                ->map(fn(ShippingRequestExpectedLine $expectedLine) => $expectedLine->getPrice())
                ->sum(),
            'netWeight' => Stream::from($shippingRequest->getExpectedLines())
                ->map(fn(ShippingRequestExpectedLine $expectedLine) => $expectedLine->getWeight())
                ->sum(),
        ]);
    }

    public function persistNewDeliverySlipAttachment(EntityManagerInterface     $entityManager,
                                                     ShippingRequest            $shippingRequest): Attachment {
        $formatService = $this->formatService;
        $projectDir = $this->kernel->getProjectDir();
        $settingRepository = $entityManager->getRepository(Setting::class);

        $deliverySlipTemplatePath = $settingRepository->getOneParamByLabel(Setting::CUSTOM_DELIVERY_SLIP_TEMPLATE)
            ?: $settingRepository->getOneParamByLabel(Setting::DEFAULT_DELIVERY_SLIP_TEMPLATE);

        $shippingRequestStatus = $shippingRequest->getStatus()->getNom();

        $variables = [
            //en-tête
            "QRCodeexpe" => $shippingRequest->getNumber() ?? "",
            "numexpedition" => $shippingRequest->getNumber() ?? "",
            "demandeurs" => $formatService->users($shippingRequest->getRequesters()) ?? "",
            "teldemandeur" => implode(', ', $shippingRequest->getRequesterPhoneNumbers()) ?? "",
            "numcommandeclient" => $shippingRequest->getCustomerOrderNumber() ?? "",
            "livraisongracieux" => $formatService->bool($shippingRequest->isFreeDelivery()) ?? "",
            "articlesconformes" => $formatService->bool($shippingRequest->isCompliantArticles()) ?? "",
            "client" => $shippingRequest->getCustomerName() ?? "",
            "destinataire" => $shippingRequest->getCustomerRecipient() ?? "",
            "teldestinataire" => $shippingRequest->getCustomerPhone() ?? "",
            "adressedestinataire" => $shippingRequest->getCustomerAddress() ?? "",
            "datecreation" => $formatService->date($shippingRequest->getCreatedAt()) ?? "",
            "dateexpedition" => $formatService->date($shippingRequest->getTreatedAt()) ?? "",

            //transport
            "datepriseenchargesouhaitee" => $formatService->date($shippingRequest->getRequestCaredAt()) ?? "",
            "port" => ShippingRequest::CARRYING_LABELS[$shippingRequest->getCarrying()] ?? "",
            "dateenlevement" => $formatService->date($shippingRequest->getExpectedPickedAt()) ?? "",
            "nomtransporteur" => $formatService->carrier($shippingRequest->getCarrier()) ?? "",
            "numtracking" => $shippingRequest->getTrackingNumber() ?? "",

            //footer
            "nbcolis" => $shippingRequest->getPackCount() ?? "",
            "poidsbruttotal" => $shippingRequest->getGrossWeight() ?? "",
            "dimensioncolis" => "",
            "specificationtransport" => "",
        ];

        if ($shippingRequestStatus == ShippingRequest::STATUS_TO_TREAT) {
            $variables["reference"] = $shippingRequest->getExpectedLines()
                ->map(function (ShippingRequestExpectedLine $line) {
                    $reference = $line->getReferenceArticle();
                    $price = $reference->getPrixUnitaire();
                    $quantity = $line->getQuantity();
                    $totalPrice = $price * $quantity;
                    return [
                        "reference" => $reference->getReference() ?? "",
                        "quantite" => $line->getQuantity() ?? "",
                        "prixunitaire" => $reference->getPrixUnitaire() ?? "",
                        "poidsnet" => $line->getWeight() ?? "",
                        "montantotal" => $totalPrice ?? "",
                        "matieredangereuse" => $this->formatService->bool($reference->isDangerousGoods()) ?? "",
                        "FDS" => $reference->getSheet() ? $reference->getSheet()->getFileName() : "",
                        "CodeONU" => $reference->getOnuCode() ?? "",
                        "classeproduit" => $reference->getProductClass() ?? "",
                        "CodeNDP" => $reference->getNdpCode() ?? "",
                    ];
                })
                ->toArray();

            $totalNetWeight = Stream::from($shippingRequest->getExpectedLines())
                ->map(fn(ShippingRequestExpectedLine $line) => $line->getWeight() * $line->getQuantity())
                ->sum();

            $totalNetWorth = Stream::from($shippingRequest->getExpectedLines())
                ->map(fn(ShippingRequestExpectedLine $line) => $line->getReferenceArticle()->getPrixUnitaire() * $line->getQuantity())
                ->sum();

        } else if ($shippingRequestStatus == ShippingRequest::STATUS_SCHEDULED) {

            $packLines = Stream::from($shippingRequest->getPackLines()->toArray())
                ->map(fn(ShippingRequestPack $shippingRequestPack) => $shippingRequestPack->getLines())
                ->toArray();

            $articlesOrReference = [];
            foreach ($packLines as $lines) {
                $articlesOrReference[] = Stream::from($lines)
                    ->map(fn(ShippingRequestLine $shippingRequestLine) => $shippingRequestLine->getArticle())
                    ->toArray();
            }

            dump($articlesOrReference);
        }

        $variables["poidsnettotal"] = $totalNetWeight;
        $variables["valeurtotal"] = $totalNetWorth;

        $tmpDocxPath = $this->wordTemplateDocument->generateDocx(
            "${projectDir}/public/$deliverySlipTemplatePath",
            $variables,
            ["barcodes" => ["QRCodeexpe"],]
        );

        $nakedFileName = uniqid();

        $deliverySlipOutdir = "{$projectDir}\public\uploads\attachements";
        $docxPath = "{$deliverySlipOutdir}\\{$nakedFileName}.docx";

        rename($tmpDocxPath, $docxPath);
        $this->PDFGeneratorService->generateFromDocx($docxPath, $deliverySlipOutdir);
        unlink($docxPath);

        $now = new \DateTime();

        $client = SpecificService::CLIENTS[$this->specificService->getAppClient()];

        $name = "BDL - {$shippingRequest->getNumber()} - {$client} - {$now->format('dmYHis')}";

        $deliverySlipAttachment = new Attachment();
        $deliverySlipAttachment
            ->setFileName($nakedFileName . '.pdf')
            ->setOriginalName($name . '.pdf');

        $shippingRequest->addAttachment($deliverySlipAttachment);

        $entityManager->persist($deliverySlipAttachment);

        return $deliverySlipAttachment;
    }

}
