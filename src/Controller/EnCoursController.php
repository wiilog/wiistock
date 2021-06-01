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
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/encours")
 */
class EnCoursController extends AbstractController
{
    /**
     * @Route("/", name="en_cours", methods={"GET"})
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ENCO})
     */
    public function index(UserService $userService,
                          Request $request,
                          EntityManagerInterface $entityManager): Response
    {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $minLocationFilter = 1;

        $locationsFilterStr = $request->query->get('locations', '');
        if (!empty($locationsFilterStr)) {
            $locationsFilterId = explode(',', $locationsFilterStr);
            $locationsFilter = !empty($locationsFilterId)
                ? $emplacementRepository->findBy(['id' => $locationsFilterId])
                : [];
        } else {
            $locationsFilter = [];
        }

        return $this->render('en_cours/index.html.twig', [
            'emplacements' => $emplacementRepository->findWhereArticleIs(),
            'locationsFilter' => $locationsFilter,
            'natures' => $natureRepository->findAll(),
            'minLocationFilter' => $minLocationFilter,
            'multiple' => true
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

        $filtersParam = $filtreSupRepository->getOnebyFieldAndPageAndUser(
            FiltreSup::FIELD_NATURES,
            FiltreSup::PAGE_ENCOURS,
            $this->getUser()
        );
        $natureIds = array_map(
            function (string $natureParam) {
                $natureParamSplit = explode(';', $natureParam);
                return $natureParamSplit[0] ?? 0;
            },
            $filtersParam ? explode(',', $filtersParam) : []
        );
        $response = $enCoursService->getEnCours([$emplacement->getId()], $natureIds);
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
                $data = $encoursService->getEnCours([$emplacement->getId()]);
                foreach ($data as $line) {
                    $encoursService->putOngoingPackLine($output, $CSVExportService, $line);
                }
            }, "Export_encours_" . $emplacement->getLabel() . ".csv", $headers);
    }

}
