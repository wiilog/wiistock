<?php

namespace App\Controller;

use App\Entity\Acheminements;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\Menu;

use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\PackAcheminement;
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
use App\Service\AcheminementsService;

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
Class AcheminementsController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var AcheminementsService
     */
    private $acheminementsService;

    private $pieceJointeRepository;

    private $attachmentService;

    private $translator;

    public function __construct(UserService $userService,
                                AcheminementsService $acheminementsService,
                                PieceJointeRepository $pieceJointeRepository,
                                AttachmentService $attachmentService,
                                TranslatorInterface $translator)
    {
        $this->userService = $userService;
        $this->acheminementsService = $acheminementsService;
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->attachmentService = $attachmentService;
        $this->translator = $translator;
    }


    /**
     * @Route("/", name="acheminements_index")
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

        $listTypes = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_ACHEMINEMENT);

        $typeChampLibre = [];

        $freeFieldsGroupedByTypes = [];
        foreach ($listTypes as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_ACHEMINEMENT);
            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
            $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
        }

        return $this->render('acheminements/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
			'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ACHEMINEMENT),
			'notTreatedStatus' => $statutRepository->findByCategorieName(CategorieStatut::ACHEMINEMENT, true, true),
            'typeChampsLibres' => $typeChampLibre,
            'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
            'types' => $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_ACHEMINEMENT)
        ]);
    }

    /**
     * @Route("/api", name="acheminements_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {

            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_ACHE)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->acheminementsService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/creer", name="acheminements_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param AcheminementsService $acheminementsService
     * @param AttachmentService $attachmentService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function new(Request $request,
                        FreeFieldService $freeFieldService,
                        AcheminementsService $acheminementsService,
                        AttachmentService $attachmentService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            $post = $request->request;
            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $acheminements = new Acheminements();
            $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
            $fileBag = $request->files->count() > 0 ? $request->files : null;
            $locationTake = $emplacementRepository->find($post->get('prise'));
            $locationDrop = $emplacementRepository->find($post->get('depose'));

            $startDate = $acheminementsService->createDateFromStr($post->get('startDate'));
            $endDate = $acheminementsService->createDateFromStr($post->get('endDate'));
            $number = $acheminementsService->createDispatchNumber($entityManager, $date);

            $acheminements
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

            $freeFieldService->manageFreeFields($acheminements, $post->all(), $entityManager);

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
                    $acheminements->addAttachement($attachment);
                }
            }

            $entityManager->persist($acheminements);
            $entityManager->flush();

            $acheminementsService->sendMailsAccordingToStatus($acheminements, false);

            $response['acheminement'] = $acheminements->getId();
            $response['redirect'] = $this->generateUrl('acheminement-show', ['id' => $acheminements->getId()]);
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/voir/{id}", name="acheminement-show", options={"expose"=true}, methods="GET|POST")
     * @param Acheminements $acheminement
     * @param EntityManagerInterface $entityManager
     * @param AcheminementsService $acheminementService
     * @return RedirectResponse|Response
     */
    public function show(Acheminements $acheminement,
                         EntityManagerInterface $entityManager,
                         AcheminementsService $acheminementService)
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_ACHE)) {
            return $this->redirectToRoute('access_denied');
        }

        $natureRepository = $entityManager->getRepository(Nature::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        return $this->render('acheminements/show.html.twig', [
            'acheminement' => $acheminement,
            'detailsConfig' => $acheminementService->createHeaderDetailsConfig($acheminement),
            'modifiable' => !$acheminement->getStatut()->getTreated(),
            'newPackConfig' => [
                'natures' => $natureRepository->findAll()
            ],
            'dispatchValidate' => [
                'treatedStatus' => $statusRepository->findDispatchStatusTreatedByType($acheminement->getType())
            ]
        ]);
    }

    /**
     * @Route("/{dispatch}/etat", name="print_dispatch_state_sheet", options={"expose"=true}, methods="GET|POST")
     * @param Acheminements $dispatch
     * @param PDFGeneratorService $PDFGenerator
     * @return PdfResponse
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function printDispatchStateSheet(Acheminements $dispatch,
                                            PDFGeneratorService $PDFGenerator): ?Response
    {
        if ($dispatch->getPackAcheminements()->isEmpty()) {
            throw new NotFoundHttpException('La fiche d\'état n\'existe pas pour cet acheminement.');
        }

        $now = new DateTime('now', new \DateTimeZone('Europe/Paris'));

        $packsConfig = $dispatch->getPackAcheminements()
            ->map(function(PackAcheminement $packAcheminement) use ($dispatch, $now){
                return [
                    'title' => 'Acheminement n°' . $dispatch->getId(),
                    'code' => $packAcheminement->getPack()->getCode(),
                    'content' => [
                        'Date de création' => $now->format('d/m/Y H:i'),
                        'Date de validation' => $dispatch->getValidationDate() ? $dispatch->getValidationDate()->format('d/m/Y H:i:s') : '',
                        'Demandeur' => $dispatch->getRequester()->getUsername(),
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
     * @Route("/modifier", name="acheminement_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param AcheminementsService $acheminementsService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function edit(Request $request,
                         AcheminementsService $acheminementsService,
                         EntityManagerInterface $entityManager): Response {

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $acheminementsRepository = $entityManager->getRepository(Acheminements::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            /** @var Acheminements $acheminement */
            $post = $request->request;
            $acheminement = $acheminementsRepository->find($data['id']);

            $statutLabel = (intval($data['statut']) === 1) ? Acheminements::STATUT_A_TRAITER : Acheminements::STATUT_TRAITE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ACHEMINEMENT, $statutLabel);

            $acheminement->setStatut($statut);

            $startDate = $acheminementsService->createDateFromStr($data['startDate']);
            $endDate = $acheminementsService->createDateFromStr($data['endDate']);

            $locationTake = $emplacementRepository->find($data['prise']);
            $locationDrop = $emplacementRepository->find($data['depose']);

            $oldStatus = $acheminement->getStatut();
            $newStatus = $statutRepository->find($data['statut']);

            $acheminement
                ->setStartDate($startDate)
                ->setEndDate($endDate)
                ->setRequester($utilisateurRepository->find($data['demandeur']))
                ->setReceiver($utilisateurRepository->find($data['destinataire']))
                ->setUrgent((bool) $data['urgent'])
                ->setLocationFrom($locationTake)
                ->setLocationTo($locationDrop)
                ->setType($typeRepository->find($data['type']))
                ->setStatut($statutRepository->find($data['statut']))
                ->setCommentaire($data['commentaire'] ?? '');

            $listAttachmentIdToKeep = $post->get('files') ?? [];

            $attachments = $acheminement->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, null, null, null, $acheminement);
                }
            }

            $this->persistAttachments($acheminement, $this->attachmentService, $request, $entityManager);

            $entityManager->flush();

            if ((!$oldStatus && $newStatus)
                || (
                    $oldStatus
                    && $newStatus
                    && ($oldStatus->getId() !== $newStatus->getId())
                )) {
                $acheminementsService->sendMailsAccordingToStatus($acheminement, true);
            }

            $response = [
                'entete' => $this->renderView('acheminements/acheminement-show-header.html.twig', [
                    'acheminement' => $acheminement,
                    'modifiable' => !$acheminement->getStatut()->getTreated(),
                    'showDetails' => $acheminementsService->createHeaderDetailsConfig($acheminement)
                ]),
                'success' => true,
                'msg' => 'La ' . $this->translator->trans('acheminement.demande d\'acheminement') . ' a bien été modifiée.'
            ];
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="acheminement_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $acheminementsRepository = $entityManager->getRepository(Acheminements::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $listTypes = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_ACHEMINEMENT);

            $typeChampLibre = [];

            $freeFieldsGroupedByTypes = [];
            foreach ($listTypes as $type) {
                $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_ACHEMINEMENT);
                $typeChampLibre[] = [
                    'typeLabel' => $type->getLabel(),
                    'typeId' => $type->getId(),
                    'champsLibres' => $champsLibres,
                ];
                $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
            }

            $acheminement = $acheminementsRepository->find($data['id']);
            $json = $this->renderView('acheminements/modalEditContentAcheminements.html.twig', [
                'acheminement' => $acheminement,
                'utilisateurs' => $utilisateurRepository->findBy([], ['username' => 'ASC']),
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ACHEMINEMENT, true, true),
                'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
                'typeChampsLibres' => $typeChampLibre,
                'attachements' => $this->pieceJointeRepository->findBy(['acheminement' => $acheminement]),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="acheminement_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $acheminementsRepository = $entityManager->getRepository(Acheminements::class);

            $acheminements = $acheminementsRepository->find($data['acheminements']);
            $entityManager->remove($acheminements);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('acheminements_index')
            ]);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @param Acheminements $entity
     * @param AttachmentService $attachmentService
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     */
    private function persistAttachments(Acheminements $entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager)
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
     * @param Acheminements $dispatch
     * @return Response
     */
    public function apiPack(Acheminements $dispatch): Response
    {
        return new JsonResponse([
            'data' => $dispatch->getPackAcheminements()
                ->map(function (PackAcheminement $packAcheminement) {
                    $pack = $packAcheminement->getPack();
                    $lastTracking = $pack->getLastTracking();
                    return [
                        'nature' => $pack->getNature() ? $pack->getNature()->getLabel() : '',
                        'code' => $pack->getCode(),
                        'quantity' => $packAcheminement->getQuantity(),
                        'lastMvtDate' => $lastTracking ? ($lastTracking->getDatetime() ? $lastTracking->getDatetime()->format('d/m/Y H:i') : '') : '',
                        'lastLocation' => $lastTracking ? ($lastTracking->getEmplacement() ? $lastTracking->getEmplacement()->getLabel() : '') : '',
                        'operator' => $lastTracking ? ($lastTracking->getOperateur() ? $lastTracking->getOperateur()->getUsername() : '') : '',
                        'actions' => $this->renderView('acheminements/datatablePackRow.html.twig', [
                            'pack' => $pack,
                            'packDispatch' => $packAcheminement,
                            'modifiable' => !$packAcheminement->getAcheminement()->getStatut()->getTreated()
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
     * @param Acheminements $dispatch
     * @return Response
     */
    public function newPack(Request $request,
                            EntityManagerInterface $entityManager,
                            TranslatorInterface $translator,
                            PackService $packService,
                            Acheminements $dispatch): Response {
        $data = json_decode($request->getContent(), true);

        $packCode = $data['pack'];
        $natureId = $data['nature'];
        $quantity = $data['quantity'];

        $alreadyCreated = !$dispatch
            ->getPackAcheminements()
            ->filter(function (PackAcheminement $packAcheminement) use ($packCode) {
                $pack = $packAcheminement->getPack();
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

            $packDispatch = new PackAcheminement();
            $packDispatch
                ->setPack($pack)
                ->setAcheminement($dispatch);
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

        $packDispatchRepository = $entityManager->getRepository(PackAcheminement::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $packDispatchId = $data['packDispatchId'];
        /** @var PackAcheminement $packDispatch */
        $packDispatch = $packDispatchRepository->find($packDispatchId);
        if (empty($packDispatch)) {
            $success = false;
            $message = $translator->trans("acheminement.Le colis n''existe pas");
        } else {
            $natureId = $data['nature'];
            $quantity = $data['quantity'];

            $pack = $packDispatch->getPack();

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
     * @Route("/packs/delete", name="dispatch_delete_pack", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deletePack(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $packDispatchRepository = $entityManager->getRepository(PackAcheminement::class);

            $pack = $packDispatchRepository->find($data['pack']);
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
     * @param AcheminementsService $acheminementsService
     * @param TranslatorInterface $translator
     * @param Acheminements $acheminement
     * @return Response
     * @throws Exception
     */
    public function validateDispatchRequest(Request $request,
                                            EntityManagerInterface $entityManager,
                                            AcheminementsService $acheminementsService,
                                            TranslatorInterface $translator,
                                            Acheminements $acheminement): Response
    {
        $status = $acheminement->getStatut();

        if(!$status || !$status->getTreated()) {
            $data = json_decode($request->getContent(), true);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $now = new DateTime('now');
            $statusId = $data['status'];
            $treatedStatus = $statusRepository->find($statusId);

            if ($treatedStatus
                && $treatedStatus->getTreated()
                && $treatedStatus->getType() === $acheminement->getType()) {

                /** @var Utilisateur $loggedUser */
                $loggedUser = $this->getUser();

                $acheminementsService->validateDispatchRequest($entityManager, $acheminement, $treatedStatus, $loggedUser);
                $acheminement->setValidationDate($now);

                $entityManager->flush();
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
            'redirect' => $this->generateUrl('acheminement-show', ['id' => $acheminement->getId()])
        ]);
    }

    /**
     * @Route("/{dispatch}/packs-counter", name="get_dispatch_packs_counter", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param Acheminements $dispatch
     * @return JsonResponse
     */
    public function getDispatchPackCounter(Acheminements $dispatch) {
        return new JsonResponse([
            'success' => true,
            'packsCounter' => $dispatch->getPackAcheminements()->count()
        ]);
    }
}
