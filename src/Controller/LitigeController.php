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

use App\Service\UserService;
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
     * @param ColisRepository $colisRepository
     * @param LitigeHistoricRepository $litigeHistoricRepository
	 * @param UserService $userService;
	 */
	public function __construct(PieceJointeRepository $pieceJointeRepository, ColisRepository $colisRepository, UserService $userService, ArrivageRepository $arrivageRepository, LitigeRepository $litigeRepository, UtilisateurRepository $utilisateurRepository, StatutRepository $statutRepository, FournisseurRepository $fournisseurRepository, TransporteurRepository $transporteurRepository, ChauffeurRepository $chauffeurRepository, TypeRepository $typeRepository, LitigeHistoricRepository $litigeHistoricRepository)
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
	}

	/**
	 * @Route("/arrivage/liste", name="litige_arrivage_index", options={"expose"=true}, methods="GET|POST")
	 * @return Response
	 */
    public function index()
    {
        return $this->render('litige/index_arrivages.html.twig',[
			'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR),
            'providers' => $this->fournisseurRepository->findAllSorted(),
            'carriers' => $this->transporteurRepository->findAllSorted(),
            'drivers' => $this->chauffeurRepository->findAllSorted(),
            'types' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
            'allColis' => $this->colisRepository->findAll()
		]);
    }

	/**
	 * @Route("/arrivage/api", name="litige_arrivage_api", options={"expose"=true}, methods="GET|POST")
	 */
    public function apiArrivage(Request $request) {
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::LITIGE, Action::LIST)) {
				return $this->redirectToRoute('access_denied');
			}

			$litiges = $this->litigeRepository->getAllWithArrivageData();
			$rows = [];
			foreach ($litiges as $litige) {
				$arrivage = $this->arrivageRepository->find($litige['arrivageId']);
				$acheteursUsernames = [];
				foreach ($arrivage->getAcheteurs() as $acheteur) {
					$acheteursUsernames[] = $acheteur->getUsername();
				}

				$lastHistoric = $this->litigeRepository->getLastHistoricByLitigeId($litige['id']);
				$lastHistoricStr = $lastHistoric ? $lastHistoric['date']->format('d/m/Y H:i') . ' : ' . strip_tags($lastHistoric['comment']) : '';

				$rows[] = [
					'type' => $litige['type'] ?? '',
					'arrivalNumber' => $litige['numeroArrivage'] ?? '',
					'buyers' => implode(', ', $acheteursUsernames),
					'provider' => $litige['provider'] ?? '',
					'carrier' => $litige['carrier'] ?? '',
					'lastHistoric' => $lastHistoricStr,
					'status' => $litige['status'] ?? '',
					'creationDate' => $litige['creationDate'] ? $litige['creationDate']->format('d/m/Y') : '',
					'updateDate' => $litige['updateDate'] ? $litige['updateDate']->format('d/m/Y') : '',
					'actions' => $this->renderView('litige/datatableLitigesArrivageRow.html.twig', [
						'litigeId' => $litige['id']
					])
				];
			}

			$data['data'] = $rows;

			return new JsonResponse($data);
		}
		throw new NotFoundHttpException('404');
	}

	/**
	 * @Route("/arrivage-infos", name="get_litiges_arrivages_for_csv", options={"expose"=true}, methods={"GET","POST"})
	 */
	public function getLitigesArrivageIntels(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';
			$litiges = $this->litigeRepository->findByDates($dateMin, $dateMax);

			$headers = [];
			$headers = array_merge($headers, ['type', 'statut', 'date creation', 'date modification', 'colis', 'ordre arrivage']);
			$data = [];
			$data[] = $headers;

			foreach ($litiges as $litige) {
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

				$litigesData[] = $colis[0]->getArrivage()->getNumeroArrivage();

				$data[] = $litigesData;
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

			$attachement = $this->pieceJointeRepository->findOneByFileNameAndLitigeId($data['pjName'], $litigeId);
			if ($attachement) {
				$em->remove($attachement);
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
	 * @Route("/{litige}", name="histo_litige_api", options={"expose"=true}, methods="GET|POST")
	 * @param Litige $litige
	 * @return Response
	 */
	public function apiHistoricLitige(Request $request, Litige $litige): Response
    {
        if ($request->isXmlHttpRequest()) {
            $rows = [];
                $idLitige = $litige->getId();
                $litigeHisto = $this->litigeHistoricRepository->findByLitigeId($idLitige);
                foreach ($litigeHisto as $histo)
                {
                    $rows[] = [
                        'user' => $histo->getUser()->getUsername(),
                        'date' => $histo->getDate()->format('d/m/Y'),
                        'commentaire' => $histo->getComment(),
                    ];
                }
            $data['data'] = $rows;

            return new JsonResponse($data);

        }
        throw new NotFoundHttpException('404');
    }
}
