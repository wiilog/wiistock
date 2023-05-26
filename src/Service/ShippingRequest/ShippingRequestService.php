<?php

namespace App\Service\ShippingRequest;

use App\Entity\Attachment;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Customer;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Role;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\ReferenceArticle;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\ShippingRequest\ShippingRequestLine;
use App\Entity\ShippingRequest\ShippingRequestPack;
use App\Entity\TrackingMovement;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Exceptions\FormException;
use App\Service\Document\TemplateDocumentService;
use App\Service\FormatService;
use App\Service\PDFGeneratorService;
use App\Service\SpecificService;
use App\Service\MailerService;
use App\Service\PackService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UserService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\MakerBundle\Str;
use DateTime;
use Symfony\Component\HttpFoundation\InputBag;
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
    public MailerService $mailerService;

    #[Required]
    public CSVExportService $CSVExportService;

    #[Required]
    public PackService $packService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public UserService $userService;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public SpecificService $specificService;

    #[Required]
    public TemplateDocumentService $wordTemplateDocument;

    #[Required]
    public PDFGeneratorService $PDFGeneratorService;

    #[Required]
    public TranslationService $translationService;

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

    public function getDataForDatatable(EntityManagerInterface $entityManager, Request $request) : array{
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
        $formatService = $this->formatService;

        $url = $this->router->generate('shipping_request_show', [
            "shippingRequest" => $shipping->getId()
        ]);
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
        ]);
    }

    public function sendMailForStatus(EntityManagerInterface $entityManager, ShippingRequest $shippingRequest)
    {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $roleRepository = $entityManager->getRepository(Role::class);

        $to = [];
        $mailTitle = '';
        $title = '';
        //Validation
        if($shippingRequest->isToTreat()) {
            $mailTitle = "FOLLOW GT // Création d'une " . strtolower($this->translationService->translate("Demande", "Expédition", "Demande d'expédition", false));
            $title = "Une " . strtolower($this->translationService->translate("Demande", "Expédition", "Demande d'expédition", false)) . " a été créée";
            if($settingRepository->getOneParamByLabel(Setting::SHIPPING_TO_TREAT_SEND_TO_REQUESTER)){
                $to = array_merge($to, $shippingRequest->getRequesters()->toArray());
            }
            if($settingRepository->getOneParamByLabel(Setting::SHIPPING_TO_TREAT_SEND_TO_USER_WITH_ROLES)){
                $rolesToSendMail = $roleRepository->findBy(['id' => Stream::explode(',',$settingRepository->getOneParamByLabel(Setting::SHIPPING_TO_TREAT_SEND_TO_ROLES))->toArray()]);
                $to = array_merge(
                    $to,
                    $userRepository->findBy(["role" => $rolesToSendMail]),
                );
            }
        }

        //Planification
        if($shippingRequest->isShipped()) {
            $mailTitle = "FOLLOW GT // " . $this->translationService->translate("Demande", "Expédition", "Demande d'expédition", false) . " effectuée";
            $title = "Vos produits ont bien été expédiés";
            if($settingRepository->getOneParamByLabel(Setting::SHIPPING_SHIPPED_SEND_TO_REQUESTER)){
                $to = array_merge($to, $shippingRequest->getRequesters()->toArray());
            }
            if($settingRepository->getOneParamByLabel(Setting::SHIPPING_SHIPPED_SEND_TO_USER_WITH_ROLES)){
                $rolesToSendMail = $roleRepository->findBy(['id' => Stream::explode(',',$settingRepository->getOneParamByLabel(Setting::SHIPPING_SHIPPED_SEND_TO_ROLES))->toArray()]);
                $to = array_merge(
                    $to,
                    $userRepository->findBy(["role" => $rolesToSendMail]),
                );
            }
        }

        $this->mailerService->sendMail(
            $mailTitle,
            [
                "name" => "mails/contents/mailShippingRequest.html.twig",
                "context" => [
                    "title" => $title,
                    "shippingRequest" => $shippingRequest,
                    "isToTreat" => $shippingRequest->isToTreat(),
                    "isShipped" => $shippingRequest->isShipped(),
                ]
            ],
            $to
        );

    }

    public function formatExpectedLinesForPacking(iterable $expectedLines): array {
        return Stream::from($expectedLines)
            ->map(function(ShippingRequestExpectedLine $expectedLine) {
                return [
                    'lineId' => $expectedLine->getId(),
                    'referenceArticleId' => $expectedLine->getReferenceArticle()->getId(),
                    'label' => $expectedLine->getReferenceArticle()->getLibelle(),
                    'quantity' => $expectedLine->getQuantity(),
                    'price' => $expectedLine->getUnitPrice(),
                    'weight' => $expectedLine->getUnitWeight(),
                    'totalPrice' => '<span class="total-price"></span>',
                ];
            })
            ->toArray();
    }

    public function putShippingRequestLine($output, array $shippingRequestData): void {
        $line = [
            $shippingRequestData['number'],
            $shippingRequestData['statusCode'],
            $this->formatService->datetime($shippingRequestData['createdAt']),
            $this->formatService->datetime($shippingRequestData['validatedAt']),
            $this->formatService->datetime($shippingRequestData['plannedAt']),
            $this->formatService->datetime($shippingRequestData['expectedPickedAt']),
            $this->formatService->datetime($shippingRequestData['treatedAt']),
            $this->formatService->datetime($shippingRequestData['requestCaredAt']),
            $shippingRequestData['requesterNames'],
            $shippingRequestData['customerOrderNumber'],
            $this->formatService->bool($shippingRequestData['freeDelivery']),
            $this->formatService->bool($shippingRequestData['compliantArticles']),
            $shippingRequestData['customerName'],
            $shippingRequestData['customerRecipient'],
            $shippingRequestData['customerPhone'],
            $shippingRequestData['customerAddress'],
            $shippingRequestData['packCode'],
            $shippingRequestData['nature'],
            $shippingRequestData['refArticle'],
            $shippingRequestData['refArticleLibelle'],
            $shippingRequestData['article'],
            $shippingRequestData['articleQuantity'],
            $shippingRequestData['price'],
            $shippingRequestData['weight'],
            $shippingRequestData['totalAmount'],
            $this->formatService->bool($shippingRequestData['dangerous_goods']),
            $shippingRequestData['onu_code'],
            $shippingRequestData['product_class'],
            $shippingRequestData['ndp_code'],
            $shippingRequestData['shipment'],
            $shippingRequestData['carrying'],
            $shippingRequestData['nbPacks'],
            $shippingRequestData['size'],
            $shippingRequestData['totalWeight'],
            $shippingRequestData['grossWeight'],
            $shippingRequestData['totalSum'],
            $shippingRequestData['carrierName'],
        ];

        $this->CSVExportService->putLine($output, $line);
    }

    public function updateShippingRequest(EntityManagerInterface $entityManager, ShippingRequest $shippingRequest, InputBag $data): bool {
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $customerRepository = $entityManager->getRepository(Customer::class);
        $requiredFields = [
            'requesters',
            'requesterPhoneNumbers',
            'customerOrderNumber',
            'customerName',
            'customerAddress',
            'customerPhone',
            'requestCaredAt',
            'shipment',
            'carrying',
        ];

        foreach ($requiredFields as $requiredField) {
            if (!$data->has($requiredField) && !empty($data->get($requiredField))) {
                throw new FormException("Une erreur est survenue un champ requis est manquant");
            }
        }

        $requestersIds = Stream::explode(',', $data->get('requesters'))
            ->filter()
            ->toArray();
        $requesters = $userRepository->findBy(['id' => $requestersIds]);

        if (empty($requesters)) {
            throw new FormException("Vous devez sélectionner au moins un demandeur");
        }

        $carrierId = $data->get('carrier');
        $carrier = $carrierId
            ? $carrierRepository->find($carrierId)
            : null;

        $customerName = $data->get('customerName');
        $customer = $customerName
            ? $customerRepository->findBy(['name' => $customerName])
            : null;

        $customerPhone = $data->get('customerPhone');
        $customerRecipient = $data->get('customerRecipient');
        $customerAddress = $data->get('customerAddress');

        if(!$customer){
            $newCustomer = (new Customer())
                ->setName($customerName)
                ->setPhoneNumber($customerPhone)
                ->setRecipient($customerRecipient)
                ->setAddress($customerAddress);

            $entityManager->persist($newCustomer);
        }

        $shippingRequest
            ->setRequesterPhoneNumbers(explode(',', $data->get('requesterPhoneNumbers')))
            ->setCustomerOrderNumber($data->get('customerOrderNumber'))
            ->setFreeDelivery($data->getBoolean('freeDelivery'))
            ->setCompliantArticles($data->getBoolean('compliantArticles'))
            ->setCustomerName($customerName)
            ->setCustomerPhone($customerPhone)
            ->setCustomerRecipient($customerRecipient)
            ->setCustomerAddress($customerAddress)
            ->setRequestCaredAt($this->formatService->parseDatetime($data->get('requestCaredAt')))
            ->setShipment($data->get('shipment'))
            ->setCarrying($data->get('carrying'))
            ->setComment($data->get('comment'))
            ->setRequesters($requesters)
            ->setCarrier($carrier);
        return true;
    }

    public function updateNetWeight(ShippingRequest $shippingRequest): void {
        $shippingRequest->setNetWeight(
            Stream::from($shippingRequest->getExpectedLines())
                ->map(fn(ShippingRequestExpectedLine $line) => $line->getQuantity() && $line->getUnitWeight() ? $line->getQuantity() * $line->getUnitWeight() : 0)
                ->sum()
        );
    }

    public function updateTotalValue(ShippingRequest $shippingRequest): void {
        $shippingRequest->setTotalValue(
            Stream::from($shippingRequest->getExpectedLines())
                ->map(fn(ShippingRequestExpectedLine $line) => $line->getQuantity() && $line->getUnitPrice() ? $line->getQuantity() * $line->getUnitPrice() : 0)
                ->sum()
        );
    }

    public function createShippingRequestPack(EntityManagerInterface $entityManager, ShippingRequest $shippingRequest, int $packNumber, ?string $size, Emplacement $packLocation, array $options = []) :ShippingRequestPack {
        $natureRepository = $entityManager->getRepository(Nature::class);
        $packNatureId = $natureRepository->findOneBy(['defaultNature' => true]);
        if(!$packNatureId) {
            throw new FormException("Aucune nature n'est définie comme nature par défaut.");
        }

        $packsCode = str_replace(ShippingRequest::NUMBER_PREFIX.'-', '', $shippingRequest->getNumber());
        $pack = $this->packService->persistPack($entityManager, $packsCode.$packNumber, 1, $packNatureId);
        $date =  $options['date'] ?? new DateTime('now');
        $trackingMovement = $this->trackingMovementService->createTrackingMovement(
            $pack,
            $packLocation,
            $this->security->getUser(),
            $date,
            false,
            null,
            TrackingMovement::TYPE_DEPOSE
        );
        $entityManager->persist($trackingMovement);

        $shippingRequestPack = new ShippingRequestPack();
        $shippingRequestPack
            ->setPack($pack)
            ->setRequest($shippingRequest)
            ->setSize($size);

        return $shippingRequestPack;
    }


    public function getDataForScheduledRequest(ShippingRequest $shippingRequest): array {
        return Stream::from($shippingRequest->getPackLines())
            ->map(function(ShippingRequestPack $shippingRequestPack) {
                $pack = $shippingRequestPack->getPack();
                $referenceData = Stream::from($shippingRequestPack->getLines())
                    ->map(function (ShippingRequestLine $shippingRequestLine) {
                        $expectedLine = $shippingRequestLine->getExpectedLine();
                        $reference = $expectedLine->getReferenceArticle();

                        $actions = $this->templating->render('utils/action-buttons/dropdown.html.twig', [
                            'actions' => [
                                [
                                    'hasRight' => $this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI) && $shippingRequestLine->getArticleOrReference() instanceof Article,
                                    'title' => 'Voir l\'aticle',
                                    'icon' => 'fa fa-eye',
                                    'attributes' => [
                                        'onclick' => "window.location.href = '{$this->router->generate('article_show_page', ['id' => $shippingRequestLine->getArticleOrReference() instanceof Article ? $shippingRequestLine->getArticleOrReference()->getId() : null])}'",
                                    ]
                                ],
                                [
                                    'hasRight' => $this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE),
                                    'title' => 'Voir la référence',
                                    'icon' => 'fa fa-eye',
                                    'attributes' => [
                                        'onclick' => "window.location.href = '{$this->router->generate('reference_article_show_page', ['id' => $reference->getId()])}'",
                                    ]
                                ],
                            ],
                        ]);

                        return [
                            'actions' => $actions,
                            'reference' => '<div class="d-flex align-items-center">' . $reference->getReference() . ($reference->isDangerousGoods() ? "<i title='Matière dangereuse' class='dangerous wii-icon wii-icon-dangerous-goods wii-icon-25px ml-2'></i>" : '') . '</div>',
                            'label' => $reference->getLibelle(),
                            'quantity' => $shippingRequestLine->getQuantity(),
                            'price' => $expectedLine->getUnitPrice(),
                            'weight' => $expectedLine->getUnitWeight(),
                            'totalPrice' => $shippingRequestLine->getQuantity() * $expectedLine->getUnitPrice(),
                        ];
                    })
                    ->toArray();

                return [
                    'pack' => [
                        'nature' => $this->formatService->nature($pack->getNature()),
                        'code' => $pack->getCode() ?? null,
                        'size' => $shippingRequestPack->getSize() ?? '/',
                        'location' => $this->formatService->location($pack->getLastDrop()?->getEmplacement()),
                        'color' => $pack?->getNature()?->getColor() ?? null,
                    ],
                    'references' => $referenceData,
                ];
            })
            ->toArray();
    }

    public function persistNewDeliverySlipAttachment(EntityManagerInterface     $entityManager,
                                                     ShippingRequest            $shippingRequest): Attachment {
        $formatService = $this->formatService;
        $projectDir = $this->kernel->getProjectDir();
        $settingRepository = $entityManager->getRepository(Setting::class);

        $deliverySlipTemplatePath = $settingRepository->getOneParamByLabel(Setting::CUSTOM_DELIVERY_SLIP_TEMPLATE)
            ?: $settingRepository->getOneParamByLabel(Setting::DEFAULT_DELIVERY_SLIP_TEMPLATE);

        $variables = [
            //en-tête
            "QRCodeexpe" => $shippingRequest->getNumber() ?? "",
            "numexpedition" => $shippingRequest->getNumber() ?? "",
            "datecreation" => $formatService->date($shippingRequest->getCreatedAt()) ?? "",
            "demandeurs" => $formatService->users($shippingRequest->getRequesters()) ?? "",
            "teldemandeur" => implode(', ', $shippingRequest->getRequesterPhoneNumbers()) ?? "",
            "numcommandeclient" => $shippingRequest->getCustomerOrderNumber() ?? "",
            "livraisongracieux" => $formatService->bool($shippingRequest->isFreeDelivery()) ?? "",
            "articlesconformes" => $formatService->bool($shippingRequest->isCompliantArticles()) ?? "",
            "destinataire" => $shippingRequest->getCustomerRecipient() ?? "",
            "teldestinataire" => $shippingRequest->getCustomerPhone() ?? "",
            "client" => $shippingRequest->getCustomerName() ?? "",
            "adressedestinataire" => $shippingRequest->getCustomerAddress() ?? "",
            "dateexpedition" => $formatService->date($shippingRequest->getTreatedAt()) ?? "",

            //transport
            "datepriseenchargesouhaitee" => $formatService->date($shippingRequest->getRequestCaredAt()) ?? "",
            "port" => ShippingRequest::CARRYING_LABELS[$shippingRequest->getCarrying()] ?? "",
            "numtracking" => $shippingRequest->getTrackingNumber() ?? "",
            "dateenlevement" => $formatService->date($shippingRequest->getExpectedPickedAt()) ?? "",
            "nomtransporteur" => $formatService->carrier($shippingRequest->getCarrier()) ?? "",
            "specificationtransport" => "",

            //footer
            "poidsnettotal" => $shippingRequest->getNetWeight() ?? "",
            "dimensioncolis" => "",
            "valeurtotal" => $shippingRequest->getTotalValue() ?? "",
            "nbcolis" => $shippingRequest->getPackLines()->count(),
            "poidsbruttotal" => $shippingRequest->getGrossWeight() ?? "",
        ];

        $variables["reference"] = Stream::from($shippingRequest->getExpectedLines())
            ->map(function (ShippingRequestExpectedLine $line) {
                $referenceArticle = $line->getReferenceArticle();
                $price = $line->getUnitPrice();
                $quantity = $line->getQuantity();

                $dangerousGoods = $this->formatService->bool($referenceArticle->isDangerousGoods());
                $FDS = $referenceArticle->getSheet() ? $referenceArticle->getSheet()->getOriginalName() : "";
                $onuCode = $referenceArticle->getOnuCode();
                $productClass = $referenceArticle->getProductClass();
                $ndpCode = $referenceArticle->getNdpCode();

                return [
                    "reference" => $referenceArticle->getReference() ?? "",
                    "quantite" => $quantity ?? "",
                    "prixunitaire" => $price ?? "",
                    "poidsnet" => $line->getUnitWeight() ?? "",
                    "montantotal" => $price * $quantity,
                    "matieredangereuse" => $dangerousGoods ?? "",
                    "FDS" => $FDS ?? "",
                    "CodeONU" => $onuCode ?? "",
                    "classeproduit" => $productClass ?? "",
                    "CodeNDP" => $ndpCode ?? "",
                ];
            })
            ->toArray();

        $tmpDocxPath = $this->wordTemplateDocument->generateDocx(
            "$projectDir/public/$deliverySlipTemplatePath",
            $variables,
            ["barcodes" => ["QRCodeexpe"],]
        );

        $nakedFileName = uniqid();

        $deliverySlipOutdir = "$projectDir/public/uploads/attachements";
        $docxPath = "$deliverySlipOutdir/$nakedFileName.docx";

        rename($tmpDocxPath, $docxPath);
        $this->PDFGeneratorService->generateFromDocx($docxPath, $deliverySlipOutdir);
        unlink($docxPath);

        $now = new DateTime();

        $client = SpecificService::CLIENTS[$this->specificService->getAppClient()];

        $name = "BDL - {$shippingRequest->getNumber()} - $client - {$now->format('dmYHis')}";

        $deliverySlipAttachment = new Attachment();
        $deliverySlipAttachment
            ->setFileName($nakedFileName . '.pdf')
            ->setOriginalName($name . '.pdf');

        $shippingRequest->addAttachment($deliverySlipAttachment);

        $entityManager->persist($deliverySlipAttachment);

        return $deliverySlipAttachment;
    }

}
