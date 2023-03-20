<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\TransferOrder;
use App\Entity\Transporteur;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Utilisateur;
use App\Service\FilterSupService;
use App\Service\FormatService;
use App\Service\TruckArrivalService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/arrivage-camion')]
class TruckArrivalController extends AbstractController
{
    #[Route('/', name: 'truck_arrival_index', methods: 'GET')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function index(TruckArrivalService $truckArrivalService,
                          EntityManagerInterface $entityManager,
                          FilterSupService $filterSupService ): Response {
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        return $this->render('truck_arrival/index.html.twig', [
            'controller_name' => 'TruckArrivalController',
            'fields' => $truckArrivalService->getVisibleColumns($this->getUser()),
            'carriersForFilter' => $carrierRepository->findAll(),
            'initial_filters' => json_encode($filterSupService->getFilters($entityManager, FiltreSup::PAGE_TRUCK_ARRIVAL)),
            'fieldsParam' => $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_TRUCK_ARRIVAL)
        ]);
    }

    #[Route('/voir/{id}', name: 'truck_arrival_show', methods: 'GET')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function show( TruckArrival $truckArrival,
                          EntityManagerInterface $entityManager,
                          TruckArrivalService $truckArrivalService): Response
    {

        return $this->render('truck_arrival/show.html.twig', [
            'truckArrival' => $truckArrival,
            'showDetails' => $truckArrivalService->createHeaderDetailsConfig($truckArrival),
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
    public function apiList(TruckArrivalService $truckArrivalService,
                            EntityManagerInterface $entityManager,
                            Request $request): JsonResponse {
        return new JsonResponse(
            $truckArrivalService->getDataForDatatable($entityManager, $request, $this->getUser()),
        );
    }

    #[Route('/save-columns', name: 'save_column_visible_for_truck_arrival', methods: 'POST', condition: 'request.isXmlHttpRequest()')]
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

    #[Route('/supprimer', name: 'truck_arrival_delete', options: ['expose' => true], methods: ['GET', 'POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DELETE_TRUCK_ARRIVALS])]
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response {

        if ($data = $request->request->get('truck_arrival')) {
            $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
            $truckArrival = $truckArrivalRepository->find($data);

            if (count($truckArrival->getTrackingLines()) === 0) {
                $entityManager->remove($truckArrival);
                $entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'redirect' => $this->generateUrl('truck_arrival_index'),
                    'msg' => "L'arrivage camion a bien été supprimé."
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "L'arrivage camion est associé à au moins un arrivage UL, vous ne pouvez pas le supprimer."
                ]);
            }
        }
        throw new BadRequestHttpException();
    }
}
