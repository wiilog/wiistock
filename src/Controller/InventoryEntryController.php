<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Menu;
use App\Service\CSVExportService;
use App\Service\InventoryEntryService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;


/**
 * @Route("/inventaire/saisie")
 */
class InventoryEntryController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

	/**
	 * @var InventoryEntryService
	 */
    private $inventoryEntryService;

    public function __construct(InventoryEntryService $inventoryEntryService,
                                UserService $userService)
    {
        $this->userService = $userService;
        $this->inventoryEntryService = $inventoryEntryService;
    }

    /**
     * @Route("/", name="inventory_entry_index", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE})
     */
    public function index()
    {
        return $this->render('saisie_inventaire/index.html.twig');
    }

    /**
     * @Route("/api", name="entries_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request)
    {
        $data = $this->inventoryEntryService->getDataForDatatable($request->request);

        return new JsonResponse($data);
	}

    /**
     * @Route("/csv", name="get_inventory_entries_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param InventoryEntryService $inventoryEntryService
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     * @return Response
     */
    public function getInventoryEntriesCSV(Request $request,
                                           InventoryEntryService $inventoryEntryService,
                                           EntityManagerInterface $entityManager,
                                           CSVExportService $CSVExportService): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }
        $headers = [
            'Libellé',
            'Référence',
            'Code barre',
            'Opérateur',
            'Emplacement',
            'Date de saisie',
            'Quantité'
        ];

        if (!empty($dateTimeMin) && !empty($dateTimeMax)) {
            $entriesRepository = $entityManager->getRepository(InventoryEntry::class);
            $entries = $entriesRepository->findByDates($dateTimeMin, $dateTimeMax);

            return $CSVExportService->streamResponse(
                function ($output) use ($entries, $CSVExportService, $inventoryEntryService) {
                    foreach ($entries as $entry) {
                        $inventoryEntryService->putEntryLine($entry, $output);
                    }
                },
                'Export_Saisies_Inventaire.csv',
                $headers
            );
        } else {
            throw new NotFoundHttpException('404');
        }
    }

}
