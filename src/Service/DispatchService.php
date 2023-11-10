<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\DispatchReferenceArticle;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fields\SubLineFixedField;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Language;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\LanguageHelper;
use App\Service\Document\TemplateDocumentService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class DispatchService {

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public UserService $userService;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public FixedFieldService $fieldsParamService;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public ArrivageService $arrivalService;

    #[Required]
    public Security $security;

    #[Required]
    public CSVExportService $CSVExportService;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public TemplateDocumentService $wordTemplateDocument;

    #[Required]
    public PDFGeneratorService $PDFGeneratorService;

    #[Required]
    public SpecificService $specificService;

    #[Required]
    public StatusHistoryService $statusHistoryService;

    #[Required]
    public AttachmentService $attachmentService;

    #[Required]
    public RefArticleDataService $refArticleDataService;

    #[Required]
    public PackService $packService;

    #[Required]
    public FormService $formService;

    private ?array $freeFieldsConfig = null;

    // cache for default nature
    private ?Nature $defaultNature = null;

    public function getDataForDatatable(InputBag $params, bool $groupedSignatureMode = false, bool $fromDashboard = false, array $preFilledFilters = []) {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $dispatchRepository = $this->entityManager->getRepository(Dispatch::class);

        if (!$fromDashboard) {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DISPATCH, $this->userService->getUser());
        } else {
            $filters = $preFilledFilters;
        }

        $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug());
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
        $language = $this->security->getUser()->getLanguage() ?: $defaultLanguage;
        $queryResult = $dispatchRepository->findByParamAndFilters($params, $filters, $this->userService->getUser(), $this->visibleColumnService,  [
            'defaultLanguage' => $defaultLanguage,
            'language' => $language
        ]);

        $dispatchesArray = $queryResult['data'];

        $rows = [];
        foreach ($dispatchesArray as $dispatch) {
            $rows[] = $this->dataRowDispatch($dispatch, [
                'groupedSignatureMode' => $groupedSignatureMode
            ]);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowDispatch(Dispatch $dispatch, array $options = []) {

        $url = $this->router->generate('dispatch_show', ['id' => $dispatch->getId()]);
        $receivers = $dispatch->getReceivers() ?? null;

        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategorieCL::DEMANDE_DISPATCH, CategoryType::DEMANDE_DISPATCH);
        }

        if ($receivers) {
            $receiversUsernames = Stream::from($receivers->toArray())
                ->map(function (Utilisateur $receiver) {
                   return $receiver->getUsername();
                })
                ->join(', ');
        }

        $typeColor = $dispatch->getType()->getColor();
        $row = [
            'id' => $dispatch->getId() ?? 'Non défini',
            'number' => $dispatch->getNumber() ?? '',
            'carrier' => $dispatch->getCarrier() ? $dispatch->getCarrier()->getLabel() : '',
            'carrierTrackingNumber' => $dispatch->getCarrierTrackingNumber(),
            'commandNumber' => $dispatch->getCommandNumber(),
            'creationDate' => $this->formatService->datetime($dispatch->getCreationDate()),
            'validationDate' => $this->formatService->datetime($dispatch->getValidationDate()),
            'endDate' => $this->formatService->date($dispatch->getEndDate()),
            'requester' => $this->formatService->user($dispatch->getRequester()),
            'receivers' => $receiversUsernames ?? '',
            'locationFrom' => $this->formatService->location($dispatch->getLocationFrom()),
            'locationTo' => $this->formatService->location($dispatch->getLocationTo()),
            'destination' => $dispatch->getDestination() ?? '',
            'nbPacks' => $dispatch->getDispatchPacks()->count(),
            'type' => "
                <div class='d-flex align-items-center'>
                    <span class='dt-type-color mr-2' style='background-color: $typeColor;'></span>
                    {$this->formatService->type($dispatch->getType())}
                </div>
            ",
            'status' => $this->formatService->status($dispatch->getStatut()),
            'emergency' => $dispatch->getEmergency() ?? 'Non',
            'treatedBy' => $this->formatService->user($dispatch->getTreatedBy()),
            'treatmentDate' => $this->formatService->datetime($dispatch->getTreatmentDate()),
            'customerName' => $dispatch->getCustomerName(),
            'customerPhone' => $dispatch->getCustomerPhone(),
            'customerRecipient' => $dispatch->getCustomerRecipient(),
            'customerAddress' => $dispatch->getCustomerAddress(),
        ];

        if(isset($options['groupedSignatureMode']) && $options['groupedSignatureMode']) {
            $dispatchId = $dispatch->getId();
            $row['actions'] = "<td><input type='checkbox' class='checkbox dispatch-checkbox' value='$dispatchId'></td>";
        } else {
            $row['actions'] = $this->templating->render('dispatch/list/actions.html.twig', [
                'dispatch' => $dispatch,
                'url' => $url
            ]);
        }

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $dispatch->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

    public function getNewDispatchConfig(EntityManagerInterface $entityManager,
                                         array $types,
                                         ?Arrivage $arrival = null,
                                         bool $fromArrival = false,
                                         array $packs = []): array {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $fieldsParam = $fixedFieldByTypeRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_DISPATCH, [FixedFieldByType::ATTRIBUTE_REQUIRED_CREATE, FixedFieldByType::ATTRIBUTE_DISPLAYED_CREATE]);

        $dispatchBusinessUnits = $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT);

        $draftStatuses = $statusRepository->findByCategoryAndStates(CategorieStatut::DISPATCH, [Statut::DRAFT]);
        $existingDispatches = $dispatchRepository->findBy([
            'requester' => $this->userService->getUser(),
            'statut' => $draftStatuses
        ]);

        return [
            'dispatchBusinessUnits' => !empty($dispatchBusinessUnits) ? $dispatchBusinessUnits : [],
            'fieldsParam' => $fieldsParam,
            'emergencies' => $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_EMERGENCY),
            'preFill' => $settingRepository->getOneParamByLabel(Setting::PREFILL_DUE_DATE_TODAY),
            'typeChampsLibres' => array_map(function(Type $type) use ($freeFieldRepository) {
                $champsLibres = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_DISPATCH);
                return [
                    'typeLabel' => $this->formatService->type($type),
                    'typeId' => $type->getId(),
                    'champsLibres' => $champsLibres,
                    'pickLocation' => [
                        "id" => $type->getPickLocation() ? $type->getPickLocation()->getId() : null,
                        "label" => $type->getPickLocation() ? $type->getPickLocation()->getLabel() : null,
                    ],
                    'dropLocation' => [
                        "id" => $type->getDropLocation() ? $type->getDropLocation()->getId() : null,
                        "label" => $type->getDropLocation() ? $type->getDropLocation()->getLabel() : null,
                    ],
                    'suggestedDropLocations' => implode(',', $type->getSuggestedDropLocations() ?? []),
                    'suggestedPickLocations' => implode(',', $type->getSuggestedPickLocations() ?? []),
                    'isDefault' => $type->isDefault(),
                ];
            }, $types),
            'notTreatedStatus' => $statusRepository->findStatusByType(CategorieStatut::DISPATCH, null, [Statut::DRAFT]),
            'packs' => $packs,
            'fromArrival' => $fromArrival,
            'arrival' => $arrival,
            'existingDispatches' => Stream::from($existingDispatches)
                ->map(fn(Dispatch $dispatch) => [
                    'id' => $dispatch->getId(),
                    'number' => $dispatch->getNumber(),
                    'locationTo' => $this->formatService->location($dispatch->getLocationTo()),
                    'type' => $this->formatService->type($dispatch->getType())
                ])
                ->toArray(),
            'dispatch' => new Dispatch(),
        ];
    }

    public function createHeaderDetailsConfig(Dispatch $dispatch): array {
        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        $type = $dispatch->getType();
        $carrier = $dispatch->getCarrier();
        $carrierTrackingNumber = $dispatch->getCarrierTrackingNumber();
        $commandNumber = $dispatch->getCommandNumber();
        $receivers = $dispatch->getReceivers();
        $locationFrom = $dispatch->getLocationFrom();
        $locationTo = $dispatch->getLocationTo();
        $creationDate = $dispatch->getCreationDate();
        $validationDate = $dispatch->getValidationDate() ?: null;
        $treatmentDate = $dispatch->getTreatmentDate() ?: null;
        $startDate = $dispatch->getStartDate();
        $endDate = $dispatch->getEndDate();
        $startDateStr = $this->formatService->date($startDate, "", $user);
        $endDateStr = $this->formatService->date($endDate, "", $user);
        $projectNumber = $dispatch->getProjectNumber();
        $updatedAt = $dispatch->getUpdatedAt() ?: null;

        $receiverDetails = [
            "label" => $this->translationService->translate('Demande', 'Général', 'Destinataire(s)', false),
            "value" => "",
            "isRaw" => true
        ];

        foreach ($receivers as $receiver) {
            $receiverLine = "<div>";

            $receiverLine .= $receiver ? $receiver->getUsername() : "";
            if ($receiver && $receiver->getAddress()) {
                $receiverLine .= '
                    <span class="pl-2"
                          data-toggle="popover"
                          data-trigger="click hover"
                          title="Adresse du destinataire"
                          data-content="' . htmlspecialchars($receiver->getAddress()) . '">
                        <i class="fas fa-search"></i>
                    </span>';
            }
            $receiverLine .= '</div>';
            $receiverDetails['value'] .= $receiverLine;
        }

        $config = [
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Transporteur', false),
                'value' => $this->formatService->carrier($carrier, '-'),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_CARRIER_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'N° tracking transporteur', false),
                'value' => $carrierTrackingNumber ?: '-',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH]
            ],
            $receiverDetails ?? [],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'N° projet', false),
                'value' => $projectNumber ?: '-',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Business unit', false),
                'value' => $dispatch->getBusinessUnit() ?? '-',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'N° commande', false),
                'value' => $commandNumber ?: '-',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_COMMAND_NUMBER_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de prise', false),
                'value' => $this->formatService->location($locationFrom, '-'),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_LOCATION_PICK]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de dépose', false),
                'value' => $this->formatService->location($locationTo, '-'),
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_LOCATION_DROP]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', "Dates d'échéance", false),
                'value' => ($startDate || $endDate)
                    ? $this->translationService->translate('Général', null, 'Zone liste', "Du {1} au {2}", [
                        1 => $startDateStr,
                        2 => $endDateStr
                    ], false)
                    : '',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_DEADLINE_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Général', null, 'Zone liste', 'Traité par', false),
                'value' => $this->formatService->user($dispatch->getTreatedBy(), '-')
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Destination', false),
                'value' => $dispatch->getDestination() ?: '-',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_DESTINATION]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Client', false),
                'value' => $dispatch->getCustomerName() ?: '-',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_CUSTOMER_NAME_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Téléphone client', false),
                'value' => $dispatch->getCustomerPhone() ?: '-',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_CUSTOMER_PHONE_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'À l\'attention de', false),
                'value' => $dispatch->getCustomerRecipient() ?: '-',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_CUSTOMER_RECIPIENT_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Adresse de livraison', false),
                'value' => $dispatch->getCustomerAddress() ?: '-',
                'show' => ['fieldName' => FixedFieldStandard::FIELD_CODE_CUSTOMER_ADDRESS_DISPATCH]
            ],
        ];

        if ($dispatch->isWithoutHistory()) {
            array_push($config,
                [
                    'label' => $this->translationService->translate('Général', null, 'Zone liste', 'Date de création', false),
                    'value' => $this->formatService->datetime($creationDate, "-")
                ],
                [
                    'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date de validation', false),
                    'value' => $this->formatService->datetime($validationDate, "-")
                ],
                [
                    'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date de traitement', false),
                    'value' => $this->formatService->datetime($treatmentDate, "-")
                ],
            );
        }

        if($updatedAt) {
            $config[] = [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Dernière mise à jour', false),
                'value' => $this->formatService->datetime($updatedAt, "-"),
            ];
        }

        return $this->fieldsParamService->filterHeaderConfig($config, FixedFieldStandard::ENTITY_CODE_DISPATCH, $dispatch->getType());
    }

    public function createDateFromStr(?string $dateStr): ?DateTime {
        $user = $this->security->getUser();
        $date = null;
        foreach ([$user->getDateFormat(), 'Y-m-d', 'd/m/Y'] as $format) {
            $date = (!empty($dateStr) && empty($date) && !empty($format))
                ? DateTime::createFromFormat($format, $dateStr)
                : $date;
        }
        return $date ?: null;
    }

    public function sendEmailsAccordingToStatus(EntityManagerInterface $entityManager,
                                                Dispatch $dispatch,
                                                bool $isUpdate,
                                                bool $fromGroupedSignature = false,
                                                ?Utilisateur $signatory = null,
                                                bool $fromCreate = false)
    {
        $status = $dispatch->getStatut();
        $recipientAbleToReceivedMail = $status && $status->getSendNotifToRecipient();
        $requesterAbleToReceivedMail = $status && $status->getSendNotifToDeclarant();
        $sendReport = $status && $status->getSendReport();
        $validationDate = new DateTime();

        if ($recipientAbleToReceivedMail || $requesterAbleToReceivedMail || $sendReport) {
            if ($recipientAbleToReceivedMail && !$dispatch->getReceivers()->isEmpty()) {
                $receiverEmailUses = $dispatch->getReceivers()->toArray();
            }
            else {
                $receiverEmailUses = [];
            }

            if ($requesterAbleToReceivedMail && $dispatch->getRequester()) {
                $receiverEmailUses[] = $dispatch->getRequester();
            }

            if($sendReport){
                $receiverEmailUses[] = $dispatch->getLocationFrom()->getEmail();
                $receiverEmailUses[] = $dispatch->getLocationTo()->getEmail();
                $receiverEmailUses[] = $signatory;
                $receiverEmailUses = Stream::from($receiverEmailUses)->filter()->unique()->toArray();
            }

            $partialDispatch = !(
                $dispatch
                    ->getDispatchPacks()
                    ->filter(fn(DispatchPack $dispatchPack) => !$dispatchPack->isTreated())
                    ->isEmpty()
            ) || $status->isPartial();

            $translatedTitle = $partialDispatch
                ? 'Acheminement {1} traité partiellement le {2}'
                : 'Acheminement {1} traité le {2}';

            $customGroupedSignatureTitle =
                $status->getGroupedSignatureType() === Dispatch::DROP
                    ? 'Bon de livraison'
                    : "Bon d'enlèvement";

            $groupedSignatureNumber =
                $dispatch->getNumber()
                . '_'
                . (
                $status->getGroupedSignatureType() === Dispatch::DROP
                    ? 'LIV'
                    : "ENL"
                )
                . '_'
                . $this->formatService->location($dispatch->getLocationFrom())
                . '_'
                . $this->formatService->location($dispatch->getLocationTo())
                . '_'
                . (new DateTime())->format('Ymd')
            ;
            // Attention!! return traduction parameters to give to translationService::translate
            $title = fn(string $slug) => (
                $fromGroupedSignature
                ? ['Demande', 'Acheminements', 'Emails', "$customGroupedSignatureTitle $groupedSignatureNumber généré pour l'acheminement {1} au statut {2} le {3}", [
                    1 => $dispatch->getNumber(),
                    2 => $this->formatService->status($status),
                    3 => $this->formatService->datetime($validationDate)
                ], false]
                : ($status->isTreated()
                    ? ['Demande', 'Acheminements', 'Emails', $translatedTitle, [
                        1 => $dispatch->getNumber(),
                        2 => $this->formatService->datetime($dispatch->getTreatmentDate())
                    ], false]
                    : (!$isUpdate
                        ? ["Demande", "Acheminements", "Emails", "Une demande d'acheminement de type {1} vous concerne :", [
                            1 => $this->formatService->type($dispatch->getType())
                        ], false]
                        : ["Demande", "Acheminements", "Emails", "Changement de statut d'une demande d'acheminement de type {1} vous concernant :", [
                            1 => $this->formatService->type($dispatch->getType())
                        ], false]))
            );

            $subject = ($status->isTreated() || $status->isPartial() || $sendReport)
                ? ($dispatch->getEmergency()
                    ? ["Demande", "Acheminements", "Emails", "FOLLOW GT // Urgence : Notification de traitement d'une demande d'acheminement", false]
                    : ["Demande", "Acheminements", "Emails", "FOLLOW GT // Notification de traitement d'une demande d'acheminement", false])
                : (!$isUpdate
                    ? ["Demande", "Acheminements", "Emails", "FOLLOW GT // Création d'une demande d'acheminement", false]
                    : ["Demande", "Acheminements", "Emails", "FOLLOW GT // Changement de statut d'une demande d'acheminement", false]);

            $isTreatedStatus = $dispatch->getStatut() && $dispatch->getStatut()->isTreated();
            $isTreatedByOperator = $dispatch->getTreatedBy() && $dispatch->getTreatedBy()->getUsername();

            $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
                $entityManager,
                $dispatch,
                ['type' => $dispatch->getType()]
            );

            if($isUpdate && $status->getSendReport()){
                $updateStatusAttachment = $this->persistNewReportAttachmentForEmail($entityManager, $dispatch, $signatory);
            } else {
                $updateStatusAttachment = null;
            }
            if (!empty($receiverEmailUses)){
                $this->mailerService->sendMail(
                    $subject,
                    [
                        "name" => 'mails/contents/mailDispatch.html.twig',
                        "context" => [
                            'dispatch' => $dispatch,
                            'title' => $title,
                            'urlSuffix' => $this->router->generate("dispatch_show", ["id" => $dispatch->getId()]),
                            'hideNumber' => $isTreatedStatus,
                            'hideTreatmentDate' => $isTreatedStatus,
                            'hideTreatedBy' => $isTreatedByOperator,
                            'totalCost' => $freeFieldArray,
                            'reportTable' => $fromGroupedSignature,
                        ]
                    ],
                    $receiverEmailUses,
                    $updateStatusAttachment ? [$updateStatusAttachment] : []
                );
            }
        }
    }

    public function treatDispatchRequest(EntityManagerInterface $entityManager,
                                         Dispatch $dispatch,
                                         Statut $treatedStatus,
                                         Utilisateur $loggedUser,
                                         bool $fromNomade = false,
                                         array $treatedPacks = []): void {
        $dispatchPacks = $dispatch->getDispatchPacks();
        $takingLocation = $dispatch->getLocationFrom();
        $dropLocation = $dispatch->getLocationTo();
        $date = new DateTime('now');

        $dispatch
            ->setTreatmentDate($date)
            ->setTreatedBy($loggedUser);

        $this->statusHistoryService->updateStatus($entityManager, $dispatch, $treatedStatus);

        $parsedPacks = [];
        foreach ($dispatchPacks as $dispatchPack) {
            if (!$dispatchPack->isTreated()
                && (
                    empty($treatedPacks)
                    || in_array($dispatchPack->getId(), $treatedPacks)
                )) {
                $pack = $dispatchPack->getPack();

                $trackingTaking = $this->trackingMovementService->createTrackingMovement(
                    $pack,
                    $takingLocation,
                    $loggedUser,
                    $date,
                    $fromNomade,
                    true,
                    TrackingMovement::TYPE_PRISE,
                    [
                        'quantity' => $dispatchPack->getQuantity(),
                        'from' => $dispatch,
                        'removeFromGroup' => true,
                        'attachments' => $dispatch->getAttachments(),
                    ]
                );

                $trackingDrop = $this->trackingMovementService->createTrackingMovement(
                    $pack,
                    $dropLocation,
                    $loggedUser,
                    $date,
                    $fromNomade,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        'quantity' => $dispatchPack->getQuantity(),
                        'from' => $dispatch,
                        'attachments' => $dispatch->getAttachments(),
                    ]
                );

                $entityManager->persist($trackingTaking);
                $entityManager->persist($trackingDrop);

                $dispatchPack->setTreated(true);
                $parsedPacks[] = $pack;
            }
        }
        $entityManager->flush();

        $this->sendEmailsAccordingToStatus($entityManager, $dispatch, true);

        foreach ($parsedPacks as $pack) {
            $this->arrivalService->sendMailForDeliveredPack($dispatch->getLocationTo(), $pack, $loggedUser, TrackingMovement::TYPE_DEPOSE, $date);
        }
    }

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser, bool $groupedSignatureMode = false): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $columnsVisible = $currentUser->getVisibleColumns()['dispatch'];
        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::DEMANDE_DISPATCH, CategorieCL::DEMANDE_DISPATCH);

        $columns = [
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'N° demande', false), 'name' => 'number'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Transporteur', false), 'name' => 'carrier'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'N° tracking transporteur', false), 'name' => 'carrierTrackingNumber'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'N° commande', false), 'name' => 'commandNumber'],
            ['title' => $this->translationService->translate('Général', null, 'Zone liste', 'Date de création', false), 'name' => 'creationDate'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date de validation', false), 'name' => 'validationDate'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date de traitement', false), 'name' => 'treatmentDate'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date d\'échéance', false), 'name' => 'endDate'],
            ['title' => $this->translationService->translate('Demande', 'Général', 'Type', false), 'name' => 'type'],
            ['title' => $this->translationService->translate('Demande', 'Général', 'Demandeur', false), 'name' => 'requester'],
            ['title' => $this->translationService->translate('Demande', 'Général', 'Destinataire(s)', false), 'name' => 'receivers', 'orderable' => false],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de prise', false), 'name' => 'locationFrom'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes','Emplacement de dépose', false), 'name' => 'locationTo'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes','Destination', false), 'name' => 'destination'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Zone liste - Noms de colonnes', 'Nombre d\'UL', false), 'name' => 'nbPacks', 'orderable' => false],
            ['title' => $this->translationService->translate('Demande', 'Général', 'Statut', false), 'name' => 'status'],
            ['title' => $this->translationService->translate('Demande', 'Général', 'Urgence', false), 'name' => 'emergency'],
            ['title' => $this->translationService->translate('Général', null, 'Zone liste', 'Traité par', false), 'name' => 'treatedBy'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Client', false), 'name' => 'customerName'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Téléphone client', false), 'name' => 'customerPhone'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', "À l'attention de", false), 'name' => 'customerRecipient'],
            ['title' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Adresse de livraison', false), 'name' => 'customerAddress'],
        ];

        if($groupedSignatureMode) {
            $dispatchCheckboxLine = [
                'title' => "<input type='checkbox' class='checkbox check-all'>",
                'name' => 'actions',
                'alwaysVisible' => true,
                'orderable' => false,
                'class' => 'noVis'
            ];
            array_unshift($columns, $dispatchCheckboxLine);
        } else {
            array_unshift($columns, ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis']);
        }

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function parseRequestForCard(Dispatch $dispatch,
                                        DateService $dateService,
                                        array $averageRequestTimesByType): array {

        $requestStatus = $dispatch->getStatut()?->getCode();
        $requestType = $dispatch->getType() ? $dispatch->getType()->getLabel() : '';
        $typeId = $dispatch->getType() ? $dispatch->getType()->getId() : null;
        $requestState = $dispatch->getStatut() ? $dispatch->getStatut()->getState() : null;

        $averageTime = $averageRequestTimesByType[$typeId] ?? null;

        $deliveryDateEstimated = 'Non estimée';
        $estimatedFinishTimeLabel = 'Date d\'acheminement non estimée';
        $today = new DateTime();

        if (isset($averageTime)) {
            $expectedDate = (clone $dispatch->getCreationDate())
                ->add($dateService->secondsToDateInterval($averageTime->getAverage()));
            if ($expectedDate >= $today) {
                $estimatedFinishTimeLabel = 'Date et heure d\'acheminement prévue';
                $deliveryDateEstimated = $expectedDate->format('d/m/Y H:i');
                if ($expectedDate->format('d/m/Y') === $today->format('d/m/Y')) {
                    $estimatedFinishTimeLabel = 'Heure d\'acheminement estimée';
                    $deliveryDateEstimated = $expectedDate->format('H:i');
                }
            }
        }
        $href = $this->router->generate('dispatch_show', ['id' => $dispatch->getId()]);

        $bodyTitle = $dispatch->getDispatchPacks()->count() . ' unités logistiques' . ' - ' . $requestType;
        $requestDate = $dispatch->getCreationDate();
        $requestDateStr = $requestDate
            ? (
                $requestDate->format('d ')
                . DateService::ENG_TO_FR_MONTHS[$requestDate->format('M')]
                . $requestDate->format(' (H\hi)')
            )
            : 'Non défini';

        $statusesToProgress = [
            Statut::TREATED => 100,
            Statut::DRAFT => 0,
            Statut::NOT_TREATED => 50,
            Statut::PARTIAL => 75,
            Statut::DISPUTE => 50,
        ];
        return [
            'href' => $href ?? null,
            'errorMessage' => 'Vous n\'avez pas les droits d\'accéder à la page de la demande d\'acheminement',
            'estimatedFinishTime' => $deliveryDateEstimated,
            'estimatedFinishTimeLabel' => $estimatedFinishTimeLabel,
            'requestStatus' => $requestStatus,
            'requestBodyTitle' => $bodyTitle,
            'requestLocation' => $dispatch->getLocationTo() ? $dispatch->getLocationTo()->getLabel() : 'Non défini',
            'requestNumber' => $dispatch->getNumber(),
            'requestDate' => $requestDateStr,
            'requestUser' => $dispatch->getRequester() ? $dispatch->getRequester()->getUsername() : 'Non défini',
            'cardColor' => $requestState === Statut::DRAFT ? 'light-grey' : 'white',
            'bodyColor' => $requestState === Statut::DRAFT ? 'white' : 'light-grey',
            'topRightIcon' => 'livreur.svg',
            'emergencyText' => '',
            'progress' => $statusesToProgress[$requestState] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'progressBarBGColor' => $requestState === Statut::DRAFT ? 'white' : 'light-grey',
        ];
    }

    public function packRow(Dispatch $dispatch,
                            ?DispatchPack $dispatchPack,
                            bool $autofocus,
                            bool $isEdit): array {
        if(!isset($this->prefixPackCodeWithDispatchNumber, $this->natures, $this->defaultNature)) {
            $this->prefixPackCodeWithDispatchNumber = $this->entityManager->getRepository(Setting::class)->getOneParamByLabel(Setting::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER);
            $natureRepository = $this->entityManager->getRepository(Nature::class);
            $this->natures = $natureRepository->findByAllowedForms([Nature::DISPATCH_CODE]);
            $this->defaultNature = $natureRepository->findOneBy(["defaultNature" => true]);
         }

        if($dispatchPack) {
            $user = $this->userService->getUser();

            $pack = $dispatchPack->getPack();
            $lastTracking = $pack->getLastTracking();
            $code = $pack->getCode();
            $quantity = $dispatchPack->getQuantity();
            $nature = $pack->getNature();
            $weight = $pack->getWeight();
            $volume = $this->formatService->decimal($pack->getVolume(), [
                'decimals' => 6,
                'decimalSeparator' => '.',
            ]);
            $comment = $pack->getComment();
            $lastMvtDate = $lastTracking && $lastTracking->getDatetime() ? $lastTracking->getDatetime()->format("{$user->getDateFormat()} H:i") : null;
            $lastLocation = $lastTracking ? $this->formatService->location($lastTracking->getEmplacement()) : null;
            $operator = $lastTracking ? $this->formatService->user($lastTracking->getOperateur()) : null;
            $status = $dispatchPack->isTreated()
                ? $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Traité')
                : $this->translationService->translate('Demande', 'Acheminements', 'Général', 'À traiter');
            $height = $dispatchPack->getHeight();
            $width = $dispatchPack->getWidth();
            $length = $dispatchPack->getLength();
        } else {
            $quantity = $this->defaultNature?->getDefaultQuantityForDispatch() ?: null;
            $nature = $this->defaultNature;
            $weight = null;
            $volume = null;
            $comment = null;
            $lastMvtDate = null;
            $lastLocation = null;
            $operator = null;
            $status = null;
            $height = null;
            $width = null;
            $length = null;
        }

        $actions = $this->templating->render("dispatch/datatablePackRow.html.twig", [
            "dispatchPack" => $dispatchPack ?? null,
            "dispatch" => $dispatch
        ]);

        if($isEdit) {
            $creationMode = !isset($dispatchPack) ? "d-none" : "";
            $class = "form-control data $creationMode";
            $autofocus = $autofocus ? "autofocus" : "";
            $packPrefix = $this->prefixPackCodeWithDispatchNumber ? $dispatch->getNumber() : null;
            $searchPrefix = $this->prefixPackCodeWithDispatchNumber
                ? "data-search-prefix='$packPrefix-' data-search-prefix-displayed='$packPrefix' "
                : "";

            $natureOptions = Stream::from($this->natures)
                ->map(fn(Nature $current) => [
                    "value" => $current->getId(),
                    "label" => $this->formatService->nature($current),
                    "selected" => $current->getId() === $nature?->getId() ? "selected" : "",
                ]);

            $subLineFieldsParamRepository = $this->entityManager->getRepository(SubLineFixedField::class);

            $fieldEntities = $subLineFieldsParamRepository->findBy([
                'entityCode' => SubLineFixedField::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT,
                'fieldCode' => [
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT,
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH,
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH,
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT,
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME,
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT,
                ],
            ]);

            $fields = Stream::from($fieldEntities)
                ->keymap(static function (SubLineFixedField $subLineFieldsParam) use ($length, $width, $height, $weight, $volume, $comment) {
                    $fieldCode = $subLineFieldsParam->getFieldCode();
                    return [
                        $fieldCode,
                        [
                            'elements' => Stream::from($subLineFieldsParam->getElements() ?? [], [$$fieldCode])
                                ->filter()
                                ->map(static fn(string $element) => [
                                    'label' => $element,
                                    'value' => $element,
                                    'selected' => $element === ($$fieldCode ?? null),
                                ])->toArray(),
                            'required' => $subLineFieldsParam->isRequired() ?: false,
                        ],
                    ];
                })->toArray();

            $data = [
                "actions" => $actions,
                "code" => isset($code)
                    ? ("<span title='$code'>$code</span>". $this->formService->macro('hidden', 'pack', $code))
                    : "<select name='pack'
                               data-s2='keyboardPacks'
                               data-parent='body'
                               data-include-params-parent='.wii-box'
                               data-include-params='[name=pack]'
                               data-include-params-group
                               class='w-300px'
                               $searchPrefix
                               $autofocus></select>",
                "quantity" => $this->formService->macro(
                    'input',
                    'quantity',
                    null,
                    true,
                    $quantity,
                    [
                        'type' => 'number',
                        'labelClass' => $creationMode,
                        'inputClass' => "$class no-overflow",
                        'additionalAttributes' => [
                            [
                                'name' => 'data-global-error',
                                'value' => 'Quantité'
                            ],
                            [
                                'name' => 'min',
                                'value' => '1',
                            ],
                            [
                                'name' => 'step',
                                'value' => '1',
                            ],
                        ],
                    ]),
                "nature" => $this->formService->macro(
                    'select',
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_NATURE,
                    null,
                    true,
                    [
                        'items' => $natureOptions,
                        'inputClass' => "$class minw-150px",
                        'labelClass' => $creationMode,
                        'value' => $nature?->getId(),
                    ],
                ),
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT => $this->formService->macro(
                    'input',
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT,
                    null,
                    $fields[SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT]['required'],
                    $weight,
                    [
                        'type' => 'number',
                        'inputClass' => "$class no-overflow",
                        'additionalAttributes' => [
                            [
                                'name' => 'data-no-arrow',
                            ],
                            [
                                'name' => 'step',
                                'value' => '0.001',
                            ],
                        ],
                    ]),
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME => $this->formService->macro(
                    'input',
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME,
                    null,
                    $fields[SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME]['required'],
                    $volume,
                    [
                        'type' => 'number',
                        'inputClass' => "$class no-overflow",
                        'additionalAttributes' => [
                            [
                                'name' => 'data-no-arrow',
                            ],
                            [
                                'name' => 'step',
                                'value' => '0.001',
                            ],
                        ],
                    ]),
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT => $this->formService->macro(
                    'wysiwyg',
                    'comment',
                    null,
                    $fields[SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT]['required'],
                    $comment,
                    [
                        'oneLineWysiwyg' => true,
                        'inputClass' => $creationMode,
                    ]),
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_TRACKING_DATE => $lastMvtDate ?? "<span class='lastMvtDate'></span>",
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION => $lastLocation ?? "<span class='lastLocation'></span>",
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR => $operator ?? "<span class='operator'></span>",
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_STATUS => $status ?? "<span class='status'></span>",
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT => $this->formService->macro(
                    'select',
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT,
                    null,
                    $fields[SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT]['required'],
                    [
                        'items' => $fields[SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT]['elements'],
                        'type' => '',
                        'editable' => true,
                        'value' => $height,
                        'labelClass' => "$creationMode w-100",
                        'emptyOption' => [
                            'selected' => true,
                        ],
                        'additionalAttributes' => [
                            [
                                'name' => 'data-parent',
                                'value' => 'body',
                            ],
                        ],
                    ]),
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH => $this->formService->macro(
                    'select',
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH,
                    null,
                    $fields[SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH]['required'],
                    [
                        'items' => $fields[SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH]['elements'],
                        'type' => '',
                        'editable' => true,
                        'value' => $width,
                        'labelClass' => "$creationMode w-100",
                        'emptyOption' => [
                            'selected' => true,
                        ],
                        'additionalAttributes' => [
                            [
                                'name' => 'data-parent',
                                'value' => 'body',
                            ],
                        ],
                    ]),
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH => $this->formService->macro(
                    'select',
                    SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH,
                    null,
                    $fields[SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH]['required'],
                    [
                        'items' => $fields[SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH]['elements'],
                        'type' => '',
                        'editable' => true,
                        'value' => $length,
                        'labelClass' => "$creationMode w-100",
                        'emptyOption' => [
                            'selected' => true,
                        ],
                        'additionalAttributes' => [
                            [
                                'name' => 'data-parent',
                                'value' => 'body',
                            ],
                        ],
                    ]),
            ];
        } else if($dispatchPack) {
            $data = [
                "actions" => $actions,
                "code" => $code,
                "nature" => $nature?->getLabel(),
                "quantity" => $quantity,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT => $weight,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME => $volume,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT => "<div class='ql-editor'>$comment</div>",
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_TRACKING_DATE => $lastMvtDate,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION => $lastLocation,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR => $operator,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_STATUS => $status,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT => $height,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH => $width,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH => $length,
            ];
        }

        return $data ?? [];
    }


    public function manageDispatchPacks(Dispatch $dispatch, array $packs, EntityManagerInterface $entityManager) {
        $packRepository = $entityManager->getRepository(Pack::class);

        foreach($packs as $pack) {
            $comment = $pack['packComment'] ?? null;
            $packId = $pack['packId'];
            $packQuantity = (int)$pack['packQuantity'];
            $pack = $packRepository->find($packId);
            $pack
                ->setComment($comment);
            $packDispatch = new DispatchPack();
            $packDispatch
                ->setPack($pack)
                ->setTreated(false)
                ->setQuantity($packQuantity)
                ->setDispatch($dispatch);
            $entityManager->persist($packDispatch);
        }
    }


    public function putDispatchLine($handle,
                                    array $dispatch,
                                    array $freeFieldsConfig,
                                    array $freeFieldsById): void {

        $freeFieldValues = $freeFieldsById[$dispatch['id']];
        $row = [
            $dispatch['number'],
            $dispatch['orderNumber'],
            $dispatch['creationDate'],
            $dispatch['validationDate'],
            $dispatch['treatmentDate'],
            $dispatch['type'],
            $dispatch['requester'],
            $dispatch['receivers'],
            $dispatch['carrier'],
            $dispatch['locationFrom'],
            $dispatch['locationTo'],
            $dispatch['destination'],
            $dispatch['treatedBy'],
            $dispatch['packCount'],
            $dispatch['status'],
            $dispatch['emergency'] ?: $this->translationService->translate('Demande', 'Général', 'Non urgent', false),
            $dispatch['businessUnit'],
            $this->formatService->html($dispatch['comment']),
            $dispatch['customerName'],
            $dispatch['customerPhone'],
            $dispatch['customerRecipient'],
            $dispatch['customerAddress'],
            $dispatch['packNature'],
            $dispatch['packCode'],
            $dispatch['dispatchPackHeight'],
            $dispatch['dispatchPackWidth'],
            $dispatch['dispatchPackLength'],
            $dispatch['packVolume'],
            $this->formatService->html($dispatch['packComment']),
            $dispatch['packQuantity'],
            $dispatch['dispatchPackQuantity'],
            $dispatch['packWeight'],
            $dispatch['packLastTrackingDate'],
            $dispatch['packLastTrackingLocation'],
            $dispatch['packLastTrackingOperator'],
            ...(Stream::from($freeFieldsConfig['freeFields'])
                ->map(function(FreeField $freeField, $freeFieldId) use ($freeFieldValues) {
                    $value = $freeFieldValues[$freeFieldId] ?? null;
                    return $value
                        ? $this->formatService->freeField($value, $freeField)
                        : $value;
                })
                ->values()
            ),
        ];

        $this->CSVExportService->putLine($handle, $row);
    }

    public function getOverconsumptionBillData(Dispatch $dispatch): array {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $freeFieldsRepository = $this->entityManager->getRepository(FreeField::class);

        $appLogo = $settingRepository->getOneParamByLabel(Setting::LABEL_LOGO);
        $overConsumptionLogo = $settingRepository->getOneParamByLabel(Setting::FILE_OVERCONSUMPTION_LOGO);

        $additionalField = [];
        if ($this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_COLLINS_VERNON)) {
            $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($dispatch->getType(), CategorieCL::DEMANDE_DISPATCH);
            $freeFieldValues = $dispatch->getFreeFields();

            $flow = current(array_filter($freeFields, function($field) {
                return $field->getLabel() === "Flux";
            }));

            $additionalField[] = [
                "label" => "Flux",
                "value" => $flow ? $this->formatService->freeField($freeFieldValues[$flow->getId()] ?? null, $flow) : null,
            ];

            $requestType = current(array_filter($freeFields, function($field) {
                return $field->getLabel() === "Type de demande";
            }));

            $additionalField[] = [
                "label" => "Type de demande",
                "value" => $requestType ? $this->formatService->freeField($freeFieldValues[$requestType->getId()] ?? null, $requestType) : null,
            ];
        }

        $now = new DateTime();
        $client = $this->specificService->getAppClientLabel();

        $originalName = "BS - {$dispatch->getNumber()} - $client - {$now->format('dmYHis')}.pdf";
        $fileName = uniqid().'.pdf';

        $pdfContent = $this->PDFGeneratorService->generatePDFOverconsumption($dispatch, $appLogo, $overConsumptionLogo, $additionalField);

        $attachment = new Attachment();
        $attachment->setFileName($fileName);
        $attachment->setOriginalName($originalName);
        $attachment->setDispatch($dispatch);

        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

        return [
            'file' => $pdfContent,
            'name' => $originalName
        ];
    }

    public function getDeliveryNoteData(Dispatch $dispatch): array {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        // TODO WIIS-8882
        $logo = $settingRepository->getOneParamByLabel(Setting::FILE_WAYBILL_LOGO);
        $now = new DateTime();
        $client = $this->specificService->getAppClientLabel();

        $name = "BL - {$dispatch->getNumber()} - $client - {$now->format('dmYHis')}";

        $pdfContent = $this->PDFGeneratorService->generatePDFDeliveryNote($name, $logo, $dispatch);

        return [
            'file' => $pdfContent,
            'name' => $name
        ];
    }

    public function getDispatchNoteData(Dispatch $dispatch): array {
        $now = new DateTime();
        $client = $this->specificService->getAppClientLabel();

        $name = "BA - {$dispatch->getNumber()} - $client - {$now->format('dmYHis')}";

        $pdfContent = $this->PDFGeneratorService->generatePDFDispatchNote($dispatch);

        return [
            'file' => $pdfContent,
            'name' => $name
        ];
    }

    public function persistNewWaybillAttachment(EntityManagerInterface $entityManager,
                                                Dispatch               $dispatch,
                                                Utilisateur            $user): Attachment {

        $projectDir = $this->kernel->getProjectDir();
        $settingRepository = $entityManager->getRepository(Setting::class);

        $waybillTypeToUse = $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_TYPE_TO_USE);
        $waybillTemplatePath = match ($waybillTypeToUse) {
            Setting::DISPATCH_WAYBILL_TYPE_TO_USE_STANDARD => (
                $settingRepository->getOneParamByLabel(Setting::CUSTOM_DISPATCH_WAYBILL_TEMPLATE)
                ?: $settingRepository->getOneParamByLabel(Setting::DEFAULT_DISPATCH_WAYBILL_TEMPLATE)
            ),
            Setting::DISPATCH_WAYBILL_TYPE_TO_USE_RUPTURE => (
                $settingRepository->getOneParamByLabel(Setting::CUSTOM_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE)
                ?: $settingRepository->getOneParamByLabel(Setting::DEFAULT_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE)
            )
        };

        $waybillData = $dispatch->getWaybillData();

        $totalWeight = Stream::from($dispatch->getDispatchPacks())
            ->filter(fn(DispatchPack $dispatchPack) => (
                !$waybillTypeToUse
                || $waybillTypeToUse === Setting::DISPATCH_WAYBILL_TYPE_TO_USE_STANDARD
                || $dispatchPack->getPack()?->getArrivage())
            )
            ->map(function(DispatchPack $dispatchPack) {
                if ($dispatchPack->getPack()->getWeight()) {
                    return $dispatchPack->getPack()->getWeight();
                } else {
                    $references = $dispatchPack->getDispatchReferenceArticles();
                    $weight = Stream::from($references)
                        ->reduce(function(int $carry, DispatchReferenceArticle $line) {
                            return $carry + floatval($line->getReferenceArticle()->getDescription()['weight'] ?? 0);
                        });
                    return $weight ?: null;
                }
            })
            ->filter()
            ->sum();
        $totalVolume = Stream::from($dispatch->getDispatchPacks())
            ->filter(fn(DispatchPack $dispatchPack) => (
                !$waybillTypeToUse
                || $waybillTypeToUse === Setting::DISPATCH_WAYBILL_TYPE_TO_USE_STANDARD
                || $dispatchPack->getPack()?->getArrivage())
            )
            ->map(function(DispatchPack $dispatchPack) {
                if ($dispatchPack->getPack()->getVolume()) {
                    return $dispatchPack->getPack()->getVolume();
                } else {
                    $references = $dispatchPack->getDispatchReferenceArticles();
                    $volume = Stream::from($references)
                        ->reduce(function(int $carry, DispatchReferenceArticle $line) {
                            return $carry + floatval($line->getReferenceArticle()->getDescription()['volume'] ?? 0);
                        });
                    return $volume ?: null;
                }
            })
            ->filter()
            ->sum(6);

        $totalQuantities = Stream::from($dispatch->getDispatchPacks())
            ->filter(fn(DispatchPack $dispatchPack) => (!$waybillTypeToUse
                || $waybillTypeToUse === Setting::DISPATCH_WAYBILL_TYPE_TO_USE_STANDARD
                || $dispatchPack->getPack()?->getArrivage())
            )
            ->map(fn(DispatchPack $dispatchPack) => $dispatchPack->getQuantity())
            ->filter()
            ->sum();

        $waybillDate = $this->formatService->parseDatetime($waybillData['dispatchDate'] ?? null, ["Y-m-d"]);
        $variables = [
            "numach" => $dispatch->getNumber(),
            "qrcodenumach" => $dispatch->getNumber(),
            "typeach" => $this->formatService->type($dispatch->getType()),
            "transporteurach" => $this->formatService->carriers([$dispatch->getType()]),
            "numtracktransach" => $dispatch->getCarrierTrackingNumber() ?: '',
            "demandeurach" => $this->formatService->user($dispatch->getRequester()),
            "destinatairesach" => $this->formatService->users($dispatch->getReceivers()),
            "numprojetach" => $dispatch->getProjectNumber() ?: '',
            "numcommandeach" => $dispatch->getCommandNumber() ?: '',
            "date1ach" => $this->formatService->date($dispatch->getStartDate()),
            "date2ach" => $this->formatService->date($dispatch->getEndDate()),
            // dispatch waybill data
            "dateacheminement" => $this->formatService->date($waybillDate, "", $user),
            "transporteur" => $waybillData['carrier'] ?? '',
            "expediteur" => $waybillData['consignor'] ?? '',
            "destinataire" => $waybillData['receiver'] ?? '',
            "nomexpediteur" => $waybillData['consignorUsername'] ?? '',
            "telemailexpediteur" => $waybillData['consignorEmail'] ?? '',
            "nomdestinataire" => $waybillData['receiverUsername'] ?? '',
            "telemaildestinataire" => $waybillData['receiverEmail'] ?? '',
            "lieuchargement" => $waybillData['locationFrom'] ?? '',
            "lieudechargement" => $waybillData['locationTo'] ?? '',
            "note" => $waybillData['notes'] ?? '',
            "totalpoids" => $this->formatService->decimal($totalWeight, [], '-'),
            "totalvolume" => $this->formatService->decimal($totalVolume, ["decimals" => 6], '-'),
            "totalquantite" => $totalQuantities,
        ];

        if ($waybillTypeToUse === Setting::DISPATCH_WAYBILL_TYPE_TO_USE_STANDARD) {
            $variables['UL'] = $dispatch->getDispatchPacks()
                ->filter(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack())
                ->map(function (DispatchPack $dispatchPack) {
                    if ($dispatchPack->getPack()->getVolume()) {
                        $volume = $dispatchPack->getPack()->getVolume();
                    } else {
                        $references = $dispatchPack->getDispatchReferenceArticles();
                        $volume = Stream::from($references)
                            ->reduce(function(int $carry, DispatchReferenceArticle $line) {
                                return $carry + floatval($line->getReferenceArticle()->getDescription()['volume'] ?? 0);
                            });
                        $volume = $volume ?: null;
                    }

                    if ($dispatchPack->getPack()->getWeight()) {
                        $weight = $dispatchPack->getPack()->getWeight();
                    } else {
                        $references = $dispatchPack->getDispatchReferenceArticles();
                        $weight = Stream::from($references)
                            ->reduce(function(int $carry, DispatchReferenceArticle $line) {
                                return $carry + floatval($line->getReferenceArticle()->getDescription()['weight'] ?? 0);
                            });
                        $weight = $weight ?: null;
                    }
                    return [
                        "UL" => $dispatchPack->getPack()->getCode(),
                        "nature" => $this->formatService->nature($dispatchPack->getPack()->getNature()),
                        "quantite" => $dispatchPack->getQuantity(),
                        "poids" => $this->formatService->decimal($weight, [], '-'),
                        "volume" => $this->formatService->decimal($volume, ["decimals" => 6], '-'),
                        "commentaire" => strip_tags($dispatchPack->getPack()->getComment()) ?: '-',
                        "numarrivage" => $dispatchPack->getPack()->getArrivage()?->getNumeroArrivage() ?: '-',
                        "numcommandearrivage" => $dispatchPack->getPack()->getArrivage()
                            ? Stream::from($dispatchPack->getPack()->getArrivage()->getNumeroCommandeList())->join("\n")
                            : "-",
                    ];
                })
                ->toArray();
        }
        else { // $waybillTypeToUse === Setting::DISPATCH_WAYBILL_TYPE_TO_USE_RUPTURE
            $packs = Stream::from($dispatch->getDispatchPacks())
                ->filter(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack()?->getArrivage())
                ->keymap(fn(DispatchPack $dispatchPack) => [
                    $dispatchPack->getPack()->getArrivage()->getId(),
                    $dispatchPack
                ],true)
                ->map(function(array $dispatchPacks) {
                    /** @var DispatchPack $firstDispatchPack */
                    $firstDispatchPack = $dispatchPacks[0];
                    $arrival = $firstDispatchPack->getPack()->getArrivage();
                    $numeroCommandeArrivage = Stream::from($arrival->getNumeroCommandeList())->join("\n");
                    return [
                        "numarrivage" => $arrival->getNumeroArrivage(),
                        "numcommandearrivage" => $numeroCommandeArrivage,
                        "tableauULarrivage" => [
                            ["Unité de tracking", "Nature", "Quantité", "Poids", "Numero commande arrivage"],
                            ...Stream::from($dispatchPacks)
                                ->map(fn(DispatchPack $dispatchPack) => [
                                    $dispatchPack->getPack()->getCode(),
                                    $this->formatService->nature($dispatchPack->getPack()->getNature()),
                                    $dispatchPack->getQuantity(),
                                    $this->formatService->decimal($dispatchPack->getPack()->getWeight(), [], '-'),
                                    $numeroCommandeArrivage,
                                ])
                                ->toArray()
                        ]
                    ];
                })
                ->values();

            if (empty($packs)) {
                throw new FormException("Il n'y a pas d'arrivage lié à cet acheminement, le modèle de rupture à l'arrivage ne peut pas être utilisé");
            }

            $variables['numarrivage'] = $packs;
        }

        $tmpDocxPath = $this->wordTemplateDocument->generateDocx(
            "{$projectDir}/public/$waybillTemplatePath",
            $variables,
            ["barcodes" => ["qrcodenumach"],]
        );


        $nakedFileName = uniqid();

        $waybillOutdir = "{$projectDir}/public/uploads/attachments";
        $docxPath = "{$waybillOutdir}/{$nakedFileName}.docx";
        rename($tmpDocxPath, $docxPath);
        $this->PDFGeneratorService->generateFromDocx($docxPath, $waybillOutdir);
        unlink($docxPath);

        $nowDate = new DateTime('now');

        $client = $this->specificService->getAppClientLabel();

        $title = "LDV - {$dispatch->getNumber()} - {$client} - {$nowDate->format('dmYHis')}";

        $wayBillAttachment = new Attachment();
        $wayBillAttachment
            ->setDispatch($dispatch)
            ->setFileName($nakedFileName . '.pdf')
            ->setOriginalName($title . '.pdf');

        $entityManager->persist($wayBillAttachment);

        return $wayBillAttachment;
    }

    public function persistNewReportAttachmentForEmail(EntityManagerInterface $entityManager,
                                                       Dispatch $dispatch,
                                                       ?Utilisateur $signatory = null): Attachment {

        $status = $dispatch->getStatut();
        $customGroupedSignatureTitle =
            $dispatch->getNumber()
            . '_'
            . (
                $status->getGroupedSignatureType() === Dispatch::DROP
                    ? 'LIV'
                    : "ENL"
            )
            . '_'
            . $this->formatService->location($dispatch->getLocationFrom())
            . '_'
            . $this->formatService->location($dispatch->getLocationTo())
            . '_'
            . (new DateTime())->format('Ymd')
        ;
        $projectDir = $this->kernel->getProjectDir();
        $settingRepository = $entityManager->getRepository(Setting::class);

        $reportTemplatePath = (
            $settingRepository->getOneParamByLabel(Setting::CUSTOM_DISPATCH_RECAP_TEMPLATE)
                ?: $settingRepository->getOneParamByLabel(Setting::DEFAULT_DISPATCH_RECAP_TEMPLATE)
        );

        $referenceArticlesStream = Stream::from($dispatch->getDispatchPacks())
            ->filter(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack() && !$dispatchPack->getDispatchReferenceArticles()->isEmpty())
            ->flatMap(fn(DispatchPack $dispatchPack) => $dispatchPack->getDispatchReferenceArticles()->toArray());

        $variables = [
            "titredocument" => $customGroupedSignatureTitle,
            "numach" => $dispatch->getNumber(),
            "qrcodenumach" => $dispatch->getNumber(),
            "statutach" => $this->formatService->status($dispatch->getStatut()),
            "emppriseach" => $this->formatService->location($dispatch->getLocationFrom()),
            "empdeposeach" => $this->formatService->location($dispatch->getLocationTo()),
            "typeach" => $this->formatService->type($dispatch->getType()),
            "transporteurach" => $this->formatService->carriers([$dispatch->getType()]),
            "numtracktransach" => $dispatch->getCarrierTrackingNumber() ?: '',
            "demandeurach" => $this->formatService->user($dispatch->getRequester()),
            "materielhorsformatref" => $referenceArticlesStream->count() === 1
                ? $this->formatService->bool($referenceArticlesStream->first()->getReferenceArticle()->getDescription()['outFormatEquipment'] ?? null, 'Non')
                : 'Non',
            "destinatairesach" => $this->formatService->users($dispatch->getReceivers()),
            "signataireach" => $this->formatService->user($signatory),
            "numprojetach" => $dispatch->getProjectNumber() ?: '',
            "numcommandeach" => $dispatch->getCommandNumber() ?: '',
            "date1ach" => $this->formatService->date($dispatch->getStartDate()),
            "date2ach" => $this->formatService->date($dispatch->getEndDate()),
            "urgenceach" => $dispatch->getEmergency() ?? 'Non',
            // keep line breaking in docx
            "commentaireach" => $this->formatService->html(str_replace("<br/>", "\n", $dispatch->getCommentaire()), '-'),
            "datestatutach" => $this->formatService->datetime($dispatch->getTreatmentDate()),
        ];

        $variables['UL'] = $referenceArticlesStream
            ->map(function(DispatchReferenceArticle $dispatchReferenceArticle) {
                $comment = $dispatchReferenceArticle->getComment()
                    ? $this->formatService->html(str_replace("<br/>", "\n", $dispatchReferenceArticle->getComment()), '-')
                    : null;
                $dispatchPack = $dispatchReferenceArticle->getDispatchPack();
                $referenceArticle = $dispatchReferenceArticle->getReferenceArticle();
                $description = $referenceArticle->getDescription() ?: [];
                return [
                    "UL" => $dispatchPack->getPack()->getCode(),
                    "natureul" => $this->formatService->nature($dispatchPack->getPack()->getNature()),
                    "quantiteul" => $dispatchPack->getQuantity(),
                    "referenceref" => $referenceArticle->getReference(),
                    "quantiteref" => $dispatchReferenceArticle->getQuantity(),
                    "numeroserieref" => $dispatchReferenceArticle->getSerialNumber(),
                    "numerolotref" => $dispatchReferenceArticle->getBatchNumber(),
                    "numeroscelleref" => $dispatchReferenceArticle->getSealingNumber(),
                    "poidsref" => $description['weight'] ?? '',
                    "volumeref" => $description['volume'] ?? '',
                    "photoref" => $dispatchReferenceArticle->getAttachments()->isEmpty() ? 'Non' : 'Oui',
                    "adrref" => $dispatchReferenceArticle->isADR() ? 'Oui' : 'Non' ,
                    "documentsref" => $description['associatedDocumentTypes'] ?? '',
                    "codefabricantref" => $description['manufacturerCode'] ?? '',
                    "materielhorsformatref" => $this->formatService->bool($description['outFormatEquipment'] ?? null, "Non"),
                    // keep line breaking in docx
                    "commentaireref" => $comment ?: '-',
                ];
            })
            ->toArray();
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

        $reportAttachment = new Attachment();
        $reportAttachment
            ->setDispatch($dispatch)
            ->setFileName($nakedFileName . '.pdf')
            ->setFullPath('/uploads/attachments/' . $nakedFileName . '.pdf')
            ->setOriginalName($customGroupedSignatureTitle . '.pdf');

        $entityManager->persist($reportAttachment);
        $entityManager->flush();

        return $reportAttachment;
    }

    public function generateWayBill(Utilisateur $user, Dispatch $dispatch, EntityManagerInterface $entityManager, array $data) {
        $userDataToSave = [];
        $dispatchDataToSave = [];
        foreach(array_keys(Dispatch::WAYBILL_DATA) as $wayBillKey) {
            if(isset(Dispatch::WAYBILL_DATA[$wayBillKey])) {
                $value = $data[$wayBillKey] ?? null;
                $dispatchDataToSave[$wayBillKey] = $value;
                if(Dispatch::WAYBILL_DATA[$wayBillKey]) {
                    $userDataToSave[$wayBillKey] = $value;
                }
            }
        }
        $user->setSavedDispatchWaybillData($userDataToSave);
        $dispatch->setWaybillData($dispatchDataToSave);

        $entityManager->flush();

        return $this->persistNewWaybillAttachment($entityManager, $dispatch, $user);
    }

    public function updateDispatchReferenceArticle(EntityManagerInterface $entityManager, array $data): JsonResponse
    {
        $dispatchId = $data['dispatch'] ?? null;
        $packId = $data['pack'] ?? null;
        $referenceArticleId = $data['reference'] ?? null;
        $quantity = $data['quantity'] ?? null;

        if (!$dispatchId) {
            throw new FormException("Une erreur est survenue");
        }
        if (!$packId || !$referenceArticleId || !$quantity ) {
            throw new FormException("Une erreur est survenue, des données sont manquantes");
        }
        if ($quantity <= 0) {
            throw new FormException('La quantité doit être supérieure à 0');
        }
        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $referenceArticle = $referenceRepository->find($referenceArticleId);
        $dispatchPack = $dispatchPackRepository->findOneBy(['dispatch' => $dispatchId, 'pack' => $packId]);

        if (!$dispatchPack) {
            throw new FormException('Une erreur est survenue lors du traitement de votre demande');
        }

        $dispatchReferenceArticleId = $data['dispatchReferenceArticle'] ?? null;
        if ($dispatchReferenceArticleId) {
            $dispatchReferenceArticleRepository = $entityManager->getRepository(DispatchReferenceArticle::class);
            $dispatchReferenceArticle = $dispatchReferenceArticleRepository->find($dispatchReferenceArticleId);

            if (isset($data['ULWeight']) && intval($data['ULWeight']) < 0) {
                throw new FormException('Le poids doit être supérieur à 0');
            } else if (isset($data['ULVolume']) && intval($data['ULVolume']) < 0) {
                throw new FormException('Le volume doit être supérieur à 0');
            }

            $nature = $data['nature'] ? $natureRepository->find($data['nature']) : null;
            if (!$nature) {
                throw new FormException("La nature de l'UL est incorrecte");
            }

            $dispatchPack->getPack()
                ->setNature($nature)
                ->setWeight($data['ULWeight'] ?? null)
                ->setVolume($data['ULVolume'] ?? null)
                ->setComment($data['ULComment'] ?? null);
        } else {
            $dispatchReferenceArticle = new DispatchReferenceArticle();
        }

        $dispatchReferenceArticle
            ->setDispatchPack($dispatchPack)
            ->setReferenceArticle($referenceArticle)
            ->setQuantity($quantity)
            ->setBatchNumber($data['batch'] ?? null)
            ->setSealingNumber($data['sealing'] ?? null)
            ->setSerialNumber($data['series'] ?? null)
            ->setComment($data['comment'] ?? null)
            ->setAdr(isset($data['adr']) && boolval($data['adr']));

        $attachments = $this->attachmentService->createAttachments($data['files']);
        foreach ($attachments as $attachment) {
            $entityManager->persist($attachment);
            $dispatchReferenceArticle->addAttachment($attachment);
        }
        $dispatchPack->getDispatch()->setUpdatedAt(new DateTime());
        $entityManager->persist($dispatchReferenceArticle);

        $description = [
            'outFormatEquipment' => $data['outFormatEquipment'] ?? null,
            'manufacturerCode' => $data['manufacturerCode'] ?? null,
            'volume' => $data['volume'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'length' => $data['length'] ?? null,
            'weight' => $data['weight'] ?? null,
            'associatedDocumentTypes' => $data['associatedDocumentTypes'] ?? null,
        ];
        $this->refArticleDataService->updateDescriptionField($entityManager, $referenceArticle, $description);

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => $dispatchReferenceArticleId ? 'Référence et UL modifiées' : 'Référence ajoutée'
        ]);
    }

    public function finishGroupedSignature(EntityManagerInterface $entityManager,
                                           $locationData,
                                           $signatoryTrigramData,
                                           $signatoryPasswordData,
                                           $statusData,
                                           $commentData,
                                           $dispatchesToSignIds,
                                           $fromNomade = false,
                                           $user = null): array {
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $groupedSignatureStatus = $statusRepository->find($statusData);

        $locationId = null;
        if($locationData['from'] && $locationData['to']) {
            $locationId = $groupedSignatureStatus->getGroupedSignatureType() === Dispatch::TAKING ? $locationData['from'] : $locationData['to'];
        } else if($locationData['from']) {
            if($groupedSignatureStatus->getGroupedSignatureType() === Dispatch::TAKING){
                $locationId = $locationData['from'];
            } else {
                throw new FormException("Le statut sélectionné ne correspond pas à un processus d'enlèvement.");
            }
        } else if($locationData['to']) {
            if($groupedSignatureStatus->getGroupedSignatureType() === Dispatch::DROP){
                $locationId = $locationData['to'];
            } else {
                throw new FormException("Le statut sélectionné ne correspond pas à un processus de " . mb_strtolower($this->translationService->translate("Demande", "Livraison", "Demande de livraison", false)) . ".");
            }
        }
        $location = $locationRepository->find($locationId);

        $signatory = $signatoryTrigramData && !$fromNomade
            ? $userRepository->find($signatoryTrigramData)
            : ($signatoryTrigramData
                ? $userRepository->findOneBy(['username' => $signatoryTrigramData])
                :  null);
        if(!$signatoryPasswordData || !$signatoryTrigramData){
            throw new FormException('Le trigramme et le code signataire doivent être rempli.');
        }

        if(!$signatory || !password_verify($signatoryPasswordData, $signatory->getSignatoryPassword())){
            throw new FormException('Le trigramme signataire ou le code est incorrect.');
        }

        $locationSignatories = Stream::from($location?->getSignatories() ?: []);

        if($locationSignatories->isEmpty()){
            $locationLabel = $location?->getLabel() ?: "invalide";
            throw new FormException("L'emplacement filtré {$locationLabel} n'a pas de signataire renseigné");
        }

        $availableSignatory = $locationSignatories->some(fn(Utilisateur $locationSignatory) => $locationSignatory->getId() === $signatory->getId());
        if(!$availableSignatory) {
            throw new FormException("Le signataire renseigné n'est pas correct");
        }


        $dispatchesToSign = $dispatchesToSignIds
            ? $dispatchRepository->findBy(['id' => $dispatchesToSignIds])
            : [];

        if($groupedSignatureStatus->getCommentNeeded() && empty($commentData)) {
            throw new FormException("Vous devez remplir le champ commentaire pour valider");
        }

        $dispatchTypes = Stream::from($dispatchesToSign)
            ->filterMap(fn(Dispatch $dispatch) => $dispatch->getType())
            ->keymap(fn(Type $type) => [$type->getId(), $type])
            ->reindex();

        if ($dispatchTypes->count() !== 1) {
            throw new FormException("Vous ne pouvez sélectionner qu'un seul type d'acheminement pour réaliser une signature groupée");
        }

        $now = new DateTime();

        foreach ($dispatchesToSign as $dispatch) {
            $this->signDispatch(
                $dispatch,
                $groupedSignatureStatus,
                $signatory,
                $user,
                $now,
                $commentData,
                false,
                $entityManager
            );
            $entityManager->flush();
        }

        return [
            'success' => true,
            'msg' => 'Signature groupée effectuée avec succès',
        ];
    }

    public function signDispatch(Dispatch $dispatch,
                                 Statut $groupedSignatureStatus,
                                 Utilisateur $signatory,
                                 Utilisateur $operator,
                                 DateTime $signatureDate,
                                 ?string $comment,
                                 bool $fromNomade,
                                 EntityManagerInterface $entityManager): void {
        $containsReferences = !(Stream::from($dispatch->getDispatchPacks())
            ->flatMap(fn(DispatchPack $dispatchPack) => $dispatchPack->getDispatchReferenceArticles()->toArray())
            ->isEmpty());

        if (!$containsReferences) {
            throw new FormException("L'acheminement {$dispatch->getNumber()} ne contient pas de référence article, vous ne pouvez pas l'ajouter à une signature groupée");
        }

        if ($dispatch->getType()->getId() === $groupedSignatureStatus->getType()->getId()) {
            $this->statusHistoryService->updateStatus($entityManager, $dispatch, $groupedSignatureStatus, [
                'initiatedBy' => $operator,
                'validatedBy' => $signatory,
                'date' => $signatureDate
            ]);
        } else {
            throw new FormException("L'acheminement {$dispatch->getNumber()} : le type du statut sélectionné est invalide.");
        }

        $newCommentDispatch = $dispatch->getCommentaire()
            ? ($dispatch->getCommentaire() . "<br>")
            : "";

        $dispatch
            ->setTreatmentDate($signatureDate)
            ->setTreatedBy($operator);

        if($comment){
            $dispatch->setCommentaire($newCommentDispatch . $comment);
        }


        $takingLocation = $dispatch->getLocationFrom();
        $dropLocation = $dispatch->getLocationTo();

        foreach ($dispatch->getDispatchPacks() as $dispatchPack) {
            $pack = $dispatchPack->getPack();
            $trackingTaking = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $takingLocation,
                $operator,
                $signatureDate,
                $fromNomade,
                true,
                TrackingMovement::TYPE_PRISE,
                [
                    'quantity' => $dispatchPack->getQuantity(),
                    'from' => $dispatch,
                    'removeFromGroup' => true,
                    'attachments' => $dispatch->getAttachments(),
                    'entityManager' => $entityManager
                ]
            );
            $trackingDrop = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $dropLocation,
                $operator,
                $signatureDate,
                $fromNomade,
                true,
                TrackingMovement::TYPE_DEPOSE,
                [
                    'quantity' => $dispatchPack->getQuantity(),
                    'from' => $dispatch,
                    'attachments' => $dispatch->getAttachments(),
                    'entityManager' => $entityManager
                ]
            );

            $entityManager->persist($trackingTaking);
            $entityManager->persist($trackingDrop);

            $dispatchPack->setTreated(true);
        }

        if($groupedSignatureStatus->getSendReport()){
            $this->sendEmailsAccordingToStatus($entityManager, $dispatch, true, true, $signatory);
        }
    }

    public function getGroupedSignatureTypes(?string $groupedSignatureType = ''): string
    {
        $emptyOption = "<option value=''></option>";
        $options = Stream::from(Dispatch::GROUPED_SIGNATURE_TYPES)
            ->map(function(string $type) use ($groupedSignatureType) {
                $selected = $type === $groupedSignatureType ? 'selected' : '';
                return "<option value='{$type}' {$selected}>{$type}</option>";
            })
            ->join('');
        return $emptyOption . $options;
    }

    public function getWayBillDataForUser(Utilisateur $user, EntityManagerInterface $entityManager, Dispatch $dispatch = null) {

        $settingRepository = $entityManager->getRepository(Setting::class);

        $dispatchSavedLDV = $settingRepository->getOneParamByLabel(Setting::DISPATCH_SAVE_LDV);
        $userSavedData = $dispatchSavedLDV ? [] : $user->getSavedDispatchWaybillData();
        $dispatchSavedData = $dispatch?->getWaybillData();

        $now = new DateTime('now');

        $isEmerson = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_EMERSON);

        $consignorUsername = $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_NAME);
        $consignorUsername = $consignorUsername !== null && $consignorUsername !== ''
            ? $consignorUsername
            : ($isEmerson ? $user->getUsername() : null);

        $consignorEmail = $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL);
        $consignorEmail = $consignorEmail !== null && $consignorEmail !== ''
            ? $consignorEmail
            : ($isEmerson ? $user->getEmail() : null);

        $defaultData = [
            'carrier' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CARRIER),
            'dispatchDate' => $now->format('Y-m-d'),
            'consignor' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONSIGNER),
            'receiver' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_RECEIVER),
            'locationFrom' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_LOCATION_FROM),
            'locationTo' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_LOCATION_TO),
            'consignorUsername' => $consignorUsername,
            'consignorEmail' => $consignorEmail,
            'receiverUsername' => $isEmerson ? $user->getUsername() : null,
            'receiverEmail' => $isEmerson ? $user->getEmail() : null,
            'packsCounter' => $dispatch?->getDispatchPacks()->count()
        ];
        return Stream::from(Dispatch::WAYBILL_DATA)
            ->keymap(fn(bool $data, string $key) => [$key, $dispatchSavedData[$key] ?? $userSavedData[$key] ?? $defaultData[$key] ?? null])
            ->toArray();
    }


    public function treatMobileDispatchReference(EntityManagerInterface $entityManager,
                                                 Dispatch $dispatch,
                                                 array $data,
                                                 array &$createdReferences,
                                                 array $options){
        if(!isset($data['logisticUnit']) || !isset($data['reference'])){
            throw new FormException("L'unité logistique et la référence n'ont pas été saisies");
        }

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $defaultNature = $natureRepository->findOneBy(['defaultNature' => true]);
        $packNature = isset($data['natureId']) ? $natureRepository->find($data['natureId']) : null;

        $reference = ($createdReferences[$data['reference']] ?? null)
            ?: $referenceArticleRepository->findOneBy(['reference' => $data['reference']]);
        if(!$reference) {
            $dispatchNewReferenceType = $settingRepository->getOneParamByLabel(Setting::DISPATCH_NEW_REFERENCE_TYPE);
            $dispatchNewReferenceStatus = $settingRepository->getOneParamByLabel(Setting::DISPATCH_NEW_REFERENCE_STATUS);
            $dispatchNewReferenceQuantityManagement = $settingRepository->getOneParamByLabel(Setting::DISPATCH_NEW_REFERENCE_QUANTITY_MANAGEMENT);

            if($dispatchNewReferenceType === null) {
                throw new FormException("Vous n'avez pas paramétré de type par défaut pour la création de références.");
            } elseif ($dispatchNewReferenceStatus === null) {
                throw new FormException("Vous n'avez pas paramétré de statut par défaut pour la création de références.");
            } elseif ($dispatchNewReferenceQuantityManagement === null) {
                throw new FormException("Vous n'avez pas paramétré de gestion de quantité par défaut pour la création de références.");
            }

            $type = $typeRepository->find($dispatchNewReferenceType);
            $status = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, $dispatchNewReferenceStatus);

            $reference = (new ReferenceArticle())
                ->setReference($data['reference'])
                ->setLibelle($data['reference'])
                ->setType($type)
                ->setStatut($status)
                ->setTypeQuantite($dispatchNewReferenceQuantityManagement == 0
                    ? ReferenceArticle::QUANTITY_TYPE_REFERENCE
                    : ReferenceArticle::QUANTITY_TYPE_ARTICLE
                )
                ->setCreatedBy($options['loggedUser'])
                ->setCreatedAt($options['now'])
                ->setBarCode($this->refArticleDataService->generateBarCode())
                ->setQuantiteStock(0)
                ->setQuantiteDisponible(0);

            $createdReferences[$data['reference']] = $reference;

            $entityManager->persist($reference);
        }

        if(isset($data['photos'])){
            $photos = json_decode($data['photos'], true);
            foreach ($photos as $index => $photo) {
                $name = uniqid();
                $this->attachmentService->createFile("$name.jpeg", file_get_contents($photo));

                $attachment = new Attachment();
                $attachment
                    ->setOriginalName($reference->getReference() . "_photo". $index . "_". $name .".jpeg")
                    ->setFileName("$name.jpeg")
                    ->setFullPath("/uploads/attachments/$name.jpeg");

                $entityManager->persist($attachment);
                $reference->addAttachment($attachment);
            }
        }

        $reference->setCommentaire($data['comment']);

        $oldDescription = $reference->getDescription();
        $this->refArticleDataService->updateDescriptionField($entityManager, $reference, [
            'outFormatEquipment' => $data['outFormatEquipment'],
            'manufacturerCode' => $data['manufacturerCode'],
            'volume' =>  $data['volume'],
            'length' =>  $data['length'] ?: ($oldDescription['length'] ?? null),
            'width' =>  $data['width'] ?: ($oldDescription['width'] ?? null),
            'height' =>  $data['height'] ?: ($oldDescription['height'] ?? null),
            'weight' => $data['weight'],
            'associatedDocumentTypes' => $data['associatedDocumentTypes'],
        ]);

        $logisticUnit = $packRepository->findOneBy(['code' => $data['logisticUnit']])
            ?? $this->packService->createPackWithCode($data['logisticUnit']);

        $logisticUnit->setNature($packNature ?? $defaultNature);

        $entityManager->persist($logisticUnit);

        $dispatchPack = $dispatch->getDispatchPack($logisticUnit);

        if (!$dispatchPack) {
            $dispatchPack = (new DispatchPack())
                ->setDispatch($dispatch)
                ->setPack($logisticUnit)
                ->setTreated(false);

            $entityManager->persist($dispatchPack);
        }

        $dispatchReferenceArticle = $dispatchPack->getDispatchReferenceArticle($reference);

        if (!$dispatchReferenceArticle) {
            $dispatchReferenceArticle = (new DispatchReferenceArticle())
                ->setReferenceArticle($reference)
                ->setDispatchPack($dispatchPack);

            $entityManager->persist($dispatchReferenceArticle);
        }

        $dispatchReferenceArticle = $dispatchReferenceArticle
            ->setQuantity($data['quantity'])
            ->setBatchNumber($data['batchNumber'])
            ->setSerialNumber($data['serialNumber'])
            ->setSealingNumber($data['sealingNumber'])
            ->setComment($data['comment'])
            ->setADR(isset($data['adr']) && boolval($data['adr']));

        $maxNbFilesSubmitted = 10;
        $fileCounter = 1;
        // upload of photo_1 to photo_10
        do {
            $photoFile = $data["photo_$fileCounter"] ?? [];
            if (!empty($photoFile)) {
                $name = uniqid();
                $this->attachmentService->createFile("$name.jpeg", file_get_contents($photoFile));

                $attachment = new Attachment();
                $attachment
                    ->setOriginalName("photo_$fileCounter.jpeg")
                    ->setFileName("$name.jpeg")
                    ->setFullPath("/uploads/attachments/$name.jpeg");

                $dispatchReferenceArticle->addAttachment($attachment);
                $entityManager->persist($attachment);
            }
            $fileCounter++;
        } while (!empty($photoFile) && $fileCounter <= $maxNbFilesSubmitted);
    }

    public function checkFormForErrors(EntityManagerInterface $entityManager,
                                       InputBag               $form,
                                       Dispatch               $dispatch,
                                       bool                   $isCreation,
                                       Type                   $type):InputBag {
        if ($form->get(FixedFieldStandard::FIELD_CODE_START_DATE_DISPATCH) && $form->get(FixedFieldStandard::FIELD_CODE_END_DATE_DISPATCH)){
            $form->add([
                FixedFieldStandard::FIELD_CODE_DEADLINE_DISPATCH => true,
            ]);
        }
        if ($dispatch->getAttachments()->count()){
            $form->add([
                FixedFieldStandard::FIELD_CODE_ATTACHMENTS_DISPATCH => true,
            ]);
        }
        return $this->fieldsParamService->checkForErrors(
            $entityManager,
            $form,
            FixedFieldStandard::ENTITY_CODE_DISPATCH,
            $isCreation,
            New ParameterBag([
                FixedFieldStandard::FIELD_CODE_EMERGENCY => true,
            ]),
            $type,
        );
    }

    public function getDispatckPacksColumnVisibleConfig(EntityManagerInterface $entityManager, bool $editMode = false): array {
        $subLineFieldsParamRepository = $entityManager->getRepository(SubLineFixedField::class);

        $fieldParams = $subLineFieldsParamRepository->getByEntity(SubLineFixedField::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT);
        $columns = [
            ['name' => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true],
            ["name" => 'code', 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Code')],
            ["name" => 'quantity', 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Quantité UL')],
            ["name" => 'nature', 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Nature')],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT, 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Poids (kg)')],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME, 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Volume (m3)')],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT, 'title' => 'Commentaire'],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_TRACKING_DATE, 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date dernier mouvement')],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION, 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Dernier emplacement')],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR, 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Opérateur')],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_STATUS, 'title' => 'Statut'],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT, 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Hauteur (m)'), 'width' => '200px'],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH, 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Largeur (m)'), 'width' => '200px'],
            ["name" => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH, 'title' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Longueur (m)'), 'width' => '200px'],
        ];

        $columns = Stream::from($columns)
            ->filterMap(function (array $column) use ($fieldParams) {
                $columnName = $column['name'] ?? null;
                if (array_key_exists($columnName, $fieldParams)) {
                    if ($fieldParams[$columnName]['required'] ?? false) {
                        $column['required'] = true;
                    }
                    if ($fieldParams[$columnName]['displayed'] ?? false) {
                        return $column;
                    } else {
                        return null;
                    }
                } else {
                    return $column;
                }
            })
            ->values();

        return $this->visibleColumnService->getArrayConfig($columns);
    }

    public function getDispatchLabelData(Dispatch $dispatch): array {
        $now = new DateTime();
        $client = $this->specificService->getAppClientLabel();

        $originalName = "ETQ - {$dispatch->getNumber()} - $client - {$now->format('dmYHis')}";
        $fileName = uniqid().'.pdf';

        $pdfContent = $this->PDFGeneratorService->generateDispatchLabel($dispatch, $originalName);

        $attachment = new Attachment();
        $attachment->setFileName($fileName);
        $attachment->setOriginalName($originalName);
        $attachment->setDispatch($dispatch);

        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

        return [
            'file' => $pdfContent,
            'name' => $originalName
        ];
    }
}
