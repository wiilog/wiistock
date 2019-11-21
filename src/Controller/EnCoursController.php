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
                    $date = \DateTime::createFromFormat(\DateTime::ATOM, date(\DateTime::ATOM));
                    $dateMvt = \DateTime::createFromFormat(\DateTime::ATOM, explode('_', $mvt->getDate())[0]);
                    $hoursBetween = $this->getHoursBetween($mvt);
                    $emplacementHours = intval(explode(':', $emplacement->getDateMaxTime())[0]);
                    $isLate = false;
                    if ($hoursBetween > $emplacementHours) {
                        $isLate = true;
                    }
                    $time = ($hoursBetween < 10 ? '0' . $hoursBetween : $hoursBetween)
                        . ':' .
                        ($this->getExtraMinsBetween($dateMvt, $date) < 10 ?
                            '0' . $this->getExtraMinsBetween($dateMvt, $date) :
                            $this->getExtraMinsBetween($dateMvt, $date));
                    $emplacements[$emplacement->getLabel()][] = [
                        'ref' => $mvt->getRefArticle(),
                        'time' => $time,
                        'late' => $isLate,
                        'max' => $emplacement->getDateMaxTime()
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
    private function getHoursBetween($mvt): int
    {
        $date = \DateTime::createFromFormat(\DateTime::ATOM, date(\DateTime::ATOM));
        $dateMvt = \DateTime::createFromFormat(\DateTime::ATOM, explode('_', $mvt->getDate())[0]);
        $interval = \DateInterval::createFromDateString('1 hour');
        $period = new \DatePeriod($dateMvt, $interval, $date->modify('-1 hour'));
        $hoursBetween = 0;
        foreach ($period as $hour) {
            $day = $this->daysRepository->findByDayAndWorked(strtolower($hour->format('l')));
            if ($day && $this->hourIsInDayHour($day, $hour)) {
                $hoursBetween++;
            }
        }
        return $hoursBetween;
    }

    /**
     * @param $dateMvt \DateTime
     * @param $date \DateTime
     * @return float|int
     */
    private function getExtraMinsBetween($dateMvt, $date) {
        return abs(intval($dateMvt->format('i')) - intval($date->format('i')));
    }

    /**
     * @param $day DaysWorked
     * @param $hour \DateTime
     * @return bool
     */
    private function hourIsInDayHour($day, $hour): bool
    {
        $daysPeriod = explode(';', $day->getTimes());
        $afternoon = $daysPeriod[1];
        $morning = $daysPeriod[0];

        $afternoonLastHourAndMinute = explode('-', $afternoon)[1];
        $afternoonFirstHourAndMinute = explode('-', $afternoon)[0];
        $morningLastHourAndMinute = explode('-', $morning)[1];
        $morningFirstHourAndMinute = explode('-', $morning)[0];

        $afternoonLastHour = intval(explode(':', $afternoonLastHourAndMinute)[0]);
        $afternoonLastMinute = intval(explode(':', $afternoonLastHourAndMinute)[1]);
        $afternoonFirstHour = intval(explode(':', $afternoonFirstHourAndMinute)[0]);
        $afternoonFirstMinute = intval(explode(':', $afternoonFirstHourAndMinute)[1]);

        $morningLastHour = intval(explode(':', $morningLastHourAndMinute)[0]);
        $morningLastMinute = intval(explode(':', $morningLastHourAndMinute)[1]);
        $morningFirstHour = intval(explode(':', $morningFirstHourAndMinute)[0]);
        $morningFirstMinute = intval(explode(':', $morningFirstHourAndMinute)[1]);

        $hourTestedMinute = intval($hour->format('i'));
        $hourTestedHour = intval($hour->format('H'));
        if (($hourTestedHour < $morningLastHour && $hourTestedHour > $morningFirstHour)
            || ($hourTestedHour < $afternoonLastHour && $hourTestedHour > $afternoonFirstHour)) {
            return true;
        } else if ($hourTestedHour === $morningLastHour) {
            return $hourTestedMinute <= $morningLastMinute;
        } else if ($hourTestedHour === $morningFirstHour) {
            return $hourTestedMinute >= $morningFirstMinute;
        } else if ($hourTestedHour === $afternoonLastHour) {
            return $hourTestedMinute <= $afternoonLastMinute;
        } else if ($hourTestedHour === $afternoonFirstHour) {
            return $hourTestedMinute >= $afternoonFirstMinute;
        }
        return false;
    }
}