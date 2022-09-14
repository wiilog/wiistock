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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/inventaire/anomalie")
 */
class InventoryAnomalyController extends AbstractController {

    #[Route("/", name: "show_anomalies")]
    #[HasPermission([Menu::STOCK, Action::INVENTORY_MANAGER])]
    public function showAnomalies(): Response {
        return $this->render('inventaire/anomalies.html.twig');
    }

    #[Route("/api", name: "inv_anomalies_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::INVENTORY_MANAGER], mode: HasPermission::IN_JSON)]
    public function apiAnomalies(Request                $request,
                                 EntityManagerInterface $entityManager,
                                 InventoryEntryService  $inventoryEntryService): Response {
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

        return $this->json([
            'data' => $rows,
            'recordsFiltered' => $anomaliesData['count'],
            'recordsTotal' => $anomaliesData['total'],
        ]);
    }

    #[Route("/traitement", name: "anomaly_treat", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::INVENTORY_MANAGER], mode: HasPermission::IN_JSON)]
    public function treatAnomaly(Request $request, InventoryService $inventoryService): Response {
        if ($data = json_decode($request->getContent(), true)) {
            try {
                $res = $inventoryService->doTreatAnomaly(
                    $data['id'],
                    $data['barcode'],
                    $data['isRef'],
                    (int) $data['newQuantity'],
                    $data['comment'],
                    $this->getUser()
                );

                $responseData = [
                    'success' => true,
                    'msg' => $res['quantitiesAreEqual']
                        ? 'L\'anomalie a bien été traitée.'
                        : 'Un mouvement de stock correctif vient d\'être créé.',
                ];
            } catch (ArticleNotAvailableException|RequestNeedToBeProcessedException $exception) {
                $responseData = [
                    'success' => false,
                    'msg' => ($exception instanceof RequestNeedToBeProcessedException)
                        ? 'Impossible : un ordre de livraison est en cours sur cet article'
                        : 'Impossible : l\'article n\'est pas disponible',
                ];
            }

            return $this->json($responseData);
        }
        throw new BadRequestHttpException();
    }

}
