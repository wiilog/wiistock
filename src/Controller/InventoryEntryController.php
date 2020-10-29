<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;

use App\Repository\InventoryEntryRepository;

use App\Service\InventoryEntryService;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     */
    public function getEntriesIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $entries = $this->inventoryEntryRepository->findByDates($dateTimeMin, $dateTimeMax);

            $headers = [];
            $headers = array_merge($headers, [
                'Libellé',
                'Référence',
                'Code barre',
                'Opérateur',
                'Emplacement',
                'Date de saisie',
                'Quantité'
            ]);

            $data = [];
            $data[] = $headers;

            foreach ($entries as $entry) {
                $article = $entry->getArticle();
                $referenceArticle = $entry->getRefArticle();

                if (!empty($referenceArticle)) {
                    $articleLabel = $referenceArticle->getLibelle();
                    $reference = $referenceArticle->getReference();
                    $barCode = $referenceArticle->getBarCode();
                }
                else if (!empty($article)) {
                    $articleLabel = $article->getLabel();
                    $articleFournisseur = $article->getArticleFournisseur();
                    $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
                    $reference = $referenceArticle ? $referenceArticle->getReference() : '';
                    $barCode = $article->getBarCode();
                }

                $entryData = [];
                $entryData[] = $articleLabel ?? '';
                $entryData[] = $reference ?? '';
                $entryData[] = $barCode ?? '';
                $entryData[] = $entry->getOperator() ? $entry->getOperator()->getUsername() : '';
                $entryData[] = $entry->getLocation() ? $entry->getLocation()->getLabel() : '';
                $entryData[] = $entry->getDate() ? $entry->getDate()->format('d/m/Y') : '';
                $entryData[] = $entry->getQuantity();

                $data[] = $entryData;
            }

            return new JsonResponse($data);
        } else {
            throw new BadRequestHttpException();
        }
    }
}
