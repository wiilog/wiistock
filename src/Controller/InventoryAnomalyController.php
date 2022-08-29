<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Service\InventoryEntryService;
use App\Service\InventoryService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;


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
	 * @var InventoryService
	 */
    private $inventoryService;

    public function __construct(InventoryService $inventoryService,
                                UserService $userService)
    {
        $this->userService = $userService;
        $this->inventoryService = $inventoryService;
    }

	/**
	 * @Route("/", name="show_anomalies")
     * @HasPermission({Menu::STOCK, Action::INVENTORY_MANAGER})
	 */
    public function showAnomalies()
	{
		return $this->render('inventaire/anomalies.html.twig');
	}

    /**
     * @Route("/api", name="inv_anomalies_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::INVENTORY_MANAGER}, mode=HasPermission::IN_JSON)
     */
	public function apiAnomalies(Request $request,
                                 EntityManagerInterface $entityManager,
                                 InventoryEntryService $inventoryEntryService)
	{
        $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
        $anomaliesData = $inventoryEntryRepository->findByParamsAndFilters($request->request, [], true);

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

    /**
     * @Route("/traitement", name="anomaly_treat", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::INVENTORY_MANAGER}, mode=HasPermission::IN_JSON)
     */
	public function treatAnomaly(Request $request)
	{
		if ($data = json_decode($request->getContent(), true)) {
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
		throw new BadRequestHttpException();
	}

}
