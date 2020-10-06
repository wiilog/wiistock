<?php
/**
 * Commande Cron exécutée toute les minutes tous les jours de 7h a 19h excepté le dimanche :
 *
 */
// */1 6-18 * * 1-6
namespace App\Command;

use App\Entity\AverageRequestTime;
use App\Entity\Demande;
use App\Entity\Type;
use App\Service\AverageTimeService;
use App\Service\DashboardService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class AverageRequestTimeCommand extends Command
{
    protected static $defaultName = 'app:feed:average:demands';

    private $em;
    private $averageRequestTimeService;

    public function __construct(EntityManagerInterface $entityManager, AverageTimeService $averageTimeService)
    {
        parent::__construct(self::$defaultName);
        $this->em = $entityManager;
        $this->averageRequestTimeService = $averageTimeService;
    }

    protected function configure()
    {
        $this->setDescription('This command feeds the average request treating time.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws ORMException
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $demandeRepository = $this->getEntityManager()->getRepository(Demande::class);
        $averageTimeRepository = $this->getEntityManager()->getRepository(AverageRequestTime::class);
        $typeRepository = $this->getEntityManager()->getRepository(Type::class);

        $requests = $demandeRepository->getTreatingTimesWithType();

        $timeForType = [];
        $avgsForType = [];
        foreach ($requests as $request) {
            $treatingDate = DateTime::createFromFormat('Y-m-d H:i:s', $request['treatingDate']);
            $creationDate = DateTime::createFromFormat('Y-m-d H:i:s', $request['creationDate']);
            $typeId = $request['typeId'];

            if (!isset($timeForType[$typeId])) {
                $timeForType[$typeId] = [];
            }
            $timeForType[$typeId][] = $treatingDate->diff($creationDate);
        }

        foreach ($timeForType as $type => $times) {
            $average = 0;
            foreach ($times as $time) {
                $average += $this->averageRequestTimeService->dateIntervalToSeconds($time);
            }
            $average = (int)floor($average / count($times));
            $avgsForType[$type] = [
                'average' => $average,
                'total' => count($times)
            ];
        }

        foreach ($avgsForType as $typeId => $avgForType) {
            $type = $typeRepository->find($typeId);

            $averageTime = $averageTimeRepository->findOneBy([
                'type' => $typeId
            ]);

            if (!$averageTime) {
                $averageTime = new AverageRequestTime();
                $averageTime
                    ->setType($type);
                $this->getEntityManager()->persist($averageTime);
            }

            $averageTime
                ->setAverage($avgForType['average'])
                ->setTotal($avgForType['total']);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * @return EntityManagerInterface
     * @throws ORMException
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->em->isOpen()
            ? $this->em
            : EntityManager::Create($this->em->getConnection(), $this->em->getConfiguration());
    }
}
