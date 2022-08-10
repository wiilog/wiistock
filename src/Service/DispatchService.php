<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\DispatchPack;
use App\Entity\FreeField;
use App\Entity\Dispatch;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\TrackingMovement;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use App\Service\TranslationService;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class DispatchService {

    const WAYBILL_MAX_PACK = 20;

    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public UserService $userService;

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public FreeFieldService $freeFieldService;

    /** @Required */
    public TranslationService $translation;

    /** @Required */
    public MailerService $mailerService;

    /** @Required */
    public TrackingMovementService $trackingMovementService;

    /** @Required */
    public FieldsParamService $fieldsParamService;

    /** @Required */
    public VisibleColumnService $visibleColumnService;

    /** @Required */
    public ArrivageService $arrivalService;

    #[Required]
    public Security $security;

    private ?array $freeFieldsConfig = null;

    public function getDataForDatatable(InputBag $params) {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $dispatchRepository = $this->entityManager->getRepository(Dispatch::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DISPATCH, $this->userService->getUser());

        $queryResult = $dispatchRepository->findByParamAndFilters($params, $filters, $this->userService->getUser(), $this->visibleColumnService);

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
        $user = $this->security->getUser();
        $row = [
            'id' => $dispatch->getId() ?? 'Non défini',
            'number' => $dispatch->getNumber() ?? '',
            'carrier' => $dispatch->getCarrier() ? $dispatch->getCarrier()->getLabel() : '',
            'carrierTrackingNumber' => $dispatch->getCarrierTrackingNumber(),
            'commandNumber' => $dispatch->getCommandNumber(),
            'creationDate' => FormatHelper::datetime($dispatch->getCreationDate(), "", false, $user),
            'validationDate' => FormatHelper::datetime($dispatch->getValidationDate(), "", false, $user),
            'endDate' => FormatHelper::date($dispatch->getEndDate(), "", false, $user),
            'requester' => $dispatch->getRequester() ? $dispatch->getRequester()->getUserName() : '',
            'receivers' => $receiversUsernames ?? '',
            'locationFrom' => $dispatch->getLocationFrom() ? $dispatch->getLocationFrom()->getLabel() : '',
            'locationTo' => $dispatch->getLocationTo() ? $dispatch->getLocationTo()->getLabel() : '',
            'destination' => $dispatch->getDestination() ?? '',
            'nbPacks' => $dispatch->getDispatchPacks()->count(),
            'type' => $dispatch->getType() ? $dispatch->getType()->getLabel() : '',
            'status' => $dispatch->getStatut() ? $dispatch->getStatut()->getNom() : '',
            'emergency' => $dispatch->getEmergency() ?? '',
            'treatedBy' => $dispatch->getTreatedBy() ? $dispatch->getTreatedBy()->getUsername() : '',
            'treatmentDate' => FormatHelper::datetime($dispatch->getTreatmentDate(), "", false, $user),
            'actions' => $this->templating->render('dispatch/list/actions.html.twig', [
                'dispatch' => $dispatch,
                'url' => $url
            ]),
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $dispatch->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = FormatHelper::freeField($freeFieldValue, $freeField, $user);
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
                    'typeLabel' => $type->getLabel(),
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
                    'locationTo' => FormatHelper::location($dispatch->getLocationTo()),
                    'type' => FormatHelper::type($dispatch->getType())
                ])
                ->toArray()
        ];
    }

    public function createHeaderDetailsConfig(Dispatch $dispatch): array {
        $fieldsParamRepository = $this->entityManager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

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
        $startDateStr = FormatHelper::date($startDate, "", false, $this->security->getUser());
        $endDateStr = FormatHelper::date($endDate, "", false, $this->security->getUser());
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
            "label" => "Destinataire(s)",
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
                'label' => 'Statut',
                'value' => $status ? $status->getNom() : ''
            ],
            [
                'label' => 'Type',
                'value' => $type ? $type->getLabel() : ''
            ],
            [
                'label' => $this->translation->trans('acheminement.Transporteur'),
                'title' => 'Transporteur',
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_CARRIER_DISPATCH]
            ],
            [
                'label' => $this->translation->trans('acheminement.Numéro de tracking transporteur'),
                'title' => 'Numéro de tracking transporteur',
                'value' => $carrierTrackingNumber,
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH]
            ],
            [
                'label' => 'Demandeur',
                'value' => $requester ? $requester->getUsername() : ''
            ],
            $receiverDetails ?? [],
            [
                'label' => $this->translation->trans('acheminement.Numéro de projet'),
                'title' => 'Numéro de projet',
                'value' => $projectNumber,
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_PROJECT_NUMBER]
            ],
            [
                'label' => $this->translation->trans('acheminement.Business unit'),
                'title' => 'Business unit',
                'value' => $dispatch->getBusinessUnit() ?? '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_BUSINESS_UNIT]
            ],
            [
                'label' => $this->translation->trans('acheminement.Numéro de commande'),
                'value' => $commandNumber,
                'title' => 'Numéro de commande',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_COMMAND_NUMBER_DISPATCH]
            ],
            [
                'label' => $this->translation->trans('acheminement.Emplacement prise'),
                'value' => $locationFrom ? $locationFrom->getLabel() : '', 'title' => 'Emplacement prise',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_LOCATION_PICK]
            ],
            [
                'label' => $this->translation->trans('acheminement.Emplacement dépose'),
                'value' => $locationTo ? $locationTo->getLabel() : '', 'title' => 'Emplacement dépose',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_LOCATION_DROP]
            ],
            [
                'label' => 'Date de création',
                'value' => FormatHelper::datetime($creationDate, "", false, $this->security->getUser())
            ],
            [
                'label' => 'Date de validation',
                'value' => FormatHelper::datetime($validationDate, "", false, $this->security->getUser())
            ],
            [
                'label' => 'Dates d\'échéance',
                'value' => ($startDate || $endDate) ? ('Du ' . $startDateStr . ' au ' . $endDateStr) : ''
            ],
            [
                'label' => 'Traité par',
                'value' => $treatedBy
            ],
            [
                'label' => 'Date de traitement',
                'value' => FormatHelper::datetime($treatmentDate, "", false, $this->security->getUser())
            ],
            [
                'label' => 'Destination',
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
                'label' => 'Commentaire',
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
                'label' => 'Pièces jointes',
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
        $status = $dispatch->getStatut();
        $recipientAbleToReceivedMail = $status ? $status->getSendNotifToRecipient() : false;
        $requesterAbleToReceivedMail = $status ? $status->getSendNotifToDeclarant() : false;

        if ($recipientAbleToReceivedMail || $requesterAbleToReceivedMail) {
            $type = $dispatch->getType() ? $dispatch->getType()->getLabel() : '';

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
                ? 'acheminement.Acheminement {numéro} traité partiellement le {date}'
                : 'acheminement.Acheminement {numéro} traité le {date}';

            $translatedCategory = $this->translation->trans('acheminement.demande d\'acheminement');
            $title = $status->isTreated()
                ? $this->translation->trans($translatedTitle, [
                    "{numéro}" => $dispatch->getNumber(),
                    "{date}" => FormatHelper::datetime($dispatch->getTreatmentDate(), "", false, $this->security->getUser())
                ])
                : (!$isUpdate
                    ? ('Un(e) ' . $translatedCategory . ' de type ' . $type . ' vous concerne :')
                    : ('Changement de statut d\'un(e) ' . $translatedCategory . ' de type ' . $type . ' vous concernant :'));
            $subject = ($status->isTreated() || $status->isPartial())
                ? ('FOLLOW GT // Notification de traitement d\'une ' . $this->translation->trans('acheminement.demande d\'acheminement') . '.')
                : (!$isUpdate
                    ? ('FOLLOW GT // Création d\'un(e) ' . $translatedCategory)
                    : 'FOLLOW GT // Changement de statut d\'un(e) ' . $translatedCategory . '.');

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
                    $this->templating->render('mails/contents/mailDispatch.html.twig', [
                        'dispatch' => $dispatch,
                        'title' => $title,
                        'urlSuffix' => $this->router->generate("dispatch_show", ["id" => $dispatch->getId()]),
                        'hideNumber' => $isTreatedStatus,
                        'hideTreatmentDate' => $isTreatedStatus,
                        'hideTreatedBy' => $isTreatedByOperator,
                        'totalCost' => $freeFieldArray
                    ]),
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
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $columnsVisible = $currentUser->getVisibleColumns()['dispatch'];
        $categorieCL = $categorieCLRepository->findOneBy(['label' => CategorieCL::DEMANDE_DISPATCH]);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::DEMANDE_DISPATCH, $categorieCL);

        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => 'Numéro demande', 'name' => 'number'],
            ['title' => 'acheminement.Transporteur', 'name' => 'carrier', 'translated' => true],
            ['title' => 'acheminement.Numéro de tracking transporteur', 'name' => 'carrierTrackingNumber', 'translated' => true],
            ['title' => 'acheminement.Numéro de commande', 'name' => 'commandNumber', 'translated' => true],
            ['title' => 'Date de création', 'name' => 'creationDate'],
            ['title' => 'Date de validation', 'name' => 'validationDate'],
            ['title' => 'Date de traitement', 'name' => 'treatmentDate'],
            ['title' => 'Date d\'échéance', 'name' => 'endDate'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Demandeur', 'name' => 'requester'],
            ['title' => 'Destinataires', 'name' => 'receivers','orderable' => false],
            ['title' => 'acheminement.Emplacement prise', 'name' => 'locationFrom', 'translated' => true],
            ['title' => 'acheminement.Emplacement dépose', 'name' => 'locationTo', 'translated' => true],
            ['title' => 'acheminement.Destination', 'name' => 'destination', 'translated' => true],
            ['title' => 'acheminement.Nb colis', 'name' => 'nbPacks', 'translated' => true, 'orderable' => false],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Urgence', 'name' => 'emergency'],
            ['title' => 'Traité par', 'name' => 'treatedBy'],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function parseRequestForCard(Dispatch $dispatch,
                                        DateService $dateService,
                                        array $averageRequestTimesByType): array {

        $requestStatus = $dispatch->getStatut() ? $dispatch->getStatut()->getNom() : '';
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

        $bodyTitle = $dispatch->getDispatchPacks()->count() . ' colis' . ' - ' . $requestType;
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
            $nature = FormatHelper::nature($pack->getNature());
            $weight = $pack->getWeight();
            $volume = $pack->getVolume();
            $comment = $pack->getComment();
            $lastMvtDate = $lastTracking && $lastTracking->getDatetime() ? $lastTracking->getDatetime()->format("Y-m-d H:i") : null;
            $lastLocation = $lastTracking ? FormatHelper::location($lastTracking->getEmplacement()) : null;
            $operator = $lastTracking ? FormatHelper::user($lastTracking->getOperateur()) : null;
            $status = $dispatchPack->isTreated() ? "Traité" : "À traiter";
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

            $natureOptions = Stream::from($this->natures)
                ->map(fn(Nature $n) => [
                    "id" => $n->getId(),
                    "label" => $n->getLabel(),
                    "selected" => ($n->getLabel() === $nature || (!$nature && $this->defaultNature === $n)) ? "selected" : "",
                ])
                ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"])
                ->map(fn(array $n) => "<option value='{$n["id"]}' {$n["selected"]}>{$n["label"]}</option>")
                ->prepend(!$nature && !$this->defaultNature ? "<option disabled selected>Sélectionnez une nature</option>" : null)
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
            $comment = $pack['packComment'];
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


}
