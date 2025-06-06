<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Service\EnCoursService;
use App\Service\LanguageService;
use App\Service\WorkPeriod\WorkPeriodItem;
use App\Service\WorkPeriod\WorkPeriodService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route("/encours")]
class EnCoursController extends AbstractController
{
    #[Route("/", name: "en_cours", methods: "GET")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_ENCO])]
    public function index(Request                $request,
                          EntityManagerInterface $entityManager,
                          EnCoursService         $enCoursService,
                          LanguageService        $languageService): Response
    {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $minLocationFilter = 1;
        $query = $request->query;

        $locationsFilterStr = $query->has('locations') ? $query->get('locations', '') : '';
        $naturesFilterStr = $query->get('natures', '');
        $fromDashboard = $query->has('fromDashboard') ? $query->get('fromDashboard') : '' ;
        $useTruckArrivals = $query->getBoolean('useTruckArrivalsFromDashboard');

        $fields = $enCoursService->getVisibleColumnsConfig($this->getUser());

        if (!empty($locationsFilterStr)) {
            $locationsFilterId = explode(',', $locationsFilterStr);
            $locationsFilter = !empty($locationsFilterId)
                ? $emplacementRepository->findBy(['id' => $locationsFilterId])
                : [];
        } else {
            $locationsFilter = [];
        }

        if (!empty($naturesFilterStr)) {
            $naturesFilter = explode(',', $naturesFilterStr);
        } else {
            $naturesFilter = [];
        }

        return $this->render('en_cours/index.html.twig', [
            'userLanguage' => $this->getUser()->getLanguage(),
            'defaultLanguage' => $languageService->getDefaultLanguage(),
            'emplacements' => $emplacementRepository->findWhereArticleIs(),
            'locationsFilter' => $locationsFilter,
            'naturesFilter' => $naturesFilter,
            'natures' => $natureRepository->findAll(),
            'minLocationFilter' => $minLocationFilter,
            'multiple' => true,
            'fromDashboard' => $fromDashboard,
            'useTruckArrivalsFromDashboard' => $useTruckArrivals,
            'fields' => $fields,
            'initial_visible_columns' => $this->apiColumns($entityManager, $enCoursService)->getContent(),
        ]);
    }

    #[Route("/api", name: "ongoing_pack_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function ongoingPackApi(Request                $request,
                                   EnCoursService         $enCoursService,
                                   EntityManagerInterface $entityManager): Response {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $emplacement = $emplacementRepository->find($request->request->get('id'));
        $query = $request->query;
        $fromDashboard = $query->has('fromDashboard') && $query->get('fromDashboard');
        $useTruckArrivals = $request->request->getBoolean('useTruckArrivals');
        $natures = $request->request->all('natures');

        if (!$fromDashboard) {
            $filtersParam = $filtreSupRepository->getOnebyFieldAndPageAndUser(
                FiltreSup::FIELD_NATURES,
                FiltreSup::PAGE_ENCOURS,
                $this->getUser()
            );
        } else {
            $filtersParam = null;
        }
        $natureFilter = $filtersParam
            ? Stream::explode(',', $filtersParam)->filter()
            : $natures;

        $natureIds = Stream::from($natureFilter)
            ->filterMap(static fn(string $natureParam) => explode(':', $natureParam)[0] ?? null)
            ->toArray();

        $response = $enCoursService->getEnCours(
            entityManager: $entityManager,
            locations: [$emplacement->getId()],
            natures: $natureIds,
            fromOnGoing: true,
            user: $this->getUser(),
            useTruckArrivals: $useTruckArrivals
        );

        return new JsonResponse([
            'data' => $response
        ]);
    }

    #[Route("/api-columns", name: "encours_api_columns", options: ["expose" => true], methods: [self::GET], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_ENCO], mode: HasPermission::IN_JSON)]
    public function apiColumns(EntityManagerInterface $entityManager,
                               EnCoursService         $enCoursService): Response
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $columns = $enCoursService->getVisibleColumnsConfig($currentUser);
        return new JsonResponse($columns);
    }

    #[Route("/check-time-worked-is-defined", name: "check_time_worked_is_defined", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function checkTimeWorkedIsDefined(EntityManagerInterface $entityManager,
                                             WorkPeriodService      $workPeriodService): JsonResponse
    {
        $workedDays = $workPeriodService->get($entityManager, WorkPeriodItem::WORKED_DAYS);

        return $this->json([
            "success" => true,
            "result" => !empty($workedDays),
        ]);

    }

    #[Route("/{emplacement}/csv", name: "ongoing_pack_csv", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::TRACA, Action::EXPORT])]
    public function getOngoingPackCSV(Emplacement            $emplacement,
                                      CSVExportService       $CSVExportService,
                                      EnCoursService         $encoursService,
                                      EntityManagerInterface $entityManager): Response
    {
        $headers = [
            'Emplacement',
            'Unité logistique',
            'Date de dépose',
            'Delai',
            'Retard',
            'Référence',
            'Libellé'
        ];

        return $CSVExportService->streamResponse(
            function ($output) use ($emplacement,
                                    $CSVExportService,
                                    $encoursService,
                                    $headers,
                                    $entityManager)
            {
                $data = $encoursService->getEnCours($entityManager, [$emplacement->getId()], [], false, true, $this->getUser());
                foreach ($data as $line) {
                    $encoursService->putOngoingPackLine($output, $CSVExportService, $line);
                }
            }, "Export_encours_" . str_replace('/','', $emplacement->getLabel()) . ".csv", $headers);
    }

    #[Route("/check-location-delay", name: "check_location_delay", options: ["expose" => true], methods: "POST")]
    public function checkLocationMaxDelay(Request $request, EntityManagerInterface $entityManager): Response
    {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $emplacements = $emplacementRepository->findBy(['id' => $request->query->all('locationIds')]);

        $delayError = Stream::from($emplacements)
            ->every(fn(Emplacement $emplacement) => $emplacement->getDateMaxTime() !== null);
        return new JsonResponse([
            'hasDelayError' => $delayError
        ]);
    }
}
