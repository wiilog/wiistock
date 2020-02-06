<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\MouvementTraca;
use App\Entity\PieceJointe;

use App\Repository\ColisRepository;
use App\Repository\EmplacementRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;

use App\Service\AttachmentService;
use App\Service\MouvementTracaService;
use App\Service\UserService;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

/**
 * @Route("/mouvement-traca")
 */
class MouvementTracaController extends AbstractController
{
    /**
     * @var MouvementTracaRepository
     */
    private $mouvementRepository;

    /**
     * @var UserService
     */
    private $userService;

	/**
	 * @var AttachmentService
	 */
    private $attachmentService;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;


    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ColisRepository
     */
    private $colisRepository;

	/**
	 * @var TypeRepository
	 */
    private $typeRepository;

	/**
	 * @var MouvementTracaService
	 */
    private $mouvementTracaService;

	/**
	 * ArrivageController constructor.
	 * @param MouvementTracaService $mouvementTracaService
	 * @param AttachmentService $attachmentService
	 * @param TypeRepository $typeRepository
	 * @param EmplacementRepository $emplacementRepository
	 * @param UtilisateurRepository $utilisateurRepository
	 * @param StatutRepository $statutRepository
	 * @param UserService $userService
	 * @param MouvementTracaRepository $mouvementTracaRepository
	 */

    public function __construct(MouvementTracaService $mouvementTracaService, ColisRepository $colisRepository, AttachmentService $attachmentService, TypeRepository $typeRepository, EmplacementRepository $emplacementRepository, UtilisateurRepository $utilisateurRepository, StatutRepository $statutRepository, UserService $userService, MouvementTracaRepository $mouvementTracaRepository)
    {
        $this->colisRepository = $colisRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->statutRepository = $statutRepository;
        $this->userService = $userService;
        $this->mouvementRepository = $mouvementTracaRepository;
        $this->typeRepository = $typeRepository;
        $this->attachmentService = $attachmentService;
        $this->mouvementTracaService = $mouvementTracaService;
    }

    /**
     * @Route("/", name="mvt_traca_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('mouvement_traca/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
            'emplacements' => $this->emplacementRepository->findAll(),
        ]);
    }

	/**
	 * @Route("/creer", name="mvt_traca_new", options={"expose"=true}, methods="GET|POST")
	 * @param Request $request
	 * @return Response
	 * @throws Exception
	 */
	public function new(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
				return $this->redirectToRoute('access_denied');
			}

			$post = $request->request;
			$em = $this->getDoctrine()->getManager();

			$date = new DateTime($post->get('datetime'), new \DateTimeZone('Europe/Paris'));
			$type = $this->statutRepository->find($post->get('type'));
			$location = $this->emplacementRepository->find($post->get('emplacement'));
			$operator = $this->utilisateurRepository->find($post->get('operator'));

			$mvtTraca = new MouvementTraca();
			$mvtTraca
				->setDatetime($date)
				->setOperateur($operator)
				->setColis($post->get('colis'))
				->setType($type)
				->setEmplacement($location)
				->setCommentaire($post->get('commentaire') ?? null);

			$em->persist($mvtTraca);
			$em->flush();

			$this->attachmentService->addAttachements($request->files, null, null, $mvtTraca);
            $em->flush();

