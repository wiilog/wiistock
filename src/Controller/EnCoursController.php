<?php


namespace App\Controller;

use App\Entity\DaysWorked;
use App\Entity\MouvementTraca;
use App\Repository\DaysWorkedRepository;
use App\Repository\MouvementTracaRepository;
use phpDocumentor\Reflection\Types\Boolean;
use phpDocumentor\Reflection\Types\Integer;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * EnCoursController constructor.
     * @param MouvementStockRepository $mouvementStockRepository
     * @param EmplacementRepository $emplacementRepository
     */
    public function __construct(MouvementTracaRepository $mouvementTracaRepository, EmplacementRepository $emplacementRepository, DaysWorkedRepository $daysRepository)
    {
        $this->daysRepository = $daysRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->emplacementRepository = $emplacementRepository;
    }


    /**
     * @Route("/encours", name="en_cours", methods={"GET"})
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function index(): Response
    {
        return $this->render('en_cours/index.html.twig', [
            'emplacements' => $this->api()
        ]);
    }

    /**
     * @Route("/encours/api", name="en_cours_api", options={"expose"=true}, methods="GET|POST")
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function api(): array
    {
        $emplacements = [];
        foreach ($this->emplacementRepository->findWhereArticleIs() as $emplacement) {
            foreach ($this->mouvementTracaRepository->findByEmplacementTo($emplacement) as $mvt) {
                if (intval($this->mouvementTracaRepository->findByEmplacementToAndArticleAndDate($emplacement, $mvt)) === 0) {
                    $minutesBetween = $this->getMinutesBetween($mvt);
                    $time =
                        (floor($minutesBetween / 60) < 10 ? ('0' . floor($minutesBetween / 60)) : floor($minutesBetween / 60))
                        . ':' .
                        ($minutesBetween % 60 < 10 ? ('0' . $minutesBetween % 60) : ($minutesBetween % 60));
                    $maxTime = $emplacement->getDateMaxTime();
                    $timeHours = floor($minutesBetween / 60);
                    $timeMinutes = $minutesBetween % 60;
                    $maxTimeHours = intval(explode(':', $maxTime)[0]);
                    $maxTimeMinutes = intval(explode(':', $maxTime)[1]);
                    $late = false;
                    if ($timeHours > $maxTimeHours) {
                        $late = true;
                    } else if (intval($timeHours) === $maxTimeHours){
                        $late =  $timeMinutes > $maxTimeMinutes;
                    }
                    $emplacements[$emplacement->getLabel()][] = [
                        'ref' => $mvt->getRefArticle(),
                        'time' => $time,
                        'max' => $emplacement->getDateMaxTime(),
                        'late' => $late
                    ];
                }
            }
        }
        return $emplacements;
    }

    /**
     * @param $maxTime string
     * @param $mvt MouvementTraca
     * @return bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function getMinutesBetween($mvt): int
    {
        $now = \DateTime::createFromFormat(\DateTime::ATOM, date(\DateTime::ATOM));
        $dateMvt = \DateTime::createFromFormat(\DateTime::ATOM, explode('_', $mvt->getDate())[0]);
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($dateMvt, $interval, $now);
        $minutesBetween = 0;
        /**
         * @var $day \DateTime
         */
        foreach ($period as $day) {
            $dayWorked = $this->daysRepository->findByDayAndWorked(strtolower($day->format('l')));
            $isToday = ($day->diff($now)->format('%a') === '0');
            $isMvtDay = ($day->diff($dateMvt)->format('%a') === '0');
            if ($dayWorked) {
                $timeArray = $dayWorked->getTimeArray();
                if ($isToday) {
                    if ($minutesBetween === 0) {
                        $minutesBetween += (intval($now->diff($dateMvt)->h) - 1)*60 + intval($now->diff($dateMvt)->i);
                    } else {
                        $minutesBetween += (
                            (intval($now->format('H'))*60) + intval($now->format('i')) -
                            ((($timeArray[0] + 1)*60) + $timeArray[1])
                        );
                    }
                } else if ($isMvtDay && !$isToday) {
                    $minutesBetween += (
                        ((($timeArray[6]+1)*60) + $timeArray[7]) -
                        ((intval($dateMvt->format('H')) + 1)*60) + intval($dateMvt->format('i'))
                    );
                } else {
                    $minutesBetween += $dayWorked->getTimeWorkedDuringThisDay();
                }
            }
        }
        return $minutesBetween;
    }
}