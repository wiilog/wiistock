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
use App\Entity\ParametrageGlobal;
use App\Entity\TrackingMovement;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\FreeFieldRepository;
use App\Repository\FieldsParamRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\StatutRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class DispatchService {

    const WAYBILL_MAX_PACK = 20;

    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Utilisateur
     */
    private $user;

    private $entityManager;
    private $freeFieldService;
    private $translator;
    private $mailerService;
    private $trackingMovementService;
    private $fieldsParamService;
    private $visibleColumnService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                FreeFieldService $champLibreService,
                                TranslatorInterface $translator,
                                TrackingMovementService $trackingMovementService,
                                MailerService $mailerService,
                                VisibleColumnService $visibleColumnService,
                                FieldsParamService $fieldsParamService) {
        $this->templating = $templating;
        $this->trackingMovementService = $trackingMovementService;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->freeFieldService = $champLibreService;
        $this->translator = $translator;
        $this->mailerService = $mailerService;
        $this->fieldsParamService = $fieldsParamService;
        $this->visibleColumnService = $visibleColumnService;
    }

    public function getDataForDatatable($params = null) {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $dispatchRepository = $this->entityManager->getRepository(Dispatch::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DISPATCH, $this->user);

        $queryResult = $dispatchRepository->findByParamAndFilters($params, $filters);

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

        $categoryFFRepository = $this->entityManager->getRepository(CategorieCL::class);
        $freeFieldsRepository = $this->entityManager->getRepository(FreeField::class);

        $categoryFF = $categoryFFRepository->findOneBy(['label' => CategorieCL::DEMANDE_DISPATCH]);
        $category = CategoryType::DEMANDE_DISPATCH;
        $freeFields = $freeFieldsRepository->getByCategoryTypeAndCategoryCL($category, $categoryFF);
        $receivers = $dispatch->getReceivers() ?? null;
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
            'creationDate' => $dispatch->getCreationDate() ? $dispatch->getCreationDate()->format('d/m/Y H:i:s') : '',
            'validationDate' => $dispatch->getValidationDate() ? $dispatch->getValidationDate()->format('d/m/Y H:i:s') : '',
            'endDate' => $dispatch->getEndDate() ? $dispatch->getEndDate()->format('d/m/Y') : '',
            'requester' => $dispatch->getRequester() ? $dispatch->getRequester()->getUserName() : '',
            'receivers' => $receiversUsernames ?? '',
            'locationFrom' => $dispatch->getLocationFrom() ? $dispatch->getLocationFrom()->getLabel() : '',
            'locationTo' => $dispatch->getLocationTo() ? $dispatch->getLocationTo()->getLabel() : '',
            'nbPacks' => $dispatch->getDispatchPacks()->count(),
            'type' => $dispatch->getType() ? $dispatch->getType()->getLabel() : '',
            'status' => $dispatch->getStatut() ? $dispatch->getStatut()->getNom() : '',
            'emergency' => $dispatch->getEmergency() ?? '',
            'treatedBy' => $dispatch->getTreatedBy() ? $dispatch->getTreatedBy()->getUsername() : '',
            'treatmentDate' => $dispatch->getTreatmentDate() ? $dispatch->getTreatmentDate()->format('d/m/Y H:i:s') : '',
            'actions' => $this->templating->render('dispatch/datatableDispatchRow.html.twig', [
                'dispatch' => $dispatch,
                'url' => $url
            ]),
        ];

        foreach ($freeFields as $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeField['id']);
            $row[$freeFieldName] = $this->freeFieldService->serializeValue([
                "valeur" => $dispatch->getFreeFieldValue($freeField["id"]),
                "typage" => $freeField["typage"],
            ]);
        }

        return $row;
    }

    public function getNewDispatchConfig(StatutRepository $statutRepository,
                                         FreeFieldRepository $champLibreRepository,
                                         FieldsParamRepository $fieldsParamRepository,
                                         ParametrageGlobalRepository $parametrageGlobalRepository,
                                         array $types,
                                         ?Arrivage $arrival = null) {
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

        $dispatchBusinessUnits = $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_BUSINESS_UNIT);
        return [
            'dispatchBusinessUnits' => !empty($dispatchBusinessUnits) ? $dispatchBusinessUnits : [],
            'fieldsParam' => $fieldsParam,
            'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY),
            'preFill' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::PREFILL_DUE_DATE_TODAY),
            'typeChampsLibres' => array_map(function(Type $type) use ($champLibreRepository) {
                $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_DISPATCH);
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
            'notTreatedStatus' => $statutRepository->findStatusByType(CategorieStatut::DISPATCH, null, [Statut::DRAFT]),
            'packs' => $arrival ? $arrival->getPacks() : [],
            'fromArrival' => $arrival !== null,
            'arrival' => $arrival
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
        $validationDate = $dispatch->getValidationDate() ? $dispatch->getValidationDate() : '';
        $treatmentDate = $dispatch->getTreatmentDate() ? $dispatch->getTreatmentDate() : '';
        $startDate = $dispatch->getStartDate();
        $endDate = $dispatch->getEndDate();
        $startDateStr = $startDate ? $startDate->format('d/m/Y') : '-';
        $endDateStr = $endDate ? $endDate->format('d/m/Y') : '-';
        $projectNumber = $dispatch->getProjectNumber();
        $comment = $dispatch->getCommentaire() ?? '';
        $treatedBy = $dispatch->getTreatedBy() ? $dispatch->getTreatedBy()->getUsername() : '';
        $attachments = $dispatch->getAttachments();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $dispatch,
            CategorieCL::DEMANDE_DISPATCH,
            CategoryType::DEMANDE_DISPATCH
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
                'label' => $this->translator->trans('acheminement.Transporteur'),
                'title' => 'Transporteur',
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_CARRIER_DISPATCH]
            ],
            [
                'label' => $this->translator->trans('acheminement.Numéro de tracking transporteur'),
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
                'label' => $this->translator->trans('acheminement.Numéro de projet'),
                'title' => 'Numéro de projet',
                'value' => $projectNumber,
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_PROJECT_NUMBER]
            ],
            [
                'label' => $this->translator->trans('acheminement.Business unit'),
                'title' => 'Business unit',
                'value' => $dispatch->getBusinessUnit() ?? '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_BUSINESS_UNIT]
            ],
            [
                'label' => $this->translator->trans('acheminement.Numéro de commande'),
                'value' => $commandNumber,
                'title' => 'Numéro de commande',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_COMMAND_NUMBER_DISPATCH]
            ],
            [
                'label' => $this->translator->trans('acheminement.Emplacement prise'),
                'value' => $locationFrom ? $locationFrom->getLabel() : '', 'title' => 'Emplacement prise',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_LOCATION_PICK]
            ],
            [
                'label' => $this->translator->trans('acheminement.Emplacement dépose'),
                'value' => $locationTo ? $locationTo->getLabel() : '', 'title' => 'Emplacement dépose',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_LOCATION_DROP]
            ],
            [
                'label' => 'Date de création',
                'value' => $creationDate ? $creationDate->format('d/m/Y H:i:s') : ''
            ],
            [
                'label' => 'Date de validation',
                'value' => $validationDate ? $validationDate->format('d/m/Y H:i:s') : ''
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
                'value' => $treatmentDate ? $treatmentDate->format('d/m/Y H:i:s') : ''
            ]
        ];
        $configFiltered = $this->fieldsParamService->filterHeaderConfig($config, FieldsParam::ENTITY_CODE_DISPATCH);
        return array_merge(
            $configFiltered,
            $freeFieldArray,
            ($this->fieldsParamService->isFieldRequired($fieldsParam, 'comment', 'displayedFormsCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, 'comment', 'displayedFormsEdit'))
                ? [[
                'label' => 'Commentaire',
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]]
                : [],
            ($this->fieldsParamService->isFieldRequired($fieldsParam, 'attachments', 'displayedFormsCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, 'attachments', 'displayedFormsEdit'))
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
        $date = null;
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = (!empty($dateStr) && empty($date))
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

            $translatedCategory = $this->translator->trans('acheminement.demande d\'acheminement');
            $title = $status->isTreated()
                ? $this->translator->trans($translatedTitle, [
                    "{numéro}" => $dispatch->getNumber(),
                    "{date}" => $dispatch->getTreatmentDate() ? $dispatch->getTreatmentDate()->format('d/m/Y à H:i:s') : ''
                ])
                : (!$isUpdate
                    ? ('Un(e) ' . $translatedCategory . ' de type ' . $type . ' vous concerne :')
                    : ('Changement de statut d\'un(e) ' . $translatedCategory . ' de type ' . $type . ' vous concernant :'));
            $subject = ($status->isTreated() || $status->isPartial())
                ? ('FOLLOW GT // Notification de traitement d\'une ' . $this->translator->trans('acheminement.demande d\'acheminement') . '.')
                : (!$isUpdate
                    ? ('FOLLOW GT // Création d\'un(e) ' . $translatedCategory)
                    : 'FOLLOW GT // Changement de statut d\'un(e) ' . $translatedCategory . '.');

            $isTreatedStatus = $dispatch->getStatut() && $dispatch->getStatut()->isTreated();
            $isTreatedByOperator = $dispatch->getTreatedBy() && $dispatch->getTreatedBy()->getUsername();

            $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
                $this->entityManager,
                $dispatch,
                CategorieCL::DEMANDE_DISPATCH,
                CategoryType::DEMANDE_DISPATCH
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
                    ['quantity' => $dispatchPack->getQuantity(), 'from' => $dispatch]
                );

                $trackingDrop = $this->trackingMovementService->createTrackingMovement(
                    $pack,
                    $dropLocation,
                    $loggedUser,
                    $date,
                    $fromNomade,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    ['quantity' => $dispatchPack->getQuantity(), 'from' => $dispatch]
                );

                $entityManager->persist($trackingTaking);
                $entityManager->persist($trackingDrop);

                $dispatchPack->setTreated(true);
            }
        }
        $entityManager->flush();

        $this->sendEmailsAccordingToStatus($dispatch, true);
    }

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $columnsVisible = $currentUser->getColumnsVisibleForDispatch();
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
            'cardColor' => $requestState === Statut::DRAFT ? 'lightGrey' : 'white',
            'bodyColor' => $requestState === Statut::DRAFT ? 'white' : 'lightGrey',
            'topRightIcon' => 'livreur.svg',
            'emergencyText' => '',
            'progress' => $statusesToProgress[$requestState] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'progressBarBGColor' => $requestState === Statut::DRAFT ? 'white' : 'lightGrey',
        ];
    }

}
