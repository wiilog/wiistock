<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\DispatchReferenceArticle;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fields\SubLineFixedField;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\ProductionRequest;
use App\Entity\Setting;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Transporteur;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\DispatchService;
use App\Service\FreeFieldService;
use App\Service\LanguageService;
use App\Service\NotificationService;
use App\Service\ProductionRequest\ProductionRequestService;
use App\Service\RedirectService;
use App\Service\RefArticleDataService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\StatusService;
use App\Service\Tracking\PackService;
use App\Service\Tracking\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\UserService;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

#[Route("/acheminements")]
class DispatchController extends AbstractController {

    #[Required]
    public UserService $userService;

    #[Route("/", name: "dispatch_index")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE])]
    public function index(Request                   $request,
                          EntityManagerInterface    $entityManager,
                          StatusService             $statusService,
                          TranslationService        $translationService,
                          DispatchService           $service): Response {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $categoryTypeRepository = $entityManager->getRepository(CategoryType::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $query = $request->query;
        $statusesFilter = $query->has('statuses') ? $query->all('statuses') : [];
        $typesFilter = $query->has('types') ? $query->all('types') : [];
        $pickLocationsFilter = $query->has('pickLocations') ? $query->all('pickLocations') : [];
        $dropLocationsFilter = $query->has('dropLocations') ? $query->all('dropLocations') : [];
        $emergenciesFilter = $query->has('dispatchEmergencies') ? $query->all('dispatchEmergencies') : [];
        $fromDashboard = $query->has('fromDashboard') ? $query->get('fromDashboard') : '' ;

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        if (!empty($statusesFilter)) {
            $statusesFilter = Stream::from($statutRepository->findBy(['id' => $statusesFilter]))
                ->map(fn(Statut $status) => $status->getId())
                ->toArray();
        }

        if (!empty($typesFilter)) {
            $typesFilter = Stream::from($typeRepository->findBy(['id' => $typesFilter]))
                ->filterMap(fn(Type $type) => $type->getLabelIn($currentUser->getLanguage()))
                ->toArray();
        }

        if (!empty($pickLocationsFilter)) {
            $pickLocationsFilter = Stream::from($locationRepository->findBy(['id' => $pickLocationsFilter]));
        }

        if (!empty($dropLocationsFilter)) {
            $dropLocationsFilter = Stream::from($locationRepository->findBy(['id' => $dropLocationsFilter]));
        }

        $fields = $service->getVisibleColumnsConfig($entityManager, $currentUser);
        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH], null, [
            'idsToFind' => $currentUser->getDispatchTypeIds(),
        ]);

        $dispatchCategoryType = $categoryTypeRepository->findOneBy(['label' => CategoryType::DEMANDE_DISPATCH]);

        $dateChoices = FiltreSup::DATE_CHOICE_VALUES[Dispatch::class];

