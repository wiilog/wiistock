<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Handling;
use App\Entity\Language;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Type\CategoryType;
use App\Entity\Utilisateur;
use App\Helper\LanguageHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class HandlingService {


    public function __construct(
        private SettingsService        $settingsService,
        private Twig_Environment       $templating,
        private RouterInterface        $router,
        private UserService            $userService,
        private EntityManagerInterface $entityManager,
        private MailerService          $mailerService,
        private TranslationService     $translation,
        private FieldModesService      $fieldModesService,
        private FreeFieldService       $freeFieldService,
        private Security               $security,
        private FormatService          $formatService,
        private LanguageService        $languageService,
    ) {
    }

    public function getDataForDatatable(EntityManagerInterface $entityManager, Request $request): array
    {
        $handlingRepository = $entityManager->getRepository(Handling::class);

        $includeDesiredTime = !$this->settingsService->getValue($entityManager, Setting::REMOVE_HOURS_DATETIME);

        $user = $this->userService->getUser();

        $filterType = $request->request->all('filterType');
        $fromDashboard = $request->request->getBoolean('fromDashboard');
        $filterStatus = $request->request->all('filterStatus');
        $selectedDate = $request->request->get('selectedDate');

        if(!$fromDashboard) {
            $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_HAND, $user);
        } else {
            $preFilledStatuses = $filterStatus
                ? implode(",", $filterStatus)
                : [];
            $preFilledType = $filterType
                ? implode(",", $filterType)
                : [];

            $preFilledFilters = [
                [
                    'field' => 'statuses-filter',
                    'value' => $preFilledStatuses,
                ],
                [
                    'field' => FiltreSup::FIELD_MULTIPLE_TYPES,
                    'value' => $preFilledType,
                ],
            ];

            $filters = $preFilledFilters;
        }

        $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug());
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
        $language = $this->security->getUser()->getLanguage() ?: $defaultLanguage;
        $queryResult = $handlingRepository->findByParamAndFilters($request->request, $filters, $user, $selectedDate, [
            'defaultLanguage' => $defaultLanguage,
            'language' => $language
        ]);

        $handlingArray = $queryResult['data'];

        $rows = [];
        foreach ($handlingArray as $handling) {
            $rows[] = $this->dataRowHandling($handling, $includeDesiredTime, $user);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowHandling(Handling $handling,
                                    bool $includeDesiredTime,
                                    Utilisateur $user): array {

        $userLanguage = $user->getLanguage();
        $defaultSlug = $this->languageService->getDefaultSlug();

        $row = [
            'id' => $handling->getId() ?: 'Non défini',
            'number' => $handling->getNumber() ?: '',
            'comment' => $handling->getComment() ?: '',
            'creationDate' => $this->formatService->datetime($handling->getCreationDate(), "", false, $this->security->getUser()),
            'type' => $this->formatService->type($handling->getType()),
            'requester' => $this->formatService->handlingRequester($handling),
            'subject' => $handling->getSubject() ?: '',
            "receivers" => $this->formatService->users($handling->getReceivers()->toArray()),
            'desiredDate' => $includeDesiredTime
                ? $this->formatService->datetime($handling->getDesiredDate(), "", false, $user)
                : $this->formatService->date($handling->getDesiredDate(), "", $user),
            'validationDate' => $this->formatService->datetime($handling->getValidationDate(), "", false, $user),
            'status' => $this->formatService->status($handling->getStatus()),
            'emergency' => $handling->getEmergency() ?? '',
            'treatedBy' => $handling->getTreatedByHandling() ? $this->formatService->user($handling->getTreatedByHandling()) : '',
            //'treatmentDelay' => $treatmentDelayStr,
            'carriedOutOperationCount' => is_int($handling->getCarriedOutOperationCount()) ? $handling->getCarriedOutOperationCount() : '',
            'actions' => $this->templating->render('handling/datatableHandlingRow.html.twig', [
                'handling' => $handling
            ]),
        ];

        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategorieCL::DEMANDE_HANDLING, CategoryType::DEMANDE_HANDLING);
        }

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->fieldModesService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $handling->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField, $user);
        }

        return $row;
    }

    public function sendEmailsAccordingToStatus(EntityManagerInterface $entityManager,
                                                Handling               $handling,
                                                bool                   $viewHoursOnExpectedDate = false,
                                                bool                   $isNewHandlingAndNotTreated = false): void {
        $status = $handling->getStatus();
        $requester = $status->getSendNotifToDeclarant() ? $handling->getRequester() : null;
        $receivers = $status->getSendNotifToRecipient() ? $handling->getReceivers() : [];

        $emailReceivers = Stream::from($receivers, [$requester])
            ->unique()
            ->toArray();

        if (!empty($emailReceivers)) {
            $statusTreated = $status->isTreated();
            if ($isNewHandlingAndNotTreated) {
                $subject = ['Demande', 'Services', 'Emails', 'Création d\'une demande de service', false];
                $title = ['Demande', 'Services', 'Emails', 'Votre demande de service a été créée', false];
            } else {
                $subject = $statusTreated
                    ? ['Demande', 'Services', 'Emails', 'Demande de service effectuée', false]
                    : ['Demande', 'Services', 'Email', 'Changement de statut d\'une demande de service', false];
                $title = $statusTreated
                    ? ['Demande', 'Services', 'Emails', 'Votre demande de service a bien été effectuée', false]
                    : ['Demande', 'Services', 'Emails', 'Une demande de service vous concernant a changé de statut', false];
            }

            $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
            $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_HANDLING);

            $this->mailerService->sendMail(
                $entityManager,
                $subject,
                [
                    'name' => 'mails/contents/mailHandlingTreated.html.twig',
                    'context' => [
                        'handling' => $handling,
                        'title' => $title,
                        'fieldsParam' => $fieldsParam,
                        'viewHoursOnExpectedDate' => $viewHoursOnExpectedDate,
                    ],
                ],
                $emailReceivers
            );
        }
    }

    public function parseRequestForCard(Handling $handling, DateTimeService $dateTimeService, array $averageRequestTimesByType): array {
        $requestStatus = $handling->getStatus()?->getCode();
        $requestBodyTitle = !empty($handling->getSubject())
            ? $handling->getSubject() . (!empty($handling->getType())
                ? ' - ' . $handling->getType()->getLabel()
                : '')
            : '';
        $state = $handling->getStatus()?->getState();

        $href = $this->router->generate('handling_show', [
            "id" => $handling->getId(),
        ]);

        $typeId = $handling->getType()?->getId();
        $averageTime = $averageRequestTimesByType[$typeId] ?? null;

        $deliveryDateEstimated = 'Non estimée';
        $estimatedFinishTimeLabel = 'Date de traitement non estimée';

        if (isset($averageTime)) {
            $today = new DateTime();
            $expectedDate = (clone $handling->getCreationDate())
                ->add($dateTimeService->secondsToDateInterval($averageTime->getAverage()));
            if ($expectedDate >= $today) {
                $estimatedFinishTimeLabel = 'Date et heure de traitement prévue';
                $deliveryDateEstimated = $expectedDate->format('d/m/Y H:i');
                if ($expectedDate->format('d/m/Y') === $today->format('d/m/Y')) {
                    $estimatedFinishTimeLabel = 'Heure de traitement estimée';
                    $deliveryDateEstimated = $expectedDate->format('H:i');
                } else {

                }
            }
        }

        $requestDate = $handling->getCreationDate();
        $requestDateStr = $requestDate
            ? (
                $requestDate->format('d ')
                . DateTimeService::ENG_TO_FR_MONTHS[$requestDate->format('M')]
                . $requestDate->format(' (H\hi)')
            )
            : 'Non défini';

        $statusesToProgress = [
            Statut::DRAFT => 0,
            Statut::NOT_TREATED => 50,
            Statut::TREATED => 100,
        ];

        return [
            'href' => $href ?? null,
            'errorMessage' => 'Vous n\'avez pas les droits d\'accéder à la page d\'état actuel de la ' . mb_strtolower($this->translation->translate("Demande", "Livraison", "Demande de livraison", false)),
            'estimatedFinishTime' => $deliveryDateEstimated,
            'estimatedFinishTimeLabel' => $estimatedFinishTimeLabel,
            'requestStatus' => $requestStatus,
            'requestBodyTitle' => $requestBodyTitle,
            'requestLocation' => $handling->getDestination() ?: 'Non défini',
            'requestNumber' => $handling->getNumber(),
            'requestDate' => $requestDateStr,
            'requestUser' => $handling->getRequester() ? $handling->getRequester()->getUsername() : 'Non défini',
            'cardColor' => 'lightest-grey',
            'bodyColor' => 'light-grey',
            'topRightIcon' => $handling->getEmergency() ? '' : 'livreur.svg',
            'emergencyText' => $handling->getEmergency() ?? '',
            'progress' => $statusesToProgress[$state] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'progressBarBGColor' => 'light-grey',
        ];
    }

    public function getColumnVisibleConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $columnsVisible = $currentUser->getFieldModes('handling');
        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::DEMANDE_HANDLING,
            CategorieCL::DEMANDE_HANDLING);

        $columns = [
            ['title' => $this->translation->translate('Demande', 'Services', 'Zone liste - Nom de colonnes', 'Numéro de demande'), 'name' => 'number',],
            ['title' => $this->translation->translate('Demande', 'Services', 'Zone liste - Nom de colonnes', 'Date demande'), 'name' => 'creationDate',],
            ['title' => $this->translation->translate('Demande', 'Général', 'Type'), 'name' => 'type'],
            ['title' => $this->translation->translate('Demande', 'Général', 'Demandeur'), 'name' => 'requester'],
            ['title' => $this->translation->translate('Demande', 'Services', 'Zone liste - Nom de colonnes', 'Objet'), 'name' => 'subject',],
            ['title' => $this->translation->translate('Demande', 'Services', 'Modale et détails', 'Date attendue'), 'name' => 'desiredDate',],
            ['title' => $this->translation->translate('Demande', 'Services', 'Zone liste - Nom de colonnes', 'Date de réalisation'), 'name' => 'validationDate',],
            ['title' => $this->translation->translate('Demande', 'Général', 'Statut'), 'name' => 'status'],
            ['title' => $this->translation->translate('Demande', 'Général', 'Urgent'), 'name' => 'emergency'],
            ['title' => $this->translation->translate('Demande', 'Services', 'Modale et détails', 'Nombre d\'opération(s) réalisée(s)'), 'name' => 'carriedOutOperationCount',],
            ['title' => $this->translation->translate('Général', null, 'Zone liste', 'Traité par'), 'name' => 'treatedBy',],
            ['title' => $this->translation->translate('Général', null, 'Modale', 'Commentaire'), 'name' => 'comment'],
        ];

        return $this->fieldModesService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function putHandlingLine(EntityManagerInterface $entityManager,
                                    CSVExportService       $CSVExportService,
                                                           $output,
                                    Handling               $handling,
                                    FormatService          $formatService,
                                    array                  $freeFieldsConfig): void {
        $includeDesiredTime = !$this->settingsService->getValue($entityManager, Setting::REMOVE_HOURS_DATETIME);
        $user = $this->userService->getUser();
        $receiversStr = Stream::from($handling->getReceivers())
            ->map(fn(Utilisateur $receiver) => $formatService->user($receiver))
            ->join(", ");
        $row = [];
        $row[] = $handling->getNumber() ?? "";
        $row[] = $handling->getCreationDate() ? $formatService->datetime($handling->getCreationDate()) : "";
        $row[] = $formatService->handlingRequester($handling);
        $row[] = $formatService->type($handling->getType());
        $row[] = $handling->getSubject() ??"";
        $row[] = $handling->getSource() ?? "";
        $row[] = $handling->getDestination() ?? "";
        $row[] = $includeDesiredTime
            ? $formatService->datetime($handling->getDesiredDate())
            : $formatService->date($handling->getDesiredDate());
        $row[] = $handling->getValidationDate() ? $formatService->datetime($handling->getValidationDate()) : "";
        $row[] = $handling->getStatus() ? $formatService->status($handling->getStatus()) : "";
        $row[] = $handling->getComment() ? $formatService->html($handling->getComment()) : "";
        $row[] = $handling->getEmergency() ?? "";
        $row[] = $handling->getCarriedOutOperationCount() ?? "";
        $row[] = $handling->getTreatedByHandling() ? $formatService->user($handling->getTreatedByHandling()) : "";
        $row[] = $receiversStr ?? "";

        $handlingFreeFields = $handling->getFreeFields();
        foreach ($freeFieldsConfig['freeFields'] as $freeFieldId => $field) {
            $row[] = $formatService->freeField($handlingFreeFields[$freeFieldId] ?? "", $field, $user);

        }
        $CSVExportService->putLine($output, $row);
    }
}
