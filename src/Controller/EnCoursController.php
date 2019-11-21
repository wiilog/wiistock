<?php


namespace App\Controller;

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
                    $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                    $dateMvt = new \DateTime(explode('_', $mvt->getDate())[0], new \DateTimeZone('Europe/Paris'));
                    $diff = $date->diff($dateMvt);
                    $diffHours = $diff->h + ($diff->d*24);
                    $diffString =
                        ($diffHours < 10 ? '0' . $diffHours : $diffHours)
                        . ':' . ($date->diff($dateMvt)->i < 10 ? '0' . $date->diff($dateMvt)->i : $date->diff($dateMvt)->i);
                    $emplacements[$emplacement->getLabel()][] = [
                        'ref' => $mvt->getRefArticle(),
                        'time' => $diffString,
                        'late' => $this->isLate($emplacement->getDateMaxTime(), $dateMvt)
                    ];
                }
            }
        }
        return $emplacements;
    }

    /**
     * @param $maxTime string
     * @param $dateStay \DateTime
     * @return bool
     * @throws \Exception
     */
    private function isLate($maxTime, $dateStay): bool {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $daysWorked = $this->daysRepository->findBy([
            'worked' => true
        ]);
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($dateStay, $interval, $now);
        foreach ($period as $day) {
            dump($day->format('l Y-m-d H:i:s'));
        }
        return true;
    }
}