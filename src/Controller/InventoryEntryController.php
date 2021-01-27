<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\InventoryEntry;
use App\Entity\Menu;

use App\Service\CSVExportService;
use App\Service\InventoryEntryService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use App\Service\UserService;
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
     * @return RedirectResponse|Response
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('saisie_inventaire/index.html.twig');
    }

    /**
     * @Route("/api", name="entries_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     * @throws Exception
     */
    public function api(Request $request)
    {
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
				return $this->redirectToRoute('access_denied');
			}

			$data = $this->inventoryEntryService->getDataForDatatable($request->request);

			return new JsonResponse($data);
		} else {
			throw new BadRequestHttpException();
		}
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
