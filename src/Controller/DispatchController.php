<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\DispatchReferenceArticle;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fields\SubLineFieldsParam;
use App\Entity\FreeField;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\DispatchService;
use App\Service\FreeFieldService;
use App\Service\LanguageService;
use App\Service\NotificationService;
use App\Service\PackService;
use App\Service\RedirectService;
use App\Service\RefArticleDataService;
use App\Service\StatusHistoryService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

#[Route("/acheminements")]
class DispatchController extends AbstractController {

    #[Required]
    public UserService $userService;

    #[Required]
    public AttachmentService $attachmentService;

    #[Route("/", name: "dispatch_index")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE])]
    public function index(Request                   $request,
                          EntityManagerInterface    $entityManager,
                          DispatchService           $service): Response {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);

        $query = $request->query;
        $statusesFilter = $query->has('statuses') ? $query->all('statuses', '') : [];
        $typesFilter = $query->has('types') ? $query->all('types', '') : [];
        $fromDashboard = $query->has('fromDashboard') ? $query->get('fromDashboard') : '' ;

        if (!empty($statusesFilter)) {
            $statusesFilter = Stream::from($statusesFilter)
                ->map(fn($statusId) => $statutRepository->find($statusId)->getNom())
                ->toArray();
        }

        if (!empty($typesFilter)) {
            $typesFilter = Stream::from($typesFilter)
                ->map(fn($typeId) => $typeRepository->find($typeId)->getLabel())
                ->toArray();
        }

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $fields = $service->getVisibleColumnsConfig($entityManager, $currentUser);
        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_DISPATCH);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);

        return $this->render('dispatch/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, 'displayOrder'),
            'carriers' => $carrierRepository->findAllSorted(),
            'emergencies' => $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_EMERGENCY),
            'types' => Stream::from($types)
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $this->getFormatter()->type($type)
                ])
                ->toArray(),
            'fieldsParam' => $fieldsParam,
            'fields' => $fields,
            'modalNewConfig' => $service->getNewDispatchConfig($entityManager, $types),
            'statusFilter' => $statusesFilter,
            'typesFilter' => $typesFilter,
            'fromDashboard' => $fromDashboard,
            'dispatch' => new Dispatch(),
        ]);
    }

    #[Route("/api-columns", name: "dispatch_api_columns", options: ["expose" => true], methods: ["GET","POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE], mode: HasPermission::IN_JSON)]
    public function apiColumns(Request $request,
                               EntityManagerInterface $entityManager,
                               DispatchService $service): Response {
            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            $groupedSignatureMode = $request->query->getBoolean('groupedSignatureMode');
            $columns = $service->getVisibleColumnsConfig($entityManager, $currentUser, $groupedSignatureMode);

            return $this->json(array_values($columns));
    }

    #[Route("/colonne-visible", name: "save_column_visible_for_dispatch", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE], mode: HasPermission::IN_JSON)]
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

    #[Route("/autocomplete", name: "get_dispatch_numbers", options: ["expose" => true], methods: ["GET","POST"], condition: "request.isXmlHttpRequest()")]
    public function getDispatchAutoComplete(Request $request,
                                            EntityManagerInterface $entityManager): Response {
        $search = $request->query->get('term');

        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $results = $dispatchRepository->getDispatchNumbers($search);

        return $this->json(['results' => $results]);
    }

    #[Route("/api", name: "dispatch_api", options: ["expose" => true], methods: ["GET","POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE], mode: HasPermission::IN_JSON)]
    public function api(Request         $request,
                        DispatchService $dispatchService): Response
    {
        $groupedSignatureMode = $request->query->getBoolean('groupedSignatureMode');
        $fromDashboard = $request->query->getBoolean('fromDashboard');
        $preFilledStatuses = $request->query->has('preFilledStatuses')
            ? implode(",", $request->query->all('preFilledStatuses'))
            : [];
        $preFilledTypes = $request->query->has('preFilledTypes')
            ? implode(",", $request->query->all('preFilledTypes'))
            : [];

        $preFilledFilters = [
            [
                'field' => 'statut',
                'value' => $preFilledStatuses,
            ],
            [
                'field' => 'multipleTypes',
                'value' => $preFilledTypes,
            ]
        ];

        $data = $dispatchService->getDataForDatatable($request->request, $groupedSignatureMode, $fromDashboard, $preFilledFilters);

        return new JsonResponse($data);
    }

    #[Route("/creer", name: "dispatch_new", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::CREATE_ACHE])]
    public function new(Request                $request,
                        FreeFieldService       $freeFieldService,
                        DispatchService        $dispatchService,
                        AttachmentService      $attachmentService,
                        EntityManagerInterface $entityManager,
                        TranslationService     $translationService,
                        UniqueNumberService    $uniqueNumberService,
                        RedirectService        $redirectService,
                        StatusHistoryService   $statusHistoryService): Response {
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
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $transporterRepository = $entityManager->getRepository(Transporteur::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $preFill = $settingRepository->getOneParamByLabel(Setting::PREFILL_DUE_DATE_TODAY);
        $printDeliveryNote = $request->query->get('printDeliveryNote');

        $dispatch = new Dispatch();
        $date = new DateTime('now');
        $fileBag = $request->files->count() > 0 ? $request->files : null;
        if(isset($fileBag)) {
            $fileNames = [];
            foreach($fileBag->all() as $file) {
                $fileNames = array_merge(
                    $fileNames,
                    $attachmentService->saveFile($file)
                );
            }
            $attachments = $attachmentService->createAttachments($fileNames);
            foreach($attachments as $attachment) {
                $entityManager->persist($attachment);
                $dispatch->addAttachment($attachment);
            }
        }

        $post = $dispatchService->checkFormForErrors($entityManager, $post, $dispatch, true);

        $type = $typeRepository->find($post->get(FixedFieldStandard::FIELD_CODE_TYPE_DISPATCH));

        $locationTake = $post->get(FixedFieldStandard::FIELD_CODE_LOCATION_PICK)
            ? ($emplacementRepository->find($post->get(FixedFieldStandard::FIELD_CODE_LOCATION_PICK)) ?: $type->getPickLocation())
            : $type->getPickLocation();
        $locationDrop = $post->get(FixedFieldStandard::FIELD_CODE_LOCATION_DROP)
            ? ($emplacementRepository->find($post->get(FixedFieldStandard::FIELD_CODE_LOCATION_DROP)) ?: $type->getDropLocation())
            : $type->getDropLocation();

        $destination = $post->get(FixedFieldStandard::FIELD_CODE_DESTINATION);

        $comment = $post->get(FixedFieldStandard::FIELD_CODE_COMMENT_DISPATCH);
        $startDateRaw = $post->get(FixedFieldStandard::FIELD_CODE_START_DATE_DISPATCH);
        $endDateRaw = $post->get(FixedFieldStandard::FIELD_CODE_END_DATE_DISPATCH);
        $carrier = $post->get(FixedFieldStandard::FIELD_CODE_CARRIER_DISPATCH);
        $carrierTrackingNumber = $post->get(FixedFieldStandard::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH);
        $commandNumber = $post->get(FixedFieldStandard::FIELD_CODE_COMMAND_NUMBER_DISPATCH);
        $receivers = $post->get(FixedFieldStandard::FIELD_CODE_RECEIVER_DISPATCH);
        $emails = $post->get(FixedFieldStandard::FIELD_CODE_EMAILS);
        $emergency = $post->get(FixedFieldStandard::FIELD_CODE_EMERGENCY);
        $projectNumber = $post->get(FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER);
        $businessUnit = $post->get(FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT);
        $statusId = $post->get(FixedFieldStandard::FIELD_CODE_STATUS_DISPATCH);

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

        $requesterId = $post->get(FixedFieldStandard::FIELD_CODE_REQUESTER_DISPATCH);
        $requester = $requesterId ? $userRepository->find($requesterId) : null;
        $requester = $requester ?? $this->getUser();

        $currentUser = $this->getUser();
        $numberFormat = $settingRepository->getOneParamByLabel(Setting::DISPATCH_NUMBER_FORMAT);
        if(!in_array($numberFormat, Dispatch::NUMBER_FORMATS)) {
            throw new FormException("Le format de numéro d'acheminement n'est pas valide");
        }
        $dispatchNumber = $uniqueNumberService->create($entityManager, Dispatch::NUMBER_PREFIX, Dispatch::class, $numberFormat);
        $dispatch
            ->setCreationDate($date)
            ->setType($type)
            ->setRequester($requester)
            ->setLocationFrom($locationTake)
            ->setLocationTo($locationDrop)
            ->setBusinessUnit($businessUnit)
            ->setNumber($dispatchNumber)
            ->setDestination($destination)
            ->setCreatedBy($currentUser)
            ->setCustomerName($post->get(FixedFieldStandard::FIELD_CODE_CUSTOMER_NAME_DISPATCH))
            ->setCustomerPhone($post->get(FixedFieldStandard::FIELD_CODE_CUSTOMER_PHONE_DISPATCH))
            ->setCustomerRecipient($post->get(FixedFieldStandard::FIELD_CODE_CUSTOMER_RECIPIENT_DISPATCH))
            ->setCustomerAddress($post->get(FixedFieldStandard::FIELD_CODE_CUSTOMER_ADDRESS_DISPATCH));

        $statusHistoryService->updateStatus($entityManager, $dispatch, $status);

        if(!empty($comment) && $comment !== "<p><br></p>" ) {
            $dispatch->setCommentaire($comment);
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

        if(!empty($emails)) {
            $emails = explode("," , $emails);
            $dispatch->setEmails($emails);
        }

        if(!empty($receivers)) {
            $receiverIds = explode("," , $receivers);

            foreach ($receiverIds as $receiverId) {
                if (!empty($receiverId)) {
                    $receiver = $receiverId ? $userRepository->find($receiverId) : null;
                    if ($receiver) {
                        $dispatch->addReceiver($receiver);
                    }
                }
            }
        }

        if(!empty($emergency)) {
            $dispatch->setEmergency($post->get(FixedFieldStandard::FIELD_CODE_EMERGENCY));
        }

        if(!empty($projectNumber)) {
            $dispatch->setProjectNumber($projectNumber);
        }

        $freeFieldService->manageFreeFields($dispatch, $post->all(), $entityManager);

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
            $dispatchService->sendEmailsAccordingToStatus($entityManager, $dispatch, false);
        }

        return new JsonResponse([
            'success' => true,
            'redirect' => $redirectService->generateUrl("dispatch_show", [
                "id" => $dispatch->getId(),
                "print-delivery-note" => $printDeliveryNote ? '1' : '0',
            ]),
            'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'L\'acheminement a bien été créé', false)
        ]);
    }

    #[Route("/voir/{id}/{printBL}", name: "dispatch_show", options: ["expose" => true], defaults: ["printBL" => 0, "fromCreation" => 0])]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE])]
    public function show(Dispatch               $dispatch,
                         Request                $request,
                         EntityManagerInterface $entityManager,
                         DispatchService        $dispatchService,
                         UserService            $userService,
                         RefArticleDataService  $refArticleDataService): Response {

        $paramRepository = $entityManager->getRepository(Setting::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        $printBL = $request->query->getBoolean('printBL');

        $dispatchStatus = $dispatch->getStatut();
        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_DISPATCH);
        $freeFields = $entityManager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($dispatch->getType(), CategorieCL::DEMANDE_DISPATCH);

        $dispatchReferenceArticleAttachments = [];
        $addNewUlToDispatch = false;
        foreach ($dispatch->getDispatchPacks() as $dispatchPack) {
            foreach ($dispatchPack->getDispatchReferenceArticles() as $dispatchReferenceArticle) {
                $addNewUlToDispatch = true;
                foreach ($dispatchReferenceArticle->getAttachments() as $attachment)
                    $dispatchReferenceArticleAttachments[] = $attachment;
            }
        }

        $dispatchAttachments = $dispatch->getAttachments()
            ->map(fn(Attachment $attachment) => $attachment)
            ->toArray();

        $attachments = array_merge($dispatchAttachments, $dispatchReferenceArticleAttachments);

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
            'fieldsParam' => $fieldsParam,
            'freeFields' => $freeFields,
            "descriptionFormConfig" => $refArticleDataService->getDescriptionConfig($entityManager, true),
            "attachments" => $attachments,
            'addNewUlToDispatch' => $addNewUlToDispatch,
            "initial_visible_columns" => json_encode($dispatchService->getDispatckPacksColumnVisibleConfig($entityManager, true)),
        ]);
    }

    #[Route("/{dispatch}/dispatch-note", name: "dispatch_note", options: ["expose" => true], condition: "request.isXmlHttpRequest()")]
    public function postDispatchNote(Dispatch               $dispatch,
                                     DispatchService        $dispatchService,
                                     EntityManagerInterface $entityManager): Response
    {
        $dispatchNoteData = $dispatchService->getDispatchNoteData($dispatch);

        $dispatchNoteAttachment = new Attachment();
        $dispatchNoteAttachment
            ->setDispatch($dispatch)
            ->setFileName(uniqid() . '.pdf')
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

    #[Route("/{dispatch}/etat", name: "print_dispatch_state_sheet", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE])]
    public function printDispatchStateSheet(TranslationService $translationService,
                                            Dispatch           $dispatch,
                                            DispatchService    $dispatchService,
                                            AttachmentService  $attachmentService): ?Response
    {
        if($dispatch->getDispatchPacks()->isEmpty()) {
            return $this->json([
                "success" => false,
                "msg" => $translationService->translate('Demande', 'Acheminements', 'Bon d\'acheminement', 'Le bon d\'acheminement n\'existe pas pour cet acheminement', false)
            ]);
        }

        $data = $dispatchService->getDispatchNoteData($dispatch);

        $dispatchSheet = $dispatch->getAttachments()->last();

        $filePath = $attachmentService->createFile($dispatchSheet->getFileName(), $data['file']);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $dispatchSheet->getOriginalName());

        return $response;
    }

    #[Route("/modifier", name: "dispatch_edit", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function edit(Request                $request,
                         DispatchService        $dispatchService,
                         TranslationService     $translationService,
                         FreeFieldService       $freeFieldService,
                         EntityManagerInterface $entityManager): Response
    {
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $post = $request->request;
        /** @var Dispatch|null $dispatch */
        $dispatch = $post->get('id')
            ? $dispatchRepository->find($post->get('id'))
            : null;

        $now = new DateTime();

        if(!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT) ||
            $dispatch->getStatut()->isDraft() && !$this->userService->hasRightFunction(Menu::DEM, Action::EDIT_DRAFT_DISPATCH) ||
            $dispatch->getStatut()->isNotTreated() && !$this->userService->hasRightFunction(Menu::DEM, Action::EDIT_UNPROCESSED_DISPATCH)) {
            return $this->redirectToRoute('access_denied');
        }

        $listAttachmentIdToKeep = $post->all('files') ?: [];

        $attachments = $dispatch->getAttachments()->toArray();
        foreach($attachments as $attachment) {
            /** @var Attachment $attachment */
            if(!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $dispatch);
            }
        }

        $this->persistAttachments($dispatch, $this->attachmentService, $request, $entityManager);

        $post = $dispatchService->checkFormForErrors($entityManager, $post, $dispatch, false);

        $type = $dispatch->getType();


        $requesterData = $post->get(FixedFieldStandard::FIELD_CODE_REQUESTER_DISPATCH);
        $requester = $requesterData ? $utilisateurRepository->find($requesterData) : null;
        $requester = $requester ?? $dispatch->getRequester() ?? $this->getUser();

        if ($post->has(FixedFieldStandard::FIELD_CODE_LOCATION_PICK)) {
            $locationPickData = $post->get(FixedFieldStandard::FIELD_CODE_LOCATION_PICK);
            $locationPick = $locationPickData
                ? $emplacementRepository->find($locationPickData)
                : $type->getPickLocation();
            $dispatch->setLocationFrom($locationPick);
        }
        if ($post->has(FixedFieldStandard::FIELD_CODE_LOCATION_DROP)) {
            $locationDropData = $post->get(FixedFieldStandard::FIELD_CODE_LOCATION_DROP);
            $locationDrop = $locationDropData
                ? $emplacementRepository->find($locationDropData)
                : $type->getDropLocation();
            $dispatch->setLocationTo($locationDrop);
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_START_DATE_DISPATCH)) {
            $startDateRaw = $post->get(FixedFieldStandard::FIELD_CODE_START_DATE_DISPATCH);
            $startDate = !empty($startDateRaw) ? $dispatchService->createDateFromStr($startDateRaw) : null;
            $dispatch->setStartDate($startDate);
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_END_DATE_DISPATCH)) {
            $endDateRaw = $post->get(FixedFieldStandard::FIELD_CODE_END_DATE_DISPATCH);
            $endDate = !empty($endDateRaw) ? $dispatchService->createDateFromStr($endDateRaw) : null;
            $dispatch->setEndDate($endDate);
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_CARRIER_DISPATCH)) {
            $carrierId = $post->get(FixedFieldStandard::FIELD_CODE_CARRIER_DISPATCH);
            $carrier = $carrierId ? $carrierRepository->find($carrierId) : null;
            $dispatch->setCarrier($carrier);
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH)) {
            $dispatch->setCarrierTrackingNumber($post->get(FixedFieldStandard::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_COMMAND_NUMBER_DISPATCH)) {
            $dispatch->setCommandNumber($post->get(FixedFieldStandard::FIELD_CODE_COMMAND_NUMBER_DISPATCH));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT)) {
            $dispatch->setBusinessUnit($post->get(FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_DESTINATION)) {
            $dispatch->setDestination($post->get(FixedFieldStandard::FIELD_CODE_DESTINATION));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER)) {
            $dispatch->setProjectNumber($post->get(FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_COMMENT_DISPATCH)) {
            $dispatch->setCommentaire($post->get(FixedFieldStandard::FIELD_CODE_COMMENT_DISPATCH));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_EMERGENCY)) {
            $dispatch->setEmergency($post->get(FixedFieldStandard::FIELD_CODE_EMERGENCY));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_CUSTOMER_NAME_DISPATCH)) {
            $dispatch->setCustomerName($post->get(FixedFieldStandard::FIELD_CODE_CUSTOMER_NAME_DISPATCH));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_CUSTOMER_PHONE_DISPATCH)) {
            $dispatch->setCustomerPhone($post->get(FixedFieldStandard::FIELD_CODE_CUSTOMER_PHONE_DISPATCH));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_CUSTOMER_RECIPIENT_DISPATCH)) {
            $dispatch->setCustomerRecipient($post->get(FixedFieldStandard::FIELD_CODE_CUSTOMER_RECIPIENT_DISPATCH));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_CUSTOMER_RECIPIENT_DISPATCH)) {
            $dispatch->setCustomerAddress($post->get(FixedFieldStandard::FIELD_CODE_CUSTOMER_ADDRESS_DISPATCH));
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_EMAILS)) {
            $emails = Stream::explode(",", $post->get(FixedFieldStandard::FIELD_CODE_EMAILS) ?? '')
                ->filter()
                ->toArray();
            $dispatch->setEmails($emails);
        }

        if ($post->has(FixedFieldStandard::FIELD_CODE_RECEIVER_DISPATCH)) {
            $receiversIds = Stream::explode(",", $post->get(FixedFieldStandard::FIELD_CODE_RECEIVER_DISPATCH) ?: '')
                ->filter()
                ->toArray();
            $existingReceivers = $dispatch->getReceivers();
            foreach($existingReceivers as $receiver) {
                $dispatch->removeReceiver($receiver);
            }
            foreach ($receiversIds as $receiverId) {
                if (!empty($receiverId)) {
                    $receiver = $utilisateurRepository->find($receiverId);
                    if ($receiver) {
                        $dispatch->addReceiver($receiver);
                    }
                }
            }
        }

        $dispatch
            ->setUpdatedAt($now)
            ->setRequester($requester);

        $freeFieldService->manageFreeFields($dispatch, $post->all(), $entityManager);

        if(!$dispatch->getLocationTo() || !$dispatch->getLocationFrom()) {
            throw new FormException(
                $translationService->translate('Demande', 'Acheminements', 'Général', "Il n'y a aucun emplacement de prise ou de dépose paramétré pour ce type.Veuillez en paramétrer ou rendre les champs visibles à la création et/ou modification.", false)
            );
        }

        if ($dispatch->getStartDate()
            && $dispatch->getEndDate()
            && $dispatch->getStartDate() > $dispatch->getEndDate()) {
            throw new FormException(
                $translationService->translate('Demande', 'Acheminements', 'Général', "La date de fin d'échéance est inférieure à la date de début.", false)
            );
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => $translationService->translate('Demande', 'Acheminements', 'Général', 'L\'acheminement a bien été modifié', false) . '.'
        ]);
    }

    #[Route("/api-modifier", name: "dispatch_edit_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    public function editApi(Request                $request,
                            EntityManagerInterface $entityManager): Response {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $attachmentRepository = $entityManager->getRepository(Attachment::class);

        $fieldsParam = $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_DISPATCH);

        $dispatch = $dispatchRepository->find($request->query->get('id'));
        $dispatchStatus = $dispatch->getStatut();

        if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)
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

        $dispatchBusinessUnits = $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT);

        $form = $this->renderView('dispatch/forms/form.html.twig', [
            'dispatchBusinessUnits' => !empty($dispatchBusinessUnits) ? $dispatchBusinessUnits : [],
            'dispatch' => $dispatch,
            'fieldsParam' => $fieldsParam,
            'emergencies' => $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_EMERGENCY),
            'utilisateurs' => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
            'statuses' => $statuses,
            'attachments' => $attachmentRepository->findBy(['dispatch' => $dispatch])
        ]);

        return new JsonResponse([
            "success" => true,
            "html"=> $form,
        ]);
    }

    #[Route("/supprimer", name: "dispatch_delete", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
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

                $dispatchPacks = $dispatch->getDispatchPacks()->toArray();
                foreach($dispatchPacks as $dispatchPack) {
                    $dispatchReferenceArticles = $dispatchPack->getDispatchReferenceArticles()->toArray();
                    foreach($dispatchReferenceArticles as $dispatchReferenceArticle) {
                        $entityManager->remove($dispatchReferenceArticle);
                    }
                    $entityManager->remove($dispatchPack);
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

    private function persistAttachments(Dispatch $entity,
                                        AttachmentService $attachmentService,
                                        Request $request,
                                        EntityManagerInterface $entityManager): void {
        $attachments = $attachmentService->createAttachments($request->files);
        foreach($attachments as $attachment) {
            $entityManager->persist($attachment);
            $entity->addAttachment($attachment);
        }
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    #[Route("/dispatch-editable-logistic-unit-columns-api", name: "dispatch_editable_logistic_unit_columns_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE], mode: HasPermission::IN_JSON)]
    public function apiEditableLogisticUnitColumns(EntityManagerInterface $entityManager,
                                                   DispatchService $dispatchService): Response {
        $columns = $dispatchService->getDispatckPacksColumnVisibleConfig($entityManager);

        return $this->json(array_values($columns));
    }

    #[Route("/{dispatch}/editable-logistic-units-api", name: "dispatch_editable_logistic_units_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE], mode: HasPermission::IN_JSON)]
    public function apiEditableLogisticUnits(UserService     $userService,
                                             DispatchService $service,
                                             Dispatch        $dispatch): Response {
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
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT => null,
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME => null,
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT => null,
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_TRACKING_DATE => null,
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION => null,
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR => null,
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_STATUS => null,
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT => null,
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH => null,
                SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH => null,
            ];
        }
        return $this->json([
            "data" => $data
        ]);
    }

    #[Route("/{dispatch}/packs/new", name: "dispatch_new_pack", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function newPack(Request                $request,
                            TranslationService     $translationService,
                            EntityManagerInterface $entityManager,
                            PackService            $packService,
                            Dispatch               $dispatch): Response
    {
        $data = $request->request->all();
        $noPrefixPackCode = trim($data["pack"]);
        $natureId = $data["nature"];
        $quantity = $data["quantity"];
        $existing = isset($data["packID"]);
        $comment = $data["comment"] ?? "";
        $weight = (floatval(str_replace(',', '.', $data["weight"] ?? "")) ?: null);
        $volume = (floatval(str_replace(',', '.', $data["volume"] ?? "")) ?: null);
        $height = $data["height"] ?? null;
        $width = $data["width"] ?? null;
        $length = $data["length"] ?? null;

        $field = match (true) {
            $height !== null && !StringHelper::matchEvery($height, StringHelper::INTEGER_AND_DECIMAL_REGEX) => SubLineFieldsParam::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_HEIGHT,
            $width !== null && !StringHelper::matchEvery($width, StringHelper::INTEGER_AND_DECIMAL_REGEX) => SubLineFieldsParam::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_WIDTH,
            $length !== null && !StringHelper::matchEvery($length, StringHelper::INTEGER_AND_DECIMAL_REGEX) => SubLineFieldsParam::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_LENGTH,
            default => null,
        };

        if($field) {
            throw new FormException("La valeur du champ $field n'est pas valide (entier et décimal uniquement).");
        }

        $settingRepository = $entityManager->getRepository(Setting::class);

        $prefixPackCodeWithDispatchNumber = $settingRepository->getOneParamByLabel(Setting::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER);
        if($prefixPackCodeWithDispatchNumber && !str_starts_with($noPrefixPackCode, $dispatch->getNumber()) && !$existing) {
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
                ->firstOr(function() use ($existing, $packCode, $packRepository) {
                    if ($existing){
                        return $packRepository->find($packCode);
                    } else {
                        return $packRepository->findOneBy(["code" => $packCode]);
                    }
                });
        }

        $packMustBeNew = $settingRepository->getOneParamByLabel(Setting::PACK_MUST_BE_NEW);
        if($packMustBeNew && isset($pack)) {
            $isNotInDispatch = $dispatch->getDispatchPacks()
                ->filter(fn(DispatchPack $dispatchPack) => $dispatchPack->getPack() === $pack)
                ->isEmpty();

            if($isNotInDispatch) {
                return $this->json([
                    "success" => false,
                    "msg" => "L'unité logistique <strong>{$packCode}</strong> existe déjà en base de données"
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
            ->setDispatch($dispatch)
            ->setHeight($height !== null ? floatval($height) : $dispatchPack->getHeight())
            ->setWidth($width !== null ? floatval($width) : $dispatchPack->getWidth())
            ->setLength($length !== null ? floatval($length) : $dispatchPack->getLength());
        $entityManager->persist($dispatchPack);

        $nature = $natureRepository->find($natureId);
        $dispatchPack->setQuantity($quantity);
        $pack
            ->setNature($nature)
            ->setComment($comment)
            ->setWeight($weight ? round($weight, 3) : null)
            ->setVolume($volume ? round($volume, 6) : null);
        $dispatch->setUpdatedAt(new DateTime());
        $success = true;
        $packCode = $pack->getCode();
        $toTranslate = 'Le colis {1} a bien été ' . ($dispatchPack->getId() ? "modifiée" : "ajoutée");
        $message = $translationService->translate('Demande', 'Acheminements', 'Détails acheminement - Liste des unités logistiques', $toTranslate, [1 => "<strong>$packCode</strong>"]);

        $entityManager->flush();

        return $this->json([
            "success" => $success,
            "msg" => $message,
            "id" => $dispatchPack->getId(),
        ]);
    }

    #[Route("/packs/delete", name: "dispatch_delete_pack", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function deletePack(Request                $request,
                               TranslationService     $translationService,
                               EntityManagerInterface $entityManager): Response
    {
        if($data = json_decode($request->getContent(), true)) {
            $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);

            if($data['pack'] && $pack = $dispatchPackRepository->find($data['pack'])) {
                $entityManager->remove($pack);
                $pack->getDispatch()->setUpdatedAt(new DateTime());
                $entityManager->flush();
            }

            return $this->json([
                "success" => true,
                "msg" => $translationService->translate('Demande',"Acheminements", 'Détails acheminement - Liste des unités logistiques', "La ligne a bien été supprimée")
            ]);
        }

        throw new BadRequestHttpException();
    }

    #[Route("/{id}/validate", name: "dispatch_validate_request", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function validateDispatchRequest(Request                $request,
                                            EntityManagerInterface $entityManager,
                                            Dispatch               $dispatch,
                                            TranslationService     $translationService,
                                            DispatchService        $dispatchService,
                                            NotificationService    $notificationService,
                                            StatusHistoryService   $statusHistoryService): Response
    {
        $status = $dispatch->getStatut();

        if(!$status || $status->isDraft()) {
            $data = json_decode($request->getContent(), true);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $data['status'];
            $untreatedStatus = $statusRepository->find($statusId);


            if($untreatedStatus && $untreatedStatus->isNotTreated() && ($untreatedStatus->getType() === $dispatch->getType())) {
                try {
                    if($dispatch->getType() &&
                        ($dispatch->getType()->isNotificationsEnabled() || $dispatch->getType()->isNotificationsEmergency($dispatch->getEmergency()))) {
                        $notificationService->toTreat($dispatch);
                    }
                    $dispatch
                        ->setValidationDate(new DateTime('now'));

                    $statusHistoryService->updateStatus($entityManager, $dispatch, $untreatedStatus);
                    $entityManager->flush();
                    $dispatchService->sendEmailsAccordingToStatus($entityManager, $dispatch, true);
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

    #[Route("/{id}/treat", name: "dispatch_treat_request", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function treatDispatchRequest(Request                $request,
                                         EntityManagerInterface $entityManager,
                                         DispatchService        $dispatchService,
                                         Dispatch               $dispatch,
                                         TranslationService     $translationService): Response
    {
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

    #[Route("/{dispatch}/packs-counter", name: "get_dispatch_packs_counter", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    public function getDispatchPackCounter(Dispatch $dispatch): Response {
        return new JsonResponse([
            'success' => true,
            'packsCounter' => $dispatch->getDispatchPacks()->count()
        ]);
    }

    #[Route("/{dispatch}/rollback-draft", name: "rollback_draft", methods: "GET")]
    public function rollbackToDraftStatus(EntityManagerInterface $entityManager,
                                          Dispatch               $dispatch,
                                          StatusHistoryService   $statusHistoryService): Response
    {
        $dispatchType = $dispatch->getType();
        $statusRepository = $entityManager->getRepository(Statut::class);

        $draftStatus = $statusRepository->findOneBy([
            'type' => $dispatchType,
            'state' => 0
        ]);

        $statusHistoryService->updateStatus($entityManager, $dispatch, $draftStatus);
        $entityManager->flush();

        return $this->redirectToRoute('dispatch_show', [
            'id' => $dispatch->getId()
        ]);
    }

    #[Route("/csv", name: "get_dispatches_csv", options: ["expose" => true], methods: "GET")]
    public function getDispatchesCSV(Request                $request,
                                     DispatchService        $dispatchService,
                                     FreeFieldService       $freeFieldService,
                                     CSVExportService       $CSVExportService,
                                     EntityManagerInterface $entityManager,
                                     DataExportService      $dataExportService): Response
    {

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $request->query->get('dateMin') . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $request->query->get('dateMax') . ' 23:59:59');

        if($dateTimeMin && $dateTimeMax) {
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $userDateFormat = $this->getUser()->getDateFormat();
            $dispatches = $dispatchRepository->getByDates($dateTimeMin, $dateTimeMax, $userDateFormat);

            $freeFieldsById = Stream::from($dispatches)
                ->keymap(fn($dispatch) => [
                    $dispatch['id'], $dispatch['freeFields']
                ])->toArray();

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_DISPATCH]);
            $headers = $dataExportService->createDispatchesHeader($freeFieldsConfig);

            return $CSVExportService->streamResponse(
                function ($output) use ($dispatches, $CSVExportService, $dispatchService, $freeFieldsConfig, $freeFieldsById) {
                    foreach ($dispatches as $dispatch) {
                        $dispatchService->putDispatchLine($output, $dispatch, $freeFieldsConfig, $freeFieldsById);
                    }
                },
                'export_acheminements.csv',
                $headers
            );
        } else {
            throw new BadRequestHttpException();
        }
    }

    #[Route("/{dispatch}/api-delivery-note", name: "api_delivery_note_dispatch", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    public function apiDeliveryNote(Request                $request,
                                    TranslationService     $translationService,
                                    EntityManagerInterface $manager,
                                    Dispatch               $dispatch): JsonResponse
    {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        $maxNumberOfPacks = 10;

        if($dispatch->getDispatchPacks()->count() === 0) {
            $errorMessage = $translationService->translate('Demande', 'Acheminements', 'Bon de livraison', 'Des unités logistiques sont nécessaires pour générer un bon de livraison', false) . '.';

            return $this->json([
                "success" => false,
                "msg" => $errorMessage
            ]);
        }

        $packs = array_slice($dispatch->getDispatchPacks()->toArray(), 0, $maxNumberOfPacks);
        $packs = array_map(function(DispatchPack $dispatchPack) {
            return [
                "code" => $dispatchPack->getPack()->getCode(),
                "quantity" => $dispatchPack->getQuantity(),
                "comment" => $dispatchPack->getPack()->getComment(),
            ];
        }, $packs);

        $userSavedData = $loggedUser->getSavedDispatchDeliveryNoteData();
        $dispatchSavedData = $dispatch->getDeliveryNoteData();
        $defaultData = [
            'deliveryNumber' => $dispatch->getNumber(),
            'projectNumber' => $dispatch->getProjectNumber(),
            'username' => $loggedUser->getUsername(),
            'userPhone' => $loggedUser->getPhone(),
            'packs' => $packs,
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

        $fieldsParamRepository = $manager->getRepository(FixedFieldStandard::class);

        $html = $this->renderView('dispatch/modalPrintDeliveryNoteContent.html.twig', array_merge($deliveryNoteData, [
            'dispatchEmergencyValues' => $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_EMERGENCY),
            'fromDelivery' => $request->query->getBoolean('fromDelivery'),
            'dispatch' => $dispatch->getId(),
        ]));

        return $this->json([
            "success" => true,
            "html" => $html
        ]);
    }

    #[Route("/delivery-note", name: "delivery_note_dispatch", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function postDeliveryNote(EntityManagerInterface $entityManager,
                                     DispatchService        $dispatchService,
                                     Request                $request): JsonResponse
    {

        $loggedUser = $this->getUser();

        $data = $request->request->all();
        $dispatch = $entityManager->find(Dispatch::class, $data['dispatch']);

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
            ->setFileName(uniqid() . '.pdf')
            ->setOriginalName($deliveryNoteData['name'] . '.pdf');

        $entityManager->persist($deliveryNoteAttachment);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le téléchargement de votre bon de livraison va commencer...',
            'attachmentId' => $deliveryNoteAttachment->getId()
        ]);
    }

    #[Route("/{dispatch}/delivery-note/{attachment}", name: "print_delivery_note_dispatch", options: ["expose" => true], methods: "GET")]
    public function printDeliveryNote(TranslationService $translationService,
                                      Dispatch           $dispatch,
                                      DispatchService    $dispatchService,
                                      AttachmentService  $attachmentService): Response
    {
        if(!$dispatch->getDeliveryNoteData()) {
            return $this->json([
                "success" => false,
                "msg" => $translationService->translate('Demande', 'Acheminements', 'Bon de livraison', 'Le bon de livraison n\'existe pas pour cet acheminement', false)
            ]);
        }

        $data = $dispatchService->getDeliveryNoteData($dispatch);

        $deliveryNote = $dispatch->getAttachments()->last();

        $filePath = $attachmentService->createFile($deliveryNote->getFileName(), $data['file']);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $deliveryNote->getOriginalName());

        return $response;
    }

    #[Route("/{dispatch}/check-waybill", name: "check_dispatch_waybill", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
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

    #[Route("/{dispatch}/api-waybill", name: "api_dispatch_waybill", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    public function apiWaybill(EntityManagerInterface $entityManager,
                               DispatchService        $dispatchService,
                               Dispatch               $dispatch): JsonResponse
    {

        $dispatchData = $dispatchService->getWayBillDataForUser($this->getUser(), $entityManager, $dispatch);

        $html = $this->renderView('dispatch/modalPrintWayBillContent.html.twig', $dispatchData);

        return $this->json([
            "success" => true,
            "html" => $html
        ]);
    }

    #[Route("/{dispatch}/waybill", name: "post_dispatch_waybill", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function postDispatchWaybill(EntityManagerInterface $entityManager,
                                        Dispatch               $dispatch,
                                        DispatchService        $dispatchService,
                                        Request                $request): JsonResponse {

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $wayBillAttachment = $dispatchService->generateWayBill($loggedUser, $dispatch, $entityManager, $data);
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

    #[Route("/{dispatch}/waybill/{attachment}", name: "print_waybill_dispatch", options: ["expose" => true], methods: "GET")]
    public function printWaybillNote(Dispatch           $dispatch,
                                     TranslationService $translationService,
                                     KernelInterface    $kernel,
                                     Attachment         $attachment): Response {
        if(!$dispatch->getWaybillData()) {
            return $this->json([
                "success" => false,
                "msg" => $translationService->translate('Demande', 'Acheminements', 'Lettre de voiture', 'La lettre de voiture n\'existe pas pour cet acheminement', false),
            ]);
        }

        $response = new BinaryFileResponse(($kernel->getProjectDir() . '/public/uploads/attachments/' . $attachment->getFileName()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName());
        return $response;
    }

    #[Route("/bon-de-surconsommation/{dispatch}", name: "generate_overconsumption_bill", options: ["expose" => true], methods: "POST")]
    public function updateOverconsumption(EntityManagerInterface $entityManager,
                                          DispatchService        $dispatchService,
                                          UserService            $userService,
                                          Dispatch               $dispatch,
                                          StatusHistoryService   $statusHistoryService): Response
    {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $overConsumptionBill = $settingRepository->getOneParamByLabel(Setting::DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS);
        if($overConsumptionBill) {
            $typeAndStatus = explode(';', $overConsumptionBill);
            $typeId = intval($typeAndStatus[0]);
            $statutsId = intval($typeAndStatus[1]);

            if ($dispatch->getType()->getId() === $typeId) {
                $untreatedStatus = $statutRepository->find($statutsId);
                $statusHistoryService->updateStatus($entityManager, $dispatch, $untreatedStatus);
                if (!$dispatch->getValidationDate()) {
                    $dispatch->setValidationDate(new DateTime('now'));
                }

                $entityManager->flush();
                $dispatchService->sendEmailsAccordingToStatus($entityManager, $dispatch, true);
            }
        }

        $dispatchStatus = $dispatch->getStatut();
        return $this->json([
           'modifiable' => (!$dispatchStatus || $dispatchStatus->isDraft()) && $userService->hasRightFunction(Menu::DEM, Action::MANAGE_PACK),
        ]);
    }

    #[Route("/bon-de-surconsommation/{dispatch}", name: "print_overconsumption_bill", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::GENERATE_OVERCONSUMPTION_BILL])]
    public function printOverconsumptionBill(Dispatch        $dispatch,
                                             DispatchService $dispatchService,
                                             AttachmentService $attachmentService): Response
    {

        $data = $dispatchService->getOverconsumptionBillData($dispatch);

        $overConsumptionBill = $dispatch->getAttachments()->last();

        $filePath = $attachmentService->createFile($overConsumptionBill->getFileName(), $data['file']);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $overConsumptionBill->getOriginalName());

        return $response;
    }

    #[Route("/create-form-arrival-template", name: "create_from_arrival_template", options: ["expose" => true], methods: "GET")]
    public function createFromArrivalTemplate(Request                $request,
                                              EntityManagerInterface $entityManager,
                                              DispatchService        $dispatchService): JsonResponse
    {
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

        Stream::from($packs)
            ->map(fn(Pack $pack) => $pack->getId())
            ->toArray();

        return $this->json([
            'success' => true,
            'html' => $this->renderView('dispatch/forms/formFromArrival.html.twig',
                $dispatchService->getNewDispatchConfig($entityManager, $types, $arrival, true, $packs),
            )
        ]);
    }

    #[Route("/get-dispatch-details", name: "get_dispatch_details", options: ["expose" => true], methods: "GET")]
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

    #[Route("/{dispatch}/status-history-api", name: "dispatch_status_history_api", options: ['expose' => true], methods: "GET")]
    public function statusHistoryApi(Dispatch        $dispatch,
                                     LanguageService $languageService): JsonResponse {
        $user = $this->getUser();
        return $this->json([
            "success" => true,
            "template" => $this->renderView('dispatch/status-history.html.twig', [
                "userLanguage" => $user->getLanguage(),
                "defaultLanguage" => $languageService->getDefaultLanguage(),
                "statusesHistory" => Stream::from($dispatch->getStatusHistory())
                    ->map(fn(StatusHistory $statusHistory) => [
                        "status" => $this->getFormatter()->status($statusHistory->getStatus()),
                        "date" => $languageService->getCurrentUserLanguageSlug() === Language::FRENCH_SLUG
                            ? $this->getFormatter()->longDate($statusHistory->getDate(), ["short" => true, "time" => true])
                            : $this->getFormatter()->datetime($statusHistory->getDate(), "", false, $user),
                    ])
                    ->toArray(),
                "dispatch" => $dispatch,
            ]),
        ]);
    }

    #[Route("/grouped-signature-modal-content", name: "grouped_signature_modal_content", options: ["expose" => true], methods: "GET")]
    public function getGroupedSignatureModalContent(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);

        $filteredStatut = $statusRepository->find($request->query->get('statusId'));

        $dispatchIdsToSign = $request->query->all('dispatchesToSign');
        $dispatchesToSign = Stream::from($dispatchIdsToSign
            ? $dispatchRepository->findBy(["id" => $dispatchIdsToSign])
            : []);

        if ($dispatchesToSign->isEmpty()) {
            throw new FormException("Vous devez sélectionner des acheminements pour réaliser une signature groupée");
        }

        $dispatchTypes = $dispatchesToSign
            ->filterMap(fn(Dispatch $dispatch) => $dispatch->getType())
            ->keymap(fn(Type $type) => [$type->getId(), $type])
            ->reindex();

        if ($dispatchTypes->count() !== 1) {
            throw new FormException("Vous ne pouvez sélectionner qu'un seul type d'acheminement pour réaliser une signature groupée");
        }

        $states = match ($filteredStatut->getState()) {
            Statut::DRAFT => [Statut::NOT_TREATED],
            Statut::NOT_TREATED, Statut::PARTIAL =>  [Statut::TREATED, Statut::PARTIAL],
            default => []
        };
        $dispatchStatusesForSelect = $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatchTypes->first(), $states);

        $formattedStatusToDisplay = Stream::from($dispatchStatusesForSelect)
            ->map(fn(Statut $status) => [
                "label" => $this->getFormatter()->status($status),
                "value" => $status->getId(),
                "needed-comment" => $status->getCommentNeeded(),
            ])
            ->toArray();

        return $this->json([
            'success' => true,
            'content' => $this->renderView('dispatch/modalGroupedSignature.html.twig', [
                'dispatchStatusesForSelect' => $formattedStatusToDisplay
            ])
        ]);
    }

    #[Route("/finish-grouped-signature", name: "finish_grouped_signature", options: ["expose" => true], methods: "POST")]
    public function finishGroupedSignature(Request                $request,
                                           EntityManagerInterface $entityManager,
                                           DispatchService        $dispatchService): Response {

        $locationData = [
            'from' => $request->query->get('from') === "null" ? null : $request->query->get('from'),
            'to' => $request->query->get('to') === "null" ? null : $request->query->get('to'),
        ];
        $signatoryTrigramData = $request->request->get("signatoryTrigram");
        $signatoryPasswordData = $request->request->get("signatoryPassword");
        $statusData = $request->request->get("status");
        $commentData = $request->request->get("comment");
        $dispatchesToSignIds = $request->query->all('dispatchesToSign');

        $response = $dispatchService->finishGroupedSignature(
            $entityManager,
            $locationData,
            $signatoryTrigramData,
            $signatoryPasswordData,
            $statusData,
            $commentData,
            $dispatchesToSignIds,
            false,
            $this->getUser()
        );

        $entityManager->flush();
        return $this->json($response);
    }

    #[Route("/{dispatch}/dispatch-reference-in-logistic-units-api", name: "dispatch_reference_in_logistic_units_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE], mode: HasPermission::IN_JSON)]
    public function apiReferenceInLogisticUnits(EntityManagerInterface $entityManager,
                                                Dispatch               $dispatch,
                                                Request                $request): JsonResponse {

        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);

        $start = 0;
        $search = $request->query->get('search') ?: null;

        $listLength = $dispatch->getDispatchPacks()->count();

        $result = $dispatchPackRepository->getByDispatch($dispatch, [
            "start" => $start,
            "length" => $listLength,
            "search" => $search,
        ]);

        return $this->json([
            "success" => true,
            "html" => $this->renderView("dispatch/line-list.html.twig", [
                "dispatch" => $dispatch,
                "dispatchPacks" => $result["data"],
                "total" => $result["total"],
                "current" => $start,
                "currentPage" => $listLength === 0 ? 1 : floor($start / $listLength),
                "pageLength" => $listLength,
                "pagesCount" => $listLength === 0 ? 1 : ceil($result["total"] / $listLength),
            ]),
        ]);
    }

    #[Route("/form-reference", name:"dispatch_form_reference", options: ['expose' => true], methods: "POST")]
    #[HasPermission([Menu::DEM, Action::ADD_REFERENCE_IN_LU], mode: HasPermission::IN_JSON)]
    public function formReference(Request                $request,
                                  EntityManagerInterface $entityManager,
                                  DispatchService        $dispatchService): JsonResponse
    {
        $data = $request->request->all();
        $data['files'] = $request->files ?? [];

        return $dispatchService->updateDispatchReferenceArticle($entityManager, $data);
    }

    #[Route("/delete-reference/{dispatchReferenceArticle}", name:"dispatch_delete_reference", options: ['expose' => true], methods: "DELETE")]
    #[HasPermission([Menu::DEM, Action::ADD_REFERENCE_IN_LU], mode: HasPermission::IN_JSON)]
    public function deleteReference(DispatchReferenceArticle $dispatchReferenceArticle,
                                    EntityManagerInterface   $entityManager): JsonResponse
    {
        $dispatchPack = $dispatchReferenceArticle->getDispatchPack();
        $dispatchReferenceArticle->getDispatchPack()->getDispatch()->setUpdatedAt(new DateTime());
        $dispatchPack->removeDispatchReferenceArticles($dispatchReferenceArticle);
        $entityManager->remove($dispatchReferenceArticle);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl("dispatch_show", ['id' => $dispatchPack->getDispatch()->getId()]),
            'msg' => 'Référence supprimée',
        ]);
    }

    #[Route("/edit-reference-api/{dispatchReferenceArticle}", name:"dispatch_edit_reference_api", options: ['expose' => true], methods: "POST")]
    #[HasPermission([Menu::DEM, Action::ADD_REFERENCE_IN_LU], mode: HasPermission::IN_JSON)]
    public function editReferenceApi(DispatchReferenceArticle $dispatchReferenceArticle,
                                     RefArticleDataService    $refArticleDataService,
                                     EntityManagerInterface   $entityManager): JsonResponse
    {
        $natureRepository = $entityManager->getRepository(Nature::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);

        $refDispatchPack = $dispatchReferenceArticle->getDispatchPack();
        $dispatch = $dispatchReferenceArticle->getDispatchPack()->getDispatch();

        $dispatchPacks = $dispatchPackRepository->findBy(['dispatch' => $dispatch]);
        $packs = Stream::from($dispatchPacks)
            ->map(fn(DispatchPack $dispatchPack) => [
                "label" => $dispatchPack->getPack()->getCode(),
                "value" => $dispatchPack->getPack()->getId()
            ])
            ->toArray();

        $natures = $natureRepository->findBy([], ['label' => 'ASC']);
        $natureItems = Stream::from($natures)
            ->map(fn(Nature $nature) => [
                "label" => $nature->getLabel(),
                "value" => $nature->getId()
            ])
            ->toArray();

        $html = $this->renderView('dispatch/modalFormReferenceContent.html.twig', [
            'dispatch' => $dispatch,
            'dispatchReferenceArticle' => $dispatchReferenceArticle,
            'pack' => $refDispatchPack->getPack(),
            'descriptionConfig' => $refArticleDataService->getDescriptionConfig($entityManager, true),
            'natures' => $natureItems,
            'packs' => $packs,
        ]);

        return new JsonResponse($html);
    }

    #[Route("/add-reference-api/{dispatch}/{pack}", name: "dispatch_add_reference_api", options: ['expose' => true], defaults: ['pack' => null], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::ADD_REFERENCE_IN_LU], mode: HasPermission::IN_JSON)]
    public function addReferenceApi(Dispatch               $dispatch,
                                    ?Pack                  $pack,
                                    RefArticleDataService  $refArticleDataService,
                                    EntityManagerInterface $entityManager): JsonResponse
    {
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $dispatchPacks = $dispatchPackRepository->findBy(['dispatch' => $dispatch]);

        if(count($dispatchPacks) === 0) {
            return $this->json([
                'success' => false,
                'msg' => "Vous devez renseigner au moins une unité logistique pour pouvoir ajouter une référence."
            ]);
        }

        $packs = [];
        foreach ($dispatchPacks as $dispatchPack) {
            $packs[] = [
                "value" => $dispatchPack->getPack()->getId(),
                "label" => $dispatchPack->getPack()->getCode(),
                "default-quantity" => $dispatchPack->getQuantity()
            ];
        }

        $html = $this->renderView('dispatch/modalFormReferenceContent.html.twig', [
            'dispatch' => $dispatch,
            'descriptionConfig' => $refArticleDataService->getDescriptionConfig($entityManager, true),
            'packs' => $packs,
            'pack' => $pack,
        ]);

        return new JsonResponse([
            'success' => true,
            'template' => $html,
        ]);
    }

    #[Route("/add-logistic-unit-api/{dispatch}", name: "dispatch_add_logistic_unit_api", options: ['expose' => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::ADD_REFERENCE_IN_LU], mode: HasPermission::IN_JSON)]
    public function addLogisticUnitApi(Dispatch               $dispatch,
                                       EntityManagerInterface $entityManager): JsonResponse
    {
        $natureRepository = $entityManager->getRepository(Nature::class);
        $defaultNature = $natureRepository->findOneBy(['defaultNature' => true]);

        $html = $this->renderView('dispatch/modalAddLogisticUnitContent.html.twig', [
            'dispatch' => $dispatch,
            'nature' => $defaultNature
        ]);

        return new JsonResponse($html);
    }

    #[Route("/etiquette/{dispatch}", name: "print_dispatch_label", options: ['expose' => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::GENERATE_DISPATCH_LABEL])]
    public function printDispatchLabel(Dispatch          $dispatch,
                                       DispatchService   $dispatchService,
                                       AttachmentService $attachmentService): Response
    {
        $data = $dispatchService->getDispatchLabelData($dispatch);

        $dispatchLabel = $dispatch->getAttachments()->last();

        $filePath = $attachmentService->createFile($dispatchLabel->getFileName(), $data['file']);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $dispatchLabel->getOriginalName());

        return $response;
    }
}
