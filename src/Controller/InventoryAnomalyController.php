<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;

use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\DemandeToTreatExistsException;
use App\Repository\InventoryMissionRepository;
use App\Repository\InventoryEntryRepository;
use App\Service\InventoryService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Service\UserService;


/**
 * @Route("/inventaire/anomalie")
 */
class InventoryAnomalyController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

    /**
     * @var InventoryEntryRepository
     */
    private $inventoryEntryRepository;

	/**
	 * @var InventoryService
	 */
    private $inventoryService;

    public function __construct(InventoryService $inventoryService,
                                UserService $userService,
                                InventoryMissionRepository $inventoryMissionRepository,
                                InventoryEntryRepository $inventoryEntryRepository)
    {
        $this->userService = $userService;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
        $this->inventoryService = $inventoryService;
    }

	/**
	 * @Route("/", name="show_anomalies")
	 */
    public function showAnomalies()
	{
		if (!$this->userService->hasRightFunction(Menu::STOCK, Action::INVENTORY_MANAGER)) {
			return $this->redirectToRoute('access_denied');
		}

		return $this->render('inventaire/anomalies.html.twig');
	}

	/**
	 * @Route("/api", name="inv_anomalies_api", options={"expose"=true}, methods="GET|POST")
	 */
	public function apiAnomalies(Request $request)
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::STOCK, Action::INVENTORY_MANAGER)) {
				return $this->redirectToRoute('access_denied');
			}

            $anomaliesOnArt = $this->inventoryEntryRepository->getAnomaliesOnArt();
            $anomaliesOnRef = $this->inventoryEntryRepository->getAnomaliesOnRef();

            $anomalies = array_merge($anomaliesOnArt, $anomaliesOnRef);

			$rows = [];
			foreach ($anomalies as $anomaly) {
				$rows[] = [
                    'reference' => $anomaly['reference'],
                    'libelle' => $anomaly['label'],
                    'quantite' => $anomaly['quantity'],
                    'Actions' => $this->renderView('inventaire/datatableAnomaliesRow.html.twig', [
                        'idEntry' => $anomaly['id'],
                        'reference' => $anomaly['reference'],
                        'isRef' => $anomaly['is_ref'],
                        'quantity' => $anomaly['quantity'],
                        'location' => $anomaly['location'],
                    ])
                ];
			}
			$data['data'] = $rows;
			return new JsonResponse($data);
		}
		throw new NotFoundHttpException("404");
	}

    /**
     * @Route("/traitement", name="anomaly_treat", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     * @throws Exception
     */
	public function treatAnomaly(Request $request)
	{
		if ($request->isXmlHttpRequest()  && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::STOCK, Action::INVENTORY_MANAGER)) {
				return $this->redirectToRoute('access_denied');
			}

            try {
                $quantitiesAreEqual = $this->inventoryService->doTreatAnomaly(
                    $data['id'],
                    $data['reference'],
                    $data['isRef'],
                    (int)$data['newQuantity'],
                    $data['comment'],
                    $this->getUser()
                );
                $responseData = [
                    'success' => true,
                    'quantitiesAreEqual' => $quantitiesAreEqual
                ];
            }
            catch (ArticleNotAvailableException|DemandeToTreatExistsException $exception) {
                $responseData = [
                    'success' => false,
                    'message' => ($exception instanceof DemandeToTreatExistsException)
                        ? 'Impossible : un ordre de livraison est en cours sur cet article'
                        : 'Impossible : l\'article n\'est pas disponible'
                ];
            }

			return new JsonResponse($responseData);
		}
		throw new NotFoundHttpException("404");
	}

}
