<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Repository\LatePackRepository;
use App\Service\EnCoursService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

        $minLocationFilter = 1;
        $maxLocationFilter = 10;

        return $this->render('en_cours/index.html.twig', [
            'emplacements' => $emplacementRepository->findWhereArticleIs(),
			'natures' => $natureRepository->findAll(),
            'minLocationFilter' => $minLocationFilter,
            'maxLocationFilter' => $maxLocationFilter,
			'multiple' => true
        ]);
    }

    /**
     * @Route("/encours-api", name="en_cours_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EnCoursService $enCoursService
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
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
     * @Route("/statistiques/retard-api", name="api_retard", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param LatePackRepository $latePackRepository
     * @return JsonResponse
     */
    public function apiForRetard(LatePackRepository $latePackRepository): Response {
        $retards = $latePackRepository->findAllForDatatable();
        return new JsonResponse([
            'data' => $retards
        ]);
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
		throw new BadRequestHttpException();

	}
}
