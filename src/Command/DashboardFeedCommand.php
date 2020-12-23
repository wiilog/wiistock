<?php
/**
 * Commande Cron exécutée toute les minutes tous les jours de 7h a 19h excepté le dimanche :
 *
 */
// */1 6-18 * * 1-6
namespace App\Command;

use App\Service\DashboardService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use App\Entity\Dashboard;

class DashboardFeedCommand extends Command
{
    protected static $defaultName = 'app:feed:dashboards';

    private $em;
    private $dashboardService;

    public function __construct(EntityManagerInterface $entityManager,
                                DashboardService $dashboardService)
    {
        parent::__construct(self::$defaultName);
        $this->em = $entityManager;
        $this->dashboardService = $dashboardService;
    }

    protected function configure()
    {
        $this->setDescription('This command feeds the dashboard data.');
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
        $entityManager = $this->getEntityManager();
        $dashboardComponentRepository = $entityManager->getRepository(Dashboard\Component::class);
        $components = $dashboardComponentRepository->findAll();

        foreach ($components as $component) {
            $componentType = $component->getType();
            switch ($componentType->getMeterKey()) {
                case Dashboard\ComponentType::OUTSTANDING_PACK:
                    $this->dashboardService->persistOutstandingPack($entityManager, $component);
                    break;
                default:
                    break;
            }
        }
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
