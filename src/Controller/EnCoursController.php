<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Repository\DaysWorkedRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\EmplacementRepository;

use App\Service\EnCoursService;

use App\Service\UserService;
use DateInterval;
use DatePeriod;
use DateTime as DateTimeAlias;
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
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var DaysWorkedRepository
     */
    private $daysRepository;

    /**
     * @var EnCoursService
     */
    private $enCoursService;

    private $userService;

	/**
	 * EnCoursController constructor.
	 * @param UserService $userService
	 * @param MouvementTracaRepository $mouvementTracaRepository
	 * @param EmplacementRepository $emplacementRepository
	 * @param DaysWorkedRepository $daysRepository
	 * @param EnCoursService $enCoursService
	 */
    public function __construct(UserService $userService, MouvementTracaRepository $mouvementTracaRepository, EmplacementRepository $emplacementRepository, DaysWorkedRepository $daysRepository, EnCoursService $enCoursService)
    {
        $this->enCoursService = $enCoursService;
        $this->daysRepository = $daysRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->userService = $userService;
    }


    /**
     * @Route("/encours", name="en_cours", methods={"GET"})
     * @throws Exception
     */
    public function index(): Response
    {
		if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ENCO)) {
			return $this->redirectToRoute('access_denied');
		}
        return $this->render('en_cours/index.html.twig', [
            'emplacements' => $this->emplacementRepository->findWhereArticleIs()
        ]);
    }

	/**
	 * @Route("/encours-api", name="en_cours_api", options={"expose"=true}, methods="GET|POST")
	 * @param Request $request
	 * @return JsonResponse
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
    public function apiForEmplacement(Request $request): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $emplacement = $this->emplacementRepository->find($data['id']);
            return new JsonResponse($this->enCoursService->getEnCoursForEmplacement($emplacement));
        }
        throw new NotFoundHttpException("404");
    }


	/**
	 * @Route("/retard-api", name="api_retard", options={"expose"=true}, methods="GET|POST")
	 * @param Request $request
	 * @return JsonResponse
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
    public function apiForRetard(Request $request): Response {
        if ($request->isXmlHttpRequest()) {
            $retards = [];
            foreach ($this->emplacementRepository->findWhereArticleIs() as $emplacementArray) {
                $emplacement = $this->emplacementRepository->find($emplacementArray['id']);
                foreach ($this->mouvementTracaRepository->findByEmplacementTo($emplacement) as $mvt) {
                    if (intval($this->mouvementTracaRepository->findByEmplacementToAndArticleAndDate($emplacement, $mvt)) === 0) {
                        $dateMvt = $mvt->getDatetime();
                        $minutesBetween = $this->enCoursService->getMinutesBetween($dateMvt);
                        $dataForTable = $this->enCoursService->buildDataForDatatable($minutesBetween, $emplacement);
                        if ($dataForTable && $dataForTable['late']) {
                            $retards[] = [
                                'colis' => $mvt->getColis(),
                                'time' => $dataForTable['time'],
                                'date' => $dateMvt->format('d/m/Y H:i'),
                                'emp' => $emplacement->getLabel(),
                            ];
                        }
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
     * @param $dateMvt DateTimeAlias
     * @return int
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function getMinutesBetween($dateMvt): int
    {
        $now = new DateTimeAlias("now", new \DateTimeZone("Europe/Paris"));
        $nowIncluding = (new DateTimeAlias("now", new \DateTimeZone("Europe/Paris")))
            ->add(new DateInterval('PT' . (23 - intval($now->format('H'))) . 'H'));
        // Get days between now and date mvt including now
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($dateMvt, $interval, $nowIncluding);
        $minutesBetween = 0;
        /**
         * @var $day DateTimeAlias
         */
        foreach ($period as $day) {
        	$minutesBetween += $this->enCoursService->getMinutesWorkedDuringThisDay($day, $now, $dateMvt);
        }

        return $minutesBetween;
    }

	/**
	 * @Route("/verification-temps-travaille", name="check_time_worked_is_defined", options={"expose"=true}, methods="GET|POST")
	 */
    public function checkTimeWorkedIsDefined(Request $request)
	{
		if ($request->isXmlHttpRequest()) {
			$nbEmptyTimes = $this->daysRepository->countEmptyTimes();

			return new JsonResponse($nbEmptyTimes == 0);
		}
		throw new NotFoundHttpException("404");

	}
}
