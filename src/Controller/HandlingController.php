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
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

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

    public function __construct(UserService $userService,
                                MailerService $mailerService)
    {
        $this->userService = $userService;
        $this->mailerService = $mailerService;
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
     * @param Request $request
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager,
                          Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $freeFieldsRepository = $entityManager->getRepository(ChampLibre::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);

        $filterStatus = $request->query->get('filter');

        return $this->render('handling/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(Handling::CATEGORIE),
			'filterStatus' => $filterStatus,
            'types' => $types,
            'modalNewConfig' => [
                'defaultStatuses' => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::HANDLING),
                'freeFieldsTypes' => array_map(function (Type $type) use ($freeFieldsRepository) {
                    $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_HANDLING);
                    return [
                        'typeLabel' => $type->getLabel(),
                        'typeId' => $type->getId(),
                        'freeFields' => $freeFields,
                    ];
                }, $types),
                'handlingStatus' => $statutRepository->findStatusByType(CategorieStatut::HANDLING),
            ]
		]);
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
            $typeRepository = $entityManager->getRepository(Type::class);

            $post = $request->request;

            $handling = new Handling();
            $date = (new DateTime('now', new \DateTimeZone('Europe/Paris')));

            $status = $statutRepository->find($post->get('status'));
            $type = $typeRepository->find($post->get('type'));
            $desiredDate = $post->get('desired-date') ? new DateTime($post->get('desired-date')) : null;
            $fileBag = $request->files->count() > 0 ? $request->files : null;
            $number = $handlingService->createHandlingNumber($entityManager, $date);

            /** @var Utilisateur $requester */
            $requester = $this->getUser();

            $handling
                ->setNumber($number)
                ->setCreationDate($date)
                ->setType($type)
                ->setRequester($requester)
                ->setSubject(substr($post->get('subject'), 0, 64))
                ->setSource($post->get('source'))
                ->setDestination($post->get('destination'))
                ->setStatus($status)
                ->setDesiredDate($desiredDate)
				->setComment($post->get('comment'))
                ->setEmergency($post->getBoolean('emergency'));

            if ($status && $status->isTreated()) {
                $handling->setValidationDate($date);
            }

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
                    $handling->addAttachment($attachment);
                }
            }

            $entityManager->persist($handling);
            $entityManager->flush();

            $handlingService->sendEmailsAccordingToStatus($handling);

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
            $attachmentsRepository = $entityManager->getRepository(PieceJointe::class);

            $handling = $handlingRepository->find($data['id']);
            $status = $handling->getStatus();
            $statusTreated = $status && $status->isTreated();
            $json = $this->renderView('handling/modalEditHandlingContent.html.twig', [
                'handling' => $handling,
                'handlingStatus' => !$statusTreated
                    ? $statutRepository->findStatusByType(CategorieStatut::HANDLING, $handling->getType())
                    : [],
                'attachments' => $attachmentsRepository->findBy(['handling' => $handling]),
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
     * @param TranslatorInterface $translator
     * @param AttachmentService $attachmentService
     * @param HandlingService $handlingService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         FreeFieldService $freeFieldService,
                         TranslatorInterface $translator,
                         AttachmentService $attachmentService,
                         HandlingService $handlingService): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);

        $post = $request->request;

        $handling = $handlingRepository->find($post->get('id'));

        $date = (new DateTime('now', new DateTimeZone('Europe/Paris')));
        $desiredDateStr = $post->get('desired-date');
        $desiredDate = $desiredDateStr ? new DateTime($desiredDateStr) : null;

        $oldStatus = $handling->getStatus();

        if (!$oldStatus || !$oldStatus->isTreated()) {
            $newStatus = $statutRepository->find($post->get('status'));
            $handling->setStatus($newStatus);
        }
        else {
            $newStatus = null;
        }

        $handling
            ->setSubject(substr($post->get('subject'), 0, 64))
            ->setSource($post->get('source'))
            ->setDestination($post->get('destination'))
            ->setDesiredDate($desiredDate)
            ->setComment($post->get('comment') ?: '')
            ->setEmergency($post->getBoolean('emergency'));

        if (!$handling->getValidationDate() && $newStatus->isTreated()) {
            $handling->setValidationDate($date);
        }

        $freeFieldService->manageFreeFields($handling, $post->all(), $entityManager);

        $listAttachmentIdToKeep = $post->get('files') ?? [];

        $attachments = $handling->getAttachments()->toArray();
        foreach ($attachments as $attachment) {
            /** @var PieceJointe $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $attachmentService->removeAndDeleteAttachment($attachment, $handling);
            }
        }

        $this->persistAttachments($handling, $attachmentService, $request, $entityManager);

        $entityManager->flush();

        // check if status has changed
        if ((!$oldStatus && $newStatus)
            || (
                $oldStatus
                && $newStatus
                && ($oldStatus->getId() !== $newStatus->getId())
            )) {
            $handlingService->sendEmailsAccordingToStatus($handling);
        }

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
            $entity->addAttachment($attachment);
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
            $attachmentRepository = $entityManager->getRepository(PieceJointe::class);

            $handling = $handlingRepository->find($data['handling']);

            if ($handling) {
                $attachments = $attachmentRepository->findBy(['handling' => $handling]);
                foreach ($attachments as $attachment) {
                    $entityManager->remove($attachment);
                }
            }

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
