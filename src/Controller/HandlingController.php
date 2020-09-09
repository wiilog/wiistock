<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Emplacement;
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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
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
 * @Route("/service")
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
        $this->mailerService = $mailerService;;
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
     * @Route("/liste/{filter}", name="handling_index", options={"expose"=true}, methods={"GET", "POST"})
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
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        return $this->render('handling/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
            'statuts' => $statutRepository->findByCategorieName(Handling::CATEGORIE),
			'filterStatus' => $filter
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
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function new(EntityManagerInterface $entityManager,
                        Request $request,
                        HandlingService $handlingService,
                        FreeFieldService $freeFieldService,
                        AttachmentService $attachmentService,
                        TranslatorInterface $translator): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $post = $request->request;
            $status = $statutRepository->findOneByCategorieNameAndStatutCode(Handling::CATEGORIE, Handling::STATUT_A_TRAITER);
            $handling = new Handling();
            $creationDate = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $type = $typeRepository->find($data['type']);
            $desiredDate = $data['desired-date'] ? new \DateTime($data['desired-date']) : null;
            $validationDate = $data['validation-date'] ? new \DateTime($data['validation-date']) : null;
            $number = $handlingService->createHandlingNumber($entityManager, $creationDate);

            /** @var Utilisateur $requester */
            $requester = $this->getUser();

            $handling
                ->setNumber($number)
                ->setCreationDate($creationDate)
                ->setType($type)
                ->setRequester($requester)
                ->setSubject(substr($data['subject'], 0, 64))
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setStatus($status)
                ->setDesiredDate($desiredDate)
				->setValidationDate($validationDate)
				->setComment($data['commentaire'])
                ->setEmergency($data['emergency']);

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
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $handling = $handlingRepository->find($data['id']);
            $json = $this->renderView('handling/modalEditHandlingContent.html.twig', [
                'handling' => $handling,
                'utilisateurs' => $utilisateurRepository->findAll(),
                'emplacements' => $emplacementRepository->findAll(),
                'statusTreated' => ($handling->getStatus()->getNom() === Handling::STATUT_A_TRAITER) ? 1 : 0,
                'statuts' => $statutRepository->findByCategorieName(Handling::CATEGORIE),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="handling_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param HandlingService $handlingService
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param AttachmentService $attachmentService
     * @param TranslatorInterface $translator
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function edit(EntityManagerInterface $entityManager,
                         HandlingService $handlingService,
                         Request $request,
                         FreeFieldService $freeFieldService,
                         AttachmentService $attachmentService,
                         TranslatorInterface $translator): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $post = $request->request;
            $status = $statutRepository->findOneByCategorieNameAndStatutCode(Handling::CATEGORIE, Handling::STATUT_A_TRAITER);
            $handling = new Handling();
            $creationDate = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $type = $typeRepository->find($data['type']);
            $requester = $utilisateurRepository->find($data['demandeur']);
            $desiredDate = $data['desired-date'] ? new \DateTime($data['desired-date']) : null;
            $validationDate = $data['validation-date'] ? new \DateTime($data['validation-date']) : null;
            $number = $handlingService->createHandlingNumber($entityManager, $creationDate);

            $statutLabel = (intval($data['statut']) === 1) ? Handling::STATUT_A_TRAITER : Handling::STATUT_TRAITE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Handling::CATEGORIE, $statutLabel);
            if ($statut->getNom() === Handling::STATUT_TRAITE
                && $statut !== $handling->getStatus()) {
                $handlingService->sendTreatedEmail($handling);
                $handling->setValidationDate(new DateTime('now', new \DateTimeZone('Europe/Paris')));
            }

            $handling
                ->setNumber($number)
                ->setCreationDate($creationDate)
                ->setType($type)
                ->setRequester($requester)
                ->setSubject(substr($data['subject'], 0, 64))
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setStatus($status)
                ->setDesiredDate($desiredDate)
                ->setValidationDate($validationDate)
                ->setComment($data['commentaire'])
                ->setEmergency($data['emergency']);

            $freeFieldService->manageFreeFields($handling, $post->all(), $entityManager);

            $listAttachmentIdToKeep = $post->get('files') ?? [];

            $attachments = $handling->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $attachmentService->removeAndDeleteAttachment($attachment, null, null, null, $handling);
                }
            }

            $this->persistAttachments($handling, $attachmentService, $request, $entityManager);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => $translator->trans("services.La demande de service a bien été modifiée") . '.'
            ]);
        }
        throw new NotFoundHttpException('404');
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
