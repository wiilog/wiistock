<?php
/**
 * Commande Cron exécutée toute les minutes tous les jours de 7h a 19h excepté le dimanche :
 *
 */

// */1 6-18 * * 1-6
namespace App\Command;

use App\Service\DashboardService;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use App\Entity\Dashboard;

class DashboardFeedCommand extends Command {

    protected static $defaultName = 'app:feed:dashboards';

    private $entityManager;
    private $dashboardService;
    private $wiilockService;

    public function __construct(EntityManagerInterface $entityManager,
                                DashboardService $dashboardService,
                                WiilockService $wiilockService) {
        parent::__construct(self::$defaultName);
        $this->entityManager = $entityManager;
        $this->dashboardService = $dashboardService;
        $this->wiilockService = $wiilockService;
    }

    protected function configure() {
        $this->setDescription('This command feeds the dashboard data.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws ORMException
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $entityManager = $this->getEntityManager();
        if($this->wiilockService->dashboardIsBeingFed($entityManager)) {
            $output->writeln("Dashboards are being fed, aborting");
            return 0;
        }

        $this->wiilockService->toggleFeedingDashboard($entityManager, true);
        $entityManager->flush();

        $dashboardComponentRepository = $entityManager->getRepository(Dashboard\Component::class);
        $components = $dashboardComponentRepository->findAll();

        foreach ($components as $component) {
            $componentType = $component->getType();
            switch ($componentType->getMeterKey()) {
                case Dashboard\ComponentType::ONGOING_PACKS:
                    $this->dashboardService->persistOngoingPack($entityManager, $component);
                    break;
                case Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS:
                    $this->dashboardService->persistDroppedPacks($entityManager, $component);
                    break;
                case Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS:
                case Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS:
                    $this->dashboardService->persistArrivalsAndPacksMeter($entityManager, $component);
                    break;
                case Dashboard\ComponentType::PACK_TO_TREAT_FROM:
                    $this->dashboardService->persistPackToTreatFrom($entityManager, $component);
                    break;
                default:
                    break;
            }
        }

        $entityManager->flush();

        $this->wiilockService->toggleFeedingDashboard($entityManager, false);
        $entityManager->flush();
    }

    /**
     * @return EntityManagerInterface
     * @throws ORMException
     */
    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : EntityManager::Create($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }

}
