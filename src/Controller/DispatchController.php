<?php

namespace App\Controller;

use App\Entity\Dispatch;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\Menu;

use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\DispatchPack;
use App\Entity\PieceJointe;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Repository\PieceJointeRepository;
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

    private $pieceJointeRepository;

    private $attachmentService;

    private $translator;

    public function __construct(UserService $userService,
                                PieceJointeRepository $pieceJointeRepository,
                                AttachmentService $attachmentService,
                                TranslatorInterface $translator) {
        $this->userService = $userService;
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->attachmentService = $attachmentService;
        $this->translator = $translator;
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
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

        $listTypes = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_DISPATCH);

        $typeChampLibre = [];

        $freeFieldsGroupedByTypes = [];
        foreach ($listTypes as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_DISPATCH);
            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
            $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
        }

        return $this->render('dispatch/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
			'statuts' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH),
			'notTreatedStatus' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, true, true),
            'typeChampsLibres' => $typeChampLibre,
            'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
            'types' => $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_DISPATCH)
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
     * @return Response
     * @throws Exception
     */
    public function new(Request $request,
                        FreeFieldService $freeFieldService,
                        DispatchService $dispatchService,
                        AttachmentService $attachmentService,
                        EntityManagerInterface $entityManager): Response
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

            $startDate = $dispatchService->createDateFromStr($post->get('startDate'));
            $endDate = $dispatchService->createDateFromStr($post->get('endDate'));
            $number = $dispatchService->createDispatchNumber($entityManager, $date);

            if ($startDate && $endDate && $startDate > $endDate) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La date de fin d\'échéance est inférieure à la date de début.'
                ]);
            }

            $dispatch
                ->setCreationDate($date)
                ->setStartDate($startDate ?: null)
                ->setEndDate($endDate ?: null)
                ->setUrgent($post->getBoolean('urgent'))
                ->setStatut($statutRepository->find($post->get('statut')))
                ->setType($typeRepository->find($post->get('type')))
                ->setRequester($utilisateurRepository->find($post->get('demandeur')) ?? null)
                ->setReceiver($utilisateurRepository->find($post->get('destinataire')) ?? null)
                ->setLocationFrom($locationTake)
                ->setLocationTo($locationDrop)
                ->setCommentaire($post->get('commentaire') ?? null)
                ->setNumber($number);

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
                    $dispatch->addAttachement($attachment);
                }
            }

            $entityManager->persist($dispatch);
            $entityManager->flush();

            $dispatchService->sendMailsAccordingToStatus($dispatch, false);

            $response['dispatch'] = $dispatch->getId();
            $response['redirect'] = $this->generateUrl('dispatch_show', ['id' => $dispatch->getId()]);
            return new JsonResponse($response);
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

        return $this->render('dispatch/show.html.twig', [
            'dispatch' => $dispatch,
            'detailsConfig' => $dispatchService->createHeaderDetailsConfig($dispatch),
            'modifiable' => !$dispatch->getStatut()->getTreated(),
            'newPackConfig' => [
                'natures' => $natureRepository->findAll()
            ],
            'dispatchValidate' => [
                'treatedStatus' => $statusRepository->findDispatchStatusTreatedByType($dispatch->getType())
            ]
        ]);
    }

    /**
     * @Route("/{dispatch}/etat", name="print_dispatch_state_sheet", options={"expose"=true}, methods="GET|POST")
     * @param Dispatch $dispatch
     * @param PDFGeneratorService $PDFGenerator
     * @return PdfResponse
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function printDispatchStateSheet(Dispatch $dispatch,
                                            PDFGeneratorService $PDFGenerator): ?Response
    {
        if ($dispatch->getDispatchPacks()->isEmpty()) {
            throw new NotFoundHttpException('La fiche d\'état n\'existe pas pour cet acheminement.');
        }

        $packsConfig = $dispatch->getDispatchPacks()
            ->map(function(DispatchPack $dispatchPack) use ($dispatch){
                return [
                    'title' => 'Acheminement n°' . $dispatch->getId(),
                    'code' => $dispatchPack->getPack()->getCode(),
                    'content' => [
                        'Date de création' => $dispatch->getCreationDate() ? $dispatch->getCreationDate()->format('d/m/Y H:i:s') : '',
                        'Date de validation' => $dispatch->getValidationDate() ? $dispatch->getValidationDate()->format('d/m/Y H:i:s') : '',
                        'Demandeur' => $dispatch->getRequester() ? $dispatch->getRequester()->getUsername() : '',
                        'Destinataire' => $dispatch->getReceiver() ? $dispatch->getReceiver()->getUsername() : '',
                        $this->translator->trans('acheminement.emplacement dépose') => $dispatch->getLocationTo() ? $dispatch->getLocationTo()->getLabel() : '',
                        $this->translator->trans('acheminement.emplacement prise') => $dispatch->getLocationFrom() ? $dispatch->getLocationFrom()->getLabel() : ''
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
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request,
                         DispatchService $dispatchService,
                         EntityManagerInterface $entityManager): Response {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $post = $request->request;
        $dispatch = $dispatchRepository->find($post->get('id'));

        $startDate = $dispatchService->createDateFromStr($post->get('startDate'));
        $endDate = $dispatchService->createDateFromStr($post->get('endDate'));

        $locationTake = $emplacementRepository->find($post->get('prise'));
        $locationDrop = $emplacementRepository->find($post->get('depose'));

        $oldStatus = $dispatch->getStatut();
        $newStatus = $statutRepository->find($post->get('statut'));

        if ($startDate && $endDate && $startDate > $endDate) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'La date de fin d\'échéance est antérieure à la date de début.'
            ]);
        }

        $dispatch
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setRequester($utilisateurRepository->find($post->get('demandeur')))
            ->setReceiver($utilisateurRepository->find($post->get('destinataire')))
            ->setUrgent((bool) $post->get('urgent'))
            ->setLocationFrom($locationTake)
            ->setLocationTo($locationDrop)
            ->setType($typeRepository->find($post->get('type')))
            ->setStatut($statutRepository->find($post->get('statut')))
            ->setCommentaire($post->get('commentaire') ?: '');

        $listAttachmentIdToKeep = $post->get('files') ?? [];

        $attachments = $dispatch->getAttachements()->toArray();
        foreach ($attachments as $attachment) {
            /** @var PieceJointe $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, null, null, null, $dispatch);
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
            $dispatchService->sendMailsAccordingToStatus($dispatch, true);
        }

        $dispatchStatus = $dispatch->getStatut();

        $response = [
            'entete' => $this->renderView('dispatch/dispatch-show-header.html.twig', [
                'dispatch' => $dispatch,
                'modifiable' => !$dispatchStatus || !$dispatchStatus->getTreated(),
                'showDetails' => $dispatchService->createHeaderDetailsConfig($dispatch)
            ]),
            'success' => true,
            'msg' => 'La ' . $this->translator->trans('acheminement.demande d\'acheminement') . ' a bien été modifiée.'
        ];
        return new JsonResponse($response);
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
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $listTypes = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_DISPATCH);

            $typeChampLibre = [];

            $freeFieldsGroupedByTypes = [];
            foreach ($listTypes as $type) {
                $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_DISPATCH);
                $typeChampLibre[] = [
                    'typeLabel' => $type->getLabel(),
                    'typeId' => $type->getId(),
                    'champsLibres' => $champsLibres,
                ];
                $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
            }

            $dispatch = $dispatchRepository->find($data['id']);
            $json = $this->renderView('dispatch/modalEditContentDispatch.html.twig', [
                'dispatch' => $dispatch,
                'utilisateurs' => $utilisateurRepository->findBy([], ['username' => 'ASC']),
                'notTreatedStatus' => $statutRepository->findByCategorieName(CategorieStatut::DISPATCH, true, true),
                'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
                'typeChampsLibres' => $typeChampLibre,
                'attachements' => $this->pieceJointeRepository->findBy(['dispatch' => $dispatch]),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="dispatch_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
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
            }
            $entityManager->remove($dispatch);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('dispatch_index')
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
            $entity->addAttachement($attachment);
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
            $message = $translator->trans('acheminement.Le colis a bien été sauvegardé');
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
            $message = $translator->trans("acheminement.Le colis n''existe pas");
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
            $message = $translator->trans('acheminement.Le colis a bien été sauvegardé');
        }
        return new JsonResponse([
            'success' => $success,
            'msg' => $message
        ]);
    }

    /**
     * @Route("/packs/delete", name="dispatch_delete_pack", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deletePack(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);

            $pack = $dispatchPackRepository->find($data['pack']);
            $entityManager->remove($pack);
            $entityManager->flush();

            $data = [
                'success' => true,
                'msg' => 'Le colis a bien été supprimé.'
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
}
