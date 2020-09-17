<?php

namespace App\Controller;

use App\Entity\Dispatch;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\Menu;

use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\DispatchPack;
use App\Entity\PieceJointe;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Service\AttachmentService;
use App\Service\FreeFieldService;
use App\Service\PackService;
use App\Service\PDFGeneratorService;
use App\Service\UserService;
use App\Service\DispatchService;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/acheminements")
 */
Class DispatchController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    private $attachmentService;

    public function __construct(UserService $userService,
                                AttachmentService $attachmentService) {
        $this->userService = $userService;
        $this->attachmentService = $attachmentService;
    }


    /**
     * @Route("/", name="dispatch_index")
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     */
    public function index(EntityManagerInterface $entityManager)
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_ACHE)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

        return $this->render('dispatch/index.html.twig', [
			'statuts' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, true),
            'types' => $types,
			'modalNewConfig' => [
                'fieldsParam' => $fieldsParam,
                'dispatchDefaultStatus' => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::DISPATCH),
                'typeChampsLibres' => array_map(function (Type $type) use ($champLibreRepository) {
                    $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_DISPATCH);
                    return [
                        'typeLabel' => $type->getLabel(),
                        'typeId' => $type->getId(),
                        'champsLibres' => $champsLibres,
                    ];
                }, $types),
			    'notTreatedStatus' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, true, true),
            ]
        ]);
    }

    /**
     * @Route("/api", name="dispatch_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param DispatchService $dispatchService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request,
                        DispatchService $dispatchService): Response
    {
        if ($request->isXmlHttpRequest()) {

            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_ACHE)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $dispatchService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/creer", name="dispatch_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param DispatchService $dispatchService
     * @param AttachmentService $attachmentService
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @return Response
     * @throws Exception
     */
    public function new(Request $request,
                        FreeFieldService $freeFieldService,
                        DispatchService $dispatchService,
                        AttachmentService $attachmentService,
                        EntityManagerInterface $entityManager,
                        TranslatorInterface $translator): Response
    {
        if ($request->isXmlHttpRequest()) {
            $post = $request->request;
            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $dispatch = new Dispatch();
            $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));

            $fileBag = $request->files->count() > 0 ? $request->files : null;
            $locationTake = $emplacementRepository->find($post->get('prise'));
            $locationDrop = $emplacementRepository->find($post->get('depose'));

            $comment = $post->get('commentaire');
            $startDateRaw = $post->get('startDate');
            $endDateRaw = $post->get('endDate');
            $receiver = $post->get('receiver');
            $emergency = $post->get('emergency');

            $startDate = !empty($startDateRaw) ? $dispatchService->createDateFromStr($startDateRaw) : null;
            $endDate = !empty($endDateRaw) ? $dispatchService->createDateFromStr($endDateRaw) : null;
            $number = $dispatchService->createDispatchNumber($entityManager, $date);

            if ($startDate && $endDate && $startDate > $endDate) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La date de fin d\'échéance est inférieure à la date de début.'
                ]);
            }

            $dispatch
                ->setCreationDate($date)
                ->setStatut($statutRepository->find($post->get('statut')))
                ->setType($typeRepository->find($post->get('type')))
                ->setRequester($utilisateurRepository->find($post->get('requester')))
                ->setLocationFrom($locationTake)
                ->setLocationTo($locationDrop)
                ->setNumber($number);

            if (!empty($comment)) {
                $dispatch->setCommentaire($comment);
            }

            if (!empty($startDate)) {
                $dispatch->setStartDate($startDate);
            }

            if (!empty($endDate)) {
                $dispatch->setEndDate($endDate);
            }

            if (!empty($receiver)) {
                $dispatch->setReceiver($utilisateurRepository->find($receiver) ?? null);
            }

            if (!empty($emergency)) {
                $dispatch->setUrgent($post->getBoolean('urgent'));
            }

            $freeFieldService->manageFreeFields($dispatch, $post->all(), $entityManager);

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
                    $dispatch->addAttachment($attachment);
                }
            }

            $entityManager->persist($dispatch);
            $entityManager->flush();

            if (!empty($receiver)) {
                $dispatchService->sendEmailsAccordingToStatus($dispatch, false);
            }

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('dispatch_show', ['id' => $dispatch->getId()]),
                'msg' => $translator->trans("acheminement.L''acheminement a bien été créé") . '.'
            ]);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/voir/{id}", name="dispatch_show", options={"expose"=true}, methods="GET|POST")
     * @param Dispatch $dispatch
     * @param EntityManagerInterface $entityManager
     * @param DispatchService $dispatchService
     * @return RedirectResponse|Response
     */
    public function show(Dispatch $dispatch,
                         EntityManagerInterface $entityManager,
                         DispatchService $dispatchService)
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_ACHE)) {
            return $this->redirectToRoute('access_denied');
        }

        $natureRepository = $entityManager->getRepository(Nature::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $dispatchStatus = $dispatch->getStatut();

        return $this->render('dispatch/show.html.twig', [
            'dispatch' => $dispatch,
            'detailsConfig' => $dispatchService->createHeaderDetailsConfig($dispatch),
            'modifiable' => !$dispatchStatus || !$dispatchStatus->getTreated(),
            'newPackConfig' => [
                'natures' => $natureRepository->findAll()
            ],
            'dispatchValidate' => [
                'treatedStatus' => $statusRepository->findTreatedStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), true)
            ]
        ]);
    }

    /**
     * @Route("/{dispatch}/etat", name="print_dispatch_state_sheet", options={"expose"=true}, methods="GET|POST")
     * @param Dispatch $dispatch
     * @param PDFGeneratorService $PDFGenerator
     * @param TranslatorInterface $translator
     * @return PdfResponse
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printDispatchStateSheet(Dispatch $dispatch,
                                            PDFGeneratorService $PDFGenerator,
                                            TranslatorInterface $translator): ?Response
    {
        if ($dispatch->getDispatchPacks()->isEmpty()) {
            throw new NotFoundHttpException($translator->trans("acheminement.La fiche d\'état n\'existe pas pour cet acheminement"));
        }

        $packsConfig = $dispatch->getDispatchPacks()
            ->map(function(DispatchPack $dispatchPack) use ($dispatch, $translator){
                return [
                    'title' => 'Acheminement n°' . $dispatch->getId(),
                    'code' => $dispatchPack->getPack()->getCode(),
                    'content' => [
                        'Date de création' => $dispatch->getCreationDate() ? $dispatch->getCreationDate()->format('d/m/Y H:i:s') : '',
                        'Date de validation' => $dispatch->getValidationDate() ? $dispatch->getValidationDate()->format('d/m/Y H:i:s') : '',
                        'Demandeur' => $dispatch->getRequester() ? $dispatch->getRequester()->getUsername() : '',
                        'Destinataire' => $dispatch->getReceiver() ? $dispatch->getReceiver()->getUsername() : '',
                        $translator->trans('acheminement.emplacement dépose') => $dispatch->getLocationTo() ? $dispatch->getLocationTo()->getLabel() : '',
                        $translator->trans('acheminement.emplacement prise') => $dispatch->getLocationFrom() ? $dispatch->getLocationFrom()->getLabel() : ''
                    ]
                ];
            })
        ->toArray();

        $fileName = 'Etat_acheminement_' . $dispatch->getId() . '.pdf';
        return new PdfResponse(
            $PDFGenerator->generatePDFStateSheet($fileName, $packsConfig),
            $fileName
        );
    }

    /**
     * @Route("/modifier", name="dispatch_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param DispatchService $dispatchService
     * @param FreeFieldService $freeFieldService
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @return Response
     */
    public function edit(Request $request,
                         DispatchService $dispatchService,
                         FreeFieldService $freeFieldService,
                         EntityManagerInterface $entityManager,
                         TranslatorInterface $translator): Response {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $post = $request->request;
        $dispatch = $dispatchRepository->find($post->get('id'));

        $startDateRaw = $post->get('startDate');
        $endDateRaw = $post->get('endDate');
        $startDate = !empty($startDateRaw) ? $dispatchService->createDateFromStr($startDateRaw) : null;
        $endDate = !empty($endDateRaw) ? $dispatchService->createDateFromStr($endDateRaw) : null;

        $locationTake = $emplacementRepository->find($post->get('prise'));
        $locationDrop = $emplacementRepository->find($post->get('depose'));

        $oldStatus = $dispatch->getStatut();
        if (!$oldStatus || !$oldStatus->getTreated()) {
            $newStatus = $statutRepository->find($post->get('statut'));
            $dispatch->setStatut($newStatus);
        }
        else {
            $newStatus = null;
        }

        if ($startDate && $endDate && $startDate > $endDate) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'La date de fin d\'échéance est antérieure à la date de début.'
            ]);
        }

        $receiverData = $post->get('receiver');
        $requesterData = $post->get('requester');
        $receiver = $receiverData ? $utilisateurRepository->find($receiverData) : null;
        $requester = $requesterData ? $utilisateurRepository->find($requesterData) : null;

        $dispatch
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setRequester($requester)
            ->setReceiver($receiver)
            ->setUrgent($post->getBoolean('urgent'))
            ->setLocationFrom($locationTake)
            ->setLocationTo($locationDrop)
            ->setCommentaire($post->get('commentaire') ?: '');

        $freeFieldService->manageFreeFields($dispatch, $post->all(), $entityManager);

        $listAttachmentIdToKeep = $post->get('files') ?? [];

        $attachments = $dispatch->getAttachments()->toArray();
        foreach ($attachments as $attachment) {
            /** @var PieceJointe $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $dispatch);
            }
        }

        $this->persistAttachments($dispatch, $this->attachmentService, $request, $entityManager);

        $entityManager->flush();

        if ((!$oldStatus && $newStatus)
            || (
                $oldStatus
                && $newStatus
                && ($oldStatus->getId() !== $newStatus->getId())
            )) {
            $dispatchService->sendEmailsAccordingToStatus($dispatch, true);
        }

        $dispatchStatus = $dispatch->getStatut();

        return new JsonResponse([
            'entete' => $this->renderView('dispatch/dispatch-show-header.html.twig', [
                'dispatch' => $dispatch,
                'modifiable' => !$dispatchStatus || !$dispatchStatus->getTreated(),
                'showDetails' => $dispatchService->createHeaderDetailsConfig($dispatch)
            ]),
            'success' => true,
            'msg' => $translator->trans("acheminement.L''acheminement a bien été modifié") . '.'
        ]);
    }

    /**
     * @Route("/api-modifier", name="dispatch_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $pieceJointeRepository = $entityManager->getRepository(PieceJointe::class);

            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

            $dispatch = $dispatchRepository->find($data['id']);
            $json = $this->renderView('dispatch/modalEditContentDispatch.html.twig', [
                'dispatch' => $dispatch,
                'fieldsParam' => $fieldsParam,
                'utilisateurs' => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
                'notTreatedStatus' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, true, true),
                'attachments' => $pieceJointeRepository->findBy(['dispatch' => $dispatch])
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="dispatch_delete", options={"expose"=true},methods={"GET","POST"})
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
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $attachmentRepository = $entityManager->getRepository(PieceJointe::class);

            $dispatch = $dispatchRepository->find($data['dispatch']);

            if($dispatch) {
                $attachments = $attachmentRepository->findBy(['dispatch' => $dispatch]);
                foreach ($attachments as $attachment) {
                    $entityManager->remove($attachment);
                }

                $trackingMovements = $dispatch->getTrackingMovements()->toArray();
                foreach ($trackingMovements as $trackingMovement) {
                    $dispatch->removeTrackingMovement($trackingMovement);
                }
            }
            $entityManager->remove($dispatch);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('dispatch_index'),
                'msg' => $translator->trans("acheminement.L''acheminement a bien été supprimé") . '.'
            ]);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @param Dispatch $entity
     * @param AttachmentService $attachmentService
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     */
    private function persistAttachments(Dispatch $entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager)
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
     * @Route("/packs/api/{dispatch}", name="dispatch_pack_api", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param Dispatch $dispatch
     * @return Response
     */
    public function apiPack(Dispatch $dispatch): Response
    {
        return new JsonResponse([
            'data' => $dispatch->getDispatchPacks()
                ->map(function (DispatchPack $dispatchPack) {
                    $pack = $dispatchPack->getPack();
                    $lastTracking = $pack->getLastTracking();
                    return [
                        'nature' => $pack->getNature() ? $pack->getNature()->getLabel() : '',
                        'code' => $pack->getCode(),
                        'quantity' => $dispatchPack->getQuantity(),
                        'lastMvtDate' => $lastTracking ? ($lastTracking->getDatetime() ? $lastTracking->getDatetime()->format('d/m/Y H:i') : '') : '',
                        'lastLocation' => $lastTracking ? ($lastTracking->getEmplacement() ? $lastTracking->getEmplacement()->getLabel() : '') : '',
                        'operator' => $lastTracking ? ($lastTracking->getOperateur() ? $lastTracking->getOperateur()->getUsername() : '') : '',
                        'actions' => $this->renderView('dispatch/datatablePackRow.html.twig', [
                            'pack' => $pack,
                            'packDispatch' => $dispatchPack,
                            'modifiable' => !$dispatchPack->getDispatch()->getStatut()->getTreated()
                        ])
                    ];
                })
                ->toArray()
        ]);
    }

    /**
     * @Route("/{dispatch}/packs/new", name="dispatch_new_pack", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @param PackService $packService
     * @param Dispatch $dispatch
     * @return Response
     */
    public function newPack(Request $request,
                            EntityManagerInterface $entityManager,
                            TranslatorInterface $translator,
                            PackService $packService,
                            Dispatch $dispatch): Response {
        $data = json_decode($request->getContent(), true);

        $packCode = $data['pack'];
        $natureId = $data['nature'];
        $quantity = $data['quantity'];

        $alreadyCreated = !$dispatch
            ->getDispatchPacks()
            ->filter(function (DispatchPack $dispatchPack) use ($packCode) {
                $pack = $dispatchPack->getPack();
                return $pack->getCode() === $packCode;
            })
            ->isEmpty();

        if ($alreadyCreated) {
            $success = false;
            $message = $translator->trans('acheminement.Le colis existe déjà dans cet acheminement');
        }
        else {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $packRepository = $entityManager->getRepository(Pack::class);

            if (!empty($packCode)) {
                $pack = $packRepository->findOneBy(['code' => $packCode]);
            }

            if (empty($pack)) {
                $pack = $packService->createPack(['code' => $packCode]);
                $entityManager->persist($pack);
            }

            $packDispatch = new DispatchPack();
            $packDispatch
                ->setPack($pack)
                ->setDispatch($dispatch);
            $entityManager->persist($packDispatch);

            $nature = $natureRepository->find($natureId);
            $pack
                ->setNature($nature);
            $packDispatch
                ->setQuantity($quantity);

            $entityManager->flush();

            $success = true;
            $message = $translator->trans('colis.Le colis a bien été sauvegardé');
        }

        return new JsonResponse([
            'success' => $success,
            'msg' => $message
        ]);
    }

    /**
     * @Route("/packs/edit", name="dispatch_edit_pack", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param TranslatorInterface $translator
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editPack(Request $request,
                             TranslatorInterface $translator,
                             EntityManagerInterface $entityManager): Response {
        $data = json_decode($request->getContent(), true);

        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $packDispatchId = $data['packDispatchId'];
        /** @var DispatchPack $dispatchPack */
        $dispatchPack = $dispatchPackRepository->find($packDispatchId);
        if (empty($dispatchPack)) {
            $success = false;
            $message = $translator->trans("colis.Le colis n''existe pas");
        } else {
            $natureId = $data['nature'];
            $quantity = $data['quantity'];

            $pack = $dispatchPack->getPack();

            $nature = $natureRepository->find($natureId);
            $pack
                ->setNature($nature);

            $dispatchPack
                ->setQuantity($quantity);

            $entityManager->flush();

            $success = true;
            $message = $translator->trans('colis.Le colis a bien été sauvegardé');
        }
        return new JsonResponse([
            'success' => $success,
            'msg' => $message
        ]);
    }

    /**
     * @Route("/packs/delete", name="dispatch_delete_pack", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param TranslatorInterface $translator
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deletePack(Request $request,
                               TranslatorInterface $translator,
                               EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);

            $pack = $dispatchPackRepository->find($data['pack']);
            $entityManager->remove($pack);
            $entityManager->flush();

            $data = [
                'success' => true,
                'msg' => $translator->trans('colis.Le colis a bien été supprimé' . '.')
            ];

            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/{id}/validate", name="dispatch_validate_request", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DispatchService $dispatchService
     * @param TranslatorInterface $translator
     * @param Dispatch $dispatch
     * @return Response
     * @throws Exception
     */
    public function validateDispatchRequest(Request $request,
                                            EntityManagerInterface $entityManager,
                                            DispatchService $dispatchService,
                                            TranslatorInterface $translator,
                                            Dispatch $dispatch): Response
    {
        $status = $dispatch->getStatut();

        if(!$status || !$status->getTreated()) {
            $data = json_decode($request->getContent(), true);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $data['status'];
            $treatedStatus = $statusRepository->find($statusId);

            if ($treatedStatus
                && $treatedStatus->getTreated()
                && $treatedStatus->getType() === $dispatch->getType()) {

                /** @var Utilisateur $loggedUser */
                $loggedUser = $this->getUser();

                $dispatchService->validateDispatchRequest($entityManager, $dispatch, $treatedStatus, $loggedUser);
            }
            else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Le statut sélectionné doit être de type traité et correspondre au type de la demande."
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'msg' => 'La ' . $translator->trans('acheminement.acheminement') . 'a bien été traité(e).',
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
        $maxNumberOfPacks = 7;

        if ($dispatch->getDispatchPacks()->count() === 0) {
            $errorMessage = $translator->trans('acheminement.Des colis sont nécessaires pour générer un bon de livraison') . '.';
            return new JsonResponse('
                <div class="text-danger">' . $errorMessage . '</div>
            ');
        }

        // TODO mettre un champ json avec toutes les data dans le dispatch
        // + ne sauvegarder uniquement les valeurs à true dans l'utilisateur
        $savedData = $loggedUser->getLastDispatchDeliveryNoteData();

        $deliveryNoteData = array_reduce(
            array_keys(Dispatch::DELIVERY_NOTE_DATA),
            function(array $carry, string $name) use ($request, $savedData) {
                if (isset(Dispatch::DELIVERY_NOTE_DATA[$name])
                    && Dispatch::DELIVERY_NOTE_DATA[$name]) {
                    $carry[$name] = $savedData[$name] ?? null;
                }

                return $carry;
            },
            []
        );

        $deliveryNoteData['deliveryNumber'] = $dispatch->getNumber();
        $deliveryNoteData['projectNumber'] = $dispatch->getProjectNumber();
        $deliveryNoteData['username'] = $loggedUser->getUsername();
        $deliveryNoteData['userPhone'] = $loggedUser->getPhone();
        $deliveryNoteData['packs'] = array_slice($dispatch->getDispatchPacks()->toArray(), 0, $maxNumberOfPacks);

        // TODO get real values
        $deliveryNoteData['dispatchEmergency'] = '24h';
        // TODO get database values
        $deliveryNoteData['dispatchEmergencyValues'] = ['24h', '48h', '72h'];

        $json = $this->renderView('dispatch/modalPrintDeliveryNoteContent.html.twig', $deliveryNoteData);

        return new JsonResponse($json);

    }

    /**
     * @Route(
     *     "/{dispatch}/delivery-note",
     *     name="delivery_note_dispatch",
     *     options={"expose"=true},
     *     methods="POST"
     * )
     * @param EntityManagerInterface $entityManager
     * @param Dispatch $dispatch
     * @param Request $request
     * @return JsonResponse
     */
    public function postDeliveryNote(EntityManagerInterface $entityManager,
                                     Dispatch $dispatch,
                                     Request $request): JsonResponse {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $deliveryNoteData = array_reduce(
            array_keys(Dispatch::DELIVERY_NOTE_DATA),
            function(array $carry, string $name) use ($data) {
                if (isset(Dispatch::DELIVERY_NOTE_DATA[$name])) {
                    $carry[$name] = $data[$name] ?? null;
                }

                return $carry;
            },
            []
        );
        $deliveryNoteData['deliveryNumber'] = $dispatch->getNumber();

        // TODO mettre un champ json avec toutes les data dans le dispatch
        // + ne sauvegarder uniquement les valeurs à true dans l'utilisateur
        $loggedUser->setLastDispatchDeliveryNoteData($deliveryNoteData);

        $entityManager->flush();

        return new JsonResponse(['success' => true]);

    }

    /**
     * @Route(
     *     "/{dispatch}/delivery-note",
     *     name="print_delivery_note_dispatch",
     *     options={"expose"=true},
     *     methods="GET"
     * )
     * @param EntityManagerInterface $entityManager
     * @param Dispatch $dispatch
     * @param Request $request
     * @return JsonResponse
     */
    public function printDeliveryNote(EntityManagerInterface $entityManager,
                                      Dispatch $dispatch,
                                      Request $request): JsonResponse {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        // TODO mettre le champ json dans le dispatch

        $entityManager->flush();

        return new JsonResponse(['success' => true]);

    }
}
