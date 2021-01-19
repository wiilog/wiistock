<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\InventoryEntry;
use App\Entity\Menu;

use App\Repository\InventoryEntryRepository;

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
     * @var InventoryEntryRepository
     */
    private $inventoryEntryRepository;

	/**
	 * @var InventoryEntryService
	 */
    private $inventoryEntryService;

    public function __construct(InventoryEntryService $inventoryEntryService,
                                UserService $userService,
                                InventoryEntryRepository $inventoryEntryRepository)
    {
        $this->userService = $userService;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
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
     * @Route("/saisies-infos", name="get_entries_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getEntriesIntels(Request $request,
                                     CSVExportService $CSVExportService,
                                     EntityManagerInterface $entityManager): Response
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

        $entriesRepository = $entityManager->getRepository(InventoryEntry::class);
        $entries = $entriesRepository->findByDates($dateTimeMin, $dateTimeMax);
 // TODO if !empty($dateTimeMin) && !empty($dateTimeMax)

        return $CSVExportService->streamResponse(

            function ($output) use ($entries, $CSVExportService) {
                foreach ($entries as $entry) {
                    $article = $entry->getArticle();
                    $referenceArticle = $entry->getRefArticle();

                    if (!empty($referenceArticle)) {
                        $this->putReferenceArticleLine($output, $CSVExportService, $entry);
                    } else if (!empty($article)) {
                        $this->putArticleLine($output, $CSVExportService, $entry);
                    }
                }
            },
            'Export_Saisies_Inventaire.csv',
            $headers
        );
        // TODO else
        //            throw new NotFoundHttpException('404');
    }

    private function putReferenceArticleLine($handle,
                                             CSVExportService $CSVExportService,
                                             InventoryEntry $entry)
    {
        $referenceArticle = $referenceArticle = $entry->getRefArticle();
        $dataEntry = [
            $referenceArticle->getLibelle() ?? '',
            $referenceArticle->getReference() ?? '',
            $referenceArticle->getBarCode() ?? '',
        ];

        $data = array_merge($dataEntry, $entry->serialize());
        $CSVExportService->putLine($handle, $data);
    }

    private function putArticleLine($handle,
                                    CSVExportService $CSVExportService,
                                    InventoryEntry $entry) {

        $article = $entry->getArticle();
        $articleFournisseur = $article->getArticleFournisseur();
        $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
        $dataEntry = [
            $article->getLabel(),
            $referenceArticle ? $referenceArticle->getReference() : '',
            $article->getBarCode(),
        ];

        $data = array_merge($dataEntry, $entry->serialize());
        $CSVExportService->putLine($handle, $data);
    }
}
