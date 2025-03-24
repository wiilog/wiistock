<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Chauffeur;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Reserve;
use App\Entity\ReserveType;
use App\Entity\Setting;
use App\Entity\Transporteur;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\FieldModesService;
use App\Service\FilterSupService;
use App\Service\PDFGeneratorService;
use App\Service\ReserveService;
use App\Service\SettingsService;
use App\Service\TruckArrivalLineService;
use App\Service\TruckArrivalService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;
use App\Entity\Dashboard;

#[Route('/arrivage-camion', name: "truck_arrival_")]
class TruckArrivalController extends AbstractController
{
    #[Route('/', name: 'index', methods: self::GET)]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function index(TruckArrivalService       $truckArrivalService,
                          Request                   $request,
                          EntityManagerInterface    $entityManager,
                          SettingsService           $settingsService,
                          FilterSupService          $filterSupService ): Response {
        $data = $request->query;
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $dashboardComponentRepository = $entityManager->getRepository(Dashboard\Component::class);

        $defaultLocationId = $settingsService->getValue($entityManager, Setting::TRUCK_ARRIVALS_DEFAULT_UNLOADING_LOCATION);
        $defaultLocation = $defaultLocationId ? $locationRepository->find($defaultLocationId) : null;

        $dashboardComponentId = $data->get("dashboardComponentId");

        /** @var Dashboard\Component $dashboardComponent */
        $dashboardComponent = $dashboardComponentId
            ? $dashboardComponentRepository->find($dashboardComponentId)
            : null;

        if(isset($dashboardComponent)) {
            $fromDashboard = true;
            $config = $dashboardComponent->getConfig();
            $locationsFilter = !empty($config["locations"])
                ? $locationRepository->findBy(["id" => $config["locations"]])
                : [];
            $countNoLinkedTruckArrival = $config["countNoLinkedTruckArrival"] ?? false;
            $carrierTrackingNumberNotAssigned = true;
        }

        return $this->render('truck_arrival/index.html.twig', [
            'controller_name' => 'TruckArrivalController',
            'fields' => $truckArrivalService->getVisibleColumns($this->getUser()),
            'carriersForFilter' => $carrierRepository->findAll(),
            'initial_filters' => json_encode($filterSupService->getFilters($entityManager, FiltreSup::PAGE_TRUCK_ARRIVAL)),
            'fieldsParam' => $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL),
            'newTruckArrival' => new TruckArrival(),
            'defaultLocation' => $defaultLocation,
            'fromDashboard' => $fromDashboard ?? false,
            'locationsFilter' => $locationsFilter ?? [],
            'carrierTrackingNumberNotAssigned' => $carrierTrackingNumberNotAssigned ?? false,
            'countNoLinkedTruckArrival' => $countNoLinkedTruckArrival ?? false,
        ]);
    }

    #[Route('/voir/{id}', name: 'show', methods: self::GET)]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function show(TruckArrival           $truckArrival,
                         EntityManagerInterface $entityManager,
                         TruckArrivalService    $truckArrivalService): Response {
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        $lineAssociated = $truckArrival->getTrackingLines()
                ->filter(fn(TruckArrivalLine $line) => $line->getArrivals()->count())
                ->count()
            . '/'
            . $truckArrival->getTrackingLines()
                ->filter(fn(TruckArrivalLine $line) => !$line?->getReserve()?->getReserveType()->isDisableTrackingNumber())
                ->count();
        $carrier = $truckArrival->getCarrier();
        $minTrackingNumber = $carrier->getMinTrackingNumberLength();
        $maxTrackingNumber = $carrier->getMaxTrackingNumberLength();

        return $this->render('truck_arrival/show.html.twig', [
            'truckArrival' => $truckArrival,
            'lineAssociated' => $lineAssociated,
            'minTrackingNumber' => $minTrackingNumber,
            'maxTrackingNumber' => $maxTrackingNumber,
            'showDetails' => $truckArrivalService->createHeaderDetailsConfig($truckArrival),
            'fieldsParam' => $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL),
        ]);
    }

    #[Route('/api-columns', name: 'api_columns', options: ['expose' => true], methods: self::GET, condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function apiColumns(TruckArrivalService $truckArrivalService): Response {
        return new JsonResponse(
            $truckArrivalService->getVisibleColumns($this->getUser())
        );
    }

    #[Route('/api-list', name: 'api_list', options: ['expose' => true], methods: self::POST, condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function apiList(TruckArrivalService     $truckArrivalService,
                            EntityManagerInterface  $entityManager,
                            Request $request): JsonResponse {
        return new JsonResponse(
            $truckArrivalService->getDataForDatatable($entityManager, $request, $this->getUser()),
        );
    }

    #[Route('/save-columns', name: 'save_column_visible', methods: self::POST, condition: 'request.isXmlHttpRequest()')]
    public function saveColumns(FieldModesService      $fieldModesService,
                                Request                $request,
                                EntityManagerInterface $entityManager): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $user = $this->getUser();

        $fieldModesService->setFieldModesByPage('truckArrival', $fields, $user);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Vos préférences de colonnes à afficher ont bien été sauvegardées'
        ]);
    }

    #[Route('/truck-arrival-lines-api', name: 'lines_api', options: ['expose' => true], methods: self::GET, condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::SETTINGS_DISPLAY_TRUCK_ARRIVALS])]
    public function truckArrivalLinesApi(TruckArrivalLineService    $truckArrivalLineService,
                                         EntityManagerInterface     $entityManager,
                                         Request                    $request): JsonResponse {
        return new JsonResponse(
            $truckArrivalLineService->getDataForDatatable($entityManager, $request),
        );
    }

    #[Route('/truck-arrival-lines-quality-reserves-api', name: 'lines_quality_reserves_api', options: ['expose' => true], methods: self::GET, condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function truckArrivalLinesQualityReservesApi(ReserveService          $reserveService,
                                                        EntityManagerInterface  $entityManager,
                                                        Request                 $request): JsonResponse {
        return new JsonResponse(
            $reserveService->getDataForDatatable($entityManager, $request),
        );
    }

    #[Route('/supprimer-ligne', name: 'lines_delete', options: ['expose' => true], methods: [self::GET, self::POST], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DELETE_CARRIER_TRACKING_NUMBER], mode: HasPermission::IN_JSON)]
    public function deleteLine(Request                $request,
                               EntityManagerInterface $entityManager): Response
    {
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

    #[Route('/supprimer-ligne-reserve', name: 'line_reserve_delete', options: ['expose' => true], methods: [self::GET, self::POST], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS], mode: HasPermission::IN_JSON)]
    public function deleteReserve(  Request                 $request,
                                    EntityManagerInterface  $entityManager): Response {
        $reserveRepository = $entityManager->getRepository(Reserve::class);
        $reserve = $reserveRepository->find($request->query->get('reserveId'));


        $entityManager->remove($reserve);
        $entityManager->flush();
        return new JsonResponse([
            'success' => true,
            'msg' => 'La réserve qualité du numéro de tracking transporteur ' . $reserve->getLine()->getNumber() . ' a bien été supprimé.'
        ]);
    }

    #[Route('/form/edit/{id}', name: 'form_edit', options: ['expose' => true], methods: self::GET, condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::CREATE_TRUCK_ARRIVALS])]
    public function formEdit(TruckArrival           $truckArrival,
                             EntityManagerInterface $entityManager): Response {
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        return new JsonResponse([
            'success'=> true,
            'html' => $this->render('truck_arrival/form-truck-arrival.html.twig', [
                'truckArrival' => $truckArrival,
                'fieldsParam' => $fieldsParamRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL)
            ])->getContent()
        ]);
    }

    #[Route('/formulaire', name: 'form_submit', options: ['expose' => true], methods: self::POST, condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::CREATE_TRUCK_ARRIVALS])]
    public function submit(Request                $request,
                           EntityManagerInterface $entityManager,
                           UniqueNumberService    $uniqueNumberService,
                           AttachmentService      $attachmentService,
                           SettingsService        $settingsService,
                           TruckArrivalLineService $truckArrivalLineService): Response {
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $driverRepository = $entityManager->getRepository(Chauffeur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);

        $autoPrintTruckArrivalLabel = $settingsService->getValue($entityManager, Setting::AUTO_PRINT_TRUCK_ARRIVAL_LABEL);

        $now = new DateTime();
        $data = $request->request;
        $truckArrival = $data->get('truckArrivalId') ? $truckArrivalRepository->find($data->get('truckArrivalId')) : null;

        if (!$truckArrival) {
            $truckArrival = new TruckArrival();
            if ($data->has('carrier')) {
                $carrierId = $data->getInt('carrier');
                $carrier = $carrierId ? $carrierRepository->find($carrierId) : null;
                $truckArrival->setCarrier($carrier);
            }

            if ($data->has('unloadingLocation')) {
                $locationId = $data->getInt('unloadingLocation');
                $location = $locationId ? $locationRepository->find($locationId) : null;
                $truckArrival->setUnloadingLocation($location);
            } else {
                $defaultLocationId = $settingsService->getValue($entityManager, Setting::TRUCK_ARRIVALS_DEFAULT_UNLOADING_LOCATION);
                $defaultLocation = $defaultLocationId ? $locationRepository->find($defaultLocationId) : null;
                $truckArrival->setUnloadingLocation($defaultLocation);
            }

            $number = $uniqueNumberService->create($entityManager, null, TruckArrival::class, UniqueNumberService::DATE_COUNTER_FORMAT_TRUCK_ARRIVAL, $now, [$carrier->getCode()]);

            $truckArrival->setNumber($number);
        }
        else {
            $attachmentService->removeAttachments($entityManager, $truckArrival, $data->all('files') ?: []);
            $attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $truckArrival]);
        }

        $truckArrival
            ->setCreationDate($now)
            ->setOperator($this->getUser());

        if($data->has('driver')){
            $driverId = $data->get('driver');
            $driver = $driverId ? $driverRepository->find($driverId) : null;
            $truckArrival->setDriver($driver ?? null);
        }

        if($data->has('registrationNumber')){
            $truckArrival->setRegistrationNumber($data->get('registrationNumber') );
        }

        $entityManager->persist($truckArrival);

        if ($data->has('hasGeneralReserve') && $data->getBoolean('hasGeneralReserve')) {
            $generalReserve = new Reserve();
            $generalReserve
                ->setComment($data->get('generalReserveComment'))
                ->setTruckArrival($truckArrival)
                ->setKind(Reserve::KIND_GENERAL);
            $entityManager->persist($generalReserve);
        }

        if ($data->has('hasQuantityReserve') && $data->getBoolean('hasQuantityReserve')) {
            $quantityReserve = new Reserve();
            $quantityReserve
                ->setComment($data->get('quantityReserveComment'))
                ->setTruckArrival($truckArrival)
                ->setKind(Reserve::KIND_QUANTITY)
                ->setQuantity($data->get('reserveQuantity'))
                ->setQuantityType($data->get('reserveType'));
            $entityManager->persist($quantityReserve);
        }

        $trackingNumbers = $data->get("trackingNumbers");
        if ($trackingNumbers && $trackingNumbers !== "") {
            $trackingNumbers = explode(",", $trackingNumbers);
            $truckArrivalLineService->checkForInvalidNumber($trackingNumbers, $entityManager);

            foreach ($trackingNumbers as $lineNumber) {
                if ($lineNumber) {
                    $arrivalLine = new TruckArrivalLine();
                    $arrivalLine
                        ->setTruckArrival($truckArrival)
                        ->setNumber($lineNumber);
                    $truckArrival->addTrackingLine($arrivalLine);
                    $entityManager->persist($arrivalLine);
                }
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'truckArrivalId' => $autoPrintTruckArrivalLabel ? $truckArrival->getId() : null,
            'redirect' => $data->has('goToArrivalButton') && boolval($data->get('goToArrivalButton'))
                            ? $this->generateUrl('arrivage_index', [
                                'truckArrivalId' => $truckArrival->getId()
                            ])
                            : '',
        ]);
    }

    #[Route('/{truckArrival}/delete', name: 'delete', options: ['expose' => true], methods: [self::GET, self::POST], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DELETE_TRUCK_ARRIVALS])]
    public function delete(TruckArrival             $truckArrival,
                           EntityManagerInterface   $entityManager): Response {

        $hasLinesAssociatedToArrival = $truckArrival->getTrackingLines()->count() > 0
            ? Stream::from($truckArrival->getTrackingLines())
                 ->filter(fn(TruckArrivalLine $line) => !$line->getArrivals()->isEmpty())
                 ->count()
            : 0;

        if ($hasLinesAssociatedToArrival !== 0) {
            throw new FormException("Cette arrivage camion contient au moins un numéro de tracking transporteur lié à au moins un arrivage UL, vous ne pouvez pas le supprimer.");
        }

        $arrivalRepository = $entityManager->getRepository(Arrivage::class);
        $countArrivals = $arrivalRepository->count(['truckArrival' => $truckArrival->getId()]);
        if ($countArrivals) {
            throw new FormException("Cet arrivage camion est lié à $countArrivals arrivage(s) UL et ne peut pas être supprimé.");
        }

        $entityManager->remove($truckArrival);
        $entityManager->flush();
        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('truck_arrival_index'),
            'msg' => "L'arrivage camion a bien été supprimé."
        ]);
    }

    #[Route('/add-tracking-number', name: 'add_tracking_number', options: ['expose' => true], methods: self::POST, condition: 'request.isXmlHttpRequest()')]
    public function addTrackingNumber(Request                   $request,
                                      EntityManagerInterface    $entityManager,
                                      TruckArrivalLineService $truckArrivalLineService): Response
    {
        $data = $request->request->all();
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $truckArrival = $truckArrivalRepository->find($request->request->get('truckArrival'));

        $trackingNumbers = explode(',', $data['trackingNumbers']);
        $truckArrivalLineService->checkForInvalidNumber($trackingNumbers, $entityManager);
        foreach ($trackingNumbers as $trackingNumber) {
            $line = (new TruckArrivalLine())
                ->setNumber($trackingNumber);
            $truckArrival->addTrackingLine($line);
            $entityManager->persist($line);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le numéro de tracking transporteur a bien été ajouté.'
        ]);
    }

    #[Route('/reserves/api', name: 'settings_reserves_api', options: ['expose' => true])]
    public function reservesApi(Request                 $request,
                                EntityManagerInterface  $entityManager): Response {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $class = "form-control data";

        $reserveTypeRepository = $entityManager->getRepository(ReserveType::class);
        $reserveTypes = $reserveTypeRepository->findAll();

        $rows = [];
        foreach($reserveTypes ?? [] as $reserveType) {
            $actions = "
                <input type='hidden' class='$class' name='id' value='{$reserveType->getId()}'>
                <button class='btn btn-silent delete-row' data-id='{$reserveType->getId()}'>
                    <i class='wii-icon wii-icon-trash text-primary'></i>
                </button>
            ";
            if($edit) {
                $isDefaultReserveType = $reserveType->isDefaultReserveType() ? 'checked' : '';
                $isActive = $reserveType->isActive() ? 'checked' : '';
                $isDisabledTrackingNumber = $reserveType->isDisableTrackingNumber() ? 'checked' : '';
                $userOptions = Stream::from($reserveType->getNotifiedUsers())
                    ->map(fn(Utilisateur $user) => "<option value='{$user->getId()}' selected>{$user->getUsername()}</option>")
                    ->join("");

                $rows[] = [
                    "id" => $reserveType->getId(),
                    "actions" => $actions,
                    "label" => "<input type='text' name='label' class='$class' value='{$reserveType->getLabel()}' required data-global-error='Libellé'/>",
                    "emails" => "<select class='form-control data select2' name='emails' multiple data-s2='user'>$userOptions</select>",
                    "defaultReserveType" => "<div class='checkbox-container'><input type='checkbox' name='defaultReserveType' class='form-control data' {$isDefaultReserveType}/></div>",
                    "active" => "<div class='checkbox-container'><input type='checkbox' name='active' class='form-control data' {$isActive}/></div>",
                    "disableTrackingNumber" => "<div class='checkbox-container'><input type='checkbox' name='disableTrackingNumber' class='form-control data' {$isDisabledTrackingNumber}/></div>"
                ];
            } else {
                $emails = Stream::from($reserveType->getNotifiedUsers())
                    ->map(fn(Utilisateur $user) => $this->formatService->user($user, '', true))
                    ->toArray();

                $rows[] = [
                    "id" => $reserveType->getId(),
                    "actions" => $actions,
                    "label" => $reserveType->getLabel(),
                    "emails" => implode(', ', $emails),
                    "defaultReserveType" => $this->formatService->bool($reserveType->isDefaultReserveType()),
                    "active" => $this->formatService->bool($reserveType->isActive()),
                    "disableTrackingNumber" => $this->formatService->bool($reserveType->isDisableTrackingNumber()),
                ];
            }
        }

        $rows[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "label" => "",
            "emails" => "",
            "defaultReserveType" => "",
            "active" => "",
            "disableTrackingNumber" => "",
        ];

        return $this->json([
            "data" => $rows,
        ]);
    }

    #[Route('/reserves/supprimer/{entity}', name: 'settings_reserve_type_delete', options: ['expose' => true])]
    #[HasPermission([Menu::PARAM, Action::DELETE])]
    public function deleteReserveType(EntityManagerInterface    $manager,
                                      ReserveType               $entity): JsonResponse {
        $reserveTypeRepository = $manager->getRepository(ReserveType::class);
        if (count($reserveTypeRepository->findAll()) === 1) {
            return new JsonResponse([
                'success' => false,
                'msg' => "Vous ne pouvez pas supprimer tous les types de réserve."
            ]);
        }

        $reserveRepository = $manager->getRepository(Reserve::class);
        if (count($reserveRepository->findBy(['reserveType' => $entity->getId()])) > 0) {
            return new JsonResponse([
                'success' => false,
                'msg' => "Une ou plusieurs réserve(s) utilise ce type de réserve."
            ]);
        }

        $manager->remove($entity);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Un type de réserve a bien été supprimé",
        ]);
    }

    #[Route('/imprimer', name: 'print_label', options: ['expose' => true],  methods: self::GET)]
    public function printTruckArrivalLabel(EntityManagerInterface $entityManager,
                                           Request                $request,
                                           PDFGeneratorService    $PDFGeneratorService,
                                           TruckArrivalService    $truckArrivalService): PdfResponse {
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $truckArrivalId = $request->query->getInt('truckArrivalId');
        $truckArrival = $truckArrivalRepository->find($truckArrivalId);

        [$fileName, $barcodeConfig] = $truckArrivalService->getLabelConfig($truckArrival);

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, [$barcodeConfig]),
            $fileName
        );
    }
}
