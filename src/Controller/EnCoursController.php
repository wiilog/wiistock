<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Service\CSVExportService;
use App\Service\EnCoursService;
use App\Service\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use WiiCommon\Helper\Stream;


/**
 * @Route("/encours")
 */
class EnCoursController extends AbstractController
{
    /**
     * @Route("/", name="en_cours", methods={"GET"})
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ENCO})
     */
    public function index(Request $request,
                          EntityManagerInterface $entityManager,
                          LanguageService $languageService): Response
    {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $minLocationFilter = 1;
        $query = $request->query;

        $locationsFilterStr = $query->has('locations') ? $query->get('locations', '') : '';
        $fromDashboard = $query->has('fromDashboard') ? $query->get('fromDashboard') : '' ;
        if (!empty($locationsFilterStr)) {
            $locationsFilterId = explode(',', $locationsFilterStr);
            $locationsFilter = !empty($locationsFilterId)
                ? $emplacementRepository->findBy(['id' => $locationsFilterId])
                : [];
        } else {
            $locationsFilter = [];
        }

        return $this->render('en_cours/index.html.twig', [
            'userLanguage' => $this->getUser()->getLanguage(),
            'defaultLanguage' => $languageService->getDefaultLanguage(),
            'emplacements' => $emplacementRepository->findWhereArticleIs(),
            'locationsFilter' => $locationsFilter,
            'natures' => $natureRepository->findAll(),
            'minLocationFilter' => $minLocationFilter,
            'multiple' => true,
            'fromDashboard' => $fromDashboard,
        ]);
    }

    /**
     * @Route("/api", name="en_cours_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiForEmplacement(Request $request,
                                      EnCoursService $enCoursService,
                                      EntityManagerInterface $entityManager): Response
    {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $emplacement = $emplacementRepository->find($request->request->get('id'));
        $query = $request->query;
        $fromDashboard = $query->has('fromDashboard') && $query->get('fromDashboard');
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
            $filtersParam ? explode(',', $filtersParam) : []
        );
        $response = $enCoursService->getEnCours([$emplacement->getId()], $natureIds, false, 100, $this->getUser());
        return new JsonResponse([
            'data' => $response
        ]);
    }

    /**
     * @Route("/verification-temps-travaille", name="check_time_worked_is_defined", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function checkTimeWorkedIsDefined(EntityManagerInterface $entityManager): JsonResponse
    {
        $daysRepository = $entityManager->getRepository(DaysWorked::class);
        $nbEmptyTimes = $daysRepository->countEmptyTimes();

        return new JsonResponse($nbEmptyTimes == 0);

    }

    /**
     * @Route ("/{emplacement}/csv", name="ongoing_pack_csv",options={"expose"=true}, methods={"GET"})
     * @param Emplacement $emplacement
     * @param CSVExportService $CSVExportService
     * @param EnCoursService $encoursService
     * @return Response
     */
    public function getOngoingPackCSV(Emplacement $emplacement,
                                      CSVExportService $CSVExportService,
                                      EnCoursService $encoursService): Response
    {
        $headers = [
            'Emplacement',
            'Colis',
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
                $data = $encoursService->getEnCours([$emplacement->getId()], [], false, 100, $this->getUser());
                foreach ($data as $line) {
                    $encoursService->putOngoingPackLine($output, $CSVExportService, $line);
                }
            }, "Export_encours_" . $emplacement->getLabel() . ".csv", $headers);
    }


    /**
     * @Route ("/check-location-delay", name="check_location_delay",options={"expose"=true}, methods={"POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
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
