<?php

namespace App\Controller;

use App\Annotation\HasPermission;
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
use App\Entity\ParametrageGlobal;
use App\Entity\Attachment;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Service\NotificationService;
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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

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
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $fields = $service->getVisibleColumnsConfig($entityManager, $currentUser);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);

        return $this->render('dispatch/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, 'displayOrder'),
            'carriers' => $carrierRepository->findAllSorted(),
            'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY),
            'types' => $types,
            'fieldsParam' => $fieldsParam,
            'fields' => $fields,
            'modalNewConfig' => $service->getNewDispatchConfig($statutRepository, $champLibreRepository, $fieldsParamRepository,
                $parametrageGlobalRepository, $types)
        ]);
    }

    /**
     * @Route("/api-columns", name="dispatch_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_ACHE}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(Request $request, EntityManagerInterface $entityManager, DispatchService $service): Response {
            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            $columns = $service->getVisibleColumnsConfig($entityManager, $currentUser);

            return $this->json(array_values($columns));
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_dispatch", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_ACHE}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request $request, EntityManagerInterface $entityManager): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $currentUser->setColumnsVisibleForDispatch($fields);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Vos préférences de colonnes ont bien été sauvegardées."
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
     * @throws Exception
     */
    public function new(Request $request,
                        FreeFieldService $freeFieldService,
                        DispatchService $dispatchService,
                        AttachmentService $attachmentService,
                        EntityManagerInterface $entityManager,
                        TranslatorInterface $translator,
                        UniqueNumberService $uniqueNumberService,
                        RedirectService $redirectService): Response {
        if(!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE) ||
            !$this->userService->hasRightFunction(Menu::DEM, Action::CREATE_ACHE)) {
            return $this->redirectToRoute('access_denied');
        }

        $post = $request->request;
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $transporterRepository = $entityManager->getRepository(Transporteur::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $preFill = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::PREFILL_DUE_DATE_TODAY);
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
        $packs = $post->get('packs');

        if(!$locationTake || !$locationDrop) {
            return new JsonResponse([
                'success' => false,
                'msg' => (
                    'Il n\'y a aucun emplacement de prise ou de dépose paramétré pour ce type.' .
                    'Veuillez en paramétrer ou rendre les champs visibles à la création et/ou modification.'
                )
            ]);
        }

        $startDate = !empty($startDateRaw) ? $dispatchService->createDateFromStr($startDateRaw) : null;
        $endDate = !empty($endDateRaw) ? $dispatchService->createDateFromStr($endDateRaw) : null;

        if($startDate && $endDate && $startDate > $endDate) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'La date de fin d\'échéance est inférieure à la date de début.'
            ]);
        }

        $dispatchNumber = $uniqueNumberService->createUniqueNumber($entityManager, Dispatch::PREFIX_NUMBER, Dispatch::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);
        $dispatch
            ->setCreationDate($date)
            ->setStatut($statutRepository->find($post->get('status')))
            ->setType($type)
            ->setRequester($utilisateurRepository->find($post->get('requester')))
            ->setLocationFrom($locationTake)
            ->setLocationTo($locationDrop)
            ->setBusinessUnit($businessUnit)
            ->setNumber($dispatchNumber)
            ->setDestination($destination);

        if(!empty($comment)) {
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

        if($packs) {
            $packs = json_decode($packs, true);
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

        $entityManager->persist($dispatch);

        try {
            $entityManager->persist($dispatch);
            $entityManager->flush();
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch(UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translator->trans('acheminement.Une autre demande d\'acheminement est en cours de création, veuillez réessayer') . '.'
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
            'msg' => $translator->trans('acheminement.L\'acheminement a bien été créé') . '.'
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
                         bool $printBL) {
        $extra = $redirectService->load();

        $paramRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $dispatchStatus = $dispatch->getStatut();

        return $this->render('dispatch/show.html.twig', [
            'dispatch' => $dispatch,
            'keep_pack_modal_open' => $paramRepository->getOneParamByLabel(ParametrageGlobal::KEEP_DISPATCH_PACK_MODAL_OPEN),
            'open_pack_modal' => $extra === self::EXTRA_OPEN_PACK_MODAL && $paramRepository->getOneParamByLabel(ParametrageGlobal::OPEN_DISPATCH_ADD_PACK_MODAL_ON_CREATION),
            'detailsConfig' => $dispatchService->createHeaderDetailsConfig($dispatch),
            'modifiable' => !$dispatchStatus || $dispatchStatus->isDraft(),
            'newPackConfig' => [
                'natures' => $natureRepository->findBy([], ['label' => 'ASC'])
            ],
            'dispatchValidate' => [
                'untreatedStatus' => $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::NOT_TREATED, Statut::PARTIAL])
            ],
            'dispatchTreat' => [
                'treatedStatus' => $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::TREATED])
            ],
            'printBL' => $printBL,
            'prefixPackCodeWithDispatchNumber' => $paramRepository->getOneParamByLabel(ParametrageGlobal::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER)
        ]);
    }

    /**
     * @Route("/{dispatch}/etat", name="print_dispatch_state_sheet", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::DEM, Action::DISPLAY_ACHE})
     */
    public function printDispatchStateSheet(PDFGeneratorService $generator, TranslatorInterface $translator, Dispatch $dispatch): ?Response {
        if($dispatch->getDispatchPacks()->isEmpty()) {
            return $this->json([
                "success" => false,
                "msg" => $translator->trans('acheminement.Le bon d\'acheminement n\'existe pas pour cet acheminement')
            ]);
        }

        return new PdfResponse(
            $generator->generatePDFDispatchNote($dispatch),
            "bon_acheminement_{$dispatch->getNumber()}.pdf"
        );
    }

    /**
     * @Route("/modifier", name="dispatch_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function edit(Request $request,
                         DispatchService $dispatchService,
                         FreeFieldService $freeFieldService,
                         EntityManagerInterface $entityManager,
                         TranslatorInterface $translator): Response {
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
                'msg' => (
                    'Il n\'y a aucun emplacement de prise ou de dépose paramétré pour ce type.' .
                    'Veuillez en paramétrer ou rendre les champs visibles à la création et/ou modification.'
                )
            ]);
        }

        if($startDate && $endDate && $startDate > $endDate) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'La date de fin d\'échéance est antérieure à la date de début.'
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
            ->setCommentaire($post->get('commentaire') ?: '')
            ->setDestination($destination);

        $freeFieldService->manageFreeFields($dispatch, $post->all(), $entityManager);

        $listAttachmentIdToKeep = $post->get('files') ?? [];

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
            'msg' => $translator->trans('acheminement.L\'acheminement a bien été modifié') . '.'
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
    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           TranslatorInterface $translator): Response {
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
                'msg' => $translator->trans("acheminement.L''acheminement a bien été supprimé") . '.'
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @param Dispatch $entity
     * @param AttachmentService $attachmentService
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     */
    private function persistAttachments(Dispatch $entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager) {
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
    public function apiPack(Dispatch $dispatch, EntityManagerInterface $manager): Response {
        $prefixPackCodeWithDispatchNumber = $manager->getRepository(ParametrageGlobal::class)->getOneParamByLabel(ParametrageGlobal::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER);
        return new JsonResponse([
            'data' => $dispatch->getDispatchPacks()
                ->map(function(DispatchPack $dispatchPack) use ($dispatch, $prefixPackCodeWithDispatchNumber) {
                    $pack = $dispatchPack->getPack();
                    $lastTracking = $pack->getLastTracking();
                    return [
                        'nature' => $pack->getNature() ? $pack->getNature()->getLabel() : '',
                        'code' => $pack->getCode(),
                        'quantity' => $dispatchPack->getQuantity(),
                        'lastMvtDate' => $lastTracking ? ($lastTracking->getDatetime() ? $lastTracking->getDatetime()->format('d/m/Y H:i') : '') : '',
                        'lastLocation' => $lastTracking ? ($lastTracking->getEmplacement() ? $lastTracking->getEmplacement()->getLabel() : '') : '',
                        'operator' => $lastTracking ? ($lastTracking->getOperateur() ? $lastTracking->getOperateur()->getUsername() : '') : '',
                        'status' => $dispatchPack->isTreated() ? 'Traité' : 'A traiter',
                        'actions' => $this->renderView('dispatch/datatablePackRow.html.twig', [
                            'pack' => $pack,
                            'pack_code' => $prefixPackCodeWithDispatchNumber
                                ? str_replace($dispatch->getNumber() . '-', '', $pack->getCode())
                                : $pack->getCode(),
                            'packDispatch' => $dispatchPack,
                            'modifiable' => $dispatchPack->getDispatch()->getStatut()->isDraft()
                        ])
                    ];
                })
                ->toArray()
        ]);
    }

    /**
     * @Route("/{dispatch}/packs/new", name="dispatch_new_pack", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function newPack(Request $request,
                            EntityManagerInterface $entityManager,
                            TranslatorInterface $translator,
                            PackService $packService,
                            Dispatch $dispatch): Response {
        $data = json_decode($request->getContent(), true);

        $packCode = trim($data['pack']);
        $natureId = $data['nature'];
        $quantity = $data['quantity'];
        $comment = $data['comment'];
        $weight = (floatval(str_replace(',', '.', $data['weight'])) ?: null);
        $volume = (floatval(str_replace(',', '.', $data['volume'])) ?: null);

        $globalSettingsRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $prefixPackCodeWithDispatchNumber = $globalSettingsRepository->getOneParamByLabel(ParametrageGlobal::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER);
        if($prefixPackCodeWithDispatchNumber) {
            $packCode = $dispatch->getNumber() . '-' . $packCode;
        }

        $packMustBeNew = $globalSettingsRepository->getOneParamByLabel(ParametrageGlobal::PACK_MUST_BE_NEW);
        if($packMustBeNew) {
            $existingPack = $entityManager->getRepository(Pack::class)->findOneBy(['code' => $packCode]);
            if($existingPack) {
                return $this->json([
                    'success' => false,
                    'msg' => "Le colis <strong>${packCode}</strong> existe déjà en base de données"
                ]);
            }
        }

        $alreadyCreated = !$dispatch
            ->getDispatchPacks()
            ->filter(function(DispatchPack $dispatchPack) use ($packCode) {
                $pack = $dispatchPack->getPack();
                return $pack->getCode() === $packCode;
            })
            ->isEmpty();

        if($alreadyCreated) {
            $success = false;
            $message = $translator->trans('acheminement.Le colis {numéro} existe déjà dans cet acheminement', [
                    "{numéro}" => '<strong>' . $packCode . '</strong>'
                ]) . '.';
        } else {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $packRepository = $entityManager->getRepository(Pack::class);

            if(!empty($packCode)) {
                $pack = $packRepository->findOneBy(['code' => $packCode]);
            }

            if(empty($pack)) {
                $pack = $packService->createPack(['code' => $packCode]);
                $entityManager->persist($pack);
            }

            $packDispatch = new DispatchPack();
            $packDispatch
                ->setPack($pack)
                ->setTreated(false)
                ->setDispatch($dispatch);
            $entityManager->persist($packDispatch);

            $nature = $natureRepository->find($natureId);
            $pack->setNature($nature);
            $pack->setComment($comment);
            $packDispatch->setQuantity($quantity);
            $pack->setWeight($weight);
            $pack->setVolume($volume);

            $entityManager->flush();

            $success = true;
            $message = $translator->trans('colis.Le colis {numéro} a bien été ajouté', [
                    "{numéro}" => '<strong>' . $pack->getCode() . '</strong>'
                ]) . '.';
        }

        return new JsonResponse([
            'success' => $success,
            'msg' => $message
        ]);
    }

    /**
     * @Route("/packs/edit", name="dispatch_edit_pack", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param PackService $packService
     * @param TranslatorInterface $translator
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editPack(Request $request,
                             PackService $packService,
                             TranslatorInterface $translator,
                             EntityManagerInterface $entityManager): Response {
        $data = json_decode($request->getContent(), true);

        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $packDispatchId = $data['packDispatchId'];
        /** @var DispatchPack $dispatchPack */
        $dispatchPack = $dispatchPackRepository->find($packDispatchId);
        if(empty($dispatchPack)) {
            $response = [
                'success' => false,
                'msg' => $translator->trans("colis.Le colis n''existe pas")
            ];
        } else {
            $pack = $dispatchPack->getPack();

            $packDataIsValid = $packService->checkPackDataBeforeEdition($data);

            if($packDataIsValid['success']) {
                $quantity = $data['quantity'];
                $packService
                    ->editPack($data, $natureRepository, $pack);
                $dispatchPack
                    ->setQuantity($quantity);

                $entityManager->flush();

                $response = [
                    'success' => true,
                    'msg' => $translator->trans('colis.Le colis {numéro} a bien été modifié', [
                            "{numéro}" => '<strong>' . $pack->getCode() . '</strong>'
                        ]) . '.'
                ];
            } else {
                $response = $packDataIsValid;
            }
        }
        return new JsonResponse($response);
    }

    /**
     * @Route("/packs/delete", name="dispatch_delete_pack", options={"expose"=true},methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     */
    public function deletePack(Request $request,
                               TranslatorInterface $translator,
                               EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);

            $pack = $dispatchPackRepository->find($data['pack']);
            $packCode = $pack->getPack()->getCode();
            $entityManager->remove($pack);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => $translator->trans('colis.Le colis {numéro} a bien été supprimé', [
                        "{numéro}" => '<strong>' . $packCode . '</strong>'
                    ]) . '.'
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/{id}/validate", name="dispatch_validate_request", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @param Dispatch $dispatch
     * @param DispatchService $dispatchService
     * @return Response
     * @throws Exception
     */
    public function validateDispatchRequest(Request $request,
                                            EntityManagerInterface $entityManager,
                                            TranslatorInterface $translator,
                                            Dispatch $dispatch,
                                            DispatchService $dispatchService,
                                            NotificationService $notificationService): Response {
        $status = $dispatch->getStatut();

        if(!$status || $status->isDraft()) {
            $data = json_decode($request->getContent(), true);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $data['status'];
            $untreatedStatus = $statusRepository->find($statusId);

            if($untreatedStatus && $untreatedStatus->isNotTreated() && ($untreatedStatus->getType() === $dispatch->getType())) {
                $dispatch
                    ->setStatut($untreatedStatus)
                    ->setValidationDate(new DateTime('now'));

                if( $dispatch->getType() &&
                    ($dispatch->getType()->isNotificationsEnabled() || $dispatch->getType()->isNotificationsEmergency($dispatch->getEmergency()))) {
                    $notificationService->toTreat($dispatch);
                }

                $entityManager->flush();
                $dispatchService->sendEmailsAccordingToStatus($dispatch, true);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Le statut sélectionné doit être de type à traiter et correspondre au type de la demande."
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'msg' => $translator->trans('acheminement.L\'acheminement a bien été passé en à traiter'),
            'redirect' => $this->generateUrl('dispatch_show', ['id' => $dispatch->getId()])
        ]);
    }

    /**
     * @Route("/{id}/treat", name="dispatch_treat_request", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DispatchService $dispatchService
     * @param TranslatorInterface $translator
     * @param Dispatch $dispatch
     * @return Response
     * @throws Exception
     */
    public function treatDispatchRequest(Request $request,
                                         EntityManagerInterface $entityManager,
                                         DispatchService $dispatchService,
                                         TranslatorInterface $translator,
                                         Dispatch $dispatch): Response {
        $status = $dispatch->getStatut();

        if(!$status || $status->isNotTreated() || $status->isPartial()) {
            $data = json_decode($request->getContent(), true);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $data['status'];
            $treatedStatus = $statusRepository->find($statusId);

            if($treatedStatus
                && $treatedStatus->isTreated()
                && $treatedStatus->getType() === $dispatch->getType()) {

                /** @var Utilisateur $loggedUser */
                $loggedUser = $this->getUser();
                $dispatchService->treatDispatchRequest($entityManager, $dispatch, $treatedStatus, $loggedUser);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Le statut sélectionné doit être de type traité et correspondre au type de la demande."
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'msg' => $translator->trans('acheminement.L\'acheminement a bien été traité'),
            'redirect' => $this->generateUrl('dispatch_show', ['id' => $dispatch->getId()])
        ]);
    }

    /**
     * @Route("/{dispatch}/packs-counter", name="get_dispatch_packs_counter", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param Dispatch $dispatch
     * @return JsonResponse
     */
    public function getDispatchPackCounter(Dispatch $dispatch) {
        return new JsonResponse([
            'success' => true,
            'packsCounter' => $dispatch->getDispatchPacks()->count()
        ]);
    }

    /**
     * @Route("/{dispatch}/rollback-draft", name="rollback_draft", methods="GET")z
     * @param EntityManagerInterface $entityManager
     * @param Dispatch $dispatch
     * @return Response
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
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param CSVExportService $CSVExportService
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @return Response
     */
    public function getDispatchesCSV(Request $request,
                                     FreeFieldService $freeFieldService,
                                     CSVExportService $CSVExportService,
                                     EntityManagerInterface $entityManager,
                                     TranslatorInterface $translator): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch(Throwable $throwable) {
        }

        if(isset($dateTimeMin) && isset($dateTimeMax)) {
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $dispatches = $dispatchRepository->getByDates($dateTimeMin, $dateTimeMax);

            $nbPacksByDispatch = $dispatchRepository->getNbPacksByDates($dateTimeMin, $dateTimeMax);

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_DISPATCH]);

            $csvHeader = array_merge(
                [
                    'Numéro demande',
                    $translator->trans('acheminement.Numéro de commande'),
                    'Date de création',
                    'Date de validation',
                    'Date de traitement',
                    'Type',
                    'Demandeur',
                    'Destinataire',
                    $translator->trans('acheminement.Emplacement prise'),
                    $translator->trans('acheminement.Emplacement dépose'),
                    $translator->trans('acheminement.Destination'),
                    'Nb ' . $translator->trans('colis.colis'),
                    'Statut',
                    'Urgence',
                    $translator->trans('natures.nature'),
                    'Code',
                    'Quantité ' . $translator->trans('colis.colis'),
                    $translator->trans('acheminement.Quantité à acheminer'),
                    'Poids',
                    'Date dernier mouvement',
                    'Dernier emplacement',
                    'Opérateur',
                    'Traité par'
                ],
                $freeFieldsConfig['freeFieldsHeader']
            );
            $receivers = $dispatchRepository->getReceiversByDates($dateTimeMin, $dateTimeMax);

            return $CSVExportService->createBinaryResponseFromData(
                'export_acheminements.csv',
                $dispatches,
                $csvHeader,
                function($dispatch) use ($freeFieldsConfig, $nbPacksByDispatch, $receivers, $dispatchRepository) {
                    $id = $dispatch['id'];
                    $receiversStr = Stream::from($receivers[$id] ?? [])
                        ->join(", ");
                    $number = $dispatch['number'] ?? '';

                    $row = [];
                    $row[] = $number;
                    $row[] = $dispatch['commandNumber'] ?: '';
                    $row[] = $dispatch['creationDate'] ? $dispatch['creationDate']->format('d/m/Y H:i:s') : '';
                    $row[] = $dispatch['validationDate'] ? $dispatch['validationDate']->format('d/m/Y H:i:s') : '';
                    $row[] = $dispatch['treatmentDate'] ? $dispatch['treatmentDate']->format('d/m/Y H:i:s') : '';
                    $row[] = $dispatch['type'] ?? '';
                    $row[] = $dispatch['requester'] ?? '';
                    $row[] = $receiversStr;
                    $row[] = $dispatch['locationFrom'] ?? '';
                    $row[] = $dispatch['locationTo'] ?? '';
                    $row[] = $dispatch['destination'] ?? '';
                    $row[] = $nbPacksByDispatch[$number] ?? '';
                    $row[] = $dispatch['status'] ?? '';
                    $row[] = $dispatch['emergency'] ?? '';
                    $row[] = $dispatch['packNatureLabel'] ?? '';
                    $row[] = $dispatch['packCode'] ?? '';
                    $row[] = $dispatch['packQuantity'] ?? '';
                    $row[] = $dispatch['dispatchQuantity'] ?? '';
                    $row[] = $dispatch['weight'] ?? '';
                    $row[] = $dispatch['lastMovement'] ? $dispatch['lastMovement']->format('Y/m/d H:i') : '';
                    $row[] = $dispatch['lastLocation'] ?? '';
                    $row[] = $dispatch['operator'] ?? '';
                    $row[] = $dispatch['treatedBy'] ?? '';

                    foreach($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
                        $row[] = FormatHelper::freeField($dispatch['freeFields'][$freeFieldId] ?? '', $freeField);
                    }

                    return [$row];
                }
            );
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route(
     *     "/{dispatch}/api-delivery-note",
     *     name="api_delivery_note_dispatch",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param Request $request
     * @param TranslatorInterface $translator
     * @param Dispatch $dispatch
     * @return JsonResponse
     */
    public function apiDeliveryNote(Request $request,
                                    TranslatorInterface $translator,
                                    Dispatch $dispatch): JsonResponse {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        $maxNumberOfPacks = 10;

        if($dispatch->getDispatchPacks()->count() === 0) {
            $errorMessage = $translator->trans('acheminement.Des colis sont nécessaires pour générer un bon de livraison') . '.';

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

        $fieldsParamRepository = $this->getDoctrine()->getRepository(FieldsParam::class);

        $html = $this->renderView('dispatch/modalPrintDeliveryNoteContent.html.twig', array_merge($deliveryNoteData, [
            'dispatchEmergencyValues' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY),
        ]));

        return $this->json([
            "success" => true,
            "html" => $html
        ]);
    }

    /**
     * @Route(
     *     "/{dispatch}/delivery-note",
     *     name="delivery_note_dispatch",
     *     options={"expose"=true},
     *     methods="POST",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param EntityManagerInterface $entityManager
     * @param Dispatch $dispatch
     * @param PDFGeneratorService $pdf
     * @param DispatchService $dispatchService
     * @param Request $request
     * @return JsonResponse
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function postDeliveryNote(EntityManagerInterface $entityManager,
                                     Dispatch $dispatch,
                                     PDFGeneratorService $pdf,
                                     DispatchService $dispatchService,
                                     Request $request,
                                     SpecificService $specificService): JsonResponse {

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

        $loggedUser->setSavedDispatchDeliveryNoteData($userDataToSave);
        $dispatch->setDeliveryNoteData($dispatchDataToSave);

        $entityManager->flush();

        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $logo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DELIVERY_NOTE_LOGO);

        $nowDate = new DateTime('now');
        $client = SpecificService::CLIENTS[$specificService->getAppClient()];

        $documentTitle = "BL - {$dispatch->getNumber()} - {$client} - {$nowDate->format('dmYHis')}";
        $fileName = $pdf->generatePDFDeliveryNote($documentTitle, $logo, $dispatch);

        $deliveryNoteAttachment = new Attachment();
        $deliveryNoteAttachment
            ->setDispatch($dispatch)
            ->setFileName($fileName)
            ->setOriginalName($documentTitle . '.pdf');

        $entityManager->persist($deliveryNoteAttachment);

        $entityManager->flush();

        $detailsConfig = $dispatchService->createHeaderDetailsConfig($dispatch);

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le téléchargement de votre bon de livraison va commencer...',
            'entete' => $this->renderView("dispatch/dispatch-show-header.html.twig", [
                'dispatch' => $dispatch,
                'showDetails' => $detailsConfig,
                'modifiable' => !$dispatch->getStatut() || $dispatch->getStatut()->isDraft(),
            ]),
            'attachmentId' => $deliveryNoteAttachment->getId()
        ]);
    }

    /**
     * @Route(
     *     "/{dispatch}/delivery-note/{attachment}",
     *     name="print_delivery_note_dispatch",
     *     options={"expose"=true},
     *     methods="GET"
     * )
     * @param TranslatorInterface $trans
     * @param Dispatch $dispatch
     * @param KernelInterface $kernel
     * @param Attachment $attachment
     * @return PdfResponse
     */
    public function printDeliveryNote(TranslatorInterface $trans,
                                      Dispatch $dispatch,
                                      KernelInterface $kernel,
                                      Attachment $attachment): Response {
        if(!$dispatch->getDeliveryNoteData()) {
            return $this->json([
                "success" => false,
                "msg" => $trans->trans('acheminement.Le bon de livraison n\'existe pas pour cet acheminement')
            ]);
        }

        $response = new BinaryFileResponse(($kernel->getProjectDir() . '/public/uploads/attachements/' . $attachment->getFileName()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName());

        return $response;
    }

    /**
     * @Route(
     *     "/{dispatch}/check-waybill",
     *     name="check_dispatch_waybill",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param TranslatorInterface $translator
     * @param Dispatch $dispatch
     * @return JsonResponse
     */
    public function checkWaybill(TranslatorInterface $translator, Dispatch $dispatch) {
        if($dispatch->getDispatchPacks()->count() === 0) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translator->trans('acheminement.Des colis sont nécessaires pour générer une lettre de voiture') . '.'
            ]);
        } else {
            return new JsonResponse([
                "success" => true,
            ]);
        }
    }

    /**
     * @Route(
     *     "/{dispatch}/api-waybill",
     *     name="api_dispatch_waybill",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param SpecificService $specificService
     * @param Dispatch $dispatch
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function apiWaybill(Request $request,
                               EntityManagerInterface $entityManager,
                               SpecificService $specificService,
                               Dispatch $dispatch): JsonResponse {

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $userSavedData = $loggedUser->getSavedDispatchWaybillData();
        $dispatchSavedData = $dispatch->getWaybillData();

        $now = new DateTime('now');

        $isEmerson = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_EMERSON);

        $consignorUsername = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONTACT_NAME);
        $consignorUsername = $consignorUsername !== null && $consignorUsername !== ''
            ? $consignorUsername
            : ($isEmerson ? $loggedUser->getUsername() : null);

        $consignorEmail = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL);
        $consignorEmail = $consignorEmail !== null && $consignorEmail !== ''
            ? $consignorEmail
            : ($isEmerson ? $loggedUser->getEmail() : null);

        $defaultData = [
            'carrier' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CARRIER),
            'dispatchDate' => $now->format('Y-m-d'),
            'consignor' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONSIGNER),
            'receiver' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_RECEIVER),
            'locationFrom' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_LOCATION_FROM),
            'locationTo' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_LOCATION_TO),
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
     * @Route(
     *     "/{dispatch}/waybill",
     *     name="post_dispatch_waybill",
     *     options={"expose"=true},
     *     condition="request.isXmlHttpRequest()",
     *     methods="POST"
     * )
     * @param EntityManagerInterface $entityManager
     * @param Dispatch $dispatch
     * @param PDFGeneratorService $pdf
     * @param DispatchService $dispatchService
     * @param TranslatorInterface $translator
     * @param Request $request
     * @return JsonResponse
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function postDispatchWaybill(EntityManagerInterface $entityManager,
                                        Dispatch $dispatch,
                                        PDFGeneratorService $pdf,
                                        DispatchService $dispatchService,
                                        TranslatorInterface $translator,
                                        Request $request,
                                        SpecificService $specificService): JsonResponse {

        if($dispatch->getDispatchPacks()->count() > DispatchService::WAYBILL_MAX_PACK) {
            $message = 'Attention : ' . $translator->trans("acheminement.L''acheminement contient plus de {nombre} colis", ["{nombre}" => DispatchService::WAYBILL_MAX_PACK]) . ', cette lettre de voiture ne peut contenir plus de ' . DispatchService::WAYBILL_MAX_PACK . ' lignes.';
            $success = false;
        } else {
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

            $entityManager->flush();

            $message = 'Le téléchargement de votre lettre de voiture va commencer...';
            $success = true;
        }

        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $logo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::WAYBILL_LOGO);

        $nowDate = new DateTime('now');

        $client = SpecificService::CLIENTS[$specificService->getAppClient()];

        $title = "LDV - {$dispatch->getNumber()} - {$client} - {$nowDate->format('dmYHis')}";
        $fileName = $pdf->generatePDFWaybill($title, $logo, $dispatch);

        $wayBillAttachment = new Attachment();
        $wayBillAttachment
            ->setDispatch($dispatch)
            ->setFileName($fileName)
            ->setOriginalName($title . '.pdf');

        $entityManager->persist($wayBillAttachment);

        $entityManager->flush();

        $detailsConfig = $dispatchService->createHeaderDetailsConfig($dispatch);

        return new JsonResponse([
            'success' => $success,
            'msg' => $message,
            'entete' => $this->renderView("dispatch/dispatch-show-header.html.twig", [
                'dispatch' => $dispatch,
                'showDetails' => $detailsConfig,
                'modifiable' => !$dispatch->getStatut() || $dispatch->getStatut()->isDraft(),
            ]),
            'attachmentId' => $wayBillAttachment->getId()
        ]);
    }

    /**
     * @Route(
     *     "/{dispatch}/waybill/{attachment}",
     *     name="print_waybill_dispatch",
     *     options={"expose"=true},
     *     methods="GET"
     * )
     * @param TranslatorInterface $trans
     * @param Dispatch $dispatch
     * @param Attachment $attachment
     * @param KernelInterface $kernel
     * @return JsonResponse
     */
    public function printWaybillNote(TranslatorInterface $trans,
                                     Dispatch $dispatch,
                                     Attachment $attachment,
                                     KernelInterface $kernel): Response {
        if(!$dispatch->getWaybillData()) {
            return $this->json([
                "success" => false,
                "msg" => $trans->trans('acheminement.La lettre de voiture n\'existe pas pour cet acheminement'),
            ]);
        }

        $response = new BinaryFileResponse(($kernel->getProjectDir() . '/public/uploads/attachements/' . $attachment->getFileName()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName());

        return $response;
    }

    /**
     * @Route("/bon-de-surconsommation/{dispatch}", name="generate_overconsumption_bill", options={"expose"=true}, methods="POST")
     */
    public function updateOverconsumption(DispatchService $dispatchService, Dispatch $dispatch): Response {
        $entityManager = $this->getDoctrine()->getManager();
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $overConsumptionBill = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS);
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

        $detailsConfig = $dispatchService->createHeaderDetailsConfig($dispatch);

        return $this->json([
            'entete' => $this->renderView("dispatch/dispatch-show-header.html.twig", [
                'dispatch' => $dispatch,
                'showDetails' => $detailsConfig,
                'modifiable' => !$dispatch->getStatut() || $dispatch->getStatut()->isDraft(),
            ])
        ]);
    }

    /**
     * @Route("/bon-de-surconsommation/{dispatch}", name="print_overconsumption_bill", options={"expose"=true}, methods="GET")
     * @HasPermission({Menu::DEM, Action::GENERATE_OVERCONSUMPTION_BILL})
     */
    public function printOverconsumptionBill(Dispatch $dispatch,
                                             PDFGeneratorService $pdfService,
                                                 EntityManagerInterface $entityManager): Response {
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $appLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::LABEL_LOGO);
        $overconsumptionLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::OVERCONSUMPTION_LOGO);

        return new PdfResponse(
            $pdfService->generatePDFOverconsumption($dispatch, $appLogo, $overconsumptionLogo),
            "{$dispatch->getNumber()}-bon-surconsommation.pdf"
        );
    }

}
