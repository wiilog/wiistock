<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\FieldsParam;
use App\Entity\Menu;
use App\Entity\Handling;

use App\Entity\Attachment;
use App\Entity\Setting;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Helper\FormatHelper;
use App\Service\NotificationService;
use App\Service\StatusHistoryService;
use App\Service\StatusService;
use App\Service\VisibleColumnService;
use GuzzleHttp\Exception\ConnectException;
use WiiCommon\Helper\Stream;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\DateService;
use App\Service\FreeFieldService;
use App\Service\UniqueNumberService;
use App\Service\HandlingService;

use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\TranslationService;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/services")
 */
class HandlingController extends AbstractController {

    /**
     * @Route("/", name="handling_index", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::DISPLAY_HAND})
     */
    public function index(EntityManagerInterface $entityManager, Request $request,
                          StatusService $statusService, HandlingService $handlingService): Response {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);

        $fields = $handlingService->getColumnVisibleConfig($entityManager, $this->getUser());

        $filterStatus = $request->query->get('filter');
        $user = $this->getUser();
        $dateChoice = [
            [
                'name' => 'creationDate',
                'label' => 'Date de création',
            ],
            [
                'name' => 'expectedDate',
                'label' => 'Date attendue',
            ],
            [
                'name' => 'treatmentDate',
                'label' => 'Date de réalisation',
            ],
        ];
        foreach ($dateChoice as &$choice) {
            $choice['default'] = (bool)$filtreSupRepository->findOnebyFieldAndPageAndUser('date-choice_'.$choice['name'], 'handling', $user);
        }
        if (Stream::from($dateChoice)->every(function ($choice) { return !$choice['default']; })) {
            $dateChoice[0]['default'] = true;
        }

