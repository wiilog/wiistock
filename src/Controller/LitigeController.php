<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Litige;
use App\Entity\Menu;
use App\Entity\LitigeHistoric;

use App\Repository\ArrivageRepository;
use App\Repository\ChauffeurRepository;
use App\Repository\ColisRepository;
use App\Repository\FournisseurRepository;
use App\Repository\LitigeHistoricRepository;
use App\Repository\LitigeRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\StatutRepository;
use App\Repository\TransporteurRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;

use App\Service\LitigeService;
use App\Service\UserService;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/litige")
 */
class LitigeController extends AbstractController
{
	/**
	 * @var UtilisateurRepository
	 */
	private $utilisateurRepository;
	/**
	 * @var StatutRepository
	 */
	private $statutRepository;
	/**
	 * @var FournisseurRepository
	 */
	private $fournisseurRepository;
	/**
	 * @var TransporteurRepository
	 */
	private $transporteurRepository;
	/**
	 * @var ChauffeurRepository
	 */
	private $chauffeurRepository;
	/**
	 * @var TypeRepository
	 */
	private $typeRepository;
	/**
	 * @var LitigeRepository
	 */
	private $litigeRepository;
	/**
	 * @var ArrivageRepository
	 */
	private $arrivageRepository;
    /**
     * @var ColisRepository
     */
    private $colisRepository;
	/**
	 * @var UserService
	 */
	private $userService;

    /**
     * @var LitigeHistoricRepository
     */
    private $litigeHistoricRepository;

	/**
	 * @var PieceJointeRepository
	 */
	private $pieceJointeRepository;

	/**
	 * @var LitigeService
	 */
	private $litigeService;

	/**
	 * @param LitigeService $litigeService
	 * @param PieceJointeRepository $pieceJointeRepository
	 * @param ColisRepository $colisRepository
	 * @param UserService $userService ;
	 * @param ArrivageRepository $arrivageRepository
	 * @param LitigeRepository $litigeRepository
	 * @param UtilisateurRepository $utilisateurRepository
	 * @param StatutRepository $statutRepository
	 * @param FournisseurRepository $fournisseurRepository
	 * @param TransporteurRepository $transporteurRepository
	 * @param ChauffeurRepository $chauffeurRepository
	 * @param TypeRepository $typeRepository
	 * @param LitigeHistoricRepository $litigeHistoricRepository
	 */
	public function __construct(LitigeService $litigeService, PieceJointeRepository $pieceJointeRepository, ColisRepository $colisRepository, UserService $userService, ArrivageRepository $arrivageRepository, LitigeRepository $litigeRepository, UtilisateurRepository $utilisateurRepository, StatutRepository $statutRepository, FournisseurRepository $fournisseurRepository, TransporteurRepository $transporteurRepository, ChauffeurRepository $chauffeurRepository, TypeRepository $typeRepository, LitigeHistoricRepository $litigeHistoricRepository)
	{
		$this->utilisateurRepository = $utilisateurRepository;
		$this->statutRepository = $statutRepository;
		$this->fournisseurRepository = $fournisseurRepository;
		$this->transporteurRepository = $transporteurRepository;
		$this->chauffeurRepository = $chauffeurRepository;
		$this->typeRepository = $typeRepository;
		$this->litigeRepository = $litigeRepository;
		$this->arrivageRepository = $arrivageRepository;
		$this->userService = $userService;
		$this->colisRepository = $colisRepository;
		$this->litigeHistoricRepository = $litigeHistoricRepository;
		$this->pieceJointeRepository = $pieceJointeRepository;
		$this->litigeService = $litigeService;
	}

