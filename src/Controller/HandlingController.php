<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Menu;
use App\Entity\Handling;

use App\Entity\PieceJointe;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Service\AttachmentService;
use App\Service\FreeFieldService;
use App\Service\MailerService;
use App\Service\UserService;
use App\Service\HandlingService;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    /**
     * @var AttachmentService
     */
    private $attachmentService;


    public function __construct(UserService $userService,
                                MailerService $mailerService,
                                AttachmentService $attachmentService)
    {
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->attachmentService = $attachmentService;
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
			throw new NotFoundHttpException('404');
		}
    }

    /**
     * @Route("/", name="handling_index", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param string|null $filter
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager,
                          $filter = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $freeFieldsRepository = $entityManager->getRepository(ChampLibre::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);

        return $this->render('handling/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
            'statuts' => $statutRepository->findByCategorieName(Handling::CATEGORIE),
			'filterStatus' => $filter,
            'types' => $types,
            'modalNewConfig' => [
                'handlingDefaultStatus' => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::HANDLING),
                'freeFieldsTypes' => array_map(function (Type $type) use ($freeFieldsRepository) {
                    $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_HANDLING);
                    return [
                        'typeLabel' => $type->getLabel(),
                        'typeId' => $type->getId(),
                        'freeFields' => $freeFields,
                    ];
                }, $types),
                'handlingStatus' => $statutRepository->findByCategorieName(CategorieStatut::HANDLING, true, false),
            ]
		]);
    }

    /**
     * @Route("/voir", name="handling_show", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function show(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND)) {
				return $this->redirectToRoute('access_denied');
			}

			$handlingRepository = $entityManager->getRepository(Handling::class);
            $handling = $handlingRepository->find($data);
            $json = $this->renderView('handling/modalShowHandlingContent.html.twig', [
                'handling' => $handling,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/creer", name="handling_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param HandlingService $handlingService
     * @param FreeFieldService $freeFieldService
     * @param AttachmentService $attachmentService
     * @param TranslatorInterface $translator
     * @return Response
     * @throws Exception
     */
    public function new(EntityManagerInterface $entityManager,
                        Request $request,
                        HandlingService $handlingService,
                        FreeFieldService $freeFieldService,
                        AttachmentService $attachmentService,
                        TranslatorInterface $translator): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $post = $request->request;

            $date = new DateTime('now');
            $handling = new Handling();
            $creationDate = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $status = $statutRepository->find($post->get('status'));
            $type = $typeRepository->find($post->get('type'));
            $requester = $utilisateurRepository->find($post->get('requester'));
            $desiredDate = $post->get('desired-date') ? new \DateTime($post->get('desired-date')) : null;
            $fileBag = $request->files->count() > 0 ? $request->files : null;
            $number = $handlingService->createHandlingNumber($entityManager, $creationDate);

            if ($desiredDate < $date) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La date attendue ne peut être antérieure à la date de création.'
                ]);
            }

            $handling
                ->setNumber($number)
                ->setCreationDate($creationDate)
                ->setType($type)
                ->setRequester($requester)
                ->setSubject(substr($post->get('subject'), 0, 64))
                ->setSource($post->get('source'))
                ->setDestination($post->get('destination'))
                ->setStatus($status)
                ->setDesiredDate($desiredDate)
				->setComment($post->get('comment'))
                ->setEmergency($post->get('emergency'));

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
                    $handling->addAttachement($attachment);
                }
            }

            $entityManager->persist($handling);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => $translator->trans("services.La demande de service a bien été créée") . '.'
            ]);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="handling_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function editApi(EntityManagerInterface $entityManager,
                            Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $handlingRepository = $entityManager->getRepository(Handling::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $attachmentsRepository = $entityManager->getRepository(PieceJointe::class);

            $handling = $handlingRepository->find($data['id']);
            $json = $this->renderView('handling/modalEditHandlingContent.html.twig', [
                'handling' => $handling,
                'utilisateurs' => $utilisateurRepository->findAll(),
                'handlingStatus' => $statutRepository->findByCategorieName(CategorieStatut::HANDLING),
                'attachements' => $attachmentsRepository->findBy(['handling' => $handling]),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="handling_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param AttachmentService $attachmentService
     * @param TranslatorInterface $translator
     * @return Response
     * @throws Exception
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         FreeFieldService $freeFieldService,
                         TranslatorInterface $translator): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);

        $post = $request->request;

        $handling = $handlingRepository->find($post->get('id'));

        $desiredDate = $post->get('desired-date') ? new \DateTime($post->get('desired-date')) : null;
        $status = $statutRepository->find( $post->get('status'));
        $validationDate = $status->getTreated() ? (new DateTime('now', new DateTimeZone('Europe/Paris'))) : null;

        $handling
            ->setSubject(substr($post->get('subject'), 0, 64))
            ->setSource($post->get('source'))
            ->setDestination($post->get('destination'))
            ->setStatus($status)
            ->setDesiredDate($desiredDate)
            ->setComment($post->get('comment'))
            ->setEmergency($post->get('emergency'));

        if (!$handling->getValidationDate()) {
            $handling->setValidationDate($validationDate);
        }

        $freeFieldService->manageFreeFields($handling, $post->all(), $entityManager);

        $listAttachmentIdToKeep = $data['files'] ?? [];

        $attachments = $handling->getAttachements()->toArray();
        foreach ($attachments as $attachment) {
            /** @var PieceJointe $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, null, null, null, $handling);
            }
        }

        $this->persistAttachments($handling, $this->attachmentService, $request, $entityManager);

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => $translator->trans("services.La demande de service a bien été modifiée") . '.'
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
            $entity->addAttachement($attachment);
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
            $handling = $handlingRepository->find($data['handling']);

            $entityManager->remove($handling);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => $translator->trans('services.La demande de service a bien été supprimée').'.'
            ]);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/infos", name="get_handlings_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getOrdreLivraisonIntels(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

            $dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
            $dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $handlingRepository = $entityManager->getRepository(Handling::class);
            $handlings = $handlingRepository->findByDates($dateTimeMin, $dateTimeMax);

            $headers = [
                'date création',
                'demandeur',
                'chargement',
                'déchargement',
                'date attendue',
                'date de réalisation',
                'statut',
            ];

            $data = [];
            $data[] = $headers;

            foreach ($handlings as $handling) {
                $this->buildInfos($handling, $data);
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }


    private function buildInfos(Handling $handling, &$data)
    {
        $data[] =
            [
                $handling->getCreationDate()->format('d/m/Y H:i'),
                $handling->getRequester()->getUsername(),
                $handling->getSource(),
                $handling->getDestination(),
                $handling->getDesiredDate()->format('d/m/Y H:i'),
                $handling->getValidationDate() ? $handling->getValidationDate()->format('d/m/Y H:i') : '',
                $handling->getStatus() ? $handling->getStatus()->getNom() : '',
            ];
    }
}
