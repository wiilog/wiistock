<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\MouvementTraca;
use App\Entity\Nature;
use App\Repository\FiltreSupRepository;
use App\Service\EnCoursService;
use App\Service\UserService;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;


class EnCoursController extends AbstractController
{
	/**
	 * @Route("/encours", name="en_cours", methods={"GET"})
	 * @param UserService $userService
	 * @param EntityManagerInterface $entityManager
	 * @return Response
	 */
    public function index(UserService $userService,
                          EntityManagerInterface $entityManager): Response {
		if (!$userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ENCO)) {
			return $this->redirectToRoute('access_denied');
		}

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
		$natureRepository = $entityManager->getRepository(Nature::class);

        return $this->render('en_cours/index.html.twig', [
            'emplacements' => $emplacementRepository->findWhereArticleIs(),
			'natures' => $natureRepository->findAll(),
			'multiple' => true
        ]);
    }

	/**
	 * @Route("/encours-api", name="en_cours_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
	 * @param Request $request
	 * @param EnCoursService $enCoursService
	 * @param FiltreSupRepository $filtreSupRepository
	 * @param EntityManagerInterface $entityManager
	 * @return JsonResponse
	 * @throws DBALException
	 * @throws NonUniqueResultException
	 */
    public function apiForEmplacement(Request $request,
                                      EnCoursService $enCoursService,
                                      FiltreSupRepository $filtreSupRepository,
                                      EntityManagerInterface $entityManager): Response
	{
    	$emplacementRepository = $entityManager->getRepository(Emplacement::class);
    	$emplacement = $emplacementRepository->find($request->request->get('id'));

		$filter = $filtreSupRepository->getOnebyFieldAndPageAndUser(FiltreSup::FIELD_NATURES, FiltreSup::PAGE_ENCOURS, $this->getUser());
		$filters = $filter ? explode(',', $filter) : null;

		return new JsonResponse($enCoursService->getEnCoursForEmplacement($emplacement, $filters));
    }


    /**
     * @Route("/retard-api", name="api_retard", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param EnCoursService $enCoursService
     * @return JsonResponse
     * @throws DBALException
     * @throws Exception
     */
    public function apiForRetard(Request $request,
                                 EntityManagerInterface $entityManager,
                                 EnCoursService $enCoursService): Response {
        if ($request->isXmlHttpRequest()) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
            $retards = [];
            foreach ($emplacementRepository->findWhereArticleIs() as $emplacementArray) {
                $emplacement = $emplacementRepository->find($emplacementArray['id']);
                $mouvements = $mouvementTracaRepository->findObjectOnLocation($emplacement);
                foreach ($mouvements as $mouvement) {
                    $dateMvt = $mouvement->getDatetime();
                    $movementAge = $enCoursService->getTrackingMovementAge($dateMvt);
                    $dataForTable = $enCoursService->buildDataForDatatable($movementAge, $emplacement);
                    if ($dataForTable && $dataForTable['late']) {
                        $retards[] = [
                            'colis' => $mouvement->getColis(),
                            'time' => $dataForTable['time'],
                            'date' => $dateMvt->format('d/m/Y H:i'),
                            'emp' => $emplacement->getLabel(),
                        ];
                    }
                }
            }
            return new JsonResponse([
                'data' => $retards
            ]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verification-temps-travaille", name="check_time_worked_is_defined", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function checkTimeWorkedIsDefined(Request $request,
                                             EntityManagerInterface $entityManager) {
		if ($request->isXmlHttpRequest()) {
		    $daysRepository = $entityManager->getRepository(DaysWorked::class);
			$nbEmptyTimes = $daysRepository->countEmptyTimes();

			return new JsonResponse($nbEmptyTimes == 0);
		}
		throw new NotFoundHttpException("404");

	}
}