    /**
     * @Route("/liste", name="litige_index", options={"expose"=true}, methods="GET|POST")
     * @param LitigeService $litigeService
     * @return Response
     */
    public function index(LitigeService $litigeService)
    {
        if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DISPLAY_LITI)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('litige/index.html.twig',[
            'statuts' => $this->statutRepository->findByCategorieNames([CategorieStatut::LITIGE_ARR, CategorieStatut::LITIGE_RECEPT]),
            'carriers' => $this->transporteurRepository->findAllSorted(),
            'types' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
			'litigeOrigins' => $litigeService->getLitigeOrigin()
		]);
    }

	/**
	 * @Route("/api", name="litige_api", options={"expose"=true}, methods="GET|POST")
	 */
    public function api(Request $request) {
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DISPLAY_LITI)) {
				return $this->redirectToRoute('access_denied');
			}

			$data = $this->litigeService->getDataForDatatable($request->request);

			return new JsonResponse($data);
		}
		throw new NotFoundHttpException('404');
	}

	/**
	 * @Route("/arrivage-infos", name="get_litiges_for_csv", options={"expose"=true}, methods={"GET","POST"})
	 * @throws Exception
	 */
	public function getLitigesIntels(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

			$litiges = $this->litigeRepository->findByDates($dateTimeMin, $dateTimeMax);

			$headers = [];
			$headers = array_merge($headers, ['type', 'statut', 'date creation', 'date modification', 'colis', 'ordre arrivage', 'date commentaire', 'utilisateur', 'commentaire']);
			$data = [];
			$data[] = $headers;

			foreach ($litiges as $litige) {
				$historics = $this->litigeHistoricRepository->findByLitige($litige);

				if (empty($historics)) {
					$litigesData = [];

					$litigesData[] = $litige->getType() ? $litige->getType()->getLabel() : '';
					$litigesData[] = $litige->getStatus() ? $litige->getStatus()->getNom() : '';
					$litigesData[] = $litige->getCreationDate() ? $litige->getCreationDate()->format('d/m/Y') : '';
					$litigesData[] = $litige->getUpdateDate() ? $litige->getUpdateDate()->format('d/m/Y') : '';

					$colis = $litige->getColis()->toArray();
					$arrColis = [];
					foreach ($colis as $coli) {
						$arrColis[] = $coli->getCode();
					}
					$strColis = implode(', ', $arrColis);
					$litigesData[] = $strColis;

					$litigesData[] = ($colis && $colis[0]->getArrivage()) ? $colis[0]->getArrivage()->getNumeroArrivage() : '';

					$litigesData[] = '';
					$litigesData[] = '';
					$litigesData[] = '';

					$data[] = $litigesData;
				} else {
					foreach ($historics as $historic) {
						$litigesData = [];

						$litigesData[] = $litige->getType() ? $litige->getType()->getLabel() : '';
						$litigesData[] = $litige->getStatus() ? $litige->getStatus()->getNom() : '';
						$litigesData[] = $litige->getCreationDate() ? $litige->getCreationDate()->format('d/m/Y') : '';
						$litigesData[] = $litige->getUpdateDate() ? $litige->getUpdateDate()->format('d/m/Y') : '';

						$colis = $litige->getColis()->toArray();
						$arrColis = [];
						foreach ($colis as $coli) {
							$arrColis[] = $coli->getCode();
						}
						$strColis = implode(', ', $arrColis);
						$litigesData[] = $strColis;

						$litigesData[] = ($colis && $colis[0]->getArrivage()) ? $colis[0]->getArrivage()->getNumeroArrivage() : '';

						$litigesData[] = $historic->getDate() ? $historic->getDate()->format('d/m/Y H:i') : '';
						$litigesData[] = $historic->getUser() ? $historic->getUser()->getUsername() : '';
						$litigesData[] = '"' . $historic->getComment() . '"';

						$data[] = $litigesData;
					}
				}

			}
			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
	}

	/**
	 * @Route("/supprime-pj-litige", name="litige_delete_attachement", options={"expose"=true}, methods="GET|POST")
	 */
	public function deleteAttachementLitige(Request $request)
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$em = $this->getDoctrine()->getManager();

			$litigeId = (int)$data['litigeId'];

			$attachements = $this->pieceJointeRepository->findOneByFileNameAndLitigeId($data['pjName'], $litigeId);
			if (!empty($attachements)) {
			    foreach ($attachements as $attachement) {
                    $em->remove($attachement);
                }
				$em->flush();
				$response = true;
			} else {
				$response = false;
			}

			return new JsonResponse($response);
		} else {
			throw new NotFoundHttpException('404');
		}
	}

	/**
	 * @Route("/histo/{litige}", name="histo_litige_api", options={"expose"=true}, methods="GET|POST")
	 * @param Litige $litige
	 * @return Response
	 */
	public function apiHistoricLitige(Request $request, Litige $litige): Response
    {
        if ($request->isXmlHttpRequest()) {
            $rows = [];
                $idLitige = $litige->getId();
                $litigeHisto = $this->litigeHistoricRepository->findByLitige($idLitige);
                foreach ($litigeHisto as $histo)
                {
                    $rows[] = [
                        'user' => $histo->getUser() ? $histo->getUser()->getUsername() : '',
                        'date' => $histo->getDate() ? $histo->getDate()->format('d/m/Y H:i') : '',
                        'commentaire' => nl2br($histo->getComment()),
                    ];
                }
            $data['data'] = $rows;

            return new JsonResponse($data);

        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/add_Comment/{litige}", name="add_comment", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param Litige $litige
     * @return Response
     * @throws Exception
     */
    public function addComment(Request $request, Litige $litige): Response
    {
        if ($request->isXmlHttpRequest() && $data = (json_decode($request->getContent(), true) ?? [])) {
            $em = $this->getDoctrine()->getManager();
            $litigeHisto = new LitigeHistoric();
            $litigeHisto
                ->setLitige($litige)
                ->setUser($this->getUser())
                ->setDate(new DateTime('now'))
                ->setComment($data);
            $em->persist($litigeHisto);
            $em->flush();

            return new JsonResponse(true);
        }
        return new JsonResponse(false);
    }

	/**
	 * @Route("/modifier", name="litige_edit",  options={"expose"=true}, methods="GET|POST")
	 */
	public function editLitige(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::QUALI, Action::EDIT)) {
				return $this->redirectToRoute('access_denied');
			}

			$post = $request->request;
			$isArrivage = $post->get('isArrivage');

			$controller = $isArrivage ? 'App\Controller\ArrivageController' : 'App\Controller\ReceptionController';

			return $this->forward($controller . '::editLitige', [
				'request' => $request
			]);

		}
		throw new NotFoundHttpException('404');
	}
}
