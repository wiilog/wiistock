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
use App\Service\FormatService;
use App\Service\ReserveService;
use App\Service\TruckArrivalLineService;
use App\Service\TruckArrivalService;
use App\Service\UniqueNumberService;
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
    public function show(TruckArrival $truckArrival,
                         EntityManagerInterface $entityManager,
                         TruckArrivalService $truckArrivalService): Response {
        $lineAssociated = $truckArrival->getTrackingLines()
                ->filter(fn(TruckArrivalLine $line) => $line->getArrivals()->count())
                ->count()
            . '/'
            . $truckArrival->getTrackingLines()->count();

        return $this->render('truck_arrival/show.html.twig', [
            'truckArrival' => $truckArrival,
            'lineAssociated' => $lineAssociated,
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

    #[Route('/truck-arrival-lines-api', name: 'truck_arrival_lines_api', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::SETTINGS_DISPLAY_TRUCK_ARRIVALS])]
    public function truckArrivalLinesApi(TruckArrivalLineService $truckArrivalLineService,
                                         EntityManagerInterface $entityManager,
                                         Request $request): JsonResponse {
        return new JsonResponse(
            $truckArrivalLineService->getDataForDatatable($entityManager, $request),
        );
    }

    #[Route('/truck-arrival-lines-quality-reserves-api', name: 'truck_arrival_lines_quality_reserves_api', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function truckArrivalLinesQualityReservesApi(ReserveService $reserveService,
                                                        EntityManagerInterface $entityManager,
                                                        Request $request): JsonResponse {
        return new JsonResponse(
            $reserveService->getDataForDatatable($entityManager, $request),
        );
    }

    #[Route('/supprimer-ligne', name: 'truck_arrival_lines_delete', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DELETE_CARRIER_TRACKING_NUMBER], mode: HasPermission::IN_JSON)]
    public function deleteLine(Request $request,
                           EntityManagerInterface $entityManager): Response {
        $truckArrivalLineRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $truckArrivalLine = $truckArrivalLineRepository->find($request->query->get('truckArrivalLineId'));

        if (!$truckArrivalLine->getArrivals()->isEmpty()) {
            return $this->json([
                "success" => false,
                "msg" => "Ce numéro de tracking transporteur est lié à un ou plusieurs arrivages UL et ne peut pas être supprimé"
            ]);
        }

        if ($truckArrivalLine->getReserve()) {
            return $this->json([
                "success" => false,
                "msg" => "Ce numéro de tracking transporteur est lié à une réserve qualité et ne peut pas être supprimé"
            ]);
        }

        $truckArrivalLine->getTruckArrival()->removeTrackingLine($truckArrivalLine);
        $entityManager->remove($truckArrivalLine);
        $entityManager->flush();
        return new JsonResponse([
            'success' => true,
            'msg' => 'Le numéro de tracking transporteur ' . $truckArrivalLine->getNumber() . ' a bien été supprimé.'
        ]);
    }

    #[Route('/supprimer-ligne-reserve', name: 'truck_arrival_line_reserve_delete', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS], mode: HasPermission::IN_JSON)]
    public function deleteReserve(Request $request,
                           EntityManagerInterface $entityManager): Response {
        $reserveRepository = $entityManager->getRepository(Reserve::class);
        $reserve = $reserveRepository->find($request->query->get('reserveId'));


        $entityManager->remove($reserve);
        $entityManager->flush();
        return new JsonResponse([
            'success' => true,
            'msg' => 'La réserve qualité du numéro de tracking transporteur ' . $reserve->getLine()->getNumber() . ' a bien été supprimé.'
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
        $lineNumberRepository = $entityManager->getRepository(TruckArrivalLine::class);
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
            if ($lineNumber && empty($lineNumberRepository->findOneBy(['number' => $lineNumber]))) {
                $arrivalLine = new TruckArrivalLine();
                $arrivalLine
                    ->setTruckArrival($truckArrival)
                    ->setNumber($lineNumber);
                $entityManager->persist($arrivalLine);
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'truckArrivalId' => $truckArrival->getId(),
        ]);
    }

    #[Route('/supprimer', name: 'truck_arrival_delete', options: ['expose' => true], methods: ['GET', 'POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DELETE_TRUCK_ARRIVALS])]
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response {

        if ($data = $request->request->get('truck_arrival')) {
            $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
            $truckArrival = $truckArrivalRepository->find($data);

            $hasLinesAssociatedToArrival =
                Stream::from($truckArrival->getTrackingLines())
                    ->filter(fn(TruckArrivalLine $line) => !$line->getArrivals()->isEmpty())
                    ->count();

            if ($hasLinesAssociatedToArrival === 0) {
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
                    'msg' => "Cette arrivage camion contient au moins un numéro de tracking transporteur lié à au moins un arrivage UL, vous ne pouvez pas le supprimer."
                ]);
            }
        }
        throw new BadRequestHttpException();
    }
}
