<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Arrivage;
use App\Entity\CategorieCL;
use App\Entity\Dispatch;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\Menu;

use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\DispatchPack;
use App\Entity\Setting;
use App\Entity\Attachment;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\ArrivageService;
use App\Service\NotificationService;
use App\Service\VisibleColumnService;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FreeFieldService;
use App\Service\PackService;
use App\Service\PDFGeneratorService;
use App\Service\RedirectService;
use App\Service\SpecificService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use App\Service\DispatchService;

use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TranslationService;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use WiiCommon\Helper\StringHelper;

/**
 * @Route("/acheminements")
 */
class DispatchController extends AbstractController {

    private const EXTRA_OPEN_PACK_MODAL = "EXTRA_OPEN_PACK_MODAL";

    /** @Required */
    public UserService $userService;

    /** @Required  */
    public AttachmentService $attachmentService;

    /**
     * @Route("/", name="dispatch_index")
     * @HasPermission({Menu::DEM, Action::DISPLAY_ACHE})
     */
    public function index(EntityManagerInterface $entityManager, DispatchService $service) {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $fields = $service->getVisibleColumnsConfig($entityManager, $currentUser);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);

        return $this->render('dispatch/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, 'displayOrder'),
            'carriers' => $carrierRepository->findAllSorted(),
            'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY),
            'types' => Stream::from($types)
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $this->getFormatter()->type($type)
                ])
                ->toArray(),
            'fieldsParam' => $fieldsParam,
            'fields' => $fields,
            'modalNewConfig' => $service->getNewDispatchConfig($entityManager, $types)
        ]);
    }

    /**
     * @Route("/api-columns", name="dispatch_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_ACHE}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(EntityManagerInterface $entityManager, DispatchService $service): Response {
            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            $columns = $service->getVisibleColumnsConfig($entityManager, $currentUser);

            return $this->json(array_values($columns));
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_dispatch", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_ACHE}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request                $request,
                                      TranslationService     $translationService,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService   $visibleColumnService): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $visibleColumnService->setVisibleColumns('dispatch', $fields, $currentUser);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => $translationService->translate('Général', null, 'Zone liste', 'Vos préférences de colonnes à afficher ont bien été sauvegardées', false)
        ]);
    }

    /**
     * @Route("/autocomplete", name="get_dispatch_numbers", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getDispatchAutoComplete(Request $request,
                                            EntityManagerInterface $entityManager): Response {
        $search = $request->query->get('term');

        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $results = $dispatchRepository->getDispatchNumbers($search);

        return $this->json(['results' => $results]);
    }

    /**
     * @Route("/api", name="dispatch_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_ACHE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        DispatchService $dispatchService): Response {
        $data = $dispatchService->getDataForDatatable($request->request);

        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="dispatch_new", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     */
    public function new(Request $request,
                        FreeFieldService $freeFieldService,
                        DispatchService $dispatchService,
                        AttachmentService $attachmentService,
                        EntityManagerInterface $entityManager,
                        TranslationService $translationService,
                        UniqueNumberService $uniqueNumberService,
                        RedirectService $redirectService): Response {
        if(!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE) ||
            !$this->userService->hasRightFunction(Menu::DEM, Action::CREATE_ACHE)) {
            return $this->json([
                'success' => false,
                'redirect' => $this->generateUrl('access_denied')
            ]);
        }

        $post = $request->request;

        $packs = [];
        if($post->has('packs')) {
            $packs = json_decode($post->get('packs'), true);

            if(empty($packs)) {
                return $this->json([
                    'success' => false,
                    'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'Une unité logistique minimum est nécessaire pour procéder à l\'acheminement', false)
                ]);
            }
        }

        if($post->getBoolean('existingOrNot')) {
            $existingDispatch = $entityManager->find(Dispatch::class, $post->getInt('existingDispatch'));
            $dispatchService->manageDispatchPacks($existingDispatch, $packs, $entityManager);

            $entityManager->flush();

            $number = $existingDispatch->getNumber();
            return $this->json([
                'success' => true,
                'redirect' => $redirectService->generateUrl("dispatch_show", ['id' => $existingDispatch->getId()]),
                'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'Les unités logistiques de l\'arrivage ont bien été ajoutés dans l`\'acheminement {1}', [1=>$number], false)
            ]);
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $transporterRepository = $entityManager->getRepository(Transporteur::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $preFill = $settingRepository->getOneParamByLabel(Setting::PREFILL_DUE_DATE_TODAY);
        $printDeliveryNote = $request->query->get('printDeliveryNote');

        $dispatch = new Dispatch();
        $date = new DateTime('now');

        $fileBag = $request->files->count() > 0 ? $request->files : null;

        $type = $typeRepository->find($post->get('type'));

        $locationTake = $post->get('prise')
            ? ($emplacementRepository->find($post->get('prise')) ?: $type->getPickLocation())
            : $type->getPickLocation();
        $locationDrop = $post->get('depose')
            ? ($emplacementRepository->find($post->get('depose')) ?: $type->getDropLocation())
            : $type->getDropLocation();

        $destination = $post->get('destination');

        $comment = $post->get('commentaire');
        $startDateRaw = $post->get('startDate');
        $endDateRaw = $post->get('endDate');
        $carrier = $post->get('carrier');
        $carrierTrackingNumber = $post->get('carrierTrackingNumber');
        $commandNumber = $post->get('commandNumber');
        $receivers = $post->get('receivers');
        $emergency = $post->get('emergency');
        $projectNumber = $post->get('projectNumber');
        $businessUnit = $post->get('businessUnit');
        $statusId = $post->get('status');

        $status = $statusId ? $statutRepository->find($statusId) : null;
        if (!isset($status) || $status?->getCategorie()?->getNom() !== CategorieStatut::DISPATCH) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'Veuillez renseigner un statut valide.', false)
            ]);
        }

        if(!$locationTake || !$locationDrop) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'Il n\'y a aucun emplacement de prise ou de dépose paramétré pour ce type.Veuillez en paramétrer ou rendre les champs visibles à la création et/ou modification.', false)
            ]);
        }

        $startDate = !empty($startDateRaw) ? $dispatchService->createDateFromStr($startDateRaw) : null;
        $endDate = !empty($endDateRaw) ? $dispatchService->createDateFromStr($endDateRaw) : null;

        if($startDate && $endDate && $startDate > $endDate) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'La date de fin d\'échéance est inférieure à la date de début.', false)
            ]);
        }

        $dispatchNumber = $uniqueNumberService->create($entityManager, Dispatch::NUMBER_PREFIX, Dispatch::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);
        $dispatch
            ->setCreationDate($date)
            ->setStatut($status)
            ->setType($type)
            ->setRequester($utilisateurRepository->find($post->get('requester')))
            ->setLocationFrom($locationTake)
            ->setLocationTo($locationDrop)
            ->setBusinessUnit($businessUnit)
            ->setNumber($dispatchNumber)
            ->setDestination($destination);

        if(!empty($comment)) {
            $dispatch->setCommentaire(StringHelper::cleanedComment($comment));
        }

        if(!empty($startDate)) {
            $dispatch->setStartDate($startDate);
        } else if ($preFill) {
            $dispatch->setStartDate(new DateTime());
        }

        if(!empty($endDate)) {
            $dispatch->setEndDate($endDate);
        } else if ($preFill) {
            $dispatch->setEndDate(new DateTime());
        }

        if(!empty($carrier)) {
            $dispatch->setCarrier($transporterRepository->find($carrier) ?? null);
        }

        if(!empty($carrierTrackingNumber)) {
            $dispatch->setCarrierTrackingNumber($carrierTrackingNumber);
        }

        if(!empty($commandNumber)) {
            $dispatch->setCommandNumber($commandNumber);
        }

        if(!empty($receivers)) {
            $receiverIds = explode("," , $receivers);

            foreach ($receiverIds as $receiverId) {
                if (!empty($receiverId)) {
                    $receiver = $receiverId ? $utilisateurRepository->find($receiverId) : null;
                    if ($receiver) {
                        $dispatch->addReceiver($receiver);
                    }
                }
            }
        }

        if(!empty($emergency)) {
            $dispatch->setEmergency($post->get('emergency'));
        }

        if(!empty($projectNumber)) {
            $dispatch->setProjectNumber($projectNumber);
        }

        $freeFieldService->manageFreeFields($dispatch, $post->all(), $entityManager);

        if(isset($fileBag)) {
            $fileNames = [];
            foreach($fileBag->all() as $file) {
                $fileNames = array_merge(
                    $fileNames,
                    $attachmentService->saveFile($file)
                );
            }
            $attachments = $attachmentService->createAttachements($fileNames);
            foreach($attachments as $attachment) {
                $entityManager->persist($attachment);
                $dispatch->addAttachment($attachment);
            }
        }

        if(!empty($packs)) {
            $dispatchService->manageDispatchPacks($dispatch, $packs, $entityManager);
        }

        $entityManager->persist($dispatch);

        try {
            $entityManager->persist($dispatch);
            $entityManager->flush();
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch(UniqueConstraintViolationException) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'Une autre demande d\'acheminement est en cours de création, veuillez réessayer', false)
            ]);
        }

        if(!empty($receiver)) {
            $dispatchService->sendEmailsAccordingToStatus($dispatch, false);
        }

        $showArguments = [
            "id" => $dispatch->getId(),
        ];

        if($printDeliveryNote) {
            $showArguments['print-delivery-note'] = "1";
        }

        return new JsonResponse([
            'success' => true,
            'redirect' => $redirectService->generateUrl("dispatch_show", $showArguments, self::EXTRA_OPEN_PACK_MODAL),
            'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'L\'acheminement a bien été créé', false)
        ]);
    }

    /**
     * @Route("/voir/{id}/{printBL}", name="dispatch_show", options={"expose"=true}, methods="GET|POST", defaults={"printBL"=0,"fromCreation"=0})
     * @HasPermission({Menu::DEM, Action::DISPLAY_ACHE})
     */
    public function show(Dispatch $dispatch,
                         EntityManagerInterface $entityManager,
                         DispatchService $dispatchService,
                         RedirectService $redirectService,
                         UserService $userService,
                         bool $printBL) {
        $extra = $redirectService->load();

        $paramRepository = $entityManager->getRepository(Setting::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $dispatchStatus = $dispatch->getStatut();

        return $this->render('dispatch/show.html.twig', [
            'dispatch' => $dispatch,
            'detailsConfig' => $dispatchService->createHeaderDetailsConfig($dispatch),
            'modifiable' => (!$dispatchStatus || $dispatchStatus->isDraft()) && $userService->hasRightFunction(Menu::DEM, Action::MANAGE_PACK),
            'newPackConfig' => [
                'natures' => $natureRepository->findBy([], ['label' => 'ASC'])
            ],
            'dispatchValidate' => [
                'untreatedStatus' => $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::NOT_TREATED])
            ],
            'dispatchTreat' => [
                'treatedStatus' => $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::TREATED, Statut::PARTIAL])
            ],
            'printBL' => $printBL,
            'prefixPackCodeWithDispatchNumber' => $paramRepository->getOneParamByLabel(Setting::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER),
            'newPackRow' => $dispatchService->packRow($dispatch, null, true, true),
        ]);
    }

    /**
     * @Route("/{dispatch}/dispatch-note", name="dispatch_note", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function postDispatchNote(Dispatch $dispatch,
                                     DispatchService $dispatchService,
                                     EntityManagerInterface $entityManager): Response {
        $dispatchNoteData = $dispatchService->getDispatchNoteData($dispatch);

        $dispatchNoteAttachment = new Attachment();
        $dispatchNoteAttachment
            ->setDispatch($dispatch)
            ->setFileName($dispatchNoteData['file'])
            ->setOriginalName($dispatchNoteData['name'] . '.pdf');

        $entityManager->persist($dispatchNoteAttachment);
        $entityManager->flush();

        $detailsConfig = $dispatchService->createHeaderDetailsConfig($dispatch);

        return new JsonResponse([
            'success' => true,
            'msg' => "Le téléchargement de votre bon d'acheminement va commencer...",
            'headerDetailsConfig' => $this->renderView("dispatch/dispatch-show-header.html.twig", [
                'dispatch' => $dispatch,
                'showDetails' => $detailsConfig,
                'modifiable' => !$dispatch->getStatut() || $dispatch->getStatut()->isDraft(),
            ]),
        ]);
    }

    /**
     * @Route("/{dispatch}/etat", name="print_dispatch_state_sheet", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::DEM, Action::DISPLAY_ACHE})
     */
    public function printDispatchStateSheet(TranslationService $translationService,
                                            Dispatch $dispatch,
                                            DispatchService $dispatchService): ?Response {
        if($dispatch->getDispatchPacks()->isEmpty()) {
            return $this->json([
                "success" => false,
                "msg" => $translationService->translate('Demande', 'Acheminements', 'Bon d\'acheminement', 'Le bon d\'acheminement n\'existe pas pour cet acheminement', false)
            ]);
        }

        $data = $dispatchService->getDispatchNoteData($dispatch);

        return new PdfResponse(
            $data['file'],
            "{$data['name']}.pdf"
        );
    }

    /**
     * @Route("/modifier", name="dispatch_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function edit(Request $request,
                         DispatchService $dispatchService,
                         TranslationService $translationService,
                         FreeFieldService $freeFieldService,
                         EntityManagerInterface $entityManager): Response {
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $transporterRepository = $entityManager->getRepository(Transporteur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $post = $request->request;
        $dispatch = $dispatchRepository->find($post->get('id'));

        if(!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT) ||
            $dispatch->getStatut()->isDraft() && !$this->userService->hasRightFunction(Menu::DEM, Action::EDIT_DRAFT_DISPATCH) ||
            $dispatch->getStatut()->isNotTreated() && !$this->userService->hasRightFunction(Menu::DEM, Action::EDIT_UNPROCESSED_DISPATCH)) {
            return $this->redirectToRoute('access_denied');
        }

        $startDateRaw = $post->get('startDate');
        $endDateRaw = $post->get('endDate');
        $startDate = !empty($startDateRaw) ? $dispatchService->createDateFromStr($startDateRaw) : null;
        $endDate = !empty($endDateRaw) ? $dispatchService->createDateFromStr($endDateRaw) : null;

        $type = $dispatch->getType();

        $locationTake = $post->get('prise')
            ? ($emplacementRepository->find($post->get('prise')) ?: $type->getPickLocation())
            : $type->getPickLocation();
        $locationDrop = $post->get('depose')
            ? ($emplacementRepository->find($post->get('depose')) ?: $type->getDropLocation())
            : $type->getDropLocation();

        $destination = $post->get('destination');

        if(!$locationTake || !$locationDrop) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', "Il n'y a aucun emplacement de prise ou de dépose paramétré pour ce type.Veuillez en paramétrer ou rendre les champs visibles à la création et/ou modification.", false)
            ]);
        }

        if($startDate && $endDate && $startDate > $endDate) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', "La date de fin d'échéance est inférieure à la date de début.", false)
            ]);
        }

        $requesterData = $post->get('requester');
        $carrierData = $post->get('carrier');
        $requester = $requesterData ? $utilisateurRepository->find($requesterData) : null;
        $carrier = $carrierData ? $transporterRepository->find($carrierData) : null;

        $transporterTrackingNumber = $post->get('transporterTrackingNumber');
        $commandNumber = $post->get('commandNumber');
        $projectNumber = $post->get('projectNumber');
        $businessUnit = $post->get('businessUnit');

        $receiversids = $post->get('receivers')
            ? explode(",", $post->get('receivers') ?? '')
            : [];

        $existingReceivers = $dispatch->getReceivers();
        foreach($existingReceivers as $receiver) {
            $dispatch->removeReceiver($receiver);
        }
        foreach ($receiversids as $receiverId) {
            if (!empty($receiverId)) {
                $receiver = $receiverId ? $utilisateurRepository->find($receiverId) : null;
                if ($receiver) {
                    $dispatch->addReceiver($receiver);
                }
            }
        }
        $dispatch
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setBusinessUnit($businessUnit)
            ->setCarrier($carrier)
            ->setCarrierTrackingNumber($transporterTrackingNumber)
            ->setCommandNumber($commandNumber)
            ->setRequester($requester)
            ->setEmergency($post->get('emergency') ?? null)
            ->setLocationFrom($locationTake)
            ->setLocationTo($locationDrop)
            ->setProjectNumber($projectNumber)
            ->setCommentaire(StringHelper::cleanedComment($post->get('commentaire')) ?: '')
            ->setDestination($destination);

        $freeFieldService->manageFreeFields($dispatch, $post->all(), $entityManager);

        $listAttachmentIdToKeep = $post->all('files') ?: [];

        $attachments = $dispatch->getAttachments()->toArray();
        foreach($attachments as $attachment) {
            /** @var Attachment $attachment */
            if(!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $dispatch);
            }
        }

        $this->persistAttachments($dispatch, $this->attachmentService, $request, $entityManager);

        $entityManager->flush();

        $dispatchStatus = $dispatch->getStatut();

        return new JsonResponse([
            'entete' => $this->renderView('dispatch/dispatch-show-header.html.twig', [
                'dispatch' => $dispatch,
                'modifiable' => !$dispatchStatus || $dispatchStatus->isDraft(),
                'showDetails' => $dispatchService->createHeaderDetailsConfig($dispatch)
            ]),
            'success' => true,
            'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'L\'acheminement a bien été modifié', false) . '.'
        ]);
    }

    /**
     * @Route("/api-modifier", name="dispatch_edit_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $attachmentRepository = $entityManager->getRepository(Attachment::class);

            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

            $dispatch = $dispatchRepository->find($data['id']);
            $dispatchStatus = $dispatch->getStatut();

            if(!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)
                || (
                    $dispatchStatus
                    && $dispatchStatus->isNotTreated()
                    && !$this->userService->hasRightFunction(Menu::DEM, Action::EDIT_UNPROCESSED_DISPATCH)
                )) {
                return $this->redirectToRoute('access_denied');
            }

            $statuses = (!$dispatchStatus || !$dispatchStatus->isTreated())
                ? $statutRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::DRAFT, Statut::NOT_TREATED])
                : [];

            $dispatchBusinessUnits = $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_BUSINESS_UNIT);

            $json = $this->renderView('dispatch/modalEditContentDispatch.html.twig', [
                'dispatchBusinessUnits' => !empty($dispatchBusinessUnits) ? $dispatchBusinessUnits : [],
                'dispatch' => $dispatch,
                'fieldsParam' => $fieldsParam,
                'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY),
                'utilisateurs' => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
                'statuses' => $statuses,
                'attachments' => $attachmentRepository->findBy(['dispatch' => $dispatch])
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="dispatch_delete", options={"expose"=true},methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     */
    public function delete(Request                $request,
                           EntityManagerInterface $entityManager,
                           TranslationService     $translationService): Response {
        if($data = json_decode($request->getContent(), true)) {
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $attachmentRepository = $entityManager->getRepository(Attachment::class);

            $dispatch = $dispatchRepository->find($data['dispatch']);

            if(!$this->userService->hasRightFunction(Menu::DEM, Action::DELETE) ||
                !$this->userService->hasRightFunction(Menu::DEM, Action::DELETE_DRAFT_DISPATCH) ||
                !$dispatch->getStatut()->isTreated() && !$this->userService->hasRightFunction(Menu::DEM, Action::DELETE_UNPROCESSED_DISPATCH) ||
                $dispatch->getStatut()->isTreated() && !$this->userService->hasRightFunction(Menu::DEM, Action::DELETE_PROCESSED_DISPATCH)) {
                return $this->redirectToRoute('access_denied');
            }

            if($dispatch) {
                $attachments = $attachmentRepository->findBy(['dispatch' => $dispatch]);
                foreach($attachments as $attachment) {
                    $entityManager->remove($attachment);
                }

                $trackingMovements = $dispatch->getTrackingMovements()->toArray();
                foreach($trackingMovements as $trackingMovement) {
                    $dispatch->removeTrackingMovement($trackingMovement);
                }
            }
            $entityManager->flush();
            $entityManager->remove($dispatch);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('dispatch_index'),
                'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'L\'acheminement a bien été supprimé', false) . '.'
            ]);
        }

        throw new BadRequestHttpException();
    }

    private function persistAttachments(Dispatch $entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager): void {
        $attachments = $attachmentService->createAttachements($request->files);
        foreach($attachments as $attachment) {
            $entityManager->persist($attachment);
            $entity->addAttachment($attachment);
        }
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    /**
     * @Route("/packs/api/{dispatch}", name="dispatch_pack_api", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function apiPack(UserService $userService,
                            DispatchService $service,
                            Dispatch $dispatch): Response {
        $dispatchStatus = $dispatch->getStatut();
        $edit = (
            $dispatchStatus->isDraft()
            && $userService->hasRightFunction(Menu::DEM, Action::MANAGE_PACK)
        );

        $data = [];
        foreach($dispatch->getDispatchPacks() as $dispatchPack) {
            $data[] = $service->packRow($dispatch, $dispatchPack, false, $edit);
        }

        if($edit) {
            if(empty($data)) {
                $data[] = $service->packRow($dispatch, null, true, true);
            }

            $data[] = [
                'createRow' => true,
                "actions" => "<span class='d-flex justify-content-start align-items-center'><span class='wii-icon wii-icon-plus'></span></span>",
                "code" => null,
                "quantity" => null,
                "nature" => null,
                "weight" => null,
                "volume" => null,
                "comment" => null,
                "lastMvtDate" => null,
                "lastLocation" => null,
                "operator" => null,
                "status" => null,
            ];
        }

        return $this->json([
            "data" => $data
        ]);
    }

    /**
     * @Route("/{dispatch}/packs/new", name="dispatch_new_pack", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function newPack(Request $request,
                            TranslationService $translationService,
                            EntityManagerInterface $entityManager,
                            PackService $packService,
                            Dispatch $dispatch): Response {
        $data = $request->request->all();

        $noPrefixPackCode = trim($data["pack"]);
        $natureId = $data["nature"];
        $quantity = $data["quantity"];
        $comment = $data["comment"] ?? "";
        $weight = (floatval(str_replace(',', '.', $data["weight"] ?? "")) ?: null);
        $volume = (floatval(str_replace(',', '.', $data["volume"] ?? "")) ?: null);

        $settingRepository = $entityManager->getRepository(Setting::class);

        $prefixPackCodeWithDispatchNumber = $settingRepository->getOneParamByLabel(Setting::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER);
        if($prefixPackCodeWithDispatchNumber && !str_starts_with($noPrefixPackCode, $dispatch->getNumber())) {
            $packCode = "{$dispatch->getNumber()}-$noPrefixPackCode";
        } else {
            $packCode = $noPrefixPackCode;
        }

        $natureRepository = $entityManager->getRepository(Nature::class);
        $packRepository = $entityManager->getRepository(Pack::class);

        if(!empty($packCode)) {
            $pack = Stream::from($dispatch->getDispatchPacks())
                ->filter(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack()->getCode() === $noPrefixPackCode)
                ->map(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack())
                ->firstOr(fn() => $packRepository->findOneBy(["code" => $packCode]));
        }

        $packMustBeNew = $settingRepository->getOneParamByLabel(Setting::PACK_MUST_BE_NEW);
        if($packMustBeNew && isset($pack)) {
            $isNotInDispatch = $dispatch->getDispatchPacks()
                ->filter(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack() === $pack)
                ->isEmpty();

            if($isNotInDispatch) {
                return $this->json([
                    "success" => false,
                    "msg" => "L'unité logistique <strong>${packCode}</strong> existe déjà en base de données"
                ]);
            }
        }

        if(empty($pack)) {
            $pack = $packService->createPack($entityManager, ['code' => $packCode]);
            $entityManager->persist($pack);
        }

        $dispatchPack = Stream::from($dispatch->getDispatchPacks())
            ->filter(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack() === $pack)
            ->first(new DispatchPack());

        $dispatchPack
            ->setPack($pack)
            ->setTreated(false)
            ->setDispatch($dispatch);
        $entityManager->persist($dispatchPack);

        $nature = $natureRepository->find($natureId);
        $pack->setNature($nature);
        $pack->setComment(StringHelper::cleanedComment($comment));
        $dispatchPack->setQuantity($quantity);
        $pack->setWeight($weight ? round($weight, 3) : null);
        $pack->setVolume($volume ? round($volume, 3) : null);

        $success = true;
        $toTranslate = 'L\'unité logistique {1} a bien été ' . ($dispatchPack->getId() ? "modifiée" : "ajoutée");
        $message = $translationService->translate('Demande', 'Acheminements', 'Détails acheminement - Liste des unités logistiques', $toTranslate, [1 => '<strong>{$pack->getCode()}</strong>']);

        $entityManager->flush();

        return $this->json([
            "success" => $success,
            "msg" => $message,
            "id" => $dispatchPack->getId(),
        ]);
    }

    /**
     * @Route("/packs/delete", name="dispatch_delete_pack", options={"expose"=true},methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     */
    public function deletePack(Request $request,
                               TranslationService $translationService,
                               EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);

            if($data['pack'] && $pack = $dispatchPackRepository->find($data['pack'])) {
                $entityManager->remove($pack);
                $entityManager->flush();
            }

            return $this->json([
                "success" => true,
                "msg" => $translationService->translate('Demande',"Acheminements", 'Détails acheminement - Liste des unités logistiques', "La ligne a bien été supprimée")
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/{id}/validate", name="dispatch_validate_request", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function validateDispatchRequest(Request $request,
                                            EntityManagerInterface $entityManager,
                                            Dispatch $dispatch,
                                            TranslationService $translationService,
                                            DispatchService $dispatchService,
                                            NotificationService $notificationService): Response {
        $status = $dispatch->getStatut();

        if(!$status || $status->isDraft()) {
            $data = json_decode($request->getContent(), true);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $data['status'];
            $untreatedStatus = $statusRepository->find($statusId);


            if($untreatedStatus && $untreatedStatus->isNotTreated() && ($untreatedStatus->getType() === $dispatch->getType())) {
                try {
                    if( $dispatch->getType() &&
                        ($dispatch->getType()->isNotificationsEnabled() || $dispatch->getType()->isNotificationsEmergency($dispatch->getEmergency()))) {
                        $notificationService->toTreat($dispatch);
                    }
                    $dispatch
                        ->setStatut($untreatedStatus)
                        ->setValidationDate(new DateTime('now'));
                    $entityManager->flush();
                    $dispatchService->sendEmailsAccordingToStatus($dispatch, true);
                } catch (Exception $e) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => "L'envoi de l'email ou de la notification a échoué. Veuillez rééssayer."
                    ]);
                }

            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Le statut sélectionné doit être de type à traiter et correspondre au type de la demande."
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'L\'acheminement a bien été passé en à traiter', false),
            'redirect' => $this->generateUrl('dispatch_show', ['id' => $dispatch->getId()])
        ]);
    }

    /**
     * @Route("/{id}/treat", name="dispatch_treat_request", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function treatDispatchRequest(Request $request,
                                         EntityManagerInterface $entityManager,
                                         DispatchService $dispatchService,
                                         Dispatch $dispatch,
                                         TranslationService $translationService): Response {
        $status = $dispatch->getStatut();

        if(!$status || $status->isNotTreated() || $status->isPartial()) {
            $data = json_decode($request->getContent(), true);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $data['status'];
            $treatedStatus = $statusRepository->find($statusId);

            if($treatedStatus
                && ($treatedStatus->isTreated() || $treatedStatus->isPartial())
                && $treatedStatus->getType() === $dispatch->getType()) {

                /** @var Utilisateur $loggedUser */
                $loggedUser = $this->getUser();
                $dispatchService->treatDispatchRequest($entityManager, $dispatch, $treatedStatus, $loggedUser);

                $entityManager->flush();
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Le statut sélectionné doit être de type traité et correspondre au type de la demande."
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'L\'acheminement a bien été traité'),
            'redirect' => $this->generateUrl('dispatch_show', ['id' => $dispatch->getId()])
        ]);
    }

    /**
     * @Route("/{dispatch}/packs-counter", name="get_dispatch_packs_counter", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function getDispatchPackCounter(Dispatch $dispatch): Response {
        return new JsonResponse([
            'success' => true,
            'packsCounter' => $dispatch->getDispatchPacks()->count()
        ]);
    }

    /**
     * @Route("/{dispatch}/rollback-draft", name="rollback_draft", methods="GET")
     */
    public function rollbackToDraftStatus(EntityManagerInterface $entityManager, Dispatch $dispatch): Response {
        $dispatchType = $dispatch->getType();
        $statusRepository = $entityManager->getRepository(Statut::class);

        $draftStatus = $statusRepository->findOneBy([
            'type' => $dispatchType,
            'state' => 0
        ]);

        $dispatch->setStatut($draftStatus);
        $entityManager->flush();

        return $this->redirectToRoute('dispatch_show', [
            'id' => $dispatch->getId()
        ]);
    }

    /**
     * @Route("/csv", name="get_dispatches_csv", options={"expose"=true}, methods={"GET"})
     */
    public function getDispatchesCSV(Request $request,
                                     DispatchService $dispatchService,
                                     FreeFieldService $freeFieldService,
                                     CSVExportService $CSVExportService,
                                     EntityManagerInterface $entityManager,
                                     TranslationService $translation): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch(Throwable) {
        }

        if(isset($dateTimeMin) && isset($dateTimeMax)) {
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $dispatches = $dispatchRepository->iterateByDates($dateTimeMin, $dateTimeMax);

            $nbPacksByDispatch = $dispatchRepository->getNbPacksByDates($dateTimeMin, $dateTimeMax);
            $receivers = $dispatchRepository->getReceiversByDates($dateTimeMin, $dateTimeMax);

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_DISPATCH]);

            $headers = array_merge(
                [
                    $translation->translate('Demande', 'Acheminements', 'Général', 'N° demande', false),
                    $translation->translate('Demande', 'Acheminements', 'Champs fixes', 'N° commande', false),
                    $translation->translate('Général', null, 'Zone liste', 'Date de création', false),
                    $translation->translate('Demande', 'Acheminements', 'Général', 'Date de validation', false),
                    $translation->translate('Demande', 'Acheminements', 'Général', 'Date de traitement', false),
                    $translation->translate('Demande', 'Général', 'Type', false),
                    $translation->translate('Demande', 'Général', 'Demandeur', false),
                    $translation->translate('Demande', 'Général', 'Destinataire(s)', false),
                    $translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de prise', false),
                    $translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de dépose', false),
                    $translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Destination', false),
                    $translation->translate('Demande', 'Acheminements', 'Zone liste - Noms de colonnes', 'Nombre d\'UL', false),
                    $translation->translate('Demande', 'Général', 'Statut', false),
                    $translation->translate('Demande', 'Général', 'Urgence', false),
                    $translation->translate('Demande', 'Acheminements', 'Général', 'Nature', false),
                    $translation->translate('Demande', 'Acheminements', 'Détails acheminement - Liste des unités logistiques', 'Unité logistique', false),
                    $translation->translate('Demande', 'Acheminements', 'Général', 'Quantité UL', false),
                    $translation->translate('Demande', 'Acheminements', 'Général', 'Quantité à acheminer', false),
                    $translation->translate('Demande', 'Acheminements', 'Général', 'Poids (kg)', false),
                    $translation->translate('Demande', 'Acheminements', 'Général', 'Date dernier mouvement', false),
                    $translation->translate('Demande', 'Acheminements', 'Général', 'Dernier emplacement', false),
                    $translation->translate('Demande', 'Acheminements', 'Général', 'Opérateur', false),
                    $translation->translate('Général', null, 'Zone liste', 'Traité par', false)
                ],
                $freeFieldsConfig['freeFieldsHeader']
            );

            return $CSVExportService->streamResponse(
                function ($output) use ($dispatches, $CSVExportService, $dispatchService, $receivers, $nbPacksByDispatch, $freeFieldsConfig) {
                    foreach ($dispatches as $dispatch) {
                        $dispatchService->putDispatchLine($output, $dispatch, $receivers, $nbPacksByDispatch, $freeFieldsConfig);
                    }
                },
                'export_acheminements.csv',
                $headers
            );
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/{dispatch}/api-delivery-note", name="api_delivery_note_dispatch", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function apiDeliveryNote(Request $request,
                                    TranslationService $translationService,
                                    EntityManagerInterface $manager,
                                    Dispatch $dispatch): JsonResponse {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        if($dispatch->getDispatchPacks()->count() === 0) {
            $errorMessage = $translationService->translate('Demande', 'Acheminements', 'Bon de livraison', 'Des unités logistiques sont nécessaires pour générer un bon de livraison', false) . '.';

            return $this->json([
                "success" => false,
                "msg" => $errorMessage
            ]);
        }

        $userSavedData = $loggedUser->getSavedDispatchDeliveryNoteData();
        $dispatchSavedData = $dispatch->getDeliveryNoteData();
        $defaultData = [
            'deliveryNumber' => $dispatch->getNumber(),
            'projectNumber' => $dispatch->getProjectNumber(),
            'username' => $loggedUser->getUsername(),
            'userPhone' => $loggedUser->getPhone(),
            'dispatchEmergency' => $dispatch->getEmergency()
        ];
        $deliveryNoteData = array_reduce(
            array_keys(Dispatch::DELIVERY_NOTE_DATA),
            function(array $carry, string $dataKey) use ($request, $userSavedData, $dispatchSavedData, $defaultData) {
                $carry[$dataKey] = (
                    $dispatchSavedData[$dataKey]
                    ?? ($userSavedData[$dataKey]
                        ?? ($defaultData[$dataKey]
                            ?? null))
                );

                return $carry;
            },
            []
        );

        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);

        $html = $this->renderView('dispatch/modalPrintDeliveryNoteContent.html.twig', array_merge($deliveryNoteData, [
            'dispatchEmergencyValues' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY),
            'fromDelivery' => $request->query->getBoolean('fromDelivery'),
        ]));

        return $this->json([
            "success" => true,
            "html" => $html
        ]);
    }

    /**
     * @Route("/{dispatch}/delivery-note", name="delivery_note_dispatch", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function postDeliveryNote(EntityManagerInterface $entityManager,
                                     Dispatch $dispatch,
                                     DispatchService $dispatchService,
                                     Request $request): JsonResponse {

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $userDataToSave = [];
        $dispatchDataToSave = [];

        // force dispatch number
        $data['deliveryNumber'] = $dispatch->getNumber();

        foreach(array_keys(Dispatch::DELIVERY_NOTE_DATA) as $deliveryNoteKey) {
            if(isset(Dispatch::DELIVERY_NOTE_DATA[$deliveryNoteKey])) {
                $value = $data[$deliveryNoteKey] ?? null;
                $dispatchDataToSave[$deliveryNoteKey] = $value;
                if(Dispatch::DELIVERY_NOTE_DATA[$deliveryNoteKey]) {
                    $userDataToSave[$deliveryNoteKey] = $value;
                }
            }
        }

        $maxNumberOfPacks = 10;
        $packs = array_slice($dispatch->getDispatchPacks()->toArray(), 0, $maxNumberOfPacks);
        $packs = array_map(function(DispatchPack $dispatchPack) {
            return [
                "code" => $dispatchPack->getPack()->getCode(),
                "quantity" => $dispatchPack->getQuantity(),
                "comment" => $dispatchPack->getPack()->getComment(),
            ];
        }, $packs);
        $dispatchDataToSave['packs'] = $packs;

        $loggedUser->setSavedDispatchDeliveryNoteData($userDataToSave);
        $dispatch->setDeliveryNoteData($dispatchDataToSave);

        $entityManager->flush();

        $deliveryNoteData = $dispatchService->getDeliveryNoteData($dispatch);

        $deliveryNoteAttachment = new Attachment();
        $deliveryNoteAttachment
            ->setDispatch($dispatch)
            ->setFileName($deliveryNoteData['file'])
            ->setOriginalName($deliveryNoteData['name'] . '.pdf');

        $entityManager->persist($deliveryNoteAttachment);
        $entityManager->flush();

        $detailsConfig = $dispatchService->createHeaderDetailsConfig($dispatch);

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le téléchargement de votre bon de livraison va commencer...',
            'headerDetailsConfig' => $this->renderView("dispatch/dispatch-show-header.html.twig", [
                'dispatch' => $dispatch,
                'showDetails' => $detailsConfig,
                'modifiable' => !$dispatch->getStatut() || $dispatch->getStatut()->isDraft(),
            ]),
            'attachmentId' => $deliveryNoteAttachment->getId()
        ]);
    }

    /**
     * @Route("/{dispatch}/delivery-note/{attachment}", name="print_delivery_note_dispatch", options={"expose"=true}, methods="GET")
     */
    public function printDeliveryNote(Dispatch $dispatch,
                                      DispatchService $dispatchService): Response {
        if(!$dispatch->getDeliveryNoteData()) {
            return $this->json([
                "success" => false,
                "msg" => 'Le bon de livraison n\'existe pas pour cet acheminement'
            ]);
        }

        $data = $dispatchService->getDeliveryNoteData($dispatch);

        return new PdfResponse($data['file'], "{$data['name']}.pdf");
    }

    /**
     * @Route("/{dispatch}/check-waybill", name="check_dispatch_waybill", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function checkWaybill(TranslationService $translation, Dispatch $dispatch): Response {
        if($dispatch->getDispatchPacks()->count() === 0) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translation->translate('Demande', 'Acheminements', 'Lettre de voiture', 'Des unités logistiques sont nécessaires pour générer une lettre de voiture', false) . '.'
            ]);
        } else {
            return new JsonResponse([
                "success" => true,
            ]);
        }
    }

    /**
     * @Route("/{dispatch}/api-waybill", name="api_dispatch_waybill", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function apiWaybill(Request $request,
                               EntityManagerInterface $entityManager,
                               SpecificService $specificService,
                               Dispatch $dispatch): JsonResponse {

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $settingRepository = $entityManager->getRepository(Setting::class);

        $userSavedData = $loggedUser->getSavedDispatchWaybillData();
        $dispatchSavedData = $dispatch->getWaybillData();

        $now = new DateTime('now');

        $isEmerson = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_EMERSON);

        $consignorUsername = $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_NAME);
        $consignorUsername = $consignorUsername !== null && $consignorUsername !== ''
            ? $consignorUsername
            : ($isEmerson ? $loggedUser->getUsername() : null);

        $consignorEmail = $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL);
        $consignorEmail = $consignorEmail !== null && $consignorEmail !== ''
            ? $consignorEmail
            : ($isEmerson ? $loggedUser->getEmail() : null);

        $defaultData = [
            'carrier' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CARRIER),
            'dispatchDate' => $now->format('Y-m-d'),
            'consignor' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONSIGNER),
            'receiver' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_RECEIVER),
            'locationFrom' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_LOCATION_FROM),
            'locationTo' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_LOCATION_TO),
            'consignorUsername' => $consignorUsername,
            'consignorEmail' => $consignorEmail,
            'receiverUsername' => $isEmerson ? $loggedUser->getUsername() : null,
            'receiverEmail' => $isEmerson ? $loggedUser->getEmail() : null
        ];

        $wayBillData = array_reduce(
            array_keys(Dispatch::WAYBILL_DATA),
            function(array $carry, string $dataKey) use ($request, $userSavedData, $dispatchSavedData, $defaultData) {
                $carry[$dataKey] = (
                    $dispatchSavedData[$dataKey]
                    ?? ($userSavedData[$dataKey]
                        ?? ($defaultData[$dataKey]
                            ?? null))
                );

                return $carry;
            },
            []
        );

        $html = $this->renderView('dispatch/modalPrintWayBillContent.html.twig', array_merge($wayBillData, [
            'packsCounter' => $dispatch->getDispatchPacks()->count()
        ]));

        return $this->json([
            "success" => true,
            "html" => $html
        ]);
    }

    /**
     * @Route("/{dispatch}/waybill", name="post_dispatch_waybill", options={"expose"=true}, condition="request.isXmlHttpRequest()", methods="POST")
     */
    public function postDispatchWaybill(EntityManagerInterface $entityManager,
                                        Dispatch               $dispatch,
                                        DispatchService        $dispatchService,
                                        Request                $request,
                                        TranslationService     $translationService): JsonResponse {

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = json_decode($request->getContent(), true);

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

        $loggedUser->setSavedDispatchWaybillData($userDataToSave);
        $dispatch->setWaybillData($dispatchDataToSave);
        $wayBillAttachment = $dispatchService->persistNewWaybillAttachment($entityManager, $dispatch);

        $entityManager->flush();

        $detailsConfig = $dispatchService->createHeaderDetailsConfig($dispatch);

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le téléchargement de votre lettre de voiture va commencer...',
            'entete' => $this->renderView("dispatch/dispatch-show-header.html.twig", [
                'dispatch' => $dispatch,
                'showDetails' => $detailsConfig,
                'modifiable' => !$dispatch->getStatut() || $dispatch->getStatut()->isDraft(),
            ]),
            'attachmentId' => $wayBillAttachment->getId()
        ]);
    }

    /**
     * @Route("/{dispatch}/waybill/{attachment}", name="print_waybill_dispatch", options={"expose"=true}, methods="GET")
     */
    public function printWaybillNote(Dispatch           $dispatch,
                                     TranslationService $translationService,
                                     DispatchService    $dispatchService): Response {
        if(!$dispatch->getWaybillData()) {
            return $this->json([
                "success" => false,
                "msg" => $translationService->translate('Demande', 'Acheminements', 'Lettre de voiture', 'La lettre de voiture n\'existe pas pour cet acheminement', false),
            ]);
        }

        $data = $dispatchService->getWaybillData($dispatch);

        return new PdfResponse($data['file'], "{$data['name']}.pdf");
    }

    /**
     * @Route("/bon-de-surconsommation/{dispatch}", name="generate_overconsumption_bill", options={"expose"=true}, methods="POST")
     */
    public function updateOverconsumption(EntityManagerInterface $entityManager,
                                          DispatchService $dispatchService,
                                          UserService $userService,
                                          Dispatch $dispatch): Response {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $overConsumptionBill = $settingRepository->getOneParamByLabel(Setting::DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS);
        if($overConsumptionBill) {
            $typeAndStatus = explode(';', $overConsumptionBill);
            $typeId = intval($typeAndStatus[0]);
            $statutsId = intval($typeAndStatus[1]);

            if ($dispatch->getType()->getId() === $typeId) {
                $untreatedStatus = $statutRepository->find($statutsId);
                $dispatch
                    ->setStatut($untreatedStatus);
                if (!$dispatch->getValidationDate()) {
                    $dispatch->setValidationDate(new DateTime('now'));
                }

                $entityManager->flush();
                $dispatchService->sendEmailsAccordingToStatus($dispatch, true);
            }
        }

        $overConsumptionBillData = $dispatchService->getOverconsumptionBillData($dispatch);

        $deliveryNoteAttachment = new Attachment();
        $deliveryNoteAttachment
            ->setDispatch($dispatch)
            ->setFileName($overConsumptionBillData['file'])
            ->setOriginalName($overConsumptionBillData['name'] . '.pdf');

        $entityManager->persist($deliveryNoteAttachment);
        $entityManager->flush();

        $detailsConfig = $dispatchService->createHeaderDetailsConfig($dispatch);
        $dispatchStatus = $dispatch->getStatut();

        return $this->json([
            'entete' => $this->renderView("dispatch/dispatch-show-header.html.twig", [
                'dispatch' => $dispatch,
                'showDetails' => $detailsConfig,
                'modifiable' => !$dispatch->getStatut() || $dispatch->getStatut()->isDraft(),
            ]),
           'modifiable' => (!$dispatchStatus || $dispatchStatus->isDraft()) && $userService->hasRightFunction(Menu::DEM, Action::MANAGE_PACK),
        ]);
    }

    /**
     * @Route("/bon-de-surconsommation/{dispatch}", name="print_overconsumption_bill", options={"expose"=true}, methods="GET")
     * @HasPermission({Menu::DEM, Action::GENERATE_OVERCONSUMPTION_BILL})
     */
    public function printOverconsumptionBill(Dispatch $dispatch,
                                             DispatchService $dispatchService): Response {

        $data = $dispatchService->getOverconsumptionBillData($dispatch);

        return new PdfResponse($data['file'], "{$data['name']}.pdf");
    }

    /**
     * @Route("/create-form-arrival-template", name="create_from_arrival_template", options={"expose"=true}, methods="POST")
     */
    public function createFromArrivalTemplate(Request $request, EntityManagerInterface $entityManager, DispatchService $dispatchService): JsonResponse {
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $arrivals = [];
        $arrival = null;
        if($request->query->has('arrivals')) {
            $arrivalsIds = $request->query->all('arrivals');
            $arrivals = $arrivageRepository->findBy(['id' => $arrivalsIds]);
        } else {
            $arrival = $arrivageRepository->find($request->query->get('arrival'));
        }

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);

        $packs = [];
        if(!empty($arrivals)) {
            foreach ($arrivals as $arrival) {
                $packs = array_merge(Stream::from($arrival->getPacks())->toArray(), $packs);
            }
        } else {
            $packs = $arrival->getPacks()->toArray();
        }

        return $this->json([
            'success' => true,
            'content' => $this->renderView('dispatch/modalNewDispatch.html.twig',
                $dispatchService->getNewDispatchConfig($entityManager, $types, $arrival, true, $packs)
            )
        ]);
    }

    /**
     * @Route("/get-dispatch-details", name="get_dispatch_details", options={"expose"=true}, methods="GET")
     */
    public function getDispatchDetails(Request $request, EntityManagerInterface $manager): JsonResponse {
        $id = $request->query->get('id');
        $dispatch = $manager->find(Dispatch::class, $id);

        if(!$dispatch) {
            return $this->json([
                'success' => true,
                'content' => '<div class="col-12"><i class="fas fa-exclamation-triangle mr-2"></i>Sélectionner un acheminement pour visualiser ses détails</div>',
            ]);
        }

        $freeFields = $manager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($dispatch->getType(), CategorieCL::DEMANDE_DISPATCH);

        return $this->json([
            'success' => true,
            'content' => $this->renderView('dispatch/details.html.twig', [
                'selectedDispatch' => $dispatch,
                'freeFields' => $freeFields
            ]),
        ]);
    }
}