			return new JsonResponse(true);
		}
		throw new NotFoundHttpException('404 not found');
	}

    /**
     * @Route("/api", name="mvt_traca_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->mouvementTracaService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

	/**
	 * @Route("/api-modifier", name="mvt_traca_api_edit", options={"expose"=true}, methods="GET|POST")
	 */
	public function editApi(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
				return $this->redirectToRoute('access_denied');
			}

			$mvt = $this->mouvementRepository->find($data['id']);

			$json = $this->renderView('mouvement_traca/modalEditMvtTracaContent.html.twig', [
				'mvt' => $mvt,
				'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
				'attachements' => $mvt->getAttachements()
			]);

			return new JsonResponse($json);
		}
		throw new NotFoundHttpException('404');
	}

	/**
	 * @Route("/modifier", name="mvt_traca_edit", options={"expose"=true}, methods="GET|POST")
	 * @param Request $request
	 * @return Response
	 */
	public function edit(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
				return $this->redirectToRoute('access_denied');
			}

			$post = $request->request;

			$date = DateTime::createFromFormat(DateTime::ATOM, $post->get('datetime') . ':00P', new \DateTimeZone('Europe/Paris'));
			$type = $this->statutRepository->find($post->get('type'));
			$location = $this->emplacementRepository->find($post->get('emplacement'));
			$operator = $this->utilisateurRepository->find($post->get('operator'));

			$mvt = $this->mouvementRepository->find($post->get('id'));
			$mvt
				->setDatetime($date)
				->setOperateur($operator)
				->setColis($post->get('colis'))
				->setType($type)
				->setEmplacement($location)
				->setCommentaire($post->get('commentaire'));

			$em = $this->getDoctrine()->getManager();
			$em->flush();

			$listAttachmentIdToKeep = $post->get('files');

			$attachments = $mvt->getAttachements()->toArray();
			foreach ($attachments as $attachment) { /** @var PieceJointe $attachment */
				if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
					$this->attachmentService->removeAndDeleteAttachment($attachment, null, null, $mvt);
				}
			}

			$this->attachmentService->addAttachements($request->files, null, null, $mvt);
            $em->flush();

			return new JsonResponse();
		}
		throw new NotFoundHttpException('404');
	}


	/**
     * @Route("/supprimer", name="mvt_traca_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mvt = $this->mouvementRepository->find($data['mvt']);

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($mvt);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/mouvement-traca-infos", name="get_mouvements_traca_for_csv", options={"expose"=true}, methods={"GET","POST"})
	 * @param Request $request
	 * @return Response
	 * @throws Exception
	 */
    public function getMouvementIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $mouvements = $this->mouvementRepository->findByDates($dateTimeMin, $dateTimeMax);

            foreach($mouvements as $mouvement) {
                $date = $mouvement->getDatetime();
                if ($dateTimeMin >= $date || $dateTimeMax <= $date) {
                    array_splice($mouvements, array_search($mouvement, $mouvements), 1);
                }
            }

            $headers = [];
            $headers = array_merge($headers, ['date', 'colis', 'emplacement', 'type', 'opérateur', 'commentaire', 'pieces jointes', 'urgence']);
            $data = [];
            $data[] = $headers;

            foreach ($mouvements as $mouvement) {
                $mouvementData = [];

                $mouvementData[] = $mouvement->getDatetime() ? $mouvement->getDatetime()->format('d/m/Y H:i') : '';
                $mouvementData[] = $mouvement->getColis();
                $mouvementData[] = $mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '';
                $mouvementData[] = $mouvement->getType() ? $mouvement->getType()->getNom() : '';
                $mouvementData[] = $mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername(): '';
                $mouvementData[] = strip_tags($mouvement->getCommentaire());

                $attachments = $mouvement->getAttachements() ? $mouvement->getAttachements()->toArray() : [];
                $attachmentsNames = [];
                foreach ($attachments as $attachment) {
                	$attachmentsNames[] = $attachment->getOriginalName();
				}
                $mouvementData[] = implode(", ", $attachmentsNames);
                $colis = $this->colisRepository->findOneByCode($mouvement->getColis());
                if ($colis) {
                    $arrivage = $colis->getArrivage();
                    $mouvementData[] = ($arrivage->getIsUrgent() ? 'oui' : 'non');
                } else {
                    $mouvementData[] = 'non';
                }
                $data[] = $mouvementData;
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

	/**
	 * @Route("/voir", name="mvt_traca_show", options={"expose"=true}, methods={"GET","POST"})
	 * @param Request $request
	 * @return Response
	 */
    public function show(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
				return $this->redirectToRoute('access_denied');
			}

			$mouvementTraca = $this->mouvementRepository->find($data);
			$json = $this->renderView('mouvement_traca/modalShowMvtTracaContent.html.twig', [
				'mvt' => $mouvementTraca,
				'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
				'attachments' => $mouvementTraca->getAttachements()
			]);
			return new JsonResponse($json);
		}
		throw new NotFoundHttpException('404');
	}
}