        return $this->render('handling/index.html.twig', [
            'selectedDate' => DateTime::createFromFormat("Y-m-d", $request->query->get('date')),
            'dateChoices' => $dateChoice,
            'statuses' => $statutRepository->findByCategorieName(Handling::CATEGORIE, 'displayOrder'),
			'filterStatus' => $filterStatus,
            'types' => $types,
            'fieldsParam' => $fieldsParam,
            'fields' => $fields,
            'status_state_values' => Stream::from($statusService->getStatusStatesValues())
                ->reduce(function(array $carry, $test) {
                    $carry[$test['id']] = $test['label'];
                    return $carry;
                }, []),
            'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY),
            'modalNewConfig' => [
                'defaultStatuses' => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::HANDLING),
                'freeFieldsTypes' => array_map(function (Type $type) use ($freeFieldsRepository) {
                    $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_HANDLING);
                    return [
                        'typeLabel' => $type->getLabel(),
                        'typeId' => $type->getId(),
                        'freeFields' => $freeFields,
                    ];
                }, $types),
                'handlingStatus' => $statutRepository->findStatusByType(CategorieStatut::HANDLING),
                'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY)
            ],
		]);
    }

    /**
     * @Route("/api-columns", name="handling_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_HAND}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(EntityManagerInterface $entityManager, Request $request, HandlingService $handlingService): Response
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $columns = $handlingService->getColumnVisibleConfig($entityManager, $currentUser);
        return new JsonResponse($columns);
    }

    /**
     * @Route("/api", name="handling_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_HAND}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, HandlingService $handlingService): Response
    {
        // cas d'un filtre statut depuis page d'accueil
        $filterStatus = $request->request->get('filterStatus');
        $selectedDate = $request->request->get('selectedDate');
        $data = $handlingService->getDataForDatatable($request->request, $filterStatus, $selectedDate);

        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="handling_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(EntityManagerInterface $entityManager,
                        Request $request,
                        HandlingService $handlingService,
                        FreeFieldService $freeFieldService,
                        AttachmentService $attachmentService,
                        TranslationService $translation,
                        UniqueNumberService $uniqueNumberService,
                        NotificationService $notificationService,
                        StatusHistoryService $statusHistoryService): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $post = $request->request;

        $handling = new Handling();
        $date = new DateTime('now');

        $status = $statutRepository->find($post->get('status'));
        $type = $typeRepository->find($post->get('type'));
        $desiredDate = $post->get('desired-date') ? new DateTime($post->get('desired-date')) : null;
        $fileBag = $request->files->count() > 0 ? $request->files : null;

        $handlingNumber = $uniqueNumberService->create($entityManager, Handling::NUMBER_PREFIX, Handling::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

        /** @var Utilisateur $requester */
        $requester = $this->getUser();

        $carriedOutOperationCount = $post->get('carriedOutOperationCount');

        $handling
            ->setNumber($handlingNumber)
            ->setCreationDate($date)
            ->setType($type)
            ->setRequester($requester)
            ->setSubject(substr($post->get('subject'), 0, 64))
            ->setSource($post->get('source') ?? '')
            ->setDestination($post->get('destination') ?? '')
            ->setStatus($status)
            ->setDesiredDate($desiredDate)
            ->setComment($post->get('comment'))
            ->setEmergency($post->get('emergency'))
            ->setCarriedOutOperationCount(is_numeric($carriedOutOperationCount) ? ((int) $carriedOutOperationCount) : null);

        $statusHistoryService->updateStatus($entityManager, $handling, $status, [
            "forceCreation" => false,
        ]);

        if ($status && $status->isTreated()) {
            $handling->setValidationDate($date);
            $handling->setTreatedByHandling($requester);
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

        $freeFieldService->manageFreeFields($handling, $post->all(), $entityManager);

        if (isset($fileBag)) {
            $fileNames = [];
            foreach ($fileBag->all() as $file) {
                $fileNames = array_merge(
                    $fileNames,
                    $attachmentService->saveFile($file)
                );
            }
            $attachments = $attachmentService->createAttachements($fileNames);
            foreach ($attachments as $attachment) {
                $entityManager->persist($attachment);
                $handling->addAttachment($attachment);
            }
        }

        $entityManager->persist($handling);
        try {
            $entityManager->flush();
            if (($handling->getStatus()->getState() == Statut::NOT_TREATED)
                && $handling->getType()
                && ($handling->getType()->isNotificationsEnabled() || $handling->getType()->isNotificationsEmergency($handling->getEmergency()))) {
                $notificationService->toTreat($handling);
            }
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException | ConnectException $e) {

            if ($e instanceof UniqueConstraintViolationException) {
                $message = $translation->trans('services.Une autre demande de service est en cours de création, veuillez réessayer') . '.';
            } else if ($e instanceof ConnectException) {
                $message = "Une erreur s'est produite lors de l'envoi de la notifiation de cette demande de service. Veuillez réessayer.";
            }
            if (!empty($message)) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => $message
                ]);
            }
        }
        $viewHoursOnExpectedDate = !$settingRepository->getOneParamByLabel(Setting::REMOVE_HOURS_DATETIME);
        $handlingService->sendEmailsAccordingToStatus($entityManager, $handling, $viewHoursOnExpectedDate, !$status->isTreated());

        return new JsonResponse([
            'success' => true,
            'msg' => $translation->trans("services.La demande de service {numéro} a bien été créée", [
                    "{numéro}" => '<strong>' . $handling->getNumber() . '</strong>'
                ]) . '.'
        ]);
    }

    /**
     * @Route("/modifier/{id}", name="handling_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param TranslationService $translation
     * @param AttachmentService $attachmentService
     * @param HandlingService $handlingService
     * @param Handling $handling
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         Handling $handling,
                         FreeFieldService $freeFieldService,
                         TranslationService $translation,
                         AttachmentService $attachmentService,
                         HandlingService $handlingService,
                         NotificationService $notificationService,
                         StatusHistoryService $statusHistoryService): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $post = $request->request;

        $date = (new DateTime('now'));
        $desiredDateStr = $post->get('desired-date');
        $desiredDate = $desiredDateStr ? FormatHelper::parseDatetime($desiredDateStr) : null;

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
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
            ->setSubject(substr($post->get('subject'), 0, 64))
            ->setSource($post->get('source') ?? $handling->getSource())
            ->setDestination($post->get('destination') ?? $handling->getDestination())
            ->setDesiredDate($desiredDate)
            ->setComment($post->get('comment') ?: '')
            ->setEmergency($post->get('emergency') ?? $handling->getEmergency())
            ->setCarriedOutOperationCount(
                (is_numeric($carriedOutOperationCount)
                    ? $carriedOutOperationCount
                    : (!empty($carriedOutOperationCount)
                        ? $handling->getCarriedOutOperationCount()
                        : null)
            ));


        $freeFieldService->manageFreeFields($handling, $post->all(), $entityManager);

        $listAttachmentIdToKeep = $post->all('files') ?? [];

        $attachments = $handling->getAttachments()->toArray();
        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $attachmentService->removeAndDeleteAttachment($attachment, $handling);
            }
        }

        $this->persistAttachments($handling, $attachmentService, $request, $entityManager);

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => $translation->trans("services.La demande de service {numéro} a bien été modifiée", [
                    "{numéro}" => '<strong>' . $handling->getNumber() . '</strong>'
                ]) . '.'
        ]);

    }

    /**
     * @param Handling $entity
     * @param AttachmentService $attachmentService
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     */
    private function persistAttachments(Handling $entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager)
    {
        $attachments = $attachmentService->createAttachements($request->files);
        foreach ($attachments as $attachment) {
            $entityManager->persist($attachment);
            $entity->addAttachment($attachment);
        }
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    /**
     * @Route("/supprimer", name="handling_delete", options={"expose"=true},methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
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

            return new JsonResponse([
                'success' => true,
                'msg' => $translation->trans('services.La demande de service {numéro} a bien été supprimée', [
                        "{numéro}" => '<strong>' . $handlingNumber . '</strong>'
                    ]).'.',
                'redirect'=> $this->generateUrl('handling_index')
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_handlings_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param TranslationService $translation
     * @param CSVExportService $CSVExportService
     * @param FreeFieldService $freeFieldService
     * @param DateService $dateService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getHandlingsCSV(Request $request,
                                    TranslationService $translation,
                                    CSVExportService $CSVExportService,
                                    FreeFieldService $freeFieldService,
                                    DateService $dateService,
                                    EntityManagerInterface $entityManager): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (!empty($dateTimeMin) && !empty($dateTimeMax)) {
            $handlingsRepository = $entityManager->getRepository(Handling::class);
            $settingRepository = $entityManager->getRepository(Setting::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_HANDLING]);
            $includeDesiredTime = !$settingRepository->getOneParamByLabel(Setting::REMOVE_HOURS_DATETIME);

            $handlingParameters = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);
            $receiversParameters = $handlingParameters[FieldsParam::FIELD_CODE_RECEIVERS_HANDLING];

            $handlings = $handlingsRepository->getByDates($dateTimeMin, $dateTimeMax);
            $receivers = $handlingsRepository->getReceiversByDates($dateTimeMin, $dateTimeMax);
            $currentDate = new DateTime('now');

            $csvHeaderBase = [
                'numéro de demande',
                'date création',
                'demandeur',
                'type',
                $translation->trans('services.Objet'),
                'chargement',
                'déchargement',
                'date attendue',
                'date de réalisation',
                'statut',
                'commentaire',
                'urgence',
                $translation->trans('services.Nombre d\'opération(s) réalisée(s)'),
                'traité par',
            ];

            $csvHeader = array_merge(
                $csvHeaderBase,
                ($receiversParameters['displayedCreate'] ?? false
                    || $receiversParameters['displayedEdit'] ?? false
                    ||  $receiversParameters['displayedFilters'] ?? false)
                    ? ['destinataire(s)']
                    : [],
                $freeFieldsConfig['freeFieldsHeader']
            );
            $user = $this->getUser();
            $today = new DateTime();
            $today = $today->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i:s' : "d-m-Y H:i:s");
            $globalTitle = 'export-services-' . $today . '.csv';
            return $CSVExportService->createBinaryResponseFromData(
                $globalTitle,
                $handlings,
                $csvHeader,
                function ($handling) use ($freeFieldsConfig, $dateService, $includeDesiredTime, $receivers, $user) {
//                    $treatmentDelay = $handling['treatmentDelay'];
//                    $treatmentDelayInterval = $treatmentDelay ? $dateService->secondsToDateInterval($treatmentDelay) : null;
//                    $treatmentDelayStr = $treatmentDelayInterval ? $dateService->intervalToStr($treatmentDelayInterval) : '';
                    $id = $handling['id'];
                    $receiversStr = Stream::from($receivers[$id] ?? [])
                        ->join(", ");
                    $row = [];
                    $row[] = $handling['number'] ?? '';
                    $row[] = FormatHelper::datetime($handling['creationDate'], "", false, $user);
                    $row[] = $handling['sensorName'] ?? ($handling['requester'] ?? '');
                    $row[] = $handling['type'] ?? '';
                    $row[] = $handling['subject'] ?? '';
                    $row[] = $handling['loadingZone'] ?? '';
                    $row[] = $handling['unloadingZone'] ?? '';
                    $row[] = $includeDesiredTime
                        ? FormatHelper::datetime($handling['desiredDate'], "", false, $user)
                        : FormatHelper::date($handling['desiredDate'], "", false, $user);
                    $row[] = FormatHelper::datetime($handling['validationDate'], "", false, $user);
                    $row[] = $handling['status'] ?? '';
                    $row[] = strip_tags($handling['comment']) ?? '';
                    $row[] = $handling['emergency'] ?? '';
                    $row[] = $handling['carriedOutOperationCount'] ?? '';
                    $row[] = $handling['treatedBy'] ?? '';
                    $row[] = $receiversStr ?? '';
//                    $row[] = $treatmentDelayStr;

                    foreach($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
                        $row[] = FormatHelper::freeField($handling['freeFields'][$freeFieldId] ?? '', $freeField, $user);
                    }

                    return [$row];
                });
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_handling", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService $visibleColumnService): Response
    {
        $data = json_decode($request->getContent(), true);

        $fields = array_keys($data);
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $visibleColumnService->setVisibleColumns("handling", $fields, $user);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Vos préférences de colonnes à afficher ont bien été sauvegardées"
        ]);
    }

    #[Route("/voir/{id}", name: "handling_show", options: ["expose" => true], methods: ["GET","POST"])]
    #[HasPermission([Menu::DEM, Action::EDIT])]
    public function show(Handling $handling, EntityManagerInterface $entityManager): Response {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $freeFields = $freeFieldRepository->findByType($handling->getType());
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);
        $status = Stream::from($statutRepository->findStatusByType(CategorieStatut::HANDLING, $handling->getType()))
        ->map(function ($statut) {
            return [
                "label" => $statut->getNom(),
                "value" => $statut->getId(),
            ];
        })->toArray();

        return $this->render('handling/show.html.twig', [
            'handling' => $handling,
            'freeFields' => $freeFields,
            'fieldsParam' => $fieldsParam,
            'status' => $status,
        ]);
    }

    // TODO Permission
    #[Route("/{id}/status-history-api", name: "handling_status_history_api", options: ['expose' => true], methods: "GET")]
    public function statusHistoryApi(int $id,
                                     EntityManagerInterface $entityManager): JsonResponse {
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $handling = $handlingRepository->find($id);

        return $this->json([
            "success" => true,
            "template" => $this->renderView('handling/status-history.html.twig', [
                "statusesHistory" => Stream::from($handling->getStatusHistory())
                    ->map(fn(StatusHistory $statusHistory) => [
                        "status" => FormatHelper::status($statusHistory->getStatus()),
                        "date" => FormatHelper::longDate($statusHistory->getDate(), ["short" => true, "time" => true])
                    ])
                    ->toArray(),
                "handling" => $handling,
            ]),
        ]);
    }

    // TODO Permission
    #[Route("/modifier-page/{id}", name: "handling_edit_page", options: ["expose" => true], methods: ["GET","POST"])]
    public function editHandling(  Handling $handling,
                           EntityManagerInterface $entityManager): Response {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        $freeFields = $freeFieldRepository->findByType($handling->getType());
        $emergencies = Stream::from($fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY))
            ->map(fn($emergency) => [
                "label" => $emergency,
                "value" => $emergency,
                "selected" => $emergency === $handling->getEmergency()
            ])
            ->toArray();
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);
        $receivers = Stream::from($handling->getReceivers())
            ->map(fn($receiver) => [
                "label" => $receiver->getUsername(),
                "value" => $receiver->getId(),
                "selected" => true
            ])
            ->toArray();

        return $this->render('handling/editHandling.html.twig', [
            'handling' => $handling,
            'freeFields' => $freeFields,
            'submit_url' => $this->generateUrl('handling_edit', ['id' => $handling->getId()]),
            'emergencies' => $emergencies,
            'fieldsParam' => $fieldsParam,
            'receivers' => $receivers,
        ]);
    }

    #[Route("/edit-statut", name: "handling_status_edit", options: ['expose' => true], methods: "POST")]
    public function editStatut(Request $request,
                               EntityManagerInterface $entityManager,
                               StatusHistoryService $statusHistoryService): JsonResponse {
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $request =json_decode($request->getContent(), true);
        $statut = $statutRepository->find($request['statut']);
        $handling = $handlingRepository->find($request['handling']);
        $requester = $this->getUser();

        $statusHistoryService->updateStatus($entityManager, $handling, $statut);

        if ($statut->isTreated()) {
            $handling->setValidationDate(new \DateTime());
            $handling->setTreatedByHandling($requester);
        }

        $entityManager->persist($handling);
        $entityManager->flush();

        return $this->json([
            "success" => true,
        ]);
    }
}
