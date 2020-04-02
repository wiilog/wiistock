<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Menu;

use App\Repository\InventoryEntryRepository;

use App\Repository\UtilisateurRepository;
use App\Service\InventoryEntryService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
	 * @var UtilisateurRepository
	 */
    private $userRepository;

	/**
	 * @var InventoryEntryService
	 */
    private $inventoryEntryService;

    public function __construct(InventoryEntryService $inventoryEntryService, UtilisateurRepository $userRepository, UserService $userService, InventoryEntryRepository $inventoryEntryRepository)
    {
        $this->userService = $userService;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
        $this->userRepository = $userRepository;
        $this->inventoryEntryService = $inventoryEntryService;
    }

    /**
     * @Route("/", name="inventory_entry_index", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     */
    public function index(EntityManagerInterface $entityManager)
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_INVE)) {
            return $this->redirectToRoute('access_denied');
        }

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        return $this->render('saisie_inventaire/index.html.twig', [
			'emplacements' => $emplacementRepository->findAll(),
		]);
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
			throw new NotFoundHttpException("404");
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
            $headers = array_merge($headers, ['Référence ou article', 'Opérateur', 'Emplacement', 'Date de saisie', 'Quantité']);

            $data = [];
            $data[] = $headers;

            foreach ($entries as $entry) {
                $entryData = [];
                $article = $entry->getArticle();
                if ($article == null)
                    $article = $entry->getRefArticle()->getLibelle();
                else
                    $article = $article->getLabel();

                $entryData[] = $article;
                $entryData[] = $entry->getOperator()->getUsername();
                $entryData[] = $entry->getLocation()->getLabel();
                $entryData[] = $entry->getDate()->format('d/m/Y');
                $entryData[] = $entry->getQuantity();

                $data[] = $entryData;
            }

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }
}
