<?php


namespace App\Command;


use App\Entity\Arrivage;
use App\Entity\ArrivalHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Repository\ArrivageRepository;

class HistoricArrivalCommand extends Command
{

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var ArrivageRepository
     */
    private $arrivageRepository;

    public function __construct(ArrivageRepository $arrivageRepository, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->arrivageRepository = $arrivageRepository;
        $this->entityManager = $entityManager;
    }


    protected function configure()
    {
        $this->setName('app:indicateur-arrivage');

        $this->setDescription('Enregistre l\'indicateur de l\'historique d\'arrivage du mois courant');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $todayStart = new \DateTime("now", new \DateTimeZone("Europe/Paris"));
        $todayStart->setTime(0, 0);
        $todayEnd = new \DateTime("now", new \DateTimeZone("Europe/Paris"));
        $todayEnd->setTime(23, 59);
        $arrivagesToday = $this->arrivageRepository->findByDates($todayStart, $todayEnd);
        $arrivageWithoutLitiges = array_filter($arrivagesToday, function($arrivage) {
            if ($arrivage->getStatus() === Arrivage::STATUS_CONFORME) return true;
            return false;
        });
        $conformRate = (int)((count($arrivageWithoutLitiges) / count($arrivagesToday))*100);
        $todayHistory = new ArrivalHistory();
        $todayHistory
            ->setNumberOfArrivals(count($arrivagesToday))
            ->setConformRate($conformRate)
            ->setDay($todayStart);
        $this->entityManager->persist($todayHistory);
        $this->entityManager->flush();
    }
}