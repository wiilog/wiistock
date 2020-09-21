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
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FreeFieldService;
use App\Service\PackService;
use App\Service\PDFGeneratorService;
use App\Service\SpecificService;
use App\Service\UserService;
use App\Service\DispatchService;

use DateTime;
use DateTimeZone;
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
class DispatchController extends AbstractController {
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
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager) {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_ACHE)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

        $dispatchBusinessUnits = json_decode($parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_BUSINESS_UNIT_VALUES));

        return $this->render('dispatch/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, true),
            'types' => $types,
            'modalNewConfig' => [
                'dispatchBusinessUnits' => !empty($dispatchBusinessUnits) ? $dispatchBusinessUnits : [],
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
			    'notTreatedStatus' => $statutRepository->findStatusByType(CategorieStatut::DISPATCH, null, [Statut::NOT_TREATED, Statut::DRAFT]),
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
                        DispatchService $dispatchService): Response {
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
                        TranslatorInterface $translator): Response {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE) ||
                !$this->userService->hasRightFunction(Menu::DEM, Action::CREATE_ACHE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;
            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $transporterRepository = $entityManager->getRepository(Transporteur::class);

            $dispatch = new Dispatch();
            $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));

            $fileBag = $request->files->count() > 0 ? $request->files : null;
            $locationTake = $emplacementRepository->find($post->get('prise'));
            $locationDrop = $emplacementRepository->find($post->get('depose'));

            $comment = $post->get('commentaire');
            $startDateRaw = $post->get('startDate');
            $endDateRaw = $post->get('endDate');
            $carrier = $post->get('carrier');
            $carrierTrackingNumber = $post->get('carrierTrackingNumber');
            $commandNumber = $post->get('commandNumber');
            $receiver = $post->get('receiver');
            $emergency = $post->get('emergency');
            $projectNumber = $post->get('projectNumber');
            $businessUnit = $post->get('businessUnit');

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
                ->setBusinessUnit($businessUnit)
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

            if (!empty($carrier)) {
                $dispatch->setCarrier($transporterRepository->find($carrier) ?? null);
            }

            if (!empty($carrierTrackingNumber)) {
                $dispatch->setCarrierTrackingNumber($carrierTrackingNumber);
            }

            if (!empty($commandNumber)) {
                $dispatch->setCommandNumber($commandNumber);
            }

            if (!empty($receiver)) {
                $dispatch->setReceiver($utilisateurRepository->find($receiver) ?? null);
            }

            if (!empty($emergency)) {
                $dispatch->setUrgent($post->getBoolean('urgent'));
            }

            if (!empty($projectNumber)) {
                $dispatch->setProjectNumber($projectNumber);
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
                'msg' => $translator->trans('acheminement.L\'acheminement a bien été créé') . '.'
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
                         DispatchService $dispatchService, TranslatorInterface $translator) {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_ACHE)) {
            return $this->redirectToRoute('access_denied');
        }

        $natureRepository = $entityManager->getRepository(Nature::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $dispatchStatus = $dispatch->getStatut();

        return $this->render('dispatch/show.html.twig', [
            'dispatch' => $dispatch,
            'detailsConfig' => $dispatchService->createHeaderDetailsConfig($dispatch),
            'modifiable' => !$dispatchStatus || $dispatchStatus->isDraft(),
            'newPackConfig' => [
                'natures' => $natureRepository->findAll()
            ],
            'dispatchValidate' => [
                'untreatedStatus' => $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::NOT_TREATED])
            ],
            'dispatchTreat' => [
                'treatedStatus' => $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::TREATED])
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
                                            TranslatorInterface $translator): ?Response {
        if ($dispatch->getDispatchPacks()->isEmpty()) {
            throw new NotFoundHttpException($translator->trans('acheminement.Le bon d\'acheminement n\'existe pas pour cet acheminement'));
        }

        $packsConfig = $dispatch->getDispatchPacks()
            ->map(function (DispatchPack $dispatchPack) use ($dispatch, $translator) {
                return [
                    'title' => 'Acheminement n°' . $dispatch->getId(),
                    'code' => $dispatchPack->getPack()->getCode(),
                    'content' => [
                        'Date de création' => $dispatch->getCreationDate() ? $dispatch->getCreationDate()->format('d/m/Y H:i:s') : '',
                        'Date de validation' => $dispatch->getValidationDate() ? $dispatch->getValidationDate()->format('d/m/Y H:i:s') : '',
                        'Demandeur' => $dispatch->getRequester() ? $dispatch->getRequester()->getUsername() : '',
                        'Destinataire' => $dispatch->getReceiver() ? $dispatch->getReceiver()->getUsername() : '',
                        $translator->trans('acheminement.Emplacement dépose') => $dispatch->getLocationTo() ? $dispatch->getLocationTo()->getLabel() : '',
                        $translator->trans('acheminement.Emplacement prise') => $dispatch->getLocationFrom() ? $dispatch->getLocationFrom()->getLabel() : ''
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
        $transporterRepository = $entityManager->getRepository(Transporteur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $post = $request->request;
        $dispatch = $dispatchRepository->find($post->get('id'));

        if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT) ||
            !$dispatch->getStatut()->isTreated() && !$this->userService->hasRightFunction(Menu::DEM, Action::EDIT_UNPROCESSED_DISPATCH)) {
            return $this->redirectToRoute('access_denied');
        }

        $startDateRaw = $post->get('startDate');
        $endDateRaw = $post->get('endDate');
        $startDate = !empty($startDateRaw) ? $dispatchService->createDateFromStr($startDateRaw) : null;
        $endDate = !empty($endDateRaw) ? $dispatchService->createDateFromStr($endDateRaw) : null;

        $locationTake = $emplacementRepository->find($post->get('prise'));
        $locationDrop = $emplacementRepository->find($post->get('depose'));

        $oldStatus = $dispatch->getStatut();
        if (!$oldStatus || !$oldStatus->isTreated()) {
            $newStatus = $statutRepository->find($post->get('statut'));
            $dispatch->setStatut($newStatus);
        } else {
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
        $carrierData = $post->get('carrier');
        $receiver = $receiverData ? $utilisateurRepository->find($receiverData) : null;
        $requester = $requesterData ? $utilisateurRepository->find($requesterData) : null;
        $carrier = $carrierData ? $transporterRepository->find($carrierData) : null;

        $transporterTrackingNumber = $post->get('transporterTrackingNumber');
        $commandNumber = $post->get('commandNumber');
        $projectNumber = $post->get('projectNumber');
        $businessUnit = $post->get('businessUnit');

        $dispatch
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setBusinessUnit($businessUnit)
            ->setCarrier($carrier)
            ->setCarrierTrackingNumber($transporterTrackingNumber)
            ->setCommandNumber($commandNumber)
            ->setRequester($requester)
            ->setReceiver($receiver)
            ->setUrgent($post->getBoolean('urgent'))
            ->setLocationFrom($locationTake)
            ->setLocationTo($locationDrop)
            ->setProjectNumber($projectNumber)
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
                'modifiable' => !$dispatchStatus || $dispatchStatus->isDraft(),
                'showDetails' => $dispatchService->createHeaderDetailsConfig($dispatch)
            ]),
            'success' => true,
            'msg' => $translator->trans('acheminement.L\'acheminement a bien été modifié') . '.'
        ]);
    }

    /**
     * @Route("/api-modifier", name="dispatch_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $pieceJointeRepository = $entityManager->getRepository(PieceJointe::class);
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

            $dispatch = $dispatchRepository->find($data['id']);
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

            $dispatchBusinessUnits = json_decode($parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_BUSINESS_UNIT_VALUES));

            $json = $this->renderView('dispatch/modalEditContentDispatch.html.twig', [
                'dispatchBusinessUnits' => !empty($dispatchBusinessUnits) ? $dispatchBusinessUnits : [],
                'dispatch' => $dispatch,
                'fieldsParam' => $fieldsParam,
                'utilisateurs' => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
                'statuses' => $statuses,
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
                           TranslatorInterface $translator): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            $attachmentRepository = $entityManager->getRepository(PieceJointe::class);

            $dispatch = $dispatchRepository->find($data['dispatch']);

            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DELETE) ||
                !$this->userService->hasRightFunction(Menu::DEM, Action::DELETE_ACHE) ||
                !$dispatch->getStatut()->isTreated() && !$this->userService->hasRightFunction(Menu::DEM, Action::DELETE_UNPROCESSED_DISPATCH)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($dispatch) {
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
    private function persistAttachments(Dispatch $entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager) {
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
    public function apiPack(Dispatch $dispatch): Response {
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
                            'modifiable' => !$dispatchPack->getDispatch()->getStatut()->isDraft()
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
        } else {
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
                               EntityManagerInterface $entityManager): Response {
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
     * @param TranslatorInterface $translator
     * @param Dispatch $dispatch
     * @return Response
     * @throws Exception
     */
    public function validateDispatchRequest(Request $request,
                                            EntityManagerInterface $entityManager,
                                            TranslatorInterface $translator,
                                            Dispatch $dispatch): Response
    {
        $status = $dispatch->getStatut();

        if(!$status || $status->isDraft()) {
            $data = json_decode($request->getContent(), true);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $data['status'];
            $untreatedStatus = $statusRepository->find($statusId);

            if ($untreatedStatus
                && $untreatedStatus->isNotTreated()
                && ($untreatedStatus->getType() === $dispatch->getType())) {

                $dispatch
                    ->setStatut($untreatedStatus)
                    ->setValidationDate(new DateTime('now', new \DateTimeZone('Europe/Paris')));

                $entityManager->flush();
            }
            else {
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

        if (!$status || $status->isNotTreated()) {
            $data = json_decode($request->getContent(), true);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $statusId = $data['status'];
            $treatedStatus = $statusRepository->find($statusId);

            if ($treatedStatus
                && $treatedStatus->isTreated()
                && $treatedStatus->getType() === $dispatch->getType()) {

                /** @var Utilisateur $loggedUser */
                $loggedUser = $this->getUser();

                $dispatchService->validateDispatchRequest($entityManager, $dispatch, $treatedStatus, $loggedUser);
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
     * @Route("/csv", name="get_dispatches_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @return Response
     */
    public function getDispatchesCSV(Request $request,
                                     CSVExportService $CSVExportService,
                                     EntityManagerInterface $entityManager,
                                     TranslatorInterface $translator): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (\Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $freeFieldsRepository = $entityManager->getRepository(ChampLibre::class);
            $freeFields = $freeFieldsRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_DISPATCH]);

            $freeFieldIds = array_map(
                function (ChampLibre $cl) {
                    return $cl->getId();
                },
                $freeFields
            );
            $freeFieldsHeader = array_map(
                function (ChampLibre $cl) {
                    return $cl->getLabel();
                },
                $freeFields
            );
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);

            $dispatches = $dispatchRepository->getByDates($dateTimeMin, $dateTimeMax);

            $csvHeader = array_merge(
                [
                    'Numéro demande',
                    'Date de création',
                    'Date de traitement',
                    'Type',
                    'Demandeur',
                    'Destinataire',
                    $translator->trans('acheminement.emplacement prise'),
                    $translator->trans('acheminement.emplacement dépose'),
                    'Nb ' . $translator->trans('colis.colis'),
                    'Statut',
                    'Urgence',
                    $translator->trans('natures.nature'),
                    'Code',
                    $translator->trans('acheminement.Quantité à acheminer'),
                    'Date dernier mouvement',
                    'Dernier emplacement',
                    'Opérateur'
                ],
                $freeFieldsHeader
            );

            return $CSVExportService->createBinaryResponseFromData(
                'export_acheminements.csv',
                $dispatches,
                $csvHeader,
                function ($dispatch) use ($freeFieldIds) {
                    $row = [];
                    $row[] = $dispatch['number'] ?? '';
                    $row[] = $dispatch['creationDate'] ? $dispatch['creationDate']->format('d/m/Y H:i:s') : '';
                    $row[] = $dispatch['validationDate'] ? $dispatch['validationDate']->format('d/m/Y H:i:s') : '';
                    $row[] = $dispatch['type'] ?? '';
                    $row[] = $dispatch['requester'] ?? '';
                    $row[] = $dispatch['receiver'] ?? '';
                    $row[] = $dispatch['locationFrom'] ?? '';
                    $row[] = $dispatch['locationTo'] ?? '';
                    $row[] = $dispatch['nbPacks'] ?? '';
                    $row[] = $dispatch['status'] ?? '';
                    $row[] = $dispatch['urgent'] ? 'oui' : 'non';
                    $row[] = $dispatch['packNatureLabel'] ?? '';
                    $row[] = $dispatch['packCode'] ?? '';
                    $row[] = $dispatch['packQuantity'] ?? '';
                    $row[] = $dispatch['lastMovement'] ? $dispatch['lastMovement']->format('Y/m/d H:i') : '';
                    $row[] = $dispatch['lastLocation'] ?? '';
                    $row[] = $dispatch['operator'] ?? '';

                    foreach ($freeFieldIds as $freeFieldId) {
                        $row[] = $dispatch['freeFields'][$freeFieldId] ?? "";
                    }
                    return [$row];
                }
            );
        } else {
            throw new NotFoundHttpException('404');
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
        $maxNumberOfPacks = 7;

        if ($dispatch->getDispatchPacks()->count() === 0) {
            $errorMessage = $translator->trans('acheminement.Des colis sont nécessaires pour générer un bon de livraison') . '.';
            return new JsonResponse('
                <div class="text-danger">' . $errorMessage . '</div>
            ');
        }

        $userSavedData = $loggedUser->getSavedDispatchDeliveryNoteData();
        $dispatchSavedData = $dispatch->getDeliveryNoteData();
        $defaultData = [
            'deliveryNumber' => $dispatch->getNumber(),
            'projectNumber' => $dispatch->getProjectNumber(),
            'username' => $loggedUser->getUsername(),
            'userPhone' => $loggedUser->getPhone(),
            'packs' => array_slice($dispatch->getDispatchPacks()->toArray(), 0, $maxNumberOfPacks),

            // TODO get real values
            'dispatchEmergency' => '24h'
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


        $json = $this->renderView('dispatch/modalPrintDeliveryNoteContent.html.twig', array_merge($deliveryNoteData, [
            // TODO get database values
            'dispatchEmergencyValues' => ['24h', '48h', '72h']
        ]));

        return new JsonResponse($json);

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
     * @param Request $request
     * @return JsonResponse
     */
    public function postDeliveryNote(EntityManagerInterface $entityManager,
                                     Dispatch $dispatch,
                                     Request $request): JsonResponse {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $userDataToSave = [];
        $dispatchDataToSave = [];

        // force dispatch number
        $data['deliveryNumber'] = $dispatch->getNumber();

        foreach (array_keys(Dispatch::DELIVERY_NOTE_DATA) as $deliveryNoteKey) {
            if (isset(Dispatch::DELIVERY_NOTE_DATA[$deliveryNoteKey])) {
                $value = $data[$deliveryNoteKey] ?? null;
                $dispatchDataToSave[$deliveryNoteKey] = $value;
                if (Dispatch::DELIVERY_NOTE_DATA[$deliveryNoteKey]) {
                    $userDataToSave[$deliveryNoteKey] = $value;
                }
            }
        }

        $loggedUser->setSavedDispatchDeliveryNoteData($userDataToSave);
        $dispatch->setDeliveryNoteData($dispatchDataToSave);

        $entityManager->flush();

        $message = 'Le téléchargement de votre bon de livraison va commencer...';
        $success = true;

        return new JsonResponse([
            'success' => $success,
            'msg' => $message
        ]);
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

        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));

        $isEmerson = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_EMERSON);

        $defaultData = [
            'carrier' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CARRIER),
            'dispatchDate' => $now->format('Y-m-d'),
            'consigner' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONSIGNER),
            'receiver' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_RECEIVER),
            'locationFrom' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_LOCATION_FROM),
            'locationTo' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_LOCATION_TO),

            'consignerUsername' => $isEmerson ? $loggedUser->getUsername() : null,
            'consignerEmail' => $isEmerson ? $loggedUser->getEmail() : null,
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

        $json = $this->renderView('dispatch/modalPrintWayBillContent.html.twig', array_merge($wayBillData, [
            'packsCounter' => $dispatch->getDispatchPacks()->count()
        ]));

        return new JsonResponse($json);
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
     * @param TranslatorInterface $translator
     * @param Request $request
     * @return JsonResponse
     */
    public function postDispatchWaybill(EntityManagerInterface $entityManager,
                                        Dispatch $dispatch,
                                        TranslatorInterface $translator,
                                        Request $request): JsonResponse {

        if ($dispatch->getDispatchPacks()->count() > 10) {
            $message = 'Attention : ' . $translator->trans("acheminement.L''acheminement contient plus 10 colis") . ', cette lettre de voiture ne peut contenir plus de 10 lignes.';
            $success = false;
        }
        else {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

            $data = json_decode($request->getContent(), true);

            $userDataToSave = [];
            $dispatchDataToSave = [];
            foreach (array_keys(Dispatch::WAYBILL_DATA) as $wayBillKey) {
                if (isset(Dispatch::WAYBILL_DATA[$wayBillKey])) {
                    $value = $data[$wayBillKey] ?? null;
                    $dispatchDataToSave[$wayBillKey] = $value;
                    if (Dispatch::WAYBILL_DATA[$wayBillKey]) {
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

        return new JsonResponse([
            'success' => $success,
            'msg' => $message
        ]);
    }
}
