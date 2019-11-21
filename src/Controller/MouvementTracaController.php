<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\MouvementTraca;

use App\Entity\PieceJointe;
use App\Repository\EmplacementRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;
use App\Service\AttachmentService;
use App\Service\UserService;
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
	 * @var TypeRepository
	 */
    private $typeRepository;

	/**
	 * ArrivageController constructor.
	 * @param TypeRepository $typeRepository
	 * @param EmplacementRepository $emplacementRepository
	 * @param UtilisateurRepository $utilisateurRepository
	 * @param StatutRepository $statutRepository
	 * @param UserService $userService
	 * @param MouvementTracaRepository $mouvementTracaRepository
	 */

    public function __construct(AttachmentService $attachmentService, TypeRepository $typeRepository, EmplacementRepository $emplacementRepository, UtilisateurRepository $utilisateurRepository, StatutRepository $statutRepository, UserService $userService, MouvementTracaRepository $mouvementTracaRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->statutRepository = $statutRepository;
        $this->userService = $userService;
        $this->mouvementRepository = $mouvementTracaRepository;
        $this->typeRepository = $typeRepository;
        $this->attachmentService = $attachmentService;
    }

    /**
     * @Route("/", name="mvt_traca_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('mouvement_traca/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
            'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
            'emplacements' => $this->emplacementRepository->findAll(),
        ]);
    }

	/**
	 * @Route("/creer", name="mvt_traca_new", options={"expose"=true}, methods="GET|POST")
	 * @param Request $request
	 * @return Response
	 * @throws \Exception
	 */
	public function new(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
				return $this->redirectToRoute('access_denied');
			}

			$post = $request->request;
			$em = $this->getDoctrine()->getManager();

			$date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
			$randomChars = substr(md5(microtime()), rand(0,26), 5);
			$formattedDate = $date->format('c') . '_' . $randomChars;
			$type = $this->statutRepository->find($post->get('type'));
			$location = $this->emplacementRepository->find($post->get('emplacement'));

			$mvtTraca = new MouvementTraca();
			$mvtTraca
				->setDate($formattedDate)
				->setType($type)
				->setColis($post->get('colis'))
				->setEmplacement($location)
				->setOperateur($this->getUser())
				->setCommentaire($post->get('commentaire') ?? null);

			$em->persist($mvtTraca);
			$em->flush();

			$this->attachmentService->addAttachements($request, null, null, $mvtTraca);

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
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $mvts = $this->mouvementRepository->findAll();

            $rows = [];
            foreach ($mvts as $mvt) {
				$dateArray = explode('_', $mvt->getDate());
				$date = new DateTime($dateArray[0]);
                $rows[] = [
                    'id' => $mvt->getId(),
                    'date' => $date->format('d/m/Y H:i:s'),
                    'colis' => $mvt->getColis(),
                    'location' => $mvt->getEmplacement() ? $mvt->getEmplacement()->getLabel() : '',
                    'type' => $mvt->getType() ? $mvt->getType()->getNom() : '',
                    'operateur' => $mvt->getOperateur() ? $mvt->getOperateur()->getUsername() : '',
                    'Actions' => $this->renderView('mouvement_traca/datatableMvtTracaRow.html.twig', [
                        'mvt' => $mvt,
                    ])
                ];
            }

            $data['data'] = $rows;

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
			if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
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
	 */
	public function edit(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
				return $this->redirectToRoute('access_denied');
			}

			$post = $request->request;

			$type = $this->statutRepository->find($post->get('type'));
			$location = $this->emplacementRepository->find($post->get('emplacement'));

			$mvt = $this->mouvementRepository->find($post->get('id'));
			$mvt
				->setType($type)
				->setOperateur($this->getUser())
				->setEmplacement($location)
//				->setDate()
				->setColis($post->get('colis'))
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

			$this->attachmentService->addAttachements($request, null, null, $mvt);

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

            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::DELETE)) {
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
     */
    public function getMouvementIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . '00:00:00';
            $dateMax = $data['dateMax'] . '23:59:59';
            $newDateMin = new DateTime($dateMin);
            $newDateMax = new DateTime($dateMax);
            $mouvements = $this->mouvementRepository->findByDates($dateMin, $dateMax);
            foreach($mouvements as $mouvement) {
                $date = substr($mouvement->getDate(),0, -10);
                $newDate = new DateTime($date);
                if ($newDateMin >= $newDate || $newDateMax <= $newDate) {
                    array_splice($mouvements, array_search($mouvement, $mouvements), 1);
                }
            }

            $headers = [];
            $headers = array_merge($headers, ['date', 'colis', 'emplacement', 'type', 'opérateur']);
            $data = [];
            $data[] = $headers;

            foreach ($mouvements as $mouvement) {
                $mouvementData = [];

                $mouvementData[] = substr($mouvement->getDate(), 0,10). ' ' . substr($mouvement->getDate(), 11,8);
                $mouvementData[] = $mouvement->getColis();
                $mouvementData[] = $mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '';
                $mouvementData[] = $mouvement->getType() ? $mouvement->getType()->getNom() : '';
                $mouvementData[] = $mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername(): '';

                $data[] = $mouvementData;
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }
}
