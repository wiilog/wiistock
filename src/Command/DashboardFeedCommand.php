<?php
/**
 * Commande Cron exécutée toute les 10 min
 */

namespace App\Command;

use App\Entity\CategorieStatut;
use App\Entity\DashboardMeter;
use App\Entity\Import;
use App\Entity\Statut;
use App\Repository\DashboardMeterRepository;
use App\Service\DashboardService;
use App\Service\ImportService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DashboardFeedCommand extends Command
{
    protected static $defaultName = 'app:feed:dashboards';

    private $em;
    private $dashboardService;
    private $dashboardMeterRepository;

    public function __construct(EntityManagerInterface $entityManager,
                                DashboardService $dashboardService,
                                DashboardMeterRepository $dashboardMeterRepository)
    {
        parent::__construct(self::$defaultName);
        $this->em = $entityManager;
        $this->dashboardService = $dashboardService;
        $this->dashboardMeterRepository = $dashboardMeterRepository;
    }

    protected function configure()
    {
        $this->setDescription('This command feeeds the dashboard data.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dashboardMeterRepository->clearTable();
        $this->retrieveAndInsertParsedDockData();
        $this->retrieveAndInsertParsedAdminData();
        $this->retrieveAndInsertParsedPackagingData();
        $this->getEntityManager()->flush();
    }

    /**
     * @throws ORMException
     */
    private function retrieveAndInsertParsedDockData() : void {
        $dockData = $this->dashboardService->getDataForReceptionDockDashboard();
        $this->parseRetrievedDataAndPersistMeter($dockData, DashboardMeter::DASHBOARD_DOCK);
    }

    /**
     * @param $data
     * @param string $dashboard
     * @throws ORMException
     */
    private function parseRetrievedDataAndPersistMeter($data, string $dashboard): void {
        foreach ($data as $key => $datum) {
            $dashboardMeter = new DashboardMeter();
            $dashboardMeter->setMeterKey($key);
            $dashboardMeter->setDashboard($dashboard);
            if (is_array($datum)) {
                $dashboardMeter
                    ->setCount($datum['count'])
                    ->setDelay($datum['delay'])
                    ->setLabel($datum['label']);
                $this->getEntityManager()->persist($dashboardMeter);
            } else {
                $dashboardMeter->setCount(intval($datum));
                $this->getEntityManager()->persist($dashboardMeter);
            }
        }
    }

    /**
     * @throws ORMException
     */
    private function retrieveAndInsertParsedAdminData(): void {
        $adminData = $this->dashboardService->getDataForReceptionAdminDashboard();
        $this->parseRetrievedDataAndPersistMeter($adminData, DashboardMeter::DASHBOARD_ADMIN);
    }

    /**
     * @throws ORMException
     * @throws Exception
     */
    private function retrieveAndInsertParsedPackagingData(): void {
        $packagingData = $this->dashboardService->getDataForMonitoringPackagingDashboard();
        $this->parseRetrievedDataAndPersistMeter($packagingData['counters'], DashboardMeter::DASHBOARD_PACKAGING);
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
