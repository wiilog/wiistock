<?php

namespace App\Controller;

use App\Entity\Acheminements;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\Menu;

use App\Entity\Pack;
use App\Entity\PieceJointe;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Repository\PieceJointeRepository;
use App\Service\AttachmentService;
use App\Service\FreeFieldService;
use App\Service\PDFGeneratorService;
use App\Service\UserService;
use App\Service\AcheminementsService;

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
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ACHE)) {
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

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ACHE)) {
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
            $acheminementNumber = $acheminementsService->createAcheminementNumber($entityManager, $date);

            $acheminements
                ->setDate($date)
                ->setStartDate($startDate ?: null)
                ->setEndDate($endDate ?: null)
                ->setUrgent($post->getBoolean('urgent'))
                ->setStatut($statutRepository->find($post->get('statut')))
                ->setType($typeRepository->find($post->get('type')))
                ->setRequester($utilisateurRepository->find($post->get('demandeur')))
                ->setReceiver($utilisateurRepository->find($post->get('destinataire')))
                ->setLocationFrom($locationTake)
                ->setLocationTo($locationDrop)
                ->setCommentaire($post->get('commentaire') ?? null)
                ->setNumeroAcheminement($acheminementNumber);

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

            $acheminementsService->sendMailToRecipient($acheminements, false);

            $response['acheminement'] = $acheminements->getId();
            $response['redirect'] = $this->generateUrl('acheminement-show', ['id' => $acheminements->getId()]);
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/voir/{id}", name="acheminement-show", options={"expose"=true}, methods="GET|POST")
     * @param Acheminements $acheminement
     * @param AcheminementsService $acheminementService
     * @return RedirectResponse|Response
     */
    public function show(Acheminements $acheminement, AcheminementsService $acheminementService)
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ACHE)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('acheminements/show.html.twig', [
            'acheminement' => $acheminement,
            'detailsConfig' => $acheminementService->createHeaderDetailsConfig($acheminement),
            'modifiable' => !$acheminement->getStatut()->getTreated(),
        ]);
    }

    /**
     * @Route("/{acheminement}/etat", name="print_acheminement_state_sheet", options={"expose"=true}, methods="GET")
     * @param Acheminements $acheminement
     * @param PDFGeneratorService $PDFGenerator
     * @return PdfResponse
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function printAcheminementStateSheet(Acheminements $acheminement,
                                                PDFGeneratorService $PDFGenerator): PdfResponse
    {
        $packs = $acheminement->getPacks();
        $now = new DateTime('now', new \DateTimeZone('Europe/Paris'));

        $fileName = 'Etat_acheminement_' . $acheminement->getId() . '.pdf';
        return new PdfResponse(
            $PDFGenerator->generatePDFStateSheet(
                $fileName,
                array_map(
                    function (string $pack) use ($acheminement, $now) {
                        return [
                            'title' => 'Acheminement n°' . $acheminement->getId(),
                            'code' => $pack,
                            'content' => [
                                'Date d\'acheminement' => $now->format('d/m/Y H:i'),
                                'Demandeur' => $acheminement->getRequester()->getUsername(),
                                'Destinataire' => $acheminement->getReceiver()->getUsername(),
                                $this->translator->trans('acheminement.emplacement dépose') => $acheminement->getLocationTo() ? $acheminement->getLocationTo()->getLabel() : '',
                                $this->translator->trans('acheminement.emplacement prise') => $acheminement->getLocationFrom() ? $acheminement->getLocationFrom()->getLabel() : ''
                            ]
                        ];
                    },
                    $packs
                )
            ),
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
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ACHEMINEMENT),
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

            $data = [
                'redirect' => $this->generateUrl('acheminements_index'),
            ];

            return new JsonResponse($data);
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
     * @Route("/non-vide", name="demande_acheminement_has_packs", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function hasPacks(Request $request,
                             EntityManagerInterface $entityManager): Response
    {
        return new JsonResponse(false);
    }
}
