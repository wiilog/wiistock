<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Handling;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use DateTime;
use App\Service\TranslationService;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class HandlingService {

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public UserService $userService;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public TokenStorageInterface $tokenStorage;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public Security $security;

    #[Required]
    public FormatService $formatService;

    public function getDataForDatatable($params = null, $statusFilter = null, $selectedDate = null): array
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $handlingRepository = $this->entityManager->getRepository(Handling::class);
        $settingRepository = $this->entityManager->getRepository(Setting::class);

        $includeDesiredTime = !$settingRepository->getOneParamByLabel(Setting::REMOVE_HOURS_DATETIME);

        if ($statusFilter) {
            $filters = [
                [
                    'field' => 'statut',
                    'value' => $statusFilter
                ]
            ];
        } else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_HAND, $this->tokenStorage->getToken()->getUser());
        }

        $queryResult = $handlingRepository->findByParamAndFilters($params, $filters, $selectedDate);

        $handlingArray = $queryResult['data'];

        $rows = [];
        foreach ($handlingArray as $handling) {
            $rows[] = $this->dataRowHandling($handling, $includeDesiredTime);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowHandling(Handling $handling, bool $includeDesiredTime = true): array {
        $row = [
            'id' => $handling->getId() ?: 'Non défini',
            'number' => $handling->getNumber() ?: '',
            'comment' => $handling->getComment() ?: '',
            'creationDate' => FormatHelper::datetime($handling->getCreationDate(), "", false, $this->security->getUser()),
            'type' => $handling->getType() ? $handling->getType()->getLabel() : '',
            'requester' => FormatHelper::handlingRequester($handling),
            'subject' => $handling->getSubject() ?: '',
            "receivers" => FormatHelper::users($handling->getReceivers()->toArray()),
            'desiredDate' => $includeDesiredTime
                ? FormatHelper::datetime($handling->getDesiredDate(), "", false, $this->security->getUser())
                : FormatHelper::date($handling->getDesiredDate(), "", false, $this->security->getUser()),
            'validationDate' => FormatHelper::datetime($handling->getValidationDate(), "", false, $this->security->getUser()),
            'status' => $this->formatService->status($handling->getStatus()) ? $handling->getStatus()->getNom() : null,
            'emergency' => $handling->getEmergency() ?? '',
            'treatedBy' => $handling->getTreatedByHandling() ? FormatHelper::user($handling->getTreatedByHandling()) : '',
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
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $handling->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = FormatHelper::freeField($freeFieldValue, $freeField, $this->security->getUser());
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
                $subject = $this->translation->trans('services.Création d\'une demande de service');
                $title = $this->translation->trans('services.Votre demande de service a été créée') . '.';
            } else {
                $subject = $statusTreated
                    ? $this->translation->trans('services.Demande de service effectuée')
                    : $this->translation->trans('services.Changement de statut d\'une demande de service');
                $title = $statusTreated
                    ? $this->translation->trans('services.Votre demande de service a bien été effectuée') . '.'
                    : $this->translation->trans('services.Une demande de service vous concernant a changé de statut') . '.';
            }

            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);

            $this->mailerService->sendMail(
                'FOLLOW GT // ' . $subject,
                $this->templating->render('mails/contents/mailHandlingTreated.html.twig', [
                    'handling' => $handling,
                    'title' => $title,
                    'fieldsParam' => $fieldsParam,
                    'viewHoursOnExpectedDate' => $viewHoursOnExpectedDate
                ]),
                $emailReceivers
            );
        }
    }

    public function parseRequestForCard(Handling $handling, DateService $dateService, array $averageRequestTimesByType): array {
        $requestStatus = $handling->getStatus() ? $this->formatService->status($handling->getStatus()) : '';
        $requestBodyTitle = !empty($handling->getSubject())
            ? $handling->getSubject() . (!empty($handling->getType())
                ? ' - ' . $handling->getType()->getLabel()
                : '')
            : '';
        $state = $handling->getStatus()?->getState();

        $href = $this->router->generate('handling_show', [
            "id" => $handling->getId()
        ]);

        $typeId = $handling->getType()?->getId();
        $averageTime = $averageRequestTimesByType[$typeId] ?? null;

        $deliveryDateEstimated = 'Non estimée';
        $estimatedFinishTimeLabel = 'Date de traitement non estimée';

        if (isset($averageTime)) {
            $today = new DateTime();
            $expectedDate = (clone $handling->getCreationDate())
                ->add($dateService->secondsToDateInterval($averageTime->getAverage()));
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
                . DateService::ENG_TO_FR_MONTHS[$requestDate->format('M')]
                . $requestDate->format(' (H\hi)')
            )
            : 'Non défini';

        $statusesToProgress = [
            Statut::DRAFT => 0,
            Statut::NOT_TREATED => 50,
            Statut::TREATED => 100
        ];

        return [
            'href' => $href ?? null,
            'errorMessage' => 'Vous n\'avez pas les droits d\'accéder à la page d\'état actuel de la demande de livraison',
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
            'progress' =>  $statusesToProgress[$state] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'progressBarBGColor' => 'light-grey',
        ];
    }

    public function getColumnVisibleConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $columnsVisible = $currentUser->getVisibleColumns()['handling'];
        $categorieCL = $categorieCLRepository->findOneBy(['label' => CategorieCL::DEMANDE_HANDLING]);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::DEMANDE_HANDLING, $categorieCL);

        $columns = [
            ['title' => 'Numéro de demande',  'name' => 'number'],
            ['title' => 'Date demande', 'name' => 'creationDate'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Demandeur', 'name' => 'requester'],
            ['title' => 'services.Objet', 'name' => 'subject', 'translated' => true],
            ['title' => 'Date attendue', 'name' => 'desiredDate'],
            ['title' => 'Date de réalisation', 'name' => 'validationDate'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Urgent', 'name' => 'emergency'],
            ['title' => 'services.Nombre d\'opération(s) réalisée(s)', 'name' => 'carriedOutOperationCount', 'translated' => true],
            ['title' => 'Traité par', 'name' => 'treatedBy'],
            ['title' => 'Commentaire', 'name' => 'comment'],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

}
