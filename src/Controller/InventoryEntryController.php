<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;

use App\Repository\EmplacementRepository;
use App\Repository\InventoryEntryRepository;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
	 * @var EmplacementRepository
	 */
    private $emplacementRepository;

    public function __construct(EmplacementRepository $emplacementRepository, UtilisateurRepository $userRepository, UserService $userService, InventoryEntryRepository $inventoryEntryRepository)
    {
        $this->userService = $userService;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
        $this->userRepository = $userRepository;
        $this->emplacementRepository = $emplacementRepository;
    }

    /**
     * @Route("/", name="inventory_entry_index", options={"expose"=true}, methods="GET|POST")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('saisie_inventaire/index.html.twig', [
        	'utilisateurs' => $this->userRepository->getIdAndUsername(),
			'emplacements' => $this->emplacementRepository->findAll(),
		]);
    }

    /**
     * @Route("/api", name="entries_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request)
    {
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
				return $this->redirectToRoute('access_denied');
			}

			$entries = $this->inventoryEntryRepository->findAll();

			$rows = [];
			foreach ($entries as $entry) {
				if ($article = $entry->getArticle()) {
					$label = $article->getLabel();
					$ref = $article->getReference();
				} else if ($refArticle = $entry->getRefArticle()) {
						$label = $refArticle->getLibelle();
						$ref = $refArticle->getReference();
				} else {
					$ref = $label = '';
				}
				$rows[] =
					[
						'Ref' => $ref,
						'Label' => $label,
						'Operator' => $entry->getOperator()->getUsername(),
						'Location' => $entry->getLocation()->getLabel(),
						'Date' => $entry->getDate()->format('d/m/Y'),
						'Quantity' => $entry->getQuantity(),
					];
			}
			$data['data'] = $rows;
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
        if ($request->isXmlHttpRequest()) {
            $entries = $this->inventoryEntryRepository->findAll();

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