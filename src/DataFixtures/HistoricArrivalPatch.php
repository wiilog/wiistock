<?php


namespace App\DataFixtures;


use App\Entity\Arrivage;
use App\Entity\ArrivalHistory;
use App\Repository\ArrivageRepository;
use App\Repository\ArrivalHistoryRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class HistoricArrivalPatch extends Fixture
{

    /**
     * @var ArrivageRepository
     */
    private $arrivageRepository;

    /**
     * @var ArrivalHistoryRepository
     */
    private $arrivalHistoryRepository;

    /**
     * HistoricArrival constructor.
     * @param ArrivageRepository $arrivageRepository
     * @param ArrivalHistoryRepository $arrivalHistoryRepository
     */
    public function __construct(ArrivageRepository $arrivageRepository, ArrivalHistoryRepository $arrivalHistoryRepository)
    {
        $this->arrivageRepository = $arrivageRepository;
        $this->arrivalHistoryRepository = $arrivalHistoryRepository;
    }

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     * @throws \Exception
     */
    public function load(ObjectManager $manager)
    {
        $arrivages = $this->arrivageRepository->findAll();
        $arrivagesByDate = [];
        foreach ($arrivages as $arrivage) {
            $arrivagesByDate[$arrivage->getDate()->format('d-m-Y')][] = $arrivage;
        }
        foreach ($arrivagesByDate as $date => $arrivage) {
            $dateTime = new \DateTime($date, new \DateTimeZone("Europe/Paris"));
            $dateTime->setTime(0, 0);
            if (!$this->arrivalHistoryRepository->getByDate($dateTime)) {
                $arrivageWithoutLitiges = array_filter($arrivage, function ($arrivage) {
                    if ($arrivage->getStatus() === Arrivage::STATUS_CONFORME) return true;
                    return false;
                });
                $conformRate = (int)((count($arrivageWithoutLitiges) / count($arrivage)) * 100);
                $todayHistory = new ArrivalHistory();
                $todayHistory
                    ->setNumberOfArrivals(count($arrivage))
                    ->setConformRate($conformRate)
                    ->setDay($dateTime);
                $manager->persist($todayHistory);
                $manager->flush();
            }
        }
    }
}