<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\FieldsParam;
use App\Entity\Menu;
use App\Entity\Handling;

use App\Entity\Attachment;
use App\Entity\ParametrageGlobal;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Helper\FormatHelper;
use WiiCommon\Helper\Stream;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\DateService;
use App\Service\FreeFieldService;
use App\Service\MailerService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use App\Service\HandlingService;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/services")
 */
class HandlingController extends AbstractController
{

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MailerService
     */
    private $mailerService;

    public function __construct(UserService $userService,
                                MailerService $mailerService)
    {
        $this->userService = $userService;
        $this->mailerService = $mailerService;
    }

    /**
     * @Route("/api", name="handling_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param HandlingService $handlingService
     * @return Response
     */
    public function api(Request $request, HandlingService $handlingService): Response
    {
		if ($request->isXmlHttpRequest()) {

			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND)) {
				return $this->redirectToRoute('access_denied');
			}

			// cas d'un filtre statut depuis page d'accueil
			$filterStatus = $request->request->get('filterStatus');
			$data = $handlingService->getDataForDatatable($request->request, $filterStatus);

			return new JsonResponse($data);
		} else {
			throw new BadRequestHttpException();
		}
    }

    /**
     * @Route("/", name="handling_index", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager,
                          Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);

        $filterStatus = $request->query->get('filter');

        return $this->render('handling/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(Handling::CATEGORIE, 'nom'),
			'filterStatus' => $filterStatus,
            'types' => $types,
            'fieldsParam' => $fieldsParam,
            'removeHourInDatetime' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REMOVE_HOURS_DATETIME),
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
            ]
		]);
    }


    /**
     * @Route("/creer", name="handling_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param HandlingService $handlingService
     * @param FreeFieldService $freeFieldService
     * @param AttachmentService $attachmentService
     * @param TranslatorInterface $translator
     * @param UniqueNumberService $uniqueNumberService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function new(EntityManagerInterface $entityManager,
                        Request $request,
                        HandlingService $handlingService,
                        FreeFieldService $freeFieldService,
                        AttachmentService $attachmentService,
                        TranslatorInterface $translator,
                        UniqueNumberService $uniqueNumberService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $post = $request->request;

            $handling = new Handling();
            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));

            $status = $statutRepository->find($post->get('status'));
            $type = $typeRepository->find($post->get('type'));
            $desiredDate = $post->get('desired-date') ? new DateTime($post->get('desired-date')) : null;
            $fileBag = $request->files->count() > 0 ? $request->files : null;

            $handlingNumber = $uniqueNumberService->createUniqueNumber($entityManager, Handling::PREFIX_NUMBER, Handling::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

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
            }
            /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => $translator->trans('services.Une autre demande de service est en cours de création, veuillez réessayer').'.'
                ]);
            }
            $viewHoursOnExpectedDate = !$parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REMOVE_HOURS_DATETIME);
            $handlingService->sendEmailsAccordingToStatus($entityManager, $handling, $viewHoursOnExpectedDate, !$status->isTreated());

            return new JsonResponse([
                'success' => true,
                'msg' => $translator->trans("services.La demande de service {numéro} a bien été créée", [
                        "{numéro}" => '<strong>' . $handling->getNumber() . '</strong>'
                    ]) . '.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="handling_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param DateService $dateService
     * @param Request $request
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function editApi(EntityManagerInterface $entityManager,
                            DateService $dateService,
                            Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $handlingRepository = $entityManager->getRepository(Handling::class);
            $attachmentsRepository = $entityManager->getRepository(Attachment::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $handling = $handlingRepository->find($data['id']);
            $status = $handling->getStatus();
            $statusTreated = $status && $status->isTreated();
            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);

            $treatmentDelay = $handling->getTreatmentDelay();
            $treatmentDelayInterval = $treatmentDelay ? $dateService->secondsToDateInterval($treatmentDelay) : null;
            $treatmentDelayStr = $treatmentDelayInterval ? $dateService->intervalToStr($treatmentDelayInterval) : '';

            $json = $this->renderView('handling/modalEditHandlingContent.html.twig', [
                'handling' => $handling,
                'removeHourInDatetime' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REMOVE_HOURS_DATETIME),
                'treatmentDelay' => $treatmentDelayStr,
                'handlingStatus' => !$statusTreated
                    ? $statutRepository->findStatusByType(CategorieStatut::HANDLING, $handling->getType())
                    : [],
                'attachments' => $attachmentsRepository->findBy(['handling' => $handling]),
                'fieldsParam' => $fieldsParam,
                'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY),
                'receivers' => $handling->getReceivers()->toArray(),
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="handling_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param TranslatorInterface $translator
     * @param AttachmentService $attachmentService
     * @param HandlingService $handlingService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         FreeFieldService $freeFieldService,
                         TranslatorInterface $translator,
                         AttachmentService $attachmentService,
                         HandlingService $handlingService): Response {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $post = $request->request;

        $handling = $handlingRepository->find($post->get('id'));

        $date = (new DateTime('now', new DateTimeZone('Europe/Paris')));
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

        $oldStatus = $handling->getStatus();

        if (!$oldStatus || !$oldStatus->isTreated()) {
            $newStatus = $statutRepository->find($post->get('status'));
            $handling->setStatus($newStatus);
        }
        else {
            $newStatus = null;
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

        if (!$handling->getValidationDate() && $newStatus->isTreated()) {
            $handling->setValidationDate($date);
            $handling->setTreatedByHandling($currentUser);
        }

        $freeFieldService->manageFreeFields($handling, $post->all(), $entityManager);

        $listAttachmentIdToKeep = $post->get('files') ?? [];

        $attachments = $handling->getAttachments()->toArray();
        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $attachmentService->removeAndDeleteAttachment($attachment, $handling);
            }
        }

        $this->persistAttachments($handling, $attachmentService, $request, $entityManager);

        $entityManager->flush();

        // check if status has changed
        if ((!$oldStatus && $newStatus)
            || (
                $oldStatus
                && $newStatus
                && ($oldStatus->getId() !== $newStatus->getId())
            )) {
            $viewHoursOnExpectedDate = !$parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REMOVE_HOURS_DATETIME);
            $handlingService->sendEmailsAccordingToStatus($entityManager, $handling, $viewHoursOnExpectedDate);
        }

        return new JsonResponse([
            'success' => true,
            'msg' => $translator->trans("services.La demande de service {numéro} a bien été modifiée", [
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
     * @Route("/supprimer", name="handling_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           TranslatorInterface $translator): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DELETE)) {
				return $this->redirectToRoute('access_denied');
			}
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

            $entityManager->remove($handling);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => $translator->trans('services.La demande de service {numéro} a bien été supprimée', [
                        "{numéro}" => '<strong>' . $handlingNumber . '</strong>'
                    ]).'.'
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_handlings_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param TranslatorInterface $translator
     * @param CSVExportService $CSVExportService
     * @param FreeFieldService $freeFieldService
     * @param DateService $dateService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getHandlingsCSV(Request $request,
                                    TranslatorInterface $translator,
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
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_HANDLING]);
            $includeDesiredTime = !$parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REMOVE_HOURS_DATETIME);

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
                $translator->trans('services.Objet'),
                'chargement',
                'déchargement',
                'date attendue',
                'date de réalisation',
                'statut',
                'commentaire',
                'urgence',
                $translator->trans('services.Nombre d\'opération(s) réalisée(s)'),
                'traité par',
            ];

            $csvHeader = array_merge(
                $csvHeaderBase,
                ($receiversParameters['displayedFormsCreate'] ?? false
                    || $receiversParameters['displayedFormsEdit'] ?? false
                    ||  $receiversParameters['displayedFilters'] ?? false)
                    ? ['destinataire(s)']
                    : [],
                $freeFieldsConfig['freeFieldsHeader']
            );

            $globalTitle = 'export-services-' . $currentDate->format('d-m-Y') . '.csv';
            return $CSVExportService->createBinaryResponseFromData(
                $globalTitle,
                $handlings,
                $csvHeader,
                function ($handling) use ($freeFieldService, $freeFieldsConfig, $dateService, $includeDesiredTime, $receivers) {
//                    $treatmentDelay = $handling['treatmentDelay'];
//                    $treatmentDelayInterval = $treatmentDelay ? $dateService->secondsToDateInterval($treatmentDelay) : null;
//                    $treatmentDelayStr = $treatmentDelayInterval ? $dateService->intervalToStr($treatmentDelayInterval) : '';
                    $id = $handling['id'];
                    $receiversStr = Stream::from($receivers[$id] ?? [])
                        ->join(", ");
                    $row = [];
                    $row[] = $handling['number'] ?? '';
                    $row[] = FormatHelper::datetime($handling['creationDate']);
                    $row[] = $handling['requester'] ?? '';
                    $row[] = $handling['type'] ?? '';
                    $row[] = $handling['subject'] ?? '';
                    $row[] = $handling['loadingZone'] ?? '';
                    $row[] = $handling['unloadingZone'] ?? '';
                    $row[] = $includeDesiredTime
                        ? FormatHelper::datetime($handling['desiredDate'])
                        : FormatHelper::date($handling['desiredDate']);
                    $row[] = FormatHelper::datetime($handling['validationDate']);
                    $row[] = $handling['status'] ?? '';
                    $row[] = strip_tags($handling['comment']) ?? '';
                    $row[] = $handling['emergency'] ?? '';
                    $row[] = $handling['carriedOutOperationCount'] ?? '';
                    $row[] = $handling['treatedBy'] ?? '';
                    $row[] = $receiversStr ?? '';
//                    $row[] = $treatmentDelayStr;

                    foreach ($freeFieldsConfig['freeFieldIds'] as $freeFieldId) {
                        $row[] = $freeFieldService->serializeValue([
                            'typage' => $freeFieldsConfig['freeFieldsIdToTyping'][$freeFieldId],
                            'valeur' => $handling['freeFields'][$freeFieldId] ?? ''
                        ]);
                    }

                    return [$row];
                }
            );
        } else {
            throw new BadRequestHttpException();
        }
    }

}