        $dispatchEmergenciesForFilter = $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_EMERGENCY);

        $dispatchStatuses = $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, 'displayOrder');
        $statuses = Stream::from($dispatchStatuses)
            ->filter(function (Statut $statut) use ($currentUser) {
                return empty($currentUser->getDispatchTypeIds())
                    || (($currentUser->getDispatchTypeIds()) && in_array($statut->getType()->getId(), $currentUser->getDispatchTypeIds()));
            })
            ->toArray();

        return $this->render('dispatch/index.html.twig', [
            'statuses' => $statuses,
            'carriers' => $carrierRepository->findAllSorted(),
            'emergencies' => [$translationService->translate('Demande', 'Général', 'Non urgent', false), ...$dispatchEmergenciesForFilter],
            'dateChoices' => $dateChoices,
            'types' => Stream::from($types)
                ->map(fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $this->getFormatter()->type($type)
                ])
                ->toArray(),
            'fields' => $fields,
            'statusStateValues' => Stream::from($statusService->getStatusStatesValues())
                ->reduce(function(array $carry, $test) {
                    $carry[$test['id']] = $test['label'];
                    return $carry;
                }, []),
            'modalNewConfig' => $service->getNewDispatchConfig($entityManager, $types),
            'statusFilter' => $statusesFilter,
            'typesFilter' => $typesFilter,
            'pickLocationsFilter' => $pickLocationsFilter,
            'dropLocationsFilter' => $dropLocationsFilter,
            'emergenciesFilter' => $emergenciesFilter,
            'fromDashboard' => $fromDashboard,
            'dispatch' => new Dispatch(),
            'defaultType' => $typeRepository->findOneBy(['category' => $dispatchCategoryType, 'defaultType' => true]),
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
    public function api(Request                $request,
                        DispatchService        $dispatchService,
                        EntityManagerInterface $entityManager): Response {
        $groupedSignatureMode = $request->query->getBoolean('groupedSignatureMode');
        $fromDashboard = $request->query->getBoolean('fromDashboard');

        $hasRightGroupedSignature = $this->userService->hasRightFunction(Menu::DEM, Action::GROUPED_SIGNATURE);

        if ($fromDashboard) {
            $preFilledStatuses = $request->query->has('filterStatus')
                ? implode(",", $request->query->all('filterStatus'))
                : [];
            $preFilledTypes = $request->query->has('preFilledTypes')
                ? implode(",", $request->query->all('preFilledTypes'))
                : [];
            $preFilledPickLocations = $request->query->has('pickLocationFilter')
                ? implode(",", $request->query->all('pickLocationFilter'))
                : [];
            $preFilledDropLocations = $request->query->has('dropLocationFilter')
                ? implode(",", $request->query->all('dropLocationFilter'))
                : [];
            $preFilledEmergency = $request->query->has('emergencyFilter')
                ? implode(",", $request->query->all('emergencyFilter'))
                : [];

            $preFilledFilters = [
                [
                    'field' => $hasRightGroupedSignature ? 'statut' : 'statuses-filter',
                    'value' => $preFilledStatuses,
                ],
                [
                    'field' => FiltreSup::FIELD_MULTIPLE_TYPES,
                    'value' => $preFilledTypes,
                ],
                ...(!empty($preFilledPickLocations) ? [[
                    'field' => FiltreSup::FIELD_LOCATION_PICK_WITH_GROUPS,
                    'value' => $preFilledPickLocations,
                ]] : []),
                ...(!empty($preFilledDropLocations) ? [[
                    'field' => FiltreSup::FIELD_LOCATION_DROP_WITH_GROUPS,
                    'value' => $preFilledDropLocations,
                ]] : []),
                ...(!empty($preFilledEmergency) ? [[
                    'field' => FiltreSup::FIELD_EMERGENCY_MULTIPLE,
                    'value' => $preFilledEmergency,
                ]] : []),
            ];
        }

        $data = $dispatchService->getDataForDatatable($entityManager, $request->request, $groupedSignatureMode, $fromDashboard, $preFilledFilters ?? []);

        return new JsonResponse($data);
    }

    #[Route("/creer", name: "dispatch_new", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::DEM, Action::CREATE_ACHE])]
    public function new(Request                  $request,
                        FreeFieldService         $freeFieldService,
                        DispatchService          $dispatchService,
                        AttachmentService        $attachmentService,
                        EntityManagerInterface   $entityManager,
                        SettingsService          $settingsService,
                        TranslationService       $translationService,
                        UniqueNumberService      $uniqueNumberService,
                        RedirectService          $redirectService,
                        StatusHistoryService     $statusHistoryService,
                        ProductionRequestService $productionRequestService): JsonResponse {
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

        $productionIds = $post->get('production');

        if($post->getBoolean('existingOrNot')) {
            $existingDispatch = $entityManager->find(Dispatch::class, $post->getInt('existingDispatch'));
            $dispatchService->manageDispatchPacks($existingDispatch, $packs, $entityManager);

            if($productionIds){
                $productionRequestService->linkProductionsAndDispatch($entityManager, json_decode($productionIds) ?? [], $existingDispatch);
            }

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

        $preFill = $settingsService->getValue($entityManager, Setting::PREFILL_DUE_DATE_TODAY);

        $printDeliveryNote = $request->query->get('printDeliveryNote');

        $dispatch = new Dispatch();
        $date = new DateTime('now');

        $currentUser = $this->getUser();
        $type = $typeRepository->find($post->get(FixedFieldStandard::FIELD_CODE_TYPE_DISPATCH));
        if (!empty($currentUser->getDispatchTypeIds())
            && (
                !$type->isActive()
                || !in_array($type->getId(), $currentUser->getDispatchTypeIds())
            )
        ) {
            throw new FormException("Veuillez rendre ce type actif ou le mettre dans les types de votre utilisateur avant de pouvoir l'utiliser.");
        }

        $post = $dispatchService->checkFormForErrors($entityManager, $post, $dispatch, true, $type);
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

        $numberFormat = $settingsService->getValue($entityManager, Setting::DISPATCH_NUMBER_FORMAT);
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

        if($productionIds){
            $productionRequestService->linkProductionsAndDispatch($entityManager, json_decode($productionIds) ?? [], $dispatch);
        }

        $statusHistoryService->updateStatus($entityManager, $dispatch, $status, [
            "initiatedBy" => $currentUser
        ]);

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
                    $receiver = $userRepository->find($receiverId);
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

        $attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $dispatch]);

        $entityManager->persist($dispatch);

        try {
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
                         SettingsService        $settingsService,
                         UserService            $userService,
                         RefArticleDataService  $refArticleDataService): Response {

        $natureRepository = $entityManager->getRepository(Nature::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);

        $printBL = $request->query->getBoolean('printBL');

        $dispatchStatus = $dispatch->getStatut();
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
        $dispatchType = $dispatch->getType();

        $fieldsParam = Stream::from($fixedFieldByTypeRepository->findBy([
            "entityCode" => FixedFieldStandard::ENTITY_CODE_DISPATCH,
            "fieldCode" => [FixedFieldStandard::FIELD_CODE_COMMENT_DISPATCH, FixedFieldStandard::FIELD_CODE_ATTACHMENTS_DISPATCH]
        ]))
            ->keymap(static fn(FixedFieldByType $field) => [$field->getFieldCode(), [
                FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT => $field->isDisplayedEdit($dispatchType),
                FixedFieldByType::ATTRIBUTE_REQUIRED_EDIT => $field->isRequiredEdit($dispatchType),
            ]])
            ->toArray();

        $hasRightToRollbackDraft = ($dispatch->getStatut()->isNotTreated() && !$dispatch->getStatut()->isDraft())
            && count($statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::DRAFT])) > 0;

        return $this->render('dispatch/show.html.twig', [
            'dispatch' => $dispatch,
            'fieldsParam' => $fieldsParam,
            'detailsConfig' => $dispatchService->createHeaderDetailsConfig($entityManager, $dispatch),
            'modifiable' => (!$dispatchStatus || $dispatchStatus->isDraft()) && $userService->hasRightFunction(Menu::DEM, Action::MANAGE_PACK),
            'newPackConfig' => [
                'natures' => $natureRepository->findBy([], ['label' => 'ASC'])
            ],
            'dispatchValidate' => [
                'untreatedStatus' => Stream::from($statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::NOT_TREATED]))
                    ->filter(static fn(Statut $status) => (!$dispatch->getType()->hasReusableStatuses() && !$dispatchService->statusIsAlreadyUsedInDispatch($dispatch, $status)) ||  $dispatch->getType()->hasReusableStatuses())
                    ->toArray(),
            ],
            'dispatchTreat' => [
                'treatedStatus' => Stream::from($statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::TREATED, Statut::PARTIAL]))
                    ->filter(static fn(Statut $status) => (!$dispatch->getType()->hasReusableStatuses() && !$dispatchService->statusIsAlreadyUsedInDispatch($dispatch, $status)) ||  $dispatch->getType()->hasReusableStatuses())
                    ->toArray(),
            ],
            'hasRightToRollbackDraft' => $hasRightToRollbackDraft,
            'printBL' => $printBL,
            'prefixPackCodeWithDispatchNumber' => $settingsService->getValue($entityManager, Setting::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER),
            'newPackRow' => $dispatchService->packRow($entityManager, $dispatch, null, true, true),
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

        $detailsConfig = $dispatchService->createHeaderDetailsConfig($entityManager, $dispatch);

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
                         EntityManagerInterface $entityManager,
                         AttachmentService      $attachmentService): Response
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

        if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)
            || ($dispatch->getStatut()->isDraft() && !$this->userService->hasRightFunction(Menu::DEM, Action::EDIT_DRAFT_DISPATCH))
            || ($dispatch->getStatut()->isNotTreated() && !$this->userService->hasRightFunction(Menu::DEM, Action::EDIT_UNPROCESSED_DISPATCH))
        ) {
            return $this->redirectToRoute('access_denied');
        }

        if ($post->getBoolean("isAttachmentForm")) {
            $attachmentService->removeAttachments($entityManager, $dispatch, $post->all('files') ?: []);
            $attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $dispatch]);
        }

        $type = $dispatch->getType();
        $post = $dispatchService->checkFormForErrors($entityManager, $post, $dispatch, false, $type);

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
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $attachmentRepository = $entityManager->getRepository(Attachment::class);

        $dispatch = $dispatchRepository->find($request->query->get('id'));
        $dispatchStatus = $dispatch->getStatut();
        $dispatchType = $dispatch->getType();

        $fieldsParam = Stream::from($fixedFieldByTypeRepository->findBy(["entityCode" => FixedFieldStandard::ENTITY_CODE_DISPATCH]))
            ->keymap(static fn(FixedFieldByType $field) => [$field->getFieldCode(), [
                FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT => $field->isDisplayedEdit($dispatchType),
                FixedFieldByType::ATTRIBUTE_REQUIRED_EDIT => $field->isRequiredEdit($dispatchType),
            ]])
            ->toArray();

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

        $dispatchBusinessUnits = $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT);

        $form = $this->renderView('dispatch/forms/form.html.twig', [
            'dispatchBusinessUnits' => !empty($dispatchBusinessUnits) ? $dispatchBusinessUnits : [],
            'dispatch' => $dispatch,
            'fieldsParam' => $fieldsParam,
            'emergencies' => $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_EMERGENCY),
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


    #[Route("/dispatch-editable-logistic-unit-columns-api", name: "dispatch_editable_logistic_unit_columns_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE], mode: HasPermission::IN_JSON)]
    public function apiEditableLogisticUnitColumns(EntityManagerInterface $entityManager,
                                                   DispatchService $dispatchService): Response {
        $columns = $dispatchService->getDispatckPacksColumnVisibleConfig($entityManager);

        return $this->json(array_values($columns));
    }

    #[Route("/{dispatch}/editable-logistic-units-api", name: "dispatch_editable_logistic_units_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE], mode: HasPermission::IN_JSON)]
    public function apiEditableLogisticUnits(UserService            $userService,
                                             DispatchService        $service,
                                             Dispatch               $dispatch,
                                             EntityManagerInterface $entityManager): Response {
        $dispatchStatus = $dispatch->getStatut();
        $edit = (
            $dispatchStatus->isDraft()
            && $userService->hasRightFunction(Menu::DEM, Action::MANAGE_PACK)
        );

        $data = [];
        foreach($dispatch->getDispatchPacks() as $dispatchPack) {
            $data[] = $service->packRow($entityManager, $dispatch, $dispatchPack, false, $edit);
        }
        if($edit) {
            if(empty($data)) {
                $data[] = $service->packRow($entityManager, $dispatch, null, true, true);
            }
            $data[] = [
                'createRow' => true,
                "actions" => "<span class='d-flex justify-content-start align-items-center'><span class='wii-icon wii-icon-plus'></span></span>",
                "code" => null,
                "quantity" => null,
                "nature" => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_ACTION_DATE => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_STATUS => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH => null,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH => null,
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
                            SettingsService        $settingsService,
                            PackService            $packService,
                            DispatchService        $dispatchService,
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
        $now = new DateTime();

        $field = match (true) {
            $height !== null && !StringHelper::matchEvery($height, StringHelper::INTEGER_AND_DECIMAL_REGEX) => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_HEIGHT,
            $width !== null && !StringHelper::matchEvery($width, StringHelper::INTEGER_AND_DECIMAL_REGEX) => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_WIDTH,
            $length !== null && !StringHelper::matchEvery($length, StringHelper::INTEGER_AND_DECIMAL_REGEX) => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_LENGTH,
            default => null,
        };

        if($field) {
            throw new FormException("La valeur du champ $field n'est pas valide (entier et décimal uniquement).");
        }


        $natureRepository = $entityManager->getRepository(Nature::class);
        $nature = $natureRepository->find($natureId);

        // check if nature is allowed
        $naturesAllowed = $natureRepository->findByAllowedForms([Nature::DISPATCH_CODE]);
        if(!in_array($nature, $naturesAllowed)){
            throw new FormException("La nature n'est pas autorisée pour ce type d'acheminement.");
        }


        $prefixPackCodeWithDispatchNumber = $settingsService->getValue($entityManager, Setting::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER);
        if($prefixPackCodeWithDispatchNumber && !str_starts_with($noPrefixPackCode, $dispatch->getNumber()) && !$existing) {
            $packCode = "{$dispatch->getNumber()}-$noPrefixPackCode";
        } else {
            $packCode = $noPrefixPackCode;
        }

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

        $packMustBeNew = $settingsService->getValue($entityManager, Setting::PACK_MUST_BE_NEW);
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
            $pack = $packService->createPack($entityManager, ['code' => $packCode], $this->getUser());
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

        $packService->persistLogisticUnitHistoryRecord($entityManager, $pack, [
            "message" => $this->formatService->list($dispatchService->serialize($dispatch)),
            "historyDate" => $now,
            "user" => $dispatch->getRequester(),
            "type" => "Acheminement",
            "location" => $dispatch->getLocationFrom(),
        ]);

        $dispatchPack->setQuantity($quantity);
        $pack
            ->setNature($nature)
            ->setComment($comment)
            ->setWeight($weight ? round($weight, 3) : null)
            ->setVolume($volume ? round($volume, 6) : null);
        $dispatch->setUpdatedAt($now);
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

    #[Route("/packs/{pack}/delete", name: "dispatch_delete_pack", options: ["expose" => true], methods: self::DELETE, condition: self::IS_XML_HTTP_REQUEST)]
    public function deletePack(DispatchPack           $pack,
                               TranslationService     $translationService,
                               EntityManagerInterface $entityManager): Response {
        $entityManager->remove($pack);
        $pack->getDispatch()->setUpdatedAt(new DateTime());
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => $translationService->translate('Demande',"Acheminements", 'Détails acheminement - Liste des unités logistiques', "La ligne a bien été supprimée")
        ]);
    }

    #[Route("/{id}/validate", name: "dispatch_validate_request", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    public function validateDispatchRequest(Request                 $request,
                                            EntityManagerInterface  $entityManager,
                                            Dispatch                $dispatch,
                                            SettingsService         $settingsService,
                                            TranslationService      $translationService,
                                            DispatchService         $dispatchService,
                                            NotificationService     $notificationService,
                                            StatusHistoryService    $statusHistoryService,
                                            TrackingMovementService $trackingMovementService,
                                            AttachmentService       $attachmentService): Response {
        $status = $dispatch->getStatut();
        $now = new DateTime('now');

        if(!$status || $status->isDraft()) {
            $payload = $request->request;
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $payload->get('status');
            $untreatedStatus = $statusRepository->find($statusId);

            if($untreatedStatus && $untreatedStatus->isNotTreated() && ($untreatedStatus->getType() === $dispatch->getType())) {
                try {
                    if(!$dispatch->getType()->hasReusableStatuses() && $dispatchService->statusIsAlreadyUsedInDispatch($dispatch, $untreatedStatus)){
                        throw new FormException("Ce statut a déjà été utilisé pour cette demande.");
                    }

                    $dispatch
                        ->setValidationDate($now)
                        ->setCommentaire($payload->get(FixedFieldEnum::comment->name));

                    $alreadySavedFiles = $payload->has('files')
                        ? $payload->all('files')
                        : [];

                    $attachmentService->removeAttachments($entityManager, $dispatch, $alreadySavedFiles);
                    $attachmentService->manageAttachments($entityManager, $dispatch, $request->files);

                    $user = $this->getUser();
                    $statusHistoryService->updateStatus($entityManager, $dispatch, $untreatedStatus, [
                        "initiatedBy" => $user
                    ]);

                    $automaticallyCreateMovementOnValidation = (bool) $settingsService->getValue($entityManager, Setting::AUTOMATICALLY_CREATE_MOVEMENT_ON_VALIDATION);
                    if ($automaticallyCreateMovementOnValidation) {
                        $automaticallyCreateMovementOnValidationTypes = explode(',', $settingsService->getValue($entityManager, Setting::AUTOMATICALLY_CREATE_MOVEMENT_ON_VALIDATION_TYPES));
                        if(in_array($dispatch->getType()->getId(), $automaticallyCreateMovementOnValidationTypes)) {
                            foreach ($dispatch->getDispatchPacks() as $dispatchPack) {
                                $pack = $dispatchPack->getPack();
                                $trackingMovement = $trackingMovementService->createTrackingMovement(
                                    $pack,
                                    $dispatch->getLocationFrom(),
                                    $user,
                                    $now,
                                    false,
                                    false,
                                    TrackingMovement::TYPE_DEPOSE,
                                    [
                                        'from' => $dispatch,
                                        "quantity" => $dispatchPack->getQuantity(),
                                    ]
                                );
                                $entityManager->persist($trackingMovement);
                            }
                        }
                    }

                    $entityManager->flush();
                    $dispatchService->sendEmailsAccordingToStatus($entityManager, $dispatch, true);

                    if ($dispatch->getStatut()?->getNeedsMobileSync()
                        && (
                            $dispatch->getType()?->isNotificationsEnabled()
                            || $dispatch->getType()?->isNotificationsEmergency($dispatch->getEmergency())
                        )) {
                        $notificationService->toTreat($dispatch);
                    }
                } catch (Exception) {
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
                                         TranslationService     $translationService,
                                         AttachmentService      $attachmentService): Response
    {
        $status = $dispatch->getStatut();

        if(!$status || $status->isNotTreated() || $status->isPartial()) {
            $payload = $request->request;
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $payload->get('status');
            $treatedStatus = $statusRepository->find($statusId);

            if($treatedStatus
                && ($treatedStatus->isTreated() || $treatedStatus->isPartial())
                && $treatedStatus->getType() === $dispatch->getType()) {

                if(!$dispatch->getType()->hasReusableStatuses() && $dispatchService->statusIsAlreadyUsedInDispatch($dispatch, $treatedStatus)){
                    throw new FormException("Ce statut a déjà été utilisé pour cette demande.");
                }

                /** @var Utilisateur $loggedUser */
                $loggedUser = $this->getUser();
                $dispatchService->treatDispatchRequest($entityManager, $dispatch, $treatedStatus, $loggedUser, false, null, $payload->get(FixedFieldEnum::comment->name));

                $alreadySavedFiles = $payload->has('files')
                    ? $payload->all('files')
                    : [];

                if ($payload->has(FixedFieldStandard::FIELD_CODE_ATTACHMENTS_DISPATCH)) {
                    $attachmentService->removeAttachments($entityManager, $dispatch, $alreadySavedFiles);
                    $attachmentService->manageAttachments($entityManager, $dispatch, $request->files);
                }

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

        $statusHistoryService->clearStatusHistory($entityManager, $dispatch);

        $statusHistoryService->updateStatus($entityManager, $dispatch, $draftStatus, [
            "initiatedBy" => $this->getUser()
        ]);

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
                                     EntityManagerInterface $entityManager): Response
    {

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $request->query->get('dateMin') . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $request->query->get('dateMax') . ' 23:59:59');

        if($dateTimeMin && $dateTimeMax) {
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $user = $this->getUser();
            $userDateFormat = $user->getDateFormat();
            $dispatches = $dispatchRepository->getByDates($dateTimeMin, $dateTimeMax, $userDateFormat);

            $freeFieldsById = Stream::from($dispatches)
                ->keymap(fn($dispatch) => [
                    $dispatch['id'], $dispatch['freeFields']
                ])->toArray();

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_DISPATCH]);

            $exportableColumns = $dispatchService->getDispatchExportableColumns($entityManager);
            $headers = Stream::from($exportableColumns)
                ->map(fn(array $column) => $column['label'] ?? '')
                ->toArray();

            // same order than header column
            $exportableColumnCodes = Stream::from($exportableColumns)
                ->map(fn(array $column) => $column['code'] ?? '')
                ->toArray();

            return $CSVExportService->streamResponse(
                function ($output) use ($dispatches, $CSVExportService, $dispatchService, $exportableColumnCodes, $freeFieldsConfig, $freeFieldsById, $user) {
                    foreach ($dispatches as $dispatch) {
                        $dispatchService->putDispatchLine($output, $dispatch, $exportableColumnCodes, $freeFieldsConfig, $freeFieldsById, $user);
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

        $deliveryNoteData = $dispatchService->getDeliveryNoteData($entityManager, $dispatch);

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
    public function printDeliveryNote(TranslationService     $translationService,
                                      Dispatch               $dispatch,
                                      DispatchService        $dispatchService,
                                      AttachmentService      $attachmentService,
                                      EntityManagerInterface $entityManager): Response {
        if(!$dispatch->getDeliveryNoteData()) {
            return $this->json([
                "success" => false,
                "msg" => $translationService->translate('Demande', 'Acheminements', 'Bon de livraison', 'Le bon de livraison n\'existe pas pour cet acheminement', false)
            ]);
        }

        $data = $dispatchService->getDeliveryNoteData($entityManager, $dispatch);

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

        $detailsConfig = $dispatchService->createHeaderDetailsConfig($entityManager, $dispatch);

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
        $statutRepository = $entityManager->getRepository(Statut::class);

        $dispatchStatuses = $statutRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType());
        $overConsumptionBillStatus = Stream::from($dispatchStatuses)
            ->filter(static fn(Statut $status) => $status->getOverconsumptionBillGenerationStatus());

        if($overConsumptionBillStatus->count() === 1) {
            $untreatedStatus = $statutRepository->find($overConsumptionBillStatus->first());
            $statusHistoryService->updateStatus($entityManager, $dispatch, $untreatedStatus, [
                "initiatedBy" => $this->getUser(),
            ]);
            if (!$dispatch->getValidationDate()) {
                $dispatch->setValidationDate(new DateTime('now'));
            }

            $entityManager->flush();
            $dispatchService->sendEmailsAccordingToStatus($entityManager, $dispatch, true);
        }

        $dispatchStatus = $dispatch->getStatut();
        return $this->json([
            'modifiable' => (!$dispatchStatus || $dispatchStatus->isDraft()) && $userService->hasRightFunction(Menu::DEM, Action::MANAGE_PACK),
            "initialVisibleColumns" => json_encode($dispatchService->getDispatckPacksColumnVisibleConfig($entityManager, true)),
        ]);
    }

    #[Route("/bon-de-surconsommation/{dispatch}", name: "print_overconsumption_bill", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::GENERATE_OVERCONSUMPTION_BILL])]
    public function printOverconsumptionBill(Dispatch               $dispatch,
                                             DispatchService        $dispatchService,
                                             AttachmentService      $attachmentService,
                                             EntityManagerInterface $entityManager): Response {
        $data = $dispatchService->getOverconsumptionBillData($entityManager, $dispatch);

        $overConsumptionBill = $dispatch->getAttachments()->last();

        $filePath = $attachmentService->createFile($overConsumptionBill->getFileName(), $data['file']);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $overConsumptionBill->getOriginalName());

        return $response;
    }

    #[Route("/create-form-entities-template", name: "create_from_entities_template", options: ["expose" => true], methods: self::GET)]
    public function createFromEntitiesTemplate(Request                $request,
                                               EntityManagerInterface $entityManager,
                                               DispatchService        $dispatchService): JsonResponse
    {
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $productionRepository = $entityManager->getRepository(ProductionRequest::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $entityIds = $request->query->all('entityIds');
        $entityType = $request->query->get('entityType');

        $entities = [];
        $packs = [];
        if(!empty($entityIds)){
           switch($entityType) {
                case 'arrivals':
                    $entities = $arrivageRepository->findBy(['id' => $entityIds]);
                    $packs = Stream::from($entities)
                        ->flatMap(static fn(Arrivage $arrival) => $arrival->getPacks()->toArray())
                        ->toArray();
                    break;
                case 'productions':
                    $entities = $productionRepository->findBy(['id' => $entityIds]);
                    $packs = Stream::from($entities)
                        ->flatMap(static fn(ProductionRequest $productionRequest) => $productionRequest->getLastTracking()
                            ? [$productionRequest->getLastTracking()->getPack()]
                            : []
                        )
                        ->toArray();
                    break;
                default:
                    break;
            }
        }

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH], null, [
            'idsToFind' => $this->getUser()->getDispatchTypeIds(),
        ]);

        $defaultType = null;

        if(count($entities) === 1 && $entities[0] instanceof ProductionRequest) {
            /** @var ProductionRequest $productionRequest */
            $defaultType = $entities[0]->getStatus()->getTypeForGeneratedDispatchOnStatusChange();
        }

        $response = [
            'success' => true,
            'html' => $this->renderView('dispatch/forms/formFromEntity.html.twig',
                $dispatchService->getNewDispatchConfig(
                    $entityManager,
                    $types,
                    count($entities) === 1 ? $entities[0] : null,
                    $packs,
                    $entityIds,
                ),
            ),
        ];

        if ($defaultType !== null) {
            $response['defaultTypeId'] = $defaultType->getId();
        }

        return $this->json($response);
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
                                  DispatchService        $dispatchService): JsonResponse {
        $dispatchReferenceArticleId = $request->request->get('dispatchReferenceArticle') ?? null;

        $dispatchService->persistDispatchReferenceArticle($entityManager, $request);

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => $dispatchReferenceArticleId ? 'Référence et UL modifiées' : 'Référence ajoutée'
        ]);
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
                                     SettingsService          $settingsService,
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

        $associatedDocumentTypesStr = $settingsService->getValue($entityManager, Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES);
        $associatedDocumentTypes = $associatedDocumentTypesStr
            ? Stream::explode(',', $associatedDocumentTypesStr)
                ->filter()
                ->map(fn($associatedDocumentType) => [
                    "label" => $associatedDocumentType,
                    "value" => $associatedDocumentType,
                    "selected" => in_array($associatedDocumentType, $dispatchReferenceArticle->getAssociatedDocumentTypes()),
                ])
                ->toArray()
            : [];

        $html = $this->renderView('dispatch/modalFormReferenceContent.html.twig', [
            'dispatch' => $dispatch,
            'dispatchReferenceArticle' => $dispatchReferenceArticle,
            'pack' => $refDispatchPack->getPack(),
            'descriptionConfig' => $refArticleDataService->getDescriptionConfig($entityManager, true),
            'natures' => $natureItems,
            'packs' => $packs,
            'associatedDocumentTypes' => $associatedDocumentTypes,
        ]);

        return new JsonResponse($html);
    }

    #[Route("/add-reference-api/{dispatch}/{pack}", name: "dispatch_add_reference_api", options: ['expose' => true], defaults: ['pack' => null], methods: "GET")]
    #[HasPermission([Menu::DEM, Action::ADD_REFERENCE_IN_LU], mode: HasPermission::IN_JSON)]
    public function addReferenceApi(Dispatch               $dispatch,
                                    ?Pack                  $pack,
                                    RefArticleDataService  $refArticleDataService,
                                    SettingsService        $settingsService,
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

        $associatedDocumentTypesStr = $settingsService->getValue($entityManager, Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES);
        $associatedDocumentTypes = $associatedDocumentTypesStr
            ? Stream::explode(',', $associatedDocumentTypesStr)
                ->filter()
                ->map(fn($associatedDocumentType) => [
                    "label" => $associatedDocumentType,
                    "value" => $associatedDocumentType,
                ])
                ->toArray()
            : [];

        $html = $this->renderView('dispatch/modalFormReferenceContent.html.twig', [
            'dispatch' => $dispatch,
            'descriptionConfig' => $refArticleDataService->getDescriptionConfig($entityManager, true),
            'packs' => $packs,
            'pack' => $pack,
            'associatedDocumentTypes' => $associatedDocumentTypes,
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
    public function printDispatchLabel(Dispatch                 $dispatch,
                                       DispatchService          $dispatchService,
                                       EntityManagerInterface   $entityManager,
                                       AttachmentService        $attachmentService): Response {
        $data = $dispatchService->getDispatchLabelData($dispatch, $entityManager);

        $dispatchLabel = $dispatch->getAttachments()->last();

        $filePath = $attachmentService->createFile($dispatchLabel->getFileName(), $data['file']);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $dispatchLabel->getOriginalName());

        return $response;
    }

    #[Route("/bon-de-transport/{dispatch}",  name: "generate_shipment_note", options: ["expose" => true], methods: [self::POST])]
    #[HasPermission([Menu::DEM, Action::GENERATE_SHIPMENT_NOTE])]
    public function generateShipmentNote(Dispatch $dispatch,
                                         EntityManagerInterface $entityManager,
                                         DispatchService $dispatchService): Response {


        $dispatchService->generateShipmentNoteAttachment($entityManager, $dispatch);
        $entityManager->flush();

        return new JsonResponse([
            "success" => true,
            "msg" => "Le téléchargement de votre bon de transport va commencer...",
        ]);
    }

    #[Route("/bon-de-transport/{dispatch}", name: "print_shipment_note", options: ['expose' => true], methods: [self::GET])]
    #[HasPermission([Menu::DEM, Action::GENERATE_SHIPMENT_NOTE])]
    public function printShipmentNote(Dispatch                 $dispatch,
                                      KernelInterface          $kernel): Response
    {
        $dispatchShipmentNote = $dispatch->getAttachments()->last();

        $filePath = $kernel->getProjectDir() . '/public/uploads/attachments/' . $dispatchShipmentNote->getFileName();
        $binaryFileResponse = new BinaryFileResponse($filePath);
        $binaryFileResponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $dispatchShipmentNote->getOriginalName());

        return $binaryFileResponse;
    }
}
