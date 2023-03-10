<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\TransferOrder;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Utilisateur;
use App\Service\FormatService;
use App\Service\TruckArrivalService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/arrivage-camion')]
class TruckArrivalController extends AbstractController
{
    #[Route('/', name: 'truck_arrival_index', methods: 'GET')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function index(TruckArrivalService $truckArrivalService): Response {
        return $this->render('truck_arrival/index.html.twig', [
            'controller_name' => 'TruckArrivalController',
            'fields' => $truckArrivalService->getVisibleColumns($this->getUser()),
        ]);
    }

    #[Route('/api-columns', name: 'truck_arrival_api_columns', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function apiColumns(EntityManagerInterface $entityManager, TruckArrivalService $truckArrivalService): Response {
        return new JsonResponse(
            $truckArrivalService->getVisibleColumns($this->getUser())
        );
    }

    #[Route('/api-list', name: 'truck_arrival_api_list', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function apiList(TruckArrivalService $truckArrivalService, Request $request): JsonResponse {
        return new JsonResponse(
            $truckArrivalService->getDataForDatatable($request, $this->getUser()),
        );
    }

    #[Route('/', name: 'save_column_visible_for_truck_arrival', methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    public function saveColumns(VisibleColumnService $visibleColumnService, Request $request, EntityManagerInterface $entityManager): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $user  = $this->getUser();

        $visibleColumnService->setVisibleColumns('truckArrival', $fields, $user);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Vos préférences de colonnes à afficher ont bien été sauvegardées'
        ]);
    }
}
