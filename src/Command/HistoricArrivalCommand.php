<?php


namespace App\Command;


use App\Entity\Arrivage;
use App\Entity\ArrivalHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DateTime;

#[AsCommand(
    name: 'app:indicateur-arrivage',
    description: 'Enregistre l\'indicateur de l\'historique d\'arrivage du mois courant'
)]
class HistoricArrivalCommand extends Command
{

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);
        $arrivalHistoryRepository = $this->entityManager->getRepository(ArrivalHistory::class);

        $todayStart = new DateTime("now");
        $todayStart->setTime(0, 0);
        $todayEnd = new DateTime("now");
        $todayEnd->setTime(23, 59);
        $arrivagesToday = $arrivageRepository->findByDates($todayStart, $todayEnd);
        $arrivageWithoutLitiges = array_filter(
            $arrivagesToday,
            function (Arrivage $arrivage) {
                return $arrivage->getStatut()->isDispute();
            }
        );
        $conformRate = count($arrivagesToday) > 0
            ? (int)((count($arrivageWithoutLitiges) / count($arrivagesToday)) * 100)
            : null;
        $todayHistory = new ArrivalHistory();
        $todayHistory
            ->setNumberOfArrivals(count($arrivagesToday))
            ->setConformRate($conformRate)
            ->setDay($todayStart);
        if (!$arrivalHistoryRepository->getByDate($todayStart)) {
            $this->entityManager->persist($todayHistory);
        }
        $this->entityManager->flush();
        return 0;
    }
}
