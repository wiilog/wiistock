<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Service\EnCoursService;
use App\Service\LanguageService;
use App\Service\TranslationService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route("/api", name: "en_cours_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function apiForEmplacement(Request                $request,
                                      EnCoursService         $enCoursService,
                                      EntityManagerInterface $entityManager): Response
    {
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
        $natureIds = array_map(
            function (string $natureParam) {
                $natureParamSplit = explode(';', $natureParam);
                return $natureParamSplit[0] ?? 0;
            },
            $filtersParam ? explode(',', $filtersParam) : $natures
        );
        $response = $enCoursService->getEnCours([$emplacement->getId()], $natureIds, false, true, $this->getUser(), $useTruckArrivals);
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
    public function checkTimeWorkedIsDefined(EntityManagerInterface $entityManager): JsonResponse
    {
        $daysRepository = $entityManager->getRepository(DaysWorked::class);
        $nbEmptyTimes = $daysRepository->countEmptyTimes();

        return new JsonResponse($nbEmptyTimes == 0);

    }

    #[Route("/{emplacement}/csv", name: "ongoing_pack_csv", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::TRACA, Action::EXPORT])]
    public function getOngoingPackCSV(Emplacement      $emplacement,
                                      CSVExportService $CSVExportService,
                                      EnCoursService   $encoursService): Response
    {
        $headers = [
            'Emplacement',
            'Unité logistique',
            'Date de dépose',
            'Delai',
            'Retard',
        ];

        return $CSVExportService->streamResponse(
            function ($output) use ($emplacement,
                                    $CSVExportService,
                                    $encoursService,
                                    $headers)
            {
                $data = $encoursService->getEnCours([$emplacement->getId()], [], false, true, $this->getUser());
                foreach ($data as $line) {
                    $encoursService->putOngoingPackLine($output, $CSVExportService, $line);
                }
            }, "Export_encours_" . $emplacement->getLabel() . ".csv", $headers);
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

    #[Route("/colonne-visible", name: "save_column_visible_for_encours", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_ENCO], mode: HasPermission::IN_JSON)]
    public function saveColumnVisible(Request                $request,
                                      TranslationService     $translationService,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService   $visibleColumnService): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $visibleColumnService->setVisibleColumns('onGoing', $fields, $currentUser);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => $translationService->translate('Général', null, 'Zone liste', 'Vos préférences de colonnes à afficher ont bien été sauvegardées', false)
        ]);
    }
}
