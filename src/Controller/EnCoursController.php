<?php


namespace App\Controller;

use App\Entity\DaysWorked;
use App\Entity\MouvementTraca;
use App\Repository\DaysWorkedRepository;
use App\Repository\MouvementTracaRepository;
use App\Service\EnCoursService;
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
     * @var EnCoursService
     */
    private $enoursService;

    /**
     * EnCoursController constructor.
     * @param MouvementTracaRepository $mouvementTracaRepository
     * @param EmplacementRepository $emplacementRepository
     * @param DaysWorkedRepository $daysRepository
     */
    public function __construct(MouvementTracaRepository $mouvementTracaRepository, EmplacementRepository $emplacementRepository, DaysWorkedRepository $daysRepository, EnCoursService $enCoursService)
    {
        $this->enoursService = $enCoursService;
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
                    $dataForTable = $this->enoursService->buildDataForDatatable($minutesBetween, $emplacement);
                    // TODO VERIFCECILE
                    $emplacements[$emplacement->getLabel()][] = [
                        'ref' => $mvt->getRefArticle(),
                        'time' => $dataForTable['time'],
                        'max' => $emplacement->getDateMaxTime(),
                        'late' => $dataForTable['late']
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
     * @throws \Exception
     */
    private function getMinutesBetween($mvt): int
    {
        $now = new \DateTime("now", new \DateTimeZone("Europe/Paris"));
        $nowIncluding = (new \DateTime("now", new \DateTimeZone("Europe/Paris")))
            ->add(new \DateInterval('PT' . (18 - intval($now->format('H'))) . 'H'));
        $dateMvt = \DateTime::createFromFormat(\DateTime::ATOM, explode('_', $mvt->getDate())[0]);
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($dateMvt, $interval, $nowIncluding);
        $minutesBetween = 0;
        /**
         * @var $day \DateTime
         */
        foreach ($period as $day) {
            $minutesBetween += $this->enoursService->getMinutesWorkedDuringThisDay($day, $now, $dateMvt);
        }
        return $minutesBetween;
    }
}