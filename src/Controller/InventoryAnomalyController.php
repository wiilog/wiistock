<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\InventoryEntry;
use App\Entity\Menu;

use App\Entity\ReferenceArticle;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Repository\InventoryMissionRepository;
use App\Repository\InventoryEntryRepository;
use App\Service\InventoryEntryService;
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
     * @param Request $request
     * @param InventoryEntryService $inventoryEntryService
     * @return JsonResponse|RedirectResponse
     */
	public function apiAnomalies(Request $request,
                                 InventoryEntryService $inventoryEntryService)
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::STOCK, Action::INVENTORY_MANAGER)) {
				return $this->redirectToRoute('access_denied');
			}

            $anomaliesData = $this->inventoryEntryRepository->findByParamsAndFilters($request->request, [], true);

			$rows = [];
			foreach ($anomaliesData['data'] as $anomalyRes) {
                $anomaly = $anomalyRes instanceof InventoryEntry ? $anomalyRes : $anomalyRes[0];

                $article = $anomaly->getArticle() ?: $anomaly->getRefArticle();
                $quantity = $article instanceof ReferenceArticle ? $article->getQuantiteStock() : $article->getQuantite();

                $row = $inventoryEntryService->dataRowInvEntry($anomaly);

				$row['Actions'] = $this->renderView('inventaire/datatableAnomaliesRow.html.twig', [
                    'idEntry' => $anomaly->getId(),
                    'reference' => $row['Ref'],
                    'isRef' => $article instanceof ReferenceArticle ? 1 : 0,
                    'label' => $row['Label'],
                    'barCode' => $row['barCode'],
                    'quantity' => $quantity,
                    'location' => $row['Location'],
                ]);
                $rows[] = $row;
			}

			return new JsonResponse([
			    'data' => $rows,
                'recordsFiltered' => $anomaliesData['count'],
                'recordsTotal' => $anomaliesData['total'],
            ]);
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
                $res = $this->inventoryService->doTreatAnomaly(
                    $data['id'],
                    $data['barCode'],
                    $data['isRef'],
                    (int)$data['newQuantity'],
                    $data['comment'],
                    $this->getUser()
                );

                $responseData = [
                    'success' => true,
                    'msg' => $res['quantitiesAreEqual']
                        ? 'L\'anomalie a bien été traitée.'
                        : 'Un mouvement de stock correctif vient d\'être créé.'
                ];
            }
            catch (ArticleNotAvailableException|RequestNeedToBeProcessedException $exception) {
                $responseData = [
                    'success' => false,
                    'msg' => ($exception instanceof RequestNeedToBeProcessedException)
                        ? 'Impossible : un ordre de livraison est en cours sur cet article'
                        : 'Impossible : l\'article n\'est pas disponible'
                ];
            }

			return new JsonResponse($responseData);
		}
		throw new NotFoundHttpException("404");
	}

}
