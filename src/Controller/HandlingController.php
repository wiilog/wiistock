<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Handling;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Setting;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\HandlingService;
use App\Service\LanguageService;
use App\Service\NotificationService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\StatusService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route('/services')]
class HandlingController extends AbstractController {

    #[Route("/", name: "handling_index", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_HAND])]
    public function index(EntityManagerInterface $entityManager,
                          Request                $request,
                          StatusService          $statusService,
                          HandlingService        $handlingService,
                          TranslationService     $translationService,
                          SettingsService        $settingsService,
                          LanguageService        $languageService): Response {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $user = $this->getUser();
        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING], 'ASC', [
            'idsToFind' => $user->getHandlingTypeIds()
        ]);

        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_HANDLING);

        $fields = $handlingService->getColumnVisibleConfig($entityManager, $this->getUser());

        $filterStatus = $request->query->get('filter');
        $dateChoice = [
            [
                'value' => 'creationDate',
                'label' => $translationService->translate('Général', null, 'Zone liste', 'Date de création'),
            ],
            [
                'value' => 'expectedDate',
                'label' => $translationService->translate('Demande', 'Services', 'Date attendue'),
            ],
            [
                'value' => 'treatmentDate',
                'label' => $translationService->translate('Demande', 'Services', 'Zone liste - Nom de colonnes', 'Date de réalisation'),
            ],
        ];
        foreach ($dateChoice as &$choice) {
            $choice['default'] = (bool)$filtreSupRepository->findOnebyFieldAndPageAndUser('date-choice_'.$choice['value'], 'handling', $user);
        }
        if (Stream::from($dateChoice)->every(function ($choice) { return !$choice['default']; })) {
            $dateChoice[0]['default'] = true;
        }

        $filterDate = $request->query->get('date');

        $handlingStatuses = $statutRepository->findByCategorieName(Handling::CATEGORIE, 'displayOrder');
        $statuses = Stream::from($handlingStatuses)
            ->filter(fn(Statut $statut) => empty($user->getHandlingTypeIds()) || in_array($statut->getType()->getId(), $user->getHandlingTypeIds()))
            ->toArray();

        // data from request
        $query = $request->query;
        $typesFilter = $query->has('types') ? $query->all('types', '') : [];
        $statusesFilter = $query->has('statuses') ? $query->all('statuses', '') : [];
        $fromDashboard = $query->has('fromDashboard') ? $query->get('fromDashboard') : '' ;

        // case type filter selected
        if (!empty($typesFilter)) {
            $typesFilter = Stream::from($typeRepository->findBy(['id' => $typesFilter]))
                ->filterMap(fn(Type $type) => $type->getLabelIn($user->getLanguage()))
                ->toArray();
        }

        // case status filter selected
        if (!empty($statusesFilter)) {
            $filterStatus = Stream::from($statutRepository->findBy(['id' => $statusesFilter]))
                ->map(fn(Statut $status) => $status->getId())
                ->toArray();
        }

        return $this->render('handling/index.html.twig', [
            'userLanguage' => $user->getLanguage(),
            'defaultLanguage' => $languageService->getDefaultLanguage(),
            'selectedDate' => $filterDate ? DateTime::createFromFormat("Y-m-d", $filterDate) : null,
            'dateChoices' => $dateChoice,
            'types' => $types,
            'statuses' => $statuses,
            'filterStatus' => $filterStatus,
            'fieldsParam' => $fieldsParam,
            'fields' => $fields,
            'statusStateValues' => Stream::from($statusService->getStatusStatesValues())
                ->reduce(function(array $carry, $test) {
                    $carry[$test['id']] = $test['label'];
                    return $carry;
                }, []),
            'emergencies' => $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_HANDLING, FixedFieldStandard::FIELD_CODE_EMERGENCY),
            'modalNewConfig' => [
                'defaultStatuses' => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::HANDLING),
                'types' => $types,
                'handlingTypes' => Stream::from($types)
                    ->filter(static fn(Type $type) => $type->isActive())
                    ->toArray(),
                'handlingStatus' => $statutRepository->findStatusByType(CategorieStatut::HANDLING),
                'emergencies' => $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_HANDLING, FixedFieldStandard::FIELD_CODE_EMERGENCY),
                'preFill' => $settingsService->getValue($entityManager, Setting::PREFILL_SERVICE_DATE_TODAY),
            ],
            "typesFilter" => $typesFilter,
            "fromDashboard" => $fromDashboard,
		]);
    }

    #[Route("/api-columns", name: "handling_api_columns", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_HAND], mode: HasPermission::IN_JSON)]
    public function apiColumns(EntityManagerInterface $entityManager, HandlingService $handlingService): Response
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $columns = $handlingService->getColumnVisibleConfig($entityManager, $currentUser);
        return new JsonResponse($columns);
    }

    #[Route("/api", name: "handling_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_HAND], mode: HasPermission::IN_JSON)]
    public function api(Request                  $request,
                        HandlingService          $handlingService,
                        EntityManagerInterface   $entityManager): Response
    {
        $data = $handlingService->getDataForDatatable($entityManager, $request);

        return new JsonResponse($data);
    }

    #[Route("/users-by-type", name: "handling_users_by_type", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_HAND], mode: HasPermission::IN_JSON)]
    public function apiUserByType(Request $request, EntityManagerInterface $entityManager, HandlingService $handlingService): Response
    {
        $typeId = $request->request->get('id');
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $receivers = $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_HANDLING, FixedFieldStandard::FIELD_CODE_RECEIVERS_HANDLING);
        $receiversId = $receivers[$typeId] ?? [];
        $users = [];
        foreach ($receiversId as $receiverId) {
            $users[$receiverId] = $userRepository->find($receiverId)->getUsername();
        }
        return new JsonResponse($users);
    }

    #[Route("/creer", name: "handling_new", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::DEM, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(EntityManagerInterface $entityManager,
                        Request $request,
                        HandlingService $handlingService,
                        FreeFieldService $freeFieldService,
                        AttachmentService $attachmentService,
                        TranslationService $translation,
                        UniqueNumberService $uniqueNumberService,
                        NotificationService $notificationService,
                        SettingsService $settingsService,
                        StatusHistoryService $statusHistoryService): Response {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $post = $request->request;

        $handling = new Handling();
        $date = new DateTime('now');

        $status = $statutRepository->find($post->get('status'));
        $type = $typeRepository->find($post->get('type'));
        $currentUser = $this->getUser();

        if (!empty($currentUser->getHandlingTypeIds())
            && (
                !$type->isActive()
                || !in_array($type->getId(), $currentUser->getHandlingTypeIds())
            )) {
            throw new FormException("Veuillez rendre ce type actif ou le mettre dans les types de votre utilisateur avant de pouvoir l'utiliser.");
        }

        $containsHours = $post->get('desired-date') && str_contains($post->get('desired-date'), ':');

        $format = ($currentUser && $currentUser->getDateFormat() ? $currentUser->getDateFormat() : Utilisateur::DEFAULT_DATE_FORMAT) . ($containsHours ? ' H:i' : '');
        $desiredDate = $post->get('desired-date') ? DateTime::createFromFormat($format, $post->get('desired-date')) : null;

        $handlingNumber = $uniqueNumberService->create($entityManager, Handling::NUMBER_PREFIX, Handling::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

        $carriedOutOperationCount = $post->get('carriedOutOperationCount');

        $handling
            ->setNumber($handlingNumber)
            ->setCreationDate($date)
            ->setType($type)
            ->setRequester($currentUser)
            ->setSubject(substr($post->get(FixedFieldEnum::object->name), 0, 64))
            ->setSource($post->get('source') ?? '')
            ->setDestination($post->get('destination') ?? '')
            ->setStatus($status)
            ->setDesiredDate($desiredDate)
            ->setComment($post->get('comment'))
            ->setEmergency($post->get('emergency'))
            ->setCarriedOutOperationCount(is_numeric($carriedOutOperationCount) ? ((int) $carriedOutOperationCount) : null);

        $statusHistoryService->updateStatus($entityManager, $handling, $status, [
            "forceCreation" => false,
            "initiatedBy" => $currentUser,
        ]);

        if ($status && $status->isTreated()) {
            $handling->setValidationDate($date);
            $handling->setTreatedByHandling($currentUser);
        }

        $receivers = $post->get('receivers');
        if (!empty($receivers)) {
            $ids = explode("," , $receivers);

            foreach ($ids as $id) {
                $receiver = $id ? $userRepository->find($id) : null;
                if ($receiver) {
                    $handling->addReceiver($receiver);
                }
            }
        }

        $freeFieldService->manageFreeFields($handling, $post->all(), $entityManager, $currentUser);
        $attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $handling]);

        $entityManager->persist($handling);
        try {
            $entityManager->flush();
            if (($handling->getStatus()->getState() == Statut::NOT_TREATED)
                && $handling->getType()
                && (($handling->getType()->isNotificationsEnabled() && !$handling->getType()->getNotificationsEmergencies())
                    || $handling->getType()->isNotificationsEmergency($handling->getEmergency()))) {
                $notificationService->toTreat($handling);
            }
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException|ConnectException $e) {
            if ($e instanceof UniqueConstraintViolationException) {
                $message = $translation->translate('Demande', 'Services', null, 'Une autre demande de service est en cours de création, veuillez réessayer.', false);
            } else if ($e instanceof ConnectException) {
                $message = $translation->translate('Demande', 'Services', null, 'Une erreur s\'est produite lors de l`\'envoi de la notification de cette demande de service. Veuillez réessayer.');
            }
            if (!empty($message)) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => $message
                ]);
            }
        }
        $viewHoursOnExpectedDate = !$settingsService->getValue($entityManager, Setting::REMOVE_HOURS_DATETIME);
        $handlingService->sendEmailsAccordingToStatus($entityManager, $handling, $viewHoursOnExpectedDate, !$status->isTreated());

        $number = '<strong>' . $handling->getNumber() . '</strong>';
        return new JsonResponse([
            'success' => true,
            'msg' => $translation->translate('Demande', 'Services', null, 'La demande de service {1} a bien été créée.', [1 => $number], false),
        ]);
    }

    #[Route("/modifier/{id}", name: "handling_edit", options: ["expose" => true], methods: "POST")]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         Handling $handling,
                         FreeFieldService $freeFieldService,
                         TranslationService $translation,
                         AttachmentService $attachmentService): Response
    {
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $post = $request->request;
        $containsHours = $post->get('desired-date') && str_contains($post->get('desired-date'), ':');

        $user = $this->getUser();
        $format = ($user && $user->getDateFormat() ? $user->getDateFormat() : Utilisateur::DEFAULT_DATE_FORMAT) . ($containsHours ? ' H:i' : '');
        $desiredDate = $post->get('desired-date') ? DateTime::createFromFormat($format, $post->get('desired-date')) : null;


        /** @var Utilisateur $currentUser */
        $receivers = $post->get('receivers')
            ? explode(",", $post->get('receivers') ?? '')
            : [];

        $existingReceivers = $handling->getReceivers();
        /** @var Utilisateur $receiver */
        foreach($existingReceivers as $receiver) {
            $handling->removeReceiver($receiver);
        }

        foreach ($receivers as $receiverId) {
            $receiver = $receiverId ? $userRepository->find($receiverId) : null;
            if ($receiver) {
                $handling->addReceiver($receiver);
            }
        }

        $carriedOutOperationCount = $post->get('carriedOutOperationCount');
        $handling
            ->setSubject(substr($post->get(FixedFieldEnum::object->name), 0, 64))
            ->setSource($post->get('source') ?? $handling->getSource())
            ->setDestination($post->get('destination') ?? $handling->getDestination())
            ->setDesiredDate($desiredDate)
            ->setComment($post->get('comment'))
            ->setEmergency($post->get('emergency'))
            ->setCarriedOutOperationCount(
                (is_numeric($carriedOutOperationCount)
                    ? $carriedOutOperationCount
                    : (!empty($carriedOutOperationCount)
                        ? $handling->getCarriedOutOperationCount()
                        : null)
            ));


        $freeFieldService->manageFreeFields($handling, $post->all(), $entityManager, $user);

        $listAttachmentIdToKeep = $post->all('files') ?? [];

        $attachmentsToRemove = Stream::from($handling->getAttachments()->toArray())
            ->filter(static fn(Attachment $attachment) => !in_array($attachment->getId(), $listAttachmentIdToKeep))
            ->toArray();
        foreach ($attachmentsToRemove as $attachment) {
            $handling->removeAttachment($attachment);
            $entityManager->remove($attachment);
        }

        $attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $handling]);
        $entityManager->flush();

        $number = '<strong>' . $handling->getNumber() . '</strong>';
        return new JsonResponse([
            'success' => true,
            'msg' => $translation->translate('Demande', 'Services', null, 'La demande de service {1} a bien été modifiée.', [1 => $number], false),
        ]);

    }


    #[Route("/supprimer", name: "handling_delete", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           TranslationService $translation): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $handlingRepository = $entityManager->getRepository(Handling::class);
            $attachmentRepository = $entityManager->getRepository(Attachment::class);

            $handling = $handlingRepository->find($data['handling']);
            $handlingNumber = $handling->getNumber();

            if ($handling) {
                $attachments = $attachmentRepository->findBy(['handling' => $handling]);
                foreach ($attachments as $attachment) {
                    $entityManager->remove($attachment);
                }
            }
            $entityManager->flush();
            $entityManager->remove($handling);
            $entityManager->flush();

            $number = '<strong>' . $handlingNumber . '</strong>';
            return new JsonResponse([
                'success' => true,
                'msg' => $translation->translate('Demande', 'Services', null, 'La demande de service {1} a bien été supprimée.', [1 => $number], false),
                'redirect'=> $this->generateUrl('handling_index')
            ]);
        }

        throw new BadRequestHttpException();
    }

    #[Route("/csv", name: "get_handlings_csv", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::EXPORT])]
    public function getHandlingsCSV(Request                $request,
                                    TranslationService     $translation,
                                    CSVExportService       $CSVExportService,
                                    FreeFieldService       $freeFieldService,
                                    HandlingService        $handlingService,
                                    EntityManagerInterface $entityManager,
                                    FormatService          $formatService): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (!empty($dateTimeMin) && !empty($dateTimeMax)) {
            $handlingsRepository = $entityManager->getRepository(Handling::class);
            $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_HANDLING]);

            $handlingParameters = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_HANDLING);
            $receiversParameters = $handlingParameters[FixedFieldStandard::FIELD_CODE_RECEIVERS_HANDLING];

            $handlings = $handlingsRepository->iterateByDates($dateTimeMin, $dateTimeMax);

            $csvHeaderBase = [
                $translation->translate('Demande', 'Services', 'Zone liste - Nom de colonnes', 'Numéro de demande', false),
                $translation->translate('Demande', 'Services', 'Zone liste - Nom de colonnes', 'Date demande', false),
                $translation->translate('Demande', 'Général', 'Demandeur', false),
                $translation->translate('Demande', 'Général', 'Type', false),
                $translation->translate('Demande', 'Services', 'Objet', false),
                $translation->translate('Demande', 'Services', 'Modale et détails', 'Chargement', false),
                $translation->translate('Demande', 'Services', 'Modale et détails', 'Déchargement', false),
                $translation->translate('Demande', 'Services', 'Modale et détails', 'Date attendue', false),
                $translation->translate('Demande', 'Services', 'Zone liste - Nom de colonnes', 'Date de réalisation', false),
                $translation->translate('Demande', 'Général', 'Statut', false),
                $translation->translate('Général', null, 'Modale', 'Commentaire', false),
                $translation->translate('Demande', 'Général', 'Urgent', false),
                $translation->translate('Demande', 'Services', 'Modale et détails', "Nombre d'opération(s) réalisée(s)", false),
                $translation->translate('Général', null, 'Zone liste', 'Traité par', false),
            ];

            $csvHeader = array_merge(
                $csvHeaderBase,
                ($receiversParameters['displayedCreate'] ?? false
                    || $receiversParameters['displayedEdit'] ?? false
                    || $receiversParameters['displayedFilters'] ?? false)
                    ? ['destinataire(s)']
                    : [],
                $freeFieldsConfig['freeFieldsHeader']
            );
            $today = new DateTime();
            $today = $today->format("d-m-Y-H:i:s");
            $globalTitle = 'export-services-' . $today . '.csv';

            return $CSVExportService->streamResponse(function($output) use (
                $freeFieldsConfig,
                $handlings,
                $handlingsRepository,
                $entityManager,
                $dateTimeMin,
                $dateTimeMax,
                $CSVExportService,
                $handlingService,
                $formatService
            ) {
                foreach ($handlings as $handling) {
                    $handlingService->putHandlingLine($entityManager, $CSVExportService, $output, $handling, $formatService, $freeFieldsConfig);
                }
            },
                $globalTitle,
                $csvHeader
            );
        }
        else {
            throw new BadRequestHttpException();
        }
    }

    #[Route("/voir/{id}", name: "handling_show", options: ["expose" => true], methods: ["GET","POST"])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_HAND])]
    public function show(Handling $handling, EntityManagerInterface $entityManager, UserService $userService): Response {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $freeFields = $freeFieldRepository->findByType($handling->getType());
        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_HANDLING);

        $hasRightToTreadHandling = $userService->hasRightFunction(Menu::DEM, Action::TREAT_HANDLING);
        $currentStatus = $handling->getStatus();
        $statuses = Stream::from($statutRepository->findStatusByType(CategorieStatut::HANDLING, $handling->getType()))
            ->filterMap(fn(Statut $status) => (
                $hasRightToTreadHandling || $status->isNotTreated()
                    ? [
                        "label" => $this->getFormatter()->status($status),
                        "value" => $status->getId(),
                    ]
                    : null
            ))
            ->toArray();

        return $this->render('handling/show.html.twig', [
            'handling' => $handling,
            'freeFields' => $freeFields,
            'fieldsParam' => $fieldsParam,
            'statuses' => $statuses,
            'currentStatus' => $currentStatus,
        ]);
    }

    #[Route("/{id}/status-history-api", name: "handling_status_history_api", options: ['expose' => true], methods: "GET")]
    public function statusHistoryApi(int $id,
                                     EntityManagerInterface $entityManager,
                                     LanguageService $languageService): JsonResponse {
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $handling = $handlingRepository->find($id);
        $user = $this->getUser();
        return $this->json([
            "success" => true,
            "template" => $this->renderView('handling/status-history.html.twig', [
                "userLanguage" => $user->getLanguage(),
                "defaultLanguage" => $languageService->getDefaultLanguage(),
                "statusesHistory" => Stream::from($handling->getStatusHistory())
                    ->map(fn(StatusHistory $statusHistory) => [
                        "status" => $this->getFormatter()->status($statusHistory->getStatus()),
                        "date" => $languageService->getCurrentUserLanguageSlug() === Language::FRENCH_SLUG
                                    ? $this->getFormatter()->longDate($statusHistory->getDate(), ["short" => true, "time" => true])
                                    : $this->getFormatter()->datetime($statusHistory->getDate(), "", false, $user),
                    ])
                    ->toArray(),
                "handling" => $handling,
            ]),
        ]);
    }

    #[Route("/modifier-page/{id}", name: "handling_edit_page", options: ["expose" => true], methods: ["GET", "POST"])]
    #[HasPermission([Menu::DEM, Action::EDIT])]
    public function editHandling(Handling               $handling,
                                 EntityManagerInterface $entityManager): Response {
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        $emergencies = Stream::from($fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_HANDLING, FixedFieldStandard::FIELD_CODE_EMERGENCY))
            ->map(fn($emergency) => [
                "label" => $emergency,
                "value" => $emergency,
                "selected" => $emergency === $handling->getEmergency(),
            ])
            ->toArray();
        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_HANDLING);
        $receivers = Stream::from($handling->getReceivers())
            ->map(fn($receiver) => [
                "label" => $receiver->getUsername(),
                "value" => $receiver->getId(),
                "selected" => true
            ])
            ->toArray();

        return $this->render('handling/editHandling.html.twig', [
            'handling' => $handling,
            'submit_url' => $this->generateUrl('handling_edit', ['id' => $handling->getId()]),
            'emergencies' => $emergencies,
            'fieldsParam' => $fieldsParam,
            'receivers' => $receivers,
        ]);
    }

    #[Route("/edit-statut", name: "handling_status_edit", options: ['expose' => true], methods: [self::POST])]
    public function editStatut(Request $request,
                               EntityManagerInterface $entityManager,
                               StatusHistoryService $statusHistoryService,
                               SettingsService $settingsService,
                               HandlingService $handlingService): JsonResponse {
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $request = json_decode($request->getContent(), true);
        $status = $statutRepository->find($request['statut']);
        $handling = $handlingRepository->find($request['handling']);
        $requester = $this->getUser();

        $statusHistoryService->updateStatus($entityManager, $handling, $status, [
            "initiatedBy" => $requester,
        ]);

        if ($status->isTreated()) {
            $handling->setValidationDate(new \DateTime());
            $handling->setTreatedByHandling($requester);
        }

        $entityManager->persist($handling);
        $entityManager->flush();

        $viewHoursOnExpectedDate = !$settingsService->getValue($entityManager, Setting::REMOVE_HOURS_DATETIME);
        $handlingService->sendEmailsAccordingToStatus($entityManager, $handling, $viewHoursOnExpectedDate);

        return $this->json([
            "success" => true,
        ]);
    }
}
