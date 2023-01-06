<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\DispatchPack;
use App\Entity\FreeField;
use App\Entity\Dispatch;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Language;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\TrackingMovement;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\LanguageHelper;
use App\Service\Document\TemplateDocumentService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

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
    public FieldsParamService $fieldsParamService;

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

    private ?array $freeFieldsConfig = null;

    public function getDataForDatatable(InputBag $params) {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $dispatchRepository = $this->entityManager->getRepository(Dispatch::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DISPATCH, $this->userService->getUser());

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
            $rows[] = $this->dataRowDispatch($dispatch);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowDispatch(Dispatch $dispatch) {

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
            'type' => $this->formatService->type($dispatch->getType()),
            'status' => $this->formatService->status($dispatch->getStatut()),
            'emergency' => $dispatch->getEmergency() ?? '',
            'treatedBy' => $this->formatService->user($dispatch->getTreatedBy()),
            'treatmentDate' => $this->formatService->datetime($dispatch->getTreatmentDate()),
            'actions' => $this->templating->render('dispatch/list/actions.html.twig', [
                'dispatch' => $dispatch,
                'url' => $url
            ]),
        ];

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
                                         array $packs = []) {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

        $dispatchBusinessUnits = $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_BUSINESS_UNIT);

        $draftStatuses = $statusRepository->findByCategoryAndStates(CategorieStatut::DISPATCH, [Statut::DRAFT]);
        $existingDispatches = $dispatchRepository->findBy([
            'requester' => $this->userService->getUser(),
            'statut' => $draftStatuses
        ]);

        return [
            'dispatchBusinessUnits' => !empty($dispatchBusinessUnits) ? $dispatchBusinessUnits : [],
            'fieldsParam' => $fieldsParam,
            'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY),
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
                    ]
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
                ->toArray()
        ];
    }

    public function createHeaderDetailsConfig(Dispatch $dispatch): array {
        $fieldsParamRepository = $this->entityManager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        $status = $dispatch->getStatut();
        $type = $dispatch->getType();
        $carrier = $dispatch->getCarrier();
        $carrierTrackingNumber = $dispatch->getCarrierTrackingNumber();
        $commandNumber = $dispatch->getCommandNumber();
        $requester = $dispatch->getRequester();
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
        $comment = $dispatch->getCommentaire() ?? '';
        $treatedBy = $dispatch->getTreatedBy() ? $dispatch->getTreatedBy()->getUsername() : '';
        $attachments = $dispatch->getAttachments();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $dispatch,
            ['type' => $dispatch->getType()],
            $this->security->getUser()
        );
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
                'label' => $this->translationService->translate('Demande', 'Général', 'Statut', false),
                'value' => $status ? $this->formatService->status($status) : ''
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Général', 'Type', false),
                'value' => $this->formatService->type($type),
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Transporteur', false),
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_CARRIER_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'N° tracking transporteur', false),
                'value' => $carrierTrackingNumber,
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Général', 'Demandeur', false),
                'value' => $requester ? $requester->getUsername() : ''
            ],
            $receiverDetails ?? [],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'N° projet', false),
                'value' => $projectNumber,
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_PROJECT_NUMBER]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Business unit', false),
                'value' => $dispatch->getBusinessUnit() ?? '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_BUSINESS_UNIT]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'N° commande', false),
                'value' => $commandNumber,
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_COMMAND_NUMBER_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de prise', false),
                'value' => $locationFrom ? $locationFrom->getLabel() : '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_LOCATION_PICK]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de dépose', false),
                'value' => $locationTo ? $locationTo->getLabel() : '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_LOCATION_DROP]
            ],
            [
                'label' => $this->translationService->translate('Général', null, 'Zone liste', 'Date de création', false),
                'value' => $this->formatService->datetime($creationDate, "", $user)
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date de validation', false),
                'value' => $this->formatService->datetime($validationDate, "", $user)
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', "Dates d'échéance", false),
                'value' => ($startDate || $endDate)
                    ? $this->translationService->translate('Général', null, 'Zone liste', "Du {1} au {2}", [
                        1 => $startDateStr,
                        2 => $endDateStr
                    ], false)
                    : ''
            ],
            [
                'label' => $this->translationService->translate('Général', null, 'Zone liste', 'Traité par', false),
                'value' => $treatedBy
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date de traitement', false),
                'value' => $this->formatService->datetime($treatmentDate, "", false, $this->security->getUser())
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Destination', false),
                'value' => $dispatch->getDestination() ?: '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_DESTINATION]
            ],
        ];
        $configFiltered = $this->fieldsParamService->filterHeaderConfig($config, FieldsParam::ENTITY_CODE_DISPATCH);
        return array_merge(
            $configFiltered,
            $freeFieldArray,
            ($this->fieldsParamService->isFieldRequired($fieldsParam, 'comment', 'displayedCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, 'comment', 'displayedEdit'))
                ? [[
                    'label' => $this->translationService->translate('Général', null, 'Modale', "Commentaire"),
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]]
                : [],
            ($this->fieldsParamService->isFieldRequired($fieldsParam, 'attachments', 'displayedCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, 'attachments', 'displayedEdit'))
                ? [[
                       'label' => $this->translationService->translate('Général', null, 'Modale', 'Pièces jointes', false),
                       'value' => $attachments->toArray(),
                       'isAttachments' => true,
                       'isNeededNotEmpty' => true
                ]]
                : []
        );
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

    public function sendEmailsAccordingToStatus(Dispatch $dispatch, bool $isUpdate)
    {
        $defaultLanguage = $this->languageService->getDefaultSlug();
        $status = $dispatch->getStatut();
        $recipientAbleToReceivedMail = $status ? $status->getSendNotifToRecipient() : false;
        $requesterAbleToReceivedMail = $status ? $status->getSendNotifToDeclarant() : false;

        if ($recipientAbleToReceivedMail || $requesterAbleToReceivedMail) {
            if ($recipientAbleToReceivedMail && !$dispatch->getReceivers()->isEmpty()) {
                $receiverEmailUses = $dispatch->getReceivers()->toArray();
            }
            else {
                $receiverEmailUses = [];
            }

            if ($requesterAbleToReceivedMail && $dispatch->getRequester()) {
                $receiverEmailUses[] = $dispatch->getRequester();
            }

            $partialDispatch = !(
                $dispatch
                    ->getDispatchPacks()
                    ->filter(function(DispatchPack $dispatchPack) {
                        return !$dispatchPack->isTreated();
                    })
                    ->isEmpty()
            );

            $translatedTitle = $partialDispatch
                ? 'Acheminement {1} traité partiellement le {2}'
                : 'Acheminement {1} traité le {2}';

            $title = fn(string $slug) => (
                $status->isTreated()
                    ? ['Demande', 'Acheminements', 'Emails', $translatedTitle, [
                        1 => $dispatch->getNumber(),
                        2 => $this->formatService->datetime($dispatch->getTreatmentDate(), "", false, $this->security->getUser())
                    ], false]
                    : (!$isUpdate
                        ? ["Demande", "Acheminements", "Emails", "Une demande d'acheminement de type {1} vous concerne :", [
                            1 => $this->formatService->type($dispatch->getType())
                        ], false]
                        : ["Demande", "Acheminements", "Emails", "Changement de statut d'une demande d'acheminement de type {1} vous concernant :", [
                            1 => $this->formatService->type($dispatch->getType())
                        ], false])
            );

            $subject = ($status->isTreated() || $status->isPartial())
                ? ['Demande','Acheminements', 'Emails', 'Follow GT // Notification de traitement d\'une demande d\'acheminement', false]
                : (!$isUpdate
                    ? ['Demande','Acheminements', 'Emails', 'Follow GT // Création d\'une demande d\'acheminement', false]
                    : ['Demande','Acheminements', 'Emails', 'FOLLOW GT // Changement de statut d\'une demande d\'acheminement', false]);

            $isTreatedStatus = $dispatch->getStatut() && $dispatch->getStatut()->isTreated();
            $isTreatedByOperator = $dispatch->getTreatedBy() && $dispatch->getTreatedBy()->getUsername();

            $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
                $this->entityManager,
                $dispatch,
                ['type' => $dispatch->getType()]
            );

            if (!empty($receiverEmailUses)) {
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
                            'totalCost' => $freeFieldArray
                        ]
                    ],
                    $receiverEmailUses
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
            ->setStatut($treatedStatus)
            ->setTreatmentDate($date)
            ->setTreatedBy($loggedUser);

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
            }
        }
        $entityManager->flush();

        $this->sendEmailsAccordingToStatus($dispatch, true);

        $packs = Stream::from($dispatch->getDispatchPacks())
            ->map(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack())
            ->toArray();

        foreach ($packs as $pack) {
            $this->arrivalService->sendMailForDeliveredPack($dispatch->getLocationTo(), $pack, $loggedUser, TrackingMovement::TYPE_DEPOSE, $date);
        }
    }

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $columnsVisible = $currentUser->getVisibleColumns()['dispatch'];
        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::DEMANDE_DISPATCH, CategorieCL::DEMANDE_DISPATCH);

        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
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
        ];

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
            $this->natures = $natureRepository->findAll();
            $this->defaultNature = $natureRepository->findOneBy(["defaultForDispatch" => true]);
         }

        if($dispatchPack) {
            $pack = $dispatchPack->getPack();
            $lastTracking = $pack->getLastTracking();

            $code = $pack->getCode();
            $quantity = $dispatchPack->getQuantity();
            $nature = $this->formatService->nature($pack->getNature());
            $weight = $pack->getWeight();
            $volume = $pack->getVolume();
            $comment = $pack->getComment();
            $lastMvtDate = $lastTracking && $lastTracking->getDatetime() ? $lastTracking->getDatetime()->format("Y-m-d H:i") : null;
            $lastLocation = $lastTracking ? $this->formatService->location($lastTracking->getEmplacement()) : null;
            $operator = $lastTracking ? $this->formatService->user($lastTracking->getOperateur()) : null;
            $status = $dispatchPack->isTreated()
                ? $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Traité')
                : $this->translationService->translate('Demande', 'Acheminements', 'Général', 'À traiter');
        } else {
            $quantity = null;
            $nature = null;
            $weight = null;
            $volume = null;
            $comment = null;
            $lastMvtDate = null;
            $lastLocation = null;
            $operator = null;
            $status = null;
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

            $labelNatureSelection = $this->translationService->translate('Traçabilité', 'Général', 'Sélectionner une nature', false);


            $natureOptions = Stream::from($this->natures)
                ->map(function(Nature $n) use ($nature) {
                    $label = $this->formatService->nature($n);

                    return [
                        "id" => $n->getId(),
                        "label" => $label,
                        "selected" => ($label === $nature || (!$nature && $this->defaultNature === $n)) ? "selected" : "",
                    ];
                 })
                ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"])
                ->map(fn(array $n) => "<option value='{$n["id"]}' {$n["selected"]}>{$n["label"]}</option>")
                ->prepend(!$nature && !$this->defaultNature ? "<option disabled selected>$labelNatureSelection</option>" : null)
                ->join("");

            $data = [
                "actions" => $actions,
                "code" => isset($code)
                    ? "<span title='$code'>$code</span> <input type='hidden' name='pack' class='data' value='$code'/>"
                    : "<select name='pack'
                               data-s2='keyboardPacks'
                               data-parent='body'
                               data-include-params-parent='.wii-box'
                               data-include-params='[name=pack]'
                               data-include-params-group
                               class='w-300px'
                               $searchPrefix
                               $autofocus></select>",
                "quantity" => "<input name='quantity' min=1 step=1 type='number' class='$class' data-global-error='Quantité' value='$quantity' required/>",
                "nature" => "<select name='nature' class='$class minw-150px' data-global-error='Nature' required>{$natureOptions}</select>",
                "weight" => "<input name='weight' type='number' class='$class no-overflow' data-no-arrow step='0.001' value='$weight'/>",
                "volume" => "<input name='volume' type='number' class='$class no-overflow' data-no-arrow step='0.001' value='$volume'/>",
                "comment" => "<div class='wii-one-line-wysiwyg ql-editor data $creationMode' data-wysiwyg='comment'>$comment</div>",
                "lastMvtDate" => $lastMvtDate ?? "<span class='lastMvtDate'></span>",
                "lastLocation" => $lastLocation ?? "<span class='lastLocation'></span>",
                "operator" => $operator ?? "<span class='operator'></span>",
                "status" => $status ?? "<span class='status'></span>",
            ];
        } else if($dispatchPack) {
            $data = [
                "actions" => $actions,
                "code" => $code,
                "nature" => $nature,
                "quantity" => $quantity,
                "weight" => $weight,
                "volume" => $volume,
                "comment" => "<div class='ql-editor'>$comment</div>",
                "lastMvtDate" => $lastMvtDate,
                "lastLocation" => $lastLocation,
                "operator" => $operator,
                "status" => $status,
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
                ->setComment(StringHelper::cleanedComment($comment));
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
                                    Dispatch $dispatch,
                                    array $receivers,
                                    array $nbPacksByDispatch,
                                    array $freeFieldsConfig): void {

        $id = $dispatch->getId();
        $number = $dispatch->getNumber();

        $dispatchDataBefore = [
            $number,
            $dispatch->getCommandNumber(),
            $this->formatService->datetime($dispatch->getCreationDate()),
            $this->formatService->datetime($dispatch->getValidationDate()),
            $this->formatService->datetime($dispatch->getTreatmentDate()),
            $this->formatService->type($dispatch->getType()),
            $this->formatService->user($dispatch->getRequester()),
            Stream::from($receivers[$id] ?? [])->join(", "),
            $this->formatService->location($dispatch->getLocationFrom()),
            $this->formatService->location($dispatch->getLocationTo()),
            $dispatch->getDestination(),
            $nbPacksByDispatch[$number] ?? '',
            $this->formatService->status($dispatch->getStatut()),
            $dispatch->getEmergency(),
        ];

        $freeFieldValues = $dispatch->getFreeFields();
        $dispatchDataAfter = array_merge(
            [$this->formatService->user($dispatch->getTreatedBy())],
            Stream::from($freeFieldsConfig['freeFields'])
                ->map(function(FreeField $freeField, $freeFieldId) use ($freeFieldValues) {
                    $value = $freeFieldValues[$freeFieldId] ?? null;
                    return $value
                        ? $this->formatService->freeField($freeFieldValues[$freeFieldId] ?? '', $freeField)
                        : $value;
                })
                ->toArray()
        );

        foreach ($dispatch->getDispatchPacks() as $dispatchPack) {
            $pack = $dispatchPack->getPack();
            $lastTracking = $pack?->getLastTracking();
            $row = array_merge(
                $dispatchDataBefore,
                [
                    $this->formatService->nature($dispatchPack->getPack()?->getNature()),
                    $pack?->getCode(),
                    $pack?->getQuantity(),
                    $dispatchPack->getQuantity(),
                    $pack?->getWeight(),
                    $this->formatService->datetime($lastTracking?->getDatetime()),
                    $this->formatService->location($lastTracking?->getEmplacement()),
                    $this->formatService->user($lastTracking?->getOperateur()),
                ],
                $dispatchDataAfter
            );
            $this->CSVExportService->putLine($handle, $row);
        }

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
        $client = SpecificService::CLIENTS[$this->specificService->getAppClient()];

        $name = "BS - {$dispatch->getNumber()} - $client - {$now->format('dmYHis')}";

        return [
            'file' => $this->PDFGeneratorService->generatePDFOverconsumption($dispatch, $appLogo, $overConsumptionLogo, $additionalField),
            'name' => $name
        ];
    }

    public function getDeliveryNoteData(Dispatch $dispatch): array {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $logo = $settingRepository->getOneParamByLabel(Setting::FILE_WAYBILL_LOGO);
        $now = new DateTime();
        $client = SpecificService::CLIENTS[$this->specificService->getAppClient()];

        $name = "BL - {$dispatch->getNumber()} - $client - {$now->format('dmYHis')}";

        return [
            'file' => $this->PDFGeneratorService->generatePDFDeliveryNote($name, $logo, $dispatch),
            'name' => $name
        ];
    }

    public function getDispatchNoteData(Dispatch $dispatch): array {
        $now = new DateTime();
        $client = SpecificService::CLIENTS[$this->specificService->getAppClient()];

        $name = "BA - {$dispatch->getNumber()} - $client - {$now->format('dmYHis')}";

        return [
            'file' => $this->PDFGeneratorService->generatePDFDispatchNote($dispatch),
            'name' => $name
        ];
    }

    public function persistNewWaybillAttachment(EntityManagerInterface $entityManager,
                                                Dispatch               $dispatch): Attachment {

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
            ->filter(fn(DispatchPack $dispatchPack) => (!$waybillTypeToUse || $dispatchPack->getPack()?->getArrivage()))
            ->map(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack()->getWeight())
            ->filter()
            ->sum();
        $totalVolume = Stream::from($dispatch->getDispatchPacks())
            ->filter(fn(DispatchPack $dispatchPack) => (!$waybillTypeToUse || $dispatchPack->getPack()?->getArrivage()))
            ->map(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack()->getVolume())
            ->filter()
            ->sum();
        $totalQuantities = Stream::from($dispatch->getDispatchPacks())
            ->filter(fn(DispatchPack $dispatchPack) => (!$waybillTypeToUse || $dispatchPack->getPack()?->getArrivage()))
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
            "dateacheminement" => $this->formatService->date($waybillDate),
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
            "totalvolume" => $this->formatService->decimal($totalVolume, [], '-'),
            "totalquantite" => $this->formatService->decimal($totalQuantities, [], '-'),
        ];

        if ($waybillTypeToUse === Setting::DISPATCH_WAYBILL_TYPE_TO_USE_STANDARD) {
            $variables['UL'] = $dispatch->getDispatchPacks()
                ->filter(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack())
                ->map(fn(DispatchPack $dispatchPack) => [
                    "UL" => $dispatchPack->getPack()->getCode(),
                    "nature" => $this->formatService->nature($dispatchPack->getPack()->getNature()),
                    "quantite" => $dispatchPack->getQuantity(),
                    "poids" => $this->formatService->decimal($dispatchPack->getPack()->getWeight(), [], '-'),
                    "volume" => $this->formatService->decimal($dispatchPack->getPack()->getVolume(), [], '-'),
                    "commentaire" => strip_tags($dispatchPack->getPack()->getComment()) ?: '-',
                ])
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
                    return [
                        "numarrivage" => $arrival->getNumeroArrivage(),
                        "numcommandearrivage" => Stream::from($arrival->getNumeroCommandeList())->join("\n"),
                        "tableauULarrivage" => [
                            ["Unité de tracking", "Nature", "Quantité", "Poids"],
                            ...Stream::from($dispatchPacks)
                                ->map(fn(DispatchPack $dispatchPack) => [
                                    $dispatchPack->getPack()->getCode(),
                                    $this->formatService->nature($dispatchPack->getPack()->getNature()),
                                    $dispatchPack->getQuantity(),
                                    $this->formatService->decimal($dispatchPack->getPack()->getWeight(), [], '-'),
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
            "${projectDir}/public/$waybillTemplatePath",
            $variables,
            ["barcodes" => ["qrcodenumach"],]
        );

        $nakedFileName = uniqid();

        $fullPath = "{$projectDir}/public/uploads/attachements/$nakedFileName";
        rename($tmpDocxPath, $fullPath . '.docx');

        $this->PDFGeneratorService->generateFromDocx($fullPath . '.docx');
        unlink($fullPath . '.docx');
        rename("$projectDir/public/$nakedFileName.pdf", $fullPath . '.pdf');
        $nowDate = new DateTime('now');

        $client = SpecificService::CLIENTS[$this->specificService->getAppClient()];

        $title = "LDV - {$dispatch->getNumber()} - {$client} - {$nowDate->format('dmYHis')}";

        $wayBillAttachment = new Attachment();
        $wayBillAttachment
            ->setDispatch($dispatch)
            ->setFileName($nakedFileName . '.pdf')
            ->setOriginalName($title . '.pdf');

        $entityManager->persist($wayBillAttachment);

        return $wayBillAttachment;
    }

}
