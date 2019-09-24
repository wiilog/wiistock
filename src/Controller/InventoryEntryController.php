<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\InventoryMission;
use App\Entity\Menu;
use App\Entity\InventoryEntry;

use App\Repository\InventoryEntryRepository;

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

    public function __construct(UserService $userService, InventoryEntryRepository $inventoryEntryRepository)
    {
        $this->userService = $userService;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
    }

    /**
     * @Route("/voir/{id}", name="inventory_entry_index", options={"expose"=true}, methods="GET|POST")
     */
    public function entry_index(InventoryMission $mission)
    {
        if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('saisie_inventaire/index.html.twig', [
            'missionId' => $mission->getId(),
        ]);
    }

    /**
     * @Route("/donnees/api/{id}", name="entries_api", options={"expose"=true}, methods="GET|POST")
     */
    public function entryApi(InventoryMission $mission)
    {
        if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $entries = $this->inventoryEntryRepository->getByMission($mission);

        $rows = [];
        foreach ($entries as $entry) {
            $article = $entry->getArticle();
            if ($article == null)
                $article = $entry->getRefArticle()->getLibelle();
            else
                $article = $article->getLabel();
            $rows[] =
                [
                    'Article' => $article,
                    'Operator' => $entry->getOperator()->getUsername(),
                    'Location' => $entry->getLocation()->getLabel(),
                    'Date' => $entry->getDate()->format('d/m/Y'),
                    'Quantity' => $entry->getQuantity()
                ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    /**
     * @Route("/saisies-infos", name="get_entries_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getEntriesIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $entries = $this->inventoryEntryRepository->getByMission($data['missionId']);

            $headers = [];
            $headers = array_merge($headers, ['Référence ou article', 'Operateur', 'Emplacement', 'Date de saisie', 'Quantité']);

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