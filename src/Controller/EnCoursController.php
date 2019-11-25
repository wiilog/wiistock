<?php


namespace App\Controller;

use App\Entity\DaysWorked;
use App\Entity\MouvementTraca;
use App\Repository\DaysWorkedRepository;
use App\Repository\MouvementTracaRepository;
use App\Service\EnCoursService;
use DateTime as DateTimeAlias;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use phpDocumentor\Reflection\Types\Boolean;
use phpDocumentor\Reflection\Types\Integer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\EmplacementRepository;
use App\Repository\MouvementStockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Date;

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

    /**
     * EnCoursController constructor.
     * @param MouvementTracaRepository $mouvementTracaRepository
     * @param EmplacementRepository $emplacementRepository
     * @param DaysWorkedRepository $daysRepository
     */
    public function __construct(MouvementTracaRepository $mouvementTracaRepository, EmplacementRepository $emplacementRepository, DaysWorkedRepository $daysRepository, EnCoursService $enCoursService)
    {
        $this->enCoursService = $enCoursService;
        $this->daysRepository = $daysRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->emplacementRepository = $emplacementRepository;
    }


    /**
     * @Route("/encours", name="en_cours", methods={"GET"})
     * @throws Exception
     */
    public function index(): Response
    {
        return $this->render('en_cours/index.html.twig', [
            'emplacements' => $this->emplacementRepository->findWhereArticleIs()
        ]);
    }

    /**
     * @Route("/encours-api", name="en_cours_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function apiForEmplacement(Request $request): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $emplacementInfo = [];
            $emplacement = $this->emplacementRepository->find($data['id']);
            foreach ($this->mouvementTracaRepository->findByEmplacementTo($emplacement) as $mvt) {
                if (intval($this->mouvementTracaRepository->findByEmplacementToAndArticleAndDate($emplacement, $mvt)) === 0) {
                    //VERIFCECILE
                    $dateMvt = DateTimeAlias::createFromFormat(DateTimeAlias::ATOM, explode('_', $mvt->getDate())[0]);
                    $minutesBetween = $this->getMinutesBetween($mvt);
                    $dataForTable = $this->enCoursService->buildDataForDatatable($minutesBetween, $emplacement);
                    //VERIFCECILE
                    $emplacementInfo[] = [
                        'colis' => $mvt->getRefArticle(),
                        'time' => $dataForTable['time'],
                        'date' => $dateMvt->format('d/m/y H:i'),
                        'max' => $emplacement->getDateMaxTime(),
                        'late' => $dataForTable['late']
                    ];
                }
            }
            return new JsonResponse([
                'data' => $emplacementInfo
            ]);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/retard-api", name="api_retard", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function apiForRetard(Request $request): Response {
        if ($request->isXmlHttpRequest()) {
            $retards = [];
            foreach ($this->emplacementRepository->findWhereArticleIs() as $emplacementArray) {
                $emplacement = $this->emplacementRepository->find($emplacementArray['id']);
                foreach ($this->mouvementTracaRepository->findByEmplacementTo($emplacement) as $mvt) {
                    if (intval($this->mouvementTracaRepository->findByEmplacementToAndArticleAndDate($emplacement, $mvt)) === 0) {
                        //VERIFCECILE
                        $dateMvt = DateTimeAlias::createFromFormat(DateTimeAlias::ATOM, explode('_', $mvt->getDate())[0]);
                        $minutesBetween = $this->getMinutesBetween($mvt);
                        $dataForTable = $this->enCoursService->buildDataForDatatable($minutesBetween, $emplacement);
                        //VERIFCECILE
                        if ($dataForTable['late']) {
                            $retards[] = [
                                'colis' => $mvt->getRefArticle(),
                                'time' => $dataForTable['time'],
                                'date' => $dateMvt->format('d/m/y H:i'),
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
     * @param $mvt MouvementTraca
     * @return int
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function getMinutesBetween($mvt): int
    {
        $now = new DateTimeAlias("now", new \DateTimeZone("Europe/Paris"));
        $nowIncluding = (new DateTimeAlias("now", new \DateTimeZone("Europe/Paris")))
            ->add(new \DateInterval('PT' . (18 - intval($now->format('H'))) . 'H'));
        //VERIFCECILE
        $dateMvt = DateTimeAlias::createFromFormat(DateTimeAlias::ATOM, explode('_', $mvt->getDate())[0]);
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($dateMvt, $interval, $nowIncluding);
        $minutesBetween = 0;
        /**
         * @var $day DateTimeAlias
         */
        foreach ($period as $day) {
            $minutesBetween += $this->enCoursService->getMinutesWorkedDuringThisDay($day, $now, $dateMvt);
        }
        return $minutesBetween;
    }
}