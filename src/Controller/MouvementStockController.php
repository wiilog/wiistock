<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;

use App\Repository\EmplacementRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;

use App\Service\MouvementStockService;
use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

/**
 * @Route("/mouvement-stock")
 */
class MouvementStockController extends AbstractController
{
    /**
     * @var MouvementStockRepository
     */
    private $mouvementStockRepository;

    /**
     * @var UserService
     */
    private $userService;

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
	 * @var MouvementStockService
	 */
    private $mouvementStockService;

	/**
	 * @var MouvementTracaRepository
	 */
    private $mouvementTracaRepository;

	/**
	 * ArrivageController constructor.
	 * @param MouvementStockService $mouvementStockService
	 * @param EmplacementRepository $emplacementRepository
	 * @param UtilisateurRepository $utilisateurRepository
	 * @param StatutRepository $statutRepository
	 * @param UserService $userService
	 * @param MouvementStockRepository $mouvementStockRepository
	 * @param MouvementTracaRepository $mouvementTracaRepository
	 */

    public function __construct(
    	MouvementStockService $mouvementStockService,
		EmplacementRepository $emplacementRepository,
		UtilisateurRepository $utilisateurRepository,
		StatutRepository $statutRepository,
		UserService $userService,
		MouvementStockRepository $mouvementStockRepository,
		MouvementTracaRepository $mouvementTracaRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->statutRepository = $statutRepository;
        $this->userService = $userService;
        $this->mouvementStockRepository = $mouvementStockRepository;
        $this->mouvementStockService = $mouvementStockService;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
    }

    /**
     * @Route("/", name="mouvement_stock_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_MOUV_STOC)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('mouvement_stock/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_STOCK),
            'emplacements' => $this->emplacementRepository->findAll(),
        ]);
    }

    /**
     * @Route("/api", name="mouvement_stock_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_MOUV_STOC)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->mouvementStockService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="mvt_stock_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mouvement = $this->mouvementStockRepository->find($data['mvt']);

            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($mouvement);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/mouvement-stock-infos", name="get_mouvements_stock_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getMouvementIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $mouvements = $this->mouvementStockRepository->findByDates($dateTimeMin, $dateTimeMax);
            foreach($mouvements as $mouvement) {
                if ($dateTimeMin > $mouvement->getDate() || $dateTimeMax < $mouvement->getDate()) {
                    array_splice($mouvements, array_search($mouvement, $mouvements), 1);
                }
            }

            $headers = [];
            $headers = array_merge($headers, ['date', 'ordre', 'référence article', 'quantité', 'origine', 'destination', 'type', 'opérateur']);
            $data = [];
            $data[] = $headers;

            foreach ($mouvements as $mouvement) {
                $mouvementData = [];
                $reference = $mouvement->getRefArticle() ? $mouvement->getRefArticle()->getReference() : null;
                // TODO code-barre au lieu de référence ??
                $reference = $reference ? $reference : $mouvement->getArticle()->getReference();

                $orderNo = null;
				if ($mouvement->getPreparationOrder()) {
					$orderNo = $mouvement->getPreparationOrder()->getNumero();
				} else if ($mouvement->getLivraisonOrder()) {
					$orderNo = $mouvement->getLivraisonOrder()->getNumero();
				} else if ($mouvement->getCollecteOrder()) {
					$orderNo = $mouvement->getCollecteOrder()->getNumero();
				} else if ($mouvement->getReceptionOrder()) {
					$orderNo = $mouvement->getReceptionOrder()->getNumeroReception();
				}
                $mouvementData[] = $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : '';
                $mouvementData[] = $orderNo ? ' ' . $orderNo : '';
                $mouvementData[] = $reference;
				$mouvementData[] = $mouvement->getQuantity();
				$mouvementData[] = $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : '';
				$mouvementData[] = $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : '';
                $mouvementData[] = $mouvement->getType();
                $mouvementData[] = $mouvement->getUser() ? $mouvement->getUser()->getUsername() : '';

                $data[] = $mouvementData;
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }
}
