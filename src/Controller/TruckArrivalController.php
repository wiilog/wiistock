<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Chauffeur;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Reserve;
use App\Entity\Setting;
use App\Entity\Transporteur;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Service\AttachmentService;
use App\Service\FilterSupService;
use App\Service\TruckArrivalService;
use App\Service\UniqueNumberService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $defaultLocationId = $settingRepository->getOneParamByLabel(Setting::TRUCK_ARRIVALS_DEFAULT_UNLOADING_LOCATION);
        $defaultLocation = $defaultLocationId ? $locationRepository->find($defaultLocationId) : null;

        return $this->render('truck_arrival/index.html.twig', [
            'controller_name' => 'TruckArrivalController',
            'fields' => $truckArrivalService->getVisibleColumns($this->getUser()),
            'carriersForFilter' => $carrierRepository->findAll(),
            'initial_filters' => json_encode($filterSupService->getFilters($entityManager, FiltreSup::PAGE_TRUCK_ARRIVAL)),
            'fieldsParam' => $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_TRUCK_ARRIVAL),
            'newTruckArrival' => new TruckArrival(),
            'defaultLocation' => $defaultLocation,
        ]);
    }

    #[Route('/voir/{id}', name: 'truck_arrival_show', methods: 'GET')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function show( TruckArrival $truckArrival, EntityManagerInterface $entityManager): Response {

        return $this->render('truck_arrival/show.html.twig', [
            'truckArrival' => $truckArrival,
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

    #[Route('/form/edit/{id}', name: 'truck_arrival_form_edit', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::CREATE_TRUCK_ARRIVALS])]
    public function formEdit(TruckArrival $truckArrival, EntityManagerInterface $entityManager): Response {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        return new JsonResponse([
            'success'=> true,
            'html' => $this->render('truck_arrival/form-truck-arrival.html.twig', [
                'truckArrival' => $truckArrival,
                'fieldsParam' => $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_TRUCK_ARRIVAL)
            ])->getContent()
        ]);
    }

    #[Route('/formulaire', name: 'truck_arrival_form_submit', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::CREATE_TRUCK_ARRIVALS])]
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        UniqueNumberService $uniqueNumberService,
                        AttachmentService $attachmentService): Response {
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $driverRepository = $entityManager->getRepository(Chauffeur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $now = new \DateTime();
        $data = $request->request->all();

        $truckArrival = isset($data['truckArrivalId']) ? $truckArrivalRepository->find($data['truckArrivalId']) : null;
        $driver = isset($data['driver']) ? $driverRepository->find($data['driver']) : null;

        if (!$truckArrival) {
            $carrier = isset($data['carrier']) ? $carrierRepository->find($data['carrier']) : null;
            $location = isset($data['unloadingLocation']) ? $locationRepository->find($data['unloadingLocation']) : null;

            $number = $uniqueNumberService->create($entityManager, null, TruckArrival::class, UniqueNumberService::DATE_COUNTER_FORMAT_TRUCK_ARRIVAL, $now, [$carrier->getCode()]);
            $truckArrival = new TruckArrival();
            $truckArrival
                ->setNumber($number)
                ->setUnloadingLocation($location)
                ->setCarrier($carrier);

        } else {
            $files = $request->files->all();
            $truckArrival->clearAttachments();

            $attachments = $attachmentService->createAttachements($files);
            foreach ($attachments as $attachment) {
                $entityManager->persist($attachment);
                $truckArrival->addAttachment($attachment);
            }
        }

        $truckArrival
            ->setDriver($driver)
            ->setRegistrationNumber($data['registrationNumber'] ?? null)
            ->setCreationDate($now)
            ->setOperator($this->getUser());

        $entityManager->persist($truckArrival);

        if ($data['hasGeneralReserve'] ?? false) {
            $generalReserve = new Reserve();
            $generalReserve
                ->setComment($data['generalReserveComment'] ?? null)
                ->setTruckArrival($truckArrival)
                ->setType(Reserve::TYPE_GENERAL);
            $entityManager->persist($generalReserve);
        }

        if ($data['hasQuantityReserve'] ?? false) {
            $quantityReserve = new Reserve();
            $quantityReserve
                ->setComment($data['quantityReserveComment'] ?? null)
                ->setTruckArrival($truckArrival)
                ->setType(Reserve::TYPE_QUANTITY)
                ->setQuantity($data['reserveQuantity'] ?? null)
                ->setQuantityType($data['reserveType'] ?? null);
            $entityManager->persist($quantityReserve);
        }

        foreach (explode(',', $data['trackingNumbers'] ?? '') as $lineNumber) {
            $arrivalLine = new TruckArrivalLine();
            $arrivalLine
                ->setTruckArrival($truckArrival)
                ->setNumber($lineNumber ?? null);
            $entityManager->persist($arrivalLine);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'truckArrivalId' => $truckArrival->getId(),
        ]);
    }
}
