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
use App\Entity\ReserveType;
use App\Entity\Setting;
use App\Entity\Transporteur;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\FilterSupService;
use App\Service\ReserveService;
use App\Service\TruckArrivalLineService;
use App\Service\TruckArrivalService;
use App\Service\UniqueNumberService;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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
        $carrier = $truckArrival->getCarrier();
        $minTrackingNumber = $carrier->getMinTrackingNumberLength();
        $maxTrackingNumber = $carrier->getMaxTrackingNumberLength();

        return $this->render('truck_arrival/show.html.twig', [
            'truckArrival' => $truckArrival,
            'lineAssociated' => $lineAssociated,
            'minTrackingNumber' => $minTrackingNumber,
            'maxTrackingNumber' => $maxTrackingNumber,
            'showDetails' => $truckArrivalService->createHeaderDetailsConfig($truckArrival),
        ]);
    }

    #[Route('/api-columns', name: 'truck_arrival_api_columns', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_TRUCK_ARRIVALS])]
    public function apiColumns(TruckArrivalService $truckArrivalService): Response {
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
        $user = $this->getUser();

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
    public function submit(Request                $request,
                           EntityManagerInterface $entityManager,
                           UniqueNumberService    $uniqueNumberService,
                           AttachmentService      $attachmentService): Response
    {
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $driverRepository = $entityManager->getRepository(Chauffeur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $lineNumberRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

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
                $defaultLocationId = $settingRepository->getOneParamByLabel(Setting::TRUCK_ARRIVALS_DEFAULT_UNLOADING_LOCATION);
                $defaultLocation = $defaultLocationId ? $locationRepository->find($defaultLocationId) : null;
                $truckArrival->setUnloadingLocation($defaultLocation);
            }

            $number = $uniqueNumberService->create($entityManager, null, TruckArrival::class, UniqueNumberService::DATE_COUNTER_FORMAT_TRUCK_ARRIVAL, $now, [$carrier->getCode()]);

            $truckArrival->setNumber($number);
        } else {
            $files = $request->files->all();
            $truckArrival->clearAttachments();

            $attachments = $attachmentService->createAttachments($files);
            foreach ($attachments as $attachment) {
                $entityManager->persist($attachment);
                $truckArrival->addAttachment($attachment);
            }
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

        if ($data->has('hasGeneralReserve') ?? false) {
            $generalReserve = new Reserve();
            $generalReserve
                ->setComment($data->get('generalReserveComment'))
                ->setTruckArrival($truckArrival)
                ->setKind(Reserve::KIND_GENERAL);
            $entityManager->persist($generalReserve);
        }

        if ($data->has('hasQuantityReserve') ?? false) {
            $quantityReserve = new Reserve();
            $quantityReserve
                ->setComment($data->get('quantityReserveComment'))
                ->setTruckArrival($truckArrival)
                ->setKind(Reserve::KIND_QUANTITY)
                ->setQuantity($data->get('reserveQuantity'))
                ->setQuantityType($data->get('reserveType'));
            $entityManager->persist($quantityReserve);
        }

        foreach (explode(',', $data->get('trackingNumbers') ?? '') as $lineNumber) {
            /** @var TruckArrivalLine $lines */
            $lines = $lineNumberRepository->findBy(['number' => $lineNumber]);
            if ($lineNumber && !empty($lines) && end($lines)?->getReserve()?->getReserveType()?->isDisableTrackingNumber()) {
                $arrivalLine = new TruckArrivalLine();
                $arrivalLine
                    ->setTruckArrival($truckArrival)
                    ->setNumber($lineNumber);
                $truckArrival->addTrackingLine($arrivalLine);
                $entityManager->persist($arrivalLine);
            }
        }

        if($truckArrival->getTrackingLines()->isEmpty()){
            return new JsonResponse([
                'success' => false,
                'msg' => "Impossible d'enregistrer votre action. Veuillez renseigner au moins un n° de tracking transporteur valide."
            ]);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'truckArrivalId' => $truckArrival->getId(),
            'redirect' => $data->has('goToArrivalButton') && boolval($data->get('goToArrivalButton'))
                            ? $this->generateUrl('arrivage_index', [
                                'truckArrivalId' => $truckArrival->getId()
                            ])
                            : '',
        ]);
    }

    #[Route('/{truckArrival}/delete', name: 'truck_arrival_delete', options: ['expose' => true], methods: ['GET', 'POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::DELETE_TRUCK_ARRIVALS])]
    public function delete(TruckArrival $truckArrival,
                           EntityManagerInterface $entityManager): Response {

        $hasLinesAssociatedToArrival = $truckArrival->getTrackingLines()->count() > 0
            ? Stream::from($truckArrival->getTrackingLines())
                 ->filter(fn(TruckArrivalLine $line) => !$line->getArrivals()->isEmpty())
                 ->count()
            : 0;

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

    // Reçois info POST du show.js
    // Si le nouveau nombre existe déjà -> erreur
    // Sinon -> enregistre en BDD le nombre et message succès
    #[Route('/add-tracking-number', name: 'add_tracking_number', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    public function addTrackingNumber(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = $request->request->all();
        $truckArrivalLineRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $truckArrival = $truckArrivalRepository->find($request->request->get('truckArrival'));

        $trackingNumbers = explode(',', $data['trackingNumbers']);

        foreach ($trackingNumbers as $trackingNumber) {
            $truckArrivalLines = $truckArrivalLineRepository->findBy(['number' => $trackingNumber]);
            if ($trackingNumber && (empty($truckArrivalLines) || end($truckArrivalLines)?->getReserve()?->getReserveType()?->isDisableTrackingNumber())) {
                $line = (new TruckArrivalLine())
                    ->setNumber($trackingNumber);
                $truckArrival->addTrackingLine($line);
                $entityManager->persist($line);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'trackingNumber' => $trackingNumber,
                    'msg' => "Le numéro de tracking transporteur " . $trackingNumber . " existe déjà. Impossible de l'ajouter à cet arrivage camion. Veuillez en sélectionner un autre."
                ]);
            }
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
    public function deleteReserveType(EntityManagerInterface $manager, ReserveType $entity): JsonResponse {
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
}
